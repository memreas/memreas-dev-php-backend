<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Admin;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Authentication\Storage;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;
use Zend\Db\ResultSet\ResultSet;
//use Zend\Db\ResultSet\AbstractResultSet;
use Zend\Db\TableGateway\TableGateway;
use Admin\Model\User;
use Admin\Model\UserTable;
use Admin\Model;

class Module {

    public function onBootstrap(MvcEvent $e) {
        $e->getApplication()->getServiceManager()->get('translator');
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        
        $eventManager->attach('route', function(MvcEvent $mvcEvent) {
                    $params = $mvcEvent->getRouteMatch()->getParams();                    
                    $_GET['ADMIN_EMAIL'] = "info@eventapp.com";
                    foreach ($params as $name => $value) {
                        if (!isset($_GET[$name])) {
                            $_GET[$name] = $value;
                        }
                    }
                });
    }

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getServiceConfig() {
        return array(
            'factories' => array(
                'Admin\Model\UserTable' => function($sm) {
                    $tableGateway = $sm->get('UserTableGateway');
                    $table = new UserTable($tableGateway);
                    return $table;
                },
                'UserTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
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
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
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
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
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
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\EventMedia());
                    return new TableGateway('event_media', $dbAdapter, null, $resultSetPrototype);
                },
                'EventTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
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
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new \Zend\Db\ResultSet\ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\FriendMedia());
                    return new TableGateway('friend_media', $dbAdapter, null, $resultSetPrototype);
                },
                'Admin\Model\MyAuthStorage' => function($sm) {
                    return new \Admin\Model\MyAuthStorage('eventapp');
                },
                'AuthService' => function($sm) {
                    //My assumption, you've alredy set dbAdapter
                    //and has users table with columns : user_name and pass_word
                    //that password hashed with md5
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $dbTableAuthAdapter = new DbTableAuthAdapter($dbAdapter,
                                    'user', 'username', 'password', 'MD5(?)');

                    $authService = new AuthenticationService();
                    $authService->setAdapter($dbTableAuthAdapter);
                    $authService->setStorage($sm->get('Admin\Model\MyAuthStorage'));

                    return $authService;
                },
            ),
        );
    }

}
