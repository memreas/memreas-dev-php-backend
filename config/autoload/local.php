<?php
/**
 * Local Configuration Override
 *
 * This configuration override file is for overriding environment-specific and
 * security-sensitive configuration information. Copy this file without the
 * .dist extension at the end and populate values as needed.
 *
 * @NOTE: This file is ignored from Git by default with the .gitignore included
 * in ZendSkeletonApplication. This is a good practice, as it prevents sensitive
 * credentials from accidentally being committed into version control.
 */
 
return array (
		// Whether or not to enable a configuration cache.
		// If enabled, the merged configuration will be cached and used in
		// subsequent requests.
		// 'config_cache_enabled' => false,
		// The key used to create the configuration cache file name.
		// 'config_cache_key' => 'module_config_cache',
		// The path in which to cache merged configuration.
		// 'cache_dir' => './data/cache',
		// ...
		
		'db' => array (
				'adapters' => array (
						'memreasintdb' => array (
								'dsn' => 'mysql:dbname=memreasintdb;host=memreasdevdb.co0fw2snbu92.us-east-1.rds.amazonaws.com',
								'username' => 'memreasdbuser',
								'password' => '4ma___2016' 
						),
						'memreasbackenddb' => array (
								'dsn' => 'mysql:dbname=memreasbackenddb;host=memreasdevdb.co0fw2snbu92.us-east-1.rds.amazonaws.com',
								'username' => 'memreasdbuser',
								'password' => '4ma___2016' 
						) 
				) 
		),
		
		'doctrine' => array (
				'connection' => array (
						'orm_default' => array (
								'doctrine_type_mappings' => array (
										'enum' => 'string',
										'bit' => 'string' 
								),
								// memreasbackenddb
								'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
								'params' => array (
										'host' => 'memreasdevdb.co0fw2snbu92.us-east-1.rds.amazonaws.com',
										'port' => '3306',
										'dbname' => 'memreasbackenddb',
										'user' => 'memreasdbuser',
										'password' => '4ma___2016' 
								) 
						) 
				) 
		) 
);

