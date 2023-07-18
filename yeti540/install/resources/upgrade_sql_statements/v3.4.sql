ALTER TABLE `file` CHANGE `adminNotes` `adminNotes` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE `file`
    ADD `fileHash` VARCHAR(32) NULL;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_max_downloads_per_day', '0',
        'The maximum files a free user can download in a 24 hour period. Set to 0 (zero) to disable.', '', 'integer',
        'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'session_expiry', '86400',
        'The amount of time a user can be inactive before their session will expire. In seconds. Default is 86400 (1 day)',
        '', 'integer', 'Page Options');

CREATE TABLE IF NOT EXISTS `country_info`
(
    `id` int
(
    11
) NOT NULL AUTO_INCREMENT,
    `iso_alpha2` varchar
(
    2
) DEFAULT NULL,
    `iso_alpha3` varchar
(
    3
) DEFAULT NULL,
    `name` varchar
(
    200
) DEFAULT NULL,
    `currency_code` char
(
    3
) DEFAULT NULL,
    `currency_name` varchar
(
    32
) DEFAULT NULL,
    `currrency_symbol` varchar
(
    3
) DEFAULT NULL,
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=248;

--
-- Dumping data for table `country_info`
--
DELETE
FROM `country_info`;
INSERT INTO `country_info` (`id`, `iso_alpha2`, `iso_alpha3`, `name`, `currency_code`, `currency_name`,
                            `currrency_symbol`)
VALUES (1, 'AD', 'AND', 'Andorra', 'EUR', 'Euro', '€'),
       (2, 'AE', 'ARE', 'United Arab Emirates', 'AED', 'Dirham', NULL),
       (3, 'AF', 'AFG', 'Afghanistan', 'AFN', 'Afghani', '؋'),
       (4, 'AG', 'ATG', 'Antigua and Barbuda', 'XCD', 'Dollar', '$'),
       (5, 'AI', 'AIA', 'Anguilla', 'XCD', 'Dollar', '$'),
       (6, 'AL', 'ALB', 'Albania', 'ALL', 'Lek', 'Lek'),
       (7, 'AM', 'ARM', 'Armenia', 'AMD', 'Dram', NULL),
       (8, 'AN', 'ANT', 'Netherlands Antilles', 'ANG', 'Guilder', 'ƒ'),
       (9, 'AO', 'AGO', 'Angola', 'AOA', 'Kwanza', 'Kz'),
       (10, 'AQ', 'ATA', 'Antarctica', '', '', NULL),
       (11, 'AR', 'ARG', 'Argentina', 'ARS', 'Peso', '$'),
       (12, 'AS', 'ASM', 'American Samoa', 'USD', 'Dollar', '$'),
       (13, 'AT', 'AUT', 'Austria', 'EUR', 'Euro', '€'),
       (14, 'AU', 'AUS', 'Australia', 'AUD', 'Dollar', '$'),
       (15, 'AW', 'ABW', 'Aruba', 'AWG', 'Guilder', 'ƒ'),
       (16, 'AX', 'ALA', 'Aland Islands', 'EUR', 'Euro', '€'),
       (17, 'AZ', 'AZE', 'Azerbaijan', 'AZN', 'Manat', 'ман'),
       (18, 'BA', 'BIH', 'Bosnia and Herzegovina', 'BAM', 'Marka', 'KM'),
       (19, 'BB', 'BRB', 'Barbados', 'BBD', 'Dollar', '$'),
       (20, 'BD', 'BGD', 'Bangladesh', 'BDT', 'Taka', NULL),
       (21, 'BE', 'BEL', 'Belgium', 'EUR', 'Euro', '€'),
       (22, 'BF', 'BFA', 'Burkina Faso', 'XOF', 'Franc', NULL),
       (23, 'BG', 'BGR', 'Bulgaria', 'BGN', 'Lev', 'лв'),
       (24, 'BH', 'BHR', 'Bahrain', 'BHD', 'Dinar', NULL),
       (25, 'BI', 'BDI', 'Burundi', 'BIF', 'Franc', NULL),
       (26, 'BJ', 'BEN', 'Benin', 'XOF', 'Franc', NULL),
       (27, 'BL', 'BLM', 'Saint Barthélemy', 'EUR', 'Euro', '€'),
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
       (48, 'CN', 'CHN', 'China', 'CNY', 'Yuan Renminbi', '¥'),
       (49, 'CO', 'COL', 'Colombia', 'COP', 'Peso', '$'),
       (50, 'CR', 'CRI', 'Costa Rica', 'CRC', 'Colon', '₡'),
       (51, 'CU', 'CUB', 'Cuba', 'CUP', 'Peso', '₱'),
       (52, 'CV', 'CPV', 'Cape Verde', 'CVE', 'Escudo', NULL),
       (53, 'CX', 'CXR', 'Christmas Island', 'AUD', 'Dollar', '$'),
       (54, 'CY', 'CYP', 'Cyprus', 'CYP', 'Pound', NULL),
       (55, 'CZ', 'CZE', 'Czech Republic', 'CZK', 'Koruna', 'Kč'),
       (56, 'DE', 'DEU', 'Germany', 'EUR', 'Euro', '€'),
       (57, 'DJ', 'DJI', 'Djibouti', 'DJF', 'Franc', NULL),
       (58, 'DK', 'DNK', 'Denmark', 'DKK', 'Krone', 'kr'),
       (59, 'DM', 'DMA', 'Dominica', 'XCD', 'Dollar', '$'),
       (60, 'DO', 'DOM', 'Dominican Republic', 'DOP', 'Peso', 'RD$'),
       (61, 'DZ', 'DZA', 'Algeria', 'DZD', 'Dinar', NULL),
       (62, 'EC', 'ECU', 'Ecuador', 'USD', 'Dollar', '$'),
       (63, 'EE', 'EST', 'Estonia', 'EEK', 'Kroon', 'kr'),
       (64, 'EG', 'EGY', 'Egypt', 'EGP', 'Pound', '£'),
       (65, 'EH', 'ESH', 'Western Sahara', 'MAD', 'Dirham', NULL),
       (66, 'ER', 'ERI', 'Eritrea', 'ERN', 'Nakfa', 'Nfk'),
       (67, 'ES', 'ESP', 'Spain', 'EUR', 'Euro', '€'),
       (68, 'ET', 'ETH', 'Ethiopia', 'ETB', 'Birr', NULL),
       (69, 'FI', 'FIN', 'Finland', 'EUR', 'Euro', '€'),
       (70, 'FJ', 'FJI', 'Fiji', 'FJD', 'Dollar', '$'),
       (71, 'FK', 'FLK', 'Falkland Islands', 'FKP', 'Pound', '£'),
       (72, 'FM', 'FSM', 'Micronesia', 'USD', 'Dollar', '$'),
       (73, 'FO', 'FRO', 'Faroe Islands', 'DKK', 'Krone', 'kr'),
       (74, 'FR', 'FRA', 'France', 'EUR', 'Euro', '€'),
       (75, 'GA', 'GAB', 'Gabon', 'XAF', 'Franc', 'FCF'),
       (76, 'GB', 'GBR', 'United Kingdom', 'GBP', 'Pound', '£'),
       (77, 'GD', 'GRD', 'Grenada', 'XCD', 'Dollar', '$'),
       (78, 'GE', 'GEO', 'Georgia', 'GEL', 'Lari', NULL),
       (79, 'GF', 'GUF', 'French Guiana', 'EUR', 'Euro', '€'),
       (80, 'GG', 'GGY', 'Guernsey', 'GGP', 'Pound', '£'),
       (81, 'GH', 'GHA', 'Ghana', 'GHC', 'Cedi', '¢'),
       (82, 'GI', 'GIB', 'Gibraltar', 'GIP', 'Pound', '£'),
       (83, 'GL', 'GRL', 'Greenland', 'DKK', 'Krone', 'kr'),
       (84, 'GM', 'GMB', 'Gambia', 'GMD', 'Dalasi', 'D'),
       (85, 'GN', 'GIN', 'Guinea', 'GNF', 'Franc', NULL),
       (86, 'GP', 'GLP', 'Guadeloupe', 'EUR', 'Euro', '€'),
       (87, 'GQ', 'GNQ', 'Equatorial Guinea', 'XAF', 'Franc', 'FCF'),
       (88, 'GR', 'GRC', 'Greece', 'EUR', 'Euro', '€'),
       (89, 'GS', 'SGS', 'South Georgia and the South Sandwich Islands', 'GBP', 'Pound', '£'),
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
       (101, 'IE', 'IRL', 'Ireland', 'EUR', 'Euro', '€'),
       (102, 'IL', 'ISR', 'Israel', 'ILS', 'Shekel', '₪'),
       (103, 'IM', 'IMN', 'Isle of Man', 'GPD', 'Pound', '£'),
       (104, 'IN', 'IND', 'India', 'INR', 'Rupee', '₨'),
       (105, 'IO', 'IOT', 'British Indian Ocean Territory', 'USD', 'Dollar', '$'),
       (106, 'IQ', 'IRQ', 'Iraq', 'IQD', 'Dinar', NULL),
       (107, 'IR', 'IRN', 'Iran', 'IRR', 'Rial', '﷼'),
       (108, 'IS', 'ISL', 'Iceland', 'ISK', 'Krona', 'kr'),
       (109, 'IT', 'ITA', 'Italy', 'EUR', 'Euro', '€'),
       (110, 'JE', 'JEY', 'Jersey', 'JEP', 'Pound', '£'),
       (111, 'JM', 'JAM', 'Jamaica', 'JMD', 'Dollar', '$'),
       (112, 'JO', 'JOR', 'Jordan', 'JOD', 'Dinar', NULL),
       (113, 'JP', 'JPN', 'Japan', 'JPY', 'Yen', '¥'),
       (114, 'KE', 'KEN', 'Kenya', 'KES', 'Shilling', NULL),
       (115, 'KG', 'KGZ', 'Kyrgyzstan', 'KGS', 'Som', 'лв'),
       (116, 'KH', 'KHM', 'Cambodia', 'KHR', 'Riels', '៛'),
       (117, 'KI', 'KIR', 'Kiribati', 'AUD', 'Dollar', '$'),
       (118, 'KM', 'COM', 'Comoros', 'KMF', 'Franc', NULL),
       (119, 'KN', 'KNA', 'Saint Kitts and Nevis', 'XCD', 'Dollar', '$'),
       (120, 'KP', 'PRK', 'North Korea', 'KPW', 'Won', '₩'),
       (121, 'KR', 'KOR', 'South Korea', 'KRW', 'Won', '₩'),
       (122, 'KW', 'KWT', 'Kuwait', 'KWD', 'Dinar', NULL),
       (123, 'KY', 'CYM', 'Cayman Islands', 'KYD', 'Dollar', '$'),
       (124, 'KZ', 'KAZ', 'Kazakhstan', 'KZT', 'Tenge', 'лв'),
       (125, 'LA', 'LAO', 'Laos', 'LAK', 'Kip', '₭'),
       (126, 'LB', 'LBN', 'Lebanon', 'LBP', 'Pound', '£'),
       (127, 'LC', 'LCA', 'Saint Lucia', 'XCD', 'Dollar', '$'),
       (128, 'LI', 'LIE', 'Liechtenstein', 'CHF', 'Franc', 'CHF'),
       (129, 'LK', 'LKA', 'Sri Lanka', 'LKR', 'Rupee', '₨'),
       (130, 'LR', 'LBR', 'Liberia', 'LRD', 'Dollar', '$'),
       (131, 'LS', 'LSO', 'Lesotho', 'LSL', 'Loti', 'L'),
       (132, 'LT', 'LTU', 'Lithuania', 'LTL', 'Litas', 'Lt'),
       (133, 'LU', 'LUX', 'Luxembourg', 'EUR', 'Euro', '€'),
       (134, 'LV', 'LVA', 'Latvia', 'LVL', 'Lat', 'Ls'),
       (135, 'LY', 'LBY', 'Libya', 'LYD', 'Dinar', NULL),
       (136, 'MA', 'MAR', 'Morocco', 'MAD', 'Dirham', NULL),
       (137, 'MC', 'MCO', 'Monaco', 'EUR', 'Euro', '€'),
       (138, 'MD', 'MDA', 'Moldova', 'MDL', 'Leu', NULL),
       (139, 'ME', 'MNE', 'Montenegro', 'EUR', 'Euro', '€'),
       (140, 'MF', 'MAF', 'Saint Martin', 'EUR', 'Euro', '€'),
       (141, 'MG', 'MDG', 'Madagascar', 'MGA', 'Ariary', NULL),
       (142, 'MH', 'MHL', 'Marshall Islands', 'USD', 'Dollar', '$'),
       (143, 'MK', 'MKD', 'Macedonia', 'MKD', 'Denar', 'ден'),
       (144, 'ML', 'MLI', 'Mali', 'XOF', 'Franc', NULL),
       (145, 'MM', 'MMR', 'Myanmar', 'MMK', 'Kyat', 'K'),
       (146, 'MN', 'MNG', 'Mongolia', 'MNT', 'Tugrik', '₮'),
       (147, 'MO', 'MAC', 'Macao', 'MOP', 'Pataca', 'MOP'),
       (148, 'MP', 'MNP', 'Northern Mariana Islands', 'USD', 'Dollar', '$'),
       (149, 'MQ', 'MTQ', 'Martinique', 'EUR', 'Euro', '€'),
       (150, 'MR', 'MRT', 'Mauritania', 'MRO', 'Ouguiya', 'UM'),
       (151, 'MS', 'MSR', 'Montserrat', 'XCD', 'Dollar', '$'),
       (152, 'MT', 'MLT', 'Malta', 'MTL', 'Lira', NULL),
       (153, 'MU', 'MUS', 'Mauritius', 'MUR', 'Rupee', '₨'),
       (154, 'MV', 'MDV', 'Maldives', 'MVR', 'Rufiyaa', 'Rf'),
       (155, 'MW', 'MWI', 'Malawi', 'MWK', 'Kwacha', 'MK'),
       (156, 'MX', 'MEX', 'Mexico', 'MXN', 'Peso', '$'),
       (157, 'MY', 'MYS', 'Malaysia', 'MYR', 'Ringgit', 'RM'),
       (158, 'MZ', 'MOZ', 'Mozambique', 'MZN', 'Meticail', 'MT'),
       (159, 'NA', 'NAM', 'Namibia', 'NAD', 'Dollar', '$'),
       (160, 'NC', 'NCL', 'New Caledonia', 'XPF', 'Franc', NULL),
       (161, 'NE', 'NER', 'Niger', 'XOF', 'Franc', NULL),
       (162, 'NF', 'NFK', 'Norfolk Island', 'AUD', 'Dollar', '$'),
       (163, 'NG', 'NGA', 'Nigeria', 'NGN', 'Naira', '₦'),
       (164, 'NI', 'NIC', 'Nicaragua', 'NIO', 'Cordoba', 'C$'),
       (165, 'NL', 'NLD', 'Netherlands', 'EUR', 'Euro', '€'),
       (166, 'NO', 'NOR', 'Norway', 'NOK', 'Krone', 'kr'),
       (167, 'NP', 'NPL', 'Nepal', 'NPR', 'Rupee', '₨'),
       (168, 'NR', 'NRU', 'Nauru', 'AUD', 'Dollar', '$'),
       (169, 'NU', 'NIU', 'Niue', 'NZD', 'Dollar', '$'),
       (170, 'NZ', 'NZL', 'New Zealand', 'NZD', 'Dollar', '$'),
       (171, 'OM', 'OMN', 'Oman', 'OMR', 'Rial', '﷼'),
       (172, 'PA', 'PAN', 'Panama', 'PAB', 'Balboa', 'B/.'),
       (173, 'PE', 'PER', 'Peru', 'PEN', 'Sol', 'S/.'),
       (174, 'PF', 'PYF', 'French Polynesia', 'XPF', 'Franc', NULL),
       (175, 'PG', 'PNG', 'Papua New Guinea', 'PGK', 'Kina', NULL),
       (176, 'PH', 'PHL', 'Philippines', 'PHP', 'Peso', 'Php'),
       (177, 'PK', 'PAK', 'Pakistan', 'PKR', 'Rupee', '₨'),
       (178, 'PL', 'POL', 'Poland', 'PLN', 'Zloty', 'zł'),
       (179, 'PM', 'SPM', 'Saint Pierre and Miquelon', 'EUR', 'Euro', '€'),
       (180, 'PN', 'PCN', 'Pitcairn', 'NZD', 'Dollar', '$'),
       (181, 'PR', 'PRI', 'Puerto Rico', 'USD', 'Dollar', '$'),
       (182, 'PS', 'PSE', 'Palestinian Territory', 'ILS', 'Shekel', '₪'),
       (183, 'PT', 'PRT', 'Portugal', 'EUR', 'Euro', '€'),
       (184, 'PW', 'PLW', 'Palau', 'USD', 'Dollar', '$'),
       (185, 'PY', 'PRY', 'Paraguay', 'PYG', 'Guarani', 'Gs'),
       (186, 'QA', 'QAT', 'Qatar', 'QAR', 'Rial', '﷼'),
       (187, 'RE', 'REU', 'Reunion', 'EUR', 'Euro', '€'),
       (188, 'RO', 'ROU', 'Romania', 'RON', 'Leu', 'lei'),
       (189, 'RS', 'SRB', 'Serbia', 'RSD', 'Dinar', 'Дин'),
       (190, 'RU', 'RUS', 'Russia', 'RUB', 'Ruble', 'руб'),
       (191, 'RW', 'RWA', 'Rwanda', 'RWF', 'Franc', NULL),
       (192, 'SA', 'SAU', 'Saudi Arabia', 'SAR', 'Rial', '﷼'),
       (193, 'SB', 'SLB', 'Solomon Islands', 'SBD', 'Dollar', '$'),
       (194, 'SC', 'SYC', 'Seychelles', 'SCR', 'Rupee', '₨'),
       (195, 'SD', 'SDN', 'Sudan', 'SDD', 'Dinar', NULL),
       (196, 'SE', 'SWE', 'Sweden', 'SEK', 'Krona', 'kr'),
       (197, 'SG', 'SGP', 'Singapore', 'SGD', 'Dollar', '$'),
       (198, 'SH', 'SHN', 'Saint Helena', 'SHP', 'Pound', '£'),
       (199, 'SI', 'SVN', 'Slovenia', 'EUR', 'Euro', '€'),
       (200, 'SJ', 'SJM', 'Svalbard and Jan Mayen', 'NOK', 'Krone', 'kr'),
       (201, 'SK', 'SVK', 'Slovakia', 'SKK', 'Koruna', 'Sk'),
       (202, 'SL', 'SLE', 'Sierra Leone', 'SLL', 'Leone', 'Le'),
       (203, 'SM', 'SMR', 'San Marino', 'EUR', 'Euro', '€'),
       (204, 'SN', 'SEN', 'Senegal', 'XOF', 'Franc', NULL),
       (205, 'SO', 'SOM', 'Somalia', 'SOS', 'Shilling', 'S'),
       (206, 'SR', 'SUR', 'Suriname', 'SRD', 'Dollar', '$'),
       (207, 'ST', 'STP', 'Sao Tome and Principe', 'STD', 'Dobra', 'Db'),
       (208, 'SV', 'SLV', 'El Salvador', 'SVC', 'Colone', '$'),
       (209, 'SY', 'SYR', 'Syria', 'SYP', 'Pound', '£'),
       (210, 'SZ', 'SWZ', 'Swaziland', 'SZL', 'Lilangeni', NULL),
       (211, 'TC', 'TCA', 'Turks and Caicos Islands', 'USD', 'Dollar', '$'),
       (212, 'TD', 'TCD', 'Chad', 'XAF', 'Franc', NULL),
       (213, 'TF', 'ATF', 'French Southern Territories', 'EUR', 'Euro  ', '€'),
       (214, 'TG', 'TGO', 'Togo', 'XOF', 'Franc', NULL),
       (215, 'TH', 'THA', 'Thailand', 'THB', 'Baht', '฿'),
       (216, 'TJ', 'TJK', 'Tajikistan', 'TJS', 'Somoni', NULL),
       (217, 'TK', 'TKL', 'Tokelau', 'NZD', 'Dollar', '$'),
       (218, 'TL', 'TLS', 'East Timor', 'USD', 'Dollar', '$'),
       (219, 'TM', 'TKM', 'Turkmenistan', 'TMM', 'Manat', 'm'),
       (220, 'TN', 'TUN', 'Tunisia', 'TND', 'Dinar', NULL),
       (221, 'TO', 'TON', 'Tonga', 'TOP', 'Pa''anga', 'T$'),
       (222, 'TR', 'TUR', 'Turkey', 'TRY', 'Lira', 'YTL'),
       (223, 'TT', 'TTO', 'Trinidad and Tobago', 'TTD', 'Dollar', 'TT$'),
       (224, 'TV', 'TUV', 'Tuvalu', 'AUD', 'Dollar', '$'),
       (225, 'TW', 'TWN', 'Taiwan', 'TWD', 'Dollar', 'NT$'),
       (226, 'TZ', 'TZA', 'Tanzania', 'TZS', 'Shilling', NULL),
       (227, 'UA', 'UKR', 'Ukraine', 'UAH', 'Hryvnia', '₴'),
       (228, 'UG', 'UGA', 'Uganda', 'UGX', 'Shilling', NULL),
       (229, 'UM', 'UMI', 'United States Minor Outlying Islands', 'USD', 'Dollar ', '$'),
       (230, 'US', 'USA', 'United States', 'USD', 'Dollar', '$'),
       (231, 'UY', 'URY', 'Uruguay', 'UYU', 'Peso', '$U'),
       (232, 'UZ', 'UZB', 'Uzbekistan', 'UZS', 'Som', 'лв'),
       (233, 'VA', 'VAT', 'Vatican', 'EUR', 'Euro', '€'),
       (234, 'VC', 'VCT', 'Saint Vincent and the Grenadines', 'XCD', 'Dollar', '$'),
       (235, 'VE', 'VEN', 'Venezuela', 'VEF', 'Bolivar', 'Bs'),
       (236, 'VG', 'VGB', 'British Virgin Islands', 'USD', 'Dollar', '$'),
       (237, 'VI', 'VIR', 'U.S. Virgin Islands', 'USD', 'Dollar', '$'),
       (238, 'VN', 'VNM', 'Vietnam', 'VND', 'Dong', '₫'),
       (239, 'VU', 'VUT', 'Vanuatu', 'VUV', 'Vatu', 'Vt'),
       (240, 'WF', 'WLF', 'Wallis and Futuna', 'XPF', 'Franc', NULL),
       (241, 'WS', 'WSM', 'Samoa', 'WST', 'Tala', 'WS$'),
       (242, 'YE', 'YEM', 'Yemen', 'YER', 'Rial', '﷼'),
       (243, 'YT', 'MYT', 'Mayotte', 'EUR', 'Euro', '€'),
       (244, 'ZA', 'ZAF', 'South Africa', 'ZAR', 'Rand', 'R'),
       (245, 'ZM', 'ZMB', 'Zambia', 'ZMK', 'Kwacha', 'ZK'),
       (246, 'ZW', 'ZWE', 'Zimbabwe', 'ZWD', 'Dollar', 'Z$'),
       (247, 'CS', 'SCG', 'Serbia and Montenegro', 'RSD', 'Dinar', 'Дин');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'logging_log_enabled', 'yes',
        'Whether to enable logging or not. The /logs/ folder should have write permissions on it. (chmod 755 or 777)',
        '["yes","no"]', 'select', 'Logs');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'logging_log_type', 'Serious Errors Only',
        'The types of log messages to store in the log files. \'Serious Errors Only\' will log the important ones. Ensure logging is enabled for this setting to work.',
        '["Serious Errors Only","Serious Errors and Warnings","All Error Types"]', 'select', 'Logs');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'logging_log_output', 'no',
        'Whether to output serious errors to screen, if possible. Always set this to \'no\' for your live site.',
        '["yes","no"]', 'select', 'Logs');

