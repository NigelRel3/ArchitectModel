<?php
namespace ArchitectModel;

class MySQL
{

    /**
     * Database connection
     *
     * @var \PDO
     */
    protected $db = null;

    protected $data = null;

    protected $active = false;

    protected static $fields = [];

    protected static $jsonArrayMode = true;

    protected static $pk = [];

    protected static $autoIndexField = null;

    protected static $table = null;

    protected static $insertSQL = [];

    protected static $selectSQL = [];

    protected static $sqlWhere = [];

    protected static $updateSQL = [];

    protected static $deleteSQL = [];

    protected static $columnNames = [];

    protected static $pkFlip = [];

    public function __construct(\PDO $db)
    {
        $this->active = false;
        if (static::$table === null) {
            $class = static::class;
            static::$table = substr($class, strrpos($class, "\\") + 1);
        }
        $this->db = $db;
        $this->data = array_fill_keys(static::$fields, null);
    }

    protected static function getTableSet(array &$set, callable $genData)
    {
        if (isset($set[static::class])) {
            $data = $set[static::class];
        } else {
            $data = $genData();
            $set[static::class] = $data;
        }
        return $data;
    }

    public function getTableName(): string
    {
        return static::$table;
    }

    public function getFields(): array
    {
        return static::$fields;
    }

    public function getColumnNames(): array
    {
        return self::getTableSet(self::$columnNames, function () {
            $columns = [];
            $table = static::$table;
            foreach (static::$fields as $field) {
                $columns[] = "`{$table}`.`{$field}`";
            }
            return $columns;
        });
    }

    public function validate()
    {
        return [];
    }

    protected static function getInsertSQL(): string
    {
        return self::getTableSet(self::$insertSQL, [
            get_called_class(),
            'genInsertSQL'
        ]);
    }

    protected static function genInsertSQL(array $fields = null): string
    {
        $fields = $fields ?? static::$fields;
        $sql = "INSERT INTO `" . static::$table . "`(`" . implode("`, `", $fields) . "`)" . " VALUES (:" . implode(", :", $fields) . ")
			ON DUPLICATE KEY UPDATE ";
        foreach ($fields as $field) {
            $sql .= "`{$field}` = :{$field}, ";
        }
        return substr($sql, 0, - 2);
    }

    protected $prepInsert = null;

    // Function to format data for database
    protected function formatToDB(): array
    {
        return $this->data;
    }

    public function insert(): bool
    {
        $this->active = false;

        if ($this->prepInsert === null) {
            $sql = static::getInsertSQL();
            $this->prepInsert = $this->db->prepare($sql);
        }
        $insert = $this->prepInsert;
        $insert->execute($this->formatToDB());
        if (static::$autoIndexField != null) {
            $this->data[static::$autoIndexField] = intval($this->db->lastInsertId());
        }
        $this->active = true;

        return $this->active;
    }

    public function insertBlock(array $fields, array $data): void
    {
        if ($this->prepInsert === null) {
            $sql = static::getInsertSQL();
            $this->prepInsert = $this->db->prepare($sql);
        }
        $this->db->beginTransaction();
        foreach ($data as $row) {
            $this->data = array_combine($fields, $row);
            $this->prepInsert->execute($this->formatToDB());
        }
        $this->db->commit();
    }

    public function getPK(): array
    {
        return array_intersect_key($this->data, $this->getPKFlip());
    }

    protected function getPKFlip(): array
    {
        return self::getTableSet(static::$pkFlip, function () {
            return array_flip(static::$pk);
        });
    }

    protected $prepUpdate = null;

    protected static function getUpdateSQL(): string
    {
        return self::getTableSet(self::$updateSQL, function () {
            $sql = "UPDATE `" . static::$table . "` SET ";
            foreach (static::$fields as $field) {
                $sql .= "`{$field}` = :{$field}, ";
            }
            $sql = substr($sql, 0, - 2) . static::getSQLWhere();

            return $sql;
        });
    }

    public function update(): bool
    {
        if ($this->prepUpdate === null) {
            $sql = self::getUpdateSQL();
            $this->prepUpdate = $this->db->prepare($sql);
        }
        return $this->prepUpdate->execute($this->formatToDB());
    }

    protected $prepSelect = null;

    protected static function getSelectSQL(): string
    {
        return self::getTableSet(self::$selectSQL, function () {
            return "SELECT `" . implode("`, `", static::$fields) . "` FROM `" . static::$table . "` ";
        });
    }

    protected static function getSQLWhere(): string
    {
        return self::getTableSet(self::$sqlWhere, function () {
            $where = " WHERE ";
            foreach (static::$pk as $keyField) {
                $where .= "`{$keyField}` = :{$keyField} and ";
            }
            return substr($where, 0, - 5);
            ;
        });
    }

