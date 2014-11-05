<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////

namespace Application\Controller;

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

class IndexController extends AbstractActionController
{
	protected $url;
	protected $media_url;
	protected $user_id;
    protected $storage;
    protected $authservice;
    protected $userTable;
    protected $eventTable;
    protected $mediaTable;
    protected $eventmediaTable;
    protected $friendmediaTable;
    
	public function fetchXML($action, $xml) {
error_log("Inside fetchXML this->url $this->url ....");
		$guzzle = new Client();
		$request = $guzzle->post(
			$this->url, 
			null, 
			array(
			'action' => $action,
    		'xml' => $xml
	    	)
		);
		$response = $request->send();
error_log("Inside fetchXML response $response ....");
		return $data = $response->getBody(true);
	}

    public function indexAction() {
error_log("indexAction()....".PHP_EOL);
    	$this->transcoderAction();
    	exit;
    }

    public function transcoderAction() {
error_log("transcoderAction()...".PHP_EOL);
    	 
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
				} elseif ( $value == "Notification" ) {
					if ( isset($_REQUEST['guzzle']) ) {
						$message_data = json_decode($_REQUEST['json'], true);
					} else {
						$inputJSON = file_get_contents('php://input');
error_log("inputJSON...... $inputJSON");
						$input= json_decode($inputJSON, true); 
						//Fetch the json from message
						$message_data = json_decode($input['Message'], true);
					}
					//Return the status code here so that the SNS topic won't keep resending the message
					ob_start();
					http_response_code(200);
					ob_end_flush(); 	// Strange behaviour, will not work
					flush();            // Unless both are called !

					//Process Message here - 
					$result = $aws_manager->snsProcessMediaSubscribe ($message_data);

					return $result;
				} //End else if "notification"
			//} else if (($name == 'User-Agent') && ($value == 'aws-sqsd')) {
			} else if (($name == 'X-Aws-Sqsd-Msgid') && (!empty($value))) {
				
				/*
				 * SQS Worker Tier Section
				 */
error_log("Inside transcoderAction:isset('User-Agent') " . PHP_EOL);
				
					$inputJSON = file_get_contents('php://input');
error_log("inputJSON...... $inputJSON");

					//Fetch the json from message
					$message_data= json_decode($inputJSON, true);
error_log("**************************************");
					//Return the status code here so that the SNS topic won't keep resending the message
					ob_start();
					
					http_response_code(200);
					ob_end_flush(); 	// Strange behaviour, will not work
					flush();            // Unless both are called !
					
					/* send header and flush */
					//header("HTTP/1.1 200 OK");
					//ob_end_flush();
					if (headers_sent() ) { 
						error_log("Success: response header 200 sucessfully sent"); 
					} else { 
						error_log("FAIL: response header 200 NOT sucessfully sent"); 
					}
					//Process Message here -
					$result = $aws_manager->snsProcessMediaSubscribe ($message_data);
					
					return $result;
			} //End else if (($name == 'User-Agent') && ($value == 'aws-sqsd'))
		} //End foreach (getallheaders() as $name => $value)			
			
    }

} // end class IndexController
