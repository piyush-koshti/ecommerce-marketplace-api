<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\EmailOtp;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_can_be_sent_to_unverified_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/auth/otp/send', ['email' => $user->email])
            ->assertOk();

        $this->assertDatabaseHas('email_otps', ['email' => $user->email]);

        Notification::assertSentTo($user, EmailOtpNotification::class);
    }

    public function test_valid_otp_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $otp = '123456';

        EmailOtp::create([
            'email' => $user->email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertOk()->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull(EmailOtp::where('email', $user->email)->first()->consumed_at);
    }

    public function test_invalid_otp_is_rejected_without_verifying_email(): void
    {
        $user = User::factory()->unverified()->create();

        EmailOtp::create([
            'email' => $user->email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => $user->email,
            'otp' => '654321',
        ])->assertUnprocessable();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        EmailOtp::create([
            'email' => $user->email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => $user->email,
            'otp' => '123456',
        ])->assertUnprocessable();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_otp_cannot_be_reused(): void
    {
        $user = User::factory()->unverified()->create();

        EmailOtp::create([
            'email' => $user->email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = ['email' => $user->email, 'otp' => '123456'];

        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertUnprocessable();
    }
}
