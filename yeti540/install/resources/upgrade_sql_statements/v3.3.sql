ALTER TABLE `download_tracker`
    ADD `download_username` VARCHAR(65) NULL AFTER `ip_address`;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_max_concurrent_uploads', '50',
        'The maximum amount of files that can be uploaded at the same time for free users.', '', 'integer',
        'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'premium_user_max_concurrent_uploads', '100',
        'The maximum amount of files that can be uploaded at the same time for paid users.', '', 'integer',
        'Premium User Settings');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'register_form_show_captcha', 'no', 'Whether to display the captcha on the site registration form.',
        '["yes","no"]', 'select', 'Captcha');

ALTER TABLE `users`
    ADD `apikey` VARCHAR(32) NOT NULL;

DELETE
FROM site_config
WHERE config_key = 'paypal_payments_email_address';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'downloads_track_current_downloads', 'yes',
        'Whether to track current downloads/connections in the admin area. Note: This should be enabled if you also want to limit concurrent download connections.',
        '["yes","no"]', 'select', 'File Downloads');

UPDATE `site_config`
SET `config_description` = 'The maximum concurrent downloads a non/free user can do at once. Set to 0 (zero) for no limit. Note: Ensure the \' downloads_track_current_downloads\' is also set to ''yes'' to enable this.'
WHERE `site_config`.`config_key` = 'free_user_max_download_threads';

ALTER TABLE `download_tracker`
    ADD INDEX(`download_username`);

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'paid_user_max_download_threads', '0',
        'The maximum concurrent downloads a paid user can do at once. Set to 0 (zero) for no limit. Note: Ensure the \'downloads_track_current_downloads\' is also set to \'yes\' to enable this.',
        '', 'integer', 'Premium User Settings');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_wait_between_downloads', '0',
        'How long a free user must wait between downloads, in seconds. Set to 0 (zero) to disable. Note: Ensure the \'downloads_track_current_downloads\' is also set to \'yes\' to enable this.',
        '', 'integer', 'Free User Settings');

ALTER TABLE `file_server` CHANGE `ipAddress` `ipAddress` VARCHAR ( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `file_server` CHANGE `serverType` `serverType` ENUM('remote','local','ftp','sftp','direct') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `file_server`
    ADD `fileServerDomainName` VARCHAR(255) NULL,  ADD `scriptPath` VARCHAR(150) NULL;
ALTER TABLE `file`
    ADD `accessPassword` VARCHAR(32) NULL;

CREATE TABLE `session_transfer`
(
    `id`           INT(11) NULL,
    `transfer_key` VARCHAR(32) NOT NULL,
    `session_id`   VARCHAR(32) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = MyISAM;
ALTER TABLE `session_transfer`
    ADD `date_added` DATETIME NOT NULL;
ALTER TABLE `session_transfer`
    ADD INDEX ( `date_added` );
ALTER TABLE `session_transfer`
    ADD INDEX ( `session_id` );
ALTER TABLE `session_transfer`
    ADD INDEX ( `transfer_key` );
ALTER TABLE `session_transfer` CHANGE `id` `id` INT ( 11 ) NOT NULL AUTO_INCREMENT;
