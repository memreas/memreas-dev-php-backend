<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Entity\ServerMonitor;
use Application\memreas\AWSMemreasRedisCache;
use Application\memreas\Mlog;
use Application\memreas\MUUID;

class AWSManagerAutoScaler {
	public $aws = null;
	public $ses = null;
	public $service_locator = null;
	public $dbAdapter = null;
	public $redis;
	public $cpu_util;
	public $server_name;
	public $server_addr;
	public $hostname;
	public function __construct($service_locator, $aws_manager) {
		try {
			
			// Fetch aws handle
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'setting aws' );
			$this->aws = $aws_manager;
			
			// Setup service locator and db
			try {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'fetch this->service_locator' );
				$this->service_locator = $service_locator;
				
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'fetch this->dbAdapter' );
				$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
			} catch ( Exception $e ) {
				Mlog::addone ( __FILE__ . __METHOD__ . __LINE__ . 'Caught exception: ', $e->getMessage () );
				$this->aws_manager->sesEmailErrorToAdmin ( "memreas backend worker error:: failed to obtain database handle " . $e->getMessage (), "error retrieveing database handle" );
				throw $e;
			}
			
			//
			// Fetch Redis Handle
			//
			try {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'fetch this->redis' );
				$this->redis = new AWSMemreasRedisCache ();
			} catch ( Exception $e ) {
				Mlog::addone ( __FILE__ . __METHOD__ . __LINE__ . 'Caught exception: ', $e->getMessage () );
				$this->aws_manager->sesEmailErrorToAdmin ( "memreas backend worker error:: failed to obtain redis handle " . $e->getMessage (), "error retrieveing redis handle" );
				throw $e;
			}
			
			//
			// Set Server Data
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'fetch this->setServerData ()' );
			$this->setServerData ();
		} catch ( Exception $e ) {
			Mlog::addone ( __FILE__ . __METHOD__ . __LINE__ . 'Caught exception: ', $e->getMessage () );
			$this->aws_manager->sesEmailErrorToAdmin ( "memreas backend worker error:: " . $e->getMessage (), __CLASS__.'::error in __construct($service_locator, $aws_manager)' );
		}
	}
	public function serverReadyToProcessTask() {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Inside serverReadyToProcessTask ()' );
		return $this->fetchTranscodingProcessHandleFromRedis ();
	}
	function fetchTranscodingProcessHandleFromRedis() {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Inside fetchTranscodingProcessHandleFromRedis()' );
		
		$result = $this->redis->getCache ( $this->server_name . "_trancode_lock" );
		exec ( "pgrep ffmpeg", $output, $isNotRunningFFMPEG );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Check if ffmpeg is running::', '$isNotRunningFFMPEG::' . $isNotRunningFFMPEG . '::$result::' . $result . '::output::' . print_r ( $output, true ) );
		
		if ((! $result) || ($result == 0) || ($isNotRunningFFMPEG)) {
			//
			// Process sets lock
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Process can set lock::', 'cachePid::' . $result . 'getmypid()::' . getmypid () );
			$this->redis->setCache ( $this->server_name . "_trancode_lock", getmypid () );
			return getmypid ();
		} else if ($result == getmypid ()) {
			//
			// Process has lock to continue processing
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Process has lock::', 'cachePid::' . $result . 'getmypid()::' . getmypid () );
			return getmypid ();
		} else if ($result != getmypid ()) {
			//
			// Another process has the lock so let this one die
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Another process has the lock so let this one die::', 'cachePid::' . $result . 'getmypid()::' . getmypid () );
			return 0;
		}
	}
	function releaseTranscodingProcessHandleFromRedis() {
		$this->redis->setCache ( $this->server_name . "_trancode_lock", 0 );
	}
	function setServerData() {
		$this->cpu_util = sys_getloadavg ();
		// $this->server_name = $_SERVER['SERVER_NAME'];
		$this->server_name = gethostname ();
		$this->server_addr = $_SERVER ['SERVER_ADDR'];
		$this->hostname = gethostname ();
		
		// $memory = $this->get_server_memory_usage();
		// Mlog::addone(__CLASS__ . __METHOD__ . '::misc', $server_data);
		// if ($server_data['cpu_util'][0] > 75) {
		// Mlog::addone(__CLASS__ . __METHOD__ . '::$server_data[cpu_util]>75',
		// $server_data['cpu_util']);
		// }
	}
	function get_server_memory_usage() {
		$free = shell_exec ( 'free' );
		$free = ( string ) trim ( $free );
		$free_arr = explode ( "\n", $free );
		$mem = explode ( " ", $free_arr [1] );
		$mem = array_filter ( $mem );
		$mem = array_merge ( $mem );
		$memory_usage = $mem [2] / $mem [1] * 100;
		// Mlog::addone(
		// __CLASS__ . __METHOD__ . 'get_server_memory_usage::$memory_usage',
		// $memory_usage);
		
		return $memory_usage;
	}
	function checkServer() {
		$query_string = "SELECT sm FROM " . " Application\Entity\ServerMonitor sm";
		if ($this->server_name) {
			$query_string .= " where sm.server_name = '" . $this->server_name . "'";
		}
		
		$query = $this->dbAdapter->createQuery ( $query_string );
		return $query->getArrayResult ();
	}
	function addServer() {
		$tblServerMonitor = new \Application\Entity\ServerMonitor ();
		$now = new \DateTime ( "now" );
		$tblServerMonitor->server_id = MUUID::fetchUUID ();
		$tblServerMonitor->server_name = $this->server_name;
		$tblServerMonitor->server_addr = $this->server_addr;
		$tblServerMonitor->hostname = $this->hostname;
		$tblServerMonitor->status = ServerMonitor::WAITING;
		$tblServerMonitor->job_count = 0;
		$tblServerMonitor->cpu_util_1min = $this->cpu_util [0];
		$tblServerMonitor->cpu_util_5min = $this->cpu_util [1];
		$tblServerMonitor->cpu_util_15min = $this->cpu_util [2];
		$tblServerMonitor->last_cpu_check = $now;
		$tblServerMonitor->start_time = $now;
		
		$this->dbAdapter->persist ( $tblServerMonitor );
		$this->dbAdapter->flush ();
	}
	function updateServer($server_data) {
		$now = new \DateTime ( "now" );
		$qb = $this->dbAdapter->createQueryBuilder ();
		$query = $qb->update ( 'Application\Entity\ServerMonitor', 'sm' )->set ( 'sm.cpu_util_1min', '?1' )->set ( 'sm.cpu_util_5min', '?2' )->set ( 'sm.cpu_util_15min', '?3' )->set ( 'sm.last_cpu_check', '?4' )->where ( 'sm.server_name = ?5' )->setParameter ( 1, $server_data ['cpu_util'] [0] )->setParameter ( 2, $server_data ['cpu_util'] [1] )->setParameter ( 3, $server_data ['cpu_util'] [2] )->setParameter ( 4, $now )->setParameter ( 5, $server_data ['server_name'] )->getQuery ();
		$result = $query->getResult ();
		return $result;
	}
}//END



