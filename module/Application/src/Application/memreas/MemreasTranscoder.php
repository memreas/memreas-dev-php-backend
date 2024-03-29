<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\Media;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;
use Application\Model\TranscodeTransaction;

class MemreasTranscoder {
	
	/*
	 * encoding compression presets
	 */
	const ULTRAFAST = 'ultrafast';
	const SUPERFAST = 'superfast';
	const VERYFAST = 'veryfast';
	const FASTER = 'faster';
	const FAST = 'fast';
	const MEDIUM = 'medium';
	const SLOW = 'slow';
	const SLOWER = 'slower';
	const VERYSLOW = 'veryslow';
	
	/*
	 * work directories
	 */
	const WEBHOME = '/var/www/ephemeral0/';
	const DESTDIR = 'media/';
	const IMAGEDIR = 'image/';
	const CONVDIR = 'media/';
	const THUMBNAILSDIR = 'thumbnails/';
	const AUDIODIR = 'audio/';
	const HLSDIR = 'hls/';
	const TSDIR = 'ts/';
	const WEBDIR = 'web/';
	const WEBMDIR = 'webm/';
	const FULLSIZE = 'fullsize/';
	const _79X80 = '79x80/';
	const _448X306 = '448x306/';
	const _384X216 = '384x216/';
	const _98X78 = '98x78/';
	const _1280x720 = '1280x720/';
	protected $user_id;
	protected $media_id;
	protected $s3prefixpath;
	protected $content_type;
	protected $s3path;
	protected $s3file_name;
	protected $s3file_basename_prefix;
	protected $is_video;
	protected $is_audio;
	protected $is_image;
	protected $applyCopyrightOnServer;
	protected $copyright;
	protected $copyright_array;
	protected $session;
	protected $aws_manager_receiver;
	protected $memreas_media;
	protected $memreas_media_metadata;
	protected $ffmpegcmd;
	protected $ffprobecmd;
	protected $MediaFileType;
	protected $MediaExt;
	protected $error_message = "";
	protected $duration = 0;
	protected $filesize = 0;
	protected $pass = 0;
	protected $type;
	protected $input_message_data_json;
	protected $json_metadata;
	protected $transcode_transaction_id;
	protected $transcode_job_duration;
	protected $server_lock;
	protected $priority;
	protected $transcode_start_time;
	protected $transcode_end_time;
	protected $service_locator;
	protected $memreas_transcoder_tables;
	protected $temp_job_uuid_dir;
	protected $homeDir;
	protected $destRandMediaName;
	protected $original_file_name;
	protected $MediaFileName;
	protected $nice_priority = 0;
	protected $compression_preset_web;
	protected $transcode_job_meta;
	protected $exception;
	protected $cmd;
	
	/*
	 * Thumbnail settings $tnWidth = 448; $tnHeight = 306; $tnfreqency = 60; //
	 * in seconds - 60 means every 60 seconds (minute) $errstr = '';
	 */
	public function __construct($aws_manager_receiver, $service_locator) {
		try {
			$this->aws_manager_receiver = $aws_manager_receiver;
			$this->temp_job_uuid_dir = 'bew_' . MUUID::fetchUUID ();
			$this->homeDir = self::WEBHOME . $this->temp_job_uuid_dir . '/';
			
			$this->service_locator = $service_locator;
			$this->entityManager = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
			$this->refreshDBConnection ();
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	function closeDBConnection() {
		try {
			$this->entityManager->flush ();
			$this->entityManager->getConnection ()->close ();
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__, 'Caught Exception::' . $e->getMessage );
			throw $e;
		}
	}
	function refreshDBConnection() {
		try {
			if ($this->entityManager->getConnection ()->ping () === false) {
				$this->closeDBConnection ();
				$this->entityManager->getConnection ()->connect ();
				Mlog::addone ( __CLASS__ . __METHOD__, '$this->entityManager->connect() is not valid ... fetching new' );
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__, '$this->entityManager->connect() is valid ... ' );
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__, 'Caught Exception::' . $e->getMessage );
			throw $e;
		}
	}
	public function markMediaForTranscoding($message_data) {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__, '::$message_data inside as json -> ' . json_encode ( $message_data, JSON_PRETTY_PRINT ) );
			
			// Register the stream wrapper from a client object
			$this->aws_manager_receiver->s3->registerStreamWrapper ();
			
			/*
			 * setup vars and store transaction
			 */
			if (isset ( $message_data ['is_video'] ) && ($message_data ['is_video'] == 1)) {
				$message_data ['is_image'] = 0;
				$message_data ['is_audio'] = 0;
			} else if (isset ( $message_data ['is_audio'] ) && ($message_data ['is_audio'] == 1)) {
				$message_data ['is_image'] = 0;
				$message_data ['is_video'] = 0;
			} else { // It's an image just resize and store thumbnails
				$message_data ['is_image'] = 1;
				$message_data ['is_video'] = 0;
				$message_data ['is_audio'] = 0;
			}
			
