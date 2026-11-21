<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_disclose_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertOk()
            ->assertJsonPath(
                'data.message',
                'If an account exists for this email, a password reset link will be sent.'
            );
    }

    public function test_password_can_be_reset_with_valid_token_and_old_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $token = Password::createToken($user);
        $oldAccessToken = $user->createToken('old-device')->plainTextToken;

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPassword@123',
            'password_confirmation' => 'NewStrongPassword@123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrongPassword@123', $user->fresh()->password));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->withToken($oldAccessToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_invalid_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewStrongPassword@123',
            'password_confirmation' => 'NewStrongPassword@123',
        ])->assertUnprocessable();
    }
}
