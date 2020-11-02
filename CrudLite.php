<?php
namespace Codethereal\Database\Sqlite;

use Codethereal\Database\Sqlite\Interfaces\ICrudLite;

abstract class CrudLite implements ICrudLite
{

    /**
     * @var DBLite
     */
    private DBLite $db;

    public function __construct(DBLite $db)
    {
        $this->db = $db;
    }
    
    public abstract function tableName(): string;

    public abstract function primaryKey(): string;

    public function create($data)
    {
        return $this->db->insert($this->tableName(), $data);
    }

    public function read()
    {
        return $this->db->get($this->tableName());
    }

    public function update($data, $id)
    {
        return $this->db->where($this->primaryKey(), $id)->update($this->tableName(), $data);
    }

    public function delete($id)
    {
        return $this->db->where($this->primaryKey(), $id)->delete($this->tableName());
    }

    public function readOne($id)
    {
        return $this->db->where($this->primaryKey(), $id)->get($this->tableName());
    }
}