<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\PluginHelper;

abstract class Plugin
{

    abstract function getPluginDetails();

    public function getPluginSettings() {
        // get database
        $db = Database::getDatabase();

        // get plugin details
        $pluginDetails = $this->getPluginDetails();

        // load config into the object
        $this->data = $db->getRow('SELECT * '
                . 'FROM plugin '
                . 'WHERE folder_name = :folder_name '
                . 'LIMIT 1', array(
            'folder_name' => $pluginDetails['folder_name'],
        ));
        if ($this->data) {
            return json_decode($this->data['plugin_settings'], true);
        }
        
        return false;
    }

    public function hasAdminSettings() {
        // get plugin details
        $pluginDetails = $this->getPluginDetails();
        $settingsPath = PLUGIN_DIRECTORY_ROOT . $pluginDetails['folder_name'] . '/admin/settings.php';

        return file_exists($settingsPath);
    }

    public function install() {
        // get plugin details
        $pluginDetails = $this->getPluginDetails();

        // import database
        if (isset($pluginDetails['database_sql'])) {
            $sqlPath = PLUGIN_DIRECTORY_ROOT . $pluginDetails['folder_name'] . '/' . $pluginDetails['database_sql'];
            $this->importSqlFile($sqlPath);
        }

        // update reference in database
        $db = Database::getDatabase();
        $db->query('UPDATE plugin '
                . 'SET is_installed = 1 '
                . 'WHERE folder_name = :folder_name', array('folder_name' => $pluginDetails['folder_name']));

        // update plugin config in the site_config
        PluginHelper::loadPluginConfigurationFiles(true);

        return true;
    }

    public function uninstall() {
        // get plugin details
        $pluginDetails = $this->getPluginDetails();

        // update reference in database
        $db = Database::getDatabase();
        $db->query('UPDATE plugin '
                . 'SET is_installed = 0 '
                . 'WHERE folder_name = :folder_name', array('folder_name' => $pluginDetails['folder_name']));

        // update plugin config in the session
        PluginHelper::loadPluginConfigurationFiles(true);

        return true;
    }

    public function importSql($sQL = '') {
        if (!strlen($sQL)) {
            return true;
        }

        // get each sql statement in an array
        $sQLLines = $this->splitSqlFile($sQL, ';');
        if (count($sQLLines)) {
            // setup database
            $db = Database::getDatabase();

            // loop sql statements and execute
            foreach ($sQLLines AS $sQLLine) {
                $db->query($sQLLine);
            }
        }

        return true;
    }

    public function importSqlFile($sQLFile) {
        if (!file_exists($sQLFile)) {
            return false;
        }

        // get each sql statement in an array
        $sQL = file_get_contents($sQLFile);

        return $this->importSql($sQL);
    }

    public function splitSqlFile($sql, $delimiter) {
        // Split up our string into "possible" SQL statements.
        $tokens = explode($delimiter, $sql);

        // try to save mem.
        $sql = "";
        $output = [];

        // we don't actually care about the matches preg gives us.
        $matches = [];

        // this is faster than calling count($oktens) every time thru the loop.
        $token_count = count($tokens);
        for ($i = 0; $i < $token_count; $i++) {
            // Don't wanna add an empty string as the last thing in the array.
            if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0))) {
                // This is the total number of single quotes in the token.
                $total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
                // Counts single quotes that are preceded by an odd number of backslashes,
                // which means they're escaped quotes.
                $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

                $unescaped_quotes = $total_quotes - $escaped_quotes;

                // If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
                if (($unescaped_quotes % 2) == 0) {
                    // It's a complete sql statement.
                    $output[] = $tokens[$i];
                    // save memory.
                    $tokens[$i] = "";
                }
                else {
                    // incomplete sql statement. keep adding tokens until we have a complete one.
                    // $temp will hold what we have so far.
                    $temp = $tokens[$i] . $delimiter;
                    // save memory..
                    $tokens[$i] = "";

                    // Do we have a complete statement yet?
                    $complete_stmt = false;

                    for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
                        // This is the total number of single quotes in the token.
                        $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
                        // Counts single quotes that are preceded by an odd number of backslashes,
                        // which means they're escaped quotes.
                        $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

                        $unescaped_quotes = $total_quotes - $escaped_quotes;

                        if (($unescaped_quotes % 2) == 1) {
                            // odd number of unescaped quotes. In combination with the previous incomplete
                            // statement(s), we now have a complete statement. (2 odds always make an even)
                            $output[] = $temp . $tokens[$j];

                            // save memory.
                            $tokens[$j] = "";
                            $temp = "";

                            // exit the loop.
                            $complete_stmt = true;
                            // make sure the outer loop continues at the right point.
                            $i = $j;
                        }
                        else {
                            // even number of unescaped quotes. We still don't have a complete statement.
                            // (1 odd and 1 even always make an odd)
                            $temp .= $tokens[$j] . $delimiter;
                            // save memory.
                            $tokens[$j] = "";
                        }
                    }
                }
            }
        }

        return $output;
    }

    public function registerRoutes(\FastRoute\RouteCollector $r) {
        // for an example see the global RouteHelper
    }

    /**
     * standard name for hooks controller is HooksController
     * exists in here so it can be overridden at the plugin level if needed
     */
    public function getHooksControllerName() {
        return 'HooksController';
    }

}
