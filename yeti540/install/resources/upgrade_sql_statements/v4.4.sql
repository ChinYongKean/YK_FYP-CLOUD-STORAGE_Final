ALTER TABLE `user_level_pricing` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `user_level` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE `user_level_pricing` CHANGE `pricing_label` `pricing_label` VARCHAR ( 50 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;
ALTER TABLE `user_level` CHANGE `label` `label` VARCHAR ( 20 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL;

INSERT INTO site_config
VALUES (NULL, 'user_register_default_folders', '',
        'Default folders for new accounts. These are automatically created when users register on the site. Leave blank to ignore. Pipe separated list. i.e. Documents|Images|Videos',
        '', 'string', 'File Manager');

INSERT INTO `site_config` (`config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES ('captcha_type', 'recaptcha', 'Which captcha to use, if enabled.', '[\"recaptcha\",\"solvemedia\"]', 'select',
        'Captcha');
INSERT INTO `site_config` (`config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES ('captcha_solvemedia_challenge_key', '',
        'Challenge key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/', '', 'string',
        'Captcha');
INSERT INTO `site_config` (`config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES ('captcha_solvemedia_ver_key', '',
        'Verification key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/', '',
        'string', 'Captcha');
INSERT INTO `site_config` (`config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES ('captcha_solvemedia_hash_key', '',
        'Authentication Hash key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/', '',
        'string', 'Captcha');

ALTER TABLE `file`
    ADD `description` varchar(255) COLLATE 'utf8_general_ci' NULL AFTER `keywords`;

ALTER TABLE `site_config`
    ADD `label` varchar(100) COLLATE 'utf8_general_ci' NULL AFTER `id`;
UPDATE site_config
SET label = REPLACE(CONCAT(UCASE(LEFT(config_key, 1)), SUBSTRING(config_key, 2)), '_', ' ');
ALTER TABLE `site_config`
    ADD `display_order` int(5) COLLATE 'utf8_general_ci' DEFAULT 0 AFTER `config_group`;
UPDATE `site_config`
SET label              = 'Max file uploads per day',
    config_description = 'Spam protect: Max files a user IP address can upload per day. Leave blank for unlimited.'
WHERE config_key = 'max_files_per_day';
UPDATE `site_config`
SET label = 'reCaptcha Secret Key'
WHERE config_key = 'captcha_secret_key';
UPDATE `site_config`
SET label = 'reCaptcha Public Key'
WHERE config_key = 'captcha_public_key';
UPDATE `site_config`
SET display_order = 1
WHERE config_key = 'adblock_limiter';
UPDATE `site_config`
SET display_order = 4
WHERE config_key = 'advert_delayed_redirect_top';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'advert_delayed_redirect_bottom';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'advert_site_footer';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'non_user_show_captcha';
UPDATE `site_config`
SET display_order = 3
WHERE config_key = 'free_user_show_captcha';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'register_form_show_captcha';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'captcha_type';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'captcha_public_key';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'captcha_secret_key';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'captcha_solvemedia_ver_key';
UPDATE `site_config`
SET display_order = 30
WHERE config_key = 'captcha_solvemedia_hash_key';
UPDATE `site_config`
SET display_order = 35
WHERE config_key = 'captcha_solvemedia_challenge_key';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'site_contact_form_email';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'contact_form_show_captcha';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'email_method';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'email_smtp_host';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'email_smtp_port';
UPDATE `site_config`
SET display_order = 12,
    config_group  = 'Email Settings'
WHERE config_key = 'default_email_address_from';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'email_secure_method';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'email_smtp_requires_auth';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'email_smtp_auth_username';
UPDATE `site_config`
SET display_order = 30
WHERE config_key = 'email_smtp_auth_password';
UPDATE `site_config`
SET display_order = 35
WHERE config_key = 'email_template_enabled';
UPDATE `site_config`
SET display_order = 40
WHERE config_key = 'email_template_header';
UPDATE `site_config`
SET display_order = 45
WHERE config_key = 'email_template_footer';
UPDATE `site_config`
SET display_order = 50
WHERE config_key = 'limit_send_via_email_per_hour';

UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'require_user_account_download';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'remote_url_download_in_background';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'downloads_track_current_downloads';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'downloads_block_all';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'file_manager_default_view';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'user_register_default_folders';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'c_file_server_selection_method';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'default_file_server';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'accepted_upload_file_types';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'max_files_per_day';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'generate_upload_url_type';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'file_url_show_filename';
UPDATE `site_config`
SET display_order = 30
WHERE config_key = 'blocked_filename_keywords';
UPDATE `site_config`
SET display_order = 35
WHERE config_key = 'blocked_upload_file_types';
UPDATE `site_config`
SET display_order = 40
WHERE config_key = 'uploads_block_all';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'site_language';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'language_user_select_language';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'show_multi_language_selector';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'language_separate_language_images';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'google_translate_api_key';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'language_show_key';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'date_format';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'date_time_format';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'date_time_format_js';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'logging_log_enabled';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'logging_log_type';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'logging_log_output';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'password_policy_min_length';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'password_policy_max_length';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'password_policy_min_numbers';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'password_policy_min_uppercase_characters';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'password_policy_min_nonalphanumeric_characters';

UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'cost_currency_symbol';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'cost_currency_code';

UPDATE `site_config`
SET display_order = 0
WHERE config_key = 'force_files_private';
UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'security_account_lock';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'premium_user_block_account_sharing';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'security_send_user_email_on_password_change';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'security_send_user_email_on_email_change';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'security_block_register_email_domain';
UPDATE `site_config`
SET display_order = 30
WHERE config_key = 'security_block_ip_login_attempts';
UPDATE `site_config`
SET display_order = 35
WHERE config_key = 'register_form_allow_password';

UPDATE `site_config`
SET display_order = 5
WHERE config_key = 'site_name';
UPDATE `site_config`
SET display_order = 10
WHERE config_key = 'page_extension';
UPDATE `site_config`
SET display_order = 15
WHERE config_key = 'site_admin_email';
UPDATE `site_config`
SET display_order = 20
WHERE config_key = 'report_abuse_email';
UPDATE `site_config`
SET display_order = 25
WHERE config_key = 'maintenance_mode';
UPDATE `site_config`
SET display_order = 30
WHERE config_key = 'enable_user_registration';
UPDATE `site_config`
SET display_order = 35
WHERE config_key = 'performance_js_file_minify';
UPDATE `site_config`
SET display_order = 40
WHERE config_key = 'enable_file_search';
UPDATE `site_config`
SET display_order = 45
WHERE config_key = 'default_admin_file_manager_view';
UPDATE `site_config`
SET display_order = 50
WHERE config_key = 'purge_deleted_files_period_minutes';
UPDATE `site_config`
SET display_order = 55
WHERE config_key = 'google_analytics_code';
UPDATE `site_config`
SET display_order = 60
WHERE config_key = 'session_expiry';

ALTER TABLE `file` CHANGE `uploadSource` `uploadSource` ENUM('direct','remote','ftp','torrent','leech','webdav','api','fileimport','other') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'direct';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('API Path', 'api_access_host', '',
        'The API hostname. Use [[[WEB_ROOT]]]/api/v2/ unless you want to move the API elsewhere.', '', 'string', 'API',
        5);
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Authentication Method', 'api_authentication_method', 'API Keys',
        'Whether to use the account username and password or generated API keys. (recommended to use generated API keys)',
        '[\"API Keys\",\"Account Access Details\"]', 'select', 'API', 10);
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Access Level', 'api_account_access_type', 'admin',
        'Restric`t access to certain account types. Hold ctrl and click to select multiple.',
        'SELECT label AS itemValue FROM user_level WHERE level_type != "nonuser" ORDER BY level_id', 'multiselect',
        'API', 15);

ALTER TABLE `file_folder_share`
    ADD `shared_with_user_id` INT(11) NULL DEFAULT NULL AFTER `created_by_user_id`;
ALTER TABLE `file_folder_share`
    ADD `share_permission_level` ENUM(  'view',  'upload_download',  'all' ) NOT NULL DEFAULT  'view';

CREATE TABLE `apiv2_access_token`
(
    `id`             int(11) NOT NULL,
    `user_id`        int(11) NOT NULL,
    `access_token`   varchar(128) NOT NULL,
    `date_added`     datetime DEFAULT NULL,
    `date_last_used` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `apiv2_access_token`
    ADD PRIMARY KEY (`id`),  ADD UNIQUE KEY `access_token` (`access_token`),  ADD KEY `date_last_used` (`date_last_used`);

ALTER TABLE `apiv2_access_token` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

CREATE TABLE `apiv2_api_key`
(
    `id`           int(11) NOT NULL,
    `key_public`   varchar(64) NOT NULL,
    `key_secret`   varchar(64) NOT NULL,
    `user_id`      int(11) NOT NULL,
    `date_created` datetime    NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `apiv2_api_key`
    ADD PRIMARY KEY (`id`),  ADD UNIQUE KEY `keys_public_secret` (`key_public`,`key_secret`) USING BTREE,  ADD KEY `date_created` (`date_created`),  ADD KEY `user_id` (`user_id`);

ALTER TABLE `apiv2_api_key` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

CREATE TABLE `file_server_container`
(
    `id`                   int(11) NOT NULL,
    `label`                varchar(100) NOT NULL,
    `entrypoint`           varchar(50)  NOT NULL,
    `expected_config_json` text,
    `is_enabled`           tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `file_server_container`
    ADD PRIMARY KEY (`id`);
ALTER TABLE `file_server_container` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `file_server` CHANGE `serverType` `serverType` VARCHAR (50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'local';

INSERT INTO `file_server_container` (`id`, `label`, `entrypoint`, `expected_config_json`, `is_enabled`)
VALUES (1, 'FTP', 'flysystem_ftp',
        '{"host":{"label":"FTP Host","type":"text","default":""},"username":{"label":"FTP Username","type":"text","default":""},"password":{"label":"FTP Password","type":"text","default":""},"port":{"label":"Port","type":"number","default":"21"},"root":{"label":"Root Path","type":"text","default":"\\/"},"passive":{"label":"Enable Passive Mode","type":"select","default":"1","option_values":["No","Yes"]},"ssl":{"label":"Use SSL","type":"select","default":"0","option_values":["No","Yes"]},"timeout":{"label":"Connection Timeout","type":"number","default":"30"}}',
        1),
       (2, 'SFTP', 'flysystem_sftp',
        '{"host":{"label":"SFTP Host","type":"text","default":""},"username":{"label":"SFTP Username","type":"text","default":""},"password":{"label":"SFTP Password","type":"text","default":""},"port":{"label":"Port","type":"number","default":"21"},"root":{"label":"Root Path","type":"text","default":"\\/"},"timeout":{"label":"Connection Timeout","type":"number","default":"30"}}',
        1),
       (3, 'Amazon S3', 'flysystem_aws',
        '{"key":{"label":"Public Key","type":"text","default":""},"secret":{"label":"Secret Key","type":"text","default":""},"bucket":{"label":"S3 Bucket","type":"text","default":""},"region":{"label":"Your Bucket Region","type":"select","default":"us-east-1","option_values":{"us-east-1":"US East (N. Virginia)","us-east-2":"US East (Ohio) - us-east-2","us-west-1":"US West (N. California) - us-west-1","us-west-2":"US West (Oregon) - us-west-2","ca-central-1":"Canada (Central) - ca-central-1","ap-south-1":"Asia Pacific (Mumbai) - ap-south-1","ap-northeast-2":"Asia Pacific (Seoul) - ap-northeast-2","ap-southeast-1":"Asia Pacific (Singapore) - ap-southeast-1","ap-southeast-2":"Asia Pacific (Sydney) - ap-southeast-2","ap-northeast-1":"Asia Pacific (Tokyo) - ap-northeast-1","eu-central-1":"EU (Frankfurt) - eu-central-1","eu-west-1":"EU (Ireland) - eu-west-1","eu-west-2":"EU (London) - eu-west-2","sa-east-1":"South America (S\\u00e3o Paulo) - sa-east-1"}},"version":{"label":"Version (Don\'t Change)","type":"string","default":"latest"}}', 1),
(4, 'Rackspace Cloud Files', 'flysystem_rackspace', '{"username":{"label":"Rackspace Username","type":"text","default":""},"apiKey":{"label":"API Key","type":"text","default":""},"container":{"label":"Cloud Files Container","type":"text","default":""},"region":{"label":"Container Region","type":"
select "," default ":" IAD "," option_values ":{" IAD ":" Nothern Virginia (IAD)"," DFW ":" Dallas (DFW)"," HKG ":" Hong Kong (HKG)"," SYD ":" Sydney (SYD)"," LON ":" London (LON) "}}}', 1),
(5, 'Azure Blob Storage', 'flysystem_azure', '{" account- name ":{" label ":" Account Name "," type ":" text "," default ":""}," api- key ":{" label ":" API Key "," type ":" text "," default ":""}," container ":{" label ":" Files Container "," type ":" text "," default ":""}}', 0);

ALTER TABLE `file`
    ADD `uploadedUserId` int(11) NULL AFTER `userId`;
ALTER TABLE `file`
    ADD INDEX `uploadedUserId` (`uploadedUserId`);
UPDATE `file`
SET `uploadedUserId` = `userId`;

ALTER TABLE `file_folder`
    ADD `totalSize` bigint(15) NULL AFTER `folderName`;
ALTER TABLE `file_folder` CHANGE `totalSize` `totalSize` bigint(15) NULL DEFAULT '0' AFTER `folderName`;

CREATE TABLE IF NOT EXISTS `payment_subscription`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `user_id` int
(
    11
) NOT NULL,
    `user_level_pricing_id` int
(
    11
) DEFAULT NULL,
    `payment_gateway` varchar
(
    50
) COLLATE utf8_bin NOT NULL,
    `gateway_subscription_id` varchar
(
    100
) COLLATE utf8_bin NOT NULL,
    `date_added` datetime NOT NULL,
    `sub_status` enum
(
    'active',
    'cancelled'
) COLLATE utf8_bin NOT NULL DEFAULT 'active',
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin;