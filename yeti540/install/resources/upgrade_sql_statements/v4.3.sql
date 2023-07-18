ALTER TABLE `file`
    ADD INDEX (  `statusId` );
ALTER TABLE `file_server`
    ADD `totalFiles` INT( 11 ) NOT NULL DEFAULT  '0' AFTER  `totalSpaceUsed` , ADD INDEX (  `totalFiles` );
INSERT INTO site_config
VALUES (NULL, 'next_check_for_server_stats_update', '0',
        'System value. The next time to update the total filesize and file count in the file_server table. Timestamp. Do not edit.',
        '', 'integer', 'System');

ALTER TABLE `user_level_pricing` CHANGE `period` `period` VARCHAR ( 10 ) CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT '1M';
ALTER TABLE `user_level_pricing`
    ADD `package_pricing_type` VARCHAR(10) NOT NULL DEFAULT 'period' AFTER  `pricing_label`;
ALTER TABLE `user_level_pricing`
    ADD `download_allowance` BIGINT( 20 ) NULL DEFAULT NULL AFTER  `period`;
ALTER TABLE `users`
    ADD `remainingBWDownload` BIGINT NULL DEFAULT NULL AFTER  `paidExpiryDate`;

CREATE TABLE `file_folder_share`
(
    `id`                 INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `folder_id`          INT(11) NOT NULL,
    `access_key`         INT(64) NOT NULL,
    `date_created`       DATETIME NOT NULL,
    `last_accessed`      DATETIME NOT NULL,
    `created_by_user_id` INT(11) NOT NULL,
    INDEX (`folder_id`, `created_by_user_id`),
    UNIQUE (`access_key`)
) ENGINE = MyISAM;
ALTER TABLE `file_folder_share` CHANGE `access_key` `access_key` VARCHAR ( 64 ) NOT NULL;

ALTER TABLE `language`
    ADD `language_code` VARCHAR(5) NULL DEFAULT NULL;
UPDATE language
SET `language_code` = `flag`;

INSERT INTO site_config
VALUES (NULL, 'google_translate_api_key', '',
        'Google Translate API key. Optional but needed if you use the automatic language translation tool within the admin area.',
        '', 'string', 'Language');

ALTER TABLE `file_server`
    ADD `serverAccess` TEXT NULL;

ALTER TABLE `file_folder`
    ADD `watermarkPreviews` TINYINT( 1 ) NOT NULL AFTER  `coverImageId` ,
ADD  `showDownloadLinks` TINYINT( 1 ) NOT NULL AFTER  `watermarkPreviews`;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'blocked_filename_keywords', 'yetishare|wurlie|reservo',
        'Any filenames with the keywords listed here will be blocked from uploading. Keep in mind that this is a partial string search, so blocking the word "exe" will also block the word "exercise". Pipe separated list. i.e. word1|word2|word3',
        '', 'string', 'File Uploads');

ALTER TABLE `site_config` CHANGE `config_value` `config_value` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'system_plugin_config_cache', '',
        'Used internally by the system to store a cache of the plugin settings.', '', 'string', 'System');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'system_theme_config_cache', '', 'Used internally by the system to store a cache of the theme settings.',
        '', 'string', 'System');

CREATE TABLE `file_block_hash`
(
    `id`           INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `file_hash`    VARCHAR(32) NOT NULL,
    `date_created` DATETIME    NOT NULL,
    UNIQUE (`file_hash`)
) ENGINE = MYISAM;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'adblock_limiter', 'Disabled',
        'Block users from the site if they are using adblock within their browser, a message is shown telling them to disable it. Block download pages only or block access to the entire site. This limitation only applies to users which are shown adverts.',
        '["Disabled","Block Download Pages", "Block Entire Site"]', 'select', 'Adverts');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'uploads_block_all', 'no',
        'Whether to block all uploads on your site, apart from the admin user. Useful as a temporary setting for site maintenance',
        '["yes", "no"]', 'select', 'File Uploads');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'downloads_block_all', 'no',
        'Whether to block all downloads on your site, apart from the admin user. Useful as a temporary setting for site maintenance',
        '["yes", "no"]', 'select', 'File Downloads');

ALTER TABLE `language_content`
    ADD `is_locked` INT( 1 ) NOT NULL DEFAULT  '0';
UPDATE `language_content`
SET is_locked = 1;
