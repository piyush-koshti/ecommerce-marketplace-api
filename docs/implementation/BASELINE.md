# Repository Baseline

- Source: user-provided `ecommerce-marketplace-api(5).zip`.
- Stack: Laravel 13 REST API, PHP requirement `^8.3`, Sanctum, MySQL 8.4 target.
- Current package contains backend APIs and tests; no customer storefront/admin frontend is present.
- Existing marketplace data model is one application database with seller-account membership isolation. It is not a database-per-SaaS-tenant control plane.
- Existing implemented areas retained: authentication, RBAC, seller accounts, customer profiles/addresses, taxonomy, products/variants, seller offers/inventory, carts/wishlists, checkout, payment integration boundary, orders and shipments.
- Existing automated tests are included in the source archive. The archive does not include `vendor/`, `.env`, or Git history, so framework tests cannot be executed from this delivery workspace.
- No destructive database commands were run. No existing migration was edited.
- The source archive contained a `tatus` text artifact and PHPUnit result cache; these are excluded from the cleaned delivery ZIP.
