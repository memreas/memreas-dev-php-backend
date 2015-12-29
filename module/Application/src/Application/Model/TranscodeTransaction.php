<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class TranscodeTransaction {
	public $transcode_transaction_id = NULL;
	public $user_id = NULL;
	public $media_id = NULL;
	public $file_name = NULL;
	public $message_data = NULL;
	public $media_type = NULL;
	public $media_extension = NULL;
	public $media_duration = NULL;
	public $media_size = NULL;
	public $transcode_status = NULL;
	public $pass_fail = NULL;
	public $metadata = NULL;
	public $error_message = NULL;
	public $server_lock = NULL;
	public $priority = NULL;
	public $transcode_job_duration = NULL;
	public $transcode_start_time = NULL;
	public $transcode_end_time = NULL;
	public function exchangeArray($data) {
		$this->transcode_transaction_id = (isset ( $data ['transcode_transaction_id'] )) ? $data ['transcode_transaction_id'] : $this->transcode_transaction_id;
		$this->user_id = (isset ( $data ['user_id'] )) ? $data ['user_id'] : $this->user_id;
		$this->media_id = (isset ( $data ['media_id'] )) ? $data ['media_id'] : $this->media_id;
		$this->file_name = (isset ( $data ['file_name'] )) ? $data ['file_name'] : $this->file_name;
		$this->message_data = (isset ( $data ['message_data'] )) ? $data ['message_data'] : $this->message_data;
		$this->media_type = (isset ( $data ['media_type'] )) ? $data ['media_type'] : $this->media_type;
		$this->media_extension = (isset ( $data ['media_extension'] )) ? $data ['media_extension'] : $this->media_extension;
		$this->media_duration = (isset ( $data ['media_duration'] )) ? $data ['media_duration'] : $this->media_duration;
		$this->media_size = (isset ( $data ['media_size'] )) ? $data ['media_size'] : $this->media_size;
		$this->transcode_status = (isset ( $data ['transcode_status'] )) ? $data ['transcode_status'] : $this->transcode_status;
		$this->pass_fail = (isset ( $data ['pass_fail'] )) ? $data ['pass_fail'] : $this->pass_fail;
		$this->metadata = (isset ( $data ['metadata'] )) ? $data ['metadata'] : $this->metadata;
		$this->error_message = (isset ( $data ['error_message'] )) ? $data ['error_message'] : $this->error_message;
		$this->server_lock = (isset ( $data ['server_lock'] )) ? $data ['server_lock'] : $this->server_lock;
		$this->priority = (isset ( $data ['priority'] )) ? $data ['priority'] : $this->priority;
		$this->transcode_job_duration = (isset ( $data ['transcode_job_duration'] )) ? $data ['transcode_job_duration'] : $this->transcode_job_duration;
		$this->transcode_start_time = (isset ( $data ['transcode_start_time'] )) ? $data ['transcode_start_time'] : $this->transcode_start_time;
		$this->transcode_end_time = (isset ( $data ['transcode_end_time'] )) ? $data ['transcode_end_time'] : $this->transcode_end_time;
	}
}


