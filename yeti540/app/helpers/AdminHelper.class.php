<?php

namespace App\Helpers;

use App\Core\Database;
use App\Models\Plugin;

class AdminHelper
{
    public static function setError($error) {
        // store in the session
        if(!isset($_SESSION['errors'])) {
            $_SESSION['errors'] = [];
        }

        $_SESSION['errors'][] = $error;
    }

    public static function isErrors() {
        return self::getErrors() > 0;
    }

    public static function getErrors() {
        if (!isset($_SESSION['errors']) || count($_SESSION['errors']) == 0) {
            return false;
        }

        return $_SESSION['errors'];
    }

    public static function compileErrorHtml() {
        $html = '';
        if (self::getErrors()) {
            $html .= '<div class="alert alert-danger alert-dismissible fade in" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>';
            $html .= '<strong>ERROR</strong><br/>';
            foreach (self::getErrors() AS $error) {
                $html .= $error . '<br/>';
            }
            $html .= '</div>';
        }
        
        // clear existing
        $_SESSION['errors'] = [];

        return $html;
    }

    public static function setSuccess($success) {
        // store in the session
        if(!isset($_SESSION['success'])) {
            $_SESSION['success'] = [];
        }

        $_SESSION['success'][] = $success;
    }

    public static function isSuccess() {
        return self::getSuccess() > 0;
    }

    public static function getSuccess() {
        if (!isset($_SESSION['success']) || count($_SESSION['success']) == 0) {
            return false;
        }
        
        return $_SESSION['success'];
    }

    public static function compileSuccessHtml() {
        $html = '';
        if (self::getSuccess()) {
            $html .= '<div class="alert alert-success alert-dismissible fade in" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>';
            $html .= '<strong>SUCCESS</strong><br/>';
            foreach (self::getSuccess() AS $success) {
                $html .= $success . '<br/>';
            }
            $html .= '</div>';
        }
        
        // clear existing
        $_SESSION['success'] = [];

        return $html;
    }

    public static function setInfo($info) {
        // store in the session
        if(!isset($_SESSION['info'])) {
            $_SESSION['info'] = [];
        }

        $_SESSION['info'][] = $info;
    }

    public static function isInfo() {
        return self::getInfo() > 0;
    }

    public static function getInfo() {
        if (!isset($_SESSION['info']) || count($_SESSION['info']) == 0) {
            return false;
        }
        
        return $_SESSION['info'];
    }

    public static function compileInfoHtml() {
        $html = '';
        if (self::getInfo()) {
            $html .= '<div class="alert alert-info alert-dismissible fade in" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>';
            $html .= '<strong>RESULT</strong><br/>';
            foreach (self::getInfo() AS $info) {
                $html .= $info . '<br/>';
            }
            $html .= '</div>';
        }
        
        // clear existing
        $_SESSION['info'] = [];

        return $html;
    }

    public static function compileNotifications() {
        $html = self::compileErrorHtml();
        $html .= self::compileSuccessHtml();
        $html .= self::compileInfoHtml();

        return $html;
    }

    public static function makeSafe($str) {
        return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
    }

    public static function redirect($path) {
        // if no headers sent
        if (!headers_sent()) {
            header('location: ' . $path);
            exit;
        }

        // fallback to meta/javascript redirect
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $path . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $path . '" />';
        echo '</noscript>';
        exit;
    }

    public static function getDirectoryList($directory, $extFilter = null, $recurr = false) {
        return CoreHelper::getDirectoryList($directory, $extFilter, $recurr);
    }

    public static function formatSize($bytes, $decimals = 0) {
        $size = $bytes / 1024;
        if ($size < 1024) {
            $size = number_format($size, $decimals);
            $size .= ' KB';
        }
        else {
            if ($size / 1024 < 1024) {
                $size = number_format($size / 1024, $decimals);
                $size .= ' MB';
            }
            else if ($size / 1024 / 1024 < 1024) {
                $size = number_format($size / 1024 / 1024, $decimals);
                $size .= ' GB';
            }
            else if ($size / 1024 / 1024 / 1024 < 1024) {
                $size = number_format($size / 1024 / 1024 / 1024, $decimals);
                $size .= ' TB';
            }
        }
        // remove unneccessary zeros
        $size = str_replace(".00 ", " ", $size);

        return $size;
    }

