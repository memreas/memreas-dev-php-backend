<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Session\Container;
use Zend\Session\Config\SessionConfig;
use Zend\Session\SessionManager;
use Zend\Authentication\Storage;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;

use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;

use Application\Model\TransactionReceiver;

use Application\Model\Subscription;

use Application\Model\SubscriptionTable;

use Application\Model\AccountDetail;

use Application\Model\AccountDetailTable;

use Application\Model\AccountBalances;

use Application\Model\AccountBalancesTable;

use Application\Model\Account;

use Application\Model\AccountTable;

use Application\Model\Transaction;
use Application\Model\TransactionTable;
use Application\Model\PaymentMethod;
use Application\Model\PaymentMethodTable;
use Application\Model\TranscodeTransaction;
use Application\Model\TranscodeTransactionTable;
use Admin\Model\User;
use Admin\Model\UserTable;
use Admin\Model;

//PayPal Modules
class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $e->getApplication()->getServiceManager()->get('translator');
        $eventManager        = $e->getApplication()->getEventManager();
        $serviceManager      = $e->getApplication()->getServiceManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $this->bootstrapSession($e);

    }

    public function bootstrapSession($e)
    {
        $session = $e->getApplication()
                     ->getServiceManager()
                     ->get('Zend\Session\SessionManager');
        $session->start();

        $container = new Container('user');
        if (!isset($container->init)) {
             $session->regenerateId(true);
             $container->init = 1;
        }
    }



    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                    'PayPal' => __DIR__ . '/../../vendor/PayPal',
                    'memreas' => __DIR__ . '/../../vendor/memreas',
                ),
            ) );
	}
	
	public function getServiceConfig() {
		return array(
				'factories' => array (
							//ZF2 Session Setup...
			                'Zend\Session\SessionManager' => function ($sm) {
    	    		            $config = $sm->get('config');
        	        		    if (isset($config['session'])) {
            	            		$session = $config['session'];

	 		                       $sessionConfig = null;
    	    		                if (isset($session['config'])) {
        	        		            $class = isset($session['config']['class'])  ? $session['config']['class'] : 'Zend\Session\Config\SessionConfig';
            	            		    $options = isset($session['config']['options']) ? $session['config']['options'] : array();
		        	                    $sessionConfig = new $class();
        		    	                $sessionConfig->setOptions($options);
                			        }

			                        $sessionStorage = null;
    	    		                if (isset($session['storage'])) {
        	        		            $class = $session['storage'];
            	            		    $sessionStorage = new $class();
		        	                }

			                        $sessionSaveHandler = null;
    	    		                if (isset($session['save_handler'])) {
        	        		            // class should be fetched from service manager since it will require constructor arguments
            	            		    $sessionSaveHandler = $sm->get($session['save_handler']);
		        	                }

			                        $sessionManager = new SessionManager($sessionConfig, $sessionStorage, $sessionSaveHandler);

    	    		                if (isset($session['validator'])) {
        	        		            $chain = $sessionManager->getValidatorChain();
            	            		    foreach ($session['validator'] as $validator) {
                	                		$validator = new $validator();
		            	                    $chain->attach('session.validate', array($validator, 'isValid'));

	        		                    }
    	            		        }
			                    } else {
        			                $sessionManager = new SessionManager();
		        	            }
        		    	        Container::setDefaultManager($sessionManager);
			                    return $sessionManager;
    	    		        },
			                'Admin\Model\MyAuthStorage' => function($sm) {
			                    return new \Admin\Model\MyAuthStorage('eventapp');
			                },
			                'AuthService' => function($sm) {
		    	                //My assumption, you've alredy set dbAdapter
			                    //and has users table with columns : user_name and pass_word
			                    //that password hashed with md5
                			    $dbAdapter = $sm->get('memreasdevdb');
			                    $dbTableAuthAdapter = new DbTableAuthAdapter($dbAdapter,
			                                    'user', 'username', 'password', 'MD5(?)');

			                    $authService = new AuthenticationService();
			                    $authService->setAdapter($dbTableAuthAdapter);
			                    $authService->setStorage($sm->get('Admin\Model\MyAuthStorage'));
	            		        return $authService;
			                },							  



							//Database Tables...
							//memreas base tables
			                'Application\Model\UserTable' => function($sm) {
			                    $tableGateway = $sm->get('UserTableGateway');
			                    $table = new UserTable($tableGateway);
			                    return $table;
			                },
			                'UserTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new User());
			                    return new TableGateway('user', $dbAdapter, null, $resultSetPrototype);
			                },
			                'Admin\Model\MediaTable' => function($sm) {
			                    $tableGateway = $sm->get('MediaTableGateway');
			                    $table = new Model\MediaTable($tableGateway);
			                    return $table;
			                },
			                'MediaTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new Model\Media());
			                    return new TableGateway('media', $dbAdapter, null, $resultSetPrototype);
			                },
			                'Admin\Model\EventTable' => function($sm) {
			                    $tableGateway = $sm->get('EventTableGateway');
			                    $table = new Model\EventTable($tableGateway);
			                    return $table;
			                },
			                'EventTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new Model\Event());
			                    return new TableGateway('event', $dbAdapter, null, $resultSetPrototype);
			                },
			                'Admin\Model\EventMediaTable' => function($sm) {
			                    $tableGateway = $sm->get('EventMediaTableGateway');
			                    $table = new Model\EventMediaTable($tableGateway);
			                    return $table;
			                },
			                'EventMediaTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new Model\EventMedia());
			                    return new TableGateway('event_media', $dbAdapter, null, $resultSetPrototype);
			                },
			                'EventTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new Model\Event());
			                    return new TableGateway('event', $dbAdapter, null, $resultSetPrototype);
			                },
			                'Admin\Model\FriendMediaTable' => function($sm) {
			                    $tableGateway = $sm->get('FriendMediaTableGateway');
			                    $table = new Model\FriendMediaTable($tableGateway);
			                    return $table;
			                },
			                'FriendMediaTableGateway' => function ($sm) {
			                    $dbAdapter = $sm->get('memreasdevdb');
			                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
			                    $resultSetPrototype->setArrayObjectPrototype(new Model\FriendMedia());
			                    return new TableGateway('friend_media', $dbAdapter, null, $resultSetPrototype);
			                },
							//Transcode related tables
							'Application\Model\TranscodeTransactionTable' => function ($sm) {
									$tableGateway = $sm->get ( 'TranscodeTransactionTableGateway' );
									$table = new TranscodeTransactionTable ( $tableGateway );
									return $table;
							}, 
							'TranscodeTransactionTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreasbackenddb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new TranscodeTransaction () );
									return new TableGateway ( 'transcode_transaction', $dbAdapter, null, $resultSetPrototype );
							},



							//Paypal related tables
							'Application\Model\TransactionTable' => function ($sm) {
									$tableGateway = $sm->get ( 'TransactionTableGateway' );
									$table = new TransactionTable ( $tableGateway );
									return $table;
							}, 
							'TransactionTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new Transaction () );
									return new TableGateway ( 'transaction', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\AccountTable' => function ($sm) {
									$tableGateway = $sm->get ( 'AccountTableGateway' );
									$table = new AccountTable ( $tableGateway );
									return $table;
							},
							'AccountTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new Account());
									return new TableGateway ( 'account', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\AccountBalancesTable' => function ($sm) {
									$tableGateway = $sm->get ( 'AccountBalancesTableGateway' );
									$table = new AccountBalancesTable( $tableGateway );
									return $table;
							},
							'AccountBalancesTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new AccountBalances());
									return new TableGateway ( 'account_balances', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\AccountDetailTable' => function ($sm) {
									$tableGateway = $sm->get ( 'AccountDetailTableGateway' );
									$table = new AccountDetailTable( $tableGateway );
									return $table;
							},
							'AccountDetailTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new AccountDetail());
									return new TableGateway ( 'account_detail', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\SubscriptionTable' => function ($sm) {
									$tableGateway = $sm->get ( 'SubscriptionTableGateway' );
									$table = new SubscriptionTable( $tableGateway );
									return $table;
							},
							'SubscriptionTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new Subscription());
									return new TableGateway ( 'subscription', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\TransactionReceiverTable' => function ($sm) {
									$tableGateway = $sm->get ( 'TransactionReceiverTableGateway' );
									$table = new SubscriptionTable( $tableGateway );
									return $table;
							},
							'TransactionReceiverTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new TransactionReceiver());
									return new TableGateway ( 'transaction_receiver', $dbAdapter, null, $resultSetPrototype );
							},
							'Application\Model\PaymentMethodTable' => function ($sm) {
									$tableGateway = $sm->get ( 'PaymentMethodTableGateway' );
									$table = new PaymentMethodTable( $tableGateway );
									return $table;
							},
							'PaymentMethodTableGateway' => function ($sm) {
									$dbAdapter = $sm->get ( 'memreaspaymentsdb' );
									$resultSetPrototype = new ResultSet ();
									$resultSetPrototype->setArrayObjectPrototype ( new PaymentMethod());
									return new TableGateway ( 'payment_method', $dbAdapter, null, $resultSetPrototype );
							},
						) 
					);
    }
}
