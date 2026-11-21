<?php

namespace Tests\Feature\Api\V1;

use App\Models\Address;
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

class ShoppingCheckoutOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function purchasableOffer(): array
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'seller_owner')->value('id'));
        $seller = SellerAccount::create([
            'legal_business_name' => 'Cart Seller Pvt Ltd',
            'display_name' => 'Cart Seller',
            'slug' => 'cart-seller-'.$owner->id,
            'status' => 'approved',
        ]);
        $seller->users()->attach($owner->id, ['membership_role' => 'owner', 'status' => 'active', 'assigned_by' => $owner->id]);
        $category = Category::create(['name' => 'Cart Category', 'slug' => 'cart-category-'.$owner->id]);
        $product = Product::create([
            'created_by_seller_account_id' => $seller->id, 'category_id' => $category->id,
            'created_by' => $owner->id, 'name' => 'Cart Product', 'slug' => 'cart-product-'.$owner->id, 'status' => 'approved',
        ]);
        $variant = $product->variants()->create(['name' => 'Standard']);
        $offer = SellerOffer::create([
            'seller_account_id' => $seller->id, 'product_variant_id' => $variant->id,
            'seller_sku' => 'CART-SKU-'.$owner->id, 'condition' => 'new', 'price' => '125.50', 'status' => 'active',
        ]);
        $offer->inventory()->create(['quantity_on_hand' => 20]);

        return [$owner, $seller, $product, $offer];
    }

    public function test_guest_cart_token_is_returned_and_cart_is_token_scoped(): void
    {
        [,,$product,$offer] = $this->purchasableOffer();
        $response = $this->postJson('/api/v1/cart/items', [
            'seller_offer_id' => $offer->id, 'quantity' => 2,
        ])->assertCreated();

        $token = $response->headers->get('X-Guest-Cart-Token');
        $this->assertNotEmpty($token);
        $this->withHeader('X-Guest-Cart-Token', $token)->getJson('/api/v1/cart')
            ->assertOk()->assertJsonPath('data.items.0.quantity', 2);

        $this->withHeader('X-Guest-Cart-Token', str_repeat('x', 48))->getJson('/api/v1/cart')
            ->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_customer_cart_and_wishlist_are_private_to_authenticated_user(): void
    {
        [$owner,,$product,$offer] = $this->purchasableOffer();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/cart/items', ['seller_offer_id' => $offer->id, 'quantity' => 1])->assertCreated();
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items.0.quantity', 1);
        $this->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])->assertCreated();

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items', []);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_checkout_quote_uses_server_offer_price_and_customer_owned_address(): void
    {
        [$customer,,$product,$offer] = $this->purchasableOffer();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/cart/items', ['seller_offer_id' => $offer->id, 'quantity' => 2])->assertCreated();
        $address = Address::create([
            'user_id' => $customer->id, 'recipient_name' => 'Test Customer', 'mobile' => '9876543210',
            'address_line_1' => 'Test Road', 'city' => 'Ahmedabad', 'state' => 'Gujarat',
            'country_code' => 'IN', 'postal_code' => '380001',
        ]);
        $quote = $this->postJson('/api/v1/checkout/quote', [
            'address_id' => $address->id, 'price' => 1, 'grand_total' => 1,
        ])->assertCreated()->assertJsonPath('data.subtotal', '251.00');
        $this->assertDatabaseHas('checkout_sessions', ['id' => $quote->json('data.id'), 'grand_total' => '251.00']);
    }
}
