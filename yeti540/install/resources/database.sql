-- MySQL dump 10.13  Distrib 5.7.20, for Linux (x86_64)
--
-- Host: localhost    Database: yetisharetemp
-- ------------------------------------------------------
-- Server version	5.7.20-0ubuntu0.16.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `apiv2_access_token`
--

DROP TABLE IF EXISTS `apiv2_access_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `apiv2_access_token`
(
    `id`             int(11) NOT NULL AUTO_INCREMENT,
    `user_id`        int(11) NOT NULL,
    `access_token`   varchar(128) CHARACTER SET utf8 NOT NULL,
    `date_added`     datetime DEFAULT NULL,
    `date_last_used` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `access_token` (`access_token`),
    KEY              `date_last_used` (`date_last_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `apiv2_access_token`
--

LOCK
TABLES `apiv2_access_token` WRITE;
/*!40000 ALTER TABLE `apiv2_access_token` DISABLE KEYS */;
/*!40000 ALTER TABLE `apiv2_access_token` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `apiv2_api_key`
--

DROP TABLE IF EXISTS `apiv2_api_key`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `apiv2_api_key`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `key_public`   varchar(64) CHARACTER SET utf8 NOT NULL,
    `key_secret`   varchar(64) CHARACTER SET utf8 NOT NULL,
    `user_id`      int(11) NOT NULL,
    `date_created` datetime                       NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `keys_public_secret` (`key_public`,`key_secret`) USING BTREE,
    KEY            `date_created` (`date_created`),
    KEY            `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `apiv2_api_key`
--

LOCK
TABLES `apiv2_api_key` WRITE;
/*!40000 ALTER TABLE `apiv2_api_key` DISABLE KEYS */;
/*!40000 ALTER TABLE `apiv2_api_key` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `background_task`
--

DROP TABLE IF EXISTS `background_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `background_task`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `task`        varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `last_update` datetime DEFAULT NULL,
    `status`      enum('running','finished','not_run') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `task` (`task`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `background_task`
--

LOCK
TABLES `background_task` WRITE;
/*!40000 ALTER TABLE `background_task` DISABLE KEYS */;
INSERT INTO `background_task`
VALUES (1, 'auto_prune.cron.php', NULL, 'not_run'),
       (3, 'create_internal_notifications.cron.php', NULL, 'not_run'),
       (4, 'downgrade_accounts.cron.php', NULL, 'not_run'),
       (5, 'plugin_tasks.cron.php', NULL, 'not_run'),
       (6, 'delete_redundant_files.cron.php', NULL, 'not_run'),
       (7, 'create_email_notifications.cron.php', NULL, 'not_run'),
       (8, 'process_remote_file_downloads.cron.php', NULL, 'not_run');
/*!40000 ALTER TABLE `background_task` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `background_task_log`
--

DROP TABLE IF EXISTS `background_task_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `background_task_log`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `task_id`     int(11) NOT NULL,
    `start_time`  datetime                                         NOT NULL,
    `end_time`    datetime                                         NOT NULL,
    `status`      enum('started','finished') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'started',
    `server_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `log_message` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    PRIMARY KEY (`id`),
    KEY           `task_id` (`task_id`),
    KEY           `start_time` (`start_time`),
    KEY           `end_time` (`end_time`),
    KEY           `server_name` (`server_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `background_task_log`
--

LOCK
TABLES `background_task_log` WRITE;
/*!40000 ALTER TABLE `background_task_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `background_task_log` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `banned_files`
--

DROP TABLE IF EXISTS `banned_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banned_files`
(
    `id`       int(11) NOT NULL AUTO_INCREMENT,
    `fileHash` varchar(32) CHARACTER SET utf8 NOT NULL,
    `fileSize` bigint(15) NOT NULL,
    PRIMARY KEY (`id`),
    KEY        `fileHash` (`fileHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banned_files`
--

LOCK
TABLES `banned_files` WRITE;
/*!40000 ALTER TABLE `banned_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `banned_files` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `banned_ip`
--

DROP TABLE IF EXISTS `banned_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banned_ip`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `ipAddress`  varchar(45) CHARACTER SET utf8 NOT NULL,
    `dateBanned` datetime                       NOT NULL,
    `banType`    varchar(30) CHARACTER SET utf8 NOT NULL,
    `banNotes`   text CHARACTER SET utf8 NOT NULL,
    `banExpiry`  datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY          `ipAddress` (`ipAddress`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banned_ip`
--

LOCK
TABLES `banned_ip` WRITE;
/*!40000 ALTER TABLE `banned_ip` DISABLE KEYS */;
/*!40000 ALTER TABLE `banned_ip` ENABLE KEYS */;
UNLOCK
TABLES;

--
-- Table structure for table `country_info`
--

DROP TABLE IF EXISTS `country_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `country_info`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `iso_alpha2`       varchar(2) CHARACTER SET utf8   DEFAULT NULL,
    `iso_alpha3`       varchar(3) CHARACTER SET utf8   DEFAULT NULL,
    `name`             varchar(200) CHARACTER SET utf8 DEFAULT NULL,
    `currency_code`    char(3) CHARACTER SET utf8      DEFAULT NULL,
    `currency_name`    varchar(32) CHARACTER SET utf8  DEFAULT NULL,
    `currrency_symbol` varchar(3) CHARACTER SET utf8   DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY                `iso_alpha2` (`iso_alpha2`),
    KEY                `iso_alpha3` (`iso_alpha3`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `country_info`
--

LOCK
TABLES `country_info` WRITE;
/*!40000 ALTER TABLE `country_info` DISABLE KEYS */;
INSERT INTO `country_info`
VALUES (1, 'AD', 'AND', 'Andorra', 'EUR', 'Euro', 'â‚¬'),
       (2, 'AE', 'ARE', 'United Arab Emirates', 'AED', 'Dirham', NULL),
       (3, 'AF', 'AFG', 'Afghanistan', 'AFN', 'Afghani', 'Ø‹'),
       (4, 'AG', 'ATG', 'Antigua and Barbuda', 'XCD', 'Dollar', '$'),
       (5, 'AI', 'AIA', 'Anguilla', 'XCD', 'Dollar', '$'),
       (6, 'AL', 'ALB', 'Albania', 'ALL', 'Lek', 'Lek'),
       (7, 'AM', 'ARM', 'Armenia', 'AMD', 'Dram', NULL),
       (8, 'AN', 'ANT', 'Netherlands Antilles', 'ANG', 'Guilder', 'Æ’'),
       (9, 'AO', 'AGO', 'Angola', 'AOA', 'Kwanza', 'Kz'),
       (10, 'AQ', 'ATA', 'Antarctica', '', '', NULL),
       (11, 'AR', 'ARG', 'Argentina', 'ARS', 'Peso', '$'),
       (12, 'AS', 'ASM', 'American Samoa', 'USD', 'Dollar', '$'),
       (13, 'AT', 'AUT', 'Austria', 'EUR', 'Euro', 'â‚¬'),
       (14, 'AU', 'AUS', 'Australia', 'AUD', 'Dollar', '$'),
       (15, 'AW', 'ABW', 'Aruba', 'AWG', 'Guilder', 'Æ’'),
       (16, 'AX', 'ALA', 'Aland Islands', 'EUR', 'Euro', 'â‚¬'),
       (17, 'AZ', 'AZE', 'Azerbaijan', 'AZN', 'Manat', 'Ð¼Ð'),
       (18, 'BA', 'BIH', 'Bosnia and Herzegovina', 'BAM', 'Marka', 'KM'),
       (19, 'BB', 'BRB', 'Barbados', 'BBD', 'Dollar', '$'),
       (20, 'BD', 'BGD', 'Bangladesh', 'BDT', 'Taka', NULL),
       (21, 'BE', 'BEL', 'Belgium', 'EUR', 'Euro', 'â‚¬'),
       (22, 'BF', 'BFA', 'Burkina Faso', 'XOF', 'Franc', NULL),
       (23, 'BG', 'BGR', 'Bulgaria', 'BGN', 'Lev', 'Ð»Ð'),
       (24, 'BH', 'BHR', 'Bahrain', 'BHD', 'Dinar', NULL),
       (25, 'BI', 'BDI', 'Burundi', 'BIF', 'Franc', NULL),
       (26, 'BJ', 'BEN', 'Benin', 'XOF', 'Franc', NULL),
       (27, 'BL', 'BLM', 'Saint BarthÃ©lemy', 'EUR', 'Euro', 'â‚¬'),
       (28, 'BM', 'BMU', 'Bermuda', 'BMD', 'Dollar', '$'),
       (29, 'BN', 'BRN', 'Brunei', 'BND', 'Dollar', '$'),
       (30, 'BO', 'BOL', 'Bolivia', 'BOB', 'Boliviano', '$b'),
       (31, 'BR', 'BRA', 'Brazil', 'BRL', 'Real', 'R$'),
       (32, 'BS', 'BHS', 'Bahamas', 'BSD', 'Dollar', '$'),
       (33, 'BT', 'BTN', 'Bhutan', 'BTN', 'Ngultrum', NULL),
       (34, 'BV', 'BVT', 'Bouvet Island', 'NOK', 'Krone', 'kr'),
       (35, 'BW', 'BWA', 'Botswana', 'BWP', 'Pula', 'P'),
       (36, 'BY', 'BLR', 'Belarus', 'BYR', 'Ruble', 'p.'),
       (37, 'BZ', 'BLZ', 'Belize', 'BZD', 'Dollar', 'BZ$'),
       (38, 'CA', 'CAN', 'Canada', 'CAD', 'Dollar', '$'),
       (39, 'CC', 'CCK', 'Cocos Islands', 'AUD', 'Dollar', '$'),
       (40, 'CD', 'COD', 'Democratic Republic of the Congo', 'CDF', 'Franc', NULL),
       (41, 'CF', 'CAF', 'Central African Republic', 'XAF', 'Franc', 'FCF'),
       (42, 'CG', 'COG', 'Republic of the Congo', 'XAF', 'Franc', 'FCF'),
       (43, 'CH', 'CHE', 'Switzerland', 'CHF', 'Franc', 'CHF'),
       (44, 'CI', 'CIV', 'Ivory Coast', 'XOF', 'Franc', NULL),
       (45, 'CK', 'COK', 'Cook Islands', 'NZD', 'Dollar', '$'),
       (46, 'CL', 'CHL', 'Chile', 'CLP', 'Peso', NULL),
       (47, 'CM', 'CMR', 'Cameroon', 'XAF', 'Franc', 'FCF'),
       (48, 'CN', 'CHN', 'China', 'CNY', 'Yuan Renminbi', 'Â¥'),
       (49, 'CO', 'COL', 'Colombia', 'COP', 'Peso', '$'),
       (50, 'CR', 'CRI', 'Costa Rica', 'CRC', 'Colon', 'â‚¡'),
       (51, 'CU', 'CUB', 'Cuba', 'CUP', 'Peso', 'â‚±'),
       (52, 'CV', 'CPV', 'Cape Verde', 'CVE', 'Escudo', NULL),
       (53, 'CX', 'CXR', 'Christmas Island', 'AUD', 'Dollar', '$'),
       (54, 'CY', 'CYP', 'Cyprus', 'CYP', 'Pound', NULL),
       (55, 'CZ', 'CZE', 'Czech Republic', 'CZK', 'Koruna', 'KÄ'),
       (56, 'DE', 'DEU', 'Germany', 'EUR', 'Euro', 'â‚¬'),
       (57, 'DJ', 'DJI', 'Djibouti', 'DJF', 'Franc', NULL),
       (58, 'DK', 'DNK', 'Denmark', 'DKK', 'Krone', 'kr'),
       (59, 'DM', 'DMA', 'Dominica', 'XCD', 'Dollar', '$'),
       (60, 'DO', 'DOM', 'Dominican Republic', 'DOP', 'Peso', 'RD$'),
       (61, 'DZ', 'DZA', 'Algeria', 'DZD', 'Dinar', NULL),
       (62, 'EC', 'ECU', 'Ecuador', 'USD', 'Dollar', '$'),
       (63, 'EE', 'EST', 'Estonia', 'EEK', 'Kroon', 'kr'),
       (64, 'EG', 'EGY', 'Egypt', 'EGP', 'Pound', 'Â£'),
       (65, 'EH', 'ESH', 'Western Sahara', 'MAD', 'Dirham', NULL),
       (66, 'ER', 'ERI', 'Eritrea', 'ERN', 'Nakfa', 'Nfk'),
       (67, 'ES', 'ESP', 'Spain', 'EUR', 'Euro', 'â‚¬'),
       (68, 'ET', 'ETH', 'Ethiopia', 'ETB', 'Birr', NULL),
       (69, 'FI', 'FIN', 'Finland', 'EUR', 'Euro', 'â‚¬'),
       (70, 'FJ', 'FJI', 'Fiji', 'FJD', 'Dollar', '$'),
       (71, 'FK', 'FLK', 'Falkland Islands', 'FKP', 'Pound', 'Â£'),
       (72, 'FM', 'FSM', 'Micronesia', 'USD', 'Dollar', '$'),
       (73, 'FO', 'FRO', 'Faroe Islands', 'DKK', 'Krone', 'kr'),
       (74, 'FR', 'FRA', 'France', 'EUR', 'Euro', 'â‚¬'),
       (75, 'GA', 'GAB', 'Gabon', 'XAF', 'Franc', 'FCF'),
       (76, 'GB', 'GBR', 'United Kingdom', 'GBP', 'Pound', 'Â£'),
       (77, 'GD', 'GRD', 'Grenada', 'XCD', 'Dollar', '$'),
       (78, 'GE', 'GEO', 'Georgia', 'GEL', 'Lari', NULL),
       (79, 'GF', 'GUF', 'French Guiana', 'EUR', 'Euro', 'â‚¬'),
       (80, 'GG', 'GGY', 'Guernsey', 'GGP', 'Pound', 'Â£'),
       (81, 'GH', 'GHA', 'Ghana', 'GHC', 'Cedi', 'Â¢'),
       (82, 'GI', 'GIB', 'Gibraltar', 'GIP', 'Pound', 'Â£'),
       (83, 'GL', 'GRL', 'Greenland', 'DKK', 'Krone', 'kr'),
       (84, 'GM', 'GMB', 'Gambia', 'GMD', 'Dalasi', 'D'),
       (85, 'GN', 'GIN', 'Guinea', 'GNF', 'Franc', NULL),
       (86, 'GP', 'GLP', 'Guadeloupe', 'EUR', 'Euro', 'â‚¬'),
       (87, 'GQ', 'GNQ', 'Equatorial Guinea', 'XAF', 'Franc', 'FCF'),
       (88, 'GR', 'GRC', 'Greece', 'EUR', 'Euro', 'â‚¬'),
       (89, 'GS', 'SGS', 'South Georgia and the South Sandwich Islands', 'GBP', 'Pound', 'Â£'),
       (90, 'GT', 'GTM', 'Guatemala', 'GTQ', 'Quetzal', 'Q'),
       (91, 'GU', 'GUM', 'Guam', 'USD', 'Dollar', '$'),
       (92, 'GW', 'GNB', 'Guinea-Bissau', 'XOF', 'Franc', NULL),
       (93, 'GY', 'GUY', 'Guyana', 'GYD', 'Dollar', '$'),
       (94, 'HK', 'HKG', 'Hong Kong', 'HKD', 'Dollar', '$'),
       (95, 'HM', 'HMD', 'Heard Island and McDonald Islands', 'AUD', 'Dollar', '$'),
       (96, 'HN', 'HND', 'Honduras', 'HNL', 'Lempira', 'L'),
       (97, 'HR', 'HRV', 'Croatia', 'HRK', 'Kuna', 'kn'),
       (98, 'HT', 'HTI', 'Haiti', 'HTG', 'Gourde', 'G'),
       (99, 'HU', 'HUN', 'Hungary', 'HUF', 'Forint', 'Ft'),
       (100, 'ID', 'IDN', 'Indonesia', 'IDR', 'Rupiah', 'Rp'),
       (101, 'IE', 'IRL', 'Ireland', 'EUR', 'Euro', 'â‚¬'),
       (102, 'IL', 'ISR', 'Israel', 'ILS', 'Shekel', 'â‚ª'),
       (103, 'IM', 'IMN', 'Isle of Man', 'GPD', 'Pound', 'Â£'),
       (104, 'IN', 'IND', 'India', 'INR', 'Rupee', 'â‚¨'),
       (105, 'IO', 'IOT', 'British Indian Ocean Territory', 'USD', 'Dollar', '$'),
       (106, 'IQ', 'IRQ', 'Iraq', 'IQD', 'Dinar', NULL),
       (107, 'IR', 'IRN', 'Iran', 'IRR', 'Rial', 'ï·¼'),
       (108, 'IS', 'ISL', 'Iceland', 'ISK', 'Krona', 'kr'),
       (109, 'IT', 'ITA', 'Italy', 'EUR', 'Euro', 'â‚¬'),
       (110, 'JE', 'JEY', 'Jersey', 'JEP', 'Pound', 'Â£'),
       (111, 'JM', 'JAM', 'Jamaica', 'JMD', 'Dollar', '$'),
       (112, 'JO', 'JOR', 'Jordan', 'JOD', 'Dinar', NULL),
       (113, 'JP', 'JPN', 'Japan', 'JPY', 'Yen', 'Â¥'),
       (114, 'KE', 'KEN', 'Kenya', 'KES', 'Shilling', NULL),
       (115, 'KG', 'KGZ', 'Kyrgyzstan', 'KGS', 'Som', 'Ð»Ð'),
       (116, 'KH', 'KHM', 'Cambodia', 'KHR', 'Riels', 'áŸ›'),
       (117, 'KI', 'KIR', 'Kiribati', 'AUD', 'Dollar', '$'),
       (118, 'KM', 'COM', 'Comoros', 'KMF', 'Franc', NULL),
       (119, 'KN', 'KNA', 'Saint Kitts and Nevis', 'XCD', 'Dollar', '$'),
       (120, 'KP', 'PRK', 'North Korea', 'KPW', 'Won', 'â‚©'),
       (121, 'KR', 'KOR', 'South Korea', 'KRW', 'Won', 'â‚©'),
       (122, 'KW', 'KWT', 'Kuwait', 'KWD', 'Dinar', NULL),
       (123, 'KY', 'CYM', 'Cayman Islands', 'KYD', 'Dollar', '$'),
       (124, 'KZ', 'KAZ', 'Kazakhstan', 'KZT', 'Tenge', 'Ð»Ð'),
       (125, 'LA', 'LAO', 'Laos', 'LAK', 'Kip', 'â‚­'),
       (126, 'LB', 'LBN', 'Lebanon', 'LBP', 'Pound', 'Â£'),
       (127, 'LC', 'LCA', 'Saint Lucia', 'XCD', 'Dollar', '$'),
       (128, 'LI', 'LIE', 'Liechtenstein', 'CHF', 'Franc', 'CHF'),
       (129, 'LK', 'LKA', 'Sri Lanka', 'LKR', 'Rupee', 'â‚¨'),
       (130, 'LR', 'LBR', 'Liberia', 'LRD', 'Dollar', '$'),
       (131, 'LS', 'LSO', 'Lesotho', 'LSL', 'Loti', 'L'),
       (132, 'LT', 'LTU', 'Lithuania', 'LTL', 'Litas', 'Lt'),
       (133, 'LU', 'LUX', 'Luxembourg', 'EUR', 'Euro', 'â‚¬'),
       (134, 'LV', 'LVA', 'Latvia', 'LVL', 'Lat', 'Ls'),
       (135, 'LY', 'LBY', 'Libya', 'LYD', 'Dinar', NULL),
       (136, 'MA', 'MAR', 'Morocco', 'MAD', 'Dirham', NULL),
       (137, 'MC', 'MCO', 'Monaco', 'EUR', 'Euro', 'â‚¬'),
       (138, 'MD', 'MDA', 'Moldova', 'MDL', 'Leu', NULL),
       (139, 'ME', 'MNE', 'Montenegro', 'EUR', 'Euro', 'â‚¬'),
       (140, 'MF', 'MAF', 'Saint Martin', 'EUR', 'Euro', 'â‚¬'),
       (141, 'MG', 'MDG', 'Madagascar', 'MGA', 'Ariary', NULL),
       (142, 'MH', 'MHL', 'Marshall Islands', 'USD', 'Dollar', '$'),
       (143, 'MK', 'MKD', 'Macedonia', 'MKD', 'Denar', 'Ð´Ð'),
       (144, 'ML', 'MLI', 'Mali', 'XOF', 'Franc', NULL),
       (145, 'MM', 'MMR', 'Myanmar', 'MMK', 'Kyat', 'K'),
       (146, 'MN', 'MNG', 'Mongolia', 'MNT', 'Tugrik', 'â‚®'),
       (147, 'MO', 'MAC', 'Macao', 'MOP', 'Pataca', 'MOP'),
       (148, 'MP', 'MNP', 'Northern Mariana Islands', 'USD', 'Dollar', '$'),
       (149, 'MQ', 'MTQ', 'Martinique', 'EUR', 'Euro', 'â‚¬'),
       (150, 'MR', 'MRT', 'Mauritania', 'MRO', 'Ouguiya', 'UM'),
       (151, 'MS', 'MSR', 'Montserrat', 'XCD', 'Dollar', '$'),
       (152, 'MT', 'MLT', 'Malta', 'MTL', 'Lira', NULL),
       (153, 'MU', 'MUS', 'Mauritius', 'MUR', 'Rupee', 'â‚¨'),
       (154, 'MV', 'MDV', 'Maldives', 'MVR', 'Rufiyaa', 'Rf'),
       (155, 'MW', 'MWI', 'Malawi', 'MWK', 'Kwacha', 'MK'),
       (156, 'MX', 'MEX', 'Mexico', 'MXN', 'Peso', '$'),
       (157, 'MY', 'MYS', 'Malaysia', 'MYR', 'Ringgit', 'RM'),
       (158, 'MZ', 'MOZ', 'Mozambique', 'MZN', 'Meticail', 'MT'),
       (159, 'NA', 'NAM', 'Namibia', 'NAD', 'Dollar', '$'),
       (160, 'NC', 'NCL', 'New Caledonia', 'XPF', 'Franc', NULL),
       (161, 'NE', 'NER', 'Niger', 'XOF', 'Franc', NULL),
       (162, 'NF', 'NFK', 'Norfolk Island', 'AUD', 'Dollar', '$'),
       (163, 'NG', 'NGA', 'Nigeria', 'NGN', 'Naira', 'â‚¦'),
       (164, 'NI', 'NIC', 'Nicaragua', 'NIO', 'Cordoba', 'C$'),
       (165, 'NL', 'NLD', 'Netherlands', 'EUR', 'Euro', 'â‚¬'),
       (166, 'NO', 'NOR', 'Norway', 'NOK', 'Krone', 'kr'),
       (167, 'NP', 'NPL', 'Nepal', 'NPR', 'Rupee', 'â‚¨'),
       (168, 'NR', 'NRU', 'Nauru', 'AUD', 'Dollar', '$'),
       (169, 'NU', 'NIU', 'Niue', 'NZD', 'Dollar', '$'),
       (170, 'NZ', 'NZL', 'New Zealand', 'NZD', 'Dollar', '$'),
       (171, 'OM', 'OMN', 'Oman', 'OMR', 'Rial', 'ï·¼'),
       (172, 'PA', 'PAN', 'Panama', 'PAB', 'Balboa', 'B/.'),
       (173, 'PE', 'PER', 'Peru', 'PEN', 'Sol', 'S/.'),
       (174, 'PF', 'PYF', 'French Polynesia', 'XPF', 'Franc', NULL),
       (175, 'PG', 'PNG', 'Papua New Guinea', 'PGK', 'Kina', NULL),
       (176, 'PH', 'PHL', 'Philippines', 'PHP', 'Peso', 'Php'),
       (177, 'PK', 'PAK', 'Pakistan', 'PKR', 'Rupee', 'â‚¨'),
       (178, 'PL', 'POL', 'Poland', 'PLN', 'Zloty', 'zÅ‚'),
       (179, 'PM', 'SPM', 'Saint Pierre and Miquelon', 'EUR', 'Euro', 'â‚¬'),
       (180, 'PN', 'PCN', 'Pitcairn', 'NZD', 'Dollar', '$'),
       (181, 'PR', 'PRI', 'Puerto Rico', 'USD', 'Dollar', '$'),
       (182, 'PS', 'PSE', 'Palestinian Territory', 'ILS', 'Shekel', 'â‚ª'),
       (183, 'PT', 'PRT', 'Portugal', 'EUR', 'Euro', 'â‚¬'),
       (184, 'PW', 'PLW', 'Palau', 'USD', 'Dollar', '$'),
       (185, 'PY', 'PRY', 'Paraguay', 'PYG', 'Guarani', 'Gs'),
       (186, 'QA', 'QAT', 'Qatar', 'QAR', 'Rial', 'ï·¼'),
       (187, 'RE', 'REU', 'Reunion', 'EUR', 'Euro', 'â‚¬'),
       (188, 'RO', 'ROU', 'Romania', 'RON', 'Leu', 'lei'),
       (189, 'RS', 'SRB', 'Serbia', 'RSD', 'Dinar', 'Ð”Ð'),
       (190, 'RU', 'RUS', 'Russia', 'RUB', 'Ruble', 'Ñ€Ñ'),
       (191, 'RW', 'RWA', 'Rwanda', 'RWF', 'Franc', NULL),
       (192, 'SA', 'SAU', 'Saudi Arabia', 'SAR', 'Rial', 'ï·¼'),
       (193, 'SB', 'SLB', 'Solomon Islands', 'SBD', 'Dollar', '$'),
       (194, 'SC', 'SYC', 'Seychelles', 'SCR', 'Rupee', 'â‚¨'),
       (195, 'SD', 'SDN', 'Sudan', 'SDD', 'Dinar', NULL),
       (196, 'SE', 'SWE', 'Sweden', 'SEK', 'Krona', 'kr'),
       (197, 'SG', 'SGP', 'Singapore', 'SGD', 'Dollar', '$'),
       (198, 'SH', 'SHN', 'Saint Helena', 'SHP', 'Pound', 'Â£'),
       (199, 'SI', 'SVN', 'Slovenia', 'EUR', 'Euro', 'â‚¬'),
       (200, 'SJ', 'SJM', 'Svalbard and Jan Mayen', 'NOK', 'Krone', 'kr'),
       (201, 'SK', 'SVK', 'Slovakia', 'SKK', 'Koruna', 'Sk'),
       (202, 'SL', 'SLE', 'Sierra Leone', 'SLL', 'Leone', 'Le'),
       (203, 'SM', 'SMR', 'San Marino', 'EUR', 'Euro', 'â‚¬'),
       (204, 'SN', 'SEN', 'Senegal', 'XOF', 'Franc', NULL),
       (205, 'SO', 'SOM', 'Somalia', 'SOS', 'Shilling', 'S'),
       (206, 'SR', 'SUR', 'Suriname', 'SRD', 'Dollar', '$'),
       (207, 'ST', 'STP', 'Sao Tome and Principe', 'STD', 'Dobra', 'Db'),
       (208, 'SV', 'SLV', 'El Salvador', 'SVC', 'Colone', '$'),
       (209, 'SY', 'SYR', 'Syria', 'SYP', 'Pound', 'Â£'),
       (210, 'SZ', 'SWZ', 'Swaziland', 'SZL', 'Lilangeni', NULL),
       (211, 'TC', 'TCA', 'Turks and Caicos Islands', 'USD', 'Dollar', '$'),
       (212, 'TD', 'TCD', 'Chad', 'XAF', 'Franc', NULL),
       (213, 'TF', 'ATF', 'French Southern Territories', 'EUR', 'Euro  ', 'â‚¬'),
       (214, 'TG', 'TGO', 'Togo', 'XOF', 'Franc', NULL),
       (215, 'TH', 'THA', 'Thailand', 'THB', 'Baht', 'à¸¿'),
       (216, 'TJ', 'TJK', 'Tajikistan', 'TJS', 'Somoni', NULL),
       (217, 'TK', 'TKL', 'Tokelau', 'NZD', 'Dollar', '$'),
       (218, 'TL', 'TLS', 'East Timor', 'USD', 'Dollar', '$'),
       (219, 'TM', 'TKM', 'Turkmenistan', 'TMM', 'Manat', 'm'),
       (220, 'TN', 'TUN', 'Tunisia', 'TND', 'Dinar', NULL),
       (221, 'TO', 'TON', 'Tonga', 'TOP',
        'Pa\'anga','T$'),(222,'TR','TUR','Turkey','TRY','Lira','YTL'),(223,'TT','TTO','Trinidad and Tobago','TTD','Dollar','TT$'),(224,'TV','TUV','Tuvalu','AUD','Dollar','$'),(225,'TW','TWN','Taiwan','TWD','Dollar','NT$'),(226,'TZ','TZA','Tanzania','TZS','Shilling',NULL),(227,'UA','UKR','Ukraine','UAH','Hryvnia','â‚´'),(228,'UG','UGA','Uganda','UGX','Shilling',NULL),(229,'UM','UMI','United States Minor Outlying Islands','USD','Dollar ','$'),(230,'US','USA','United States','USD','Dollar','$'),(231,'UY','URY','Uruguay','UYU','Peso','$U'),(232,'UZ','UZB','Uzbekistan','UZS','Som','Ð»Ð'),(233,'VA','VAT','Vatican','EUR','Euro','â‚¬'),(234,'VC','VCT','Saint Vincent and the Grenadines','XCD','Dollar','$'),(235,'VE','VEN','Venezuela','VEF','Bolivar','Bs'),(236,'VG','VGB','British Virgin Islands','USD','Dollar','$'),(237,'VI','VIR','U.S. Virgin Islands','USD','Dollar','$'),(238,'VN','VNM','Vietnam','VND','Dong','â‚«'),(239,'VU','VUT','Vanuatu','VUV','Vatu','Vt'),(240,'WF','WLF','Wallis and Futuna','XPF','Franc',NULL),(241,'WS','WSM','Samoa','WST','Tala','WS$'),(242,'YE','YEM','Yemen','YER','Rial','ï·¼'),(243,'YT','MYT','Mayotte','EUR','Euro','â‚¬'),(244,'ZA','ZAF','South Africa','ZAR','Rand','R'),(245,'ZM','ZMB','Zambia','ZMK','Kwacha','ZK'),(246,'ZW','ZWE','Zimbabwe','ZWD','Dollar','Z$'),(247,'CS','SCG','Serbia and Montenegro','RSD','Dinar','Ð”Ð');
/*!40000 ALTER TABLE `country_info` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cross_site_action`
--

