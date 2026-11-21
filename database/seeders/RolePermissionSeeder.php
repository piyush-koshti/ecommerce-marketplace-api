<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'customer',
                'group_key' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Customer account and shopping access.',
            ],
            [
                'name' => 'seller_owner',
                'group_key' => 'seller',
                'display_name' => 'Seller Owner',
                'description' => 'Manage seller organisation and store operations.',
            ],
            [
                'name' => 'seller_staff',
                'group_key' => 'seller',
                'display_name' => 'Seller Staff',
                'description' => 'Perform tasks assigned by seller owner.',
            ],
            [
                'name' => 'super_admin',
                'group_key' => 'admin',
                'display_name' => 'Super Admin',
                'description' => 'Global configuration and privileged approvals.',
            ],
            [
                'name' => 'operations_admin',
                'group_key' => 'admin',
                'display_name' => 'Operations Admin',
                'description' => 'Seller, catalogue, order and customer operations.',
            ],
            [
                'name' => 'finance_admin',
                'group_key' => 'admin',
                'display_name' => 'Finance Admin',
                'description' => 'Transactions, reconciliation and seller settlements.',
            ],
            [
                'name' => 'support_agent',
                'group_key' => 'staff',
                'display_name' => 'Support Agent',
                'description' => 'Customer support and permitted order context.',
            ],
            [
                'name' => 'warehouse_fulfilment',
                'group_key' => 'staff',
                'display_name' => 'Warehouse / Fulfilment',
                'description' => 'Warehouse, stock and fulfilment operations.',
            ],
            [
                'name' => 'auditor_analyst',
                'group_key' => 'staff',
                'display_name' => 'Auditor / Analyst',
                'description' => 'Read-only reports and audit history.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    ...$role,
                    'is_system' => true,
                ]
            );
        }

        $permissions = [
            ['name' => 'customers.view', 'module' => 'customers', 'display_name' => 'View Customers'],
            ['name' => 'customers.update', 'module' => 'customers', 'display_name' => 'Update Customers'],

            ['name' => 'sellers.view', 'module' => 'sellers', 'display_name' => 'View Sellers'],
            ['name' => 'sellers.approve', 'module' => 'sellers', 'display_name' => 'Approve Sellers'],
            ['name' => 'sellers.suspend', 'module' => 'sellers', 'display_name' => 'Suspend Sellers'],

            ['name' => 'products.view', 'module' => 'products', 'display_name' => 'View Products'],
            ['name' => 'products.create', 'module' => 'products', 'display_name' => 'Create Products'],
            ['name' => 'products.update', 'module' => 'products', 'display_name' => 'Update Products'],
            ['name' => 'products.approve', 'module' => 'products', 'display_name' => 'Approve Products'],

            ['name' => 'orders.view', 'module' => 'orders', 'display_name' => 'View Orders'],
            ['name' => 'orders.manage', 'module' => 'orders', 'display_name' => 'Manage Orders'],

            ['name' => 'inventory.view', 'module' => 'inventory', 'display_name' => 'View Inventory'],
            ['name' => 'inventory.manage', 'module' => 'inventory', 'display_name' => 'Manage Inventory'],

            ['name' => 'finance.view', 'module' => 'finance', 'display_name' => 'View Finance'],
            ['name' => 'finance.reconcile', 'module' => 'finance', 'display_name' => 'Reconcile Transactions'],

            ['name' => 'payouts.view', 'module' => 'payouts', 'display_name' => 'View Payouts'],
            ['name' => 'payouts.approve', 'module' => 'payouts', 'display_name' => 'Approve Payouts'],

            ['name' => 'support.view', 'module' => 'support', 'display_name' => 'View Support Tickets'],
            ['name' => 'support.manage', 'module' => 'support', 'display_name' => 'Manage Support Tickets'],

            ['name' => 'reports.view', 'module' => 'reports', 'display_name' => 'View Reports'],
            ['name' => 'audit_logs.view', 'module' => 'audit_logs', 'display_name' => 'View Audit Logs'],

            ['name' => 'roles.view', 'module' => 'roles', 'display_name' => 'View Roles'],
            ['name' => 'roles.manage', 'module' => 'roles', 'display_name' => 'Manage Roles'],

            ['name' => 'permissions.view', 'module' => 'permissions', 'display_name' => 'View Permissions'],
            ['name' => 'permissions.manage', 'module' => 'permissions', 'display_name' => 'Manage Permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        /*
         * Role-permission assignments.
         *
         * Customer actions are protected by ownership checks.
         * Seller staff permissions will be assigned after
         * implementing seller-owner-controlled staff access.
         */
        $rolePermissions = [
            'customer' => [],

            'seller_owner' => [
                'products.view',
                'products.create',
                'products.update',
                'orders.view',
                'orders.manage',
                'inventory.view',
                'inventory.manage',
            ],

            'seller_staff' => [],

            'super_admin' => Permission::pluck('name')->all(),

            'operations_admin' => [
                'customers.view',
                'customers.update',
                'sellers.view',
                'sellers.approve',
                'sellers.suspend',
                'products.view',
                'products.create',
                'products.update',
                'products.approve',
                'orders.view',
                'orders.manage',
            ],

            'finance_admin' => [
                'finance.view',
                'finance.reconcile',
                'payouts.view',
            ],

            'support_agent' => [
                'customers.view',
                'orders.view',
                'support.view',
                'support.manage',
            ],

            'warehouse_fulfilment' => [
                'orders.view',
                'orders.manage',
                'inventory.view',
                'inventory.manage',
            ],

            'auditor_analyst' => [
                'reports.view',
                'audit_logs.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->firstOrFail();

            $permissions = Permission::whereIn(
                'name',
                $permissionNames
            )->get();

            if ($permissions->count() !== count(array_unique($permissionNames))) {
                throw new \RuntimeException(
                    "One or more permissions are missing for role: {$roleName}"
                );
            }

            $role->permissions()->sync($permissions->modelKeys());
        }
    }
}
