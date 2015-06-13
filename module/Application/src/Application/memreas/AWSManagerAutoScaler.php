<?php
namespace Application\memreas;
use Application\Model\MemreasConstants;
use Application\memreas\RmWorkDir;
use Application\memreas\MUUID;
use Application\memreas\Mlog;
use Application\Entity\ServerMonitor;
use Aws\AutoScaling\AutoScalingClient;

class AWSManagerAutoScaler
{

    protected $service_locator = null;

    protected $dbAdapter = null;

    protected $autoscaler = null;

    public function __construct ($service_locator)
    {
        try {
            $this->service_locator = $service_locator;
            $this->dbAdapter = $service_locator->get(
                    'doctrine.entitymanager.orm_default');
            
            // Fetch the AutoScaling class
            $this->autoscaler = new AutoScalingClient(
                    [
                            'version' => 'latest',
                            'region' => 'us-east-1',
                            'credentials' => [
                                    'key' => MemreasConstants::AWS_APPKEY,
                                    'secret' => MemreasConstants::AWS_APPSEC
                            ]
                    ]);
        } catch (Exception $e) {
            error_log('Caught exception: ' . $e->getMessage() . PHP_EOL);
        }
        Mlog::addone(__FILE__ . __METHOD__, 
                'Exit AWSManagerReceiver constructor');
    }

    public function checkInstance ()
    {
        /*
         * Check CPU level
         */
        $server_data = $this->fetchServerData();
        
        /*
         * Check if server is in server_monitor
         */
        $server = $this->checkServer($server_data['server_name']);
        $process_task = false;
        if (empty($server)) {
            /*
             * no servers so we're starting up - add me
             */
            $this->addServer($server_data);
            $process_task = true;
        } else {
            /*
             * Server exists so update stats
             */
            $this->updateServer($server_data);
            
            /*
             * If server is backlogged and above 75% for 15m
             * then start new server
             */
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



