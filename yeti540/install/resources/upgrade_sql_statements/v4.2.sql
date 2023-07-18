ALTER TABLE `file`
    ADD `unique_hash` VARCHAR(64) NULL;
ALTER TABLE `file`
    ADD UNIQUE (`unique_hash`);

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
SELECT *
FROM (SELECT NULL,
             'non_user_show_captcha',
             'yes',
             'Show the captcha after a non user sees the countdown timer.',
             '["yes","no"]',
             'select',
             'Captcha') AS tmp
WHERE NOT EXISTS(
        SELECT config_key FROM site_config WHERE config_key = 'non_user_show_captcha'
    ) LIMIT 1;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'default_admin_file_manager_view', 'list', 'Default view to show in the admin file manager.',
        '["list","thumb"]', 'select', 'Site Options');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'enable_file_search', 'yes', 'Whether to enable the file search tool on the site.', '["yes","no"]',
        'select', 'Site Options');