<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

// For join tables
use Zend\Db\Sql\Sql;
// For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;
use Application\memreas\MUUID;
use Application\Model\MemreasTranscoderTables;

class MediaTable {
	protected $tableGateway;
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	public function fetchAll($where = null) {
		$resultSet = $this->tableGateway->select ( $where );
		
		return $resultSet;
	}
	public function getMedia($id) {
		$rowset = $this->tableGateway->select ( array (
				'media_id' => $id 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find media row for ---> $id" );
		}
		return $row;
	}
	public function deleteMedia($id) {
		$this->tableGateway->delete ( array (
				'media_id' => $id 
		) );
		return true;
	}
	public function saveMedia(Media $media) {
		// error_log("Inside saveMedia media ---> " . print_r($media, true) . PHP_EOL);
		$data = array (
				'media_id' => $media->media_id,
				'user_id' => $media->user_id,
				'is_profile_pic' => $media->is_profile_pic,
				'sync_status' => $media->sync_status,
				'transcode_status' => $media->transcode_status,
				'metadata' => $media->metadata,
				'report_flag' => $media->report_flag,
				'create_date' => $media->create_date,
				'update_date' => $media->update_date 
		);
		
		if (isset ( $media->media_id )) {
			if ($this->getMedia ( $media->media_id )) {
				$this->tableGateway->update ( $data, array (
						'media_id' => $media->media_id 
				) );
			} else {
				throw new \Exception ( 'Form media_id does not exist' );
			}
		} else {
			$media_id = MUUID::fetchUUID ();
			$data ['media_id'] = $media_id;
			$this->tableGateway->insert ( $data );
		}
		return $data ['media_id'];
	}
}