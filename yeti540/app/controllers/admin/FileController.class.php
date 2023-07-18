<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Helpers\StatsHelper;
use App\Models\File;
use App\Models\FileStatusReason;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\DownloadTrackerHelper;
use App\Helpers\FileHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserActionLogHelper;

class FileController extends AdminBaseController
{

    public function fileManage() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load all users
        $sQL = "SELECT id, username AS selectValue "
                . "FROM users "
                . "ORDER BY username";
        $userDetails = $db->getRows($sQL);

        // load all file servers
        $sQL = "SELECT id, serverLabel "
                . "FROM file_server "
                . "ORDER BY serverLabel";
        $serverDetails = $db->getRows($sQL);

        // load all file status
        $statusDetails = array('active', 'trash', 'deleted');

        // defaults
        $filterText = '';
        if ($request->query->has('filterText')) {
            $filterText = trim($request->query->get('filterText'));
        }

        $filterByStatus = 'active';
        if ($request->query->has('filterByStatus')) {
            $filterByStatus =  $request->query->get('filterByStatus');
        }

        $filterByServer = null;
        if ($request->query->has('filterByServer')) {
            $filterByServer = (int) $request->query->get('filterByServer');
        }

        $filterByUser = null;
        $filterByUserLabel = '';
        if ($request->query->has('filterByUser')) {
            $filterByUser = (int) $request->query->get('filterByUser');
            $filterByUserLabel = $db->getValue('SELECT username '
                    . 'FROM users '
                    . 'WHERE id = ' . (int) $filterByUser . ' '
                    . 'LIMIT 1');
        }

        $filterBySource = null;
        if ($request->query->has('filterBySource')) {
            $filterBySource = $request->query->get('filterBySource');
        }

        $filterByFileId = null;
        if ($request->query->has('filterByFileId')) {
            $filterByFileId = $request->query->get('filterByFileId');
            $filterByStatus = '';
        }

