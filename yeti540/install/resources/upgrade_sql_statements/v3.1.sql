UPDATE site_config
SET config_description='How long non/free users have to wait before being able to download (in seconds). Set to 0 to allow non/free users direct download access.',
    `config_group`    = 'Free User Settings'
WHERE config_key = 'redirect_delay_seconds';
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'require_user_account_upload', 'no', 'Users must register for an account to upload.', '["yes","no"]',
        'select', 'File Uploads');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'require_user_account_download', 'no', 'Users must register for an account to download.', '["yes","no"]',
        'select', 'File Downloads');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'generate_upload_url_type', 'Shortest',
        'What format to generate the file url in. Shortest will increment based on the previous upload. Hashed will create a longer random character hash.',
        '["Shortest","Medium Hash","Long Hash"]', 'select', 'File Uploads');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_max_download_filesize', '0',
        'The maximum filesize a non/free user can download (in bytes). Set to 0 (zero) to ignore.', '', 'integer',
        'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_max_download_threads', '0',
        'The maximum concurrent downloads a non/free user can do at once. Set to 0 (zero) for no limit.', '', 'integer',
        'Free User Settings');

ALTER TABLE `file`
    ADD INDEX ( `userId` );
ALTER TABLE `users`
    ADD INDEX ( `status` );
ALTER TABLE `file`
    ADD INDEX ( `statusId` );
ALTER TABLE `users`
    ADD INDEX ( `email` );

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_max_remote_urls', '5', 'The maximum remote urls a non/free user can specify at once.', '',
        'integer', 'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'premium_user_max_remote_urls', '50', 'The maximum remote urls a paid user can specify at once.', '',
        'integer', 'Free User Settings');