DROP TABLE IF EXISTS `cross_site_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cross_site_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key1` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `key2` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `data` text CHARACTER SET utf8 COLLATE utf8_bin,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key1` (`key1`,`key2`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cross_site_action`
--

LOCK TABLES `cross_site_action` WRITE;
/*!40000 ALTER TABLE `cross_site_action` DISABLE KEYS */;
/*!40000 ALTER TABLE `cross_site_action` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `download_page`
--

DROP TABLE IF EXISTS `download_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `download_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `download_page` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `user_level_id` int(11) NOT NULL,
  `page_order` int(5) NOT NULL DEFAULT '0',
  `additional_javascript_code` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `additional_settings` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_level_id` (`user_level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `download_page`
--

LOCK TABLES `download_page` WRITE;
/*!40000 ALTER TABLE `download_page` DISABLE KEYS */;
INSERT INTO `download_page` VALUES (3,'compare_timed.html.twig',0,1,'','{\"download_wait\":60}'),(4,'compare_timed.html.twig',1,1,'','{\"download_wait\":60}');
/*!40000 ALTER TABLE `download_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `download_token`
--

DROP TABLE IF EXISTS `download_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `download_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `file_id` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `expiry` datetime NOT NULL,
  `download_speed` int(11) NOT NULL DEFAULT '0',
  `max_threads` int(3) NOT NULL DEFAULT '0',
  `file_transfer` int(1) NOT NULL DEFAULT '1',
  `process_ppd` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `expiry` (`expiry`),
  KEY `ip_address` (`ip_address`),
  KEY `file_id` (`file_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `download_token`
--

LOCK TABLES `download_token` WRITE;
/*!40000 ALTER TABLE `download_token` DISABLE KEYS */;
/*!40000 ALTER TABLE `download_token` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `download_tracker`
--

DROP TABLE IF EXISTS `download_tracker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `download_tracker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `download_username` varchar(65) CHARACTER SET utf8 DEFAULT NULL,
  `date_started` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `date_finished` datetime NOT NULL,
  `status` enum('downloading','finished','error','cancelled') CHARACTER SET utf8 NOT NULL,
  `start_offset` bigint(20) NOT NULL,
  `seek_end` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `date_updated` (`date_updated`),
  KEY `status` (`status`),
  KEY `file_id` (`file_id`),
  KEY `download_username` (`download_username`),
  KEY `date_started` (`date_started`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `download_tracker`
--

LOCK TABLES `download_tracker` WRITE;
/*!40000 ALTER TABLE `download_tracker` DISABLE KEYS */;
/*!40000 ALTER TABLE `download_tracker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file`
--

DROP TABLE IF EXISTS `file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `originalFilename` varchar(255) CHARACTER SET utf8 NOT NULL,
  `shortUrl` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `fileType` varchar(150) CHARACTER SET utf8 DEFAULT NULL,
  `extension` varchar(10) CHARACTER SET utf8 DEFAULT NULL,
  `fileSize` bigint(15) DEFAULT NULL,
  `localFilePath` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `userId` int(11) DEFAULT NULL,
  `uploadedUserId` int(11) DEFAULT NULL,
  `totalDownload` int(11) DEFAULT NULL,
  `uploadedIP` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
  `uploadedDate` timestamp NULL DEFAULT NULL,
  `statusId` int(2) DEFAULT NULL,
  `status` enum('active','trash','deleted') DEFAULT 'active',
  `visits` int(11) DEFAULT '0',
  `lastAccessed` timestamp NULL DEFAULT NULL,
  `deleteHash` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `folderId` int(11) DEFAULT NULL,
  `serverId` int(11) DEFAULT '1',
  `adminNotes` text CHARACTER SET utf8,
  `accessPassword` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `fileHash` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `minUserLevel` int(3) DEFAULT NULL,
  `linkedFileId` int(11) DEFAULT NULL,
  `keywords` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `isPublic` int(1) NOT NULL DEFAULT '1',
  `total_likes` int(11) NOT NULL DEFAULT '0',
  `uploadSource` enum('direct','remote','ftp','torrent','leech','webdav','api','fileimport','other') CHARACTER SET utf8 NOT NULL DEFAULT 'direct',
  `unique_hash` varchar(64) CHARACTER SET utf8 DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hash` (`unique_hash`),
  KEY `shortUrl` (`shortUrl`),
  KEY `originalFilename` (`originalFilename`),
  KEY `fileSize` (`fileSize`),
  KEY `visits` (`visits`),
  KEY `lastAccessed` (`lastAccessed`),
  KEY `extension` (`extension`),
  KEY `userId` (`userId`),
  KEY `statusId` (`statusId`),
  KEY `userId_2` (`userId`),
  KEY `uploadedDate` (`uploadedDate`),
  KEY `folderId` (`folderId`),
  KEY `serverId` (`serverId`),
  KEY `fileHash` (`fileHash`),
  KEY `linkedFileId` (`linkedFileId`),
  KEY `statusId_2` (`statusId`),
  KEY `uploadedUserId` (`uploadedUserId`),
  KEY `keywords` (`keywords`),
  KEY `status` (`status`),
  KEY `uploadedIP` (`uploadedIP`)
) ENGINE=InnoDB AUTO_INCREMENT=700 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file`
--

LOCK TABLES `file` WRITE;
/*!40000 ALTER TABLE `file` DISABLE KEYS */;
/*!40000 ALTER TABLE `file` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_action`
--

DROP TABLE IF EXISTS `file_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_action` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) DEFAULT NULL,
  `server_id` int(11) NOT NULL,
  `file_path` text CHARACTER SET utf8 NOT NULL,
  `is_uploaded_file` int(11) NOT NULL DEFAULT '0',
  `file_action` enum('delete','move','restore') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `action_detail` text CHARACTER SET utf8,
  `status` enum('pending','processing','complete','failed','cancelled') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `action_data` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `status_msg` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `last_updated` datetime DEFAULT NULL,
  `action_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `server_id` (`server_id`),
  KEY `date_created` (`date_created`),
  KEY `file_action` (`file_action`),
  KEY `action_date` (`action_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_action`
--

LOCK TABLES `file_action` WRITE;
/*!40000 ALTER TABLE `file_action` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_action` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_block_hash`
--

DROP TABLE IF EXISTS `file_block_hash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_block_hash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_hash` varchar(32) CHARACTER SET utf8 NOT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_hash` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_block_hash`
--

LOCK TABLES `file_block_hash` WRITE;
/*!40000 ALTER TABLE `file_block_hash` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_block_hash` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_folder`
--

DROP TABLE IF EXISTS `file_folder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_folder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `addedUserId` int(11) DEFAULT NULL,
  `parentId` int(11) DEFAULT NULL,
  `folderName` varchar(255) CHARACTER SET utf8 NOT NULL,
  `totalSize` bigint(15) DEFAULT '0',
  `isPublic` int(1) NOT NULL DEFAULT '0',
  `accessPassword` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `coverImageId` int(11) DEFAULT NULL,
  `watermarkPreviews` tinyint(1) NOT NULL,
  `showDownloadLinks` tinyint(1) NOT NULL,
  `urlHash` varchar(32) CHARACTER SET utf8 DEFAULT NULL,
  `status` enum('active','trash','deleted') CHARACTER SET utf8 DEFAULT 'active',
  `date_added` datetime DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urlHash` (`urlHash`),
  KEY `userId` (`userId`),
  KEY `parentId` (`parentId`),
  KEY `totalSize` (`totalSize`),
  KEY `isPublic` (`isPublic`),
  KEY `folderName` (`folderName`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_folder`
--

LOCK TABLES `file_folder` WRITE;
/*!40000 ALTER TABLE `file_folder` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_folder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_folder_share`
--

DROP TABLE IF EXISTS `file_folder_share`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_folder_share` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_key` varchar(128) CHARACTER SET latin1 NOT NULL,
  `date_created` datetime NOT NULL,
  `last_accessed` datetime DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `shared_with_user_id` int(11) DEFAULT NULL,
  `is_global` int(1) NOT NULL DEFAULT '0',
  `share_permission_level` enum('view','upload_download','all') CHARACTER SET utf8 NOT NULL DEFAULT 'view',
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_key` (`access_key`),
  KEY `folder_id` (`created_by_user_id`),
  KEY `is_global` (`is_global`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_folder_share`
--

LOCK TABLES `file_folder_share` WRITE;
/*!40000 ALTER TABLE `file_folder_share` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_folder_share` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_folder_share_item`
--

DROP TABLE IF EXISTS `file_folder_share_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_folder_share_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_folder_share_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_folder_share_id` (`file_folder_share_id`),
  KEY `file_id` (`file_id`),
  KEY `folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_folder_share_item`
--

LOCK TABLES `file_folder_share_item` WRITE;
/*!40000 ALTER TABLE `file_folder_share_item` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_folder_share_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_report`
--

DROP TABLE IF EXISTS `file_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `report_date` datetime NOT NULL,
  `reported_by_name` varchar(150) CHARACTER SET utf8 NOT NULL,
  `reported_by_email` varchar(255) CHARACTER SET utf8 NOT NULL,
  `reported_by_address` text CHARACTER SET utf8 NOT NULL,
  `reported_by_telephone_number` varchar(30) CHARACTER SET utf8 NOT NULL,
  `digital_signature` varchar(150) CHARACTER SET utf8 NOT NULL,
  `report_status` enum('pending','cancelled','accepted') CHARACTER SET utf8 NOT NULL,
  `reported_by_ip` varchar(45) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `other_information` text CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_report`
--

LOCK TABLES `file_report` WRITE;
/*!40000 ALTER TABLE `file_report` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_report` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_server`
--

DROP TABLE IF EXISTS `file_server`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_server` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serverLabel` varchar(100) CHARACTER SET utf8 NOT NULL,
  `serverType` varchar(50) CHARACTER SET utf8 DEFAULT 'local',
  `ipAddress` varchar(255) CHARACTER SET utf8 NOT NULL,
  `ftpPort` int(11) NOT NULL DEFAULT '21',
  `ftpUsername` varchar(50) CHARACTER SET utf8 NOT NULL,
  `ftpPassword` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `statusId` int(11) NOT NULL DEFAULT '1',
  `scriptRootPath` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `storagePath` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `fileServerDomainName` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `scriptPath` varchar(150) CHARACTER SET utf8 DEFAULT NULL,
  `totalSpaceUsed` float(18,0) NOT NULL DEFAULT '0',
  `totalFiles` int(11) NOT NULL DEFAULT '0',
  `maximumStorageBytes` bigint(20) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT '0',
  `routeViaMainSite` int(1) NOT NULL DEFAULT '0',
  `lastFileActionQueueProcess` datetime DEFAULT NULL,
  `serverConfig` text CHARACTER SET utf8,
  `dlAccelerator` int(1) NOT NULL DEFAULT '0',
  `serverAccess` text CHARACTER SET utf8,
  `geoUploadCountries` varchar(1000) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statusId` (`statusId`),
  KEY `totalFiles` (`totalFiles`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_server`
--

LOCK TABLES `file_server` WRITE;
/*!40000 ALTER TABLE `file_server` DISABLE KEYS */;
INSERT INTO `file_server` VALUES (1,'Local Default','local','',0,'',NULL,2,NULL,'files/',NULL,NULL,0,0,0,0,0,NULL,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `file_server` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_server_container`
--

DROP TABLE IF EXISTS `file_server_container`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_server_container` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) CHARACTER SET utf8 NOT NULL,
  `entrypoint` varchar(50) CHARACTER SET utf8 NOT NULL,
  `expected_config_json` text CHARACTER SET utf8,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_server_container`
--

LOCK TABLES `file_server_container` WRITE;
/*!40000 ALTER TABLE `file_server_container` DISABLE KEYS */;
INSERT INTO `file_server_container` VALUES (1,'FTP','flysystem_ftp','{\"host\":{\"label\":\"FTP Host\",\"type\":\"text\",\"default\":\"\"},\"username\":{\"label\":\"FTP Username\",\"type\":\"text\",\"default\":\"\"},\"password\":{\"label\":\"FTP Password\",\"type\":\"text\",\"default\":\"\"},\"port\":{\"label\":\"Port\",\"type\":\"number\",\"default\":\"21\"},\"root\":{\"label\":\"Root Path\",\"type\":\"text\",\"default\":\"\\/\"},\"passive\":{\"label\":\"Enable Passive Mode\",\"type\":\"select\",\"default\":\"1\",\"option_values\":[\"No\",\"Yes\"]},\"ssl\":{\"label\":\"Use SSL\",\"type\":\"select\",\"default\":\"0\",\"option_values\":[\"No\",\"Yes\"]},\"timeout\":{\"label\":\"Connection Timeout\",\"type\":\"number\",\"default\":\"30\"}}',1),(2,'SFTP','flysystem_sftp','{\"host\":{\"label\":\"SFTP Host\",\"type\":\"text\",\"default\":\"\"},\"username\":{\"label\":\"SFTP Username\",\"type\":\"text\",\"default\":\"\"},\"password\":{\"label\":\"SFTP Password\",\"type\":\"text\",\"default\":\"\"},\"port\":{\"label\":\"Port\",\"type\":\"number\",\"default\":\"21\"},\"root\":{\"label\":\"Root Path\",\"type\":\"text\",\"default\":\"\\/\"},\"timeout\":{\"label\":\"Connection Timeout\",\"type\":\"number\",\"default\":\"30\"}}',1),(3,'Amazon S3','flysystem_aws','{\"key\":{\"label\":\"Public Key\",\"type\":\"text\",\"default\":\"\"},\"secret\":{\"label\":\"Secret Key\",\"type\":\"text\",\"default\":\"\"},\"bucket\":{\"label\":\"S3 Bucket\",\"type\":\"text\",\"default\":\"\"},\"region\":{\"label\":\"Your Bucket Region\",\"type\":\"select\",\"default\":\"us-east-1\",\"option_values\":{\"us-east-1\":\"US East (N. Virginia)\",\"us-east-2\":\"US East (Ohio) - us-east-2\",\"us-west-1\":\"US West (N. California) - us-west-1\",\"us-west-2\":\"US West (Oregon) - us-west-2\",\"ca-central-1\":\"Canada (Central) - ca-central-1\",\"ap-south-1\":\"Asia Pacific (Mumbai) - ap-south-1\",\"ap-northeast-2\":\"Asia Pacific (Seoul) - ap-northeast-2\",\"ap-southeast-1\":\"Asia Pacific (Singapore) - ap-southeast-1\",\"ap-southeast-2\":\"Asia Pacific (Sydney) - ap-southeast-2\",\"ap-northeast-1\":\"Asia Pacific (Tokyo) - ap-northeast-1\",\"eu-central-1\":\"EU (Frankfurt) - eu-central-1\",\"eu-west-1\":\"EU (Ireland) - eu-west-1\",\"eu-west-2\":\"EU (London) - eu-west-2\",\"sa-east-1\":\"South America (S\\u00e3o Paulo) - sa-east-1\"}},\"version\":{\"label\":\"Version (Don\'t Change)\",\"type\":\"string\",\"default\":\"latest\"}}',1),(4,'Rackspace Cloud Files','flysystem_rackspace','{\"username\":{\"label\":\"Rackspace Username\",\"type\":\"text\",\"default\":\"\"},\"apiKey\":{\"label\":\"API Key\",\"type\":\"text\",\"default\":\"\"},\"container\":{\"label\":\"Cloud Files Container\",\"type\":\"text\",\"default\":\"\"},\"region\":{\"label\":\"Container Region\",\"type\":\"select\",\"default\":\"IAD\",\"option_values\":{\"IAD\":\"Nothern Virginia (IAD)\",\"DFW\":\"Dallas (DFW)\",\"HKG\":\"Hong Kong (HKG)\",\"SYD\":\"Sydney (SYD)\",\"LON\":\"London (LON)\"}}}',1),(5,'Azure Blob Storage','flysystem_azure','{\"account-name\":{\"label\":\"Account Name\",\"type\":\"text\",\"default\":\"\"},\"api-key\":{\"label\":\"API Key\",\"type\":\"text\",\"default\":\"\"},\"container\":{\"label\":\"Files Container\",\"type\":\"text\",\"default\":\"\"}}',0),(6,'Backblaze B2','flysystem_backblaze_b2','{\"account_id\":{\"label\":\"Master Key Id\",\"type\":\"text\",\"default\":\"\"},\"application_key\":{\"label\":\"Master Application Key (Master Only Supported)\",\"type\":\"text\",\"default\":\"\"},\"bucket\":{\"label\":\"Bucket Name\",\"type\":\"text\",\"default\":\"\"}}',1);
/*!40000 ALTER TABLE `file_server_container` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_server_status`
--

