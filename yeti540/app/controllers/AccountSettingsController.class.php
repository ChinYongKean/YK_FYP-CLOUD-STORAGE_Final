<?php

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\UserActionLogHelper;
use App\Models\FileFolder;
use App\Models\Language;
use App\Models\User;
use App\Helpers\CacheHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\InternalNotificationHelper;
use App\Helpers\LanguageHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\ValidationHelper;
use App\Services\Password;
use Spatie\Image\Image;

class AccountSettingsController extends AccountController
{

    public function edit() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();
        $db = Database::getDatabase();

        // don't allow non-user types (level_id = 0)
        if ($Auth->level_id === 0) {
            return $this->redirect(CoreHelper::getCoreSitePath() . '/account');
        }

        // load user for later
        $user = User::loadOneById($Auth->id);

        // dropdowns
        $watermarkPositionOptions = [];
        $watermarkPositionOptions['top left'] = 'Top-Left';
        $watermarkPositionOptions['top'] = 'Top-Middle';
        $watermarkPositionOptions['top right'] = 'Top-Right';
        $watermarkPositionOptions['right'] = 'Right';
        $watermarkPositionOptions['bottom right'] = 'Bottom-Right';
        $watermarkPositionOptions['bottom'] = 'Bottom-Middle';
        $watermarkPositionOptions['bottom left'] = 'Bottom-Left';
        $watermarkPositionOptions['left'] = 'Left';
        $watermarkPositionOptions['center'] = 'Middle';

        $statisticsOptions = [
            0 => TranslateHelper::t('settings_statistics_public', 'Publicly Accessible (using ~s on url)'),
            1 => TranslateHelper::t('settings_statistics_private', 'Private (only via your account)'),
        ];

        // pickup request for later
        $request = $this->getRequest();

