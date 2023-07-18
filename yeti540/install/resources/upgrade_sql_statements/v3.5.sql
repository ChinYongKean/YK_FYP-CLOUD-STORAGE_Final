ALTER TABLE `file` CHANGE `fileType` `fileType` VARCHAR ( 150 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
WHERE extension = 'xlsx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.template'
WHERE extension = 'xltx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.presentationml.template'
WHERE extension = 'potx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.presentationml.slideshow'
WHERE extension = 'ppsx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
WHERE extension = 'pptx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.presentationml.slide'
WHERE extension = 'sldx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
WHERE extension = 'docx';
UPDATE file
SET fileType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.template'
WHERE extension = 'dotx';
UPDATE file
SET fileType = 'application/vnd.ms-excel.addin.macroEnabled.12'
WHERE extension = 'xlam';
UPDATE file
SET fileType = 'application/vnd.ms-excel.sheet.binary.macroEnabled.12'
WHERE extension = 'xlsb';

CREATE TABLE IF NOT EXISTS `download_token`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `token` varchar
(
    64
) COLLATE utf8_bin NOT NULL,
    `user_id` int
(
    11
) DEFAULT NULL,
    `ip_address` varchar
(
    15
) COLLATE utf8_bin NOT NULL,
    `file_id` int
(
    11
) NOT NULL,
    `created` datetime NOT NULL,
    `expiry` datetime NOT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `token`
(
    `token`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin AUTO_INCREMENT=1;

UPDATE `site_config`
SET `config_group` = 'Premium User Settings'
WHERE `config_key` = 'premium_user_max_remote_urls';

ALTER TABLE `file` CHANGE `adminNotes` `adminNotes` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE `users` CHANGE `lastloginip` `lastloginip` VARCHAR ( 32 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;

ALTER TABLE `file_server`
    ADD `routeViaMainSite` INT( 1 ) NOT NULL DEFAULT '0';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'file_manager_default_view', 'icon', 'The default view for the file manager.', '["icon", "list"]',
        'select', 'File Manager');
INSERT INTO site_config (SELECT null,
                                'site_contact_form_email',
                                config_value,
                                'The email address all contact form queries will be sent',
                                '',
                                'string',
                                'Contact Form'
                         FROM site_config
                         WHERE config_key = 'site_admin_email');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'contact_form_show_captcha', 'yes', 'Show the captcha on the contact form.', '["yes","no"]', 'select',
        'Contact Form');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'performance_js_file_minify', 'no',
        'Whether to automatically group and minify js files. ''yes'' increases page load times. Use ''no'' if you have any issues or in dev. The ''cache'' folder must be writable.',
        '["yes","no"]', 'select', 'Site Options');

ALTER TABLE `language_content` CHANGE `languageKeyId` `languageKeyId` INT ( 11 ) NOT NULL;
ALTER TABLE `stats` DROP INDEX `page_title`;
ALTER TABLE `stats` DROP INDEX `dt`;
ALTER TABLE `stats` CHANGE `page_title` `file_id` INT ( 11 ) NOT NULL;
ALTER TABLE `stats`
    ADD INDEX ( `file_id` );
