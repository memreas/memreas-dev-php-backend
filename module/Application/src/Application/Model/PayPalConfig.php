<?php 
namespace Application\Model;

class PayPalConfig
{
	// For a full list of configuration parameters refer in wiki page (https://github.com/paypal/sdk-core-php/wiki/Configuring-the-SDK)
	public static function getConfig()
	{
		$config = array(
				// values: 'sandbox' for testing
				//		   'live' for production
				"mode" => "sandbox"
	
				// These values are defaulted in SDK. If you want to override default values, uncomment it and add your value.
				// "http.ConnectionTimeOut" => "5000",
				// "http.Retry" => "2",
		);
		return $config;
	}
	
	// Creates a configuration array containing credentials and other required configuration parameters.
	public static function getAcctAndConfig()
	{
		$config = array(
				// Signature Credential
				"acct1.UserName" => "jmeah_seller1_api1.memreas.com",
				"acct1.Password" => "1370207047",
				"acct1.Signature" => "AFcWxV21C7fd0v3bYYYRCpSSRl31AmewT4FFrvVKRjV-o4CWf7RpfiYe",
				// Subject is optional and is required only in case of third party authorization
				// "acct1.Subject" => "",
				
				// Sample Certificate Credential
				// "acct1.UserName" => "certuser_biz_api1.paypal.com",
				// "acct1.Password" => "D6JNKKULHN3G5B8A",
				// Certificate path relative to config folder or absolute path in file system
				// "acct1.CertPath" => "cert_key.pem",
				// Subject is optional and is required only in case of third party authorization
				// "acct1.Subject" => "",
		
				);
		
		return array_merge($config, self::getConfig());;
	}

}