			//
			// Get the size of an object
			// - for video check size to set priority
			// - low priority are large video files over 100MB
			// - medium priority are video files < 100 MB
			// - high priority are all else (e.g. images, audio, etc).
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'about to get object filesize...' );
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$message_data--->', $message_data );
			$bucket = MemreasConstants::S3BUCKET;
			$path = "s3://$bucket/" . $message_data ['s3path'] . $message_data ['s3file_name'];
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::object filesize of ' . "path" . ' is::' . $path );
			$file_size = filesize ( $path );
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::object filesize is::' . $file_size );
			// $video_size = $this->aws_manager_receiver->s3->get_object_filesize ( MemreasConstants::S3BUCKET, $message_data ['s3path'], false );
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::object video_size is::' . $video_size );
			if ($file_size > MemreasConstants::SIZE_100MB) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::priority->low' );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::greater than MemreasConstants::SIZE_100MB--->' . MemreasConstants::SIZE_100MB );
				$message_data ['priority'] = 'low';
			} else if ($file_size > MemreasConstants::SIZE_10MB) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::priority->medium' );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::greater than MemreasConstants::SIZE_10MB--->' . MemreasConstants::SIZE_10MB );
				$message_data ['priority'] = 'medium';
			} else if ($file_size < MemreasConstants::SIZE_10MB) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::priority->high' );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::less than MemreasConstants::SIZE_10MB--->' . MemreasConstants::SIZE_10MB );
				$message_data ['priority'] = 'high';
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::priority->low' );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::MemreasConstants::SIZE_UNDEFINED' );
				$message_data ['priority'] = 'low';
			}
			
			//
			// Transcode Transaction data
			//
			$starttime = date ( 'Y-m-d H:i:s' );
			$this->user_id = $message_data ['user_id'];
			$this->media_id = $message_data ['media_id'];
			$this->content_type = $message_data ['content_type'];
			$this->s3path = $message_data ['s3path'];
			$this->s3file_name = $message_data ['s3file_name'];
			$this->original_file_name = $message_data ['s3file_name'];
			$this->input_message_data_json = json_encode ( $message_data );
			$this->transcode_status = 'backlog';
			$this->s3file_basename_prefix = $message_data ['s3file_basename_prefix'];
			$this->s3prefixpath = $this->user_id . '/' . $this->media_id . '/';
			$this->is_video = $message_data ['is_video'];
			$this->is_audio = $message_data ['is_audio'];
			$this->is_image = $message_data ['is_image'];
			$this->server_lock = $message_data ['server_lock'];
			$this->priority = $message_data ['priority'];
			$this->json_metadata = json_encode ( $message_data );
			$this->transcode_start_time = $this->now ();
			
			//
			// Media data
			//
			$this->memreas_media = $this->getMemreasTranscoderTables ()->getMediaTable ()->getMedia ( $this->media_id );
			$this->memreas_media_metadata = json_decode ( $this->memreas_media->metadata, true );
			
			$starttime = date ( 'Y-m-d H:i:s' );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] = array ();
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_started';
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@' . $starttime;
			
			//
			// All entries are backlog to start with
			//
			try {
				$row = $this->fetchTranscodeTransactionByMediaId ( $this->media_id );
			} catch ( \Exception $e ) {
				// do nothing entry doesn't exist
			}
			if ($row) {
				Mlog::addone ( '$row is not empty so entry exists', '...' );
				// error_log ( "" . print_r ( $row, true ) . PHP_EOL );
				$this->transcode_transaction_id = $row->transcode_transaction_id;
			} else {
				Mlog::addone ( '$message_data[backlog] is empty so this is new entry', '...' );
				$this->transcode_transaction_id = $this->persistTranscodeTransaction ();
			}
			
			return $this->transcode_transaction_id;
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	public function exec($message_data, $isUpload = false) {
		$cm = __CLASS__ . __METHOD__;
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::exec($message_data, $isUpload = false) $message_data  before as json::', json_encode ( $message_data, JSON_PRETTY_PRINT ) );
			/*
			 * Processing for current entry if set
			 */
			if (! empty ( $message_data ['backlog'] )) {
				$this->transcode_transaction_id = $message_data ['transcode_transaction_id'];
			}
			
			if (isset ( $message_data )) {
				if (getcwd () === '/var/www/memreas-dev-php-backend') {
					// AWS ffmpeg && ffprobe
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG;
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE;
				} else {
					// Local ffmpeg && ffprobe
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG_LOCAL;
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE_LOCAL;
				}
				
				// //////////////////////
				// create work folders
				$this->createFolders ();
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_folders_created';
				
				if (! $isUpload) {
					$this->user_id = $message_data ['user_id'];
					$s3file = $message_data ['s3path'] . $message_data ['s3file_name'];
					
					/*
					 * Fetch the file to transcode:
					 */
					$tmp_file = $this->homeDir . self::DESTDIR . $message_data ['s3file_name'];
					$response = $this->aws_manager_receiver->pullMediaFromS3 ( $s3file, $tmp_file );
					
					$this->destRandMediaName = $tmp_file;
					if ($response) {
						// update progress...
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_S3_file_saved';
					} else {
						// Something went wrong throw exception
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_error';
						$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error: S3 file fetch and save failed!';
						throw new \Exception ( "Transcoder: S3 file fetch and save failed!" );
					}
				}
				
				// Set file related data
				$this->original_file_name = $this->s3file_name;
				$this->message_data = json_encode ( $message_data );
				$this->MediaFileName = $this->s3file_basename_prefix;
				$this->MediaFileType = $message_data ['content_type'];
				$this->MediaExt = pathinfo ( $this->s3file_name, PATHINFO_EXTENSION );
				$this->filesize = filesize ( $this->destRandMediaName );
				
				if ($this->is_video || $this->is_audio) {
					/*
					 * Video Section
					 */
					// Let's use $this->MediaFileType variable to check wheather
					// uploaded file is supported.
					// We use PHP SWITCH statement to check valid video format,
					Mlog::addone ( $cm . __LINE__, '::$this->MediaFileType-->' . $this->MediaFileType );
					switch (strtolower ( $this->MediaFileType )) {
						case 'video/mp4' :
							break;
						case 'video/mov' :
							$this->MediaFileType = 'video/quicktime';
							break;
						case 'video/quicktime' :
							break;
						case 'video/avi' :
							break;
						case 'video/msvideo' :
							break;
						case 'video/avs-video' :
							break;
						case 'video/x-msvideo' :
							break;
						case 'video/x-ms-wmv' :
							break;
						case 'video/wmv' :
							break;
						case 'video/x-flv' :
							break;
						case 'video/3gpp' :
							break;
						case 'video/3gp' :
							break;
						case 'video/webm' :
							break;
						case 'video/mp1s' :
							break;
						case 'video/mp2p' :
							break;
						case 'video/mkv' :
							break;
						case 'video/mpeg' :
							break;
						case 'video/mpg' :
							break;
						case 'video/flv' :
							break;
						case 'video/wmv' :
							break;
						case 'video/divx' :
							break;
						case 'video/ogv' :
							break;
						case 'video/ogm' :
							break;
						case 'video/nut' :
							break;
						case 'video/vob' :
							break;
						case 'video/vro' :
							break;
						// audio
						case 'audio/caf' :
							break;
						case 'audio/vnd.wav' :
							break;
						case 'audio/mpeg' :
							break;
						default :
							{
								// Set status
								$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_error';
								$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error:.invalid_file_type:' . $this->MediaFileType;
								// output error and exit
								throw new \Exception ( 'Unsupported File!' );
							}
					} // End Switch
				}
				
				/*
				 * ffprobe here...
				 */
				if ($this->is_video || $this->is_audio) {
					// Calc media vars
					$this->cmd = $this->ffprobecmd . ' -v error -print_format json -show_format -show_streams ' . $this->destRandMediaName;
					Mlog::addone ( $cm, '::$this->cmd---->' . $this->cmd );
					
					try {
						$ffprobe_json = shell_exec ( $this->cmd );
						$ffprobe_json_array = json_decode ( $ffprobe_json, true );
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::_FFPROBE_::' . $this->cmd, $ffprobe_json );
						
						$this->duration = $ffprobe_json_array ['format'] ['duration'];
						$this->setNicePriorityAndCompression ();
						$this->filesize = $ffprobe_json_array ['format'] ['size'];
					} catch ( \Exception $e ) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::ffprobe cmd:: $this->cmd \n exception:: $e->getMessage()" );
					}
					$this->transcode_start_time = date ( "Y-m-d H:i:s" );
				} else {
					// image
					$ffprobe_json_array = [ ];
					$this->duration = 0; // image
					$this->filesize = filesize ( $this->destRandMediaName );
					$this->transcode_start_time = date ( "Y-m-d H:i:s" );
				}
				
				/*
				 * update status
				 */
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@' . $this->now ();
				$this->memreas_media_metadata ['S3_files'] ['ffprobe_data'] = $ffprobe_json_array;
				$this->memreas_media_metadata ['S3_files'] ['size'] = $this->filesize;
				
				/*
				 * update transcode_transaction
				 */
				$this->transcode_status = "in_progress";
				$this->persistTranscodeTransaction ();
				
				if ($this->is_video) {
					Mlog::addone ( "video duration is ", $this->duration );
					/*
					 * Thumbnails
					 */
					$this->createThumbNails ();
					
					$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'thumbnails_complete';
					$this->persistMedia ();
					
					/*
					 * Create transcode job array...
					 */
					$this->transcode_job_meta = array ();
					
					/*
					 * Apply copyright if needed
					 */
					Mlog::addone ( __CLASS__ . __METHOD__, "checking applyCopyrightOnServer for video" );
					if ((isset ( $message_data ['copyright'] )) & (! empty ( $message_data ['copyright'] ))) {
						Mlog::addone ( __CLASS__ . __METHOD__, '::$message_data [copyright]::' . $message_data ['copyright'] );
						$this->copyright = $message_data ['copyright']; // json_encoded
						$this->copyright_array = json_decode ( $message_data ['copyright'], true );
						Mlog::addone ( __CLASS__ . __METHOD__, '::$this->copyright_array [applyCopyrightOnServer]::' . $this->copyright_array ['applyCopyrightOnServer'] );
						if (($this->copyright_array ['applyCopyrightOnServer'] == 1) && (empty ( $this->copyright_array ['copyright_inscribed'] ))) {
							Mlog::addone ( __CLASS__ . __METHOD__, '::$message_data [applyCopyrightOnServer]::' . $message_data ['applyCopyrightOnServer'] . " is set" );
							$this->applyCopyrightOnServer = 1;
							$this->type = 'copyright';
							$this->transcode (); // set $this->transcode_job_meta in function
							Mlog::addone ( __CLASS__ . __METHOD__, "finished applying copyright" );
							$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'copyright inscribed';
							// set status to show web available
							$this->transcode_status = "success_copyright";
							// update media metadata and transcode transaction metadata
							$this->persistMedia ();
							$this->persistTranscodeTransaction ();
						} else {
							Mlog::addone ( __CLASS__ . __METHOD__, '::$message_data [applyCopyrightOnServer]::NOTSET' );
						}
					}
					
					/*
					 * Web quality mp4 conversion (h.264)
					 */
					Mlog::addone ( __CLASS__ . __METHOD__, "starting web video" );
					$this->type = 'web';
					$this->transcode (); // set $this->transcode_job_meta in function
					$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'web_mp4_complete';
					// set status to show web available
					$this->transcode_status = "success_web";
					$this->pass = "1";
					// update media metadata and transcode transaction metadata
					$this->persistMedia ();
					$this->persistTranscodeTransaction ();
					
					/*
					 * Create copy of web mp4 for web download
					 */
					try {
						$web_mp4 = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . '.mp4';
						$download_file = $this->s3prefixpath . "download/" . $this->s3file_name;
						$this->aws_manager_receiver->pushMediaToS3 ( $web_mp4, $download_file, "application/octet-stream" );
						$this->memreas_media_metadata ['S3_files'] ['download'] = $download_file;
					} catch ( \Exception $e ) {
						throw $e;
					}
					
					/*
					 * HLS conversion
					 */
					Mlog::addone ( __CLASS__ . __METHOD__, '$this->transcode ( hls )' );
					$this->type = 'hls';
					// set $this->transcode_job_meta in transcode function
					$this->transcode ();
					
					/*
					 * WEBM conversion
					 */
					Mlog::addone ( __CLASS__ . __METHOD__, '$this->transcode ( webm )' );
					$this->type = 'webm';
					// set $this->transcode_job_meta in transcode function
					$this->transcode ();
					
					// set status to show all (web,hls, webm) available
					$this->transcode_status = "success";
					$this->pass = "1";
					
					// update media metadata and transcode transaction metadata
					$this->persistMedia ();
					$this->persistTranscodeTransaction ();
					
					// End if ($is_video)
				} else if ($this->is_audio) {
					Mlog::addone ( $cm, '::Inside $this->is_audio cmd--->' . $this->cmd );
					
					// Audio section
					// Create web quality mp3
					$this->transcode_job_meta = array ();
					$this->type = 'audio';
					$this->transcode ();
					$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'audio_complete';
					// update media metadata and transcode transaction
					// metadata
					$this->persistMedia ();
					$this->persistTranscodeTransaction ();
				} else if ($this->is_image) {
					// Image section
					$this->transcode_job_meta = array ();
					$this->createThumbNails ( $this->is_image );
					$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'thumbnails_complete';
					// update media metadata and transcode transaction
					// metadata
					$this->persistMedia ();
					$this->persistTranscodeTransaction ();
				}
				
				/*
				 * Update the metadata here for the transcoded files
				 */
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_end@' . $this->now ();
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_end';
				
				/*
				 * Update transcode_transaction to mark completion
				 */
				$this->transcode_status = "success";
				$this->pass = "1";
				$this->transcode_end_time = $this->now ();
				$this->transcode_job_duration = strtotime ( $this->transcode_end_time ) - strtotime ( $this->transcode_start_time );
				$this->persistTranscodeTransaction ();
				
				/*
				 * Update media to mark completion
				 */
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_complete';
				$this->memreas_media_metadata ['S3_files'] ['transcode_status'] = $this->pass;
				$this->persistMedia ();
				
				// Debugging - log table entry
				Mlog::addone ( __CLASS__ . __METHOD__ . '::$this->persistMedia($this->memreas_media, 
                        $memreas_media_data_array)', $this->transcode_status );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->memreas_media_metadata::after::', $this->memreas_media_metadata );
			} // End if(isset($_POST))
			  
			//
			  // Clear Redis Cache for user
			  //
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'AWSMemreasRedisCache::getHandle() - invalidate start' );
			$redis = AWSMemreasRedisCache::getHandle ();
			$redis->invalidateCache ( "listallmedia_" . $this->user_id );
			$redis->invalidateCache ( "listnotification_" . $this->user_id );
			$redis->invalidateCache ( "viewevents_is_my_event_" . $this->user_id );
			$redis->invalidateCache ( "viewevents_is_friend_event_" . $this->user_id );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'AWSMemreasRedisCache::getHandle() - invalidate end' );
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Caught exception: ', $e->getMessage () );
			//
			// Fail transaction
			//
			// Transcode_transaction
			$this->transcode_status = "failure";
			$this->pass = "0";
			$this->transcode_end_time = $this->now ();
			$this->persistTranscodeTransaction ();
			
			// Media
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_failed';
			$this->memreas_media_metadata ['S3_files'] ['transcode_status'] = 0;
			$this->json_metadata = empty ( $this->memreas_media_metadata ) ? '' : json_encode ( $this->memreas_media_metadata );
			$memreas_media_data_array = array (
					'metadata' => $this->json_metadata,
					// 'transcode_status' => 'failure',
					'update_date' => $this->now () 
			);
			//
			// persist
			//
			$media_id = $this->persistMedia ( $this->memreas_media, $memreas_media_data_array );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "entry marked as failure to avoid infinite loop!" );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::catch throwing error', $this->transcode_status );
			
			//
			// Send email
			//
			$message_data ['command'] = isset ( $this->cmd ) ? ( string ) $this->cmd : "";
			$message_data ['error_line'] = ( string ) $e->getLine ();
			$message_data ['error_message'] = ( string ) $e->getMessage ();
			// $message_data ['error_trace'] = (string) $e->getTrace ();
			$this->aws_manager_receiver->sesEmailErrorToAdmin ( json_encode ( $message_data, JSON_PRETTY_PRINT ), __CLASS__ . "::error occurred during transcode" );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "email sent..." );
			
			throw $e;
		} finally {
			//
			// remove work dir
			//
			$result = $this->rmWorkDir ( $this->homeDir );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::removed directory::', $this->homeDir );
			
			//
			// Close db connection - avoid too many connections
			//
			$this->closeDBConnection ();
		}
		
		return $this->pass;
	}
	public function createThumbnails($is_image = null) {
		try {
			// base thumb size
			$tnWidth = 1280;
			$tnHeight = 720;
			
			if (! $this->is_image) {
				/*
				 * Thumbnails for videos
				 * Here let's determine how many thumbnails to make
				 * ex: >1 hr = 3600 seconds <--- store 20 thumbnails
				 */
				if ($this->duration > 3600) { // greater then 60 minutes
					$interval = $this->duration / 20;
				} else if ($this->duration > 300) { // greater than 5
				                                    // minutes
					$interval = $this->duration / 10;
				} else if ($this->duration > 60) { // greater than 1
				                                   // minutes
					$interval = $this->duration / 5;
				} else { // less than a minute
					$interval = $this->duration / 3;
				}
				// $interval = $this->duration/20; //create a total of 20
				// thumbnails
				if ($interval > 0) {
					$tnfreqency = 1 / $interval;
				}
				$imagename = 'thumbnail_' . $this->original_file_name . '_media-%d.png';
				$command = array (
						'-i',
						$this->destRandMediaName,
						'-s',
						$tnWidth . 'x' . $tnHeight,
						'-f',
						'image2',
						'-vf',
						'fps=fps=' . $tnfreqency,
						$this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . self::FULLSIZE . $imagename,
						'2>&1' 
				);
				
				$this->cmd = join ( " ", $command );
				$this->cmd = $this->ffmpegcmd . " " . $this->cmd;
				$op = shell_exec ( $this->cmd );
				$media_thumb_arr = glob ( $this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . self::FULLSIZE . 'thumbnail_' . $this->original_file_name . '_media-*.png' );
				$result = shell_exec ( "ls -al " . $this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . self::FULLSIZE );
				// Mlog::addone ( __CLASS__ . __METHOD__ . '::$media_thumb_arr ls -al', $result );
				// Mlog::addone ( __CLASS__ . __METHOD__ . '::$media_thumb_arr', json_encode ( $media_thumb_arr ) );
			} else {
				/*
				 * Thumbnails for images
				 */
				$media_thumb_arr = array (
						$this->destRandMediaName 
				);
				Mlog::addone ( __CLASS__ . __METHOD__ . ':: else $media_thumb_arr', $media_thumb_arr );
			}
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_built_thumbnails';
			
			$s3paths = array (
					"79x80" => $this->s3prefixpath . 'thumbnails/79x80/',
					"448x306" => $this->s3prefixpath . 'thumbnails/448x306/',
					"384x216" => $this->s3prefixpath . 'thumbnails/384x216/',
					"98x78" => $this->s3prefixpath . 'thumbnails/98x78/',
					"1280x720" => $this->s3prefixpath . 'thumbnails/1280x720/' 
			);
			
			/*
			 * This for loop fetches all the thumbnails just created
			 */
			$this->memreas_media_metadata ['S3_files'] ['thumbnails'] = '';
			foreach ( $media_thumb_arr as $filename ) {
				
				// ////////////////////////////////////////////////
				// Resize thumbnails as needed and save locally
				$tns_sized = array (
						"79x80" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_79X80, $filename, basename ( $filename ), 79, 80 ),
						"448x306" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_448X306, $filename, basename ( $filename ), 448, 306 ),
						"384x216" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_384X216, $filename, basename ( $filename ), 384, 216 ),
						"98x78" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_98X78, $filename, basename ( $filename ), 98, 78 ),
						"1280x720" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_1280x720, $filename, basename ( $filename ), 1280, 720 ) 
				);
				
				/*
				 * For each path I want to store in S3 what i just sized (79x80,
				 * 98x78, 384x216, 448x306, 1280x720)
				 * - reset thumbnails section section also
				 */
				foreach ( $tns_sized as $key => $file ) {
					// Push to S3
					$s3thumbnail_path = $s3paths ["$key"] . basename ( $filename );
					/*
					 * If image push each thumbnail
					 */
					if ($this->is_image) {
						$this->aws_manager_receiver->pushMediaToS3 ( $file, $s3thumbnail_path, "image/png" );
					}
					$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ["$key"] [] = $s3thumbnail_path;
				} // End for each tns_sized as file
			} // End for each thumbnail
			
			/*
			 * For videos upload the directory
			 */
			if (! $this->is_image) {
				
				// fullsize, 79x80, 448x306, 384x216, 98x78
				$local_thumnails_dir = rtrim ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR, "/" );
				$this->aws_manager_receiver->pushThumbnailsToS3 ( $local_thumnails_dir, $this->s3prefixpath . self::THUMBNAILSDIR );
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails';
			}
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	// end createThumNails()
	public function createFolders() {
		try {
			// Make directories here - create a unique directory by user_id
			$toCreate = array (
					$this->homeDir, // data/temp_uuid_dir/
					$this->homeDir . self::DESTDIR, // data/temp_job_uuid_dir/media/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR, // data/temp_job_uuid_dir/media/thumbnails/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::FULLSIZE, // data/temp_job_uuid_dir/media/thumbnails/79x80/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_79X80, // data/temp_job_uuid_dir/media/thumbnails/79x80/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_448X306, // data/temp_job_uuid_dir/media/thumbnails/448x306/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_384X216, // data/temp_job_uuid_dir/media/thumbnails/384x216/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_98X78, // data/temp_job_uuid_dir/media/thumbnails/98x78/
					$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_1280x720, // data/temp_job_uuid_dir/media/thumbnails/1280x720/
					$this->homeDir . self::DESTDIR . self::WEBDIR, // data/temp_job_uuid_dir/media/web/
					$this->homeDir . self::DESTDIR . self::AUDIODIR, // data/temp_job_uuid_dir/media/audio/
					$this->homeDir . self::DESTDIR . self::TSDIR, // data/temp_job_uuid_dir/media/ts/
					$this->homeDir . self::DESTDIR . self::HLSDIR, // data/temp_job_uuid_dir/media/hls/
					$this->homeDir . self::DESTDIR . self::WEBMDIR 
			); // data/temp_job_uuid_dir/media/webm/
			
			$permissions = 0777;
			foreach ( $toCreate as $dir ) {
				// mkdir($dir, $permissions, TRUE);
				$save = umask ( 0 );
				if (mkdir ( $dir )) {
					chmod ( $dir, $permissions );
				}
				umask ( $save );
				// error_log ( "created dir ---> $dir" . PHP_EOL );
			}
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	public function transcode() {
		try {
			
			// var setup
			$cm = __CLASS__ . __METHOD__;
			$mpeg4ext = '.mp4';
			$tsext = '.ts';
			$aacext = '.m4a';
			//
			// 4k codec level - can't be downloaded to apple
			//
			/*
			 * $isMP4 = false; // 4k
			 * $this->memreas_media_metadata['S3_files']['type']['video']['codec_level']
			 * = (isset(
			 * $ffprobe_json_array['format']['tags']['major_brand']) &&
			 * ! empty(
			 * $ffprobe_json_array['format']['tags']['major_brand'])) ?
			 * $ffprobe_json_array['format']['tags']['major_brand'] : "";
			 * if (strripos(
			 * $this->memreas_media_metadata['S3_files']['type']['video']['codec_level'],
			 * "mp4")) {
			 * $isMP4 = true;
			 * }
			 */
			Mlog::addone ( $cm . __LINE__, '::Inside transcode $this->type --->' . $this->type );
			
			if ($this->type == 'copyright') {
				
				// Sample command
				// ffmpeg -i input.mp4 -vf drawtext="fontfile=/usr/share/fonts/TTF/Vera.ttf: \
				// text='Stack Overflow': fontcolor=white: fontsize=24: box=1: boxcolor=black: \
				// x=(w-text_w)/2: y=(h-text_h-line_h)/2" -codec:a copy output.flv
				
				/*
				 * Add copyright as needed
				 */
				$copyrightMD5 = $this->copyright_array ['copyright_id_md5'];
				Mlog::addone ( $cm . __LINE__ . '$this->copyright_array [copyright_id_md5]', $this->copyright_array ['copyright_id_md5'] );
				$copyrightSHA256 = $this->copyright_array ['copyright_id_sha256'];
				Mlog::addone ( $cm . __LINE__ . '$this->copyright_array [copyright_id_sha256]', $this->copyright_array ['copyright_id_sha256'] );
				$mRight = "md5_" . $copyrightMD5 . " sha256_" . $copyrightSHA256;
				Mlog::addone ( $cm . __LINE__ . '$mRight', $mRight );
				$qv = ' -vf drawtext="fontfile=' . getcwd () . '/usr/share/fonts/memreas/segoescb.ttf:text=' . "'$mRight'" . ':fontsize=48:fontcolor=blue:x=0:y=0" ';
				Mlog::addone ( $cm . __LINE__ . '$qv', $qv );
				sleep ( 10 );
				// $transcoded_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . $mpeg4ext;
				// $transcoded_file_name = $this->MediaFileName . $mpeg4ext;
				$path_parts = pathinfo ( $this->destRandMediaName );
				$inscribed_file = $path_parts ['dirname'] . '/' . $path_parts ['basename'] . '.copy.' . $path_parts ['extension'];
				Mlog::addone ( $cm . __LINE__ . '::$inscribed_file::', $inscribed_file );
				// Mlog::addone ( $cm . __LINE__ ,$path_parts['dirname']);
				// Mlog::addone ( $cm . __LINE__ ,$path_parts['basename']);
				// Mlog::addone ( $cm . __LINE__ ,$path_parts['extension']);
				$this->cmd = 'nice -' . $this->nice_priority . ' ' . $this->ffmpegcmd . ' -i  ' . $this->destRandMediaName . $qv . $inscribed_file . ' 2>&1';
				Mlog::addone ( $cm . __LINE__ . '$this->cmd', $this->cmd );
				Mlog::addone ( $cm . __LINE__ . '::$copyright::', $this->copyright );
				$transcoded_file = $this->destRandMediaName;
			} else if ($this->type == 'web') {
				/*
				 * Test lossless with best compression
				 */
				$this->memreas_media_metadata ['S3_files'] ['type'] ['video'] ['width'] = (isset ( $ffprobe_json_array ['streams'] [0] ['width'] ) && ! empty ( $ffprobe_json_array ['streams'] [0] ['width'] )) ? $ffprobe_json_array ['streams'] [0] ['width'] : "";
				$this->memreas_media_metadata ['S3_files'] ['type'] ['video'] ['height'] = (isset ( $ffprobe_json_array ['streams'] [0] ['height'] ) && ! empty ( $ffprobe_json_array ['streams'] [0] ['height'] )) ? $ffprobe_json_array ['streams'] [0] ['height'] : "";
				
				// 6-DEC-2015 - baseline mp4 cmd
				$qv = ' -c:v libx264 ' . ' -profile:v high -level 4.0 ' . ' -preset ' . $this->compression_preset_web . ' -crf 18 ' . ' -pix_fmt yuv420p ' . ' -movflags ' . ' +faststart ' . ' -c:a aac ' . ' -strict experimental ' . '-b:a 128k ';
				
				//
				// apple doesn't support h.265 playback as of 9-SEP-2015 so we
				// need this for download but can't use for 4k??
				//
				$transcoded_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . $mpeg4ext;
				$transcoded_file_name = $this->MediaFileName . $mpeg4ext;
				$this->cmd = 'nice -' . $this->nice_priority . ' ' . $this->ffmpegcmd . ' -nostats -i  ' . $this->destRandMediaName . ' ' . $qv . ' ' . $transcoded_file . ' 2>&1';
			} else if ($this->type == 'hls') {
				Mlog::addone ( $cm, "else if ($this->type == 'hls')" );
				
				// Note: this section uses the transcoded web file
				// above
				$input_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . $mpeg4ext;
				$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName . $mpeg4ext;
				$transcoded_file_name = $this->MediaFileName . $mpeg4ext;
				$transcoded_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName . '.m3u8';
				$transcoded_hls_ts_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName;
				Mlog::addone ( $cm . '$transcoded_file', $transcoded_file );
				Mlog::addone ( $cm . '$transcoded_file', $transcoded_hls_ts_file );
				
				// //
				// // TODO: Encryption for .ts files not working - m3u8 protected by url signing and encyption
				// // *.ts files protected by aws encryption...
				// // Create file for segment encryption
				// 16-DEC-2015 - last working for fast start of 4k but still choppy
				// 19-JUL-2016 - HLS working better streaming...
				$this->cmd = 'nice -' . $this->nice_priority . ' ' . $this->ffmpegcmd . ' -nostats -re -y -i ' . $input_file . ' -pix_fmt yuv420p ' . ' -profile:v high -level 4.0 ' . ' -hls_list_size 0 ' . ' -hls_time 2 ' . ' -hls_allow_cache 1 ' . ' -hls_segment_filename ' . $transcoded_hls_ts_file . "%05d.ts " . $transcoded_file;
			} else if ($this->type == 'webm') {
				Mlog::addone ( $cm, 'else if ($this->type == webm)' );
				
				// Note: this section uses the transcoded web file above
				$input_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . $mpeg4ext;
				// $transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::WEBMDIR . $this->MediaFileName . $mpeg4ext;
				$transcoded_file = $this->homeDir . self::CONVDIR . self::WEBMDIR . $this->MediaFileName . '.webm';
				$transcoded_file_name = $this->MediaFileName . '.webm';
				Mlog::addone ( $cm . '$transcoded_file', $transcoded_file );
				
				// 19-JUL-2016 - VP9 encoding for webm
				// ffmpeg -i input.mp4 -c:v libvpx-vp9 -crf 10 -b:v 0 -c:a libvorbis output.webm
				Mlog::addone ( $cm . __LINE__, '$this->cmd::' . $this->cmd );
				$this->cmd = 'nice -' . $this->nice_priority . ' ' . $this->ffmpegcmd . ' -i ' . $input_file . ' -c:v libvpx-vp9 -crf 10 -b:v 0 -c:a libvorbis -threads 8 -strict -2 ' . $transcoded_file;
			} else if ($this->type == 'audio') {
				/*
				 * TODO: add audio cmd
				 */
				// Mlog::addone($cm,'::Inside audio section');
				$qv = ' -c:a libfdk_aac -movflags +faststart ';
				$transcoded_file = $this->homeDir . self::CONVDIR . self::AUDIODIR . $this->MediaFileName . $aacext;
				$transcoded_file_name = $this->MediaFileName . $aacext;
				$this->cmd = 'nice ' . $this->ffmpegcmd . " -i $this->destRandMediaName $qv $transcoded_file " . '2>&1';
				Mlog::addone ( $cm, '::$this->cmd---->' . $this->cmd );
				Mlog::addone ( $cm . '$transcoded_file', $transcoded_file );
			} else {
				throw new \Exception ( "MemreasTranscoder $this->type not found." );
			}
			
			$this->pass = 0;
			//
			// exec ffmpeg operation
			//
			Mlog::addone ( $cm . __LINE__, '$this->cmd-->' . $this->cmd );
			$this->execFFMPEG ( $transcoded_file );
			
			if ($this->type == 'copyright') {
				//
				// Delete original and replace with inscribed file
				//
				shell_exec ( "rm $this->destRandMediaName" );
				shell_exec ( "mv $inscribed_file $this->destRandMediaName" );
				shell_exec ( "rm $inscribed_file" );
				
				//
				// Change to inscribed file for web input
				//
				$this->copyright_array ['fileCheckSumMD5'] = md5_file ( $this->destRandMediaName );
				$this->copyright_array ['fileCheckSumSHA1'] = sha1_file ( $this->destRandMediaName );
				$this->copyright_array ['fileCheckSumSHA256'] = hash_file ( 'sha256', $this->destRandMediaName );
				$this->copyright_array ['copyright_inscribed'] = 1;
				$this->copyright = json_encode ( $this->copyright_array );
				Mlog::addone ( $cm . __LINE__ . '::$copyright::', $this->copyright );
			}
			
			//
			// Push to S3
			//
			$s3file = $this->s3prefixpath . $this->type . '/' . $transcoded_file_name;
			if ($this->type == "hls") {
				
				// Push m3u8 file
				$s3file = $this->s3prefixpath . $this->type . '/' . $this->MediaFileName . '.m3u8';
				$this->aws_manager_receiver->pushMediaToS3 ( $transcoded_file, $s3file, "application/x-mpegurl", true, MemreasConstants::S3HLSBUCKET, true );
				
				// Push all .ts files
				$pat = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName . "*.ts";
				$fsize = 0;
				
				foreach ( glob ( $pat ) as $filename ) {
					$fsize += filesize ( $filename );
					$s3tsfile = $this->s3prefixpath . $this->type . '/' . basename ( $filename );
					$this->aws_manager_receiver->pushMediaToS3 ( $filename, $s3tsfile, "video/mp2t", true, MemreasConstants::S3HLSBUCKET, true );
				}
			} else if ($this->type == "webm") {
				$this->aws_manager_receiver->pushMediaToS3 ( $transcoded_file, $s3file, "video/webm", true );
				$fsize = filesize ( $transcoded_file );
			} else if ($this->is_audio) {
				$this->aws_manager_receiver->pushMediaToS3 ( $transcoded_file, $s3file, "audio/m4a", true );
				$fsize = filesize ( $transcoded_file );
			} else {
				$this->aws_manager_receiver->pushMediaToS3 ( $transcoded_file, $s3file, "video/mp4", true );
				$fsize = filesize ( $transcoded_file );
			}
			
			// Log status
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_' . $this->type . '_upload_S3';
			$this->memreas_media_metadata ['S3_files'] [$this->type] = $s3file;
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_' . $this->type . '_completed';
			
			/**
			 * Dedup the array in case of retranscode
			 */
			if (! empty ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['79x80'] )) {
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['79x80'] = array_unique ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['79x80'] );
			}
			if (! empty ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['448x306'] )) {
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['448x306'] = array_unique ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['448x306'] );
			}
			if (! empty ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['384x216'] )) {
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['384x216'] = array_unique ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['384x216'] );
			}
			if (! empty ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['98x78'] )) {
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['98x78'] = array_unique ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['98x78'] );
			}
			if (! empty ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['1280x720'] )) {
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['1280x720'] = array_unique ( $this->memreas_media_metadata ['S3_files'] ['thumbnails'] ['1280x720'] );
			}
			Mlog::addone ( $cm, '::complete::transcode_status::' . $this->transcode_status . '::' . $this->type );
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	// End transcode
	private function execFFMPEG($transcoded_file) {
		try {
			// Log command
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "cmd::$this->cmd" );
			$this->transcode_job_meta [$this->type] ["ffmpeg_cmd"] = $this->cmd;
			$output_start_time = $this->now ();
			$this->transcode_job_meta [$this->type] ["output_start_time"] = $output_start_time;
			
			$this->persistTranscodeTransaction ();
			
			//
			// Execute ffmpeg command
			//
			$op = shell_exec ( $this->cmd );
			
			//
			// Refresh DB Connection in case mysql has gone away
			//
			$this->refreshDBConnection ();
			
			//
			// Fetch transcode_job_meta in case of GC
			//
			$row = $this->fetchTranscodeTransactionByMediaId ( $this->media_id );
			$this->transcode_job_meta = json_decode ( $row->metadata, true );
			
			//
			// Command should be complete check for file...
			//
			if (! file_exists ( $transcoded_file )) {
				throw new \Exception ( 'Failed to find $transcoded_file::' . $transcoded_file . '::op::' . $op );
			} else {
				// Transcode_Transaction status
				$this->pass = 1;
				// Log pass
				$this->transcode_job_meta [$this->type] ["ffmpeg_cmd_output"] = json_encode ( $op );
				// file size in bytes...
				$this->transcode_job_meta [$this->type] ["output_size"] = filesize ( $transcoded_file );
				$this->transcode_job_meta [$this->type] ["pass_fail"] = $this->pass;
				$this->transcode_job_meta [$this->type] ["error_message"] = "";
				$this->transcode_job_meta [$this->type] ["output_start_time"] = $output_start_time;
				$this->transcode_job_meta [$this->type] ["output_end_time"] = date ( "Y-m-d H:i:s" );
			}
			$this->persistTranscodeTransaction ();
			$this->persistMedia ();
		} catch ( \Exception $e ) {
			$this->pass = 0;
			error_log ( "transcoder $this->type failed - op -->" . $op . PHP_EOL );
			// Log pass
			// $this->transcode_job_meta [$this->type] ["ffmpeg_cmd"] = $this->cmd; //set above prior to call...
			$this->transcode_job_meta [$this->type] ["ffmpeg_cmd_output"] = json_encode ( $op );
			$this->transcode_job_meta [$this->type] ["pass_fail"] = $this->pass;
			$this->transcode_job_meta [$this->type] ["error_message"] = $e->getMessage ();
			$this->transcode_job_meta [$this->type] ["output_end_time"] = date ( "Y-m-d H:i:s" );
			$this->error_message = $e->getMessage ();
			$this->persistTranscodeTransaction ();
			// persist media data also
			$this->persistMedia ();
			throw $e;
		}
		return $op;
	}
	private function rmWorkDir($dir) {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'rmWorkDir ($dir)', $dir );
			$it = new \RecursiveDirectoryIterator ( $dir );
			$files = new \RecursiveIteratorIterator ( $it, \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ($file->getFilename () === '.' || $file->getFilename () === '..') {
					continue;
				}
				if ($file->isDir ()) {
					rmdir ( $file->getRealPath () );
				} else {
					unlink ( $file->getRealPath () );
				}
			}
			rmdir ( $dir );
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::rmWorkDir failed for dir::', $dir );
			throw $e;
		}
	}
	public function resizeImage($dirPath, $file, $thumbnail_name, $height, $width) {
		try {
			
			// Initialize Sybio
			$createFolders = true;
			$layer = ImageWorkshop::initFromPath ( $file );
			
			
			//get heigh and width 
			//
			// Check for orientation
			//
			$landscape = false;
			list($fwidth, $fheight) = getimagesize($file);
			Mlog::addone(__CLASS__.__METHOD__.__LINE__, "fwidth is $fwidth .... fheight is $fheight");
			if ($fwidth > $fheight) {
				// landscape only if width > height else portrait
				$landscape = true;
			}
			
 			Mlog::addone(__CLASS__.__METHOD__.__LINE__, "file is $file....");
 			//Mlog::addone(__CLASS__.__METHOD__.__LINE__, "orientation is $orientation....");
 			//sleep(3);
			
			// old code
			// $layer->resizeInPixel($height, $width, true, 0, 0, 'MM');
			
			//
			// Orient thumbnail properly
			//
			if ($landscape) {
				// landscape
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "thumbnail is landscape" );
				$layer->resizeInPixel ( $width, $height, true, 0, 0, 'MM' );
			} else {
				// portrait
				// flip width and height
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "thumbnail is portrait" );
				$layer->resizeInPixel ( $height, $width, true, 0, 0, 'MM' );
			}
			
			$backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
			$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0
			                    // to
			                    // 100%)
			$layer->save ( $dirPath, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality );
			$file = $dirPath . $thumbnail_name;
			
			return $file;
			
			// Maintains image
			// $layer->resizeInPixel($width, $height );
			// $layer->resizeInPixel( $height, $width );
			// $backgroundColor = null; // transparent, only for PNG (otherwise it
			// will
			// be white if set null)
			// $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0
			// to
			// 100%)
			// end old code
			
			/*
			 * //new method - odd output
			 * //3 - Get a portrait (or landscape) format not in a box:
			 * //http://phpimageworkshop.com/tutorial/2/creating-thumbnails.html
			 * $expectedWidth = $width;
			 * $expectedHeight = $height;
			 *
			 * // Determine the largest expected side automatically
			 * ($expectedWidth > $expectedHeight) ? $largestSide = $expectedWidth : $largestSide = $expectedHeight;
			 *
			 * // Get a squared layer
			 * $layer->cropMaximumInPixel(0, 0, "MM");
			 *
			 * // Resize the squared layer with the largest side of the expected thumb
			 * $layer->resizeInPixel($largestSide, $largestSide);
			 *
			 * // Crop the layer to get the expected dimensions
			 * $layer->cropInPixel($expectedWidth, $expectedHeight, 0, 0, 'MM');
			 */
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	function getMemreasTranscoderTables() {
		return new MemreasTranscoderTables ( $this->service_locator );
	}
	public function persistCopyright() {
		try {
			/*
			 * Fetch Copyright entry
			 */
			$row = $this->getMemreasTranscoderTables ()->getMediaTable ()->fetchTranscodeTransactionByMediaId ( $this->media_id );
			
			/*
			 * Store media
			 */
			$this->json_metadata = json_encode ( $this->memreas_media_metadata );
			$data_array = [ ];
			$data_array ['metadata'] = ! empty ( $this->json_metadata ) ? $this->json_metadata : '';
			$data_array ['transcode_status'] = ! empty ( $this->transcode_status ) ? $this->transcode_status : '';
			$data_array ['update_date'] = $this->now ();
			$this->memreas_media->exchangeArray ( $data_array );
			$media_id = $this->getMemreasTranscoderTables ()->getMediaTable ()->saveMedia ( $this->memreas_media );
		} catch ( \Exception $e ) {
			$error_data = [ ];
			$error_data ['error_line'] = $e->getLine ();
			$error_data ['error_message'] = $e->getMessage ();
			$error_data ['error_trace'] = $e->getTrace ();
			
			Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . __LINE__ . '::Caught exception: ', json_encode ( $error_data, JSON_PRETTY_PRINT ) );
			throw $e;
		}
	}
	public function persistMedia() {
		try {
			/*
			 * Store media
			 */
			$this->json_metadata = json_encode ( $this->memreas_media_metadata );
			$data_array = [ ];
			$data_array ['metadata'] = ! empty ( $this->json_metadata ) ? $this->json_metadata : '';
			$data_array ['transcode_status'] = ! empty ( $this->transcode_status ) ? $this->transcode_status : '';
			$data_array ['update_date'] = $this->now ();
			$this->memreas_media->exchangeArray ( $data_array );
			$media_id = $this->getMemreasTranscoderTables ()->getMediaTable ()->saveMedia ( $this->memreas_media );
		} catch ( \Exception $e ) {
			$error_data = [ ];
			$error_data ['error_line'] = $e->getLine ();
			$error_data ['error_message'] = $e->getMessage ();
			$error_data ['error_trace'] = $e->getTrace ();
			
			Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . __LINE__ . '::Caught exception: ', json_encode ( $error_data, JSON_PRETTY_PRINT ) );
			throw $e;
		}
	}
	public function fetchTranscodeTransactionByMediaId() {
		$row = $this->getMemreasTranscoderTables ()->getTranscodeTransactionTable ()->fetchTranscodeTransactionByMediaId ( $this->media_id );
		
		return $row;
	}
	public function persistTranscodeTransaction() {
		try {
			$data_array = [ ];
			$data_array ['user_id'] = ! empty ( $this->user_id ) ? $this->user_id : '';
			$data_array ['media_id'] = ! empty ( $this->media_id ) ? $this->media_id : '';
			$data_array ['file_name'] = ! empty ( $this->original_file_name ) ? $this->original_file_name : '';
			$data_array ['message_data'] = ! empty ( $this->input_message_data_json ) ? $this->input_message_data_json : '';
			$data_array ['media_type'] = ! empty ( $this->content_type ) ? $this->content_type : '';
			$data_array ['media_extension'] = ! empty ( $this->content_type ) ? $this->content_type : '';
			$data_array ['media_duration'] = ! empty ( $this->duration ) ? $this->duration : '';
			$data_array ['media_size'] = ! empty ( $this->filesize ) ? $this->filesize : '';
			$data_array ['transcode_status'] = ! empty ( $this->transcode_status ) ? $this->transcode_status : 'pending';
			$data_array ['pass_fail'] = ! empty ( $this->pass ) ? $this->pass : 0;
			$data_array ['metadata'] = ! empty ( $this->transcode_job_meta ) ? json_encode ( $this->transcode_job_meta ) : null;
			$data_array ['error_message'] = ! empty ( $this->error_message ) ? $this->error_message : 0;
			$data_array ['server_lock'] = ! empty ( $this->server_lock ) ? $this->server_lock : null;
			$data_array ['priority'] = ! empty ( $this->priority ) ? $this->priority : 'low';
			$data_array ['transcode_job_duration'] = ! empty ( $this->transcode_job_duration ) ? $this->transcode_job_duration : 0;
			$data_array ['transcode_start_time'] = ! empty ( $this->transcode_start_time ) ? $this->transcode_start_time : date ( 'Y-m-d H:i:s' );
			$data_array ['transcode_end_time'] = ! empty ( $this->transcode_end_time ) ? $this->transcode_end_time : null;
			
			if (empty ( $this->transcode_transaction_id )) {
				$transcode_transaction = new TranscodeTransaction ();
				$transcode_transaction->exchangeArray ( $data_array );
				$this->transcode_transaction_id = $this->getMemreasTranscoderTables ()->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
				Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . __LINE__ . '::Insert TranscodeTransaction: ', $this->transcode_transaction_id );
				return $this->transcode_transaction_id;
			} else { // Update
				$transcode_transaction = $this->getMemreasTranscoderTables ()->getTranscodeTransactionTable ()->getTranscodeTransaction ( $this->transcode_transaction_id );
				$transcode_transaction->exchangeArray ( $data_array );
				$this->getMemreasTranscoderTables ()->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
				Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . __LINE__ . '::Update TranscodeTransaction: ', $this->transcode_transaction_id );
				
				return $this->transcode_transaction_id;
			}
		} catch ( \Exception $e ) {
			$error_data = [ ];
			$error_data ['error_line'] = $e->getLine ();
			$error_data ['error_message'] = $e->getMessage ();
			$error_data ['error_trace'] = $e->getTrace ();
			
			Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . __LINE__ . '::Caught exception: ', json_encode ( $error_data, JSON_PRETTY_PRINT ) );
			throw $e;
		}
	}
	function setNicePriorityAndCompression() {
		try {
			// duration stored in db in seconds
			$duration_in_minutes = $this->duration / 60;
			if ($duration_in_minutes <= 5) {
				$this->nice_priority = 5;
				$this->compression_preset_web = self::MEDIUM;
			} else if ($duration_in_minutes > 5 && $duration_in_minutes <= 10) {
				$this->nice_priority = 10;
				$this->compression_preset_web = self::FASTER;
			} else if ($duration_in_minutes > 10 && $duration_in_minutes <= 30) {
				$this->nice_priority = 15;
				$this->compression_preset_web = self::VERYFAST;
			} else if ($duration_in_minutes > 30) {
				$this->nice_priority = 20;
				$this->compression_preset_web = self::ULTRAFAST;
			}
		} catch ( \Exception $e ) {
			throw $e;
		}
	}
	function now() {
		return date ( 'Y-m-d H:i:s' );
	}
} //End class


