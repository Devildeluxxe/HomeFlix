<?php

class Db extends Base
{
    /**
     * @var \PDO
     */
    private $pdo;
    /**
     * @var \PDOStatement
     */
    private $statement;
    /**
     * @var array
     */
    private $params = [];
    /**
     * @var Db
     */
    private static $_instance;
    /**
     * @var string
     */
    private $queryString;
    /**
     * @var array
     */
    private $result;

    /**
     * @return Db
     */
    public static function getInstance(): Db
    {
        if (null === self::$_instance) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
    }

    /**
     *
     */
    private function __clone()
    {
    }

    /**
     * Db constructor.
     */
    public function __construct()
    {
        parent::__construct();
        global $config;
        $this->pdo = new \PDO('mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'], $config['db_user'], $config['db_pass']);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES utf8;');
    }

    /**
     * @param $table
     */
    public function readLock($table): void
    {
        $this->query("LOCK TABLES $table WRITE;");
    }

    /**
     *
     */
    public function unlock(): void
    {
        $this->query('UNLOCK TABLES;');
    }

    /**
     * @param string $table
     * @param string|array $columns
     * @param array $where
     * @param string $order
     * @param string $group
     * @param string $limit
     * @param string $resultKey
     * @return array
     */
    public function select($table, $columns = '*', $where = null, $order = null, $group = null, $limit = null, $resultKey = null): array
    {
        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }
        $this->params = [];
        if ($where === null) {
            $this->queryString = "SELECT $columns FROM $table";
        } else {
            $whereString = $this->createWhereString($where);
            $this->queryString = "SELECT $columns FROM $table WHERE $whereString ";
            foreach ($where as $field => $value) {
                if (is_array($value)) {
                    $valParams = array_pop($value);
                    $this->params[':' . array_keys($valParams)[0]] = array_values($valParams)[0];
                } else {
                    $this->params[':' . $field] = $value;
                }
            }
        }
        if ($order !== null) {
            $order = filter_var($order, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[a-zA-Z0-9_\ ]*/']]);
            $this->queryString .= " ORDER BY $order ";
        }
        if ($group !== null) {
            $group = filter_var($group, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[a-zA-Z0-9_,\ ]*/']]);
            $this->queryString .= " GROUP BY $group ";
        }
        if ($limit !== null) {
            $limit = filter_var($limit, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[0-9A-Z,\ ]*/']]);
            $this->queryString .= ' LIMIT ' . $limit . ' ';
        }
        $this->statement = $this->prepare($this->queryString);
        $this->statement->execute($this->params);
        $results = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($resultKey)) {
            $results = array_column($results, null, $resultKey);
        }
        if (!empty($results)) {
            return $results;
        }
        return [];
    }

    /**
     * @param string $queryString
     * @return \PDOStatement
     */
    public function prepare($queryString): \PDOStatement
    {
        $this->queryString = $queryString;
        $this->statement = $this->pdo->prepare($this->queryString);
        return $this->statement;
    }

    /**
     * @param array $params
     */
    public function setParams($params): void
    {
        $this->params = $params;
    }

    /**
     * @param string $string
     * @return string
     */
    public function quote($string): string
    {
        return $this->pdo->quote($string);
    }

    /**
     * @param array $params
     * @return bool
     */
    public function execute($params = null): bool
    {
        if ($params !== null) {
            $this->params = $params;
        }
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param array $params
     * @return int
     */
    public function execGetAffectedRows($params = null): int
    {
        if ($params !== null) {
            $this->params = $params;
        }
        $this->statement->execute($this->params);
        return (int)$this->statement->rowCount();
    }

    /**
     * @param array $params
     * @return int
     */
    public function execGetErrorCode($params = null): int
    {
        if ($params !== null) {
            $this->params = $params;
        }
        $this->statement->execute($this->params);
        return $this->pdo->errorCode();
    }

    /**
     * @param array $params
     * @param string $resultKey
     * @return array
     */
    public function execGetResults($params = null, $resultKey = null): array
    {
        if ($params !== null) {
            $this->params = $params;
        }
        global $debug;
        if (!$debug) {
            if (empty($this->queryString)) {
                return [];
            }
            global $config;
            try {
                $this->statement->execute($this->params);
            } catch (\Exception $e) {
                var_dump($e);
            }
        } else {
            $this->statement->execute($this->params);
        }
        if ($this->statement->errorCode() > 0) {
            return [];
        }
        $results = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        if (!$results) {
            return [];
        }
        if ($resultKey !== null) {
            $orderedResults = [];
            foreach ($results as $result) {
                $orderedResults[$result[$resultKey]] = $result;
            }
            return $orderedResults;
        }
        return $results;
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @param string $order
     * @param string $group
     * @return int
     */
    public function count($table, $fieldsValues = null, $order = null, $group = null): int
    {
        $this->params = [];
        if ($fieldsValues === null) {
            $this->queryString = "SELECT COUNT(id) AS num_rows FROM $table";
        } else {
            $whereString = $this->createWhereString($fieldsValues);
            $this->queryString = "SELECT COUNT(id) AS num_rows FROM $table WHERE $whereString ";
            foreach ($fieldsValues as $field => $value) {
                if (is_array($value)) {
                    $this->params[':' . $field] = $value[0];
                } else {
                    $this->params[':' . $field] = $value;
                }
            }
        }
        if ($group !== null) {
            $group = filter_var($order, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[a-zA-Z0-9_]*/']]);
            $this->queryString .= " GROUP BY $group ";
        }
        $this->prepare($this->queryString);
        $this->statement->execute($this->params);
        $results = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        return (int)$results[0]['num_rows'];
    }

    /**
     * @param $table
     * @return int
     */
    public function countAllRows($table): int
    {
        return $this->count($table);
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @return bool
     */
    public function insert($table, $fieldsValues): bool
    {
        $this->params = [];
        $fields = implode(',', array_keys($fieldsValues));
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
        }
        $values = implode(',', array_keys($this->params));
        $this->queryString = "INSERT INTO $table ($fields) VALUES ($values)";
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param $table
     * @param $fieldsValues
     * @return bool
     */
    public function insertOnDuplicateKeyUpdate($table, $fieldsValues): bool
    {
        $this->params = [];
        $fields = implode(',', array_keys($fieldsValues));
        $fvStrings = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $values = implode(',', array_keys($this->params));
        $this->queryString = "INSERT INTO $table ($fields) VALUES ($values) 
                ON DUPLICATE KEY UPDATE " . implode(',', $fvStrings) . ' ; ';
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @return bool
     */
    public function insertIgnore($table, $fieldsValues): bool
    {
        $this->params = [];
        $fields = implode(',', array_keys($fieldsValues));
        $fvStrings = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $values = implode(',', array_keys($this->params));
        $this->queryString = "INSERT IGNORE INTO $table ($fields) VALUES ($values) ";
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @return bool
     */
    public function replace($table, $fieldsValues): bool
    {
        $this->params = [];
        $fields = implode(',', array_keys($fieldsValues));
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
        }
        $values = implode(',', array_keys($this->params));
        $this->queryString = "REPLACE INTO $table ($fields) VALUES ($values)";
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param int id
     * @param array $fieldsValues
     * @return bool
     */
    public function update($table, $id, $fieldsValues): bool
    {
        $fvStrings = [];
        $this->params = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $this->params[':id'] = $id;
        $this->queryString = "UPDATE $table SET " . implode(',', $fvStrings) . ' WHERE id=:id ';
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param int id
     * @param array $fieldsValues
     * @return bool
     */
    public function unlockAndUpdate($table, $id, $fieldsValues): bool
    {
        $fvStrings = [];
        $this->params = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $this->params[':id'] = $id;
        $this->queryString = "UPDATE $table SET " . implode(',', $fvStrings) . ' WHERE id=:id; UNLOCK TABLES;';
        $this->prepare($this->queryString);
        return $this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @param array $where
     * @return bool
     */
    public function updateByProperty($table, $fieldsValues, $where): bool
    {
        $fvStrings = [];
        $this->params = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $whereString = $this->createWhereString($where);
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                $this->params[':' . $field] = $value[0];
            } else {
                $this->params[':' . $field] = $value;
            }
        }
        $this->queryString = "UPDATE $table SET " . implode(',', $fvStrings) . " WHERE $whereString";
        $this->prepare($this->queryString);
        return $this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param array $fieldsValues
     * @param array $where
     * @return bool
     */
    public function updateByPropertyLimitOne($table, $fieldsValues, $where): bool
    {
        $fvStrings = [];
        $this->params = [];
        foreach ($fieldsValues as $field => $value) {
            $this->params[':' . $field] = $value;
            $fvStrings[] = "$field=:$field";
        }
        $whereString = $this->createWhereString($where);
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                $this->params[':' . $field] = $value[0];
            } else {
                $this->params[':' . $field] = $value;
            }
        }
        $this->queryString = "UPDATE $table SET " . implode(',', $fvStrings) . " WHERE $whereString LIMIT 1";
        #$this->queryString = "UPDATE $table SET " . implode(',', $fvStrings) . ' WHERE id=:id; UNLOCK TABLES;';
        $this->prepare($this->queryString);
        return $this->statement->execute($this->params);
    }

    /**
     * @param string $table
     * @param array $where
     * @return bool
     */
    public function delete($table, $where): bool
    {
        $this->params = [];
        $whereString = $this->createWhereString($where);
        $this->queryString = "DELETE FROM $table WHERE $whereString ";
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                $this->params[':' . $field] = $value[0];
            } else {
                $this->params[':' . $field] = $value;
            }
        }
        $this->prepare($this->queryString);
        return (bool)$this->statement->execute($this->params);
    }

    /**
     * @param $queryString
     * @return array|bool
     */
    public function query($queryString = null)
    {
        if ($queryString !== null) {
            $this->queryString = $queryString;
        }
        if ($queryString == '' or $queryString == 'null') {
            return [];
        }
        global $debug;
        if (!$debug) {
            try {
                $this->statement = $this->pdo->query($this->queryString, \PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $monitorSyslogRepository = \Cyberscan\Repository\MonitorSyslog::getInstance();
                global $config;
                $monitorSyslogRepository->add([
                    'host' => $config['db_name'],
                    'instance' => 'SQL',
                    'message' => $e->getMessage(),
                    'severity' => 200,
                    'details' => $this->queryString
                ]);
            }
        } else {
            $this->statement = $this->pdo->query($this->queryString, \PDO::FETCH_ASSOC);
        }
        if (stripos($this->queryString, 'select') === false) {
            return true;
        }
        $this->result = $this->execGetResults();
        if (!$this->result) {
            return [];
        }
        return $this->result;
    }

    /**
     * @return array
     */
    public function getResults(): array
    {
        if (!$this->result) {
            return [];
        }
        return $this->result;
    }

    /**
     * @param string $table
     * @param string $field
     * @param array $where
     * @return int
     */
    public function sum($table, $field, $where): int
    {
        $this->params = [];
        $whereString = $this->createWhereString($where);
        $this->queryString = "SELECT SUM($field) AS field_sum FROM $table WHERE $whereString ";
        foreach ($where as $fieldName => $value) {
            if (is_array($value)) {
                $this->params[':' . $fieldName] = $value[0];
            } else {
                $this->params[':' . $fieldName] = $value;
            }
        }
        $this->prepare($this->queryString);
        $this->execute($this->params);
        $results = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        if (!$results) {
            return 0;
        }
        return (int)$results[0]['field_sum'];
    }

    /**
     * @param $table
     * @param $dbfield
     * @param $where
     * @return string
     */
    public function get($table, $dbfield, $where): string
    {
        $this->params = [];
        $whereString = $this->createWhereString($where);
        $this->queryString = "SELECT $dbfield FROM $table WHERE $whereString ";
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                $this->params[':' . $field] = $value[0];
            } else {
                $this->params[':' . $field] = $value;
            }
        }
        $this->prepare($this->queryString);
        $this->execute();
        $results = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        return $results[0][$dbfield] ?? '';
    }

    /**
     * @param array $where
     * @return string
     */
    private function createWhereString($where): string
    {
        $whereString = implode(' AND ', array_map(
            function ($v, $k) {
                if (is_array($v)) {
                    $operator = filter_var(array_keys($v)[0], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[<>=!]*/']]);
                    $whereInner = array_pop($v);
                    $field = filter_var(array_keys($whereInner)[0], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[a-zA-Z0-9_]*/']]);
                    return sprintf(' %s %s :%s ', $field, $operator, $field);
                }
                $field = filter_var($k, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/[a-zA-Z0-9_]*/']]);
                return sprintf(' %s = :%s ', $field, $field);
            }, $where,
            array_keys($where)
        ));
        return $whereString . '';
    }

    /**
     * @param string $scheme
     * @return array
     */
    public function getSchemeTables($scheme = ''): array
    {
        $query = "SELECT * FROM `INFORMATION_SCHEMA`.`TABLES` WHERE TABLE_SCHEMA = '" . $scheme . "' ORDER BY TABLE_NAME ASC";
        $res = $this->query($query);
        if ($res) {
            return $res;
        }
        return [];
    }

    /**
     * @param string $dbName
     * @param string $tableName
     * @return int
     */
    public function getColumnCountByDbAndTable($dbName, $tableName)
    {
        $query = "SELECT COUNT(COLUMN_NAME) AS columnCount FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $dbName . "' AND TABLE_NAME = '" . $tableName . "'";
        $res = $this->query($query);
        if ($res) {
            return (int)$res[0]['columnCount'];
            //return $res;
        } else {
            return -1;
        }
    }
}

