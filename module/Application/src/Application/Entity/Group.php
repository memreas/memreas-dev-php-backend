<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Group
 *
 * @ORM\Table(name="`group`")
 * @ORM\Entity
 */
class Group
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
     * @ORM\Column(name="user_id", type="string", length=255, nullable=false)
     */
    private $user_id;

    /**
     * @var string
     *
     * @ORM\Column(name="group_name", type="string", length=255, nullable=false)
     */
    private $group_name;

    /**
     * @var string
     *
     * @ORM\Column(name="create_date", type="string", length=255, nullable=false)
     */
    private $create_date;

    /**
     * @var string
     *
     * @ORM\Column(name="update_date", type="string", length=255, nullable=false)
     */
    private $update_date;

	  public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }


}
