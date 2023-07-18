ALTER TABLE `download_token`
    ADD `process_ppd` int(1) NOT NULL DEFAULT '1';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Script Version Number', 'script_version_number', '2.0', 'System value. The current script version number.', '',
        'integer', 'System', '0');
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Admin Approve Registration', 'admin_approve_registrations', 'no',
        'Whether admin should manually approve all account registrations.', '[\"yes\",\"no\"]', 'select',
        'Site Options', '31');
ALTER TABLE `users` CHANGE `status` `status` enum('active','pending','disabled','suspended','awaiting approval') COLLATE 'utf8_unicode_ci' NOT NULL DEFAULT 'active' AFTER `lastloginip`;

ALTER TABLE `file_folder`
    ADD UNIQUE `urlHash` (`urlHash`);
UPDATE file_folder
SET urlHash = MD5(CONCAT(NOW(), RAND(), UUID()))
WHERE urlHash IS NULL;

ALTER TABLE `plugin_payment_gateways`
    ADD `gateway_additional_params` TEXT NULL AFTER `available`;

UPDATE plugin_payment_gateways
SET gateway_type = 'onsite';
UPDATE plugin_payment_gateways
SET gateway_type = 'offsite'
WHERE class_name = 'BitPay';
UPDATE plugin_payment_gateways
SET gateway_type = 'offsite'
WHERE class_name = 'Mollie';
UPDATE plugin_payment_gateways
SET gateway_type = 'offsite'
WHERE class_name = 'TargetPay_Ideal';

UPDATE plugin_payment_gateways
SET gateway_additional_params = '{"description":true,"returnUrl":true}'
WHERE class_name = 'Mollie';
UPDATE plugin_payment_gateways
SET gateway_additional_params = '{"description":true,"returnUrl":true,"issuer":true}'
WHERE class_name = 'TargetPay_Ideal';

UPDATE `site_config`
SET `availableValues` = '[\"recaptcha\",\"solvemedia\",\"cryptoloot\"]'
WHERE `config_key` = 'captcha_type';
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`)
VALUES ('Cryptoloot Public Key', 'captcha_cryptoloot_public_key', '',
        'Public site key for cryptoloot captcha, if enabled. Register at https://crypto-loot.com', '', 'string',
        'Captcha');
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`)
VALUES ('Cryptoloot Private Key', 'captcha_cryptoloot_private_key', '',
        'Private site key for cryptoloot captcha, if enabled. Register at http://crypto-loot.com', '', 'string',
        'Captcha');

UPDATE `site_config`
SET display_order = 40
WHERE config_key = 'captcha_cryptoloot_public_key';
UPDATE `site_config`
SET display_order = 45
WHERE config_key = 'captcha_cryptoloot_private_key';

INSERT INTO `file_server_container` (`id`, `label`, `entrypoint`, `expected_config_json`, `is_enabled`)
VALUES (NULL, 'Backblaze B2', 'flysystem_backblaze_b2',
        '{\"account_id\":{\"label\":\"Master Key Id\",\"type\":\"text\",\"default\":\"\"},\"application_key\":{\"label\":\"Master Application Key (Master Only Supported)\",\"type\":\"text\",\"default\":\"\"},\"bucket\":{\"label\":\"Bucket Name\",\"type\":\"text\",\"default\":\"\"}}',
        1);

ALTER TABLE `banned_ips` RENAME TO `banned_ip`;

UPDATE language_content
SET content = REPLACE(content, '.[[[PAGE_EXTENSION]]]', '')
WHERE content LIKE '%.[[[PAGE_EXTENSION]]]%';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, '.[[[PAGE_EXTENSION]]]', '')
WHERE defaultContent LIKE '%.[[[PAGE_EXTENSION]]]%';
UPDATE language_content
SET content = REPLACE(content, '.[[[SITE_CONFIG_PAGE_EXTENSION]]]', '')
WHERE content LIKE '%.[[[SITE_CONFIG_PAGE_EXTENSION]]]%';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, '.[[[SITE_CONFIG_PAGE_EXTENSION]]]', '')
WHERE defaultContent LIKE '%.[[[SITE_CONFIG_PAGE_EXTENSION]]]%';

