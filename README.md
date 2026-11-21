# Ecommerce Marketplace API

Laravel 13 REST API foundation for an India-first multi-vendor ecommerce marketplace.

## Technology

- PHP 8.3+
- Laravel 13
- MySQL 8.4 (local development database)
- Laravel Sanctum
- API prefix: `/api/v1`
- API response envelope: `data/meta` for success and `error` for failures
- Web UI direction: Laravel Blade + JavaScript (no Livewire)

## Existing foundation retained

- Registration, login, logout and logout-all
- Sanctum token authentication
- Email verification and email OTP
- Password reset and token revocation
- Request ID middleware and standard API errors
- Custom RBAC with multiple roles per user
- Role and permission seed data
- Role and permission middleware
- Authentication and RBAC feature tests

## Implemented phases in this package

### Phase 1 — Seller organisation and tenant membership

- Seller account creation with `pending_review` status
- Seller owner membership
- Existing-user staff membership management
- Seller review actions: approve, reject and suspend
- Seller account reads are scoped through active seller membership
- Seller membership pivot is separate from global user roles
- Seller staff membership can be removed without globally removing a role that may be needed for another seller

Staff onboarding currently adds an existing marketplace user by user ID. Invitation emails, acceptance tokens, KYC document workflows, detailed audit logs and seller-specific task permissions are not implemented yet.

### Phase 2 — Customer profiles and addresses

- Customer profile read/update
- Multiple customer addresses
- Default-address handling
- Address queries are always scoped to the authenticated user
- Registration assigns the baseline `customer` role

### Phase 3 — Categories, brands and attributes

- Hierarchical categories with parent/child relationships
- Brand management
- Attribute definitions and data types
- Category-to-attribute configuration
- Public active-category, active-brand and filterable-attribute endpoints
- Admin operations protected by role and permission middleware

Product listing, SKU/variant management, product attribute values, seller offers and catalogue moderation are later modules.

## Setup

1. Install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create local environment configuration:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Configure MySQL credentials in `.env`. Do not commit `.env`.

4. Run migrations:

   ```bash
   php artisan migrate
   ```

5. Seed roles and permissions:

   ```bash
   php artisan db:seed --class=RolePermissionSeeder
   ```

   Or run `php artisan db:seed` if you intentionally want the existing `DatabaseSeeder` test user too.

6. Build frontend assets when needed:

   ```bash
   npm run build
   ```

7. Run tests:

   ```bash
   php artisan test
   vendor\bin\pint.bat --test
   ```

The PHPUnit configuration uses SQLite in-memory for tests. It does not use the local MySQL database.

## New API endpoints

### Seller organisation

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/v1/seller/accounts` | Authenticated seller memberships |
| POST | `/api/v1/seller/accounts` | Authenticated user; creates owner membership |
| GET | `/api/v1/seller/accounts/{sellerAccount}` | Active member of that seller |
| POST | `/api/v1/seller/accounts/{sellerAccount}/staff` | Seller owner with `seller_staff.manage` |
| DELETE | `/api/v1/seller/accounts/{sellerAccount}/staff/{user}` | Seller owner with `seller_staff.manage` |
| GET | `/api/v1/admin/sellers` | Admin role + `sellers.view` |
| POST | `/api/v1/admin/sellers/{sellerAccount}/approve` | Admin role + `sellers.approve` |
| POST | `/api/v1/admin/sellers/{sellerAccount}/reject` | Admin role + `sellers.approve` |
| POST | `/api/v1/admin/sellers/{sellerAccount}/suspend` | Admin role + `sellers.suspend` |

Seller statuses currently used: `pending_review`, `approved`, `rejected`, `suspended`.

### Customer profile and addresses

| Method | Endpoint |
|---|---|
| GET | `/api/v1/customer/profile` |
| PUT | `/api/v1/customer/profile` |
| GET/POST | `/api/v1/customer/addresses` |
| GET/PUT/PATCH/DELETE | `/api/v1/customer/addresses/{address}` |

### Public catalogue discovery

| Method | Endpoint |
|---|---|
| GET | `/api/v1/catalog/categories` |
| GET | `/api/v1/catalog/brands` |
| GET | `/api/v1/catalog/categories/{category}/attributes` |

### Admin taxonomy

Category, brand and attribute create/read/update/delete endpoints are under `/api/v1/admin` and require both an allowed admin role and the corresponding permission.

Permissions added in this phase:
- `seller_staff.manage`
- `categories.view/create/update/delete`
- `brands.view/create/update/delete`
- `attributes.view/create/update/delete`

The seeder is repeatable and synchronizes role-permission assignments. Review permission changes before running it in production.

## Security notes

- A global role is not a seller tenant boundary. Seller-specific access must always query through the user's active `seller_users` membership.
- Do not trust seller IDs or ownership claims from request payloads.
- Seller staff membership and global role assignment are separate concepts.
- Customer address queries are scoped through the authenticated user's relationship.
- Admin taxonomy writes require both role and permission middleware.
- Sensitive seller onboarding/KYC and financial approval workflows need additional audit and maker-checker controls before production.

## Git hygiene

- `.env`, SQLite files, logs, cache artifacts and vendor dependencies are excluded from the delivery ZIP.
- Commit `.env.example`, migrations, source code, tests and documentation.
- Review `git status` before the first commit.
