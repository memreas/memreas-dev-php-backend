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
 * $dbParams = array(
 * 'database' => 'memreaspaymentsdevdb',
 * 'username' => 'root',
 * 'password' => 'john1016',
 * 'hostname' => 'localhost',
 * // buffer_results - only for mysqli buffered queries, skip for others
 * 'options' => array('buffer_results' => true)
 * );
 */
return array (
		'db' => array (
				'adapters' => array (
						'memreasintdb' => array (
								'driver' => 'Pdo',
								'driver_options' => array (
										PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'' 
								) 
						),
						'memreasbackenddb' => array (
								'driver' => 'Pdo',
								'driver_options' => array (
										PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'' 
								) 
						) 
				) 
		),
		'service_manager' => array (
				'abstract_factories' => array (
						'Zend\Db\Adapter\AdapterAbstractServiceFactory' 
				) 
		) 
);
