UPDATE `site_config`
SET `config_description` = 'Should remote file downloads be done in the background? If yes you will need to setup the crontask /app/tasks/process_remote_file_downloads.cron.php to run every minute.'
WHERE `config_key` = 'remote_url_download_in_background';
DELETE
FROM `site_config`
WHERE config_key = 'security_account_lock' LIMIT 1;
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Enable 2FA', 'enable_2fa', 'no', 'Whether 2FA is available to your users.', '[\"yes\",\"no\"]', 'select',
        'Security', '50');
ALTER TABLE `users`
    ADD `login_2fa_enabled` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `users`
    ADD `login_2fa_salt` varchar(32) NULL;
ALTER TABLE `users`
    ADD `fileReferrerWhitelist` text NULL AFTER `isPublic`;

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Show Front-End API Documentation', 'show_api_page', 'yes',
        'Whether to show the front-end API documentation page. It appears as a link in the site footer.',
        '[\"yes\",\"no\"]', 'select', 'Site Options', '51');

ALTER TABLE `users` DROP `userGroupId`;

ALTER TABLE `file_block_hash`
    ADD `file_size` bigint(15) NULL AFTER `file_hash`;
INSERT INTO `file_block_hash` (SELECT null, fileHash, fileSize, NOW()
                               FROM banned_files
                               WHERE fileHash NOT IN (SELECT file_hash FROM file_block_hash));
DROP TABLE `banned_files`;

ALTER TABLE `download_page`
    ADD `file_type_limit` text NULL AFTER `page_order`;

UPDATE `site_config`
SET `config_value` = ''
WHERE `config_key` = 'system_plugin_config_cache';

SET
@s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'plugin_newsletter2'
        AND table_schema = DATABASE()
    ) > 0,
    "ALTER TABLE `plugin_newsletter` CHANGE `subject` `subject` text COLLATE 'utf8_bin' NULL AFTER `title`, CHANGE `html_content` `html_content` text COLLATE 'utf8_bin' NULL AFTER `subject`",
    "SELECT null"
));

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET
@s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'plugin_newsletter2'
        AND table_schema = DATABASE()
    ) > 0,
    "ALTER TABLE IF EXISTS `plugin_newsletter` CHANGE `user_group` `user_group` varchar(20) COLLATE 'utf8_bin' NULL AFTER `form_type`",
    "SELECT null"
));

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `site_config`
SET config_value       = REPLACE(config_value, '[[[SITE_IMAGE_PATH]]]/logo/logo-whitebg.png', '[[[EMAIL_LOGO_URL]]]'),
    config_description = REPLACE(config_description, 'Test using the \'admin/_test_scripts/test_email.php\' script', 'Test via the \'test tools\' within the script admin area')
WHERE config_key = 'email_template_header';
UPDATE `site_config`
SET config_description = REPLACE(config_description, 'Test using the \'admin/_test_scripts/test_email.php\' script', 'Test via the \'test tools\' within the script admin area')
WHERE config_key = 'email_template_footer';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('File Hashing Max Filesize (GB)', 'file_hashing_max_filesize_gb', '',
        'Optional. Some low resource web hosts may take a long time generating file hashes on upload. This would be noticed by a long delay at the 100% uploaded point. Set a numeric value here to not calculate these file hashes for files bigger than this GB in size (example value: 4). Leave empty to ignore.',
        '', 'integer', 'File Uploads', '101');

DROP TABLE IF EXISTS `file_status`;
CREATE TABLE `file_status_reason`
(
    `id`    int(11) NOT NULL AUTO_INCREMENT,
    `label` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `file_status_reason` (`id`, `label`)
VALUES (1, 'removed'),
       (2, 'user removed'),
       (3, 'admin removed'),
       (4, 'copyright removed'),
       (5, 'system expired');

ALTER TABLE `file`
    ADD `status_reason_id` int(3) NULL AFTER `status`;
UPDATE `theme`
SET theme_description = REPLACE(theme_description, 'Yetishare', 'Yetishare'),
    author_name       = 'Yetishare'
WHERE author_name = 'Yetishare';
