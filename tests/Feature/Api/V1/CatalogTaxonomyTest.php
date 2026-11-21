<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_operations_admin_can_create_category(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'operations_admin')->value('id'));
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'electronics');
    }

    public function test_category_cannot_be_assigned_as_its_own_ancestor(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'operations_admin')->value('id'));
        Sanctum::actingAs($user);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->putJson('/api/v1/admin/categories/'.$category->id, [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'parent_id' => $category->id,
        ])->assertUnprocessable();
    }

    public function test_public_catalog_only_returns_active_brands(): void
    {
        Brand::create(['name' => 'Active Brand', 'slug' => 'active-brand', 'is_active' => true]);
        Brand::create(['name' => 'Inactive Brand', 'slug' => 'inactive-brand', 'is_active' => false]);

        $this->getJson('/api/v1/catalog/brands')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'active-brand'])
            ->assertJsonMissing(['slug' => 'inactive-brand']);
    }

    public function test_customer_cannot_create_admin_category(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'customer')->value('id'));
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Restricted',
            'slug' => 'restricted',
        ])->assertForbidden();
    }
}
