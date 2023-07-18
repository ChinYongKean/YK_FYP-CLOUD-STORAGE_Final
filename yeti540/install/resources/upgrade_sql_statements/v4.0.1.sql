ALTER TABLE `internal_notification`
    ADD `is_read` INT(1) NOT NULL DEFAULT '0';
INSERT INTO `site_config`
VALUES (null, 'google_analytics_code', '',
        'Your Google Analytics or other stats code. This is appended to the footer of the site. It should include the script tags.',
        '', 'textarea', 'Site Options');

ALTER TABLE `users` CHANGE `uploadServerOverride` `uploadServerOverride` BIGINT( 20 ) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `background_task`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `task` varchar
(
    255
) COLLATE utf8_bin NOT NULL,
    `last_update` datetime DEFAULT NULL,
    `status` enum
(
    'running',
    'finished',
    'not_run'
) COLLATE utf8_bin NOT NULL,
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `task`
(
    `task`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin AUTO_INCREMENT=7;

INSERT INTO `background_task` (`id`, `task`, `last_update`, `status`)
VALUES (1, 'auto_prune.cron.php', NULL, 'not_run'),
       (3, 'create_internal_notifications.cron.php', NULL, 'not_run'),
       (4, 'downgrade_accounts.cron.php', NULL, 'not_run'),
       (5, 'plugin_tasks.cron.php', NULL, 'not_run'),
       (6, 'delete_redundant_files.cron.php', NULL, 'not_run');

CREATE TABLE IF NOT EXISTS `background_task_log`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `task_id` int
(
    11
) NOT NULL,
    `start_time` datetime NOT NULL,
    `end_time` datetime NOT NULL,
    `status` enum
(
    'started',
    'finished'
) COLLATE utf8_bin NOT NULL DEFAULT 'started',
    `server_name` varchar
(
    255
) COLLATE utf8_bin NOT NULL,
    `log_message` text COLLATE utf8_bin NOT NULL,
    PRIMARY KEY
(
    `id`
),
    KEY `task_id`
(
    `task_id`
),
    KEY `start_time`
(
    `start_time`
),
    KEY `end_time`
(
    `end_time`
),
    KEY `server_name`
(
    `server_name`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE =utf8_bin AUTO_INCREMENT=1;
