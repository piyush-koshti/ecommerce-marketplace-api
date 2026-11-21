<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function forgetAuthGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();

        $firstToken = $user->createToken('device-one')->plainTextToken;
        $secondToken = $user->createToken('device-two')->plainTextToken;

        $this->withToken($firstToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'device-one',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'device-two',
        ]);

        $this->forgetAuthGuards();

        $this->withToken($firstToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->forgetAuthGuards();

        $this->withToken($secondToken)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_logout_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }
}
