<?php
namespace Admin\Model;

use Zend\Db\TableGateway\TableGateway;

//For join tables
use Zend\Db\Sql\Sql;
//For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;


class FriendMediaTable
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

    

    

    public function delete($id)
    {
        $this->tableGateway->delete(array('media_id' => $id));
    }
    
}