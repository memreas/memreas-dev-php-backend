<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EventFriend
 *
 * @ORM\Table(name="event_friend")
 * @ORM\Entity
 */
class EventFriend
{
    /**
     * @var string
     *
     * @ORM\Column(name="event_id", type="string", length=255, nullable=false)
     * @ORM\Id
     
     */
    private $event_id;

    /**
     * @var string
     *
     * @ORM\Column(name="friend_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $friend_id;

	  public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }


}
