<?php
namespace memreas;

use Zend\Session\Container;

//PayPal API
use PayPal\Api\CreditCard;
use PayPal\Api\CreditCardToken;
use PayPal\Api\Address;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Transaction as PayPal_Transaction;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Core\PPConfigManager;
use PayPal\Exception\PPConnectionException;
define('PP_CONFIG_PATH', dirname(__FILE__) . "/config/");

//memreas models
use memreas\DBProfiler;
use Application\Model\User;
use Application\Model\Account;
use Application\Model\AccountBalances;
use Application\Model\AccountDetail;
use Application\Model\PaymentMethod;
use Application\Model\Subscription;
use Application\Model\Transaction as Memreas_Transaction;
use Application\Model\TransactionReceiver;

class MemreasPayPal {

	// Link used for code
	// https://developer.paypal.com/webapps/developer/docs/api/#store-a-credit-card
	
	protected $user_id;
	protected $session;

	public function fetchSession() {
		if (!isset($this->session)) {
			$this->session = new Container('user');
			$this->user_id = $this->session->offsetGet('user_id');
		}
	}

	public function fetchPayPalCredential($service_locator) {
		//Fetch Session
		$this->fetchSession();

		//Fetch the PayPal credentials
		$token = "";
		$credential = "";
	    if(!$this->session->offsetExists('paypal_credential')){
			//Fetch an OAuth token...
			$config = $service_locator->get('Config');
			$client_id = $config['paypal_constants']['CLIENT_ID'];				
			$client_secret = $config['paypal_constants']['CLIENT_SECRET'];				
			$credential = new OAuthTokenCredential($client_id, $client_secret);
			$config = PPConfigManager::getInstance()->getConfigHashmap();
			$token = $credential->getAccessToken($config);
			$this->session->offsetSet('paypal_credential', $credential);
			$this->session->offsetSet('paypal_token', $token);
		} else {
			$credential = $this->session->offsetGet('paypal_credential');
		}
		return $credential;
	}


	public function payPalAccountHistory($message_data, $memreas_paypal_tables, $service_locator) 
	{
		//Fetch Session
		$this->fetchSession();

		//Fetch the user_name and id
		$user_name = $message_data['user_name'];
		$user = $memreas_paypal_tables->getUserTable()->getUserByUsername($user_name);

		//Fetch the user_id
	    if(isset($this->user_id)){

			//Fetch the Account
			$account = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($user->user_id);	
			if (!$account) {
				$result = array ( "Status"=>"Error", "Description"=>"Could not find account", );
				return $result;
			}		
			$account_id = $account->account_id;
			
			//Fetch the transactions
			$transactions =  $memreas_paypal_tables->getTransactionTable()->getTransactionByAccountId($account_id);
		
			//Debug...	
			$transactions_arr = array();
			foreach ($transactions as $row) {
				$row_arr = array();
     		   //echo $row->my_column . PHP_EOL;
     		   $row_arr["transaction_id"] = $row->transaction_id;
     		   $row_arr["account_id"] = $row->account_id;
     		   $row_arr["transaction_type"] = $row->transaction_type;
     		   $row_arr["pass_fail"] = $row->pass_fail;
     		   $row_arr["amount"] = $row->amount;
     		   $row_arr["currency"] = $row->currency;
     		   $row_arr["transaction_request"] = $row->transaction_request;
     		   $row_arr["transaction_response"] = $row->transaction_response;
     		   $row_arr["transaction_sent"] = $row->transaction_sent;
     		   $row_arr["transaction_receive"] = $row->transaction_receive;
     		   $transactions_arr[] = $row_arr;
			}
			
			//Return a success message:
			$result = array (
				"Status"=>"Success",
				"account"=>$account,
				"transactions"=>$transactions_arr,
				);
			return $result;
		}

		//Return an error message:
		$result = array (
			"Status"=>"Error",
			"Description"=>"$user_id not found"
			);
		return $result;
	}

	public function paypalDecrementValue($message_data, $memreas_paypal_tables, $service_locator) 
	{
		//Fetch Session
		$this->fetchSession();
		//Get the data from the form
		$seller = $message_data['seller'];
		$memreas_master = $message_data['memreas_master'];
		$amount = $message_data['amount'];

		//////////////////////////
		//Fetch the Buyer Account
		//////////////////////////
		$account = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($this->user_id);	
		if (!$account) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find account", );
			return $result;
		}		
		$account_id = $account->account_id;
		
