<?php

/**
 * Exception helper for the Database class
 */
class DatabaseException extends Exception
{
    // Default Exception class handles everything
}

/**
 * A basic database interface using MySQLi
 */
class Database
{

    /**
     * @var strting Last executed SQL query
     */
    private $sql;

    /**
     * @var false|mysqli Database connection
     */
    private $mysql;

    /**
     * @var mixed Last result
     */
    private $result;

    /**
     * @var mixed[] Result rows
     */
    private $result_rows;

    /**
     * @var string Database name
     */
    public $database_name;

    /**
     * @var Database Singleton helper
     */
    private static $instance;

    /**
     * Query history
     *
     * @var array
     */
    static $queries = [];

    /**
     * Database() constructor
     *
     * @param string $database_name
     * @param string $username
     * @param string $password
     * @param string $host
     * @param mysqli $mock
     * @throws DatabaseException
     */
    function __construct($database_name, $username, $password, $host = 'localhost', $mock = null)
    {
        self::$instance = $this;

        $this->database_name = $database_name;
        $this->mysql = $mock ?: new mysqli($host, $username, $password, $database_name);

        if(!$this->mysql || $this->mysql->connect_errno) {
            throw new DatabaseException('Database connection error: ' . $this->mysql->connect_error);
        }

        $this->mysql->set_charset('utf8');
    }

    /**
     * Get instance
     *
     * @param string $database_name
     * @param string $username
     * @param string $password
     * @param string $host
     * @param null $mock
     * @return Database
     * @throws DatabaseException
     */
    final public static function instance($database_name = null, $username = null, $password = null, $host = 'localhost', $mock = null)
    {
        if(!isset(self::$instance)) {
            self::$instance = new Database($database_name, $username, $password, $host, $mock);
        }

        return self::$instance;
    }

    /**
     * Helper for throwing exceptions
     *
     * @param $error
     * @throws DatabaseException
     */
    private function _error($error)
    {
        throw new DatabaseException('Database error: ' . $error);
    }

    /**
     * Turn an array into a where statement
     *
     * @param mixed $where
     * @param string $where_mode
     * @return string
     * @throws DatabaseException
     */
    public function process_where($where, $where_mode = 'AND')
    {
        $query = '';
        if(is_array($where)) {
            $num = 0;
            $where_count = count($where);
            foreach($where as $key => $value) {
                if(is_array($value)) {
                    $array_keys = array_keys($value);
                    if(reset($array_keys) != 0) {
                        throw new DatabaseException('Can not handle associative arrays');
                    }
                    $query .= " `" . $key . "` IN (" . $this->join_array($value) . ")";
                } elseif(!is_integer($key)) {
                    $query .= ' `' . $key . "`='" . $this->escape($value) . "'";
                } else {
                    $query .= ' ' . $value;
                }
                $num++;
                if($num != $where_count) {
                    $query .= ' ' . $where_mode;
                }
            }
        } else {
            $query .= ' ' . $where;
        }

        return $query;
    }

    /**
     * Perform a SELECT operation
     *
     * @param string $table
     * @param array $where
     * @param bool $limit
     * @param bool $order
     * @param string $where_mode
     * @param string $select_fields
     * @return Database
     * @throws DatabaseException
     */
    public function select($table, $where = [], $limit = false, $order = false, $where_mode = "AND", $select_fields = '*')
    {
        $this->result = null;
        $this->sql = null;

        if(is_array($select_fields)) {
            $fields = '';
            foreach($select_fields as $field) {
                $fields .= '`' . $field . '`, ';
            }
            $select_fields = rtrim($fields, ', ');
        }

        $query = 'SELECT ' . $select_fields . ' FROM `' . $table . '`';
        if(!empty($where)) {
            $query .= ' WHERE' . $this->process_where($where, $where_mode);
        }
        if($order) {
            $query .= ' ORDER BY ' . $order;
        }
        if($limit) {
            $query .= ' LIMIT ' . $limit;
        }

        return $this->query($query);
    }

