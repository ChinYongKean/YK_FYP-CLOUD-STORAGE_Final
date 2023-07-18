<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\FileServer;
use App\Models\FileServerResourceUsage;

class ServerResourceHelper
{

    static function logResources()
    {
        // action for the current server id
        $currentServer = FileHelper::getCurrentServerDetails();

        // if we're calling this script from a local server, load all other local servers (in the case that some are
        // defined as additional local storage points)
        $servers = [$currentServer];
        if ($currentServer['serverType'] === 'local') {
            // get file server cache
            $fileServers = FileHelper::getFileServerData();
            $servers = [];
            foreach ($fileServers as $fileServer) {
                if ($fileServer['serverType'] === 'local' && (int)$fileServer['capture_resource_usage'] === 1) {
                    $servers[] = $fileServer;
                }
            }
        } elseif ($currentServer['serverType'] !== 'direct' && (int)$currentServer['capture_resource_usage'] === 0) {
            // only 'direct' or 'local' servers can be used here
            return false;
        }

        // exit if no valid server to check
        if(!count($servers)) {
            return false;
        }

        // loop servers and update resource stats
        foreach($servers AS $server) {
            // get all stats
            $resourceStats = self::getAllResourceStats($server);

            // update stats into the database
            $fileServerResourceUsage = FileServerResourceUsage::create();
            $fileServerResourceUsage->file_server_id = $server['id'];
            $fileServerResourceUsage->date_created = CoreHelper::sqlDateTime();
            $fileServerResourceUsage->cpu_load_1_minute = $resourceStats['cpu_load']['1_minute'];
            $fileServerResourceUsage->cpu_load_5_minutes = $resourceStats['cpu_load']['5_minutes'];
            $fileServerResourceUsage->cpu_load_15_minutes = $resourceStats['cpu_load']['15_minutes'];
            $fileServerResourceUsage->cpu_count = $resourceStats['cpu_count'];
            $fileServerResourceUsage->memory_total_gb = $resourceStats['memory']['memory_total_gb'];
            $fileServerResourceUsage->memory_used_gb = $resourceStats['memory']['memory_used_gb'];
            $fileServerResourceUsage->memory_free_gb = $resourceStats['memory']['memory_free_gb'];
            $fileServerResourceUsage->memory_shared_gb = $resourceStats['memory']['memory_shared_gb'];
            $fileServerResourceUsage->memory_cached_gb = $resourceStats['memory']['memory_cached_gb'];
            $fileServerResourceUsage->memory_available_gb = $resourceStats['memory']['memory_available_gb'];
            $fileServerResourceUsage->disk_primary_total_bytes = $resourceStats['disk'][0]['usage']['disk_total_bytes'];
            $fileServerResourceUsage->disk_primary_used_bytes = $resourceStats['disk'][0]['usage']['disk_used_bytes'];
            $fileServerResourceUsage->disk_primary_used_percent = $resourceStats['disk'][0]['usage']['disk_used_percent'];
            $fileServerResourceUsage->network_established_connections = $resourceStats['network_established_connections'];
            $fileServerResourceUsage->network_total_connections = $resourceStats['network_total_connections'];
            $fileServerResourceUsage->has_shell_exec = $resourceStats['has_shell_exec'];
            $fileServerResourceUsage->has_netstat = $resourceStats['has_netstat'];
            $fileServerResourceUsage->save();
        }

        // ensure the database is disconnected
        $db = Database::getDatabase();
        $db->close();

        return true;
    }

    static function getAllResourceStats(array $currentServer = null): array
    {
        // fallback onto current server id
        if ($currentServer === null) {
            // action for the current server id
            $currentServer = FileHelper::getCurrentServerDetails();
        }

        return [
            'cpu_load' => self::getCPULoad(),
            'cpu_count' => self::getCPUCount(),
            'memory' => self::getMemoryStats(),
            'disk' => self::getDiskStats($currentServer),
            'network_established_connections' => self::getEstablishedConnections(),
            'network_total_connections' => self::getTotalConnections(),
            'has_shell_exec' => (int)function_exists('shell_exec'),
            'has_netstat' => (int)(function_exists('shell_exec') && (bool)shell_exec('netstat')),
        ];
    }