DELETE
FROM site_config
WHERE config_key = 'page_extension';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Enable Application Cache', 'enable_application_cache', 'yes',
        'Whether to activate application cache or not. This will cache the Twig templates and url routes to improve performance.',
        '["yes","no"]', 'select', 'Site Options', 6);

UPDATE download_page
SET download_page = REPLACE(download_page, '_download_page_captcha.inc.php', 'captcha.html.twig');
UPDATE download_page
SET download_page = REPLACE(download_page, '_download_page_compare_all.inc.php', 'compare_all.html.twig');
UPDATE download_page
SET download_page = REPLACE(download_page, '_download_page_compare_timed.inc.php', 'compare_timed.html.twig');
UPDATE download_page
SET download_page = REPLACE(download_page, '_download_page_file_info.inc.php', 'file_info.html.twig');
UPDATE download_page
SET download_page = REPLACE(download_page, '_download_page_timed.inc.php', 'timed.html.twig');

ALTER TABLE `site_config` CHANGE `config_description` `config_description` text COLLATE 'utf8_general_ci' NOT NULL AFTER `config_value`;
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('User Session Type', 'user_session_type', 'Database Sessions',
        'Login session type. If you are using any "Direct" file servers, that must be "Database Sessions", using "Local Sessions" will break cross server support. If you enable a "Direct" file server, this is automatically changed to "Database Sessions". After changing you will need to re-login.',
        '["Local Sessions", "Database Sessions"]', 'select', 'Site Options', 59);
UPDATE `site_config`
SET `config_value` = (SELECT IF(COUNT(id) > 0, 'Database Sessions', 'Local Sessions')
                      FROM file_server
                      WHERE serverType = 'direct')
WHERE `config_key` = 'user_session_type';

CREATE TABLE `file_folder_share_item`
(
    `id`                   int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `file_folder_share_id` int(11) NOT NULL,
    `file_id`              int(11) NULL,
    `folder_id`            int(11) NULL,
    `date_created`         datetime NOT NULL
) ENGINE='InnoDB' COLLATE 'utf8_bin';

ALTER TABLE `file_folder_share_item`
    ADD INDEX `file_folder_share_id` (`file_folder_share_id`),
ADD INDEX `file_id` (`file_id`),
ADD INDEX `folder_id` (`folder_id`);

INSERT INTO `file_folder_share_item` (`file_folder_share_id`, `folder_id`, `date_created`) (SELECT `id`, `folder_id`, `date_created` FROM `file_folder_share`);
ALTER TABLE `file_folder_share` DROP `folder_id`;
ALTER TABLE `file_folder_share`
    ADD `is_global` int(1) NOT NULL DEFAULT '0' AFTER `shared_with_user_id`;

ALTER TABLE `file_folder_share` CHANGE `access_key` `access_key` varchar (128) COLLATE 'latin1_swedish_ci' NOT NULL AFTER `id`;
ALTER TABLE `file_folder_share`
    ADD INDEX `is_global` (`is_global`);

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Support Legacy Folder Urls', 'support_legacy_folder_urls', 'Disabled',
        'Whether to support legacy public folder urls or not. In the recent code these are made using a unique 32 character length hash, whereas older urls used the shorter folder id.',
        '["Enabled", "Disabled"]', 'select', 'File Manager', 99);

ALTER TABLE `file_folder`
    ADD `addedUserId` int(11) NULL AFTER `userId`;
UPDATE `file_folder`
SET `addedUserId` = `userId`;

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Lock Download Tokens To IP', 'lock_download_tokens_to_ip', 'Disabled',
        'Whether to lock downloads to the original requesting IP address for additional leech protection. Note: This will cause the document viewer to stop working if you are using this functionality.',
        '["Enabled", "Disabled"]', 'select', 'File Downloads', 99);

