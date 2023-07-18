ALTER TABLE `download_tracker`
    ADD INDEX ( `date_started` );
ALTER TABLE `sessions`
    ADD INDEX ( `updated_on` );
ALTER TABLE `file`
    ADD INDEX ( `folderId` );
ALTER TABLE `file`
    ADD INDEX ( `serverId` );

UPDATE `site_config`
SET `config_value`       = 'html',
    `config_description` = 'The page extension to use on the site',
    `availableValues`    = '["html"]'
WHERE `config_key` = 'page_extension';

ALTER TABLE `file_action` CHANGE `file_action` `file_action` ENUM( 'delete', 'move', 'copy' ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `file_action`
    ADD `action_detail` TEXT NULL AFTER  `file_action`;

ALTER TABLE `users` CHANGE `password` `password` VARCHAR ( 100 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;

DELETE
FROM site_config
WHERE config_description LIKE '%email address new account registrations will be sent from%'
  AND (SELECT total
       FROM (SELECT COUNT(sc.id) AS total FROM site_config sc WHERE sc.config_key = 'default_email_address_from') x) >
      1;

ALTER TABLE `stats` CHANGE `ip` `ip` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `download_tracker` CHANGE `ip_address` `ip_address` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `banned_ips` CHANGE `ipAddress` `ipAddress` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `download_token` CHANGE `ip_address` `ip_address` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `file` CHANGE `uploadedIP` `uploadedIP` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `file_report` CHANGE `reported_by_ip` `reported_by_ip` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `users` CHANGE `lastloginip` `lastloginip` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `users` CHANGE `createdip` `createdip` VARCHAR ( 45 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;

ALTER TABLE `users`
    ADD `uploadServerOverride` INT NULL;

CREATE TABLE `internal_notification`
(
    `id`                INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `to_user_id`        INT(11) NOT NULL,
    `date_added`        DATETIME     NOT NULL,
    `title`             VARCHAR(100) NOT NULL,
    `content`           VARCHAR(255) NOT NULL,
    `notification_icon` VARCHAR(30)  NOT NULL DEFAULT 'entypo-info',
    INDEX (`to_user_id`)
) ENGINE = MyISAM;
ALTER TABLE `internal_notification` DROP `title`;
ALTER TABLE `internal_notification`
    ADD `href_url` VARCHAR(255) NULL AFTER  `notification_icon` ,
ADD  `onclick` VARCHAR( 255 ) NULL AFTER  `href_url`;

ALTER TABLE `users`
    ADD INDEX(`level_id`);

ALTER TABLE `banned_ips`
    ADD `banExpiry` DATETIME NULL DEFAULT NULL;

CREATE TABLE `login_failure`
(
    `id`         INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(15) NOT NULL,
    `date_added` DATETIME    NOT NULL,
    `username`   VARCHAR(65) NOT NULL,
    INDEX ( `ip_address` )
) ENGINE = MYISAM;
ALTER TABLE `login_failure`
    ADD INDEX ( `date_added` );

INSERT INTO `site_config`
VALUES (null, 'security_block_ip_login_attempts', '5',
        'How many login attempts before an IP is blocked from logging in for 24 hours.', '', 'integer', 'Security');
INSERT INTO `site_config`
VALUES (null, 'security_send_user_email_on_password_change', 'yes',
        'Send user an email confirmation when they change their password in account settings.', '["yes","no"]',
        'select', 'Security');
INSERT INTO `site_config`
VALUES (null, 'security_send_user_email_on_email_change', 'yes',
        'Send user an email confirmation when they change their email address in account settings.', '["yes","no"]',
        'select', 'Security');

CREATE TABLE IF NOT EXISTS `theme`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `theme_name` varchar
(
    150
) COLLATE utf8_bin NOT NULL,
    `folder_name` varchar
(
    100
) COLLATE utf8_bin NOT NULL,
    `theme_description` varchar
(
    255
) COLLATE utf8_bin NOT NULL,
    `is_installed` int
(
    1
) NOT NULL DEFAULT '0',
    `date_installed` datetime NOT NULL,
    `theme_settings` text COLLATE utf8_bin NOT NULL,
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `theme`
    ADD `author_name` VARCHAR(255) NULL DEFAULT NULL AFTER `theme_description`, ADD `author_website` VARCHAR( 255 ) NULL DEFAULT NULL AFTER `author_name`;

UPDATE `site_config`
SET `availableValues` = 'SELECT folder_name AS itemValue FROM theme ORDER BY folder_name'
WHERE `config_key` = 'site_theme';

INSERT INTO `site_config`
VALUES (null, 'email_template_header',
        '<html>\n    <head>\n        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">\n    </head>\n    <body style="background-color: #eee; padding: 0px; margin: 0px; font-family: Arial, Helvetica; font-size: 12px;">\n        <div style="padding: 18px 18px 0px 18px; background-color: #ffffff;">\n            <a href="[[[WEB_ROOT]]]">\n                <div style="background-image: url(''[[[SITE_IMAGE_PATH]]]/main_logo_inverted.png''); background-repeat: no-repeat; height: 45px; width: 100%; float: left;"><!-- --></div>\n            </a>\n            <div style="clear: left;"><!-- --></div>\n        </div>\n        <div style="background-color: #ffffff; padding: 18px 18px 25px 18px;">',
        'HTML code for the header of all emails. Test using the ''admin/_test_scripts/test_email.php'' script. Accepts the following placeholders for replacements: [[[SITE_NAME]]], [[[WEB_ROOT]]], [[[DATE_NOW]]], [[[TIME_NOW]]].',
        '', 'textarea', 'Email Settings');
INSERT INTO `site_config`
VALUES (null, 'email_template_footer',
        '        </div>\n        <div style="color: #aaa; font-size: 10px; padding: 18px; margin-left: auto; margin-right: auto;">\n            This e-mail is intended solely for the addressee. If you are not the addressee please do not read, print, re-transmit, store or act in reliance on it or any attachments. Instead, please inform [[[SITE_NAME]]] support and then immediately permanently delete it.<br/><br/>\n            Please do not respond to this email. These are generated automatically by the [[[SITE_NAME]]] system and so the return address is not monitored for email. Please submit a request via our <a href="[[[WEB_ROOT]]]">website</a> if you have a query.<br/><br/>\n            Message sent from <a href="[[[WEB_ROOT]]]">[[[SITE_NAME]]]</a> on [[[DATE_TIME_NOW]]]\n        </div>\n    </body>\n</html>',
        'HTML code for the footer of all emails. Test using the \'admin/_test_scripts/test_email.php\' script. Accepts the following placeholders for replacements: [[[SITE_NAME]]], [[[WEB_ROOT]]], [[[DATE_NOW]]], [[[DATE_TIME_NOW]]].',
        '', 'textarea', 'Email Settings');
INSERT INTO `site_config`
VALUES (null, 'email_template_enabled', 'yes', 'Whether to use the email header and footer html.', '["yes","no"]',
        'select', 'Email Settings');

UPDATE `site_config`
SET `config_group` = 'System'
WHERE `site_config`.`config_key` = 'site_theme';

INSERT INTO `site_config`
VALUES (null, 'download_use_apache_xsendfile', 'no',
        'Use XSendFile in Apache. Performance increase for busy sites. <a href="https://support.mfscripts.com/p/kb_view/1/" target="_blank">More info</a>.',
        '["yes","no"]', 'select', 'File Downloads');
INSERT INTO `site_config`
VALUES (null, 'download_use_nginx_xaccelredirect', 'no',
        'Use X-Accel-Redirect in Nginx. Performance increase for busy sites. <a href="https://support.mfscripts.com/p/kb_view/2/" target="_blank">More info</a>.',
        '["yes","no"]', 'select', 'File Downloads');

CREATE TABLE IF NOT EXISTS `login_success`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `ip_address` varchar
(
    15
) COLLATE utf8_bin NOT NULL,
    `date_added` datetime NOT NULL,
    `username` varchar
(
    65
) COLLATE utf8_bin NOT NULL,
    PRIMARY KEY
(
    `id`
),
    KEY `ip_address`
(
    `ip_address`
),
    KEY `date_added`
(
    `date_added`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin;

ALTER TABLE `login_success` CHANGE `username` `user_id` INT ( 11 ) NOT NULL;
ALTER TABLE `login_success`
    ADD INDEX ( `user_id` );
ALTER TABLE `login_success`
    ADD `country_code` VARCHAR(2) NULL DEFAULT NULL, ADD INDEX ( `country_code` );

ALTER TABLE `language`
    ADD `direction` VARCHAR(3) NOT NULL DEFAULT 'LTR';

ALTER TABLE `file`
    ADD `minUserLevel` INT( 3 ) NULL DEFAULT NULL;

ALTER TABLE `download_token`
    ADD INDEX(`expiry`);

CREATE TABLE `cross_site_action`
(
    `id`         INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `key1`       VARCHAR(64) NOT NULL,
    `key2`       VARCHAR(64) NOT NULL,
    `data`       TEXT NULL,
    `date_added` DATETIME    NOT NULL,
    UNIQUE (`key1`, `key2`)
) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_bin;
ALTER TABLE `cross_site_action`
    ADD INDEX ( `date_added` );

DROP TABLE session_transfer;

INSERT INTO `site_config`
VALUES (null, 'security_block_register_email_domain', '',
        'Block email address domains from registering. Comma separated list of domains. i.e. exampledomain.com,exampledomain2.com,etc',
        '', 'textarea', 'Security');

ALTER TABLE `file_action` CHANGE `file_action` `file_action` ENUM( 'delete', 'move' ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `file_action`
    ADD `action_data` VARCHAR(255) NULL AFTER `status`;

ALTER TABLE `file_action`
    ADD `action_date` DATETIME NULL DEFAULT NULL;
ALTER TABLE `file_action`
    ADD INDEX (  `action_date` );
ALTER TABLE `file_action` CHANGE `status` `status` ENUM( 'pending', 'processing', 'complete', 'failed', 'cancelled' ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;

INSERT INTO `site_config`
VALUES (null, 'purge_deleted_files_period_minutes', '1440',
        'How long to keep deleted files for on the server. On delete via the script UI they are moved into /files/_deleted/ then purged after this period. Useful for recovery if needed. Set in minutes. Default 24 hours, so 1440.',
        '', 'input', 'Site Options');

UPDATE `site_config`
SET `config_value` = 'flow'
WHERE `config_key` = 'site_theme';

ALTER TABLE `file`
    ADD INDEX ( `fileHash` );
ALTER TABLE `users`
    ADD INDEX ( `level_id` );