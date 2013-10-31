<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;
use Application\memreas\UUID;

class TransactionTable {
	protected $tableGateway;
	
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}
	
	public function getTransactionByAccountId($account_id) {
		$resultSet = $this->tableGateway->select ( array ('account_id' => $account_id ) );
		return $resultSet;
	}
	
	public function getTransaction($transaction_id) {
		$rowset = $this->tableGateway->select ( array ('transaction_id' => $transaction_id ) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $transactionId" );
		}
		return $row;
	}
	
	public function saveTransaction(Transaction $transaction) {
		$data = array ('transaction_id' => $transaction->transaction_id, 
					'account_id' => $transaction->account_id, 
					'transaction_type' => $transaction->transaction_type, 
					'pass_fail' => $transaction->pass_fail, 
					'amount' => $transaction->amount, 
					'currency' => $transaction->currency, 
					'transaction_request' => $transaction->transaction_request, 
					'transaction_response' => $transaction->transaction_response, 
					'transaction_sent' => $transaction->transaction_sent, 
					'transaction_receive' => $transaction->transaction_receive );
		if (isset($transaction->transaction_id)) {
			if ($this->getTransaction($transaction->transaction_id )) {
				$this->tableGateway->update ( $data, array ('transaction_id' => $transaction->transaction_id ) );
			} else {
				throw new \Exception ( 'Form transaction_id does not exist' );
			}
		} else {
			$transaction_id = UUID::fetchUUID();
			$transaction->transactionId = $transaction_id;	
			$data['transaction_id'] = $transaction_id;	
			$this->tableGateway->insert ( $data );
		}
		return $data['transaction_id'];
	}
	
	public function deleteTransaction($transactionId) {
		$this->tableGateway->delete ( array ('transactionId' => $transactionId ) );
	}
}