ALTER TABLE `file_folder`
    ADD `parentId` INT( 11 ) NULL DEFAULT NULL AFTER `userId`;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'adverts_show_to_premium', 'hide', 'Whether to show or hide adverts to paid and admin users.',
        '["show","hide"]', 'select', 'Adverts');

ALTER TABLE `plugin`
    ADD `load_order` INT( 3 ) NULL DEFAULT 999;
ALTER TABLE `plugin`
    ADD INDEX ( `load_order` );

ALTER TABLE `payment_log`
    ADD INDEX (  `date_created` );
ALTER TABLE `payment_log`
    ADD INDEX (  `description` );
ALTER TABLE `payment_log`
    ADD INDEX (  `user_id` );

CREATE TABLE `file_report`
(
    `id`                           INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `file_id`                      INT(11) NOT NULL,
    `report_date`                  DATETIME     NOT NULL,
    `reported_by_name`             VARCHAR(150) NOT NULL,
    `reported_by_email`            VARCHAR(255) NOT NULL,
    `reported_by_address`          TEXT         NOT NULL,
    `reported_by_telephone_number` VARCHAR(30)  NOT NULL,
    `digital_signature`            VARCHAR(150) NOT NULL,
    `report_status`                ENUM('pending','cancelled','accepted') NOT NULL,
    `reported_by_ip`               VARCHAR(15)  NOT NULL,
    `other_information`            TEXT         NOT NULL,
    INDEX (`file_id`)
) ENGINE = MyISAM;

