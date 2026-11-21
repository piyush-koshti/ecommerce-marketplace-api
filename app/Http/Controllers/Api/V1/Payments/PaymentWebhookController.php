<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('marketplace.webhook_secrets.'.$provider, '');
        abort_if($secret === '', 503, 'Webhook provider is not configured.');

        $signature = (string) $request->header('X-Provider-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401, 'Invalid webhook signature.');

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string', 'max:100'],
            'payment_intent_id' => ['required', 'integer', 'exists:payment_intents,id'],
            'provider_transaction_id' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', 'in:succeeded,failed'],
        ]);

        $result = DB::transaction(function () use ($provider, $data) {
            $now = now();

            // The unique(provider, event_id) index plus insertOrIgnore makes concurrent
            // deliveries converge on one event row without a firstOrCreate race.
            DB::table('payment_webhook_events')->insertOrIgnore([
                'provider' => $provider,
                'event_id' => $data['event_id'],
                'event_type' => $data['event_type'],
                'processing_status' => 'received',
                'safe_payload' => json_encode([
                    'payment_intent_id' => $data['payment_intent_id'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $event = PaymentWebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $data['event_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->processing_status === 'processed') {
                return ['duplicate' => true];
            }

            $intent = PaymentIntent::query()->with('order')->lockForUpdate()->findOrFail($data['payment_intent_id']);
            abort_unless($intent->provider === $provider, 404);
            abort_unless(
                number_format((float) $intent->amount, 2, '.', '') === number_format((float) $data['amount'], 2, '.', ''),
                422,
                'Webhook amount does not match payment intent.'
            );

            // Do not create repeated financial transactions for distinct duplicate-state events.
            if ($intent->status !== $data['status']) {
                $intent->update(['status' => $data['status']]);

                $transaction = PaymentTransaction::create([
                    'payment_intent_id' => $intent->id,
                    'provider_transaction_id' => $data['provider_transaction_id'] ?? null,
                    'transaction_type' => $data['status'] === 'succeeded' ? 'capture' : 'failure',
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'safe_response' => ['event_id' => $data['event_id']],
                ]);

                if ($data['status'] === 'succeeded') {
                    $intent->order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
                } else {
                    $intent->order->update(['payment_status' => 'failed']);
                }
            }

            $event->update(['processing_status' => 'processed', 'processed_at' => now()]);

            return ['duplicate' => false];
        }, 3);

        return ApiResponse::success($result);
    }
}