    public static function t($key, $defaultContent = '', $replacements = array()) {
        return TranslateHelper::getTranslation($key, $defaultContent, 1, $replacements);
    }

    public static function registerPlugins() {
        // get database connection
        $db = Database::getDatabase();

        // scan plugin directory and make sure they are all listed within the database
        $directories = self::getDirectoryList(PLUGIN_DIRECTORY_ROOT);
        if (count($directories)) {
            foreach ($directories AS $directory) {
                // check the database to see if it already exists
                $found = $db->getValue("SELECT id "
                        . "FROM plugin "
                        . "WHERE folder_name = " . $db->quote($directory));
                if ($found) {
                    continue;
                }

                // not found in the db, we probably need to add it
                $pluginPath = PLUGIN_DIRECTORY_ROOT . $directory . '/';
                $pluginClassFile = $pluginPath . 'Plugin' . UCFirst(strtolower($directory)) . '.class.php';
                $pluginClassName = '\\Plugins\\'.UCFirst(strtolower($directory)).'\\Plugin' . UCFirst(strtolower($directory));

                // make sure we have the main class file
                if (!file_exists($pluginClassFile)) {
                    continue;
                }

                try {
                    // try to create an instance of the class
                    if (!class_exists($pluginClassName)) {
                        continue;
                    }

                    $instance = new $pluginClassName();
                    if (!$instance) {
                        continue;
                    }

                    // get plugin details
                    $pluginDetails = $instance->getPluginDetails();

                    // insert new plugin into db
                    if ($pluginDetails) {
                        $plugin = Plugin::create();
                        $plugin->plugin_name = $pluginDetails['plugin_name'];
                        $plugin->folder_name = $pluginDetails['folder_name'];
                        $plugin->plugin_description = $pluginDetails['plugin_description'];
                        $plugin->is_installed = 0;
                        $plugin->save();
                    }
                }
                catch (Exception $e) {
                    continue;
                }
            }
        }

        return true;
    }

    public static function recursiveDelete($str) {
        // failsafe, make sure it's only in the plugin directory
        if (substr($str, 0, strlen(PLUGIN_DIRECTORY_ROOT)) != PLUGIN_DIRECTORY_ROOT) {
            return false;
        }

        if (is_file($str)) {
            return @unlink($str);
        }
        elseif (is_dir($str)) {
            // look for .htaccess files
            $scan = glob(rtrim($str, '/') . '/.*');
            foreach ($scan as $index => $path) {
                @unlink($path);
            }

            // handle directories
            $scan = glob(rtrim($str, '/') . '/*');
            foreach ($scan as $index => $path) {
                self::recursiveDelete($path);
            }

            return @rmdir($str);
        }
    }

    public static function recursiveThemeDelete($str) {
        // failsafe, make sure it's only in the plugin directory
        if (substr($str, 0, strlen(SITE_THEME_DIRECTORY_ROOT)) != SITE_THEME_DIRECTORY_ROOT) {
            return false;
        }

        if (is_file($str)) {
            return @unlink($str);
        }
        elseif (is_dir($str)) {
            // look for .htaccess files
            $scan = glob(rtrim($str, '/') . '/.*');
            foreach ($scan as $index => $path) {
                @unlink($path);
            }

            // handle directories
            $scan = glob(rtrim($str, '/') . '/*');
            foreach ($scan as $index => $path) {
                self::recursiveThemeDelete($path);
            }

            return @rmdir($str);
        }
    }

    public static function limitStringLength($string, $length = 100) {
        // don't add the ... if the string length is already less than $length
        if (strlen($string) < $length) {
            return $string;
        }

        // safer string limiting
        if (function_exists('mb_substr')) {
            return mb_substr($string, 0, $length, "utf-8") . '...';
        }

        // fallback
        return substr($string, 0, $length) . '...';
    }