    /**
     * Perform a query
     *
     * @param string $query
     * @return self
     * @throws DatabaseException
     */
    public function query($query)
    {
        self::$queries[] = $query;
        $this->sql = $query;

        $this->result_rows = null;
        $this->result = $this->mysql->query($query);

        if($this->mysql->error != '') {
            $this->_error($this->mysql->error);
            $this->result = null;

            return $this;
        }

        return $this;
    }

    /**
     * Get last executed query
     *
     * @return string|null
     */
    public function sql()
    {
        return $this->sql;
    }

    /**
     * Get an array of objects with the query result
     *
     * @param string|null $key_field
     * @return array
     */
    public function result($key_field = null)
    {
        if(!$this->result_rows) {
            $this->result_rows = [];
            while($row = mysqli_fetch_assoc($this->result)) {
                $this->result_rows[] = $row;
            }
        }

        $result = [];
        $index = 0;

        foreach($this->result_rows as $row) {
            $key = $index;
            if(!empty($key_field) && isset($row[$key_field])) {
                $key = $row[$key_field];
            }
            $result[$key] = new stdClass();
            foreach($row as $column => $value) {
                $this->is_serialized($value, $value);
                $result[$key]->{$column} = $this->clean($value);
            }
            $index++;
        }

        return $result;
    }

    /**
     * Get an array of arrays with the query result
     *
     * @return array
     */
    public function result_array()
    {
        if(!$this->result_rows) {
            $this->result_rows = [];
            while($row = mysqli_fetch_assoc($this->result)) {
                $this->result_rows[] = $row;
            }
        }
        $result = [];
        $index = 0;
        foreach($this->result_rows as $row) {
            $result[$index] = [];
            foreach($row as $key => $value) {
                $this->is_serialized($value, $value);
                $result[$index][$key] = $this->clean($value);
            }
            $index++;
        }

        return $result;
    }

    /**
     * Get a specific row from the result as an object
     *
     * @param int $index
     * @return stdClass
     */
    public function row($index = 0)
    {
        if(!$this->result_rows) {
            $this->result_rows = [];
            while($row = mysqli_fetch_assoc($this->result)) {
                $this->result_rows[] = $row;
            }
        }

        $num = 0;
        foreach($this->result_rows as $column) {
            if($num == $index) {
                $row = new stdClass();
                foreach($column as $key => $value) {
                    $this->is_serialized($value, $value);
                    $row->{$key} = $this->clean($value);
                }

                return $row;
            }
            $num++;
        }

        return new stdClass();
    }

    /**
     * Get a specific row from the result as an array
     *
     * @param int $index
     * @return array
     */
    public function row_array($index = 0)
    {
        if(!$this->result_rows) {
            $this->result_rows = [];
            while($row = mysqli_fetch_assoc($this->result)) {
                $this->result_rows[] = $row;
            }
        }

        $num = 0;
        foreach($this->result_rows as $column) {
            if($num == $index) {
                $row = [];
                foreach($column as $key => $value) {
                    $this->is_serialized($value, $value);
                    $row[$key] = $this->clean($value);
                }

                return $row;
            }
            $num++;
        }

        return [];
    }

    /**
     * Get the number of result rows
     *
     * @return bool|int
     */
    public function count()
    {
        if($this->result) {
            return mysqli_num_rows($this->result);
        } elseif(isset($this->result_rows)) {
            return count($this->result_rows);
        } else {
            return false;
        }
    }

    /**
     * Execute a SELECT COUNT(*) query on a table
     *
     * @param null $table
     * @param array $where
     * @param bool $limit
     * @param bool $order
     * @param string $where_mode
     * @return mixed
     * @throws DatabaseException
     */
    public function num($table = null, $where = [], $limit = false, $order = false, $where_mode = "AND")
    {
        if(!empty($table)) {
            $this->select($table, $where, $limit, $order, $where_mode, 'COUNT(*)');
        }

        $res = $this->row();

        return $res->{'COUNT(*)'};
    }

