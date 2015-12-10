<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Application\Entity\Event;

/**
 * Media
 *
 * @ORM\Table(name="media")
 * @ORM\Entity
 */
class Media {
	
	/**
	 *
	 * @var string @ORM\Column(name="media_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *     
	 */
	private $media_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	private $user_id;
	
	/**
	 *
	 * @var boolean @ORM\Column(name="is_profile_pic", type="boolean", nullable=false)
	 */
	private $is_profile_pic = '0';
	
	/**
	 *
	 * @var integer @ORM\Column(name="sync_status", type="integer", nullable=false)
	 */
	private $sync_status = '0';
	
	/**
	 *
	 * @var string @ORM\Column(name="metadata", type="text", nullable=false)
	 */
	private $metadata;
	
	/**
	 *
	 * @var string @ORM\Column(name="report_flag", type="string", length=1, nullable=false)
	 */
	private $report_flag = '0';
	
	/**
	 *
	 * @var string @ORM\Column(name="create_date", type="string", length=255, nullable=false)
	 */
	private $create_date;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_date", type="string", length=255, nullable=false)
	 */
	private $update_date;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
	public function __construct() {
		$this->events = new \Doctrine\Common\Collections\ArrayCollection ();
	}
}
