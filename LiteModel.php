<?php
namespace Codethereal\Database\Sqlite;

use Codethereal\Database\Sqlite\Exceptions\ModelNotValidated;
use Codethereal\Database\Sqlite\Exceptions\MustBeInstanceOfLiteDB;
use Codethereal\Database\Sqlite\Interfaces\ILiteModel;

abstract class LiteModel extends LiteDB implements ILiteModel
{

    /**
     * Validation instance for model validation
     * @var Validation
     */
    private Validation $validation;

    public function __construct()
    {
        parent::__construct();
        $this->validation = new Validation();
    }

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
     * Validation rules for creating and changing
     * @return array
     */
    public abstract function rules(): array;

    /**
     * Returns the tableName.primaryKey for ambiguous joins
     * @return string
     */
    private function primaryKeyWithoutAmbiguous(){
        return $this->tableName().".".$this->primaryKey();
    }

    public function read(string $select = "*")
    {
        return $this->select($select)->get($this->primaryKeyWithoutAmbiguous());
    }

    public function readOne(int $id, string $select = "*")
    {
        return $this->select($select)->where($this->primaryKeyWithoutAmbiguous(), $id)->row($this->tableName());
    }

    public function create($data)
    {
        $validated = $this->validation->validate($this->rules(), $data);
        if (!$validated){
            $model = get_called_class();
            throw new ModelNotValidated("Validation failed for model : $model",422, null, $this->validation->errors());
        }
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