<?php

namespace Codethereal\Database\Sqlite;

class LiteDB extends \SQLite3
{
    private array $where = [];

    private array $bindings = [];

    private array $allowedOperators = ['=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    private array $orderings = [];

    private array $joins = [];

    private string $query = '';

    /**
     * LiteDB constructor.
     */
    public function __construct(string $path = "")
    {
        // parent::__construct($path);
        $this->open($path);
    }

    public function select(string $select = "*"): LiteDB
    {
        $this->query .= "SELECT $select FROM {{table}} ";
        return $this;
    }

    public function where(string|array|\Closure $column, $operator = null, $value = null, string $condition = ' AND '): LiteDB
    {
        // I will also use this as a unique identifier for bindings because
        // Multiple bindings with same key occurs a problem so i will append where count
        // at the end of every binding
        $countWheres = count($this->where);

        // If first where query, do not add any condition
        if ($countWheres <= 0){
            $condition = '';
        }

        if (is_callable($column)){
            // Nested query instance
            $nestedQuery = $column(new self());

            // Add sub wheres
            $this->addWhere($condition, "(", implode($nestedQuery->where), ")");

            // Merge nested queries bindings with parent query
            $this->bindings = array_merge($this->bindings, $nestedQuery->bindings);
        } elseif (is_array($column) && count($column) > 0) {
            // If multiple where conditions passed as array loop over them
            foreach ($column as $item) {
                if (isset($item[2]) && $this->validOperator($operator)) {
                    $this->addWhere($condition, $item[0], $item[1], ":$item[0]".$countWheres);
                    $this->addBinding([":$item[0]".$countWheres, $item[2]]);
                } else {
                    $this->addWhere($condition, $item[0], "=", ":$item[0]".$countWheres);
                    $this->addBinding([":$item[0]".$countWheres, $item[1]]);
                }
            }
        } else if ($value !== null && $this->validOperator($operator)) {
            // Single condition with operator
            $this->addWhere($condition, $column, $operator, ":$column".$countWheres);
            $this->addBinding([":$column".$countWheres, $value]);
        } else {
            // Single condition with default operator
            $this->addWhere($condition, $column, "=", ":$column".$countWheres);
            $this->addBinding([":$column".$countWheres, $operator]);
        }
        return $this;
    }

    public function orWhere(string|array|\Closure $column, $operator = null, $value = null): LiteDB
    {
        $this->where($column, $operator, $value, ' OR ');
        return $this;
    }

    public function like(string $column, string $value): LiteDB
    {
        $this->where($column, 'LIKE', $value);
        return $this;
    }

    public function notLike(string $column, string $value): LiteDB
    {
        $this->where($column, 'NOT LIKE', $value);
        return $this;
    }

    public function in(string $column, array $values): LiteDB
    {
        foreach ($values as &$value) {
            $value = self::escapeString($value);
        }
        $inQuery = implode(",", $values);
        $this->addWhere($column, "IN", "($inQuery)");
        return $this;
    }

    public function notIn(string $column, array $values): LiteDB
    {
        foreach ($values as &$value) {
            $value = self::escapeString($value);
        }
        $notInQuery = implode(",", $values);
        $this->addWhere($column, "NOT IN", "($notInQuery)");
        return $this;
    }

    public function orderBy(string|array $column, string $sortMethod = "ASC"): LiteDB
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

    public function join(string $table, string $condition = "", $type = 'LEFT OUTER'): LiteDB
    {
        $condition = !empty($condition) ? "ON $condition" : "";
        array_push($this->joins, " $type JOIN $table $condition");
        return $this;
    }

    public function get(string $table): \SQLite3Result
    {
        if (str_contains($this->query, '{{table}}')) {
            // If there is a table inside query replace it with given one, else create a query
            $this->query = str_replace('{{table}}', $table, $this->query);
        } else {
            $this->query = "SELECT * FROM $table";
        }
        return $this->withJoin()->withWhere()->withOrder()->bindAndExecute($this->bindings);
    }

    public function row(string $table): bool|array
    {
        return $this->get($table)->fetchArray(SQLITE3_ASSOC);
    }

    public function insert(string $table, array $data): bool|int
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

    public function update(string $table, array $data): \SQLite3Result
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

    public function count(string $table): int
    {
        $this->query = "SELECT COUNT(*) as count FROM $table";

        $this->withWhere();

        $result = $this->bindAndExecute($this->bindings)->fetchArray(1);
        return $result['count'] ?? 0;
    }

    public function delete(string $table): \SQLite3Result
    {
        $this->query = "DELETE FROM $table";

        $this->withWhere();

        return $this->bindAndExecute($this->bindings);
    }

    /**
     * Just begins a new sql query (mostly used with bindAndExecute() method)
     */
    public function begin(string $query): LiteDB
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Binds the values and executes it immediately
     */
    public function bindAndExecute(array $bindings): \SQLite3Result
    {
        $statement = $this->bindAndReturn($bindings);
        $this->end();
        return $statement?->execute();
    }

    /**
     * Binds the values and returns the statement instead executing it
     */
    public function bindAndReturn(array $bindings): false|\SQLite3Stmt
    {
        $statement = $this->prepare($this->query);
        foreach ($bindings as $binding) {
            $statement->bindValue($binding[0], $binding[1]);
        }
        return $statement;
    }

    /**
     * Combine query with where conditions
     */
    private function withWhere(): LiteDB
    {
        if (count($this->where) > 0) {
            $this->query .= " WHERE ";
            $this->query .= implode(" ", $this->where);
        }
        return $this;
    }

    /**
     * Combine query with order conditions
     */
    private function withOrder(): LiteDB
    {
        if (count($this->orderings) > 0) {
            $this->query .= " ORDER BY ";
            $this->query .= implode(",", $this->orderings);
        }
        return $this;
    }

    /**
     * Combine query with joins
     */
    private function withJoin(): LiteDB
    {
        if (count($this->joins) > 0) {
            $this->query .= implode(" ", $this->joins);
        }
        return $this;
    }

    /**
     * End the query, empty arrays
     */
    private function end(): void
    {
        $this->bindings = [];
        $this->orderings = [];
        $this->where = [];
        $this->joins = [];
        $this->query = "";
    }

    public function transBegin(): void
    {
        $this->exec('BEGIN;');
    }

    public function transCommit(): void
    {
        $this->exec('COMMIT;');
    }

    private function addBinding($binding){
        # Remove any dot notation inside column, table.key
        $binding[0] = str_replace(".", "", $binding[0]);
        array_push($this->bindings, $binding);
    }

    private function addWhere($condition, ...$where){
        array_push($this->where, $condition . implode(" ", $where));
    }

    /**
     * Check if valid operator
     */
    private function validOperator(string $operator): bool
    {
        return in_array($operator, $this->allowedOperators);
    }
}