        // update user
        if ($request->request->has('submitme')) {
            // validation
            $title = trim($request->request->get('title'));
            $firstname = trim($request->request->get('firstname'));
            $lastname = trim($request->request->get('lastname'));
            $emailAddress = trim(strtolower($request->request->get('emailAddress')));
            $password = trim($request->request->get('password'));
            $passwordConfirm = trim($request->request->get('passwordConfirm'));
            $languageId = null;
            if ($request->request->has('languageId')) {
                $languageId = (int) $request->request->get('languageId');
            }
            $privateFileStatistics = (int) $request->request->get('privateFileStatistics');
            $fileReferrerWhitelist = trim($request->request->get('fileReferrerWhitelist'));
            $isPublic = (int) $request->request->get('isPublic');
            $uploadedAvatar = null;
            if (isset($_FILES['avatar']) && strlen($_FILES['avatar']['tmp_name'])) {
                $uploadedAvatar = $_FILES['avatar'];
            }
            $uploadedWatermark = null;
            if (isset($_FILES['watermark']) && strlen($_FILES['watermark']['tmp_name'])) {
                $uploadedWatermark = $_FILES['watermark'];
            }

            $removeAvatar = false;
            if (($request->request->has('removeAvatar')) && ((int) $request->request->get('removeAvatar') == 1)) {
                $removeAvatar = true;
            }
            $removeWatermark = false;
            if (($request->request->has('removeWatermark')) && ((int) $request->request->get('removeWatermark') == 1)) {
                $removeWatermark = true;
            }
            $watermarkPosition = trim($request->request->get('watermarkPosition'));
            $watermarkPadding = (int) $request->request->get('watermarkPadding');

            // pickup api keys
            $key1 = $request->request->has('key1') ? trim($request->request->get('key1')) : null;
            $key2 = $request->request->has('key2') ? trim($request->request->get('key2')) : null;

            // handle marketing email subscription
            if (PluginHelper::pluginEnabled('newsletters')) {
                $marketingEmails = $request->request->has('marketingEmails') ? (int) $request->request->get('marketingEmails') : 0;
            }

            if (!strlen($firstname)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_firstname", "Please enter your firstname"));
            }
            elseif (!strlen($lastname)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_lastname", "Please enter your lastname"));
            }
            elseif (!strlen($emailAddress)) {
                NotificationHelper::setError(TranslateHelper::t("please_enter_your_email_address", "Please enter your email address"));
            }
            elseif (!ValidationHelper::validEmail($emailAddress)) {
                NotificationHelper::setError(TranslateHelper::t("your_email_address_is_invalid", "Your email address is invalid"));
            }
            elseif ($this->inDemoMode()) {
                NotificationHelper::setError(TranslateHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $checkEmail = User::loadOne('email', $emailAddress);
                if (($checkEmail) && ($checkEmail->id != $Auth->id)) {
                    // username exists
                    NotificationHelper::setError(TranslateHelper::t("email_address_already_exists", "Email address already exists on another account"));
                }
                else {
                    // check password if one set
                    if (strlen($password)) {
                        if ($password != $passwordConfirm) {
                            NotificationHelper::setError(TranslateHelper::t("your_password_confirmation_does_not_match", "Your password confirmation does not match"));
                        }
                        else {
                            $passValid = UserHelper::validatePassword($password);
                            if (is_array($passValid)) {
                                NotificationHelper::setError(implode('<br/>', $passValid));
                            }
                        }
                    }
                }
            }

            if (!NotificationHelper::isErrors()) {
                if ($uploadedAvatar) {
                    // check filesize
                    $maxAvatarSize = 1024 * 1024 * 5;
                    if ($uploadedAvatar['size'] > $maxAvatarSize) {
                        NotificationHelper::setError(TranslateHelper::t("account_edit_avatar_is_too_large", "The uploaded image can not be more than [[[MAX_SIZE_FORMATTED]]]", ['MAX_SIZE_FORMATTED' => CoreHelper::formatSize($maxAvatarSize)]));
                    }
                    else {
                        // make sure it's an image
                        $imagesizedata = @getimagesize($uploadedAvatar['tmp_name']);
                        if ($imagesizedata === FALSE) {
                            //not image
                            NotificationHelper::setError(TranslateHelper::t("account_edit_avatar_is_not_an_image", "Your avatar must be a jpg, png or gif image."));
                        }
                    }
                }
            }

            if (!NotificationHelper::isErrors()) {
                if ($uploadedWatermark) {
                    // check filesize
                    $maxWatermarkSize = 1024 * 1024 * 5;
                    if ($uploadedWatermark['size'] > $maxWatermarkSize) {
                        NotificationHelper::setError(TranslateHelper::t("account_edit_watermark_is_too_large", "The uploaded watermark can not be more than [[[MAX_SIZE_FORMATTED]]]", ['MAX_SIZE_FORMATTED' => CoreHelper::formatSize($maxWatermarkSize)]));
                    }
                    else {
                        // make sure it's a png image
                        $imgInfo = getimagesize($uploadedWatermark['tmp_name']);
                        if ($imgInfo[2] != IMAGETYPE_PNG) {
                            // not image
                            NotificationHelper::setError(TranslateHelper::t("account_edit_watermark_is_not_a_png", "Your watermark must be a png image."));
                        }
                    }
                }
            }

            if (!NotificationHelper::isErrors()) {
                if (strlen($key1) || strlen($key2)) {
                    // make sure keys are 64 characters in length
                    if ((strlen($key1) != 64) || (strlen($key2) != 64)) {
                        NotificationHelper::setError(TranslateHelper::t("account_api_keys_not_correct_length", "API keys should be 64 characters in length."));
                    }
                }
            }

            if (!NotificationHelper::isErrors()) {
                // update referring whitelist domains
                $standardisedReferrers = [];
                if (strlen($fileReferrerWhitelist)) {
                    // catch any set as new lines
                    $fileReferrerWhitelist = preg_replace('/\r\n|\r|\n/', ',', $fileReferrerWhitelist);

                    // normalise data
                    $fileReferrerWhitelistParts = explode(',', $fileReferrerWhitelist);
                    if (count($fileReferrerWhitelistParts)) {
                        foreach ($fileReferrerWhitelistParts AS $fileReferrerWhitelistPart) {
                            if (strlen(trim($fileReferrerWhitelistPart)) === 0) {
                                continue;
                            }

                            // normalise
                            $fileReferrerWhitelistPart = strtolower($fileReferrerWhitelistPart);
                            $fileReferrerWhitelistPart = str_replace(' ', '', $fileReferrerWhitelistPart);

                            // only keep hosts
                            $fileReferrerWhitelistPart = parse_url('http://' . $fileReferrerWhitelistPart, PHP_URL_HOST);
                            if (strlen(trim($fileReferrerWhitelistPart)) === 0) {
                                continue;
                            }

                            $standardisedReferrers[] = $fileReferrerWhitelistPart;
                        }
                    }
                }

                // update the original string with the standardised data
                $fileReferrerWhitelist = implode(',', $standardisedReferrers);
            }

            // update the account
            if (!NotificationHelper::isErrors()) {
                // if password changed send confirmation notice to user
                if (SITE_CONFIG_SECURITY_SEND_USER_EMAIL_ON_PASSWORD_CHANGE == 'yes') {
                    if (strlen($password)) {
                        $subject = TranslateHelper::t('password_change_email_subject', 'Password changed for account on [[[SITE_NAME]]]', ['SITE_NAME' => SITE_CONFIG_SITE_NAME]);

                        $replacements = [
                            'FIRST_NAME' => $user->firstname,
                            'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                            'WEB_ROOT' => ThemeHelper::getLoadedInstance()->getAccountWebRoot(),
                            'USERNAME' => $user->username,
                        ];
                        $defaultContent = "Dear [[[FIRST_NAME]]],<br/><br/>";
                        $defaultContent .= "This is a courtesy email notifying you that your account password on [[[SITE_NAME]]] has been changed.<br/><br/>";
                        $defaultContent .= "If you didn't change your password, please contact us immediately. Otherwise just ignore this email.<br/><br/>";
                        $defaultContent .= "<strong>Url:</strong> <a href='[[[WEB_ROOT]]]'>[[[WEB_ROOT]]]</a><br/>";
                        $defaultContent .= "<strong>Username:</strong> [[[USERNAME]]]<br/><br/>";
                        $defaultContent .= "Feel free to contact us if you need any support with your account.<br/><br/>";
                        $defaultContent .= "Regards,<br/>";
                        $defaultContent .= "[[[SITE_NAME]]] Admin";
                        $htmlMsg = TranslateHelper::t('password_change_email_content', $defaultContent, $replacements);

                        CoreHelper::sendHtmlEmail($user->email, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));
                    }
                }

                // if email changed send confirmation notice to user
                if (SITE_CONFIG_SECURITY_SEND_USER_EMAIL_ON_EMAIL_CHANGE == 'yes') {
                    if ($emailAddress != $user->email) {
                        $subject = TranslateHelper::t('email_change_email_subject', 'Email changed for account on [[[SITE_NAME]]]', ['SITE_NAME' => SITE_CONFIG_SITE_NAME]);

                        $replacements = [
                            'FIRST_NAME' => $user->firstname,
                            'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                            'WEB_ROOT' => ThemeHelper::getLoadedInstance()->getAccountWebRoot(),
                            'USERNAME' => $user->username,
                            'NEW_EMAIL' => $emailAddress,
                        ];
                        $defaultContent = "Dear [[[FIRST_NAME]]],<br/><br/>";
                        $defaultContent .= "This is a courtesy email notifying you that your account email address on [[[SITE_NAME]]] has been changed to [[[NEW_EMAIL]]].<br/><br/>";
                        $defaultContent .= "If you didn't change your email address, please contact us immediately. Otherwise just ignore this email.<br/><br/>";
                        $defaultContent .= "<strong>Url:</strong> <a href='[[[WEB_ROOT]]]'>[[[WEB_ROOT]]]</a><br/>";
                        $defaultContent .= "<strong>Username:</strong> [[[USERNAME]]]<br/>";
                        $defaultContent .= "<strong>New Email:</strong> [[[NEW_EMAIL]]]<br/><br/>";
                        $defaultContent .= "Feel free to contact us if you need any support with your account.<br/><br/>";
                        $defaultContent .= "Regards,<br/>";
                        $defaultContent .= "[[[SITE_NAME]]] Admin";
                        $htmlMsg = TranslateHelper::t('email_change_email_content', $defaultContent, $replacements);

                        CoreHelper::sendHtmlEmail($user->email, $subject, $htmlMsg, SITE_CONFIG_DEFAULT_EMAIL_ADDRESS_FROM, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));
                    }
                }

                // update our user object
                $oldUser = (array) $user;
                $user->title = $title;
                $user->firstname = $firstname;
                $user->lastname = $lastname;
                $user->email = $emailAddress;
                $user->languageId = $languageId;
                $user->privateFileStatistics = $privateFileStatistics;
                $user->isPublic = $isPublic;
                if (strlen($password)) {
                    $user->password = Password::createHash($password);
                }
                $user->fileReferrerWhitelist = $fileReferrerWhitelist;
                $user->save();

                // user action logs
                $changes = UserActionLogHelper::getChangedData($oldUser, $user);
                if(!empty($changes)) {
                    UserActionLogHelper::log('Edited account', 'ACCOUNT', 'UPDATE', [
                        'user_id' => $user->id,
                        'data' => $changes,
                    ]);
                }

                // reset site language in session if updated
                if ($languageId != null) {
                    $language = Language::loadOneById($languageId);
                    if ($language) {
                        $_SESSION['_t'] = $language->languageName;
                    }
                }

                // handle avatar
                $avatarCachePath = 'user/' . (int) $user->id . '/profile';

                // delete any existing avatar files including generate cache
                if ($removeAvatar || $uploadedAvatar) {
                    if (file_exists(CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath)) {
                        $files = CoreHelper::getDirectoryListing(CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath);
                        if (count($files)) {
                            foreach ($files AS $file) {
                                @unlink($file);
                            }
                        }

                        // user action logs
                        UserActionLogHelper::log('Removed avatar', 'ACCOUNT', 'DELETE', [
                            'user_id' => $user->id,
                        ]);
                    }

                    // save new avatar
                    if ($uploadedAvatar) {
                        CoreHelper::checkCreateDirectory(CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath);
                        Image::load($uploadedAvatar['tmp_name'])
                                ->save(CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/avatar_original.png');

                        // user action logs
                        UserActionLogHelper::log('Added avatar', 'ACCOUNT', 'ADD', [
                            'user_id' => $user->id,
                        ]);
                    }

                    // track whether to clear any image cache or now
                    $clearImageCache = false;
                }

                // save watermark image
                if ($removeWatermark || $uploadedWatermark) {
                    $watermarkCachePath = 'user/' . (int) $user->id . '/watermark';

                    // delete any existing avatar files including generate cache
                    if (file_exists(CACHE_DIRECTORY_ROOT . '/' . $watermarkCachePath)) {
                        $files = CoreHelper::getDirectoryListing(CACHE_DIRECTORY_ROOT . '/' . $watermarkCachePath);
                        if (count($files)) {
                            foreach ($files AS $file) {
                                @unlink($file);
                            }
                        }

                        // user action logs
                        UserActionLogHelper::log('Removed watermark', 'ACCOUNT', 'DELETE', [
                            'user_id' => $user->id,
                        ]);
                    }

                    // save new file
                    if ($uploadedWatermark) {
                        CoreHelper::checkCreateDirectory(CACHE_DIRECTORY_ROOT . '/' . $watermarkCachePath);
                        Image::load($uploadedWatermark['tmp_name'])
                                ->save(CACHE_DIRECTORY_ROOT . '/' . $watermarkCachePath . '/watermark_original.png');

                        // user action logs
                        UserActionLogHelper::log('Added watermark', 'ACCOUNT', 'ADD', [
                            'user_id' => $user->id,
                        ]);
                    }

                    // clear image cache
                    $clearImageCache = true;
                }

                // if there's any changes to the watermarking, clear the cache
                if (($watermarkPosition != $user->getProfileValue('watermarkPosition')) || ($watermarkPadding != $user->getProfileValue('watermarkPadding'))) {
                    // clear image cache
                    $clearImageCache = true;
                }

                // setup for profile data
                $profile = [];
                $profile['watermarkPosition'] = $watermarkPosition;
                $profile['watermarkPadding'] = $watermarkPadding;

                // update any profile information, this is used for fields which may not be part of the core script, so theme specific
                $user->storeProfileData($profile);

                // clear any image cache if we need to
                if ($clearImageCache == true) {
                    $folders = FileFolder::loadByClause('userId = :userId', [
                                'userId' => $user->id,
                    ]);
                    if ($folders) {
                        $pluginObj = PluginHelper::getInstance('filepreviewer');
                        foreach ($folders AS $folder) {
                            $files = FileHelper::loadAllActiveByFolderId($folder->id);
                            if ($files) {
                                foreach ($files AS $file) {
                                    $pluginObj->deleteImagePreviewCache($file->id);
                                }
                            }
                        }
                    }
                }

                // update api keys
                if ($key1 !== null && $key2 !== null) {
                    $keepSame = $db->getValue('SELECT COUNT(id) AS total '
                            . 'FROM apiv2_api_key '
                            . 'WHERE key_public = :key_public '
                            . 'AND key_secret = :key_secret '
                            . 'AND user_id = :user_id '
                            . 'LIMIT 1', [
                        'user_id' => (int) $Auth->id,
                        'key_public' => $key1,
                        'key_secret' => $key2,
                    ]);
                    if (!$keepSame) {
                        // delete any existing keys for the user
                        $db->query('DELETE FROM apiv2_api_key '
                                . 'WHERE user_id = :user_id '
                                . 'LIMIT 1', [
                            'user_id' => (int) $Auth->id,
                        ]);

                        // add the new keys
                        if (strlen($key1) && strlen($key2)) {
                            $db->query('INSERT INTO apiv2_api_key (key_public, key_secret, user_id, date_created) VALUES (:key_public, :key_secret, :user_id, NOW())', [
                                'user_id' => (int) $Auth->id,
                                'key_public' => $key1,
                                'key_secret' => $key2,
                            ]);

                            // user action logs
                            UserActionLogHelper::log('Generated API keys', 'ACCOUNT', 'ADD', [
                                'user_id' => $user->id,
                                'data' => [
                                    'key_public' => $key1,
                                    'key_secret' => '[HIDDEN]',
                                ],
                            ]);
                        }
                    }
                }

                // update newsletter state
                if (PluginHelper::pluginEnabled('newsletters')) {
                    // get existing
                    $isUnsubscribed = $db->getValue('SELECT id FROM plugin_newsletter_unsubscribe WHERE user_id = :user_id', [
                        'user_id' => (int) $Auth->id,
                    ]);

                    // clear existing setting
                    if ($marketingEmails === 1 && $isUnsubscribed !== false) {
                        $db->query('DELETE FROM plugin_newsletter_unsubscribe '
                            .'WHERE user_id = :user_id '
                            .'LIMIT 1', [
                            'user_id' => (int)$Auth->id,
                        ]);

                        // user action logs
                        UserActionLogHelper::log('Subscribed to newsletter', 'PLUGIN', 'REMOVE', [
                            'plugin' => 'newsletters',
                            'user_id' => (int) $Auth->id,
                        ]);
                    }

                    // unsubscribe
                    if ($marketingEmails === 0 && $isUnsubscribed === false) {
                        $db->query('INSERT INTO plugin_newsletter_unsubscribe (user_id, date_unsubscribed) '
                                . 'VALUES (:user_id, NOW())', [
                            'user_id' => (int) $Auth->id,
                        ]);

                        // user action logs
                        UserActionLogHelper::log('Unsubscribed from newsletter', 'PLUGIN', 'UPDATE', [
                            'plugin' => 'newsletters',
                            'user_id' => (int) $Auth->id,
                        ]);
                    }
                }

                // make sure our user object on Auth is updated
                $Auth->user = $user;

                // message
                NotificationHelper::setSuccess(TranslateHelper::t("account_updated_success_message", "Account details successfully updated"));
            }
        }
        else {
            $title = $user->title;
            $firstname = $user->firstname;
            $lastname = $user->lastname;
            $emailAddress = $user->email;
            $languageId = $user->languageId;
            $isPublic = (int) $user->isPublic;
            $privateFileStatistics = $user->privateFileStatistics;
            $fileReferrerWhitelist = $user->fileReferrerWhitelist;

            // load any profile info
            $watermarkPosition = $user->getProfileValue('watermarkPosition') ? $user->getProfileValue('watermarkPosition') : 'bottom-right';
            $watermarkPadding = (int) $user->getProfileValue('watermarkPadding') ? $user->getProfileValue('watermarkPadding') : 10;

            // setup keys
            $key1 = '';
            $key2 = '';
            $accountAPIKeys = $db->getRow('SELECT key_public, key_secret '
                    . 'FROM apiv2_api_key '
                    . 'WHERE user_id = :user_id '
                    . 'LIMIT 1', [
                'user_id' => $Auth->id,
                ]
            );
            if ($accountAPIKeys) {
                $key1 = $accountAPIKeys['key_public'];
                $key2 = $accountAPIKeys['key_secret'];
            }

            // get newsletter state
            $marketingEmails = 0;
            if (PluginHelper::pluginEnabled('newsletters')) {
                $marketingEmails = (int) !$db->getValue("SELECT COUNT(*) AS total "
                                . "FROM plugin_newsletter_unsubscribe "
                                . "WHERE user_id = :user_id "
                                . "LIMIT 1", [
                            'user_id' => $user->id,
                ]);
            }
        }

