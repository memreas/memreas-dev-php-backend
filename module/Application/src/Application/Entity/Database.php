<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Database
 *
 * @ORM\Table(name="database")
 * @ORM\Entity
 */
class Database
{
    /**
     * @var integer
     *
     * @ORM\Column(name="database_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $databaseId;

    /**
     * @var string
     *
     * @ORM\Column(name="endpoint", type="string", length=255, nullable=false)
     */
    private $endpoint;

    /**
     * @var string
     *
     * @ORM\Column(name="region", type="string", length=50, nullable=false)
     */
    private $region;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=false)
     */
    private $description;

    /**
     * @var float
     *
     * @ORM\Column(name="utilization_percent", type="decimal", precision=10, scale=2, nullable=false)
     */
    private $utilizationPercent;

    /**
     * @var string
     *
     * @ORM\Column(name="create_time", type="string", length=255, nullable=false)
     */
    private $createTime;

    /**
     * @var string
     *
     * @ORM\Column(name="update_time", type="string", length=255, nullable=false)
     */
    private $updateTime;


}