UPDATE `plugin`
SET is_installed = 1
WHERE folder_name = 'fileimport';

UPDATE `site_config`
SET `config_value` = '32'
WHERE `config_key` = 'password_policy_max_length'
  AND `config_value` = '8';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Username min length', 'username_min_length', '6', 'The minimum character length for a username.', '', 'string',
        'Security', 40);
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Username max length', 'username_max_length', '20', 'The maximum character length for a username.', '',
        'string', 'Security', 41);

DELETE
FROM `site_config`
WHERE `config_key` = 'script_version_number' LIMIT 1;
UPDATE `site_config`
SET `config_description` = 'Secret key for reCaptcha v2. Register at https://www.google.com/recaptcha'
WHERE config_key = 'captcha_secret_key';
UPDATE `site_config`
SET `config_description` = 'Public key for reCaptcha v2. Register at https://www.google.com/recaptcha'
WHERE config_key = 'captcha_public_key';

UPDATE language_content
SET content = REPLACE(content, 'contact.html', 'contact')
WHERE content LIKE '%contact.html%';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'contact.html', 'contact')
WHERE defaultContent LIKE '%contact.html%';

UPDATE language_content
SET content = REPLACE(content, 'SEARCH FILES', 'Search Files')
WHERE content = 'SEARCH FILES';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'SEARCH FILES', 'Search Files')
WHERE defaultContent = 'SEARCH FILES';

UPDATE language_content
SET content = REPLACE(content, 'premium', 'Premium')
WHERE content = 'premium';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'premium', 'Premium')
WHERE defaultContent = 'premium';

UPDATE language_content
SET content = REPLACE(content, 'faq', 'FAQ')
WHERE content = 'faq';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'faq', 'FAQ')
WHERE defaultContent = 'faq';

UPDATE language_content
SET content = REPLACE(content, 'Q: ', '')
WHERE content LIKE 'Q: %';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'Q: ', '')
WHERE defaultContent = 'Q: %';

UPDATE language_content
SET content = REPLACE(content, 'A: ', '')
WHERE content LIKE 'A: %';
UPDATE language_key
SET defaultContent = REPLACE(defaultContent, 'A: ', '')
WHERE defaultContent = 'A: %';

ALTER TABLE `users` CHANGE `title` `title` varchar (10) COLLATE 'utf8_unicode_ci' NULL AFTER `status`;

ALTER TABLE `premium_order`
    CHANGE `upgrade_file_id` `upgrade_file_id` int (11) NULL AFTER `date_created`,
    CHANGE `upgrade_user_id` `upgrade_user_id` int (11) NULL AFTER `upgrade_file_id`;

UPDATE `premium_order`
SET upgrade_file_id = NULL
WHERE upgrade_file_id = 0;
UPDATE `premium_order`
SET upgrade_user_id = NULL
WHERE upgrade_user_id = 0;

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
SELECT 'Advert File Manager View File Top',
       'advert_file_manager_view_file_top',
       '',
       'Advert shown on the view file page above the tabs.',
       '',
       'textarea',
       'Adverts',
       '11'
FROM `site_config`
WHERE ((`id` = '16'));

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
SELECT 'Advert File Manager View File Bottom',
       'advert_file_manager_view_file_bottom',
       '',
       'Advert shown on the view file page below the tabs.',
       '',
       'textarea',
       'Adverts',
       '12'
FROM `site_config`
WHERE ((`id` = '16'));

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
SELECT 'Advert File Manager View File Right',
       'advert_file_manager_view_file_right',
       '',
       'Advert shown on the view file page on the right-hand side.',
       '',
       'textarea',
       'Adverts',
       '13'
