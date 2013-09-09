<?php
namespace Admin\Model;

use Zend\Db\TableGateway\TableGateway;

//For join tables
use Zend\Db\Sql\Sql;
//For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;


class EventMediaTable
{
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function fetchAll($where=null, $order=null, $count=null, $offset=null)
    {
        
        $resultSet = $this->tableGateway->select($where);
       
        return $resultSet;
    }

    public function getEventMedia($id)
    {
        $rowset = $this->tableGateway->select(array('event_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $id");
        }
        return $row;
    }

    

    public function delete($where)
    {
        $this->tableGateway->delete($where);
        return true;
    }
    
}