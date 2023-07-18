<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Models\FileFolder;
use App\Models\User;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserHelper;
use App\Helpers\ValidationHelper;

class FolderFileManager extends BaseFileManager
{
    protected $currentFolder = null;

    public function getData() {
        // preload current user and DB
        $Auth = AuthHelper::getAuth();
        $db = Database::getDatabase();

        // create SQL to load the data
        $foldersClauseReplacements = [];
        $filesClauseReplacements = [];
        $foldersClause = 'WHERE 1=1 ';
        $filesClause = 'WHERE 1=1 ';

        // root level folder
        if ($this->getParameter('nodeId') == -1) {
            $foldersClause .= 'AND file_folder.userId = :user_id ';
            $foldersClauseReplacements['user_id'] = $Auth->id;
            
            $filesClause .= 'AND (file.userId = :user_id '
                . 'OR file.uploadedUserId = :user_id) ';
            $filesClauseReplacements['user_id'] = $Auth->id;
            
            $foldersClause .= 'AND file_folder.parentId IS NULL '
                    . 'AND status = "active" ';
            $filesClause .= 'AND file.folderId IS NULL '
                    . 'AND file.status = "active" ';
        }
        // non root level, load folder
        else {
            if ($this->currentFolder === null) {
                $this->currentFolder = FileFolder::loadOneById($this->getParameter('nodeId'));
            }
            if (!$this->currentFolder) {
                $this->throwException('Failed loading folder.');
            }
            
            // if the current user is the owner, limit by their account
            if($this->currentFolder->userId === $Auth->id) {
                $foldersClause .= 'AND file_folder.userId = :user_id ';
                $foldersClauseReplacements['user_id'] = $Auth->id;
                $filesClause .= 'AND (file.userId = :user_id '
                    . 'OR file.uploadedUserId = :user_id) ';
                $filesClauseReplacements['user_id'] = $Auth->id;
            }
            elseif((int)$this->currentFolder->isPublic === 1) {
                // only load public folders
                $foldersClause .= 'AND file_folder.isPublic = 1 ';
            }
            else {
                // if not owned nor public, exit
                return false;
            }

            // if the folder is in the trash, ensure we only show trashed files or
            // sub-folders within
            $foldersClause .= 'AND file_folder.parentId = :parent_id '
                    . 'AND file_folder.status = ' . $db->quote($this->currentFolder->status) . ' ';
            $foldersClauseReplacements['parent_id'] = $this->getParameter('nodeId');

            // if the folder is in the trash, ensure we only show trashed files or
            // sub-folders within
            $filesClause .= 'AND file.status = ' . $db->quote($this->currentFolder->status) . ' '
                    . 'AND file.folderId = ' . (int) $this->getParameter('nodeId') . ' ';
        }
        
        // load the folders data into the object
        $this->getFoldersData($foldersClause, $foldersClauseReplacements);
        
        // load the files data into the object
        $this->getFilesData($filesClause, $filesClauseReplacements);
    }

    public function requiresLogin() {
        // allow for non users
        return false;
    }

