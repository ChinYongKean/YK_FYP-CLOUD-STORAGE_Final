ALTER TABLE `file`
    ADD `linkedFileId` INT(11) NULL,  ADD INDEX (`linkedFileId`);

ALTER TABLE `file`
    ADD `keywords` VARCHAR(255) NULL DEFAULT NULL,  ADD FULLTEXT (`keywords`);

ALTER TABLE `file`
    ADD `isPublic` INT(1) NOT NULL DEFAULT '1';

ALTER TABLE `stats`
    ADD `user_id` INT( 11 ) NULL DEFAULT NULL;

INSERT INTO `site_config`
VALUES (NULL, 'register_form_allow_password', 'no', 'allow users to choose their own passwords when registering.',
        '["yes","no"]', 'select', 'Security');

UPDATE `site_config`
SET `config_description` = 'Secret key for reCaptcha. Register at https://www.google.com/recaptcha',
    `config_value`       = '6LeyGQcTAAAAAH14UxDtIxYUnPcM11Oo0RVCc6dY'
WHERE `site_config`.`config_key` = 'captcha_private_key';

UPDATE `site_config`
SET `config_description` = 'Site key for reCaptcha. Register at https://www.google.com/recaptcha',
    `config_value`       = '6LeyGQcTAAAAADxvgyjaMHqkuGAZ3vsqpUSUS7bM'
WHERE `site_config`.`config_key` = 'captcha_public_key';

UPDATE `site_config`
SET `config_key` = 'captcha_secret_key'
WHERE `site_config`.`config_key` = 'captcha_private_key';

INSERT INTO site_config
VALUES (NULL, 'blocked_upload_file_types', '',
        'The file extensions which are NOT permitted. Leave blank to allow all file types. Separate by semi-colon. i.e. .jpg;.gif;.doc;',
        '', 'string', 'File Uploads');

CREATE TABLE `banned_files`
(
    `id`       INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `fileHash` VARCHAR(32) NOT NULL,
    `fileSize` BIGINT( 15 ) NOT NULL
) ENGINE = INNODB;

ALTER TABLE `file_folder`
    ADD `coverImageId` INT( 11 ) NULL DEFAULT NULL;

ALTER TABLE `users`
    ADD `userGroupId` INT( 11 ) NULL DEFAULT NULL;

ALTER TABLE `user_level`
    ADD `can_upload` INT(1) NOT NULL DEFAULT '0',  ADD `wait_between_downloads` INT(11) NOT NULL DEFAULT '0',  ADD `download_speed` BIGINT( 16 ) NOT NULL DEFAULT '0',  ADD `max_storage_bytes` BIGINT( 16 ) NOT NULL DEFAULT '0',  ADD `show_site_adverts` INT(1) NOT NULL DEFAULT '0',  ADD `show_upgrade_screen` INT(1) NOT NULL DEFAULT '0',  ADD `days_to_keep_inactive_files` INT(11) NOT NULL DEFAULT '360',  ADD `concurrent_uploads` INT(11) NOT NULL DEFAULT '50',  ADD `concurrent_downloads` INT(11) NOT NULL DEFAULT '5',  ADD `downloads_per_24_hours` INT(11) NOT NULL DEFAULT '0',  ADD `max_download_filesize_allowed` BIGINT( 16 ) NOT NULL DEFAULT '0',  ADD `max_remote_download_urls` INT(11) NOT NULL DEFAULT '0';

ALTER TABLE `user_level`
    ADD `max_upload_size` BIGINT( 16 ) NOT NULL DEFAULT  0;

