UPDATE plugin_newsletter SET date_sent = '2038-01-01 00:00:00' WHERE CAST(date_sent AS CHAR(20)) = '0000-00-00 00:00:00';
ALTER TABLE `plugin_newsletter` CHANGE `date_sent` `date_sent` datetime NULL AFTER `date_created`;
UPDATE plugin_newsletter SET date_sent = NULL WHERE date_sent = '2038-01-01 00:00:00';
ALTER TABLE `plugin_newsletter` ADD `form_type` int(1) NOT NULL DEFAULT '1' AFTER `html_content`;
