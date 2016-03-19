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

class CopyrightTable {
	protected $tableGateway;
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	public function getCopyright($id) {
		$rowset = $this->tableGateway->select ( array (
				'copyright_id' => $id 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find media row for ---> $id" );
		}
		return $row;
	}
	public function updateCopyright(Copyright $copyright) {
		// error_log("Inside saveMedia media ---> " . print_r($media, true) . PHP_EOL);
		$data = array (
				'copyright_id' => $copyright->copyright_id,
				'copyright_batch_id' => $copyright->copyright_batch_id,
				'user_id' => $copyright->user_id,
				'media_id' => $copyright->media_id,
				'metadata' => $copyright->metadata,
				'validated' => $copyright->validated,
				'update_time' => $copyright->update_time 
		);
		
		if (isset ( $copyright->copyright_id )) {
			if ($this->getMedia ( $copyright->copyright_id )) {
				$this->tableGateway->update ( $data, array (
						'copyright_id' => $copyright->copyright_id 
				) );
			} else {
				throw new \Exception ( 'entry copyright_id does not exist' );
			}
		}
		return $data ['copyright_id'];
	}
}