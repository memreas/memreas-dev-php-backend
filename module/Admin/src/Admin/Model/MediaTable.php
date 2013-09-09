<?php
namespace Admin\Model;

use Zend\Db\TableGateway\TableGateway;

//For join tables
use Zend\Db\Sql\Sql;
//For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;


class MediaTable
{
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function fetchAll($where=null)
    {
        
        $resultSet = $this->tableGateway->select($where);
       
        return $resultSet;
    }

    public function getMedia($id)
    {
        $rowset = $this->tableGateway->select(array('media_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $id");
        }
        return $row;
    }

    

    public function deleteMedia($id)
    {
        $this->tableGateway->delete(array('media_id' => $id));
        return true;
    }
    
}