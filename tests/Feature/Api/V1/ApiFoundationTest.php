<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_health_endpoint_returns_standard_success_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'application',
                ],
                'meta' => [
                    'request_id',
                ],
            ]);

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('meta.request_id')
        );
    }

    public function test_protected_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertUnauthorized()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields',
                    'request_id',
                ],
            ])
            ->assertJsonPath(
                'error.code',
                'UNAUTHENTICATED'
            );
    }

    public function test_unknown_api_endpoint_returns_standard_error(): void
    {
        $response = $this->getJson('/api/v1/unknown-endpoint');

        $response->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'HTTP_ERROR'
            )
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields',
                    'request_id',
                ],
            ]);
    }

    public function test_api_rate_limit_returns_429_after_sixty_requests(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/health')->assertOk();
        }

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(429)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'fields',
                    'request_id',
                ],
            ]);
    }
}
