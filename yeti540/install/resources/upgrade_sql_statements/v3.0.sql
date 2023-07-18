ALTER TABLE `users`
    ADD `identifier` VARCHAR(32) NOT NULL;
ALTER TABLE `premium_order`
    ADD `upgrade_file_id` INT( 11 ) NOT NULL;
ALTER TABLE `premium_order`
    ADD `upgrade_user_id` INT( 11 ) NOT NULL;
UPDATE `site_config`
SET `config_group` = 'Premium User Settings'
WHERE `site_config`.`id` = 41;
UPDATE `site_config`
SET availableValues = REPLACE(availableValues, '\'', '"');
ALTER TABLE `language` ADD `isLocked` INT( 1 ) NOT NULL;
UPDATE `language` SET `isLocked` = '1' WHERE `language`.`id` =1;
ALTER TABLE `language` ADD `flag` VARCHAR( 20 ) NOT NULL;
UPDATE `language` SET `flag` = 'us' WHERE `language`.`id` =1;
UPDATE `language_content` SET content = REPLACE(content , ':', '') WHERE languageId=1;
UPDATE `language_key` SET defaultContent = REPLACE(defaultContent, ':', '');

ALTER TABLE `users` ADD INDEX ( `level` );
ALTER TABLE `users` ADD INDEX ( `datecreated` );
ALTER TABLE `users` ADD INDEX ( `username` );
ALTER TABLE `stats` ADD INDEX ( `dt` );
ALTER TABLE `stats` ADD INDEX ( `page_title` );
ALTER TABLE `banned_ips` ADD INDEX ( `ipAddress` );
ALTER TABLE `file` ADD INDEX ( `shortUrl` );
ALTER TABLE `file` ADD INDEX ( `originalFilename` );
ALTER TABLE `file` ADD INDEX ( `fileSize` );
ALTER TABLE `file` ADD INDEX ( `visits` );
ALTER TABLE `file` ADD INDEX ( `lastAccessed` );
ALTER TABLE `file` ADD INDEX ( `extension` );
ALTER TABLE `site_config` ADD INDEX ( `config_key` );

CREATE TABLE `download_tracker` (
`id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`file_id` INT( 11 ) NOT NULL ,
`ip_address` VARCHAR( 15 ) NOT NULL ,
`date_started` DATETIME NOT NULL ,
`date_updated` DATETIME NOT NULL ,
`date_finished` DATETIME NOT NULL ,
`status` ENUM( 'downloading','finished','error','cancelled' ) NOT NULL
) ENGINE = MyISAM;

ALTER TABLE `download_tracker` ADD INDEX ( `ip_address` );
ALTER TABLE `download_tracker` ADD INDEX ( `date_updated` );
ALTER TABLE `download_tracker` ADD INDEX ( `status` );
ALTER TABLE `download_tracker` ADD INDEX ( `file_id` );
ALTER TABLE `download_tracker` ADD `start_offset` BIGINT( 20 ) NOT NULL;
ALTER TABLE `download_tracker` ADD `seek_end` BIGINT( 20 ) NOT NULL;

UPDATE `site_config` SET `availableValues` = 'SELECT serverLabel AS itemValue FROM file_server LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id WHERE statusId=2 ORDER BY serverLabel' WHERE `config_key` = 'default_file_server';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`) VALUES (NULL, 'reserved_usernames', 'admin|administrator|localhost|support|billing|sales|payments', 'Any usernames listed here will be blocked from the main registration. Pipe separated list.', '', 'string', 'Default');

ALTER TABLE `language` ADD `isActive` INT( 1 ) NOT NULL DEFAULT '1' AFTER `isLocked`;

UPDATE `site_config` SET `config_description` = 'Site language for text conversions <a href="translation_manage.php">(manage languages)</a>' WHERE `config_key` = 'site_language';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`) VALUES (NULL, 'show_multi_language_selector', 'hide', 'Whether to show or hide the multi language selector on the site.', '["hide","show"]', 'select', 'Language');

ALTER TABLE `file_server` DROP `connectionMethod`;
ALTER TABLE `file_server` CHANGE `serverType` `serverType` ENUM( 'remote', 'local', 'ftp', 'sftp' ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
UPDATE `file_server`
SET `serverType` = 'ftp'
WHERE `serverType` = 'remote';

ALTER TABLE `file_server`
    ADD `sftpHost` VARCHAR(255) NOT NULL AFTER `ftpPassword` ,
ADD `sftpPort` INT(11) NOT NULL DEFAULT '22' AFTER `sftpHost` ,
ADD `sftpUsername` VARCHAR( 50 ) NOT NULL AFTER `sftpPort` ,
ADD `sftpPassword` VARCHAR( 50 ) NOT NULL AFTER `sftpUsername`;

CREATE TABLE `plugin`
(
    `id`                 INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plugin_name`        VARCHAR(150) NOT NULL,
    `folder_name`        VARCHAR(100) NOT NULL,
    `plugin_description` VARCHAR(255) NOT NULL,
    `is_installed`       INT( 1 ) NOT NULL DEFAULT '0',
    `date_installed`     DATETIME     NOT NULL
) ENGINE = MYISAM;

ALTER TABLE `plugin`
    ADD `plugin_settings` TEXT NOT NULL;
ALTER TABLE `plugin`
    ADD `plugin_enabled` INT( 1 ) NOT NULL DEFAULT '1';

ALTER TABLE `file`
    ADD `adminNotes` TEXT NOT NULL;

INSERT INTO site_config (config_key, config_value, config_description, config_type, config_group)
SELECT 'site_admin_email',
       config_value,
       'The email address all site admin emails will be sent.',
       'string',
       'Page Options'
FROM site_config
WHERE config_key = 'report_abuse_email';

