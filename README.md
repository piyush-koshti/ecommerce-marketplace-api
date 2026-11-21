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


## Phases 6–9 backend implementation package

This package adds backend API foundations for shopping, checkout, payments integration boundaries and seller order fulfilment.

### Phase 6 — Cart and wishlist
- Signed-in carts are scoped to the authenticated user.
- Guest carts use a random `X-Guest-Cart-Token`; only its SHA-256 hash is stored.
- The token is returned in the response header when a guest cart is first created.
- Guest cart merge requires authentication and the guest token.
- Wishlist records are scoped to the authenticated user.
- Cart requests validate active seller offers and available stock; prices are never accepted from the client.

### Phase 7 — Checkout and pricing
- Checkout quotes require an address owned by the authenticated customer.
- The quote is calculated from current seller-offer prices and stock on the server.
- Quote records contain item snapshots and expire after a configurable interval.
- The initial pricing snapshot deliberately uses zero tax, zero shipping and zero discount and flags those rules as pending. This is a safe technical placeholder, not a production tax/shipping calculation.
- COD order placement is disabled by default. It can only be enabled after business policy approval using `MARKETPLACE_COD_ENABLED=true`.
- Online order placement returns HTTP 503 until a payment-provider adapter and approved marketplace settlement model are integrated.

### Phase 8 — Payment integration boundary
- Payment intent, payment transaction, webhook event and ledger schema are added.
- The generic webhook endpoint requires an HMAC-SHA256 signature in `X-Provider-Signature` and an explicitly configured secret.
- Webhook events are idempotent by provider + event ID; payment amount is checked against the stored intent.
- The payment-intent endpoint intentionally returns 503 until a real provider adapter is implemented and configured.
- No payment is marked successful from a browser/client request.
- Ledger tables and an immutable ledger-entry model are provided, but balanced marketplace postings, commission splits, refunds, reconciliation and payout logic are not enabled until finance policy and provider settlement rules are approved.

### Phase 9 — Orders and fulfilment
- A parent order and seller-specific fulfilment groups are created for an accepted COD order.
- Order and seller-order state changes are recorded in append-only history rows.
- Seller order access is scoped to active seller membership and order permissions.
- A seller can create a shipment record after packing and then mark the group shipped.
- The current shipment record stores carrier/tracking references; carrier API integration, tracking webhooks, partial shipments, RTO and returns remain follow-up work.
- COD stock is reserved and committed transactionally when the order is accepted; cancellation before shipment restores stock and records a stock movement.

### Added API endpoints
- `GET|POST /api/v1/cart`, `POST /api/v1/cart/items`, `PATCH|DELETE /api/v1/cart/items/{item}`
- `POST /api/v1/cart/merge-guest`
- `GET /api/v1/wishlist`, `POST /api/v1/wishlist/items`, `DELETE /api/v1/wishlist/items/{item}`
- `POST /api/v1/checkout/quote`, `POST /api/v1/checkout/{checkout}/place`
- `POST /api/v1/orders/{order}/payment-intent`
- `POST /api/v1/webhooks/payments/{provider}`
- `GET /api/v1/orders`, `GET /api/v1/orders/{order}`
- `GET /api/v1/seller/accounts/{sellerAccount}/orders`
- `POST /api/v1/seller/accounts/{sellerAccount}/orders/{sellerOrder}/shipments`
- `PATCH /api/v1/seller/accounts/{sellerAccount}/orders/{sellerOrder}/status`

### Install and verify
1. Back up the database and project.
2. Merge this package into the existing project; preserve `.env` and `.git`.
3. Run `composer install` only if dependencies changed (no new Composer package is required).
4. Run `php artisan optimize:clear`.
5. Run `php artisan migrate` (do not run `migrate:fresh`).
6. Run `php artisan test`.
7. Run `vendor\\bin\\pint.bat` and `vendor\\bin\\pint.bat --test`.

### Decisions required before production
- Approved GST/tax configuration and invoice treatment.
- Shipping-rate/serviceability provider and shipping charge rules.
- Coupon/discount rules and who funds each discount.
- Payment provider, credentials, webhook signing format, marketplace split settlement and refund capabilities.
- Commission rates and ledger posting policy.
- COD availability, eligibility and collection/reconciliation process.
- Reservation expiry, payment timeout and cancellation policy for online payments.
- Partial shipment/cancellation, return windows, restock and refund policies.
- Seller/carrier tracking integration and delivery exception workflows.

This is a backend-only package. It does not add the customer storefront, seller dashboard or admin frontend. Payment provider processing, production tax/shipping calculations and finance settlement must not be represented as complete.


## Phases 10–14 — Data operations and operational readiness

This backend-only delivery adds the following Phase 10 foundations:

### Seller inventory data operations

| Method | Endpoint | Permission |
|---|---|---|
| POST | `/api/v1/seller/accounts/{sellerAccount}/inventory/import` | `inventory.manage` |
| POST | `/api/v1/seller/accounts/{sellerAccount}/inventory/export` | `inventory.view` or `inventory.manage` |
| GET | `/api/v1/seller/accounts/{sellerAccount}/data-operations/{operation}` | Request owner + active seller member |
| GET | `/api/v1/seller/accounts/{sellerAccount}/data-operations/{operation}/download` | Request owner + active seller member |

Inventory import accepts CSV with the exact header:

```csv
seller_sku,quantity_on_hand,reason
SKU-100,25,Verified physical stock count
```

Import limit: 5,000 rows and 10 MiB. The entire file is validated before updates are applied. Unknown SKUs, duplicate SKUs, malformed rows or quantities below reserved plus damaged stock fail the whole import. Each changed inventory row creates a stock movement.

Operations are asynchronous. Configure a queue connection and run:

```bash
php artisan queue:work database --queue=default --tries=3 --timeout=120
```

### In-app notifications

- Low-stock notifications are created when an inventory adjustment crosses the low-stock threshold.
- Seller-order status changes create a notification for the customer.
- Authenticated endpoints:
  - `GET /api/v1/notifications`
  - `PATCH /api/v1/notifications/{notification}/read`
  - `PATCH /api/v1/notifications/read-all`

Notifications are database-only. Email, SMS, WhatsApp and push providers are not configured.

### Operations commands

```bash
php artisan marketplace:ops-status
php artisan marketplace:db-backup
```

`marketplace:db-backup` supports MySQL and requires `mysqldump` on PATH or `MARKETPLACE_MYSQLDUMP_PATH` configured in `.env`. Backups are written under private `storage/app/private/backups`. This command does not back up media files or copy backups off-site. Restore is documented as a manual procedure for a disposable database only.

### Phase 11–14 status and limitations

- Phase 11: security/performance review checklist and verification commands are documented; full VAPT/load testing still requires local/staging execution.
- Phase 12: API usability review is documented. The supplied project has no frontend source, so visual accessibility/responsive review is pending.
- Phase 13: queue, backup, restore, release and rollback runbooks are included.
- Phase 14: final handover, known limitations, test plan and manual QA checklist are included.

See `docs/implementation/` for phase status and runbooks.

**Architecture boundary:** this repository currently implements a shared marketplace database with seller-account membership isolation. It does not implement a SaaS control plane with a separate business database per tenant. Do not enable database-per-tenant assumptions without an approved architecture migration.

**Phase 15:** no Phase 15 title or scope was included in the supplied roadmap screenshot or saved master roadmap.