    public static function registerThemes() {
        return ThemeHelper::registerThemes();
    }

    public static function phpinfoArray($return = false) {
        ob_start();
        phpinfo(-1);
        $pi = preg_replace(array('#^.*<body>(.*)</body>.*$#ms', '#<h2>PHP License</h2>.*$#ms',
            '#<h1>Configuration</h1>#', "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
            "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
            '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a>'
            . '<h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
            '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
            '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
            "# +#", '#<tr>#', '#</tr>#'), array('$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
            '<h2>PHP Configuration</h2>' . "\n" . '<tr><td>PHP Version</td><td>$2</td></tr>' .
            "\n" . '<tr><td>PHP Egg</td><td>$1</td></tr>',
            '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
            '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
            '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'), ob_get_clean());

        $sections = explode('<h2>', strip_tags($pi, '<h2><th><td>'));
        unset($sections[0]);

        $pi = [];
        foreach ($sections as $section) {
            $n = substr($section, 0, strpos($section, '</h2>'));
            preg_match_all('#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#', $section, $askapache, PREG_SET_ORDER);
            foreach ($askapache as $m)
                $pi[$n][$m[1]] = (!isset($m[3]) || $m[2] == $m[3]) ? $m[2] : array_slice($m, 2);
        }

        return ($return === false) ? ($pi) : $pi;
    }

    public static function output404() {
        CoreHelper::output404();
    }

    public static function output401() {
        CoreHelper::output401();
    }

