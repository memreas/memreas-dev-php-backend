<?php
namespace Application\Model;

class Transaction{
	
	public $transaction_id = NULL;
	public $account_id = NULL;
	public $transaction_type = NULL;
	public $pass_fail = NULL;
	public $amount = NULL;
	public $currency = NULL;
	public $transaction_request = NULL;
	public $transaction_response = NULL;
	public $transaction_sent = NULL;
	public $transaction_receive = NULL;
	
	
	public function exchangeArray($data)
	{
		$this->transaction_id = (isset($data['transaction_id'])) ?  $data['transaction_id'] : $this->transaction_id;
		$this->account_id = (isset($data['account_id'])) ?  $data['account_id'] : $this->account_id;
		$this->transaction_type = (isset($data['transaction_type'])) ?  $data['transaction_type'] : $this->transaction_type;
		$this->pass_fail = (isset($data['pass_fail'])) ?  $data['pass_fail'] : $this->pass_fail;
		$this->amount  = (isset($data['amount'])) ?  $data['amount'] : $this->amount;
		$this->currency  = (isset($data['currency'])) ?  $data['currency'] : $this->currency;
		$this->transaction_request = (isset($data['transaction_request'])) ?  $data['transaction_request'] : $this->transaction_request;
		$this->transaction_response = (isset($data['transaction_response'])) ?  $data['transaction_response'] : $this->transaction_response;
		$this->transaction_sent = (isset($data['transaction_sent'])) ?  $data['transaction_sent'] : $this->transaction_sent;
		$this->transaction_receive = (isset($data['transaction_receive'])) ?  $data['transaction_receive'] : $this->transaction_receive;
	}
}