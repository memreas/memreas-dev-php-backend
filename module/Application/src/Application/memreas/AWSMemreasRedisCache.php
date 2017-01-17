<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;
use Predis\Collection\Iterator;

class AWSMemreasRedisCache {
	private $aws = "";
	public $cache = "";
	private $client = "";
	private $isCacheEnable = MemreasConstants::REDIS_SERVER_USE;
	private static $handle;
	private static $isInitialized;
	public function __construct() {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'::', '__construct' );
		
		$fp = fsockopen(MemreasConstants::REDIS_SERVER_ENDPOINT, 6379, $errno, $errstr, 5);
		if (!$fp) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'::', 'PORT 6379 IS CLOSED' );
			// port is closed or blocked
		} else {
			// port is open and available
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'::', 'PORT 6379 IS OPEN' );
			fclose($fp);
		}		
		
		if (! $this->isCacheEnable) {
			return;
		}
		
		try {
			$this->cache = new \Predis\Client ( [ 
					'scheme' => 'tcp',
					'host' => MemreasConstants::REDIS_SERVER_ENDPOINT,
					'port' => 6379 
			] );
			self::$isInitialized = true;
			self::$handle = $this;
		//} catch ( \Predis\Connection\ConnectionException $ex ) {
		//	error_log ( "exception ---> " . print_r ( $ex, true ) . PHP_EOL );
		} catch ( \Exception $ex ) {
			error_log ( "predis connection exception ---> " . print_r ( $ex, true ) . PHP_EOL );
		}
		$this->cache->set('foo', 'bar');
		error_log("Fetching from REDIS! ---> " . $this->cache->get('foo') . " for host --->" . gethostname () . PHP_EOL);
		$this->cache->del ( 'foo' );
	}
	public static function getHandle() {
		if (! empty ( self::$isInitialized )) {
			return self::$handle;
		}
	}
	public function setCache($key, $value, $ttl = MemreasConstants::REDIS_CACHE_TTL) {
		if (! $this->isCacheEnable) {
			return false;
		}
		// $result = $this->cache->set ( $key , $value, $ttl );
		$result = $this->cache->executeRaw ( array (
				'SETEX',
				$key,
				$ttl,
				$value 
		) );
		
		// Debug
		if ($result) {
			// error_log('JUST ADDED THIS KEY ----> ' . $key . PHP_EOL);
			// error_log('VALUE ----> ' . $value . PHP_EOL);
		} else {
			// error_log('FAILED TO ADD THIS KEY ----> ' . $key . ' reason code
			// ---> ' . $this->cache->getResultCode(). PHP_EOL);
			error_log ( 'FAILED TO ADD THIS KEY VALUE----> ' . $value . PHP_EOL );
		}
		return $result;
	}
	public function findSet($set, $match) {
		error_log ( "Inside findSet.... set $set match $match" . PHP_EOL );
		// Scan the hash and return 0 or the sub-array
		$result = $this->cache->executeRaw ( array (
				'ZRANGEBYLEX',
				$set,
				"[" . $match,
				"(" . $match . "z" 
		) );
		if ($result != "(empty list or set)") {
			$matches = $result;
		} else {
			$matches = 0;
		}
		// error_log ( "matches------> " . json_encode ( $matches ) . PHP_EOL );
		return $matches;
	}
	public function addSet($set, $key, $val = null) {
		if (is_null ( $val ) && ! $this->cache->executeRaw ( array (
				'ZCARD',
				$set,
				$key 
		) )) {
			return $this->cache->zadd ( "$set", "$key" );
		} else if (! is_null ( $val )) {
			// error_log("addSet $set:$key:$val".PHP_EOL);
			return $this->cache->hset ( "$set", "$key", "$val" );
		} else {
			// do nothing key exists...
			// error_log("addSet $set:$key already exists...".PHP_EOL);
		}
	}
	public function hasSet($set, $hash = false) {
		// Scan the hash and return 0 or the sub-array
		if ($hash) {
			$result = $this->cache->executeRaw ( array (
					'HLEN',
					$set 
			) );
		} else {
			$result = $this->cache->executeRaw ( array (
					'ZCARD',
					$set 
			) );
		}
		
		return $result;
	}
	public function getSet($set) {
		return $this->cache->smembers ( $set, true );
	}
	public function remSet($set) {
		$this->cache->executeRaw ( array (
				'DEL',
				$set 
		) );
	}
	public function getCache($key) {
		if (! $this->isCacheEnable) {
			// error_log("isCacheEnable ----> ".$this->isCacheEnable.PHP_EOL);
			return false;
		}
		
		$result = $this->cache->get ( $key );
		
		return $result;
	}
	public function invalidateCache($key) {
		if (! $this->isCacheEnable) {
			Mlog::addone(__CLASS__.__METHOD__.__LINE__, "invalidateCache failure for key $key");
			return false;
		}
		Mlog::addone(__CLASS__.__METHOD__.__LINE__, "invalidateCache success for key $key");
		$result = $this->cache->del ( $key );
	}
	public function invalidateCacheMulti($keys) {
		if (! $this->isCacheEnable) {
			return false;
		}
		
		return $this->cache->deleteMulti ( $keys );
	}
}

?>
		