    static function getCPULoad(): ?array
    {
        // get load, last 1, 5 and 15 minutes, respectively
        $loadAverage = sys_getloadavg();
        if (!$loadAverage) {
            return null;
        }

        return [
            '1_minute' => $loadAverage[0],
            '5_minutes' => $loadAverage[1],
            '15_minutes' => $loadAverage[1],
        ];
    }

    static function getCPUCount()
    {
        // get the total CPUs, only supported if shell_exec() enabled
        if (!function_exists('shell_exec')) {
            return null;
        }

        return shell_exec('nproc');
    }

    static function getMemoryStats(): ?array
    {
        // get the total CPUs, only supported if shell_exec() enabled
        if (!function_exists('shell_exec')) {
            return null;
        }

        // get memory stats using shell_exec
        $free = shell_exec('free');
        $free = (string)trim($free);
        $freeArr = explode("\n", $free);
        $memory = explode(" ", $freeArr[1]);
        $memory = array_filter($memory, function ($value) {
            return ($value !== null && $value !== false && $value !== '');
        }); // removes nulls from array
        $memory = array_merge($memory); // puts arrays back to [0],[1],[2] after

        return [
            'memory_total_gb' => round($memory[1] / 1000000, 2),
            'memory_used_gb' => round($memory[2] / 1000000, 2),
            'memory_free_gb' => round($memory[3] / 1000000, 2),
            'memory_shared_gb' => round($memory[4] / 1000000, 2),
            'memory_cached_gb' => round($memory[5] / 1000000, 2),
            'memory_available_gb' => round($memory[6] / 1000000, 2),
        ];
    }

    static function getDiskStats(array $serverDetails): array
    {
        // get file storage path
        $serverFileStoragePath = FileServerHelper::getServerFileStoragePath($serverDetails);

        // add all locations to check
        $paths = [
            $serverFileStoragePath,
            sys_get_temp_dir(),
        ];

        // get stats for all paths
        $stats = [];
        foreach ($paths as $path) {
            // get disk stats
            $diskFree = disk_free_space($path);
            $diskTotal = disk_total_space($path);

            $stats[] = [
                'path' => $path,
                'usage' => [
                    'disk_free_bytes' => $diskFree,
                    'disk_total_bytes' => $diskTotal,
                    'disk_used_bytes' => $diskTotal - $diskFree,
                    'disk_used_percent' => number_format((($diskTotal - $diskFree) / $diskTotal) * 100, 2),
                ],
            ];
        }

        return $stats;
    }

    static function getEstablishedConnections()
    {
        // get the total CPUs, only supported if shell_exec() enabled
        if (!function_exists('shell_exec')) {
            return null;
        }

        // use netstat to get established connections
        $total = trim(shell_exec('netstat -ntu | grep :80 | grep ESTABLISHED | grep -v LISTEN | awk \'{print $5}\' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l'));
        if (!strlen($total)) {
            return null;
        }

        return (int)$total;
    }

    static function getTotalConnections()
    {
        // get the total CPUs, only supported if shell_exec() enabled
        if (!function_exists('shell_exec')) {
            return null;
        }

        // use netstat to get total connections
        $total = trim(shell_exec('netstat -ntu | grep :80 | grep -v LISTEN | awk \'{print $5}\' | cut -d: -f1 | sort | uniq -c | sort -rn | grep -v 127.0.0.1 | wc -l'));
        if (!strlen($total)) {
            return null;
        }

        return (int)$total;
    }

    static function updateAllServerAvailability() {
        // get all servers which are set to be monitored
        $fileServers = FileServer::loadByClause('enable_availability_checker = 1 AND serverType = "direct"');
        if($fileServers) {
            foreach($fileServers as $fileServer) {
                // get response code
                $responseCode = self::getFileServerResponseCode($fileServer);

                // update response
                $fileServer->availability_state = $responseCode === 200 ? 1: 0;
                $fileServer->save();
            }
        }
    }

    static function getFileServerResponseCode($fileServer) {
        // prepare URL
        $testUrl = _CONFIG_SITE_PROTOCOL . '://' . FileHelper::getFileDomainAndPath(null, $fileServer->id, true) . '/_config.inc.php';

        // create cURL handle
        $ch = curl_init($testUrl);

        // set timeouts
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        // execute
        curl_exec($ch);

        // get response code
        $responseCode = false;
        if (!curl_errno($ch)) {
            $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        // Close handle
        curl_close($ch);

        return $responseCode;
    }
}
