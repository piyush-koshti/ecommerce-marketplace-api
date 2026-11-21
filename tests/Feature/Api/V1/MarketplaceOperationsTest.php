<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ImportSellerInventory;
use App\Models\Category;
use App\Models\DataOperation;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_seller_can_queue_inventory_import_and_operation_is_owner_scoped(): void
    {
        Queue::fake();
        [$owner, $seller] = $this->approvedSeller();
        Sanctum::actingAs($owner);

        $response = $this->post("/api/v1/seller/accounts/{$seller->id}/inventory/import", [
            'file' => UploadedFile::fake()->createWithContent(
                'inventory.csv',
                "seller_sku,quantity_on_hand,reason\nSKU-100,12,Opening count\n"
            ),
        ], ['Accept' => 'application/json'])->assertAccepted();

        $operationId = $response->json('data.id');

        $this->assertDatabaseHas('data_operations', [
            'id' => $operationId,
            'user_id' => $owner->id,
            'seller_account_id' => $seller->id,
            'operation_type' => 'inventory_import',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ImportSellerInventory::class);

        [$otherUser, $otherSeller] = $this->approvedSeller();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/seller/accounts/{$otherSeller->id}/data-operations/{$operationId}")
            ->assertNotFound();
    }

    public function test_inventory_import_updates_stock_and_writes_a_movement(): void
    {
        [$owner, $seller, $offer, $inventory] = $this->sellerWithInventory();
        $path = UploadedFile::fake()->createWithContent(
            'inventory.csv',
            "seller_sku,quantity_on_hand,reason\nSKU-100,12,Verified stock count\n"
        )->store('marketplace/imports', 'local');

        $operation = DataOperation::create([
            'user_id' => $owner->id,
            'seller_account_id' => $seller->id,
            'operation_type' => 'inventory_import',
            'status' => 'queued',
            'source_path' => $path,
        ]);

        (new ImportSellerInventory($operation->id))->handle();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'quantity_on_hand' => 12,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $inventory->id,
            'actor_id' => $owner->id,
            'movement_type' => 'stock_import_adjustment',
            'quantity_before' => 10,
            'quantity_after' => 12,
        ]);
        $this->assertDatabaseHas('data_operations', [
            'id' => $operation->id,
            'status' => 'completed',
        ]);
    }

    public function test_inventory_import_is_atomic_when_any_sku_is_invalid(): void
    {
        [$owner, $seller, $offer, $inventory] = $this->sellerWithInventory();
        $path = UploadedFile::fake()->createWithContent(
            'inventory.csv',
            "seller_sku,quantity_on_hand,reason\nSKU-100,20,First row\nUNKNOWN-999,5,Invalid row\n"
        )->store('marketplace/imports', 'local');

        $operation = DataOperation::create([
            'user_id' => $owner->id,
            'seller_account_id' => $seller->id,
            'operation_type' => 'inventory_import',
            'status' => 'queued',
            'source_path' => $path,
        ]);

        (new ImportSellerInventory($operation->id))->handle();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'quantity_on_hand' => 10,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('data_operations', [
            'id' => $operation->id,
            'status' => 'failed',
        ]);
    }

    public function test_inventory_adjustment_notifies_active_seller_members_when_stock_crosses_threshold(): void
    {
        Notification::fake();
        [$owner, $seller, $offer] = $this->sellerWithInventory();
        $offer->inventory()->update(['low_stock_threshold' => 3]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/seller/accounts/{$seller->id}/offers/{$offer->id}/inventory/adjust", [
            'quantity_change' => -8,
            'reason' => 'Cycle count',
        ])->assertOk();

        Notification::assertSentTo($owner, LowStockNotification::class);
    }

    private function approvedSeller(): array
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'seller_owner')->value('id'));

        $seller = SellerAccount::create([
            'legal_business_name' => 'Operations Test Pvt Ltd',
            'display_name' => 'Operations Test',
            'slug' => 'operations-test-'.$user->id,
            'status' => 'approved',
        ]);

        $seller->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'assigned_by' => $user->id,
        ]);

        return [$user, $seller];
    }

    private function sellerWithInventory(): array
    {
        [$user, $seller] = $this->approvedSeller();
        $category = Category::create(['name' => 'Operations', 'slug' => 'operations-'.$user->id]);
        $product = Product::create([
            'created_by_seller_account_id' => $seller->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'name' => 'Operations Product',
            'slug' => 'operations-product-'.$user->id,
            'status' => 'approved',
        ]);
        $variant = $product->variants()->create(['name' => 'Standard']);
        $offer = SellerOffer::create([
            'seller_account_id' => $seller->id,
            'product_variant_id' => $variant->id,
            'seller_sku' => 'SKU-100',
            'condition' => 'new',
            'price' => 100,
            'status' => 'active',
        ]);
        $inventory = $offer->inventory()->create([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
            'low_stock_threshold' => 3,
        ]);

        return [$user, $seller, $offer, $inventory];
    }
}