    public static function convertCamelcaseToHuman($str) {
        return preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $str);
    }
    
    public static function convertCamelcaseToUnderscoreLower($str) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }

    /**
     * Upgrader functions
     */
    public static function installRootWritable() {
        return is_writable(DOC_ROOT);
    }

    public static function getPhpRunningAsUser() {
        $tempFile = tempnam(sys_get_temp_dir(), 'TMP');
        $user = fileowner($tempFile);
        unlink($tempFile);
        if (!$user) {
            return false;
        }

        $userDetails = posix_getpwuid($user);
        if (!$userDetails) {
            return false;
        }

        return $userDetails['name'];
    }

    public static function extractNamespace($file) {
        $ns = false;
        $handle = fopen($file, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, 'namespace') === 0) {
                    $parts = explode(' ', $line);
                    $ns = rtrim(trim($parts[1]), ';');
                    break;
                }
            }
            fclose($handle);
        }
        
        return $ns;
    }
    
    public static function getSupportInfoStr() {
        // pickup request
        $db = Database::getDatabase();
        
        // compile support info
        $plugins = $db->getRows("SELECT * "
                . "FROM plugin "
                . "ORDER BY plugin_name ASC");
        $servers = $db->getRows("SELECT * "
                . "FROM file_server "
                . "ORDER BY id DESC");
        $phparr = self::phpinfoArray();
        $dt = new \DateTime();

        // get all the info from the server
        $content = "Server Information for " . _CONFIG_SITE_PROTOCOL .'://' . _CONFIG_SITE_FULL_URL . ".\n\n";
        $content .= "Operating System: " . php_uname() . "\n";
        $content .= "Current Server Time: " . $dt->format('d-m-Y H:i:s') . "\n";
        $content .= "Web Server: " . $_SERVER['SERVER_SIGNATURE'] ? $_SERVER['SERVER_SIGNATURE'] : $_SERVER['SERVER_SOFTWARE'] . "\n";
        $content .= "Script Domain Name: " . _CONFIG_SITE_HOST_URL . "\n";
        $content .= "Server Hostname: " . $_SERVER['SERVER_SIGNATURE'] ? $_SERVER['SERVER_SIGNATURE'] : $_SERVER['SERVER_SOFTWARE'] . "\n";
        $content .= "Server IP Address: " . $_SERVER['SERVER_ADDR'] . "\n";
        $content .= "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

        // MySQL Information
        $content .= "MySQLi Information.\n\n";
        $content .= "MySQL Client Version: " . $phparr['mysqli']['Client API library version'] . "\n";
        $content .= "MySQL Server Version: " . $db->getValue("SELECT version();") . "\n";
        $content .= "MySQL Server Time: " . $db->getValue('SELECT NOW();') . "\n";
        $content .= "PDO Installed: " . $phparr['PDO']['PDO drivers'] . "\n";
        $content .= "PDO Version: " . $phparr['pdo_mysql']['Client API version'] . "\n\n";

        // PHP information
        $content .= "PHP Information.\n\n";
        $content .= "PHP Version: " . phpversion() . "\n";
        $content .= "Current PHP Time: " . date('Y-m-d H:i:s') . "\n";
        $content .= "php.ini Location: " . php_ini_loaded_file() . "\n";
        $content .= "Max Execution Time: " . $phparr['Core']['max_execution_time'] . "\n";
        $content .= "Max Input Time: " . $phparr['Core']['max_input_time'] . "\n";
        $content .= "Memory Limit: " . $phparr['Core']['memory_limit'] . "\n";
        $content .= "Post Max Size: " . $phparr['Core']['post_max_size'] . "\n";
        $content .= "Upload Max Filesize: " . $phparr['Core']['upload_max_filesize'] . "\n";
        $content .= "cURL Enabled: " . ucfirst($phparr['curl']['cURL support']) . "\n";
        $content .= "cURL Version: " . $phparr['curl']['cURL Information'] . "\n";
        $content .= "Default Timezone: " . $phparr['date']['Default timezone'] . "\n";
        $content .= "GD Enabled: " . ucfirst($phparr['gd']['GD Support']) . "\n";
        $content .= "GD Version: " . $phparr['gd']['GD Version'] . "\n";
        $content .= "OpenSSL Details: " . print_r($phparr['openssl'], true) . "\n";
        $content .= "Default Timezone: " . $phparr['date']['Default timezone'] . "\n";
        $content .= "Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "\n\n";

        // script Information
        $content .= "Script Information\n\n";
        $content .= "Script Version: v" . CoreHelper::getScriptInstalledVersion() . "\n";
        $content .= "Script Identifier: " . CoreHelper::getScriptVersionIdentifier() . "\n";
        $content .= "Site Protocol: " . _CONFIG_SITE_PROTOCOL . "\n\n";
        $content .= "Plugins Installed (" . count($plugins) . ").\n\n";

        if ($plugins) {
            foreach ($plugins AS $plugin) {
                $content .= $plugin['plugin_name'] . "\n";
            }
        }
        else {
            $content .= "No plugins installed.\n\n";
        }

        // file Servers
        if ($servers) {
            $content .= "\n\n";
            $content .= "File Servers (" . count($servers) . ").";
            $content .= "\n\n";

            foreach ($servers AS $server) {
                $content .= 'Server Label: ' . $server['serverLabel'] . "\n";
                $content .= 'Server Type: ' . $server['serverType'] . "\n";
                if ($server['statusId'] == 1) {
                    $content .= "Server Status: Disabled.\n";
                }
                elseif ($server['statusId'] == 2) {
                    $content .= "Server Status: Active.\n";
                }
                elseif ($server['statusId'] == 3) {
                    $content .= "Server Status: Read Only.\n";
                }
                $content .= "Space Used: " . self::formatSize($server['totalSpaceUsed']) . "\n";
                $totalFiles = $db->getValue("SELECT COUNT(file.id) "
                        . "FROM file "
                        . "LEFT JOIN file_artifact fa ON file.id = fa.file_id AND file_artifact_type = 'primary' "
                        . "LEFT JOIN file_artifact_storage fas ON fa.id = fas.file_artifact_id "
                        . "WHERE fas.file_server_id = :file_server_id "
                        . "AND file.status = 'active'", [
                            'file_server_id' => $server['id'],
                ]);
                $content .= "Total Files: " . $totalFiles . "\n";
                $content .= "Storage Path: " . $server['storagePath'] . "\n";
                $content .= "\r";
            }
        }
        
        return $content;
    }
}
