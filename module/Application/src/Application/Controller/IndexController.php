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
        $actionname = isset($_REQUEST["action"]) ? $_REQUEST["action"] : '';
        
        $this->checkGitPull = new CheckGitPull();
        $this->checkGitPull->exec();
        if ($actionname == "gitpull") {
            $gitpull = true;
            echo $this->checkGitPull->exec($gitpull);
            exit();
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

    protected function transcoderAction ()
    {
        // Web Server Handle
        $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : '';
        $json = isset($_REQUEST["json"]) ? $_REQUEST["json"] : '';
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
            $message_data = json_decode($json, true);
            $message_data['backlog'] = 1;
            /*
             * Here if no media_id is set then work on any backlog items...
             */
            $aws_manager = new AWSManagerReceiver($this->getServiceLocator());
            if (! empty($message_data['media_id'])) {
                $response = $aws_manager->memreasTranscoder->markMediaForTranscoding(
                        $message_data);
            } else {
                throw new \Exception("Transcoder::media_id is empty!");
            }
            
            $this->returnResponse($response);
            /**
             * ****** background process starts here *******
             * ** process task if cpu < 75% usage
             * ** after completing task fetch another
             */
            /*
             * Process initial message - no longer necessary all messages
             * backlog
             */
            Mlog::addone(__CLASS__ . __METHOD__ . '::$message_data', 
                    $message_data);
            if (! $this->awsManagerAutoScaler->serverReadyToProcessTask()) {
                //
                // end process here is already a process operating on the
                // backlog
                //
                Mlog::addone(__CLASS__ . __METHOD__, 
                        '::getmypid()::' . getmypid() . ' exiting...');
                exit();
            } else
                while ($this->awsManagerAutoScaler->serverReadyToProcessTask()) {
                    //
                    // Process is running and has lock
                    //
                    Mlog::addone(__CLASS__ . __METHOD__ . __LINE__, 
                            'Top of while loop Process has lock pid::' .
                                     getmypid());
                    
                    try {
                        
                        //
                        // Fetch $aws_manager
                        //
                        $aws_manager = new AWSManagerReceiver(
                                $this->getServiceLocator());
                        Mlog::addone(__CLASS__ . __METHOD__ . __LINE__, 
                                'Fetched $aws_manager' . getmypid());
                        
                        //
                        // Fetch next backlog entry
                        //
                        $message_data = $aws_manager->fetchBackLogEntry();
                        if (empty($message_data)) {
                            Mlog::addone(
                                    __CLASS__ . __METHOD__ .
                                             '$this->fetchBackLogEntry()', 
                                            ' returned null - processing complete!');
                            exit();
                        } else {
                            /*
                             * Process backlog messages
                             */
                            Mlog::addone(
                                    __CLASS__ . __METHOD__ .
                                             '$this->fetchBackLogEntry() - message_data', 
                                            $message_data);
                            $aws_manager = new AWSManagerReceiver(
                                    $this->getServiceLocator());
                            $aws_manager->memreasTranscoder->markMediaForTranscoding(
                                    $message_data);
                            Mlog::addone(
                                    __CLASS__ . __METHOD__ . '::$message_data', 
                                    $message_data);
                            $this->isTranscodingSoWait = true;
                            $result = $aws_manager->snsProcessMediaSubscribe(
                                    $message_data);
                            $this->isTranscodingSoWait = false;
                        }
                    } catch (\Exception $e) {
                        // continue processing - email likely sent
                    } finally {
                        /*
                         * Reset and continue work on backlog
                         */
                        Mlog::addone(__CLASS__ . __METHOD__ . __LINE__, 
                                'transcoderAction::unset vars');
                        unset($message_data);
                        unset($response);
                        unset($this->dbAdapter);
                        unset($aws_manager);
                    }
                } // end while
                      //
                      // If the while finished we release the lock
                      //
            Mlog::addone(
                    __CLASS__ . __METHOD__ . __LINE__ .
                             '::$this->awsManagerAutoScaler->releaseTranscodeingProcessHandleFromRedis()::', 
                            'lock release for pid::' . getmypid());
            $this->awsManagerAutoScaler->releaseTranscodeingProcessHandleFromRedis();
            
            exit();
            // At this point it's time to exit. The while loop is finished
            // and/or the pid doesn't match.
            //
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
