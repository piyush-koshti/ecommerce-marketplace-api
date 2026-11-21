# Manual QA Checklist

Use a staging or disposable environment. Do not perform destructive tests against production.

| ID | Prerequisite | Steps | Expected result | Status / Notes |
|---|---|---|---|---|
| OPS-01 | Seller owner with inventory.manage | Upload valid inventory CSV using exact header `seller_sku,quantity_on_hand,reason` | HTTP 202; operation completes; stock and stock movement update | Pending |
| OPS-02 | CSV with unknown SKU | Import a CSV containing one valid and one unknown SKU | Operation fails; no row is applied | Pending |
| OPS-03 | Inventory with reserved/damaged units | Import a quantity below reserved + damaged | Operation fails atomically | Pending |
| OPS-04 | Seller owner with inventory.view | Request inventory export, poll operation, download file | Private CSV contains only that seller's inventory | Pending |
| OPS-05 | Two seller accounts | Attempt to view/download another seller's operation | 404; no data disclosure | Pending |
| OPS-06 | Inventory threshold configured | Adjust stock from above to at/below threshold | Active seller members receive in-app low-stock notification | Pending |
| OPS-07 | Customer order with seller fulfilment | Change seller order status | Customer receives in-app status notification | Pending |
| OPS-08 | MySQL staging DB and mysqldump | Run `php artisan marketplace:db-backup` | SQL backup created in private storage | Pending |
| OPS-09 | Disposable MySQL database | Restore a backup and compare key records | Restore succeeds and key relationships match | Pending |
| OPS-10 | Queue worker running | Dispatch inventory import/export | Queue processes job; failed jobs are visible | Pending |
| OPS-11 | Operations command | Run `php artisan marketplace:ops-status` | DB, migrations, queue, failed jobs and low-stock status are reported | Pending |
| UX-01 | Frontend build available | Review keyboard, contrast, mobile, loading/empty/error states | Record frontend-specific results; not testable from this backend ZIP | Blocked: frontend absent |
