<?php
namespace memreas;

class MemreasTranscoderTables {

    protected $service_locator = NULL;
    protected $transcodeTransactionTable = NULL;
	    
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
	
}
?>
