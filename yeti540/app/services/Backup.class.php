<?php

namespace App\Services;

use Ifsnop\Mysqldump as IMysqldump;

class Backup
{
    public $backupPath = null;
    public $sqlBackupFilename = null;
    public $zipBackupFilename = null;
    public $skipDatabaseTableData = array('sessions', 'cross_site_action', 'download_token');
    public $skipFolderContents = array('files', '.svn', 'cache', 'logs');

    function __construct() {
        // make sure the script can handle large folders/files
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        // setup paths
        $this->backupPath = DOC_ROOT . '/files/_backup';

        // make sure the backup folder exists
        if (!file_exists($this->backupPath)) {
            @mkdir($this->backupPath);
        }
    }

    public function getBackupPath() {
        return $this->backupPath;
    }

    public function backupDatabase() {
        // setup database backup filename
        $this->sqlBackupFilename = 'database_' . date('YmdHis') . '_' . MD5(microtime()) . '.sql';

        // setup the sql backup path
        $sqlBackupFilePath = $this->getBackupPath() . '/' . $this->sqlBackupFilename;

        // setup the options
        $dumpSettings = array(
            'add-drop-table' => true,
            'no-data' => $this->skipDatabaseTableData,
        );

        // dump the database
        $dump = new IMysqldump\Mysqldump('mysql:host=' . _CONFIG_DB_HOST . ';dbname=' . _CONFIG_DB_NAME, _CONFIG_DB_USER, _CONFIG_DB_PASS, $dumpSettings);
        $dump->start($sqlBackupFilePath);

        return file_exists($sqlBackupFilePath);
    }

    public function backupCode() {
        return $this->zipPath(DOC_ROOT);
    }

    public function zipPath($source) {
        // setup database backup filename
        $this->zipBackupFilename = 'code_' . date('YmdHis') . '_' . MD5(microtime()) . '.zip';

        // setup the zip backup path
        $sqlBackupFilePath = $this->getBackupPath() . '/' . $this->zipBackupFilename;
        if (extension_loaded('zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($sqlBackupFilePath, \ZIPARCHIVE::CREATE)) {
                $source = realpath($source);
                if (is_dir($source)) {
                    $iterator = new \RecursiveDirectoryIterator($source);
                    $filtered = new DirFilter($iterator, $this->skipFolderContents);

                    // skip dot files while iterating 
                    $files = new \RecursiveIteratorIterator($filtered, \RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $file) {
                        // double check path
                        $file = realpath($file);
                        if ($source != substr($file, 0, strlen($source))) {
                            continue;
                        }

                        if (is_dir($file)) {
                            $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                        }
                        else if (is_file($file)) {
                            $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }
                    }
                }
                else if (is_file($source)) {
                    $zip->addFromString(basename($source), file_get_contents($source));
                }
            }

            // add empty directories back in
            $zip->addEmptyDir('files');
            $zip->addEmptyDir('cache');
            $zip->addEmptyDir('logs');

            return $zip->close();
        }

        return false;
    }

}

class DirFilter extends \RecursiveFilterIterator
{
    protected $exclude;

    public function __construct($iterator, array $exclude) {
        parent::__construct($iterator);
        $this->exclude = $exclude;
    }

    public function accept() {
        return !($this->isDir() && in_array($this->getFilename(), $this->exclude));
    }

    public function getChildren() {
        return new DirFilter($this->getInnerIterator()->getChildren(), $this->exclude);
    }

}
