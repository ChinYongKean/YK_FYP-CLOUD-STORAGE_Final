<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\FileServer;

/**
 * main file server class
 */
class FileServerHelper
{

    static function getAvailableServerId() {
        // connect db
        $db = Database::getDatabase();

        // if user logged in, check for server override
        $Auth = AuthHelper::getAuth();
        if ($Auth->loggedIn() === true) {
            // user is logged in, check for server override
            if ((int) $Auth->user->uploadServerOverride) {
                // double check via the database
                $uploadServerOverride = (int) $db->getValue('SELECT uploadServerOverride '
                                . 'FROM users '
                                . 'WHERE id = :id '
                                . 'LIMIT 1', array(
                            'id' => $Auth->id,
                ));
                if ($uploadServerOverride) {
                    return $uploadServerOverride;
                }
            }
        }

        // get users country
        $usersIp = StatsHelper::getIP();
        $country = StatsHelper::getCountry($usersIp);

        // ignore 'unknown' countries, these are generally when the script is running locally
        if($country === 'ZZ') {
            $country = null;
            $appendCountrySql = '';
        }
        else {
            $appendCountrySql = 'AND (geoUploadCountries LIKE "%,'.$db->escape($country).',%" OR geoUploadCountries IS NULL) ';
        }

        // add any restrictions by account type
        $appendTypesSql = 'AND (accountUploadTypes LIKE "%,'.(int)$Auth->package_id.',%" OR accountUploadTypes IS NULL) ';

        // check for only available file servers, if the setting is enabled
        $availabilitySql = 'AND (IF(enable_availability_checker = 1, availability_state, 1) IN (null, 1)) ';

        // choose server
        switch (SITE_CONFIG_C_FILE_SERVER_SELECTION_METHOD) {
            case 'Least Used Space':
                $sQL = "SELECT file_server.id ";
                $sQL .= "FROM file_server ";
                $sQL .= "WHERE statusId = 2 ";
                $sQL .= $appendCountrySql;
                $sQL .= $appendTypesSql;
                $sQL .= $availabilitySql;
                $sQL .= "ORDER BY totalSpaceUsed ASC";

                $serverDetails = $db->getRow($sQL);
                if (is_array($serverDetails)) {
                    return $serverDetails['id'];
                }

                // none found so return false
                return false;

                break;
            case 'Until Full':
                $sQL = "SELECT file_server.id "
                        . "FROM file_server "
                        . "WHERE IF(maximumStorageBytes > 0, totalSpaceUsed <= maximumStorageBytes, 1=1) "
                        . "AND statusId = 2 "
                        . $appendCountrySql
                        . $appendTypesSql
                        . $availabilitySql
                        . "ORDER BY priority ASC, id ASC";

                $serverDetails = $db->getRow($sQL);
                if (is_array($serverDetails)) {
                    return $serverDetails['id'];
                }

                // none found so return false
                return false;

                break;
            default:
                // lookup specific server
                $sQL = "SELECT id "
                        . "FROM file_server "
                        . "WHERE serverLabel = :serverLabel "
                        . "AND statusId = 2 "
                        . $appendCountrySql
                        . $appendTypesSql
                        . $availabilitySql
                        . "LIMIT 1";
                $serverDetails = $db->getRow($sQL, array(
                    'serverLabel' => SITE_CONFIG_DEFAULT_FILE_SERVER,
                ));
                if (is_array($serverDetails)) {
                    return $serverDetails['id'];
                }
                
                // none found, revert to loading for any active by country, with matching 
                // account type aswell
                if($country !== 'ZZ') {
                    $sQL = "SELECT file_server.id ";
                    $sQL .= "FROM file_server ";
                    $sQL .= "WHERE statusId = 2 ";
                    $sQL .= 'AND geoUploadCountries LIKE "%,'.$db->escape($country).',%" ';
                    $sQL .= 'AND accountUploadTypes LIKE "%,'.(int)$Auth->package_id.',%" ';
                    $sQL .= $availabilitySql;
                    $sQL .= "ORDER BY priority ASC, id ASC";

                    $serverDetails = $db->getRow($sQL);
                    if (is_array($serverDetails)) {
                        return $serverDetails['id'];
                    }
                }
                
                // none found, revert to loading for any active by account type only
                $sQL = "SELECT file_server.id ";
                $sQL .= "FROM file_server ";
                $sQL .= "WHERE statusId = 2 ";
                $sQL .= 'AND accountUploadTypes LIKE "%,'.(int)$Auth->package_id.',%" ';
                $sQL .= $availabilitySql;
                $sQL .= "ORDER BY priority ASC, id ASC";

                $serverDetails = $db->getRow($sQL);
                if (is_array($serverDetails)) {
                    return $serverDetails['id'];
                }
                
                // none found, revert to loading for any active by country, ignoring account type
                if($country !== 'ZZ') {
                    $sQL = "SELECT file_server.id ";
                    $sQL .= "FROM file_server ";
                    $sQL .= "WHERE statusId = 2 ";
                    $sQL .= 'AND geoUploadCountries LIKE "%,'.$db->escape($country).',%" ';
                    $sQL .= $availabilitySql;
                    $sQL .= "ORDER BY priority ASC, id ASC";

                    $serverDetails = $db->getRow($sQL);
                    if (is_array($serverDetails)) {
                        return $serverDetails['id'];
                    }
                }

                // none found so return false
                return false;
        }

        // fall back
        return false;
    }

