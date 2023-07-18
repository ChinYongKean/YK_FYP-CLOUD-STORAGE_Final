<?php

use App\Core\Database;

session_start();

require_once("settings.inc.php");
require_once('../app/core/Database.class.php');
require_once("functions.inc.php");
require_once("languages.inc.php");

$completed = false;
$error_mg = [];
$task = $_POST['task'] ?? "";

define('CRON_PATH', realpath(dirname(__FILE__).'/../app/tasks'));

if ($task == "step2") {
    $username = $_POST['username'] ?? "";
    $password = $_POST['password'] ?? "";
    $license_key = $_POST['license_key'] ?? "";
    $database_host = $_POST['database_host'] ?? "";
    $database_name = $_POST['database_name'] ?? "";
    $database_username = $_POST['database_username'] ?? "";
    $database_password = $_POST['database_password'] ?? "";
    $secret_key = genRandomString(128);

    if (empty($database_host)) {
        $error_mg[] = lang_key("alert_db_host_empty");
    }
    if (empty($database_name)) {
        $error_mg[] = lang_key("alert_db_name_empty");
    }
    if (empty($database_username)) {
        $error_mg[] = lang_key("alert_db_username_empty");
    }

    if (empty($username)) {
        $error_mg[] = lang_key("alert_admin_username_empty");
    } elseif (!preg_match('/^[a-zA-Z0-9]{4,}$/', $username)) {
        $error_mg[] = "Username must be a minimum of 4 characters and alpha-numeric only.";
    }
    if (empty($password)) {
        $error_mg[] = lang_key("alert_admin_password_empty");
    } elseif (strlen($password) < 6) {
        $error_mg[] = "Password must be at least 6 characters in length.";
    } elseif (!function_exists('utf8_decode')) {
        $error_mg[] = "utf8_decode() function does not exist, please enable it within PHP to continue.";
    }

    // validate license key
    if (empty($error_mg)) {
        $responseArr = validateLicenseKey($license_key);

        // handle response
        if (!is_array($responseArr)) {
            $error_mg[] = 'Could not validate license, please try again later.';
        } else {
            if ((int)$responseArr['error'] === 1) {
                $error_mg[] = $responseArr['msg'];
            }
        }
    }

    if (empty($error_mg)) {
        $db = Database::getDatabase(false, false, $database_host, $database_name, $database_username, $database_password, true);
        $db->setNotifyType(Database::NOTIFY_TYPE_ARRAY);
        if ($dbConnection = $db->connect()) {
            // read sql dump file
            if (!($db_error = dbImport(INSTALLER_SQL_DUMP_FILE, $db))) {
                $error_mg[] = lang_key("error_sql_executing");
            } else {
                // write additional operations here, like setting up system preferences etc.
                $completed = true;

                // now try to create file and write information
                $config_file = file_get_contents(INSTALLER_CONFIG_FILE_TEMPLATE);
                $config_file = str_replace("<SITE_HOST_URL>", str_replace('"', '\"', getInstallHost()), $config_file);
                $config_file = str_replace("<SITE_FULL_URL>", str_replace('"', '\"', getInstallPath()), $config_file);
                $config_file = str_replace("<DB_HOST>", str_replace('"', '\"', $database_host), $config_file);
                $config_file = str_replace("<DB_NAME>", str_replace('"', '\"', $database_name), $config_file);
                $config_file = str_replace("<DB_USER>", str_replace('"', '\"', $database_username), $config_file);
                $config_file = str_replace("<DB_PASSWORD>",
                    str_replace('$', '\$', str_replace('"', '\"', $database_password)), $config_file);
                $config_file = str_replace("<SECRET_KEY>", str_replace('"', '\"', $secret_key), $config_file);

                if ($_SERVER["SERVER_PORT"] == "443") {
                    $config_file = str_replace("<SITE_PROTOCOL>", 'https', $config_file);
                } else {
                    $config_file = str_replace("<SITE_PROTOCOL>", 'http', $config_file);
                }

                @chmod(INSTALLER_CONFIG_FILE_PATH, 0755);
                $f = @fopen(INSTALLER_CONFIG_FILE_PATH, "w+");
                if (!@fwrite($f, $config_file) > 0) {
                    $error_mg[] = str_replace("_CONFIG_FILE_PATH_", INSTALLER_CONFIG_FILE_PATH,
                        lang_key("error_can_not_open_config_file"));
                }
                @fclose($f);

                // update user in database
                $sQL = 'UPDATE users SET username=:username, password=:password WHERE id=1 LIMIT 1';
                $db->query($sQL, [
                    'username' => $username,
                    'password' => md5($password),
                ]);
            }
        } else {
            $error_mg[] = $dbConnection['error'];
        }
    }
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title><?php
        echo lang_key("installation_guide"); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <link rel="stylesheet" type="text/css" href="css/styles.css"></link>
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="css/stylesIE.css"></link>
    <![endif]-->
    <script type="text/javascript">
        var EI_LOCAL_PATH = "language/<?php echo $curr_lang; ?>/";
    </script>
    <script type="text/javascript" src="js/main.js"></script>
    <script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>
</head>
<body>

<table align="center" width="1000" cellspacing="0" cellpadding="0" border="0">
    <tbody>
    <tr>
        <td class="text" valign="top">
            <h2><?php
                echo INSTALLER_APPLICATION_NAME; ?> - Installation Script</h2>
            Follow the Wizard to setup your site configuration, database and initial admin area login.<br/><br/>
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tbody>
                <tr>
                    <td class="gray_table">
                        <table width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tbody>
                            <tr>
                                <td class="ltcorner"></td>
                                <td></td>
                                <td class="rtcorner"></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td align="middle">
                                    <table class="text mainTable" width="99%" cellspacing="0" cellpadding="0"
                                           border="0">
                                        <tbody>
                                        <?php
                                        if (!$completed) {
                                            ?>
                                            <tr>
                                                <td align="left">
                                                    <h2>Site Configuration Error</h2>
                                                </td>
                                            </tr>
                                            <?php
                                            foreach ($error_mg as $msg) {
                                                echo "<tr><td class='text' align='left'><span style='color:#bb5500;'>&#8226; ".$msg."</span></td></tr>";
                                            }
                                            ?>
                                            <tr>
                                                <td nowrap height="25px">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td align="left">
                                                    <img class="form_button" src="language/<?php
                                                    echo $curr_lang; ?>/buttons/button_back.gif" name="button_back"
                                                         id="button_back" onmouseover="buttonOver('button_back')"
                                                         onmouseout="buttonOut('button_back')" alt=""
                                                         onclick="javascript: history.go(-1);"/>
                                                    &nbsp;&nbsp;&nbsp;&nbsp;
                                                    <img class="form_button" src="language/<?php
                                                    echo $curr_lang; ?>/buttons/button_retry.gif" name="button_retry"
                                                         id="button_retry" onmouseover="buttonOver('button_retry')"
                                                         onmouseout="buttonOut('button_retry')" alt=""
                                                         onclick="javascript: location.reload();"/>
                                                </td>
                                            </tr>
                                            <?php
                                        } else {
                                            ?>
                                            <tr>
                                                <td align="left"><h2 style='color: green;'><?php
                                                        echo lang_key("step_2_installation_completed"); ?></h2></td>
                                            </tr>
                                            <tr>
                                                <td align="left">
                                                    <?php
                                                    echo str_replace("_CONFIG_FILE_", INSTALLER_CONFIG_FILE_PATH,
                                                        lang_key("file_successfully_created")); ?><br/>
                                                    <br/><br/>

                                                    <h3><b>Important: Remove Install Folder.</b></h3>
                                                    <hr>
                                                    <span><?php
                                                        echo lang_key("alert_remove_files"); ?></span>
                                                    <br/><br/><br/>

                                                    <h3><b>Final Step: Setup Cron Tasks.</b></h3>
                                                    <hr>
                                                    <p><?php
                                                        echo INSTALLER_APPLICATION_NAME; ?> uses a number of cron
                                                        (background) tasks to ensure redundant files are deleted,
                                                        accounts are auto downgraded etc. Details of these are below.
                                                        You can leave these until later if you want to test the
                                                        installation first. See <a
                                                                href="http://www.cyberciti.biz/faq/how-do-i-add-jobs-to-cron-under-linux-or-unix-oses/"
                                                                target="_blank">here for more information</a> on cron
                                                        tasks.</p>
                                                    <span style="font-family: courier,Consolas,monospace;">
                                                        0 0 * * * php <?php
                                                        echo CRON_PATH; ?>/auto_prune.cron.php >> /dev/null 2>&amp;1<br/>
                                                        0 0 * * * php <?php
                                                        echo CRON_PATH; ?>/create_internal_notifications.cron.php >> /dev/null 2>&amp;1<br/>
                                                        0 * * * * php <?php
                                                        echo CRON_PATH; ?>/delete_redundant_files.cron.php >> /dev/null 2>&amp;1<br/>
                                                        0 0 * * * php <?php
                                                        echo CRON_PATH; ?>/downgrade_accounts.cron.php >> /dev/null 2>&amp;1<br/>
                                                        */5 * * * * php <?php
                                                        echo CRON_PATH; ?>/process_file_queue.cron.php >> /dev/null 2>&amp;1<br/>
										                0 1 * * * php <?php
                                                        echo CRON_PATH; ?>/create_email_notifications.cron.php >> /dev/null 2>&amp;1<br/>
                                                        * * * * * php <?php
                                                        echo CRON_PATH; ?>/process_server_resources.cron.php >> /dev/null 2>&amp;1<br/>
                                                        * * * * * php <?php
                                                        echo CRON_PATH; ?>/process_server_monitoring.cron.php >> /dev/null 2>&amp;1
                                                                            </span><br/>

                                                    <br/>
                                                    Remove the "install" folder then <a href="<?php
                                                    echo "../"; ?>"><?php
                                                        echo lang_key("proceed_to_login_page"); ?></a> or <a href="<?php
                                                    echo "../"; ?>admin/"><?php
                                                        echo lang_key("proceed_to_admin_page"); ?></a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                    <br/>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="lbcorner"></td>
                                <td></td>
                                <td class="rbcorner"></td>
                            </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                </tbody>
            </table>

            <?php
            include_once("footer.php"); ?>
        </td>
    </tr>
    </tbody>
</table>

</body>
</html>