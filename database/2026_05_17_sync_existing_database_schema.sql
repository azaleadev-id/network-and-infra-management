-- Sinkronisasi schema database lama agar sesuai dengan kode aplikasi saat ini.
-- File ini hanya menambah kolom/index yang belum ada dan menyesuaikan enum yang sudah dipakai aplikasi.
-- Tidak menghapus tabel maupun data yang sudah ada.

START TRANSACTION;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL add_column_if_missing('admin_users', 'email', 'VARCHAR(191) NULL AFTER `nama`');
CALL add_column_if_missing('admin_users', 'google_id', 'VARCHAR(191) NULL AFTER `email`');
CALL add_column_if_missing('admin_users', 'google_email', 'VARCHAR(191) NULL AFTER `google_id`');
CALL add_column_if_missing('admin_users', 'avatar_url', 'VARCHAR(255) NULL AFTER `google_email`');
CALL add_column_if_missing('admin_users', 'login_provider', 'ENUM(''manual'',''google'') NOT NULL DEFAULT ''manual'' AFTER `avatar_url`');
CALL add_column_if_missing('admin_users', 'can_scan_payment', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`');
CALL add_column_if_missing('admin_users', 'parent_admin_user_id', 'INT(11) NULL AFTER `can_scan_payment`');
CALL add_column_if_missing('admin_users', 'created_by', 'INT(11) NULL AFTER `parent_admin_user_id`');

CALL add_column_if_missing('tenants', 'owner_admin_user_id', 'INT(11) NULL AFTER `expired_at`');

CALL add_index_if_missing('admin_users', 'idx_admin_users_email', '(`email`)');
CALL add_index_if_missing('admin_users', 'idx_admin_users_google_email', '(`google_email`)');
CALL add_index_if_missing('admin_users', 'idx_admin_users_google_id', '(`google_id`)');
CALL add_index_if_missing('admin_users', 'idx_admin_users_parent_admin', '(`parent_admin_user_id`)');
CALL add_index_if_missing('admin_users', 'idx_admin_users_created_by', '(`created_by`)');
CALL add_index_if_missing('tenants', 'idx_tenants_owner_admin_user', '(`owner_admin_user_id`)');

UPDATE `tenants` t
JOIN (
    SELECT `tenant_id`, MIN(`id`) AS `owner_id`
    FROM `admin_users`
    WHERE `role` IN ('superadmin', 'superuser', 'owner')
    GROUP BY `tenant_id`
) owner_map ON owner_map.`tenant_id` = t.`id`
SET t.`owner_admin_user_id` = owner_map.`owner_id`
WHERE t.`owner_admin_user_id` IS NULL;

ALTER TABLE `admin_users`
    MODIFY COLUMN `role` ENUM(
        'superadmin',
        'superuser',
        'owner',
        'admin',
        'operator',
        'viewer',
        'cashier',
        'collector',
        'karyawan',
        'kasir',
        'penagih',
        'petugas',
        'staff'
    ) NOT NULL DEFAULT 'owner';

ALTER TABLE `tenants`
    MODIFY COLUMN `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active';

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

COMMIT;