    public function fetch(array $keys): bool
    {
        $keys = array_map('strval', $keys);
        if (array_key_first($keys) === 0) {
            $keys = array_combine(static::$pk, $keys);
        }
        if ($this->prepSelect === null) {
            $sql = self::getSelectSQL() . static::getSQLWhere();
            $this->prepSelect = $this->db->prepare($sql);
        }
        $this->prepSelect->execute($keys);
        if ($found = $this->fetchRetrieve($this->prepSelect)) {
            $this->data = $found[0]->data;
            $this->active = true;
            return true;
        }
        $this->data = null;
        $this->active = false;
        return false;
    }

    public function fetchWhere(string $where, array $keys, string $orderBy = null): array
    {
        $sql = self::getSelectSQL() . " WHERE " . $where;
        if ($orderBy != null) {
            $sql .= " ORDER BY " . $orderBy;
        }
        $select = $this->db->prepare($sql);
        $select->execute($keys);
        return $this->fetchRetrieve($select);
    }

    public function fetchAll(string $orderBy = null): array
    {
        $sql = self::getSelectSQL();
        if ($orderBy != null) {
            $sql .= " ORDER BY " . $orderBy;
        }
        $select = $this->db->prepare($sql);
        $select->execute();
        return $this->fetchRetrieve($select);
    }

    public function fetchIn(array $keys): array
    {
        $sql = self::getSelectSQL();
        $sql .= " WHERE (" . implode(",", static::$pk) . ") IN (";
        $part = "(" . str_repeat("?,", count(static::$pk) - 1) . "?)";
        $sqlParams = [];
        $data = [];
        foreach ($keys as $key) {
            $sql .= $part . ",";
            $sqlParams = array_merge($sqlParams, $key);
        }
        if (count($sqlParams) > 0) {
            $sql = substr($sql, 0, - 1) . ")";
            $select = $this->db->prepare($sql);
            $select->execute($sqlParams);
            $dbData = $this->fetchRetrieve($select);
            $data = array_merge($data, $dbData);
        }
        return $data;
    }

    /**
     *
     * @param
     *            string - SQL segment
     * @param
     *            array - key values
     * @return array
     */
    public function fetchSQL(string $sql, array $keys = null): array
    {
        $sql = "SELECT " . implode(", ", $this->getColumnNames()) . " " . $sql;
        $this->prepSelect = $this->db->prepare($sql);
        $this->prepSelect->execute($keys);
        return $this->fetchRetrieve($this->prepSelect);
    }

    /**
     *
     * @param
     *            string - SQL segment
     * @param
     *            array - key values
     * @return array
     */
    public function fetchRAW(string $sql, array $keys = null): array
    {
        $prepSelect = $this->db->prepare($sql);
        $prepSelect->execute($keys);
        return $prepSelect->fetchAll();
    }

    public function executeRAW(string $sql, array $keys = null): bool
    {
        $prepSelect = $this->db->prepare($sql);
        return $prepSelect->execute($keys);
    }

    // Method overridden in class for format data if needed
    protected function formatFromDB(array &$row): void
    {}

    protected function fetchRetrieve(\PDOStatement $select): array
    {
        $selected = [];
        $class = static::class;
        while ($row = $select->fetch(\PDO::FETCH_ASSOC)) {
            $newObject = new $class($this->db);
            $this->formatFromDB($row);
            $newObject->data = $row;
            $newObject->active = true;
            $selected[] = $newObject;
        }
        return $selected;
    }

    protected $prepDelete = null;

    protected static function getDeleteSQL(): string
    {
        return self::getTableSet(self::$deleteSQL, function () {
            return "DELETE FROM `" . static::$table . "`" . static::getSQLWhere();
        });
    }

    public function delete(): bool
    {
        if ($this->prepDelete === null) {
            $sql = self::getDeleteSQL();
            $this->prepDelete = $this->db->prepare($sql);
        }
        $key = array_intersect_key($this->data, $this->getPKFlip());
        $this->prepDelete->execute($key);
        $this->active = false;

        return ! $this->active;
    }

    public function __get(string $name)
    {
        if (array_search($name, static::$fields) === false) {
            throw new \InvalidArgumentException("Unknown variable: {$name}");
        }
        return $this->data[$name];
    }

    public function __set(string $name, $value): void
    {
        if (array_search($name, static::$fields) === false) {
            throw new \InvalidArgumentException("Unknown variable: {$name}");
        }
        $this->data[$name] = $value;
    }

    public function __isset(string $name)
    {
        return isset($this->data[$name]);
    }

    public function set(array $data): void
    {
        // Check only valid fields passed in
        $extraKeys = array_diff_key($data, $this->data);
        if (count($extraKeys) != 0) {
            throw new \InvalidArgumentException("Unknown variable: " . implode(",", array_keys($extraKeys)));
        }
        $this->data = array_replace($this->data, $data);
    }

    public function get(): array
    {
        $data = $this->data;
        return $data;
    }

    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    public function rollback(): bool
    {
        return $this->db->rollBack();
    }

    public function commit(): bool
    {
        return $this->db->commit();
    }
}