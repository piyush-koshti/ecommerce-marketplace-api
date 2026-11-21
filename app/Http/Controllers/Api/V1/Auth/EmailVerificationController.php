<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success([
                'message' => 'Email address is already verified.',
            ]);
        }

        $url = URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        Mail::raw(
            "Verify your email address using this link:\n\n{$url}\n\nThis link expires in 60 minutes.",
            function ($message) use ($user): void {
                $message->to($user->email)
                    ->subject('Verify your email address');
            }
        );

        return ApiResponse::success([
            'message' => 'Verification email sent successfully.',
        ]);
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::error('RESOURCE_NOT_FOUND', 'User not found.', 404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error(
                'INVALID_VERIFICATION_LINK',
                'The verification link is invalid.',
                403
            );
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return ApiResponse::success([
            'message' => 'Email address verified successfully.',
            'email_verified' => true,
        ]);
    }
}
