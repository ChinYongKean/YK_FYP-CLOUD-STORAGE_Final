<?php

namespace App\Services\File_Manager;

use App\Core\Database;
use App\Models\File;
use App\Models\FileFolder;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\FileManagerHelper;
use App\Helpers\PluginHelper;
use App\Helpers\ThemeHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\ValidationHelper;

abstract class BaseFileManager
{
    public $pageType = null;
    public $parameters = [];
    public $allStatsFiles = null;
    public $allStatsFolders = null;
    public $files = [];
    public $folders = [];

    abstract function getData();

    abstract function requiresLogin();

    abstract function getRequiredPrechecks();

    abstract function getPageTitle();

    abstract function getPageUrl();

    abstract function getBreadcrumbs();

    public function __construct($pageType) {
        $this->pageType = $pageType;
    }

    public function isCurrentUserAuthenticated() {
        if ($this->requiresLogin() === true) {
            $Auth = AuthHelper::getAuth();
            if ($Auth->loggedIn() === false) {
                return false;
            }
        }
    }

    public function getPageType() {
        return $this->pageType;
    }

    public function getPageTypeForFolders() {
        return $this->pageType;
    }

    public function setParameters($parameters) {
        $this->parameters = $parameters;
    }

    public function getParameter($name) {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : false;
    }

