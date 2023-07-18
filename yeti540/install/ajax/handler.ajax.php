<?php

use App\Core\Database;

session_start();

require_once("../settings.inc.php");
require_once('../../app/core/Database.class.php');
require_once("../functions.inc.php");
require_once("../languages.inc.php");

// pickup the post variables
$database_host = isset($_POST['db_host']) ? $_POST['db_host'] : "";
$database_name = isset($_POST['db_name']) ? $_POST['db_name'] : "";
$database_username = isset($_POST['db_username']) ? $_POST['db_username'] : "";
$database_password = isset($_POST['db_password']) ? $_POST['db_password'] : "";

// prepare the response
$arr = [];
$arr['status'] = 1;
$arr['db_connection_status'] = 0;
$arr['db_error'] = '';

// validation
if (empty($database_host)) {
    $arr['db_error'] = lang_key("alert_db_host_empty");
}
elseif (empty($database_name)) {
    $arr['db_error'] = lang_key("alert_db_name_empty");
}
elseif (empty($database_username)) {
    $arr['db_error'] = lang_key("alert_db_username_empty");
}

// attempt to connect to the database
if (!strlen($arr['db_error'])) {

    // attempt to connect to the database
    $db = Database::getDatabase(false, false, $database_host, $database_name, $database_username, $database_password, true);
    $db->setNotifyType(Database::NOTIFY_TYPE_ARRAY);
    $dbConnection = $db->connect();
    if ($dbConnection === true) {
        $arr['db_connection_status'] = 1;
    } else {
        $arr['db_error'] = $dbConnection['error'];
    }
}

echo json_encode($arr);