    static function nginxXAccelRedirectEnabled($serverId) {
        // connect db
        $db = Database::getDatabase();

        // nginx, look for config value
        $nginx = $db->getRow("SELECT dlAccelerator "
                . "FROM file_server "
                . "WHERE id = :id "
                . "AND (serverType = 'direct' OR serverType = 'local') "
                . "LIMIT 1", array(
            'id' => $serverId,
        ));
        if ((int) $nginx['dlAccelerator'] == 1) {
            return true;
        }

        return false;
    }

    static function apacheXSendFileEnabled($serverId) {
        // connect db
        $db = Database::getDatabase();

        // apache, look for config value
        $apache = $db->getRow("SELECT dlAccelerator "
            . "FROM file_server "
            . "WHERE id = :id "
            . "AND (serverType = 'direct' OR serverType = 'local') "
            . "LIMIT 1", array(
            'id' => $serverId,
        ));
        if ((int) $apache['dlAccelerator'] == 2) {
            return true;
        }

        return false;
    }

    static function litespeedXLitespeedLocationEnabled($serverId) {
        // connect db
        $db = Database::getDatabase();

        // apache, look for config value
        $litespeed = $db->getRow("SELECT dlAccelerator "
            . "FROM file_server "
            . "WHERE id = :id "
            . "AND (serverType = 'direct' OR serverType = 'local') "
            . "LIMIT 1", array(
            'id' => $serverId,
        ));

        if ((int) $litespeed['dlAccelerator'] == 3) {
            return true;
        }

        return false;
    }

    static function setDocRootData($fileServerId, $docRoot) {
        $db = Database::getDatabase();

        // get file server data
        $serverData = $db->getValue("SELECT serverConfig "
                . "FROM file_server "
                . "WHERE id = :id "
                . "LIMIT 1", array(
            'id' => $fileServerId,
        ));
        if ($serverData === false) {
            return false;
        }

        $serverDataArr = [];
        if (strlen($serverData)) {
            $serverDataArr = json_decode($serverData, true);
        }

        $serverDataArr['server_doc_root'] = $docRoot;

        // update in database
        $sQL = 'UPDATE file_server '
                . 'SET serverConfig = :serverConfig, '
                . 'scriptRootPath = :scriptRootPath '
                . 'WHERE id = :fileServerId '
                . 'LIMIT 1';
        $db->query($sQL, array(
            'fileServerId' => (int) $fileServerId,
            'serverConfig' => json_encode($serverDataArr),
            'scriptRootPath' => $docRoot,
        ));

        return true;
    }

