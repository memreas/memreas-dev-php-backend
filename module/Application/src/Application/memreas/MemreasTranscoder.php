<?php

namespace Application\memreas;

use Zend\Session\Container;
use PHPImageWorkshop\ImageWorkshop;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
// memreas custom
use Application\memreas\MemreasTranscoderTables;
use Application\memreas\MUUID;
// memreas models
use Application\Model\MemreasConstants;
use Application\Model\Media;
use Application\Model\MediaTable;
use Application\Model\TranscodeTransaction;
use Application\Model\TranscodeTransactionTable;


class MemreasTranscoder {
	protected $user_id;
	protected $media_id;
	protected $s3prefixpath;
	protected $content_type;
	protected $s3path;
	protected $s3file_name;
	protected $is_video;
	protected $is_audio;
	protected $is_image;
	protected $session;
	protected $aws_manager_receiver;
	protected $memreas_media;
	protected $memreas_media_metadata;
	protected $ffmpegcmd;
	protected $ffprobecmd;
	
	
	protected $MediaFileType;
	protected $MediaExt;
	protected $duration=0;
	protected $filesize=0;
	protected $pass=0;
	protected $json_metadata;
	protected $transcode_transaction_id;
	protected $transcode_job_duration;
	protected $transcode_start_time;
	protected $transcode_end_time;
	protected $url_signer;
	protected $memreas_transcoder_tables;
	
	
	// Directory related variables - create a unique directory by user_id
	protected $temp_job_uuid_dir;
	protected $homeDir;
	protected $destRandMediaName;
	protected $original_file_name;
	protected $MediaFileName;
	
	/*
	 * 14-SEP-2014 Testing new mounted ephemeral storage
	 */
	//const WEBHOME 		= '/var/app/current/data/';
	const WEBHOME 		= '/var/app/ephemeral0/';
	const DESTDIR 		= 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const IMAGEDIR 		= 'image/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const CONVDIR 		= 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const _1080PDIR 	= '1080p/'; // Your 1080p Dir, end with slash (/)
	const THUMBNAILSDIR = 'thumbnails/'; // Your thumbnails Dir, end with slash (/)
	const AUDIODIR 		= 'audio/'; // Your audio Dir, end with slash (/)
	const HLSDIR 		= 'hls/'; // Your hls Dir, end with slash (/)
	const TSDIR 		= 'ts/'; // Your hls Dir, end with slash (/)
	const WEBDIR		= 'web/'; // Your web Dir, end with slash (/)
	const WEBMDIR 		= 'webm/'; // Your webm Dir, end with slash (/)
	const FLVDIR 		= 'flv/'; // Your flv Dir, end with slash (/)
	const FULLSIZE	 	= 'fullsize/'; // Your fullsize Dir, end with slash (/)
	const _79X80 		= '79x80/'; // Your 79x80 Dir, end with slash (/)
	const _448X306 		= '448x306/'; // Your 448x306 Dir, end with slash (/)
	const _384X216 		= '384x216/'; // Your 384x216 Dir, end with slash (/)
	const _98X78 		= '98x78/'; // Your 98x78 Dir, end with slash (/)
	const _1280x720 = '1280x720/'; // Your 1280x720 Dir, end with slash (/)
	
	/*
	 * Thumbnail settings $tnWidth = 448; $tnHeight = 306; $tnfreqency = 60; // in seconds - 60 means every 60 seconds (minute) $errstr = '';
	 */
	public function __construct($aws_manager_receiver) {
		$this->aws_manager_receiver = $aws_manager_receiver;
		$this->temp_job_uuid_dir = MUUID::fetchUUID ();
		$this->homeDir = self::WEBHOME . $this->temp_job_uuid_dir . '/'; // Home Directory ends with / (slash) :::: Your AMAZON home
	}
	
	
	function shutDownFunction() {
		$error = error_get_last();
		error_log("FATAL_ERROR::".print_r($error, true).PHP_EOL);
	}
	
