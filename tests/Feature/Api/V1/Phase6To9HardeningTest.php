<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use App\Services\Inventory\StockReservationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6To9HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function inventoryItem()
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'seller_owner')->value('id'));

        $seller = SellerAccount::create([
            'legal_business_name' => 'Hardening Test Seller Pvt Ltd',
            'display_name' => 'Hardening Test Seller',
            'slug' => 'hardening-test-seller-'.$owner->id,
            'status' => 'approved',
        ]);

        $seller->users()->attach($owner->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'assigned_by' => $owner->id,
        ]);

        $category = Category::create([
            'name' => 'Hardening Category '.$owner->id,
            'slug' => 'hardening-category-'.$owner->id,
        ]);

        $product = Product::create([
            'created_by_seller_account_id' => $seller->id,
            'category_id' => $category->id,
            'created_by' => $owner->id,
            'name' => 'Hardening Product '.$owner->id,
            'slug' => 'hardening-product-'.$owner->id,
            'status' => 'approved',
        ]);

        $variant = $product->variants()->create(['name' => 'Standard']);

        $offer = SellerOffer::create([
            'seller_account_id' => $seller->id,
            'product_variant_id' => $variant->id,
            'seller_sku' => 'HARDENING-'.$owner->id,
            'condition' => 'new',
            'price' => '100.00',
            'status' => 'active',
        ]);

        return $offer->inventory()->create(['quantity_on_hand' => 10]);
    }

    public function test_committed_stock_can_be_reversed_only_once(): void
    {
        $inventory = $this->inventoryItem();
        $service = app(StockReservationService::class);

        // Stock movements require a valid actor ID.
        $actor = User::factory()->create();

        // Reserve 3 units and commit them.
        $reservation = $service->reserve(
            $inventory,
            'seller_order',
            'SO-HARDEN-1',
            3,
            15
        );

        $service->commit($reservation, $actor->id);

        // Verify stock after commitment.
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
        ]);

        // Reverse the committed reservation.
        $reservation->refresh();

        $service->reverseCommitted($reservation, $actor->id);

        // Attempt a second reversal; it must not restore stock again.
        $service->reverseCommitted($reservation->fresh(), $actor->id);

        // Verify stock was restored exactly once.
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->assertDatabaseHas('stock_reservations', [
            'id' => $reservation->id,
            'status' => 'reversed',
        ]);

        // One order_commit + one order_cancel_restore movement.
        $this->assertDatabaseCount('stock_movements', 2);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $inventory->id,
            'actor_id' => $actor->id,
            'movement_type' => 'order_cancel_restore',
            'quantity_before' => 7,
            'quantity_after' => 10,
        ]);
    }

    public function test_releasing_a_reservation_more_than_once_does_not_change_stock_twice(): void
    {
        $inventory = $this->inventoryItem();
        $service = app(StockReservationService::class);

        $reservation = $service->reserve($inventory, 'seller_order', 'SO-HARDEN-2', 4, 15);
        $service->release($reservation);
        $service->release($reservation->fresh());

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_reservations', [
            'id' => $reservation->id,
            'status' => 'released',
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