        // load params for template
        $totalActiveFileSize = $user->getTotalActiveFileSize();
        $totalFileStorage = UserHelper::getMaxFileStorage($user->id);

        // get percentage used
        $storagePercentage = 1;
        if ($totalFileStorage > 0) {
            $storagePercentage = ($totalActiveFileSize / $totalFileStorage) * 100;
            if ($storagePercentage < 1) {
                $storagePercentage = 1;
            }
            else {
                $storagePercentage = floor($storagePercentage);
            }
        }

        // load totals for later
        $totalFreeSpace = $user->getAvailableStorage();

        // check for existing avatar
        $hasAvatar = false;
        $avatarCachePath = 'user/' . (int) $Auth->id . '/profile/avatar_original.png';
        if (CacheHelper::checkCacheFileExists($avatarCachePath)) {
            $hasAvatar = true;
        }

        // check for existing profile image
        $hasWatermark = false;
        $watermarkCachePath = 'user/' . (int) $Auth->id . '/watermark/watermark_original.png';
        $watermarkCacheUrl = CACHE_WEB_ROOT . '/' . $watermarkCachePath;
        if (CacheHelper::checkCacheFileExists($watermarkCachePath)) {
            $hasWatermark = true;
        }

        // load current user type
        $accountType = $db->getValue('SELECT level_type '
                . 'FROM user_level '
                . 'WHERE id = :id '
                . 'LIMIT 1', [
            'id' => $Auth->level_id,
        ]);

