<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\AdminHelper;
use App\Helpers\CacheHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\UserActionLogHelper;
use PclZip;

class ThemeController extends AdminBaseController
{

    public function themeManage()
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // import any new themes as uninstalled
        ThemeHelper::clearCachedThemeSettings();
        ThemeHelper::registerThemes();

        // update theme config cache
        ThemeHelper::loadThemeConfigurationFiles(true);

        if ($request->query->has('activate')) {
            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }

            if (!AdminHelper::isErrors()) {
                $folderName = trim($request->query->get('activate'));

                // double-check the folder exists
                $themeExists = (int)$db->getValue('SELECT COUNT(id) AS total '
                    .'FROM theme '
                    .'WHERE folder_name = '.$db->quote($folderName));
                if ($themeExists) {
                    // activate theme
                    $db->query('UPDATE theme '
                        .'SET is_installed = 0 '
                        .'WHERE is_installed = 1');
                    $db->query('UPDATE theme '
                        .'SET is_installed = 1 '
                        .'WHERE folder_name = '.$db->quote($folderName));
                    $db->query('UPDATE site_config '
                        .'SET config_value = '.$db->quote($folderName).' '
                        .'WHERE config_key = \'site_theme\' '
                        .'LIMIT 1');

                    // user action logs
                    UserActionLogHelper::logAdmin('Theme activated', 'ADMIN', 'UPDATE', [
                        'data' => [
                            'folder_name' => $folderName,
                        ],
                    ]);

                    // do a redirect to refresh the admin area changes for the theme
                    return $this->redirect(ADMIN_WEB_ROOT.'/theme_manage?st='.urlencode($folderName));
                } else {
                    AdminHelper::setError('Can not find theme to set active.');
                }
            }
        }

        if ($request->query->has('delete')) {
            $delete = trim($request->query->get('delete'));
            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            } elseif (strlen($delete) == 0) {
                AdminHelper::setError('Can not find a theme to delete.');
            }

            if (!AdminHelper::isErrors()) {
                $themeDetails = $db->getRow("SELECT * "
                    ."FROM theme "
                    ."WHERE folder_name = '".$db->escape($delete)."' "
                    ."AND is_installed = '0' "
                    ."LIMIT 1");
                if (!$themeDetails) {
                    AdminHelper::setError('Could not get the theme details, please try again.');
                } else {
                    $themePath = SITE_THEME_DIRECTORY_ROOT.$themeDetails['folder_name'];
                    if (file_exists($themePath)) {
                        if (AdminHelper::recursiveThemeDelete($themePath) == false) {
                            AdminHelper::setError('Could not delete some files, please delete them manually.');
                        }
                    }
                    if (file_exists($themePath)) {
                        if (!rmdir($themePath)) {
                            AdminHelper::setError('Could not delete some files, please delete them manually.');
                        }
                    }
                }
                if (AdminHelper::isErrors() === false) {
                    $db->query("DELETE FROM theme "
                        ."WHERE folder_name = '".$themeDetails['folder_name']."'");

                    // user action logs
                    UserActionLogHelper::logAdmin('Theme deleted', 'ADMIN', 'DELETE', [
                        'data' => [
                            'folder_name' => $themeDetails['folder_name'],
                        ],
                    ]);

                    return $this->redirect(ADMIN_WEB_ROOT.'/theme_manage?de=1');
                }
            }
        }

        // error/success messages
        if ($request->query->has('sa')) {
            AdminHelper::setSuccess('Theme successfully added. Activate it below.');
        } elseif ($request->query->has('de')) {
            AdminHelper::setSuccess('Theme successfully deleted.');
        } elseif ($request->query->has('st')) {
            AdminHelper::setSuccess('Theme successfully set to '.AdminHelper::makeSafe($request->query->get('st')));
        } elseif ($request->query->has('error')) {
            AdminHelper::setError(urldecode($request->query->get('error')));
        }

        // clear route cache
        CacheHelper::removeRouteCache();

        // load current theme from config, can not use the SITE_CONFIG_SITE_THEME constant encase it's been changed
        $siteTheme = $db->getValue('SELECT config_value '
            .'FROM site_config '
            .'WHERE config_key = \'site_theme\' '
            .'LIMIT 1');

        // load all themes
        $sQL = "SELECT * "
            ."FROM theme "
            ."ORDER BY FIELD(folder_name, :folder_name) DESC, theme_name ASC";
        $limitedRS = $db->getRows($sQL, [
            'folder_name' => $siteTheme,
        ]);
        foreach ($limitedRS as $k => $row) {
            // set the theme settings path
            $row['settingsPath'] = ADMIN_WEB_ROOT.'/theme_settings/'.$row['folder_name'];
            $limitedRS[$k] = $row;
        }

        // load template
        return $this->render('admin/theme_manage.html', [
            'siteTheme' => $siteTheme,
            'limitedRS' => $limitedRS,
            'currentProductUrl' => ThemeHelper::getCurrentProductUrl(),
        ]);
    }

    public function themeManageAdd()
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // check for write permissions on the themes folder
        if (!is_writable(SITE_THEME_DIRECTORY_ROOT)) {
            AdminHelper::setError(AdminHelper::t("error_theme_folder_is_not_writable",
                "Theme folder is not writable. Ensure you set the following folder to CHMOD 755 or 777: [[[THEME_FOLDER]]]",
                ['THEME_FOLDER' => SITE_THEME_DIRECTORY_ROOT]));
        }

        // handle page submissions
        if ($request->request->has('submitted')) {
            // delete existing tmp folder
            $tmpPath = SITE_THEME_DIRECTORY_ROOT.'_tmp';
            if (file_exists($tmpPath)) {
                AdminHelper::recursiveDelete($tmpPath);
            }

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            } elseif (!isset($_FILES['theme_zip'])) {
                AdminHelper::setError(AdminHelper::t("no_file_found", "No theme file found, please try again."));
            } elseif (strlen($_FILES['theme_zip']['tmp_name']) == 0) {
                AdminHelper::setError(AdminHelper::t("no_file_found", "No theme file found, please try again."));
            } elseif (strpos(strtolower($_FILES['theme_zip']['name']), '.zip') === false) {
                AdminHelper::setError(AdminHelper::t("not_a_zip_file",
                    "The uploaded file does not appear to be a zip file."));
            }

            // add the plugin
            if (!AdminHelper::isErrors()) {
                // attempt to extract the contents
                $zip = new PclZip($_FILES['theme_zip']['tmp_name']);
                if ($zip) {
                    if (!mkdir($tmpPath)) {
                        AdminHelper::setError(AdminHelper::t("error_creating_theme_dir",
                            "There was a problem creating the theme folder. Please ensure the following folder has CHMOD 777 permissions: [[[THEME_FOLDER]]] and the theme _tmp folder does NOT exist: [[[TMP_FOLDER]]]",
                            ['THEME_FOLDER' => SITE_THEME_DIRECTORY_ROOT, 'TMP_FOLDER' => $tmpPath]));
                    }

                    if (AdminHelper::isErrors() === false) {
                        $zip->extract(PCLZIP_OPT_PATH, $tmpPath.'/');

                        // make sure we have a ThemeConfig.class.php file
                        if (!file_exists($tmpPath.'/ThemeConfig.class.php')) {
                            AdminHelper::setError(AdminHelper::t("error_reading_theme_details_file",
                                "Could not read the theme settings file 'ThemeConfig.class.php'."));
                        }

                        // get the namespace, so we can read the class properties later
                        $namespace = AdminHelper::extractNamespace($tmpPath.'/ThemeConfig.class.php');
                        if ($namespace === false) {
                            AdminHelper::setError(AdminHelper::t("error_reading_theme_namespace",
                                "Could not read the theme namespace from 'ThemeConfig.class.php'. Please ensure this is defined and try again."));
                        }

                        if (!AdminHelper::isErrors()) {
                            require_once($tmpPath.'/ThemeConfig.class.php');
                            try {
                                // try to load the class to get the config
                                $fullClassname = '\\'.$namespace.'\ThemeConfig';
                                $themeObj = new $fullClassname();
                                $themeConfig = $themeObj->getThemeConfig();
                            } catch (\Exception $e) {
                                AdminHelper::setError($e->getMessage());
                            }

                            if (!AdminHelper::isErrors() && (!isset($themeConfig['folder_name']))) {
                                // check for the folder_name setting in _plugin_config.inc.php
                                AdminHelper::setError(AdminHelper::t("error_reading_theme_folder_name_file",
                                    "Could not read the theme folder name from 'ThemeConfig.class.php'."));
                            }

                            if (!AdminHelper::isErrors()) {
                                // rename tmp folder
                                if (!rename($tmpPath, SITE_THEME_DIRECTORY_ROOT.$themeConfig['folder_name'])) {
                                    AdminHelper::setError(AdminHelper::t("error_renaming_theme_folder",
                                        "Could not rename theme folder, it may be that the theme is already installed or a permissions issue."));
                                } else {
                                    // user action logs
                                    UserActionLogHelper::logAdmin('Theme added', 'ADMIN', 'ADD', [
                                        'data' => $themeConfig,
                                    ]);

                                    // redirect to theme listing
                                    AdminHelper::redirect('theme_manage?sa=1');
                                }
                            }
                        }
                    }
                } else {
                    AdminHelper::setError(AdminHelper::t("error_problem_unzipping_the_file",
                        "There was a problem unzipping the file, please try and manually upload the zip files contents into the themes directory or contact support."));
                }
            }
        }

        // load template
        return $this->render('admin/theme_manage_add.html', [
            'currentProductUrl' => ThemeHelper::getCurrentProductUrl(),
            'currentProductName' => ThemeHelper::getCurrentProductName(),
        ]);
    }

    public function themePreview($themeFolderName)
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();

        // make sure theme exists in the database
        $exists = $db->getValue('SELECT id '
            .'FROM theme '
            .'WHERE folder_name = :folder_name '
            .'LIMIT 1', [
            'folder_name' => $themeFolderName,
        ]);
        if ($exists) {
            $_SESSION['_current_theme'] = $themeFolderName;
        }

        // redirect to site root to show theme
        return $this->redirect(WEB_ROOT);
    }

}
