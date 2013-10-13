<?php
namespace memreas;

use memreas\UUID;

class MemreasTranscoderTables {

    protected $service_locator = NULL;
    protected $transcodeTransactionTable = NULL;
    protected $mediaTable = NULL;
    protected $uuid = NULL;
	    
	function __construct($sl) {
	   $this->service_locator = $sl;
	}
   
   	//Transcoder related tables
	public function getTranscodeTransactionTable()
	{
		if (!$this->transcodeTransactionTable) {
error_log("Inside else !transcodeTransactionTable");
			$this->transcodeTransactionTable = $this->service_locator->get('Application\Model\TranscodeTransactionTable');
		}
		return $this->transcodeTransactionTable;
	}
	
   	//Media table
	public function getMediaTable()
	{
		if (!$this->mediaTable) {
error_log("Inside else !mediaTable");
			$this->mediaTable = $this->service_locator->get('Application\Model\MediaTable');
		}
error_log("got mediaTable...");
		return $this->mediaTable;
	}
}
?>
