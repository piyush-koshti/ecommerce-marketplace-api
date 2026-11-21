# Phase 10 — Data Operations and Business Workflows

## Implemented in this backend package

### Seller inventory CSV export
- `POST /api/v1/seller/accounts/{sellerAccount}/inventory/export`
- Requires active seller membership and `inventory.view` or `inventory.manage`.
- Queues a CSV export containing seller SKU, product/variant names, price and stock quantities.
- Operation status: `GET /api/v1/seller/accounts/{sellerAccount}/data-operations/{operation}`.
- Download: `GET /api/v1/seller/accounts/{sellerAccount}/data-operations/{operation}/download`.
- The operation is scoped to both the seller account and the user who requested it.

### Seller inventory CSV import
- `POST /api/v1/seller/accounts/{sellerAccount}/inventory/import` with multipart `file`.
- Requires active seller membership and `inventory.manage`.
- CSV header must be exactly `seller_sku,quantity_on_hand,reason`.
- Maximum 5,000 rows and 10 MiB upload.
- Only inventory for offers belonging to the selected seller can be changed.
- Import is atomic: any invalid SKU, malformed row or quantity below reserved plus damaged stock rejects the entire batch.
- Every changed stock quantity creates a `stock_import_adjustment` stock movement with actor and before/after quantities.
- Import/export run through Laravel queues. Use the database queue and run a worker.

### In-app notifications
- Low-stock notification is created when an inventory adjustment crosses from above to at/below the configured threshold.
- Seller-order status changes create a database notification for the customer.
- Authenticated notification APIs: `GET /api/v1/notifications`, `PATCH /api/v1/notifications/{notification}/read`, `PATCH /api/v1/notifications/read-all`.
- Notifications are database-only; email, SMS, WhatsApp and push delivery are not configured.

### Operations commands
- `php artisan marketplace:ops-status` reports DB connectivity, migration count, queue connection, failed jobs, low stock and data-operation counts.
- `php artisan marketplace:db-backup` creates a MySQL logical backup under private `storage/app/private/backups`.

## Not included
- Product catalogue/customer/order bulk import/export.
- Automated cloud asset backup, retention lifecycle or off-site replication.
- Automated restore command. Restore is intentionally a documented manual operation to reduce destructive risk.
- SaaS database-per-tenant migration orchestration; this repository currently has a shared marketplace database and seller-account isolation, not a tenant registry.
