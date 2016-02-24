<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Controller;

use Application\memreas\AWSManagerAutoScaler;
use Application\memreas\AWSManagerReceiver;
use Application\memreas\CheckGitPull;
use Application\memreas\Mlog;
use GuzzleHttp\Client;
use Zend\Mvc\Controller\AbstractActionController;

class IndexController extends AbstractActionController {
	protected $dbAdapter = null;
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
	protected $awsManagerAutoScaler;
	protected $checkGitPull;
	protected $aws_manager;
	public function fetchXML($action, $xml) {
		$guzzle = new \GuzzleHttp\Client ();
		$response = $guzzle->post ( $this->url, [ 
				'form_params' => [ 
						'action' => $action,
						'cache_me' => true,
						'xml' => $xml 
				] 
		] );
		
		return $response->getBody ();
	}
	public function indexAction() {
		Mlog::addone ( __CLASS__ . __METHOD__ . '::$_SERVER-->', $_SERVER );
		// Mlog::addone ( __CLASS__ . __METHOD__, '::entered indexAction....' );
		$actionname = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
		
		if ($actionname == "gitpull") {
			$this->checkGitPull = new CheckGitPull ();
			$this->checkGitPull->exec ();
			Mlog::addone ( __CLASS__ . __METHOD__, '::entered gitpull processing' );
			$gitpull = true;
			echo $this->checkGitPull->exec ( $gitpull );
			exit ();
		} else if ($actionname == "clearlog") {
			try {
				$filename = getcwd () . '/php_errors.log';
				// $result = unlink ( $filename );
				file_put_contents ( $filename, '' );
				Mlog::addone ( __CLASS__ . __METHOD__ . '::' . $actionname, "Log has been cleared!" );
				echo 'success';
			} catch ( Exception $e ) {
				echo 'Caught exception: ', $e->getMessage (), "\n";
			}
			exit ();
		} else if ($actionname == "wakeup") {
			try {
				Mlog::addone ( __CLASS__ . __METHOD__, "wakeup called" );
				
				//
				// Return response
				//
				$this->returnResponse ( "processing" );
				
				//
				// Fetch AWS Manager
				//
				$this->aws_manager = new AWSManagerReceiver ( $this->getServiceLocator () );
				
				//
				// Check Instance against AutoScaler
				//
				$this->awsManagerAutoScaler = new AWSManagerAutoScaler ( $this->getServiceLocator (), $this->aws_manager );
				
				//
				// Send email notification
				//
				$this->aws_manager->sesEmailErrorToAdmin ( __CLASS__ . __METHOD__ . __LINE__ . "::wakeup called for " . $this->awsManagerAutoScaler->server_name, "new server added::" . $this->awsManagerAutoScaler->server_name );
				
				//
				// Start processing backlog - wakeup call...
				//
				$this->processBacklog ();
			} catch ( Exception $e ) {
				Mlog::addone ( __CLASS__ . __METHOD__ . '::Caught exception', $e->getMessage () );
			}
		} else {
			//
			// Backend worker - base call user initiated upload
			//
			Mlog::addone ( __CLASS__ . __METHOD__, '::entered indexAction backend worker processing' );
			try {
				//
				// Check Instance against AutoScaler
				//
				$this->awsManagerAutoScaler = new AWSManagerAutoScaler ( $this->getServiceLocator (), $this->aws_manager );
				
				/*
				 * If need server launch, guzzle to start,
				 * and set transaction as pending
				 */
				$this->transcoderAction ();
			} catch ( Exception $e ) {
				Mlog::addone ( __CLASS__ . __METHOD__ . '::Caught exception', $e->getMessage () );
			}
		}
		exit ();
	}
	protected function transcoderAction() {
		// Mlog::addone ( __CLASS__ . __METHOD__, '::entered transcoderAction...' );
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
			//
			// Mark all entries as backlog until server is ready to process for
			// single thread ffmpeg
			//
			$message_data = json_decode ( $json, true );
			$message_data ['backlog'] = 1;
			Mlog::addone ( __CLASS__ . __METHOD__ . '$message_data as json', $json );
			//
			// Here if no media_id is set then work on any backlog items...
			//
			$this->aws_manager = new AWSManagerReceiver ( $this->getServiceLocator () );
			if (! empty ( $message_data ['media_id'] )) {
				$response = $this->aws_manager->memreasTranscoder->markMediaForTranscoding ( $message_data );
			} else {
				throw new \Exception ( "Transcoder::media_id is empty!" );
			}
			$this->returnResponse ( $response );
			
			/*
			 * -
			 * ****** background process starts here *******
			 * ** process task if cpu < 75% usage
			 * ** after completing task fetch another
			 */
			/*
			 * -
			 * Process initial message - no longer necessary all messages
			 * backlog
			 */
			Mlog::addone ( __CLASS__ . __METHOD__ . '::$message_data', $message_data );
			if (! $this->awsManagerAutoScaler->serverReadyToProcessTask ()) {
				//
				// end process here is already a process operating on the
				// backlog
				//
				Mlog::addone ( __CLASS__ . __METHOD__, '::getmypid()::' . getmypid () . ' exiting...' );
				exit ();
			} else {
				$this->processBacklog ();
			}
			
			//
			// If the while finished we release the lock
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->awsManagerAutoScaler->releaseTranscodeingProcessHandleFromRedis()::', 'lock release for pid::' . getmypid () );
			$this->awsManagerAutoScaler->releaseTranscodeingProcessHandleFromRedis ();
			
			exit ();
			// At this point it's time to exit. The while loop is finished
			// and/or the pid doesn't match.
			//
		}
	}
	protected function processBacklog() {
		//
		// Fetch $this->aws_manager
		//
		$this->aws_manager = new AWSManagerReceiver ( $this->getServiceLocator () );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Fetched $this->aws_manager for pid' . getmypid () );
		while ( $this->awsManagerAutoScaler->serverReadyToProcessTask () || $this->aws_manager->checkForHighPriorityEntry () ) {
			//
			// Process is running and has lock
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Top of while loop Process has lock pid::' . getmypid () );
			
			try {
				
				if (! $this->getServiceLocator ()) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'getServiceLocator is empty - try to init' );
					$this->setServiceLocator ( new \Zend\ServiceManager\ServiceManager () );
				}
				//
				// Fetch $this->aws_manager
				//
				$this->aws_manager = new AWSManagerReceiver ( $this->getServiceLocator () );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Fetched $this->aws_manager for pid' . getmypid () );
				
				//
				// Fetch next backlog entry
				//
				$message_data = $this->aws_manager->fetchBackLogEntry ( $this->awsManagerAutoScaler->server_name );
				if (empty ( $message_data )) {
					Mlog::addone ( __CLASS__ . __METHOD__ . '$this->fetchBackLogEntry()', ' returned null - processing complete!' );
					$this->awsManagerAutoScaler->releaseTranscodingProcessHandleFromRedis ();
					exit ();
				} else {
					/*
					 * Process backlog messages
					 */
					// Mlog::addone ( __CLASS__ . __METHOD__ . '$this->aws_manager->memreasTranscoder->markMediaForTranscoding ( $message_data ) $message_data before as json', json_encode ( $message_data, JSON_PRETTY_PRINT ) );
					$this->aws_manager->memreasTranscoder->markMediaForTranscoding ( $message_data );
					// Mlog::addone ( __CLASS__ . __METHOD__ . '$this->aws_manager->memreasTranscoder->markMediaForTranscoding ( $message_data ) $message_data after as json', json_encode ( $message_data, JSON_PRETTY_PRINT ) );
					$result = $this->aws_manager->snsProcessMediaSubscribe ( $message_data );
				}
			} catch ( \Exception $e ) {
				// continue processing - email likely sent
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Error in while loop::' . $e->getMessage () );
				$this->aws_manager->sesEmailErrorToAdmin ( __CLASS__ . __METHOD__ . __LINE__ . '::Error in while loop::' . $e->getMessage (), "error in while loop" );
				exit ();
			} finally {
				/*
				 * Reset and continue work on backlog
				 */
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'transcoderAction::unset vars' );
				unset ( $message_data );
				unset ( $response );
				unset ( $this->dbAdapter );
				unset ( $this->aws_manager );
				// $this->aws_manager->memreasTranscoder->refreshDBConnection();
			}
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Bottom of while loop fetch next entry...' );
		} // end while
	}
	protected function returnResponse($response) {
		Mlog::addone ( __CLASS__ . __METHOD__, '::start' );
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
		header ( 'Connection: close' );
		
		// flush all output
		ob_end_flush ();
		ob_flush ();
		flush ();
		
		Mlog::addone ( __CLASS__ . __METHOD__, '::flushed' );
		// if you're using sessions, this prevents subsequent requests
		// from hanging while the background process executes
		if (session_id ()) {
			session_write_close ();
		}
		
		Mlog::addone ( __CLASS__ . __METHOD__, '::session closed' );
		// check headers
		if (headers_sent ()) {
			error_log ( "Success: response header 200 sucessfully sent" );
		} else {
			error_log ( "FAIL: response header 200 NOT sucessfully sent" );
		}
	}
} // end class IndexController