        // load template
        return $this->render('admin/file_manage.html', array(
                    'userDetails' => $userDetails,
                    'serverDetails' => $serverDetails,
                    'statusDetails' => $statusDetails,
                    'filterText' => $filterText,
                    'filterByStatus' => $filterByStatus,
                    'filterByServer' => $filterByServer,
                    'filterByUser' => $filterByUser,
                    'filterByUserLabel' => $filterByUserLabel,
                    'filterBySource' => $filterBySource,
                    'filterByFileId' => $filterByFileId,
                                ));
    }

    public function ajaxFileManage() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;
        $filterByUser = strlen($request->query->get('filterByUser')) ? $request->query->get('filterByUser') : false;
        $filterByServer = strlen($request->query->get('filterByServer')) ? (int) $request->query->get('filterByServer') : false;
        $filterByStatus = strlen($request->query->get('filterByStatus')) ? $request->query->get('filterByStatus') : false;
        $filterBySource = strlen($request->query->get('filterBySource')) ? $request->query->get('filterBySource') : false;
        $filterView = strlen($request->query->get('filterView')) ? $request->query->get('filterView') : 'list';
        $filterByFileId = (int) $request->query->get('filterByFileId') ? (int) $request->query->get('filterByFileId') : false;

        // setup joins
        $joins = [];
        $joins['file_artifact'] = 'LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = "primary"';
        $joins['file_artifact_storage'] = 'LEFT JOIN file_artifact_storage fas ON fa.id = fas.file_artifact_id';

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'originalFilename';
        switch ($sortColumnName) {
            case 'filename':
                $sort = 'file.originalFilename';
                break;
            case 'filesize':
                $sort = 'fa.file_size';
                break;
            case 'date_uploaded':
                $sort = 'file.uploadedDate';
                break;
            case 'downloads':
                $sort = 'file.visits';
                break;
            case 'status':
                $sort = 'file.status';
                break;
            case 'owner':
                $sort = 'users.username';
                $joins['users'] = 'LEFT JOIN users ON file.userId = users.id';
                break;
        }

        $sqlClause = "WHERE 1=1 ";
        if (strlen($filterText)) {
            $filterText = $db->escape($filterText);
            $sqlClause .= "AND (CONCAT('" . _CONFIG_SITE_FULL_URL . "/', file.shortUrl) LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file.originalFilename LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file.uploadedIP LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file.id = '" . $filterText . "')";
        }

        if ($filterByUser) {
            $sqlClause .= " AND users.username = " . $db->quote($filterByUser);
            $joins['users'] = 'LEFT JOIN users ON file.userId = users.id';
        }

        if ($filterByServer) {
            $sqlClause .= " AND fas.file_server_id = " . $filterByServer;
        }

        if ($filterByStatus) {
            $sqlClause .= " AND file.status = " . $db->quote($filterByStatus);
        }

        if ($filterBySource) {
            $sqlClause .= " AND file.uploadSource = " . $db->quote($filterBySource);
        }

        if ($filterByFileId) {
            $sqlClause .= " AND file.id = " . (int) $filterByFileId;
        }

        $totalRS = $db->getValue("SELECT COUNT(1) AS total "
                . "FROM file " . implode(' ', $joins) . " " . $sqlClause);
        $limitedRS = $db->getRows("SELECT file.*, file.status AS label, users.username, fa.file_size, "
                . "(SELECT file_action.id FROM file_action WHERE file_action.file_id = file.id "
                . "AND (file_action.status = 'pending' OR file_action.status='processing') LIMIT 1) AS has_pending_action "
                . "FROM file "
                . "LEFT JOIN users ON file.userId = users.id "
                . "LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = 'primary' "
                . "LEFT JOIN file_artifact_storage fas ON fa.id = fas.file_artifact_id "
                . $sqlClause . " "
                . "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " "
                . "LIMIT " . $iDisplayStart . ", " . $iDisplayLength);

        // preload delete reasons
        $deleteReasons = FileStatusReason::loadAllAsAssocArray();
        
        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                // hydrate our File object
                $file = File::hydrateSingleRecord($row);

                $lRow = [];
                $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/' . $row['extension'] . '.png';
                if (!file_exists(CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/icons/file_types/16px/' . $row['extension'] . '.png')) {
                    $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/_page.png';
                }
                $typeIcon = '<span style="vertical-align: middle;"><img src="' . $icon . '" width="16" height="16" title="' . $row['extension'] . '" alt="' . $row['extension'] . '" style="margin-right: 5px;"/></span>';

                // checkbox
                $checkbox = '<input type="checkbox" id="cbElement' . $row['id'] . '" value="' . $row['id'] . '" name="table_records" class="checkbox flat"/>';
                if ((int) $row['has_pending_action'] > 0) {
                    $checkbox = '';
                }
                if (!in_array($row['status'], ['active', 'trash'])) {
                    $checkbox = '';
                }
                $lRow[] = $checkbox;

                if ($row['status'] == 'active') {
                    if ($filterView == 'list') {
                        // list item
                        $colContent = '<span class="file-listing-view">' . $typeIcon . '<a href="' . FileHelper::getFileUrl($row['id']) . '" target="_blank" title="' . FileHelper::getFileUrl($row['id']) . '">' . AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35)) . '</a></span>';
                    }
                    else {
                        // file thumbnail
                        $previewImageUrlLarge = FileHelper::getIconPreviewImageUrl($file, false, 160, false, 200, 200, 'cropped');
                        $colContent = '<span class="file-thumbnail-view"><a href="' . FileHelper::getFileUrl($row['id']) . '" target="_blank" title="' . FileHelper::getFileUrl($row['id']) . '" style="display:block; text-align: center;"><img src="' . ((substr($previewImageUrlLarge, 0, 4) == 'http') ? $previewImageUrlLarge : (SITE_IMAGE_PATH . '/trans_1x1.gif')) . '" alt="" class="' . ((substr($previewImageUrlLarge, 0, 4) != 'http') ? $previewImageUrlLarge : '#') . '" style="border: 1px solid #ffffff; margin: 2px;"><br/>' . AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35)) . '</a></span>';
                    }

                    $lRow[] .= $colContent;
                }
                else {
                    $lRow[] = $typeIcon . AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35));
                }
                $lRow[] = CoreHelper::formatDate($row['uploadedDate'], SITE_CONFIG_DATE_TIME_FORMAT);
                $lRow[] = (int) $row['file_size'] > 0 ? AdminHelper::formatSize($row['file_size']) : 0;
                $lRow[] = (int) $row['visits'] > 0 ? ((int) $row['visits'] . ' <a href="download_previous?fileId=' . $row['id'] . '"> <span class="fa fa-search" aria-hidden="true"></span></a>') : 0;
                $lRow[] = strlen($row['username']) ? ('<a title="IP: ' . AdminHelper::makeSafe($row['uploadedIP']) . '" href="' . ADMIN_WEB_ROOT . '/user_manage?filterByAccountId=' . AdminHelper::makeSafe($row['userId']) . '">' . AdminHelper::makeSafe($row['username']) . ' <span class="fa fa-search" aria-hidden="true"></span></a>') : '<span style="color: #aaa;" title="[no login]"><a href="' . ADMIN_WEB_ROOT . '/file_manage?filterText=' . AdminHelper::makeSafe($row['uploadedIP']) . '">' . AdminHelper::makeSafe($row['uploadedIP']) . ' <span class="fa fa-search" aria-hidden="true"></span></a></span>';
                $statusRow = '<span class="statusText' . str_replace(" ", "", AdminHelper::makeSafe(ucwords($row['label']))) . '"';
                if((int)$row['status_reason_id'] && isset($deleteReasons[$row['status_reason_id']])) {
                    $statusRow .= ' data-toggle="tooltip" data-placement="top" data-original-title="'. AdminHelper::makeSafe(ucwords($deleteReasons[$row['status_reason_id']])).'"';
                }
                $statusRow .= '>' . ucwords($row['label']) . '</span>';
                $lRow[] = $statusRow;

                $linkStr = '';
                $links = [];
                if ($row['status'] === 'active') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="edit" onClick="editFile(' . (int) $row['id'] . '); return false;"><span class="fa fa-pencil" aria-hidden="true"></span></a>';
                }
                $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="stats" href="' . FileHelper::getFileStatisticsUrl($row['id']) . '" target="_blank"><span class="fa fa-pie-chart text-default" aria-hidden="true"></span></a>';
                $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="file history" href="user_action_log?filterByFileId='.$row['id'].'"><span class="fa fa-file-text" aria-hidden="true"></span></a>';
                if (in_array($row['status'], ['active', 'trash'])) {
                    $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="remove" href="#" onClick="confirmRemoveFile(' . (int) $row['id'] . '); return false;"><span class="fa fa-trash text-danger" aria-hidden="true"></span></a>';
                }
                if ($row['status'] === 'active') {
                    $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="download" href="' . FileHelper::getFileUrl($row['id']) . '" target="_blank"><span class="fa fa-download" aria-hidden="true"></span></a>';
                }
                if (strlen($row['adminNotes'])) {
                    $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="notes" href="#" onClick="showNotes(\'' . str_replace(array("\n", "\r"), "<br/>", AdminHelper::makeSafe(str_replace("'", "\"", $row['adminNotes']))) . '\'); return false;"><span class="fa fa-file-text-o" aria-hidden="true"></span></a>';
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

    public function exportCSV() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // allow for 10 minutes for the export
        set_time_limit(60 * 10);

        // resulting csv data
        $formattedCSVData = [];

        // header 
        $lArr = [];
        $lArr[] = "Id";
        $lArr[] = "Filename";
        $lArr[] = "Url";
        $lArr[] = "Filesize";
        $lArr[] = "Total Downloads";
        $lArr[] = "Uploaded Date";
        $lArr[] = "Last Accessed";
        $formattedCSVData[] = "\"" . implode("\",\"", $lArr) . "\"";

        // get all url data
        $urlData = $db->getRows("SELECT file.*, fa.file_size "
                . "FROM file "
                . "LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = 'primary' "
                . "ORDER BY uploadedDate asc");
        foreach ($urlData AS $row) {
            $lArr = [];
            $lArr[] = $row['id'];
            $lArr[] = $row['originalFilename'];
            $lArr[] = FileHelper::getFileUrl($row['id']);
            $lArr[] = $row['file_size'];
            $lArr[] = $row['visits'];
            $lArr[] = ($row['uploadedDate'] != "0000-00-00 00:00:00") ? CoreHelper::formatDate($row['uploadedDate']) : "";
            $lArr[] = ($row['lastAccessed'] != "0000-00-00 00:00:00") ? CoreHelper::formatDate($row['lastAccessed']) : "";

            $formattedCSVData[] = "\"" . implode("\",\"", $lArr) . "\"";
        }

        // user action logs
        UserActionLogHelper::logAdmin('File data exported as CSV via admin', 'ADMIN', 'READ');

        return $this->renderDownloadFile(implode("\n", $formattedCSVData), "file_data.csv");
    }

    public function ajaxFileManageAutoComplete() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // get request
        $users = [];
        $autoComplete = $request->query->has('filterByUser') ? trim($request->query->get('filterByUser')) : '';
        if (strlen($autoComplete) < 1) {
            return $this->renderJson($users);
        }

        $returnQuery = $db->getRows("SELECT username "
                . "FROM users "
                . "WHERE username LIKE '%" . $db->escape($autoComplete) . "%' "
                . "ORDER BY username ASC "
                . "LIMIT 50");
        foreach ($returnQuery AS $return) {
            $users[] = $return['username'];
        }

        // output response
        return $this->renderJson($users);
    }

    public function ajaxFileManageBulkDelete() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $request = $this->getRequest();

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // pick up file ids
        $fileIds = $request->request->get('fileIds');
        $deleteData = false;
        if ($request->request->has('deleteData')) {
            $deleteData = $request->request->get('deleteData') == 'false' ? false : true;
        }

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            $totalRemoved = 0;

            // load files
            if (count($fileIds)) {
                foreach ($fileIds AS $fileId) {
                    // load file and process if active
                    $file = File::loadOneById($fileId);
                    if ($file) {
                        $rs = false;
                        if ($deleteData == true) {
                            // user action logs
                            UserActionLogHelper::logAdmin('File and data deleted via admin', 'ADMIN', 'DELETE', [
                                'file_id' => $file->id,
                                'data' => (array)$file,
                            ]);

                            // delete
                            $rs = $file->deleteFileIncData();
                        }
                        elseif ($file->status == 'active') {
                            // user action logs
                            UserActionLogHelper::logAdmin('File deleted via admin', 'ADMIN', 'DELETE', [
                                'file_id' => $file->id,
                                'data' => (array)$file,
                            ]);

                            // remove
                            $rs = $file->removeByAdmin();
                        }

                        if ($rs) {
                            $totalRemoved++;
                        }
                    }
                }
            }

            $result['msg'] = 'Removed ' . $totalRemoved . ' file' . ($totalRemoved != 1 ? 's' : '') . '.';
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxFileManageEditForm() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // make sure we have the file to edit
        if (!$request->request->has('gEditFileId')) {
            $result = [];
            $result['error'] = true;
            $result['msg'] = 'File not found.';

            return $this->renderJson($result);
        }

        // load file
        $file = File::loadOneById((int) $request->request->get('gEditFileId'));
        if (!$file) {
            $result = [];
            $result['error'] = true;
            $result['msg'] = 'File not found.';

            return $this->renderJson($result);
        }

        // load file server
        $fileServers = $file->loadAllFileServers();
        $serverLabels = [];
        foreach($fileServers as $fileServer) {
            $serverLabels[] = $fileServer['serverLabel'];
        }

        // load all user types
        $userTypes = $db->getRows('SELECT id, label '
                . 'FROM user_level '
                . 'ORDER BY id ASC');
        $privacyTypes = array(0 => 'Private', 1 => 'Public');
        
        // find out whether file is shared
        $fileIsDuplicateText = 'No - none found.';
        if($file->isDuplicate()) {
            $fileIsDuplicateText = 'Yes - Duplicate(s) found.';
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/file_manage_edit_form.html', array(
            'file' => $file,
            'fileIsDuplicateText' => $fileIsDuplicateText,
            'serverLabels' => $serverLabels,
            'userTypes' => $userTypes,
            'privacyTypes' => $privacyTypes,
            'fileArtifacts' => $file->getFileArtifacts(),
        ));

        // output response
        return $this->renderJson($result);
    }

    public function ajaxFileManageEditProcess() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // get params
        $existing_file_id = (int) $request->request->get('existing_file_id');
        $filename = CoreHelper::cleanTextareaInput($request->request->get('filename'));
        $file_owner = trim($request->request->get('file_owner'));
        $short_url = trim($request->request->get('short_url'));
        $enablePassword = ($request->request->get('enablePassword') == 'true' || $request->request->get('enablePassword') == '1') ? true : false;
        $password = trim($request->request->get('password'));
        $mime_type = trim($request->request->get('mime_type'));
        $min_user_level = trim($request->request->get('min_user_level'));
        $admin_notes = trim($request->request->get('admin_notes'));
        $file_description = CoreHelper::cleanTextareaInput($request->request->get('file_description'));
        $file_keywords = CoreHelper::cleanTextareaInput($request->request->get('file_keywords'));
        $is_public = (int) $request->request->get('is_public') === 0 ? 0 : 1;
        $purge_download_stats = (int) $request->request->get('purge_download_stats') === 0 ? 0 : 1;

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // load file
        $file = File::loadOneById($existing_file_id);
        if (!$file) {
            $result['error'] = true;
            $result['msg'] = 'Failed loading file to edit.';

            return $this->renderJson($result);
        }

        // validate submission
        if (strlen($filename) == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("please_enter_the_filename", "Please enter the filename");
        }
        elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // double check for files with the same name in the same folder
            $foundExistingFile = (int) $db->getValue('SELECT COUNT(id) '
                            . 'FROM file '
                            . 'WHERE originalFilename = ' . $db->quote($filename . '.' . $file->extension) . ' '
                            . 'AND status = "active" '
                            . 'AND folderId ' . ((int) $file->folderId > 0 ? ('=' . $file->folderId) : 'IS NULL') . ' '
                            . 'AND id != ' . (int) $file->id);
            if ($foundExistingFile) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("active_file_with_same_name_found", "Active file with same name found in the same folder. Please ensure the file name is unique.");
            }
        }

        if (strlen($result['msg']) == 0) {
            // lookup user id if set
            $userId = null;
            if (strlen($file_owner)) {
                $userId = $db->getValue('SELECT id '
                        . 'FROM users '
                        . 'WHERE username = ' . $db->quote($file_owner) . ' '
                        . 'LIMIT 1');
                if (!$userId) {
                    $result['error'] = true;
                    $result['msg'] = AdminHelper::t("edit_file_could_not_find_username", "Could not find file owner username. Leave blank to set the file with no owner.");
                }
            }
        }

        if (strlen($result['msg']) == 0) {
            // make sure there's no disallowed characters in the short url
            if (ValidationHelper::containsInvalidCharacters($short_url, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12345678900')) {
                $result['error'] = true;
                $result['msg'] = AdminHelper::t("edit_file_short_url_is_invalid", "Short url structure is invalid. Only alphanumeric values are allowed.");
            }
            else {
                // check short url not already used (for case sensitive use "BINARY shortUrl = ", 
                // although it will ignore indexes). Search code for "optional BINARY"
                // to find the other instance.
                $existingFileCheck = $db->getValue('SELECT id '
                        . 'FROM file '
                        . 'WHERE id != ' . (int) $file->id . ' '
                        . 'AND shortUrl = ' . $db->quote($short_url));
                if ($existingFileCheck) {
                    $result['error'] = true;
                    $result['msg'] = AdminHelper::t("edit_file_file_with_same_short_url_exist", "Short url already exists on another file.");
                }
            }
        }

        if (strlen($result['msg']) == 0) {
            $accessPassword = null;
            if ($enablePassword === true) {
                $accessPassword = $file->accessPassword != null ? $file->accessPassword : null;
                if ((strlen($password)) && ($password != '**********')) {
                    $accessPassword = md5($password);
                }
            }
        }

        // no errors
        if (strlen($result['msg']) == 0) {
            // keep old data for later
            $oldFile = (array) $file;

            // update the existing record
            $file->originalFilename = $filename . '.' . $file->extension;
            $file->userId = $userId;
            $file->shortUrl = $short_url;
            $file->accessPassword = $accessPassword;
            $file->minUserLevel = strlen($min_user_level) ? (int) $min_user_level : null;
            $file->adminNotes = $admin_notes;
            $file->description = $file_description;
            $file->keywords = $file_keywords;
            $file->isPublic = $is_public;
            $file->save();

            // user action logs
            UserActionLogHelper::logAdmin('File edited via admin', 'ADMIN', 'UPDATE', [
                'file_id' => $file->id,
                'data' => UserActionLogHelper::getChangedData($oldFile, $file),
            ]);

            // update the primary file artifact
            $primaryFileArtifact = $file->getPrimaryArtifact();
            $primaryFileArtifact->file_type = $mime_type;
            $primaryFileArtifact->save();

            // purge stats
            if($purge_download_stats === 1) {
                StatsHelper::purgeStatsByFileId($file->id);

                // user action logs
                UserActionLogHelper::logAdmin('File stats purged via admin', 'ADMIN', 'DELETE', [
                    'file_id' => $file->id,
                ]);
            }

            $result['error'] = false;
            $result['msg'] = 'File \'' . $file->originalFilename . '\' updated.';
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxUpdateFileState() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $fileId = (int) $request->request->get('fileId');
        $statusId = (int) $request->request->get('statusId');
        $adminNotes = $request->request->has('adminNotes') ? trim($request->request->get('adminNotes')) : '';
        $blockUploads = (int) $request->request->get('blockUploads');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // load file
            $file = File::loadOneById($fileId);
            if (!$file) {
                $result['error'] = true;
                $result['msg'] = 'Could not locate the file.';

                return $this->renderJson($result);
            }

            // check for removal
            if (($statusId == 3) || ($statusId == 4)) {
                // block file if it's requested
                if ((int) $blockUploads === 1) {
                    $file->blockFutureUploads();
                }

                // user action logs
                UserActionLogHelper::logAdmin('File deleted via admin', 'ADMIN', 'DELETE', [
                    'file_id' => $file->id,
                    'data' => (array)$file,
                ]);

                // remove
                $file->removeByAdmin();
                
                // store reason
                $file->status_reason_id = $statusId;
            }

            $result['error'] = false;
            $result['msg'] = 'File \'' . $file->originalFilename . '\' removed.';

            $file->adminNotes = $adminNotes;
            $file->save();

            if ((int) $blockUploads == 1) {
                $result['msg'] .= ' The file content hash was also added to the block list, so the same file can not be re-uploaded.';
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function fileManageActionQueue() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // process cancels
        if ($request->query->has('cancel')) {
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            else {
                $db->query('UPDATE file_action '
                        . 'SET status = \'cancelled\', last_updated=NOW(), '
                        . 'status_msg=\'Cancelled\' '
                        . 'WHERE id = ' . (int) $request->query->get('cancel') . ' '
                        . 'AND status = \'pending\' '
                        . 'LIMIT 1');

                // user action logs
                UserActionLogHelper::logAdmin('Cancelled file action entry', 'ADMIN', 'UPDATE', [
                    'file_action_id' => (int) $request->query->get('cancel'),
                ]);
            }
        }

        // load all servers
        $sQL = "SELECT id, serverLabel "
                . "FROM file_server "
                . "ORDER BY serverLabel";
        $serverDetails = $db->getRows($sQL);

        // prepare status
        $statusDetails = array('pending', 'processing', 'failed', 'complete', 'cancelled');

        // defaults
        $filterText = '';
        if ($request->query->has('filterText')) {
            $filterText = trim($request->query->get('filterText'));
        }

        $filterByStatus = '';
        if ($request->query->has('filterByStatus')) {
            $filterByStatus = $request->query->get('filterByStatus');
        }

        $filterByServer = null;
        if ($request->query->has('filterByServer')) {
            $filterByServer = (int) $request->query->get('filterByServer');
        }

        // load template
        return $this->render('admin/file_manage_action_queue.html', array(
                    'serverDetails' => $serverDetails,
                    'statusDetails' => $statusDetails,
                    'filterText' => $filterText,
                    'filterByStatus' => $filterByStatus,
                    'filterByServer' => $filterByServer,
                                ));
    }

    public function ajaxFileManageActionQueue() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;
        $filterByStatus = strlen($request->query->get('filterByStatus')) ? $request->query->get('filterByStatus') : false;
        $filterByServer = strlen($request->query->get('filterByServer')) ? (int) $request->query->get('filterByServer') : false;

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'id '.$db->escape($sSortDir_0).', date_created';
        switch ($sortColumnName) {
            case 'date_added':
                $sort = 'id '.$db->escape($sSortDir_0).', date_created';
                break;
            case 'file_path':
                $sort = 'file_path';
                break;
            case 'server':
                $sort = 'file_server.serverLabel';
                break;
            case 'file_action':
                $sort = 'file_action';
                break;
            case 'status':
                $sort = 'status';
                break;
        }

        $sqlClause = "WHERE 1=1 ";

        if ($filterByStatus) {
            $sqlClause .= " AND file_action.status = " . $db->quote($filterByStatus);
        }

        if ($filterByServer) {
            $sqlClause .= " AND file_action.server_id = " . $filterByServer;
        }

        if ($filterText) {
            $filterText = $db->escape($filterText);
            $sqlClause .= " AND (file_action.file_path LIKE '%" . $filterText . "%')";
        }

        $totalRS = $db->getValue("SELECT COUNT(file_action.id) AS total "
                . "FROM file_action "
                . "LEFT JOIN file_server ON file_action.server_id = file_server.id " . $sqlClause);
        $limitedRS = $db->getRows("SELECT file_server.serverLabel, file_action.* "
                . "FROM file_action LEFT JOIN file_server "
                . "ON file_action.server_id = file_server.id " . $sqlClause . " "
                . "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " "
                . "LIMIT " . $iDisplayStart . ", " . $iDisplayLength);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $lRow = [];
                $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/system/16x16/';
                switch ($row['status']) {
                    case 'complete':
                        $icon .= 'accept.png';
                        break;
                    case 'failed':
                        $icon .= 'block.png';
                        break;
                    case 'processing':
                        $icon .= 'clock.png';
                        break;
                    case 'cancelled':
                        $icon .= 'delete_page.png';
                        break;
                    case 'restore':
                        $icon .= 'restore.png';
                        break;
                    default:
                        $icon .= 'clock.png';
                        break;
                }

                $typeIcon = '<span style="vertical-align: middle;"><img src="' . $icon . '" width="16" height="16" title="' . $row['status'] . '" alt="' . $row['status'] . '" style="margin-right: 5px;"/></span>';
                $lRow[] = $typeIcon;
                $lRow[] = CoreHelper::formatDate($row['date_created'], SITE_CONFIG_DATE_TIME_FORMAT);
                $lRow[] = AdminHelper::makeSafe($row['serverLabel']);
                $lRow[] = AdminHelper::makeSafe($row['file_path']);
                $lRow[] = AdminHelper::makeSafe(UCWords($row['file_action']));
                $statusRow = '<span class="statusText' . str_replace(" ", "", AdminHelper::makeSafe(UCWords($row['status']))) . '"';
                $statusRow .= '>' . UCWords($row['status']) . '</span>';
                if ((strlen($row['action_date'])) && ($row['status'] == 'pending')) {
                    $statusRow .= '<br/><span style="color: #999999;">(' . CoreHelper::formatDate($row['action_date']) . ')</span>';
                }
                $lRow[] = $statusRow;

                $links = [];
                if ($row['status'] == 'pending') {
                    $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="cancel" href="#" onClick="cancelItem(' . $row['id'] . '); return false;"><span class="fa fa-remove text-danger" aria-hidden="true"></span></a>';
                }
                if (($row['status'] == 'complete') || ($row['status'] == 'failed') || ($row['status'] == 'cancelled')) {
                    if ($row['status_msg'] != NULL) {
                        $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="info" href="#" onClick="alert(\'Result: ' . UCWords($row['status']) . '\n\n' . AdminHelper::makeSafe(UCWords($row['status_msg'])) . '\'); return false;"><span class="fa fa-info-circle" aria-hidden="true"></span></a>';
                    }
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

    public function ajaxFileManageMoveForm() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load file servers
        $fileServers = $db->getRows('SELECT file_server.id, file_server.serverLabel '
                . 'FROM file_server '
                . 'WHERE statusId=2 '
                . 'AND serverType IN (\'local\', \'direct\') '
                . 'ORDER BY serverLabel ASC');
        if (count($fileServers) == 0) {
            $result = [];
            $result['error'] = false;
            $result['msg'] = '';
            $result['html'] = 'No active servers.';

            return $this->renderJson($result);
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/file_manage_move_form.html', array(
            'fileServers' => $fileServers,
        ));

        // output response
        return $this->renderJson($result);
    }

    public function ajaxFileManageMoveProcess() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // get params
        $gFileIds = $request->request->get('gFileIds');
        $serverIds = $request->request->get('serverIds');

        // preload file servers for lookups
        $fileServerArr = [];
        $fileServers = $db->getRows('SELECT file_server.id, file_server.serverType '
                . 'FROM file_server '
                . 'WHERE (statusId=2 OR statusId=3) '
                . 'AND serverType IN (\'local\', \'direct\') '
                . 'ORDER BY serverLabel ASC');
        foreach ($fileServers AS $fileServer) {
            $fileServerArr[] = $fileServer['id'];
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // validate submission
        if ($serverIds == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("please_select_the_server", "Please select the new server.");
        }
        elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }

        $errorTracker = [];
        if (strlen($result['msg']) == 0) {
            // load server details
            $server = FileHelper::loadServerDetails($serverIds);

            // loop files and add to move queue
            foreach ($gFileIds AS $gFileId) {
                $file = File::loadOneById($gFileId);

                // ignore files on non local or direct file servers
                if (in_array($file->getPrimaryServerId(), $fileServerArr)) {
                    // user action logs
                    UserActionLogHelper::logAdmin('Scheduled file server moved via admin', 'ADMIN', 'UPDATE', [
                        'file_id' => $file->id,
                    ]);

                    $file->scheduleServerMove($server['id']);
                }
            }

            // finish up
            $result['error'] = false;
            $result['msg'] = 'File move has been scheduled. The file(s) will be moved when processed by the <a href="file_manage_action_queue">file action queue</a>.';
        }

        // output response
        return $this->renderJson($result);
    }

    public function downloadCurrent() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // clear any expired download trackers
        DownloadTrackerHelper::clearTimedOutDownloads();
        DownloadTrackerHelper::purgeDownloadData();

        // load template
        return $this->render('admin/download_current.html', array(
                                ));
    }

    public function ajaxDownloadCurrent() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'download_tracker.date_started';
        switch ($sortColumnName) {
            case 'date_started':
                $sort = 'download_tracker.date_started';
                break;
            case 'ip_address':
                $sort = 'download_tracker.ip_address';
                break;
            case 'file_name':
                $sort = 'file.originalFilename';
                break;
            case 'file_size':
                $sort = 'fa.file_size';
                break;
            case 'status':
                $sort = 'download_tracker.status';
                break;
            case 'total_threads':
                $sort = 'COUNT(download_tracker.id)';
                break;
        }

        $sqlClause = "WHERE download_tracker.status='downloading' ";

        $sQL = "SELECT COUNT(download_tracker.id) AS total_threads, download_tracker.date_started, "
                . "download_tracker.ip_address, download_tracker.status, file.originalFilename, "
                . "fa.file_size, file.shortUrl, file.extension ";
        $sQL .= "FROM download_tracker ";
        $sQL .= "LEFT JOIN file ON download_tracker.file_id = file.id ";
        $sQL .= "LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = 'primary' ";
        $sQL .= $sqlClause . " ";
        $sQL .= "GROUP BY download_tracker.ip_address, download_tracker.file_id ";
        $totalRS = $db->numRows($sQL);

        $sQL .= "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " ";
        $sQL .= "LIMIT " . $iDisplayStart . ", " . $iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/' . $row['extension'] . '.png';
                if (!file_exists(CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/icons/file_types/16px/' . $row['extension'] . '.png')) {
                    $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/_page.png';
                }
                $lRow = [];
                $lRow[] = '<img src="' . $icon . '" width="16" height="16" title="' . $row['extension'] . '" alt="' . $row['extension'] . '"/>';
                $lRow[] = CoreHelper::formatDate($row['date_started'], SITE_CONFIG_DATE_TIME_FORMAT);
                $lRow[] = strlen($row['download_username']) ? (AdminHelper::makeSafe($row['download_username']) . '<br/>' . AdminHelper::makeSafe($row['ip_address'])) : '<span style="color: #aaa;" title="[not logged in]">' . AdminHelper::makeSafe($row['ip_address']) . '</span>';
                $lRow[] = '<a href="file_manage?filterText=/' . $row['shortUrl'] . '" title="' . (FileHelper::getFileUrl($row['id'])) . '">' . AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35)) . '</a>';
                $lRow[] = AdminHelper::makeSafe(AdminHelper::formatSize($row['file_size']));
                $lRow[] = (int) $row['total_threads'];
                $lRow[] = '<span class="statusText' . str_replace(" ", "", UCWords($row['status'])) . '">' . $row['status'] . '</span>';

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

    public function downloadPrevious() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load file
        $file = File::loadOneById((int) $request->query->get('fileId'));

        // load template
        return $this->render('admin/download_previous.html', array(
                    'file' => $file,
                                ));
    }

    public function ajaxDownloadPrevious() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load file for later
        $file = File::loadOneById($request->query->get('fileId'));

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'stats.download_date';
        switch ($sortColumnName) {
            case 'date_started':
                $sort = 'stats.download_date';
                break;
            case 'ip_address':
                $sort = 'stats.ip';
                break;
            case 'username':
                $sort = 'users.username';
                break;
        }

        $sqlClause = "WHERE stats.file_id = " . (int) $file->id;

        $sQL = "SELECT stats.download_date, stats.ip, stats.user_id, users.username ";
        $sQL .= "FROM stats ";
        $sQL .= "LEFT JOIN users ON stats.user_id = users.id ";
        $sQL .= $sqlClause . " ";
        $totalRS = $db->numRows($sQL);

        $sQL .= "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " ";
        $sQL .= "LIMIT " . $iDisplayStart . ", " . $iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $lRow = [];
                $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/' . $file->extension . '.png';
                if (!file_exists(CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/icons/file_types/16px/' . $file->extension . '.png')) {
                    $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/_page.png';
                }
                $lRow[] = '<img src="' . $icon . '" width="16" height="16" title="' . $file->extension . '" alt="' . $file->extension . '"/>';
                $lRow[] = CoreHelper::formatDate($row['download_date'], SITE_CONFIG_DATE_TIME_FORMAT);
                $lRow[] = AdminHelper::makeSafe($row['ip']);
                $lRow[] = strlen($row['username']) ? (AdminHelper::makeSafe($row['username'])) : '<span style="color: #aaa;" title="[not logged in]">[not logged in]</span>';

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
}