    /**
     * Check if a table with a specific name exists
     *
     * @param $name
     * @return bool
     */
    function table_exists($name)
    {
        $res = $this->mysql->query("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '" . $this->escape($this->database_name) . "' AND table_name = '" . $this->escape($name) . "'");

        return ($this->mysqli_result($res, 0) == 1);
    }

    /**
     * Helper function for process_where
     *
     * @param $array
     * @return string
     */
    private function join_array($array)
    {
        $number = 0;
        $query = '';
        foreach($array as $key => $value) {
            if(is_object($value) || is_array($value) || is_bool($value)) {
                $value = serialize($value);
            }
            if($value === null) {
                $query .= ' NULL';
            } else {
                $query .= ' \'' . $this->escape($value) . '\'';
            }
            $number++;
            if($number != count($array)) {
                $query .= ',';
            }
        }

        return trim($query);
    }

    /* Insert/update functions */

    /**
     * Insert a row in a table
     *
     * @param $table
     * @param array $fields
     * @param bool|false $appendix
     * @param bool|false $ret
     * @return bool|Database
     * @throws Exception
     */
    function insert($table, $fields = [], $appendix = false, $ret = false)
    {
        $this->result = null;
        $this->sql = null;

        $query = 'INSERT INTO';
        $query .= ' `' . $this->escape($table) . "`";

        if(is_array($fields)) {
            $query .= ' (';
            $num = 0;
            foreach($fields as $key => $value) {
                $query .= ' `' . $key . '`';
                $num++;
                if($num != count($fields)) {
                    $query .= ',';
                }
            }
            $query .= ' ) VALUES ( ' . $this->join_array($fields) . ' )';
        } else {
            $query .= ' ' . $fields;
        }
        if($appendix) {
            $query .= ' ' . $appendix;
        }
        if($ret) {
            return $query;
        }
        $this->sql = $query;
        $this->result = mysqli_query($this->mysql, $query);
        if(mysqli_error($this->mysql) != '') {
            $this->_error(mysqli_error($this->mysql));
            $this->result = null;

            return false;
        } else {
            return $this;
        }
    }

    /**
     * Execute an UPDATE statement
     *
     * @param $table
     * @param array $fields
     * @param array $where
     * @param bool $limit
     * @param bool $order
     * @return $this|bool
     * @throws DatabaseException
     */
    function update($table, $fields = [], $where = [], $limit = false, $order = false)
    {
        if(empty($where)) {
            throw new DatabaseException('Where clause is empty for update method');
        }

        $this->result = null;
        $this->sql = null;
        $query = 'UPDATE `' . $table . '` SET';
        if(is_array($fields)) {
            $number = 0;
            foreach($fields as $key => $value) {
                if(is_object($value) || is_array($value) || is_bool($value)) {
                    $value = serialize($value);
                }
                if($value === null) {
                    $query .= ' `' . $key . "`=NULL";
                } else {
                    $query .= ' `' . $key . "`='" . $this->escape($value) . "'";
                }
                $number++;
                if($number != count($fields)) {
                    $query .= ',';
                }
            }
        } else {
            $query .= ' ' . $fields;
        }
        if(!empty($where)) {
            $query .= ' WHERE' . $this->process_where($where);
        }
        if($order) {
            $query .= ' ORDER BY ' . $order;
        }
        if($limit) {
            $query .= ' LIMIT ' . $limit;
        }
        $this->sql = $query;
        $this->result = mysqli_query($this->mysql, $query);
        if(mysqli_error($this->mysql) != '') {
            $this->_error(mysqli_error($this->mysql));
            $this->result = null;

            return false;
        } else {
            return $this;
        }
    }