    public function getRequiredPrechecks() {
        // skip for root level
        if ($this->getParameter('nodeId') == -1) {
            return false;
        }

        // preload user
        $Auth = AuthHelper::getAuth();

        // non root level, load folder
        if ($this->currentFolder === null) {
            $this->currentFolder = FileFolder::loadOneById($this->getParameter('nodeId'));
        }
        if (!$this->currentFolder) {
            $this->throwException('Failed loading folder.');
        }

        // if the folder has an owner
        if ((int) $this->currentFolder->userId) {
            // get folder owner details
            $owner = User::loadOneById($this->currentFolder->userId);

            // store if the current user owns the folder
            if ($owner->id === $Auth->id) {
                $userOwnsFolder = true;
                $shareAccessLevel = 'all';
            }
            // internally shared folders
            elseif ($Auth->loggedIn()) {
                // allow if user has been granted share access to the folder
                $db = Database::getDatabase();
                $_SESSION['sharekeyFolder' . $this->currentFolder->id] = false;
                $shareData = $db->getRow('SELECT file_folder_share.id, share_permission_level, access_key '
                        . 'FROM file_folder_share '
                        . 'LEFT JOIN file_folder_share_item ON file_folder_share.id = file_folder_share_item.file_folder_share_id '
                        . 'WHERE (shared_with_user_id = :shared_with_user_id OR (shared_with_user_id IS NULL AND is_global = 1)) '
                        . 'AND folder_id = :folder_id '
                        . 'LIMIT 1', array(
                            'shared_with_user_id' => (int) $Auth->id,
                            'folder_id' => (int) $this->currentFolder->id,
                        ));
                if ($shareData) {
                    $db->query('UPDATE file_folder_share '
                            . 'SET last_accessed = NOW() '
                            . 'WHERE id = :id '
                            . 'LIMIT 1', array(
                                'id' => (int) $shareData['id'],
                            ));
                    $_SESSION['sharekeyFolder' . $this->currentFolder->id] = true;
                    $shareAccessLevel = $shareData['share_permission_level'];
                }
            }
        }

        // privacy
        if (((int) $this->currentFolder->userId > 0) && ($this->currentFolder->userId !== $Auth->id)) {
            if (CoreHelper::getOverallPublicStatus($this->currentFolder->userId, $this->currentFolder->id) == false) {
                // output response
                $returnJson['html'] = '<div class="ajax-error-image"><!-- --></div>';
                $returnJson['javascript'] = 'showErrorNotification("' . str_replace("\"", "'", UCWords(TranslateHelper::t('error', 'Error'))) . '", "' . str_replace("\"", "'", TranslateHelper::t('folder_is_not_publicly_shared_please_contact', 'Folder is not publicly shared. Please contact the owner and request they update the privacy settings.')) . '");';
                $returnJson['page_title'] = UCWords(TranslateHelper::t('error', 'Error'));
                $returnJson['page_url'] = '';

                // output response
                return $returnJson;
            }
        }

        // check if folder needs a password, ignore if logged in as the owner
        //if((strlen($folder->accessPassword) > 0) && ($owner->id != Auth.id) && ($_SESSION['sharekeyFolder'.$folder->id] == false))
        if ((strlen($this->currentFolder->accessPassword) > 0) && ($owner->id != $Auth->id)) {
            // see if we have it in the session already
            $askPassword = true;
            if (!isset($_SESSION['folderPassword'])) {
                $_SESSION['folderPassword'] = [];
            }
            elseif (isset($_SESSION['folderPassword'][$this->currentFolder->id])) {
                if ($_SESSION['folderPassword'][$this->currentFolder->id] == $this->currentFolder->accessPassword) {
                    $askPassword = false;
                }
            }

            if ($askPassword == true) {
                // output response
                $returnJson['html'] = '<div class="ajax-error-image"><!-- --></div><div id="albumPasswordModel" data-backdrop="static" data-keyboard="false" class="albumPasswordModel modal fade custom-width general-modal"><div class="modal-dialog"><div class="modal-content"><form id="folderPasswordForm" action="' . CoreHelper::getCoreSitePath() . '/ajax/folder_password_process" autocomplete="off" onSubmit="$(\'#password-submit-btn\').click(); return false;"><div class="modal-body">';

                $returnJson['html'] .= '<div class="row">';
                $returnJson['html'] .= '	<div class="col-md-3">';
                $returnJson['html'] .= '		<div class="modal-icon-left"><img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/modal_icons/shield_lock.png"/></div>';
                $returnJson['html'] .= '	</div>';
                $returnJson['html'] .= '	<div class="col-md-9">';
                $returnJson['html'] .= '		<h4>' . TranslateHelper::t('password_required', 'Password Required') . '</h4><hr style="margin-top: 5px;"/>';
                $returnJson['html'] .= '		<div class="form-group">';
                $returnJson['html'] .= '			<p>' . TranslateHelper::t('this_folder_has_a_password_set', 'This folder requires a password to gain access. Use the form below to enter the password, then click "unlock".') . '</p>';
                $returnJson['html'] .= '		</div>';

                $returnJson['html'] .= '		<div class="form-group">';
                $returnJson['html'] .= '			<label for="folderName" class="control-label">' . UCWords(TranslateHelper::t('access_password', 'Access Password')) . ':</label>';
                $returnJson['html'] .= '			<div class="input-grsoup">';
                $returnJson['html'] .= '				<input type="password" name="folderPassword" id="folderPassword" class="form-control" placeholder="************"/>';
                $returnJson['html'] .= '			</div>';
                $returnJson['html'] .= '		</div>';
                $returnJson['html'] .= '	</div>';
                $returnJson['html'] .= '</div>';

                $returnJson['html'] .= '</div><div class="modal-footer" style="margin-top: 0px;">';
                $returnJson['html'] .= '<input type="hidden" value="' . (int) $this->currentFolder->id . '" id="folderId" name="folderId"/>';
                $returnJson['html'] .= '<input type="hidden" value="1" id="submitme" name="submitme"/>';
                $returnJson['html'] .= '<button type="button" class="btn btn-default" data-dismiss="modal">' . TranslateHelper::t('cancel', 'Cancel') . '</button>';
                $returnJson['html'] .= '<button type="button" class="btn btn-info" id="password-submit-btn" onClick="processAjaxForm(this, function() { $(\'.modal\').modal(\'hide\'); $(\'.modal-backdrop\').remove(); loadFiles(' . (int) $this->currentFolder->id . '); }); return false;">' . TranslateHelper::t('unlock', 'Unlock') . ' <i class="entypo-check"></i></button>';
                $returnJson['html'] .= '</div></form></div></div></div>';
                $returnJson['javascript'] = "jQuery('.albumPasswordModel').modal('show');";

                // output response
                return $returnJson;
            }
        }

        return false;
    }

