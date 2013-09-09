<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use \Exception;
use Zend\Paginator\Paginator as Paginator;
use Zend\Session\Container;

use Admin\Model;
use Admin\Model\UserTable;
use Admin\Form;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;

class IndexController extends AbstractActionController {

    protected $storage;
    protected $authservice;
    protected $userTable;
    protected $eventTable;
    protected $mediaTable;
    protected $eventmediaTable;
    protected $friendmediaTable;

    public function getUserTable() {
        if (!$this->userTable) {
            $sm = $this->getServiceLocator();
            $this->userTable = $sm->get('Admin\Model\UserTable');
        }
        return $this->userTable;
    }

    public function getEventTable() {
        if (!$this->eventTable) {
            $sm = $this->getServiceLocator();
            $this->eventTable = $sm->get('Admin\Model\EventTable');
        }
        return $this->eventTable;
    }

    public function getEventMediaTable() {
        if (!$this->eventmediaTable) {
            $sm = $this->getServiceLocator();
            $this->eventmediaTable = $sm->get('Admin\Model\EventMediaTable');
        }
        return $this->eventmediaTable;
    }

    public function getMediaTable() {
        if (!$this->mediaTable) {
            $sm = $this->getServiceLocator();
            $this->mediaTable = $sm->get('Admin\Model\MediaTable');
        }
        return $this->mediaTable;
    }

    public function getFriendMediaTable() {
        if (!$this->friendmediaTable) {
            $sm = $this->getServiceLocator();
            $this->friendmediaTable = $sm->get('Admin\Model\FriendMediaTable');
        }
        return $this->friendmediaTable;
    }

    public function getAuthService() {
        if (!$this->authservice) {
            $this->authservice = $this->getServiceLocator()
                    ->get('AuthService');
        }

        return $this->authservice;
    }

    public function getSessionStorage() {
error_log("Inside getSessionStorage....");
        if (!$this->storage) {
            $this->storage = $this->getServiceLocator()
                    ->get('admin\Model\MyAuthStorage');
        }

        return $this->storage;
    }

    function security() {
error_log("Inside security....");
        //if already login, redirect to success page
        if ($this->getAuthService()->hasIdentity()) {
            $user = $this->getAuthService()->getStorage()->read('user');
            if ($user['role'] == 2)
                return $user['role'];
            else {
                $this->flashmessenger()->addMessage("You are not Authenticat User");
                return $this->redirect()->toRoute('admin', array('action' => 'logout'));
            }
        }
        return $this->redirect()->toRoute('admin', array('action' => 'logout'));
    }

    public function memreasMeFriendsAction() {
        $this->security();
        return array();
    }
    public function loginAction() {
        //$this->security();
        return array();
    }

	public function indexAction() {

error_log("Inside indexAction....");

		$redirect = 'login';
    	return $this->redirect()->toRoute('admin', array('action' => $redirect));














        $this->layout('layout/login');
        $form = new \Admin\Form\UserForm();
        $user = new \Admin\Model\User();
        $redirect = 'index';
        $messages = array();
        $request = $this->getRequest();
//        echo "<pre>";
//        print_r($request);
        if ($this->flashmessenger()->hasMessages())
            foreach ($this->flashmessenger()->getMessages() as $message)
                $messages[] = $message;
        $this->flashMessenger()->clearCurrentMessages();
        if ($request->isPost()) {
error_log("Inside request->isPost()....");

            $form->setInputFilter($user->getInputFilter());
            $form->setData($request->getPost());
            $form->setValidationGroup('username', 'password');
            if ($form->isValid()) {
error_log("Inside form->isValid()....");
                //check authentication...
                $this->getAuthService()->getAdapter()
                        ->setIdentity($request->getPost('username'))
                        ->setCredential($request->getPost('password'));
                $this->flashMessenger()->clearMessages();
                $result = $this->getAuthService()->authenticate();
                foreach ($result->getMessages() as $message) { //save message temporary into flashmessenger
                    $messages[] = $message;
                    $this->flashmessenger()->addMessage($message);
                }
                
                if ($result->isValid()) {                    
error_log("Inside result->isValid()....");
                    $userObj = $this->getUserTable()->getUserByUsername($request->getPost('username'));
error_log("userObj ---> " . json_encode( $userObj ));
                    if($userObj->disable_account==0)
                    { 
                    //$redirect = 'manageusers';
                    //$redirect = 'memreas-me-friends';
                    $this->layout('layout/memreas-me-friends');
                    $redirect = 'index';
                    //check if it has rememberMe :
                    if ($request->getPost('rememberme') == 1) {
                        $this->getSessionStorage()
                                ->setRememberMe(1);
                        //set storage again
                        $this->getAuthService()->setStorage($this->getSessionStorage());
                    }

                    
                    if ($userObj->role == 2) {
                        $user = get_object_vars($userObj);
                        $this->getAuthService()->getStorage()->write($request->getPost('username'));
                        $this->getAuthService()->getStorage()->write($user);
                        $sessionObj = new Container('user_information');
                        $sessionObj->offsetSet('username', $request->getPost('username'));
                        return $this->redirect()->toRoute('admin', array('action' => $redirect));
                    } else {
 error_log("Inside But You are not authorized  User....");
                       
                        $messages[] = "But You are not authorized  User";
                    }                    
                    }else {
                        
 error_log("Inside But Your account is disabled....");
                        $messages[] = "But Your account is disabled";
                    }
                }
            }
        }        
        return array('form' => $form, 'messages' => $messages);
    }

