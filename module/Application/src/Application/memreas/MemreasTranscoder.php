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
	protected $content_type;
	protected $s3path;
	protected $s3file_name;
	protected $is_video;
	protected $is_audio;
	protected $session;
	protected $aws_manager_receiver;
	protected $memreas_media_metadata;
	protected $ffmpegcmd;
	protected $ffprobecmd;
	
	protected $VideoFileType;
	protected $VideoExt;
	protected $duration=0;
	protected $filesize=0;
	protected $pass=0;
	protected $json_metadata;
	protected $transcode_job_duration;
	protected $transcode_start_time;
	protected $transcode_end_time;
	
	
	// Directory related variables - create a unique directory by user_id
	protected $temp_job_uuid_dir;
	protected $homeDir;
	protected $destRandMediaName;
	protected $original_file_name;
	protected $VideoFileName;
	const WEBHOME 		= '/var/app/current/data/';
	const DESTDIR 		= 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const CONVDIR 		= 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const _1080PDIR 	= '1080p/'; // Your 1080p Dir, end with slash (/)
	const THUMBNAILSDIR = 'thumbnails/'; // Your thumbnails Dir, end with slash (/)
	const AUDIODIR 		= 'audio/'; // Your audio Dir, end with slash (/)
	const HLSDIR 		= 'hls/'; // Your hls Dir, end with slash (/)
	const TSDIR 		= 'ts/'; // Your hls Dir, end with slash (/)
	const WEBDIR		= 'web/'; // Your web Dir, end with slash (/)
	const WEBMDIR 		= 'webm/'; // Your webm Dir, end with slash (/)
	const FLVDIR 		= 'flv/'; // Your flv Dir, end with slash (/)
	const _79X80 		= '79x80/'; // Your 79x80 Dir, end with slash (/)
	const _448X306 		= '448x306/'; // Your 448x306 Dir, end with slash (/)
	const _384X216 		= '384x216/'; // Your 384x216 Dir, end with slash (/)
	const _98X78 		= '98x78/'; // Your 98x78 Dir, end with slash (/)
	
	/*
	 * Thumbnail settings $tnWidth = 448; $tnHeight = 306; $tnfreqency = 60; // in seconds - 60 means every 60 seconds (minute) $errstr = '';
	 */
	public function __construct($aws_manager_receiver) {
		$this->aws_manager_receiver = $aws_manager_receiver;
		$this->temp_job_uuid_dir = MUUID::fetchUUID ();
		$this->homeDir = self::WEBHOME . $this->temp_job_uuid_dir . '/'; // Home Directory ends with / (slash) :::: Your AMAZON home
	}
	public function exec($message_data, $memreas_transcoder_tables, $service_locator, $isUpload = false) {
		error_log ( "_REQUEST----> " . print_r ( $_REQUEST, true ) . PHP_EOL );
		error_log ( "message_data----> " . print_r ( $message_data, true ) . PHP_EOL );
		
		try {
			// date_default_timezone_set('UTC');
			$starttime = date ( 'Y-m-d H:i:s' );
			// $message_data entries
			$this->user_id = $message_data ['user_id'];
			$this->media_id = $message_data ['media_id'];
			$this->content_type = $message_data ['content_type'];
			$this->s3path = $message_data ['s3path'];
			$this->s3file_name = $message_data ['s3file_name'];
			$this->is_video = $message_data ['is_video'];
			$this->is_audio = $message_data ['is_audio'];
			$this->json_metadata = json_encode($message_data);
			
			// Fetch the media entry here:
			$memreas_media = $memreas_transcoder_tables->getMediaTable ()->getMedia ( $this->media_id );
			$this->memreas_media_metadata = json_decode ( $memreas_media->metadata, true );
			// date_default_timezone_set('UTC');
			$starttime = date ( 'Y-m-d H:i:s' );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@'.$starttime;
			
//Debugging
error_log ( "input meta------>" . $this->memreas_media_metadata->metadata . PHP_EOL );
			
			if (isset ( $message_data )) {
				if (getcwd () == '/var/app/current') {
					error_log ( "found /var/app/current" . PHP_EOL );
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG; // :::: AWS ffmpeg installation
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE; // :::: AWS ffprobe installation
				} else {
					error_log ( "!found /var/app/current" . PHP_EOL );
					$this->ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG_LOCAL; // :::: Your ffmpeg installation
					$this->ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE_LOCAL; // :::: Your ffmpeg installation
				}
				
				////////////////////////
				// create work folders
				$this->createFolders ();
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_folders_created';
				
				if (! $isUpload) {
					$this->user_id = $message_data ['user_id'];
					// Fetch the file to transcode:
					$s3file = $message_data ['s3path'] . $message_data ['s3file_name'];
					$tmp_file = $this->homeDir . self::DESTDIR . $message_data ['s3file_name'];
					$response = $this->aws_manager_receiver->pullMediaFromS3 ( $s3file, $tmp_file );
					if ($response) {
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_S3_file_saved';
					} else {
						// Something went wrong throw exception
						$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_error';
						$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error: S3 file fetch and save failed!';
						throw new \Exception ( "Transcoder: S3 file fetch and save failed!" );
					}

					$this->VideoFileName = str_replace ( ' ', '-', $message_data ['s3file_name'] );
					$this->VideoFileType = $message_data ['content_type'];
					
					// Get file extension from Video name, this will be re-added after random name
					$this->VideoExt = substr ( $this->VideoFileName, strrpos ( $this->VideoFileName, '.' ) );
					$this->VideoExt = str_replace ( '.', '', $this->VideoExt );
					
					// remove extension from filename
					$this->VideoFileName = preg_replace ( "/\\.[^.\\s]{3,4}$/", "", $this->VideoFileName );
					
					// Construct a new video name (with random number added) for our new video.
					$this->original_file_name = $this->VideoFileName . "." . $this->VideoExt;
					$this->filesize = filesize ( $this->destRandMediaName );
					// set the Destination Video
					
					$this->destRandMediaName = $this->homeDir . self::DESTDIR . $this->original_file_name; // Name for Big Video
						                                                                 // $this->destRandMediaName = $tmp_file;
				} else if (isset ( $_FILES ['VideoFile'] ) && is_uploaded_file ( $_FILES ['VideoFile'] ['tmp_name'] [0] )) {
/*					
					error_log ( "Inside if videofile and is uploaded...." . PHP_EOL );
					// Elements (values) of $_FILES['VideoFile'] array
					// let's access these values by using their index position
					$this->VideoFileName = str_replace ( ' ', '-', strtolower ( $_FILES ['VideoFile'] ['name'] [0] ) );
					$TempSrc = $_FILES ['VideoFile'] ['tmp_name'] [0]; // Tmp name of video file stored in PHP tmp folder
					$this->VideoFileType = $_FILES ['VideoFile'] ['type'] [0]; // Obtain file type, returns "video/png", video/jpeg, text/plain etc.
					                                                  // Get file extension from Video name, this will be re-added after random name
					$this->VideoExt = substr ( $this->VideoFileName, strrpos ( $this->VideoFileName, '.' ) );
					$this->VideoExt = str_replace ( '.', '', $this->VideoExt );
					
					// remove extension from filename
					$this->VideoFileName = preg_replace ( "/\\.[^.\\s]{3,4}$/", "", $this->VideoFileName );
					
					// Construct a new video name (with random number added) for our new video.
					$this->original_file_name = $this->VideoFileName . "." . $this->VideoExt;
					// set the Destination Video
					
					$this->destRandMediaName = $this->homeDir . self::DESTDIR . $this->original_file_name;
*/
error_log("Inside Memreas Transcode reach Upload section - error - this shouldn't occur".PHP_EOL);										 
				} else if (! isset ( $_FILES ['VideoFile'] ) || ! is_uploaded_file ( $_FILES ['VideoFile'] ['tmp_name'] [0] )) {
					throw new \Exception ( 'Something went wrong with Upload!' ); // output error when above checks fail.
				}
				
				// Let's use $this->VideoFileType variable to check wheather uploaded file is supported.
				// We use PHP SWITCH statement to check valid video format, PHP SWITCH is similar to IF/ELSE statements
				// suitable if we want to compare the a variable with many different values
				switch (strtolower ( $this->VideoFileType )) {
					case 'video/mp4' :
						break;
					case 'video/mov' :
						$this->VideoFileType = 'video/quicktime';
						break;
					case 'video/quicktime' :
						break;
					case 'video/x-msvideo' :
						break;
					case 'video/x-ms-wmv' :
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
							$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error:.invalid_file_type:' . $this->VideoFileType;
							throw new \Exception ( 'Unsupported File!' ); // output error and exit
						}
				}
				
				// Save file in upload destination
				if ($isUpload) {
error_log("Inside Memreas Transcode reach Upload section - error - this shouldn't occur".PHP_EOL);										 
/*
					move_uploaded_file ( $TempSrc, $this->destRandMediaName );
					// Put to S3 here...
					$s3file = $this->user_id . '/media/' . $this->original_file_name;
					$this->aws_manager_receiver->pushMediaToS3($this->destRandMediaName, $s3file, "video/mpeg");
						
					// Store the metadata
					$metadata ['S3_files'] ['path'] = $media_s3_path;
					$metadata ['S3_files'] ['full'] = $media_s3_path;
					// Insert a media table entry here
					$now = date ( 'Y-m-d H:i:s' );
					$memreas_media = new Media ();
					$memreas_media->exchangeArray ( array (
							'user_id' => $this->user_id,
							'is_profile_pic' => 0,
							'sync_status' => 0,
							'metadata' => json_encode ( $metadata ),
							'report_flag' => 0,
							'create_date' => $now,
							'update_date' => $now 
					) );
					$this->media_id = $memreas_transcoder_tables->getMediaTable ()->saveMedia ( $memreas_media );
*/
				} else {
error_log("Do nothing we have the media_id ----> $this->media_id" . PHP_EOL);
				}
				
				if ($this->is_video  || $this->is_audio) {
					//Calc media vars
					$this->duration = str_replace ( ",", "", shell_exec ( "$this->ffmpegcmd -i $this->destRandMediaName 2>&1 | grep 'Duration' | cut -d ' ' -f 4" ) );
					$timed = explode ( ":", $this->duration );
					$this->duration = (( float ) $timed [0]) * 3600 + (( float ) $timed [1]) * 60 + ( float ) $timed [2];
					$this->filesize = filesize ( $this->destRandMediaName );
					$this->transcode_start_time = date ( "Y-m-d H:i:s" );
				}
				
// error_log ( "media_type --> $this->VideoFileType");
// error_log ( "media_extension --> $this->VideoExt");
// error_log ( "file_name --> $this->original_file_name");
// error_log ( "media_duration --> $this->duration");
// error_log ( "media_size --> $this->filesize");
// error_log ( "pass_fail --> $this->pass");
// error_log ( "metadata --> $this->json_metadata");
// error_log ( "transcode_job_duration --> $this->transcode_job_duration");
// error_log ( "transcode_start_time --> $this->transcode_start_time");
// error_log ( "insert data: user_id ---> $this->user_id".PHP_EOL);
// error_log ( "insert data: media_id ---> $this->media_id".PHP_EOL);
// error_log ( "insert data: media_type ---> $this->VideoFileType".PHP_EOL);
// error_log ( "insert data: media_extension ---> $this->VideoExt".PHP_EOL);
// error_log ( "insert data: file_name ---> $this->original_file_name".PHP_EOL);
// error_log ( "insert data: media_duration ---> $this->duration".PHP_EOL);
// error_log ( "insert data: media_size ---> $this->filesize".PHP_EOL);
// error_log ( "insert data: pass_fail ---> $this->pass".PHP_EOL);
// error_log ( "insert data: metadata ---> $this->json_metadata".PHP_EOL);

				/*
				 * Insert transcode_transaction so we have a record
				 */
				$now = date ( 'Y-m-d H:i:s' );
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start@'.$now;
				
				$transcode_transaction = new TranscodeTransaction();
				$transcode_transaction->exchangeArray ( array (
						'user_id' => $this->user_id,
						'media_id' => $this->media_id,
						'file_name' => $this->original_file_name,
						'media_type' => $this->VideoFileType,
						'media_extension' => $this->VideoExt,
						'media_duration' => $this->duration,
						'media_size' => $this->filesize,
						'pass_fail' => $this->pass,
						'metadata' => $this->json_metadata, //set later
						'transcode_start_time' => $this->transcode_start_time
				) );
				$transcode_transaction_id = $memreas_transcoder_tables->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
//error_log ( "Inserted transcode_transaction --> $transcode_transaction_id" . PHP_EOL );
				
				if ($this->is_video) {
					// Create Thumbnails
					$this->createThumbNails ();
error_log("Finished thumbnails..." . PHP_EOL);
					// Create web quality mpeg
					$transcode_job_meta = array ();
					$transcode_job_meta ['web'] = $this->transcode ( 'web' );
error_log("Finished web..." . PHP_EOL);
					// Create high quality mpeg
					$transcode_job_meta ['1080p'] = $this->transcode ( '1080p' );
error_log("Finished 1080p..." . PHP_EOL);
					// Create webm file
//					$transcode_job_meta ['webm'] = $this->transcode ( 'webm' );
//error_log("Finished webm..." . PHP_EOL);
					// Create flash file
//					$transcode_job_meta ['flv'] = $this->transcode ( 'flv' );
//error_log("Finished flv..." . PHP_EOL);
// 					// Create ts
// 					$transcode_job_meta ['ts'] = $this->transcode ( 'ts' );
// error_log("Finished ts..." . PHP_EOL);
					// Create hls
					$transcode_job_meta ['hls'] = $this->transcode ( 'hls' );
error_log("Finished hls..." . PHP_EOL);
				} // End if ($is_video)
				else {  
					//Audio section
					// Create web quality mp3
					$transcode_job_meta = array ();
					$transcode_job_meta ['audio'] = $this->transcode ( 'audio' );
error_log("Finished audio..." . PHP_EOL);
					// Update the metadata here for the transcoded files
				}
				// Update the metadata here for the transcoded files
				$now = date ( 'Y-m-d H:i:s' );
				$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_end@'.$now;
				$this->json_metadata = json_encode ( $transcode_job_meta );
				
//Debugging
// error_log("Insert transcode_transaction values...".PHP_EOL);
// error_log("user_id --> ".$this->user_id.PHP_EOL);
// error_log("media_type --> ".$this->VideoFileType.PHP_EOL);
// error_log("media_extension --> ".$this->VideoExt.PHP_EOL);
// error_log("file_name --> ".$this->original_file_name.PHP_EOL);
// error_log("media_duration --> ".$this->duration.PHP_EOL);
// error_log("media_size --> ".$this->filesize.PHP_EOL);
// error_log("pass_fail --> ".$this->pass.PHP_EOL);
// error_log("metadata --> ".$this->json_metadata.PHP_EOL);
// error_log("transcode_job_duration --> ".$this->transcode_job_duration.PHP_EOL);
// error_log("transcode_start_time --> ".$this->transcode_start_time.PHP_EOL);
// error_log("transcode_end_time --> ".$this->transcode_end_time.PHP_EOL);				
				
				///////////////////////////////
				// Update transcode_transaction
				$this->pass = 1;
				$this->transcode_end_time = date ( 'Y-m-d H:i:s' );
				$this->transcode_job_duration = strtotime ( $this->transcode_end_time ) - strtotime ( $this->transcode_start_time );
				$transcode_transaction->exchangeArray ( array (
						'pass_fail' => $this->pass,
						'metadata' => $this->json_metadata,
						'transcode_job_duration' => $this->transcode_job_duration,
						'transcode_end_time' => $this->transcode_end_time 
				) );
				$transcode_transaction_id = $memreas_transcoder_tables->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
error_log ( "Updated transcode_transaction...." . PHP_EOL );
				
				///////////////////////////////
				// Update the media table entry here
				$now = date ( 'Y-m-d H:i:s' );
				$this->json_metadata = json_encode ( $this->memreas_media_metadata );
				error_log ( "**************************************************************************" . PHP_EOL );
				error_log ( "memreas media json metadata before ----> " . $memreas_media->metadata . PHP_EOL );
				error_log ( "**************************************************************************" . PHP_EOL );
				$memreas_media->exchangeArray ( array (
						'metadata' => $this->json_metadata,
						'update_date' => $now 
				) );
				$this->media_id = $memreas_transcoder_tables->getMediaTable ()->saveMedia ( $memreas_media );
				error_log ( "memreas media json metadata after ----> " . $this->json_metadata . PHP_EOL );
				error_log ( "**************************************************************************" . PHP_EOL );
				
				error_log ( "Just updated $this->media_id" . PHP_EOL );
			} // End if(isset($_POST))
		} catch ( \Exception $e ) {
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
		// Always delete the temp dir...
		// Delete the temp dir if we got this far...
		try {
//error_log("Recursive delete $this->homeDir".PHP_EOL);			
			$result = $this->rmWorkDir ( $this->homeDir );
		} catch ( \Exception $e ) {
			$this->pass = 0;
			error_log ( "error string ---> " . $e->getMessage () . PHP_EOL );
		}
	}
	public function createThumbnails() {
		// //////////////////////
		// Thumbnails section
		// //////////////////////
		$tnWidth = 448; 
		$tnHeight = 306; 
		$tnfreqency = 10;  //every 10 seconds take a thumbshot
		
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
				$this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . $imagename,
				'2>&1' 
		);
		
		$cmd = join ( " ", $command );
		$cmd = $this->ffmpegcmd . " " . $cmd;
		// echo "$cmd<br>";
		$op = shell_exec ( $cmd );
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_built_thumbnails';
		
		foreach ( glob ( $this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . 'thumbnail_' . $this->original_file_name . '_media-*.png' ) as $filename ) {
error_log("filename ----> $filename".PHP_EOL);
				
			// error_log("WebHome DestinationDirectory thumbnails basename(filename) ----> " . self::WEBHOME.self::DESTDIR.self::THUMBNAILSDIR.basename($filename) . PHP_EOL);
			// error_log("basename(filename) ----> " . basename($filename) . PHP_EOL);
			// error_log("filename ----> " . $filename . PHP_EOL);
			$tns [] = self::WEBHOME . self::DESTDIR . self::THUMBNAILSDIR . basename ( $filename );
			
			// ////////////////////////////////////////////////
			// Resize thumbnails as needed and save locally
			$tns_sized = array (
					"full" => $filename,
					"79x80" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_79X80, $filename, basename ( $filename ), 79, 80 ),
					"448x306" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_448X306, $filename, basename ( $filename ), 448, 306 ),
					"384x216" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_384X216, $filename, basename ( $filename ), 384, 216 ),
					"98x78" => $this->resizeImage ( $this->homeDir . self::DESTDIR . self::THUMBNAILSDIR . self::_98X78, $filename, basename ( $filename ), 98, 78 ) 
			);
			$s3paths = array (
					"thumbnails" => array (
							// web
							"full" => $this->user_id . '/media/thumbnails/',
							"79x80" => $this->user_id . '/media/thumbnails/79x80/',
							"448x306" => $this->user_id . '/media/thumbnails/448x306/',
							"384x216" => $this->user_id . '/media/thumbnails/384x216/',
							"98x78" => $this->user_id . '/media/thumbnails/98x78/' 
					) 
			);
			// Put original thumbnail to S3 here...
			foreach ( $s3paths as $fmt ) {
//error_log("fmt ----> $fmt".PHP_EOL);					
				$i=0;
				foreach ( $tns_sized as $key => $file ) {
//error_log("key ----> $key".PHP_EOL);					
//error_log("file ----> $file".PHP_EOL);					
					//Push to S3
					$s3thumbnail_file = $fmt [$key] . basename ( $filename );
					$this->aws_manager_receiver->pushMediaToS3($file, $s3thumbnail_file, "image/png");					
					$this->memreas_media_metadata ['S3_files'] ['thumbnails'] [$key] [$i] = $fmt [$key] . basename ( $filename );
error_log("thumb in meta ---> ".$this->memreas_media_metadata ['S3_files'] ['thumbnails'] [$key] [$i].PHP_EOL);
					$i = $i + 1;
//error_log("Uploadeded thumbnail ---> ".$fmt [$key] . basename ( $filename ).PHP_EOL);					
				}
			}
		} // End for each thumbnail
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_stored_thumbnails';
	} // end createThumNails()
	
	public function createFolders() {
		// Make directories here - create a unique directory by user_id
		$toCreate = array (
				$this->homeDir, // data/temp_uuid_dir/
				$this->homeDir . self::DESTDIR, // data/temp_job_uuid_dir/media/
				$this->homeDir . self::DESTDIR . self::THUMBNAILSDIR, // data/temp_job_uuid_dir/media/thumbnails/
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
//error_log("created dir ---> $dir".PHP_EOL);			
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
			//$qv=' -c:v mpeg4 ';
			$qv=' -c:v libx264 -c:a libfdk_aac -preset veryfast -profile:v main -level 4.0 -movflags +faststart -pix_fmt yuv420p -b:a 128k ';
			//$qv='';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::WEBDIR . $this->VideoFileName . $mpeg4ext;
			$transcoded_file_name = $this->VideoFileName . $mpeg4ext;
			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
		} else if ($type == '1080p') {
			//$qv=' -c:v mpeg4 -q:v 1 ';
			$qv=' -c:v libx264 -c:a libfdk_aac -preset medium -profile:v main -level 4.0 -movflags +faststart -pix_fmt yuv420p -b:a 240k ';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->VideoFileName . $mpeg4ext; 
			$transcoded_file_name = $this->VideoFileName . $mpeg4ext;
			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
// 		} else if ($type == 'ts') {
// 			//$qv=' -c:v mpeg2video -q:v 3 -strict experimental -c:a aac ';
// 			//$qv=' -vcodec copy -acodec copy -f mpegts ';
// 			$qv=' -codec copy -bsf:v h264_mp4toannexb -f mpegts ';
// 			$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->VideoFileName . $mpeg4ext;
// 			$transcoded_file = $this->homeDir . self::CONVDIR . self::TSDIR . $this->VideoFileName . $tsext; 
// 			$transcoded_file_name = $this->VideoFileName . $tsext;
// 			$cmd = $this->ffmpegcmd ." -i $transcoded_mp4_file $qv $transcoded_file ".'2>&1';
//		} else if ($type == 'webm') {
//			$qv=' -c:v libvpx -c:a libvorbis  -b:v 2000k -q:a 3 ';
//			$transcoded_file = $this->homeDir . self::CONVDIR . self::FLVDIR . $this->VideoFileName . '.webm';
//			$transcoded_file_name = $this->VideoFileName . '.webm';
//			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
//		} else if ($type == 'flv') {
//			$qv='';
//			$transcoded_file = $this->homeDir . self::CONVDIR . self::FLVDIR . $this->VideoFileName . '.flv';
//			$transcoded_file_name = $this->VideoFileName . '.flv';
//			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
		} else if ($type == 'hls') {
			//Note: this section uses the transcoded 1080p file above 
			$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $this->VideoFileName . $mpeg4ext;
			$transcoded_file_name = $this->VideoFileName . $mpeg4ext;
			//$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::TSDIR . $this->VideoFileName . $tsext;
			//$transcoded_file_name = $this->VideoFileName . $tsext;
			//$transcoded_mp4_file = $this->destRandMediaName;
			$transcoded_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->VideoFileName . '.m3u8';
			$transcoded_hls_ts_file = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->VideoFileName;
			// Sample: http://sinclairmediatech.com/encoding-hls-with-ffmpeg/
// 			$cmd = $this->ffmpegcmd .
// 				" -re -y -i ".$transcoded_mp4_file.
// 				" -map 0 ".
// 				" -f segment ".
// 				" -segment_list ".$transcoded_file.
// 				//" -segment_list_flags +live ".
// 				" -segment_time 5 ".
// 				" -segment_format mpegts ".
// 				" -segment_list_type m3u8 ".
// 				$transcoded_hls_ts_file."%05d.ts".
// 				' 2>&1';
			
			$cmd = $this->ffmpegcmd .
				" -re -y -i ".$transcoded_mp4_file.
				" -map 0 ".
				" -pix_fmt yuv420p ". 
				" -vcodec libx264 ". 
				" -acodec libfdk_aac ". 
				" -r 25 ". 
				//" -profile:v baseline ". 
				" -profile:v main -level 4.0 ". 
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
				' 2>&1';
		} else if ($type == 'audio') {
			/*
			 * TODO: add audio cmd
			 */
error_log("Inside transcode type=audio ...".PHP_EOL);
			$qv=' -c:a libfdk_aac -movflags +faststart ';
			$transcoded_file = $this->homeDir . self::CONVDIR . self::AUDIODIR . $this->VideoFileName . $aacext; 
			$transcoded_file_name = $this->VideoFileName . $aacext;
			$cmd = $this->ffmpegcmd ." -i $this->destRandMediaName $qv $transcoded_file ".'2>&1';
			
		} else
			throw new \Exception("MemreasTranscoder $type not found.");

error_log("cmd2 ---> $cmd".PHP_EOL);				
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
		$s3file = $this->user_id.'/media/'.$type.'/'.$transcoded_file_name;
		if ($type == "hls") {
			$s3file = $this->user_id.'/media/'.$type.'/'.$this->VideoFileName.'.m3u8';
			$this->aws_manager_receiver->pushMediaToS3($transcoded_file, $s3file, "application/x-mpegurl");
error_log("pushed to S3 --> $s3file ---> $s3file".PHP_EOL);
			//Push all .ts files
			$pat = $this->homeDir . self::CONVDIR . self::HLSDIR . $this->VideoFileName . "*.ts";
			$fsize = 0;
//error_log("pat ---> $pat".PHP_EOL);
			foreach (glob($pat) as $filename) {
				$fsize += filesize($filename);
				$s3tsfile = $this->user_id.'/media/'.$type.'/'.basename($filename);
//error_log("filename ---> $filename".PHP_EOL);
error_log("pushed to S3 --> $s3tsfile ---> $s3tsfile".PHP_EOL);
				$this->aws_manager_receiver->pushMediaToS3($filename, $s3tsfile, "video/mp2t");
			}
		} else if ($this->is_audio) {
error_log("pushed to S3 --> $s3file ---> $s3file".PHP_EOL);
			$this->aws_manager_receiver->pushMediaToS3($transcoded_file, $s3file, "audio/m4a", true);
			$fsize = filesize ( $transcoded_file );
		} else {
error_log("pushed to S3 --> $s3file ---> $s3file".PHP_EOL);
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
		$this->memreas_media_metadata ['S3_files'] [$type] = $s3file; //$this->user_id . '/media/'.$type.'/' . $this->original_file_name . '.mp4';
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_'.$type.'_completed';

		return $arr;
	} // End transcode

	private function rmWorkDir($dir) {
		return; //DEBUGGING
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
} //End class


