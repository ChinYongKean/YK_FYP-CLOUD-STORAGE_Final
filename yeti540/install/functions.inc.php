<?php

use App\Core\Database;

/**
 * Prepare reading of SQL dump file and executing SQL statements
 *
 * @param string $sqlDumpFile
 * @param $db
 * @return bool
 */
function dbImport(string $sqlDumpFile, $db)
{
    $query = "";

    // get  sql dump content
    $sqlDump = file($sqlDumpFile);

    // add ";" at the end of file to catch last sql query
    if (substr($sqlDump[count($sqlDump) - 1], -1) != ";") {
        $sqlDump[count($sqlDump) - 1] .= ";";
    }

    foreach ($sqlDump as $sqlLine) {
        $tsl = trim(utf8_decode($sqlLine));
        $tsl = trim($sqlLine);
        if (($sqlLine != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "?") && (substr($tsl, 0, 1) != "#")) {
            $query .= $sqlLine;
            if (preg_match("/;\s*$/", $sqlLine)) {
                if (strlen(trim($query)) > 5) {
                    if (!@$db->query($query)) {
                        return false;
                    }
                }
                $query = "";
            }
        }
    }

    return true;
}

/**
 * Returns language key
 *
 * @param $key
 * @return array|mixed|string|string[]
 */
function lang_key($key)
{
    global $arrLang;
    $output = "";

    if (isset($arrLang[$key])) {
        $output = $arrLang[$key];
    } else {
        $output = str_replace("_", " ", $key);
    }

    return $output;
}

function getInstallHost()
{
    return $_SERVER["SERVER_NAME"];
}

function getInstallPath()
{
    $pageURL = $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];

    // remove install folder
    $pageUrlExp = explode("/install/", $pageURL);

    return $pageUrlExp[0];
}

function genRandomString($length = 16)
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
    $final_rand = '';
    for ($i = 0; $i < $length; $i++) {
        $final_rand .= $chars[rand(0, strlen($chars) - 1)];
    }

    return $final_rand;
}

function isExistingInstall()
{
    // function to check if we're already on an existing install
    $existingInstall = false;
    if (file_exists('../_config.inc.php')) {
        include_once('../_config.inc.php');
        if (strlen(_CONFIG_SITE_HOST_URL)) {
            $existingInstall = true;
        }
    }

    return $existingInstall;
}

function getScriptVersion()
{
    require_once(realpath(dirname(__FILE__).'/../app/core/Framework.class.php'));

    return App\Core\Framework::VERSION_NUMBER;
}

function validateLicenseKey($licenseKey)
{
    return ["status"=>"SUCCESS"];
}
