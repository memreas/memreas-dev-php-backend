<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;
use Application\memreas\MUUID;

class TranscodeTransactionTable {
	protected $tableGateway;
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}
	public function fetchTranscodeTransactionByMediaId($media_id) {
		$rowset = $this->tableGateway->select ( array (
				'media_id' => $media_id 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row for media_id:: $media_id" );
		}
		return $row;
	}
	public function getTranscodeTransaction($transcode_transaction_id) {
		$rowset = $this->tableGateway->select ( array (
				'transcode_transaction_id' => $transcode_transaction_id 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row transcode_transaction_id::$transcode_transaction_id" );
		}
		return $row;
	}
	public function saveTranscodeTransaction(TranscodeTransaction $transcode_transaction) {
		$data = array (
				'transcode_transaction_id' => $transcode_transaction->transcode_transaction_id,
				'user_id' => $transcode_transaction->user_id,
				'media_id' => $transcode_transaction->media_id,
				'file_name' => $transcode_transaction->file_name,
				'message_data' => $transcode_transaction->message_data,
				'media_type' => $transcode_transaction->media_type,
				'media_extension' => $transcode_transaction->media_extension,
				'media_duration' => $transcode_transaction->media_duration,
				'media_size' => $transcode_transaction->media_size,
				'transcode_status' => $transcode_transaction->transcode_status,
				'pass_fail' => $transcode_transaction->pass_fail,
				'metadata' => $transcode_transaction->metadata,
				'error_message' => $transcode_transaction->error_message,			
				'transcode_job_duration' => $transcode_transaction->transcode_job_duration,
				'server_lock' => $transcode_transaction->server_lock,
				'priority' => $transcode_transaction->priority,				
				'transcode_start_time' => $transcode_transaction->transcode_start_time,
				'transcode_end_time' => $transcode_transaction->transcode_end_time 
		);
		try {
			if (isset ( $transcode_transaction->transcode_transaction_id )) {
				if ($this->getTranscodeTransaction ( $transcode_transaction->transcode_transaction_id )) {
					$this->tableGateway->update ( $data, array (
							'transcode_transaction_id' => $transcode_transaction->transcode_transaction_id 
					) );
				} else {
					throw new \Exception ( 'Form transaction_id does not exist' );
				}
			} else {
				$transcode_transaction_id = MUUID::fetchUUID ();
				$data ['transcode_transaction_id'] = $transcode_transaction_id;
				$this->tableGateway->insert ( $data );
			}
		} catch ( \Exception $e ) {
			error_log ( "Error message ---> " . $e->getMessage () . PHP_EOL );
			throw $e;
		}
		return $data ['transcode_transaction_id'];
	}
	public function deleteTranscodeTransaction($transcode_transaction_id) {
		$this->tableGateway->delete ( array (
				'transcode_transaction_id' => $transcode_transaction_id 
		) );
	}
}