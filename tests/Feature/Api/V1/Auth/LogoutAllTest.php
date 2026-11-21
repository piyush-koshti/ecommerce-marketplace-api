<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutAllTest extends TestCase
{
    use RefreshDatabase;

    private function forgetAuthGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_logout_all_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create();

        $tokens = [
            $user->createToken('device-one')->plainTextToken,
            $user->createToken('device-two')->plainTextToken,
            $user->createToken('device-three')->plainTextToken,
        ];

        $this->withToken($tokens[0])
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk()
            ->assertJsonPath(
                'data.message',
                'Logged out from all devices successfully.'
            );

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        foreach ($tokens as $token) {
            $this->forgetAuthGuards();

            $this->withToken($token)
                ->getJson('/api/v1/user')
                ->assertUnauthorized();
        }
    }

    public function test_logout_all_does_not_revoke_another_users_tokens(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tokenA = $userA->createToken('user-a-device')->plainTextToken;
        $tokenB = $userB->createToken('user-b-device')->plainTextToken;

        $this->withToken($tokenA)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $userA->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $userB->id,
        ]);

        $this->forgetAuthGuards();

        $this->withToken($tokenB)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $userB->id);
    }

    public function test_logout_all_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout-all')
            ->assertUnauthorized();
    }

    public function test_logout_all_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')
            ->postJson('/api/v1/auth/logout-all')
            ->assertUnauthorized();
    }

    public function test_logout_all_works_when_user_has_no_other_tokens(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('only-device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->forgetAuthGuards();

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    }
}
