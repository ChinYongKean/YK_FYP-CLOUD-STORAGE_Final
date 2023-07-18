<?php

namespace Plugins\Fileimport\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserActionLogHelper;
use App\Models\Plugin;
use App\Models\User;
use App\Services\FileSystem;

class PluginController extends CorePluginController
{

    public function pluginSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'fileimport';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', [
                    'folder_name' => $folderName,
        ]);

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // load plugin details
        $pluginObj = PluginHelper::getInstance($folderName);
        $basePath = $pluginObj->getLowestWritableBasePath();
        if ($this->inDemoMode()) {
            $basePath = DOC_ROOT;
        }

        // handle submissions
        $startImport = false;
        $import_path = '';
        $import_account = '';
        $urlParams = '';
        if ($request->request->has('submitted')) {
            $import_path = trim($request->request->get('import_path'));
            $import_account = trim($request->request->get('import_account'));
            $import_folder = (int) $request->request->get('import_folder');
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            elseif (strlen($import_path) == 0) {
                AdminHelper::setError('Please set the import path.');
            }
            elseif ($import_path == DIRECTORY_SEPARATOR) {
                AdminHelper::setError('The import path can not be root.');
            }
            elseif (!is_readable($import_path)) {
                AdminHelper::setError('The import path is not readable, please move the files to a readable directory and try again.');
            }
            elseif (strlen($import_account) == 0) {
                AdminHelper::setError('Please set the account to import the files into.');
            }
            else {
                // lookup the account
                $user = User::loadOneByClause('username = :username', [
                            'username' => $import_account
                ]);
                if (!$user) {
                    AdminHelper::setError('User account not found, please check and try again');
                }
            }

            // ok to run the import
            if (!AdminHelper::isErrors()) {
                $startImport = true;
                $urlParams = http_build_query([
                    'import_path' => $import_path,
                    'import_account' => $import_account,
                    'import_folder' => $import_folder,
                ]);
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'submitted' => $request->request->has('submitted'),
                    'basePath' => $basePath,
                    'startImport' => $startImport,
                    'import_path' => $import_path,
                    'import_account' => $import_account,
                    'urlParams' => $urlParams,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    public function ajaxFolderListing() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'fileimport';
        $pluginObj = PluginHelper::getInstance($folderName);
        $basePath = $pluginObj->getLowestWritableBasePath();
        if ($this->inDemoMode()) {
            $basePath = DOC_ROOT;
        }

        // attempt to get file system info
        $fs = new FileSystem($basePath);
        try {
            $node = $request->query->has('id') && $request->query->get('id') !== '#' ? $request->query->get('id') : '/';
            $rs = $fs->lst($node, ($request->query->has('id') && $request->query->get('id') === '#'));

            return $this->renderJson($rs);
        }
        catch (Exception $e) {
            return $this->renderContent($e->getMessage());
        }
    }

    public function downloadImportScript() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // prep path
        $folderName = 'fileimport';
        $filePath = PLUGIN_DIRECTORY_ROOT . $folderName . '/offline/import.php.txt';

        return $this->renderDownloadFileFromPath($filePath, 'import.php');
    }

    public function processFileImport() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // allow 12 hours and more memory
        set_time_limit(60 * 12);
        ini_set('memory_limit', '2048M');

        // handle submissions
        $import_path = trim($request->query->get('import_path'));
        $import_account = trim($request->query->get('import_account'));
        $import_folder = (int) $request->query->get('import_folder');
        if (strlen($import_path) == 0) {
            AdminHelper::setError('Please set the import path.');
        }
        elseif ($import_path == DIRECTORY_SEPARATOR) {
            AdminHelper::setError('The import path can not be root.');
        }
        elseif (!is_readable($import_path)) {
            AdminHelper::setError('The import path is not readable, please move the files to a readable directory and try again.');
        }
        elseif (strlen($import_account) == 0) {
            AdminHelper::setError('Please set the account to import the files into.');
        }
        else {
            // lookup the account
            $user = User::loadOneByClause('username = :username', array(
                        'username' => $import_account
            ));
            if (!$user) {
                AdminHelper::setError('User account not found, please check and try again');
            }
        }

        // output styles in the iframe
        // @TODO - move away from echoing out in controllers. This is only still
        // here as this script sends output as each file is imported, there's
        // currently no other way to handle this.
        echo '<link href="'.CORE_ASSETS_ADMIN_WEB_ROOT.'/vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="screen" /><link href="'.CORE_ASSETS_ADMIN_WEB_ROOT.'/assets/css/responsive.css" rel="stylesheet"><link href="'.CORE_ASSETS_ADMIN_WEB_ROOT.'/assets/css/custom.css" rel="stylesheet">';
        if (AdminHelper::isErrors()) {
            return $this->renderContent(AdminHelper::compileErrorHtml());
        }
        else {
            // setup access to the plugin functions
            $pluginObj = PluginHelper::getInstance('fileimport');
            if (!$pluginObj) {
                AdminHelper::setError('ERROR: Failed loading plugin object to import files, please ensure the plugin has been enabled.', true);
            }

            // prepare folder id
            if ((int) $import_folder == 0) {
                $import_folder = null;
            }

            // prepare path
            if (substr($import_path, strlen($import_path) - 1, 1) != '/') {
                $import_path .= '/';
            }

            // scan for files
            $items = CoreHelper::getDirectoryListing($import_path);
            if (count($items) == 0) {
                AdminHelper::setError('ERROR: No files or folders found in folder. Total: ' . count($items), true);
            }
            
            if (AdminHelper::isErrors()) {
                return $this->renderContent(AdminHelper::compileErrorHtml());
            }

            // 1KB of initial data, required by Webkit browsers
            echo "<span style='display: none;'>" . str_repeat("0", 1000) . "</span>";

            // user action logs
            UserActionLogHelper::logAdmin('Imported files', 'ADMIN', 'IMPORT', [
                'data' => [
                    'import_path' => $import_path,
                    'import_user_id' => $user->id,
                    'import_folder' => $import_folder,
                ],
            ]);

            // import files
            $pluginObj->importFiles($import_path, $user->id, $import_folder);

            // finish
            $pluginObj->output('<br/><span class="text-success"><strong>Import process completed.</strong></span>');
            $pluginObj->output('<br/>Note: The original files in your import folder have not been moved, only database entries have been added. You should manually move these files into '.DOC_ROOT.'/files/imported/ retaining any folder structure.');
            
            return $this->renderEmpty200Response();
        }
    }

}
