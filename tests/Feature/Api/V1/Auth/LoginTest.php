<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_using_email(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'StrongPassword@123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'mobile'],
                    'access_token',
                    'token_type',
                ],
                'meta' => ['request_id'],
            ])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'customer-api',
        ]);
    }

    public function test_customer_can_login_using_mobile(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '9876543210',
            'password' => 'StrongPassword@123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.mobile', '9876543210')
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_login_rejects_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'WrongPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['fields' => ['login']],
            ]);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'unknown@example.com',
            'password' => 'StrongPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_login_requires_login_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['login', 'password'],
                ],
            ]);
    }
}
