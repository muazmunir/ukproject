# Multi-DB Migration Guide

Use this guide to decide where a new migration should go and how to run it.

## Rule

- Create each migration in the matching folder under `database/migrations-multi/<db_connection>`.
- Legacy migrations may still live under `database/migrations` until they are moved; new work should use `migrations-multi`.

## Migration Folders

- `database/migrations-multi/auth_db`
- `database/migrations-multi/pii_db`
- `database/migrations-multi/kyc_db`
- `database/migrations-multi/payments_db`
- `database/migrations-multi/app_db`
- `database/migrations-multi/comms_db`
- `database/migrations-multi/media_db`
- `database/migrations-multi/audit_db`

## Create New Migration In Specific DB

Examples:

```bash
php artisan make:migration create_wallet_holds_table --path=database/migrations-multi/payments_db
php artisan make:migration create_support_tags_table --path=database/migrations-multi/comms_db
php artisan make:migration add_last_seen_to_users_table --path=database/migrations-multi/auth_db
```

## Run Migrations

All split domains:

```bash
composer migrate:multi
```

Single domain only:

```bash
php artisan migrate --database=payments_db --path=database/migrations-multi/payments_db
```

## Rollback

Rollback one domain:

```bash
php artisan migrate:rollback --database=payments_db --path=database/migrations-multi/payments_db
```

Rollback all domains: run rollback per domain in same order as migrate.

## Which DB For New Table?

Use the mapping in `docs/database-split-plan.md`.
If a table does not clearly fit one domain, decide based on:

- data ownership
- sensitivity level (PII/KYC/payment/audit)
- query locality (which module writes most)
- retention/compliance needs
