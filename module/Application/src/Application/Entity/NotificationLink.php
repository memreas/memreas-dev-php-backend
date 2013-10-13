<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="notifications_link")
 * 
 */
class NotificationLink 
{
    /**
     * @var string
     *
     * @ORM\Column(name="notification_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $notification_id;

     /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=255, nullable=false)
  
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="table_name", type="string", length=255, nullable=false)
     */
    private $table_name;
    
    

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }


    
}