-- Multi-database split script (tables + data)
-- Monolith DB placeholder __SPLIT_SOURCE__ is replaced when you run:
--   php artisan db:split-multi   (or: composer db:split-multi)
-- Purpose:
-- 1) Create target databases
-- 2) Move mapped tables from source DB into target DBs (data moves with table)
-- 3) Create compatibility views in entry DB for app compatibility
--
-- IMPORTANT:
-- - This script is NOT auto-run by the app.
-- - Take a full backup before executing manually.
-- - MySQL cross-database RENAME TABLE is used (same server instance).

SET @SOURCE_DB = '__SPLIT_SOURCE__';
SET @ENTRY_DB  = 'auth_db';

CREATE DATABASE IF NOT EXISTS `auth_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `pii_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `kyc_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `payments_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `app_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `comms_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `media_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `audit_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Persistent mapping tables (not TEMPORARY): stored procedures run in a new
-- session and cannot see session temp tables, so CALL move_mapped_tables() must
-- read from real tables in the source database.
CREATE TABLE IF NOT EXISTS `__SPLIT_SOURCE__`.`_split_multidb_table_map` (
    table_name VARCHAR(128) NOT NULL,
    target_db VARCHAR(128) NOT NULL,
    PRIMARY KEY (table_name)
);
TRUNCATE TABLE `__SPLIT_SOURCE__`.`_split_multidb_table_map`;

INSERT INTO `__SPLIT_SOURCE__`.`_split_multidb_table_map` (table_name, target_db) VALUES
-- auth_db
('users', 'auth_db'),
('password_reset_tokens', 'auth_db'),
('sessions', 'auth_db'),
('user_verifications', 'auth_db'),
('staff_invites', 'auth_db'),
('staff_teams', 'auth_db'),
('staff_team_members', 'auth_db'),
('staff_documents', 'auth_db'),

-- pii_db
('visits', 'pii_db'),
('newsletter_subscribers', 'pii_db'),
('support_conversation_reads', 'pii_db'),
('coach_profiles', 'pii_db'),

-- kyc_db
('coach_verification_documents', 'kyc_db'),
('agent_absence_requests', 'kyc_db'),
('agent_absence_request_files', 'kyc_db'),

-- payments_db
('payments', 'payments_db'),
('refunds', 'payments_db'),
('payouts', 'payments_db'),
('payout_runs', 'payments_db'),
('payout_batches', 'payments_db'),
('wallet_transactions', 'payments_db'),
('coach_withdrawals', 'payments_db'),
('coach_payout_methods', 'payments_db'),
('coach_payout_accounts', 'payments_db'),
('coach_payouts', 'payments_db'),
('coach_payout_items', 'payments_db'),
('booking_fees', 'payments_db'),
('service_fees', 'payments_db'),
('disputes', 'payments_db'),
('dispute_summaries', 'payments_db'),

-- app_db
('services', 'app_db'),
('service_categories', 'app_db'),
('service_packages', 'app_db'),
('service_faqs', 'app_db'),
('service_favorites', 'app_db'),
('coach_favorites', 'app_db'),
('reservations', 'app_db'),
('reservation_slots', 'app_db'),
('reservation_reviews', 'app_db'),
('coach_weekly_hours', 'app_db'),
('coach_unavailabilities', 'app_db'),
('coach_availability_overrides', 'app_db'),
('site_settings', 'app_db'),

-- comms_db
('conversations', 'comms_db'),
('messages', 'comms_db'),
('support_conversations', 'comms_db'),
('support_messages', 'comms_db'),
('support_conversation_ratings', 'comms_db'),
('support_questions', 'comms_db'),
('support_question_messages', 'comms_db'),
('support_question_acknowledgements', 'comms_db'),
('staff_chat_rooms', 'comms_db'),
('staff_chat_room_users', 'comms_db'),

-- media_db
('staff_chat_attachments', 'media_db'),

