<?php
namespace Admin\Model;

use Zend\Db\TableGateway\TableGateway;

//For join tables
use Zend\Db\Sql\Sql;
//For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;


class EventTable
{
    protected $tableGateway;

    public function __construct(TableGateway $tableGateway)
    {
        $this->tableGateway = $tableGateway;
    }

    public function fetchAllCondition($where=null, $order=null, $count=null, $offset=null)
    {
        
//        $resultSet = $this->tableGateway->select($where);
       $select = new Select;
       $table=$this->tableGateway->getTable();
        $select->from($table);
        if($where!=null)
            $select->where($where);
        if($order!=null)
        $select->order($order);
        if($count!=null)
            $select->limit($count);
        if($offset!=null)
        $select->offset($offset);
        $statement = $this->tableGateway->getAdapter()->createStatement();
        $select->prepareStatement($this->tableGateway->getAdapter(), $statement);
         $resultSet = new ResultSet\ResultSet();
        $resultSet->initialize($statement->execute());

//        echo $select->getSqlString()."\n <pre>";        print_r($resultSet);
        $resultSet->buffer();
//        $resultSet->next();
        return $resultSet;
    }
    public function fetchAll(){
        $resultSet = $this->tableGateway->select();
        $resultSet->buffer();
        $resultSet->next();
          return $resultSet;
    }
    public function getEvent($id)
    {
        $rowset = $this->tableGateway->select(array('event_id' => $id));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $id");
        }
        return $row;
    }

    

    public function deleteEvent($id)
    {
        $this->tableGateway->delete(array('event_id' => $id));
    }
    
    public function getEventMedia($id){
       $select = new Select;
       $table=$this->tableGateway->getTable();
        $select->from(array('e'=> $table))
                   ->join(array("em"=>"event_media"),
                          'e.event_id=em.event_id',array(),'left')
                    ->join(array("m"=>"media"),
                          'm.media_id=em.media_id',array('media_id','user_id', 'is_profile_pic', 'sync_status', 'metadata'),'left')
                   ->where(array('e.event_id'=>$id));            
            $statement = $this->tableGateway->getAdapter()->createStatement();
        $select->prepareStatement($this->tableGateway->getAdapter(), $statement);
        $data=$statement->execute();
//            $sql = $select->__toString();
////                        
//        echo $select->getSqlString()."\n <pre>";        print_r($data->current());exit;
            return $data;
    }
}