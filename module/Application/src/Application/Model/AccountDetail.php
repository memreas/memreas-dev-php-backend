<?php
namespace Application\Model;

class AccountDetail{
	
	public $account_detail_id = NULL;
	public $account_id = NULL;
	public $first_name = NULL;
	public $last_name = NULL;
	public $address_line_1 = NULL;
	public $address_line_2 = NULL;
	public $city = NULL;
	public $state = NULL;
	public $zip_code = NULL;
	public $postal_code = NULL;
	public $paypal_card_reference_id = NULL;
	
	public function exchangeArray($data)
	{
		$this->account_detail_id     = (isset($data['account_detail_id'])) ? $data['account_detail_id'] : $this->account_detail_id;
		$this->account_id = (isset($data['account_id'])) ? $data['account_id'] : $this->account_id;
		$this->first_name  = (isset($data['first_name'])) ? $data['first_name'] : $this->first_name;
		$this->last_name  = (isset($data['last_name'])) ? $data['last_name'] : $this->last_name;
		$this->address_line_1  = (isset($data['address_line_1'])) ? $data['address_line_1'] : $this->address_line_1;
		$this->address_line_2  = (isset($data['address_line_2'])) ? $data['address_line_2'] : $this->address_line_2;
		$this->city  = (isset($data['city'])) ? $data['city'] : $this->city;
		$this->state  = (isset($data['state'])) ? $data['state'] : $this->state;
		$this->zip_code  = (isset($data['zip_code'])) ? $data['zip_code'] : $this->zip_code;
		$this->postal_code  = (isset($data['postal_code'])) ? $data['postal_code'] : $this->postal_code;
		$this->paypal_card_reference_id  = (isset($data['paypal_card_reference_id'])) ? $data['paypal_card_reference_id'] : $this->paypal_card_reference_id;
	}
}