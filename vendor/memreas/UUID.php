<?php
namespace memreas;

use Guzzle\Http\Client;
use Application\Model\MemreasConstants;

class UUID {

	public static function fetchUUID() 
	{
		$guzzle = new Client();

		$request = $guzzle->get(MemreasConstants::UUID_URL);
		$response = $request->send();
		$json = $response->getBody(true);
		$arr = json_decode($json, true);
		return $arr['UUID'];
	}
}
?>