ALTER TABLE `language_content`
    ADD INDEX ( `languageKeyId` );
ALTER TABLE `language_content`
    ADD INDEX ( `languageId` );

ALTER TABLE `users`
    ADD `storageLimitOverride` BIGINT( 15 ) NULL DEFAULT NULL;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'free_user_maximum_storage', '0',
        'Maximum storage permitted for free users, in bytes. Use 0 (zero) for no limits.', '', 'integer',
        'Free User Settings');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'premium_user_maximum_storage', '0',
        'Maximum storage permitted for paid users, in bytes. Use 0 (zero) for no limits.', '', 'integer',
        'Premium User Settings');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_concurrent_uploads',
                                                   config_value,
                                                   'The maximum amount of files that can be uploaded at the same time for non-logged in users.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_concurrent_uploads');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_redirect_delay_seconds',
                                                   config_value,
                                                   'How long non users have to wait before being able to download (in seconds). Set to 0 to allow non users direct download access.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'redirect_delay_seconds');
UPDATE site_config
SET config_key         = 'free_user_redirect_delay_seconds',
    config_description = 'How long free users have to wait before being able to download (in seconds). Set to 0 to allow free users direct download access.'
WHERE config_key = 'redirect_delay_seconds';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_downloads_per_day',
                                                   config_value,
                                                   'The maximum files a non-logged in user can download in a 24 hour period. Set to 0 (zero) to disable. Note: Ensure the \' downloads_track_current_downloads\' is also set to \'yes\' to enable this.', availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_downloads_per_day');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'premium_user_max_downloads_per_day',
                                                   config_value,
                                                   'The maximum files a paid user can download in a 24 hour period. Set to 0 (zero) to disable. Note: Ensure the \' downloads_track_current_downloads\' is also set to \'yes\' to enable this.', availableValues,
                                                   config_type,
                                                   'Premium User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_downloads_per_day');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_download_filesize',
                                                   config_value,
                                                   'The maximum filesize a non-logged in user can download (in bytes). Set to 0 (zero) to ignore.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_download_filesize');