	public function exec($message_data, $memreas_transcoder_tables, $service_locator, $isUpload = false) {
		
		try {

			$starttime = date ( 'Y-m-d H:i:s' );
			$this->user_id = $message_data ['user_id'];
			$this->media_id = $message_data ['media_id'];
			$this->content_type = $message_data ['content_type'];
			$this->s3path = $message_data ['s3path'];
			$this->s3file_name = $message_data ['s3file_name'];
			$this->s3prefixpath= $this->user_id.'/'.$this->media_id.'/';
			$this->is_video = $message_data ['is_video'];
			$this->is_audio = $message_data ['is_audio'];
			$this->is_image = $message_data ['is_image'];
			$this->json_metadata = json_encode($message_data);
			$this->url_signer = new MemreasSignedURL($message_data, $memreas_tables, $service_locator);
			$this->memreas_transcoder_tables = $memreas_transcoder_tables;
			

			/*
			 * 5-SEP-2014 - Refactoring code - adding fetch function
			 */
			$this->memreas_media = $this->memreas_transcoder_tables->getMediaTable ()->getMedia ( $this->media_id );
			$this->memreas_media_metadata = json_decode ( $this->memreas_media->metadata, true );

			$starttime = date ( 'Y-m-d H:i:s' );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@'.$starttime;
			
			if (isset ( $message_data )) {
				if (getcwd () == '/var/app/current') {
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG; // :::: AWS ffmpeg installation
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE; // :::: AWS ffprobe installation
				} else {
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG_LOCAL; // :::: Your ffmpeg installation
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE_LOCAL; // :::: Your ffmpeg installation
				}
				
				////////////////////////
				// create work folders
				$this->createFolders ();
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_folders_created';
				
				if (! $isUpload) {
					$this->user_id = $message_data ['user_id'];
					$s3file = $message_data ['s3path'] . $message_data ['s3file_name'];
						
					/*
					 *  Fetch the file to transcode:
					 */
					$tmp_file = $this->homeDir . self::DESTDIR . $message_data ['s3file_name'];
error_log("pulling from S3 ".$s3file." to tmp_file ---> ".$tmp_file.PHP_EOL);					
					$response = $this->aws_manager_receiver->pullMediaFromS3 ( $s3file, $tmp_file );
					
					$this->destRandMediaName = $tmp_file;
					if ($response) {
						//update progress...
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_S3_file_saved';
					} else {
						// Something went wrong throw exception
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_error';
						$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error: S3 file fetch and save failed!';
						throw new \Exception ( "Transcoder: S3 file fetch and save failed!" );
					}

					/*
					 * 10-SEP-2014 - make a copy on S3 as application/octet-stream for download
					*/
					// Copy an object and add server-side encryption.
error_log("s3file---> ".$s3file.PHP_EOL);					
error_log("MemreasConstants::S3BUCKET ---> " . MemreasConstants::S3BUCKET .PHP_EOL);					
error_log("s3file---> ".$s3file.PHP_EOL);
// error_log("CopySource ---> "."{". MemreasConstants::S3BUCKET . "}/{" . $s3file . "}" .PHP_EOL);	
// 					$download_file = $this->s3prefixpath . "download/" . $this->s3file_name;				
// error_log("download_file->".$download_file .PHP_EOL);	
// 					$result = $this->aws_manager_receiver->copyMediaInS3( MemreasConstants::S3BUCKET, $download_file, $s3file);
// 					$this->memreas_media_metadata ['S3_files'] ['download']  = $download_file;
						
					$download_file = $this->s3prefixpath . "download/" . $this->s3file_name;				
					//$download_file = $this->s3path . "download/" . $this->s3file_name;
					$this->aws_manager_receiver->pushMediaToS3($tmp_file, $download_file, "application/octet-stream");
					$this->memreas_media_metadata ['S3_files'] ['download']  = $download_file;
				}

				// Set file related data
				$this->original_file_name = $this->s3file_name;
				$this->MediaFileName = basename( $this->s3file_name );
				$this->MediaFileType = $message_data ['content_type'];
				$this->MediaExt = pathinfo($this->s3file_name, PATHINFO_EXTENSION);
				$this->filesize = filesize ( $this->destRandMediaName );

				if ( $this->is_video ) { 
					/*
					 * Video Section
					 */
					// Let's use $this->MediaFileType variable to check wheather uploaded file is supported.
					// We use PHP SWITCH statement to check valid video format, PHP SWITCH is similar to IF/ELSE statements
					// suitable if we want to compare the a variable with many different values
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
								throw new \Exception ( 'Unsupported File!' ); // output error and exit
							}
					} // End Switch
				}
				
				if ($this->is_video  || $this->is_audio) {
					//Calc media vars
					$this->duration = str_replace ( ",", "", shell_exec ( "$this->ffmpegcmd -i $this->destRandMediaName 2>&1 | grep 'Duration' | cut -d ' ' -f 4" ) );
					$timed = explode ( ":", $this->duration );
					$this->duration = (( float ) $timed [0]) * 3600 + (( float ) $timed [1]) * 60 + ( float ) $timed [2];
					$this->filesize = filesize ( $this->destRandMediaName );
					$this->transcode_start_time = date ( "Y-m-d H:i:s" );
				} else {
					$this->duration = 0; //image
					$this->filesize = filesize ( $this->destRandMediaName );
					$this->transcode_start_time = date ( "Y-m-d H:i:s" );
				}
				
				/*
				 * Insert transcode_transaction so we have a record
				 */
				$now = date ( 'Y-m-d H:i:s' );
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@'.$now;
				$this->memreas_media_metadata ['S3_files'] ['size'] = $this->filesize;
				//$transcode_transaction_ = $this->persistTranscodeTransaction();
				$this->transcode_transaction_id = $this->persistTranscodeTransaction();
				
				if ($this->is_video) {
error_log ( "video duration is ".$this->duration.PHP_EOL );

					/*
					 * Thumbnails
					 */
error_log ( "starting thumbnails...".PHP_EOL );
					$this->createThumbNails ();
	
error_log ( "finished thumbnails".PHP_EOL );
					$now = date ( 'Y-m-d H:i:s' );
					$this->json_metadata = json_encode ( $this->memreas_media_metadata );
					$memreas_media_data_array = array (
							'metadata' => $this->json_metadata,
							'update_date' => $now
					);
					$media_id = $this->persistMedia($this->memreas_media, $memreas_media_data_array);
//error_log ( "memreas media json metadata after ----> " . $this->json_metadata . PHP_EOL );

					/*
					 * Web quality mp4 conversion
					 */
					$transcode_job_meta = array ();
error_log ( "starting web video".PHP_EOL );
					$transcode_job_meta ['web'] = $this->transcode ( 'web' );
					//$this->memreas_media_metadata ['S3_files']['web'] = $transcode_job_meta ['web'];
error_log ( "finished web video".PHP_EOL );
					$now = date ( 'Y-m-d H:i:s' );
					$this->json_metadata = json_encode ( $this->memreas_media_metadata );
					$memreas_media_data_array = array (
							'metadata' => $this->json_metadata,
							'update_date' => $now
					);
					$media_id = $this->persistMedia($this->memreas_media, $memreas_media_data_array);

//error_log("finished transcode web for video this->memreas_media_metadata ---> " . json_encode ( $this->memreas_media_metadata ) .PHP_EOL);
//error_log("finished transcode web for video transcode_job_meta ---> " . json_encode ( $transcode_job_meta ) .PHP_EOL);
						
					
					/*
					 * High quality mp4 conversion
					 */
error_log ( "starting 1080p video".PHP_EOL );
					$transcode_job_meta ['1080p'] = $this->transcode ( '1080p' );
error_log ( "finished 1080p video".PHP_EOL );
					//$this->memreas_media_metadata ['S3_files']['1080p'] = $transcode_job_meta ['1080p'];
					$now = date ( 'Y-m-d H:i:s' );
					$this->json_metadata = json_encode ( $this->memreas_media_metadata );
					$memreas_media_data_array = array (
							'metadata' => $this->json_metadata,
							'update_date' => $now
					);
					$media_id = $this->persistMedia($this->memreas_media, $memreas_media_data_array);

					// Create webm file
//					$transcode_job_meta ['webm'] = $this->transcode ( 'webm' );
					// Create flash file
//					$transcode_job_meta ['flv'] = $this->transcode ( 'flv' );
 					// Create ts
// 					$transcode_job_meta ['ts'] = $this->transcode ( 'ts' );
					// Create hls
//					$transcode_job_meta ['hls'] = $this->transcode ( 'hls' );
				} // End if ($is_video)
				else if ($this->is_audio) {  
					//Audio section
					// Create web quality mp3
					$transcode_job_meta = array ();
					$transcode_job_meta ['audio'] = $this->transcode ( 'audio' );
					$this->memreas_media_metadata ['S3_files']['1080p'] = $transcode_job_meta ['1080p'];
					// Update the metadata here for the transcoded files

				} 	else if ($this->is_image) {  
					//Image section
					$transcode_job_meta = array ();
					$transcode_job_meta = $this->createThumbNails ($this->is_image);
					
					//error_log("finished thumbnails for image transcode_job_meta ---> " . json_encode ( $transcode_job_meta ) .PHP_EOL);
								
				}
				
				//////////////////////////////////////////////////////////////
				// Update the metadata here for the transcoded files
				$now = date ( 'Y-m-d H:i:s' );
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_end@'.$now;
				
				///////////////////////////////
				// Update transcode_transaction
				$this->pass = "1";
				$this->transcode_end_time = date("Y-m-d H:i:s");
				$transcode_transaction_data = array();
				$transcode_transaction_data['pass'] = $this->pass;
				$transcode_transaction_data['metadata'] = json_encode ( $transcode_job_meta );
				$transcode_transaction_data['transcode_end_time'] = date("Y-m-d H:i:s");
				$transcode_transaction_data['transcode_job_duration'] = strtotime ( $this->transcode_end_time ) - strtotime ( $this->transcode_start_time );

				$transcode_transaction = $this->memreas_transcoder_tables->getTranscodeTransactionTable()->getTranscodeTransaction($this->transcode_transaction_id);
				$transaction_id = $this->persistTranscodeTransaction($transcode_transaction, $transcode_transaction_data);	
				
				///////////////////////////////
				// Update the media table entry here
				$now = date ( 'Y-m-d H:i:s' );
				$this->json_metadata = json_encode ( $this->memreas_media_metadata );
				$memreas_media_data_array = array (
						'metadata' => $this->json_metadata,
						'update_date' => $now 
				);
				$media_id = $this->persistMedia($this->memreas_media, $memreas_media_data_array);
			} // End if(isset($_POST))
		} catch ( \Exception $e ) {
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}

		// Always delete the temp dir...
		// Delete the temp dir if we got this far...
		try {
			$result = $this->rmWorkDir ( $this->homeDir );
		} catch ( \Exception $e ) {
			$this->pass = 0;
			error_log ( "error string ---> " . $e->getMessage () . PHP_EOL );
		}
		return $this->pass;
	}
	
	public function createThumbnails($is_image = null) {
		// //////////////////////
		// Thumbnails section
		// //////////////////////
		$tnWidth = 1280; 
		$tnHeight = 720; 

		if (!$this->is_image) {
			//$tnfreqency = 1/360;  //every 360 seconds take a thumbnail
			/*
			 * Here let's determine how many thumbnails to make
			 *   ex: >1 hr = 3600 seconds <--- store 20 thumbnails 
			 */
			if ($this->duration > 3600) { //greater then 60 minutes
				$interval = $this->duration/20;
			} else if ($this->duration > 300) { //greater than 5 minutes
				$interval = $this->duration/10;
			} else if ($this->duration > 60) { //greater than 1 minutes
				$interval = $this->duration/5; 
			} else  { //less than a minute
				$interval = $this->duration/3; 
			}
			//$interval = $this->duration/20;  //create a total of 20 thumbnails
			$tnfreqency = 1 / $interval;
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
					$this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . self::FULLSIZE. $imagename,
					'2>&1' 
			);
			
			$cmd = join ( " ", $command );
			$cmd = $this->ffmpegcmd . " " . $cmd;
			// echo "$cmd<br>";
			$op = shell_exec ( $cmd );
			$media_thumb_arr = glob ( $this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . self::FULLSIZE . 'thumbnail_' . $this->original_file_name . '_media-*.png' );
error_log("media_thumb_arr ----> ".json_encode($media_thumb_arr).PHP_EOL);			
		} else {
			$media_thumb_arr = array ($this->destRandMediaName);
		}
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_built_thumbnails';
		
		$s3paths = array (
				"79x80" => $this->s3prefixpath.'thumbnails/79x80/',
				"448x306" => $this->s3prefixpath.'thumbnails/448x306/',
				"384x216" => $this->s3prefixpath.'thumbnails/384x216/',
				"98x78" => $this->s3prefixpath.'thumbnails/98x78/',
				"1280x720" => $this->s3prefixpath.'thumbnails/1280x720/',
		);
		
		/*
		 * This for loop fetches all the thumbnails just created
		 */
		foreach ( $media_thumb_arr as $filename ) {
				
			// ////////////////////////////////////////////////
			// Resize thumbnails as needed and save locally
			$tns_sized = array (
					//"full" => $filename,
					"79x80" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_79X80, $filename, basename($filename), 79, 80 ),
					"448x306" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_448X306, $filename, basename($filename), 448, 306 ),
					"384x216" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_384X216, $filename, basename($filename), 384, 216 ),
					"98x78" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_98X78, $filename, basename($filename), 98, 78 ),
					"1280x720" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_1280x720, $filename, basename($filename), 1280, 720 )
			);
			
			/*
			 * For each path I want to store in S3 what i just sized (79x80, 98x78, 384x216, 448x306, 1280x720)
			 */
			foreach ( $tns_sized as $key => $file ) {
				//Push to S3
				$s3thumbnail_path = $s3paths["$key"] . basename($filename);
				/*
				 * If image push each thumbnail
				 */
				if (is_image) {
					$this->aws_manager_receiver->pushMediaToS3($file, $s3thumbnail_path, "image/png");
				}					
				$this->memreas_media_metadata ['S3_files'] ['thumbnails'] ["$key"] [] = $s3thumbnail_path;
			} //End for each tns_sized as file				 
		} // End for each thumbnail
