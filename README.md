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


## Phase 4 — Product catalogue, variants and SKU management

Implemented API foundations:
- Shared `products` catalogue with category/brand, creator, moderation state and tax-classification reference field.
- `product_variants` with category attribute values (JSON), barcode and shipping dimensions/weight.
- Product media metadata and seller upload endpoint (JPG/JPEG/PNG/WebP, max 5 MB).
- Seller product draft and submission workflow; admin approve/reject workflow.
- Public product browse endpoint returns approved products and active offers from approved sellers.
- Required category attributes are checked against each active variant when a seller submits a product.
- Seller SKU is stored on the seller offer, not on the shared product variant.

## Phase 5 — Seller offers and inventory management

Implemented API foundations:
- Seller-specific offer records with unique seller SKU per seller and one offer per seller/variant.
- Offer activation requires an approved seller and an approved product variant.
- Seller-managed inventory for MVP; inventory is one record per offer (no multi-warehouse allocation).
- Transactional stock adjustment with row locking and immutable before/after movement records.
- Low-stock threshold field and update endpoint.
- Reservation service supports reserve/release/commit with transaction locks for later checkout integration.

### New migration and setup commands

Run additive migrations only (do not use `migrate:fresh`):

```bash
php artisan migrate
php artisan optimize:clear
php artisan test
vendor\bin\pint.bat --test
```

If product images are served from Laravel's public disk, create the storage link once:

```bash
php artisan storage:link
```

### Phase 4/5 API routes

- `GET /api/v1/catalog/products`
- `GET|POST /api/v1/seller/accounts/{sellerAccount}/products`
- `GET|PUT /api/v1/seller/accounts/{sellerAccount}/products/{product}`
- `POST /api/v1/seller/accounts/{sellerAccount}/products/{product}/submit`
- `POST /api/v1/seller/accounts/{sellerAccount}/products/{product}/media`
- `DELETE /api/v1/seller/accounts/{sellerAccount}/products/{product}/media/{media}`
- `GET /api/v1/admin/products`
- `POST /api/v1/admin/products/{product}/approve`
- `POST /api/v1/admin/products/{product}/reject`\n- `PATCH /api/v1/admin/products/{product}/status` (inactive/archived)
- `GET|POST /api/v1/seller/accounts/{sellerAccount}/offers`
- `PUT /api/v1/seller/accounts/{sellerAccount}/offers/{offer}`
- `POST /api/v1/seller/accounts/{sellerAccount}/offers/{offer}/activate`
- `POST /api/v1/seller/accounts/{sellerAccount}/offers/{offer}/deactivate`
- `GET /api/v1/seller/accounts/{sellerAccount}/offers/{offer}/inventory`
- `POST /api/v1/seller/accounts/{sellerAccount}/offers/{offer}/inventory/adjust`
- `PUT /api/v1/seller/accounts/{sellerAccount}/offers/{offer}/inventory/threshold`

### Explicit implementation boundaries

- Seller-managed inventory is implemented for the MVP. Multi-warehouse stock allocation is deferred.
- Reservation service is present for checkout integration; this phase does not create a cart/checkout/payment workflow or automatically expire reservations through a scheduler.
- Image upload validates file type and size and stores through Laravel's configured `public` disk. Malware scanning, image derivative generation and production object-storage configuration still need infrastructure integration.
- Tax classification is stored as a reference field only. No GST rate or tax formula is inferred.
- Seller SKU is supplied by the seller; no SKU generation format is assumed.
- Bulk product import, row-level import reports and idempotent re-import are not part of these two phase deliverables yet.
