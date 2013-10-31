<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

//For join tables
use Zend\Db\Sql\Sql;
//For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;

use Application\memreas\UUID;
use Application\Model\MemreasTranscoderTables;

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
    
	public function saveMedia(Media $media) {
error_log("Inside saveMedia media ---> " . print_r($media, true) . PHP_EOL);
	
		$data = array (
					'media_id' => $media->media_id, 
					'user_id' => $media->user_id, 
					'is_profile_pic' => $media->is_profile_pic, 
					'sync_status' => $media->sync_status, 
					'metadata' => $media->metadata, 
					'report_flag' => $media->report_flag, 
					'create_date' => $media->create_date, 
					'update_date' => $media->update_date, 
				);
		if (isset($data->media_id)) {
error_log("Inside isset data->media_id");
			if ($this->getTransaction($data->media_id )) {
				$this->tableGateway->update ( $data, array ('media_id' => $data->media_id ) );
			} else {
				throw new \Exception ( 'Form media_id does not exist' );
			}
		} else {
			$media_id = UUID::getInstance()->fetchUUID();
error_log("Inside else !isset media->media_id ----> $media_id" . PHP_EOL);
			//$media_id = $uuid->fetchUUID();
			//$transcode_transaction->transcode_transaction_id = $transcode_transaction_id;	
			$data['media_id'] = $media_id;	
			$this->tableGateway->insert ( $data );
		}
		return $data['media_id'];
	}

}