FROM `site_config`
WHERE ((`id` = '16'));

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
SELECT 'Advert File Manager Left Bar',
       'advert_file_manager_left_bar',
       '',
       'Advert shown on the bottom-left of the file manager.',
       '',
       'textarea',
       'Adverts',
       '14'
FROM `site_config`
WHERE ((`id` = '16'));

UPDATE `download_page`
SET download_page = 'compare_timed.html.twig'
WHERE download_page = '_download_page_compare_timed.inc.php';
UPDATE `download_page`
SET download_page = 'simple_timed.html.twig'
WHERE download_page = '_download_page_timed.inc.php';
UPDATE `download_page`
SET download_page = 'compare_all.html.twig'
WHERE download_page = '_download_page_compare_all.inc.php';
UPDATE `download_page`
SET download_page = 'file_info.html.twig'
WHERE download_page = '_download_page_file_info.inc.php';

ALTER TABLE `users`
    CHANGE `firstname` `firstname` varchar (150) COLLATE 'utf8_unicode_ci' NULL AFTER `title`,
    CHANGE `lastname` `lastname` varchar (150) COLLATE 'utf8_unicode_ci' NULL AFTER `firstname`;

UPDATE `user_level`
SET `label` = 'temp user'
WHERE `id` = '0'
  AND `label` = 'non user';

ALTER TABLE `user_level`
    ADD `delete_account_after_days` int(11) NOT NULL DEFAULT '0' AFTER `blocked_file_types`;

UPDATE site_config
SET config_value = REPLACE(config_value, "main_logo_inverted.png", "logo/logo-whitebg.png");

UPDATE `site_config`
SET `config_value` = '<a href="https://yetishare.com" target="_blank"><img src="https://via.placeholder.com/468x60?text=468x60+Advert"/></a>'
WHERE (config_value = '' OR config_value LIKE "%dreamhost%")
  AND `config_group` = 'Adverts';
UPDATE `site_config`
SET `config_value` = '<a href="https://yetishare.com" target="_blank"><img src="https://via.placeholder.com/250x250?text=250x250"/></a>'
WHERE `config_key` = 'advert_file_manager_left_bar';
UPDATE `site_config`
SET `config_value` = '<a href="https://yetishare.com" target="_blank"><img src="https://via.placeholder.com/300x600?text=300x600"/></a>'
WHERE `config_key` = 'advert_file_manager_view_file_right';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('New Account Default File Privacy', 'new_account_default_file_privacy', 'Private',
        'When a new account is created, this sets the default file privacy option in their account settings.',
        '["Private", "Public"]', 'select', 'Site Options', 100);

INSERT INTO `plugin` (`id`, `plugin_name`, `folder_name`, `plugin_description`, `is_installed`, `date_installed`,
                      `plugin_settings`, `plugin_enabled`, `load_order`)
VALUES (null, 'File Previewer', 'filepreviewer', 'Display files directly within the file manager.', 1, NULL,
        '{\"allow_direct_links\":0,\"non_show_viewer\":1,\"free_show_viewer\":1,\"paid_show_viewer\":1,\"enable_preview_image\":1,\"preview_image_show_thumb\":1,\"auto_rotate\":1,\"supported_image_types\":\"jpg,jpeg,png,gif,wbmp\",\"enable_preview_document\":1,\"preview_document_pdf_thumbs\":1,\"preview_document_ext\":\"doc,docx,xls,xlsx,ppt,pptx,pdf,pages,ai,psd,tiff,dxf,svg,eps,ps,ttf,otf,xps\",\"enable_preview_video\":1,\"preview_video_ext\":\"mp4,flv,ogg\",\"enable_preview_audio\":1,\"preview_audio_ext\":\"mp3\",\"caching\":1,\"image_quality\":90}',
        1, 999);

DELETE
FROM `plugin`
WHERE folder_name = 'docviewer' LIMIT 1;

