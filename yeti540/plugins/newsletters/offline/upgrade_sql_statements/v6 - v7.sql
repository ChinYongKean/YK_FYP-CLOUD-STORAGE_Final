ALTER TABLE `plugin_newsletter` CHANGE `subject` `subject` text COLLATE 'utf8_bin' NULL AFTER `title`, CHANGE `html_content` `html_content` text COLLATE 'utf8_bin' NULL AFTER `subject`;
ALTER TABLE `plugin_newsletter` CHANGE `user_group` `user_group` varchar(20) COLLATE 'utf8_bin' NULL AFTER `form_type`;