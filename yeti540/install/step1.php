<?php

session_start();

require_once("settings.inc.php");
require_once("functions.inc.php");
require_once("languages.inc.php");

$database_host = $_REQUEST['database_host'] ?? "localhost";
$database_name = $_REQUEST['database_name'] ?? "";
$database_username = $_REQUEST['database_username'] ?? "";
$database_password = $_REQUEST['database_password'] ?? "";
$license_key = $_REQUEST['license_key'] ?? "";

// prepare focus field
if ($license_key == "") {
    $focus_field = "license_key";
} elseif ($database_host == "") {
    $focus_field = "database_host";
} elseif ($database_name == "") {
    $focus_field = "database_name";
} elseif ($database_username == "") {
    $focus_field = "database_username";
} elseif ($database_password == "") {
    $focus_field = "database_password";
} else {
    $focus_field = "database_host";
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
    <?php
    if (file_exists("languages/js/".$curr_lang.".js")) {
        echo "<script type='text/javascript' src='language/".$curr_lang."/js/common.js'></script>";
    } else {
        echo "<script type='text/javascript' src='language/en/js/common.js'></script>";
    }
    ?>
</head>
<body onload="bodyOnLoad()">
<table align="center" width="1000" cellspacing="0" cellpadding="0" border="0">
    <tbody>
    <tr>
        <td class=text valign=top>
            <h2><?php
                echo INSTALLER_APPLICATION_NAME; ?> - Installation Script</h2>
            Follow the Wizard to setup your site configuration, database and initial admin area login.<br/><br/>
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tbody>
                <tr>
                    <td class="gray_table">
                        <table border="0" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tbody>
                            <tr>
                                <td class="ltcorner"></td>
                                <td></td>
                                <td class="rtcorner"></td>
                            </tr>
                            <tr>
                                <td width="2%" nowrap></td>
                                <td align="left">
                                    <form method="post" action="step2.php">
                                        <input type="hidden" name="task" value="step2"/>
                                        <h2>Script License Key:</h2>
                                        <p class="text">Enter your license key below. You can get this by logging into
                                            your account on <a href="https://yetishare.com" target="_blank">yetishare.com</a>.
                                        </p>

                                        <table class="mainTable text" width="100%" border="0" cellspacing="0"
                                               cellpadding="0">
                                            <tr>
                                                <td nowrap style="width: 200px;">&nbsp;License Key: <span
                                                            class="star">*</span></td>
                                                <td>
                                                    <input type="text" class="form_text" name="license_key"
                                                           id="license_key" size="30" value='<?php
                                                    echo $license_key; ?>' onfocus="textboxOnFocus('notes_license_key')"
                                                           onblur="textboxOnBlur('notes_license_key')"/>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>&nbsp;</td>
                                                <td>
                                                    <img class="form_button" src="language/<?php
                                                    echo $curr_lang; ?>/buttons/button_validate_license.gif"
                                                         name="btn_test" id="button_test"
                                                         onmouseover="buttonOver('button_validate_license')"
                                                         onmouseout="buttonOut('button_validate_license')"
                                                         title="Validate License Key" alt=""
                                                         onclick="validateLicenseKey()"/>
                                                </td>
                                            </tr>
                                        </table>

                                        <h2>Database Setup:</h2>
                                        <p class="text">Create your database using your hosting control panel, then set
                                            the details below to automatically create the database structure.</p>

                                        <table class="mainTable text" width="100%" border="0" cellspacing="0"
                                               cellpadding="0">
                                            <tr>
                                                <td nowrap style="width: 200px;">&nbsp;<?php
                                                    echo lang_key("database_host"); ?>: <span class="star">*</span></td>
                                                <td>
                                                    <input type="text" class="form_text" name="database_host"
                                                           id="database_host" size="30" value='<?php
                                                    echo $database_host; ?>' onfocus="textboxOnFocus('notes_host')"
                                                           onblur="textboxOnBlur('notes_host')"/>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td nowrap>&nbsp;<?php
                                                    echo lang_key("database_name"); ?>: <span class="star">*</span></td>
                                                <td>
                                                    <input type="text" class="form_text" name="database_name"
                                                           id="database_name" size="30" value="<?php
                                                    echo $database_name; ?>" onfocus="textboxOnFocus('notes_db_name')"
                                                           onblur="textboxOnBlur('notes_db_name')"/>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td nowrap>&nbsp;<?php
                                                    echo lang_key("database_username"); ?>: <span class="star">*</span>
                                                </td>
                                                <td>
                                                    <input type="text" class="form_text" name="database_username"
                                                           id="database_username" size="30" value="<?php
                                                    echo $database_username; ?>"
                                                           onfocus="textboxOnFocus('notes_db_user')"
                                                           onblur="textboxOnBlur('notes_db_user')"/>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td nowrap>&nbsp;<?php
                                                    echo lang_key("Database Password"); ?>:
                                                </td>
                                                <td>
                                                    <input type="password" class="form_text" name="database_password"
                                                           id="database_password" size="30" value="<?php
                                                    echo $database_password; ?>" autocomplete='off'
                                                           onfocus="textboxOnFocus('notes_db_password')"
                                                           onblur="textboxOnBlur('notes_db_password')"/>
                                                </td>
                                            </tr>
                                            <input type="hidden" name="database_prefix" size="12" maxlength="12"
                                                   value=""/>
                                            <input type="hidden" name="install_type" id="rb_create" value="create"
                                                   checked/>
                                            <tr>
                                                <td>&nbsp;</td>
                                                <td>
                                                    <img class="form_button" src="language/<?php
                                                    echo $curr_lang; ?>/buttons/button_test.gif" name="btn_test"
                                                         id="button_test" onmouseover="buttonOver('button_test')"
                                                         onmouseout="buttonOut('button_test')" title="<?php
                                                    echo lang_key("test_database_connection"); ?>" alt=""
                                                         onclick="testDatabaseConnection()"/>
                                                </td>
                                            </tr>
                                        </table>

                                        <h2>Script Admin User:</h2>
                                        <p class="text">This will be the user you'll use to access the site admin
                                            area.</p>

                                        <table class="text mainTable" width="100%" border="0" cellspacing="0"
                                               cellpadding="0">
                                            <tr id="line_admin_login">
                                                <td style="width: 200px;">&nbsp;<?php
                                                    echo lang_key("admin_login"); ?>&nbsp;<span class="star">*</span>
                                                </td>
                                                <td class="text"><input name="username" class="form_text" size="28"
                                                                        maxlength="22" value=""
                                                                        onfocus="textboxOnFocus('notes_admin_username')"
                                                                        onblur="textboxOnBlur('notes_admin_username')"
                                                                        autocomplete='off'/></td>
                                            </tr>
                                            <tr id="line_admin_password">
                                                <td>&nbsp;<?php
                                                    echo lang_key("admin_password"); ?>&nbsp;<span class="star">*</span>
                                                </td>
                                                <td class="text"><input name="password" class="form_text"
                                                                        type="password" size="28" maxlength="22"
                                                                        value=""
                                                                        onfocus="textboxOnFocus('notes_admin_password')"
                                                                        onblur="textboxOnBlur('notes_admin_password')"
                                                                        autocomplete='off'/></td>
                                            </tr>
                                        </table>

                                        <table class="text" width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td colspan="2" nowrap height="20px">&nbsp;</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" align='left'>
                                                    <a href='index.php'><img class="form_button" src="language/<?php
                                                        echo $curr_lang; ?>/buttons/button_cancel.gif" name="btn_back"
                                                                             id="button_cancel"
                                                                             onmouseover="buttonOver('button_cancel')"
                                                                             onmouseout="buttonOut('button_cancel')"
                                                                             title="<?php
                                                                             echo lang_key("cancel_installation"); ?>"
                                                                             alt=""/></a>
                                                    &nbsp;&nbsp;&nbsp;&nbsp;
                                                    <input type="image" src="language/<?php
                                                    echo $curr_lang; ?>/buttons/button_continue.gif" class="form_button"
                                                           name="btn_submit" id="button_continue"
                                                           onmouseover="buttonOver('button_continue')"
                                                           onmouseout="buttonOut('button_continue')" title="<?php
                                                    echo lang_key("continue_installation"); ?>" alt=""/>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                    <br/>
                                </td>
                                <td width="290px" align="left" valign="top" style="padding-top:30px;">

                                    <div id='notes_license_key'>
                                        <h4>License Key</h4>
                                        <p>Enter your license key for the script. This can be found via your account on
                                            Yetishare.com.</p>
                                    </div>
                                    <div id='notes_host'>
                                        <h4><?php
                                            echo lang_key("database_host"); ?></h4>
                                        <p><?php
                                            echo lang_key("database_host_info"); ?></p>
                                    </div>
                                    <div id='notes_db_name'>
                                        <h4><?php
                                            echo lang_key("database_name"); ?></h4>
                                        <p><?php
                                            echo lang_key("database_name_info"); ?></p>
                                    </div>
                                    <div id='notes_db_user'>
                                        <h4><?php
                                            echo lang_key("database_username"); ?></h4>
                                        <p><?php
                                            echo lang_key("database_username_info"); ?></p>
                                    </div>
                                    <div id='notes_db_password'>
                                        <h4><?php
                                            echo lang_key("database_password"); ?></h4>
                                        <p><?php
                                            echo lang_key("database_password_info"); ?></p>
                                    </div>
                                    <div id='notes_db_prefix'>
                                        <h4><?php
                                            echo lang_key("database_prefix"); ?></h4>
                                        <p><?php
                                            echo lang_key("database_prefix_info"); ?></p>
                                    </div>
                                    <div id='notes_admin_username'>
                                        <h4><?php
                                            echo lang_key("admin_login"); ?></h4>
                                        <p><?php
                                            echo lang_key("admin_login_info"); ?></p>
                                    </div>
                                    <div id='notes_admin_password'>
                                        <h4><?php
                                            echo lang_key("admin_password"); ?></h4>
                                        <p><?php
                                            echo lang_key("admin_password_info"); ?></p>
                                    </div>
                                    <img class="loading_img" src="img/ajax_loading.gif" alt="<?php
                                    echo lang_key("loading"); ?>..."/>
                                    <div id='notes_message'></div>
                                </td>
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
<script type='text/javascript'>
    function bodyOnLoad() {
        setFocus('<?php echo $focus_field; ?>');
        installTypeOnClick($("input[@name='install_type']:checked").val());
    }
</script>
</body>
</html>