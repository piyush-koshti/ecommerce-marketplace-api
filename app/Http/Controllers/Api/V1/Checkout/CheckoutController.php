<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\SellerOffer;
use App\Models\SellerOrder;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Inventory\StockReservationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function quote(Request $request, CheckoutQuoteService $quotes): JsonResponse
    {
        $data = $request->validate(['address_id' => ['required', 'integer', 'exists:addresses,id']]);
        $address = Address::where('user_id', $request->user()->id)->findOrFail($data['address_id']);
        $cart = Cart::where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();

        return ApiResponse::success($quotes->create($cart, $request->user()->id, $address->id), 201);
    }

    public function place(Request $request, CheckoutSession $checkout, StockReservationService $reservations): JsonResponse
    {
        abort_unless((int) $checkout->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['payment_method' => ['required', 'in:online,cod']]);
        abort_unless($checkout->status === 'quoted' && $checkout->expires_at->isFuture(), 409, 'Checkout quote has expired or was already used.');
        if ($data['payment_method'] === 'online') {
            return ApiResponse::error('PAYMENT_PROVIDER_NOT_CONFIGURED', 'Configure and approve a payment provider adapter before accepting online payments.', 503);
        }
        abort_unless((bool) config('marketplace.cod_enabled', false), 422, 'Cash on delivery is not enabled by marketplace policy.');

        $order = DB::transaction(function () use ($checkout, $request, $data, $reservations) {
            $locked = CheckoutSession::with('items')->lockForUpdate()->findOrFail($checkout->id);
            abort_unless($locked->status === 'quoted' && $locked->expires_at->isFuture(), 409);
            $address = Address::where('user_id', $request->user()->id)->findOrFail($locked->address_id);
            $order = Order::create([
                'order_number' => 'ORD-'.Str::upper(Str::random(12)), 'user_id' => $request->user()->id,
                'checkout_session_id' => $locked->id, 'status' => 'confirmed', 'payment_status' => 'pending',
                'payment_method' => $data['payment_method'], 'currency' => $locked->currency,
                'subtotal' => $locked->subtotal, 'discount_total' => $locked->discount_total,
                'shipping_total' => $locked->shipping_total, 'tax_total' => $locked->tax_total,
                'grand_total' => $locked->grand_total, 'shipping_address_snapshot' => $address->only([
                    'recipient_name', 'mobile', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state', 'country_code', 'postal_code',
                ]), 'placed_at' => now(),
            ]);
            $groups = [];
            foreach ($locked->items as $item) {
                $offer = SellerOffer::with(['inventory', 'variant.product', 'sellerAccount'])->findOrFail($item->seller_offer_id);
                abort_unless(
                    $offer->status === 'active'
                    && $offer->sellerAccount?->status === 'approved'
                    && $offer->variant?->is_active
                    && $offer->variant?->product?->status === 'approved',
                    409,
                    'An item in this checkout is no longer available.'
                );
                abort_unless(
                    number_format((float) $offer->price, 2, '.', '') === number_format((float) $item->unit_price, 2, '.', ''),
                    409,
                    'An item price changed. Please request a new checkout quote.'
                );
                $key = $offer->seller_account_id;
                if (! isset($groups[$key])) {
                    $groups[$key] = SellerOrder::create([
                        'order_id' => $order->id, 'seller_account_id' => $key,
                        'seller_order_number' => $order->order_number.'-S'.$key, 'status' => 'confirmed',
                    ]);
                }
                abort_unless($offer->inventory, 409, 'Inventory record is no longer available.');
                $reservation = $reservations->reserve($offer->inventory, 'seller_order', (string) $groups[$key]->id, (int) $item->quantity, max(1, (int) config('marketplace.stock_reservation_minutes', 30)));
                $reservations->commit($reservation, $request->user()->id);
                $groups[$key]->items()->create([
                    'seller_offer_id' => $offer->id, 'product_variant_id' => $offer->product_variant_id,
                    'product_name' => $item->product_name, 'variant_name' => $item->variant_name,
                    'seller_name' => $item->seller_name, 'seller_sku' => $item->seller_sku,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total,
                ]);
            }
            $order->histories()->create(['actor_id' => $request->user()->id, 'from_status' => null, 'to_status' => 'confirmed', 'source' => 'customer', 'reason' => 'Order placed with COD acceptance.']);
            $locked->update(['status' => 'ordered']);
            Cart::whereKey($locked->cart_id)->update(['status' => 'converted']);

            return $order->load(['sellerOrders.items']);
        }, 3);

        return ApiResponse::success($order,201);
    }
}