    public function logoutAction() {
//        session_start();
//        session_unset();
        session_destroy();

        $this->getSessionStorage()->forgetMe();
        $this->getAuthService()->clearIdentity();
        $sessionObj = new Container('user_information');
         $sessionObj->getManager()->destroy(); 
         
        $this->flashmessenger()->addMessage("You've been logged out");
        return $this->redirect()->toRoute('admin');
    }

    public function successAction() {
error_log("Inside successAction....");
        $this->security();
    }

    public function manageusersAction() {
        $role = $this->security();
        try {
        $users = $this->getUserTable()->fetchAll();
//        echo "<pre>";
//        print_r($users);
        $page = $this->params()->fromRoute('page', 1);
            $iteratorAdapter = new \Zend\Paginator\Adapter\Iterator($users);
            $paginator = new Paginator($iteratorAdapter);
            $paginator->setCurrentPageNumber($page);
            $paginator->setItemCountPerPage(ADMIN_QUERY_LIMIT);
        
        } catch (Exception $exc) {
            
            return array();
        }
        return array('entries' => $paginator, 'role' => $role, 'user_total' => count($users));
    }

    public function viewuserAction() {
error_log("Inside viewuserAction....");
        $role = $this->security();
        try{
        $userid = $this->params('id');
        $user = $this->getUserTable()->getUser($userid);
        } catch (Exception $exc) {
            
            return array();
        }
        return array('entries' => $user);
    }

    public function activeuserAction() {
error_log("Inside viewuserAction....");
        $role = $this->security();
        try{
        $userid = $this->params('id');

        $datauser = $this->getUserTable()->getUser($userid);
        if ($role == 3) {
            if ($datauser->role == 3 || $datauser->role == 1) {
                $this->flashMessenger()->addMessage("You are not authorized  user for this.");
                return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
            }
        }
        if ($datauser->disable_account == 1) {
            $datauser->disable_account = 0;
        } else {
            $datauser->disable_account = 1;
        }

        $this->getUserTable()->saveUser($datauser);
        $this->flashMessenger()->addMessage("Status changed successfully.");
        } catch (Exception $exc) {
            $this->flashMessenger()->addMessage($exc->getMessage());
            return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
        }
        return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
    }