    public function getFileManagerHtml() {
        // get current user
        $Auth = AuthHelper::getAuth();

        // first get data
        $this->getData();

        // run pre-checks, for example if a password is required before displaying the folder
        $returnJson = $this->getRequiredPrechecks();
        if ($returnJson !== false) {
            return $returnJson;
        }

        // some variables for later
        $sortingOptions = $this->getSortingOptions();

        // create response
        $returnJson = [];
        $returnJson['html'] = '';
        $returnJson['html'] .= '<div class="image-browse">';
        $returnJson['html'] .= '<div id="fileManager" class="fileManager ' . ValidationHelper::safeOutputToScreen($_SESSION['browse']['viewType']) . '">';
        if (($this->files) || ($this->folders)) {
            $returnJson['html'] .= '<div class="toolbar-container">
		<!-- toolbar -->
		<div class="col-md-6 clearfix">
			<!-- breadcrumbs -->
			<div class="row breadcrumbs-container">
				<div class="col-md-12 col-sm-12 clearfix">
					<ol id="folderBreadcrumbs" class="btn-group btn-breadcrumb">' . implode('', $this->getBreadcrumbs()) . '</ol>
				</div>
			</div></div>';

            $returnJson['html'] .= '
		<div class="col-md-6 clearfix right-toolbar-options">
			<div class="list-inline pull-right">
				<div class="btn-toolbar pull-right" role="toolbar">';

            // ordering and page dropdowns
            $returnJson['html'] .= '<div class="btn-group">';
            $returnJson['html'] .= '<div class="btn-group">
							<button id="perPageButton" data-toggle="dropdown" class="btn btn-white dropdown-toggle" type="button">
								' . (int) $_SESSION['search']['perPage'] . ' <i class="entypo-arrow-combo"></i>
							</button>
							<ul role="menu" class="dropdown-menu dropdown-white pull-right per-page-menu">
								<li class="disabled"><a href="#">' . TranslateHelper::t('per_page', 'Per Page') . ':</a></li>';
            foreach ($this->getPerPageOptions() AS $perPageOption) {
                $returnJson['html'] .= '<li><a href="#" onclick="updatePerPage(\'' . (int) $perPageOption . '\', \'' . (int) $perPageOption . '\', this' . $this->generateAdditionalParamsStringForPaging() . '); return false;">' . (int) $perPageOption . '</a></li>';
            }
            $returnJson['html'] .= '</ul><input name="perPageElement" id="perPageElement" value="100" type="hidden">
						</div>';
            $returnJson['html'] .= '<div class="btn-group">
                                                            <button id="filterButton" data-toggle="dropdown" class="btn btn-white dropdown-toggle" type="button">
                                                                    ' . ValidationHelper::safeOutputToScreen($sortingOptions[$this->getFilterOrderBy()]) . ' <i class="entypo-arrow-combo"></i>
                                                            </button>
                                                            <ul role="menu" class="dropdown-menu dropdown-white pull-right">
                                                                    <li class="disabled"><a href="#">' . TranslateHelper::t('sort_by', 'Sort By') . ':</a></li>';
            foreach ($sortingOptions AS $k => $v) {
                $returnJson['html'] .= '<li><a href="#" onclick="updateSorting(\'' . $k . '\', \'' . $v . '\', this' . $this->generateAdditionalParamsStringForPaging() . '); return false;">' . $v . '</a></li>';
            }
            $returnJson['html'] .= '</ul>
                                                            <input name="filterOrderBy" id="filterOrderBy" value="' . ValidationHelper::safeOutputToScreen($_SESSION['search']['filterOrderBy']) . '" type="hidden">
                                                    </div>';
            $returnJson['html'] .= '</div>';

            // other buttons
            $returnJson['html'] .= '<div class="btn-group">';
            if ($this->getShowToolbarActionButtons() === true) {
                $returnJson['html'] .= '<button class="btn btn-white disabled fileActionLinks" type="button" title="" data-original-title="' . TranslateHelper::t('account_file_download_selected', 'Download Selected (Zip)') . '" data-placement="bottom" data-toggle="tooltip" onclick="downloadSelected();
                                                                return false;"><i class="entypo-download"></i></button>';
                $returnJson['html'] .= '<button class="btn btn-white disabled fileActionLinks" type="button" title="" data-original-title="' . TranslateHelper::t('account_file_details_share', 'Share') . '" data-placement="bottom" data-toggle="tooltip" onclick="showSharingForm();
                                                                return false;"><i class="entypo-share"></i></button>';
                $returnJson['html'] .= '<button class="btn btn-white disabled fileActionLinks" type="button" title="" data-original-title="' . TranslateHelper::t('links', 'Links') . '" data-placement="bottom" data-toggle="tooltip" onclick="viewFileLinks();
                                                                return false;"><i class="entypo-link"></i></button>';
                $returnJson['html'] .= '<button class="btn btn-white disabled fileActionLinks" type="button" title="" data-original-title="' . TranslateHelper::t('delete', 'Delete') . '" data-placement="bottom" data-toggle="tooltip" onclick="trashFiles();
                                                                return false;"><i class="entypo-trash"></i></button>';
            }
            $returnJson['html'] .= '<button class="btn btn-white" type="button" title="" data-original-title="' . TranslateHelper::t('list_view', 'List View') . '" data-placement="bottom" data-toggle="tooltip" onclick="toggleViewType();
                                                                return false;" id="viewTypeText"><i class="entypo-list"></i></button>
                                                <button class="btn btn-white" type="button" title="" data-original-title="' . TranslateHelper::t('fullscreen', 'Fullscreen') . '" data-placement="bottom" data-toggle="tooltip" onclick="toggleFullScreenMode();
                                                                return false;"><i class="entypo-resize-full"></i></button>
                        </div>';

            // append empty trash option
            if ($this->getPageType() === 'trash' && SITE_CONFIG_USER_CAN_EMPTY_TRASH_CAN === 'yes') {
                $returnJson['html'] .= '<div class="btn-group">';
                $returnJson['html'] .= '<button class="btn btn-white" type="button" title="" data-original-title="Empty Trash" data-placement="bottom" data-toggle="tooltip" onclick="confirmEmptyTrash(); return false;">Empty Trash <i class="glyphicon glyphicon-trash"></i></button>';
                $returnJson['html'] .= '</div>';
            }

            // only for the non-logged in file sharing page
            if (isset($_SESSION['sharekeyFileFolderShareId']) && ((int) $_SESSION['sharekeyFileFolderShareId'] > 0)) {
                if (!in_array($this->getShareAccessLevel(), array('view'))) {
                    $returnJson['html'] .= '<div class="btn-group">
                                                            <button id="filterButton" data-toggle="dropdown" class="btn btn-white dropdown-toggle" type="button">
                                                                <i class="glyphicon glyphicon-floppy-save"></i> <i class="entypo-arrow-combo"></i>
                                                            </button>
                                                            <ul role="menu" class="dropdown-menu dropdown-white pull-right">
                                                                    <li class="disabled"><a href="#">' . TranslateHelper::t('download_as_zip', 'Download As Zip') . ':</a></li>';
                    $returnJson['html'] .= '<li><a href="#" onclick="downloadAllFilesFromFolderShared(); return false;">' . TranslateHelper::t('entire_share', 'Entire Share') . '</a></li>';
                    $folderId = 0;
                    if (isset($this->currentFolder->id)) {
                        $folderId = (int) $this->currentFolder->id;
                    }
                    $returnJson['html'] .= '<li class="' . ($folderId === 0 ? 'disabled' : '') . '"><a href="#" onclick="' . ($folderId === 0 ? '' : ('downloadAllFilesFromFolderShared(' . (int) $folderId . ');')) . ' return false;">' . TranslateHelper::t('current_folder', 'Current Folder') . '</a></li>';
                    $returnJson['html'] .= '</ul>
                                                                    <input name="filterOrderBy" id="filterOrderBy" value="' . ValidationHelper::safeOutputToScreen($_SESSION['search']['filterOrderBy']) . '" type="hidden">
                                                            </div>';
                }
            }

            $returnJson['html'] .= '</div>
				<ol id="folderBreadcrumbs2" class="breadcrumb bc-3 pull-right">
					<li class="active">
						<span id="statusText"></span>
					</li>
				</ol>
			</div>';
            $returnJson['html'] .= '</div>';

            $returnJson['html'] .= '
		<!-- /.navbar-collapse -->
	</div>';

            $returnJson['html'] .= '<div class="gallery-env"><div class="fileListing" id="fileListing">';

            // output folders
            if ($this->folders) {
                foreach ($this->folders AS $folder) {
                    // hydrate folder so we have our object
                    $folderObj = FileFolder::hydrateSingleRecord($folder);
                    $folderLabel = $folder['folderName'];

                    // check folder ownership
                    $ownedByCurrentUser = false;
                    if (($Auth->loggedIn() == true) && ($Auth->id == $folderObj->userId) && ((int) $folderObj->userId > 0)) {
                        $ownedByCurrentUser = true;
                    }

                    // prepare cover image
                    $coverData = $folderObj->getCoverData();
                    $coverId = (int) $coverData['file_id'];
                    $coverUniqueHash = $coverData['unique_hash'];

                    $returnJson['html'] .= '<div id="folderItem' . (int) $folderObj->id . '" data-clipboard-action="copy" data-clipboard-target="#clipboard-placeholder" class="fileItem folderIconLi folderItem' . (int) $folderObj->id . ' ' . ($folder['status'] != 'active' ? 'folderDeletedLi' : '') . ' fileIconLi col-xs-4 image-thumb ' . ($ownedByCurrentUser == true ? 'owned-folder' : 'not-owned-folder') . '" onClick="loadImages(\'' . $this->getPageTypeForFolders() . '\', ' . (int) $folderObj->id . ', null, 0, \'\', ' . $this->generateAdditionalParamsStringForPaging() . '); return false;" folderId="' . (int) $folderObj->id . '" sharing-url="' . $folderObj->getFolderUrl() . '">';

                    if ($ownedByCurrentUser) {
                        $returnJson['html'] .= '<div class="itemCheckbox">';
                        $returnJson['html'] .= '<span class="cr"><i class="cr-icon glyphicon glyphicon-ok"></i></span>';
                        $returnJson['html'] .= '</div>';
                    }

                    $returnJson['html'] .= '<div class="thumbIcon">';
                    $returnJson['html'] .= '<a name="link">';
                    if (!$ownedByCurrentUser) {
                        if ($folder['fileCount'] == 0) {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_share_fm_grid.png" />';
                        }
                        else {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_share_full_fm_grid.png" />';
                        }
                    }
                    elseif ($folder['isPublic'] == 1) {
                        if ($folder['fileCount'] == 0) {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_fm_grid.png" />';
                        }
                        else {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_full_fm_grid.png" />';
                        }
                    }
                    elseif ($folder['isPublic'] == 0) {
                        if ($folder['fileCount'] == 0) {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_lock_fm_grid.png" />';
                        }
                        else {
                            $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_lock_full_fm_grid.png" />';
                        }
                    }
                    else {
                        $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_full_fm_grid.png" />';
                    }

                    $returnJson['html'] .= '</a>';
                    $returnJson['html'] .= '</div>';

                    $returnJson['html'] .= '<span class="filesize">' . ValidationHelper::safeOutputToScreen(CoreHelper::formatSize($folder['totalSize'])) . '</span>';
                    $returnJson['html'] .= '<span class="fileUploadDate">' . ValidationHelper::safeOutputToScreen(CoreHelper::formatDate($folder['date_updated'] != null ? $folder['date_updated'] : $folder['date_added'])) . '</span>';
                    $returnJson['html'] .= '<span class="downloadCount">-</span>';
                    $returnJson['html'] .= '<span class="fileOwner">' . ValidationHelper::safeOutputToScreen($this->createFolderSubLabel($folder, false))  . '</span>';
                    $returnJson['html'] .= '<span class="thumbList">';
                    $returnJson['html'] .= '<a name="link">';
                    if ($folder['fileCount'] == 0 && $folder['isPublic'] == 1) {
                        $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_fm_list.png" />';
                    }
                    elseif ($folder['fileCount'] > 0 && $folder['isPublic'] == 1) {
                        $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_full_fm_list.png" />';
                    }
                    elseif ($folder['fileCount'] >= 0 && $folder['isPublic'] == 0) {
                        $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_lock_fm_list.png" />';
                    }
                    else {
                        $returnJson['html'] .= '<img src="' . ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/folder_full_fm_list.png" />';
                    }
                    $returnJson['html'] .= '</a>';
                    $returnJson['html'] .= '</span>';

                    $returnJson['html'] .= '<span class="filename">' . ValidationHelper::safeOutputToScreen($folderLabel) . '</span>';
                    $returnJson['html'] .= '<span class="filesizeAlt">' . ValidationHelper::safeOutputToScreen($this->createFolderSubLabel($folder)) . '</span>';

                    // menu link
                    if ($ownedByCurrentUser) {
                        $returnJson['html'] .= '  <div class="fileOptions">';
                        $returnJson['html'] .= '      <a class="fileDownload" href="#"><i class="caret"></i></a>';
                        $returnJson['html'] .= '  </div>';
                    }

                    $returnJson['html'] .= '</div>';
                }
            }

            // output files
            if ($this->files) {
                foreach ($this->files AS $file) {
                    // get file object
                    $fileObj = File::hydrateSingleRecord($file);

                    // check image ownership
                    $ownedByCurrentUser = false;
                    if (($Auth->loggedIn() == true) && ($Auth->id == $fileObj->userId || $Auth->id == $fileObj->uploadedUserId) && ((int) $fileObj->userId > 0)) {
                        $ownedByCurrentUser = true;
                    }

                    $sizingMethod = 'middle';
                    if (ThemeHelper::getConfigValue('thumbnail_type') === 'full') {
                        $sizingMethod = 'cropped';
                    }
                    $previewImageUrlLarge = FileHelper::getIconPreviewImageUrl($fileObj, false, 48, false, 160, 134, $sizingMethod);
                    $previewImageUrlMedium = FileHelper::getIconPreviewImageUrlMedium($fileObj);

                    // append any plugin menu items
                    $pluginMenus = PluginHelper::callHookRecursive('accountHomeFileListMenu', array(
                                'file' => $fileObj,
                                    )
                    );

                    $menuItemsStr = '';
                    if (count($pluginMenus)) {
                        $formattedForOutput = [];
                        foreach($pluginMenus AS $pluginMenu) {
                            foreach($pluginMenu AS $k=>$menuItem) {
                                $formattedForOutput[$k] = $menuItem;
                            }
                        }
                        $menuItemsStr = json_encode($formattedForOutput);
                    }

                    $returnJson['html'] .= '<div dttitle="' . ValidationHelper::safeOutputToScreen($file['originalFilename']) . '" dtsizeraw="' . ValidationHelper::safeOutputToScreen($file['file_size']) . '" dtuploaddate="' . ValidationHelper::safeOutputToScreen(CoreHelper::formatDate($file['uploadedDate'])) . '" dtfullurl="' . ValidationHelper::safeOutputToScreen($fileObj->getFullShortUrl()) . '" dtfilename="' . ValidationHelper::safeOutputToScreen($file['originalFilename']) . '" dtsafefilenameforurl="' . ValidationHelper::safeOutputToScreen($fileObj->getSafeFilenameForUrl()) . '" dtstatsurl="' . ValidationHelper::safeOutputToScreen($fileObj->getStatisticsUrl()) . '" dturlhtmlcode="' . ValidationHelper::safeOutputToScreen($fileObj->getHtmlLinkCode()) . '" dturlbbcode="' . ValidationHelper::safeOutputToScreen($fileObj->getForumLinkCode()) . '" dtfilehash="' . ValidationHelper::safeOutputToScreen($fileObj->getFileHash()) . '" dtextramenuitems="' . ValidationHelper::safeOutputToScreen($menuItemsStr) . '" title="' . ValidationHelper::safeOutputToScreen($file['originalFilename']) . ' (' . ValidationHelper::safeOutputToScreen(CoreHelper::formatSize($file['fileSize'])) . ')" fileId="' . $file['id'] . '" class="col-xs-4 image-thumb image-thumb-' . $sizingMethod . ' fileItem' . $file['id'] . ' fileIconLi ' . ($file['status'] != 'active' ? 'fileDeletedLi' : '') . ' ' . ($ownedByCurrentUser == true ? 'owned-image' : 'not-owned-image') . '">';

                    if ($ownedByCurrentUser) {
                        $returnJson['html'] .= '<div class="itemCheckbox">';
                        $returnJson['html'] .= '<span class="cr"><i class="cr-icon glyphicon glyphicon-ok"></i></span>';
                        $returnJson['html'] .= '</div>';
                    }

                    $returnJson['html'] .= '<div class="thumbIcon">';
                    $returnJson['html'] .= '<a name="link"><img src="' . ((substr($previewImageUrlLarge, 0, 4) == 'http') ? $previewImageUrlLarge : (ThemeHelper::getLoadedInstance()->getAccountImagePath() . '/trans_1x1.gif')) . '" alt="" class="' . ((substr($previewImageUrlLarge, 0, 4) != 'http') ? $previewImageUrlLarge : '#') . '" style="max-width: 100%; max-height: 100%; min-width: 30px; min-height: 30px;"></a>';
                    $returnJson['html'] .= '</div>';

                    $returnJson['html'] .= '<span class="filesize">' . ValidationHelper::safeOutputToScreen(CoreHelper::formatSize($file['file_size'])) . '</span>';
                    $returnJson['html'] .= '<span class="fileUploadDate">' . ValidationHelper::safeOutputToScreen(CoreHelper::formatDate($file['uploadedDate'])) . '</span>';
                    $returnJson['html'] .= '<span class="downloadCount" title="'.ucwords(TranslateHelper::t('downloads', 'downloads')).'">'.number_format((int)$fileObj->visits, 0, '.', ',').'</span>';
                    $returnJson['html'] .= '<span class="fileOwner">' . ValidationHelper::safeOutputToScreen($file['username']) . '</span>';
                    $returnJson['html'] .= '<span class="thumbList">';
                    $returnJson['html'] .= '<a name="link"><img src="' . $previewImageUrlMedium . '" alt=""></a>';
                    $returnJson['html'] .= '</span>';

                    $returnJson['html'] .= '<span class="filename">' . ValidationHelper::safeOutputToScreen($file['originalFilename']) . '</span>';
                    $returnJson['html'] .= '<span class="filesizeAlt">' . ValidationHelper::safeOutputToScreen(CoreHelper::formatSize($file['file_size'])) . '</span>';

                    // menu link
                    if ($ownedByCurrentUser) {
                        $returnJson['html'] .= '  <div class="fileOptions">';
                        $returnJson['html'] .= '      <a class="fileDownload" href="#"><i class="caret"></i></a>';
                        $returnJson['html'] .= '  </div>';
                    }

                    $returnJson['html'] .= '</div>';
                }
            }
            $returnJson['html'] .= '</div>';
            $returnJson['html'] .= '</div>';

            $returnJson['html'] .= $this->generatePagingHtml();
        }
        else {
            // no results
            $returnJson['html'] .= '<div class="toolbar-container">
		<!-- toolbar -->
		<div class="col-md-6 col-sm-8 clearfix">
                    <!-- breadcrumbs -->
                    <div class="row breadcrumbs-container">
                        <div class="col-md-12 col-sm-12 clearfix">
                            <ol id="folderBreadcrumbs" class="btn-group btn-breadcrumb">' . implode('', $this->getBreadcrumbs()) . '</ol>
                        </div>
                    </div>
		</div>
            </div>';

            $returnJson['html'] .= '<div class="no-results-wrapper">';
            $returnJson['html'] .= $this->getNoResultsHtml();
            $returnJson['html'] .= '</div>';
        }

        $returnJson['html'] .= '</div>';
        $returnJson['html'] .= '</div>';

        // add file manager totals as hidden elements
        $returnJson['html'] .= $this->generateFileManagerStats();

        return $returnJson;
    }

