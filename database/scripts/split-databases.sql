-- Multi-database split script (COPY — monolith unchanged)
-- Table → domain map is duplicated in database/split_multidb_table_map.php for:
--   php artisan db:split-multi:export   (mysqldump per table into storage/app/db-split-export/{connection}/)
--   php artisan db:split-multi:import   (mysql each file into that connection’s database)
-- Placeholders (replaced by php artisan db:split-multi):
--   __SPLIT_SOURCE__     = monolith database (read-only for table data)
--   __SPLIT_CONTROL__    = metadata DB (see DB_SPLIT_CONTROL_DATABASE in .env)
--   __AUTH_DB__ … __AUDIT_DB__ = domain DB names from config / .env
--
-- Shared hosting (Hostinger, etc.): your MySQL user usually CANNOT CREATE DATABASE.
-- Create these EMPTY databases in the hosting panel first (exact names must match .env):
--   DB_SPLIT_CONTROL_DATABASE, DB_AUTH_DATABASE, DB_PII_DATABASE, DB_KYC_DATABASE,
--   DB_PAYMENTS_DATABASE, DB_APP_DATABASE, DB_COMMS_DATABASE, DB_MEDIA_DATABASE, DB_AUDIT_DATABASE
--
-- Then run: php artisan db:split-multi --force

SET @SOURCE_DB = '__SPLIT_SOURCE__';
SET @ENTRY_DB  = '__AUTH_DB__';

CREATE TABLE IF NOT EXISTS `__SPLIT_CONTROL__`.`_split_multidb_table_map` (
    table_name VARCHAR(128) NOT NULL,
    target_db VARCHAR(128) NOT NULL,
    PRIMARY KEY (table_name)
);
TRUNCATE TABLE `__SPLIT_CONTROL__`.`_split_multidb_table_map`;

INSERT INTO `__SPLIT_CONTROL__`.`_split_multidb_table_map` (table_name, target_db) VALUES
('users', '__AUTH_DB__'),
('password_reset_tokens', '__AUTH_DB__'),
('sessions', '__AUTH_DB__'),
('user_verifications', '__AUTH_DB__'),
('staff_invites', '__AUTH_DB__'),
('staff_teams', '__AUTH_DB__'),
('staff_team_members', '__AUTH_DB__'),
('staff_documents', '__AUTH_DB__'),
('visits', '__PII_DB__'),
('newsletter_subscribers', '__PII_DB__'),
('support_conversation_reads', '__PII_DB__'),
('coach_profiles', '__PII_DB__'),
('coach_verification_documents', '__PII_DB__'),
('agent_absence_requests', '__KYC_DB__'),
('agent_absence_request_files', '__KYC_DB__'),
('payments', '__PAYMENTS_DB__'),
('refunds', '__PAYMENTS_DB__'),
('payouts', '__PAYMENTS_DB__'),
('payout_runs', '__PAYMENTS_DB__'),
('payout_batches', '__PAYMENTS_DB__'),
('wallet_transactions', '__PAYMENTS_DB__'),
('coach_withdrawals', '__PAYMENTS_DB__'),
('coach_payout_methods', '__PAYMENTS_DB__'),
('coach_payout_accounts', '__PAYMENTS_DB__'),
('coach_payouts', '__PAYMENTS_DB__'),
('coach_payout_items', '__PAYMENTS_DB__'),
('booking_fees', '__PAYMENTS_DB__'),
('service_fees', '__PAYMENTS_DB__'),
('disputes', '__PAYMENTS_DB__'),
('dispute_summaries', '__PAYMENTS_DB__'),
('services', '__APP_DB__'),
('service_categories', '__APP_DB__'),
('service_packages', '__APP_DB__'),
('service_faqs', '__APP_DB__'),
('service_favorites', '__APP_DB__'),
('coach_favorites', '__APP_DB__'),
('reservations', '__APP_DB__'),
('reservation_slots', '__APP_DB__'),
('reservation_reviews', '__APP_DB__'),
('coach_weekly_hours', '__APP_DB__'),
('coach_unavailabilities', '__APP_DB__'),
('coach_availability_overrides', '__APP_DB__'),
('site_settings', '__APP_DB__'),
('countries', '__APP_DB__'),
('cities', '__APP_DB__'),
('conversations', '__COMMS_DB__'),
('messages', '__COMMS_DB__'),
('support_conversations', '__COMMS_DB__'),
('support_messages', '__COMMS_DB__'),
('support_conversation_ratings', '__COMMS_DB__'),
('support_questions', '__COMMS_DB__'),
('support_question_messages', '__COMMS_DB__'),
('support_question_acknowledgements', '__COMMS_DB__'),
('staff_chat_rooms', '__COMMS_DB__'),
('staff_chat_room_users', '__COMMS_DB__'),
('staff_chat_attachments', '__MEDIA_DB__'),
('admin_action_logs', '__AUDIT_DB__'),
('admin_security_events', '__AUDIT_DB__'),
('staff_deletion_audits', '__AUDIT_DB__'),
('agent_absence_audits', '__AUDIT_DB__'),
('agent_status_logs', '__AUDIT_DB__'),
('staff_dm_threads', '__AUDIT_DB__'),
('staff_dm_messages', '__AUDIT_DB__'),
('staff_chat_messages', '__AUDIT_DB__'),
('analytics_events', '__AUDIT_DB__'),
('dispute_messages', '__AUDIT_DB__'),
('dispute_attachments', '__AUDIT_DB__'),
('cache', '__AUDIT_DB__'),
('cache_locks', '__AUDIT_DB__'),
('jobs', '__AUDIT_DB__'),
('job_batches', '__AUDIT_DB__'),
('failed_jobs', '__AUDIT_DB__');

