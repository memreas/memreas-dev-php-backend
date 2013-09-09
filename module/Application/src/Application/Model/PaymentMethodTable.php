<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
use memreas\UUID;

class PaymentMethodTable {
	protected $tableGateway;
	
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}
	
	public function getPaymentMethodsByUserId($user_id) {

		//Fetch the adapter...
		$adapter = $this->tableGateway->getAdapter();
		//Setup the query
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->tableGateway->table)
			   ->join('account', 'payment_method.account_id = account.account_id');

		//add where on account table for user_id
		$select->columns(array('*'))->where(array('account.user_id' => $user_id));
		$sqlString = $sql->getSqlStringForSqlObject($select);
 
		//you can check your query by echo-ing :
		//error_log("SQL STATEMENT-----> " .  $sqlString);
		$statement = $sql->prepareStatementForSqlObject($select);
		$result = $statement->execute();

		return $result;
	}

	public function getPaymentMethod($payment_method_id) {
		$rowset = $this->tableGateway->select ( array ('payment_method_id' => $payment_method_id ) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $payment_method_id" );
		}
		return $row;
	}

	public function getPaymentMethodByPayPalReferenceId($paypal_card_reference_id) {
		$rowset = $this->tableGateway->select ( array ('paypal_card_reference_id' => $paypal_card_reference_id ) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $payment_method_id" );
		}
		return $row;
	}
	
	public function savePaymentMethod(PaymentMethod $payment_method) {
		
		$data = array ('payment_method_id' => $payment_method->payment_method_id, 
		'account_id' => $payment_method->account_id, 
		'paypal_card_reference_id' => $payment_method->paypal_card_reference_id, 
		'card_type' => $payment_method->card_type, 
		'obfuscated_card_number' => $payment_method->obfuscated_card_number, 
		'exp_month' => $payment_method->exp_month, 
		'exp_year' => $payment_method->exp_year, 
		'valid_until' => $payment_method->valid_until, 
		'create_time' => $payment_method->create_time, 
		'update_time' => $payment_method->update_time );
		
		if (isset($payment_method->payment_method_id)) {
			if ($this->getPaymentMethod ( $payment_method->payment_method_id )) {
				$this->tableGateway->update ( $data, array ('payment_method_id' =>  $payment_method->payment_method_id ) );
			} else {
				throw new \Exception ( 'Form payment_method_id does not exist' );
			}
		} else {
			$payment_method_id = UUID::fetchUUID();
			$payment_method->payment_method_id = $payment_method_id;	
			$data['payment_method_id'] = $payment_method_id;	
			$this->tableGateway->insert ( $data );
		}
		return $data['payment_method_id'];
	}
	
	public function deletePaymentMethod($payment_method_id) {
		$this->tableGateway->delete ( array ('payment_method_id' => $payment_method_id ) );
	}

	public function deletePaymentMethodByPayPalCardReferenceId($paypal_card_reference_id) {
		$this->tableGateway->delete ( array ('paypal_card_reference_id' => $paypal_card_reference_id ) );
	}
}