    public function createFolderSubLabel($folder, $includeTotalSize = true) {
        $sections = [];

        $size = '';
        if($includeTotalSize && (int)$folder['fileCount']) {
            $size = CoreHelper::formatSize($folder['totalSize'], 'both', true, 0) . ' - ';
        }

        if((int)$folder['folderCount']) {
            $sections[] = ((int)$folder['folderCount'].' '.($folder['folderCount'] == 1 ? strtolower(TranslateHelper::t('folder',
                    'folder')) : strtolower(TranslateHelper::t('folders', 'folders'))));
        }

        if((int)$folder['fileCount']) {
            $sections[] = ((int)$folder['fileCount'].' '.($folder['fileCount'] == 1 ? strtolower(TranslateHelper::t('file',
                    'file')) : strtolower(TranslateHelper::t('files', 'files'))));
        }

        if(empty($sections)) {
            $sections[] = TranslateHelper::t('no_items', '0 items');
        }

        return $size . implode(', ', $sections);
    }

    public function getNoResultsHtml() {
        return '<div class="alert alert-warning"><i class="entypo-attention"></i> '
                . TranslateHelper::t('no_files_found_in_search', 'No files found within folder or search criteria.')
                . '</div>';
    }

    public function generatePagingHtml() {
        // paging
        $currentPage = $this->getParameter('pageStart');
        if ((int) $currentPage === 0) {
            $currentPage = 1;
        }
        $totalPages = ceil(((int) $this->allStatsFiles['totalFileCount'] + (int) $this->allStatsFolders['totalFolderCount']) / (int) $_SESSION['search']['perPage']);
        $html = '';
        $html .= '<div class="paginationRow">';
        $html .= '	<div id="pagination" class="paginationWrapper col-md-12 responsiveAlign">';
        $html .= '		<ul class="pagination">';
        $html .= '			<li class="' . ($currentPage == 1 ? 'disabled' : '') . '"><a href="#" onClick="' . ($currentPage > 1 ? 'loadImages(\'' . $this->getPageType() . '\', \'' . $this->getParameter('nodeId') . '\', 1, 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . ');' : '') . ' return false;"><i class="entypo-to-start"></i><span>' . UCWords(TranslateHelper::t('first', 'first')) . '</span></a></li>';
        $html .= '			<li class="' . ($currentPage == 1 ? 'disabled' : '') . '"><a href="#" onClick="' . ($currentPage > 1 ? 'loadImages(\'' . $this->getPageType() . '\', \'' . $this->getParameter('nodeId') . '\', ' . ((int) $currentPage - 1) . ', 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . ');' : '') . ' return false;"><i class="entypo-left-dir"></i> <span>' . UCWords(TranslateHelper::t('previous', 'previous')) . '</span></a></li>';

        // calculate numbers before and after
        $startPager = $currentPage - 3;
        if ($startPager < 1) {
            $startPager = 1;
        }

        for ($i = 0; $i <= 8; $i++) {
            $currentPager = $startPager + $i;
            if ($currentPager > $totalPages) {
                continue;
            }
            $html .= '		<li class="' . (($currentPager == $currentPage) ? 'active' : '') . '"><a href="#" onclick="loadImages(\'' . $this->getPageType() . '\', \'' . $this->getParameter('nodeId') . '\', ' . (int) $currentPager . ', 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . '); return false;">' . (int) $currentPager . '</a></li>';
        }

