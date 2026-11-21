<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
            'password_confirmation' => 'StrongPassword@123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'mobile',
                    ],
                    'access_token',
                    'token_type',
                ],
                'meta' => ['request_id'],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
        ]);

        $user = User::where(
            'email',
            'customer@example.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check('StrongPassword@123', $user->password)
        );

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'customer-api',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Customer',
            'email' => 'customer@example.com',
            'mobile' => '9876543211',
            'password' => 'StrongPassword@123',
            'password_confirmation' => 'StrongPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['email'],
                ],
            ]);
    }

    public function test_registration_rejects_duplicate_mobile(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Customer',
            'email' => 'another@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
            'password_confirmation' => 'StrongPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['mobile'],
                ],
            ]);
    }

    public function test_registration_rejects_invalid_mobile(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'mobile' => '12345',
            'password' => 'StrongPassword@123',
            'password_confirmation' => 'StrongPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['mobile'],
                ],
            ]);
    }

    public function test_registration_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
            'password_confirmation' => 'DifferentPassword@123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'fields' => ['password'],
                ],
            ]);
    }
}