-- audit_db
('admin_action_logs', 'audit_db'),
('admin_security_events', 'audit_db'),
('staff_deletion_audits', 'audit_db'),
('agent_absence_audits', 'audit_db'),
('agent_status_logs', 'audit_db'),
('staff_dm_threads', 'audit_db'),
('staff_dm_messages', 'audit_db'),
('staff_chat_messages', 'audit_db'),
('analytics_events', 'audit_db'),
('dispute_messages', 'audit_db'),
('dispute_attachments', 'audit_db'),
('cache', 'audit_db'),
('cache_locks', 'audit_db'),
('jobs', 'audit_db'),
('job_batches', 'audit_db'),
('failed_jobs', 'audit_db');

CREATE TABLE IF NOT EXISTS `__SPLIT_SOURCE__`.`_split_multidb_auth_tables` (
    table_name VARCHAR(128) NOT NULL PRIMARY KEY
);
TRUNCATE TABLE `__SPLIT_SOURCE__`.`_split_multidb_auth_tables`;

INSERT INTO `__SPLIT_SOURCE__`.`_split_multidb_auth_tables` (table_name) VALUES
('users'),
('password_reset_tokens'),
('sessions'),
('user_verifications'),
('staff_invites'),
('staff_teams'),
('staff_team_members'),
('staff_documents');

DELIMITER $$
DROP PROCEDURE IF EXISTS move_mapped_tables $$
CREATE PROCEDURE move_mapped_tables()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table VARCHAR(128);
    DECLARE v_target_db VARCHAR(128);

    DECLARE cur CURSOR FOR
        SELECT table_name, target_db
        FROM `__SPLIT_SOURCE__`.`_split_multidb_table_map`
        ORDER BY target_db, table_name;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SET @SOURCE_DB = '__SPLIT_SOURCE__';

    SET FOREIGN_KEY_CHECKS = 0;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_table, v_target_db;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        IF EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = @SOURCE_DB
              AND table_name = v_table
        ) THEN
            SET @sql = CONCAT(
                'RENAME TABLE `', @SOURCE_DB, '`.`', v_table, '` TO `', v_target_db, '`.`', v_table, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END LOOP;
    CLOSE cur;

    SET FOREIGN_KEY_CHECKS = 1;
END $$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS create_compat_views $$
CREATE PROCEDURE create_compat_views()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table VARCHAR(128);
    DECLARE v_target_db VARCHAR(128);

    DECLARE cur CURSOR FOR
        SELECT m.table_name, m.target_db
        FROM `__SPLIT_SOURCE__`.`_split_multidb_table_map` m
        LEFT JOIN `__SPLIT_SOURCE__`.`_split_multidb_auth_tables` a ON a.table_name = m.table_name
        WHERE a.table_name IS NULL
        ORDER BY m.target_db, m.table_name;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SET @ENTRY_DB = 'auth_db';

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_table, v_target_db;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        -- Skip if this table was not moved (no physical table in target yet)
        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = v_target_db
              AND table_name = v_table
              AND table_type = 'BASE TABLE'
        ) THEN
            ITERATE read_loop;
        END IF;

        -- Avoid conflicts when rerunning
        SET @drop_view_sql = CONCAT('DROP VIEW IF EXISTS `', @ENTRY_DB, '`.`', v_table, '`');
        PREPARE drop_stmt FROM @drop_view_sql;
        EXECUTE drop_stmt;
        DEALLOCATE PREPARE drop_stmt;

        -- Build updatable single-table view
        SET @create_view_sql = CONCAT(
            'CREATE VIEW `', @ENTRY_DB, '`.`', v_table, '` AS SELECT * FROM `', v_target_db, '`.`', v_table, '`'
        );
        PREPARE create_stmt FROM @create_view_sql;
        EXECUTE create_stmt;
        DEALLOCATE PREPARE create_stmt;
    END LOOP;
    CLOSE cur;
END $$
DELIMITER ;

-- Manual run command (do this yourself when ready):
-- CALL move_mapped_tables();
-- CALL create_compat_views();

-- Optional cleanup after successful run:
-- DROP PROCEDURE IF EXISTS move_mapped_tables;
-- DROP PROCEDURE IF EXISTS create_compat_views;
