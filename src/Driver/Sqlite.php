<?php

namespace Codethereal\Database\Driver;

use Codethereal\Database\Builder\Query;
use SQLite3Result;

class Sqlite extends \SQLite3 implements DriverInterface
{
    private Query $builder;

    public function __construct(string $path = '')
    {
        $this->open($path);
        $this->builder = new Query($this);
    }

    public function getQueryBuilder(): Query
    {
        return $this->builder;
    }

    public function execute(string $sql, array $bindings = []): bool|SQLite3Result
    {
        $statement = $this->prepare($sql);

        foreach ($bindings as $binding) {
            $statement->bindValue($binding[0], $binding[1]);
        }

        return $statement?->execute();
    }

    public function transBegin(): void
    {
        $this->exec('BEGIN;');
    }

    public function transCommit(): void
    {
        $this->exec('COMMIT;');
    }
}
