<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * FriendGroup
 *
 * @ORM\Table(name="friend_group")
 * @ORM\Entity
 */
class FriendGroup
{
    /**
     * @var string
     *
     * @ORM\Column(name="group_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $group_id;

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
