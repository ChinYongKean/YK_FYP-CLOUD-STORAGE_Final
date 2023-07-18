UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `user_level`.`level_id` = 0;
UPDATE `user_level`
SET `level_type` = 'nonuser'
WHERE `label` = 'Non User';

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

