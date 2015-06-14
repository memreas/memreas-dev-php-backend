<?php
namespace Application\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * ServerMonitor
 *
 * @ORM\Table(name="server_monitor")
 * @ORM\Entity
 */
class ServerMonitor
{
    
    /**
     *
     * @var string @ORM\Column(name="transcode_transaction_id", type="string",
     *      length=45, nullable=false)
     *      @ORM\Id
     */
    private $transcode_transaction_id;
    //     `transcode_transaction_id` varchar(45) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="user_id", type="string", length=45,
     *      nullable=false)
     */
    private $user_id;
    //     `user_id` varchar(45) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="media_id", type="string", length=45,
     *      nullable=false)
     */
    private $media_id;
    //     `media_id` varchar(45) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="file_name", type="string", length=255,
     *      nullable=false)
     */
    private $file_name;
    //     `file_name` varchar(255) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="message_data", type="string", length=45,
     *      nullable=false)
     */
    private $message_data;
    //     `message_data` varchar(45) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="media_type", type="string", length=20,
     *      nullable=true)
     */
    private $media_type;
    //     `media_type` varchar(20) DEFAULT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="media_extension", type="string", length=45,
     *      nullable=true)
     */
    private $media_extension;
    //     `media_extension` varchar(45) DEFAULT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="media_duration", type="string", length=45,
     *      nullable=true)
     */
    private $media_duration;
    //     `media_duration` varchar(45) DEFAULT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="media_size", type="string", length=45,
     *      nullable=true)
     */
    private $media_size;
    //     `media_size` varchar(45) DEFAULT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="transcode_status", type="string",
     *      length=45, nullable=false)
     */
    private $transcode_status;
    //     `transcode_status` varchar(45) NOT NULL DEFAULT 'pending',
    
    /**
     *
     * @var string @ORM\Column(name="pass_fail", type="integer", nullable=false)
     */
    private $pass_fail;
    //     `pass_fail` bit(1) NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="metadata", type="json_array",
     *      nullable=true)
     */
    private $metadata;
    //     `metadata` longtext,
    
    /**
     *
     * @var string @ORM\Column(name="error_message", type="json_array",
     *      nullable=true)
     */
    private $error_message;
    //     `error_message` text,
    
    /**
     *
     * @var string @ORM\Column(name="transcode_job_duration", type="integer",
     *      nullable=true)
     */
    private $transcode_job_duration;
    //     `transcode_job_duration` int(11) DEFAULT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="transcode_start_time", type="datetime",
     *      nullable=false)
     */
    private $transcode_start_time;
    //     `transcode_start_time` datetime NOT NULL,
    
    /**
     *
     * @var string @ORM\Column(name="transcode_end_time", type="datetime",
     *      nullable=true)
     */
    private $transcode_end_time;
    //     `transcode_end_time` datetime DEFAULT NULL,
    
    public function __set ($name, $value)
    {
        $this->$name = $value;
    }

    public function __get ($name)
    {
        return $this->$name;
    }
}
