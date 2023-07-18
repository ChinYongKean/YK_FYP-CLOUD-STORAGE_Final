ALTER TABLE `file`
    ADD INDEX ( `userId` );
ALTER TABLE `file`
    ADD INDEX ( `uploadedDate` );

INSERT INTO `site_config`
VALUES (null, 'password_policy_min_length', '8', 'Minimum password length', '', 'integer', 'Password Policy');
INSERT INTO `site_config`
VALUES (null, 'password_policy_max_length', '32', 'Maximum password length', '', 'integer', 'Password Policy');
INSERT INTO `site_config`
VALUES (null, 'password_policy_min_uppercase_characters', '0', 'Minimum upper case characters (set to 0 to ignore)', '',
        'integer', 'Password Policy');
INSERT INTO `site_config`
VALUES (null, 'password_policy_min_numbers', '0', 'Minimum numbers (set to 0 to ignore)', '', 'integer',
        'Password Policy');
INSERT INTO `site_config`
VALUES (null, 'password_policy_min_nonalphanumeric_characters', '0',
        'Minimum nonalphanumeric characters, i.e. symbols (set to 0 to ignore)', '', 'integer', 'Password Policy');

