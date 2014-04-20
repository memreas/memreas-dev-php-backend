<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="group")
 * 
  * @property int $role_id

 */
class Group  
{


    /**
     * @ORM\Id
     * @ORM\Column(type="integer");
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $group_id	;
	  /**
     * @ORM\Column(type="integer")
     */
	  



    
}