UPDATE site_config
SET config_description = 'The maximum filesize a free user can download (in bytes). Set to 0 (zero) to ignore.'
WHERE config_key = 'free_user_max_download_filesize';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_download_speed',
                                                   config_value,
                                                   'Maximum download speed for non-logged in users, in bytes per second. i.e. 50000. Use 0 for unlimited. ',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_download_speed');
UPDATE site_config
SET config_description = 'Maximum download speed for free users, in bytes per second. i.e. 50000. Use 0 for unlimited. '
WHERE config_key = 'free_user_max_download_speed';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_remote_urls',
                                                   config_value,
                                                   'The maximum remote urls a non-logged in user can specify at once.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_remote_urls');
UPDATE site_config
SET config_description = 'The maximum remote urls a free user can specify at once.'
WHERE config_key = 'free_user_max_remote_urls';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_max_upload_filesize',
                                                   config_value,
                                                   'The max upload filesize for non-logged in users (in bytes)',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_max_upload_filesize');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_show_captcha',
                                                   config_value,
                                                   'Show the captcha after a non-logged in user sees the countdown timer.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_show_captcha');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_upload_removal_days',
                                                   config_value,
                                                   'The amount of days after non-active files are removed for non account users. Leave blank for unlimited.',
                                                   availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_upload_removal_days');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`) (SELECT NULL,
                                                   'non_user_wait_between_downloads',
                                                   config_value,
                                                   'How long a non-logged in user must wait between downloads, in seconds. Set to 0 (zero) to disable. Note: Ensure the \' downloads_track_current_downloads\' is also set to \'yes\' to enable this.', availableValues,
                                                   config_type,
                                                   'Non User Settings'
                                            FROM site_config
                                            WHERE config_key = 'free_user_wait_between_downloads');