        // get user account paid details
        $user = User::loadOneById($Auth->id);
        $accountExpiry = (in_array($accountType, ['admin', 'moderator']) || ($accountType === 'paid' && (int) $user->never_expire === 1)) ? ucwords(TranslateHelper::t('never', 'never')) : CoreHelper::formatDate($user->paidExpiryDate);
        $enabled2FA = (int) $user->login_2fa_enabled;

        // prep params for template
        $templateParams = $this->getFileManagerTemplateParams();
        $templateParams = array_merge([
            'watermarkPositionOptions' => $watermarkPositionOptions,
            'avatarUrl' => $user->getAvatarUrl(),
            'watermarkUrl' => $user->getImageWatermarkUrl(),
            'totalFreeSpace' => $totalFreeSpace,
            'totalFreeSpaceExt' => CoreHelper::formatSize($totalFreeSpace, 'ext'),
            'totalFreeSpaceSize' => CoreHelper::formatSize($totalFreeSpace, 'size'),
            'totalActiveFileSize' => $totalActiveFileSize,
            'totalActiveFileSizeExt' => CoreHelper::formatSize($totalActiveFileSize, 'ext'),
            'totalActiveFileSizeSize' => CoreHelper::formatSize($totalActiveFileSize, 'size'),
            'totalActiveFileSizeBoth' => CoreHelper::formatSize($totalActiveFileSize, 'both'),
            'totalActiveFiles' => $user->getTotalActiveFiles(),
            'totalTrash' => $user->getTotalTrashFiles(),
            'totalDownloads' => $user->getTotalDownloadCountAllFiles(),
            'totalFileStorage' => $totalFileStorage,
            'totalFileStorageBoth' => CoreHelper::formatSize($totalFileStorage, 'both'),
            'storagePercentage' => $storagePercentage,
            'packageId' => $user->level_id,
            't' => $request->request->has('t') ? $request->request->get('t') : '',
            'title' => $title,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'emailAddress' => $emailAddress,
            'languageId' => $languageId,
            'isPublic' => $isPublic,
            'fileReferrerWhitelist' => $fileReferrerWhitelist,
            'enabled2FA' => $enabled2FA,
            'key1' => $key1,
            'key2' => $key2,
            'privateFileStatistics' => $privateFileStatistics,
            'watermarkPosition' => $watermarkPosition,
            'watermarkPadding' => $watermarkPadding,
            'activeLanguages' => LanguageHelper::getActiveLanguages(),
            'hasAvatar' => $hasAvatar,
            'hasWatermark' => $hasWatermark,
            'watermarkCacheUrl' => $watermarkCacheUrl,
            'accountType' => $accountType,
            'accountExpiry' => $accountExpiry,
            'accountTypeLabel' => TranslateHelper::t('account_type_' . str_replace(' ', '_', $Auth->level), ucwords($Auth->level)),
            'statisticsOptions' => $statisticsOptions,
            'marketingEmails' => $marketingEmails,
        ], $templateParams);

