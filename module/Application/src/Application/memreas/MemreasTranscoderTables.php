<?php

namespace Application\memreas;

class MemreasTranscoderTables {
	protected $service_locator = NULL;
	protected $transcodeTransactionTable = NULL;
	protected $mediaTable = NULL;
	protected $uuid = NULL;
	function __construct($sl) {
		$this->service_locator = $sl;
	}
	
	// Transcoder related tables
	public function getTranscodeTransactionTable() {
		if (! $this->transcodeTransactionTable) {
			$this->transcodeTransactionTable = $this->service_locator->get ( 'Application\Model\TranscodeTransactionTable' );
		}
		return $this->transcodeTransactionTable;
	}
	
	// Media table
	public function getMediaTable() {
		if (! $this->mediaTable) {
			$this->mediaTable = $this->service_locator->get ( 'Application\Model\MediaTable' );
		}
		return $this->mediaTable;
	}
}
?>
