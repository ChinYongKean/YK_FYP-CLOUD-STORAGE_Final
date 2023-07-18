<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\AdminHelper;
use App\Helpers\ThemeHelper;
use App\Models\UserLevel;
use App\Helpers\UserActionLogHelper;

class AdvertisingController extends AdminBaseController
{

    public function advertisingSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // handle submissions
        if ($request->request->has('submitted')) {
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                // get params
                $configItem = $request->request->get('config_item');
                $changesForAudit = [];

                // loop $configItem and update DB
                foreach ($configItem AS $k => $value) {
                    if(in_array($k, ['advert_video_ad_vast_urls', 'adult_advert_video_ad_vast_urls'])) {
                        $value = str_replace(array("\n\r", "\r\n", "\r", "\n\n"), "\n", $value);
                        $value = str_replace(array("\n"), "|", $value);
                    }

                    // check if the value has changed
                    $oldValue = constant('SITE_CONFIG_' . strtoupper($k));
                    if($oldValue === $value) {
                        continue;
                    }

                    // update value
                    $db->query('UPDATE site_config '
                            . 'SET config_value = :configValue '
                            . 'WHERE config_key = :configKey '
                            . 'LIMIT 1', array(
                        'configValue' => $value,
                        'configKey' => $k,
                            )
                    );

                    $changesForAudit[$k] = [
                        'old' => $oldValue,
                        'new' => $value,
                    ];
                }

                // user action logs
                UserActionLogHelper::logAdmin('Site configuration changed', 'ADMIN', 'UPDATE', [
                    'data' => $changesForAudit,
                ]);

                // confirmation alert
                AdminHelper::setSuccess("Advertising settings updated.");
            }
        }

        // load editable config items for the current group
        $configItemElements = [];

        // load banner items
        $bannerConfigItems = $db->getRows("SELECT * "
                . "FROM site_config "
                . "WHERE config_group = 'AdvertsBannerAds' "
                . "ORDER BY display_order ASC, "
                . "config_description ASC"
        );

        if (is_array($bannerConfigItems) && count($bannerConfigItems)) {
            foreach ($bannerConfigItems AS $config) {
                // prep key for title text
                $titleText = 'SITE_CONFIG_' . strtoupper($config['config_key']);

                $colSize = 6;
                $cssClass = strpos($titleText, 'ADULT_') !== false?' adult-field':'';
                switch ($config['config_type']) {
                    case 'textarea':
                    default:
                        $elementHtml = '<textarea name="config_item[' . AdminHelper::makeSafe($config['config_key']) . ']" class="form-control'.$cssClass.'" title="' . $titleText . '" style="font-family: monospace; min-height: 120px;">' . AdminHelper::makeSafe($config['config_value']) . '</textarea>';
                        break;
                }

                $description = $config['config_description'];
                $description = str_replace('[[[WEB_ROOT]]]', WEB_ROOT, $description);

                $imageUrl = '';
                $thumbUrl = '';
                $imagePath = CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/advertising/screens/' . strtolower($config['config_key']) . '.jpg';
                if (file_exists($imagePath)) {
                    $imageUrl = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/advertising/screens/' . strtolower($config['config_key']) . '.jpg';
                    $thumbUrl = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/advertising/thumbs/' . strtolower($config['config_key']) . '.jpg';
                }

                $configItemElements[] = array(
                    'label' => $config['label'],
                    'elementHtml' => $elementHtml,
                    'colSize' => $colSize,
                    'description' => $description,
                    'image_url' => $imageUrl,
                    'thumb_url' => $thumbUrl,
                );
            }
        }
        
        // load other config items
        $configItem = [];
        $configDBItems = $db->getRows("SELECT * "
                . "FROM site_config "
                . "WHERE config_group = 'Adverts'"
        );
        foreach($configDBItems AS $configDBItem) {
            $value = $configDBItem['config_value'];
            if(in_array($configDBItem['config_key'], ['advert_video_ad_vast_urls', 'adult_advert_video_ad_vast_urls'])) {
                $value = str_replace("|", "\n", $value);
            }
            $configItem[$configDBItem['config_key']] = $value;
        }
        $theme = ThemeHelper::getLoadedInstance();
        
        // preload available values
        $adblockAvailableValues = $db->getValue('SELECT availableValues '
                . 'FROM site_config '
                . 'WHERE config_key = "adblock_limiter" '
                . 'LIMIT 1');
        $adblockLimiterDescription = $db->getValue('SELECT config_description '
                . 'FROM site_config '
                . 'WHERE config_key = "adblock_limiter" '
                . 'LIMIT 1');
        $adblockFilenameDescription = $db->getValue('SELECT config_description '
                . 'FROM site_config '
                . 'WHERE config_key = "adblock_filename" '
                . 'LIMIT 1');
        
        // load user levels which have adverts enabled
        $userLevels = UserLevel::loadByClause('show_site_adverts = 1');
        $userLevelLabels = [];
        if($userLevels) {
            foreach($userLevels AS $userLevel) {
                $userLevelLabels[] = ucwords($userLevel->label);
            }
        }

        // load template
        return $this->render('admin/advertising_settings.html', array(
                    'yesNoOptions' => array(0 => 'No', 1 => 'Yes'),
                    'configItemElements' => $configItemElements,
                    'videoAdTypeOptions' => array(
                        '' => 'Disabled',
                        'vast' => 'Vast Urls',
                        //'own' => 'Own Banner Ads, Popups and Pre/mid/post Roll Videos',
                    ),
                    'adblockAvailableValues' => json_decode($adblockAvailableValues, true),
                    'adblockLimiterDescription' => $adblockLimiterDescription,
                    'adblockFilenameDescription' => $adblockFilenameDescription,
                    'config_item' => $configItem,
                    'logoUrl' => $theme->getMainLogoUrl(),
                    'userLevelLabels' => $userLevelLabels,
        ));
    }

}
