# Known Limitations

1. The source is a Laravel API only; storefront/admin frontend is not included.
2. Current isolation boundary is seller account membership in one marketplace database, not SaaS database-per-tenant isolation.
3. Inventory CSV import/export is implemented; general product/customer/order bulk operations are not.
4. Notifications are stored in the database only. No email/SMS/WhatsApp/push provider is wired.
5. MySQL logical backup is implemented; media backup, off-site replication, retention enforcement and automated restore are not.
6. Multi-tenant migration status/execution cannot be implemented without an approved tenant registry and tenant database connection model.
7. Payment provider, shipping provider, GST/tax calculation and settlement business rules require approved specifications and credentials.
8. Full VAPT, load testing, restore drill and frontend accessibility review remain unverified.
9. Phase 15 is not defined by the supplied screenshot or saved master roadmap.