ALTER TABLE `stats` CHANGE `dt` `download_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `stats`
    ADD INDEX ( `download_date` );
ALTER TABLE `stats` DROP `img_search`;
ALTER TABLE `stats` CHANGE `country` `country` VARCHAR ( 6 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
ALTER TABLE `stats` DROP `url`;
ALTER TABLE `stats` DROP `os_version`;
ALTER TABLE `stats` DROP `browser_version`;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'non_user_show_upgrade_page', 'yes', 'Show the premium account upgrade page for non logged in users.',
        '["yes","no"]', 'select', 'Non User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_show_upgrade_page', 'yes', 'Show the premium account upgrade page for logged in free users.',
        '["yes","no"]', 'select', 'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'paid_user_show_upgrade_page', 'yes', 'Show the premium account upgrade page for paid users.',
        '["yes","no"]', 'select', 'Premium User Settings');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'non_user_max_download_threads', '1',
        'The maximum concurrent downloads a non user can do at once. Set to 0 (zero) for no limit. Note: Ensure the ''downloads_track_current_downloads'' is also set to ''yes'' to enable this.',
        '', 'integer', 'Non User Settings');
UPDATE `site_config`
SET `config_description` = 'The maximum concurrent downloads a free user can do at once. Set to 0 (zero) for no limit. Note: Ensure the ''downloads_track_current_downloads'' is also set to ''yes'' to enable this.'
WHERE `site_config`.`config_key` = 'free_user_max_download_threads';

ALTER TABLE `language_key`
    ADD `foundOnScan` INT( 1 ) NOT NULL DEFAULT '0';
DELETE
FROM language_content
WHERE languageKeyId IN (SELECT id
                        FROM language_key
                        WHERE languageKey IN ('not_permitted_to_create_urls_on_site', 'error_with_url',
                                              'can_not_create_url_on_this_site', 'date_entered_is_incorrect',
                                              'custom_short_url_already_exits', 'problem_creating_short_url',
                                              'access_restricted_enter_password', 'redirecting_to',
                                              'shorturl_filter_disabled', 'account_home', 'email_confirm', 'stats'));
DELETE
FROM language_key
WHERE languageKey IN ('not_permitted_to_create_urls_on_site', 'error_with_url', 'can_not_create_url_on_this_site',
                      'date_entered_is_incorrect', 'custom_short_url_already_exits', 'problem_creating_short_url',
                      'access_restricted_enter_password', 'redirecting_to', 'shorturl_filter_disabled', 'account_home',
                      'email_confirm', 'stats');
DELETE
FROM language_content
WHERE languageKeyId NOT IN (SELECT id FROM language_key);

CREATE TABLE IF NOT EXISTS `download_page`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `download_page` varchar
(
    100
) COLLATE utf8_bin NOT NULL,
    `user_level_id` int
(
    11
) NOT NULL,
    `page_order` int
(
    5
) NOT NULL DEFAULT '0',
    `additional_javascript_code` text COLLATE utf8_bin NOT NULL,
    `additional_settings` text COLLATE utf8_bin NOT NULL,
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin AUTO_INCREMENT=3;

ALTER TABLE `users`
    ADD `privateFileStatistics` INT( 1 ) NOT NULL DEFAULT '0';

INSERT INTO `download_page` (SELECT null,
                                    '_download_page_compare_timed.inc.php',
                                    0,
                                    1,
                                    '',
                                    CONCAT('{"download_wait":', config_value, '}')
                             FROM site_config
                             WHERE config_key = 'non_user_redirect_delay_seconds');
INSERT INTO `download_page` (SELECT null,
                                    '_download_page_compare_timed.inc.php',
                                    1,
                                    1,
                                    '',
                                    CONCAT('{"download_wait":', config_value, '}')
                             FROM site_config
                             WHERE config_key = 'free_user_redirect_delay_seconds');
DELETE
FROM `site_config`
WHERE config_key = 'non_user_redirect_delay_seconds';
DELETE
FROM `site_config`
WHERE config_key = 'free_user_redirect_delay_seconds';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'non_user_show_adverts', 'yes', 'Show adverts for non logged in users.', '["yes","no"]', 'select',
        'Non User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_show_adverts', 'yes', 'Show adverts for logged in free users.', '["yes","no"]', 'select',
        'Free User Settings');
INSERT INTO `site_config` (SELECT null,
                                  'paid_user_show_adverts',
                                  IF(config_value = 'hide', 'no', 'yes'),
                                  'Show adverts for paid users.',
                                  '["yes","no"]',
                                  'select',
                                  'Premium User Settings'
                           FROM site_config
                           WHERE config_key = 'adverts_show_to_premium');
DELETE
FROM `site_config`
WHERE config_key = 'adverts_show_to_premium';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'paid_user_allow_uploads', 'yes', 'Allow paid users to upload.', '["yes","no"]', 'select',
        'Premium User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_allow_uploads', 'yes', 'Allow free users to upload.', '["yes","no"]', 'select',
        'Free User Settings');
INSERT INTO `site_config` (SELECT null,
                                  'non_user_allow_uploads',
                                  IF(config_value = 'no', 'yes', 'no'),
                                  'Allow non logged in users to upload.',
                                  '["yes","no"]',
                                  'select',
                                  'Non User Settings'
                           FROM site_config
                           WHERE config_key = 'require_user_account_upload');
DELETE
FROM `site_config`
WHERE config_key = 'require_user_account_upload';
