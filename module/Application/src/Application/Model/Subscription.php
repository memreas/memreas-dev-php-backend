<?php
namespace Application\Model;

class Subscription{
	
	public $subscriptionId;
	public $accountId;
	public $preapprovalStartdate;
	public $preapprovalEnddate;
	public $paymentDteOfMonth;
	public $currencyCode;
	public $maximumAmountPerPayment;
	public $maximumNoOfPayments;
	public $maximumNoOfPaymentsPerPeriod;
	public $maximumTotalOfAllPayment;
	public $paymentPeriod;
	public $senderEmail;
	public $isPinRequired;
	public $feesPayer;
	public $feesDate;
	public $createDate;
	public $updateTime;
	
	public function exchangeArray($data)
	{
		$this->subscriptionId = (isset($data['subscription_id'])) ?   $data['subscription_id'] : null;
		$this->accountId = (isset($data['account_id'])) ?   $data['account_id'] : null;
		$this->preapprovalStartdate = (isset($data['preapproval_startdate'])) ?   $data['preapproval_startdate'] : null;
		$this->preapprovalEnddate = (isset($data['preapproval_enddate'])) ?   $data['preapproval_enddate'] : null;
		$this->paymentDteOfMonth = (isset($data['payment_dte_of_month'])) ?   $data['payment_dte_of_month'] : null;
		$this->currencyCode = (isset($data['currency_code'])) ?   $data['currency_code'] : null;
		$this->maximumAmountPerPayment = (isset($data['maximum_amount_per_payment'])) ?   $data['maximum_amount_per_payment'] : null;
		$this->maximumNoOfPayments = (isset($data['maximum_no_of_payments'])) ?   $data['maximum_no_of_payments'] : null;
		$this->maximumNoOfPaymentsPerPeriod = (isset($data['maximum_no_of_payments_per_period'])) ?   $data['maximum_no_of_payments_per_period'] : null;
		$this->maximumTotalOfAllPayment = (isset($data['maximum_total_of_all_payment'])) ?   $data['maximum_total_of_all_payment'] : null;
		$this->paymentPeriod = (isset($data['payment_period'])) ?   $data['payment_period'] : null;
		$this->senderEmail = (isset($data['sender_email'])) ?   $data['sender_email'] : null;
		$this->isPinRequired = (isset($data['is_pin_required'])) ?   $data['is_pin_required'] : null;
		$this->feesPayer = (isset($data['fees_payer'])) ?   $data['fees_payer'] : null;
		$this->feesDate = (isset($data['fees_date'])) ?   $data['fees_date'] : null;
		$this->createDate = (isset($data['create_date'])) ?   $data['create_date'] : null;
		$this->updateTime = (isset($data['update_time'])) ?   $data['update_time'] : null;
	}
}