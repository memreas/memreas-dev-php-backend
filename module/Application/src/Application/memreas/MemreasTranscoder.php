<?php
namespace Application\memreas;

use Zend\Session\Container;
use PHPImageWorkshop\ImageWorkshop;

//memreas custom
use Application\memreas\MemreasAWSTranscoder;
use Application\memreas\MemreasTranscoderTables;
use Application\memreas\MUUID;
//memreas models
use Application\Model\MemreasConstants;
use Application\Model\Media;
use Application\Model\MediaTable;
use Application\Model\TranscodeTransaction;
use Application\Model\TranscodeTransactionTable;


class MemreasTranscoder {

	// Link used for code
	// https://developer.paypal.com/webapps/developer/docs/api/#store-a-credit-card
	
	protected $user_id;
	protected $session;
	protected $memreas_aws_transcoder;

    public function __construct() {
		$this->memreas_aws_transcoder = new MemreasAWSTranscoder();
    }
    
    public function resizeImage($dirPath, $file, $thumbnail_name, $height, $width) {

        $layer = ImageWorkshop::initFromPath($file);
        //$layer->resizeInPixel($height, $width, true, 0, 0, 'MM');  //Maintains image
        $layer->resizeInPixel($height, $width);
        $createFolders = true;
        $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
        $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
        $layer->save($dirPath, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality);
        $file = $dirPath . $thumbnail_name;

//error_log("Inside fetchResizeUpload - resized and saved local file is now  --> " . $file);                	

		return $file;    
    }

