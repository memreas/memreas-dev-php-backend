<?php
namespace memreas;

use Guzzle\Http\Client;

class UUID {
	const url_uuid = "http://192.168.1.9/eventapp_zend2.1/webservices/generateUUID_json.php";

	public static function fetchUUID() 
	{
		$guzzle = new Client();

		$request = $guzzle->get(UUID::url_uuid);
		$response = $request->send();
		$json = $response->getBody(true);
		$arr = json_decode($json, true);
		return $arr['UUID'];
	}
}
?>
