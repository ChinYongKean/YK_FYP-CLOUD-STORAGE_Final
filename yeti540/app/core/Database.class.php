<?php

namespace App\Core;

use \PDO;

class Database
{

    // singleton object. Leave $me alone.
    private static $me;
    public static $queries = [];
    public $db = null;
    public $host;
    public $name;
    public $username;
    public $password;
    public $debug;
    public $result = null;
    public $redirect = false;
    public $notifyType;
    private $reconnectCount = 0;

    const NOTIFY_TYPE_ONSCREEN = 1;
    const NOTIFY_TYPE_ARRAY = 2;

    // singleton constructor
    private function __construct(
        $connect = false,
        $host = null,
        $name = null,
        $username = null,
        $password = null,
        $debug = null
    ) {
        $this->host = is_null($host) ? _CONFIG_DB_HOST : $host;
        $this->name = is_null($name) ? _CONFIG_DB_NAME : $name;
        $this->username = is_null($username) ? _CONFIG_DB_USER : $username;
        $this->password = is_null($password) ? _CONFIG_DB_PASS : $password;
        $this->debug = is_null($debug) ? _CONFIG_DEBUG : $debug;
        $this->notifyType = self::NOTIFY_TYPE_ONSCREEN;
        if ($connect === true) {
            $this->connect();
        }
    }

    // Get Singleton object
    public static function getDatabase(
        $connect = true,
        $forceReconnect = false,
        $host = null,
        $name = null,
        $username = null,
        $password = null,
        $debug = null
    )
    {
        if (is_null(self::$me) || $forceReconnect === true) {
            self::$me = new Database($connect, $host, $name, $username, $password, $debug);
        }

        return self::$me;
    }

    public function setNotifyType(int $notifyType) {
        $this->notifyType = $notifyType;
    }

    // Do we have a valid database connection?
    public function isConnected()
    {
        return is_object($this->db);
    }

    public function connect()
    {
        // check if we already have a connection
        if ($this->db !== null && $this->isConnected()) {
            return true;
        }

        try {
            // attempt to connect to the database
            $this->db = new PDO(
                "mysql:host=".$this->host.";dbname=".$this->name.";charset=utf8",
                $this->username,
                $this->password,
                [
                    // ensure everything in the database is UTF8 and disable strict mode
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8, SESSION sql_mode=""',
                ]
            );

            // catch errors if debug is enabled
            if ($this->debug === true) {
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (\Exception $e) {
            // check for the MySQL PDO driver
            if (!$this->hasPDODriver()) {
                return $this->notify('PDO driver unavailable. Please contact your host to request '
                    .'the MySQL PDO driver to be enabled within PHP.');
            }

            // otherwise, show generic connection error
            return $this->notify('Failed connecting to the database with the supplied connection '
                .'details. Please check the details are correct and your MySQL user '
                .'has permissions to access this database.');
        }

        return $this->isConnected();
    }

    public function reconnect()
    {
        $this->close();
        $this->reconnectCount++;

        return $this->connect();
    }

    public function close()
    {
        $this->db = null;
        $this->result = null;
        self::closeDB();
    }

    public static function closeDB()
    {
        if (!is_null(self::$me)) {
            self::$me->db = null;
            self::$me = null;
        }
    }

    public function query($sQL, $args = null)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        // allow for prepared arguments. Example:
        $sth = $this->db->prepare($sQL);

        // track the query information if debug is enabled
        if ($this->debug === true) {
            $debugSql = $sQL;
        }

        // prepare the params
        $params = [];
        if (is_array($args)) {
            foreach ($args as $name => $val) {
                $params[':'.$name] = $val;

                // track the query information if debug is enabled
                if ($this->debug === true) {
                    $replacement = "'".$val."'";
                    if (is_int($val)) {
                        $replacement = $val;
                    } elseif ($val === null) {
                        $replacement = 'null';
                    }

                    $debugSql = preg_replace('/:\b'.$name.'\b/u', $replacement, $debugSql);
                }
            }
        }

        // track the query information if debug is enabled
        if ($this->debug === true) {
            $start = microtime();
            $startEx = explode(' ', $start);
            $start = $startEx[1] + $startEx[0];

            // track query
            $nextIndex = $this->numQueries();
            self::$queries[$nextIndex] = array(
                'sql' => $debugSql,
                'start' => $start,
            );
        }

        try {
            // execute the SQL statement
            $sth->execute($params);
        } catch (\PDOException $e) {
            if ($e->getCode() != 'HY000' || !stristr($e->getMessage(),
                    'server has gone away') || $this->reconnectCount >= 3) {
                return $this->notify($e);
            }

            // if we have "PDOException: SQLSTATE[HY000]: General error: 2006 MySQL 
            // server has gone away", try to reconnect and re-run query
            $this->reconnect();

            return $this->query($sQL, $args);
        }

        // track the query information if debug is enabled
        if ($this->debug === true) {
            $end = microtime();
            $endEx = explode(' ', $end);
            $end = $endEx[1] + $endEx[0];

            $total = number_format($end - $start, 6);
            self::$queries[$nextIndex]['end'] = $end;
            self::$queries[$nextIndex]['total'] = $total;
        }

        $this->result = $sth;

        return $this->result;
    }

    // Returns the number of rows.
    // You can pass in nothing, a string, or a db result
    public function numRows($arg = null)
    {
        $result = $this->resulter($arg);

        return ($result !== false) ? $result->rowCount() : false;
    }

    // Returns true / false if the result has one or more rows
    public function hasRows($arg = null)
    {
        $result = $this->resulter($arg);

        return is_object($result) && ($result->rowCount() > 0);
    }

    // Returns the number of rows affected by the previous operation
    public function affectedRows()
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->result->rowCount();
    }

