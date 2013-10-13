<?php
namespace memreas;

use Guzzle\Http\Client;
use Application\Model\MemreasConstants;

class UUID {

    protected static $instance = null;
    protected static $adapter = null;
    protected static $stmt = null;
    protected static $result = null;
    protected static $row = null;

	public static function getInstance($myadapter = NULL)
    {
        if (self::$instance === null) {
            self::$instance = new UUID();
            if (isset($myadapter)) {
              self::$adapter = $myadapter;
            }
        }
        return self::$instance;
    }

    /**
     * Private ctor so nobody else can instance it
     *
     */
    private function __construct()
    {

    }

	public static function fetchUUID() 
	{
		
		self::$stmt = self::$adapter->query('SELECT UUID() AS UUID');
 		self::$result = self::$stmt->execute();
 		self::$row = self::$result->current();

 		return self::$row['UUID'];

		/*

		$guzzle = new Client();

		$request = $guzzle->get(MemreasConstants::UUID_URL);
		$response = $request->send();
		$json = $response->getBody(true);
		$arr = json_decode($json, true);
		return $arr['UUID'];
		
		*/
	}
}
?>
