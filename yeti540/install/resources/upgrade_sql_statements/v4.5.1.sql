INSERT INTO `site_config` (`config_key`, `label`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('show_cookie_notice', 'Show Cookie Notice', 'yes',
        'Show the cookie notice on the front-end theme. GDPR requirement. Shows only the first time for each visitor.',
        '[\"yes\",\"no\"]', 'select', 'Site Options', 100);

DELETE
FROM plugin_newsletter_unsubscribe
WHERE EXISTS(SELECT *
             FROM information_schema.TABLES
             WHERE (TABLE_NAME = 'plugin_newsletter_unsubscribe'));

INSERT INTO plugin_newsletter_unsubscribe (user_id, date_unsubscribed)
SELECT id, NOW()
FROM users
WHERE EXISTS(SELECT *
             FROM information_schema.TABLES
             WHERE (TABLE_NAME = 'plugin_newsletter_unsubscribe'));