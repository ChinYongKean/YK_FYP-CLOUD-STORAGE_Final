UPDATE `banned_ip`
SET `banType` = 'Uploading',
    banNotes  = CONCAT('Originally banned whole site. ', banNotes)
WHERE banType = 'Whole Site';
ALTER TABLE `banned_ip`
    ADD INDEX `banExpiry` (`banExpiry`);
ALTER TABLE `language`
    ADD INDEX `languageName` (`languageName`);

-- Start Yetishare v5.4.0
ALTER TABLE `file_server` ADD `capture_resource_usage` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET file_server.`capture_resource_usage` = 1 WHERE serverType IN ('local', 'direct');

ALTER TABLE `file_server`
    ADD INDEX `capture_resource_usage` (`capture_resource_usage`),
    ADD INDEX `serverType` (`serverType`);

ALTER TABLE `file_server`
    ADD INDEX `accountUploadTypes` (`accountUploadTypes`);

CREATE TABLE `file_artifact` (
                                 `id` int unsigned NOT NULL AUTO_INCREMENT,
                                 `file_id` int unsigned NOT NULL,
                                 `local_file_path` varchar(255) DEFAULT NULL,
                                 `file_type` varchar(150) DEFAULT NULL,
                                 `file_hash` varchar(32) CHARACTER SET utf8mb4 DEFAULT NULL,
                                 `file_artifact_type` enum('primary','mirror','preview','version','format') NOT NULL DEFAULT 'primary',
                                 `file_size` bigint NOT NULL DEFAULT '0',
                                 `internal_reference` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
                                 `override_filename` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
                                 `override_extension` varchar(10) CHARACTER SET utf8mb4 DEFAULT NULL,
                                 `created` datetime NOT NULL,
                                 `updated` datetime DEFAULT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `file_id` (`file_id`),
                                 KEY `internal_reference` (`internal_reference`),
                                 KEY `created` (`created`),
                                 KEY `file_hash` (`file_hash`),
                                 KEY `file_artifact_type` (`file_artifact_type`),
                                 KEY `file_size` (`file_size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO file_artifact (SELECT null, id, localFilePath, fileType, fileHash, 'primary', fileSize, null, null, null, now(), null FROM file WHERE status IN('active', 'trash'));

CREATE TABLE `file_artifact_storage` (
                                         `id` int unsigned NOT NULL AUTO_INCREMENT,
                                         `file_artifact_id` int unsigned NOT NULL,
                                         `file_server_id` int NOT NULL,
                                         `is_primary` tinyint(1) NOT NULL DEFAULT '1',
                                         `created` datetime NOT NULL,
                                         `updated` datetime DEFAULT NULL,
                                         PRIMARY KEY (`id`),
                                         UNIQUE KEY `file_artifact_id_file_server_id` (`file_artifact_id`,`file_server_id`),
                                         KEY `file_server_id` (`file_server_id`),
                                         KEY `file_artifact_id` (`file_artifact_id`),
                                         KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO file_artifact_storage (SELECT null, file_artifact.id, serverId, 1, now(), null FROM file_artifact LEFT JOIN file ON file_artifact.file_id = file.id WHERE status IN('active', 'trash'));

ALTER TABLE `file_server` ADD `is_default` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET is_default = 1 WHERE serverLabel = 'Local Default';

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, `display_order`)
VALUES ("Storage Filename Structure", "upload_storage_filename_structure", "Secure Hashed", "Whether to store uploaded files using a secure hashed filename or in subfolders with their original filenames. This only impacts how the files are actually stored on your servers. Setting as 'User Id/Original Filename' will disable the de-dupe functionality. Recommended to set this as 'Secure Hashed' in most instances for security reasons.", "[\"Secure Hashed\",\"User Id/Original Filename\"]", "select", "File Uploads", 10);

DROP TABLE IF EXISTS `user_action_log`;
CREATE TABLE `user_action_log` (
                                   `id` int unsigned NOT NULL AUTO_INCREMENT,
                                   `message` varchar(1000) NOT NULL,
                                   `params` varchar(2000) DEFAULT NULL,
                                   `category` int NOT NULL,
                                   `action_type` int NOT NULL,
                                   `date_created` datetime NOT NULL,
                                   `action_user_id` int DEFAULT NULL,
                                   `user_ip` varchar(45) DEFAULT NULL,
                                   `admin_area_action` tinyint NOT NULL DEFAULT '0',
                                   `file_id` int DEFAULT NULL,
                                   `user_id` int DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `action_user_id` (`action_user_id`),
                                   KEY `file_id` (`file_id`),
                                   KEY `user_id` (`user_id`),
                                   KEY `date_created` (`date_created`),
                                   KEY `is_admin_user_id` (`admin_area_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO user_action_log (SELECT null, "File uploaded", null, 3, 6, uploadedDate, userId, uploadedIP, 0, id, null FROM file);

ALTER TABLE `file_action`
    ADD `artifact_id` int NULL AFTER `file_id`;

ALTER TABLE `file`
    DROP `fileSize`,
    DROP `fileType`,
    DROP `localFilePath`,
    DROP `serverId`,
    DROP `fileHash`;

ALTER TABLE `user_level` ADD `download_size_per_24_hours` int NOT NULL DEFAULT '0' AFTER `downloads_per_24_hours`;

UPDATE `plugin_payment_gateways` SET `gateway_type` = 2, `gateway_additional_params` = '{\"description\true,\"returnUrl\true}' WHERE `class_name` = 'Coinbase';
UPDATE plugin_payment_gateways SET available = 1 WHERE class_name = 'TwoCheckout';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Braintree';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'Buckaroo_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'CardSave';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Coinbase';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'FirstData_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'GoCardless';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'Migs_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Netaxept';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'NetBanx';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'OKPAY';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Paysafecard';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Pin';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'TargetPay_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'WebMoney';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Sofort';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Paysera';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'EgopayRu';
INSERT INTO `plugin_payment_gateways` (`class_name`, `label`, `description`, `url`, `gateway_type`, `gateway_group`, `available`, `gateway_additional_params`)
SELECT 'Bill99', '99Bill', NULL, 'https://www.99bill.com', 1, '99Bill', '1', NULL
FROM `plugin_payment_gateways`
WHERE ((`id` = '50'));

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order)
VALUES ("User Can Empty Trash Can", "user_can_empty_trash_can", "yes",
        "Whether to enable access for users to empty the trash via their account", '["yes","no"]', "select", "File Manager", 500);

ALTER TABLE `file_server` ADD `monitor_server_resources` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET `monitor_server_resources` = 1 WHERE serverType IN ('local', 'direct');

ALTER TABLE `download_token` ADD `internal_download` int NOT NULL DEFAULT '0';

ALTER TABLE `file_server` ADD `enable_availability_checker` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `file_server` ADD `availability_state` tinyint NULL DEFAULT NULL;

DROP TABLE IF EXISTS `file_server_resource_usage`;
CREATE TABLE `file_server_resource_usage` (
      `id` int NOT NULL AUTO_INCREMENT,
      `file_server_id` int NOT NULL,
      `date_created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
      `cpu_load_1_minute` decimal(10,2) DEFAULT NULL,
      `cpu_load_5_minutes` decimal(10,2) DEFAULT NULL,
      `cpu_load_15_minutes` decimal(10,2) DEFAULT NULL,
      `cpu_count` int DEFAULT NULL,
      `memory_total_gb` decimal(10,2) DEFAULT NULL,
      `memory_used_gb` decimal(10,2) DEFAULT NULL,
      `memory_free_gb` decimal(10,2) DEFAULT NULL,
      `memory_shared_gb` decimal(10,2) DEFAULT NULL,
      `memory_cached_gb` decimal(10,2) DEFAULT NULL,
      `memory_available_gb` decimal(10,2) DEFAULT NULL,
      `disk_primary_total_bytes` bigint DEFAULT NULL,
      `disk_primary_used_bytes` bigint DEFAULT NULL,
      `disk_primary_used_percent` decimal(10,2) DEFAULT NULL,
      `network_established_connections` int DEFAULT NULL,
      `network_total_connections` int DEFAULT NULL,
      `has_shell_exec` tinyint(1) DEFAULT '0',
      `has_netstat` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `file_server_id` (`file_server_id`),
      KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
