<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->registerTestRoutes();
    }

    /**
     * Register temporary routes for middleware testing.
     */
    private function registerTestRoutes(): void
    {
        Route::get('/api/v1/__rbac-tests/admin', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware([
            'auth:sanctum',
            'role:super_admin',
        ]);

        Route::get('/api/v1/__rbac-tests/operations', function () {
            return response()->json(['message' => 'Operations access granted']);
        })->middleware([
            'auth:sanctum',
            'role:super_admin,operations_admin',
        ]);

        Route::get('/api/v1/__rbac-tests/products', function () {
            return response()->json(['message' => 'Product access granted']);
        })->middleware([
            'auth:sanctum',
            'permission:products.view',
        ]);

        Route::get('/api/v1/__rbac-tests/products-edit', function () {
            return response()->json(['message' => 'Product edit access granted']);
        })->middleware([
            'auth:sanctum',
            'permission:products.view,products.update',
        ]);

        Route::get('/api/v1/__rbac-tests/finance', function () {
            return response()->json(['message' => 'Finance access granted']);
        })->middleware([
            'auth:sanctum',
            'permission:finance.view',
        ]);
    }

    /**
     * Assign a role to a user.
     */
    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user->roles()->attach($role->id);
    }

    public function test_super_admin_can_access_admin_route(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'super_admin');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/admin')
            ->assertOk()
            ->assertJson([
                'message' => 'Admin access granted',
            ]);
    }

    public function test_customer_cannot_access_admin_route(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'customer');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/admin')
            ->assertForbidden();
    }

    public function test_user_with_any_accepted_role_can_access_route(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'operations_admin');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/operations')
            ->assertOk()
            ->assertJson([
                'message' => 'Operations access granted',
            ]);
    }

    public function test_seller_owner_can_access_product_view_route(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'seller_owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/products')
            ->assertOk()
            ->assertJson([
                'message' => 'Product access granted',
            ]);
    }

    public function test_seller_owner_cannot_access_finance_route(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'seller_owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/finance')
            ->assertForbidden();
    }

    public function test_user_must_have_all_required_permissions(): void
    {
        $user = User::factory()->create();

        $this->assignRole($user, 'seller_owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/__rbac-tests/products-edit')
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $this->getJson('/api/v1/__rbac-tests/admin')
            ->assertUnauthorized();
    }
}
