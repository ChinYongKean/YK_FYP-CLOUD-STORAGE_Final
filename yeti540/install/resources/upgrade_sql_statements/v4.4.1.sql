ALTER TABLE `file_folder`
    ADD `urlHash` varchar(32) NULL AFTER `showDownloadLinks`;

ALTER TABLE `file_folder`
    ADD INDEX `userId` (`userId`), ADD INDEX `parentId` (`parentId`), ADD INDEX `totalSize` (`totalSize`), ADD INDEX `isPublic` (`isPublic`), ADD INDEX `folderName` (`folderName`);

ALTER TABLE `banned_files`
    ADD INDEX `fileHash` (`fileHash`);

ALTER TABLE `country_info`
    ADD INDEX `iso_alpha2` (`iso_alpha2`), ADD INDEX `iso_alpha3` (`iso_alpha3`);

ALTER TABLE `download_page`
    ADD INDEX `user_level_id` (`user_level_id`);

ALTER TABLE `download_token`
    ADD INDEX `ip_address` (`ip_address`), ADD INDEX `file_id` (`file_id`);
ALTER TABLE `download_token`
    ADD INDEX `user_id` (`user_id`);

ALTER TABLE `language`
    ADD INDEX `isLocked` (`isLocked`), ADD INDEX `isActive` (`isActive`);

ALTER TABLE `plugin`
    ADD INDEX `is_installed` (`is_installed`);

ALTER TABLE `user_level_pricing`
    ADD INDEX `user_level_id` (`user_level_id`);

ALTER TABLE `user_level`
    ADD INDEX `level_id` (`level_id`);

ALTER TABLE `users`
    ADD INDEX `apikey` (`apikey`);

ALTER TABLE `theme`
    ADD INDEX `is_installed` (`is_installed`);

ALTER TABLE `stats`
    ADD INDEX `ip` (`ip`), ADD INDEX `user_id` (`user_id`);

ALTER TABLE `remote_url_download_queue`
    ADD INDEX `user_id` (`user_id`), ADD INDEX `file_server_id` (`file_server_id`), ADD INDEX `folder_id` (`folder_id`), ADD INDEX `new_file_id` (`new_file_id`);

ALTER TABLE `premium_order`
    ADD INDEX `user_id` (`user_id`), ADD INDEX `payment_hash` (`payment_hash`), ADD INDEX `user_level_pricing_id` (`user_level_pricing_id`), ADD INDEX `order_status` (`order_status`), ADD INDEX `upgrade_file_id` (`upgrade_file_id`), ADD INDEX `upgrade_user_id` (`upgrade_user_id`);