		//Decrement the user's account
		//Fetch Account_Balances 
		$currentAccountBalance = $memreas_paypal_tables->getAccountBalancesTable()->getAccountBalances($account_id);
		//If no acount found set the starting balance to zero else use the ending balance.
		if (!isset($currentAccountBalance)) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find account_balances", );
			return $result;
		}		

		//Log the transaction
		$now = date('Y-m-d H:i:s');
		$memreas_transaction  = new Memreas_Transaction;
		$memreas_transaction->exchangeArray(array(
				'account_id'=>$account_id,
				'transaction_type' =>'decrement_value_from_account',
				'pass_fail' => 1,
				'amount' => "-$amount",
				'currency' => 'USD',
				'transaction_request' => "N/a",
				'transaction_sent' =>$now,
				'transaction_response' => "N/a",
				'transaction_receive' =>$now,	
		));
		$transaction_id = $memreas_paypal_tables->getTransactionTable()->saveTransaction($memreas_transaction);

		//Decrement the account		
		$starting_balance = $currentAccountBalance->ending_balance;
		$ending_balance = $starting_balance - $amount;

		//Insert the new account balance
		$now = date('Y-m-d H:i:s');
		$endingAccountBalance = new AccountBalances();
		$endingAccountBalance->exchangeArray(array(
			'account_id' => $account_id,
			'transaction_id' => $transaction_id, 
			'transaction_type' => "decrement_value_from_account", 
			'starting_balance' => $starting_balance, 
			'amount' => "-$amount", 
			'ending_balance' => $ending_balance, 
			'create_time' => $now,
			));
		$transaction_id = $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($endingAccountBalance);

		//Update the account table
		$now = date('Y-m-d H:i:s');
		$account = $memreas_paypal_tables->getAccountTable()->getAccount($account_id);
		$account->exchangeArray(array(
			'balance' => $ending_balance, 
			'update_time' => $now, 
			));
		$account_id = $memreas_paypal_tables->getAccountTable()->saveAccount($account);
		
		//////////////////////////
		//Fetch the Seller Account
		//////////////////////////
		$seller_user = $memreas_paypal_tables->getUserTable()->getUserByUsername($seller);
		$seller_user_id = $seller_user->user_id;
		$seller_account = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($seller_user_id);	
		if (!$seller_account) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find seller account", );
			return $result;
		}		
		$seller_account_id = $seller_account->account_id;
		
		//Increment the seller's account by 80% of the purchase
		//Fetch Account_Balances 
		$currentAccountBalance = $memreas_paypal_tables->getAccountBalancesTable()->getAccountBalances($seller_account_id);
		//If no acount found set the starting balance to zero else use the ending balance.
		if (!isset($currentAccountBalance)) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find account_balances", );
			return $result;
		}		

		//Log the transaction
		$now = date('Y-m-d H:i:s');
		$memreas_transaction  = new Memreas_Transaction;
		$seller_amount = $amount * 0.8;
		$memreas_master_amount = $amount - $seller_amount;
		
		$memreas_transaction->exchangeArray(array(
				'account_id'=>$seller_account_id,
				'transaction_type' =>'increment_value_to_account',
				'pass_fail' => 1,
				'amount' => $seller_amount,
				'currency' => 'USD',
				'transaction_request' => "N/a",
				'transaction_sent' =>$now,
				'transaction_response' => "N/a",
				'transaction_receive' =>$now,	
		));
		$transaction_id = $memreas_paypal_tables->getTransactionTable()->saveTransaction($memreas_transaction);

		//Increment the account		
		$starting_balance = $currentAccountBalance->ending_balance;
		$ending_balance = $starting_balance + $seller_amount;

		//Insert the new account balance
		$now = date('Y-m-d H:i:s');
		$endingAccountBalance = new AccountBalances();
		$endingAccountBalance->exchangeArray(array(
			'account_id' => $account_id,
			'transaction_id' => $transaction_id, 
			'transaction_type' => "increment_value_to_account", 
			'starting_balance' => $starting_balance, 
			'amount' => "$seller_amount", 
			'ending_balance' => $ending_balance, 
			'create_time' => $now,
			));
		$transaction_id = $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($endingAccountBalance);

		//Update the account table
		$now = date('Y-m-d H:i:s');
		$account = $memreas_paypal_tables->getAccountTable()->getAccount($account_id);
		$account->exchangeArray(array(
			'balance' => $ending_balance, 
			'update_time' => $now, 
			));
		$account_id = $memreas_paypal_tables->getAccountTable()->saveAccount($account);



		//////////////////////////
		//Fetch the memreas_master Account
		//////////////////////////
		$memreas_master_user = $memreas_paypal_tables->getUserTable()->getUserByUsername($memreas_master);
		$memreas_master_user_id = $memreas_master_user->user_id;
		$memreas_master_account = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($memreas_master_user_id);	
		if (!$memreas_master_account) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find memreas_master account", );
			return $result;
		}		
		$memreas_master_account_id = $memreas_master_account->account_id;

		
		//Increment the memreas_master account by 20% of the purchase
		//Fetch Account_Balances 
		$currentAccountBalance = $memreas_paypal_tables->getAccountBalancesTable()->getAccountBalances($memreas_master_account_id);
		//If no acount found set the starting balance to zero else use the ending balance.
		if (!isset($currentAccountBalance)) {
			$result = array ( "Status"=>"Error", "Description"=>"Could not find account_balances", );
			return $result;
		}		

		//Log the transaction
		$now = date('Y-m-d H:i:s');
		$memreas_transaction  = new Memreas_Transaction;
		
		$memreas_transaction->exchangeArray(array(
				'account_id'=>$memreas_master_account_id,
				'transaction_type' =>'increment_value_to_account',
				'pass_fail' => 1,
				'amount' => $memreas_master_amount,
				'currency' => 'USD',
				'transaction_request' => "N/a",
				'transaction_sent' =>$now,
				'transaction_response' => "N/a",
				'transaction_receive' =>$now,	
		));
		$transaction_id = $memreas_paypal_tables->getTransactionTable()->saveTransaction($memreas_transaction);

		//Increment the account		
		$starting_balance = $currentAccountBalance->ending_balance;
		$ending_balance = $starting_balance + $memreas_master_amount;

		//Insert the new account balance
		$now = date('Y-m-d H:i:s');
		$endingAccountBalance = new AccountBalances();
		$endingAccountBalance->exchangeArray(array(
			'account_id' => $memreas_master_account_id,
			'transaction_id' => $transaction_id, 
			'transaction_type' => "increment_value_to_account", 
			'starting_balance' => $starting_balance, 
			'amount' => "$memreas_master_amount", 
			'ending_balance' => $ending_balance, 
			'create_time' => $now,
			));
		$transaction_id = $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($endingAccountBalance);

		//Update the account table
		$now = date('Y-m-d H:i:s');
		$account = $memreas_paypal_tables->getAccountTable()->getAccount($memreas_master_account_id);
		$account->exchangeArray(array(
			'balance' => $ending_balance, 
			'update_time' => $now, 
			));
		$account_id = $memreas_paypal_tables->getAccountTable()->saveAccount($account);


