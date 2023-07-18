<?php

/**
 * Main file server container class. Any new adapters defined in Flysystem should be
 * added below as a new method named 'init[UCWORDS_SERVER_TYPE]' i.e. 'initFlysystemFtp'
 */

namespace App\Helpers;

use App\Core\Database;
// ftp
use \League\Flysystem\Adapter\Ftp;
// sftp
use \League\Flysystem\Sftp\SftpAdapter;
// aws
use \Aws\S3\S3Client;
use \League\Flysystem\AwsS3v3\AwsS3Adapter;
// rackspace
use \OpenCloud\OpenStack;
use \OpenCloud\Rackspace;
use \League\Flysystem\Rackspace\RackspaceAdapter;
// backblaze
use \Mhetreramesh\Flysystem\BackblazeAdapter;
use \BackblazeB2\Client;
// core flysystem
use \League\Flysystem\Filesystem;

class FileServerContainerHelper
{

    /**
     * Main function to instantiate the Flysystem adapter.
     * 
     * @param integer $fileServerId
     * @return boolean
     */
    public static function init($fileServerId) {
        // get database connection
        $db = Database::getDatabase();

        // load the file server details
        $fileServer = FileHelper::getServerDetailsById($fileServerId);
        if (!$fileServer) {
            return false;
        }

        // figure out the name of the method to call within this class
        $serverType = $fileServer['serverType'];
        $methodName = 'init' . str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($serverType))));

        // make sure the method exists
        if (!method_exists(__CLASS__, $methodName)) {
            die('Static method \'' . $methodName . '\' for Flysystem adapter does not exist in: ' . __FILE__ . ', it will need to be added.');
        }

        // init the adapter
        return call_user_func('self::' . $methodName, $fileServer);
    }

    /**
     * Method for instantiating Flysystem FTP connectivity.
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemFtp($fileServer) {
        // prep config
        $config = self::_doConfigReplacements($fileServer['serverConfig'], [
                    'host' => '',
                    'username' => '',
                    'password' => '',
                    'port' => '',
                    'root' => '',
                    'passive' => '',
                    'ssl' => '',
                    'timeout' => '',
        ]);

        // connect
        $adapter = new \League\Flysystem\Adapter\Ftp($config);

        return new Filesystem($adapter);
    }

    /**
     * Method for instantiating Flysystem SFTP connectivity.
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemSftp($fileServer) {
        // prep config
        $config = self::_doConfigReplacements($fileServer['serverConfig'], [
                    'host' => '',
                    'port' => 21,
                    'username' => '',
                    'password' => '',
                    'root' => '/',
                    'timeout' => 30,
        ]);

        // connect
        $adapter = new \League\Flysystem\Sftp\SftpAdapter($config);

        return new Filesystem($adapter);
    }

    /**
     * Method for instantiating Flysystem AWS (SDK v3) connectivity.
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemAws($fileServer) {
        // prep config
        $config = self::_doConfigReplacements($fileServer['serverConfig'], [
                    'credentials' => [
                        'key' => '',
                        'secret' => ''
                    ],
                    'region' => '',
                    'version' => '',
        ]);

        // setup client
        $client = new S3Client($config);

        // turn the config json into an array
        $serverConfigArr = json_decode($fileServer['serverConfig'], true);

        // connect
        $adapter = new \League\Flysystem\AwsS3v3\AwsS3Adapter($client, $serverConfigArr['bucket']);

        return new Filesystem($adapter);
    }

    /**
     * Method for instantiating Flysystem Rackspace Cloud Files connectivity.
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemRackspace($fileServer) {
        // prep config
        $config = self::_doConfigReplacements($fileServer['serverConfig'], [
                    'username' => '',
                    'apiKey' => '',
        ]);

        // setup client
        $client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, $config);

        // turn the config json into an array
        $serverConfigArr = json_decode($fileServer['serverConfig'], true);

        // connect
        $store = $client->objectStoreService('cloudFiles', $serverConfigArr['region']);
        $container = $store->getContainer($serverConfigArr['container']);

        return new Filesystem(new \League\Flysystem\Rackspace\RackspaceAdapter($container));
    }
    
    /**
     * Method for instantiating Flysystem Backblaze Cloud Files connectivity.
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemBackblazeB2($fileServer) {
        // turn the config json into an array
        $serverConfigArr = json_decode($fileServer['serverConfig'], true);        
        
        // setup the client
        $client = new Client($serverConfigArr['account_id'], $serverConfigArr['application_key']);
        $adapter = new BackblazeAdapter($client, $serverConfigArr['bucket']);

        return new Filesystem($adapter);
    }

    /**
     * Helper for replacing config values for the adapters above.
     * 
     * @param string $serverConfig
     * @param array $configTemplate
     * @return array
     */
    public static function _doConfigReplacements($serverConfig, $configTemplate) {
        // turn the config json into an array
        $serverConfigArr = json_decode($serverConfig, true);

        // loop over the config template and populate the config for this file server
        foreach ($configTemplate AS $k => $configTemplateItem) {
            // allow for sub-arrays
            if (is_array($configTemplateItem)) {
                foreach ($configTemplateItem AS $k2 => $configTemplateItem2) {
                    $configTemplate[$k][$k2] = $serverConfigArr[$k2];
                }
            }
            else {
                $configTemplate[$k] = $serverConfigArr[$k];
            }
        }

        return $configTemplate;
    }
    
    /**
     * Method for instantiating Flysystem Wasabi connectivity
     * 
     * @param array $fileServer
     * @return Filesystem
     */
    public static function initFlysystemWasabi($fileServer) {
        // prep config
        $serverConfigArr = json_decode($fileServer['serverConfig'], true);
        $config = self::_doConfigReplacements($fileServer['serverConfig'], [
                    'credentials' => [
                        'key' => '',
                        'secret' => ''
                    ],
                    'region' => '',
                    'version' => '',
        ]);
        
        // add hardcoded properties
        $config['endpoint'] = 'https://' . $serverConfigArr['bucket'] . '.s3.' . $serverConfigArr['region'] . '.wasabisys.com';
        $config['bucket_endpoint'] = true;
        $config['version'] = 'latest';

        // setup client
        $client = new S3Client($config);

        // connect
        $adapter = new \League\Flysystem\AwsS3v3\AwsS3Adapter($client, $serverConfigArr['bucket'], '/');

        return new Filesystem($adapter);
    }

}
