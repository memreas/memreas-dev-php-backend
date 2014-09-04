<?php

namespace Application\memreas;

use Aws\Common\Aws;
use Aws\CloudFront\CloudFrontClient;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class MemreasSignedURL {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $aws;
	protected $s3;
	protected $cloud_front;
	
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( MemreasConstants::MEMREASDB );
		//$this->private_key_filename = getcwd () . '/key/pk-APKAJC22BYF2JGZTOC6A.pem';
		//$this->key_pair_id = 'VOCBNKDCW72JC2ZCP3FCJEYRGPS2HCVQ';
		$this->private_key_filename = getcwd () . '/key/pk-APKAISSKGZE3DR5HQCHA.pem';
		$this->key_pair_id = 'APKAISSKGZE3DR5HQCHA';
		$this->expires = time () + 3600; // 1 hour from now
		$this->signature_encoded = null;
		$this->policy_encoded = null;
		
		$this->aws = Aws::factory ( array (
				'key' => 'AKIAJMXGGG4BNFS42LZA',
				'secret' => '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',
				'region' => 'us-east-1' 
		) );
		
		// Fetch the S3 class
		$this->s3 = $this->aws->get ( 's3' );
		
		
		$this->aws = Aws::factory ( array (
				'key' => 'AKIAJ5JYKD6J3GCXMUAQ',
				'secret' => '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',
				'region' => 'us-east-1' 
		) );
		
		// Fetch the CloudFront class
		$this->cloud_front = $this->aws->get ( 'CloudFront' );
		
		/*
		$this->cloud_front = CloudFrontClient::factory(array(
				'private_key' => $this->private_key_filename,
				'key_pair_id' => $this->key_pair_id,
		));
		*/
		
	}
	
	public function fetchSignedURL($path) {
		if ((MemreasConstants::SIGNURLS) && !empty($path) && !is_array($path)) {
			$this->expires = time() + MemreasConstants::EXPIRES;

			//doesn't work...
			//$path = $this->cloud_front->getSignedUrl(array(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST.$path, $this->expires));

			$signed_url = $this->get_canned_policy_stream_name ( $path, $this->private_key_filename, $this->key_pair_id, $this->expires );
//error_log("Inside fetchSignedURL path after signing... ".$signed_url.PHP_EOL);
	
			return $signed_url;
		} else {
			return $path; //path is empty
		}			
	}
	
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		// 0 = not empty, 1 = empty
		$path = trim ( $data->signedurl->path );
		$time = time ();
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<signedurlresponse>";

		if (isset ( $path ) && ! empty ( $path )) {
			$signedurl = $this->get_canned_policy_stream_name ( $path, $this->private_key_filename, $this->key_pair_id, $this->expires );
			$xml_output .= "<status>success</status>";
			// $xml_output .= "<signedurl>$signedurl</signedurl>";
			$xml_output .= "<key_pair_id>$this->key_pair_id</key_pair_id>";
			$xml_output .= "<signature_encoded>$this->signature_encoded</signature_encoded>";
			$xml_output .= "<policy_encoded>$this->policy_encoded</policy_encoded>";
		} else {
			$xml_output .= "<status>failure</status><message>Please checked that you have given all the data required for signedurl.</message>";
		}
		$xml_output .= "</signedurlresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		error_log ( "SignedUrl ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
	function rsa_sha1_sign($policy, $private_key_filename) {
		$signature = "";
		
		// load the private key
		$fp = fopen ( $private_key_filename, "r" );
		$priv_key = fread ( $fp, 8192 );
		fclose ( $fp );
		$pkeyid = openssl_get_privatekey ( $priv_key );
		
		// compute signature
		openssl_sign ( $policy, $signature, $pkeyid );
		
		// free the key from memory
		openssl_free_key ( $pkeyid );
		
		return $signature;
	}
	function url_safe_base64_encode($value) {
		$encoded = base64_encode ( $value );
		// replace unsafe characters +, = and / with the safe characters -, _ and ~
		return str_replace ( array (
				'+',
				'=',
				'/' 
		), array (
				'-',
				'_',
				'~' 
		), $encoded );
	}
	function create_stream_name($stream, $policy, $signature, $key_pair_id, $expires) {
		$result = $stream;
		
		$path = ""; // Change made here to fix missing $path variable
		          
		// if the stream already contains query parameters, attach the new query parameters to the end
		          // otherwise, add the query parameters
		$separator = strpos ( $stream, '?' ) == FALSE ? '?' : '&';
		// the presence of an expires time means we're using a canned policy
		if ($expires) {
			$result .= $path . $separator . "Expires=" . $expires . "&Signature=" . $signature . "&Key-Pair-Id=" . $key_pair_id;
		} 		// not using a canned policy, include the policy itself in the stream name
		else {
			$result .= $path . $separator . "Policy=" . $policy . "&Signature=" . $signature . "&Key-Pair-Id=" . $key_pair_id;
		}
		
		// new lines would break us, so remove them
		return str_replace ( '\n', '', $result );
	}
	function encode_query_params($stream_name) {
		// the adobe flash player has trouble with query parameters being passed into it,
		// so replace the bad characters with their url-encoded forms
		return str_replace ( array (
				'?',
				'=',
				'&' 
		), array (
				'%3F',
				'%3D',
				'%26' 
		), $stream_name );
	}
	function get_canned_policy_stream_name($video_path, $private_key_filename, $key_pair_id, $expires) {
		// this policy is well known by CloudFront, but you still need to sign it, since it contains your parameters
		$canned_policy = '{"Statement":[{"Resource":"' . $video_path . '","Condition":{"DateLessThan":{"AWS:EpochTime":' . $expires . '}}}]}';
		// the policy contains characters that cannot be part of a URL, so we base64 encode it
		$encoded_policy = $this->url_safe_base64_encode ( $canned_policy );
		$this->policy_encoded = $encoded_policy;
		// sign the original policy, not the encoded version
		$signature = $this->rsa_sha1_sign ( $canned_policy, $private_key_filename );
		// make the signature safe to be included in a url
		$encoded_signature = $this->url_safe_base64_encode ( $signature );
		$this->signature_encoded = $encoded_signature;
		
		// combine the above into a stream name
		$stream_name = $this->create_stream_name ( $video_path, null, $encoded_signature, $key_pair_id, $expires );
		// url-encode the query string characters to work around a flash player bug
		
		// Commented this line there was no need to encode the query params for JW Player
		// return encode_query_params($stream_name);
		
		return $stream_name;
	}
}

?>
