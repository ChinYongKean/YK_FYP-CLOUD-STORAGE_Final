<?php

namespace Plugins\Newsletters\Controllers\Admin;

use App\Core\Database;
use App\Controllers\Admin\PluginController AS CorePluginController;
use App\Helpers\AdminHelper;
use App\Helpers\CoreHelper;
use App\Helpers\PluginHelper;
use App\Helpers\UserActionLogHelper;
use App\Models\Plugin;
use Plugins\Newsletters\Models\PluginNewsletter;
use Plugins\Newsletters\Models\PluginNewsletterSent;

class PluginController extends CorePluginController
{

    public function pluginSettings() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'newsletters';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        $plugin_enabled = (int) $plugin->plugin_enabled;
        $test_email_address = SITE_CONFIG_SITE_ADMIN_EMAIL;
        $unsubscribe_text = 'You are receiving this email as you have an account on our site. If you wish to unsubscribe from future emails, please <a href="[[[unsubscribe_link]]]">click here</a>.';
        $send_email_from_email = 'email@yoursite.com';


        // load existing settings
        if (strlen($plugin->plugin_settings)) {
            $plugin_settings = json_decode($plugin->plugin_settings, true);
            if ($plugin_settings) {
                $test_email_address = $plugin_settings['test_email_address'];
                $unsubscribe_text = $plugin_settings['unsubscribe_text'];
                $send_email_from_email = $plugin_settings['send_email_from_email'];
            }
        }

