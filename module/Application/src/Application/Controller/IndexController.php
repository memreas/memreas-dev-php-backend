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
use Application\memreas\CheckGitPull;

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

    protected $checkGitPull;

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
        Mlog::addone(__CLASS__ . __METHOD__, "enter");
        $actionname = isset($_REQUEST["action"]) ? $_REQUEST["action"] : '';
        
        $this->checkGitPull = new CheckGitPull();
        $this->checkGitPull->exec();
        if ($actionname == "gitpull") {
            $gitpull = true;
            $this->checkGitPull->exec($gitpull);
        } else 
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
            
            /*
             * Here if no media_id is set then work on any backlog items...
             */
            $aws_manager = new AWSManagerReceiver($this->getServiceLocator());
            if (! empty($message_data['media_id'])) {
                Mlog::addone(
                        __CLASS__ . __METHOD__ . '!empty($message_data[media_id]', 
                        $message_data['media_id']);
                $response = $aws_manager->memreasTranscoder->markMediaForTranscoding(
                        $message_data);
            } else {
                Mlog::addone(
                        __CLASS__ . __METHOD__ . 'empty($message_data[media_id]', 
                        'backlog');
                $response = jsone_encode('backlog');
            }
            
            $this->returnResponse($response);
            /**
             * ****** background process starts here *******
             * ** process task if cpu < 75% usage
             * ** after completing task fetch another
             */
            while ($this->awsManagerAutoScaler->serverReadyToProcessTask()) {
                if (empty($message_data)) {
                    $message_data = $this->fetchBackLogEntry();
                    $message_data['backlog'] = 1;
                    Mlog::addone(
                            __CLASS__ . __METHOD__ . '$this->fetchBackLogEntry()', 
                            $message_data);
                }
                if (! empty($message_data)) {
                    $result = $aws_manager->snsProcessMediaSubscribe(
                            $message_data);
                }
                $message_data = null;
                $aws_manager = new AWSManagerReceiver($this->getServiceLocator());
            }
            exit();
        }
    }

    protected function fetchBackLogEntry ()
    {
        try {
            $query_string = "SELECT tt FROM " .
                     " Application\Entity\TranscodeTransaction tt " .
                     " where tt.transcode_status='backlog' " .
                     " order by tt.transcode_start_time asc";
            
            Mlog::addone(__CLASS__ . __METHOD__ . '$query_string', 
                    $query_string);
            $this->dbAdapter = $this->getServiceLocator()->get(
                    'doctrine.entitymanager.orm_default');
            $query = $this->dbAdapter->createQuery($query_string);
            $result = $query->getArrayResult();
            // Mlog::addone(__CLASS__ . __METHOD__ . '$result', $result);
            if ($result) {
                foreach ($result as $entry) {
                    $message_data = json_decode($entry['message_data'], true);
                    $message_data['transcode_transaction_id'] = $entry['transcode_transaction_id'];
                    $message_data['backlog'] = 1;
                    break;
                }
            }
            Mlog::addone(__CLASS__ . __METHOD__ . '::backlog::$message_data', 
                    json_encode($message_data));
            
            return $message_data;
        } catch (Exception $e) {
            Mlog::addone(__CLASS__ . __METHOD__ . '::Caught exception', 
                    $e->getMessage());
            throw $e;
        }
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
