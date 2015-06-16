<?php
namespace Application\memreas;
use Application\Model\MemreasConstants;
use Application\memreas\RmWorkDir;
use Application\memreas\MUUID;
use Application\memreas\Mlog;
use Application\Entity\ServerMonitor;
use Aws\Common\Aws;

class AWSManagerAutoScaler
{

    protected $aws = null;

    protected $service_locator = null;

    protected $dbAdapter = null;

    protected $autoscaler = null;

    public function __construct ($service_locator)
    {
        Mlog::addone(__FILE__ . __METHOD__, 
                'Inside AWSManagerAutoScaler constructor');
        try {
            Mlog::addone(__FILE__ . __METHOD__ . __LINE__, '...');
            $this->service_locator = $service_locator;
            Mlog::addone(__FILE__ . __METHOD__ . __LINE__, '...');
            $this->dbAdapter = $service_locator->get(
                    'doctrine.entitymanager.orm_default');
            Mlog::addone(__FILE__ . __METHOD__ . __LINE__, '...');
            $this->aws = Aws::factory(
                    array(
                            'key' => MemreasConstants::AWS_APPKEY,
                            'secret' => MemreasConstants::AWS_APPSEC,
                            'region' => MemreasConstants::AWS_APPREG
                    ));
            Mlog::addone(__FILE__ . __METHOD__ . __LINE__, '...');
            // Fetch the AutoScaling class
            $this->autoscaler = $this->aws->get('AutoScaling');
            Mlog::addone(__FILE__ . __METHOD__ . __LINE__, '...');
        } catch (Exception $e) {
            Mlog::addone(
                    __FILE__ . __METHOD__ . __LINE__ . 'Caught exception: ', 
                    $e->getMessage());
        }
        Mlog::addone(__FILE__ . __METHOD__, 
                'Exit AWSManagerAutoScaler constructor');
    }

    public function serverReadyToProcessTask ()
    {
        /*
         * Check CPU level
         */
        $server_data = $this->fetchServerData();
        
        /*
         * Check if server is in server_monitor
         */
        // $server = $this->checkServer($server_data['server_name']);
        $process_task = false;
        if ($server_data['cpu_util'][0] < 75) {
            /*
             * no servers so we're starting up - add me
             */
            // $this->addServer($server_data);
            $process_task = true;
        } else {
            /*
             * Server exists so update stats
             */
            // $this->updateServer($server_data);
            $process_task = false;
        }
        $server = $this->checkServer();
        Mlog::addone(__CLASS__ . __METHOD__ . '::$server', $server);
        return $process_task;
    }

    function fetchServerData ()
    {
        $server_data = [];
        $server_data['cpu_util'] = sys_getloadavg();
        $server_data['server_name'] = $_SERVER['SERVER_NAME'];
        $server_data['server_addr'] = $_SERVER['SERVER_ADDR'];
        $server_data['hostname'] = gethostname();
        // $memory = $this->get_server_memory_usage();
        Mlog::addone(__CLASS__ . __METHOD__ . '::misc', $server_data);
        if ($server_data['cpu_util'][0] > 80) {
            Mlog::addone(__CLASS__ . __METHOD__ . '::$server_data[cpu_util]>80', 
                    $server_data['cpu_util']);
        }
        return $server_data;
    }

    function checkServer ($server_name = null)
    {
        $query_string = "SELECT sm FROM " .
                 " Application\Entity\ServerMonitor sm";
        if ($server_name) {
            $query_string .= " where sm.server_name = '$server_name'";
        }
        
        $query = $this->dbAdapter->createQuery($query_string);
        return $query->getArrayResult();
    }

    function addServer ($server_data)
    {
        $tblServerMonitor = new \Application\Entity\ServerMonitor();
        $now = new \DateTime("now");
        $tblServerMonitor->server_id = MUUID::fetchUUID();
        $tblServerMonitor->server_name = $server_data['server_name'];
        $tblServerMonitor->server_addr = $server_data['server_addr'];
        $tblServerMonitor->hostname = $server_data['hostname'];
        $tblServerMonitor->status = ServerMonitor::WAITING;
        $tblServerMonitor->job_count = 0;
        $tblServerMonitor->cpu_util_1min = $server_data['cpu_util'][0];
        $tblServerMonitor->cpu_util_5min = $server_data['cpu_util'][1];
        $tblServerMonitor->cpu_util_15min = $server_data['cpu_util'][2];
        $tblServerMonitor->last_cpu_check = $now;
        $tblServerMonitor->start_time = $now;
        
        $this->dbAdapter->persist($tblServerMonitor);
        $this->dbAdapter->flush();
    }

    function updateServer ($server_data)
    {
        $now = new \DateTime("now");
        $qb = $this->dbAdapter->createQueryBuilder();
        $query = $qb->update('Application\Entity\ServerMonitor', 'sm')
            ->set('sm.cpu_util_1min', '?1')
            ->set('sm.cpu_util_5min', '?2')
            ->set('sm.cpu_util_15min', '?3')
            ->set('sm.last_cpu_check', '?4')
            ->where('sm.server_name = ?5')
            ->setParameter(1, $server_data['cpu_util'][0])
            ->setParameter(2, $server_data['cpu_util'][1])
            ->setParameter(3, $server_data['cpu_util'][2])
            ->setParameter(4, $now)
            ->setParameter(5, $server_data['server_name'])
            ->getQuery();
        $result = $query->getResult();
        return $result;
    }
}//END



