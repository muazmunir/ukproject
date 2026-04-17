# Multi-Database Split Plan

This document maps the current schema into separate databases and reports total table counts.

## Totals

- Total unique tables: `70`
- Total databases: `8`
- Databases: `auth_db`, `pii_db`, `kyc_db`, `payments_db`, `app_db`, `comms_db`, `media_db`, `audit_db`

## Database-Wise Mapping

### `auth_db` (8 tables)

- `users`
- `password_reset_tokens`
- `sessions`
- `user_verifications`
- `staff_invites`
- `staff_teams`
- `staff_team_members`
- `staff_documents`

### `pii_db` (4 tables)

- `visits`
- `newsletter_subscribers`
- `support_conversation_reads`
- `coach_profiles`

### `kyc_db` (3 tables)

- `coach_verification_documents`
- `agent_absence_requests`
- `agent_absence_request_files`

### `payments_db` (15 tables)

- `payments`
- `refunds`
- `payouts`
- `payout_runs`
- `payout_batches`
- `wallet_transactions`
- `coach_withdrawals`
- `coach_payout_methods`
- `coach_payout_accounts`
- `coach_payouts`
- `coach_payout_items`
- `booking_fees`
- `service_fees`
- `disputes`
- `dispute_summaries`

### `app_db` (13 tables)

- `services`
- `service_categories`
- `service_packages`
- `service_faqs`
- `service_favorites`
- `coach_favorites`
- `reservations`
- `reservation_slots`
- `reservation_reviews`
- `coach_weekly_hours`
- `coach_unavailabilities`
- `coach_availability_overrides`
- `site_settings`

### `comms_db` (10 tables)

- `conversations`
- `messages`
- `support_conversations`
- `support_messages`
- `support_conversation_ratings`
- `support_questions`
- `support_question_messages`
- `support_question_acknowledgements`
- `staff_chat_rooms`
- `staff_chat_room_users`

### `media_db` (1 table)

- `staff_chat_attachments`

### `audit_db` (16 tables)

- `admin_action_logs`
- `admin_security_events`
- `staff_deletion_audits`
- `agent_absence_audits`
- `agent_status_logs`
- `staff_dm_threads`
- `staff_dm_messages`
- `staff_chat_messages`
- `analytics_events`
- `dispute_messages`
- `dispute_attachments`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

## Notes

- This mapping follows your requested domain split architecture.
- A few tables (for example, `support_conversation_reads`, `coach_profiles`, `staff_dm_threads`) can be placed in more than one domain depending on query patterns. They are currently assigned for practical separation.
- The SQL script uses `RENAME TABLE` across databases, so table structure and data move together.
- The SQL script also creates compatibility views in `auth_db` (entry DB), so legacy queries can keep working when `DB_TOPOLOGY=multi`.
- Run the SQL script in `database/scripts/split-databases.sql` only after taking a full DB backup.

## App Toggle (`single` vs `multi`)

- `DB_TOPOLOGY=single`: app uses `DB_DATABASE` (monolith).
- `DB_TOPOLOGY=multi`: app uses `DB_DATABASE_MULTI_ENTRY` (default `auth_db`) and can still access split tables through compatibility views.
- Multi DB connection names are also configured in app config: `auth_db`, `pii_db`, `kyc_db`, `payments_db`, `app_db`, `comms_db`, `media_db`, `audit_db`.

## Migration Handling

- Single DB: use normal `database/migrations` and run `composer migrate:single`.
- Multi DB: create migrations in `database/migrations-multi/<connection>` and run `composer migrate:multi`.
- Full step-by-step process is documented in `docs/multi-db-migrations.md`.