error_log("account_id ----> $account_id" . PHP_EOL);
error_log("seller_account_id ----> $seller_account_id" . PHP_EOL);
error_log("memreas_master_account_id ----> $memreas_master_account_id" . PHP_EOL);

		

		//Return an error message:
		$result = array (
			"Status"=>"Success",
			"Description"=>"Decremented value $amount from account",
			"starting_balance"=>$starting_balance,
			"amount"=>"-$amount",
			"ending_balance"=>$ending_balance,
			);
		return $result;
				
	}

	public function paypalAddValue($message_data, $memreas_paypal_tables, $service_locator) 
	{
		//Fetch Session
		$this->fetchSession();

		//Fetch PayPal credential
		$credential = $this->fetchPayPalCredential($service_locator);
		//Setup an api context for the card
		$api_context = new ApiContext($credential);
				
		//Get the data from the form
		$paypal_card_reference_id = $message_data['paypal_card_reference_id'];
		$amount = $message_data['amount'];

		//Fetch the address 
		$account_detail = $memreas_paypal_tables->getAccountDetailTable()->getAccountDetailByPayPalReferenceId($paypal_card_reference_id);
		$credit_card = CreditCard::get($paypal_card_reference_id);
		
		//Must use card token for stored cards.
		$credit_card_token = new CreditCardToken();
		$credit_card_token->setCreditCardId($credit_card->getId());
		$credit_card_token->setPayerId($this->user_id);
		//Set the funding instrument (credit card)
		$fi = new FundingInstrument();
		$fi->setCreditCardToken($credit_card_token);

		//Set the Payer
		$payer = new Payer();
		$payer->setPayment_method('credit_card');
		$payer->setFunding_instruments(array($fi));

		//Set the amount details.
		$details = new Details();
		$details->setShipping('0.00');
		$details->setSubtotal($amount);
		$details->setTax('0.00');
		$details->setFee('0.00');
		
		//Set the amount.
		$paypal_amount = new Amount();
		$paypal_amount->setCurrency('USD');
		$paypal_amount->setTotal($amount);
		$paypal_amount->setDetails($details);

		$paypal_transaction = new PayPal_Transaction;
		$paypal_transaction->setAmount($paypal_amount);
		$paypal_transaction->setDescription('Adding $amount value to account');

		$payment = new Payment();
		$payment->setIntent('sale');
		$payment->setPayer($payer);
		$payment->setTransactions(array($paypal_transaction));

		//Store the transaction before sending
		$now = date('Y-m-d H:i:s');
		$memreas_transaction  = new Memreas_Transaction;
		$memreas_transaction->exchangeArray(array(
				'account_id'=>$account_detail->account_id,
				'transaction_type' =>'add_value_to_account',
				'transaction_request' => $payment->toJSON(),
				'transaction_sent' =>$now
		));
		$transaction_id =  $memreas_paypal_tables->getTransactionTable()->saveTransaction($memreas_transaction);
		
		/////////////////////////
		// PayPal Payment Request
		/////////////////////////
		try {
			$payment->create();
		} catch (PPConnectionException $ex) {
			$result = array (
				"Status"=>"Error",
				"Description"=>"$ex->getMessage()",
			);
			return $result;		  
		}			

		//Update the transaction table with the PayPal response
		//$transaction  = new Transaction();
		$now = date('Y-m-d H:i:s');
		$memreas_transaction->exchangeArray(array(
				'transaction_id' => $transaction_id,
				'pass_fail' => 1,
				'amount' => $amount,
				'currency' => 'USD',
				'transaction_response' => $payment->toJSON(),
				'transaction_receive' =>$now	
		));
		$transaction_id = $memreas_paypal_tables->getTransactionTable()->saveTransaction($memreas_transaction);

		//Get the last balance
		$currentAccountBalance = $memreas_paypal_tables->getAccountBalancesTable()->getAccountBalances($account_detail->account_id);
		//If no acount found set the starting balance to zero else use the ending balance.
		$starting_balance = (isset($currentAccountBalance)) ? $currentAccountBalance->ending_balance : '0.00';
		$ending_balance = $starting_balance + $amount;
		
		//Insert the new account balance
		$now = date('Y-m-d H:i:s');
		$endingAccountBalance = new AccountBalances();
		$endingAccountBalance->exchangeArray(array(
			'account_id' => $account_detail->account_id,
			'transaction_id' => $transaction_id, 
			'transaction_type' => "add_value_to_account", 
			'starting_balance' => $starting_balance, 
			'amount' => $amount, 
			'ending_balance' => $ending_balance, 
			'create_time' => $now,
			));
		$transaction_id = $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($endingAccountBalance);

		//Return an error message:
		$result = array (
			"Status"=>"Success",
			"Description"=>"Added value $amount to card",
			"starting_balance"=>$starting_balance,
			"amount"=>$amount,
			"ending_balance"=>$ending_balance,
			);
		return $result;
	}
	
	public function paypalDeleteCards($message_data, $memreas_paypal_tables, $service_locator) 
	{

		//Fetch Session
		$this->fetchSession();

		//Fetch PayPal credential
		$credential = $this->fetchPayPalCredential($service_locator);
		//Setup an api context for the card
		$api_context = new ApiContext($credential);
				
		//Delete the card at PayPal and update the database
		$arr = array();
		foreach($message_data as $card) {
			$creditCard = CreditCard::get($card, $api_context);
			try {
				$creditCard->delete($api_context);
				$arr[] = "$card";
				//$row = $memreas_paypal_tables->getPaymentMethodTable()->getPaymentMethodByPayPalReferenceId($card);	
				//Delete Payment Method Table
				$memreas_paypal_tables->getPaymentMethodTable()->deletePaymentMethodByPayPalCardReferenceId($card);
				//Delete Account Detail Table (associated billing address)
				$memreas_paypal_tables->getAccountDetailTable()->deleteAccountDetailByPayPalCardReferenceId($card);
			} catch (\PPConnectionException $ex) {
	  			$result = array (
					"Status"=>"Error",
					"Description"=>$ex->getMessage()
				);
			  return $result;
			}		
		}		

		$result = array (
			"Status"=>"Success",
			"Description"=>"Deleted the following cards at PayPal",
			"Deleted_Cards"=>$arr,
			);

		return $result;
	}

	public function paypalListCards($message_data, $memreas_paypal_tables, $service_locator) 
	{

		//Fetch Session
		$this->fetchSession();

		//Fetch the user_id
	    if($this->session->offsetExists('user_id')){

			$this->user_id = $this->session->offsetGet('user_id');
			//Fetch the users's list of cards from the database
			$payment_method = new PaymentMethod();
			
			$rowset = $memreas_paypal_tables->getPaymentMethodTable()->getPaymentMethodsByUserId($this->user_id);	
			//$rowset = $memreas_paypal_tables->getPaymentMethodTable()->getPaymentMethodByUserId($account_id);	
			$rowCount = count($rowset);
			$payment_methods = array();
			foreach ($rowset as $row) {
					$payment_method_result = array();
					$payment_method_result['payment_method_id'] = $row['payment_method_id'];
					$payment_method_result['account_id'] = $row['account_id'];
					$payment_method_result['user_id'] = $row['user_id'];
					$payment_method_result['paypal_card_reference_id'] = $row['paypal_card_reference_id'];
					$payment_method_result['card_type'] = $row['card_type'];
					$payment_method_result['obfuscated_card_number'] = $row['obfuscated_card_number'];
					$payment_method_result['exp_month'] = $row['exp_month'];
					$payment_method_result['valid_until'] = $row['valid_until'];
					$payment_methods[] = $payment_method_result;
			}

 			$str="";
 			$status="Error";
			if ($rowCount > 0) {
			    $str = "found $rowCount rows";
			    $status = "Success";
			} else {
			    $str = 'no rows matched the query';
			}		

			//Return a success message:
			$result = array (
				"Status"=>$status,
				"NumRows"=>"$str",
				"payment_methods"=>$payment_methods,
				);
			return $result;
		}
		//Return an error message:
		$result = array (
			"Status"=>"Error",
			"Description"=>"$user_id not found"
			);
		return $result;
	}

	public function storeCreditCard($message_data, $memreas_paypal_tables, $service_locator) 
	{

		//Fetch Session
		$this->fetchSession();
		//Fetch PayPal credential
		$credential = $this->fetchPayPalCredential($service_locator);
		//Setup an api context for the card
		$api_context = new ApiContext($credential);

		//Store the card with PayPal
		$card = new CreditCard();
		$card->setType(strtolower($message_data['credit_card_type']));
		$card->setNumber($message_data['credit_card_number']);
		$card->setExpire_month($message_data['expiration_month']);
		$card->setExpire_year($message_data['expiration_year']);
		//$card->setCvv2("012");
		$card->setFirst_name($message_data['first_name']);
		$card->setLast_name($message_data['last_name']);			
		$card->setPayer_Id($this->user_id);
		
		//Get the Billing Address and associate it with the card
		$billing_address = new Address();
		$billing_address->setLine1($message_data['address_line_1']);
		$billing_address->setLine2($message_data['address_line_2']);
		$billing_address->setCity($message_data['city']);
		$billing_address->setState($message_data['state']);
		$billing_address->setPostalCode($message_data['zip_code']);
		$billing_address->setPostal_code($message_data['zip_code']);
		$billing_address->setCountryCode("US");
		$billing_address->setCountry_code("US");
		$card->setBillingAddress($billing_address);
		$card->setBilling_address($billing_address);

		//Associate the user with the card
		$card->setPayer_Id($this->user_id);

		//Store the data before the call
		//Check for an existing account 
		
		//Fetch the Account
		$row = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($this->user_id);	
		if (!$row) {
			//Create an account entry
			$now = date('Y-m-d H:i:s');
			$account  = new Account();
			$account->exchangeArray(array(
				'user_id' => $this->user_id,
				'account_type' => 'buyer',
				'balance' => 0,
				'create_time' => $now,
				'update_time' => $now
			));
			$account_id =  $memreas_paypal_tables->getAccountTable()->saveAccount($account);
		} else {
			$account_id = $row->account_id;
		}
		
		$accountDetail  = new AccountDetail();
		$accountDetail->exchangeArray(array(
			'account_id'=>$account_id,
			'first_name'=>$message_data['first_name'],
			'last_name'=>$message_data['last_name'],
			'address_line_1'=>$message_data['address_line_1'],
			'address_line_2'=>$message_data['address_line_2'],
			'city'=>$message_data['city'],
			'state'=>$message_data['state'],
			'zip_code'=>$message_data['zip_code'],
			'postal_code'=>$message_data['zip_code'],
			));
		$account_detail_id = $memreas_paypal_tables->getAccountDetailTable()->saveAccountDetail($accountDetail);

		//Store the transaction that is sent to PayPal
		$now = date('Y-m-d H:i:s');
		$transaction  = new Memreas_Transaction();
		$transaction->exchangeArray(array(
				'account_id'=>$account_id,
				'transaction_type' =>'store_credit_card',
				'transaction_request' => $card->toJSON(),
				'transaction_sent' =>$now
		));

		$transaction_id =  $memreas_paypal_tables->getTransactionTable()->saveTransaction($transaction);

		//DEBUG - Data queries (need to make config change to enable
		//$profiler = new DBProfiler();
		//$profiler->logQueries($this->getServiceLocator()); 


		try {
			$card->create($api_context); 
		} catch (PPConnectionException $ex) {
		  echo "Exception:" . $ex->getMessage() . PHP_EOL;
		  var_dump($ex->getData());
		  exit(1);
		}			

		//Update the transaction table with the PayPal response
		//$transaction  = new Transaction();
		$transaction->exchangeArray(array(
				'transaction_id' => $transaction_id,
				'pass_fail' => 1,
				'transaction_response' => $card->toJSON(),
				'transaction_receive' =>$now	
		));
		$transactionid =  $memreas_paypal_tables->getTransactionTable()->saveTransaction($transaction);

		//$accountDetail  = new AccountDetail();
		$accountDetail->exchangeArray(array(
			'account_detail_id'=>$account_detail_id,
			'paypal_card_reference_id'=> $card->getId(),
			));
		$account_detail_id = $memreas_paypal_tables->getAccountDetailTable()->saveAccountDetail($accountDetail);

		//Add the new payment method
		$payment_method  = new PaymentMethod();
		$payment_method->exchangeArray(array(
			'account_id' => $account_id, 
			'paypal_card_reference_id' => $card->getId(), 
			'card_type' => $card->getType(), 
			'obfuscated_card_number' => $card->getNumber(), 
			'exp_month' => $card->getExpireMonth(), 
			'exp_year' => $card->getExpireYear(), 
			'valid_until' => $card->getValidUntil(), 
			'create_time' => $now,
			'update_time' => $now
		));
		$payment_method_id =  $memreas_paypal_tables->getPaymentMethodTable()->savePaymentMethod($payment_method);


/*
		//Insert account balances as needed
		$account_balances  = new AccountBalances();
		$account_balances->exchangeArray(array(
			'account_id' => $account_id, 
			'transaction_id' => $transaction_id, 
			'transaction_type' => 'store_card', 
			'starting_balance' => 0, 
			'amount' => 0, 
			'ending_balance' => 0, 
			'create_time' => $now
		));
		$account_balances_id =  $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($account_balances);
*/
		//Return a success message:
		$result = array (
			"Status"=>"Success",
			"paypal_card_id"=> $card->getId(),
			"account_id"=> $account_id,
			"account_detail_id"=>$account_detail_id,
			"transaction_id"=>$transactionid,
			"account_balances_id"=>$account_balances_id,
			"payment_method_id"=>$payment_method_id,
			);
			
		return $result;
	}

	public function payPalAddSeller($message_data, $memreas_paypal_tables, $service_locator) 
	{
		//Fetch Session
		$this->fetchSession();
		//Get memreas user name
		$user_name = $message_data['user_name'];
		$user = $memreas_paypal_tables->getUserTable()->getUserByUsername($user_name);
		//Get Paypal email address
		$paypal_email_address = $message_data['paypal_email_address'];
		//Get the Billing Address and associate it with the card
		$billing_address = new Address();
		$billing_address->setLine1($message_data['address_line_1']);
		$billing_address->setLine2($message_data['address_line_2']);
		$billing_address->setCity($message_data['city']);
		$billing_address->setState($message_data['state']);
		$billing_address->setPostalCode($message_data['zip_code']);
		$billing_address->setPostal_code($message_data['zip_code']);
		$billing_address->setCountryCode("US");
		$billing_address->setCountry_code("US");

		//Fetch the Account
		$row = $memreas_paypal_tables->getAccountTable()->getAccountByUserId($user->user_id);	
		if (!$row) {
			//Create an account entry
			$now = date('Y-m-d H:i:s');
			$account  = new Account();
			$account->exchangeArray(array(
				'user_id' => $user->user_id,
				'account_type' => 'seller',
				'balance' => 0,
				'create_time' => $now,
				'update_time' => $now
			));
			$account_id =  $memreas_paypal_tables->getAccountTable()->saveAccount($account);
		} else {
			$account_id = $row->account_id;
			//Return a success message:
			$result = array (
				"Status"=>"Failure",
				"account_id"=> $account_id,
				"Error"=>"Seller already exists",
			);
			return $result;
		}
		
		$accountDetail  = new AccountDetail();
		$accountDetail->exchangeArray(array(
			'account_id'=>$account_id,
			'first_name'=>$message_data['first_name'],
			'last_name'=>$message_data['last_name'],
			'address_line_1'=>$message_data['address_line_1'],
			'address_line_2'=>$message_data['address_line_2'],
			'city'=>$message_data['city'],
			'state'=>$message_data['state'],
			'zip_code'=>$message_data['zip_code'],
			'postal_code'=>$message_data['zip_code'],
			'paypal_email_address'=>$message_data['paypal_email_address'],
			));
		$account_detail_id = $memreas_paypal_tables->getAccountDetailTable()->saveAccountDetail($accountDetail);

		//Store the transaction that is sent to PayPal
		$now = date('Y-m-d H:i:s');
		$transaction  = new Memreas_Transaction();
		$transaction->exchangeArray(array(
				'account_id'=>$account_id,
				'transaction_type' =>'add_seller',
				'transaction_request' => "N/a",
				'pass_fail' => 1,
				'transaction_sent' =>$now,
				'transaction_receive' =>$now	
		));
		$transaction_id =  $memreas_paypal_tables->getTransactionTable()->saveTransaction($transaction);

		//Insert account balances as needed
		$account_balances  = new AccountBalances();
		$account_balances->exchangeArray(array(
			'account_id' => $account_id, 
			'transaction_id' => $transaction_id, 
			'transaction_type' => 'add seller', 
			'starting_balance' => 0, 
			'amount' => 0, 
			'ending_balance' => 0, 
			'create_time' => $now
		));
		$account_balances_id =  $memreas_paypal_tables->getAccountBalancesTable()->saveAccountBalances($account_balances);

		//Return a success message:
		$result = array (
			"Status"=>"Success",
			"account_id"=> $account_id,
			"account_detail_id"=>$account_detail_id,
			"transaction_id"=>$transaction_id,
			"account_balances_id"=>$account_balances_id,
		);
			
		return $result;
	}

}
?>
