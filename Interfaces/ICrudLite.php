<?php

namespace Codethereal\Database\Sqlite\Interfaces;

interface ICrudLite
{
    public function tableName(): string;

    public function primaryKey(): string;

    public function create($data);

    public function read();

    public function update($data, $id);

    public function delete($id);

    public function readOne($id);
}