    public function getPageTitle() {
        // folder level title
        if ($this->currentFolder) {
            return $this->currentFolder->folderName;
        }

        // root level title
        return TranslateHelper::t('my_files', 'My Files');
    }

    public function getPageUrl() {
        // folder level url
        if ($this->currentFolder) {
            return $this->currentFolder->getFolderUrl();
        }

        // root level url
        return ThemeHelper::getLoadedInstance()->getAccountWebRoot();
    }
    
    public function getSelectedNavItem() {
        // folder level
        if ($this->currentFolder) {
            // if the folder status is "trash", assume we're in the trash bin
            if($this->currentFolder->status === 'trash') {
                return 'trash';
            }
            
            return $this->currentFolder->id;
        }
        
        // root level
        return '-1';
    }
    
    public function getUserOwnsFolder() {
        if ($this->currentFolder) {
            $Auth = AuthHelper::getAuth();
            if($this->currentFolder->userId === $Auth->id) {
                return true;
            }
            
            return false;
        }
        
        return true;
    }
    
    public function getShowToolbarActionButtons() {
        if($this->getUserOwnsFolder() === false) {
            return false;
        }
        
        // don't show action folders on trash items
        if ($this->currentFolder && $this->currentFolder->status === 'trash') {
            return false;
        }
        
        return true;
    }
    
    public function getNoResultsHtml() {
        // preload the user
        $Auth = AuthHelper::getAuth();

        // prepare html
        $html = '';
        if ($Auth->loggedIn()) {
            $html .= '<div class="no-files-upload-wrapper" onClick="uploadFiles(' . ($this->currentFolder !== null?$this->currentFolder->id:'null') . ', true); return false;">';
            $html .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/modal_icons/upload-computer-icon.png" class="upload-icon-image"/>';
            $html .= '<div class="clear"><!-- --></div>';
            $html .= TranslateHelper::t('no_files_found_in_this_folder_drag_and_drop', 'Drag & drop files or click here to upload');
            $html .= '</div>';
        }
        else {
            $html .= '<div class="alert alert-warning"><i class="entypo-attention"></i> ' . TranslateHelper::t('no_files_found_in_folder', 'No files found within this folder.') . '</div>';
        }

        return $html;
    }