CREATE TABLE IF NOT EXISTS `__SPLIT_CONTROL__`.`_split_multidb_auth_tables` (
    table_name VARCHAR(128) NOT NULL PRIMARY KEY
);
TRUNCATE TABLE `__SPLIT_CONTROL__`.`_split_multidb_auth_tables`;

INSERT INTO `__SPLIT_CONTROL__`.`_split_multidb_auth_tables` (table_name) VALUES
('users'),
('password_reset_tokens'),
('sessions'),
('user_verifications'),
('staff_invites'),
('staff_teams'),
('staff_team_members'),
('staff_documents');

USE `__SPLIT_CONTROL__`;

DELIMITER $$
DROP PROCEDURE IF EXISTS copy_mapped_tables $$
CREATE PROCEDURE copy_mapped_tables()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table VARCHAR(128);
    DECLARE v_target_db VARCHAR(128);

    DECLARE cur CURSOR FOR
        SELECT table_name, target_db
        FROM `__SPLIT_CONTROL__`.`_split_multidb_table_map`
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
              AND table_type = 'BASE TABLE'
        ) THEN
            SET @drop_t = CONCAT('DROP TABLE IF EXISTS `', v_target_db, '`.`', v_table, '`');
            PREPARE dt FROM @drop_t;
            EXECUTE dt;
            DEALLOCATE PREPARE dt;

            SET @create_like = CONCAT(
                'CREATE TABLE `', v_target_db, '`.`', v_table, '` LIKE `', @SOURCE_DB, '`.`', v_table, '`'
            );
            PREPARE cl FROM @create_like;
            EXECUTE cl;
            DEALLOCATE PREPARE cl;

            SET @ins = CONCAT(
                'INSERT INTO `', v_target_db, '`.`', v_table, '` SELECT * FROM `', @SOURCE_DB, '`.`', v_table, '`'
            );
            PREPARE insstmt FROM @ins;
            EXECUTE insstmt;
            DEALLOCATE PREPARE insstmt;
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
        FROM `__SPLIT_CONTROL__`.`_split_multidb_table_map` m
        LEFT JOIN `__SPLIT_CONTROL__`.`_split_multidb_auth_tables` a ON a.table_name = m.table_name
        WHERE a.table_name IS NULL
        ORDER BY m.target_db, m.table_name;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    SET @ENTRY_DB = '__AUTH_DB__';

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_table, v_target_db;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        IF NOT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = v_target_db
              AND table_name = v_table
              AND table_type = 'BASE TABLE'
        ) THEN
            ITERATE read_loop;
        END IF;

        SET @drop_view_sql = CONCAT('DROP VIEW IF EXISTS `', @ENTRY_DB, '`.`', v_table, '`');
        PREPARE drop_stmt FROM @drop_view_sql;
        EXECUTE drop_stmt;
        DEALLOCATE PREPARE drop_stmt;

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
