<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * FriendMedia
 *
 * @ORM\Table(name="friend_media")
 * @ORM\Entity
 */
class FriendMedia
{
    /**
     * @var string
     *
     * @ORM\Column(name="friend_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $friendId;

    /**
     * @var string
     *
     * @ORM\Column(name="media_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $mediaId;


	  public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }

}
