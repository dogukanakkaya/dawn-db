<?php
namespace Codethereal\Database\Sqlite;

use Codethereal\Database\Sqlite\Exceptions\MustBeInstanceOfLiteDB;
use Codethereal\Database\Sqlite\Interfaces\ILiteModel;

abstract class LiteModel extends LiteDB implements ILiteModel
{

    /**
     * Table name for model
     * @return string
     */
    public abstract function tableName(): string;

    /**
     * Primary key for model
     * @return string
     */
    public abstract function primaryKey(): string;

    /**
     * Returns the tableName.primaryKey for ambiguous joins
     * @return string
     */
    private function primaryKeyWithoutAmbiguous(){
        return $this->tableName().".".$this->primaryKey();
    }

    public function read($select = "*")
    {
        return $this->select($select)->get($this->primaryKeyWithoutAmbiguous());
    }

    public function readOne(int $id, $select = "*")
    {
        return $this->select($select)->where($this->primaryKeyWithoutAmbiguous(), $id)->row($this->tableName());
    }

    public function create($data)
    {
        return $this->insert($this->tableName(), $data);
    }

    public function change($data, int $id)
    {
        return $this->where($this->primaryKey(), $id)->update($this->tableName(), $data);
    }

    public function destroy(int $id)
    {
        return $this->where($this->primaryKey(), $id)->delete($this->tableName());
    }

    public function with($instance, array $options = array())
    {
        # Throw exception if no class with given instance
        if (!class_exists($instance)) {
            throw new MustBeInstanceOfLiteDB('First parameter for with() method must be instance of LiteModel');
        }

        $withClass = new $instance();

        # Throw exception if class is not instance of LiteModel
        if (!$withClass instanceof LiteModel){
            throw new MustBeInstanceOfLiteDB('First parameter for with() method must be instance of LiteModel');
        }

        $foreignTable = $withClass->tableName(); # Foreign table name
        $foreignTablePrimary = $withClass->primaryKey(); # Foreign table's primary key
        $foreignTableKey = $options['reference'] ?? LiteHelper::singularize($foreignTable)."_id"; # This table's reference to foreign table, if we do not have that in options it is foreign table name underscore id
        $thisTable = $this->tableName(); # This table's name

        $this->join($foreignTable,"$foreignTable.$foreignTablePrimary = $thisTable.$foreignTableKey");
        return $this;
    }
}