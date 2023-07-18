<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Models\FileFolder;
use App\Models\FileFolderShare;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileFolderHelper;
use App\Helpers\SharingHelper;
use App\Helpers\UserActionLogHelper;

class SharingController extends AdminBaseController
{

    public function sharingManage()
    {
        // admin only
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load template
        return $this->render('admin/sharing_manage.html', [
            'addServerTrigger' => $request->query->has('add'),
        ]);
    }

    public function ajaxSharingManage()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int)$request->query->get('iDisplayLength');
        $iDisplayStart = (int)$request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;
        $iSortCol_0 = (int)$request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'file_folder_share.date_created';
        switch ($sortColumnName) {
            case 'by_user':
                $sort = 'by_user.username';
                break;
            case 'to_user':
                $sort = 'to_user.username';
                break;
            case 'shared_items':
                $sort = '(SELECT count(id) FROM file_folder_share_item WHERE file_folder_share_id = file_folder_share.id)';
                break;
            case 'access_level':
                $sort = 'file_folder_share.share_permission_level';
                break;
            case 'is_global':
                $sort = 'file_folder_share.is_global';
                break;
            case 'last_accessed':
                $sort = 'file_folder_share.last_accessed';
                break;
        }

        $sqlClause = "WHERE 1=1 ";
        if ($filterText) {
            $filterText = $db->escape($filterText);
            $sqlClause .= "AND (access_key = '".$filterText."' OR ";
            $sqlClause .= "by_user.username = '".$filterText."' OR ";
            $sqlClause .= "to_user.username = '".$filterText."')";
        }

        $sQL = 'SELECT COUNT(*) AS total '
            .'FROM file_folder_share '
            .'LEFT JOIN users by_user ON file_folder_share.created_by_user_id = by_user.id '
            .'LEFT JOIN users to_user ON file_folder_share.shared_with_user_id = to_user.id '
            .$sqlClause.' ';
        $totalRS = $db->getValue($sQL);

        $sQL = 'SELECT file_folder_share.*, '
            .'(SELECT count(id) FROM file_folder_share_item WHERE file_folder_share_id = file_folder_share.id) AS total_shared_items, '
            .'by_user.username AS by_username, to_user.username AS to_username '
            .'FROM file_folder_share '
            .'LEFT JOIN users by_user ON file_folder_share.created_by_user_id = by_user.id '
            .'LEFT JOIN users to_user ON file_folder_share.shared_with_user_id = to_user.id '
            .$sqlClause.' ';

        $sQL .= "ORDER BY ".$sort." ".$db->escape($sSortDir_0)." ";
        $sQL .= "LIMIT ".$iDisplayStart.", ".$iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS as $row) {
                // get the FileFolderShare object for the url later
                $fileFolderShare = FileFolderShare::hydrateSingleRecord($row);

                $lRow = [];

                $imagePath = CORE_ASSETS_ADMIN_WEB_ROOT.'/images/icons/server/16/'.$row['serverType'].'.png';
                if (!file_exists(CORE_ASSETS_ADMIN_DIRECTORY_ROOT.'/images/icons/server/16/'.$row['serverType'].'.png')) {
                    $imagePath = CORE_ASSETS_ADMIN_WEB_ROOT.'/images/icons/server/16/local.png';
                }
                $lRow[] = '<img src="'.$imagePath.'" width="16" height="16" title="'.UCWords(AdminHelper::makeSafe(str_replace('_',
                        ' ', $row['serverType']))).'" alt="'.UCWords(AdminHelper::makeSafe(str_replace('_', ' ',
                        $row['serverType']))).'"/>';

                $lRow[] = CoreHelper::formatDate($row['date_created']);

                $lRow[] = '<a href="'.ADMIN_WEB_ROOT.'/user_edit/'.AdminHelper::makeSafe($row['created_by_user_id']).'">'.AdminHelper::makeSafe($row['by_username']).' <span class="fa fa-search" aria-hidden="true"></span></a>';
                $lRow[] = $row['shared_with_user_id'] !== null ? ('<a href="'.ADMIN_WEB_ROOT.'/user_edit/'.AdminHelper::makeSafe($row['shared_with_user_id']).'">'.AdminHelper::makeSafe($row['to_username']).' <span class="fa fa-search" aria-hidden="true"></span></a>') : '<span style="color: #ccc;">[non-account]</span>';

                $lRow[] = (int)$row['total_shared_items'];
                $lRow[] = AdminHelper::makeSafe(ucwords(str_replace('_', ' & ', $row['share_permission_level'])));
                $lRow[] = ((int)$row['is_global'] === 1) ? 'Yes' : '-';
                $lRow[] = CoreHelper::formatDate($row['last_accessed']);

                $links = [];
                if ($row['shared_with_user_id'] === null) {
                    $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="view share" href="'.$fileFolderShare->getFullSharingUrl().'" target="_blank"><span class="fa fa-link" aria-hidden="true"></span></a>';
                }
                $links[] = '<a class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="remove" href="#" onclick="confirmRemoveSharingLink('.(int)$row['id'].'); return false;"><span class="fa fa-trash text-danger" aria-hidden="true"></span></a>';

                $linkStr = '<div class="btn-group">'.implode(" ", $links).'</div>';
                $lRow[] = $linkStr;

                $data[] = $lRow;
            }
        }

        $resultArr = [];
        $resultArr["sEcho"] = intval($_GET['sEcho']);
        $resultArr["iTotalRecords"] = (int)$totalRS;
        $resultArr["iTotalDisplayRecords"] = $resultArr["iTotalRecords"];
        $resultArr["aaData"] = $data;

        // output response
        return $this->renderJson($resultArr);
    }

    public function ajaxSharingManageRemove()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $sharingId = (int)$request->request->get('sharingId');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        } else {
            // load share for logging later
            $fileFolderShare = FileFolderShare::loadOneById($sharingId);
            $shareUrl = $fileFolderShare->getFullSharingUrl().

            // remove share
            $rs = SharingHelper::removeShareById($sharingId);

            if ($rs === true) {
                // user action logs
                UserActionLogHelper::logAdmin('Deleted sharing link #'.$sharingId, 'ADMIN', 'DELETE', [
                    'data' => [
                        'share_url' => $shareUrl,
                    ],
                ]);

                $result['error'] = false;
                $result['msg'] = 'Sharing link removed.';
            } else {
                $result['error'] = true;
                $result['msg'] = 'Could not remove the sharing link, please try again later.';
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxSharingManageAddForm()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // preload list of users (done using SQL as potential to be memory hungry
        // using the ORM when there's a lot of users)
        $users = $db->getRows('SELECT id, username '
            .'FROM users '
            .'ORDER BY username '
            .'LIMIT 10000');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/sharing_manage_add_form.html', array(
            'users' => $users,
            'accessLevels' => array(
                'view' => 'View Only',
                'upload_download' => 'Upload, Download & View',
            ),
        ));

        // output response
        return $this->renderJson($result);
    }

    public function ajaxSharingManageAddGetFolderListing()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $created_by_user_id = (int)$request->request->get('created_by_user_id');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/sharing_manage_add_get_folder_listing.html', array(
            'userFolders' => FileFolderHelper::loadAllActiveForSelect($created_by_user_id),
        ));

        // output response
        return $this->renderJson($result);
    }

    public function ajaxSharingManageAddProcess()
    {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $created_by_user_id = (int)$request->request->get('created_by_user_id');
        $folder_id = (int)$request->request->get('folder_id');
        $shared_with_user_id = (int)$request->request->get('shared_with_user_id');
        $shared_with_user_id = $shared_with_user_id === 0 ? null : $shared_with_user_id;
        $share_permission_level = $request->request->get('share_permission_level');
        $is_global = $shared_with_user_id === null ? 1 : 0;

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // validate submission
        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        } elseif ($folder_id === 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("folder_not_found", "Folder not found.");
        } elseif ($created_by_user_id === $shared_with_user_id) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("both_shared_users_can_not_be_the_same",
                "Both shared users can not be the same.");
        } else {
            // create share
            SharingHelper::createShare([], [$folder_id], $shared_with_user_id, $share_permission_level, true,
                $is_global, $created_by_user_id);

            // user action logs
            UserActionLogHelper::logAdmin('Created sharing link', 'ADMIN', 'ADD', [
                'data' => [
                    'created_by_user_id' => $created_by_user_id,
                    'folder_id' => $folder_id,
                    'shared_with_user_id' => $shared_with_user_id,
                    'share_permission_level' => $share_permission_level,
                    'is_global' => $is_global,
                ],
            ]);

            $result['msg'] = AdminHelper::t("share_created", "Share created.");
        }

        // output response
        return $this->renderJson($result);
    }
}
