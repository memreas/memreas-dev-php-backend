<?php

namespace Application\memreas;

use Zend\Session\Container;
use PHPImageWorkshop\ImageWorkshop;

// memreas custom
use Application\memreas\MemreasAWSTranscoder;
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
	protected $session;
	protected $aws_manager_receiver;
	protected $memreas_media_metadata;
	protected $ffmpegcmd;
	protected $ffprobecmd;
	
	
	// Directory related variables - create a unique directory by user_id
	protected $temp_job_uuid_dir;
	protected $homeDir;
	const WEBHOME = '/var/app/current/data/';
	const DESTDIR = 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const CONVDIR = 'media/'; // Upload Directory ends with / (slash):::: media/ in JSON
	const _1080PDIR = '1080p/'; // Your 1080p Dir, end with slash (/)
	const THUMBNAILSDIR = 'thumbnails/'; // Your thumbnails Dir, end with slash (/)
	const AUDIODIR = 'audio/'; // Your audio Dir, end with slash (/)
	const HLSDIR = 'hls/'; // Your hls Dir, end with slash (/)
	const WEBDIR = 'web/'; // Your web Dir, end with slash (/)
	const WEBMDIR = 'webm/'; // Your webm Dir, end with slash (/)
	const _79X80 = '79x80/'; // Your 79x80 Dir, end with slash (/)
	const _448x306 = '448x306/'; // Your 448x306 Dir, end with slash (/)
	const _384X216 = '384x216/'; // Your 384x216 Dir, end with slash (/)
	const _98X78 = '98x78/'; // Your 98x78 Dir, end with slash (/)
	
	/*
	 * //Thumbnail settings $tnWidth 				= 448; $tnHeight 				= 306; $tnfreqency 			= 60; // in seconds - 60 means every 60 seconds (minute) $errstr = '';
	 */
	public function __construct($aws_manager_receiver) {
		$this->aws_manager_receiver = $aws_manager_receiver;
		$this->temp_job_uuid_dir = MUUID::fetchUUID ();
		$this->homeDirectory = self::WEBHOME . $temp_job_uuid_dir . '/'; // Home Directory ends with / (slash) :::: Your AMAZON home
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
		
		// error_log("Inside fetchResizeUpload - resized and saved local file is now --> " . $file);
		
		return $file;
	}
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
	public function exec($message_data, $memreas_transcoder_tables, $service_locator, $isUpload = false) {
		error_log ( "_REQUEST----> " . print_r ( $_REQUEST, true ) . PHP_EOL );
		error_log ( "message_data----> " . print_r ( $message_data, true ) . PHP_EOL );
		
		try {
			// $message_data entries
			$user_id = $message_data ['user_id'];
			$media_id = $message_data ['media_id'];
			$content_type = $message_data ['content_type'];
			$s3path = $message_data ['s3path'];
			$s3file_name = $message_data ['s3file_name'];
			$isVideo = $message_data ['isVideo'];
			
			// Fetch the media entry here:
			$memreas_media = $memreas_transcoder_tables->getMediaTable ()->getMedia ( $media_id );
			$this->memreas_media_metadata = json_decode ( $memreas_media->metadata, true );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_start';
			
			// Debugging
			error_log ( "********************************************" . PHP_EOL );
			error_log ( "meta------>" . $memreas_media->metadata . PHP_EOL );
			
			// date_default_timezone_set('UTC');
			$starttime = date ( 'Y-m-d H:i:s' );
			$message_data;
			// $metadata = array();
			if (isset ( $message_data )) {
				// HomeDirectory.self::DESTDIR.$NewVideoName
				// AWS Settings
				
				$mysqli = NULL;
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
					// Fetch the json from the post
					if (isset ( $_POST ['json'] )) {
						$message_data = json_decode ( $_POST ['json'], true );
					}
					
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
					/*
					 * $response = $this->memreas_aws_transcoder->s3->getObject(array( 'Bucket' => MemreasConstants::S3BUCKET, 'Key'	 =>	$message_data['s3path'].$message_data['s3file_name'], 'SaveAs' =>	$tmp_file, ));
					 */
					$VideoFileName = str_replace ( ' ', '-', $message_data ['s3file_name'] );
					$VideoFileType = $message_data ['content_type'];
					// Get file extension from Video name, this will be re-added after random name
					$VideoExt = substr ( $VideoFileName, strrpos ( $VideoFileName, '.' ) );
					$VideoExt = str_replace ( '.', '', $VideoExt );
					
					// remove extension from filename
					$VideoFileName = preg_replace ( "/\\.[^.\\s]{3,4}$/", "", $VideoFileName );
					
					// Construct a new video name (with random number added) for our new video.
					$original_file_name = $VideoFileName . "." . $VideoExt;
					$NewVideoName = $original_file_name;
					$filesize = filesize ( $DestRandVideoName );
					// set the Destination Video
					
					$DestRandVideoName = $this->homeDir . self::DESTDIR . $NewVideoName; // Name for Big Video
						                                                                 // $DestRandVideoName = $tmp_file;
				} else if (isset ( $_FILES ['VideoFile'] ) && is_uploaded_file ( $_FILES ['VideoFile'] ['tmp_name'] [0] )) {
					
					error_log ( "Inside if videofile and is uploaded...." . PHP_EOL );
					// Elements (values) of $_FILES['VideoFile'] array
					// let's access these values by using their index position
					$VideoFileName = str_replace ( ' ', '-', strtolower ( $_FILES ['VideoFile'] ['name'] [0] ) );
					$TempSrc = $_FILES ['VideoFile'] ['tmp_name'] [0]; // Tmp name of video file stored in PHP tmp folder
					$VideoFileType = $_FILES ['VideoFile'] ['type'] [0]; // Obtain file type, returns "video/png", video/jpeg, text/plain etc.
					                                                  // Get file extension from Video name, this will be re-added after random name
					$VideoExt = substr ( $VideoFileName, strrpos ( $VideoFileName, '.' ) );
					$VideoExt = str_replace ( '.', '', $VideoExt );
					
					// remove extension from filename
					$VideoFileName = preg_replace ( "/\\.[^.\\s]{3,4}$/", "", $VideoFileName );
					
					// Construct a new video name (with random number added) for our new video.
					$original_file_name = $VideoFileName . "." . $VideoExt;
					$NewVideoName = $original_file_name;
					// set the Destination Video
					
					$DestRandVideoName = $this->homeDir . self::DESTDIR . $NewVideoName; // Name for Big Video
					error_log ( "Leaving ... Inside if videofile and is uploaded...." . PHP_EOL );
				} else if (! isset ( $_FILES ['VideoFile'] ) || ! is_uploaded_file ( $_FILES ['VideoFile'] ['tmp_name'] [0] )) {
					error_log ( 'Something went wrong with Upload!' );
					throw new \Exception ( 'Something went wrong with Upload!' ); // output error when above checks fail.
				}
				
				// Let's use $VideoFileType variable to check wheather uploaded file is supported.
				// We use PHP SWITCH statement to check valid video format, PHP SWITCH is similar to IF/ELSE statements
				// suitable if we want to compare the a variable with many different values
				switch (strtolower ( $VideoFileType )) {
					case 'video/mp4' :
						break;
					case 'video/mov' :
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
					case 'video/webm' :
						break;
					case 'video/mp1s' :
						break;
					case 'video/mp2p' :
						break;
					case 'audio/caf' :
						break;
					default :
						{
							// Set status
							$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_error';
							$this->memreas_media_metadata ['S3_files'] ['error_message'] = 'transcode_error:.invalid_file_type:' . $VideoFileType;
							throw new \Exception ( 'Unsupported File!' ); // output error and exit
						}
				}
				
				// Save file in upload destination
				if ($isUpload) {
					move_uploaded_file ( $TempSrc, $DestRandVideoName );
					// Put to S3 here...
					$message_data = array (
							"s3file_name" => $original_file_name,
							"file" => $DestRandVideoName,
							"user_id" => $this->user_id,
							"media_id" => "placeholder",
							"content_type" => "video/mpeg", // must be mpeg for lgpl ffmpeg conversions
							"s3path" => $this->user_id . '/media/' 
					);
					$media_s3_path = $this->s3videoUpload ( $message_data );
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
					$media_id = $memreas_transcoder_tables->getMediaTable ()->saveMedia ( $memreas_media );
				} else {
					// error_log("Do nothing we have the media_id ----> $media_id" . PHP_EOL);
				}
				
				// error_log("About to build thumbnails..." . PHP_EOL);
				if ($isVideo) {
					
					// Create Thumbnails
					$this->createThumbNails ();
					
					// Create web quality mpeg
					$transcode_job_meta = array ();
					$transcode_job_meta ['web'] = $this->transcode ( 'web' );
					
					// Create high quality mpeg
					$transcode_job_meta ['1080p'] = $this->transcode ( '1080p' );
					
					// Update the metadata here for the transcoded files
					$json_metadata = json_encode ( $transcode_job_meta );
				} // End if ($isVideo)
				
				///////////////////////////////
				// Insert transcode_transaction
				$transcode_job_duration = strtotime ( $transcode_end_time ) - strtotime ( $transcode_start_time );
				$now = date ( 'Y-m-d H:i:s' );
				$transcode_transaction = new TranscodeTransaction ();
				$transcode_transaction->exchangeArray ( array (
						'user_id' => $this->user_id,
						'media_type' => $VideoFileType,
						'media_extension' => $VideoExt,
						'file_name' => $NewVideoName,
						'media_duration' => $duration,
						'media_size' => $filesize,
						'pass_fail' => $pass,
						'metadata' => $json_metadata,
						'transcode_job_duration' => $transcode_job_duration,
						'transcode_start_time' => $transcode_start_time,
						'transcode_end_time' => $transcode_end_time 
				) );
				$transcode_transaction_id = $memreas_transcoder_tables->getTranscodeTransactionTable ()->saveTranscodeTransaction ( $transcode_transaction );
				error_log ( "Inserted transcode_transaction...." . PHP_EOL );
				
				///////////////////////////////
				// Update the media table entry here
				$now = date ( 'Y-m-d H:i:s' );
				$json_metadata = json_encode ( $this->memreas_media_metadata );
				error_log ( "**************************************************************************" . PHP_EOL );
				error_log ( "memreas media json metadata before ----> " . $memreas_media->metadata . PHP_EOL );
				error_log ( "**************************************************************************" . PHP_EOL );
				$memreas_media->exchangeArray ( array (
						'metadata' => $json_metadata,
						'update_date' => $now 
				) );
				$media_id = $memreas_transcoder_tables->getMediaTable ()->saveMedia ( $memreas_media );
				error_log ( "memreas media json metadata after ----> " . $json_metadata . PHP_EOL );
				error_log ( "**************************************************************************" . PHP_EOL );
				
				error_log ( "Just updated $media_id" . PHP_EOL );
			} // End if(isset($_POST))
		} catch ( \Exception $e ) {
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
		// Always delete the temp dir...
		// Delete the temp dir if we got this far...
		try {
			$result = $this->rmWorkDir ( $this->homeDir );
		} catch ( \Exception $e ) {
			$pass = 0;
			$errstr = $e->getMessage ();
			error_log ( "error string ---> " . $errstr . PHP_EOL );
		}
	}
	public function createThumbnails() {
		// //////////////////////
		// Thumbnails section
		// //////////////////////
		$duration = str_replace ( ",", "", shell_exec ( "$this->ffmpegcmd -i $DestRandVideoName 2>&1 | grep 'Duration' | cut -d ' ' -f 4" ) );
		$timed = explode ( ":", $duration );
		$duration = (( float ) $timed [0]) * 3600 + (( float ) $timed [1]) * 60 + ( float ) $timed [2];
		error_log ( "duration of video is ----> $duration" . PHP_EOL );
		$filesize = filesize ( $DestRandVideoName );
		error_log ( "filesize of video is ----> $filesize" . PHP_EOL );
		$pass_fail = 0;
		$transcode_start_time = date ( "Y-m-d H:i:s" );
		
		$imagename = 'thumbnail_' . $NewVideoName . '_media-%5d.png';
		$command = array (
				'-i',
				$DestRandVideoName,
				'-s',
				$tnWidth . 'x' . $tnHeight,
				'-f',
				'image2',
				'-vf',
				'fps=fps=1/' . $tnfreqency,
				$this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . $imagename,
				'2>&1' 
		);
		
		$cmd = join ( " ", $command );
		$cmd = $ffmpegcmd . " " . $cmd;
		// echo "$cmd<br>";
		$op = shell_exec ( $cmd );
		$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_built_thumbnails';
		
		error_log ( "Just finished thumbnail operation  $cmd" . PHP_EOL );
		foreach ( glob ( $this->homeDir . self::CONVDIR . self::THUMBNAILSDIR . 'thumbnail_' . $NewVideoName . '_media-*.png' ) as $filename ) {
			
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
							"base" => $this->user_id . '/media/thumbnails/',
							"79x80" => $this->user_id . '/media/thumbnails/79x80/',
							"448x306" => $this->user_id . '/media/thumbnails/448x306/',
							"384x216" => $this->user_id . '/media/thumbnails/384x216/',
							"98x78" => $this->user_id . '/media/thumbnails/98x78/' 
					) 
			);
			
			// Put original thumbnail to S3 here...
			foreach ( $s3paths as $fmt ) {
				foreach ( $tns_sized as $key => $file ) {
					$message_data = array (
							"s3file_name" => basename ( $filename ),
							"file" => $file,
							"user_id" => $this->user_id,
							"media_id" => $media_id,
							"content_type" => "image/png",
							"s3path" => $fmt [$key] 
					);
					$this->s3videoUpload ( $message_data );
					$this->memreas_media_metadata ['S3_files'] ['thumbnails'] [$key] = $fmt [$key] . basename ( $filename );
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
				                                             // $this->homeDir.self::DESTDIR.$hls, // data/temp_job_uuid_dir/media/hls/
				$this->homeDir . self::DESTDIR . self::_1080PDIR  // data/temp_job_uuid_dir/media/p1080/
		);
		
		$permissions = 0777;
		foreach ( $toCreate as $dir ) {
			// mkdir($dir, $permissions, TRUE);
			$save = umask ( 0 );
			if (mkdir ( $dir ))
				chmod ( $dir, $permissions );
			umask ( $save );
		}
		// error_log("created directories...." . PHP_EOL);
	}
	public function s3videoUpload($message_data, $isThumbnail = false) {
		$s3file_name = $message_data ['s3file_name'];
		$file = $message_data ['file'];
		$user_id = $message_data ['user_id'];
		$media_id = $message_data ['media_id'];
		$content_type = $message_data ['content_type'];
		$output_type = $message_data ['output_type'];
		$s3path = $message_data ['s3path'];
		
		$s3_media_path = $s3path . $s3file_name;
		
		// S3 Folder Setup
		$body = EntityBody::factory ( fopen ( $file, 'r+' ) );
		$uploader = UploadBuilder::newInstance ()->setClient ( $this->s3 )->setSource ( $body )->setBucket ( MemreasConstants::S3BUCKET )->setMinPartSize ( 10 * Size::MB )->setOption ( 'Content-Type', $content_type )->setKey ( $s3_media_path )->build ();
		
		// Modified - Perform the upload to S3. Abort the upload if something goes wrong
		try {
			$uploader->upload ();
			// error_log( "Upload complete.\n", 0);
		} catch ( MultipartUploadException $e ) {
			$uploader->abort ();
			// error_log( "Upload failed.\n", 0);
		}
		
		// error_log("s3_media_path PATH ----> " . $s3_media_path);
		
		return $s3_media_path;
	}
	public function transcode($type) {
		if ($type == 'web') {
			// //////////////////////
			// web section
			// //////////////////////
			// $command =array_merge(array( '-i',$DestRandVideoName,'-vcodec', 'libx264', '-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),$ae,$customParams,array($this->homeDir.self::CONVDIR.self::WEBDIR.$NewVideoName.'x264.mp4','2>&1'));
			$transcoded_mp4_file = $this->homeDir . self::CONVDIR . self::WEB . $NewVideoName . '.mp4';
			// $cmd = $this->ffmpegcmd ." -i $DestRandVideoName $transcoded_mp4_file ".'2>&1';
			$cmd = $this->ffmpegcmd . " -i $DestRandVideoName -c:v mpeg4 -q:v 5 $transcoded_mp4_file " . '2>&1';
			
			$pass = 0;
			$output_start_time = date ( "Y-m-d H:i:s" );
			try {
				$op = shell_exec ( $cmd );
				if ($op) {
					$pass = 1;
				}
			} catch ( Exception $e ) {
				$pass = 0;
				$errstr = $e->getMessage ();
				error_log ( "error string ---> " . $errstr . PHP_EOL );
				throw $e;
			}
			
			// Put to S3 here...
			$message_data = array (
					"s3file_name" => $original_file_name . '.mp4',
					"file" => $transcoded_mp4_file,
					"user_id" => $this->user_id,
					"media_id" => $media_id,
					"content_type" => "video/mpeg", // must be mpeg for lgpl ffmpeg conversion
					"s3path" => $this->user_id . '/media/web/' 
			);
			$this->s3videoUpload ( $message_data );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_web_upload_S3';
			$arr = array (
					"ffmpeg_cmd" => $cmd,
					"ffmpeg_cmd_output" => $op,
					"output_size" => filesize ( $transcoded_mp4_file ),
					"pass_fail" => $pass,
					"error_message" => $errstr,
					"output_start_time" => $output_start_time,
					"output_end_time" => date ( "Y-m-d H:i:s" ) 
			);
			$this->memreas_media_metadata ['S3_files'] ['web'] = $this->user_id . '/media/web/' . $original_file_name . '.mp4';
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_web_completed';
		} else if ($type == '1080p') {
			// //////////////////////
			// 1080p section
			// //////////////////////
			$transcoded_1080p_file = $this->homeDir . self::CONVDIR . self::_1080PDIR . $NewVideoName . '.mp4';
			// $cmd = $this->ffmpegcmd ." -i $DestRandVideoName -q:v 1 $transcoded_1080p_file ".'2>&1';
			$cmd = $this->ffmpegcmd . " -i $DestRandVideoName c:v mpeg4 -q:v 1 $transcoded_1080p_file " . '2>&1';
			$pass = 0;
			$output_start_time = date ( "Y-m-d H:i:s" );
			try {
				$op = shell_exec ( $cmd );
				$pass = 1;
				// echo $driver->command($command);
			} catch ( Exception $e ) {
				$pass = 0;
				$errstr = $e->getMessage ();
				error_log ( "transcoder 1080p failed - op -->" . $op . PHP_EOL );
			}
			
			// Put to S3 here...
			$message_data = array (
					"s3file_name" => $original_file_name . '.mp4',
					"file" => $transcoded_1080p_file,
					"user_id" => $this->user_id,
					"media_id" => $media_id,
					"content_type" => "video/mp4", // specific to mp4 for aws metadata
					"s3path" => $this->user_id . '/media/1080p/' 
			);
			$this->s3videoUpload ( $message_data );
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_1080p_upload_S3';
			
			$arr = array (
					"ffmpeg_cmd" => $cmd,
					"ffmpeg_cmd_output" => $op,
					"output_size" => filesize ( $this->homeDir . self::CONVDIR . self::_1080PDIR . $NewVideoName . '.mp4' ),
					"pass_fail" => $pass,
					"error_message" => $errstr,
					"output_start_time" => $output_start_time,
					"output_end_time" => date ( "Y-m-d H:i:s" ) 
			);
			
			$this->memreas_media_metadata ['S3_files'] ['1080p'] = $this->user_id . '/media/1080p/' . $original_file_name . '.mp4';
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_1080p_completed';
			$this->memreas_media_metadata ['S3_files'] ['transcode_progress'] [] = 'transcode_completed';
		}
		return $arr;
	} // End transcode
} //End class


