DELETE
FROM background_task_log
WHERE task_id IN (SELECT id FROM background_task WHERE task = 'plugin_tasks.cron.php');
DELETE
FROM background_task
WHERE task = 'plugin_tasks.cron.php';

UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_delayed_redirect_top'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_delayed_redirect_bottom'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_site_footer'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_file_manager_view_file_top'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_file_manager_view_file_bottom'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_file_manager_view_file_right'
  AND config_group = 'Adverts';
UPDATE site_config
SET config_group = 'AdvertsBannerAds'
WHERE config_key = 'advert_file_manager_left_bar'
  AND config_group = 'Adverts';

ALTER TABLE `site_config`
    ADD `site_settings_hidden` int(1) NULL DEFAULT '0';
UPDATE site_config
SET site_settings_hidden = 1
WHERE config_group = 'AdvertsBannerAds';
UPDATE site_config
SET site_settings_hidden = 1
WHERE config_group = 'System';

ALTER TABLE `user_level`
    ADD `upload_url_slug` varchar(150) NOT NULL DEFAULT '' AFTER `max_storage_bytes`;
ALTER TABLE `user_level`
    ADD `download_url_slug` varchar(150) NOT NULL DEFAULT '' AFTER `max_download_filesize_allowed`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group)
VALUES ("Different Ads For Adult Content", "different_ads_for_adult_content", "0",
        "Whether to use different ads for files which contain adult content", "", "integer", "Adverts");
UPDATE site_config
SET site_settings_hidden = 1
WHERE config_group = 'Adverts';
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order)
VALUES ("Adult Content Keywords", "adult_content_keywords", "",
        "Optional. A list of words used to identify adult content. Matched against the uploaded filename and keywords. Pipe separated list. i.e. word1|word2|word3.",
        "", "textarea", "File Uploads", 1000);

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Adult Advert Delayed Redirect Top", "adult_advert_delayed_redirect_top", "",
        "Adult Only - Delayed redirect top advert (html)", "", "textarea", "AdvertsBannerAds", 5, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Adult Advert Delayed Redirect Bottom", "adult_advert_delayed_redirect_bottom", "",
        "Adult Only - Delayed redirect bottom advert (html)", "", "textarea", "AdvertsBannerAds", 6, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Adult Advert File Manager View File Top", "adult_advert_file_manager_view_file_top", "",
        "Adult Only - Advert shown on the view file page above the tabs.", "", "textarea", "AdvertsBannerAds", 12, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Adult Advert File Manager View File Bottom", "adult_advert_file_manager_view_file_bottom", "",
        "Adult Only - Advert shown on the view file page below the tabs.", "", "textarea", "AdvertsBannerAds", 13, 1);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Adult Advert File Manager View File Right", "adult_advert_file_manager_view_file_right", "",
        "Adult Only - Advert shown on the view file page on the right-hand side.", "", "textarea", "AdvertsBannerAds",
        14, 1);

ALTER TABLE `file_server`
    ADD `accountUploadTypes` varchar(1000) COLLATE 'utf8_general_ci' NULL;

ALTER TABLE `download_token`
    ADD `limit_by_ip` int(1) NOT NULL DEFAULT '0';

UPDATE `language_key`
SET `defaultContent` = 'File Privacy'
WHERE `languageKey` = 'default_privacy' LIMIT 1;
UPDATE `language_content`
SET content = 'File Privacy'
WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'default_privacy')
  AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

UPDATE `language_key`
SET `defaultContent` = 'All Files Private (access only via your account or by generating unique sharing urls)'
WHERE `languageKey` = 'settings_private_files' LIMIT 1;
UPDATE `language_content`
SET content = 'All Files Private (access only via your account or by generating unique sharing urls)'
WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'settings_private_files')
  AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

UPDATE `language_key`
SET `defaultContent` = 'Files Publicly Accessible (publicly shared by default, you can still create private folders with private files within)'
WHERE `languageKey` = 'settings_public_files' LIMIT 1;
UPDATE `language_content`
SET content = 'Files Publicly Accessible (publicly shared by default, you can still create private folders with private files within)'
WHERE languageKeyId IN (SELECT id FROM `language_key` WHERE `languageKey` = 'settings_public_files')
  AND languageId IN (SELECT id FROM language WHERE languageName = 'English (en)');

ALTER TABLE `users`
    ADD `never_expire` tinyint(1) NULL DEFAULT '0' AFTER `paidExpiryDate`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group)
VALUES ("Video Advert Type", "advert_video_ad_type", "", "Which type of adverts to use in videos.", "", "string",
        "Adverts");
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group)
VALUES ("Video Advert VAST Urls", "advert_video_ad_vast_urls", "",
        "The list of VAST urls to use on videos if the advert type is set to VAST.", "", "string", "Adverts");

ALTER TABLE `login_failure` CHANGE `ip_address` `ip_address` varchar (45) COLLATE 'utf8_general_ci' NOT NULL AFTER `id`;
ALTER TABLE `login_success` CHANGE `ip_address` `ip_address` varchar (45) COLLATE 'utf8_general_ci' NOT NULL AFTER `id`;

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Enable Chunked Uploads", "chunked_uploading_enabled", "yes",
        "Whether chunked uploading is enabled. Recommended to keep as 'yes'.", '["yes","no"]', "select", "File Uploads",
        101, 0);
INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Chunked Upload Size", "chunked_upload_size_mb", "100",
        "If 'Enable Chunked Uploads' is set to 'yes', this is the size of the chunks in MB. Recommended to use '100'.",
        '', "integer", "File Uploads", 102, 0);

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group)
VALUES ("Adult Video Advert VAST Urls", "adult_advert_video_ad_vast_urls", "",
        "Adult Only - The list of VAST urls to use on adult videos if the advert type is set to VAST.", "", "string",
        "Adverts");

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Advert 'Head' Tag Code", "advert_head_tag_code", "",
        "Optional. Code inserted into the <head> secton of every page.", "", "textarea", "AdvertsBannerAds", 99, 1);

UPDATE site_config
SET site_settings_hidden = 1
WHERE config_group = 'Adverts';

INSERT INTO site_config (label, config_key, config_value, config_description, availableValues, config_type,
                         config_group, display_order, site_settings_hidden)
VALUES ("Login Required To Upload", "upload_login_required", "no",
        "Whether a login is required for file uploads. This only applies in the UI if the theme supports non-login uploads. The 'Spirit' theme currently requires a login to upload. This option can also be used to block offsite uploads for non-login, such as desktop uploader apps.",
        '["yes","no"]', "select", "File Uploads", 103, 0);