    // Returns the auto increment ID generated by the previous insert statement
    public function insertId()
    {
        if (!$this->isConnected()) {
            return false;
        }

        $id = $this->db->lastInsertId();
        if ($id === 0 || $id === false) {
            return false;
        }

        return $id;
    }

    // Returns a single value.
    // You can pass in nothing, a string, or a db result
    public function getValue($arg = null, $args_to_prepare = [])
    {
        $result = $this->resulter($arg, $args_to_prepare);
        $data = false;
        if ($result) {
            $row = $result->fetch(PDO::FETCH_NUM);
            if (is_array($row) && array_key_exists(0, $row)) {
                $data = $row[0];
            }
        }

        return $data;
    }

    // Returns the first row.
    // You can pass in nothing, a string, or a db result
    public function getRow($arg = null, $args_to_prepare = [], $fetchType = PDO::FETCH_ASSOC)
    {
        $result = $this->resulter($arg, $args_to_prepare);
        $data = $result->fetch($fetchType);

        return $result->rowCount() ? $data : false;
    }

    // Returns an array of all the rows.
    // You can pass in nothing, a string, or a db result
    public function getRows($arg = null, $args_to_prepare = [], $fetchType = PDO::FETCH_ASSOC)
    {
        // execute the query
        $result = $this->resulter($arg, $args_to_prepare);
        $data = $result->fetchAll($fetchType);

        return $result->rowCount() ? $data : [];
    }

    // escapes a value and wraps it in single quotes
    public function quote($var)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->db->quote($var);
    }

    // escapes a value
    public function escape($var)
    {
        $str = $this->quote($var);
        if (strlen($str) > 2) {
            $str = substr($str, 1, strlen($str) - 2);
        }

        return $str;
    }

    public function numQueries()
    {
        return count(self::$queries);
    }

    public function lastQuery()
    {
        if ($this->numQueries() > 0) {
            return self::$queries[$this->numQueries() - 1];
        }

        return false;
    }

    private function notify($errMsg = null)
    {
        if ($errMsg === null) {
            $errors = $this->db->errorInfo();
            $errMsg = implode('. ', $errors);
        }

        if ($this->debug === true) {
            // send error report onscreen
            if($this->notifyType === Database::NOTIFY_TYPE_ONSCREEN) {
                echo '<p style="border:5px solid red;background-color:#fff;padding:12px;font-family: verdana, sans-serif;"><strong>Database Error:</strong><br/>'.$errMsg.'</p>';
                $lastQuery = $this->lastQuery();
                if ($lastQuery !== false) {
                    echo '<p style="border:5px solid red;background-color:#fff;padding:12px;font-family: verdana, sans-serif;"><strong>Last Rendered Query:</strong><br/>'.$lastQuery['sql'].'</p>';
                }

                echo '<pre>';
                debug_print_backtrace();
                echo '</pre>';
                exit;
            }

            // return as an array, this is generally used in the installer
            return [
                'error' => $errMsg,
            ];
        }

        if (is_string($this->redirect)) {
            header('Location: '.$this->redirect);
            exit;
        }
    }

    // Takes nothing, a MySQL result, or a query string and returns
    // the corresponding MySQL result resource or false if none available.
    private function resulter($arg = null, $args_to_prepare = [])
    {
        if (is_null($arg) && is_object($this->result)) {
            return $this->result;
        } elseif (is_object($arg)) {
            return $arg;
        } elseif (is_string($arg)) {
            $this->query($arg, $args_to_prepare);
            if (is_object($this->result)) {
                return $this->result;
            }

            return false;
        }

        return false;
    }

    private function hasPDODriver()
    {
        // check for PDO driver
        return class_exists('PDO');
    }

}