    public function edituserAction() {
        $role = $this->security();
        try {
            $objUsers = new Model\User();
            $userid = $this->params('id');
            $return = array();
            if (!empty($userid)) {
//            $this->view->userid = $userid;
                $return['userid'] = $userid;
                $userdetail = $this->getUserTable()->getUser($userid);

                if (!empty($userdetail)) {
//                $this->view->userdata = $userdetail;
                    $return['userdata'] = $userdetail;
                }
            } else {
                $this->flashMessenger()->addMessage("Empty User.");
                return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
            }
            if ($role == 3 && $userdetail->role != 2) {
                $this->flashMessenger()->addMessage("You are not authorized user.");
                return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
            }
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                $uid = $formData['userid'];
                if (!empty($uid)) {
                    $userinfo = $this->getUserTable()->getUser($userid);
                    if (!empty($userinfo)) {
                        $where['email_address'] = $formData['email'];
                        $where['username'] = $formData['username'];
                        $where['user_id'] = $userid;

                        $is_user_exist = $this->getUserTable()->isExist($where);
                        //--------------------------------if User(email) already exist
                        if ($is_user_exist) {
//                        $this->view->message = "User Already Exist Please Enter another Email or Name";
                            $return['message'] = "User Already Exist Please Enter another Email or Name";
                        } else {//----------------user not already exist
                            $objUsers->user_id = $uid;
                            $objUsers->role = $userinfo->role;
                            $objUsers->database_id = $userinfo->database_id;
                            $objUsers->password = $userinfo->password;
                            $objUsers->username = $formData['username'];
                            $objUsers->email_address = ($formData['email']);
                            $objUsers->disable_account = ($formData['disable_account']);
                            $objUsers->facebook_username = ($formData['facebook_username']);
                            $objUsers->twitter_username = ($formData['twitter_username']);

                            $this->getUserTable()->saveUser($objUsers);
                            $this->flashMessenger()->addMessage("User detail updated successfully.");
                            return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
                        }
                    }
                } else {
                    return $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
                }
            }            
        } catch (Exception $exc) {
            
//            $return['message'] = $exc->getPrevious()->getMessage();
            return $return;
        }
        return $return;
    }

    public function manageeventsAction() {
        
        $this->security();
        $order = array('event_id Desc'); //"id desc";

        $objEvent = new Model\Event();
        
        $user = $this->getUserTable()->getUserByRole('2');
        //pagination
        $userid = $this->params()->fromRoute('userid');
        $where = array('1' => '1');
        $order = array("create_time DESC");
        if($userid){        
        $where = array('user_id' => $userid);
        $return['user_id']=$userid;
        }
        $res=$this->getEventTable()->fetchAllCondition($where, $order);
        $page = $this->params()->fromRoute('page', 1);
            $iteratorAdapter = new \Zend\Paginator\Adapter\Iterator($res);
            $paginator = new Paginator($iteratorAdapter);
            $paginator->setCurrentPageNumber($page);
            $paginator->setItemCountPerPage(ADMIN_QUERY_LIMIT);
                      //  $paginator=$this->getEventTable()->fetchAll();
            $return['event_total']=count($res);
            $return['users']=$user;
            $return['entries']=$paginator;
        return $return;
    }

    public function changepasswordAction() {
        $this->security();
        $user = $this->getAuthService()->getStorage()->read('user');
//        print_r($user_id);exit;
        $user_id = $user['user_id'];
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();
            if (!empty($user_id))
                $user_data = $this->getUserTable()->getUser($user_id);
            if ($user_data->password == md5($formData['current_password'])) {
                if (($formData['new_password'] == $formData['confirm_password'])) {
                    $user_data->password = md5($formData['confirm_password']);
                    $this->getUserTable()->saveUser($user_data);
                    $this->flashMessenger()->addMessage("Password Changed Successfully.");
                    $this->redirect()->toRoute('admin', array('action' => 'manageusers'));
                }
            }
        }
    }

    public function vieweventAction() {
        $this->security();
        
        try {
        $eventid = $this->params('id');
        $event = $this->getEventTable()->getEventMedia($eventid);
        } catch (Exception $exc) {
            return array();
        }
        return array('event_media' => $event);
    }

    public function deletesingalmedia($mediaid) {
        $this->security();

        //--------------get media for delete image-----
        $getMedia = $this->getMediaTable()->getMedia($mediaid);
        $metadata = $getMedia->metadata;
        $json_array = json_decode($metadata, true);
        $file = basename($json_array['S3_files']['path']);
        /*
         //we dont have physical path of ser thats y 
        if (isset($json_array['type']['image']) && is_array($json_array['type']['image'])) {
            $type[] = getcwd() . "/public/media/userimage/";
            $type[] = getcwd() . "/public/media/79x80/";
            $type[] = getcwd() . "/public/media/98x78/";
            $type[] = getcwd() . "/public/media/448x306/";
        } else if (isset($json_array['type']['video']) && is_array($json_array['type']['video']))
            $type[] = getcwd() . "/public/media/uploadVideo/";
        else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio']))
            $type[] = getcwd() . "/public/media/upload_audio/";
        foreach ($type as $value) {
            $flag = unlink($value . $file);
        }
        if (isset($flag) && $flag == 1) {*/

            $ev = $this->getMediaTable()->deleteMedia($mediaid);
            if (!$ev) {
                $this->flashMessenger()->addMessage("Error in deletion...");
            }
            $f = $this->getEventMediaTable()->delete(array('media_id' => $mediaid));
//-------------delete data from friend_media table
//            $mediaid = $this->getRequest()->getParam('id');

            $this->getFriendMediaTable()->delete($mediaid);
            if ($f)
                $this->flashMessenger()->addMessage("Media Item Successfully deleted...");
            else
                $this->flashMessenger()->addMessage("Error in deletion...");
//        }else
//            $this->flashMessenger()->addMessage("Error in file deletion...");
    }

    public function deletemediaAction() {
        $this->security();
        $mediaid = $this->params('id');
        $this->deletesingalmedia($mediaid);
        $this->redirect()->toRoute('admin', array('action' => 'manageevents'));
    }

    public function deleteeventAction() {
        $this->security();
//        echo "<pre>";
        $eventid = $this->params('id');
        $res = $this->getEventMediaTable()->fetchAll(array('event_id' => $eventid));
        print_r($res);
        foreach ($res as $event_mediarow) {
            $mediaid = $event_mediarow->media_id;
            $this->deletesingalmedia($mediaid);
        }
//        print_r($res);

        $this->getEventMediaTable()->delete(array('event_id' => $eventid));
        $this->getEventTable()->deleteEvent($eventid);
        $this->flashMessenger()->clearCurrentMessages();
        $this->flashMessenger()->addMessage("Event Successfully Deleted...");
        $this->redirect()->toRoute('admin', array('action' => 'manageevents'));
    }

    public function forgotpasswordAction() {
        $this->layout('layout/login');
        $request = $this->getRequest();
        $return = array('success' => true);
        if ($request->isPost()) {
            $username = $_POST['username'];
            $where = array('username' => $username);

            $users = $this->getUserTable()->getUserBy($where);
            if (count($users) > 0) {
                $password = mt_rand(1000, 99999);

                $content = "<p>Dear User,</p><br />";
                $content .="<p>As requested, we have reset your password.</p><br/>";
                $content .="<p>New Password : " . $password . " </p><br/>";
                $content .="<p>Thank You,</p>";

                $subject = "Forgot Password";
                $from = $_GET['ADMIN_EMAIL'];
                $to = $users->email_address;
                try {/* ***************send mail useing zf2************************** */
                    $message = new Message();
                    $message->addTo($to)
                            ->addFrom($from)
                            ->setSubject($subject)
                            ->setBody($content);

                    $transport = new SendmailTransport();
                    $transport->send($message);
                } catch (Exception $e) {                    
                    $message_flash= "<br>Message sending failed, please try after sometime.";
                    $this->flashMessenger()->addMessage($message_flash);
                }
                /*                 * **************sending mail useing core PHP************************** */
                // Always set content-type when sending HTML email
                /* $headers = "MIME-Version: 1.0" . "\r\n";
                  $headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
                  $headers .= 'From: <'.$from.'>' . "\r\n";
                  $sendmail = mail($to, $subject, $content, $headers);
                  //                $sendmail = $this->sendMail($htmlBody, $textBody, $subject, $from, $to);

                 * 
                 */
                $user = new Model\User();
                $user->user_id = $users->user_id;
                $user->password = md5($password);
                //$this->getUsersTable()->savedata($data);
                if ($this->getUserTable()->saveUser($user)) {
                    $this->flashMessenger()->addMessage('Your password has been changed successfully. Check your email account for new password.');
                    return $this->redirect()->toRoute('admin', array('action' => 'index'));
                } else {
                    $this->flashMessenger()->addMessage('Unable to save Password, please try again.');
                    return $this->redirect()->toRoute('admin', array('action' => 'forgotpassword'));
                }
            } else {
                $this->flashMessenger()->addMessage('Incorrect Email Address, please try again.');
            }
            return $this->redirect()->toRoute('admin', array('action' => 'forgotpassword'));
        }
        $flashMessenger = $this->flashMessenger();
        if ($flashMessenger->hasMessages()) {
            $return['messages'] = $flashMessenger->getMessages();
        }
//        echo "hi";
        return $return;
    }
}
