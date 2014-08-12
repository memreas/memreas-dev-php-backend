<?php

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Application\Model;
use Application\Model\UserTable;
use Application\Form;
use Guzzle\Http\Client;

use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerReceiver;
use Application\memreas\MemreasTranscoder;
use Application\memreas\MemreasTranscoderTables;
use Application\memreas\MemreasPayPal;
use Application\memreas\MemreasPayPalTables;

//echo "Hit deploy_test.php";
error_log ("Hit deploy_test.php".PHP_EOL);
//$path = $this->security("application/index/tcode.phtml");
 
//Fetch AWS Handle
$aws_manager = new AWSManagerReceiver($this->getServiceLocator());

//Fetch the post data
foreach (getallheaders() as $name => $value) {
	error_log("$name : $value\n", 0);
	/*
	 * SNS Topic Section - deprecated
	*/
	if ( $name == "x-amz-sns-message-type" ) {
		if ( $value == "SubscriptionConfirmation" ) {
			$inputJSON = file_get_contents('php://input');
			error_log($inputJSON, 0); // manually get the URL here and paste into browser to subscribe
			//Return the status code here so that we subscribe to the message - hopefully
			ob_start();
			http_response_code(200);
			ob_end_flush(); 	// Strange behaviour, will not work
			flush();            // Unless both are called !
			exit;
		} 
	}
}
?>