    static function getDocRoot($fileServerId) {
        $db = Database::getDatabase();

        // get file server data
        $sQL = "SELECT scriptRootPath "
                . "FROM file_server "
                . "WHERE id = :fileServerId "
                . "LIMIT 1";
        $scriptRootPath = $db->getValue($sQL, array(
            'fileServerId' => (int) $fileServerId,
        ));
        if ($scriptRootPath === false) {
            return false;
        }

        // if we have the script root, return it
        if (strlen($scriptRootPath)) {
            return $scriptRootPath;
        }

        // failed finding the script root from the database, try to find it and 
        // store for future use
        $sQL = "SELECT * "
                . "FROM file_server "
                . "WHERE id = :fileServerId "
                . "LIMIT 1";
        $row = $db->getRow($sQL, array(
            'fileServerId' => (int) $fileServerId,
        ));

        switch ($row['serverType']) {
            case 'direct':
                // set new doc root
                $url = CrossSiteActionHelper::appendUrl(_CONFIG_SITE_PROTOCOL . '://' . $row['fileServerDomainName'] . $row['scriptPath'] . '/' . ADMIN_FOLDER_NAME . '/ajax/server_manage_get_server_detail.ajax.php');
                $responseJson = CoreHelper::getRemoteUrlContent($url);
                $responseArr = json_decode($responseJson, true);
                if (!is_array($responseArr)) {
                    return false;
                }

                $scriptRootPath = $responseArr['server_doc_root'];
                if (strlen($scriptRootPath)) {
                    self::setDocRootData($fileServerId, $scriptRootPath);
                }
                break;
            case 'local':
                $scriptRootPath = DOC_ROOT;
                self::setDocRootData($fileServerId, $scriptRootPath);

                // ensure ALL other local servers are up to date
                $sQL = "SELECT id "
                        . "FROM file_server "
                        . "WHERE id != :fileServerId "
                        . "AND serverType = 'local' "
                        . "AND (scriptRootPath = '' OR scriptRootPath IS NULL)";
                $localServers = $db->getRows($sQL, array(
                    'fileServerId' => (int) $fileServerId,
                ));
                if ($localServers) {
                    foreach ($localServers AS $localServer) {
                        self::setDocRootData($localServer['id'], $scriptRootPath);
                    }
                }

            default:
                // nothing for other file types
                break;
        }

        return $scriptRootPath;
    }

    static function getFileServerAccessDetails($directFileServers = array()) {
        // setup database
        $db = Database::getDatabase();

        // get default local server id for later
        $serverId = FileHelper::getDefaultLocalServerId();

        // get server access details
        $serverDetails = $db->getRows('SELECT id, serverAccess, storagePath '
                . 'FROM file_server '
                . 'WHERE serverAccess IS NOT NULL '
                . 'AND serverType IN (\'local\', \'direct\')');
        if ($serverDetails) {
            foreach ($serverDetails AS $serverDetail) {
                $serverAccess = CoreHelper::decryptValue($serverDetail['serverAccess']);
                $serverAccessArray = json_decode($serverAccess, true);
                if (!is_array($serverAccessArray)) {
                    continue;
                }

                $storagePath = $serverDetail['storagePath'];
                if (strlen($storagePath) == 0) {
                    $storagePath = "files/";
                }

                // remove trailing forward slash
                if (substr($storagePath, strlen($storagePath) - 1, 1) == '/') {
                    $storagePath = substr($storagePath, 0, strlen($storagePath) - 1);
                }

                // override any existing entries
                foreach ($directFileServers AS $k => $directFileServer) {
                    if ($directFileServer['file_server_id'] == $serverDetail['id']) {
                        unset($directFileServers[$k]);
                    }
                }

                // log which is the primary server
                $primaryServer = false;
                if ($serverId == $serverDetail['id']) {
                    $primaryServer = true;
                }

                $directFileServers[] = array(
                    'file_server_id' => $serverDetail['id'],
                    'ssh_host' => $serverAccessArray['file_server_direct_ip_address'],
                    'ssh_port' => $serverAccessArray['file_server_direct_ssh_port'],
                    'ssh_username' => $serverAccessArray['file_server_direct_ssh_username'],
                    'ssh_password' => isset($serverAccessArray['file_server_direct_ssh_password']) ? $serverAccessArray['file_server_direct_ssh_password'] : null,
                    'ssh_key' => isset($serverAccessArray['file_server_direct_ssh_key']) ? $serverAccessArray['file_server_direct_ssh_key'] : null,
                    'ssh_authentication_type' => isset($serverAccessArray['file_server_direct_ssh_authentication_type']) ? $serverAccessArray['file_server_direct_ssh_authentication_type'] : 'ssh_password',
                    'primary_local_server' => $primaryServer,
                );
            }
        }

        return $directFileServers;
    }

    // note, ensure it always ends with a forward slash
    static function getCurrentServerFileStoragePath() {
        return self::getServerFileStoragePath(FileHelper::getCurrentServerDetails());
    }

    static function getServerFileStoragePath($uploadServerDetails) {
        $currentServerDocRoot = self::getDocRoot($uploadServerDetails['id']);

        $path = $currentServerDocRoot . '/' . $uploadServerDetails['storagePath'];
        if (substr($path, strlen($path) - 1, 1) != '/') {
            $path .= '/';
        }

        return $path;
    }

    static function getServersWithApplicationCache() {
        // load all servers which could have application cache on them. Direct
        // or Local servers which are active or read only.
        return FileServer::loadByClause('(serverType = "direct" '
                . 'AND statusId IN (2, 3)) OR serverType = "local"');
    }
}
