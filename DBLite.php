<?php

namespace Codethereal\Database\Sqlite;

use SQLite3;

class DBLite extends SQLite3
{

    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    const JOIN_INNER = 'INNER';
    const JOIN_LEFT = 'LEFT OUTER';
    const JOIN_CROSS = 'CROSS';

    /**
     * @var array
     */
    private array $where = [];

    /**
     * @var array
     * Bindings for where conditions
     */
    private array $bindings = [];

    /**
     * @var array|string[]
     * Allowed where condition operators
     */
    private array $allowedOperators = ['=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    /**
     * @var array
     */
    private array $orderings = [];

    /**
     * @var array
     */
    private array $joins = [];

    /**
     * @var string
     * Query string
     */
    private string $query = "";

    /**
     * DBLite constructor.
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->open($path);
        $this->enableExceptions(false);
    }

    public function select(string $select = "*")
    {
        $this->query .= "SELECT $select FROM {{table}} ";
        return $this;
    }

    public function where($column, $operator = null, $value = null)
    {
        if (is_array($column) && count($column) > 0) {
            foreach ($column as $item) {
                if (isset($item[2]) && in_array($operator, $this->allowedOperators)) {
                    array_push($this->where, "$item[0] $item[1] :$item[0]");
                    array_push($this->bindings, [":$item[0]", $item[2]]);
                } else {
                    array_push($this->where, "$item[0] = :$item[0]");
                    array_push($this->bindings, [":$item[0]", $item[1]]);
                }
            }
        } else if ($value !== null && in_array($operator, $this->allowedOperators)) {
            array_push($this->where, "$column $operator :$column");
            array_push($this->bindings, [":$column", $value]);
        } else {
            array_push($this->where, "$column = :$column");
            array_push($this->bindings, [":$column", $operator]);
        }
        return $this;
    }

    public function like(string $column, $value)
    {
        if (is_array($column) && count($column) > 0) {
            foreach ($column as $item) {
                $this->where($item[0], 'LIKE', "$item[1]");
            }
        } else {
            $this->where($column, 'LIKE', "$value");
        }
        return $this;
    }

    public function notLike(string $column, $value)
    {
        if (is_array($column) && count($column) > 0) {
            foreach ($column as $item) {
                $this->where($item[0], 'NOT LIKE', "$item[1]");
            }
        } else {
            $this->where($column, 'NOT LIKE', "$value");
        }
        return $this;
    }

    public function in(string $column, array $values)
    {
        foreach ($values as &$value) {
            $value = self::escapeString($value);
        }
        $inQuery = implode(",", $values);
        array_push($this->where, "$column IN ($inQuery)");
        return $this;
    }

    public function notIn(string $column, array $values)
    {
        foreach ($values as &$value) {
            $value = self::escapeString($value);
        }
        $notInQuery = implode(",", $values);
        array_push($this->where, "$column NOT IN ($notInQuery)");
        return $this;
    }

    public function orderBy($column, string $sortMethod = "ASC")
    {
        if (is_array($column)) {
            foreach ($column as $item) {
                $sortM = $item[1] ?? 'ASC';
                array_push($this->orderings, "$item[0] $sortM");
            }
        } else {
            array_push($this->orderings, "$column $sortMethod");
        }
        return $this;
    }

    public function join(string $table, string $condition = "", $type = self::JOIN_LEFT)
    {
        $condition = !empty($condition) ? "ON $condition" : "";
        array_push($this->joins, "$type JOIN $table $condition");
        return $this;
    }

    public function get(string $table)
    {
        if (strpos($this->query, '{{table}}') !== false) {
            // If there is a table inside query replace it with given one, else create a query
            $this->query = str_replace('{{table}}', $table, $this->query);
        } else {
            $this->query = "SELECT * FROM $table";
        }

        return $this->withWhere()->withOrder()->withJoin()->bindAndExecute($this->bindings);
    }

    public function insert(string $table, array $data)
    {
        $keys = array_keys($data);
        $insertKeys = implode(",", $keys);

        $params = array_map(fn($value) => ":$value", $keys);
        $insertParams = implode(",", $params);

        $this->query = "INSERT INTO $table ($insertKeys) VALUES ($insertParams)";

        $statement = $this->prepare($this->query);
        foreach ($data as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $this->end();
        return $statement->execute() ? $this->lastInsertRowID() : false;
    }

    public function update(string $table, array $data)
    {
        $params = array_map(fn($item) => "$item = :$item", array_keys($data));
        $params = implode(",", $params);

        $this->query = "UPDATE $table SET $params";

        $this->withWhere();

        $statement = $this->bindAndReturn($this->bindings);
        foreach ($data as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $this->end();
        return $statement->execute();
    }

    public function count(string $table)
    {
        $this->query = "SELECT COUNT(*) as count FROM $table";

        $this->withWhere();

        $result = $this->bindAndExecute($this->bindings)->fetchArray(1);
        return $result['count'] ?? 0;
    }

    public function delete($table)
    {
        $this->query = "DELETE FROM $table";

        $this->withWhere();

        return $this->bindAndExecute($this->bindings);
    }

    /**
     * @param string $sql
     * @return $this
     * Just begins a new sql query (mostly used with bindAndExecute() method)
     */
    public function begin(string $sql)
    {
        $this->query = $sql;
        return $this;
    }

    /**
     * @param array $bindings
     * @return \SQLite3Result
     * Binds the values and executes it immediately
     */
    public function bindAndExecute(array $bindings)
    {
        $statement = $this->prepare($this->query);
        foreach ($bindings as $binding) {
            $statement->bindValue($binding[0], $binding[1]);
        }
        $this->end();
        return $statement->execute();
    }

    /**
     * @param array $bindings
     * @return false|\SQLite3Stmt
     * Binds the values and returns the statement instead executing it
     */
    public function bindAndReturn(array $bindings)
    {
        $statement = $this->prepare($this->query);
        foreach ($bindings as $binding) {
            $statement->bindValue($binding[0], $binding[1]);
        }
        return $statement;
    }

    /**
     * @return $this
     * Combine query with where conditions
     */
    private function withWhere()
    {
        if (count($this->where) > 0) {
            $this->query .= " WHERE ";
            $this->query .= implode(" AND ", $this->where);
        }
        return $this;
    }

    /**
     * @return $this
     * Combine query with order conditions
     */
    private function withOrder()
    {
        if (count($this->orderings) > 0) {
            $this->query .= " ORDER BY ";
            $this->query .= implode(",", $this->orderings);
        }
        return $this;
    }

    /**
     * @return $this
     * Combine query with joins
     */
    private function withJoin()
    {
        if (count($this->joins) > 0) {
            $this->query .= implode(" ", $this->joins);
        }
        return $this;
    }

    /**
     * End the query, empty arrays
     */
    private function end()
    {
        $this->bindings = [];
        $this->orderings = [];
        $this->where = [];
        $this->joins = [];
        $this->query = "";
    }

    public function transBegin()
    {
        $this->exec('BEGIN;');
    }

    public function transCommit()
    {
        $this->exec('COMMIT;');
    }
}