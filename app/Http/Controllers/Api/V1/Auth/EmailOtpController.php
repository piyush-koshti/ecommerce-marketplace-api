<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmailOtpController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $key = 'email-otp:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return ApiResponse::error(
                'RATE_LIMITED',
                'Please wait before requesting another OTP.',
                429
            );
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $email)->first();

        // Do not reveal whether an email exists or is already verified.
        if (! $user || $user->hasVerifiedEmail()) {
            return ApiResponse::success([
                'message' => 'If the account is eligible, an OTP will be sent.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        DB::transaction(function () use ($email, $otp): void {
            EmailOtp::where('email', $email)->delete();

            EmailOtp::create([
                'email' => $email,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        $user->notify(new EmailOtpNotification($otp));

        return ApiResponse::success([
            'message' => 'If the account is eligible, an OTP will be sent.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = Str::lower(trim($validated['email']));

        $result = DB::transaction(function () use ($email, $validated): string {
            $record = EmailOtp::where('email', $email)
                ->lockForUpdate()
                ->first();

            if (
                ! $record ||
                $record->consumed_at ||
                $record->expires_at->isPast() ||
                $record->attempts >= 5
            ) {
                return 'invalid';
            }

            if (! Hash::check($validated['otp'], $record->otp_hash)) {
                $record->increment('attempts');

                return 'invalid';
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                return 'invalid';
            }

            $record->update(['consumed_at' => now()]);
            $user->markEmailAsVerified();

            return 'verified';
        });

        if ($result !== 'verified') {
            return ApiResponse::error(
                'INVALID_OR_EXPIRED_OTP',
                'The OTP is invalid, expired or has already been used.',
                422
            );
        }

        return ApiResponse::success([
            'message' => 'Email OTP verified successfully.',
            'email_verified' => true,
        ]);
    }
}
