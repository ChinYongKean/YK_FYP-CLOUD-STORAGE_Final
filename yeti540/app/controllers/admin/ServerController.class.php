<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\ServerResourceHelper;
use App\Models\File;
use App\Models\FileServer;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\CrossSiteActionHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileServerHelper;
use App\Helpers\FileServerContainerHelper;
use App\Helpers\PluginHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserActionLogHelper;

class ServerController extends AdminBaseController
{

    public function serverManage() {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // update storage stats
        if ($request->query->has('r')) {
            FileHelper::updateFileServerStorageStats(null, true);

            AdminHelper::setSuccess(AdminHelper::t("file_storage_stats_updated", "File storage stats updated"));
        }

        // handle upload/download toggles
        if ($request->query->has('toggle_uploads')) {
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $configValue = 'yes';
                if (SITE_CONFIG_UPLOADS_BLOCK_ALL == 'yes') {
                    $configValue = 'no';
                }
                $db->query('UPDATE site_config '
                        . 'SET config_value = :configValue '
                        . 'WHERE config_key = \'uploads_block_all\' '
                        . 'LIMIT 1', [
                    'configValue' => $configValue,
                    ]
                );

                // user action logs
                UserActionLogHelper::logAdmin(($configValue === 'yes' ? 'Disabled' : 'Enabled').' all site uploads', 'ADMIN', 'UPDATE', [
                    'data' => [
                        'config_key' => 'uploads_block_all',
                        'config_value' => $configValue,
                    ],
                ]);

                // redirect to self
                return $this->redirect(ADMIN_WEB_ROOT . '/server_manage?toggle_uploadss=' . $configValue);
            }
        }
        elseif ($request->query->has('toggle_uploadss')) {
            AdminHelper::setSuccess("All uploading has been " . ($request->query->get('toggle_uploadss') == 'yes' ? 'disabled' : 'enabled') . ".");
        }
        elseif ($request->query->has('toggle_downloads')) {
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $configValue = 'yes';
                if (SITE_CONFIG_DOWNLOADS_BLOCK_ALL == 'yes') {
                    $configValue = 'no';
                }
                $db->query('UPDATE site_config '
                        . 'SET config_value = :configValue '
                        . 'WHERE config_key = \'downloads_block_all\' '
                        . 'LIMIT 1', array(
                    'configValue' => $configValue,
                        )
                );

                // user action logs
                UserActionLogHelper::logAdmin(($configValue === 'yes' ? 'Disabled' : 'Enabled').' all site downloads', 'ADMIN', 'UPDATE', [
                    'data' => [
                        'config_key' => 'uploads_block_all',
                        'config_value' => $configValue,
                    ],
                ]);

                // redirect to self
                return $this->redirect(ADMIN_WEB_ROOT . '/server_manage?toggle_downloadss=' . $configValue);
            }
        }
        elseif ($request->query->has('toggle_downloadss')) {
            AdminHelper::setSuccess("All downloading has been " . ($request->query->get('toggle_downloadss') == 'yes' ? 'disabled' : 'enabled') . ".");
        }

        // defaults
        $filterText = '';
        if ($request->query->has('filterText')) {
            $filterText = trim($request->query->get('filterText'));
        }

        // load template
        return $this->render('admin/server_manage.html', [
            'addServerTrigger' => $request->query->has('add'),
            'filterText' => $filterText,
        ]);
    }

    public function ajaxServerManage() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // update storage stats
        FileHelper::updateFileServerStorageStats();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'file_server.serverLabel';
        switch ($sortColumnName) {
            case 'server_type':
                $sort = 'file_server.serverType';
                break;
            case 'storage_path':
                $sort = 'file_server.storagePath';
                break;
            case 'total_files':
                $sort = 'file_server.totalFiles';
                break;
            case 'status':
                $sort = 'file_server_status.label';
                break;
        }

        $sqlClause = "WHERE 1=1 ";
        if ($filterText) {
            $filterText = $db->escape($filterText);
            $sqlClause .= "AND (file_server.serverLabel LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_server.ipAddress LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_server.serverType = '" . $filterText . "' OR ";
            $sqlClause .= "file_server.storagePath LIKE '%" . $filterText . "%')";
        }

        $sQL = 'SELECT COUNT(1) AS total '
            . 'FROM file_server '
            . 'LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id '
            . $sqlClause;
        $totalRS = $db->getValue($sQL);

        $sQL = 'SELECT file_server.*, file_server_status.label AS statusLabel, '
            . 'totalSpaceUsed, totalFiles, '
            . '(SELECT CONCAT(cpu_load_1_minute, "|", disk_primary_used_percent, "|", network_total_connections, "|", cpu_count) FROM file_server_resource_usage WHERE file_server_id = file_server.id AND date_created > NOW() - INTERVAL 1 DAY ORDER BY id DESC LIMIT 1) AS resource_stats '
            . 'FROM file_server '
            . 'LEFT JOIN file_server_status ON file_server.statusId = file_server_status.id '
            . $sqlClause . ' ';
        $sQL .= "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " ";
        $sQL .= "LIMIT " . $iDisplayStart . ", " . $iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        // get any additional types from plugins
        $additionalServerTypes = PluginHelper::callHookRecursive('adminServerManageAddFormList');
        $formattedAdditional = [];
        if(count($additionalServerTypes)) {
            foreach($additionalServerTypes AS $k=>$additionalServerType) {
                $formattedAdditional[$additionalServerType['filestore_key']] = $k;
            }
        }

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $lRow = [];

                $imagePath = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/server/128/cloud.png';
                if(in_array($row['serverType'], ['local', 'direct', 'ftp'])) {
                    // core storage
                    $imagePath = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/server/128/'.$row['serverType'].'.png';
                }
                elseif(isset($formattedAdditional[$row['serverType']])) {
                    // plugin storage
                    $imagePath = PLUGIN_WEB_ROOT . '/'.$formattedAdditional[$row['serverType']].'/assets/img/icons/128px.png';
                }

                // prepare availability icon
                $availabilityIcon = '';
                if($row['serverType'] === 'direct' && (int) $row['enable_availability_checker'] === 1) {
                    if((int) $row['availability_state'] === 0) {
                        $availabilityIcon = '<i class="availability-icon fa fa-circle offline fa-pulse" aria-hidden="true" title="Offline"></i>';
                    }
                    elseif((int) $row['availability_state'] === 1) {
                        $availabilityIcon = '<i class="availability-icon fa fa-circle online" aria-hidden="true" title="Online"></i>';
                    }
                }

                $lRow[] = '<img src="' . $imagePath . '" width="16" title="' . UCWords(AdminHelper::makeSafe(str_replace('_', ' ', $row['serverType']))) . '" alt="' . UCWords(AdminHelper::makeSafe(str_replace('_', ' ', $row['serverType']))) . '"/>';
                $label = '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'">'.AdminHelper::makeSafe($row['serverLabel']).'</a>'.$availabilityIcon;
                if (strlen($row['ipAddress'])) {
                    $label .= ' (' . AdminHelper::makeSafe($row['ipAddress']) . ') ';
                }
                elseif (strlen($row['fileServerDomainName'])) {
                    $label .= '<br/>- ' . AdminHelper::makeSafe($row['fileServerDomainName']) ;
                }

                // append resource information
                $resourceStats = $row['resource_stats'];
                $resourceUsage = '';
                $backgroundOK = 'grey';
                $backgroundWarning = 'orange';
                $backgroundError = 'red';
                if(strlen($resourceStats) && (int) $row['monitor_server_resources'] === 1) {
                    $resourceItems = explode('|', $resourceStats);
                    $resourceUsage = '<br/>';
                    $resourceUsage .= '<span class="sub-text">';
                    $itemClass =  $backgroundOK;
                    if(strlen($resourceItems[3]) && (int)$resourceItems[3] > 0) {
                        // concerned load is only when load/cpu_count > cpu_count
                        if(((float)$resourceItems[0] / (int)$resourceItems[3]) > (int)$resourceItems[3]) {
                            $itemClass =  $backgroundWarning;
                        }
                    }
                    $resourceUsage .= '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'" class="badge bg-alt-style bg-'.$itemClass.'" title="CPU load over the last minute"><strong>CPU:</strong> '.AdminHelper::makeSafe($resourceItems[0]).'</a>';
                    if(strlen($resourceItems[1])) {
                        $itemClass =  $backgroundOK;
                        if((float)$resourceItems[1] > 95) {
                            $itemClass =  $backgroundError;
                        }
                        elseif((float)$resourceItems[1] > 80) {
                            $itemClass =  $backgroundWarning;
                        }
                        $resourceUsage .= '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'" class="badge bg-alt-style bg-'.$itemClass.'" title="Percent HD used"><strong>HD:</strong> '.AdminHelper::makeSafe($resourceItems[1]).'%</a>';
                    }
                    if(strlen($resourceItems[2])) {
                        $resourceUsage .= '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'" class="badge bg-alt-style bg-'.$backgroundOK.'" title="Total network connections"><strong>CON:</strong> '.AdminHelper::makeSafe($resourceItems[2]).'</a>';
                    }
                    $resourceUsage .= '</span>';
                }

                $lRow[] = $label . $resourceUsage;
                $lRow[] = UCWords(AdminHelper::makeSafe(str_replace('_', ' ', $row['serverType'])));
                $lRow[] = (int) $row['totalFiles'] > 0 ? ('<a href="file_manage?filterByServer=' . (int) $row['id'] . '">' . AdminHelper::makeSafe($row['totalFiles']) . ' <span class="fa fa-search" aria-hidden="true"></span></a><br/>'.AdminHelper::makeSafe(AdminHelper::formatSize($row['totalSpaceUsed'], 2))) : 0;
                $lRow[] = '<span class="statusText' . str_replace(" ", "", UCWords($row['statusLabel'])) . '">' . $row['statusLabel'] . '</span>';

                $links = [];
                $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="view files" href="file_manage?filterByServer=' . (int) $row['id'] . '"><span class="fa fa-upload" aria-hidden="true"></span></a>';
                if ($row['is_default'] !== 1) {
                    $links[] = '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="edit"><span class="fa fa-pencil" aria-hidden="true"></span></a>';
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="remove storage" onClick="confirmRemoveFileServer(' . (int) $row['id'] . ', \'' . AdminHelper::makeSafe($row['serverLabel']) . '\', ' . (int) $row['totalFiles'] . '); return false;"><span class="fa fa-trash text-danger" aria-hidden="true"></span></a>';
                }
                else {
                    $links[] = '<a href="'.ADMIN_WEB_ROOT.'/server_add_edit/'.(int) $row['id'].'" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="edit"><span class="fa fa-pencil" aria-hidden="true"></span></a>';
                }

                if ($row['serverType'] == 'ftp') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="test server" onClick="testFtpFileServer(' . (int) $row['id'] . '); return false;"><span class="fa fa-heartbeat" aria-hidden="true"></span></a>';
                }
                elseif ($row['serverType'] == 'sftp') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="test server" onClick="testSftpFileServer(' . (int) $row['id'] . '); return false;"><span class="fa fa-heartbeat" aria-hidden="true"></span></a>';
                }
                elseif ($row['serverType'] == 'direct') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="test server" onClick="testDirectFileServer(' . (int) $row['id'] . '); return false;"><span class="fa fa-heartbeat" aria-hidden="true"></span></a>';
                }
                elseif (substr($row['serverType'], 0, 10) == 'flysystem_') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="test server" onClick="testFlysystemFileServer(' . (int) $row['id'] . '); return false;"><span class="fa fa-heartbeat" aria-hidden="true"></span></a>';
                }
                $linkStr = '<div class="btn-group">' . implode(" ", $links) . '</div>';
                $lRow[] = $linkStr;

                $data[] = $lRow;
            }
        }

        $resultArr = [];
        $resultArr["sEcho"] = intval($_GET['sEcho']);
        $resultArr["iTotalRecords"] = (int) $totalRS;
        $resultArr["iTotalDisplayRecords"] = $resultArr["iTotalRecords"];
        $resultArr["aaData"] = $data;

        // output response
        return $this->renderJson($resultArr);
    }

    public function serverAddEdit($fileServerId = null) {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load all flysystem storage containers
        $flySystemContainers = $db->getRows('SELECT * '
            . 'FROM file_server_container '
            . 'WHERE is_enabled = 1 '
            . 'ORDER BY label');

        // prepare variables
        $server_label = '';
        $status_id = '';
        $server_type = 'local';
        $ftp_host = '';
        $ftp_port = 21;
        $ftp_username = '';
        $ftp_password = '';
        $storage_path = 'files/';
        $formType = 'set the new';
        $file_server_domain_name = '';
        $script_root_path = '';
        $script_path = '/';
        $max_storage_space = 0;
        $server_priority = 0;
        $route_via_main_site = 1;
        $download_accelerator = 0;
        $file_server_direct_ip_address = '';
        $file_server_direct_ssh_port = 22;
        $file_server_direct_ssh_authentication_type = 'ssh_password';
        $file_server_direct_ssh_username = '';
        $file_server_direct_ssh_password = '';
        $file_server_direct_ssh_key = '';
        $server_config_array = [];
        $file_server_download_proto = _CONFIG_SITE_PROTOCOL;
        $cdn_url = '';
        $geo_upload_countries = [];
        $account_upload_types = [];

        // server config variables
        $ftp_server_type = 'linux';
        $ftp_passive_mode = 'no';

        // prepare whether we should disable local server or not
        $isDefaultServer = false;
        $resourceUsage = false;
        $monitorServerResources = 0;
        $enableAvailabilityChecker = 0;
        $availabilityIcon = '';

        // is this an edit?
        if ($fileServerId) {
            $sQL = "SELECT * "
                . "FROM file_server "
                . "WHERE id = :id "
                . "LIMIT 1";
            $serverDetails = $db->getRow($sQL, [
                'id' => $fileServerId,
            ]);
            if ($serverDetails) {
                $server_label = $serverDetails['serverLabel'];
                $status_id = $serverDetails['statusId'];
                $server_type = $serverDetails['serverType'];
                $ftp_host = $serverDetails['ipAddress'];
                $ftp_port = $serverDetails['ftpPort'];
                $ftp_username = $serverDetails['ftpUsername'];
                $ftp_password = $serverDetails['ftpPassword'];
                $storage_path = $serverDetails['storagePath'];
                $formType = 'update the';
                $file_server_domain_name = $serverDetails['fileServerDomainName'];
                $script_root_path = (strlen($serverDetails['scriptRootPath']) ? $serverDetails['scriptRootPath'] : ($server_type === 'local' ? DOC_ROOT : $script_root_path));
                $script_path = $serverDetails['scriptPath'];
                $max_storage_space = strlen($serverDetails['maximumStorageBytes']) ? $serverDetails['maximumStorageBytes'] : 0;
                $server_priority = (int) $serverDetails['priority'];
                $route_via_main_site = (int) $serverDetails['routeViaMainSite'];
                $download_accelerator = (int) $serverDetails['dlAccelerator'];
                $geo_upload_countries = strlen($serverDetails['geoUploadCountries'])?explode(',', $serverDetails['geoUploadCountries']):array();
                $account_upload_types = strlen($serverDetails['accountUploadTypes'])?explode(',', $serverDetails['accountUploadTypes']):array();
                if ((int) $serverDetails['is_default'] === 1) {
                    $isDefaultServer = true;
                }
                $monitorServerResources = (int) $serverDetails['monitor_server_resources'];
                $enableAvailabilityChecker = (int) $serverDetails['enable_availability_checker'];

                // @TODO - later move the above settings into here
                $server_config = $serverDetails['serverConfig'];
                if (strlen($server_config)) {
                    $server_config_array = json_decode($server_config, true);
                    if (is_array($server_config_array)) {
                        foreach ($server_config_array AS $k => $v) {
                            // make available as local variables
                            $$k = $v;
                        }

                        // if we have the path in the serverConfig, store it in the script_root_path
                        if ((isset($server_config_array['file_server_direct_server_path_to_storage']) && (strlen($server_config_array['file_server_direct_server_path_to_storage'])))) {
                            $script_root_path = $server_config_array['file_server_direct_server_path_to_storage'];
                        }
                    }
                }

                // server login data
                $server_access = $serverDetails['serverAccess'];
                if (strlen($server_access)) {
                    $server_access = CoreHelper::decryptValue($server_access);
                    $server_access_array = json_decode($server_access, true);
                    if (is_array($server_access_array)) {
                        foreach ($server_access_array AS $k => $v) {
                            // make available as local variables
                            $$k = $v;
                        }
                    }
                }

                // load resource usage
                if(in_array($server_type, ['local', 'direct']) && $monitorServerResources === 1) {
                    $sQL = 'SELECT * '
                        .'FROM file_server_resource_usage '
                        .'WHERE file_server_id = :file_server_id '
                        .'ORDER BY id DESC LIMIT 1';
                    $resourceUsage = $db->getRow($sQL, [
                        'file_server_id' => $fileServerId,
                    ]);

                    // calculate other values
                    if($resourceUsage['cpu_load_15_minutes'] > 0 && $resourceUsage['cpu_load_1_minute'] > 0) {
                        $resourceUsage['cpu_load_change'] = number_format((($resourceUsage['cpu_load_15_minutes'] - $resourceUsage['cpu_load_1_minute']) / $resourceUsage['cpu_load_1_minute']) * 100, 2);
                    }
                }

                // prepare availability icon
                if($serverDetails['serverType'] === 'direct' && (int) $serverDetails['enable_availability_checker'] === 1) {
                    if((int) $serverDetails['availability_state'] === 0) {
                        $availabilityIcon = '<i class="availability-icon fa fa-circle offline fa-pulse" aria-hidden="true" title="Offline"></i>';
                    }
                    elseif((int) $serverDetails['availability_state'] === 1) {
                        $availabilityIcon = '<i class="availability-icon fa fa-circle online" aria-hidden="true" title="Online"></i>';
                    }
                }
            }
        }

        // load all server statuses
        $sQL = "SELECT id, label "
            . "FROM file_server_status "
            . "ORDER BY label";
        $statusDetails = $db->getRows($sQL);

        // add any flysystem containers
        $flysystemFileServerTypesOptionsHtml = '';
        if (count($flySystemContainers)) {
            $flysystemFileServerTypesOptionsHtml .= '<optgroup label="Flysystem Adapters">';
            foreach ($flySystemContainers AS $flySystemContainer) {
                $dataFields = $flySystemContainer['expected_config_json'];
                if ($server_type == $flySystemContainer['entrypoint']) {
                    $dataFields = $this->_populateDataFields($dataFields, $server_config_array);
                }
                $flysystemFileServerTypesOptionsHtml .= '<option data-fields="' . AdminHelper::makeSafe($dataFields) . '" value="' . AdminHelper::makeSafe($flySystemContainer['entrypoint']) . '"' . ($server_type == $flySystemContainer['entrypoint'] ? ' SELECTED' : '' ) . '>' . AdminHelper::makeSafe($flySystemContainer['label']) . '</option>';
            }
            $flysystemFileServerTypesOptionsHtml .= '</optgroup>';
        }

        // other options
        $dlAcceleratorOptions = [
            2 => 'XSendFile (Apache Only)',
            1 => 'X-Accel-Redirect (Nginx Only)',
            3 => 'X-LiteSpeed-Location (LiteSpeed Only)',
            0 => 'Disabled',
        ];
        $ftpServerTypes = [
            'linux' => 'Linux (for most)',
            'windows' => 'Windows',
            'windows_alt' => 'Windows Alternative'
        ];

        // get any additional types from plugins
        $additionalServerTypes = PluginHelper::callHookRecursive('adminServerManageAddFormList');
        $countryOptions = $db->getRows('SELECT iso_alpha2, name '
            . 'FROM country_info '
            . 'ORDER BY name');
        $accountTypeOptions = $db->getRows('SELECT id, label '
            . 'FROM user_level '
            . 'ORDER BY level_id');

        // handle page submissions
        if ($request->request->has('submitted')) {
            $server_label = trim($request->request->get('server_label'));
            $status_id = (int) $request->request->get('status_id');
            $server_type = trim($request->request->get('server_type'));
            $storage_path = rtrim(trim($request->request->get('storage_path')), '/') . '/';
            $ftp_host = trim(strtolower($request->request->get('ftp_host')));
            $ftp_port = (int) $request->request->get('ftp_port');
            $ftp_username = trim($request->request->get('ftp_username'));
            $ftp_password = trim($request->request->get('ftp_password'));
            $file_server_domain_name = trim(strtolower($request->request->get('file_server_domain_name')));
            $script_path = trim($request->request->get('script_path'));
            $max_storage_space = str_replace(array(',', '.', '-', 'M', 'm', 'G', 'g', 'k', 'K', 'bytes', '(', ')', 'b', 'B', ' '), '', trim($request->request->get('max_storage_space')));
            $max_storage_space = strlen($max_storage_space) ? $max_storage_space : 0;
            $server_priority = (int) trim($request->request->get('server_priority'));
            $route_via_main_site = 1;
            $ftp_server_type = trim($request->request->get('ftp_server_type'));
            $ftp_passive_mode = trim($request->request->get('ftp_passive_mode'));
            $dlAccelerator = (int) $request->request->get('dlAccelerator');
            $cdn_url = trim($request->request->get('cdn_url'));
            $script_root_path = trim($request->request->get('script_root_path'));
            $geo_upload_countries = is_array($request->request->get('geo_upload_countries'))?$request->request->get('geo_upload_countries'):array();
            $account_upload_types = is_array($request->request->get('account_upload_types'))?$request->request->get('account_upload_types'):array();
            $monitorServerResources = in_array((int)$request->request->get('monitor_server_resources_'.$server_type), [0, 1]) ? (int)$request->request->get('monitor_server_resources_'.$server_type) : 0;
            $enableAvailabilityChecker = in_array((int)$request->request->get('enable_availability_checker'), [0, 1]) ? (int)$request->request->get('enable_availability_checker') : 0;
            $file_server_direct_ip_address = trim($request->request->get('file_server_direct_ip_address'));
            if (strlen($file_server_direct_ip_address) > 0) {
                $file_server_direct_ssh_port = (int) $request->request->get('file_server_direct_ssh_port');
                $file_server_direct_ssh_authentication_type = $request->request->get('file_server_direct_ssh_authentication_type');
                $file_server_direct_ssh_username = trim($request->request->get('file_server_direct_ssh_username'));
                $file_server_direct_ssh_password = trim($request->request->get('file_server_direct_ssh_password'));
                $file_server_direct_ssh_key = trim($request->request->get('file_server_direct_ssh_key'));
            }

            // remove trailing forward slash from path
            if (strlen($script_root_path) > 0) {
                if (substr($script_root_path, strlen($script_root_path) - 1, 1) == '/') {
                    $script_root_path = substr($script_root_path, 0, strlen($script_root_path) - 1);
                }
            }

            // validate submission
            if (strlen($server_label) == 0) {
                AdminHelper::setError(AdminHelper::t("server_label_invalid", "Please specify the server label."));
            }
            elseif ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            elseif ($server_type == 'local') {
                if ((strlen($file_server_direct_ip_address) > 0) && ((ValidationHelper::validIPAddress($file_server_direct_ip_address) == false) && ($file_server_direct_ip_address != 'localhost'))) {
                    AdminHelper::setError(AdminHelper::t("server_file_server_ssh_ipaddress_invalid", "The server IP address is invalid."));
                }
            }
            elseif ($server_type == 'ftp') {
                if (strlen($ftp_host) == 0) {
                    AdminHelper::setError(AdminHelper::t("server_ftp_host_invalid", "Please specify the server ftp host."));
                }
                elseif ($ftp_port == 0) {
                    AdminHelper::setError(AdminHelper::t("server_ftp_port_invalid", "Please specify the server ftp port."));
                }
                elseif (strlen($ftp_username) == 0) {
                    AdminHelper::setError(AdminHelper::t("server_ftp_username_invalid", "Please specify the server ftp username."));
                }
            }
            elseif ($server_type == 'direct') {
                $file_server_domain_name = str_replace(['http://', 'https://'], '', $file_server_domain_name);
                if (strlen($file_server_domain_name) == 0) {
                    AdminHelper::setError(AdminHelper::t("server_file_server_domain_name_empty", "Please specify the file server domain name."));
                }
                elseif (strlen($script_path) == 0) {
                    $script_path = '/';
                }
                elseif (strlen($script_path) != strlen(str_replace(' ', '', $script_path))) {
                    AdminHelper::setError(AdminHelper::t("server_file_server_path", "The file server path can not contain spaces."));
                }
                elseif ((strlen($file_server_direct_ip_address) > 0) && (ValidationHelper::validIPAddress($file_server_direct_ip_address) == false)) {
                    AdminHelper::setError(AdminHelper::t("server_file_server_ssh_ipaddress_invalid", "The server IP address is invalid."));
                }

                // remove trailing forward slash
                if (substr($file_server_domain_name, strlen($file_server_domain_name) - 1, 1) == '/') {
                    $file_server_domain_name = substr($file_server_domain_name, 0, strlen($file_server_domain_name) - 1);
                }

                $file_server_download_proto = $request->request->get('file_server_download_proto');
            }

            if (strlen($cdn_url)) {
                // remove trailing forward slash
                if (substr($cdn_url, strlen($cdn_url) - 1, 1) == '/') {
                    $cdn_url = substr($cdn_url, 0, strlen($cdn_url) - 1);
                }
            }

            if (!AdminHelper::isErrors()) {
                $row = $db->getRow('SELECT id '
                    . 'FROM file_server '
                    . 'WHERE serverLabel = ' . $db->quote($server_label) . ' '
                    . 'AND id != ' . (int)$fileServerId);
                if (is_array($row)) {
                    AdminHelper::setError(AdminHelper::t("server_label_already_in_use", "That server label has already been used, please choose another."));
                }
                else {
                    // load some existing settings
                    $sQL = "SELECT serverAccess "
                        . "FROM file_server "
                        . "WHERE id = " . (int) $fileServerId . " "
                        . "LIMIT 1";
                    $serverDetails = $db->getRow($sQL);
                    $serverAccessArr = [];
                    if ($serverDetails) {
                        // server login data
                        $server_access = $serverDetails['serverAccess'];
                        if (strlen($server_access)) {
                            $server_access = CoreHelper::decryptValue($server_access);
                            $serverAccessArr = json_decode($server_access, true);
                        }
                    }

                    // prepare server config json
                    $serverConfigArr = [];
                    if (substr($server_type, 0, 10) == 'flysystem_') {
                        // loop received params and add them to the array
                        if (is_array($request->request->get('flysystem_config')) && count($request->request->get('flysystem_config'))) {
                            $flysystem_config = $request->request->get('flysystem_config');
                            foreach ($flysystem_config AS $k => $v) {
                                // strip out the $server_type from the variable name
                                $serverConfigArr[str_replace($server_type . '_', '', $k)] = $v;
                            }
                        }
                    }
                    else {
                        $serverConfigArr['ftp_server_type'] = $ftp_server_type;
                        $serverConfigArr['ftp_passive_mode'] = $ftp_passive_mode;
                        $serverConfigArr['file_server_download_proto'] = $file_server_download_proto;
                    }
                    $serverConfigArr['cdn_url'] = str_replace(['http://', 'https://'], '', $cdn_url);

                    // prepare server access json
                    $serverAccessArr['file_server_direct_ip_address'] = $file_server_direct_ip_address;
                    $serverAccessArr['file_server_direct_ssh_port'] = $file_server_direct_ssh_port;
                    $serverAccessArr['file_server_direct_ssh_authentication_type'] = $file_server_direct_ssh_authentication_type;
                    $serverAccessArr['file_server_direct_ssh_username'] = $file_server_direct_ssh_username;
                    if (strlen($file_server_direct_ssh_password)) {
                        $serverAccessArr['file_server_direct_ssh_password'] = $file_server_direct_ssh_password;
                    }
                    if (strlen($file_server_direct_ssh_key)) {
                        $serverAccessArr['file_server_direct_ssh_key'] = $file_server_direct_ssh_key;
                    }

                    if ($fileServerId !== null) {
                        // update the existing record
                        $fileServer = FileServer::loadOneById($fileServerId);
                        $oldFileServer = (array) $fileServer;
                        $fileServer->serverLabel = $server_label;
                        $fileServer->serverType = $server_type;
                        $fileServer->statusId = $status_id;
                        $fileServer->ipAddress = $ftp_host;
                        $fileServer->ftpPort = $ftp_port;
                        $fileServer->ftpUsername = $ftp_username;
                        $fileServer->ftpPassword = $ftp_password;
                        $fileServer->scriptRootPath = $script_root_path;
                        $fileServer->storagePath = $storage_path;
                        $fileServer->fileServerDomainName = $file_server_domain_name;
                        $fileServer->scriptPath = $script_path;
                        $fileServer->maximumStorageBytes = $max_storage_space;
                        $fileServer->priority = $server_priority;
                        $fileServer->routeViaMainSite = $route_via_main_site;
                        $fileServer->serverConfig = json_encode($serverConfigArr);
                        $fileServer->dlAccelerator = $dlAccelerator;
                        $fileServer->serverAccess = CoreHelper::encryptValue(json_encode($serverAccessArr));
                        $fileServer->geoUploadCountries = count($geo_upload_countries)?','.implode(',', $geo_upload_countries).',':null;
                        $fileServer->accountUploadTypes = count($account_upload_types)?','.implode(',', $account_upload_types).',':null;
                        $fileServer->monitor_server_resources = (int) $monitorServerResources;
                        $fileServer->enable_availability_checker = (int) $enableAvailabilityChecker;
                        $fileServer->save();

                        // user action logs
                        UserActionLogHelper::logAdmin('Updated file server', 'ADMIN', 'UPDATE', [
                            'data' => UserActionLogHelper::getChangedData($oldFileServer, $fileServer),
                        ]);

                        AdminHelper::setSuccess('File server \'' . $server_label . '\' updated.');
                    }
                    else {
                        // add the file server
                        $fileServer = FileServer::create();
                        $fileServer->serverLabel = $server_label;
                        $fileServer->serverType = $server_type;
                        $fileServer->ipAddress = $ftp_host;
                        $fileServer->ftpPort = $ftp_port;
                        $fileServer->ftpUsername = $ftp_username;
                        $fileServer->ftpPassword = $ftp_password;
                        $fileServer->statusId = $status_id;
                        $fileServer->scriptRootPath = $script_root_path;
                        $fileServer->storagePath = $storage_path;
                        $fileServer->fileServerDomainName = $file_server_domain_name;
                        $fileServer->scriptPath = $script_path;
                        $fileServer->maximumStorageBytes = $max_storage_space;
                        $fileServer->priority = $server_priority;
                        $fileServer->routeViaMainSite = $route_via_main_site;
                        $fileServer->serverConfig = json_encode($serverConfigArr);
                        $fileServer->dlAccelerator = $dlAccelerator;
                        $fileServer->serverAccess = CoreHelper::encryptValue(json_encode($serverAccessArr));
                        $fileServer->geoUploadCountries = count($geo_upload_countries)?','.implode(',', $geo_upload_countries).',':null;
                        $fileServer->accountUploadTypes = count($account_upload_types)?','.implode(',', $account_upload_types).',':null;
                        $fileServer->monitor_server_resources = (int) $monitorServerResources;
                        $fileServer->enable_availability_checker = (int) $enableAvailabilityChecker;
                        $fileServer->save();

                        // user action logs
                        UserActionLogHelper::logAdmin('Added file server', 'ADMIN', 'ADD', [
                            'data' => UserActionLogHelper::getNewDataFromObject($fileServer),
                        ]);

                        AdminHelper::setSuccess('File server \'' . $server_label . '\' has been added.');

                        $fileServerId = $fileServer->id;
                    }

                    // if server type is "direct", ensure we're using DB sessions
                    if(AdminHelper::isSuccess() && $server_type === 'direct') {
                        $db->query('UPDATE site_config '
                            . 'SET config_value = "Database Sessions" '
                            . 'WHERE config_key = "user_session_type" '
                            . 'LIMIT 1');
                        AdminHelper::setSuccess('As you\'ve configured a "direct" storage server, your user session support has been upgraded to be database driven. You may need to re-login to the admin area if prompted.');
                    }
                }
            }

            // update any flysystem containers
            $flysystemFileServerTypesOptionsHtml = '';
            if (count($flySystemContainers)) {
                $flysystemFileServerTypesOptionsHtml .= '<optgroup label="Flysystem Adapters">';
                foreach ($flySystemContainers AS $flySystemContainer) {
                    $dataFields = $flySystemContainer['expected_config_json'];
                    if ($server_type == $flySystemContainer['entrypoint']) {
                        $dataFields = $this->_populateDataFields($dataFields, $server_config_array);
                    }
                    $flysystemFileServerTypesOptionsHtml .= '<option data-fields="' . AdminHelper::makeSafe($dataFields) . '" value="' . AdminHelper::makeSafe($flySystemContainer['entrypoint']) . '"' . ($server_type == $flySystemContainer['entrypoint'] ? ' SELECTED' : '' ) . '>' . AdminHelper::makeSafe($flySystemContainer['label']) . '</option>';
                }
                $flysystemFileServerTypesOptionsHtml .= '</optgroup>';
            }
        }

        // load template
        return $this->render('admin/server_add_edit.html', [
            'flySystemContainers' => $flySystemContainers,
            'server_label' => $server_label,
            'status_id' => $status_id,
            'server_type' => $server_type,
            'fileServerId' => $fileServerId,
            'ftp_host' => $ftp_host,
            'ftp_port' => $ftp_port,
            'ftp_username' => $ftp_username,
            'ftp_password' => $ftp_password,
            'storage_path' => $storage_path,
            'formType' => $formType,
            'file_server_domain_name' => $file_server_domain_name,
            'script_root_path' => $script_root_path,
            'script_path' => $script_path,
            'max_storage_space' => $max_storage_space,
            'server_priority' => $server_priority,
            'route_via_main_site' => $route_via_main_site,
            'download_accelerator' => $download_accelerator,
            'file_server_direct_ip_address' => $file_server_direct_ip_address,
            'file_server_direct_ssh_port' => $file_server_direct_ssh_port,
            'file_server_direct_ssh_authentication_type' => $file_server_direct_ssh_authentication_type,
            'file_server_direct_ssh_username' => $file_server_direct_ssh_username,
            'file_server_direct_ssh_password' => $file_server_direct_ssh_password,
            'file_server_direct_ssh_key' => $file_server_direct_ssh_key,
            'server_config_array' => $server_config_array,
            'file_server_download_proto' => $file_server_download_proto,
            'cdn_url' => $cdn_url,
            'ftp_server_type' => $ftp_server_type,
            'ftp_passive_mode' => $ftp_passive_mode,
            'statusDetails' => $statusDetails,
            'isDefaultServer' => $isDefaultServer,
            'flysystemFileServerTypesOptionsHtml' => $flysystemFileServerTypesOptionsHtml,
            'dlAcceleratorOptions' => $dlAcceleratorOptions,
            'ftpServerTypes' => $ftpServerTypes,
            'serverPassiveOptions' => array('no' => 'No (default)', 'yes' => 'Yes'),
            'routeViaMainSiteOptions' => array('no' => 'No (default)', 'yes' => 'Yes'),
            'downloadProtocolOptions' => array('http' => 'http', 'https' => 'https'),
            'authenticationTypeOptions' => array('ssh_password' => 'SSH Password', 'ssh_key' => 'SSH Key (Recommended)'),
            'additionalServerTypes' => $additionalServerTypes,
            'countryOptions' => $countryOptions,
            'geo_upload_countries' => $geo_upload_countries,
            'accountTypeOptions' => $accountTypeOptions,
            'resourceUsage' => $resourceUsage,
            'account_upload_types' => $account_upload_types,
            'yesNoOptions' => [0 => 'No', 1 => 'Yes'],
            'monitor_server_resources' => $monitorServerResources,
            'enable_availability_checker' => $enableAvailabilityChecker,
            'availabilityIcon' => $availabilityIcon,
            'form_action' => ADMIN_WEB_ROOT . '/server_add_edit'.($fileServerId ? ('/'.$fileServerId): ''),
        ]);
    }

    private function _populateDataFields($dataFields, $populateData) {
        $dataFieldsArr = json_decode($dataFields, true);
        if (count($dataFieldsArr)) {
            foreach ($dataFieldsArr AS $fieldName => $dataFieldsArrItem) {
                if (isset($populateData[$fieldName])) {
                    $dataFieldsArr[$fieldName]['default'] = $populateData[$fieldName];
                }
            }
        }

        return json_encode($dataFieldsArr);
    }

    public function ajaxServerManageRemove() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $serverId = (int) $request->request->get('serverId');

        $serverLabel = $db->getValue('SELECT serverLabel '
                . 'FROM file_server '
                . 'WHERE id=:id', [
                    'id' => $serverId,
        ]);

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // load any files on the server
            $files = $db->getRows('SELECT file.id, statusId '
                    . 'FROM file '
                    . 'LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = "primary" '
                    . 'LEFT JOIN file_artifact_storage fas ON fa.id = fas.file_artifact_id '
                    . 'WHERE fas.file_server_id = :file_server_id', [
                        'file_server_id' => $serverId,
            ]);
            foreach ($files AS $file) {
                // get file object
                $fileObj = File::loadOneById($file['id']);

                // only active files
                if ($file['statusId'] == 1) {
                    // remove file
                    $fileObj->removeBySystem();
                }

                // remove any statistical data
                $db->query('DELETE FROM stats '
                        . 'WHERE file_id = :fileId', array(
                    'fileId' => $file['id'],
                        )
                );

                // remove any download tracker data
                $db->query('DELETE FROM download_tracker '
                        . 'WHERE file_id = :fileId', array(
                    'fileId' => $file['id'],
                        )
                );

                // remove any file
                $db->query('DELETE FROM file '
                        . 'WHERE id = :fileId', array(
                    'fileId' => $file['id'],
                        )
                );
            }

            // revert to the default file server if this server is currently in use
            if ($serverLabel === SITE_CONFIG_DEFAULT_FILE_SERVER) {
                $defaultFileServerLabel = $db->getValue('SELECT serverLabel '
                    . 'FROM file_server '
                    . 'WHERE is_default = 1');
                if($defaultFileServerLabel) {
                    $db->query('UPDATE site_config '
                        . 'SET config_value = ' . $db->quote($defaultFileServerLabel) . ' '
                        . 'WHERE config_key=\'default_file_server\' '
                        . 'LIMIT 1');
                }
            }

            // delete the server record
            $fileServer = FileServer::loadOneById($serverId);
            $db->query('DELETE FROM file_server '
                    . 'WHERE id = :id', [
                'id' => $serverId,
                ]
            );
            if ($db->affectedRows() == 1) {
                // user action logs
                UserActionLogHelper::logAdmin('Deleted file server', 'ADMIN', 'DELETE', [
                    'data' => UserActionLogHelper::getNewDataFromObject($fileServer),
                ]);

                $result['error'] = false;
                $result['msg'] = 'Server, files and any relating data removed.';
            }
            else {
                $result['error'] = true;
                $result['msg'] = 'Could not remove the file server, please try again later.';
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxServerManageGetServerDetail() {
        // note, does not immediately require admin access
        // pickup request
        $request = $this->getRequest();
        $serverId = (int) $request->request->get('serverId');

        // process csaKeys and authenticate user
        $csaKey1 = $request->query->has('csaKey1') ? trim($request->query->get('csaKey1')) : '';
        $csaKey2 = $request->query->has('csaKey2') ? trim($request->query->get('csaKey2')) : '';
        $dataArr = CrossSiteActionHelper::getData($csaKey1, $csaKey2);
        if (!$dataArr) {
            $this->restrictAdminAccess();
        }

        // else user is fine
        CrossSiteActionHelper::deleteData($csaKey1, $csaKey2);

        $result = [];
        $result['server_doc_root'] = DOC_ROOT;

        // output response
        return $this->renderJson($result);
    }

    public function serverManageDirectGetConfigFile() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // which file
        $fileName = trim($request->query->get('fileName'));
        $contentStr = "";
        if ($this->inDemoMode()) {
            $contentStr .= 'Unavailable in demo mode.';
        }
        else {
            // create content
            switch ($fileName) {
                case '.htaccess':
                    $REWRITE_BASE = trim($request->query->get('REWRITE_BASE'));
                    if (strlen($REWRITE_BASE) == 0) {
                        $REWRITE_BASE = '/';
                    }
                    if (substr($REWRITE_BASE, 0, 1) != '/') {
                        $REWRITE_BASE = '/' . $REWRITE_BASE;
                    }
                    if (substr($REWRITE_BASE, strlen($REWRITE_BASE) - 1, 1) != '/') {
                        $REWRITE_BASE = $REWRITE_BASE . '/';
                    }
                    $contentStr .= "RewriteEngine On\n";
                    $contentStr .= "RewriteBase " . $REWRITE_BASE . "\n";
                    $contentStr .= "RewriteRule ^(.+)\~s$ " . WEB_ROOT . "/$1~s [L]\n";
                    $contentStr .= "RewriteRule ^(.+)\~i$ " . WEB_ROOT . "/$1~i [QSA,L]\n";
                    $contentStr .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
                    $contentStr .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
                    $contentStr .= "RewriteRule ^(.*)$ index.php?_page_url=$1 [QSA]\n";
                    break;
                case '_config.inc.php':
                    $SITE_HOST = trim($_REQUEST['SITE_HOST']);
                    if (substr($SITE_HOST, strlen($SITE_HOST) - 1, 1) == '/') {
                        $SITE_HOST = substr($SITE_HOST, 0, strlen($SITE_HOST) - 1);
                    }
                    $REWRITE_BASE = trim($_REQUEST['REWRITE_BASE']);
                    if (substr($REWRITE_BASE, strlen($REWRITE_BASE) - 1, 1) == '/') {
                        $REWRITE_BASE = substr($REWRITE_BASE, 0, strlen($REWRITE_BASE) - 1);
                    }
                    if (strlen($REWRITE_BASE)) {
                        if (substr($REWRITE_BASE, 0, 1) != '/') {
                            $REWRITE_BASE = '/' . $REWRITE_BASE;
                        }
                    }
                    $REWRITE_BASE = $SITE_HOST . $REWRITE_BASE;
                    $contentStr = file_get_contents(DOC_ROOT . '/_config.inc.php');
					// @TODO - investigate issue. Seems <?php in file downloads now, results in an empty file
					//if(strlen($contentStr) === 0) {
						die('Your PHP settings blocked access to this file. To create it manually follow the guidance on this page - https://support.mfscripts.com/public/kb_view/79/');
					//}
                    $contentStr = $this->_replaceConstantValue('_CONFIG_SITE_HOST_URL', $SITE_HOST, $contentStr);
                    $contentStr = $this->_replaceConstantValue('_CONFIG_SITE_FULL_URL', $REWRITE_BASE, $contentStr);
                    $contentStr = $this->_replaceConstantValue('_CONFIG_CORE_SITE_HOST_URL', _CONFIG_SITE_HOST_URL, $contentStr);
                    $contentStr = $this->_replaceConstantValue('_CONFIG_CORE_SITE_FULL_URL', _CONFIG_SITE_FULL_URL, $contentStr);

                    // if database is localhost, update to main site host
                    if (_CONFIG_DB_HOST == 'localhost') {
                        $contentStr = $this->_replaceConstantValue('_CONFIG_DB_HOST', _CONFIG_SITE_HOST_URL, $contentStr);
                    }

                    // clear password
                    $contentStr = $this->_replaceConstantValue('_CONFIG_DB_PASS', '', $contentStr);
                    break;
                default:
                    return $this->render404();
            }
        }

        // download file
        return $this->renderDownloadFile($contentStr, $fileName);
    }

    private function _replaceConstantValue($constantName, $newValue, $contentStr) {
        $constantName = strtoupper($constantName);
        $newContent = [];
        $contentStrExp = explode("\n", $contentStr);
        foreach ($contentStrExp AS $contentLine) {
            $oldDefineStart = 'define("' . $constantName . '",';
            if (substr($contentLine, 0, strlen($oldDefineStart)) == $oldDefineStart) {
                $newContent[] = $oldDefineStart . "\t\t\"" . str_replace("\"", "\\\"", $newValue) . '");';
            }
            else {
                $newContent[] = $contentLine;
            }
        }

        return implode("\n", $newContent);
    }

    public function serverManageTestFlysystem() {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $serverId = (int) $request->query->get('serverId');
        $contentHtml = '';

        // load server details
        $row = FileHelper::getServerDetailsById($serverId);
        if (!$row) {
            $contentHtml .= TranslateHelper::t("could_not_load_server", "Could not load server details.");
        }
        else {
            $serverConfigArr = '';
            if (strlen($row['serverConfig'])) {
                $serverConfig = json_decode($row['serverConfig'], true);
                if (is_array($serverConfig)) {
                    $serverConfigArr = $serverConfig;
                }
            }

            $error = '';
            $contentHtml .= '<p>' . TranslateHelper::t("file_server_test_flysystem_intro", "Testing connection to file server... ([[[SERVER_LABEL]]])", array('SERVER_LABEL' => $row['serverLabel'])) . '</p>';

            if (strlen($error) == 0) {
                $contentHtml .= '<p>- Setting up Flysystem adapter... ';
                try {
                    $filesystem = FileServerContainerHelper::init($row['id']);
                    if (!$filesystem) {
                        $error = 'Could not setup adapter.';
                    }
                }
                catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully setup adapter.</span></p>';
                $contentHtml .= '<p>- Attempting test upload... ';

                // create test file
                $testFilename = '_test_' . MD5(microtime().CoreHelper::generateRandomHash()) . '.txt';

                try {
                    $rs = $filesystem->write($testFilename, 'Test - Feel free to remove this file.');
                    if (!$rs) {
                        $error = 'Could not upload test file! Please check the file storage settings and try again.';
                    }
                    else {
                        // delete the file we just uploaded
                        $filesystem->delete($testFilename);
                    }
                }
                catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully uploaded then removed test file.</span></p>';
            }

            if (strlen($error) > 0) {
                $contentHtml .= '<span style="color: red; font-weight:bold;">' . $error . '</span></p>';
            }
            else {
                $contentHtml .= '<p style="color: green; font-weight:bold;">- No errors found connecting to "' . $row['serverLabel'] . '".</p>';
            }
        }

        // load template
        return $this->render('admin/server_manage_test_server.html', [
                    'contentHtml' => $contentHtml,
        ]);
    }

    public function serverManageTestDirect() {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $serverId = (int) $request->query->get('serverId');
        $contentHtml = '';

        // load server details
        $row = FileHelper::getServerDetailsById($serverId);
        if (!$row) {
            $contentHtml .= TranslateHelper::t("could_not_load_server", "Could not load server details.");
        }
        else {
            // hydrate file server
            $fileServer = FileServer::hydrateSingleRecord($row);
            $serverConfigArr = '';
            if (strlen($row['serverConfig'])) {
                $serverConfig = json_decode($row['serverConfig'], true);
                if (is_array($serverConfig)) {
                    $serverConfigArr = $serverConfig;
                }
            }

            $error = '';
            $contentHtml .= '<p>- Testing that server and path is available ' . _CONFIG_SITE_PROTOCOL . '://' . $row['fileServerDomainName'] . $row['scriptPath'] . '... ';

            // check site headers
            $responseCode = ServerResourceHelper::getFileServerResponseCode($fileServer);
            if ($responseCode != 200) {
                $error = 'Could not see the file server or the required php files. Response code: ' . $responseCode;
            }

            // update server status
            $fileServer->availability_state = $responseCode === 200 ? 1: 0;
            $fileServer->save();

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully found server.</span></p>';
                $contentHtml .= '<p>- Checking connectivity to the site database from the file server... ';

                // check database connectivity
                $rs = CoreHelper::getRemoteUrlContent($row['fileServerDomainName'] . $row['scriptPath']);
                if (strpos(strtolower($rs), 'failed connecting to the database')) {
                    $error = 'Problem connecting to the main script database from your file server. Ensure the settings in /_config.inc.php are correct and that your MySQL user has privileges to connect remotely.!';
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Database ok.</span></p>';
                $contentHtml .= '<p>- Testing rewrite rules... ';

                // check site headers
                $testUrl = _CONFIG_SITE_PROTOCOL . '://' . $row['fileServerDomainName'] . $row['scriptPath'] . '/terms';
                $headers = get_headers($testUrl);
                $responseCode = substr($headers[0], 9, 3);
                if ($responseCode != 200) {
                    $error = 'Could not validate the rewrite rules. For Apache make sure you upload the .htaccess file and enabled mod rewrite. For Nginx, ensure you\'ve set the rewrite rules in your server conf file. Test url: '.$testUrl;
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Mod Rewrite &amp; .htaccess ok.</span></p>';
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<p>- Setting up server paths in database... ';

                // attempt to get server details, requires login
                $url = CrossSiteActionHelper::appendUrl(_CONFIG_SITE_PROTOCOL . '://' . $row['fileServerDomainName'] . $row['scriptPath'] . ADMIN_FOLDER_NAME . '/ajax/server_manage_get_server_detail');
                $responseJson = CoreHelper::getRemoteUrlContent($url);
                if (strlen($responseJson) == 0) {
                    $error = 'Could not get access to the server paths, no response. Url: ' . $url;
                }

                // attempt to convert to array
                if (strlen($error) == 0) {
                    $responseArr = json_decode($responseJson, true);
                    if (!is_array($responseArr)) {
                        $error = 'Could not convert response into array to read the data. (' . $responseJson . ') Url: ' . $url;
                    }
                    else {
                        // update database
                        FileServerHelper::setDocRootData($serverId, $responseArr['server_doc_root']);
                    }
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Found server information and updated local data.</span></p>';
            }

            if (strlen($error) > 0) {
                $contentHtml .= '<span style="color: red; font-weight:bold;">' . $error . '</span></p>';
            }
            else {
                $contentHtml .= '<p style="color: green; font-weight:bold;">- No errors found using file server ' . $row['fileServerDomainName'] . '.</p>';
            }
        }

        // load template
        return $this->render('admin/server_manage_test_server.html', [
                    'contentHtml' => $contentHtml,
        ]);
    }

    public function serverManageTestFTP() {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $serverId = (int) $request->query->get('serverId');
        $contentHtml = '';

        // load server details
        $row = FileHelper::getServerDetailsById($serverId);
        if (!$row) {
            $contentHtml .= TranslateHelper::t("could_not_load_server", "Could not load server details.");
        }
        else {
            $serverConfigArr = '';
            if (strlen($row['serverConfig'])) {
                $serverConfig = json_decode($row['serverConfig'], true);
                if (is_array($serverConfig)) {
                    $serverConfigArr = $serverConfig;
                }
            }

            $error = '';
            $contentHtml .= '<p>- Making sure ftp functions are available in PHP... ';

            // make sure ftp functions exists
            if (!function_exists('ftp_connect')) {
                $error = 'Could not find PHP ftp functions! Please contact your host to request they\'re enabled.';
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">FTP functions found.</span></p>';
                $contentHtml .= '<p>- Finding file server ' . $row['serverLabel'] . ' on ip ' . $row['ipAddress'] . ' (port: ' . $row['ftpPort'] . ')... ';

                // connect via ftp
                $conn_id = ftp_connect($row['ipAddress'], $row['ftpPort'], 30);
                if ($conn_id === false) {
                    $error = 'Could not connect!';
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully found.</span></p>';
                $contentHtml .= '<p>- Authenticating with stored user \'' . $row['ftpUsername'] . '\' and password [HIDDEN]... ';

                // authenticate
                $login_result = ftp_login($conn_id, $row['ftpUsername'], $row['ftpPassword']);
                if ($login_result === false) {
                    $error = 'Could not authenticate!';
                    // close ftp
                    ftp_close($conn_id);
                }
            }

            if (strlen($error) == 0) {
                if ((isset($serverConfigArr['ftp_passive_mode'])) && ($serverConfigArr['ftp_passive_mode'] == 'yes')) {
                    // enable passive mode
                    ftp_pasv($conn_id, true);
                }

                $contentHtml .= '<span style="color: green;">Successfully authenticated.</span></p>';
                $contentHtml .= '<p>- Changing to storage directory: ' . $row['storagePath'] . '... ';

                // change directory
                if (ftp_chdir($conn_id, $row['storagePath']) === false) {
                    $error = 'Could not find storage directory!';
                    // close ftp
                    ftp_close($conn_id);
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully changed directory.</span></p>';
                $contentHtml .= '<p>- Attempting test upload to: ' . $row['storagePath'] . '... ';

                $testFile = tmpfile();
                if (!$testFile) {
                    $error = 'Could not create tmp file for testing upload!';
                }
                else {
                    // upload test file
                    $testFilename = "_yetishare_test_" . time() . ".txt";
                    fwrite($testFile, 'Yetishare test file.');
                    fseek($testFile, 0);
                    if (!ftp_fput($conn_id, $testFilename, $testFile, FTP_BINARY)) {
                        $error = 'Could not upload a file to ' . $row['storagePath'] . '!';
                    }
                    else {
                        // remove test file
                        ftp_delete($conn_id, $testFilename);
                    }
                    fclose($testFile);
                }
            }

            if (strlen($error) == 0) {
                $contentHtml .= '<span style="color: green;">Successfully uploaded and removed test file.</span></p>';
                // close ftp
                ftp_close($conn_id);
                $contentHtml .= '<p>- Disconnected from ftp.</p>';
            }

            if (strlen($error) > 0) {
                $contentHtml .= '<span style="color: red; font-weight:bold;">' . $error . '</span></p>';
            }
            else {
                $contentHtml .= '<p style="color: green; font-weight:bold;">- No errors found connecting to ' . $row['serverLabel'] . '.</p>';
            }
        }

        // load template
        return $this->render('admin/server_manage_test_server.html', [
                    'contentHtml' => $contentHtml,
        ]);
    }

}