    private function rmWorkDir($dir) {
		$it = new \RecursiveDirectoryIterator($dir);
		$files = new \RecursiveIteratorIterator($it,
					 \RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($dir);
	}
	
	public function exec($message_data, $memreas_transcoder_tables, $service_locator, $isUpload=false) {
	
error_log("_REQUEST----> " . print_r($_REQUEST, true) .PHP_EOL);
error_log("message_data----> " . print_r($message_data, true) .PHP_EOL);

		try {
			 //$message_data entries
			$user_id = $message_data['user_id'];
			$media_id = $message_data['media_id'];
			$content_type = $message_data['content_type'];
			$s3path = $message_data['s3path'];
			$s3file_name = $message_data['s3file_name'];
			$isVideo = $message_data['isVideo'];
				
			//Fetch the media entry here:
			$memreas_media = $memreas_transcoder_tables->getMediaTable()->getMedia($media_id);
			$meta = $memreas_media->metadata;
//error_log("meta------>".$meta.PHP_EOL);
			$memreas_media_metadata = json_decode($memreas_media->metadata, true);
//error_log("meta------>".print_r($memreas_media_metadata,true).PHP_EOL);
			$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_start';

//Debugging			
error_log("********************************************".PHP_EOL);
error_log("meta------>".print_r($memreas_media_metadata,true).PHP_EOL);

			
			//date_default_timezone_set('UTC');
			$starttime = date('Y-m-d H:i:s');
			$files_json;
			$message_data;
			//$metadata = array();
			if(isset($message_data)) {
			//HomeDirectory.$DestinationDirectory.$NewVideoName
				//AWS Settings

				$mysqli = NULL;
				if (getcwd()=='/var/app/current') {
error_log("found /var/app/current" . PHP_EOL);
					$ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG;		//  :::: AWS ffmpeg installation
					$ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE;		//  :::: AWS ffprobe installation
				} else {
error_log("!found /var/app/current" . PHP_EOL);
					$ffmpegcmd = MemreasConstants::MEMREAS_TRANSCODER_FFMPEG_LOCAL;		//  :::: Your ffmpeg installation
					$ffprobecmd = MemreasConstants::MEMREAS_TRANSCODER_FFPROBE_LOCAL;		//  :::: Your ffmpeg installation
				}

				//Make directories here - create a unique directory by user_id
				$temp_job_uuid_dir = MUUID::fetchUUID();
error_log("temp_job_uuid_dir ----> $temp_job_uuid_dir" . PHP_EOL);

				//Some Settings
				$WebHome 				= '/var/app/current/data/'; // 2944444a-cc8f-11e2-8fd6-12313909a953 in JSON
				//$WebHome 				= '/memreas_transcode_worker/'; // 2944444a-cc8f-11e2-8fd6-12313909a953 in JSON
				$HomeDirectory			= $WebHome . $temp_job_uuid_dir . '/'; //Home Directory ends with / (slash) :::: Your AMAZON home
	
				$DestinationDirectory	= 'media/'; //Upload Directory ends with / (slash):::: media/ in JSON
				$ConvertedDirectory		= 'media/'; //Converted Directory ends with / (slash) :::: media/ in JSON
				$p1080 					= '1080p/'; // Your 1080p Dir, end with slash (/)
				$thumbnails				= 'thumbnails/';  // Your thumbnails Dir, end with slash (/)
				$hls 					= 'hls/';  // Your hls Dir, end with slash (/)
				$webm					= 'webm/';  // Your webm Dir, end with slash (/)
				$web					= 'web/';  // Your base mpeg4 Dir, end with slash (/)
				/*
				 * TODO - Add 125x98 as thumbnail
				 */
				$_79x80					= '79x80/';  // Your 79x80 Dir, end with slash (/)
				$_448x306				= '448x306/';  // Your 448x306 Dir, end with slash (/)
				$_384x216				= '384x216/';  // Your _384x216 Dir, end with slash (/)
				$_98x78					= '98x78/';  // Your 98x78 Dir, end with slash (/)
				/*
				 * TODO: Fix this with device id
				 */
				$identifier				= '2944444a-cc8f-11e2-8fd6-12313909a953_354614555375243'; // Change accordingly

				//Thumbnail settings	
				$tnWidth 				= 448;
				$tnHeight 				= 306;
				$tnfreqency 			= 60; // in seconds - 60 means every 60 seconds (minute)
				$errstr = '';

				//Make directories here - create a unique directory by user_id
				$toCreate = array(
					$HomeDirectory, // data/temp_uuid_dir/
					$HomeDirectory.$DestinationDirectory, // data/temp_job_uuid_dir/media/
					$HomeDirectory.$DestinationDirectory.$thumbnails, // data/temp_job_uuid_dir/media/thumbnails/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_79x80, // data/temp_job_uuid_dir/media/thumbnails/79x80/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_448x306, // data/temp_job_uuid_dir/media/thumbnails/448x306/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_384x216, // data/temp_job_uuid_dir/media/thumbnails/384x216/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_98x78, // data/temp_job_uuid_dir/media/thumbnails/98x78/
					$HomeDirectory.$DestinationDirectory.$web, // data/temp_job_uuid_dir/media/web/
					//$HomeDirectory.$DestinationDirectory.$webm, // data/temp_job_uuid_dir/media/webm/
					//$HomeDirectory.$DestinationDirectory.$hls, // data/temp_job_uuid_dir/media/hls/
					$HomeDirectory.$DestinationDirectory.$p1080, // data/temp_job_uuid_dir/media/p1080/
				);

				$permissions = 0777;
				foreach ($toCreate as $dir) {
				  	//mkdir($dir, $permissions, TRUE);
					$save = umask(0);
				    if (mkdir($dir)) chmod($dir, $permissions);
				    umask($save);
				}				
error_log("created directories...." . PHP_EOL);
				
				if (!$isUpload) {
					//Fetch the json from the post
					if (isset($_POST['json'])) {
						$message_data = json_decode($_POST['json'], true);
					}
						
					$this->user_id = $message_data['user_id'];
					//get the file from S3 here
					$tmp_file = $HomeDirectory.$DestinationDirectory.$message_data['s3file_name'];

//Debugging
$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_folders_created';
						
					
/*
error_log("About to get " . $message_data['s3path'].$message_data['s3file_name'] . PHP_EOL);	
error_log("About to save as  " . $tmp_file . PHP_EOL);	
error_log('MemreasConstants::S3BUCKET ----> ' . MemreasConstants::S3BUCKET . PHP_EOL);	
error_log('message_data[s3path] ----> ' . $message_data['s3path'] . PHP_EOL);	
error_log('message_data[s3file_name] ----> ' . $message_data['s3file_name'] . PHP_EOL);
error_log("About to fetch S3 file ... ".PHP_EOL);	
error_log("About to fetch S3 file - Key ---> ".$message_data['s3path'].$message_data['s3file_name'].PHP_EOL);	
error_log("About to fetch S3 file - SaveAs ---> ".$tmp_file.PHP_EOL);	
*/
	
					$response = $this->memreas_aws_transcoder->s3->getObject(array(
						'Bucket' => MemreasConstants::S3BUCKET, 
						'Key'	 =>	$message_data['s3path'].$message_data['s3file_name'], 
						'SaveAs' =>	$tmp_file,
					));
					$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_S3_file_saved';

error_log("Fetched S3 file ... ".PHP_EOL);	
//Debugging

					//$VideoFileName 	= str_replace(' ','-',strtolower($message_data['s3file_name'])); 
					$VideoFileName 	= str_replace(' ','-',$message_data['s3file_name']); 
					//$TempSrc	 	= $_FILES['VideoFile']['tmp_name'][0]; // Tmp name of video file stored in PHP tmp folder
					//$VideoFileType	= $response['ContentType']; //Obtain file type, returns "video/png", video/jpeg, text/plain etc.
					$VideoFileType = $message_data['content_type'];
					//Get file extension from Video name, this will be re-added after random name
					$VideoExt = substr($VideoFileName, strrpos($VideoFileName, '.'));
					$VideoExt = str_replace('.','',$VideoExt);
	
					//remove extension from filename
					$VideoFileName 		= preg_replace("/\\.[^.\\s]{3,4}$/", "", $VideoFileName); 
	
					//Construct a new video name (with random number added) for our new video.
					$original_file_name=$VideoFileName.".".$VideoExt;
					$NewVideoName = $original_file_name;
					$filesize =  filesize($DestRandVideoName);
					//set the Destination Video
	
					$DestRandVideoName = $HomeDirectory.$DestinationDirectory.$NewVideoName; //Name for Big Video	
					//$DestRandVideoName = $tmp_file;
				} else if(isset($_FILES['VideoFile']) && is_uploaded_file($_FILES['VideoFile']['tmp_name'][0])) {
					
error_log("Inside if videofile and is uploaded...." . PHP_EOL);	
					// Elements (values) of $_FILES['VideoFile'] array
					//let's access these values by using their index position
					$VideoFileName 	= str_replace(' ','-',strtolower($_FILES['VideoFile']['name'][0])); 
					$TempSrc	 	= $_FILES['VideoFile']['tmp_name'][0]; // Tmp name of video file stored in PHP tmp folder
					$VideoFileType	= $_FILES['VideoFile']['type'][0]; //Obtain file type, returns "video/png", video/jpeg, text/plain etc.
					//Get file extension from Video name, this will be re-added after random name
					$VideoExt = substr($VideoFileName, strrpos($VideoFileName, '.'));
					$VideoExt = str_replace('.','',$VideoExt);
	
					//remove extension from filename
					$VideoFileName 		= preg_replace("/\\.[^.\\s]{3,4}$/", "", $VideoFileName); 
	
					//Construct a new video name (with random number added) for our new video.
					$original_file_name=$VideoFileName.".".$VideoExt;
					$NewVideoName = $original_file_name;
					//set the Destination Video
	
					$DestRandVideoName 			= $HomeDirectory.$DestinationDirectory.$NewVideoName; //Name for Big Video	
error_log("Leaving ... Inside if videofile and is uploaded...." . PHP_EOL);	
				} else if(!isset($_FILES['VideoFile']) || !is_uploaded_file($_FILES['VideoFile']['tmp_name'][0])) {
						error_log('Something went wrong with Upload!'); 
						throw new \Exception('Something went wrong with Upload!'); // output error when above checks fail.
				}
	
error_log("Fetched S3 file ... ".PHP_EOL);	
error_log("VIDEO FILE TYPE ----------> $VideoFileType" . PHP_EOL);
				//Let's use $VideoFileType variable to check wheather uploaded file is supported.
				//We use PHP SWITCH statement to check valid video format, PHP SWITCH is similar to IF/ELSE statements 
				//suitable if we want to compare the a variable with many different values
				switch(strtolower($VideoFileType))
				{
					case 'video/mp4': break;
					case 'video/mov': break;
					case 'video/quicktime': break;
					case 'video/x-msvideo': break;
					case 'video/x-ms-wmv': break;
					case 'video/x-flv': break;
					case 'video/3gpp': break;
					case 'video/webm': break;
					case 'video/mp1s': break;
					case 'video/mp2p': break;
					default: {
//Debugging
$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_error';
$memreas_media_metadata['S3_files']['error_message'] = 'transcode_error:.invalid_file_type:'.$VideoFileType;
						throw new \Exception('Unsupported File!'); //output error and exit
					}
				}
				
				// Save file in upload destination
				if ($isUpload) {
error_log("move file ...." . PHP_EOL);	
					move_uploaded_file($TempSrc,$DestRandVideoName);

error_log("upload to s3...." . PHP_EOL);	
					//Put to S3 here...
					$message_data = array (
						"s3file_name"=>$original_file_name,
						"file"=>$DestRandVideoName,
						"user_id"=>$this->user_id,
						"media_id"=>"placeholder",
						"content_type"=>"video/mpeg", //must be mpeg for lgpl ffmpeg conversions
						"s3path"=>$this->user_id.'/media/',
					);
					$media_s3_path = $this->memreas_aws_transcoder->s3videoUpload($message_data);
					//Store the metadata
					$metadata['S3_files']['path'] = $media_s3_path;
					$metadata['S3_files']['full'] = $media_s3_path;
					//Insert a media table entry here
					$now = date('Y-m-d H:i:s');
					$memreas_media = new Media();
					$memreas_media->exchangeArray(array(
								'user_id' => $this->user_id, 
								'is_profile_pic' => 0, 
								'sync_status' => 0, 
								'metadata' => json_encode($metadata), 
								'report_flag' => 0, 
								'create_date' => $now, 
								'update_date' => $now, 
							));
					$media_id = $memreas_transcoder_tables->getMediaTable()->saveMedia($memreas_media);
				} else {
//error_log("Do nothing we have the media_id ----> $media_id" . PHP_EOL);
				}
				
//error_log("About to build thumbnails..." . PHP_EOL);
				////////////////////////
				// Thumbnails section
				////////////////////////
				$duration = str_replace(",","",shell_exec("$ffmpegcmd -i $DestRandVideoName 2>&1 | grep 'Duration' | cut -d ' ' -f 4"));
				$timed = explode(":",$duration);
				$duration = ((float) $timed[0])*3600+((float) $timed[1])*60+(float) $timed[2];
error_log("duration of video is ----> $duration" . PHP_EOL);
				$filesize =  filesize($DestRandVideoName);
error_log("filesize of video is ----> $filesize" . PHP_EOL);
				$pass_fail = 0;
				$transcode_start_time = date("Y-m-d H:i:s");

				$imagename = 'thumbnail_'.$NewVideoName.'_media-%5d.png';
				$command = array(
								'-i',$DestRandVideoName,
								'-s', $tnWidth.'x'.$tnHeight, 
								'-f', 'image2', 
								'-vf', 'fps=fps=1/'.$tnfreqency ,$HomeDirectory.$ConvertedDirectory.$thumbnails.$imagename,
								'2>&1'
							);
	
				$cmd = join(" ",$command);
				$cmd = $ffmpegcmd ." ".$cmd;
				//echo "$cmd<br>";
				$op = shell_exec($cmd);
				$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_built_thumbnails';
				
error_log("Just finished thumbnail operation  $cmd" . PHP_EOL);
				foreach(glob($HomeDirectory.$ConvertedDirectory.$thumbnails.'thumbnail_'.$NewVideoName.'_media-*.png') as $filename){
						
//error_log("WebHome  DestinationDirectory  thumbnails basename(filename)  ---->  " . $WebHome.$DestinationDirectory.$thumbnails.basename($filename) . PHP_EOL);
//error_log("basename(filename)  ---->  " . basename($filename) . PHP_EOL);
//error_log("filename  ---->  " . $filename . PHP_EOL);
					$tns[] = $WebHome.$DestinationDirectory.$thumbnails.basename($filename);

					//////////////////////////////////////////////////
					//Resize thumbnails as needed and save locally
					$tns_sized = array(
						"full"=>$filename,
						"79x80"=>$this->resizeImage(
									$HomeDirectory.$DestinationDirectory.$thumbnails.$_79x80, 
									$filename, basename($filename), 79, 80),
						"448x306"=>$this->resizeImage(
									$HomeDirectory.$DestinationDirectory.$thumbnails.$_448x306, 
									$filename, basename($filename), 448, 306),
						"384x216"=>$this->resizeImage(
									$HomeDirectory.$DestinationDirectory.$thumbnails.$_384x216, 
									$filename, basename($filename), 384, 216),
						"98x78"=>$this->resizeImage(
									$HomeDirectory.$DestinationDirectory.$thumbnails.$_98x78, 
									$filename, basename($filename), 98, 78),
					);
					$s3paths = array (
						"thumbnails"=>array (
						//web
							"base"=>$this->user_id.'/media/thumbnails/',
							"79x80"=>$this->user_id.'/media/thumbnails/79x80/',
							"448x306"=>$this->user_id.'/media/thumbnails/448x306/',
							"384x216"=>$this->user_id.'/media/thumbnails/384x216/',
							"98x78"=>$this->user_id.'/media/thumbnails/98x78/',
						),
					);

					//Put original thumbnail to S3 here...
					foreach ($s3paths as $fmt) {
						foreach ($tns_sized as $key => $file) {
							$message_data = array (
								"s3file_name"=>basename($filename),
								"file"=>$file,
								"user_id"=>$this->user_id,
								"media_id"=>$media_id,
								"content_type"=>"image/png", 
								"s3path"=>$fmt[$key],
							);
							$this->memreas_aws_transcoder->s3videoUpload($message_data);
							$memreas_media_metadata['S3_files']['thumbnails'][$key] = $fmt[$key].basename($filename);
						}
					}
				} //End for each thumbnail
				$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_stored_thumbnails';

				////////////////////////
				// web section
				////////////////////////
				if (isset($_POST['encoding_web']) || (!$isUpload)) {
		
					if ($audioEnabled) {
						$ae[] = '-acodec';
						$ae[] =	'libfaac';
					}
		
					//$command =array_merge(array( '-i',$DestRandVideoName,'-vcodec', 'libx264', '-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),$ae,$customParams,array($HomeDirectory.$ConvertedDirectory.$web.$NewVideoName.'x264.mp4','2>&1'));
					$transcoded_mp4_file = $HomeDirectory.$ConvertedDirectory.$web.$NewVideoName.'.mp4';
					$cmd = $ffmpegcmd ." -i $DestRandVideoName $transcoded_mp4_file ".'2>&1';
					//$cmd = $ffmpegcmd ." -i $DestRandVideoName -c:v mpeg4 -q:v 5 $transcoded_mp4_file ".'2>&1';
					
					$html .= "Generating MPEG4 (Web Quality)\n<br>\n\n";
					$pass = 0;
					$output_start_time = date("Y-m-d H:i:s");
					try{
						$op = shell_exec($cmd);
error_log("op ---> " . $op . PHP_EOL);						
						$pass = 1;
					}
					catch(Exception $e){$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);}

					//Put to S3 here...
					$message_data = array (
						"s3file_name"=>$original_file_name.'.mp4',
						"file"=>$transcoded_mp4_file,
						"user_id"=>$this->user_id,
						"media_id"=>$media_id,
						"content_type"=>"video/mpeg", //must be mpeg for lgpl ffmpeg conversion
						"s3path"=>$this->user_id.'/media/web/',
					);
					$this->memreas_aws_transcoder->s3videoUpload($message_data);
					$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_web_upload_S3';
					$webarr = array(
						"ffmpeg_cmd"=>$cmd,
						"ffmpeg_cmd_output"=>$op,
						"output_size"=>filesize($transcoded_mp4_file),
						"pass_fail"=>$pass,
						"error_message"=>$errstr,
						"output_start_time"=>$output_start_time,
						"output_end_time"=>date("Y-m-d H:i:s"),
					);
					$memreas_media_metadata['S3_files']['web'] = $this->user_id.'/media/web/'.$original_file_name.'.mp4';
					$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_web_completed';
				} //End web section
				
				////////////////////////
				// 1080p section
				////////////////////////
				if (isset($_POST['encoding_1080']) || (!$isUpload)) {
				
						$transcoded_1080p_file = $HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4';
						$cmd = $ffmpegcmd ." -i $DestRandVideoName -q:v 1 $transcoded_1080p_file ".'2>&1';
						//$cmd = $ffmpegcmd ." -i $DestRandVideoName c:v mpeg4 -q:v 1 $transcoded_1080p_file ".'2>&1';
						$pass = 0;
						$output_start_time = date("Y-m-d H:i:s");
						try{
							$op = shell_exec($cmd);
error_log("op ---> " . $op . PHP_EOL);						
							$pass = 1;
							// echo $driver->command($command);
						} catch(Exception $e){$pass = 0; $errstr = $e->getMessage();}
				
						//Put to S3 here...
						$message_data = array (
									"s3file_name"=>$original_file_name.'.mp4',
									"file"=>$HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4',
									"user_id"=>$this->user_id,
									"media_id"=>$media_id,
									"content_type"=>"video/mp4", //specific to mp4 for aws metadata
									"s3path"=>$this->user_id.'/media/1080p/',
								);
						$this->memreas_aws_transcoder->s3videoUpload($message_data);
						$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_1080p_upload_S3';
				
						$p1080arr = array(
								"ffmpeg_cmd"=>$cmd,
								"ffmpeg_cmd_output"=>$op,
								"output_size"=>filesize($HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4'),
								"pass_fail"=>$pass,
								"error_message"=>$errstr,
										"output_start_time"=>$output_start_time,
										"output_end_time"=>date("Y-m-d H:i:s"),
								);
				
						$html .= '<a href="/'.$WebHome.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4'.'" alt="Thumbnail">MPEG 1080</a><br>';
						$memreas_media_metadata['S3_files']['1080p'] = $this->user_id.'/media/1080p/'.$original_file_name.'.mp4';
						$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_1080p_completed';
						$memreas_media_metadata['S3_files']['transcode_progress'][] = 'transcode_completed';
				}				
				//Update the metadata here for the transcoded files
				$transcode_job_meta = array();
				$transcode_job_meta['1080p'] = $p1080arr;
				$transcode_job_meta['Web'] = $webarr;
				$json_metadata = json_encode($transcode_job_meta);				

/*
error_log("this->user_id".$this->user_id.PHP_EOL);
error_log("VideoFileType".$VideoFileType.PHP_EOL);
error_log("VideoExt".$VideoExt.PHP_EOL);
error_log("NewVideoName".$NewVideoName.PHP_EOL);
error_log("duration".$duration.PHP_EOL);
error_log("filesize".$filesize.PHP_EOL);
error_log("pass".$pass.PHP_EOL);
error_log("transcode_job_duration".$transcode_job_duration.PHP_EOL);
error_log("transcode_start_time".$transcode_start_time.PHP_EOL);
error_log("transcode_end_time".$transcode_end_time.PHP_EOL);
*/				
				 $transcode_job_duration = strtotime($transcode_end_time) - strtotime($transcode_start_time);
				 //Insert transcode_transaction
				 $now = date('Y-m-d H:i:s');
				 $transcode_transaction = new TranscodeTransaction();
				 $transcode_transaction->exchangeArray(array(
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
							'transcode_end_time' => $transcode_end_time, 
				 ));
				 $transcode_transaction_id =  $memreas_transcoder_tables->getTranscodeTransactionTable()->saveTranscodeTransaction($transcode_transaction);
error_log("Inserted transcode_transaction....".PHP_EOL);
				
				//Update the media table entry here
				$now = date('Y-m-d H:i:s');
				$json_metadata = json_encode($memreas_media_metadata);
error_log("**************************************************************************".PHP_EOL);				
error_log("memreas media json metadata before ----> ".$memreas_media->metadata.PHP_EOL);				
error_log("**************************************************************************".PHP_EOL);				
				$memreas_media->exchangeArray(array(
							'metadata' => $json_metadata, 
							'update_date' => $now, 
						));
				$media_id = $memreas_transcoder_tables->getMediaTable()->saveMedia($memreas_media);
error_log("memreas media json metadata after ----> ".$json_metadata.PHP_EOL);
error_log("**************************************************************************".PHP_EOL);

error_log("Just updated $media_id" . PHP_EOL);

			} // End if(isset($_POST))
		} catch (\Exception $e) {
			error_log( 'Caught exception: '.  $e->getMessage() . PHP_EOL);
		}
		//Always delete the temp dir...
		//Delete the temp dir if we got this far...
		try{
			$result = $this->rmWorkDir($HomeDirectory);
		}
		catch(\Exception $e){
			$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);
		}
	}
}