    public function getBreadcrumbs() {
        // get base level first
        $breadcrumbs = $this->getBaseBreadcrumbs();

        // folder level breadcrumbs
        if ($this->currentFolder) {
            if($this->getUserOwnsFolder() === true) {
                $dropdownMenu = '';
                if($this->currentFolder->status !== 'trash') {
                    $dropdownMenu .= '<ul role="menu" class="dropdown-menu dropdown-white pull-left"><li>';
                    if (UserHelper::getAllowedToUpload() == true) {
                        $dropdownMenu .= '	<li><a href="#" onClick="uploadFiles(' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-cloud-upload"></span></span>' . TranslateHelper::t('upload_files', 'Upload Files') . '</a></li>';
                        $dropdownMenu .= '	<li class="divider"></li>';
                    }

                    $dropdownMenu .= '	<li><a href="#" onClick="showAddFolderForm(' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-plus"></span></span>' . TranslateHelper::t('add_sub_folder', 'Add Sub Folder') . '</a></li>';
                    $dropdownMenu .= '	<li class="divider"></li>';
                    $dropdownMenu .= '	<li><a href="#" onClick="showAddFolderForm(null, ' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-pencil"></span></span>' . TranslateHelper::t('edit_folder', 'Edit') . '</a></li>';
                    //$dropdownMenu .= '	<li><a href="#" onClick="moveFilesSelectFolder(null, ' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-move"></span></span>' . TranslateHelper::t('move', 'Move') . '</a></li>';
                    //$dropdownMenu .= '	<li><a href="#" onClick="copyItems(null, ' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-plus-sign"></span></span>' . TranslateHelper::t('create_copy', 'Create Copy') . '</a></li>';
                    $dropdownMenu .= '	<li><a href="#" onClick="confirmTrashFolder(' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-trash"></span></span>' . TranslateHelper::t('delete_folder', 'Delete') . '</a></li>';
                    $dropdownMenu .= '	<li class="divider"></li>';
                    $dropdownMenu .= '	<li><a href="#" onClick="downloadAllFilesFromFolder(' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-floppy-save"></span></span>' . TranslateHelper::t('download_all_files', 'Download All Files (Zip)') . '</a></li>';
                    $dropdownMenu .= '	<li class="divider"></li>';
                    $dropdownMenu .= '	<li><a href="#" onClick="selectAllItems();"><span class="context-menu-icon"><span class="glyphicon glyphicon-check"></span></span>' . TranslateHelper::t('account_file_details_select_all_items', 'Select All Items') . '</a></li>';
                    $dropdownMenu .= '	<li><a href="#" onClick="showSharingForm(' . (int) $this->currentFolder->id . ');"><span class="context-menu-icon"><span class="glyphicon glyphicon-share"></span></span>' . TranslateHelper::t('share_folder', 'Share Folder') . '</a></li>';
                    $dropdownMenu .= '</ul>';
                }
            }

            $localFolder = $this->currentFolder;
            $localBreadcrumbs = [];
            $first = true;
            while ($localFolder != false) {
                if ($this->currentFolder == null) {
                    $first = false;
                }

                $localBreadcrumbs[] = '<a href="#" ' . (($first == true && strlen($dropdownMenu)) ? ' data-toggle="dropdown"' : 'onClick="loadImages(\'folder\', ' . (int) $localFolder->id . ', 1); return false;"') . ' class="btn btn-white' . (($first == true && strlen($dropdownMenu)) ? '' : ' mid-item') . '">' . ValidationHelper::safeOutputToScreen($localFolder->folderName) . (($first == true && strlen($dropdownMenu)) ? ($totalText . '&nbsp;&nbsp;<i class="caret"></i>') : '') . '</a>' . (($first == true && strlen($dropdownMenu)) ? $dropdownMenu : '');
                $first = false;
                $localFolder = FileFolder::loadOneById($localFolder->parentId);
            }

            // change direction of breadcrumbs and add globally
            $breadcrumbs = array_merge($breadcrumbs, array_reverse($localBreadcrumbs));

            // add on 'add folder' plus icon
            $breadcrumbs[] = '<a class="add-sub-folder-plus-btn" href="#" onClick="showAddFolderForm(' . (int) $this->currentFolder->id . '); return false;" title="" data-original-title="' . TranslateHelper::t('add_sub_folder', 'Add Sub Folder') . '" data-placement="bottom" data-toggle="tooltip"><i class="glyphicon glyphicon-plus-sign"></i></a>';

            return $breadcrumbs;
        }

        // root level
        $dropdownMenu = '<ul role="menu" class="dropdown-menu dropdown-white pull-left">	<li>';
        if (UserHelper::getAllowedToUpload() == true) {
            $dropdownMenu .= '<a href="#" onClick="uploadFiles(\'\');"><span class="context-menu-icon"><span class="glyphicon glyphicon-cloud-upload"></span></span>' . TranslateHelper::t('upload_files', 'Upload Files') . '</a></li>	<li class="divider"></li>	';
        }
        $dropdownMenu .= '<li><a href="#" onClick="showAddFolderForm(-1);"><span class="context-menu-icon"><span class="glyphicon glyphicon-plus"></span></span>' . TranslateHelper::t('add_folder', 'Add Folder') . '</a></li>	<li class="divider"></li>	<li><a href="#" onClick="selectAllItems();"><span class="context-menu-icon"><span class="glyphicon glyphicon-check"></span></span>' . TranslateHelper::t('account_file_details_select_all_items', 'Select All Items') . '</a></li></ul>';

        // add on any custom breadcrumbs
        $breadcrumbs[] = '<a href="#" data-toggle="dropdown" class="btn btn-white">' . TranslateHelper::t('account_file_details_root_folder', 'Root Folder') . $this->getBreadcrumbTotalText() . '&nbsp;&nbsp;<i class="caret"></i></a>' . $dropdownMenu;

        // add on 'add folder' plus icon
        $breadcrumbs[] = '<a class="add-sub-folder-plus-btn" href="#" onClick="showAddFolderForm(' . (int) $this->currentFolder->id . '); return false;" title="" data-original-title="' . TranslateHelper::t('add_sub_folder', 'Add Sub Folder') . '" data-placement="bottom" data-toggle="tooltip"><i class="glyphicon glyphicon-plus-sign"></i></a>';

        return $breadcrumbs;
    }

}
