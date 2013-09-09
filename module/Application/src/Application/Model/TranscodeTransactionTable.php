<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;
use memreas\UUID;

class TranscodeTransactionTable {
	protected $tableGateway;
	
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}

	public function getTranscodeTransaction($transaction_id) {
		$rowset = $this->tableGateway->select ( array ('transcode_transaction_id' => $transcode_transaction_id ) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $transactionId" );
		}
		return $row;
	}
	
	public function saveTranscodeTransaction(TranscodeTransaction $transcode_transaction) {
error_log("Inside saveTranscodeTransaction");
		$data = array (
					'transcode_transaction_id' => $transcode_transaction->transcode_transaction_id, 
					'user_id' => $transcode_transaction->user_id, 
					'media_type' => $transcode_transaction->media_type, 
					'media_extension' => $transcode_transaction->media_extension, 
					'file_name' => $transcode_transaction->file_name, 
					'media_duration' => $transcode_transaction->media_duration, 
					'media_size' => $transcode_transaction->media_size, 
					'pass_fail' => $transcode_transaction->pass_fail, 
					'metadata' => $transcode_transaction->metadata, 
					'error_message' => $transcode_transaction->error_message, 
					'transcode_job_duration' => $transcode_transaction->transcode_job_duration, 
					'transcode_start_time' => $transcode_transaction->transcode_start_time, 
					'transcode_end_time' => $transcode_transaction->transcode_end_time, 
				);
		if (isset($transcode_transaction->transcode_transaction_id)) {
error_log("Inside isset transcode_transaction_id");
			if ($this->getTransaction($transcode_transaction->transcode_transaction_id )) {
				$this->tableGateway->update ( $data, array ('transcode_transaction_id' => $transcode_transaction->transcode_transaction_id ) );
			} else {
				throw new \Exception ( 'Form transaction_id does not exist' );
			}
		} else {
error_log("Inside else !isset transcode_transaction_id");
			$transcode_transaction_id = UUID::fetchUUID();
			//$transcode_transaction->transcode_transaction_id = $transcode_transaction_id;	
			$data['transcode_transaction_id'] = $transcode_transaction_id;	
			$this->tableGateway->insert ( $data );
		}
		return $data['transcode_transaction_id'];
	}
	
	public function deleteTranscodeTransaction($transcode_transaction_id) {
		$this->tableGateway->delete ( array ('transcode_transaction_id' => $transcode_transaction_id ) );
	}
}