<?php

namespace App\Controllers\admin;

use App\Core\Database;
use App\Models\File;
use App\Helpers\AdminHelper;
use App\Helpers\AuthHelper;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;
use App\Helpers\TranslateHelper;
use App\Helpers\UserActionLogHelper;
use App\Services\Password;

class FileReportController extends AdminBaseController
{

    public function fileReportManage() {
        // admin only
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // status list
        $statusDetails = array(
            'pending',
            'cancelled',
            'accepted',
        );

        $filterByReportStatus = 'pending';
        if ($request->query->has('filterByReportStatus')) {
            $filterByReportStatus = trim($request->query->get('filterByReportStatus'));
        }

        // load template
        return $this->render('admin/file_report_manage.html', array(
                    'statusDetails' => $statusDetails,
                    'filterByReportStatus' => $filterByReportStatus,
                                ));
    }

    public function ajaxFileReportManage() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;
        $filterByReportStatus = $request->query->has('filterByReportStatus') ? $request->query->get('filterByReportStatus') : false;

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'report_date';
        switch ($sortColumnName) {
            case 'report_date':
                $sort = 'report_date';
                break;
            case 'reported_by_name':
                $sort = 'reported_by_name';
                break;
            case 'file_name':
                $sort = 'file.originalFilename';
                break;
            case 'reported_by_ip':
                $sort = 'reported_by_ip';
                break;
            case 'report_status':
                $sort = 'report_status';
                break;
        }

