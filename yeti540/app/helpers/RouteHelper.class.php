<?php

namespace App\Helpers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RouteHelper
{
    // the dispatcher
    private static $dispatcher = null;

    /**
     * IMPORTANT: If you edit any routes, either here or in the plugins/themes,
     * ensure you delete route.cache in the cache folder. You can also clear the
     * application cache via the admin area.
     */
    public static function registerRoutes() {
        // reference - https://github.com/nikic/FastRoute
        self::$dispatcher = \FastRoute\cachedDispatcher(function(\FastRoute\RouteCollector $r) {
            // add plugin routes
            PluginHelper::registerRoutes($r);
            
            // add theme routes
            ThemeHelper::registerRoutes($r);

            // add core routes
            $r->addRoute(['GET'], '/index', 'IndexController/index');
            $r->addRoute(['GET', 'POST'], '/register', 'IndexController/register');
            $r->addRoute(['GET'], '/register_complete', 'IndexController/registerComplete');
            $r->addRoute(['GET'], '/terms', 'IndexController/terms');
            $r->addRoute(['GET'], '/privacy', 'IndexController/privacy');
            $r->addRoute(['GET'], '/down_for_maintenance', 'IndexController/downForMaintenance');
            $r->addRoute(['GET'], '/js/translations.js', 'IndexController/jsTranslations');
            $r->addRoute(['GET'], '/error', 'IndexController/error');
            $r->addRoute(['GET'], '/folder/{folderUrlHash}[/{folderName}]', 'AccountFolderController/viewFolder');
            $r->addRoute(['GET'], '/shared/{accessKey}', 'AccountSharingController/viewShare');
            $r->addRoute(['GET', 'POST'], '/api/v2/{urlPart1}[/{urlPart2}]', 'ApiController/index');
            $r->addRoute(['GET', 'POST', 'OPTIONS'], '/api_upload_handler', 'ApiController/apiUploadHandler');
            $r->addRoute(['GET'], '/assets/js/uploader.js', 'AccountUploadController/uploaderJs');
            $r->addRoute(['GET', 'POST'], '/file_password', 'FileController/filePassword');
            $r->addRoute(['GET', 'POST'], '/{shortUrl}~s', 'FileController/fileStats');
            $r->addRoute(['GET', 'POST'], '/{shortUrl}~i', 'FileController/fileInfo');
            $r->addRoute(['GET'], '/set_language/{languageName}', 'IndexController/setLanguage');
            $r->addRoute(['GET'], '/register_non_user', 'IndexController/registerNonUser');
            if(defined('SITE_CONFIG_ADBLOCK_FILENAME')) {
                $r->addRoute(['GET'], '/js/'.SITE_CONFIG_ADBLOCK_FILENAME, 'IndexController/jsAdblock');
            }
            
            // for custom upload urls by account type
            $r->addRoute(['POST', 'OPTIONS'], '/{uploadUrlSlug}/ajax/file_upload_handler', 'FileController/ajaxFileUploadHandler');

            $r->addGroup('/ajax', function (\FastRoute\RouteCollector $r) {
                $r->addRoute(['POST'], '/folder_password_process', 'IndexController/folderPasswordProcess');
                $r->addRoute(['POST', 'OPTIONS'], '/file_upload_handler', 'FileController/ajaxFileUploadHandler');
                $r->addRoute(['GET'], '/url_upload_handler', 'FileController/ajaxUrlUploadHandler');
                $r->addRoute(['POST'], '/update_file_options', 'FileController/ajaxUpdateFileOptions');
                $r->addRoute(['GET'], '/non_account_download_all_as_zip', 'FileController/ajaxDownloadAllAsZip');
                $r->addRoute(['GET'], '/non_account_download_all_as_zip_get_file/{fileName}/{downloadZipName}', 'FileController/ajaxDownloadAllAsZipGetFile');
            });

            $r->addGroup('/account', function (\FastRoute\RouteCollector $r) {
                $r->addRoute(['GET', 'POST'], '/login', 'AccountSecurityController/login');
                $r->addRoute(['GET', 'POST'], '/login_2fa', 'AccountSecurityController/login2FA');
                $r->addRoute(['GET', 'POST'], '/forgot_password', 'AccountSecurityController/forgotPassword');
                $r->addRoute(['GET'], '/forgot_password_confirm', 'AccountSecurityController/forgotPasswordConfirm');
                $r->addRoute(['GET', 'POST'], '/forgot_password_reset', 'AccountSecurityController/forgotPasswordReset');
                $r->addRoute(['GET'], '/logout', 'AccountSecurityController/logout');
                $r->addRoute(['GET'], '', 'AccountController/index');
                $r->addRoute(['GET', 'POST'], '/edit', 'AccountSettingsController/edit');
                $r->addRoute(['GET'], '/avatar/{userId:[0-9]+}/{width:[0-9]+}x{height:[0-9]+}.png', 'AccountSettingsController/viewAccountAvatar');
                $r->addRoute(['GET'], '/register_complete', 'AccountController/registerComplete');
                $r->addRoute(['GET'], '/search/', 'AccountSearchController/search');
                $r->addRoute(['GET'], '/shared_with_me', 'AccountFolderController/nonFolderFileManagerPage');
                $r->addRoute(['GET'], '/recent', 'AccountFolderController/nonFolderFileManagerPage');
                $r->addRoute(['GET'], '/all_files', 'AccountFolderController/nonFolderFileManagerPage');
                $r->addRoute(['GET'], '/trash', 'AccountFolderController/nonFolderFileManagerPage');
                $r->addRoute(['GET'], '/direct_download/{fileId:[0-9]+}/{fileHash}/{filename}', 'AccountDownloadController/directDownload');

                $r->addGroup('/ajax', function (\FastRoute\RouteCollector $r) {
                    $r->addRoute(['POST'], '/login', 'AccountSecurityController/ajaxLogin');
                    $r->addRoute(['POST'], '/uploader', 'AccountUploadController/ajaxUploader');
                    $r->addRoute(['POST'], '/forgot_password', 'AccountSecurityController/ajaxForgotPassword');
                    $r->addRoute(['POST'], '/forgot_password_reset', 'AccountSecurityController/ajaxForgotPasswordReset');
                    $r->addRoute(['POST'], '/get_account_file_stats', 'AccountFileController/ajaxGetAccountFileStats');
                    $r->addRoute(['GET'], '/home_v2_folder_listing', 'AccountFolderController/ajaxHomeV2FolderListing');
                    $r->addRoute(['POST'], '/load_files', 'AccountFolderController/ajaxLoadFiles');
                    $r->addRoute(['POST'], '/edit_file', 'AccountFileController/ajaxEditFile');
                    $r->addRoute(['POST'], '/file_stats', 'AccountFileController/ajaxFileStats');
                    $r->addRoute(['POST'], '/file_details', 'AccountFileController/ajaxFileDetails');
                    $r->addRoute(['POST'], '/edit_file_process', 'AccountFileController/ajaxEditFileProcess');
                    $r->addRoute(['POST'], '/duplicate_file', 'AccountFileController/ajaxDuplicateFile');
                    $r->addRoute(['POST'], '/trash_files', 'AccountFileController/ajaxTrashFiles');
                    $r->addRoute(['POST'], '/delete_files', 'AccountFileController/ajaxDeleteFiles');
                    $r->addRoute(['POST'], '/restore_from_trash', 'AccountFileController/ajaxRestoreFromTrash');
                    $r->addRoute(['POST'], '/restore_from_trash_process', 'AccountFileController/ajaxRestoreFromTrashProcess');
                    $r->addRoute(['POST'], '/copy_to_folder', 'AccountFileController/ajaxCopyToFolder');
                    $r->addRoute(['POST'], '/copy_to_folder_process', 'AccountFileController/ajaxCopyToFolderProcess');
                    $r->addRoute(['POST'], '/move_to_folder', 'AccountFileController/ajaxMoveToFolder');
                    $r->addRoute(['GET'], '/empty_trash', 'AccountFileController/ajaxEmptyTrash');
                    $r->addRoute(['POST'], '/add_edit_folder', 'AccountFolderController/ajaxAddEditFolder');
                    $r->addRoute(['POST'], '/add_edit_folder_process', 'AccountFolderController/ajaxAddEditFolderProcess');
                    $r->addRoute(['GET'], '/drag_files_into_folder', 'AccountFileController/ajaxDragFilesIntoFolder');
                    $r->addRoute(['POST'], '/share_file_folder', 'AccountFolderController/ajaxShareFileFolder');
                    $r->addRoute(['POST'], '/share_file_folder_internally', 'AccountFolderController/ajaxShareFileFolderInternally');
                    $r->addRoute(['GET'], '/share_file_folder_internally_remove', 'AccountFolderController/ajaxShareFileFolderInternallyRemove');
                    $r->addRoute(['POST'], '/share_file_folder_internally_existing', 'AccountFolderController/ajaxShareFileFolderInternallyExisting');
                    $r->addRoute(['POST'], '/email_folder_url', 'AccountFolderController/ajaxEmailFolderUrl');
                    $r->addRoute(['POST'], '/generate_folder_sharing_url', 'AccountFolderController/ajaxGenerateFolderSharingUrl');
                    $r->addRoute(['GET'], '/update_view_type', 'AccountSettingsController/ajaxUpdateViewType');
                    $r->addRoute(['GET'], '/search_widget', 'AccountSearchController/ajaxSearchWidget');
                    $r->addRoute(['POST'], '/file_details_send_email_process', 'AccountFileController/ajaxFileDetailsSendEmailProcess');
                    $r->addRoute(['GET'], '/file_details_similar_files', 'AccountFileController/ajaxFileDetailsSimilarFiles');
                    $r->addRoute(['GET'], '/download_all_as_zip/{folderId:[0-9]+}', 'AccountFileController/ajaxDownloadAllAsZip');
                    $r->addRoute(['GET'], '/download_all_as_zip_get_file/{fileName}/{downloadZipName}', 'AccountFileController/ajaxDownloadAllAsZipGetFile');
                    $r->addRoute(['POST'], '/download_selected_files_set_session', 'AccountFileController/ajaxDownloadSelectedFilesSetSession');
                    $r->addRoute(['GET'], '/download_selected_files_iframe', 'AccountFileController/ajaxDownloadSelectedFilesIframe');
                    $r->addRoute(['GET'], '/internal_notification_mark_all_read', 'AccountSettingsController/ajaxInternalNotificationMarkAllRead');
                    $r->addRoute(['GET'], '/existing_background_url_download', 'FileController/ajaxExistingBackgroundUrlDownload');
                    $r->addRoute(['GET'], '/remove_background_url_download/{gRemoveUrlId}', 'FileController/ajaxRemoveBackgroundUrlDownload');
                    $r->addRoute(['POST'], '/enable_2fa', 'AccountSecurityController/enable2fa');
                    $r->addRoute(['POST'], '/enable_2fa_process', 'AccountSecurityController/enable2faProcess');
                    $r->addRoute(['POST'], '/disable_2fa_process', 'AccountSecurityController/disable2faProcess');
                    $r->addRoute(['POST'], '/account_copy_file', 'AccountFileController/ajaxAccountCopyFile');
                    $r->addRoute(['GET'], '/file_history/{fileId:[0-9]+}', 'AccountFileController/ajaxFileHistory');
                });
            });

            // admin area routes
            $r->addGroup('/' . ADMIN_FOLDER_NAME, function (\FastRoute\RouteCollector $r) {
                define('ADMIN_CONTROLLER_PATH', '\app\controllers\admin\\');
                $r->addRoute(['GET'], '/', ADMIN_CONTROLLER_PATH . 'DashboardController/index');
                $r->addRoute(['GET'], '', ADMIN_CONTROLLER_PATH . 'DashboardController/indexRedirector');
                $r->addRoute(['GET'], '/index', ADMIN_CONTROLLER_PATH . 'DashboardController/indexRedirector');
                $r->addRoute(['GET', 'POST'], '/login', ADMIN_CONTROLLER_PATH . 'DashboardController/login');
                $r->addRoute(['GET', 'POST'], '/login_2fa', ADMIN_CONTROLLER_PATH . 'DashboardController/login2FA');
                $r->addRoute(['GET', 'POST'], '/logout', ADMIN_CONTROLLER_PATH . 'DashboardController/logout');
                $r->addRoute(['GET', 'POST'], '/setting_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/settingManage');
                $r->addRoute(['GET', 'POST'], '/banned_ip_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/bannedIpManage');

                $r->addRoute(['GET', 'POST'], '/translation_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/translationManage');
                $r->addRoute(['GET', 'POST'], '/translation_manage_text', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/translationManageText');
                $r->addRoute(['GET', 'POST'], '/translation_manage_export', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/translationManageExport');
                $r->addRoute(['GET', 'POST'], '/translation_manage_import', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/translationManageImport');

                $r->addRoute(['GET', 'POST'], '/log_file_viewer', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/logFileViewer');
                $r->addRoute(['GET'], '/log_file_download/{base64LogFile}', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/logFileDownload');

                $r->addRoute(['GET', 'POST'], '/background_task_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/backgroundTaskManage');
                $r->addRoute(['GET', 'POST'], '/background_task_manage_log', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/backgroundTaskManageLog');

                $r->addRoute(['GET'], '/server_info', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/serverInfo');
                $r->addRoute(['GET'], '/support_info', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/supportInfo');
                $r->addRoute(['GET'], '/support_info_download', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/supportInfoDownload');

                $r->addRoute(['GET', 'POST'], '/database_browser', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/databaseBrowser');

                $r->addRoute(['GET'], '/account_package_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/accountPackageManage');
                $r->addRoute(['GET'], '/account_package_pricing_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/accountPackagePricingManage');

                $r->addRoute(['GET'], '/api_documentation', ADMIN_CONTROLLER_PATH . 'ApiController/apiDocumentation');
                $r->addRoute(['GET'], '/api_test_framework', ADMIN_CONTROLLER_PATH . 'ApiController/apiTestFramework');

                $r->addRoute(['GET'], '/backup_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/backupManage');
                $r->addRoute(['GET'], '/backup_download', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/backupDownload');

                $r->addRoute(['GET'], '/file_manage', ADMIN_CONTROLLER_PATH . 'FileController/fileManage');
                $r->addRoute(['GET'], '/export_csv', ADMIN_CONTROLLER_PATH . 'FileController/exportCSV');
                $r->addRoute(['GET'], '/file_manage_action_queue', ADMIN_CONTROLLER_PATH . 'FileController/fileManageActionQueue');
                $r->addRoute(['GET'], '/download_current', ADMIN_CONTROLLER_PATH . 'FileController/downloadCurrent');
                $r->addRoute(['GET'], '/download_previous', ADMIN_CONTROLLER_PATH . 'FileController/downloadPrevious');

                $r->addRoute(['GET'], '/system_update', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/systemUpdate');
                $r->addRoute(['GET'], '/purge_application_cache[/{doPurge}]', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/purgeApplicationCache');

                $r->addRoute(['GET'], '/theme_manage', ADMIN_CONTROLLER_PATH . 'ThemeController/themeManage');
                $r->addRoute(['GET', 'POST'], '/theme_manage_add', ADMIN_CONTROLLER_PATH . 'ThemeController/themeManageAdd');
                $r->addRoute(['GET'], '/theme_preview/{themeFolderName}', ADMIN_CONTROLLER_PATH . 'ThemeController/themePreview');

                $r->addRoute(['GET'], '/user_manage', ADMIN_CONTROLLER_PATH . 'UserController/userManage');
                $r->addRoute(['GET', 'POST'], '/user_edit/{userId:[0-9]+}', ADMIN_CONTROLLER_PATH . 'UserController/userEdit');
                $r->addRoute(['GET', 'POST'], '/user_add', ADMIN_CONTROLLER_PATH . 'UserController/userAdd');
                $r->addRoute(['GET'], '/user_login_history', ADMIN_CONTROLLER_PATH . 'UserController/userLoginHistory');
                $r->addRoute(['GET'], '/user_action_log', ADMIN_CONTROLLER_PATH . 'UserController/userActionLog');

                $r->addRoute(['GET'], '/plugin_manage', ADMIN_CONTROLLER_PATH . 'PluginController/pluginManage');
                $r->addRoute(['GET', 'POST'], '/plugin_manage_add', ADMIN_CONTROLLER_PATH . 'PluginController/pluginManageAdd');

                $r->addRoute(['GET'], '/server_manage', ADMIN_CONTROLLER_PATH . 'ServerController/serverManage');
                $r->addRoute(['GET', 'POST'], '/server_add_edit[/{fileServerId}]', ADMIN_CONTROLLER_PATH . 'ServerController/serverAddEdit');
                $r->addRoute(['GET'], '/server_manage_direct_get_config_file', ADMIN_CONTROLLER_PATH . 'ServerController/serverManageDirectGetConfigFile');
                $r->addRoute(['GET'], '/server_manage_test_flysystem', ADMIN_CONTROLLER_PATH . 'ServerController/serverManageTestFlysystem');
                $r->addRoute(['GET'], '/server_manage_test_direct', ADMIN_CONTROLLER_PATH . 'ServerController/serverManageTestDirect');
                $r->addRoute(['GET'], '/server_manage_test_ftp', ADMIN_CONTROLLER_PATH . 'ServerController/serverManageTestFTP');
                
                $r->addRoute(['GET'], '/sharing_manage', ADMIN_CONTROLLER_PATH . 'SharingController/sharingManage');

                $r->addRoute(['GET'], '/download_page_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/downloadPageManage');
                $r->addRoute(['GET'], '/test_tools', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/testTools');

                $r->addRoute(['GET'], '/payment_manage', ADMIN_CONTROLLER_PATH . 'UserController/paymentManage');
                $r->addRoute(['GET'], '/payment_subscription_manage', ADMIN_CONTROLLER_PATH . 'UserController/paymentSubscriptionManage');

                $r->addRoute(['GET', 'POST'], '/file_report_manage_bulk_remove', ADMIN_CONTROLLER_PATH . 'FileReportController/fileReportManageBulkRemove');
                $r->addRoute(['GET'], '/file_report_manage', ADMIN_CONTROLLER_PATH . 'FileReportController/fileReportManage');
                
                $r->addRoute(['GET'], '/test_tools/apache_xsendfile', ADMIN_CONTROLLER_PATH . 'TestToolsController/testApacheXsendfile');
                $r->addRoute(['GET'], '/test_tools/create_database_backup', ADMIN_CONTROLLER_PATH . 'TestToolsController/testCreateDatabaseBackup');
                $r->addRoute(['GET'], '/test_tools/curl', ADMIN_CONTROLLER_PATH . 'TestToolsController/testCurl');
                $r->addRoute(['GET', 'POST'], '/test_tools/email', ADMIN_CONTROLLER_PATH . 'TestToolsController/testEmail');
                $r->addRoute(['GET'], '/test_tools/encryption', ADMIN_CONTROLLER_PATH . 'TestToolsController/testEncryption');
                $r->addRoute(['GET'], '/test_tools/file_encryption', ADMIN_CONTROLLER_PATH . 'TestToolsController/testFileEncryption');
                $r->addRoute(['GET'], '/test_tools/flysystem', ADMIN_CONTROLLER_PATH . 'TestToolsController/testFlysystem');
                $r->addRoute(['GET'], '/test_tools/generate_password_hash', ADMIN_CONTROLLER_PATH . 'TestToolsController/testGeneratePasswordHash');
                $r->addRoute(['GET'], '/test_tools/geocode_ip', ADMIN_CONTROLLER_PATH . 'TestToolsController/testGeocodeIp');
                $r->addRoute(['GET'], '/test_tools/ip', ADMIN_CONTROLLER_PATH . 'TestToolsController/testIp');
                $r->addRoute(['GET'], '/test_tools/omnipay_payment', ADMIN_CONTROLLER_PATH . 'TestToolsController/testOmnipayPayment');
                $r->addRoute(['GET'], '/test_tools/output_buffering', ADMIN_CONTROLLER_PATH . 'TestToolsController/testOutputBuffering');
                $r->addRoute(['GET'], '/test_tools/permissions', ADMIN_CONTROLLER_PATH . 'TestToolsController/testPermissions');
                $r->addRoute(['GET'], '/test_tools/ports', ADMIN_CONTROLLER_PATH . 'TestToolsController/testPorts');
                $r->addRoute(['GET'], '/test_tools/read_exif', ADMIN_CONTROLLER_PATH . 'TestToolsController/testReadExif');
                $r->addRoute(['GET', 'POST'], '/test_tools/upload', ADMIN_CONTROLLER_PATH . 'TestToolsController/testUpload');
                $r->addRoute(['GET'], '/test_tools/server_resource_usage', ADMIN_CONTROLLER_PATH . 'TestToolsController/testServerResourceUsage');
                $r->addRoute(['GET'], '/test_tools/server_variables', ADMIN_CONTROLLER_PATH . 'TestToolsController/testServerVariables');
                
                $r->addRoute(['GET', 'POST'], '/api[/]', ADMIN_CONTROLLER_PATH . 'AdminApiController/api');
                
                $r->addRoute(['GET', 'POST'], '/advertising/settings', ADMIN_CONTROLLER_PATH . 'AdvertisingController/advertisingSettings');

                $r->addGroup('/ajax', function (\FastRoute\RouteCollector $r) {
                   // $r->addRoute(['GET'], '/check_for_upgrade', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxCheckForUpgrade');
                    $r->addRoute(['GET'], '/dashboard_chart_12_months_chart', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChart12MonthsChart');
                    $r->addRoute(['GET'], '/dashboard_chart_14_day_chart', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChart14DayChart');
                    $r->addRoute(['GET'], '/dashboard_chart_14_day_users', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChart14DayUsers');
                    $r->addRoute(['GET'], '/dashboard_chart_file_status_chart', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChartFileStatusChart');
                    $r->addRoute(['GET'], '/dashboard_chart_file_type_chart', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChartFileTypeChart');
                    $r->addRoute(['GET'], '/dashboard_chart_user_status_chart', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxDashboardChartUserStatusChart');

                    $r->addRoute(['GET'], '/banned_ip_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBannerIpManage');
                    $r->addRoute(['POST'], '/banned_ip_manage_add_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBannedIpManageAddForm');
                    $r->addRoute(['POST'], '/banned_ip_manage_add_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBannedIpManageAddProcess');
                    $r->addRoute(['POST'], '/banned_ip_manage_remove', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBannedIpManageRemove');
                    $r->addRoute(['GET'], '/account_view_avatar', ADMIN_CONTROLLER_PATH . 'DashboardController/ajaxAccountViewAvatar');

                    $r->addRoute(['GET'], '/translation_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManage');
                    $r->addRoute(['GET'], '/translation_manage_text', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageText');
                    $r->addRoute(['POST'], '/translation_manage_text_set_is_locked', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageTextSetIsLocked');
                    $r->addRoute(['POST'], '/translation_manage_remove', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageRemove');
                    $r->addRoute(['POST'], '/translation_manage_add_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageAddForm');
                    $r->addRoute(['POST'], '/translation_manage_add_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageAddProcess');
                    $r->addRoute(['POST'], '/translation_manage_set_available_state', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageSetAvailableState');
                    $r->addRoute(['POST'], '/translation_manage_set_default_language', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageSetDefaultLanguage');
                    $r->addRoute(['POST'], '/translation_manage_text_edit_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageTextEditForm');
                    $r->addRoute(['POST'], '/translation_manage_text_edit_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageTextEditProcess');
                    $r->addRoute(['POST'], '/translation_manage_text_auto_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageTextAutoProcess');
                    $r->addRoute(['GET'], '/translation_manage_text_auto_convert/{languageId}', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxTranslationManageTextAutoConvert');

                    $r->addRoute(['GET'], '/background_task_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBackgroundTaskManage');
                    $r->addRoute(['GET'], '/background_task_manage_log', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxBackgroundTaskManageLog');

                    $r->addRoute(['GET'], '/account_package_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackageManage');
                    $r->addRoute(['POST'], '/account_package_manage_add_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackageManageAddForm');
                    $r->addRoute(['POST'], '/account_package_manage_add_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackageManageAddProcess');
                    $r->addRoute(['GET'], '/account_package_pricing_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackagePricingManage');
                    $r->addRoute(['POST'], '/account_package_pricing_manage_add_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackagePricingManageAddForm');
                    $r->addRoute(['POST'], '/account_package_pricing_manage_add_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxAccountPackagePricingManageAddProcess');

                    $r->addRoute(['GET'], '/file_manage', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManage');
                    $r->addRoute(['GET'], '/file_manage_auto_complete', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageAutoComplete');
                    $r->addRoute(['POST'], '/file_manage_bulk_delete', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageBulkDelete');
                    $r->addRoute(['POST'], '/file_manage_edit_form', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageEditForm');
                    $r->addRoute(['POST'], '/file_manage_edit_process', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageEditProcess');
                    $r->addRoute(['POST'], '/file_manage_move_form', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageMoveForm');
                    $r->addRoute(['POST'], '/file_manage_move_process', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageMoveProcess');
                    $r->addRoute(['POST'], '/update_file_state', ADMIN_CONTROLLER_PATH . 'FileController/ajaxUpdateFileState');
                    $r->addRoute(['GET'], '/file_manage_action_queue', ADMIN_CONTROLLER_PATH . 'FileController/ajaxFileManageActionQueue');
                    $r->addRoute(['GET'], '/download_current', ADMIN_CONTROLLER_PATH . 'FileController/ajaxDownloadCurrent');
                    $r->addRoute(['GET'], '/download_previous', ADMIN_CONTROLLER_PATH . 'FileController/ajaxDownloadPrevious');

                    $r->addRoute(['GET'], '/user_manage', ADMIN_CONTROLLER_PATH . 'UserController/ajaxUserManage');
                    $r->addRoute(['POST'], '/user_remove', ADMIN_CONTROLLER_PATH . 'UserController/ajaxUserRemove');
                    $r->addRoute(['POST'], '/user_approve', ADMIN_CONTROLLER_PATH . 'UserController/ajaxUserApprove');
                    $r->addRoute(['POST'], '/user_decline', ADMIN_CONTROLLER_PATH . 'UserController/ajaxUserDecline');
                    $r->addRoute(['POST'], '/get_user_folder_for_select', ADMIN_CONTROLLER_PATH . 'UserController/ajaxGetUserFolderForSelect');
                    $r->addRoute(['GET'], '/user_action_log', ADMIN_CONTROLLER_PATH . 'UserController/ajaxUserActionLog');

                    $r->addRoute(['GET'], '/plugin_manage', ADMIN_CONTROLLER_PATH . 'PluginController/ajaxPluginManage');
                    $r->addRoute(['POST'], '/plugin_manage_install', ADMIN_CONTROLLER_PATH . 'PluginController/ajaxPluginManageInstall');
                    $r->addRoute(['POST'], '/plugin_manage_uninstall', ADMIN_CONTROLLER_PATH . 'PluginController/ajaxPluginManageUninstall');
                    $r->addRoute(['POST'], '/plugin_manage_delete', ADMIN_CONTROLLER_PATH . 'PluginController/ajaxPluginManageDelete');

                    $r->addRoute(['GET'], '/server_manage', ADMIN_CONTROLLER_PATH . 'ServerController/ajaxServerManage');
                    $r->addRoute(['POST'], '/server_manage_remove', ADMIN_CONTROLLER_PATH . 'ServerController/ajaxServerManageRemove');
                    $r->addRoute(['GET'], '/server_manage_get_server_detail', ADMIN_CONTROLLER_PATH . 'ServerController/ajaxServerManageGetServerDetail');
                    
                    $r->addRoute(['GET'], '/sharing_manage', ADMIN_CONTROLLER_PATH . 'SharingController/ajaxSharingManage');
                    $r->addRoute(['POST'], '/sharing_manage_remove', ADMIN_CONTROLLER_PATH . 'SharingController/ajaxSharingManageRemove');
                    $r->addRoute(['POST'], '/sharing_manage_add_form', ADMIN_CONTROLLER_PATH . 'SharingController/ajaxSharingManageAddForm');
                    $r->addRoute(['POST'], '/sharing_manage_add_get_folder_listing', ADMIN_CONTROLLER_PATH . 'SharingController/ajaxSharingManageAddGetFolderListing');
                    $r->addRoute(['POST'], '/sharing_manage_add_process', ADMIN_CONTROLLER_PATH . 'SharingController/ajaxSharingManageAddProcess');
                    

                    $r->addRoute(['GET'], '/download_page_manage', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxDownloadPageManage');
                    $r->addRoute(['POST'], '/download_page_manage_add_form', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxDownloadPageManageAddForm');
                    $r->addRoute(['POST'], '/download_page_manage_add_process', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxDownloadPageManageAddProcess');
                    $r->addRoute(['POST'], '/download_page_manage_remove', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxDownloadPageManageRemove');

                    $r->addRoute(['GET'], '/payment_manage', ADMIN_CONTROLLER_PATH . 'UserController/ajaxPaymentManage');
                    $r->addRoute(['POST'], '/payment_manage_add_form', ADMIN_CONTROLLER_PATH . 'UserController/ajaxPaymentManageAddForm');
                    $r->addRoute(['POST'], '/payment_manage_add_process', ADMIN_CONTROLLER_PATH . 'UserController/ajaxPaymentManageAddProcess');
                    $r->addRoute(['POST'], '/payment_manage_detail', ADMIN_CONTROLLER_PATH . 'UserController/ajaxPaymentManageDetail');
                    $r->addRoute(['GET'], '/payment_subscription_manage', ADMIN_CONTROLLER_PATH . 'UserController/ajaxPaymentSubscriptionManage');

                    $r->addRoute(['GET'], '/file_report_manage', ADMIN_CONTROLLER_PATH . 'FileReportController/ajaxFileReportManage');
                    $r->addRoute(['POST'], '/file_report_detail', ADMIN_CONTROLLER_PATH . 'FileReportController/ajaxFileReportDetail');
                    $r->addRoute(['POST'], '/file_report_accept', ADMIN_CONTROLLER_PATH . 'FileReportController/ajaxFileReportAccept');
                    $r->addRoute(['POST'], '/file_report_decline', ADMIN_CONTROLLER_PATH . 'FileReportController/ajaxFileReportDecline');
                    
                    $r->addRoute(['POST'], '/purge_application_cache_process/{serverId}', ADMIN_CONTROLLER_PATH . 'SiteConfigurationController/ajaxPurgeApplicationCacheProcess');
                });
            });

            // old routes for compatibility with older script versions
            $r->addRoute(['GET'], '/index.html', 'LegacyController/index');
            $r->addRoute(['GET'], '/account_home.html', 'LegacyController/accountIndex');
            $r->addRoute(['GET'], '/account_edit.html', 'LegacyController/accountEdit');
            
            // for custom download urls by account type
            $r->addRoute(['GET', 'POST'], '/token/download/{downloadUrlSlug}/{path:.*}', 'FileController/downloadSlugHandler');

            // fallback to file download
            $r->addRoute(['GET', 'POST'], '/{path:.*}', 'FileController/downloadHandler');
        }, [
            'cacheFile' => CACHE_DIRECTORY_ROOT . '/route.cache',
            'cacheDisabled' => !CacheHelper::isApplicationCachingEnabled(),
        ]);
    }

    public static function processRoutes() {
        // setup routes
        self::registerRoutes();

        // dispatch with the request method and url
        $routeInfo = self::$dispatcher->dispatch(self::getRequestMethod(), '/' . _INT_PAGE_URL);
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                // ... 404 Not Found
                die('404 - Route not found.');
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                // ... 405 Method Not Allowed
                die('405 - Route not found.');
                break;
            case \FastRoute\Dispatcher::FOUND:
                // get out handler and variables
                $handler = $routeInfo[1];

                // if we have a function, call it
                if (is_callable($handler)) {
                    $response = call_user_func($handler);
                }
                else {
                    // otherwise, we have a controller/method call
                    $vars = $routeInfo[2];

                    // call $handler with $vars
                    list($class, $method) = explode("/", $handler, 2);

                    // if the class doesn't have the full namespace, assume it's
                    // within our core controllers, try theme controller first
                    $themeControllerName = 'Themes\\'.ucfirst(ThemeHelper::getCurrentThemeKey()).'\Controllers\\'.$class;
                    $foundMethod = false;
                    if (class_exists($themeControllerName)) {
                        $classInstance = new $themeControllerName(_INT_PAGE_URL);
                        if (method_exists($classInstance, $method)) {
                            $foundMethod = true;
                        }
                    }

                    // if not found, try global controllers
                    if($foundMethod === false) {
                        if (strpos($class, '\\') === false) {
                            $class = 'App\Controllers\\' . $class;
                        }

                        // make sure the method exists in our class
                        $classInstance = new $class(_INT_PAGE_URL);
                        if (!method_exists($classInstance, $method)) {
                            die('Error: Method \'' . $method . '\' does not exist in the class \'' . $class . '\'.');
                        }
                    }

                    // prepend the full controller path
                    $response = call_user_func_array(array($classInstance, $method), $vars);
                }

                // make sure response is a 'Response' object (https://symfony.com/doc/3.4/components/http_foundation.html)
                if ($response instanceof Response === false && $response instanceof RedirectResponse === false) {
                    die('Error: Expected response of controller to be a \'Response\' or \'RedirectResponse\' object.');
                }

                // send the response
                $response->send();
                break;
        }
    }

    public static function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

}
