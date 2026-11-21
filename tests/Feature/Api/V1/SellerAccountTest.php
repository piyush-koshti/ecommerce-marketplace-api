<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\SellerAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_create_seller_account_and_become_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/seller/accounts', [
            'legal_business_name' => 'Example Retail Private Limited',
            'display_name' => 'Example Retail',
            'contact_email' => 'seller@example.test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_review');

        $this->assertDatabaseHas('seller_users', [
            'seller_account_id' => $response->json('data.id'),
            'user_id' => $user->id,
            'membership_role' => 'owner',
            'status' => 'active',
        ]);

        $this->assertTrue($user->fresh()->hasRole('seller_owner'));
    }

    public function test_seller_owner_can_only_view_own_seller_account(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $seller = SellerAccount::create([
            'legal_business_name' => 'Owner Seller',
            'slug' => 'owner-seller',
            'status' => 'pending_review',
        ]);

        $seller->users()->attach($owner->id, [
            'membership_role' => 'owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/seller/accounts/'.$seller->id)
            ->assertNotFound();
    }

    public function test_seller_owner_can_add_staff_member_to_own_account(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $seller = SellerAccount::create([
            'legal_business_name' => 'Staff Seller',
            'slug' => 'staff-seller',
            'status' => 'pending_review',
        ]);

        $seller->users()->attach($owner->id, [
            'membership_role' => 'owner',
            'status' => 'active',
        ]);
        $owner->roles()->attach(Role::where('name', 'seller_owner')->value('id'));

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/seller/accounts/'.$seller->id.'/staff', [
            'user_id' => $staff->id,
        ])->assertCreated();

        $this->assertDatabaseHas('seller_users', [
            'seller_account_id' => $seller->id,
            'user_id' => $staff->id,
            'membership_role' => 'staff',
            'status' => 'active',
        ]);

        $this->assertTrue($staff->fresh()->hasRole('seller_staff'));
    }

    public function test_non_owner_cannot_add_staff_to_seller_account(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();
        $seller = SellerAccount::create([
            'legal_business_name' => 'Protected Seller',
            'slug' => 'protected-seller',
            'status' => 'pending_review',
        ]);

        $seller->users()->attach($user->id, [
            'membership_role' => 'staff',
            'status' => 'active',
        ]);
        $user->roles()->attach(Role::where('name', 'seller_owner')->value('id'));

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/seller/accounts/'.$seller->id.'/staff', [
            'user_id' => $staff->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('seller_users', [
            'seller_account_id' => $seller->id,
            'user_id' => $staff->id,
        ]);
    }
}
