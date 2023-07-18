<?php

/*
 * Title: Sanity check files
 * Author: Yetishare.com
 * Period: As required
 * 
 * Description:
 * Script to check the stored files on the file system against what Yetishare has
 * listed in the database. It will also work on external file servers, just make
 * sure it's always called from this directory and via the command line.
 * 
 * Note: This script may take some time to run and it iterates over all your
 * stored files.
 *
 * How To Call:
 * On the command line via PHP, like this:
 * php test_sanity_check_files.php
 */

namespace App\Tasks;

// include framework
use App\Core\Database;
use App\Core\Framework;
use App\Helpers\CoreHelper;
use App\Helpers\FileHelper;

require_once(realpath(dirname(__FILE__).'/../core/Framework.class.php'));

// setup light environment
Framework::runLight();

// get database
$db = Database::getDatabase();

// output type
if (($argv[0] == '--listfailed') || (isset($argv[1]) && ($argv[1] == '--listfailed')) || (isset($argv[2]) && ($argv[2] == '--listfailed'))) {
    define('LISTFAILED', true);
}
else {
    define('LISTFAILED', false);
}

// should we move into _deleted
if (($argv[0] == '--deletefailed') || (isset($argv[1]) && ($argv[1] == '--deletefailed')) || (isset($argv[2]) && ($argv[2] == '--deletefailed'))) {
    define('DELETEFAILED', true);
}
else {
    define('DELETEFAILED', false);
}

// first get the id of the current server
$currentServerId = FileHelper::getCurrentServerId();

// get list of active files on that server for lookups
$activeFiles = $db->getRows('SELECT localFilePath '
        . 'FROM file '
        . 'WHERE status IN ("active", "trash") '
        . 'GROUP BY localFilePath');
$activeFilePaths = [];
foreach ($activeFiles AS $activeFile) {
    $activeFilePaths[] = $activeFile['localFilePath'];
}
unset($activeFiles);

// get root server storage path
$uploadServerDetails = FileHelper::loadServerDetails($currentServerId);
if ($uploadServerDetails) {
    $storageLocation = $uploadServerDetails['storagePath'];
    $storageType = $uploadServerDetails['serverType'];
    $serverName = $uploadServerDetails['serverLabel'];
}

// make sure path is absolute
$storageLocation = str_replace(DOC_ROOT, '', $storageLocation);
if (substr($storageLocation, strlen($storageLocation) - 1, 1) == '/') {
    $storageLocation = substr($storageLocation, 0, strlen($storageLocation) - 1);
}
$storageLocation = DOC_ROOT . '/' . $storageLocation;
$storageLocation .= '/';
define('STORAGE_LOCATION', $storageLocation);

// log
echo "\n";

// log
SanityChecker::output('*******************************************************************');
SanityChecker::output('Server Name: ' . $serverName);
SanityChecker::output('Server ID: ' . $currentServerId);
SanityChecker::output('File Storage Path: ' . $storageLocation);
SanityChecker::output('*******************************************************************');

// loop over files and log any which exist on the file system but not in our database
SanityChecker::checkFiles($storageLocation, $activeFilePaths);

// log
SanityChecker::output('*******************************************************************');
SanityChecker::output('Finished.');
SanityChecker::output('*******************************************************************');
SanityChecker::output('Matched Files: ' . SanityChecker::$found);
SanityChecker::output('Failed Matches: ' . SanityChecker::$fails);
SanityChecker::output('Deleted: ' . SanityChecker::$deleted);
SanityChecker::output('*******************************************************************');
if ((SanityChecker::$fails > 0) && (SanityChecker::$deleted == 0)) {
    SanityChecker::output('You can list just the failed files by re-running this script and');
    SanityChecker::output('adding --listfailed onto the end. Like this:');
    SanityChecker::output('$ php test_sanity_check_files --listfailed');
    SanityChecker::output('*******************************************************************');
    SanityChecker::output('You can also move any failed matches into /files/_deleted/ by');
    SanityChecker::output('adding --deletefailed onto the end. Like this:');
    SanityChecker::output('$ php test_sanity_check_files --deletefailed');
    SanityChecker::output('Once moved, manually remove the contents of _deleted when you\'re');
    SanityChecker::output('happy everything is working as it should be.');
    SanityChecker::output('USE WITH CARE THOUGH! YOU SHOULD BE SURE THESE FILES AREN\'T USED');
    SanityChecker::output('BEFORE MOVING THEM. IF ERRORS OCCUR, JUST COPY THE FILES BACK.');
    SanityChecker::output('*******************************************************************');
}
else {
    SanityChecker::output('No failed matches. Everything is ok.');
    SanityChecker::output('*******************************************************************');
}

// log
echo "\n";

// local functions
class SanityChecker
{
    static public $fails = 0;
    static public $found = 0;
    static public $deleted = 0;
    static public $failedPaths = [];

    static public function checkFiles($localPath, $activeFilePaths) {
        // get items
        $items = CoreHelper::getDirectoryListing($localPath);
        if (count($items)) {
            foreach ($items AS $item) {
                if (is_dir($item)) {
                    // ignores
                    $partialPath = str_replace(STORAGE_LOCATION, '', $item);
                    if (substr($partialPath, 0, 1) == '/') {
                        $partialPath = substr($partialPath, 1, strlen($partialPath) - 1);
                    }
                    if (in_array($partialPath, array('_tmp', '_deleted'))) {
                        continue;
                    }

                    // loop again for files within
                    SanityChecker::checkFiles($item, $activeFilePaths);
                }
                else {
                    // compare with database data
                    $partialPath = str_replace(STORAGE_LOCATION, '', $item);
                    if (substr($partialPath, 0, 1) == '/') {
                        $partialPath = substr($partialPath, 1, strlen($partialPath) - 1);
                    }

                    // ignores
                    if (in_array($partialPath, array('.htaccess', 'Thumbs.db'))) {
                        continue;
                    }

                    // check data
                    $inData = in_array($partialPath, $activeFilePaths);
                    if ($inData) {
                        self::addFound();
                    }
                    else {
                        self::addFail($partialPath);
                        if (DELETEFAILED == true) {
                            self::delete($item, $partialPath);
                        }

                        if (LISTFAILED == true) {
                            echo $partialPath . "\n";
                        }
                        else {
                            SanityChecker::output('Error: Data not found in database (' . $partialPath . ')');
                        }
                    }
                }
            }
        }
    }

    static function delete($fullPath, $partialPath) {
        // move file into _deleted
        $finalPath = STORAGE_LOCATION . '_deleted/' . $partialPath;
        if (!file_exists(dirname($finalPath))) {
            @mkdir(dirname($finalPath), 0755, true);
        }

        rename($fullPath, $finalPath);

        self::addDeleted();
    }

    static public function output($msg = '', $exit = false) {
        if (LISTFAILED == true) {
            return;
        }
        echo $msg . "\n";
    }

    static function addFail($failedPath) {
        self::$fails++;
        self::$failedPaths[] = $failedPath;
    }

    static function addFound() {
        self::$found++;
    }

    static function addDeleted() {
        self::$deleted++;
    }

}
