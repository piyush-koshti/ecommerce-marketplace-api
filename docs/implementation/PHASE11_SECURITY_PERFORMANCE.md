# Phase 11 — Quality, Security and Performance

## Controls retained
- Sanctum authentication, custom role/permission middleware and seller membership scoping.
- Server-side price/stock quote logic and inventory row locks.
- Stock movements and reservation state transitions.
- Payment webhook HMAC verification and event uniqueness/idempotency foundations.
- New import/export operations are scoped by active seller membership, operation owner and seller account.
- Inventory import validates the entire file before applying changes and writes stock movement records inside a database transaction.
- Download endpoint checks operation ownership and file existence before serving a private file.

## Required local verification
Run from the API project root:
```bash
php artisan optimize:clear
php artisan migrate
php artisan test
vendor/bin/pint --test
git diff --check
composer audit
```
On Windows, use `vendor\\bin\\pint.bat --test`.

## Required security/performance follow-up
- Run VAPT/OWASP testing against a staging environment.
- Verify authorization for every seller/user combination and guessed operation IDs.
- Test duplicate queue delivery and worker retries.
- Test inventory imports with malformed rows, duplicate SKUs, unknown SKUs, over-limit files and reserved/damaged stock constraints.
- Measure slow catalogue/order/admin queries with representative data and add indexes only from query-plan evidence.
- Run load tests only against a disposable/staging environment.
- Review dependency advisories using the installed Composer lock file and current Composer audit output.

No VAPT, production load test or live provider test is claimed as completed by this package.
