<?php
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

class SubscriptionTable {
	protected $tableGateway;
	
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	
	public function fetchAll() {
		$resultSet = $this->tableGateway->select ();
		return $resultSet;
	}
	
	public function getSubscription($subscription_id) {
		$subscription_id = ( int ) $subscription_id;
		$rowset = $this->tableGateway->select ( array ('subscription_id' => $subscription_id ) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $subscription_id" );
		}
		return $row;
	}
	
	public function saveSubscription(Subscription $subscription) {
		$data = array ('subscription_id' => $subscription->subscriptionId, 'account_id' => $subscription->accountId, 'preapproval_startdate' => $subscription->preapprovalStartdate, 'preapproval_enddate' => $subscription->preapprovalEnddate, 'payment_dte_of_month' => $subscription->paymentDteOfMonth, 'currency_code' => $subscription->currencyCode, 'maximum_amount_per_payment' => $subscription->maximumAmountPerPayment, 'maximum_no_of_payments' => $subscription->maximumNoOfPayments, 'maximum_no_of_payments_per_period' => $subscription->maximumNoOfPaymentsPerPeriod, 'maximum_total_of_all_payment' => $subscription->maximumTotalOfAllPayment, 'payment_period' => $subscription->paymentPeriod, 'sender_email' => $subscription->senderEmail, 'is_pin_required' => $subscription->isPinRequired, 'fees_payer' => $subscription->feesPayer, 'fees_date' => $subscription->feesDate, 'create_date' => $subscription->createDate, 'update_time' => $subscription->updateTime );
		
		$subscription_id = ( int ) $subscription->subscriptionId;
		if ($subscription_id == 0) {
			$this->tableGateway->insert ( $data );
		} else {
			if ($this->getSubscription ( $subscription_id )) {
				$this->tableGateway->update ( $data, array ('subscription_id' => $subscription_id ) );
			} else {
				throw new \Exception ( 'Form subscription_id does not exist' );
			}
		}
		
		return $this->tableGateway->getLastInsertValue();
	}
	
	public function deleteSubscription($subscription_id) {
		$this->tableGateway->delete ( array ('subscription_id' => $subscription_id ) );
	}
}