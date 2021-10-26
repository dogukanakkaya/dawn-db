<?php

namespace Codethereal\Database\Driver;

use Codethereal\Database\Builder\Query;

interface DriverInterface
{
    public function getQueryBuilder(): Query;

    public function execute(string $sql, array $bindings);
}