<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

class Media {
	public $media_id, $user_id, $is_profile_pic, $sync_status, $transcode_status, $metadata, $report_flag, $create_date, $update_date;
	protected $inputFilter;
	public function exchangeArray($data) {
		$this->user_id = (isset ( $data ['user_id'] )) ? $data ['user_id'] : $this->user_id;
		$this->media_id = (isset ( $data ['media_id'] )) ? $data ['media_id'] : $this->media_id;
		$this->is_profile_pic = (isset ( $data ['is_profile_pic'] )) ? $data ['is_profile_pic'] : $this->is_profile_pic;
		$this->sync_status = (isset ( $data ['sync_status'] )) ? $data ['sync_status'] : $this->sync_status;
		$this->transcode_status = (isset ( $data ['transcode_status'] )) ? $data ['transcode_status'] : $this->transcode_status;
		$this->metadata = (isset ( $data ['metadata'] )) ? $data ['metadata'] : $this->metadata;
		$this->report_flag = (isset ( $data ['report_flag'] )) ? $data ['report_flag'] : $this->report_flag;
		$this->create_date = (isset ( $data ['create_date'] )) ? $data ['create_date'] : $this->create_date;
		$this->update_date = (isset ( $data ['update_date'] )) ? $data ['update_date'] : $this->update_date;
	}
	// Add content to these methods:
	public function setInputFilter(InputFilterInterface $inputFilter) {
		throw new \Exception ( "Not used" );
	}
	public function getInputFilter() {
		if (! $this->inputFilter) {
			$inputFilter = new InputFilter ();
			$factory = new InputFactory ();
			
			$inputFilter->add ( $factory->createInput ( array (
					'name' => 'user_id',
					'required' => true,
					'filters' => array (
							array (
									'name' => 'StripTags' 
							) 
					) 
			) ) );
			
			$this->inputFilter = $inputFilter;
		}
		
		return $this->inputFilter;
	}
}

?>
