<?php

session_start();

require_once("../settings.inc.php");
require_once("../functions.inc.php");
require_once("../languages.inc.php");

$license_key = isset($_POST['license_key']) ? trim($_POST['license_key']) : "";

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT"); // always modified
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0
header("Content-Type: application/json");

// validate request
$rs = [];
$rs['error'] = true;
$rs['messageType'] = 'error';
if (strlen($license_key) === 0) {
    $rs['title'] = 'Error';
    $rs['content'] = 'Please enter your license key.';
} else {
    $responseArr = validateLicenseKey($license_key);

    // handle response
    if (!is_array($responseArr)) {
        $rs['title'] = 'Error';
        $rs['content'] = 'Could not validate license, please try again later.';
    } else {
        $rs['error'] = (int)$responseArr['error'] === 1;
        $rs['messageType'] = $rs['error'] === true ? 'error' : 'success';
        $rs['title'] = $rs['error'] === true ? 'Error' : 'Passed';
        $rs['content'] = $responseArr['msg'];
    }
}

echo json_encode($rs, true);