DROP TABLE IF EXISTS `plugin_filepreviewer_meta`;
CREATE TABLE `plugin_filepreviewer_meta`
(
    `id`             int(11) NOT NULL AUTO_INCREMENT,
    `file_id`        int(11) NOT NULL,
    `width`          int(8) NOT NULL,
    `height`         int(8) NOT NULL,
    `raw_data`       text COLLATE utf8_bin,
    `date_taken`     datetime                      DEFAULT NULL,
    `image_colors`   varchar(100) COLLATE utf8_bin DEFAULT NULL,
    `image_bg_color` varchar(7) COLLATE utf8_bin   DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY              `file_id` (`file_id`),
    KEY              `image_bg_color` (`image_bg_color`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `plugin_filepreviewer_watermark`;
CREATE TABLE `plugin_filepreviewer_watermark`
(
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `file_name`     varchar(255) NOT NULL,
    `image_content` blob         NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

UPDATE theme
SET date_installed = '2038-01-01 00:00:00'
WHERE CAST(date_installed AS CHAR(20)) = '0000-00-00 00:00:00';
ALTER TABLE `theme` CHANGE `date_installed` `date_installed` datetime NULL AFTER `is_installed`;
UPDATE theme
SET date_installed = NULL
WHERE date_installed = '2038-01-01 00:00:00';

INSERT INTO `theme` (`id`, `theme_name`, `folder_name`, `theme_description`, `author_name`, `author_website`,
                     `is_installed`, `date_installed`, `theme_settings`)
VALUES (null, 'Spirit Theme', 'spirit', 'Bootstrap Yetishare theme included with the core script.', 'Yetishare',
        'https://yetishare.com', 1, null, '');
UPDATE `site_config`
SET `config_value` = '{"spirit":{"data":{"theme_name":"Spirit Theme","folder_name":"spirit","theme_description":"Bootstrap Yetishare theme included with the core script.","author_name":"Yetishare","author_website":"https:\/\/yetishare.com","is_installed":"1","date_installed":null,"theme_settings":""},"config":{"theme_name":"Spirit Theme","folder_name":"spirit","theme_description":"Bootstrap Yetishare theme included with the core script.","author_name":"Yetishare","author_website":"https:\/\/yetishare.com","theme_version":"1.0","required_script_version":"5.0","product":"file_hosting","product_name":"Yetishare","product_url":"https:\/\/yetishare.com"}}}'
WHERE config_key = 'system_theme_config_cache' LIMIT 1;
UPDATE `theme`
SET is_installed = 0;
UPDATE `theme`
SET is_installed = 1
WHERE folder_name = 'spirit' LIMIT 1;
UPDATE `site_config`
SET `config_value` = 'spirit'
WHERE config_key = 'site_theme' LIMIT 1;

ALTER TABLE `plugin_filepreviewer_watermark`
    ADD `category` varchar(20) NOT NULL DEFAULT 'images';

DROP TABLE IF EXISTS `plugin_filepreviewer_background_thumb`;
CREATE TABLE `plugin_filepreviewer_background_thumb`
(
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `file_id`         int(11) NOT NULL,
    `thumb_status`    enum('processing','failed','created','nonimage') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `date_added`      datetime NOT NULL,
    `processing_time` decimal(5, 2) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY               `file_id` (`file_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE `file_server`
    ADD `geoUploadCountries` varchar(1000) COLLATE 'utf8_general_ci' NULL;

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Concurrent Uploads', 'file_manager_concurrent_uploads', 'Enabled',
        'Whether to process concurrent uploads or one at a time. Enabling will speed up the uploader. For sites with limited resources available, you should disable this.',
        '["Disabled", "Enabled"]', 'select', 'File Manager', 101);
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Concurrent Upload Limit', 'file_manager_concurrent_upload_limit', '3',
        'If concurrent uploads is enabled, limit the concurrent upload requests here.',
        '["1", "2", "3", "4", "5", "6", "7", "8"]', 'select', 'File Manager', 102);
