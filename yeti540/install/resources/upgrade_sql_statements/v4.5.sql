SET NAMES utf8mb4;

ALTER TABLE `user_level`
    ADD `can_remote_download` int(1) NOT NULL DEFAULT '1' AFTER `max_download_filesize_allowed`;

INSERT INTO `site_config` (`config_key`, `label`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('captcha_login_screen_normal', 'Show User Login Screen', 'no', 'Show the captcha on the standard login screen.',
        '[\"yes\",\"no\"]', 'select', 'Captcha', 6);
INSERT INTO `site_config` (`config_key`, `label`, `config_value`, `config_description`, `availableValues`,
                           `config_type`, `config_group`, `display_order`)
VALUES ('captcha_login_screen_admin', 'Show Admin Login Screen', 'no', 'Show the captcha on the admin login screen.',
        '[\"yes\",\"no\"]', 'select', 'Captcha', 7);
UPDATE `site_config`
SET label = 'File Download - Non User'
WHERE config_key = 'non_user_show_captcha';
UPDATE `site_config`
SET label = 'File Download - Free User'
WHERE config_key = 'free_user_show_captcha';

ALTER TABLE `download_token`
    ADD `file_transfer` int(1) NOT NULL DEFAULT '1';

ALTER TABLE `file_action`
    ADD `is_uploaded_file` int(11) NOT NULL DEFAULT '0' AFTER `file_path`;

UPDATE file_server
SET storagePath = 'files/'
WHERE (storagePath = '' OR storagePath IS NULL)
  AND serverType = 'local';

ALTER TABLE `file_server` DROP `lastFileActionQueueProcess`;
ALTER TABLE `file_server`
    ADD `lastFileActionQueueProcess` datetime NULL AFTER `routeViaMainSite`;
ALTER TABLE `file_server`
    ADD `scriptRootPath` varchar(255) NULL AFTER `statusId`;

UPDATE file_folder_share
SET last_accessed = '2038-01-01 00:00:00'
WHERE CAST(last_accessed AS CHAR(20)) = '0000-00-00 00:00:00';
ALTER TABLE `file_folder_share` CHANGE `last_accessed` `last_accessed` datetime NULL AFTER `date_created`;
UPDATE file_folder_share
SET last_accessed = NULL
WHERE last_accessed = '2038-01-01 00:00:00';

UPDATE plugin
SET date_installed = '2038-01-01 00:00:00'
WHERE CAST(date_installed AS CHAR(20)) = '0000-00-00 00:00:00';
ALTER TABLE `plugin` CHANGE `date_installed` `date_installed` datetime NULL AFTER `is_installed`;
UPDATE plugin
SET date_installed = NULL
WHERE date_installed = '2038-01-01 00:00:00';

ALTER TABLE `file` DROP INDEX `keywords`;
ALTER TABLE `file`
    ADD INDEX `keywords` (`keywords`);
ALTER TABLE `stats` CHANGE `download_date` `download_date` datetime NULL AFTER `id`;

ALTER TABLE `apiv2_access_token` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `apiv2_api_key` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `background_task` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `background_task_log` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `banned_files` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `banned_ips` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `country_info` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `cross_site_action` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `download_page` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `download_token` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `download_tracker` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_action` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_block_hash` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_folder` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_folder_share` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_report` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_server` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_server_container` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_server_status` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `file_status` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `internal_notification` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `language` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `language_content` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `language_key` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `login_failure` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `login_success` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `payment_log` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `payment_subscription` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `plugin` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `premium_order` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `remote_url_download_queue` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `sessions` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `site_config` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `stats` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `theme` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `users` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `user_level` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';
ALTER TABLE `user_level_pricing` ENGINE= InnoDB COLLATE 'utf8mb4_general_ci';

ALTER TABLE `file_folder`
    ADD `status` enum('active','trash','deleted') COLLATE 'utf8_general_ci' NULL DEFAULT 'active' AFTER `urlHash`;
ALTER TABLE `file`
    ADD `status` enum('active','trash','deleted') NULL DEFAULT 'active' AFTER `statusId`;
UPDATE `file`
SET status = 'deleted'
WHERE statusId != 1;
ALTER TABLE `file`
    ADD `date_updated` datetime NULL;

ALTER TABLE `file`
    ADD INDEX `status` (`status`);
ALTER TABLE `file_folder`
    ADD INDEX `status` (`status`);

ALTER TABLE `user_level`
    ADD `max_uploads_per_day` bigint(18) NOT NULL DEFAULT '0' AFTER `max_upload_size`;
UPDATE `user_level`
SET `max_uploads_per_day` = (SELECT config_value FROM site_config WHERE config_key = 'max_files_per_day' LIMIT 1);
DELETE
FROM `site_config`
WHERE config_key = 'max_files_per_day' LIMIT 1;

ALTER TABLE `file`
    ADD INDEX `uploadedIP` (`uploadedIP`);

ALTER TABLE `user_level`
    ADD `accepted_file_types` varchar(255) NOT NULL DEFAULT '' AFTER `max_uploads_per_day`;
UPDATE `user_level`
SET `accepted_file_types` = (SELECT config_value
                             FROM site_config
                             WHERE config_key = 'accepted_upload_file_types' LIMIT 1);
DELETE
FROM `site_config`
WHERE config_key = 'accepted_upload_file_types' LIMIT 1;

ALTER TABLE `user_level`
    ADD `blocked_file_types` varchar(255) NOT NULL DEFAULT '' AFTER `accepted_file_types`;
UPDATE `user_level`
SET `blocked_file_types` = (SELECT config_value
                            FROM site_config
                            WHERE config_key = 'blocked_upload_file_types' LIMIT 1);
DELETE
FROM `site_config`
WHERE config_key = 'blocked_upload_file_types' LIMIT 1;

ALTER TABLE `user_level`
    ADD `days_to_keep_trashed_files` int(11) NOT NULL DEFAULT '0' AFTER `days_to_keep_inactive_files`;

INSERT INTO `plugin` (`id`, `plugin_name`, `folder_name`, `plugin_description`, `is_installed`, `date_installed`,
                      `plugin_settings`, `plugin_enabled`, `load_order`)
VALUES (NULL, 'Payment Gateways', 'payment', 'Access to over 50 payment gateways for premium account upgrades.', 1,
        NULL, '', 1, 999);

DROP TABLE IF EXISTS `plugin_payment_gateways`;
CREATE TABLE `plugin_payment_gateways`
(
    `id`            int(11) NOT NULL AUTO_INCREMENT,
    `class_name`    varchar(150) COLLATE utf8_bin NOT NULL,
    `label`         varchar(150) COLLATE utf8_bin DEFAULT NULL,
    `description`   text COLLATE utf8_bin,
    `url`           varchar(255) COLLATE utf8_bin DEFAULT NULL,
    `gateway_type`  enum('onsite','offsite') COLLATE utf8_bin NOT NULL DEFAULT 'offsite',
    `gateway_group` varchar(100) COLLATE utf8_bin DEFAULT NULL,
    `available`     tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `class_name` (`class_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `plugin_payment_gateways` (`id`, `class_name`, `label`, `description`, `url`, `gateway_type`,
                                       `gateway_group`, `available`)
VALUES (1, 'TwoCheckout', '2Checkout', NULL, 'https://www.2checkout.com', 'offsite', '2Checkout', 0),
       (2, 'AuthorizeNet_AIM', 'AuthorizeNet - AIM', NULL, 'https://www.authorize.net', 'offsite', 'AuthorizeNet', 1),
       (3, 'AuthorizeNet_CIM', 'AuthorizeNet - CIM', NULL, 'https://www.authorize.net', 'offsite', 'AuthorizeNet', 1),
       (4, 'AuthorizeNet_SIM', 'AuthorizeNet - SIM', NULL, 'https://www.authorize.net', 'offsite', 'AuthorizeNet', 1),
       (5, 'AuthorizeNet_DPM', 'AuthorizeNet - DPM', NULL, 'https://www.authorize.net', 'offsite', 'AuthorizeNet', 1),
       (6, 'BitPay', 'BitPay', NULL, 'https://bitpay.com', 'offsite', 'BitPay', 1),
       (7, 'Braintree', 'Braintree', NULL, 'https://www.braintreepayments.com', 'offsite', 'Braintree', 1),
       (8, 'Buckaroo_CreditCard', 'Buckaroo - CreditCard', NULL, 'https://www.buckaroo-payments.com', 'offsite',
        'Buckaroo', 1),
       (9, 'Buckaroo_Ideal', 'Buckaroo - Ideal', NULL, 'https://www.buckaroo-payments.com', 'offsite', 'Buckaroo', 1),
       (10, 'Buckaroo_PayPal', 'Buckaroo - PayPal', NULL, 'https://www.buckaroo-payments.com', 'offsite', 'Buckaroo',
        1),
       (11, 'Buckaroo_SepaDirectDebit', 'Buckaroo - SepaDirectDebit', NULL, 'https://www.buckaroo-payments.com',
        'offsite', 'Buckaroo', 1),
       (12, 'CardSave', 'CardSave', NULL, 'https://www.cardsave.net', 'offsite', 'CardSave', 1),
       (13, 'Coinbase', 'Coinbase', NULL, 'https://www.coinbase.com', 'offsite', 'Coinbase', 1),
       (14, 'Eway_RapidDirect', 'Eway - RapidDirect',
        'This is the primary gateway used for direct card processing, i.e. where you collect the card details from the customer and pass them to eWay yourself via the API.',
        'https://eway.io', 'offsite', 'Eway', 1),
       (15, 'Eway_Rapid', 'Eway - Rapid',
        'This is used for eWAY Rapid Transparent Redirect requests. The gateway is just called Eway_Rapid as it was the first implemented. Like other redirect gateways the purchase() call will return a redirect response and then requires you to redirect the customer to the eWay site for the actual purchase.',
        'https://eway.io', 'offsite', 'Eway', 1),
       (16, 'Eway_RapidShared', 'Eway - RapidShared',
        'This provides a hosted form for entering payment information, other than that it is similar to the Eway_Rapid gateway in functionality.',
        'https://eway.io', 'offsite', 'Eway', 1),
       (17, 'FirstData_Connect', 'FirstData - Connect', NULL, 'https://www.firstdata.com/ecommerce/index.html',
        'offsite', 'FirstData', 1),
       (18, 'FirstData_Webservice', 'FirstData - Webservice', NULL, 'https://www.firstdata.com/ecommerce/index.html',
        'offsite', 'FirstData', 1),
       (19, 'FirstData_Payeezy', 'FirstData - Payeezy', NULL, 'https://www.firstdata.com/ecommerce/index.html',
        'offsite', 'FirstData', 1),
       (20, 'GoCardless', 'GoCardless', NULL, 'https://gocardless.com', 'offsite', 'GoCardless', 1),
       (21, 'Migs_TwoParty', 'Migs - TwoParty', NULL,
        'https://www.mastercard.us/en-us/about-mastercard/what-we-do/payment-processing.html', 'offsite', 'Migs', 1),
       (22, 'Migs_ThreeParty', 'Migs - ThreeParty', NULL,
        'https://www.mastercard.us/en-us/about-mastercard/what-we-do/payment-processing.html', 'offsite', 'Migs', 1),
       (23, 'Mollie', 'Mollie', NULL, 'https://www.mollie.com', 'offsite', 'Mollie', 1),
       (24, 'MultiSafepay_Rest', 'MultiSafepay - Rest', NULL, 'https://www.multisafepay.com', 'offsite', 'MultiSafepay',
        1),
       (25, 'Netaxept', 'Netaxept', NULL, 'https://www.nets.eu', 'offsite', 'Netaxept', 1),
       (26, 'NetBanx', 'NetBanx', NULL, NULL, 'offsite', 'NetBanx', 0),
       (27, 'OKPAY', 'OKPay', NULL, 'https://www.okpay.com', 'offsite', 'OKPay', 1),
       (28, 'PayFast', 'PayFast', NULL, 'https://www.payfast.co.za', 'offsite', 'PayFast', 1),
       (29, 'Payflow_Pro', 'Payflow - Pro', NULL, NULL, 'offsite', 'Payflow', 0),
       (30, 'PaymentExpress_PxPay', 'PaymentExpress - PxPay', NULL, 'https://www.paymentexpress.co.uk', 'offsite',
        'PaymentExpress', 1),
       (31, 'PaymentExpress_PxPost', 'PaymentExpress - PxPost', NULL, 'https://www.paymentexpress.co.uk', 'offsite',
        'PaymentExpress', 1),
       (32, 'PaymentExpress_PxFusion', 'PaymentExpress - PxFusion', NULL, 'https://www.paymentexpress.co.uk', 'offsite',
        'PaymentExpress', 0),
       (33, 'PayPal_Express', 'PayPal - Express', 'PayPal Express Checkout', 'https://www.paypal.com', 'offsite',
        'PayPal', 1),
       (34, 'PayPal_ExpressInContext', 'PayPal - ExpressInContext', 'PayPal Express In-Context Checkout',
        'https://www.paypal.com', 'offsite', 'PayPal', 1),
       (35, 'PayPal_Pro', 'PayPal - Pro', 'PayPal Website Payments Pro', 'https://www.paypal.com', 'offsite', 'PayPal',
        1),
       (36, 'PayPal_Rest', 'PayPal - Rest', 'Paypal Rest API', 'https://www.paypal.com', 'offsite', 'PayPal', 1),
       (37, 'Paysafecard', 'Paysafecard', NULL, 'https://www.paysafecard.com', 'offsite', 'Paysafecard', 1),
       (38, 'Pin', 'Pin', NULL, 'https://pinpayments.com', 'offsite', 'Pin', 1),
       (39, 'SagePay_Direct', 'SagePay - Direct', NULL, 'https://www.sagepay.co.uk', 'offsite', 'SagePay', 1),
       (40, 'SagePay_Server', 'SagePay - Server', NULL, 'https://www.sagepay.co.uk', 'offsite', 'SagePay', 1),
       (41, 'SecurePay_SecureXML', 'SecurePay Secure XML', NULL, 'https://www.securepay.com.au', 'offsite', 'SecurePay',
        1),
       (42, 'Stripe', 'Stripe', NULL, 'https://stripe.com', 'offsite', 'Stripe', 1),
       (43, 'TargetPay_Directebanking', 'TargetPay - Directebanking', NULL, 'https://www.targetpay.com', 'offsite',
        'TargetPay', 1),
       (44, 'TargetPay_Ideal', 'TargetPay - Ideal', NULL, 'https://www.targetpay.com', 'offsite', 'TargetPay', 1),
       (45, 'TargetPay_Mrcash', 'TargetPay - Mrcash', NULL, 'https://www.targetpay.com', 'offsite', 'TargetPay', 1),
       (46, 'WebMoney', 'WebMoney', NULL, 'https://www.wmtransfer.com', 'offsite', 'WebMoney', 1),
       (47, 'WorldPay', 'WorldPay', NULL, 'https://www.worldpay.com', 'offsite', 'WorldPay', 1),
       (48, 'WorldPay_Json', 'WorldPay - Json', NULL, 'https://www.worldpay.com', 'offsite', 'WorldPay', 1),
       (49, 'Sofort', 'Sofort', NULL, 'https://www.klarna.com/sofort', 'offsite', 'Sofort', 1),
       (50, 'Paysera', 'Paysera', NULL, 'https://www.paysera.com', 'offsite', 'Paysera', 1),
       (51, 'EgopayRu', 'EgopayRu', NULL, 'http://www.ego-pay.com', 'offsite', 'EgopayRu', 0),
       (52, 'CoinPayments', 'Coinpayments', NULL, 'https://www.coinpayments.net', 'offsite', 'Coinpayments', 1);

DROP TABLE IF EXISTS `plugin_payment_gateways_configured`;
CREATE TABLE `plugin_payment_gateways_configured`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `gateway_id`   int(11) NOT NULL,
    `params`       text COLLATE utf8_bin,
    `status`       enum('active','disabled') COLLATE utf8_bin NOT NULL DEFAULT 'active',
    `date_created` datetime DEFAULT NULL,
    `date_updated` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
