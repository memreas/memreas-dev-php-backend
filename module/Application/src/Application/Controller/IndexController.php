<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

// ///////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
// ///////////////////////////////
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Http\PhpEnvironment\Response;
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
use Application\memreas\Mlog;

class IndexController extends AbstractActionController {
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
		error_log ( "Inside fetchXML this->url $this->url ...." );
		$guzzle = new Client ();
		$request = $guzzle->post ( $this->url, null, array (
				'action' => $action,
				'xml' => $xml 
		) );
		$response = $request->send ();
		error_log ( "Inside fetchXML response $response ...." );
		return $data = $response->getBody ( true );
	}
	public function indexAction() {
		error_log ( "indexAction()...." . PHP_EOL );
		$actionname = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
		if ($actionname == "clearlog") {
			try {
				error_log("hi");
				$result = unlink ( getcwd () . '/php_errors.log' );
				$myfile = fopen(getcwd () . '/php_errors.log', "w") or die("Unable to open file!");
				$txt = "John Doe\n";
				fwrite($myfile, $txt);
				fclose($myfile);				
				Mlog::addone ( __CLASS__ . __METHOD__ . '::' . $actionname, "Log has been cleared!" );
				echo 'success';
			} catch (Exception $e) {
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
			exit ();
		} else {
			$this->transcoderAction ();
		}
		exit ();
	}
	public function transcoderAction() {
		error_log ( "transcoderAction()..." . PHP_EOL );
		
		// Web Server Handle
		$action = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
		$json = isset ( $_REQUEST ["json"] ) ? $_REQUEST ["json"] : '';
		$proceed = 0;
		$response = 'error - check action or json';
		if (($action) && ($json)) {
			$proceed = 1;
			$response = 'received';
		}
		if ($proceed) {
			$message_data = json_decode ( $json, true );
			
			// Fetch AWS Handle
			$aws_manager = new AWSManagerReceiver ( $this->getServiceLocator (), $message_data );
			
			Mlog::addone ( __CLASS__ . __METHOD__ . '$message_data', $message_data );
			$response = $aws_manager->memreasTranscoder->markMediaForTranscoding ( $message_data );
			$this->returnResponse ( $response );
			/**
			 * ****** background process starts here *******
			 */
			$result = $aws_manager->snsProcessMediaSubscribe ( $message_data );
			die ();
		}
	}
	public function returnResponse($response) {
		// buffer all upcoming output
		ignore_user_abort ( true ); // keeps php from stopping process
		ob_start ();
		header ( 'HTTP/1.0 200 OK' );
		header ( 'Content-Type: application/json' );
		echo json_encode ( $response );
		// get the size of the output
		$size = ob_get_length ();
		// send headers to tell the browser to close the connection
		// http_response_code ( 200 );
		header ( 'HTTP/1.0 200 OK' );
		header ( "Content-Length: $size" );
		header ( 'Connection: close');
		
		// flush all output
		ob_end_flush();
		ob_flush();
		flush();
		
		// if you're using sessions, this prevents subsequent requests
		// from hanging while the background process executes
		if (session_id()) {
			session_write_close();
		}
		
		//check headers
		if (headers_sent ()) {
			error_log ( "Success: response header 200 sucessfully sent" );
		} else {
			error_log ( "FAIL: response header 200 NOT sucessfully sent" );
		}
	}
} // end class IndexController
