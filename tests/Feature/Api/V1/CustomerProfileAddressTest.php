<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProfileAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_retrieve_own_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/customer/profile', [
            'first_name' => 'Asha',
            'last_name' => 'Patel',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Asha');

        $this->getJson('/api/v1/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Patel');
    }

    public function test_customer_can_create_address_and_set_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/customer/addresses', $this->addressPayload())
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_customer_cannot_view_another_users_address(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = $owner->addresses()->create($this->addressPayload());

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/customer/addresses/'.$address->id)
            ->assertNotFound();
    }

    public function test_setting_new_default_address_unsets_previous_default(): void
    {
        $user = User::factory()->create();
        $first = $user->addresses()->create($this->addressPayload());
        Sanctum::actingAs($user);

        $payload = $this->addressPayload();
        $payload['is_default'] = true;

        $this->postJson('/api/v1/customer/addresses', $payload)->assertCreated();

        $this->assertDatabaseHas('addresses', [
            'id' => $first->id,
            'is_default' => false,
        ]);
    }

    private function addressPayload(): array
    {
        return [
            'label' => 'home',
            'recipient_name' => 'Test Customer',
            'mobile' => '9876543210',
            'address_line_1' => '10 Test Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country_code' => 'IN',
            'postal_code' => '380001',
            'is_default' => true,
        ];
    }
}
