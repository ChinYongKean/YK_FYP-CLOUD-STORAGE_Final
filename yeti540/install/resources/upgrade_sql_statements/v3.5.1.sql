ALTER TABLE `download_token`
    ADD `download_speed` INT( 11 ) NOT NULL DEFAULT '0';
ALTER TABLE `download_token`
    ADD `max_threads` INT( 3 ) NOT NULL DEFAULT '0';

CREATE TABLE `file_action`
(
    `id`           INT( 11 ) NOT NULL,
    `file_id`      INT( 11 ) NULL DEFAULT NULL,
    `server_id`    INT( 11 ) NOT NULL,
    `file_path`    TEXT     NOT NULL,
    `file_action`  ENUM( 'delete' ) NOT NULL,
    `status`       ENUM( 'pending', 'processing', 'complete', 'failed' ) NOT NULL,
    `date_created` DATETIME NOT NULL,
    `last_updated` DATETIME NOT NULL
) ENGINE = MYISAM;

ALTER TABLE `file_action`
    ADD `status_msg` VARCHAR(255) NOT NULL AFTER `status`;
ALTER TABLE `file_action`
    ADD INDEX ( `file_action` );
ALTER TABLE `file_action`
    ADD INDEX ( `status` );
ALTER TABLE `file_action`
    ADD INDEX ( `file_id` );
ALTER TABLE `file_action`
    ADD INDEX ( `server_id` );
ALTER TABLE `file_action`
    ADD INDEX ( `date_created` );

ALTER TABLE `file_action` CHANGE `last_updated` `last_updated` DATETIME NULL;
ALTER TABLE `file_action` CHANGE `status_msg` `status_msg` VARCHAR ( 255 ) CHARACTER SET utf8 COLLATE utf8_bin NULL;
ALTER TABLE `file_action`
    ADD PRIMARY KEY (`id`);
ALTER TABLE `file_action` CHANGE `id` `id` INT ( 11 ) NOT NULL AUTO_INCREMENT;

ALTER TABLE `file_server`
    ADD `lastFileActionQueueProcess` TIMESTAMP NOT NULL DEFAULT 0;
ALTER TABLE `file_server`
    ADD `serverConfig` TEXT NULL;

ALTER TABLE `file_server`
DROP
`sftpHost`,
  DROP
`sftpPort`,
  DROP
`sftpUsername`,
  DROP
`sftpPassword`;

