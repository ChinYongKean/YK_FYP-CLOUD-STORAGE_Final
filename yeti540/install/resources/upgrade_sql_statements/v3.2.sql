ALTER TABLE `file` CHANGE `fileSize` `fileSize` BIGINT( 15 ) NULL DEFAULT NULL;
INSERT INTO `plugin`
VALUES (NULL, 'PayPal Payment Integration', 'paypal', 'Accept payments using PayPal.', 1, '0000-00-00 00:00:00',
        '{"paypal_email":"paypal@yoursite.com"}', 1);
UPDATE plugin p, site_config c
SET p.plugin_settings = CONCAT('{"paypal_email":"', c.config_value, '"}')
WHERE c.config_key = 'paypal_payments_email_address' AND p.folder_name = 'paypal';
