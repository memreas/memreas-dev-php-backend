<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class Copyright {
	public $copyright_id = NULL;
	public $copyright_batch_id = NULL;
	public $user_id = NULL;
	public $media_id = NULL;
	public $metadata = NULL;
	public $validated = NULL;
	public $update_time = NULL;
	public function exchangeArray($data) {
		// We only allow fetch by copyright_id and update so use $data
		$this->copyright_id = $data ['copyright_id'];
		$this->copyright_batch_id = $data ['copyright_batch_id'];
		$this->user_id = $data ['user_id'];
		$this->media_id = $data ['media_id'];
		$this->metadata = $data ['metadata'];
		$this->validated = $data ['validated'];
		$this->update_time = $data ['update_time'];
	}
}