        $sqlClause = "WHERE 1=1 ";
        if ($filterText) {
            $filterText = $db->escape($filterText);
            $sqlClause .= "AND (file_report.reported_by_name LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_report.reported_by_email LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_report.reported_by_address LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_report.reported_by_telephone_number LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_report.digital_signature LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file_report.reported_by_ip LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file.originalFilename LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "file.id = '" . $filterText . "')";
        }

        if ($filterByReportStatus) {
            $sqlClause .= " AND file_report.report_status = " . $db->quote($filterByReportStatus);
        }

        $totalRS = $db->getValue("SELECT COUNT(file_report.id) AS total "
                . "FROM file_report "
                . "LEFT JOIN file ON file_report.file_id = file.id " . $sqlClause);
        $limitedRS = $db->getRows("SELECT file_report.*, file.originalFilename, "
                . "file.extension, file.id AS fileId, file.status "
                . "FROM file_report "
                . "LEFT JOIN file ON file_report.file_id = file.id " . $sqlClause . " "
                . "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " "
                . "LIMIT " . $iDisplayStart . ", " . $iDisplayLength);


        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $lRow = [];

                $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16x16/' . $row['extension'] . '.png';
                if (!file_exists(CORE_ASSETS_ADMIN_DIRECTORY_ROOT . '/images/icons/file_types/16x16/' . $row['extension'] . '.png')) {
                    $icon = CORE_ASSETS_ADMIN_WEB_ROOT . '/images/icons/file_types/16px/_page.png';
                }
                $lRow[] = '<img src="' . $icon . '" width="16" height="16" title="' . $row['extension'] . '" alt="' . $row['extension'] . '"/>';
                $lRow[] = CoreHelper::formatDate($row['report_date'], SITE_CONFIG_DATE_FORMAT);

                if ($row['status'] == 'active') {
                    $lRow[] = '<a href="' . FileHelper::getFileUrl($row['fileId']) . '" target="_blank" title="' . FileHelper::getFileUrl($row['fileId']) . '">' . AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35)) . '</a>';
                }
                else {
                    $lRow[] = AdminHelper::makeSafe(AdminHelper::limitStringLength($row['originalFilename'], 35));
                }

                $lRow[] = AdminHelper::makeSafe($row['reported_by_name']);
                $lRow[] = AdminHelper::makeSafe($row['reported_by_ip']);
                $statusRow = '<span class="statusText' . str_replace(" ", "", AdminHelper::makeSafe(UCWords($row['report_status']))) . '"';
                $statusRow .= '>' . $row['report_status'] . '</span>';
                $lRow[] = $statusRow;

                $links = [];
                $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="view" onClick="viewReport(' . (int) $row['id'] . ', \'Removed after abuse report received on ' . CoreHelper::formatDate($row['report_date'], SITE_CONFIG_DATE_FORMAT) . '. Abuse report #' . $row['id'] . '.\', ' . (int) $row['fileId'] . ', \'' . $row['report_status'] . '\'); return false;"><span class="fa fa-info text-primary" aria-hidden="true"></span></a>';
                if ($row['status'] == 'active') {
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="remove file" onClick="confirmRemoveFile(' . (int) $row['id'] . ', \'Removed after abuse report received on ' . CoreHelper::formatDate($row['report_date'], SITE_CONFIG_DATE_FORMAT) . '. Abuse report #' . $row['id'] . '.\', ' . (int) $row['fileId'] . '); return false;"><span class="fa fa-check text-success" aria-hidden="true"></span></a>';
                    $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="decline" onClick="declineReport(' . (int) $row['id'] . '); return false;"><span class="fa fa-close text-danger" aria-hidden="true"></span></a>';
                }
                else {
                    if ($row['report_status'] == 'pending') {
                        $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="approve" onClick="acceptReport(' . (int) $row['id'] . '); return false;"><span class="fa fa-check text-success" aria-hidden="true"></span></a>';
                        $links[] = '<a href="#" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" data-original-title="decline" onClick="declineReport(' . (int) $row['id'] . '); return false;"><span class="fa fa-close text-danger" aria-hidden="true"></span></a>';
                    }
                }
                $lRow[] = '<div class="btn-group">' . implode(" ", $links) . '</div>';

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

    public function ajaxFileReportDecline() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $reportId = (int) $request->request->get('reportId');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // update to cancelled
            $db->query('UPDATE file_report '
                    . 'SET report_status = \'cancelled\' '
                    . 'WHERE id = :reportId '
                    . 'LIMIT 1', array(
                'reportId' => $reportId,
                    )
            );

            // user action logs
            UserActionLogHelper::logAdmin('Cancelled file report #'.$reportId, 'ADMIN', 'UPDATE');

            $result['error'] = false;
            $result['msg'] = 'Report cancelled.';
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxFileReportAccept() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $reportId = $request->request->get('abuseId');
        $sendConfirmationEmail = $request->request->has('sendConfirmationEmail')?(int)$request->request->get('sendConfirmationEmail'):0;

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // update to accepted
            $db->query('UPDATE file_report '
                    . 'SET report_status = \'accepted\' '
                    . 'WHERE id = :reportId '
                    . 'LIMIT 1', array(
                'reportId' => $reportId,
                    )
            );

            // user action logs
            UserActionLogHelper::logAdmin('Accepted file report #'.$reportId, 'ADMIN', 'UPDATE');

            if ($db->affectedRows() == 1) {
                if($sendConfirmationEmail === 1) {
                    // send a confirmation email of removal
                    $sQL = "SELECT file_report.file_id, file_report.report_date, "
                            . "file_report.reported_by_name, file_report.reported_by_email, "
                            . "file_report.reported_by_address, file_report.reported_by_telephone_number, "
                            . "file_report.digital_signature, file_report.report_status, "
                            . "file_report.reported_by_ip, file_report.other_information "
                            . "FROM file_report "
                            . "LEFT JOIN file ON file_report.file_id = file.id "
                            . "WHERE file_report.id=" . (int) $reportId . " "
                            . "LIMIT 1";
                    $reportDetail = $db->getRow($sQL);
                    if ($reportDetail) {
                        // load file
                        $file = File::loadOneById($reportDetail['file_id']);

                        // send email
                        $subject = TranslateHelper::t('report_file_accept_email_subject', 'Update on file removal request for [[[SITE_NAME]]]', array('SITE_NAME' => SITE_CONFIG_SITE_NAME));
                        $replacements = array(
                            'FILE_DETAILS' => $file->getFullShortUrl(),
                            'SITE_NAME' => SITE_CONFIG_SITE_NAME,
                            'WEB_ROOT' => WEB_ROOT,
                            'REPORTER_NAME' => $reportDetail['reported_by_name'],
                        );
                        $defaultContent = "Dear [[[REPORTER_NAME]]]<br/><br/>";
                        $defaultContent .= "This is confirmation that we have removed the following file you reported on our site:<br/><br/>";
                        $defaultContent .= "- [[[FILE_DETAILS]]]<br/><br/>";
                        $defaultContent .= "If you have any further questions, feel free to contact us via [[[WEB_ROOT]]].<br/><br/>";
                        $defaultContent .= "Kind Regards,<br/>";
                        $defaultContent .= "[[[SITE_NAME]]]";
                        $htmlMsg = TranslateHelper::t('report_file_accept_email_content', $defaultContent, $replacements);

                        CoreHelper::sendHtmlEmail($reportDetail['reported_by_email'], $subject, $htmlMsg, null, strip_tags(str_replace("<br/>", "\n", $htmlMsg)));
                    }
                }

                $result['error'] = false;
                $result['msg'] = 'File removed and report accepted.';
            }
            else {
                $result['error'] = true;
                $result['msg'] = 'Could not accept report, please try again later.';
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxFileReportDetail() {
        // admin restrictions
        $this->restrictAdminAccess(10);

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $abuseId = $request->request->get('abuseId');

        // load all data
        $result['error'] = false;
        $sQL = "SELECT file_report.file_id, file_report.report_date, file_report.reported_by_name, "
                . "file_report.reported_by_email, file_report.reported_by_address, "
                . "file_report.reported_by_telephone_number, file_report.digital_signature, "
                . "file_report.report_status, file_report.reported_by_ip, "
                . "file_report.other_information "
                . "FROM file_report "
                . "LEFT JOIN file ON file_report.file_id = file.id "
                . "WHERE file_report.id=" . (int) $abuseId . " "
                . "LIMIT 1";
        $reportDetail = $db->getRow($sQL);
        if (!$reportDetail) {
            $result['error'] = true;
            $result['msg'] = 'Failed finding the report.';
        }
        else {
            $file = File::loadOneById($reportDetail['file_id']);
            $statusCssClass = 'statusText' . str_replace(" ", "", UCWords($reportDetail['report_status']));

            $result['html'] = $this->getRenderedTemplate('admin/ajax/file_report_detail.html', array(
                'file' => $file,
                'reportDetail' => $reportDetail,
                'statusCssClass' => $statusCssClass,
                'reportDateFormatted' => CoreHelper::formatDate($reportDetail['report_date'], SITE_CONFIG_DATE_TIME_FORMAT),
            ));
        }

        // output response
        return $this->renderJson($result);
    }

    public function fileReportManageBulkRemove() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $Auth = AuthHelper::getAuth();
        $maxAbuseRemoveUrls = 500;

        // allow some time to run
        set_time_limit(60 * 60);

        // handle page submissions
        $file_urls = '';
        $confirm_password = '';
        $removal_type = 4;
        $admin_notes = '';
        $deleteRs = [];
        if ($request->request->has('submitted')) {
            // pickup variables
            $file_urls = trim($request->request->get('file_urls'));
            $confirm_password = trim($request->request->get('confirm_password'));
            $removal_type = (int) $request->request->get('removal_type');
            $admin_notes = trim($request->request->get('admin_notes'));

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            elseif (strlen($file_urls) == 0) {
                AdminHelper::setError(AdminHelper::t("file_report_manage_bulk_remove_enter_file_urls", "Please enter the file urls."));
            }
            elseif (strlen($confirm_password) == 0) {
                AdminHelper::setError(AdminHelper::t("file_report_manage_bulk_remove_enter_password", "Please enter your account password."));
            }

            // check password
            if (AdminHelper::isErrors() === false) {
                $storedUserPassword = $db->getValue('SELECT password '
                        . 'FROM users '
                        . 'WHERE id = :id '
                        . 'LIMIT 1', array(
                    'id' => $Auth->id,
                ));
                if (Password::validatePassword($confirm_password, $storedUserPassword) == false) {
                    AdminHelper::setError(AdminHelper::t("file_report_manage_bulk_remove_entered_password_is_invalid", "The account password you entered is incorrect."));
                }
            }

            if (AdminHelper::isErrors() === false) {
                // loop urls
                $file_urls = str_replace(array("\n\r", "\r\n", "\r\r", "\r"), "\n", $file_urls);
                $file_urls = str_replace(array("\n\n"), "\n", $file_urls);
                $fileUrlItems = explode("\n", $file_urls);
                if (count($fileUrlItems) > $maxAbuseRemoveUrls) {
                    AdminHelper::setError(AdminHelper::t("file_report_manage_bulk_remove_entered_too_many", "Too many urls, max [[[URLS]]].", array('URLS' => $maxAbuseRemoveUrls)));
                }
            }

            if (AdminHelper::isErrors() === false) {
                // loop urls and process
                foreach ($fileUrlItems AS $fileUrlItem) {
                    $file = FileHelper::loadByFullUrl($fileUrlItem);
                    if (!$file) {
                        // make sure we've found the file
                        $deleteRs[] = '<i class="fa fa-close text-danger"></i> <span class="text-danger">' . AdminHelper::t("file_report_manage_bulk_remove_file_not_found", "Error: File not found") . '</span> - ' . AdminHelper::makeSafe($fileUrlItem);
                    }
                    elseif ($file->status !== 'active') {
                        // make sure the file is active
                        $deleteRs[] = '<i class="fa fa-close text-danger"></i> <span class="text-danger">' . AdminHelper::t("file_report_manage_bulk_remove_file_not_active", "Error: File not active") . '</span> - ' . AdminHelper::makeSafe($fileUrlItem);
                    }
                    else {
                        // delete the file
                        $file->removeBySystem();
                        $file->status_reason_id = $removal_type;
                        $file->adminNotes = $admin_notes;
                        $file->save();

                        // user action logs
                        UserActionLogHelper::logAdmin('File deleted via admin bulk file report', 'ADMIN', 'DELETE', [
                            'file_id' => $file->id,
                            'admin_notes' => $admin_notes,
                            'data' => (array)$file,
                        ]);

                        $deleteRs[] = '<i class="fa fa-check text-success"></i> <span class="text-success">' . AdminHelper::t("file_report_manage_bulk_remove_file_deleted", "Success: File deleted") . '</span> - ' . AdminHelper::makeSafe($fileUrlItem);
                    }
                }
            }
        }

        // load template
        return $this->render('admin/file_report_manage_bulk_remove.html', array(
                    'maxAbuseRemoveUrls' => $maxAbuseRemoveUrls,
                    'file_urls' => $file_urls,
                    'confirm_password' => $confirm_password,
                    'removal_type' => $removal_type,
                    'admin_notes' => $admin_notes,
                    'deleteRs' => $deleteRs,
                                ));
    }

}