ALTER TABLE `sessions`
    ADD `user_id` INT NULL DEFAULT NULL , ADD INDEX ( `user_id` );
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'premium_user_block_account_sharing', 'no',
        'Block paid account sharing. Accounts will only allow 1 login session. Any open sessions will be closed on a new login.',
        '["yes","no"]', 'select', 'Premium User Settings');

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'language_separate_language_images', 'no',
        'Use different images/css for each language. If yes, copy all the files from images/styles in /themes/blue_v2/ to /themes/blue_v2/[flag_code]/, keeping the folders. Replace \'[flag_code]\' with the 2 letter language flag code. i.e. /themes/blue_v2/es/',
        '["yes","no"]', 'select', 'Language');

UPDATE `site_config`
SET `config_description` = 'Show translation value or key. (use "key" to debug translations, "translation" to show actual translated value. "key title text" to show the key as a title tag around the text content)',
    `availableValues`    = '["key","translation", "key title text"]'
WHERE `site_config`.`config_key` = 'language_show_key';

ALTER TABLE `users`
    ADD `languageId` INT( 11 ) NULL AFTER `lastname`;

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'language_user_select_language', 'no',
        'Give users the option to set their account language. Available as a drop-down in account settings. Automatically sets the language of the site on login.',
        '["yes","no"]', 'select', 'Language');
INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'maintenance_mode', 'no',
        'Whether to place the entire site into maintenance mode. Useful for site upgrades or server moves. Admin area is still accessible. Maintenance page content is in _maintenance_page.inc.php.',
        '["yes","no"]', 'select', 'Page Options');

ALTER TABLE `payment_log`
    ADD `payment_method` VARCHAR(50) NULL;

CREATE TABLE `user_level`
(
    `id`       INT( 5 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `level_id` INT( 5 ) NOT NULL,
    `label`    VARCHAR(20) NOT NULL
) ENGINE = MYISAM;

INSERT INTO `user_level` (`id`, `level_id`, `label`)
VALUES (NULL, '1', 'free user'),
       (NULL, '2', 'paid user');
INSERT INTO `user_level` (`id`, `level_id`, `label`)
VALUES (NULL, '10', 'moderator'),
       (NULL, '20', 'admin');
ALTER TABLE `users`
    ADD `level_id` INT( 5 ) NOT NULL DEFAULT '1' AFTER `level`;
UPDATE users
SET level_id = 1
WHERE level = 'free user';
UPDATE users
SET level_id = 2
WHERE level = 'paid user';
UPDATE users
SET level_id = 10
WHERE level = 'moderator';
UPDATE users
SET level_id = 20
WHERE level = 'admin';
ALTER TABLE `users` DROP `level`;

UPDATE `site_config`
SET `config_group` = 'Site Options'
WHERE `config_group` = 'Page Options';

INSERT INTO `site_config` (`id`, `config_key`, `config_value`, `config_description`, `availableValues`, `config_type`,
                           `config_group`)
VALUES (NULL, 'enable_user_registration', 'yes', 'Whether to enable user registration on the site.', '["yes","no"]',
        'select', 'Site Options');

UPDATE `site_config`
SET `config_description` = "Server selection method. How to select the file server to use. If using 'until full', you'll also need to set the file server priority on each.",
    `availableValues`    = '["Least Used Space","Specific Server","Until Full"]'
WHERE `site_config`.`config_key` = 'c_file_server_selection_method';
ALTER TABLE `file_server`
    ADD `priority` INT( 11 ) NOT NULL DEFAULT '0';
ALTER TABLE `file_server`
    ADD `maximumStorageBytes` BIGINT( 20 ) NOT NULL DEFAULT '0' AFTER `scriptPath`;
ALTER TABLE `file_server`
    ADD `totalSpaceUsed` FLOAT(18, 0 ) NOT NULL DEFAULT '0' AFTER `scriptPath`;

UPDATE `site_config`
SET `config_group` = 'Premium User Settings'
WHERE `config_key` = 'premium_user_max_remote_urls';
