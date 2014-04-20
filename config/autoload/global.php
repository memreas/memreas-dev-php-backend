<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */
/*
$dbParams = array(
    'database'  => 'memreaspaymentsdevdb',
    'username'  => 'root',
    'password'  => 'john1016',
    'hostname'  => 'localhost',
    // buffer_results - only for mysqli buffered queries, skip for others
    'options' => array('buffer_results' => true)
);
*/

return array(
	'db'=> array(
		'adapters'=>array(
			'memreasintdb' => array(
		        'driver'         => 'Pdo',
    			'driver_options' => array(
		            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
				),
			),
			'memreaspaymentsdb' => array(
		        'driver'         => 'Pdo',
    			'driver_options' => array(
		            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
				),
			),
			'memreasbackenddb' => array(
		        'driver'         => 'Pdo',
    			'driver_options' => array(
		            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
				),
			),
		)
	),

/*
    'db' => array(
        'driver'         => 'Pdo',
    	'driver_options' => array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        ),
    ),
*/
/* - Use this to debug queries...
 'service_manager' => array(
        'factories' => array(
            'Zend\Db\Adapter\Adapter' => function ($sm) use ($dbParams) {
                $adapter = new BjyProfiler\Db\Adapter\ProfilingAdapter(array(
                    'driver'    => 'pdo',
                    'dsn'       => 'mysql:dbname='.$dbParams['database'].';host='.$dbParams['hostname'],
                    'database'  => $dbParams['database'],
                    'username'  => $dbParams['username'],
                    'password'  => $dbParams['password'],
                    'hostname'  => $dbParams['hostname'],
                ));

                $adapter->setProfiler(new BjyProfiler\Db\Profiler\Profiler);
                if (isset($dbParams['options']) && is_array($dbParams['options'])) {
                    $options = $dbParams['options'];
                } else {
                    $options = array();
                }
                $adapter->injectProfilingStatementPrototype($options);
                return $adapter;
            },
        ),
    ),
*/
    'service_manager' => array(
//        'factories' => array(
//            'Zend\Db\Adapter\Adapter'
//                    => 'Zend\Db\Adapter\AdapterServiceFactory',
//        ),
		'abstract_factories' => array(
				'Zend\Db\Adapter\AdapterAbstractServiceFactory',
		),

    ),
    'doctrine' => array(
        'connection' => array(
            'orm_default' => array(
                'doctrine_type_mappings' => array(
                    'enum' => 'string',
                    'bit' => 'string'
                ),
				//integration db
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params' => array(
                    'host' => 'memreasdev-db.co0fw2snbu92.us-east-1.rds.amazonaws.com',
                    'port' => '3306',
                    'dbname' => 'memreasintdb',
                    'user'     => 'memreasdbuser',
					'password' => 'memreas2013',
/*
				//localhost db
                'params' => array(
                    'host' => 'localhost',
                    'port' => '3306',
                    'dbname' => 'memreasintdb',
                    'user'     => 'root',
					'password' => 'john1016',
*/
                )
            )
        )
    ),
    'session' => array(
        'config' => array(
            'class' => 'Zend\Session\Config\SessionConfig',
            'options' => array(
                'name' => 'myapp',
            ),
        ),
        'storage' => 'Zend\Session\Storage\SessionArrayStorage',
        'validators' => array(
            array(
                'Zend\Session\Validator\RemoteAddr',
                'Zend\Session\Validator\HttpUserAgent',
            ),
        ),
    ),

);