    /**
     * Execute a DELETE statement
     *
     * @param $table
     * @param array $where
     * @param string $where_mode
     * @param bool $limit
     * @param bool $order
     * @return $this|bool
     * @throws DatabaseException
     * @throws Exception
     */
    function delete($table, $where = [], $where_mode = "AND", $limit = false, $order = false)
    {
        if(empty($where)) {
            throw new DatabaseException('Where clause is empty for update method');
        }

        // Notice: different syntax to keep backwards compatibility
        $this->result = null;
        $this->sql = null;
        $query = 'DELETE FROM `' . $table . '`';
        if(!empty($where)) {
            $query .= ' WHERE' . $this->process_where($where, $where_mode);
        }
        if($order) {
            $query .= ' ORDER BY ' . $order;
        }
        if($limit) {
            $query .= ' LIMIT ' . $limit;
        }
        $this->sql = $query;

        $this->result = mysqli_query($this->mysql, $query);
        if(mysqli_error($this->mysql) != '') {
            $this->_error(mysqli_error($this->mysql));
            $this->result = null;

            return false;
        } else {
            return $this;
        }
    }

    /**
     * Get the primary key of the last inserted row
     *
     * @return int|string
     */
    public function id()
    {
        return mysqli_insert_id($this->mysql);
    }

    /**
     * Get the number of rows affected by your last query
     *
     * @return int
     */
    public function affected()
    {
        return mysqli_affected_rows($this->mysql);
    }

    /**
     * Escape a parameter
     *
     * @param $str
     * @return string
     */
    public function escape($str)
    {
        return $this->mysql->real_escape_string($str);
    }

    /**
     * Get the last error message
     *
     * @return string
     */
    public function error()
    {
        return $this->mysql->error;
    }

    /**
     * Fix UTF-8 encoding problems
     *
     * @param $str
     * @return string
     */
    private function clean($str)
    {
        if(is_string($str)) {
            if(!mb_detect_encoding($str, 'UTF-8', true)) {
                $str = utf8_encode($str);
            }
        }

        return $str;
    }

    /**
     * Check if a variable is serialized
     *
     * @param mixed $data
     * @param null $result
     * @return bool
     */
    public function is_serialized($data, &$result = null)
    {
        if(!is_string($data)) {
            return false;
        }

        $data = trim($data);

        if(empty($data)) {
            return false;
        }
        if($data === 'b:0;') {
            $result = false;

            return true;
        }
        if($data === 'b:1;') {
            $result = true;

            return true;
        }
        if($data === 'N;') {
            $result = null;

            return true;
        }
        if(strlen($data) < 4) {
            return false;
        }
        if($data[1] !== ':') {
            return false;
        }
        $lastc = substr($data, -1);
        if(';' !== $lastc && '}' !== $lastc) {
            return false;
        }

        $token = $data[0];
        switch($token) {
            case 's' :
                if('"' !== substr($data, -2, 1)) {
                    return false;
                }
                break;
            case 'a' :
            case 'O' :
                if(!preg_match("/^{$token}:[0-9]+:/s", $data)) {
                    return false;
                }
                break;
            case 'b' :
            case 'i' :
            case 'd' :
                if(!preg_match("/^{$token}:[0-9.E-]+;/", $data)) {
                    return false;
                }
        }

        try {
            if(($res = @unserialize($data)) !== false) {
                $result = $res;

                return true;
            }
            if(($res = @unserialize(utf8_encode($data))) !== false) {
                $result = $res;

                return true;
            }
        } catch(Exception $exception) {
            return false;
        }

        return false;
    }

    /**
     * MySQL compatibility method mysqli_result
     * http://www.php.net/manual/en/class.mysqli-result.php#109782
     *
     * @param mysqli_result $res
     * @param int $row
     * @param int $field
     * @return mixed
     */
    private function mysqli_result($res, $row, $field = 0)
    {
        $res->data_seek($row);
        $datarow = $res->fetch_array();

        return $datarow[$field];
    }

}
