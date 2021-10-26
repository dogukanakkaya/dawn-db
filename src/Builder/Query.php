<?php

namespace Codethereal\Database\Builder;

use Codethereal\Database\Driver\DriverInterface;

class Query
{
    const ALLOWED_OPERATORS = ['=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    private array $where = [];

    private array $bindings = [];

    private array $orderings = [];

    private array $joins = [];

    private string $query = '';

    private int $limit = 0;

    public function __construct(private DriverInterface $driver, private int $nestCount = 0) { }

    public function select(string $select = '*'): self
    {
        $this->query = 'SELECT ' . $select . ' FROM {{table}}';

        return $this;
    }

    public function where(string|\Closure $column, $valueOrOperator = null, $value = null, string $condition = ' AND '): self
    {
        $countWheres = count($this->where);

        // If first where query, do not add any condition
        if ($countWheres <= 0){
            $condition = '';
        }

        // use this as a unique identifier for bindings because
        // multiple bindings with same key occurs a problem, so I will append where count
        // at the end of every binding
        $uniqueBinder = $countWheres . $this->nestCount;

        if (is_callable($column)){
            // Nested query instance
            $nestedQuery = $column(new self($this->driver, $this->nestCount + 1));

            // Add sub where
            $this->addWhere($condition, '(', implode($nestedQuery->where), ')');

            // Merge nested queries bindings with parent query
            $this->bindings = array_merge($this->bindings, $nestedQuery->bindings);
        } else if ($value !== null && $this->isValidOperator($valueOrOperator)) {
            // IN and NOT IN can't be bind directly
            if (in_array($valueOrOperator, ['IN', 'NOT IN'])) {
                $this->addWhere($condition, $column, $valueOrOperator, $value);
            } else {
                $this->addWhere($condition, $column, $valueOrOperator, ':' . $column . $uniqueBinder);
                $this->addBinding([':' . $column . $uniqueBinder, $value]);
            }
        } else {
            $this->where($column, '=', $valueOrOperator, $condition);
        }

        return $this;
    }

    public function orWhere(string|array|\Closure $column, $valueOrOperator = null, $value = null): self
    {
        $this->where($column, $valueOrOperator, $value, ' OR ');

        return $this;
    }

    public function in(string $column, array $values): self
    {
        $values = array_map(fn ($value) => addslashes($value), $values);

        $this->where($column, 'IN', '(' . implode(',', $values) . ')');

        return $this;
    }

    public function notIn(string $column, array $values): self
    {
        $values = array_map(fn ($value) => addslashes($value), $values);

        $this->where($column, 'NOT IN', '(' . implode(',', $values) . ')');

        return $this;
    }

    public function orderBy(string|array $column, string $sortMethod = 'ASC'): self
    {
        array_push($this->orderings, "$column $sortMethod");

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function join(string $table, string $condition = '', $type = 'LEFT OUTER'): self
    {
        $condition = !empty($condition) ? "ON $condition" : '';
        array_push($this->joins, " $type JOIN $table $condition");

        return $this;
    }

    public function get(string $table)
    {
        if (empty($this->query)) {
            $this->select();
        }

        $this->query = str_replace('{{table}}', $table, $this->query);

        return $this->executeDriver();
    }

    public function getSingle(string $table)
    {
        $this->limit(1);

        return $this->get($table);
    }

    public function insert(string $table, array $data)
    {
        $keys = array_keys($data);
        $insertKeys = implode(',', $keys);

        $params = array_map(fn($value) => ":$value", $keys);
        $insertParams = implode(',', $params);

        $this->query = "INSERT INTO $table ($insertKeys) VALUES ($insertParams)";

        foreach ($data as $key => $value) {
            $this->addBinding([":$key", $value]);
        }

        return $this->executeDriver();
    }

    public function update(string $table, array $data)
    {
        $params = array_map(fn($item) => "$item = :$item", array_keys($data));
        $params = implode(',', $params);

        $this->query = "UPDATE $table SET $params";

        foreach ($data as $key => $value) {
            $this->addBinding([":$key", $value]);
        }

        return $this->executeDriver();
    }

    public function count(string $table)
    {
        $this->query = "SELECT COUNT(*) as count FROM $table";

        return $this->executeDriver();
    }

    public function delete(string $table)
    {
        $this->query = "DELETE FROM $table";

        return $this->executeDriver();
    }

    public function toSql(): string
    {
        $this
            ->withJoin()
            ->withWhere()
            ->withOrder();

        if ($this->limit) {
            $this->query .= " LIMIT $this->limit";
        }

        return $this->query;
    }

    private function executeDriver()
    {
        $result = $this->driver->execute(
            $this->toSql(),
            $this->bindings
        );

        $this->end();

        return $result;
    }

    private function withWhere(): self
    {
        if (count($this->where) > 0) {
            $this->query .= ' WHERE ';
            $this->query .= implode(' ', $this->where);
        }

        return $this;
    }

    private function withOrder(): self
    {
        if (count($this->orderings) > 0) {
            $this->query .= ' ORDER BY ';
            $this->query .= implode(',', $this->orderings);
        }

        return $this;
    }

    private function withJoin(): self
    {
        if (count($this->joins) > 0) {
            $this->query .= implode(' ', $this->joins);
        }

        return $this;
    }

    private function end(): void
    {
        $this->bindings = [];
        $this->orderings = [];
        $this->where = [];
        $this->joins = [];
        $this->query = '';
        $this->limit = 0;
    }

    private function addBinding($binding){
        // Remove any dot notation inside column, table.key
        $binding[0] = str_replace('.', '', $binding[0]);
        array_push($this->bindings, $binding);
    }

    private function addWhere($condition, ...$where){
        array_push($this->where, $condition . implode(' ', $where));
    }

    private function isValidOperator(string $operator): bool
    {
        return in_array($operator, self::ALLOWED_OPERATORS);
    }
}