UPDATE user_level
SET can_upload = (SELECT IF(config_value = 'yes', 1, 0)
                  FROM site_config
                  WHERE config_key = 'free_user_allow_uploads' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET wait_between_downloads = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'free_user_wait_between_downloads' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET download_speed = (SELECT config_value FROM site_config WHERE config_key = 'free_user_max_download_speed' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET max_storage_bytes = (SELECT config_value FROM site_config WHERE config_key = 'free_user_maximum_storage' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET show_site_adverts = (SELECT IF(config_value = 'yes', 1, 0)
                         FROM site_config
                         WHERE config_key = 'free_user_show_adverts' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET show_upgrade_screen = (SELECT IF(config_value = 'yes', 1, 0)
                           FROM site_config
                           WHERE config_key = 'free_user_show_upgrade_page' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET days_to_keep_inactive_files = (SELECT config_value
                                   FROM site_config
                                   WHERE config_key = 'free_user_upload_removal_days' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET concurrent_uploads = (SELECT config_value
                          FROM site_config
                          WHERE config_key = 'free_user_max_concurrent_uploads' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET concurrent_downloads = (SELECT config_value
                            FROM site_config
                            WHERE config_key = 'free_user_max_download_threads' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET downloads_per_24_hours = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'free_user_max_downloads_per_day' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET max_download_filesize_allowed = (SELECT config_value
                                     FROM site_config
                                     WHERE config_key = 'free_user_max_download_filesize' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET max_remote_download_urls = (SELECT config_value
                                FROM site_config
                                WHERE config_key = 'free_user_max_remote_urls' LIMIT 1)
WHERE level_id = 1;
UPDATE user_level
SET max_upload_size = (SELECT config_value FROM site_config WHERE config_key = 'free_user_max_upload_filesize' LIMIT 1)
WHERE level_id = 1;

UPDATE user_level
SET can_upload = (SELECT IF(config_value = 'yes', 1, 0)
                  FROM site_config
                  WHERE config_key = 'paid_user_allow_uploads' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET wait_between_downloads = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'paid_user_wait_between_downloads' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET download_speed = (SELECT config_value FROM site_config WHERE config_key = 'premium_user_max_download_speed' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET max_storage_bytes = (SELECT config_value FROM site_config WHERE config_key = 'premium_user_maximum_storage' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET show_site_adverts = (SELECT IF(config_value = 'yes', 1, 0)
                         FROM site_config
                         WHERE config_key = 'paid_user_show_adverts' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET show_upgrade_screen = (SELECT IF(config_value = 'yes', 1, 0)
                           FROM site_config
                           WHERE config_key = 'paid_user_show_upgrade_page' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET days_to_keep_inactive_files = (SELECT config_value
                                   FROM site_config
                                   WHERE config_key = 'premium_user_upload_removal_days' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET concurrent_uploads = (SELECT config_value
                          FROM site_config
                          WHERE config_key = 'premium_user_max_concurrent_uploads' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET concurrent_downloads = (SELECT config_value
                            FROM site_config
                            WHERE config_key = 'paid_user_max_download_threads' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET downloads_per_24_hours = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'premium_user_max_downloads_per_day' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET max_download_filesize_allowed = (SELECT config_value
                                     FROM site_config
                                     WHERE config_key = 'paid_user_max_download_filesize' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET max_remote_download_urls = (SELECT config_value
                                FROM site_config
                                WHERE config_key = 'premium_user_max_remote_urls' LIMIT 1)
WHERE level_id >= 2;
UPDATE user_level
SET max_upload_size = (SELECT config_value
                       FROM site_config
                       WHERE config_key = 'premium_user_max_upload_filesize' LIMIT 1)
WHERE level_id >= 2;

ALTER TABLE `user_level`
    ADD `level_type` ENUM(  'admin',  'free',  'paid',  'moderator' ) NOT NULL DEFAULT  'free';
ALTER TABLE `user_level` CHANGE `level_type` `level_type` ENUM( 'admin', 'free', 'paid', 'moderator', 'nonuser' ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'free';
UPDATE `user_level`
SET `level_type` = 'paid'
WHERE `user_level`.`level_id` < 10;
UPDATE `user_level`
SET `level_type` = 'free'
WHERE `user_level`.`level_id` = 1;
UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `user_level`.`level_id` = 0;
UPDATE `user_level`
SET `level_type` = 'moderator'
WHERE `user_level`.`level_id` = 10;
UPDATE `user_level`
SET `level_type` = 'admin'
WHERE `user_level`.`level_id` = 20;
UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `label` = 'Non User';

DELETE
FROM `site_config`
WHERE `config_group` = 'Free User Settings';
DELETE
FROM `site_config`
WHERE `config_group` = 'Premium User Settings'
  AND config_key != 'premium_user_block_account_sharing';
UPDATE site_config
SET `config_group` = 'Security'
WHERE config_key = 'premium_user_block_account_sharing';

INSERT INTO `user_level` (`id`, `level_id`, `label`)
VALUES (NULL, '0', 'non user');
UPDATE user_level
SET can_upload = (SELECT IF(config_value = 'yes', 1, 0)
                  FROM site_config
                  WHERE config_key = 'non_user_allow_uploads' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET wait_between_downloads = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'non_user_wait_between_downloads' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET download_speed = (SELECT config_value FROM site_config WHERE config_key = 'non_user_max_download_speed' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET max_storage_bytes = (SELECT config_value FROM site_config WHERE config_key = 'non_user_maximum_storage' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET show_site_adverts = (SELECT IF(config_value = 'yes', 1, 0)
                         FROM site_config
                         WHERE config_key = 'non_user_show_adverts' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET show_upgrade_screen = (SELECT IF(config_value = 'yes', 1, 0)
                           FROM site_config
                           WHERE config_key = 'non_user_show_upgrade_page' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET days_to_keep_inactive_files = (SELECT config_value
                                   FROM site_config
                                   WHERE config_key = 'non_user_upload_removal_days' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET concurrent_uploads = (SELECT config_value
                          FROM site_config
                          WHERE config_key = 'non_user_max_concurrent_uploads' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET concurrent_downloads = (SELECT config_value
                            FROM site_config
                            WHERE config_key = 'non_user_max_download_threads' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET downloads_per_24_hours = (SELECT config_value
                              FROM site_config
                              WHERE config_key = 'non_user_max_downloads_per_day' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET max_download_filesize_allowed = (SELECT config_value
                                     FROM site_config
                                     WHERE config_key = 'non_user_max_download_filesize' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET max_remote_download_urls = (SELECT config_value
                                FROM site_config
                                WHERE config_key = 'non_user_max_remote_urls' LIMIT 1)
WHERE level_id = 0;
UPDATE user_level
SET max_upload_size = (SELECT config_value FROM site_config WHERE config_key = 'non_user_max_upload_filesize' LIMIT 1)
WHERE level_id = 0;
DELETE
FROM `site_config`
WHERE `config_group` = 'Non User Settings';

ALTER TABLE `user_level`
    ADD `on_upgrade_page` INT( 1 ) NOT NULL DEFAULT  '0';
UPDATE `user_level`
SET `on_upgrade_page` = '1'
WHERE `user_level`.`level_id` = 2;
UPDATE `user_level`
SET `on_upgrade_page` = '1'
WHERE `user_level`.`level_id` = 3;
UPDATE `user_level`
SET `on_upgrade_page` = '1'
WHERE `user_level`.`level_id` = 4;

CREATE TABLE `user_level_pricing`
(
    `id`            INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_level_id` INT(11) NOT NULL,
    `price`         FLOAT(7, 4
) NOT NULL) ENGINE = MyISAM;
UPDATE `user_level`
SET `level_type` = 'admin'
WHERE `user_level`.`level_id` = 20;

ALTER TABLE `user_level_pricing`
    ADD `pricing_label` VARCHAR(50) NOT NULL AFTER  `user_level_id`;
ALTER TABLE `user_level_pricing`
    ADD `period` VARCHAR(10) NOT NULL DEFAULT '1M' AFTER  `pricing_label`;

INSERT INTO `user_level_pricing` (`user_level_id`, `pricing_label`, `period`, `price`)
VALUES (2, '7 Days', '7D',
        (SELECT config_value FROM site_config WHERE config_key = 'cost_for_7_days_premium' LIMIT 1) );
INSERT INTO `user_level_pricing` (`user_level_id`, `pricing_label`, `period`, `price`)
VALUES (2, '6 Months', '6M',
        (SELECT config_value FROM site_config WHERE config_key = 'cost_for_180_days_premium' LIMIT 1) );
INSERT INTO `user_level_pricing` (`user_level_id`, `pricing_label`, `period`, `price`)
VALUES (2, '1 Month', '1M',
        (SELECT config_value FROM site_config WHERE config_key = 'cost_for_30_days_premium' LIMIT 1) );
INSERT INTO `user_level_pricing` (`user_level_id`, `pricing_label`, `period`, `price`)
VALUES (2, '1 Year', '1Y',
        (SELECT config_value FROM site_config WHERE config_key = 'cost_for_365_days_premium' LIMIT 1) );
INSERT INTO `user_level_pricing` (`user_level_id`, `pricing_label`, `period`, `price`)
VALUES (2, '3 Months', '3M',
        (SELECT config_value FROM site_config WHERE config_key = 'cost_for_90_days_premium' LIMIT 1) );

DELETE
FROM site_config
WHERE config_key = 'cost_for_7_days_premium' LIMIT 1;
DELETE
FROM site_config
WHERE config_key = 'cost_for_30_days_premium' LIMIT 1;
DELETE
FROM site_config
WHERE config_key = 'cost_for_90_days_premium' LIMIT 1;
DELETE
FROM site_config
WHERE config_key = 'cost_for_180_days_premium' LIMIT 1;
DELETE
FROM site_config
WHERE config_key = 'cost_for_365_days_premium' LIMIT 1;

INSERT INTO site_config (id, config_key, config_value, config_description, availableValues, config_type, config_group)
VALUES (NULL, 'security_account_lock', 'no', 'Enable users to lock their accounts?', '["yes","no"]', 'select',
        'Security');

ALTER TABLE `users`
    ADD `accountLockStatus` INT( 1 ) NOT NULL DEFAULT  '0', ADD  `accountLockHash` VARCHAR( 16 ) NOT NULL;
INSERT INTO site_config (id, config_key, config_value, config_description, availableValues, config_type, config_group)
VALUES (NULL, 'email_secure_method', 'none', 'Whether the mail server requires SSL/TLS or None.',
        '["ssl","tls","none"]', 'select', 'Email Settings');

INSERT INTO `background_task` (`id`, `task`, `last_update`, `status`)
VALUES (NULL, 'create_email_notifications.cron.php', NULL, 'not_run');

CREATE TABLE IF NOT EXISTS `remote_url_download_queue`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `user_id` int
(
    11
) NOT NULL,
    `url` text COLLATE utf8_bin NOT NULL,
    `file_server_id` int
(
    11
) NOT NULL,
    `created` datetime NOT NULL,
    `started` datetime NOT NULL,
    `finished` datetime NOT NULL,
    `job_status` enum
(
    'downloading',
    'pending',
    'processing',
    'complete',
    'cancelled',
    'failed'
) COLLATE utf8_bin NOT NULL DEFAULT 'pending',
    `total_size` bigint
(
    16
) NOT NULL DEFAULT '0',
    `downloaded_size` bigint
(
    16
) NOT NULL DEFAULT '0',
    `download_percent` int
(
    3
) NOT NULL,
    `folder_id` int
(
    11
) DEFAULT NULL,
    `notes` text COLLATE utf8_bin,
    `new_file_id` int
(
    11
) DEFAULT NULL,
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin;

INSERT INTO site_config (id, config_key, config_value, config_description, availableValues, config_type, config_group)
VALUES (NULL, 'remote_url_download_in_background', 'no',
        'Should remote file downloads be done in the background? If yes you will need to setup the crontask /admin/tasks/process_remote_file_downloads.cron.php to run every minute.',
        '["yes","no"]', 'select', 'File Downloads');

INSERT INTO `background_task` (`id`, `task`, `last_update`, `status`)
VALUES (NULL, 'process_remote_file_downloads.cron.php', NULL, 'not_run');

UPDATE user_level
SET id = level_id;

ALTER TABLE `users`
    ADD `profile` TEXT NULL;
ALTER TABLE `file_folder`
    ADD `date_added` DATETIME NULL;
ALTER TABLE `file_folder`
    ADD `date_updated` DATETIME NULL;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'force_files_private', 'no',
        'Do you want to make all files uploaded private? All sharing links will be removed from the site pages and the users will only be able to download their own files.',
        '["yes","no"]', 'select', 'Security');

ALTER TABLE `users`
    ADD `isPublic` INT(1) NOT NULL DEFAULT '1';

ALTER TABLE `file_server`
    ADD `dlAccelerator` INT(1) NOT NULL DEFAULT '0';

UPDATE file_server
SET dlAccelerator = (SELECT IF(config_value = 'yes', 1, 0)
                     FROM site_config
                     WHERE config_key = 'download_use_nginx_xaccelredirect')
WHERE serverType = 'direct';
UPDATE file_server
SET dlAccelerator = (SELECT IF(config_value = 'yes', 2, 0)
                     FROM site_config
                     WHERE config_key = 'download_use_apache_xsendfile')
WHERE serverType = 'direct';
DELETE
FROM site_config
WHERE config_key = 'download_use_apache_xsendfile' LIMIT 1;
DELETE
FROM site_config
WHERE config_key = 'download_use_nginx_xaccelredirect' LIMIT 1;
ALTER TABLE `file_action` CHANGE `file_action` `file_action` ENUM('delete', 'move', 'restore') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'limit_send_via_email_per_hour', '10',
        'The maximum amount of emails that a user can send per hour from the \'send via email\' page.', '', 'string',
        'Email Settings');

ALTER TABLE `file`
    ADD `total_likes` INT( 11 ) NOT NULL DEFAULT  '0';

ALTER TABLE `premium_order`
    ADD `user_level_pricing_id` INT( 11 ) NULL AFTER  `payment_hash`;
ALTER TABLE `premium_order` CHANGE `amount` `amount` DECIMAL ( 10, 2 ) NOT NULL;
ALTER TABLE `user_level_pricing` CHANGE `price` `price` DECIMAL ( 10, 2 ) NOT NULL;

ALTER TABLE `premium_order`
    ADD `description` VARCHAR(100) NULL AFTER  `user_id`;

UPDATE `site_config`
SET `config_value` = '10080'
WHERE `config_key` = 'purge_deleted_files_period_minutes';

ALTER TABLE `file`
    ADD `uploadSource` ENUM(  'direct',  'remote',  'ftp',  'torrent',  'leech',  'webdav',  'api',  'other' ) NOT NULL DEFAULT  'direct';

ALTER TABLE `user_level` CHANGE `max_storage_bytes` `max_storage_bytes` BIGINT( 18 ) NOT NULL DEFAULT '0';
ALTER TABLE `user_level` CHANGE `max_download_filesize_allowed` `max_download_filesize_allowed` BIGINT( 18 ) NOT NULL DEFAULT '0';
ALTER TABLE `user_level` CHANGE `max_upload_size` `max_upload_size` BIGINT( 18 ) NOT NULL DEFAULT '0';

UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `user_level`.`level_id` = 0;
UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `label` = 'Non User';