        // handle page submissions
        if ($request->request->has('submitted')) {
            // get variables
            $oldPluginSettings = json_decode($plugin->plugin_settings, true);
            $plugin_enabled = (int) $request->request->get('plugin_enabled');
            $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;
            $test_email_address = trim(strtolower($request->request->get('test_email_address')));
            $unsubscribe_text = $request->request->get('unsubscribe_text');
            $send_email_from_email = trim(strtolower($request->request->get('send_email_from_email')));

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t('no_changes_in_demo_mode', 'No change permitted in demo mode.'));
            }

            // update the settings
            if (AdminHelper::isErrors() == false) {
                // compile new settings
                $settingsArr = [];
                $settingsArr['test_email_address'] = $test_email_address;
                $settingsArr['unsubscribe_text'] = $unsubscribe_text;
                $settingsArr['send_email_from_email'] = $send_email_from_email;

                // update the plugin settings
                $plugin->plugin_enabled = $plugin_enabled;
                $plugin->plugin_settings = json_encode($settingsArr);
                $plugin->save();

                // reload plugin cache
                PluginHelper::loadPluginConfigurationFiles(true);

                // user action logs
                UserActionLogHelper::logAdmin('Edited "'.$folderName.'" plugin settings', 'ADMIN', 'UPDATE', [
                    'plugin' => $folderName,
                    'data' => UserActionLogHelper::getChangedData($oldPluginSettings, $settingsArr),
                ]);

                // set onscreen alert
                AdminHelper::setSuccess('Plugin settings updated.');
            }
        }

        // load template
        return $this->render('admin/plugin_settings.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(
                        0 => 'No',
                        1 => 'Yes'),
                    'plugin_enabled' => $plugin_enabled,
                    'test_email_address' => $test_email_address,
                    'unsubscribe_text' => $unsubscribe_text,
                    'send_email_from_email' => $send_email_from_email,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    public function manageNewsletter() {
        // admin restrictions
        $this->restrictAdminAccess();

        // for later
        $request = $this->getRequest();
        $newslettersObj = PluginHelper::getInstance('newsletters');
        $newslettersSettings = $newslettersObj->getPluginSettings();

        // load template
        return $this->render('admin/manage_newsletter.html', array(
                    'newslettersObj' => $newslettersObj,
                    'newslettersSettings' => $newslettersSettings,
                    'triggerCreate' => $request->query->has('create')?$request->query->get('create'):0,
                        ), PLUGIN_DIRECTORY_ROOT . 'newsletters/views');
    }

    public function ajaxManageNewsletter() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $iDisplayLength = (int) $request->query->get('iDisplayLength');
        $iDisplayStart = (int) $request->query->get('iDisplayStart');
        $sSortDir_0 = ($request->query->has('sSortDir_0') && $request->query->get('sSortDir_0') === 'asc') ? 'asc' : 'desc';
        $filterText = $request->query->has('filterText') ? $request->query->get('filterText') : null;

        // setup joins
        $joins = [];

        // get sorting columns
        $iSortCol_0 = (int) $request->query->get('iSortCol_0');
        $sColumns = trim($request->query->get('sColumns'));
        $arrCols = explode(",", $sColumns);
        $sortColumnName = $arrCols[$iSortCol_0];
        $sort = 'plugin_newsletter.date_created';
        switch ($sortColumnName) {
            case 'title':
                $sort = 'plugin_newsletter.title';
                break;
            case 'date':
                $sort = 'plugin_newsletter.date_created';
                break;
            case 'subject':
                $sort = 'plugin_newsletter.subject';
                break;
            case 'status':
                $sort = 'plugin_newsletter.status';
                break;
        }

        $sqlClause = "WHERE 1=1 ";
        if ($filterText) {
            $filterText = strtolower($db->escape($filterText));
            $sqlClause .= "AND (LOWER(plugin_newsletter.title) LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "LOWER(plugin_newsletter.subject) LIKE '%" . $filterText . "%' OR ";
            $sqlClause .= "LOWER(plugin_newsletter.status) = '" . $filterText . "' OR ";
            $sqlClause .= "LOWER(plugin_newsletter.html_content) LIKE '%" . $filterText . "%')";
        }

        $sQL = "SELECT * FROM plugin_newsletter ";
        $sQL .= $sqlClause . " ";
        $totalRS = $db->getRows($sQL);

        $sQL .= "ORDER BY " . $sort . " " . $db->escape($sSortDir_0) . " ";
        $sQL .= "LIMIT " . $iDisplayStart . ", " . $iDisplayLength;
        $limitedRS = $db->getRows($sQL);

        $data = [];
        if (count($limitedRS) > 0) {
            foreach ($limitedRS AS $row) {
                $lRow = [];

                $icon = 'local';
                $lRow[] = '<img src="' . PLUGIN_WEB_ROOT . '/newsletters/assets/img/icons/16px_' . $row['status'] . '.png" width="16" height="16" title="' . UCWords($row['status']) . '" alt="' . UCWords($row['status']) . '"/>';
                $lRow[] = AdminHelper::makeSafe(CoreHelper::formatDate($row['date_created'], SITE_CONFIG_DATE_TIME_FORMAT));
                $lRow[] = AdminHelper::makeSafe($row['title']);
                $lRow[] = AdminHelper::makeSafe($row['subject']);
                $lRow[] = AdminHelper::makeSafe((int)$row['form_type']==1?'Newsletter':'Service Email');
                $lRow[] = '<span class="statusText' . str_replace(" ", "", UCWords($row['status'])) . '">' . UCWords($row['status']) . ($row['status'] == 'sent' ? ('&nbsp;&nbsp;<font style="color: #999;">(' . CoreHelper::formatDate($row['date_sent'], SITE_CONFIG_DATE_TIME_FORMAT) . ')</font>') : '') . '</span>';

                $links = [];
                $links[] = '<a href="#" onClick="viewNewsletter(' . (int) $row['id'] . '); return false;">view</a>';
                if ($row['status'] == 'draft') {
                    if((int)$row['form_type'] === 1) {
                        $links[] = '<a href="#" onClick="editNewsletterForm(' . (int) $row['id'] . '); return false;">edit</a>';
                    }
                    else {
                        $links[] = '<a href="#" onClick="editServiceForm(' . (int) $row['id'] . '); return false;">edit</a>';
                    }
                    $links[] = '<a href="#" onClick="confirmRemoveNewsletter(' . (int) $row['id'] . ', \'' . AdminHelper::makeSafe($row['serverLabel']) . '\', ' . (int) $row['totalFiles'] . '); return false;">remove</a>';
                }
                $lRow[] = implode(" | ", $links);

                $data[] = $lRow;
            }
        }

        $resultArr = [];
        $resultArr["sEcho"] = intval($request->query->get('sEcho'));
        $resultArr["iTotalRecords"] = (int) $totalRS;
        $resultArr["iTotalDisplayRecords"] = $resultArr["iTotalRecords"];
        $resultArr["aaData"] = $data;

        // output response
        return $this->renderJson($resultArr);
    }

    public function ajaxManageNewsletterAddForm() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();
        $formType = (int) $request->request->get('formType');

        // load values
        $accountTypeDetails = $db->getRows('SELECT id, level_id, label '
                    . 'FROM user_level '
                    . 'WHERE id > 0 '
                    . 'ORDER BY level_id ASC');

        // prepare variables
        $title = '';
        $userGroup = '';
        $subject = '';
        $htmlContent = '';

        // is this an edit?
        $editNewsletterId = (int) $request->request->get('gEditNewsletterId');
        if ($editNewsletterId > 0) {
            $pluginNewsletter = PluginNewsletter::loadOneById($editNewsletterId);
            if ($pluginNewsletter) {
                $title = $pluginNewsletter->title;
                $userGroup = $pluginNewsletter->user_group;
                $formType = $pluginNewsletter->form_type;
                $subject = $pluginNewsletter->subject;
                $htmlContent = $pluginNewsletter->html_content;

                // backwards compatible with older levels
                switch ($userGroup) {
                    case 'free only':
                        $userGroup = 1;
                        break;
                    case 'premium only':
                        $userGroup = 2;
                        break;
                    case 'moderator only':
                        $userGroup = 10;
                        break;
                    case 'moderator only':
                        $userGroup = 20;
                        break;
                    case (int) $userGroup:
                        break;
                    default:
                        $userGroup = '';
                }
            }
        }

        // formType = 1 is newsletters
        if($formType === 1) {
            $allUsersTotal = (int) $db->getValue('SELECT COUNT(id) AS total '
                            . 'FROM users '
                            . 'WHERE level_id > 0 '
                            . 'AND status=\'active\' '
                            . 'AND (email != "" AND email IS NOT NULL) '
                            . 'AND id NOT IN (SELECT user_id FROM plugin_newsletter_unsubscribe)');
            foreach ($accountTypeDetails AS $k => $accountTypeDetail) {
                $accountTypeDetails[$k]['_total_users'] = (int) $db->getValue('SELECT COUNT(id) AS total '
                                . 'FROM users '
                                . 'WHERE status=\'active\' '
                                . 'AND level_id = :level_id '
                                . 'AND id NOT IN (SELECT user_id FROM plugin_newsletter_unsubscribe)', array(
                            'level_id' => (int) $accountTypeDetail['level_id'],
                ));
            }
        }
        // formType = 2 is service emails
        else {
            $allUsersTotal = (int) $db->getValue('SELECT COUNT(id) AS total '
                            . 'FROM users '
                            . 'WHERE level_id > 0 '
                            . 'AND status=\'active\' '
                            . 'AND (email != "" AND email IS NOT NULL)');
            foreach ($accountTypeDetails AS $k => $accountTypeDetail) {
                $accountTypeDetails[$k]['_total_users'] = (int) $db->getValue('SELECT COUNT(id) AS total '
                                . 'FROM users '
                                . 'WHERE status=\'active\' '
                                . 'AND level_id = :level_id', array(
                            'level_id' => (int) $accountTypeDetail['level_id'],
                ));
            }
        }

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';
        $result['html'] = $this->getRenderedTemplate('admin/ajax/newsletter_add_form.html', array(
            'allUsersTotal' => $allUsersTotal,
            'accountTypeDetails' => $accountTypeDetails,
            'title' => $title,
            'userGroup' => $userGroup,
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'formType' => $formType,
                ), PLUGIN_DIRECTORY_ROOT . 'newsletters/views');

        // output response
        return $this->renderJson($result);
    }

    public function ajaxManageNewsletterAddProcess() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $title = trim($request->request->get('title'));
        $userGroup = trim($request->request->get('userGroup'));
        $subject = trim($request->request->get('subject'));
        $htmlContent = trim($request->request->get('htmlContent'));
        $existingNewsletterId = (int) $request->request->get('gEditNewsletterId');
        $formType = (int) $request->request->get('formType');
        $send = (int) $request->request->get('send');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        // validate submission
        if (strlen($title) == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_newsletter_enter_title", "Please enter the newsletter title.");
        }
        elseif ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        elseif (strlen($subject) == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_newsletter_enter_subject", "Please enter the newsletter subject.");
        }
        elseif (strlen($htmlContent) == 0) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("plugin_newsletter_enter_content", "Please enter the newsletter content.");
        }

        if (strlen($result['msg']) == 0) {
            if ($existingNewsletterId > 0) {
                // update the existing record
                $pluginNewsletter = PluginNewsletter::loadOneById($existingNewsletterId);
                $oldPluginNewsletter = $pluginNewsletter;
                $pluginNewsletter->title = $title;
                $pluginNewsletter->subject = $subject;
                $pluginNewsletter->html_content = $htmlContent;
                $pluginNewsletter->form_type = $formType;
                $pluginNewsletter->user_group = $userGroup;
                $pluginNewsletter->save();

                $result['error'] = false;
                $result['msg'] = 'Newsletter updated.';

                // user action logs
                UserActionLogHelper::log('Newsletter updated', 'PLUGIN', 'UPDATE', [
                    'plugin' => 'newsletters',
                    'data' => UserActionLogHelper::getChangedData($oldPluginNewsletter, $pluginNewsletter)
                ]);
            }
            else {
                // add the newsletter
                $pluginNewsletter = PluginNewsletter::create();
                $pluginNewsletter->title = $title;
                $pluginNewsletter->subject = $subject;
                $pluginNewsletter->html_content = $htmlContent;
                $pluginNewsletter->form_type = $formType;
                $pluginNewsletter->user_group = $userGroup;
                $pluginNewsletter->status = 'draft';
                $pluginNewsletter->date_created = CoreHelper::sqlDateTime();
                if (!$pluginNewsletter->save()) {
                    $result['error'] = true;
                    $result['msg'] = AdminHelper::t("plugin_newsletter_error_problem_record", "There was a problem adding the newsletter, please try again.");
                }
                else {
                    // user action logs
                    UserActionLogHelper::log('Newsletter created', 'PLUGIN', 'ADD', [
                        'plugin' => 'newsletters',
                        'data' => UserActionLogHelper::getNewDataFromObject($pluginNewsletter),
                    ]);

                    $result['error'] = false;
                    $result['msg'] = 'Newsletter added and saved as draft.';
                    $existingNewsletterId = $pluginNewsletter->id;
                }
            }
        }

        // should we attempt to send a test?
        if (($result['error'] == false) && ($send == 2)) {
            // get instance
            $newslettersObj = PluginHelper::getInstance('newsletters');
            $newslettersSettings = $newslettersObj->getPluginSettings();
            $emailAddress = $newslettersSettings['test_email_address'];

            // prepare unsubscribe link
            $unsubscribeLink = WEB_ROOT . '/newsletter_unsubscribe';

            // create email content
            $replacedHtmlContent = $htmlContent;
            $replacedSubject = $subject;

            // add on unsubscribe text
            if($formType === 1) {
                $replacedHtmlContent .= '<br/><br/><font style="font-size: 10px; color: #666;">' . $newslettersSettings['unsubscribe_text'] . '</font>';
            }

            // other replacements
            $replacedHtmlContent = str_replace('[[[current_date]]]', date(SITE_CONFIG_DATE_FORMAT), $replacedHtmlContent);
            $replacedHtmlContent = str_replace('[[[current_time]]]', date('H:i'), $replacedHtmlContent);
            $replacedHtmlContent = str_replace('[[[unsubscribe_link]]]', $unsubscribeLink, $replacedHtmlContent);
            $replacedSubject = str_replace('[[[current_date]]]', date(SITE_CONFIG_DATE_FORMAT), $replacedSubject);
            $replacedSubject = str_replace('[[[current_time]]]', date('H:i'), $replacedSubject);
            $replacedSubject = str_replace('[[[unsubscribe_link]]]', $unsubscribeLink, $replacedSubject);

            // send
            $rs = $newslettersObj->sendNewsletter($replacedSubject, $replacedHtmlContent, $emailAddress, $newslettersSettings['send_email_from_email']);

            // update confirmation message
            $result['msg'] = ($formType===1?'Newsletter':'Service email').' test sent to ' . $emailAddress . '.';
        }

        // should we attempt to send the newsletter?
        if (($result['error'] == false) && ($send == 1)) {
            // get instance
            $newslettersObj = PluginHelper::getInstance('newsletters');
            $newslettersSettings = $newslettersObj->getPluginSettings();

            // get all emails for newsletter
            $includeUnsubs = $formType==2?true:false;
            $emailRecipients = $newslettersObj->getRecipients($userGroup, $includeUnsubs);

            // update the existing record
            $pluginNewsletter = PluginNewsletter::loadOneById($existingNewsletterId);
            $pluginNewsletter->status = 'sending';
            $pluginNewsletter->save();

            // loop recipients and send
            if (count($emailRecipients)) {
                foreach ($emailRecipients AS $emailRecipient) {
                    // prepare unsubscribe link
                    $unsubscribeLink = WEB_ROOT . '/newsletter_unsubscribe?e=' . urlencode($emailRecipient['email']);

                    // create email content
                    $replacedHtmlContent = $htmlContent;
                    $replacedSubject = $subject;
                    foreach ($emailRecipient AS $columName => $columValue) {
                        $replacedHtmlContent = str_replace('[[[' . $columName . ']]]', $columValue, $replacedHtmlContent);
                        $replacedSubject = str_replace('[[[' . $columName . ']]]', $columValue, $replacedSubject);
                    }

                    // add on unsubscribe text
                    if($formType === 1) {
                        $replacedHtmlContent .= '<br/><br/><font style="font-size: 10px; color: #666;">' . $newslettersSettings['unsubscribe_text'] . '</font>';
                    }

                    // other replacements
                    $replacedHtmlContent = str_replace('[[[current_date]]]', date(SITE_CONFIG_DATE_FORMAT), $replacedHtmlContent);
                    $replacedHtmlContent = str_replace('[[[current_time]]]', date('H:i'), $replacedHtmlContent);
                    $replacedHtmlContent = str_replace('[[[unsubscribe_link]]]', $unsubscribeLink, $replacedHtmlContent);
                    $replacedSubject = str_replace('[[[current_date]]]', date(SITE_CONFIG_DATE_FORMAT), $replacedSubject);
                    $replacedSubject = str_replace('[[[current_time]]]', date('H:i'), $replacedSubject);
                    $replacedSubject = str_replace('[[[unsubscribe_link]]]', $unsubscribeLink, $replacedSubject);

                    // send
                    $rs = $newslettersObj->sendNewsletter($replacedSubject, $replacedHtmlContent, $emailRecipient['email'], $newslettersSettings['send_email_from_email']);

                    // add to audit
                    $pluginNewsletterSent = PluginNewsletterSent::create();
                    $pluginNewsletterSent->to_email_address = $emailRecipient['email'];
                    $pluginNewsletterSent->to_user_id = $emailRecipient['id'];
                    $pluginNewsletterSent->subject = $replacedSubject;
                    $pluginNewsletterSent->html_content = $replacedHtmlContent;
                    $pluginNewsletterSent->date_created = CoreHelper::sqlDateTime();
                    $pluginNewsletterSent->newsletter_id = $existingNewsletterId;
                    if ($rs == true) {
                        $pluginNewsletterSent->date_sent = CoreHelper::sqlDateTime();
                        $pluginNewsletterSent->status = 'sent';
                    }
                    else {
                        $pluginNewsletterSent->status = 'failed';
                    }
                    $pluginNewsletterSent->save();

                    // user action logs
                    UserActionLogHelper::log('Sent newsletter', 'PLUGIN', 'ADD', [
                        'plugin' => 'newsletters',
                        'user_id' => $emailRecipient['id'],
                        'data' => [
                            'subject' => $replacedSubject,
                        ],
                    ]);
                }
            }

            // update status to sent
            $pluginNewsletter->status = 'sent';
            $pluginNewsletter->date_sent = CoreHelper::sqlDateTime();
            $pluginNewsletter->save();

            // update confirmation message
            $result['msg'] = ($formType===1?'Newsletter':'Service email').' sent.';
        }

        // output response
        return $this->renderJson($result);
    }

    public function ajaxManageNewsletterRemove() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        $gRemoveNewsletterId = (int) $request->request->get('gRemoveNewsletterId');

        // prepare result
        $result = [];
        $result['error'] = false;
        $result['msg'] = '';

        if ($this->inDemoMode()) {
            $result['error'] = true;
            $result['msg'] = AdminHelper::t("no_changes_in_demo_mode");
        }
        else {
            // load and remove data
            $pluginNewsletter = PluginNewsletter::loadOneById($gRemoveNewsletterId);
            $oldPluginNewsletter = $pluginNewsletter;
            $formType = $pluginNewsletter->form_type;
            if ($pluginNewsletter->delete() === true) {
                // user action logs
                UserActionLogHelper::log('Removed '.($formType==1?'newsletter':'service email'), 'PLUGIN', 'DELETE', [
                    'plugin' => 'newsletters',
                    'data' => UserActionLogHelper::getNewDataFromObject($oldPluginNewsletter),
                ]);

                $result['error'] = false;
                $result['msg'] = ($formType==1?'Newsletter':'Service email').' removed.';
            }
            else {
                $result['error'] = true;
                $result['msg'] = 'Could not remove newsletter, please try again later.';
            }
        }

        // output response
        return $this->renderJson($result);
    }

    public function exportUserData() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $db = Database::getDatabase();
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'newsletters';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        // get instance
        $newslettersObj = PluginHelper::getInstance($folderName);

        // prepare data
        $availableColumns = [];
        $availableColumns['email'] = 'Email Address';
        $availableColumns['title'] = 'Title';
        $availableColumns['firstname'] = 'First Name';
        $availableColumns['lastname'] = 'Last Name';
        $availableColumns['username'] = 'Username';
        $availableColumns['level'] = 'Account Type';
        $availableColumns['status'] = 'Account Status';
        $availableColumns['datecreated'] = 'Date Created';
        $availableColumns['paidExpiryDate'] = 'Paid Expiry Date';
        $availableColumns['lastPayment'] = 'Last Payment Date';

        // available formats
        $availableFormats = [];
        $availableFormats['csv'] = 'CSV';

        // default values
        $columns = [];
        $includeUnsubscribed = '0';
        $exportFormat = 'csv';
        $userGroup = 'all registered';

        // handle page submissions
        if ($request->request->has('submitted')) {
            // get variables
            $columns = $request->request->get('columns');
            $includeUnsubscribed = (int) $request->request->get('includeUnsubscribed');
            $exportFormat = trim($request->request->get('exportFormat'));
            $userGroup = trim($request->request->get('userGroup'));

            // validate submission
            if ($this->inDemoMode()) {
                AdminHelper::setError(AdminHelper::t("no_changes_in_demo_mode"));
            }
            elseif (count($columns) == 0) {
                AdminHelper::setError(AdminHelper::t("plugin_newsletter_please_choose_at_least_1_column", "Please choose at least 1 column."));
            }

            // update the settings
            if (AdminHelper::isErrors() == false) {
                // get all emails for newsletter
                $emailRecipients = $newslettersObj->getRecipients($userGroup, ($includeUnsubscribed == 1 ? true : false));

                // export data
                $dataExport = [];
                if (count($emailRecipients)) {
                    foreach ($emailRecipients AS $emailRecipient) {
                        $lArr = [];
                        foreach ($emailRecipient AS $columnName => $columnValue) {
                            if (in_array($columnName, $columns)) {
                                $lArr[$columnName] = $columnValue;
                            }
                        }
                        $dataExport[] = $lArr;
                    }
                }

                if (count($dataExport) === 0) {
                    AdminHelper::setError(AdminHelper::t("plugin_newsletter_no_data_found", "No data found."));
                }
                else {
                    // allow for 10 minutes for the export
                    set_time_limit(60 * 10);

                    // resulting csv data
                    $out = fopen('php://output', 'w');

                    // header 
                    $headerCols = [];
                    foreach ($dataExport AS $row) {
                        foreach ($row AS $k => $cell) {
                            $headerCols[$k] = $availableColumns[$k];
                        }
                    }
                    fputcsv($out, $headerCols);

                    // create data
                    foreach ($dataExport AS $row) {
                        fputcsv($out, $row);
                    }
                    $data = [];
                    array_walk($data, '__outputCSV', $out);
                    fclose($out);

                    // user action logs
                    UserActionLogHelper::log('Exported newsletter data', 'PLUGIN', 'EXPORT', [
                        'plugin' => 'newsletters',
                        'data' => [
                            'filename' => 'user_export_' . date('YmdHis'),
                        ],
                    ]);

                    return $this->renderDownloadFile($data, 'user_export_' . date('YmdHis') . '.' . $exportFormat);
                }
            }
        }

        // load template
        return $this->render('admin/export_user_data.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'yesNoOptions' => array(
                        0 => 'No',
                        1 => 'Yes'),
                    'availableColumns' => $availableColumns,
                    'availableFormats' => $availableFormats,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

    public function manageNewsletterView() {
        // admin restrictions
        $this->restrictAdminAccess();

        // pickup request
        $request = $this->getRequest();

        // load plugin details
        $folderName = 'newsletters';
        $plugin = Plugin::loadOneByClause('folder_name = :folder_name', array(
                    'folder_name' => $folderName,
        ));

        if (!$plugin) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the plugin details.'));
        }

        $newsletterId = (int) $request->query->get('id');
        if (!$newsletterId) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the newsletter.'));
        }

        // load newsletter
        $pluginNewsletter = PluginNewsletter::loadOneById($newsletterId);
        if (!$pluginNewsletter) {
            return $this->redirect(ADMIN_WEB_ROOT . '/plugin_manage?error=' . urlencode('There was a problem loading the newsletter.'));
        }

        // load template
        return $this->render('admin/manage_newsletter_view.html', array(
                    'pluginName' => $plugin->plugin_name,
                    'pluginNewsletter' => $pluginNewsletter,
                                ), PLUGIN_DIRECTORY_ROOT . $folderName . '/views');
    }

}
