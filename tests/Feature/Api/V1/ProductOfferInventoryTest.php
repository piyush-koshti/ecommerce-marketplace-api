<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerAccount;
use App\Models\SellerOffer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductOfferInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approvedSeller(): array
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'seller_owner')->value('id'));
        $seller = SellerAccount::create([
            'legal_business_name' => 'Test Seller Pvt Ltd',
            'display_name' => 'Test Seller',
            'slug' => 'test-seller-'.$user->id,
            'status' => 'approved',
        ]);
        $seller->users()->attach($user->id, [
            'membership_role' => 'owner', 'status' => 'active', 'assigned_by' => $user->id,
        ]);

        return [$user, $seller];
    }

    public function test_seller_can_create_product_and_submit_for_review(): void
    {
        [$user, $seller] = $this->approvedSeller();
        Sanctum::actingAs($user);

        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $response = $this->postJson("/api/v1/seller/accounts/{$seller->id}/products", [
            'category_id' => $category->id,
            'name' => 'Laravel Guide',
            'slug' => 'laravel-guide',
            'variants' => [['name' => 'Paperback', 'attribute_values' => ['format' => 'paperback']]],
        ])->assertCreated();

        $productId = $response->json('data.id');
        $this->postJson("/api/v1/seller/accounts/{$seller->id}/products/{$productId}/submit")
            ->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_seller_cannot_read_another_sellers_product(): void
    {
        [$ownerA, $sellerA] = $this->approvedSeller();
        [$ownerB, $sellerB] = $this->approvedSeller();

        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $product = Product::create([
            'created_by_seller_account_id' => $sellerA->id,
            'category_id' => $category->id,
            'created_by' => $ownerA->id,
            'name' => 'Private Product',
            'slug' => 'private-product',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($ownerB);
        $this->getJson("/api/v1/seller/accounts/{$sellerB->id}/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_only_admin_can_approve_a_submitted_product(): void
    {
        [$owner, $seller] = $this->approvedSeller();
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $product = Product::create([
            'created_by_seller_account_id' => $seller->id,
            'category_id' => $category->id,
            'created_by' => $owner->id,
            'name' => 'Review Product',
            'slug' => 'review-product',
            'status' => 'pending_review',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/admin/products/{$product->id}/approve")->assertForbidden();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'operations_admin')->value('id'));
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/products/{$product->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_seller_offer_starts_draft_and_inventory_adjustment_is_ledgered(): void
    {
        [$user, $seller] = $this->approvedSeller();
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $product = Product::create([
            'created_by_seller_account_id' => $seller->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'name' => 'Inventory Product',
            'slug' => 'inventory-product',
            'status' => 'approved',
        ]);
        $variant = $product->variants()->create(['name' => 'Standard']);
        Sanctum::actingAs($user);

        $offerResponse = $this->postJson("/api/v1/seller/accounts/{$seller->id}/offers", [
            'product_variant_id' => $variant->id,
            'seller_sku' => 'SELLER-BOOK-001',
            'condition' => 'new',
            'price' => '499.00',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $offerId = $offerResponse->json('data.id');
        $this->postJson("/api/v1/seller/accounts/{$seller->id}/offers/{$offerId}/activate")->assertOk();

        $this->postJson("/api/v1/seller/accounts/{$seller->id}/offers/{$offerId}/inventory/adjust", [
            'quantity_change' => 10,
            'reason' => 'Opening stock',
        ])->assertOk()->assertJsonPath('data.quantity_on_hand', 10);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'stock_in',
            'quantity_before' => 0,
            'quantity_after' => 10,
        ]);
    }

    public function test_inventory_cannot_be_reduced_below_reserved_quantity(): void
    {
        [$user, $seller] = $this->approvedSeller();
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $product = Product::create([
            'created_by_seller_account_id' => $seller->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'name' => 'Reserved Product',
            'slug' => 'reserved-product',
            'status' => 'approved',
        ]);
        $variant = $product->variants()->create(['name' => 'Standard']);
        $offer = SellerOffer::create([
            'seller_account_id' => $seller->id,
            'product_variant_id' => $variant->id,
            'seller_sku' => 'RESERVED-001',
            'condition' => 'new',
            'price' => 100,
            'status' => 'active',
        ]);
        $inventory = $offer->inventory()->create([
            'quantity_on_hand' => 5, 'quantity_reserved' => 3,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/seller/accounts/{$seller->id}/offers/{$offer->id}/inventory/adjust", [
            'quantity_change' => -3,
            'reason' => 'Incorrect count',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('inventory_items', ['id' => $inventory->id, 'quantity_on_hand' => 5]);
    }
}
