<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Comment
 *
 * @ORM\Table(name="comment")
 * @ORM\Entity
 */
class Comment
{
    /**
     * @var string
     *
     * @ORM\Column(name="comment_id", type="string", length=255, nullable=false)
     * @ORM\Id
     */
    private $comment_id;

    /**
     * @var string
     *
     * @ORM\Column(name="media_id", type="string", length=255, nullable=false)
     */
    private $media_id;

    /**
     * @var string
     *
     * @ORM\Column(name="user_id", type="string", length=255, nullable=false)
     */
    private $user_id;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", nullable=true)
     */
    private $type = 'text';

    /**
     * @var boolean
     *
     * @ORM\Column(name="`like`", type="boolean", nullable=false)
     */
    private $like=0;

    /**
     * @var string
     *
     * @ORM\Column(name="text", type="text", nullable=false)
     */
    private $text=0;

    /**
     * @var string
     *
     * @ORM\Column(name="event_id", type="string", length=255, nullable=false)
     */
    private $event_id=0;

    /**
     * @var string
     *
     * @ORM\Column(name="inappropriate", type="string", length=1, nullable=false)
     */
    private $inappropriate =0;

    /**
     * @var string
     *
     * @ORM\Column(name="audio_id", type="string", length=255, nullable=false)
     */
    private $audio_id=0;

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