DROP TABLE IF EXISTS `file_server_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_server_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_server_status`
--

LOCK TABLES `file_server_status` WRITE;
/*!40000 ALTER TABLE `file_server_status` DISABLE KEYS */;
INSERT INTO `file_server_status` VALUES (1,'disabled'),(2,'active'),(3,'read only');
/*!40000 ALTER TABLE `file_server_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_status`
--

DROP TABLE IF EXISTS `file_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_status`
--

LOCK TABLES `file_status` WRITE;
/*!40000 ALTER TABLE `file_status` DISABLE KEYS */;
INSERT INTO `file_status` VALUES (1,'active'),(2,'user removed'),(3,'admin removed'),(4,'copyright removed'),(5,'system expired');
/*!40000 ALTER TABLE `file_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internal_notification`
--

DROP TABLE IF EXISTS `internal_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internal_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_user_id` int(11) NOT NULL,
  `date_added` datetime NOT NULL,
  `content` varchar(255) CHARACTER SET utf8 NOT NULL,
  `notification_icon` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT 'entypo-info',
  `href_url` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `onclick` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `is_read` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `to_user_id` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internal_notification`
--

LOCK TABLES `internal_notification` WRITE;
/*!40000 ALTER TABLE `internal_notification` DISABLE KEYS */;
/*!40000 ALTER TABLE `internal_notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `language`
--

DROP TABLE IF EXISTS `language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `languageName` varchar(255) CHARACTER SET utf8 NOT NULL,
  `isLocked` int(1) NOT NULL,
  `isActive` int(1) NOT NULL DEFAULT '1',
  `flag` varchar(20) CHARACTER SET utf8 NOT NULL,
  `direction` varchar(3) CHARACTER SET utf8 NOT NULL DEFAULT 'LTR',
  `language_code` varchar(5) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `isLocked` (`isLocked`),
  KEY `isActive` (`isActive`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `language`
--

LOCK TABLES `language` WRITE;
/*!40000 ALTER TABLE `language` DISABLE KEYS */;
INSERT INTO `language` VALUES (1,'English (en)',1,1,'us','LTR','us');
/*!40000 ALTER TABLE `language` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `language_content`
--

DROP TABLE IF EXISTS `language_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `languageKeyId` int(11) NOT NULL,
  `languageId` int(11) NOT NULL,
  `content` text CHARACTER SET utf8 NOT NULL,
  `is_locked` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `languageKeyId` (`languageKeyId`),
  KEY `languageId` (`languageId`)
) ENGINE=InnoDB AUTO_INCREMENT=1062 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `language_content`
--

LOCK TABLES `language_content` WRITE;
/*!40000 ALTER TABLE `language_content` DISABLE KEYS */;
INSERT INTO `language_content` VALUES (581,1,1,'home',1),(582,3,1,'banned words / urls',1),(583,4,1,'admin users',1),(584,5,1,'banned ips',1),(585,6,1,'site settings',1),(586,7,1,'languages',1),(587,8,1,'logout',1),(588,9,1,'Language Details',1),(589,10,1,'Are you sure you want to remove this IP ban?',1),(590,11,1,'Are you sure you want to update the status of this user?',1),(591,12,1,'view',1),(592,13,1,'disable',1),(593,14,1,'enable',1),(594,15,1,'Are you sure you want to remove this banned word?',1),(595,16,1,'IP address appears to be invalid, please try again.',1),(596,17,1,'IP address is already in the blocked list.',1),(597,18,1,'There was a problem inserting/updating the record, please try again later.',1),(598,19,1,'Banned word is already in the list.',1),(599,20,1,'Language already in the system.',1),(600,21,1,'Username must be between 6-16 characters long.',1),(601,22,1,'Password must be between 6-16 characters long.',1),(602,23,1,'Please enter the firstname.',1),(603,24,1,'Please enter the lastname.',1),(604,25,1,'Please enter the email address.',1),(605,26,1,'The email address you entered appears to be invalid.',1),(606,27,1,'Copyright',1),(607,28,1,'Support',1),(608,30,1,'Admin Panel',1),(609,31,1,'Logged in as',1),(610,32,1,'To ban an IP Address <a href=\"#\" onClick=\"displayBannedIpPopup(); return false;\">click here</a> or delete any existing ones below',1),(611,33,1,'Add banned IP address',1),(612,34,1,'remove',1),(613,35,1,'IP Address',1),(614,36,1,'Ban From',1),(615,37,1,'Notes',1),(616,38,1,'Add Banned IP',1),(617,39,1,'There was an error submitting the form, please try again later.',1),(618,40,1,'Enter IP Address details',1),(619,41,1,'To ban an word within the original url <a href=\"#\" onClick=\"displayBannedWordsPopup(); return false;\">click here</a> or delete any existing ones below',1),(620,42,1,'Add banned word',1),(621,43,1,'Banned Word',1),(622,44,1,'Date Banned',1),(623,45,1,'Ban Notes',1),(624,46,1,'Action',1),(625,47,1,'Enter Banned Word details',1),(626,48,1,'Use the main navigation above to manage this site. A quick overview of the site can be seen below',1),(627,49,1,'New Files (last 14 days)',1),(628,50,1,'New Files (last 12 months)',1),(629,51,1,'Urls',1),(630,52,1,'active',1),(631,53,1,'disabled',1),(632,54,1,'spam',1),(633,55,1,'expired',1),(634,56,1,'Total active files',1),(635,57,1,'Total disabled files',1),(636,58,1,'Total downloads to all files',1),(637,59,1,'Item Name',1),(638,60,1,'Value',1),(639,61,1,'Manage the available content for the selected language. Click on any of the \'Translated Content\' cells to edit the value.',1),(640,62,1,'Select a language to manage or <a href=\'#\' onClick=\'displayAddLanguagePopup(); return false;\'>add a new one here</a>. NOTE Once translated, to set the site default language go to the <a href=\'settings.php\'>site settings</a> area.',1),(641,63,1,'Language Key',1),(642,64,1,'Default Content',1),(643,65,1,'Translated Content',1),(644,66,1,'Error Changes to this section can not be made within demo mode.',1),(645,67,1,'Manage other languages',1),(646,68,1,'There is no available content.',1),(647,69,1,'select language',1),(648,70,1,'Add Language',1),(649,71,1,'Language Name',1),(650,72,1,'Click on any of the items within the \"Config Value\" column below to edit',1),(651,73,1,'Group',1),(652,74,1,'Config Description',1),(653,75,1,'Config Value',1),(654,76,1,'Filter results',1),(655,77,1,'Double click on any of the users below to edit the account information or <a href=\"#\" onClick=\"displayUserPopup(); return false;\">click here to add a new user</a>',1),(656,78,1,'Add new user',1),(657,79,1,'Username',1),(658,80,1,'Email Address',1),(659,81,1,'Account Type',1),(660,82,1,'Last Login',1),(661,83,1,'Account Status',1),(662,84,1,'Password',1),(663,85,1,'Title',1),(664,86,1,'Firstname',1),(665,87,1,'Lastname',1),(666,88,1,'Enter user details',1),(667,90,1,'Terms &amp; Conditions',1),(668,515,1,'Main Navigation',1),(669,92,1,'Created By',1),(678,108,1,'please wait',1),(679,109,1,'There was a general site error, please try again later.',1),(680,110,1,'Error',1),(681,153,1,'visits',1),(682,154,1,'created',1),(683,155,1,'Visitors',1),(684,156,1,'Countries',1),(685,157,1,'Top Referrers',1),(686,158,1,'Browsers',1),(687,159,1,'Operating Systems',1),(688,160,1,'last 24 hours',1),(689,161,1,'last 7 days',1),(690,162,1,'last 30 days',1),(691,163,1,'last 12 months',1),(692,164,1,'Hour',1),(693,165,1,'Visits',1),(694,166,1,'Date',1),(695,167,1,'Total visits',1),(696,168,1,'Percentage',1),(697,169,1,'Day',1),(698,170,1,'Month',1),(699,171,1,'Country',1),(700,172,1,'Site',1),(701,173,1,'Browser',1),(702,174,1,'Operating System',1),(703,175,1,'Andorra',1),(704,176,1,'United Arab Emirates',1),(705,177,1,'Afghanistan',1),(706,178,1,'Antigua And Barbuda',1),(707,179,1,'Anguilla',1),(708,180,1,'Albania',1),(709,181,1,'Armenia',1),(710,182,1,'Netherlands Antilles',1),(711,183,1,'Angola',1),(712,184,1,'Antarctica',1),(713,185,1,'Argentina',1),(714,186,1,'American Samoa',1),(715,187,1,'Austria',1),(716,188,1,'Australia',1),(717,189,1,'Aruba',1),(718,190,1,'Azerbaijan',1),(719,191,1,'Bosnia And Herzegovina',1),(720,192,1,'Barbados',1),(721,193,1,'Bangladesh',1),(722,194,1,'Belgium',1),(723,195,1,'Burkina Faso',1),(724,196,1,'Bulgaria',1),(725,197,1,'Bahrain',1),(726,198,1,'Burundi',1),(727,199,1,'Benin',1),(728,200,1,'Bermuda',1),(729,201,1,'Brunei Darussalam',1),(730,202,1,'Bolivia',1),(731,203,1,'Brazil',1),(732,204,1,'Bahamas',1),(733,205,1,'Bhutan',1),(734,206,1,'Botswana',1),(735,207,1,'Belarus',1),(736,208,1,'Belize',1),(737,209,1,'Canada',1),(738,210,1,'The Democratic Republic Of The Congo',1),(739,211,1,'Central African Republic',1),(740,212,1,'Congo',1),(741,213,1,'Switzerland',1),(742,214,1,'Cote Divoire',1),(743,215,1,'Cook Islands',1),(744,216,1,'Chile',1),(745,217,1,'Cameroon',1),(746,218,1,'China',1),(747,219,1,'Colombia',1),(748,220,1,'Costa Rica',1),(749,221,1,'Serbia And Montenegro',1),(750,222,1,'Cuba',1),(751,223,1,'Cape Verde',1),(752,224,1,'Cyprus',1),(753,225,1,'Czech Republic',1),(754,226,1,'Germany',1),(755,227,1,'Djibouti',1),(756,228,1,'Denmark',1),(757,229,1,'Dominica',1),(758,230,1,'Dominican Republic',1),(759,231,1,'Algeria',1),(760,232,1,'Ecuador',1),(761,233,1,'Estonia',1),(762,234,1,'Egypt',1),(763,235,1,'Eritrea',1),(764,236,1,'Spain',1),(765,237,1,'Ethiopia',1),(766,238,1,'European Union',1),(767,239,1,'Finland',1),(768,240,1,'Fiji',1),(769,241,1,'Falkland Islands (Malvinas)',1),(770,242,1,'Federated States Of Micronesia',1),(771,243,1,'Faroe Islands',1),(772,244,1,'France',1),(773,245,1,'Gabon',1),(774,246,1,'United Kingdom',1),(775,247,1,'Grenada',1),(776,248,1,'Georgia',1),(777,249,1,'French Guiana',1),(778,250,1,'Ghana',1),(779,251,1,'Gibraltar',1),(780,252,1,'Greenland',1),(781,253,1,'Gambia',1),(782,254,1,'Guinea',1),(783,255,1,'Guadeloupe',1),(784,256,1,'Equatorial Guinea',1),(785,257,1,'Greece',1),(786,258,1,'South Georgia And The South Sandwich Islands',1),(787,259,1,'Guatemala',1),(788,260,1,'Guam',1),(789,261,1,'Guinea-Bissau',1),(790,262,1,'Guyana',1),(791,263,1,'Hong Kong',1),(792,264,1,'Honduras',1),(793,265,1,'Croatia',1),(794,266,1,'Haiti',1),(795,267,1,'Hungary',1),(796,268,1,'Indonesia',1),(797,269,1,'Ireland',1),(798,270,1,'Israel',1),(799,271,1,'India',1),(800,272,1,'British Indian Ocean Territory',1),(801,273,1,'Iraq',1),(802,274,1,'Islamic Republic Of Iran',1),(803,275,1,'Iceland',1),(804,276,1,'Italy',1),(805,277,1,'Jamaica',1),(806,278,1,'Jordan',1),(807,279,1,'Japan',1),(808,280,1,'Kenya',1),(809,281,1,'Kyrgyzstan',1),(810,282,1,'Cambodia',1),(811,283,1,'Kiribati',1),(812,284,1,'Comoros',1),(813,285,1,'Saint Kitts And Nevis',1),(814,286,1,'Republic Of Korea',1),(815,287,1,'Kuwait',1),(816,288,1,'Cayman Islands',1),(817,289,1,'Kazakhstan',1),(818,290,1,'Lao Peoples Democratic Republic',1),(819,291,1,'Lebanon',1),(820,292,1,'Saint Lucia',1),(821,293,1,'Liechtenstein',1),(822,294,1,'Sri Lanka',1),(823,295,1,'Liberia',1),(824,296,1,'Lesotho',1),(825,297,1,'Lithuania',1),(826,298,1,'Luxembourg',1),(827,299,1,'Latvia',1),(828,300,1,'Libyan Arab Jamahiriya',1),(829,301,1,'Morocco',1),(830,302,1,'Monaco',1),(831,303,1,'Republic Of Moldova',1),(832,304,1,'Madagascar',1),(833,305,1,'Marshall Islands',1),(834,306,1,'The Former Yugoslav Republic Of Macedonia',1),(835,307,1,'Mali',1),(836,308,1,'Myanmar',1),(837,309,1,'Mongolia',1),(838,310,1,'Macao',1),(839,311,1,'Northern Mariana Islands',1),(840,312,1,'Martinique',1),(841,313,1,'Mauritania',1),(842,314,1,'Malta',1),(843,315,1,'Mauritius',1),(844,316,1,'Maldives',1),(845,317,1,'Malawi',1),(846,318,1,'Mexico',1),(847,319,1,'Malaysia',1),(848,320,1,'Mozambique',1),(849,321,1,'Namibia',1),(850,322,1,'New Caledonia',1),(851,323,1,'Niger',1),(852,324,1,'Norfolk Island',1),(853,325,1,'Nigeria',1),(854,326,1,'Nicaragua',1),(855,327,1,'Netherlands',1),(856,328,1,'Norway',1),(857,329,1,'Nepal',1),(858,330,1,'Nauru',1),(859,331,1,'Niue',1),(860,332,1,'New Zealand',1),(861,333,1,'Oman',1),(862,334,1,'Panama',1),(863,335,1,'Peru',1),(864,336,1,'French Polynesia',1),(865,337,1,'Papua New Guinea',1),(866,338,1,'Philippines',1),(867,339,1,'Pakistan',1),(868,340,1,'Poland',1),(869,341,1,'Puerto Rico',1),(870,342,1,'Palestinian Territory',1),(871,343,1,'Portugal',1),(872,344,1,'Palau',1),(873,345,1,'Paraguay',1),(874,346,1,'Qatar',1),(875,347,1,'Reunion',1),(876,348,1,'Romania',1),(877,349,1,'Russian Federation',1),(878,350,1,'Rwanda',1),(879,351,1,'Saudi Arabia',1),(880,352,1,'Solomon Islands',1),(881,353,1,'Seychelles',1),(882,354,1,'Sudan',1),(883,355,1,'Sweden',1),(884,356,1,'Singapore',1),(885,357,1,'Slovenia',1),(886,358,1,'Slovakia (Slovak Republic)',1),(887,359,1,'Sierra Leone',1),(888,360,1,'San Marino',1),(889,361,1,'Senegal',1),(890,362,1,'Somalia',1),(891,363,1,'Suriname',1),(892,364,1,'Sao Tome And Principe',1),(893,365,1,'El Salvador',1),(894,366,1,'Syrian Arab Republic',1),(895,367,1,'Swaziland',1),(896,368,1,'Chad',1),(897,369,1,'French Southern Territories',1),(898,370,1,'Togo',1),(899,371,1,'Thailand',1),(900,372,1,'Tajikistan',1),(901,373,1,'Tokelau',1),(902,374,1,'Timor-Leste',1),(903,375,1,'Turkmenistan',1),(904,376,1,'Tunisia',1),(905,377,1,'Tonga',1),(906,378,1,'Turkey',1),(907,379,1,'Trinidad And Tobago',1),(908,380,1,'Tuvalu',1),(909,381,1,'Taiwan Province Of China',1),(910,382,1,'United Republic Of Tanzania',1),(911,383,1,'Ukraine',1),(912,384,1,'Uganda',1),(913,385,1,'United States',1),(914,386,1,'Uruguay',1),(915,387,1,'Uzbekistan',1),(916,388,1,'Holy See (Vatican City State)',1),(917,389,1,'Saint Vincent And The Grenadines',1),(918,390,1,'Venezuela',1),(919,391,1,'Virgin Islands',1),(920,392,1,'Virgin Islands',1),(921,393,1,'Viet Nam',1),(922,394,1,'Vanuatu',1),(923,395,1,'Samoa',1),(924,396,1,'Yemen',1),(925,397,1,'Mayotte',1),(926,398,1,'Serbia And Montenegro (Formally Yugoslavia)',1),(927,399,1,'South Africa',1),(928,400,1,'Zambia',1),(929,401,1,'Zimbabwe',1),(930,402,1,'Unknown',1),(932,405,1,'register',1),(933,408,1,'Login',1),(935,410,1,'Registration completed',1),(936,411,1,'Your registration has been completed.',1),(937,412,1,'registration, completed, file, hosting, site',1),(938,413,1,'Thank you for registering!',1),(939,414,1,'We\'ve sent an email to your registered email address with your access password. Please check your spam filters to ensure emails from this site get through. ',1),(940,415,1,'Emails from this site are sent from ',1),(941,416,1,'Login',1),(942,417,1,'Login to your account',1),(943,418,1,'login, register, short url',1),(944,419,1,'Your username and password are invalid',1),(945,420,1,'Account Login',1),(946,421,1,'Please enter your username and password below to login.',1),(947,422,1,'Your account username. 6 characters or more and alpha numeric.',1),(948,423,1,'Your account password. Min 6 characters, alpha numeric, no spaces.',1),(949,428,1,'Please enter your username',1),(950,429,1,'Account Home',1),(951,430,1,'Your Account Home',1),(952,431,1,'account, home, file, hosting, members, area',1),(953,433,1,'FAQ',1),(954,434,1,'FAQ',1),(955,435,1,'Frequently Asked Questions',1),(956,436,1,'faq, frequently, asked, questions, file, hosting, site',1),(957,437,1,'Please enter your password',1),(958,511,1,'Report Abuse',1),(959,441,1,'Register Account',1),(962,446,1,'info',1),(963,447,1,'Email Confirm',1),(964,449,1,'Created/Last Visited',1),(965,450,1,'Status',1),(966,451,1,'Options',1),(967,452,1,'upload file',1),(968,453,1,'Register',1),(969,454,1,'Register for an account',1),(970,455,1,'register, account, short, url, user',1),(971,456,1,'your files',1),(972,457,1,'File has been removed.',1),(973,458,1,'Uploaded',1),(974,459,1,'downloads',1),(975,460,1,'download now',1),(976,461,1,'loading file, please wait',1),(977,462,1,'Download File',1),(978,463,1,'Download file',1),(979,464,1,'download, file, upload, mp3, avi, zip',1),(980,465,1,'Your Files',1),(981,466,1,'Download Url',1),(982,467,1,'Uploaded/Last Visited',1),(983,468,1,'Download Url/Filename',1),(984,469,1,'Total Active Files',1),(985,470,1,'Total Inactive Files',1),(986,471,1,'Total Downloads',1),(987,472,1,'user removed',1),(988,473,1,'files',1),(989,474,1,'Manage Files',1),(990,475,1,'Filter Results',1),(991,476,1,'Show Disabled',1),(992,477,1,'Export File Data',1),(993,478,1,'File has been removed by the site administrator.',1),(994,479,1,'Show Removed',1),(995,480,1,'admin removed',1),(996,481,1,'Delete File',1),(997,482,1,'Delete File',1),(998,483,1,'delete, remove, file',1),(999,484,1,'Delete File',1),(1000,485,1,'Please confirm whether to delete the file below.',1),(1001,486,1,'Cancel',1),(1002,487,1,'report file',1),(1003,488,1,'upgrade account',1),(1004,489,1,'Terms and Conditions',1),(1005,490,1,'Terms and Conditions',1),(1006,491,1,'terms, and, conditions, file, hosting, site',1),(1007,492,1,'extend account',1),(1008,493,1,'Extend Account',1),(1009,494,1,'Extend Your Account',1),(1010,495,1,'extend, account, paid, membership, upload, download, site',1),(1011,496,1,'Payment Complete',1),(1012,497,1,'Payment Complete',1),(1013,498,1,'payment, complete, file, hosting, site',1),(1014,499,1,'premium account benefits',1),(1015,500,1,'account benefits',1),(1016,501,1,' Information',1),(1017,502,1,'Information about ',1),(1018,503,1,', share, information, file, upload, download, site',1),(1019,504,1,'download urls',1),(1020,505,1,'statistics',1),(1021,506,1,'share',1),(1022,507,1,'other options',1),(1023,508,1,'Enter the details of the file (as above) you wish to report.',1),(1024,510,1,'Please enter the details of the reported file.',1),(1025,516,1,'Legal Bits',1),(1026,517,1,'Your Account',1),(1027,518,1,'days',1),(1028,519,1,'Premium',1),(1029,520,1,'Pay via PayPal',1),(1030,521,1,'secure payment',1),(1031,522,1,'100% Safe & Anonymous',1),(1032,523,1,'Add files...',1),(1033,524,1,'Start upload',1),(1034,525,1,'Cancel upload',1),(1035,526,1,'Select files',1),(1036,527,1,'Drag &amp; drop files here or click to browse...',1),(1037,528,1,'Max file size',1),(1038,529,1,'add file',1),(1039,530,1,'copy all links',1),(1040,531,1,'File uploads completed.',1),(1041,532,1,'Delete Url',1),(1042,533,1,'Stats Url',1),(1043,534,1,'HTML Code',1),(1044,535,1,'Forum Code',1),(1045,536,1,'Full Info',1),(1046,537,1,'click here',1),(1047,538,1,'extend',1),(1048,539,1,'reverts to free account',1),(1049,540,1,'never',1),(1050,541,1,'filename',1),(1051,542,1,'download',1),(1052,543,1,'filesize',1),(1053,544,1,'url',1),(1054,545,1,'Download from',1),(1055,546,1,'share file',1),(1056,549,1,'upload, share, track, file, hosting, host',1),(1057,548,1,'Upload, share, track, manage your files in one simple to use file host.',1),(1058,547,1,'Upload Files',1),(1059,550,1,'Please enter your firstname',1),(1060,550,1,'Please enter your firstname',1),(1061,551,1,'Click here to browse your files...',1);
/*!40000 ALTER TABLE `language_content` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `language_key`
--

DROP TABLE IF EXISTS `language_key`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language_key` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `languageKey` varchar(255) CHARACTER SET utf8 NOT NULL,
  `defaultContent` text CHARACTER SET utf8 NOT NULL,
  `isAdminArea` int(1) NOT NULL DEFAULT '0',
  `foundOnScan` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `languageKey` (`languageKey`)
) ENGINE=InnoDB AUTO_INCREMENT=557 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `language_key`
--

LOCK TABLES `language_key` WRITE;
/*!40000 ALTER TABLE `language_key` DISABLE KEYS */;
INSERT INTO `language_key` VALUES (1,'home','home',1,0),(3,'banned_words_urls','banned words / urls',1,0),(4,'admin_users','admin users',1,0),(5,'banned_ips','banned ips',1,0),(6,'site_settings','site settings',1,0),(7,'languages','languages',1,0),(8,'logout','logout',1,0),(9,'language_details','Language Details',1,0),(10,'are_you_sure_you_want_to_remove_this_ip_ban','Are you sure you want to remove this IP ban?',1,0),(11,'are_you_sure_update_user_status','Are you sure you want to update the status of this user?',1,0),(12,'view','view',1,0),(13,'disable','disable',1,0),(14,'enable','enable',1,0),(15,'are_you_sure_remove_banned_word','Are you sure you want to remove this banned word?',1,0),(16,'ip_address_invalid_try_again','IP address appears to be invalid, please try again.',1,0),(17,'ip_address_already_blocked','IP address is already in the blocked list.',1,0),(18,'error_problem_record','There was a problem inserting/updating the record, please try again later.',1,0),(19,'banned_word_already_in_list','Banned word is already in the list.',1,0),(20,'language_already_in_system','Language already in the system.',1,0),(21,'username_length_invalid','Username must be between 6-16 characters long.',1,0),(22,'password_length_invalid','Password must be between 6-16 characters long.',1,0),(23,'enter_first_name','Please enter the firstname.',1,0),(24,'enter_last_name','Please enter the lastname.',1,0),(25,'enter_email_address','Please enter the email address.',1,0),(26,'entered_email_address_invalid','The email address you entered appears to be invalid.',1,0),(27,'copyright','Copyright',1,0),(28,'support','Support',1,0),(30,'admin_panel','Admin Panel',1,0),(31,'logged_in_as','Logged in as',1,0),(32,'banned_ips_intro','To ban an IP Address <a href=\"#\" onClick=\"displayBannedIpPopup(); return false;\">click here</a> or delete any existing ones below',1,0),(33,'banned_ips_add_banned_ip','Add banned IP address',1,0),(34,'remove','remove',1,0),(35,'ip_address','IP Address',1,0),(36,'ban_from','Ban From',1,0),(37,'notes','Notes',1,0),(38,'add_banned_ip','Add Banned IP',1,0),(39,'error_submitting_form','There was an error submitting the form, please try again later.',1,0),(40,'enter_ip_address_details','Enter IP Address details',1,0),(41,'banned_terms_intro','To ban an word within the original url <a href=\"#\" onClick=\"displayBannedWordsPopup(); return false;\">click here</a> or delete any existing ones below',1,0),(42,'add_banned_term','Add banned word',1,0),(43,'banned_term','Banned Word',1,0),(44,'date_banned','Date Banned',1,0),(45,'ban_notes','Ban Notes',1,0),(46,'action','Action',1,0),(47,'enter_banned_term_details','Enter Banned Word details',1,0),(48,'dashboard_intro','Use the main navigation above to manage this site. A quick overview of the site can be seen below',1,0),(49,'dashboard_graph_last_14_days_title','New Files (last 14 days)',1,0),(50,'dashboard_graph_last_12_months_title','New Files (last 12 months)',1,0),(51,'urls','Urls',1,0),(52,'active','active',1,0),(53,'disabled','disabled',1,0),(54,'spam','spam',1,0),(55,'expired','expired',1,0),(56,'dashboard_total_active_urls','Total active files',1,0),(57,'dashboard_total_disabled_urls','Total disabled files',1,0),(58,'dashboard_total_visits_to_all_urls','Total downloads to all files',1,0),(59,'item_name','Item Name',1,0),(60,'value','Value',1,0),(61,'manage_languages_intro_2','Manage the available content for the selected language. Click on any of the \'Translated Content\' cells to edit the value.',1,0),(62,'manage_languages_intro_1','Select a language to manage or <a href=\'#\' onClick=\'displayAddLanguagePopup(); return false;\'>add a new one here</a>. NOTE Once translated, to set the site default language go to the <a href=\'settings.php\'>site settings</a> area.',1,0),(63,'language_key','Language Key',1,0),(64,'default_content','Default Content',1,0),(65,'translated_content','Translated Content',1,0),(66,'no_changes_in_demo_mode','Error Changes to this section can not be made within demo mode.',1,0),(67,'manage_other_languages','Manage other languages',1,0),(68,'no_available_content','There is no available content.',1,0),(69,'select_language','select language',1,0),(70,'add_language','Add Language',1,0),(71,'language_name','Language Name',1,0),(72,'settings_intro','Click on any of the items within the \"Config Value\" column below to edit',1,0),(73,'group','Group',1,0),(74,'config_description','Config Description',1,0),(75,'config_value','Config Value',1,0),(76,'shorturls_filter_results','Filter results',1,0),(77,'user_management_intro','Double click on any of the users below to edit the account information or <a href=\"#\" onClick=\"displayUserPopup(); return false;\">click here to add a new user</a>',1,0),(78,'add_new_user','Add new user',1,0),(79,'username','Username',1,0),(80,'email_address','Email Address',1,0),(81,'account_type','Account Type',1,0),(82,'last_login','Last Login',1,0),(83,'account_status','Account Status',1,0),(84,'password','Password',1,0),(85,'title','Title',1,0),(86,'firstname','Firstname',1,0),(87,'lastname','Lastname',1,0),(88,'enter_user_details','Enter user details',1,0),(90,'term_and_conditions','Terms &amp; Conditions',0,0),(92,'created_by','Created By',0,0),(108,'please_wait','please wait',0,0),(109,'general_site_error','There was a general site error, please try again later.',0,0),(110,'error','Error',0,0),(153,'visits_','visits',0,0),(154,'created_','created',0,0),(155,'visitors','Visitors',0,0),(156,'countries','Countries',0,0),(157,'top_referrers','Top Referrers',0,0),(158,'browsers','Browsers',0,0),(159,'operating_systems','Operating Systems',0,0),(160,'last_24_hours','last 24 hours',0,0),(161,'last_7_days','last 7 days',0,0),(162,'last_30_days','last 30 days',0,0),(163,'last_12_months','last 12 months',0,0),(164,'hour','Hour',0,0),(165,'visits','Visits',0,0),(166,'date','Date',0,0),(167,'total_visits','Total visits',0,0),(168,'percentage','Percentage',0,0),(169,'day','Day',0,0),(170,'month','Month',0,0),(171,'country','Country',0,0),(172,'site','Site',0,0),(173,'browser','Browser',0,0),(174,'operating_system','Operating System',0,0),(175,'AD','Andorra',0,0),(176,'AE','United Arab Emirates',0,0),(177,'AF','Afghanistan',0,0),(178,'AG','Antigua And Barbuda',0,0),(179,'AI','Anguilla',0,0),(180,'AL','Albania',0,0),(181,'AM','Armenia',0,0),(182,'AN','Netherlands Antilles',0,0),(183,'AO','Angola',0,0),(184,'AQ','Antarctica',0,0),(185,'AR','Argentina',0,0),(186,'AS','American Samoa',0,0),(187,'AT','Austria',0,0),(188,'AU','Australia',0,0),(189,'AW','Aruba',0,0),(190,'AZ','Azerbaijan',0,0),(191,'BA','Bosnia And Herzegovina',0,0),(192,'BB','Barbados',0,0),(193,'BD','Bangladesh',0,0),(194,'BE','Belgium',0,0),(195,'BF','Burkina Faso',0,0),(196,'BG','Bulgaria',0,0),(197,'BH','Bahrain',0,0),(198,'BI','Burundi',0,0),(199,'BJ','Benin',0,0),(200,'BM','Bermuda',0,0),(201,'BN','Brunei Darussalam',0,0),(202,'BO','Bolivia',0,0),(203,'BR','Brazil',0,0),(204,'BS','Bahamas',0,0),(205,'BT','Bhutan',0,0),(206,'BW','Botswana',0,0),(207,'BY','Belarus',0,0),(208,'BZ','Belize',0,0),(209,'CA','Canada',0,0),(210,'CD','The Democratic Republic Of The Congo',0,0),(211,'CF','Central African Republic',0,0),(212,'CG','Congo',0,0),(213,'CH','Switzerland',0,0),(214,'CI','Cote Divoire',0,0),(215,'CK','Cook Islands',0,0),(216,'CL','Chile',0,0),(217,'CM','Cameroon',0,0),(218,'CN','China',0,0),(219,'CO','Colombia',0,0),(220,'CR','Costa Rica',0,0),(221,'CS','Serbia And Montenegro',0,0),(222,'CU','Cuba',0,0),(223,'CV','Cape Verde',0,0),(224,'CY','Cyprus',0,0),(225,'CZ','Czech Republic',0,0),(226,'DE','Germany',0,0),(227,'DJ','Djibouti',0,0),(228,'DK','Denmark',0,0),(229,'DM','Dominica',0,0),(230,'DO','Dominican Republic',0,0),(231,'DZ','Algeria',0,0),(232,'EC','Ecuador',0,0),(233,'EE','Estonia',0,0),(234,'EG','Egypt',0,0),(235,'ER','Eritrea',0,0),(236,'ES','Spain',0,0),(237,'ET','Ethiopia',0,0),(238,'EU','European Union',0,0),(239,'FI','Finland',0,0),(240,'FJ','Fiji',0,0),(241,'FK','Falkland Islands (Malvinas)',0,0),(242,'FM','Federated States Of Micronesia',0,0),(243,'FO','Faroe Islands',0,0),(244,'FR','France',0,0),(245,'GA','Gabon',0,0),(246,'GB','United Kingdom',0,0),(247,'GD','Grenada',0,0),(248,'GE','Georgia',0,0),(249,'GF','French Guiana',0,0),(250,'GH','Ghana',0,0),(251,'GI','Gibraltar',0,0),(252,'GL','Greenland',0,0),(253,'GM','Gambia',0,0),(254,'GN','Guinea',0,0),(255,'GP','Guadeloupe',0,0),(256,'GQ','Equatorial Guinea',0,0),(257,'GR','Greece',0,0),(258,'GS','South Georgia And The South Sandwich Islands',0,0),(259,'GT','Guatemala',0,0),(260,'GU','Guam',0,0),(261,'GW','Guinea-Bissau',0,0),(262,'GY','Guyana',0,0),(263,'HK','Hong Kong',0,0),(264,'HN','Honduras',0,0),(265,'HR','Croatia',0,0),(266,'HT','Haiti',0,0),(267,'HU','Hungary',0,0),(268,'ID','Indonesia',0,0),(269,'IE','Ireland',0,0),(270,'IL','Israel',0,0),(271,'IN','India',0,0),(272,'IO','British Indian Ocean Territory',0,0),(273,'IQ','Iraq',0,0),(274,'IR','Islamic Republic Of Iran',0,0),(275,'IS','Iceland',0,0),(276,'IT','Italy',0,0),(277,'JM','Jamaica',0,0),(278,'JO','Jordan',0,0),(279,'JP','Japan',0,0),(280,'KE','Kenya',0,0),(281,'KG','Kyrgyzstan',0,0),(282,'KH','Cambodia',0,0),(283,'KI','Kiribati',0,0),(284,'KM','Comoros',0,0),(285,'KN','Saint Kitts And Nevis',0,0),(286,'KR','Republic Of Korea',0,0),(287,'KW','Kuwait',0,0),(288,'KY','Cayman Islands',0,0),(289,'KZ','Kazakhstan',0,0),(290,'LA','Lao Peoples Democratic Republic',0,0),(291,'LB','Lebanon',0,0),(292,'LC','Saint Lucia',0,0),(293,'LI','Liechtenstein',0,0),(294,'LK','Sri Lanka',0,0),(295,'LR','Liberia',0,0),(296,'LS','Lesotho',0,0),(297,'LT','Lithuania',0,0),(298,'LU','Luxembourg',0,0),(299,'LV','Latvia',0,0),(300,'LY','Libyan Arab Jamahiriya',0,0),(301,'MA','Morocco',0,0),(302,'MC','Monaco',0,0),(303,'MD','Republic Of Moldova',0,0),(304,'MG','Madagascar',0,0),(305,'MH','Marshall Islands',0,0),(306,'MK','The Former Yugoslav Republic Of Macedonia',0,0),(307,'ML','Mali',0,0),(308,'MM','Myanmar',0,0),(309,'MN','Mongolia',0,0),(310,'MO','Macao',0,0),(311,'MP','Northern Mariana Islands',0,0),(312,'MQ','Martinique',0,0),(313,'MR','Mauritania',0,0),(314,'MT','Malta',0,0),(315,'MU','Mauritius',0,0),(316,'MV','Maldives',0,0),(317,'MW','Malawi',0,0),(318,'MX','Mexico',0,0),(319,'MY','Malaysia',0,0),(320,'MZ','Mozambique',0,0),(321,'NA','Namibia',0,0),(322,'NC','New Caledonia',0,0),(323,'NE','Niger',0,0),(324,'NF','Norfolk Island',0,0),(325,'NG','Nigeria',0,0),(326,'NI','Nicaragua',0,0),(327,'NL','Netherlands',0,0),(328,'NO','Norway',0,0),(329,'NP','Nepal',0,0),(330,'NR','Nauru',0,0),(331,'NU','Niue',0,0),(332,'NZ','New Zealand',0,0),(333,'OM','Oman',0,0),(334,'PA','Panama',0,0),(335,'PE','Peru',0,0),(336,'PF','French Polynesia',0,0),(337,'PG','Papua New Guinea',0,0),(338,'PH','Philippines',0,0),(339,'PK','Pakistan',0,0),(340,'PL','Poland',0,0),(341,'PR','Puerto Rico',0,0),(342,'PS','Palestinian Territory',0,0),(343,'PT','Portugal',0,0),(344,'PW','Palau',0,0),(345,'PY','Paraguay',0,0),(346,'QA','Qatar',0,0),(347,'RE','Reunion',0,0),(348,'RO','Romania',0,0),(349,'RU','Russian Federation',0,0),(350,'RW','Rwanda',0,0),(351,'SA','Saudi Arabia',0,0),(352,'SB','Solomon Islands',0,0),(353,'SC','Seychelles',0,0),(354,'SD','Sudan',0,0),(355,'SE','Sweden',0,0),(356,'SG','Singapore',0,0),(357,'SI','Slovenia',0,0),(358,'SK','Slovakia (Slovak Republic)',0,0),(359,'SL','Sierra Leone',0,0),(360,'SM','San Marino',0,0),(361,'SN','Senegal',0,0),(362,'SO','Somalia',0,0),(363,'SR','Suriname',0,0),(364,'ST','Sao Tome And Principe',0,0),(365,'SV','El Salvador',0,0),(366,'SY','Syrian Arab Republic',0,0),(367,'SZ','Swaziland',0,0),(368,'TD','Chad',0,0),(369,'TF','French Southern Territories',0,0),(370,'TG','Togo',0,0),(371,'TH','Thailand',0,0),(372,'TJ','Tajikistan',0,0),(373,'TK','Tokelau',0,0),(374,'TL','Timor-Leste',0,0),(375,'TM','Turkmenistan',0,0),(376,'TN','Tunisia',0,0),(377,'TO','Tonga',0,0),(378,'TR','Turkey',0,0),(379,'TT','Trinidad And Tobago',0,0),(380,'TV','Tuvalu',0,0),(381,'TW','Taiwan Province Of China',0,0),(382,'TZ','United Republic Of Tanzania',0,0),(383,'UA','Ukraine',0,0),(384,'UG','Uganda',0,0),(385,'US','United States',0,0),(386,'UY','Uruguay',0,0),(387,'UZ','Uzbekistan',0,0),(388,'VA','Holy See (Vatican City State)',0,0),(389,'VC','Saint Vincent And The Grenadines',0,0),(390,'VE','Venezuela',0,0),(391,'VG','Virgin Islands',0,0),(392,'VI','Virgin Islands',0,0),(393,'VN','Viet Nam',0,0),(394,'VU','Vanuatu',0,0),(395,'WS','Samoa',0,0),(396,'YE','Yemen',0,0),(397,'YT','Mayotte',0,0),(398,'YU','Serbia And Montenegro (Formally Yugoslavia)',0,0),(399,'ZA','South Africa',0,0),(400,'ZM','Zambia',0,0),(401,'ZW','Zimbabwe',0,0),(402,'ZZ','Unknown',0,0),(405,'register','register',0,0),(408,'login','Login',0,0),(410,'register_complete_page_name','Registration completed',0,0),(411,'register_complete_meta_description','Your registration has been completed.',0,0),(412,'register_complete_meta_keywords','registration, completed, file, hosting, site',0,0),(413,'register_complete_sub_title','Thank you for registering!',0,0),(414,'register_complete_main_text','We\'ve sent an email to your registered email address with your access password. Please check your spam filters to ensure emails from this site get through. ',0,0),(415,'register_complete_email_from','Emails from this site are sent from ',0,0),(416,'login_page_name','Login',0,0),(417,'login_meta_description','Login to your account',0,0),(418,'login_meta_keywords','login, register, short url',0,0),(419,'username_and_password_is_invalid','Your username and password are invalid',0,0),(420,'account_login','Account Login',0,0),(421,'login_intro_text','Please enter your username and password below to login.',0,0),(422,'username_requirements','Your account username. 6 characters or more and alpha numeric.',0,0),(423,'password_requirements','Your account password. Min 6 characters, alpha numeric, no spaces.',0,0),(428,'please_enter_your_username','Please enter your username',0,0),(429,'account_home_page_name','Account Home',0,0),(430,'account_home_meta_description','Your Account Home',0,0),(431,'account_home_meta_keywords','account, home, file, hosting, members, area',0,0),(433,'faq','FAQ',0,0),(434,'faq_page_name','FAQ',0,0),(435,'faq_meta_description','Frequently Asked Questions',0,0),(436,'faq_meta_keywords','faq, frequently, asked, questions, file, hosting, site',0,0),(437,'please_enter_your_password','Please enter your password',0,0),(441,'register_account','Register Account',0,0),(446,'info','info',0,0),(447,'email_address_confirm','Email Confirm',0,0),(449,'created_last_visited','Created/Last Visited',0,0),(450,'status','Status',0,0),(451,'options','Options',0,0),(452,'upload_file','upload file',0,0),(453,'register_page_name','Register',0,0),(454,'register_meta_description','Register for an account',0,0),(455,'register_meta_keywords','register, account, short, url, user',0,0),(456,'your_files','your files',0,0),(457,'error_file_has_been_removed_by_user','File has been removed.',0,0),(458,'uploaded','Uploaded',0,0),(459,'downloads','downloads',0,0),(460,'download_now','download now',0,0),(461,'loading_file_please_wait','loading file, please wait',0,0),(462,'file_download_title','Download File',0,0),(463,'file_download_description','Download file',0,0),(464,'file_download_keywords','download, file, upload, mp3, avi, zip',0,0),(465,'your_recent_files','Your Files',0,0),(466,'download_url','Download Url',0,0),(467,'uploaded_last_visited','Uploaded/Last Visited',0,0),(468,'download_url_filename','Download Url/Filename',0,0),(469,'dashboard_total_active_files','Total Active Files',0,0),(470,'dashboard_total_disabled_files','Total Inactive Files',0,0),(471,'dashboard_total_downloads_to_all','Total Downloads',0,0),(472,'user removed','user removed',0,0),(473,'files','files',0,0),(474,'manage_files','Manage Files',0,0),(475,'files_filter_results','Filter Results',0,0),(476,'files_filter_disabled','Show Disabled',0,0),(477,'export_files_as_csv','Export File Data',0,0),(478,'error_file_has_been_removed_by_admin','File has been removed by the site administrator.',0,0),(479,'files_filter_removed','Show Removed',0,0),(480,'admin removed','admin removed',0,0),(481,'delete_file_page_name','Delete File',0,0),(482,'delete_file_meta_description','Delete File',0,0),(483,'delete_file_meta_keywords','delete, remove, file',0,0),(484,'delete_file','Delete File',0,0),(485,'delete_file_intro','Please confirm whether to delete the file below.',0,0),(486,'cancel','Cancel',0,0),(487,'report_file','report file',0,0),(488,'uprade_account','upgrade account',0,0),(489,'terms_page_name','Terms and Conditions',0,0),(490,'terms_meta_description','Terms and Conditions',0,0),(491,'terms_meta_keywords','terms, and, conditions, file, hosting, site',0,0),(492,'extend_account','extend account',0,0),(493,'upgrade_page_name','Extend Account',0,0),(494,'upgrade_meta_description','Extend Your Account',0,0),(495,'upgrade_meta_keywords','extend, account, paid, membership, upload, download, site',0,0),(496,'payment_complete_page_name','Payment Complete',0,0),(497,'payment_complete_meta_description','Payment Complete',0,0),(498,'payment_complete_meta_keywords','payment, complete, file, hosting, site',0,0),(499,'premium_account_benefits','premium account benefits',0,0),(500,'account_benefits','account benefits',0,0),(501,'file_information_page_name',' Information',0,0),(502,'file_information_description','Information about ',0,0),(503,'file_information_meta_keywords',', share, information, file, upload, download, site',0,0),(504,'download_urls','download urls',0,0),(505,'statistics','statistics',0,0),(506,'share','share',0,0),(507,'other_options','other options',0,0),(508,'problem_file_requirements','Enter the details of the file (as above) you wish to report.',0,0),(510,'report_abuse_error_no_content','Please enter the details of the reported file.',0,0),(511,'report_abuse','Report Abuse',0,0),(515,'main_navigation','Main Navigation',0,0),(516,'legal_bits','Legal Bits',0,0),(517,'your_account','Your Account',0,0),(518,'days','days',0,0),(519,'premium','Premium',0,0),(520,'pay_via_paypal','Pay via PayPal',0,0),(521,'secure_payment','secure payment',0,0),(522,'safe_and_anonymous','100% Safe & Anonymous',0,0),(523,'add_files','Add files...',0,0),(524,'start_upload','Start upload',0,0),(525,'cancel_upload','Cancel upload',0,0),(526,'select_files','Select files',0,0),(527,'drag_and_drop_files_here_or_click_to_browse','Drag &amp; drop files here or click to browse...',0,0),(528,'max_file_size','Max file size',0,0),(529,'add_file','add file',0,0),(530,'copy_all_links','copy all links',0,0),(531,'file_upload_completed','File uploads completed.',0,0),(532,'delete_url','Delete Url',0,0),(533,'stats_url','Stats Url',0,0),(534,'html_code','HTML Code',0,0),(535,'forum_code','Forum Code',0,0),(536,'full_info','Full Info',0,0),(537,'click_here','click here',0,0),(538,'extend','extend',0,0),(539,'reverts_to_free_account','reverts to free account',0,0),(540,'never','never',0,0),(541,'filename','filename',0,0),(542,'download','download',0,0),(543,'filesize','filesize',0,0),(544,'url','url',0,0),(545,'download_from','Download from',0,0),(546,'share_file','share file',0,0),(547,'index_page_name','Upload Files',0,0),(548,'index_meta_description','Upload, share, track, manage your files in one simple to use file host.',0,0),(549,'index_meta_keywords','upload, share, track, file, hosting, host',0,0),(550,'please_enter_your_firstname','Please enter your firstname',0,0),(551,'click_here_to_browse_your_files','Click here to browse your files...',0,0),(552,'optional_account_expiry','Paid Expiry Y-m-d (optional)',1,0),(553,'account_expiry_invalid','Account expiry date is invalid. It should be in the format YYYY-mm-dd',1,0),(554,'admin_file_servers','File Servers',1,0),(555,'ftp_host','FTP Ip Address',1,0),(556,'ftp_port','FTP Port',1,0);
/*!40000 ALTER TABLE `language_key` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_failure`
--

DROP TABLE IF EXISTS `login_failure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_failure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(15) CHARACTER SET utf8 NOT NULL,
  `date_added` datetime NOT NULL,
  `username` varchar(65) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_failure`
--

LOCK TABLES `login_failure` WRITE;
/*!40000 ALTER TABLE `login_failure` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_failure` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_success`
--

DROP TABLE IF EXISTS `login_success`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_success` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(15) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `date_added` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `country_code` varchar(2) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `date_added` (`date_added`),
  KEY `user_id` (`user_id`),
  KEY `country_code` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_success`
--

LOCK TABLES `login_success` WRITE;
/*!40000 ALTER TABLE `login_success` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_success` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_log`
--

DROP TABLE IF EXISTS `payment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date_created` datetime NOT NULL,
  `amount` float(9,2) NOT NULL,
  `currency_code` varchar(3) CHARACTER SET latin1 NOT NULL,
  `from_email` varchar(255) CHARACTER SET latin1 NOT NULL,
  `to_email` varchar(255) CHARACTER SET latin1 NOT NULL,
  `description` varchar(255) CHARACTER SET latin1 NOT NULL,
  `request_log` text CHARACTER SET latin1 NOT NULL,
  `payment_method` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `date_created` (`date_created`),
  KEY `description` (`description`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_log`
--

LOCK TABLES `payment_log` WRITE;
/*!40000 ALTER TABLE `payment_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_subscription`
--

DROP TABLE IF EXISTS `payment_subscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_subscription` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_level_pricing_id` int(11) DEFAULT NULL,
  `payment_gateway` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `gateway_subscription_id` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `date_added` datetime NOT NULL,
  `sub_status` enum('active','cancelled') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_subscription`
--

LOCK TABLES `payment_subscription` WRITE;
/*!40000 ALTER TABLE `payment_subscription` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_subscription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin`
--

DROP TABLE IF EXISTS `plugin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(150) CHARACTER SET utf8 NOT NULL,
  `folder_name` varchar(100) CHARACTER SET utf8 NOT NULL,
  `plugin_description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `is_installed` int(1) NOT NULL DEFAULT '0',
  `date_installed` datetime DEFAULT NULL,
  `plugin_settings` text CHARACTER SET utf8,
  `plugin_enabled` int(1) NOT NULL DEFAULT '1',
  `load_order` int(3) DEFAULT '999',
  PRIMARY KEY (`id`),
  KEY `load_order` (`load_order`),
  KEY `is_installed` (`is_installed`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin`
--

LOCK TABLES `plugin` WRITE;
/*!40000 ALTER TABLE `plugin` DISABLE KEYS */;
INSERT INTO `plugin` VALUES (1,'PayPal Payment Integration','paypal','Accept payments using PayPal.',1,NULL,'{\"paypal_email\":\"paypal@yoursite.com\"}',1,999),(2,'Payment Gateways','payment','Access to over 50 payment gateways for premium account upgrades.',1,NULL,'',1,999),(3,'File Previewer','filepreviewer','Display files directly within the file manager.',1,NULL,'{\"allow_direct_links\":0,\"non_show_viewer\":1,\"free_show_viewer\":1,\"paid_show_viewer\":1,\"enable_preview_image\":1,\"preview_image_show_thumb\":1,\"auto_rotate\":1,\"supported_image_types\":\"jpg,jpeg,png,gif,wbmp\",\"enable_preview_document\":1,\"preview_document_pdf_thumbs\":1,\"preview_document_ext\":\"doc,docx,xls,xlsx,ppt,pptx,pdf,pages,ai,psd,tiff,dxf,svg,eps,ps,ttf,otf,xps\",\"enable_preview_video\":1,\"preview_video_ext\":\"mp4,flv,ogg\",\"enable_preview_audio\":1,\"preview_audio_ext\":\"mp3\",\"caching\":1,\"image_quality\":90}',1,999);
/*!40000 ALTER TABLE `plugin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_filepreviewer_background_thumb`
--

DROP TABLE IF EXISTS `plugin_filepreviewer_background_thumb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_filepreviewer_background_thumb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `thumb_status` enum('processing','failed','created','nonimage') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `date_added` datetime NOT NULL,
  `processing_time` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_filepreviewer_background_thumb`
--

LOCK TABLES `plugin_filepreviewer_background_thumb` WRITE;
/*!40000 ALTER TABLE `plugin_filepreviewer_background_thumb` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_filepreviewer_background_thumb` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_filepreviewer_meta`
--

DROP TABLE IF EXISTS `plugin_filepreviewer_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_filepreviewer_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `width` int(8) NOT NULL,
  `height` int(8) NOT NULL,
  `raw_data` text COLLATE utf8_bin,
  `date_taken` datetime DEFAULT NULL,
  `image_colors` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `image_bg_color` varchar(7) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `image_bg_color` (`image_bg_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_filepreviewer_meta`
--

LOCK TABLES `plugin_filepreviewer_meta` WRITE;
/*!40000 ALTER TABLE `plugin_filepreviewer_meta` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_filepreviewer_meta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_filepreviewer_watermark`
--

DROP TABLE IF EXISTS `plugin_filepreviewer_watermark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_filepreviewer_watermark` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `image_content` blob NOT NULL,
  `category` varchar(20) NOT NULL DEFAULT 'images',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_filepreviewer_watermark`
--

LOCK TABLES `plugin_filepreviewer_watermark` WRITE;
/*!40000 ALTER TABLE `plugin_filepreviewer_watermark` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_filepreviewer_watermark` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_payment_gateways`
--

DROP TABLE IF EXISTS `plugin_payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(150) COLLATE utf8_bin NOT NULL,
  `label` varchar(150) COLLATE utf8_bin DEFAULT NULL,
  `description` text COLLATE utf8_bin,
  `url` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `gateway_type` enum('onsite','offsite') COLLATE utf8_bin NOT NULL DEFAULT 'offsite',
  `gateway_group` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `gateway_additional_params` text COLLATE utf8_bin,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_name` (`class_name`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_payment_gateways`
--

LOCK TABLES `plugin_payment_gateways` WRITE;
/*!40000 ALTER TABLE `plugin_payment_gateways` DISABLE KEYS */;
INSERT INTO `plugin_payment_gateways` VALUES (1,'TwoCheckout','2Checkout',NULL,'https://www.2checkout.com','onsite','2Checkout',0,NULL),(2,'AuthorizeNet_AIM','AuthorizeNet - AIM',NULL,'https://www.authorize.net','onsite','AuthorizeNet',1,NULL),(3,'AuthorizeNet_CIM','AuthorizeNet - CIM',NULL,'https://www.authorize.net','onsite','AuthorizeNet',1,NULL),(4,'AuthorizeNet_SIM','AuthorizeNet - SIM',NULL,'https://www.authorize.net','onsite','AuthorizeNet',1,NULL),(5,'AuthorizeNet_DPM','AuthorizeNet - DPM',NULL,'https://www.authorize.net','onsite','AuthorizeNet',1,NULL),(6,'BitPay','BitPay',NULL,'https://bitpay.com','offsite','BitPay',1,NULL),(7,'Braintree','Braintree',NULL,'https://www.braintreepayments.com','onsite','Braintree',1,NULL),(8,'Buckaroo_CreditCard','Buckaroo - CreditCard',NULL,'https://www.buckaroo-payments.com','onsite','Buckaroo',1,NULL),(9,'Buckaroo_Ideal','Buckaroo - Ideal',NULL,'https://www.buckaroo-payments.com','onsite','Buckaroo',1,NULL),(10,'Buckaroo_PayPal','Buckaroo - PayPal',NULL,'https://www.buckaroo-payments.com','onsite','Buckaroo',1,NULL),(11,'Buckaroo_SepaDirectDebit','Buckaroo - SepaDirectDebit',NULL,'https://www.buckaroo-payments.com','onsite','Buckaroo',1,NULL),(12,'CardSave','CardSave',NULL,'https://www.cardsave.net','onsite','CardSave',1,NULL),(13,'Coinbase','Coinbase',NULL,'https://www.coinbase.com','onsite','Coinbase',1,NULL),(14,'Eway_RapidDirect','Eway - RapidDirect','This is the primary gateway used for direct card processing, i.e. where you collect the card details from the customer and pass them to eWay yourself via the API.','https://eway.io','onsite','Eway',1,NULL),(15,'Eway_Rapid','Eway - Rapid','This is used for eWAY Rapid Transparent Redirect requests. The gateway is just called Eway_Rapid as it was the first implemented. Like other redirect gateways the purchase() call will return a redirect response and then requires you to redirect the customer to the eWay site for the actual purchase.','https://eway.io','onsite','Eway',1,NULL),(16,'Eway_RapidShared','Eway - RapidShared','This provides a hosted form for entering payment information, other than that it is similar to the Eway_Rapid gateway in functionality.','https://eway.io','onsite','Eway',1,NULL),(17,'FirstData_Connect','FirstData - Connect',NULL,'https://www.firstdata.com/ecommerce/index.html','onsite','FirstData',1,NULL),(18,'FirstData_Webservice','FirstData - Webservice',NULL,'https://www.firstdata.com/ecommerce/index.html','onsite','FirstData',1,NULL),(19,'FirstData_Payeezy','FirstData - Payeezy',NULL,'https://www.firstdata.com/ecommerce/index.html','onsite','FirstData',1,NULL),(20,'GoCardless','GoCardless',NULL,'https://gocardless.com','onsite','GoCardless',1,NULL),(21,'Migs_TwoParty','Migs - TwoParty',NULL,'https://www.mastercard.us/en-us/about-mastercard/what-we-do/payment-processing.html','onsite','Migs',1,NULL),(22,'Migs_ThreeParty','Migs - ThreeParty',NULL,'https://www.mastercard.us/en-us/about-mastercard/what-we-do/payment-processing.html','onsite','Migs',1,NULL),(23,'Mollie','Mollie',NULL,'https://www.mollie.com','offsite','Mollie',1,'{\"description\":true,\"returnUrl\":true}'),(24,'MultiSafepay_Rest','MultiSafepay - Rest',NULL,'https://www.multisafepay.com','onsite','MultiSafepay',1,NULL),(25,'Netaxept','Netaxept',NULL,'https://www.nets.eu','onsite','Netaxept',1,NULL),(26,'NetBanx','NetBanx',NULL,NULL,'onsite','NetBanx',0,NULL),(27,'OKPAY','OKPay',NULL,'https://www.okpay.com','onsite','OKPay',1,NULL),(28,'PayFast','PayFast',NULL,'https://www.payfast.co.za','onsite','PayFast',1,NULL),(29,'Payflow_Pro','Payflow - Pro',NULL,NULL,'onsite','Payflow',0,NULL),(30,'PaymentExpress_PxPay','PaymentExpress - PxPay',NULL,'https://www.paymentexpress.co.uk','onsite','PaymentExpress',1,NULL),(31,'PaymentExpress_PxPost','PaymentExpress - PxPost',NULL,'https://www.paymentexpress.co.uk','onsite','PaymentExpress',1,NULL),(32,'PaymentExpress_PxFusion','PaymentExpress - PxFusion',NULL,'https://www.paymentexpress.co.uk','onsite','PaymentExpress',0,NULL),(33,'PayPal_Express','PayPal - Express','PayPal Express Checkout','https://www.paypal.com','onsite','PayPal',1,NULL),(34,'PayPal_ExpressInContext','PayPal - ExpressInContext','PayPal Express In-Context Checkout','https://www.paypal.com','onsite','PayPal',1,NULL),(35,'PayPal_Pro','PayPal - Pro','PayPal Website Payments Pro','https://www.paypal.com','onsite','PayPal',1,NULL),(36,'PayPal_Rest','PayPal - Rest','Paypal Rest API','https://www.paypal.com','onsite','PayPal',1,NULL),(37,'Paysafecard','Paysafecard',NULL,'https://www.paysafecard.com','onsite','Paysafecard',1,NULL),(38,'Pin','Pin',NULL,'https://pinpayments.com','onsite','Pin',1,NULL),(39,'SagePay_Direct','SagePay - Direct',NULL,'https://www.sagepay.co.uk','onsite','SagePay',1,NULL),(40,'SagePay_Server','SagePay - Server',NULL,'https://www.sagepay.co.uk','onsite','SagePay',1,NULL),(41,'SecurePay_SecureXML','SecurePay Secure XML',NULL,'https://www.securepay.com.au','onsite','SecurePay',1,NULL),(42,'Stripe','Stripe',NULL,'https://stripe.com','onsite','Stripe',1,NULL),(43,'TargetPay_Directebanking','TargetPay - Directebanking',NULL,'https://www.targetpay.com','onsite','TargetPay',1,NULL),(44,'TargetPay_Ideal','TargetPay - Ideal',NULL,'https://www.targetpay.com','offsite','TargetPay',1,'{\"description\":true,\"returnUrl\":true,\"issuer\":true}'),(45,'TargetPay_Mrcash','TargetPay - Mrcash',NULL,'https://www.targetpay.com','onsite','TargetPay',1,NULL),(46,'WebMoney','WebMoney',NULL,'https://www.wmtransfer.com','onsite','WebMoney',1,NULL),(47,'WorldPay','WorldPay',NULL,'https://www.worldpay.com','onsite','WorldPay',1,NULL),(48,'WorldPay_Json','WorldPay - Json',NULL,'https://www.worldpay.com','onsite','WorldPay',1,NULL),(49,'Sofort','Sofort',NULL,'https://www.klarna.com/sofort','onsite','Sofort',1,NULL),(50,'Paysera','Paysera',NULL,'https://www.paysera.com','onsite','Paysera',1,NULL),(51,'EgopayRu','EgopayRu',NULL,'http://www.ego-pay.com','onsite','EgopayRu',0,NULL),(52,'CoinPayments','Coinpayments',NULL,'https://www.coinpayments.net','onsite','Coinpayments',1,NULL);
/*!40000 ALTER TABLE `plugin_payment_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plugin_payment_gateways_configured`
--

DROP TABLE IF EXISTS `plugin_payment_gateways_configured`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plugin_payment_gateways_configured` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_id` int(11) NOT NULL,
  `params` text COLLATE utf8_bin,
  `status` enum('active','disabled') COLLATE utf8_bin NOT NULL DEFAULT 'active',
  `date_created` datetime DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plugin_payment_gateways_configured`
--

LOCK TABLES `plugin_payment_gateways_configured` WRITE;
/*!40000 ALTER TABLE `plugin_payment_gateways_configured` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugin_payment_gateways_configured` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `premium_order`
--

DROP TABLE IF EXISTS `premium_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `premium_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `description` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `payment_hash` varchar(32) CHARACTER SET latin1 NOT NULL,
  `user_level_pricing_id` int(11) DEFAULT NULL,
  `days` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `order_status` enum('pending','cancelled','completed') CHARACTER SET latin1 NOT NULL,
  `date_created` datetime NOT NULL,
  `upgrade_file_id` int(11) DEFAULT NULL,
  `upgrade_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `payment_hash` (`payment_hash`),
  KEY `user_level_pricing_id` (`user_level_pricing_id`),
  KEY `order_status` (`order_status`),
  KEY `upgrade_file_id` (`upgrade_file_id`),
  KEY `upgrade_user_id` (`upgrade_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `premium_order`
--

LOCK TABLES `premium_order` WRITE;
/*!40000 ALTER TABLE `premium_order` DISABLE KEYS */;
/*!40000 ALTER TABLE `premium_order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remote_url_download_queue`
--

DROP TABLE IF EXISTS `remote_url_download_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remote_url_download_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `url` text CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `file_server_id` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `started` datetime NOT NULL,
  `finished` datetime NOT NULL,
  `job_status` enum('downloading','pending','processing','complete','cancelled','failed') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'pending',
  `total_size` bigint(16) NOT NULL DEFAULT '0',
  `downloaded_size` bigint(16) NOT NULL DEFAULT '0',
  `download_percent` int(3) NOT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `notes` text CHARACTER SET utf8 COLLATE utf8_bin,
  `new_file_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `file_server_id` (`file_server_id`),
  KEY `folder_id` (`folder_id`),
  KEY `new_file_id` (`new_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remote_url_download_queue`
--

LOCK TABLES `remote_url_download_queue` WRITE;
/*!40000 ALTER TABLE `remote_url_download_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `remote_url_download_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `data` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `updated_on` int(10) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `updated_on` (`updated_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_config`
--

DROP TABLE IF EXISTS `site_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `config_key` varchar(100) CHARACTER SET utf8 NOT NULL,
  `config_value` mediumtext CHARACTER SET utf8 NOT NULL,
  `config_description` text CHARACTER SET utf8 NOT NULL,
  `availableValues` varchar(255) CHARACTER SET utf8 NOT NULL,
  `config_type` varchar(30) CHARACTER SET utf8 NOT NULL,
  `config_group` varchar(100) CHARACTER SET utf8 NOT NULL DEFAULT 'Default',
  `display_order` int(5) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_config`
--

LOCK TABLES `site_config` WRITE;
/*!40000 ALTER TABLE `site_config` DISABLE KEYS */;
INSERT INTO `site_config` VALUES (5,'Date time format','date_time_format','d/m/Y H:i:s','Date time format in php','','string','Local',5),(6,'Date format','date_format','d/m/Y','Date format in php','','string','Local',0),(7,'Site name','site_name','File Upload Script','Site name','','string','Site Options',5),(9,'Site theme','site_theme','spirit','Site template theme','SELECT folder_name AS itemValue FROM theme ORDER BY folder_name','select','System',0),(11,'Date time format js','date_time_format_js','%d-%m-%Y %H:%i','Date time format in javascript','','string','Local',10),(15,'Advert site footer','advert_site_footer','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/468x60?text=468x60+Advert\"/></a>','Site footer ads across the site (html)','','textarea','Adverts',10),(16,'Advert delayed redirect top','advert_delayed_redirect_top','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/468x60?text=468x60+Advert\"/></a>','Delayed redirect top advert (html)','','textarea','Adverts',4),(18,'Advert delayed redirect bottom','advert_delayed_redirect_bottom','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/468x60?text=468x60+Advert\"/></a>','Delayed redirect bottom advert (html)','','textarea','Adverts',5),(19,'Report abuse email','report_abuse_email','abuse@yoursite.com','Email address for which all abuse reports are sent.','','string','Site Options',20),(20,'Site language','site_language','English (en)','Site language for text conversions <a href=\"translation_manage.php\">(manage languages)</a>','SELECT languageName AS itemValue FROM language ORDER BY languageName','select','Language',0),(21,'Language show key','language_show_key','translation','Show translation value or key. (use \"key\" to debug translations, \"translation\" to show actual translated value. \"key title text\" to show the key as a title tag around the text content)','[\"key\",\"translation\", \"key title text\"]','select','Language',25),(23,'Stats only count unique','stats_only_count_unique','yes','Revisits in the same day, by the same IP address will not be counted on stats.','[\"yes\", \"no\"]','select','Default',0),(24,'Default email address from','default_email_address_from','email@yoursite.com','The default email address to send emails from.','','string','Email Settings',12),(31,'Next check for file removals','next_check_for_file_removals','1327013029','System value. The next time to delete any files which haven\'t recently been accessed. Timestamp. Do not edit.','','integer','System',0),(37,'Cost currency symbol','cost_currency_symbol','$','The symbol to use for currency. i.e. $','[\"$\", \"£\", \"€\"]','string','Premium Pricing',5),(38,'Cost currency code','cost_currency_code','USD','The currency code for the current currency. i.e. USD','[\"USD\", \"GBP\", \"EUR\"]','select','Premium Pricing',10),(42,'Email method','email_method','php','The method for sending emails via the script.','[\"php\",\"smtp\"]','select','Email Settings',0),(43,'Email smtp host','email_smtp_host','mail.yoursite.com','Your SMTP host if you\'ve selected SMTP email method. (leave blank is email_method = php)','','string','Email Settings',5),(44,'Email smtp port','email_smtp_port','25','Your SMTP port if you\'ve selected SMTP email method. (Normally 25)','','integer','Email Settings',10),(45,'Email smtp requires auth','email_smtp_requires_auth','no','Whether your SMTP server requires authentication.','[\"yes\",\"no\"]','select','Email Settings',20),(46,'Email smtp auth username','email_smtp_auth_username','','Your SMTP username if SMTP auth is required.','','string','Email Settings',25),(47,'Email smtp auth password','email_smtp_auth_password','','Your SMTP password if SMTP auth is required.','','string','Email Settings',30),(48,'File url show filename','file_url_show_filename','yes','Show the original filename on the end of the generated url.','[\"yes\",\"no\"]','select','File Uploads',25),(49,'Default file server','default_file_server','Local Default','The file server to use for all new uploads. Only used if \'active\' state and \'server selection method\' is \'specific server\'.','SELECT serverLabel AS itemValue FROM file_server LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id WHERE statusId=2 ORDER BY serverLabel','select','File Uploads',5),(50,'C file server selection method','c_file_server_selection_method','Least Used Space','Server selection method. How to select the file server to use. If using \'until full\', you\'ll also need to set the file server priority on each.','[\"Least Used Space\",\"Specific Server\",\"Until Full\"]','select','File Uploads',0),(51,'File Download - Free User','free_user_show_captcha','no','Show the captcha after a free user sees the countdown timer.','[\"yes\",\"no\"]','select','Captcha',3),(52,'reCaptcha Secret Key','captcha_secret_key','6LeyGQcTAAAAAH14UxDtIxYUnPcM11Oo0RVCc6dY','Secret key for reCaptcha v2. Register at https://www.google.com/recaptcha','','string','Captcha',20),(53,'reCaptcha Public Key','captcha_public_key','6LeyGQcTAAAAADxvgyjaMHqkuGAZ3vsqpUSUS7bM','Public key for reCaptcha v2. Register at https://www.google.com/recaptcha','','string','Captcha',15),(54,'Reserved usernames','reserved_usernames','admin|administrator|localhost|support|billing|sales|payments','Any usernames listed here will be blocked from the main registration. Pipe separated list.','','string','Default',0),(55,'Show multi language selector','show_multi_language_selector','hide','Whether to show or hide the multi language selector on the site.','[\"hide\",\"show\"]','select','Language',10),(56,'Site admin email','site_admin_email','abuse@yoursite.com','The email address all site admin emails will be sent.','','string','Site Options',15),(58,'Require user account download','require_user_account_download','no','Users must register for an account to download.','[\"yes\",\"no\"]','select','File Downloads',5),(59,'Generate upload url type','generate_upload_url_type','Shortest','What format to generate the file url in. Shortest will increment based on the previous upload. Hashed will create a longer random character hash.','[\"Shortest\",\"Medium Hash\",\"Long Hash\"]','select','File Uploads',20),(66,'Register form show captcha','register_form_show_captcha','no','Whether to display the captcha on the site registration form.','[\"yes\",\"no\"]','select','Captcha',5),(67,'Downloads track current downloads','downloads_track_current_downloads','yes','Whether to track current downloads/connections in the admin area. Note: This should be enabled if you also want to limit concurrent download connections.','[\"yes\",\"no\"]','select','File Downloads',15),(71,'Session expiry','session_expiry','86400','The amount of time a user can be inactive before their session will expire. In seconds. Default is 86400 (1 day)','','integer','Site Options',60),(72,'Logging log enabled','logging_log_enabled','yes','Whether to enable logging or not. The /logs/ folder should have write permissions on it. (chmod 755 or 777)','[\"yes\",\"no\"]','select','Logs',0),(73,'Logging log type','logging_log_type','Serious Errors Only','The types of log messages to store in the log files. \'Serious Errors Only\' will log the important ones. Ensure logging is enabled for this setting to work.','[\"Serious Errors Only\",\"Serious Errors and Warnings\",\"All Error Types\"]','select','Logs',5),(74,'Logging log output','logging_log_output','no','Whether to output serious errors to screen, if possible. Always set this to \'no\' for your live site.','[\"yes\",\"no\"]','select','Logs',10),(89,'Premium user block account sharing','premium_user_block_account_sharing','no','Block paid account sharing. Accounts will only allow 1 login session. Any open sessions will be closed on a new login.','[\"yes\",\"no\"]','select','Security',10),(90,'Language separate language images','language_separate_language_images','no','Use different images/css for each language. If yes, copy all the files from images/styles in /themes/blue_v2/ to /themes/blue_v2/[flag_code]/, keeping the folders. Replace \'[flag_code]\' with the 2 letter language flag code. i.e. /themes/blue_v2/es/','[\"yes\",\"no\"]','select','Language',15),(91,'Language user select language','language_user_select_language','no','Give users the option to set their account language. Available as a drop-down in account settings. Automatically sets the language of the site on login.','[\"yes\",\"no\"]','select','Language',5),(92,'Maintenance mode','maintenance_mode','no','Whether to place the entire site into maintenance mode. Useful for site upgrades or server moves. Admin area is still accessible. Maintenance page content is in _maintenance_page.inc.php.','[\"yes\",\"no\"]','select','Site Options',25),(93,'Enable user registration','enable_user_registration','yes','Whether to enable user registration on the site.','[\"yes\",\"no\"]','select','Site Options',30),(94,'File manager default view','file_manager_default_view','icon','The default view for the file manager.','[\"icon\", \"list\"]','select','File Manager',0),(95,'Site contact form email','site_contact_form_email','abuse@yoursite.com','The email address all contact form queries will be sent','','string','Contact Form',0),(96,'Contact form show captcha','contact_form_show_captcha','yes','Show the captcha on the contact form.','[\"yes\",\"no\"]','select','Contact Form',5),(97,'Performance js file minify','performance_js_file_minify','no','Whether to automatically group and minify js files. \'yes\' increases page load times. Use \'no\' if you have any issues or in dev. The \'cache\' folder must be writable.','[\"yes\",\"no\"]','select','Site Options',35),(108,'Password policy min length','password_policy_min_length','8','Minimum password length','','integer','Password Policy',0),(109,'Password policy max length','password_policy_max_length','32','Maximum password length','','integer','Password Policy',5),(110,'Password policy min uppercase characters','password_policy_min_uppercase_characters','0','Minimum upper case characters (set to 0 to ignore)','','integer','Password Policy',15),(111,'Password policy min numbers','password_policy_min_numbers','0','Minimum numbers (set to 0 to ignore)','','integer','Password Policy',10),(112,'Password policy min nonalphanumeric characters','password_policy_min_nonalphanumeric_characters','0','Minimum nonalphanumeric characters, i.e. symbols (set to 0 to ignore)','','integer','Password Policy',20),(113,'Security block ip login attempts','security_block_ip_login_attempts','5','How many login attempts before an IP is blocked from logging in for 24 hours.','','integer','Security',30),(114,'Security send user email on password change','security_send_user_email_on_password_change','yes','Send user an email confirmation when they change their password in account settings.','[\"yes\",\"no\"]','select','Security',15),(115,'Security send user email on email change','security_send_user_email_on_email_change','yes','Send user an email confirmation when they change their email address in account settings.','[\"yes\",\"no\"]','select','Security',20),(116,'Email template header','email_template_header','<html>\n    <head>\n        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n    </head>\n    <body style=\"background-color: #eee; padding: 0px; margin: 0px; font-family: Arial, Helvetica; font-size: 12px;\">\n        <div style=\"padding: 18px 18px 0px 18px; background-color: #ffffff;\">\n            <a href=\"[[[WEB_ROOT]]]\">\n                <div style=\"background-image: url(\'[[[SITE_IMAGE_PATH]]]/logo/logo-whitebg.png\'); background-repeat: no-repeat; height: 45px; width: 100%; float: left;\"><!-- --></div>\n            </a>\n            <div style=\"clear: left;\"><!-- --></div>\n        </div>\n        <div style=\"background-color: #ffffff; padding: 18px 18px 25px 18px;\">','HTML code for the header of all emails. Test using the \'admin/_test_scripts/test_email.php\' script. Accepts the following placeholders for replacements: [[[SITE_NAME]]], [[[WEB_ROOT]]], [[[DATE_NOW]]], [[[TIME_NOW]]].','','textarea','Email Settings',40),(117,'Email template footer','email_template_footer','        </div>\n        <div style=\"color: #aaa; font-size: 10px; padding: 18px; margin-left: auto; margin-right: auto;\">\n            This e-mail is intended solely for the addressee. If you are not the addressee please do not read, print, re-transmit, store or act in reliance on it or any attachments. Instead, please inform [[[SITE_NAME]]] support and then immediately permanently delete it.<br/><br/>\n            Please do not respond to this email. These are generated automatically by the [[[SITE_NAME]]] system and so the return address is not monitored for email. Please submit a request via our <a href=\"[[[WEB_ROOT]]]\">website</a> if you have a query.<br/><br/>\n            Message sent from <a href=\"[[[WEB_ROOT]]]\">[[[SITE_NAME]]]</a> on [[[DATE_TIME_NOW]]]\n        </div>\n    </body>\n</html>','HTML code for the footer of all emails. Test using the \'admin/_test_scripts/test_email.php\' script. Accepts the following placeholders for replacements: [[[SITE_NAME]]], [[[WEB_ROOT]]], [[[DATE_NOW]]], [[[DATE_TIME_NOW]]].','','textarea','Email Settings',45),(118,'Email template enabled','email_template_enabled','yes','Whether to use the email header and footer html.','[\"yes\",\"no\"]','select','Email Settings',35),(121,'Security block register email domain','security_block_register_email_domain','','Block email address domains from registering. Comma separated list of domains. i.e. exampledomain.com,exampledomain2.com,etc','','textarea','Security',25),(122,'Purge deleted files period minutes','purge_deleted_files_period_minutes','10080','How long to keep deleted files for on the server. On delete via the script UI they are moved into /files/_deleted/ then purged after this period. Useful for recovery if needed. Set in minutes. Default 24 hours, so 1440.','','input','Site Options',50),(123,'Google analytics code','google_analytics_code','','Your Google Analytics or other stats code. This is appended to the footer of the site. It should include the script tags.','','textarea','Site Options',55),(124,'Register form allow password','register_form_allow_password','no','allow users to choose their own passwords when registering.','[\"yes\",\"no\"]','select','Security',35),(126,'Security account lock','security_account_lock','no','Enable users to lock their accounts?','[\"yes\",\"no\"]','select','Security',5),(127,'Email secure method','email_secure_method','none','Whether the mail server requires SSL/TLS or None.','[\"ssl\",\"tls\",\"none\"]','select','Email Settings',15),(128,'Remote url download in background','remote_url_download_in_background','no','Should remote file downloads be done in the background? If yes you will need to setup the crontask /app/tasks/process_remote_file_downloads.cron.php to run every minute.','[\"yes\",\"no\"]','select','File Downloads',10),(129,'Force files private','force_files_private','no','Do you want to make all files uploaded private? All sharing links will be removed from the site pages and the users will only be able to download their own files.','[\"yes\",\"no\"]','select','Security',0),(130,'Limit send via email per hour','limit_send_via_email_per_hour','10','The maximum amount of emails that a user can send per hour from the \'send via email\' page.','','string','Email Settings',50),(131,'File Download - Non User','non_user_show_captcha','no','Show the captcha after a non user sees the countdown timer.','[\"yes\",\"no\"]','select','Captcha',0),(132,'Default admin file manager view','default_admin_file_manager_view','list','Default view to show in the admin file manager.','[\"list\",\"thumb\"]','select','Site Options',45),(133,'Enable file search','enable_file_search','yes','Whether to enable the file search tool on the site.','[\"yes\",\"no\"]','select','Site Options',40),(134,'Next check for server stats update','next_check_for_server_stats_update','0','System value. The next time to update the total filesize and file count in the file_server table. Timestamp. Do not edit.','','integer','System',0),(135,'Google translate api key','google_translate_api_key','','Google Translate API key. Optional but needed if you use the automatic language translation tool within the admin area.','','string','Language',20),(136,'Blocked filename keywords','blocked_filename_keywords','yetishare|wurlie|reservo','Any filenames with the keywords listed here will be blocked from uploading. Keep in mind that this is a partial string search, so blocking the word \"exe\" will also block the word \"exercise\". Pipe separated list. i.e. word1|word2|word3','','string','File Uploads',30),(137,'System plugin config cache','system_plugin_config_cache','','Used internally by the system to store a cache of the plugin settings.','','string','System',0),(138,'System theme config cache','system_theme_config_cache','{\"spirit\":{\"data\":{\"theme_name\":\"Spirit Theme\",\"folder_name\":\"spirit\",\"theme_description\":\"Bootstrap Yetishare theme included with the core script.\",\"author_name\":\"Yetishare\",\"author_website\":\"https://yetishare.com\",\"is_installed\":\"1\",\"date_installed\":null,\"theme_settings\":\"\"},\"config\":{\"theme_name\":\"Spirit Theme\",\"folder_name\":\"spirit\",\"theme_description\":\"Bootstrap Yetishare theme included with the core script.\",\"author_name\":\"Yetishare\",\"author_website\":\"https://yetishare.com\",\"theme_version\":\"1.0\",\"required_script_version\":\"5.0\",\"product\":\"file_hosting\",\"product_name\":\"Yetishare\",\"product_url\":\"https://yetishare.com\"}}}','Used internally by the system to store a cache of the theme settings.','','string','System',0),(139,'Adblock limiter','adblock_limiter','Disabled','Block users from the site if they are using adblock within their browser, a message is shown telling them to disable it. Block download pages only or block access to the entire site. This limitation only applies to users which are shown adverts.','[\"Disabled\",\"Block Download Pages\", \"Block Entire Site\"]','select','Adverts',1),(140,'Uploads block all','uploads_block_all','no','Whether to block all uploads on your site, apart from the admin user. Useful as a temporary setting for site maintenance','[\"yes\", \"no\"]','select','File Uploads',40),(141,'Downloads block all','downloads_block_all','no','Whether to block all downloads on your site, apart from the admin user. Useful as a temporary setting for site maintenance','[\"yes\", \"no\"]','select','File Downloads',20),(142,'User register default folders','user_register_default_folders','','Default folders for new accounts. These are automatically created when users register on the site. Leave blank to ignore. Pipe separated list. i.e. Documents|Images|Videos','','string','File Manager',5),(143,'Captcha type','captcha_type','recaptcha','Which captcha to use, if enabled.','[\"recaptcha\",\"solvemedia\",\"cryptoloot\"]','select','Captcha',10),(144,'Captcha solvemedia challenge key','captcha_solvemedia_challenge_key','','Challenge key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/','','string','Captcha',35),(145,'Captcha solvemedia ver key','captcha_solvemedia_ver_key','','Verification key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/','','string','Captcha',25),(146,'Captcha solvemedia hash key','captcha_solvemedia_hash_key','','Authentication Hash key for solvemedia captcha, if enabled. Register at http://solvemedia.com/publishers/','','string','Captcha',30),(147,'API Path','api_access_host','','The API hostname. Use [[[WEB_ROOT]]]/api/v2/ unless you want to move the API elsewhere.','','string','API',5),(148,'Authentication Method','api_authentication_method','API Keys','Whether to use the account username and password or generated API keys. (recommended to use generated API keys)','[\"API Keys\",\"Account Access Details\"]','select','API',10),(149,'Access Level','api_account_access_type','admin','Restric`t access to certain account types. Hold ctrl and click to select multiple.','SELECT label AS itemValue FROM user_level WHERE level_type != \"nonuser\" ORDER BY level_id','multiselect','API',15),(150,'Show User Login Screen','captcha_login_screen_normal','no','Show the captcha on the standard login screen.','[\"yes\",\"no\"]','select','Captcha',6),(151,'Show Admin Login Screen','captcha_login_screen_admin','no','Show the captcha on the admin login screen.','[\"yes\",\"no\"]','select','Captcha',7),(153,'Admin Approve Registration','admin_approve_registrations','no','Whether admin should manually approve all account registrations.','[\"yes\",\"no\"]','select','Site Options',31),(154,'Cryptoloot Public Key','captcha_cryptoloot_public_key','','Public site key for cryptoloot captcha, if enabled. Register at https://crypto-loot.com','','string','Captcha',40),(155,'Cryptoloot Private Key','captcha_cryptoloot_private_key','','Private site key for cryptoloot captcha, if enabled. Register at http://crypto-loot.com','','string','Captcha',45),(156,'Enable Application Cache','enable_application_cache','yes','Whether to activate application cache or not. This will cache the Twig templates and url routes to improve performance.','[\"yes\",\"no\"]','select','Site Options',6),(157,'User Session Type','user_session_type','Local Sessions','Login session type. If you are using any \"Direct\" file servers, that must be \"Database Sessions\", using \"Local Sessions\" will break cross server support. If you enable a \"Direct\" file server, this is automatically changed to \"Database Sessions\". After changing you will need to re-login.','[\"Local Sessions\", \"Database Sessions\"]','select','Site Options',59),(158,'Support Legacy Folder Urls','support_legacy_folder_urls','Disabled','Whether to support legacy public folder urls or not. In the recent code these are made using a unique 32 character length hash, whereas older urls used the shorter folder id.','[\"Enabled\", \"Disabled\"]','select','File Manager',99),(159,'Lock Download Tokens To IP','lock_download_tokens_to_ip','Disabled','Whether to lock downloads to the original requesting IP address for additional leech protection. Note: This will cause the document viewer to stop working if you are using this functionality.','[\"Enabled\", \"Disabled\"]','select','File Downloads',99),(160,'Username min length','username_min_length','6','The minimum character length for a username.','','string','Security',40),(161,'Username max length','username_max_length','20','The maximum character length for a username.','','string','Security',41),(162,'Advert File Manager View File Top','advert_file_manager_view_file_top','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/468x60?text=468x60+Advert\"/></a>','Advert shown on the view file page above the tabs.','','textarea','Adverts',11),(163,'Advert File Manager View File Bottom','advert_file_manager_view_file_bottom','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/468x60?text=468x60+Advert\"/></a>','Advert shown on the view file page below the tabs.','','textarea','Adverts',12),(164,'Advert File Manager View File Right','advert_file_manager_view_file_right','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/300x600?text=300x600\"/></a>','Advert shown on the view file page on the right-hand side.','','textarea','Adverts',13),(165,'Advert File Manager Left Bar','advert_file_manager_left_bar','<a href=\"https://yetishare.com\" target=\"_blank\"><img src=\"https://via.placeholder.com/250x250?text=250x250\"/></a>','Advert shown on the bottom-left of the file manager.','','textarea','Adverts',14),(166,'New Account Default File Privacy','new_account_default_file_privacy','Private','When a new account is created, this sets the default file privacy option in their account settings.','[\"Private\", \"Public\"]','select','Site Options',100),(167,'Concurrent Uploads','file_manager_concurrent_uploads','Enabled','Whether to process concurrent uploads or one at a time. Enabling will speed up the uploader. For sites with limited resources available, you should disable this.','[\"Disabled\", \"Enabled\"]','select','File Manager',101),(168,'Concurrent Upload Limit','file_manager_concurrent_upload_limit','3','If concurrent uploads is enabled, limit the concurrent upload requests here.','[\"1\", \"2\", \"3\", \"4\", \"5\", \"6\", \"7\", \"8\"]','select','File Manager',102);
/*!40000 ALTER TABLE `site_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stats`
--

DROP TABLE IF EXISTS `stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `download_date` datetime DEFAULT NULL,
  `referer` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `referer_is_local` tinyint(4) NOT NULL DEFAULT '0',
  `file_id` int(11) NOT NULL,
  `country` varchar(6) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `browser_family` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `os` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `base_url` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `download_date` (`download_date`),
  KEY `ip` (`ip`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stats`
--

LOCK TABLES `stats` WRITE;
/*!40000 ALTER TABLE `stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `theme`
--

DROP TABLE IF EXISTS `theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `theme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `theme_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `folder_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `theme_description` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `author_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `author_website` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `is_installed` int(1) NOT NULL DEFAULT '0',
  `date_installed` datetime DEFAULT NULL,
  `theme_settings` text CHARACTER SET utf8 COLLATE utf8_bin,
  PRIMARY KEY (`id`),
  KEY `is_installed` (`is_installed`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `theme`
--

LOCK TABLES `theme` WRITE;
/*!40000 ALTER TABLE `theme` DISABLE KEYS */;
INSERT INTO `theme` VALUES (1,'Spirit Theme','spirit','Bootstrap Yetishare theme included with the core script.','Yetishare','https://yetishare.com',1,NULL,'');
/*!40000 ALTER TABLE `theme` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_level`
--

DROP TABLE IF EXISTS `user_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_level` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `level_id` int(5) NOT NULL,
  `label` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `can_upload` int(1) NOT NULL DEFAULT '0',
  `wait_between_downloads` int(11) NOT NULL DEFAULT '0',
  `download_speed` bigint(16) NOT NULL DEFAULT '0',
  `max_storage_bytes` bigint(18) NOT NULL DEFAULT '0',
  `show_site_adverts` int(1) NOT NULL DEFAULT '0',
  `show_upgrade_screen` int(1) NOT NULL DEFAULT '0',
  `days_to_keep_inactive_files` int(11) NOT NULL DEFAULT '360',
  `days_to_keep_trashed_files` int(11) NOT NULL DEFAULT '0',
  `concurrent_uploads` int(11) NOT NULL DEFAULT '50',
  `concurrent_downloads` int(11) NOT NULL DEFAULT '5',
  `downloads_per_24_hours` int(11) NOT NULL DEFAULT '0',
  `max_download_filesize_allowed` bigint(18) NOT NULL DEFAULT '0',
  `can_remote_download` int(1) NOT NULL DEFAULT '1',
  `max_remote_download_urls` int(11) NOT NULL DEFAULT '0',
  `max_upload_size` bigint(18) NOT NULL DEFAULT '0',
  `max_uploads_per_day` bigint(18) NOT NULL DEFAULT '0',
  `accepted_file_types` varchar(255) NOT NULL DEFAULT '',
  `blocked_file_types` varchar(255) NOT NULL DEFAULT '',
  `delete_account_after_days` int(11) NOT NULL DEFAULT '0',
  `level_type` enum('admin','free','paid','moderator','nonuser') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT 'free',
  `on_upgrade_page` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `level_id` (`level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_level`
--

LOCK TABLES `user_level` WRITE;
/*!40000 ALTER TABLE `user_level` DISABLE KEYS */;
INSERT INTO `user_level` VALUES (0,0,'temp user',1,0,50000,0,1,1,60,0,50,1,0,0,1,5,104857600,50,'','',0,'nonuser',0),(1,1,'free user',1,0,50000,0,1,1,60,0,50,0,0,0,1,5,104857600,50,'','',0,'free',0),(2,2,'paid user',1,0,0,0,0,1,0,0,100,0,0,0,1,50,1073741824,50,'','',0,'paid',1),(10,10,'moderator',1,0,0,0,0,1,0,0,100,0,0,0,1,50,1073741824,50,'','',0,'moderator',0),(20,20,'admin',1,0,0,0,0,1,0,0,100,0,0,0,1,50,1073741824,50,'','',0,'admin',0);
/*!40000 ALTER TABLE `user_level` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_level_pricing`
--

DROP TABLE IF EXISTS `user_level_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_level_pricing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_level_id` int(11) NOT NULL,
  `pricing_label` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `package_pricing_type` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT 'period',
  `period` varchar(10) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT '1M',
  `download_allowance` bigint(20) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_level_id` (`user_level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_level_pricing`
--

LOCK TABLES `user_level_pricing` WRITE;
/*!40000 ALTER TABLE `user_level_pricing` DISABLE KEYS */;
INSERT INTO `user_level_pricing` VALUES (1,2,'7 Days','period','7D',NULL,4.99),(2,2,'6 Months','period','6M',NULL,34.99),(3,2,'1 Month','period','1M',NULL,9.99),(4,2,'1 Year','period','1Y',NULL,59.99),(5,2,'3 Months','period','3M',NULL,19.99);
/*!40000 ALTER TABLE `user_level_pricing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(65) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `level_id` int(5) NOT NULL DEFAULT '1',
  `email` varchar(65) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `lastlogindate` timestamp NULL DEFAULT NULL,
  `lastloginip` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` enum('active','pending','disabled','suspended','awaiting approval') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `title` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `firstname` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `lastname` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `languageId` int(11) DEFAULT NULL,
  `datecreated` timestamp NULL DEFAULT NULL,
  `createdip` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `lastPayment` timestamp NULL DEFAULT NULL,
  `paidExpiryDate` timestamp NULL DEFAULT NULL,
  `remainingBWDownload` bigint(20) DEFAULT NULL,
  `paymentTracker` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `passwordResetHash` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `identifier` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `apikey` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `storageLimitOverride` bigint(15) DEFAULT NULL,
  `privateFileStatistics` int(1) NOT NULL DEFAULT '0',
  `uploadServerOverride` bigint(20) DEFAULT NULL,
  `userGroupId` int(11) DEFAULT NULL,
  `accountLockStatus` int(1) NOT NULL DEFAULT '0',
  `accountLockHash` varchar(16) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `profile` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `isPublic` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `datecreated` (`datecreated`),
  KEY `username_2` (`username`),
  KEY `status` (`status`),
  KEY `email` (`email`),
  KEY `level_id` (`level_id`),
  KEY `level_id_2` (`level_id`),
  KEY `apikey` (`apikey`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','5f4dcc3b5aa765d61d8327deb882cf99',20,'email@yoursite.com',NULL,'192.168.2.100','active','Mr','Admin','User',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'','',NULL,0,NULL,NULL,0,'',NULL,1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;


INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('Hide Uploader Popup On Finish', 'file_manager_hide_upload_on_finish', 'yes', 'Whether to hide the uploader popup when file uploading has finished.', '[\"yes\",\"no\"]', 'select', 'File Manager', '103');

UPDATE file_server SET routeViaMainSite = 1 WHERE serverType = 'direct';
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('Adblock Filename', 'adblock_filename', 'xads.js', 'The JS filename to use for the adblock code. Do not change this unless the Adblock Limiter is no longer working. No spaces or special characters allowed. Example: xads.js', '', 'string', 'Adverts', '2');

UPDATE `site_config` SET `availableValues` = 'SELECT serverLabel AS itemValue, IF(statusId=2, "Active", "Unavailable") AS itemGroup FROM file_server LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id ORDER BY serverLabel' WHERE `config_key` = 'default_file_server';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('Show Report File Form', 'captcha_report_file_form', 'no', 'Show the captcha on the report_file form.', '["yes","no"]', 'select', 'Captcha', '8');

DELETE FROM `site_config` WHERE `config_key` = 'show_cookie_notice';
INSERT INTO `site_config` (`config_key`, `label`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('show_cookie_notice', 'Show Cookie Notice', 'yes', 'Show the cookie notice on the front-end theme. GDPR requirement. Shows only the first time for each visitor.', '[\"yes\",\"no\"]', 'select', 'Site Options', 100);


--
-- v5.2.0
--

UPDATE `site_config` SET `config_description` = 'Should remote file downloads be done in the background? If yes you will need to setup the crontask /app/tasks/process_remote_file_downloads.cron.php to run every minute.' WHERE `config_key` = 'remote_url_download_in_background';
DELETE FROM `site_config` WHERE config_key = 'security_account_lock' LIMIT 1;
INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('Enable 2FA', 'enable_2fa', 'no', 'Whether 2FA is available to your users.', '[\"yes\",\"no\"]', 'select', 'Security', '50');
ALTER TABLE `users` ADD `login_2fa_enabled` int(1) NOT NULL DEFAULT '0';
ALTER TABLE `users` ADD `login_2fa_salt` varchar(32) NULL;
ALTER TABLE `users` ADD `fileReferrerWhitelist` text NULL AFTER `isPublic`;

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('Show Front-End API Documentation', 'show_api_page', 'yes', 'Whether to show the front-end API documentation page. It appears as a link in the site footer.', '[\"yes\",\"no\"]', 'select', 'Site Options', '51');

ALTER TABLE `users` DROP `userGroupId`;

ALTER TABLE `file_block_hash` ADD `file_size` bigint(15) NULL AFTER `file_hash`;
INSERT INTO `file_block_hash` (SELECT null, fileHash, fileSize, NOW() FROM banned_files WHERE fileHash NOT IN (SELECT file_hash FROM file_block_hash));
DROP TABLE `banned_files`;

ALTER TABLE `download_page` ADD `file_type_limit` text NULL AFTER `page_order`;

UPDATE `site_config` SET `config_value` = '' WHERE `config_key` = 'system_plugin_config_cache';

SET @s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'plugin_newsletter2'
        AND table_schema = DATABASE()
    ) > 0,
    "ALTER TABLE `plugin_newsletter` CHANGE `subject` `subject` text COLLATE 'utf8_bin' NULL AFTER `title`, CHANGE `html_content` `html_content` text COLLATE 'utf8_bin' NULL AFTER `subject`",
    "SELECT null"
));

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'plugin_newsletter2'
        AND table_schema = DATABASE()
    ) > 0,
    "ALTER TABLE IF EXISTS `plugin_newsletter` CHANGE `user_group` `user_group` varchar(20) COLLATE 'utf8_bin' NULL AFTER `form_type`",
    "SELECT null"
));

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `site_config` SET config_value = REPLACE(config_value, '[[[SITE_IMAGE_PATH]]]/logo/logo-whitebg.png', '[[[EMAIL_LOGO_URL]]]'), config_description = REPLACE(config_description, 'Test using the \'admin/_test_scripts/test_email.php\' script', 'Test via the \'test tools\' within the script admin area') WHERE config_key = 'email_template_header';
UPDATE `site_config` SET config_description = REPLACE(config_description, 'Test using the \'admin/_test_scripts/test_email.php\' script', 'Test via the \'test tools\' within the script admin area') WHERE config_key = 'email_template_footer';

INSERT INTO `site_config` (`label`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`, `config_group`, `display_order`) VALUES ('File Hashing Max Filesize (GB)', 'file_hashing_max_filesize_gb', '', 'Optional. Some low resource web hosts may take a long time generating file hashes on upload. This would be noticed by a long delay at the 100% uploaded point. Set a numeric value here to not calculate these file hashes for files bigger than this GB in size (example value: 4). Leave empty to ignore.', '', 'integer', 'File Uploads', '101');

DROP TABLE IF EXISTS `file_status`;
CREATE TABLE `file_status_reason` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(30) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `file_status_reason` (`id`, `label`) VALUES
(1,	'removed'),
(2,	'user removed'),
(3,	'admin removed'),
(4,	'copyright removed'),
(5,	'system expired');

ALTER TABLE `file` ADD `status_reason_id` int(3) NULL AFTER `status`;
UPDATE `theme` SET theme_description = REPLACE(theme_description, 'Yetishare', 'Yetishare'), author_name = 'Yetishare' WHERE author_name = 'Yetishare';


--
-- v5.3.0
--
DELETE FROM background_task_log WHERE task_id IN (SELECT id FROM background_task WHERE task = 'plugin_tasks.cron.php');
DELETE FROM background_task WHERE task = 'plugin_tasks.cron.php';

UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_delayed_redirect_top' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_delayed_redirect_bottom' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_site_footer' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_file_manager_view_file_top' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_file_manager_view_file_bottom' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_file_manager_view_file_right' AND config_group = 'Adverts';
UPDATE site_config SET config_group = 'AdvertsBannerAds' WHERE config_key = 'advert_file_manager_left_bar' AND config_group = 'Adverts';

ALTER TABLE `site_config` ADD `site_settings_hidden` int(1) NULL DEFAULT '0';
UPDATE site_config SET site_settings_hidden = 1 WHERE config_group = 'AdvertsBannerAds';
UPDATE site_config SET site_settings_hidden = 1 WHERE config_group = 'System';

ALTER TABLE `user_level` ADD `upload_url_slug` varchar(150) NOT NULL DEFAULT '' AFTER `max_storage_bytes`;
ALTER TABLE `user_level` ADD `download_url_slug` varchar(150) NOT NULL DEFAULT '' AFTER `max_download_filesize_allowed`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group) VALUES ("Different Ads For Adult Content", "different_ads_for_adult_content", "0", "Whether to use different ads for files which contain adult content", "", "integer", "Adverts");
UPDATE site_config SET site_settings_hidden = 1 WHERE config_group = 'Adverts';
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order) VALUES ("Adult Content Keywords", "adult_content_keywords", "", "Optional. A list of words used to identify adult content. Matched against the uploaded filename and keywords. Pipe separated list. i.e. word1|word2|word3.", "", "textarea", "File Uploads", 1000);

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Adult Advert Delayed Redirect Top", "adult_advert_delayed_redirect_top", "", "Adult Only - Delayed redirect top advert (html)", "", "textarea", "AdvertsBannerAds", 5, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Adult Advert Delayed Redirect Bottom", "adult_advert_delayed_redirect_bottom", "", "Adult Only - Delayed redirect bottom advert (html)", "", "textarea", "AdvertsBannerAds", 6, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Adult Advert File Manager View File Top", "adult_advert_file_manager_view_file_top", "", "Adult Only - Advert shown on the view file page above the tabs.", "", "textarea", "AdvertsBannerAds", 12, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Adult Advert File Manager View File Bottom", "adult_advert_file_manager_view_file_bottom", "", "Adult Only - Advert shown on the view file page below the tabs.", "", "textarea", "AdvertsBannerAds", 13, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Adult Advert File Manager View File Right", "adult_advert_file_manager_view_file_right", "", "Adult Only - Advert shown on the view file page on the right-hand side.", "", "textarea", "AdvertsBannerAds", 14, 1);

ALTER TABLE `file_server` ADD `accountUploadTypes` varchar(1000) COLLATE 'utf8_general_ci' NULL;

ALTER TABLE `download_token` ADD `limit_by_ip` int(1) NOT NULL DEFAULT '0';

UPDATE `language_key` SET `defaultContent` = 'File Privacy' WHERE `languageKey` = 'default_privacy' LIMIT 1;
UPDATE `language_content` SET content = 'File Privacy' WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'default_privacy') AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

UPDATE `language_key` SET `defaultContent` = 'All Files Private (access only via your account or by generating unique sharing urls)' WHERE `languageKey` = 'settings_private_files' LIMIT 1;
UPDATE `language_content` SET content = 'All Files Private (access only via your account or by generating unique sharing urls)' WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'settings_private_files') AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

UPDATE `language_key` SET `defaultContent` = 'Files Publicly Accessible (publicly shared by default, you can still create private folders with private files within)' WHERE `languageKey` = 'settings_public_files' LIMIT 1;
UPDATE `language_content` SET content = 'Files Publicly Accessible (publicly shared by default, you can still create private folders with private files within)' WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'settings_public_files') AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

ALTER TABLE `users` ADD `never_expire` tinyint(1) NULL DEFAULT '0' AFTER `paidExpiryDate`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group) VALUES ("Video Advert Type", "advert_video_ad_type", "", "Which type of adverts to use in videos.", "", "string", "Adverts");
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group) VALUES ("Video Advert VAST Urls", "advert_video_ad_vast_urls", "", "The list of VAST urls to use on videos if the advert type is set to VAST.", "", "string", "Adverts");

ALTER TABLE `login_failure` CHANGE `ip_address` `ip_address` varchar(45) COLLATE 'utf8_general_ci' NOT NULL AFTER `id`;
ALTER TABLE `login_success` CHANGE `ip_address` `ip_address` varchar(45) COLLATE 'utf8_general_ci' NOT NULL AFTER `id`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Enable Chunked Uploads", "chunked_uploading_enabled", "yes", "Whether chunked uploading is enabled. Recommended to keep as 'yes'.", '["yes","no"]', "select", "File Uploads", 101, 0);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Chunked Upload Size", "chunked_upload_size_mb", "100", "If 'Enable Chunked Uploads' is set to 'yes',
        this is the size of the chunks in MB. Recommended to use '100'.", '', "integer", "File Uploads", 102, 0);

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group) VALUES ("Adult Video Advert VAST Urls", "adult_advert_video_ad_vast_urls", "", "Adult Only - The list of VAST urls to use on adult videos if the advert type is set to VAST.", "", "string", "Adverts");

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Advert 'Head' Tag Code", "advert_head_tag_code", "", "Optional. Code inserted into the <head> secton of every page.", "", "textarea", "AdvertsBannerAds", 99, 1);

UPDATE site_config SET site_settings_hidden = 1 WHERE config_group = 'Adverts';

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, display_order, site_settings_hidden) VALUES ("Login Required To Upload", "upload_login_required", "no", "Whether a login is required for file uploads. This only applies in the UI if the theme supports non-login uploads. The 'Spirit' theme currently requires a login to upload. This option can also be used to block offsite uploads for non-login,
        such as desktop uploader apps.", '["yes","no"]', "select", "File Uploads", 103, 0);

UPDATE `banned_ip`
SET `banType` = 'Uploading',
    banNotes  = CONCAT('Originally banned whole site. ', banNotes)
WHERE banType = 'Whole Site';
ALTER TABLE `banned_ip`
    ADD INDEX `banExpiry` (`banExpiry`);
ALTER TABLE `language`
    ADD INDEX `languageName` (`languageName`);

--
-- v5.4.0
--
ALTER TABLE `file_server` ADD `capture_resource_usage` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET file_server.`capture_resource_usage` = 1 WHERE serverType IN ('local', 'direct');

ALTER TABLE `file_server`
    ADD INDEX `capture_resource_usage` (`capture_resource_usage`),
    ADD INDEX `serverType` (`serverType`);

ALTER TABLE `file_server`
    ADD INDEX `geoUploadCountries` (`geoUploadCountries`),
    ADD INDEX `accountUploadTypes` (`accountUploadTypes`);

CREATE TABLE `file_artifact` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `file_id` int unsigned NOT NULL,
 `local_file_path` varchar(255) DEFAULT NULL,
 `file_type` varchar(150) DEFAULT NULL,
 `file_hash` varchar(32) CHARACTER SET utf8mb4 DEFAULT NULL,
 `file_artifact_type` enum('primary','mirror','preview','version','format') NOT NULL DEFAULT 'primary',
 `file_size` bigint NOT NULL DEFAULT '0',
 `internal_reference` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL,
 `override_filename` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
 `override_extension` varchar(10) CHARACTER SET utf8mb4 DEFAULT NULL,
 `created` datetime NOT NULL,
 `updated` datetime DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `file_id` (`file_id`),
 KEY `internal_reference` (`internal_reference`),
 KEY `created` (`created`),
 KEY `file_hash` (`file_hash`),
 KEY `file_artifact_type` (`file_artifact_type`),
 KEY `file_size` (`file_size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO file_artifact (SELECT null, id, localFilePath, fileType, fileHash, 'primary', fileSize, null, null, null, now(), null FROM file WHERE status IN('active', 'trash'));

CREATE TABLE `file_artifact_storage` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `file_artifact_id` int unsigned NOT NULL,
 `file_server_id` int NOT NULL,
 `is_primary` tinyint(1) NOT NULL DEFAULT '1',
 `created` datetime NOT NULL,
 `updated` datetime DEFAULT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `file_artifact_id_file_server_id` (`file_artifact_id`,`file_server_id`),
 KEY `file_server_id` (`file_server_id`),
 KEY `file_artifact_id` (`file_artifact_id`),
 KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO file_artifact_storage (SELECT null, file_artifact.id, serverId, 1, now(), null FROM file_artifact LEFT JOIN file ON file_artifact.file_id = file.id WHERE status IN('active', 'trash'));

ALTER TABLE `file_server` ADD `is_default` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET is_default = 1 WHERE serverLabel = 'Local Default';

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type, config_group, `display_order`)
VALUES ("Storage Filename Structure", "upload_storage_filename_structure", "Secure Hashed", "Whether to store uploaded files using a secure hashed filename or in subfolders with their original filenames. This only impacts how the files are actually stored on your servers. Setting as 'User Id/Original Filename' will disable the de-dupe functionality. Recommended to set this as 'Secure Hashed' in most instances for security reasons.", "[\"Secure Hashed\",\"User Id/Original Filename\"]", "select", "File Uploads", 10);

DROP TABLE IF EXISTS `user_action_log`;
CREATE TABLE `user_action_log` (
                                   `id` int unsigned NOT NULL AUTO_INCREMENT,
                                   `message` varchar(1000) NOT NULL,
                                   `params` varchar(2000) DEFAULT NULL,
                                   `category` int NOT NULL,
                                   `action_type` int NOT NULL,
                                   `date_created` datetime NOT NULL,
                                   `action_user_id` int DEFAULT NULL,
                                   `user_ip` varchar(45) DEFAULT NULL,
                                   `admin_area_action` tinyint NOT NULL DEFAULT '0',
                                   `file_id` int DEFAULT NULL,
                                   `user_id` int DEFAULT NULL,
                                   PRIMARY KEY (`id`),
                                   KEY `action_user_id` (`action_user_id`),
                                   KEY `file_id` (`file_id`),
                                   KEY `user_id` (`user_id`),
                                   KEY `date_created` (`date_created`),
                                   KEY `is_admin_user_id` (`admin_area_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO user_action_log (SELECT null, "File uploaded", null, 3, 6, uploadedDate, userId, uploadedIP, 0, id, null FROM file);

ALTER TABLE `file_action`
    ADD `artifact_id` int NULL AFTER `file_id`;

ALTER TABLE `file`
DROP `fileSize`,
    DROP `fileType`,
    DROP `localFilePath`,
    DROP `serverId`,
    DROP `fileHash`;

ALTER TABLE `user_level` ADD `download_size_per_24_hours` int NOT NULL DEFAULT '0' AFTER `downloads_per_24_hours`;

UPDATE `plugin_payment_gateways` SET `gateway_type` = 2, `gateway_additional_params` = '{\"description\true,\"returnUrl\true}' WHERE `class_name` = 'Coinbase';
UPDATE plugin_payment_gateways SET available = 1 WHERE class_name = 'TwoCheckout';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Braintree';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'Buckaroo_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'CardSave';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Coinbase';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'FirstData_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'GoCardless';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'Migs_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Netaxept';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'NetBanx';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'OKPAY';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Paysafecard';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Pin';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name LIKE 'TargetPay_%';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'WebMoney';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Sofort';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'Paysera';
UPDATE plugin_payment_gateways SET available = 0 WHERE class_name = 'EgopayRu';
INSERT INTO `plugin_payment_gateways` (`class_name`, `label`, `description`, `url`, `gateway_type`, `gateway_group`, `available`, `gateway_additional_params`)
SELECT 'Bill99', '99Bill', NULL, 'https://www.99bill.com', 1, '99Bill', '1', NULL
FROM `plugin_payment_gateways`
WHERE ((`id` = '50'));

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order)
VALUES ("User Can Empty Trash Can", "user_can_empty_trash_can", "yes",
        "Whether to enable access for users to empty the trash via their account", '["yes","no"]', "select", "File Manager", 500);

ALTER TABLE `file_server` ADD `monitor_server_resources` tinyint NOT NULL DEFAULT '0';
UPDATE `file_server` SET `monitor_server_resources` = 1 WHERE serverType IN ('local', 'direct');

ALTER TABLE `download_token` ADD `internal_download` int NOT NULL DEFAULT '0';

ALTER TABLE `file_server` ADD `enable_availability_checker` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `file_server` ADD `availability_state` tinyint NULL DEFAULT NULL;

DROP TABLE IF EXISTS `file_server_resource_usage`;
CREATE TABLE `file_server_resource_usage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `file_server_id` int NOT NULL,
  `date_created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `cpu_load_1_minute` decimal(10,2) DEFAULT NULL,
  `cpu_load_5_minutes` decimal(10,2) DEFAULT NULL,
  `cpu_load_15_minutes` decimal(10,2) DEFAULT NULL,
  `cpu_count` int DEFAULT NULL,
  `memory_total_gb` decimal(10,2) DEFAULT NULL,
  `memory_used_gb` decimal(10,2) DEFAULT NULL,
  `memory_free_gb` decimal(10,2) DEFAULT NULL,
  `memory_shared_gb` decimal(10,2) DEFAULT NULL,
  `memory_cached_gb` decimal(10,2) DEFAULT NULL,
  `memory_available_gb` decimal(10,2) DEFAULT NULL,
  `disk_primary_total_bytes` bigint DEFAULT NULL,
  `disk_primary_used_bytes` bigint DEFAULT NULL,
  `disk_primary_used_percent` decimal(10,2) DEFAULT NULL,
  `network_established_connections` int DEFAULT NULL,
  `network_total_connections` int DEFAULT NULL,
  `has_shell_exec` tinyint(1) DEFAULT '0',
  `has_netstat` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `file_server_id` (`file_server_id`),
  KEY `date_created` (`date_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;