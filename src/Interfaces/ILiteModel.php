<?php

namespace Codethereal\Database\Sqlite\Interfaces;

interface ILiteModel
{
    public function tableName(): string;

    public function primaryKey(): string;

    public function read(string $select);

    public function readOne(int $id, string $select);

    public function create($data);

    public function change($data, int $id);

    public function destroy(int $id);

    public function with(\Codethereal\Database\Sqlite\LiteModel $instance, array $options);
}