error_log("meta after for loop ----> ".json_encode($this->memreas_media_metadata).PHP_EOL);
		
		if (!is_image) {
			//fullsize
			$local_thumnails_dir = rtrim($this->homeDir . self::DESTDIR . self::THUMBNAILSDIR,"/");
			$this->aws_manager_receiver->pushThumbnailsToS3( $local_thumnails_dir, $this->s3path.self::THUMBNAILSDIR );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails';
	
			//79x80
			$local_thumnails_dir = rtrim($this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_79X80,"/");
			$this->aws_manager_receiver->pushThumbnailsToS3( $local_thumnails_dir, $this->s3path.self::THUMBNAILSDIR );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails_79X80';
			
			//448x306
			$local_thumnails_dir = rtrim($this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_448X306,"/");
			$this->aws_manager_receiver->pushThumbnailsToS3( $local_thumnails_dir, $this->s3path.self::THUMBNAILSDIR );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails_448x306';
	
			//384x216
			$local_thumnails_dir = rtrim($this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_384X216,"/");
			$this->aws_manager_receiver->pushThumbnailsToS3( $local_thumnails_dir, $this->s3path.self::THUMBNAILSDIR );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails_384X216';
			
			//98x78
			$local_thumnails_dir = rtrim($this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_98X78,"/");
			$this->aws_manager_receiver->pushThumbnailsToS3( $local_thumnails_dir, $this->s3path.self::THUMBNAILSDIR );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails_98X78';
		}
		
	} // end createThumNails()
	
	public function createFolders() {
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
				$this->homeDir . self::DESTDIR . self::WEBDIR, // data/temp_job_uuid_dir/media/web/
				$this->homeDir . self::DESTDIR . self::AUDIODIR, // data/temp_job_uuid_dir/media/webm/
				$this->homeDir . self::DESTDIR . self::_1080PDIR,  // data/temp_job_uuid_dir/media/p1080/
				$this->homeDir . self::DESTDIR . self::TSDIR,  // data/temp_job_uuid_dir/media/hls/
				$this->homeDir . self::DESTDIR . self::HLSDIR,  // data/temp_job_uuid_dir/media/hls/
		);
		
		$permissions = 0777;
		foreach ( $toCreate as $dir ) {
			// mkdir($dir, $permissions, TRUE);
			$save = umask ( 0 );
			if (mkdir ( $dir ))
				chmod ( $dir, $permissions );
			umask ( $save );
error_log("created dir ---> $dir".PHP_EOL);			
		}
	}
	
	public function transcode($type) {

		// FFMPEG transcode to mpeg (samples)
		// $command =array_merge(array( '-i',$this->destRandMediaName,'-vcodec', 'libx264', '-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),$ae,$customParams,array($this->homeDir.self::CONVDIR.self::WEBDIR.$this->original_file_name.'x264.mp4','2>&1'));
		// $cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_mp4_file ".'2>&1';
		
		$mpeg4ext = '.mp4';
		$tsext = '.ts';
		$aacext = '.m4a';
		if ($type == 'web') {
			/*
			 * See -> https://trac.ffmpeg.org/wiki/Encode/H.264
			 * 
			 */
			//$qv=' -c:v mpeg4 ';
			/*
			 * Test lossless with best compression
			 */
			//$qv=' -c:v libx264 -c:a libfdk_aac -preset slow -qp 0 -profile:v main -level 4.0 -movflags +faststart -pix_fmt yuv420p -b:a 128k ';
			$qv=' -c:v libx264 -c:a libfdk_aac -preset veryfast -profile:v main -level 4.0 -movflags +faststart -pix_fmt yuv420p -b:a 128k ';
			//$qv=' -c:v libx264 -threads 6 -c:a libfdk_aac -preset ultrafast -profile:v high -level 4.2 -movflags +faststart -pix_fmt yuv420p '; // -b:a 128k ';
			//$qv=' -c:v libx264 -c:a libfdk_aac -preset ultrafast -profile:v high -level 4.2 -movflags +faststart -pix_fmt yuv420p '; // -b:a 128k ';
			//$qv='';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->MediaFileName . $mpeg4ext;
			$transcoded_file_name = $this->MediaFileName . $mpeg4ext;
			//$ffmpeg_log_file = $this->homeDir . self::WEBHOME . "ffmpeg-".$this->media_id.date ( 'Y-m-d H:i:s').".log";
			//$ffmpeg_logger = " -loglevel info ";
			//$ffmpeg_logger = " -report ";
			$ffmpeg_logger = "";
				
			//$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
			$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".$ffmpeg_logger.'2>&1';
			//$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2> $ffmpeg_logger';
				
		} else if ($type == '1080p') {
			//$qv=' -c:v mpeg4 -q:v 1 ';
			$qv=' -c:v libx264 -c:a libfdk_aac -preset medium -profile:v main -level 4.0 -movflags +faststart -pix_fmt yuv420p -b:a 240k ';
			//$qv=' -c:v libx264 -threads 6 -c:a libfdk_aac -preset fast -profile:v high -level 4.2 -movflags +faststart -pix_fmt yuv420p '; //-b:a 240k ';
			//$qv=' -c:v libx264 -c:a libfdk_aac -preset fast -profile:v high -level 4.2 -movflags +faststart -pix_fmt yuv420p '; //-b:a 240k ';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->MediaFileName . $mpeg4ext; 
			$transcoded_file_name = $this->MediaFileName . $mpeg4ext;
			//$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
			$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".$ffmpeg_logger.'2>&1';
			
// 		} else if ($type == 'ts') {
// 			//$qv=' -c:v mpeg2video -q:v 3 -strict experimental -c:a aac ';
// 			//$qv=' -vcodec copy -acodec copy -f mpegts ';
// 			$qv=' -codec copy -bsf:v h264_mp4toannexb -f mpegts ';
// 			$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->MediaFileName . $mpeg4ext;
// 			$transcoded_file = $this->homeDir . self::CONVDIR . self::TSDIR . $this->MediaFileName . $tsext; 
// 			$transcoded_file_name = $this->MediaFileName . $tsext;
// 			$cmd = $this->ffmpegcmd ." -i $transcoded_mp4_file $qv $transcoded_file ".'2>&1';
//		} else if ($type == 'webm') {
//			$qv=' -c:v libvpx -c:a libvorbis  -b:v 2000k -q:a 3 ';
//			$transcoded_file = $this->homeDir . self::CONVDIR . self::FLVDIR . $this->MediaFileName . '.webm';
//			$transcoded_file_name = $this->MediaFileName . '.webm';
//			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
//		} else if ($type == 'flv') {
//			$qv='';
//			$transcoded_file = $this->homeDir . self::CONVDIR . self::FLVDIR . $this->MediaFileName . '.flv';
//			$transcoded_file_name = $this->MediaFileName . '.flv';
//			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
		} else if ($type == 'hls') {
			//Note: this section uses the transcoded 1080p file above 
			$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->MediaFileName . $mpeg4ext;
			$transcoded_file_name = $this->MediaFileName . $mpeg4ext;
			//$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::TSDIR . $this->MediaFileName . $tsext;
			//$transcoded_file_name = $this->MediaFileName . $tsext;
			//$transcoded_mp4_file = $this->destRandMediaName;
			$transcoded_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName . '.m3u8';
			$transcoded_hls_ts_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName;
			// Sample: http://sinclairmediatech.com/encoding-hls-with-ffmpeg/
/* 			$cmd = $this->ffmpegcmd .
				" -re -y -i ".$transcoded_mp4_file.
				" -map 0 ".
				" -f segment ".
				" -segment_list ".$transcoded_file.
				//" -segment_list_flags +live ".
				" -segment_time 5 ".
				" -segment_format mpegts ".
				" -segment_list_type m3u8 ".
				$transcoded_hls_ts_file."%05d.ts".
				' 2>&1';
 */			
			$cmd = 'nice ' . 
				$this->ffmpegcmd .
				" -re -y -i ".$transcoded_mp4_file.
				//" -threads 6 " . //testing threads 
				" -map 0 ".
				" -pix_fmt yuv420p ". 
				" -vcodec libx264 ". 
				" -acodec libfdk_aac ". 
				" -r 25 ". 
				//" -profile:v baseline ". 
				" -profile:v main -level 4.0 ". 
				//" -profile:v high -level 4.2 ". 
				" -b:v 1500k ". 
				" -maxrate 2000k ". 
				" -force_key_frames 50 ". 
				//" -s 640x360 ". 
				" -flags -global_header ". 
				" -f segment ". 
				" -segment_list_type m3u8 ".
				" -segment_list ".$transcoded_file.
				" -segment_time 10 ". 
				" -segment_format mpeg_ts ". 
				$transcoded_hls_ts_file."%05d.ts".
				//$ffmpeg_logger.'2>&1';
				' 2>&1';
		} else if ($type == 'audio') {
			/*
			 * TODO: add audio cmd
			 */
//error_log("Inside transcode type=audio ...".PHP_EOL);
			$qv=' -c:a libfdk_aac -movflags +faststart ';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::AUDIODIR . $this->MediaFileName . $aacext; 
			$transcoded_file_name = $this->MediaFileName . $aacext;
			$cmd = 'nice ' . $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
			
		} else
			throw new \Exception("MemreasTranscoder $type not found.");

//error_log("cmd2 ---> $cmd".PHP_EOL);				
		$this->pass = 0;
		$output_start_time = date ( "Y-m-d H:i:s" );
		try {
			$op = shell_exec ( $cmd );
			if (!file_exists($transcoded_file))
				throw new \Exception($op);
			else 
				$pass = 1;
		} catch ( \Exception $e ) {
			$this->pass = 0;
			error_log ( "transcoder $type failed - op -->" . $op . PHP_EOL );
			throw $e;
		}

		//Push to S3
		$s3file = $this->s3prefixpath.$type.'/'.$transcoded_file_name;
		if ($type == "hls") {
			$s3file = $this->s3prefixpath.$type.'/'.$this->MediaFileName.'.m3u8';
			$this->aws_manager_receiver->pushMediaToS3($transcoded_file, $s3file, "application/x-mpegurl");
			//Push all .ts files
			$pat = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->MediaFileName . "*.ts";
			$fsize = 0;
			foreach (glob($pat) as $filename) {
				$fsize += filesize($filename);
				$s3tsfile = $this->s3prefixpath.$type.'/'.basename($filename);
				$this->aws_manager_receiver->pushMediaToS3($filename, $s3tsfile, "video/mp2t");
			}
		} else if ($this->is_audio) {
			$this->aws_manager_receiver->pushMediaToS3($transcoded_file, $s3file, "audio/m4a", true);
			$fsize = filesize ( $transcoded_file );
		} else {
			$this->aws_manager_receiver->pushMediaToS3($transcoded_file, $s3file, "video/mp4", true);
			$fsize = filesize ( $transcoded_file );
		}

		//Log status
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_'.$type.'_upload_S3';
		$arr = array (
				"ffmpeg_cmd" => $cmd,
				"ffmpeg_cmd_output" => $op,
				"output_size" => $fsize,
				"pass_fail" => $this->pass,
				"error_message" => "",
				"output_start_time" => $output_start_time,
				"output_end_time" => date ( "Y-m-d H:i:s" ) 
		);
		$this->memreas_media_metadata ['S3_files'] [$type] = $s3file; 
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_'.$type.'_completed';

		return $arr;
	} // End transcode

	private function rmWorkDir($dir) {
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
	}
	
	public function resizeImage($dirPath, $file, $thumbnail_name, $height, $width) {
		$layer = ImageWorkshop::initFromPath ( $file );
		// $layer->resizeInPixel($height, $width, true, 0, 0, 'MM'); //Maintains image
		$layer->resizeInPixel ( $height, $width );
		$createFolders = true;
		$backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
		$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
		$layer->save ( $dirPath, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality );
		$file = $dirPath . $thumbnail_name;
		
		return $file;
	}
		
	public function persistMedia($media, $media_data_array) {
		/*
		 * Store media
		 */
		$media->exchangeArray ( $media_data_array );
		$media_id = $this->memreas_transcoder_tables->getMediaTable ()->saveMedia ( $media );
	}

	public function persistTranscodeTransaction($transcode_transaction = null, $transcode_data_array = null) {

		if (is_null($transcode_transaction)) {		
			$transcode_transaction = new TranscodeTransaction();
			$transcode_transaction->exchangeArray ( array (
					'user_id' => $this->user_id,
					'media_id' => $this->media_id,
					'file_name' => $this->original_file_name,
					'media_type' => $this->MediaFileType,
					'media_extension' => $this->MediaExt,
					'media_duration' => $this->duration,
					'media_size' => $this->filesize,
					'pass_fail' => $this->pass,
					'metadata' => $this->json_metadata, //set later
					'transcode_start_time' => $this->transcode_start_time
			) );
			$transcode_transaction_id = $this->memreas_transcoder_tables->getTranscodeTransactionTable()->saveTranscodeTransaction ( $transcode_transaction );
//error_log("inserted transcode_transaction_id ------> ".$transcode_transaction_id.PHP_EOL);			
			return $transcode_transaction_id;
		} else { // Update
			$transcode_transaction->exchangeArray ( $transcode_data_array );
			$transcode_transaction_id = $this->memreas_transcoder_tables->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
//error_log("updated transcode_transaction_id ------> ".$transcode_transaction_id.PHP_EOL);			
			return $transcode_transaction_id;
		}
	}
	
} //End class


