<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ServerMonitor
 *
 * @ORM\Table(name="server_monitor")
 * @ORM\Entity
 */
class ServerMonitor {
	const WAITING = 'waiting';
	const EXECUTING = 'executing';
	const BACKLOG = 'backlog';
	
	/**
	 *
	 * @var integer @ORM\Column(name="server_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 */
	private $server_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="server_name", type="string", length=255, nullable=false)
	 */
	private $server_name;
	
	/**
	 *
	 * @var string @ORM\Column(name="server_addr", type="string", length=255, nullable=false)
	 */
	private $server_addr;
	
	/**
	 *
	 * @var string @ORM\Column(name="hostname", type="string", length=255, nullable=false)
	 */
	private $hostname;
	
	/**
	 *
	 * @var string @ORM\Column(name="status", type="string", length=255, nullable=false)
	 */
	private $status;
	
	/**
	 *
	 * @var string @ORM\Column(name="job_count", type="integer", nullable=false)
	 */
	private $job_count;
	
	/**
	 *
	 * @var float @ORM\Column(name="cpu_util_1min", type="decimal", precision=3, scale=3, nullable=false)
	 */
	private $cpu_util_1min;
	
	/**
	 *
	 * @var float @ORM\Column(name="cpu_util_5min", type="decimal", precision=3, scale=3, nullable=false)
	 */
	private $cpu_util_5min;
	
	/**
	 *
	 * @var float @ORM\Column(name="cpu_util_15min", type="decimal", precision=3, scale=3, nullable=false)
	 */
	private $cpu_util_15min;
	
	/**
	 *
	 * @var string @ORM\Column(name="last_cpu_check", type="datetime", nullable=false)
	 */
	private $last_cpu_check;
	
	/**
	 *
	 * @var string @ORM\Column(name="start_time", type="datetime", nullable=false)
	 */
	private $start_time;
	
	/**
	 *
	 * @var string @ORM\Column(name="end_time", type="datetime", nullable=true)
	 */
	private $end_time;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}
