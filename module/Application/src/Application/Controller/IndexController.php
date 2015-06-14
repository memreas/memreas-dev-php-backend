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
use Application\memreas\Mlog;
use Application\memreas\AWSManagerAutoScaler;

class IndexController extends AbstractActionController
{

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

    public function fetchXML ($action, $xml)
    {
        error_log("Inside fetchXML this->url $this->url ....");
        $guzzle = new Client();
        $request = $guzzle->post($this->url, null, 
                array(
                        'action' => $action,
                        'xml' => $xml
                ));
        $response = $request->send();
        error_log("Inside fetchXML response $response ....");
        return $data = $response->getBody(true);
    }

    public function indexAction ()
    {
        error_log("indexAction()...." . PHP_EOL);
        $actionname = isset($_REQUEST["action"]) ? $_REQUEST["action"] : '';
        if ($actionname == "clearlog") {
            try {
                $filename = getcwd() . '/php_errors.log';
                // $result = unlink ( $filename );
                file_put_contents($filename, '');
                Mlog::addone(__CLASS__ . __METHOD__ . '::' . $actionname, 
                        "Log has been cleared!");
                echo 'success';
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
            exit();
        } else {
            try {
                /*
                 * Check Instance against AutoScaler
                 */
                $this->awsManagerAutoScaler = new AWSManagerAutoScaler(
                        $this->getServiceLocator());
                
                /*
                 * If need server launch, guzzle to start,
                 * and set transaction as pending
                 */
                $this->transcoderAction();
            } catch (Exception $e) {
                Mlog::addone(__CLASS__ . __METHOD__ . '::Caught exception', 
                        $e->getMessage());
            }
        }
        exit();
    }

    protected function get_server_memory_usage ()
    {
        $free = shell_exec('free -m');
        $free = (string) trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $memory_usage = $mem[2] / $mem[1] * 100;
        
        return $memory_usage;
    }

    protected function transcoderAction ()
    {
        Mlog::addone(__CLASS__ . __METHOD__, 'transcoderAction()');
        
        // Web Server Handle
        $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : '';
        $json = isset($_REQUEST["json"]) ? $_REQUEST["json"] : '';
        Mlog::addone(__CLASS__ . __METHOD__ . '$action', $action);
        Mlog::addone(__CLASS__ . __METHOD__ . '$json', $json);
        $proceed = 0;
        $response = 'error - check action or json';
        if (($action) && ($json)) {
            $proceed = 1;
            $response = 'received';
        }
        if ($proceed) {
            Mlog::addone(__CLASS__ . __METHOD__ . '$proceed', $proceed);
            $message_data = json_decode($json, true);
            $message_data['process_task'] = $this->awsManagerAutoScaler->serverReadyToProcessTask();
            
            // Fetch AWS Handle
            $aws_manager = new AWSManagerReceiver($this->getServiceLocator(), 
                    $message_data);
            
            // Mlog::addone(__CLASS__ . __METHOD__ . '$message_data',
            // $message_data);
            $response = $aws_manager->memreasTranscoder->markMediaForTranscoding(
                    $message_data);
            $this->returnResponse($response);
            /**
             * ****** background process starts here *******
             * ** process task if cpu < 75% usage
             * ** after completing task fetch another
             */
            while ($this->awsManagerAutoScaler->serverReadyToProcessTask()) {
                $result = $aws_manager->snsProcessMediaSubscribe($message_data);
                /*
                 * Build new task from database
                 */
                $message_data = $this->fetchBackLogEntry();
            }
            exit();
        }
    }

    protected function fetchBackLogEntry ()
    {
        $query_string = "SELECT tt.message_data FROM " .
                 " Application\Entity\TranscodeTransaction tt " .
                 " where tt.transcode_status='backlog' " .
                 " order by tt.transcode_start_time asc" . " limit 0, 1";
        
        $this->dbAdapter = $this->getServiceLocator()->get(
                'doctrine.entitymanager.orm_default');
        $query = $this->dbAdapter->createQuery($query_string);
        $result = $query->getSingleResult();
        if ($result) {
            $message_data = $result->message_data;
            $message_data['transcode_transaction_id'] = $result->transcode_transaction_id;
            $message_data['backlog'] = true;
        }
        Mlog::addone(
                __CLASS__ . __METHOD__ . '::backlog::$message_data' .
                         $message_data);
        
        return $message_data;
    }

    protected function returnResponse ($response)
    {
        // buffer all upcoming output
        ignore_user_abort(true); // keeps php from stopping process
        ob_start();
        header('HTTP/1.0 200 OK');
        header('Content-Type: application/json');
        echo json_encode($response);
        // get the size of the output
        $size = ob_get_length();
        // send headers to tell the browser to close the connection
        // http_response_code ( 200 );
        header('HTTP/1.0 200 OK');
        header("Content-Length: $size");
        header('Connection: close');
        
        // flush all output
        ob_end_flush();
        ob_flush();
        flush();
        
        // if you're using sessions, this prevents subsequent requests
        // from hanging while the background process executes
        if (session_id()) {
            session_write_close();
        }
        
        // check headers
        if (headers_sent()) {
            error_log("Success: response header 200 sucessfully sent");
        } else {
            error_log("FAIL: response header 200 NOT sucessfully sent");
        }
    }
} // end class IndexController
