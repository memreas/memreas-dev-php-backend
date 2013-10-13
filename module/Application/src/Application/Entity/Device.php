<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="device")
 * 
 */
class Device  
{
    const ANROID = '1';
    const APPLE = '2';
    
    /**
     * @var string
     *
     * @ORM\Column(name="device_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $device_id;

    /**
     * @var string
     *
     * @ORM\Column(name="user_id", type="string", length=255, nullable=false)
     */
    private $user_id;

    /**
     * @var string
     *
     * @ORM\Column(name="device_token", type="string", length=255, nullable=false)
     */
    private $device_token;

      /**
     * @var string
     *
     * @ORM\Column(name="device_type", type="string", length=255, nullable=false)
     */
    private $device_type;

    /**
     * @var string
     *
     * @ORM\Column(name="create_time", type="string", length=255, nullable=false)
     */
    private $create_time;

    /**
     * @var string
     *
     * @ORM\Column(name="update_time", type="string", length=255, nullable=false)
     */
    private $update_time;

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }


    
}