        // load template
        return $this->render('account/account_edit.html', $templateParams);
    }

    public function viewAccountAvatar($userId, $width, $height) {
        if (($width == 0) || ($height == 0)) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/avatar_default.png');
        }

        // block memory issues
        if (($width > 500) || ($height > 500)) {
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/avatar_default.png');
        }

        // setup paths
        $avatarCachePath = 'user/' . (int) $userId . '/profile';
        $avatarCacheFilename = md5((int) $userId . $width . $height . 'square') . '.png';
        $originalFilename = 'avatar_original.png';

        // check if user has cached avatar
        if ($fileContent = CacheHelper::checkCacheFileExists($avatarCachePath . '/' . $avatarCacheFilename)) {
            return $this->redirect(CACHE_WEB_ROOT . '/' . $avatarCachePath . '/' . $avatarCacheFilename) . '?' . CacheHelper::getCacheModifiedTimestamp($avatarCachePath . '/' . $avatarCacheFilename);
        }

        // do plugin includes, i.e. override avatar with social login one
        $params = PluginHelper::callHook('accountAvatar', ['photoURL' => null]);
        if (strlen($params['photoURL'])) {
            // get contents
            $photoContents = CoreHelper::getRemoteUrlContent($params['photoURL']);
            if (strlen($photoContents)) {
                // figure out file type
                switch (strtolower(substr($params['photoURL'], strlen($params['photoURL']) - 3, 3))) {
                    case 'png':
                        $originalFilename = 'avatar_original.png';
                        break;
                    case 'gif':
                        $originalFilename = 'avatar_original.gif';
                        break;
                }
                // save temp copy locally
                CacheHelper::saveCacheToFile($avatarCachePath . '/' . $originalFilename, $photoContents);
            }
        }

        // check for original avatar image
        if (!CacheHelper::getCacheFromFile($avatarCachePath . '/' . $originalFilename)) {
            // no avatar uploaded, output default icon
            return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/avatar_default.png?r=' . time());
        }

        // make sure we have either an original png or jpg file
        $avatarOriginal = CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/' . $originalFilename;
        if (!file_exists($avatarOriginal)) {
            $avatarOriginal = CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/avatar_original.jpg';
            if (!file_exists($avatarOriginal)) {
                // avatar file not found, output default icon
                return $this->redirect(ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/avatar_default.png?r=' . time());
            }
        }

        // resize image to square thumbnail
        Image::load($avatarOriginal)
                ->width($width)
                ->height($height)
                ->save(CACHE_DIRECTORY_ROOT . '/' . $avatarCachePath . '/' . $avatarCacheFilename);

        // output image
        return $this->redirect(CACHE_WEB_ROOT . '/' . $avatarCachePath . '/' . $avatarCacheFilename . '?r=' . time());
    }

    public function ajaxUpdateViewType() {
        if (!isset($_SESSION['browse']['viewType'])) {
            $_SESSION['browse']['viewType'] = 'fileManagerIcon';
            if (SITE_CONFIG_FILE_MANAGER_DEFAULT_VIEW == 'list') {
                $_SESSION['browse']['viewType'] = 'fileManagerList';
            }
        }

        // update view in session
        $viewType = trim($_REQUEST['viewType']);
        if (in_array($viewType, ['fileManagerIcon', 'fileManagerList'])) {
            $_SESSION['browse']['viewType'] = $viewType;
        }

        $result['error'] = false;

        return $this->renderJson($result);
    }

    public function ajaxInternalNotificationMarkAllRead() {
        // require user login
        if (($response = $this->requireLogin()) !== false) {
            return $response;
        }

        // get params for later
        $Auth = $this->getAuth();

        // prepare result
        $rs = [];
        $rs['error'] = false;
        $rs['msg'] = 'Marked read.';

        InternalNotificationHelper::markAllReadByUserId($Auth->id);

        // user action logs
        UserActionLogHelper::log('Set all notifications as read', 'ACCOUNT', 'UPDATE');

        return $this->renderJson($rs);
    }

}
