<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Models\Plugin;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\CacheHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\UserActionLogHelper;
use PclZip;

class PluginController extends AdminBaseController
{

    public function pluginManage()
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // update plugin config cache
        PluginHelper::loadPluginConfigurationFiles(true);

        // clear route cache
        CacheHelper::removeRouteCache();

        // error/success messages
        if ($request->query->has('sm')) {
            // redirect to plugin settings
            AdminHelper::setSuccess(urldecode($request->query->get('sm')));
            if (strlen(trim($request->query->get('plugin')))) {
                AdminHelper::redirect(ADMIN_WEB_ROOT.'/plugin/'.urlencode(trim($request->query->get('plugin'))).'/settings');
            }
        } elseif ($request->query->has('d')) {
            AdminHelper::setSuccess(urldecode($request->query->get('d')));
        } elseif ($request->query->has('error')) {
            AdminHelper::setError(urldecode($request->query->get('error')));
        }

        // load template
        return $this->render('admin/plugin_manage.html', [
            'currentProductUrl' => ThemeHelper::getCurrentProductUrl(),
            'currentProductName' => ThemeHelper::getCurrentProductName(),
        ]);
    }

    public function ajaxPluginManage()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // import any new plugins as uninstalled
        AdminHelper::registerPlugins();
        $pluginConfigs = PluginHelper::getPluginConfiguration();

        $iDisplayLength = (int)$request->query->get('iDisplayLength');
        $iDisplayStart = (int)$request->query->get('iDisplayStart');
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;

        $sqlClause = "WHERE 1=1 ";
        if ($filterText) {
            $filterText = $db->escape($filterText);
            $sqlClause .= "AND (CAST(plugin.folder_name AS CHAR CHARACTER SET latin1) LIKE '%".$filterText."%' OR ";
            $sqlClause .= "CAST(plugin.plugin_name AS CHAR CHARACTER SET latin1) LIKE '%".$filterText."%')";
        }

        $sQL = "SELECT * FROM plugin ";
        $sQL .= $sqlClause." ";
        $sQL .= "ORDER BY plugin_name ";
        $totalRS = $db->getRows($sQL);

        $sQL .= "LIMIT ".$iDisplayStart.", ".$iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS as $row) {
                // preload version number
                $pluginVersion = 'NOT FOUND';
                $pluginConfig = PluginHelper::getPluginConfigByFolderName($row['folder_name']);
                if ($pluginConfig !== false) {
                    $pluginVersion = 'v'.$pluginConfig->getPluginVersion();
                }

                $lRow = [];
                $lRow[] = '<img src="'.WEB_ROOT.'/plugins/'.$row['folder_name'].'/assets/img/icons/16px.png" width="16" height="16" title="'.$row['plugin_name'].'" alt="'.$row['plugin_name'].'"/>';
                $lRow[] = (($row['is_installed'] == 1) ? ('<a href="'.ADMIN_WEB_ROOT.'/plugin/'.$row['folder_name'].'/settings">') : '').AdminHelper::makeSafe($row['plugin_name']).(($row['is_installed'] == 1) ? '</a>' : '').'<br/><span style="color: #777;">'.AdminHelper::makeSafe($row['plugin_description']).'</span>';
                $lRow[] = '/'.AdminHelper::makeSafe($row['folder_name']);
                $lRow[] = '<span class="statusText'.(($row['is_installed'] == 1) ? 'Yes' : 'No').'">'.(($row['is_installed'] == 1) ? 'Yes' : 'No').'</span>';
                $lRow[] = $pluginVersion;
                $lRow[] = '<img src="'.CORE_ASSETS_ADMIN_WEB_ROOT.'/images/spinner_small.gif" alt="Checking for Updates" data-toggle="tooltip" data-placement="top" data-original-title="Checking for Updates" class="update_checker identifier_'.$row['folder_name'].'"/>';

                $links = [];
                if ($row['is_installed'] == 1) {
                    // link in settings
                    $links[] = '<a href="'.ADMIN_WEB_ROOT.'/plugin/'.$row['folder_name'].'/settings">settings</a>';

                    // add any plugin specific links
                    if (isset($pluginConfigs[$row['folder_name']]['config']['admin_settings']['plugin_manage_nav'])) {
                        foreach ($pluginConfigs[$row['folder_name']]['config']['admin_settings']['plugin_manage_nav'] as $pluginLinks) {
                            $links[] = '<a href="'.PLUGIN_WEB_ROOT.'/'.$row['folder_name'].'/'.AdminHelper::makeSafe($pluginLinks['link_url']).'">'.strtolower(AdminHelper::makeSafe($pluginLinks['link_text'])).'</a>';
                        }
                    }

                    // uninstall link
                    $links[] = '<a href="#" onClick="confirmUninstallPlugin('.(int)$row['id'].'); return false;" class="plugin_uninstall_'.$row['folder_name'].'">uninstall</a>';
                } elseif ($pluginVersion != 'NOT FOUND') {
                    $links[] = '<a href="#" onClick="confirmInstallPlugin('.(int)$row['id'].'); return false;" class="plugin_install_'.$row['folder_name'].'">install</a>';
                }

                if (($row['is_installed'] != 1) || ($pluginVersion == 'NOT FOUND')) {
                    $links[] = '<a href="#" onClick="confirmDeletePlugin('.(int)$row['id'].'); return false;" class="plugin_delete_'.$row['folder_name'].'">delete</a>';
                }
                $lRow[] = implode(" <span class='plugin_option_divider'>|</span> ", $links);

                $data[] = $lRow;
            }
        }

        $resultArr = [];
        $resultArr["sEcho"] = intval($_GET['sEcho']);
        $resultArr["iTotalRecords"] = (int)count($totalRS);
        $resultArr["iTotalDisplayRecords"] = $resultArr["iTotalRecords"];
        $resultArr["aaData"] = $data;

        // output response
        return $this->renderJson($resultArr);
    }

    public function pluginManageAdd()
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // check for write permissions on the plugins folder
        if (!is_writable(PLUGIN_DIRECTORY_ROOT)) {
            AdminHelper::setError(AdminHelper::t("error_plugin_folder_is_not_writable",
                "Plugin folder is not writable. Ensure you set the following folder to CHMOD 755 or 777: [[[PLUGIN_FOLDER]]]",
                ['PLUGIN_FOLDER' => PLUGIN_DIRECTORY_ROOT]));
        }

        // handle page submissions
        if ($request->request->has('submitted')) {
            // delete existing tmp folder
            $tmpPath = PLUGIN_DIRECTORY_ROOT.'_tmp';
            if (file_exists($tmpPath)) {
                AdminHelper::recursiveDelete($tmpPath);
            }

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            } elseif (!isset($_FILES['plugin_zip'])) {
                AdminHelper::setError(AdminHelper::t("no_file_found", "No plugin file found, please try again."));
            } elseif (strlen($_FILES['plugin_zip']['tmp_name']) == 0) {
                AdminHelper::setError(AdminHelper::t("no_file_found", "No plugin file found, please try again."));
            } elseif (strpos(strtolower($_FILES['plugin_zip']['name']), '.zip') === false) {
                AdminHelper::setError(AdminHelper::t("not_a_zip_file",
                    "The uploaded file does not appear to be a zip file."));
            }

            // add the plugin
            if (AdminHelper::isErrors() === false) {
                // attempt to extract the contents
                $zip = new PclZip($_FILES['plugin_zip']['tmp_name']);
                if ($zip) {
                    if (!mkdir($tmpPath)) {
                        AdminHelper::setError(AdminHelper::t("error_creating_plugin_folder",
                            "There was a problem creating the plugin folder. Please ensure the following folder has CHMOD 777 permissions: ".PLUGIN_DIRECTORY_ROOT));
                    }

                    if (AdminHelper::isErrors() === false) {
                        $zip->extract(PCLZIP_OPT_PATH, $tmpPath.'/');

                        // make sure we have a PluginConfig.class.php file
                        if (!file_exists($tmpPath.'/PluginConfig.class.php')) {
                            AdminHelper::setError(AdminHelper::t("error_reading_plugin_details_file",
                                "Could not read the plugin settings file 'PluginConfig.class.php'."));
                        }

                        // get the namespace, so we can read the class properties later
                        $namespace = AdminHelper::extractNamespace($tmpPath.'/PluginConfig.class.php');
                        if ($namespace === false) {
                            AdminHelper::setError(AdminHelper::t("error_reading_plugin_namespace",
                                "Could not read the plugin namespace from 'PluginConfig.class.php'. Please ensure this is defined and try again."));
                        }

                        if (AdminHelper::isErrors() === false) {
                            require_once($tmpPath.'/PluginConfig.class.php');
                            try {
                                // try to load the class to get the config
                                $fullClassname = '\\'.$namespace.'\PluginConfig';
                                $pluginObj = new $fullClassname();
                                $pluginConfig = $pluginObj->getPluginConfig();
                            } catch (\Exception $e) {
                                AdminHelper::setError($e->getMessage());
                            }

                            if ((AdminHelper::isErrors() === false) && (!isset($pluginConfig['folder_name']))) {
                                // check for the folder_name setting in _plugin_config.inc.php
                                AdminHelper::setError(AdminHelper::t("error_reading_plugin_folder_name_file",
                                    "Could not read the plugin folder name from 'PluginConfig.class.php'."));
                            }

                            // only for yetishare as other product types have different versions
                            if ((AdminHelper::isErrors() === false) && (ThemeHelper::getCurrentProductType() == 'file_hosting')) {
                                if (isset($pluginConfig['required_script_version'])) {
                                    // check that the required script version is valid for the current script version
                                    if (version_compare($pluginConfig['required_script_version'],
                                            CoreHelper::getScriptInstalledVersion()) > 0) {
                                        AdminHelper::setError(AdminHelper::t("error_minimum_script_version_not_met",
                                            "The minimum core script version for this plugin is v[[[MIN_SCRIPT_VERSION]]], you are using v[[[CURRENT_SCRIPT_VERSION]]]. Please upgrade if you want to install this plugin.",
                                            [
                                                'MIN_SCRIPT_VERSION' => $pluginConfig['required_script_version'],
                                                'CURRENT_SCRIPT_VERSION' => CoreHelper::getScriptInstalledVersion(),
                                            ]));
                                    }
                                }
                            }

                            if (AdminHelper::isErrors() === false) {
                                // rename tmp folder
                                if (!rename($tmpPath, PLUGIN_DIRECTORY_ROOT.$pluginConfig['folder_name'])) {
                                    AdminHelper::setError(AdminHelper::t("error_renaming_plugin_folder",
                                        "Could not rename plugin folder, it may be that the plugin is already installed or a permissions issue."));
                                } else {
                                    // user action logs
                                    UserActionLogHelper::logAdmin('Added plugin', 'ADMIN', 'ADD', [
                                        'data' => $pluginConfig,
                                    ]);

                                    // redirect to plugin listing
                                    AdminHelper::setSuccess('Plugin successfully added. To enable the plugin, install it below and configure any plugin specific settings.');
                                    AdminHelper::redirect('plugin_manage');
                                }
                            }
                        }
                    }
                } else {
                    AdminHelper::setError(AdminHelper::t("error_problem_unzipping_the_file",
                        "There was a problem unzipping the file, please try and manually upload the zip files contents into the plugins directory or contact support."));
                }
            }
        }

        // load template
        return $this->render('admin/plugin_manage_add.html', [
            'currentProductUrl' => ThemeHelper::getCurrentProductUrl(),
            'currentProductName' => ThemeHelper::getCurrentProductName(),
        ]);
    }

    public function ajaxPluginManageInstall()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $pluginId = (int)$request->request->get('plugin_id');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['id'] = $pluginId;
        $result['plugin'] = '';

        // validate submission
        if ($pluginId == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_id_not_found", "Plugin id not found.");
        } elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }

        if (strlen($result['msg']) == 0) {
            $plugin = Plugin::loadOneById($pluginId);
            if (!$plugin) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("could_not_locate_plugin",
                    "Could not locate plugin within the database, please try again later.");
            } elseif ($plugin->is_installed == 1) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("plugin_already_installed",
                    "The plugin you've selected is already installed.");
            } else {
                // install plugin
                $pluginPath = PLUGIN_DIRECTORY_ROOT.$plugin->folder_name.'/';
                $pluginClassFile = $pluginPath.'Plugin'.UCFirst(strtolower($plugin->folder_name)).'.class.php';
                $pluginClassName = '\\Plugins\\'.UCFirst(strtolower($plugin->folder_name)).'\\Plugin'.UCFirst(strtolower($plugin->folder_name));

                // make sure we have the main class file
                if (!file_exists($pluginClassFile)) {
                    $result['error'] = true;
                    $result['msg'] = AdminHelper::t("plugin_code_not_found",
                        "Could not locate the plugin code within the plugins folder, please add it and try again.");
                } else {
                    try {
                        // include the plugin code
                        include_once($pluginClassFile);

                        // create an instance of the plugin
                        $instance = new $pluginClassName();

                        // call the install method
                        $instance->install();

                        // user action logs
                        UserActionLogHelper::logAdmin('Installed plugin', 'ADMIN', 'UPDATE', [
                            'data' => [
                                'plugin_name' => $plugin->plugin_name,
                                'folder_name' => $plugin->folder_name,
                            ],
                        ]);
                    } catch (Exception $e) {
                        $result['error'] = true;
                        $result['msg'] = "Exception: ".$e->getMessage();
                    }
                }

                if ($result['error'] == false) {
                    $result['msg'] = 'Plugin \''.$plugin->plugin_name.'\' successfully installed. Please configure any settings for the plugin using the link below.';
                    $result['plugin'] = $plugin->folder_name;
                }
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxPluginManageUninstall()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $pluginId = (int)$request->request->get('plugin_id');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['id'] = $pluginId;
        $result['plugin'] = '';

        // validate submission
        if ($pluginId == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_id_not_found", "Plugin id not found.");
        } elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }

        if (strlen($result['msg']) == 0) {
            $plugin = Plugin::loadOneById($pluginId);
            if (!$plugin) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("could_not_locate_plugin",
                    "Could not locate plugin within the database, please try again later.");
            } elseif ($plugin->is_installed == 0) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("plugin_already_uninstalled",
                    "The plugin you've selected has already been uninstalled.");
            } else {
                // uninstall plugin
                $pluginPath = PLUGIN_DIRECTORY_ROOT.$plugin->folder_name.'/';
                $pluginClassFile = $pluginPath.'Plugin'.UCFirst(strtolower($plugin->folder_name)).'.class.php';
                $pluginClassName = '\\Plugins\\'.UCFirst(strtolower($plugin->folder_name)).'\\Plugin'.UCFirst(strtolower($plugin->folder_name));

                // make sure we have the main class file
                if (!file_exists($pluginClassFile)) {
                    // failed loading the config, just set the record as uninstalled
                    $db->query('UPDATE plugin '
                        .'SET is_installed = 0 '
                        .'WHERE id = :id '
                        .'LIMIT 1', [
                        'id' => $plugin->id,
                    ]);

                    // error reporting
                    $result['msg'] = AdminHelper::t("plugin_code_not_found_error_uninstalled",
                        "Could not locate the plugin code within the plugins folder, although we have marked the plugin as uninstalled within the database.");

                    return $this->renderJson($result);
                } else {
                    try {
                        // include the plugin code
                        include_once($pluginClassFile);

                        // create an instance of the plugin
                        $instance = new $pluginClassName();

                        // call the uninstall method
                        $instance->uninstall();

                        // user action logs
                        $pluginObj = PluginHelper::getInstance($plugin->folder_name);
                        UserActionLogHelper::logAdmin('Uninstalled plugin', 'ADMIN', 'UPDATE', [
                            'data' => [
                                'plugin_name' => $plugin->plugin_name,
                                'folder_name' => $plugin->folder_name,
                            ],
                        ]);
                    } catch (Exception $e) {
                        $result['error'] = true;
                        $result['msg'] = "Exception: ".$e->getMessage();
                    }
                }

                if ($result['error'] == false) {
                    $result['msg'] = 'Plugin \''.$plugin->plugin_name.'\' successfully uninstalled.';
                    $result['plugin'] = $plugin->folder_name;
                }
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxPluginManageDelete()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $pluginId = (int)$request->request->get('plugin_id');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['id'] = $pluginId;
        $result['plugin'] = '';

        // validate submission
        if ($pluginId == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_id_not_found", "Plugin id not found.");
        } elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }

        if (strlen($result['msg']) == 0) {
            $plugin = Plugin::loadOneById($pluginId);
            if (!$plugin) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("could_not_locate_plugin",
                    "Could not locate plugin within the database, please try again later.");
            } elseif ($plugin->is_installed == 1) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t('uninstall_plugin_before_deleting',
                    'Please uninstall the plugin before deleting.');
            } else {
                // delete the plugin
                $pluginPath = realpath(PLUGIN_DIRECTORY_ROOT.$plugin->folder_name);
                if (file_exists($pluginPath)) {
                    if (AdminHelper::recursiveDelete($pluginPath) == false) {
                        if (!rmdir($pluginPath)) {
                            $result['error'] = true;
                            $result['msg'] = AdminHelper::t('Could_not_delete_some_plugin_files',
                                'Could not delete some files, please delete them manually.');
                        }
                    }
                }
                if (strlen($result['msg']) == 0) {
                    $db->query('DELETE FROM plugin '
                        .'WHERE id = :id '
                        .'LIMIT 1', [
                        'id' => $plugin->id,
                    ]);

                    // user action logs
                    UserActionLogHelper::logAdmin('Deleted plugin', 'ADMIN', 'DELETE', [
                        'data' => [
                            'plugin_name' => $plugin->plugin_name,
                            'folder_name' => $plugin->folder_name,
                        ],
                    ]);

                    $result['msg'] = AdminHelper::t('plugin_successfully_deleted', 'Plugin successfully deleted.');
                }
            }
        }

        // output response
        return $this->renderJson($result);
    }

}
