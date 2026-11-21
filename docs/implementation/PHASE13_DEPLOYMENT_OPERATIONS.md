# Phase 13 — Deployment and Operations

## Queue worker
Configure `QUEUE_CONNECTION=database` (or a separately approved queue backend), run migrations, then supervise:
```bash
php artisan queue:work database --queue=default --tries=3 --timeout=120
```
Do not run a worker only in an interactive terminal in production; use a process supervisor and restart workers during releases:
```bash
php artisan queue:restart
```

## Scheduler
No new recurring scheduled tasks are required by this package. If scheduled tasks are added later, configure the Laravel scheduler using the hosting platform's supported cron/task scheduler.

## Operations status
```bash
php artisan marketplace:ops-status
php artisan queue:failed
```

## MySQL database backup
Configure `MARKETPLACE_MYSQLDUMP_PATH` if `mysqldump` is not on PATH:
```bash
php artisan marketplace:db-backup
```
The command writes a logical SQL backup to private application storage. It does not back up uploaded media/assets or replicate backups off-site. Restrict access to the backup directory and implement an approved retention/off-site policy.

## Restore drill (DISPOSABLE DATABASE ONLY)
1. Create a new empty disposable MySQL database.
2. Confirm the backup file and checksum/size.
3. Restore using the matching MySQL client and credentials supplied through a protected client option file.
4. Verify migration records, key table counts and representative seller/product/order/inventory relationships.
5. Record restore duration and any errors.
6. Never test restore against production or the developer's active database.

## Release and rollback
1. Back up database and media before a production migration.
2. Review `php artisan migrate:status`.
3. Deploy code and run `php artisan migrate --force` only after approval and backup verification.
4. Clear/rebuild caches as appropriate.
5. Restart queue workers.
6. Verify health endpoint, logs, queue failures and critical API flows.
7. Roll back application release if checks fail. Database rollback must follow a reviewed, migration-specific recovery plan; do not blindly run `migrate:rollback` in production.
