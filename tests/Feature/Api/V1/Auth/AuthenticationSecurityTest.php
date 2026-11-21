<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_endpoint_rejects_missing_token(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_protected_endpoint_rejects_invalid_token(): void
    {
        $this->withToken('this-is-not-a-valid-token')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_protected_endpoint_rejects_malformed_bearer_token(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer',
        ])->getJson('/api/v1/user')
            ->assertUnauthorized();
    }

    public function test_user_token_only_authenticates_its_owner(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tokenA = $userA->createToken('user-a-device')->plainTextToken;

        $response = $this->withToken($tokenA)
            ->getJson('/api/v1/user');

        $response->assertOk()
            ->assertJsonPath('data.id', $userA->id)
            ->assertJsonMissingPath('data.password');

        $this->assertNotEquals(
            $userB->id,
            $response->json('data.id')
        );
    }

    public function test_health_endpoint_remains_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_login_response_does_not_expose_password(): void
    {
        User::factory()->create([
            'email' => 'customer@example.com',
            'mobile' => '9876543210',
            'password' => 'StrongPassword@123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@example.com',
            'password' => 'StrongPassword@123',
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
    }
}