        $html .= '			<li class="' . ($currentPage == $totalPages ? 'disabled' : '') . '"><a href="#" onClick="' . ($currentPage != $totalPages ? 'loadImages(\'' . $this->getPageType() . '\', \'' . $this->getParameter('nodeId') . '\', ' . ((int) $currentPage + 1) . ', 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . ');' : '') . ' return false;"><span>' . UCWords(TranslateHelper::t('next', 'next')) . '</span> <i class="entypo-right-dir"></i></a></li>';
        $html .= '			<li class="' . ($currentPage == $totalPages ? 'disabled' : '') . '"><a href="#" onClick="' . ($currentPage != $totalPages ? 'loadImages(\'' . $this->getPageType() . '\', \'' . $this->getParameter('nodeId') . '\', ' . ((int) $totalPages) . ', 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . ');' : '') . ' return false;"><span>' . UCWords(TranslateHelper::t('last', 'last')) . '</span> <i class="entypo-to-end"></i></a></li>';
        $html .= '		</ul>';
        $html .= '	</div>';
        $html .= '</div>';

        return $html;
    }

    public function generateAdditionalParamsStringForPaging() {
        return '';
    }

    public function generateFileManagerStats() {
        // response stats
        $fileManagerStats = array(
            'rspFolderTotalFiles' => (int) $this->allStatsFiles['totalFileCount'],
            'rspFolderTotalSize' => $this->allStatsFiles['totalFileSize'],
            'rspTotalPerPage' => (int) $_SESSION['search']['perPage'],
            'rspTotalResults' => (int) $this->allStatsFiles['totalFileCount'],
            'rspCurrentStart' => (int) $this->getParameter('pageStart'),
            'rspCurrentPage' => ceil(((int) $this->getParameter('pageStart') + (int) $_SESSION['search']['perPage']) / (int) $_SESSION['search']['perPage']),
            'rspTotalPages' => ceil((int) $this->allStatsFiles['totalFileCount'] / (int) $_SESSION['search']['perPage']),
            'rspShareAccessLevel' => $this->getShareAccessLevel(),
            'rspUserOwnsFolder' => (int) $this->getUserOwnsFolder(),
            'rspSelectedNavItem' => $this->getSelectedNavItem(),
        );

        // prepare hidden element html
        $elements = [];
        foreach ($fileManagerStats AS $name => $value) {
            $elements[] = '<input id="' . $name . '" value="' . $value . '" type="hidden"/>';
        }

        return implode("", $elements);
    }

    public function getSelectedNavItem() {
        return $this->getPageType();
    }

    public function getFileManagerJavascript() {
        return '';
    }

    public function getSQLOrderColumns() {
        // save session params
        $_SESSION['search']['perPage'] = $this->getParameter('perPage');
        $_SESSION['search']['filterOrderBy'] = $this->getParameter('filterOrderBy');

        // get column names for the SQL ordering
        $fileSortColName = 'originalFilename';
        $folderSortColName = 'folderName';
        $sortDir = 'asc';
        switch ($this->getFilterOrderBy()) {
            case 'order_by_filename_asc':
                $fileSortColName = 'originalFilename';
                $folderSortColName = 'folderName';
                $sortDir = 'asc';
                break;
            case 'order_by_filename_desc':
                $fileSortColName = 'originalFilename';
                $folderSortColName = 'folderName';
                $sortDir = 'desc';
                break;
            case 'order_by_uploaded_date_asc':
            case '':
                $fileSortColName = 'uploadedDate';
                $folderSortColName = 'IFNULL(date_updated, date_added)';
                $sortDir = 'asc';
                break;
            case 'order_by_uploaded_date_desc':
                $fileSortColName = 'uploadedDate';
                $folderSortColName = 'IFNULL(date_updated, date_added)';
                $sortDir = 'desc';
                break;
            case 'order_by_downloads_asc':
                $fileSortColName = 'visits';
                $sortDir = 'asc';
                break;
            case 'order_by_downloads_desc':
                $fileSortColName = 'visits';
                $sortDir = 'desc';
                break;
            case 'order_by_filesize_asc':
                $fileSortColName = 'fa.file_size';
                $folderSortColName = 'totalSize';
                $sortDir = 'asc';
                break;
            case 'order_by_filesize_desc':
                $fileSortColName = 'fa.file_size';
                $folderSortColName = 'totalSize';
                $sortDir = 'desc';
                break;
            case 'order_by_last_access_date_asc':
                $fileSortColName = 'lastAccessed';
                $folderSortColName = 'IFNULL(date_updated, date_added)';
                $sortDir = 'asc';
                break;
            case 'order_by_last_access_date_desc':
                $fileSortColName = 'lastAccessed';
                $folderSortColName = 'IFNULL(date_updated, date_added)';
                $sortDir = 'desc';
                break;
        }

        return array(
            'fileSortColName' => $fileSortColName,
            'folderSortColName' => $folderSortColName,
            'sortDir' => $sortDir,
        );
    }

    public function getFilterOrderBy() {
        return $_SESSION['search']['filterOrderBy'];
    }

    public function getUserOwnsFolder() {
        return false;
    }

    public function getShowToolbarActionButtons() {
        return false;
    }

    public function getBaseBreadcrumbs() {
        // get logged in user
        $Auth = AuthHelper::getAuth();

        // setup initial breadcrumbs
        $breadcrumbs = [];
        if ($Auth->loggedIn()) {
            $breadcrumbs[] = '<a href="#" onClick="loadImages(\'folder\', -1, 1, 0, \'\'' . $this->generateAdditionalParamsStringForPaging() . '); return false;" class="btn btn-white mid-item"><i class="glyphicon glyphicon-home"></i></a>';
        }
        else {
            $breadcrumbs[] = '<a href="#" class="btn btn-white mid-item"><i class="glyphicon glyphicon-folder-open"></i></a>';
        }

        return $breadcrumbs;
    }

    public function getBreadcrumbTotalText() {
        $labels = [];
        if ((int) $this->allStatsFolders['totalFolderCount'] > 0) {
            $labels[] = (int) $this->allStatsFolders['totalFolderCount'] . ' ' . ucwords((int) $this->allStatsFolders['totalFolderCount'] !== 1 ? TranslateHelper::t('folders', 'Folders') : TranslateHelper::t('folder', 'Folder'));
        }

        if ((int) $this->allStatsFiles['totalFileCount'] > 0) {
            $labels[] = (int) $this->allStatsFiles['totalFileCount'] . ' ' . ucwords((int) $this->allStatsFiles['totalFileCount'] !== 1 ? TranslateHelper::t('files', 'Files') : TranslateHelper::t('file', 'File')) . ((int) $this->allStatsFiles['totalFileCount'] > 0 ? (' (' . CoreHelper::formatSize($this->allStatsFiles['totalFileSize']) . ')') : '');
        }

        if (count($labels)) {
            return ' - ' . implode(', ', $labels);
        }

        return '';
    }

    public function getSortingOptions() {
        return FileManagerHelper::getFileBrowserSortingOptions();
    }

    public function getPerPageOptions() {
        return FileManagerHelper::getPerPageOptions();
    }

    public function throwException($msg) {
        throw new \Exception($msg);
    }

    public function getFoldersData($foldersClause = '', $foldersClauseReplacements = array()) {
        // block zero length clause
        if (strlen($foldersClause) === 0) {
            $this->throwException('No folders SQL clause found.');
        }

        // preload DB
        $db = Database::getDatabase();

        // prepare SQL ordering
        $sqlOrderColumns = $this->getSQLOrderColumns();

        // get file total for this account and filter
        $this->allStatsFolders = $db->getRow("SELECT COUNT(DISTINCT file_folder.id) AS totalFolderCount "
                . "FROM file_folder " . $foldersClause, $foldersClauseReplacements);

        // load folders
        $foldersSQL = 'SELECT DISTINCT file_folder.id, file_folder.status, file_folder.userId, '
                . 'file_folder.parentId, file_folder.folderName, file_folder.isPublic, '
                . 'file_folder.totalSize, file_folder.date_updated, '
                . 'file_folder.date_added, file_folder.urlHash, '
                . '(SELECT COUNT(file.id) AS fileCount FROM file WHERE file.folderId = file_folder.id) AS fileCount, '
                . '(SELECT COUNT(ff2.id) AS folderCount FROM file_folder ff2 WHERE ff2.parentId = file_folder.id) AS folderCount '
                . 'FROM file_folder '
                . $foldersClause . ' '
                . 'GROUP BY file_folder.id '
                . 'ORDER BY ' . $sqlOrderColumns['folderSortColName'] . ' ' . $sqlOrderColumns['sortDir'] . ' '
                . $this->getFoldersSQLPagingLimitString();
        $this->folders = $db->getRows($foldersSQL, $foldersClauseReplacements);
    }

    public function getFilesData($filesClause = '', $filesClauseReplacements = array()) {
        // block zero length clause
        if (strlen($filesClause) === 0) {
            $this->throwException('No files SQL clause found.');
        }

        // preload DB
        $db = Database::getDatabase();

        // prepare SQL ordering
        $sqlOrderColumns = $this->getSQLOrderColumns();

        $this->allStatsFiles = $db->getRow('SELECT COUNT(file.id) AS totalFileCount, SUM(fa.file_size) AS totalFileSize '
                . 'FROM file '
                . 'LEFT JOIN file_folder ON file.folderId = file_folder.id '
                . 'LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = "primary" ' . $filesClause, $filesClauseReplacements);

        // load files
        // if we have the file preview plugin installed, lookup width and height
        $appendCols = '';
        $appendJoins = '';
        if(PluginHelper::pluginEnabled('filepreviewer')) {
            $appendCols = ', plugin_filepreviewer_meta.width, plugin_filepreviewer_meta.height';
            $appendJoins = 'LEFT JOIN plugin_filepreviewer_meta ON file.id = plugin_filepreviewer_meta.file_id ';
        }
        $filesSQL = 'SELECT file.*, users.username, fa.file_size'.$appendCols.' '
                . 'FROM file '
                . 'LEFT JOIN file_folder ON file.folderId = file_folder.id '
                . 'LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = "primary" '
                . $appendJoins
                . 'LEFT JOIN users ON file.uploadedUserId = users.id '
                . $filesClause . ' '
                . 'ORDER BY ' . $sqlOrderColumns['fileSortColName'] . ' ' . $sqlOrderColumns['sortDir'] . ' '
                . $this->getFilesSQLPagingLimitString();
        $this->files = $db->getRows($filesSQL, $filesClauseReplacements);
    }

    public function getFoldersSQLPagingLimitString() {
        $pageStart = ((int)$this->getParameter('pageStart') - 1) < 0 ? 0 : ((int)$this->getParameter('pageStart') - 1);

        return 'LIMIT ' . ($pageStart * (int) $_SESSION['search']['perPage']) . ', ' . (int) $_SESSION['search']['perPage'];
    }

    public function getFilesSQLPagingLimitString() {
        $pageStart = ((int)$this->getParameter('pageStart') - 1) < 0 ? 0 : ((int)$this->getParameter('pageStart') - 1);
        $newStart = floor(($pageStart * (int) $_SESSION['search']['perPage']) - $this->allStatsFolders['totalFolderCount']);
        if ($newStart < 0) {
            $newStart = 0;
        }
        $newLimit = (int) $_SESSION['search']['perPage'] - COUNT($this->folders);

        return 'LIMIT ' . $newStart . ',' . $newLimit;
    }

    public function getShareAccessLevel() {
        return 'all';
    }

}
