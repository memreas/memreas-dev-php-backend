<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EventMedia
 *
 * @ORM\Table(name="event_media")
 * @ORM\Entity
 */
class Event_Media
{
    
    /**
     * @var string
     *
     * @ORM\Column(name="media_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $media_id;

    /**
     * @var string
     *
     * @ORM\Column(name="event_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $event_id;
    
    public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }


}
