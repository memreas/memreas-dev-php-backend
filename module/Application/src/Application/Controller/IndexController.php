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
use memreas\MemreasTranscoder;
use memreas\MemreasTranscoderTables;
use memreas\MemreasPayPal;
use memreas\MemreasPayPalTables;

class IndexController extends AbstractActionController
{
	//protected $url = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index_json.php";
	//protected $media_url = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/addmediaevent.php";
	//$this->url = MemreasConstants::ORIGINAL_URL;

	protected $url = MemreasConstants::ORIGINAL_URL;
	protected $media_url = MemreasConstants::MEDIA_URL;
	protected $user_id;
    protected $storage;
    protected $authservice;
    protected $userTable;
    protected $eventTable;
    protected $mediaTable;
    protected $eventmediaTable;
    protected $friendmediaTable;
    
	public function fetchXML($action, $xml) {
error_log("Inside fetchXML....");
		$guzzle = new Client();

error_log("Inside fetchXML this->url $this->url ....");
		$request = $guzzle->post(
			$this->url, 
			null, 
			array(
			'action' => $action,
			//'cache_me' => true,
    		'xml' => $xml
	    	)
		);
		$response = $request->send();
error_log("Inside fetchXML response $response ....");
		return $data = $response->getBody(true);
	}

    public function indexAction() {
error_log("Inside indexAction....");
	    $path = $this->security("application/index/paypal.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
    }

    public function moreAction() {
	    $path = $this->security("application/index/more.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
    }


    public function payPalListMassPayeeAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->payPalListMassPayee($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;	
	}
	
    public function paypalPayoutMassPayeesAction() {
error_log("Inside paypalPayoutMassPayeesAction..." . PHP_EOL);		
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalPayoutMassPayees($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;	
	}

    public function payPalAddSellerAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->payPalAddSeller($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;		  
     }
     public function paypalDecrementValueAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalDecrementValue($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;		  
     }

     public function paypalAddValueAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalAddValue($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;			
    }

    public function paypalListCardsAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalListCards($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;			
    }

     public function paypalDeleteCardsAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalDeleteCards($message_data, $memreas_paypal_tables, $this->getServiceLocator());
			
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;			
    }

    public function paypalAccountHistoryAction() {
error_log("Inside payPalAccountHistory...");

	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->paypalAccountHistory($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;			
    }
    
   public function paypalAction() {
	    $path = $this->security("application/index/paypal.phtml");

		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			$memreasPayPal = new MemreasPayPal();
			$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			$result = $memreasPayPal->storeCreditCard($message_data, $memreas_paypal_tables, $this->getServiceLocator());
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}

		return $view;			
    }

   public function transcoderAction() {

error_log("Inside transcoderAction" . PHP_EOL);
	    $path = $this->security("application/index/tcode.phtml");

		//Fetch the post data
		//$request = $this->getRequest();
		//$postData = $request->getPost()->toArray();
		//$username = $postData ['username'];
		//$password = $postData ['password'];

		$memreasTranscoder = new MemreasTranscoder();
		$memreas_transcoder_tables = new MemreasTranscoderTables($this->getServiceLocator());
		if(isset($_POST['json'])) {
			//Fetch from S3
			$result = $memreasTranscoder->exec($memreas_transcoder_tables, $this->getServiceLocator(), false);		
		} else if(!empty($_FILES)) {
			//Web direct upload
			//Memreas Transcoder related calls...
			$result = $memreasTranscoder->exec($memreas_transcoder_tables, $this->getServiceLocator(), true);
		}

		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			


//////
	    $path = $this->security("application/index/tcode.phtml");

/*
		if (isset($_REQUEST['callback'])) {
			//Fetch parms
			$callback = $_REQUEST['callback'];
			$json = $_REQUEST['json'];
			$jsonArr = json_decode($json, true);
			$actionname = $jsonArr['action'];
			$type = $jsonArr['type'];
			$message_data = $jsonArr['json'];

			//PayPal related calls...
			//$memreasPayPal = new MemreasPayPal();
			//$memreas_paypal_tables = new MemreasPayPalTables($this->getServiceLocator());
			//$result = $memreasPayPal->storeCreditCard($message_data, $memreas_paypal_tables, $this->getServiceLocator());
			
			$result = '{"Status":"Success"}';
				
			$json = json_encode($result);
			//Return the ajax call...
			$callback_json = $callback . "(" . $json . ")";
			$output = ob_get_clean();
			header("Content-type: plain/text");
			echo $callback_json;
			//Need to exit here to avoid ZF2 framework view.
			exit;
		} else {
			$view = new ViewModel();
			$view->setTemplate($path); // path to phtml file under view folder
		}
*/



		return $view;			
    }


    public function galleryAction() {
	    $path = $this->security("application/index/gallery.phtml");

		$action = 'listallmedia';
		$session = new Container('user');        
		$xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet('user_id') . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
		$result = $this->fetchXML($action, $xml);

		$view = new ViewModel(array('xml'=>$result));
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
        //return new ViewModel();
    }

    public function eventAction() {
	    $path = $this->security("application/index/event.phtml");

		$action = 'listallmedia';
		$session = new Container('user');        
		$xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet('user_id') . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
		$result = $this->fetchXML($action, $xml);

		$view = new ViewModel(array('xml'=>$result));
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
        //return new ViewModel();
    }

    public function shareAction() {
	    $path = $this->security("application/index/share.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
    }

    public function queueAction() {
	    $path = $this->security("application/index/queue.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
    }

    public function eventGalleryAction() {
	    $path = $this->security("application/index/event-gallery.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;			
    }

    public function memreasMeFriendsAction() {
	    $path = $this->security("application/index/memreas-me-friends.phtml");
		$view = new ViewModel();
		$view->setTemplate($path); // path to phtml file under view folder
		return $view;		
    }

    public function loginAction() {
error_log("Inside loginAction....");
		//Fetch the post data
		$request = $this->getRequest();
		$postData = $request->getPost()->toArray();
		$username = $postData ['username'];
		$password = $postData ['password'];

		//Setup the URL and action
		$action = 'login';
		$xml = "<xml><login><username>$username</username><password>$password</password></login></xml>";
		$redirect = 'paypal';
		
		//Guzzle the LoginWeb Service		
		$result = $this->fetchXML($action, $xml);
		$data = simplexml_load_string($result);

		//ZF2 Authenticate
		if ($data->loginresponse->status == 'success') {
error_log("Inside loginAction success....");
			$this->setSession($username);
            //Redirect here
			return $this->redirect()->toRoute('index', array('action' => $redirect));
		} else {
			return $this->redirect()->toRoute('index', array('action' => "index"));
		}
    }

    public function logoutAction() {
		$this->getSessionStorage()->forgetMe();
        $this->getAuthService()->clearIdentity();
        $session = new Container('user');
        $session->getManager()->destroy(); 
         
        $view = new ViewModel();
		$view->setTemplate('application/index/index.phtml'); // path to phtml file under view folder
		return $view;			
    }

    public function setSession($username) {
		//Fetch the user's data and store it in the session...
   	    $user = $this->getUserTable()->getUserByUsername($username);
        unset($user->password);
       	unset($user->disable_account);
   	    unset($user->create_date);
        unset($user->update_time);
		$session = new Container('user');        
		$session->offsetSet('user_id', $user->user_id);
		$session->offsetSet('username', $username);
        $session->offsetSet('user', json_encode($user));    
    }
     
    public function registrationAction()
    {
		//Fetch the post data
		$postData = $this->getRequest()->getPost()->toArray();
		$email = $postData ['email'];
		$username = $postData ['username'];
		$password = $postData ['password'];

		//Setup the URL and action
		$action = 'registration';
		$xml = "<xml><registration><email>$email</email><username>$username</username><password>$password</password></registration></xml>";
		$redirect = 'event';
		
		//Guzzle the Registration Web Service		
		$result = $this->fetchXML($action, $xml);
		$data = simplexml_load_string($result);

		//ZF2 Authenticate
		if ($data->registrationresponse->status == 'success') {
			$this->setSession($username);
			
			//If there's a profile pic upload it...
			if (isset($_FILES['file'])) { 
    	 		$file = $_FILES['file'];
		     	$fileName = $file['name'];
    	 		$filetype = $file['type'];
    		 	$filetmp_name = $file['tmp_name'];
	     		$filesize = $file['size'];
     	
				$guzzle = new Client();
				$session = new Container('user');        
				$request = $guzzle->post($media_url)
								->addPostFields(
									array(
										'user_id' => $session->offsetGet('user_id'),
										'filename' => $fileName,
										'event_id' => "",
										'device_id' => "",
										'is_profile_pic' => 1,
										'is_server_image' => 0,
									)
								)
								->addPostFiles(
									array(
										'f' => $filetmp_name,
									)
								);
			}
			$response = $request->send();
			$data = $response->getBody(true);
			$xml = simplexml_load_string($result);

			//ZF2 Authenticate
			error_log("addmediaevent result -----> " . $data);
			if ($xml->addmediaeventresponse->status == 'success') {
				//Do nothing even if it fails...
			}
			
            //Redirect here
			return $this->redirect()->toRoute('index', array('action' => $redirect));
		} else {
			return $this->redirect()->toRoute('index', array('action' => "index"));
		}
    }
    public function getUserTable() {
        if (!$this->userTable) {
            $sm = $this->getServiceLocator();
            $this->userTable = $sm->get('Admin\Model\UserTable');
        }
        return $this->userTable;
    }

    public function getAuthService() {
        if (!$this->authservice) {
            $this->authservice = $this->getServiceLocator()
                    ->get('AuthService');
        }

        return $this->authservice;
    }

    public function getSessionStorage() {
        if (!$this->storage) {
            $this->storage = $this->getServiceLocator()
                    ->get('admin\Model\MyAuthStorage');
        }

        return $this->storage;
    }

    public function security($path) {
    	//if already login do nothing
		$session = new Container("user");
	    if(!$session->offsetExists('user_id')){
			error_log("Not there so logout");
	    	$this->logoutAction();
    	  return "application/index/index.phtml";
	    }
		return $path;			
        //return $this->redirect()->toRoute('index', array('action' => 'login'));
    }
	
} // end class IndexController
