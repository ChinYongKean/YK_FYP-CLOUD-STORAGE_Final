UPDATE file_server
SET routeViaMainSite = 1
WHERE serverType = 'direct';
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Adblock Filename', 'adblock_filename', 'xads.js',
        'The JS filename to use for the adblock code. Do not change this unless the Adblock Limiter is no longer working. No spaces or special characters allowed. Example: xads.js',
        '', 'string', 'Adverts', '2');

UPDATE `site_config`
SET `availableValues` = 'SELECT serverLabel AS itemValue, IF(statusId=2, "Active", "Unavailable") AS itemGroup FROM file_server LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id ORDER BY serverLabel'
WHERE `config_key` = 'default_file_server';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('Show Report File Form', 'captcha_report_file_form', 'no', 'Show the captcha on the report_file form.',
        '["yes","no"]', 'select', 'Captcha', '8');

DELETE
FROM `site_config`
WHERE `config_key` = 'show_cookie_notice';
INSERT INTO `site_config` (`config_key`, `label`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('show_cookie_notice', 'Show Cookie Notice', 'yes',
        'Show the cookie notice on the front-end theme. GDPR requirement. Shows only the first time for each visitor.',
        '[\"yes\",\"no\"]', 'select', 'Site Options', 100);
