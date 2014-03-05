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
			//date_default_timezone_set('UTC');
			$starttime = date('Y-m-d H:i:s');
			$files_json;
			$message_data;
			$metadata = array();
			if(isset($message_data)) {
			//HomeDirectory.$DestinationDirectory.$NewVideoName
				//AWS Settings

				$mysqli = NULL;
				if (getcwd()=='/var/app/current') {
error_log("found /var/app/current" . PHP_EOL);
					$ffmpegcmd = '/memreas_ffmpeg_install/bin/ffmpeg';		//  :::: Your ffmpeg installation
					$ffprobecmd = '/memreas_ffmpeg_install/bin/ffprobe';		//  :::: Your ffmpeg installation
				} else {
//error_log("!found /var/app/current" . PHP_EOL);
					$ffmpegcmd = '/usr/local/Cellar/ffmpeg/ffmpeg';		//  :::: Your ffmpeg installation
					$ffprobecmd = '/usr/local/Cellar/ffmpeg/ffprobe';		//  :::: Your ffmpeg installation
				}

				//Make directories here - create a unique directory by user_id
				$temp_job_uuid_dir = MUUID::fetchUUID();
//error_log("temp_job_uuid_dir ----> $temp_job_uuid_dir" . PHP_EOL);

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
					$HomeDirectory.$DestinationDirectory.$webm, // data/temp_job_uuid_dir/media/webm/
					$HomeDirectory.$DestinationDirectory.$hls, // data/temp_job_uuid_dir/media/hls/
					$HomeDirectory.$DestinationDirectory.$p1080, // data/temp_job_uuid_dir/media/p1080/
				);

error_log("About to create folders..." . PHP_EOL);	
				$permissions = 0777;
				foreach ($toCreate as $dir) {
				  	//mkdir($dir, $permissions, TRUE);
					$save = umask(0);
				    if (mkdir($dir)) chmod($dir, $permissions);
				    umask($save);
error_log("mkdir ----> $dir" . PHP_EOL);	
				}				

				if (!$isUpload) {
					//Fetch the json from the post
					if (isset($_POST['json'])) {
						$message_data = json_decode($_POST['json'], true);
error_log("FOUND JSON ----> " . $_POST['json'] . PHP_EOL);	
					}

/*
//$message_data entries
$user_id = $message_data['user_id'];
$media_id = $message_data['media_id'];
$content_type = $message_data['content_type'];
$s3path = $message_data['s3path'];
$s3file_name = $message_data['s3file_name'];
$isVideo = $message_data['isVideo'];
$email = $message_data['email'];
*/


error_log("user_id ----> " . $message_data['user_id'] . PHP_EOL);	
					$this->user_id = $message_data['user_id'];
					//get the file from S3 here
					$tmp_file = $HomeDirectory.$DestinationDirectory.$message_data['s3file_name'];
						
/*
error_log("About to get " . $message_data['s3path'].$message_data['s3file_name'] . PHP_EOL);	
error_log("About to save as  " . $tmp_file . PHP_EOL);	
error_log('MemreasConstants::S3BUCKET ----> ' . MemreasConstants::S3BUCKET . PHP_EOL);	
error_log('message_data[s3path] ----> ' . $message_data['s3path'] . PHP_EOL);	
error_log('message_data[s3file_name] ----> ' . $message_data['s3file_name'] . PHP_EOL);
*/	
error_log("About to fetch S3 file ... ".PHP_EOL);	
					$response = $this->memreas_aws_transcoder->s3->getObject(array(
						'Bucket' => MemreasConstants::S3BUCKET, 
						'Key'	 =>	$message_data['s3path'].$message_data['s3file_name'], 
						'SaveAs' =>	$tmp_file,
					));

error_log("Fetched S3 file ... ".PHP_EOL);	
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
						die('Something went wrong with Upload!'); // output error when above checks fail.
				}
	
error_log("Fetched S3 file ... ".PHP_EOL);	
error_log("VIDEO FILE TYPE ----------> $VideoFileType" . PHP_EOL);
				//Let's use $VideoFileType variable to check wheather uploaded file is supported.
				//We use PHP SWITCH statement to check valid video format, PHP SWITCH is similar to IF/ELSE statements 
				//suitable if we want to compare the a variable with many different values
				switch(strtolower($VideoFileType))
				{
					case 'video/mp4': break;
					case 'video/quicktime': break;
					case 'video/x-msvideo': break;
					case 'video/x-ms-wmv': break;
					case 'video/x-flv': break;
					case 'video/3gpp': break;
					case 'video/webm': break;
					case 'video/mp1s': break;
					case 'video/mp2p': break;
					default: die('Unsupported File!'); //output error and exit
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
						"content_type"=>"video/mp4", //specific to webm for aws metadata
						"s3path"=>$this->user_id.'/media/',
					);
					$media_s3_path = $this->memreas_aws_transcoder->s3videoUpload($message_data);
					//Store the metadata

error_log("about to save media_s3_path ----.> $media_s3_path...." . PHP_EOL);	
					$metadata['S3_files']['path'] = $media_s3_path;
					$metadata['S3_files']['Full'] = $media_s3_path;

error_log("Just got media s3 path and set metadata...." . PHP_EOL);	
error_log("metadata ---> " . json_encode($metadata) . PHP_EOL);	
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
error_log("Just inserted $media_id" . PHP_EOL);
				} else {
					//Fetch the media table entry here?...
					//$memreas_media = $memreas_transcoder_tables->getMediaTable()->getMedia($message_data['media_id']);
					//$memreas_media->exchangeArray(array(
					//			'metadata' => json_encode($metadata), 
					//			'update_date' => $now, 
					//		));
					//$media_id = $memreas_transcoder_tables->getMediaTable()->saveMedia($memreas_media);
error_log("Do nothing we have the media_id ----> $media_id" . PHP_EOL);
				}
				
error_log("About to build thumbnails..." . PHP_EOL);
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
				
error_log("Just finished thumbnail operation  $cmd" . PHP_EOL);
error_log("result  $op" . PHP_EOL);

				$tns;
				$html = "<h3>Thumbnails</h3><br><br>\n\n";
				foreach(glob($HomeDirectory.$ConvertedDirectory.$thumbnails.'thumbnail_'.$NewVideoName.'_media-*.png') as $filename){
						
//error_log("WebHome  DestinationDirectory  thumbnails basename(filename)  ---->  " . $WebHome.$DestinationDirectory.$thumbnails.basename($filename) . PHP_EOL);
//error_log("basename(filename)  ---->  " . basename($filename) . PHP_EOL);
//error_log("filename  ---->  " . $filename . PHP_EOL);
					$tns[] = $WebHome.$DestinationDirectory.$thumbnails.basename($filename);
					$html .= '<div style="margin:10px;padding:5px:"><img src="/'.$WebHome.$ConvertedDirectory.$thumbnails.basename($filename).'" alt="Thumbnail"></div>';

					//////////////////////////////////////////////////
					//Resize thumbnails as needed and save locally
					$tns_sized = array(
						"base"=>$filename,
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
						"web"=>array (
						//web
							"base"=>$this->user_id.'/media/web/thumbnail/',
							"79x80"=>$this->user_id.'/media/web/thumbnail/79x80/',
							"448x306"=>$this->user_id.'/media/web/thumbnail/448x306/',
							"384x216"=>$this->user_id.'/media/web/thumbnail/384x216/',
							"98x78"=>$this->user_id.'/media/web/thumbnail/98x78/',
						),
/*
							"webm"=>array (
							//webm
							"base"=>$this->user_id.'/media/webm/thumbnail/',
							"79x80"=>$this->user_id.'/media/webm/thumbnail/79x80/',
							"448x306"=>$this->user_id.'/media/webm/thumbnail/448x306/',
							"384x216"=>$this->user_id.'/media/webm/thumbnail/384x216/',
							"98x78"=>$this->user_id.'/media/webm/thumbnail/98x78/',
						),
						"hls"=>array (
							//hls
							"base"=>$this->user_id.'/media/hls/thumbnail/',
							"79x80"=>$this->user_id.'/media/hls/thumbnail/79x80/',
							"448x306"=>$this->user_id.'/media/hls/thumbnail/448x306/',
							"384x216"=>$this->user_id.'/media/hls/thumbnail/384x216/',
							"98x78"=>$this->user_id.'/media/hls/thumbnail/98x78/',
						),
*/
						"1080p"=>array (
						//1080p
							"base"=>$this->user_id.'/media/1080p/thumbnail/',
							"79x80"=>$this->user_id.'/media/1080p/thumbnail/79x80/',
							"448x306"=>$this->user_id.'/media/1080p/thumbnail/448x306/',
							"384x216"=>$this->user_id.'/media/1080p/thumbnail/384x216/',
							"98x78"=>$this->user_id.'/media/1080p/thumbnail/98x78/',
						)
					);

					//Put original thumbnail to S3 here...
					foreach ($s3paths as $fmt) {
						foreach ($tns_sized as $key => $file) {
							$message_data = array (
								"s3file_name"=>basename($filename),
								"file"=>$file,
								"user_id"=>$this->user_id,
								"media_id"=>$media_id,
								"content_type"=>"image/png", //specific to webm for aws metadata
								"s3path"=>$fmt[$key],
							);
							$this->memreas_aws_transcoder->s3videoUpload($message_data);
						}
					}
				} //End for each thumbnail
				$html .= "<br><br>\n\n";

				// Custom Settings

				// Video
				$videoSize = isset($_POST['video_size']) ? $_POST['video_size'] : '640x360';
				$videoBitrate = isset($_POST['video_bitrate'])	? (int)$_POST['video_bitrate'] : '700';
				$videoFramerate	= isset($_POST['video_framerate'])	? (int)$_POST['video_framerate'] : '30';
				$videoDeinterlace	= isset($_POST['encoding_video_deinterlace'])	? 1 : 0 ;

				//$videoBitrate = ((int)$videoBitrate)*1000;

				// Adudio
				$audioEnabled	= (isset($_POST['encoding_enable_audio']) || (!$isUpload))	? 1 : 0 ;
				$audioSamplerate	= isset($_POST['encoding_audio_sampling_rate'])	? (int)$_POST['encoding_audio_sampling_rate'] : '44100';
				$audioBitrate	= isset($_POST['encoding_audio_bitrate'])	? (int)$_POST['encoding_audio_bitrate'] : '128';
				$audioChannels	= (isset($_POST['encoding_audio_channels']) && $_POST['encoding_audio_channels']	== 'stereo')	? 2 : 1 ;

				// Build up the ffmpeg params from the values posted from the html form
				$customParams[]  = '-s'; 
				$customParams[]  = $videoSize; // Format the video size
	
				$customParams[]  = '-b:v';
				$customParams[]  = $videoBitrate.'k'; // Format the video bit rate
	
				$customParams[]  = '-r';
				$customParams[]  = $videoFramerate;	// Format the video frame rate

				if ($videoDeinterlace) {
					$customParams[] = '-deinterlace ';	// Deinterlace the video
				}
				if ($audioEnabled) {
					$customParams[]  = '-ar';
					$customParams[]  = $audioSamplerate;	// Audio sample rate
		
					$customParams[]  = '-ab';
					$customParams[]  = $audioBitrate.'k';	// Audio bit rate
		
					$customParams[]  = '-ac';
					$customParams[]  = $audioChannels;	// Audio Channels
				}
				else
				{
					$customParams[]  = '-an'; // Disable audio
				}
	
				$customParams[] 	 = '-y';	// Overwrite existing file


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
					$html .= "Generating MPEG4 (Web Quality)\n<br>\n\n";
					$pass = 0;
					$output_start_time = date("Y-m-d H:i:s");
					try{
						$op = shell_exec($cmd);
error_log("**********************************************".PHP_EOL);
error_log("FFMPEG MPEG4 NORMAL CMD -----> $cmd".PHP_EOL);
error_log("**********************************************".PHP_EOL);
error_log("OUTPUT OF FFMPEG MPEG4 CMD -----> $op".PHP_EOL);
error_log("**********************************************".PHP_EOL);
						$pass = 1;
					}
					catch(Exception $e){$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);}

					//Put to S3 here...
					$message_data = array (
						"s3file_name"=>$original_file_name.'.mp4',
						"file"=>$transcoded_mp4_file,
						"user_id"=>$this->user_id,
						"media_id"=>"placeholder",
						"content_type"=>"video/mp4", //specific to webm for aws metadata
						"s3path"=>$this->user_id.'/media/web/',
					);
					$this->memreas_aws_transcoder->s3videoUpload($message_data);
					$webarr = array(
						"ffmpeg_cmd"=>$cmd,
						"ffmpeg_cmd_output"=>$op,
						"output_size"=>filesize($transcoded_mp4_file),
						"pass_fail"=>$pass,
						"error_message"=>$errstr,
						"output_start_time"=>$output_start_time,
						"output_end_time"=>date("Y-m-d H:i:s"),
					);
		
					$html .= '<a href="/'.$transcoded_mp4_file.'" alt="Thumbnail">web</a><br>';
					
				} //End web section

				////////////////////////
				// 1080p section
				////////////////////////
				if (isset($_POST['encoding_1080']) || (!$isUpload)) {
				
						$transcoded_1080p_file = $HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4';
						$cmd = $ffmpegcmd ." -i $DestRandVideoName -q:v 1 $transcoded_1080p_file ".'2>&1';
						$html .= "Generating MPEG4 (1080)\n<br>";
						$pass = 0;
						$output_start_time = date("Y-m-d H:i:s");
						try{
						$op = shell_exec($cmd);
error_log("**********************************************".PHP_EOL);
error_log("FFMPEG 1080P CMD -----> $cmd".PHP_EOL);
error_log("**********************************************".PHP_EOL);
error_log("OUTPUT OF FFMPEG 1080P CMD -----> $op".PHP_EOL);
error_log("**********************************************".PHP_EOL);
						$pass = 1;
						// echo $driver->command($command);
						}
						catch(Exception $e){$pass = 0; $errstr = $e->getMessage();}
				
						//Put to S3 here...
						$message_data = array (
						"s3file_name"=>$original_file_name.'.mp4',
											"file"=>$HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4',
						"user_id"=>$this->user_id,
						"media_id"=>"placeholder",
						"content_type"=>"video/mp4", //specific to mp4 for aws metadata
						"s3path"=>$this->user_id.'/media/1080p/',
								);
								$this->memreas_aws_transcoder->s3videoUpload($message_data);
				
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
				}				
				
				
//error_log("Finished transcoding for each type....".PHP_EOL);
				$metadata = array(
				  "1080p"=>$p1080arr,
				  "Web"=>$webarr,
				);
				$json_metadata = json_encode($metadata);				

//error_log("Finished transcoding for each json below....".PHP_EOL);
//error_log($json_metadata.PHP_EOL);

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
				 	
				$html .= '<pre>
				Output:
					{
					"S3_files": {
						"path":
							"'.$_SERVER['SERVER_NAME'].'/'.$WebHome.$DestinationDirectory.'",
						"Full":
							"'.$_SERVER['SERVER_NAME'].'/'.$WebHome.$DestinationDirectory.'",
						"1080p":
							"'.$WebHome.$DestinationDirectory.$p1080.$NewVideoName.'p1080.mp4",
						"1080p_thumbails": [
							'.$tnstring.'
						],
						"web": "'.$WebHome.$DestinationDirectory.$webm.$NewVideoName.'.web",
						"web_thumbnails": [
							'.$tnstring.'
						]
					},
					"local_filenames": {
						"device": {
							"unique_device_identifier1":
							"'.$identifier.'"
						}
					},
					"type": {
						"video": {
							"format": "mp4"
						}
					}
					}
							</pre>';

				//echo $html;
				//echo '{ "files": [ { "url": "http://url.to/file/or/page", "thumbnail_url": "http://url.to/thumnail.jpg ", "name": "thumb2.jpg", "type": "image/jpeg", "size": 46353, "delete_url": "http://url.to/delete /file/", "delete_type": "DELETE" } ] }';
				//error_log( '{"files":[{"url":"'.$DestinationDirectory.$NewVideoName.'","thumbnailUrl":['.$tnstring.'],"name":"'.$NewVideoName.'","type":"'.$VideoFileType.'","size":"'.$filesize.'","deleteUrl":"","deleteType":"","webm":"'.$ConvertedDirectory.$webm.$NewVideoName.'.webm","webmsize":'.filesize($HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.webm').',"web":"'.$ConvertedDirectory.$web.$NewVideoName.'web.mp4","websize":'.filesize($HomeDirectory.$ConvertedDirectory.$web.$NewVideoName.'web.mp4').'}]}');
				//echo '{"files":[{"url":"'.$DestinationDirectory.$NewVideoName.'","thumbnailUrl":['.$tnstring.'],"name":"'.$NewVideoName.'","type":"'.$VideoFileType.'","size":"'.$filesize.'","deleteUrl":"","deleteType":"","webm":"'.$ConvertedDirectory.$web.$NewVideoName.'.webm","webmsize":'.filesize($HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.webm').',"web":"'.$ConvertedDirectory.$web.$NewVideoName.'web.mp4","websize":'.filesize($HomeDirectory.$ConvertedDirectory.$web.$NewVideoName.'web.mp4').'}]}';

				//Update the media table entry here
				$now = date('Y-m-d H:i:s');
				$memreas_media = $memreas_transcoder_tables->getMediaTable()->getMedia($media_id);
				$memreas_media->exchangeArray(array(
							'metadata' => $json_metadata, 
							'update_date' => $now, 
						));
				$media_id = $memreas_transcoder_tables->getMediaTable()->saveMedia($memreas_media);

error_log("Just updated $media_id" . PHP_EOL);

				//Delete the temp dir if we got this far...
				try{
					$result = $this->rmWorkDir($HomeDirectory);
				}
				catch(Exception $e){
					$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);
					$result = $this->rmWorkDir($HomeDirectory);
				}
				exit();

			} // End if(isset($_POST))
		} catch (Exception $e) {
			error_log( 'Caught exception: '.  $e->getMessage() . PHP_EOL);
			echo 'Caught exception: ',  $e->getMessage(), "\n";
			//Always delete the temp dir...
			$result = $this->rmWorkDir($HomeDirectory);
		}


	
	
	}
}


/*
 * Samples from prior coding 
 */
// 				/*
// 				 * TODO: HLS still not working
// 				 */

// 				////////////////////////
// 				// hls section -
// 				////////////////////////
// 				if (isset($_POST['encoding_hls']) || (!$isUpload)) {

// 					//////////////////////////
// 					// MPEG-2 section for HLS
// 					//////////////////////////
// 					//$ ffmpeg -i video.VOB -target ntsc-dvd -q:a 0 -q:v 0 output.mpg
// 					$transcoded_mp2_file = $HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.mp2';

// 					$command =array_merge(
// 							array(
// 									'-i',
// 									$DestRandVideoName,
// 									'-target', 'ntsc-dvd',
// 									'-q:a', '0',
// 									'-q:v', '0'
// 							),

// 							//'-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),
// 							//$ae,
// 							//$customParams,
// 							array($transcoded_mp2_file,'2>&1')
// 					);
	
// 					$cmd = join(" ",$command);
// 					$cmd = $ffmpegcmd ." ".$cmd;
// 					$html .= "Generating MPEG-2\n<br>\n\n";
// 					$pass = 0;
// 					$output_start_time = date("Y-m-d H:i:s");
// 					try{
// 						$op = shell_exec($cmd);
// 						error_log("**********************************************".PHP_EOL);
// 						error_log("FFMPEG MPEG-2 CMD -----> $cmd".PHP_EOL);
// 						error_log("**********************************************".PHP_EOL);
// 						error_log("OUTPUT OF FFMPEG WEBM CMD -----> $op".PHP_EOL);
// 						error_log("**********************************************".PHP_EOL);
// 						$pass = 1;
// 					}
// 					catch(Exception $e){$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);}
	
	
// 					// ffmpeg -y -i 720/sintel_trailer_2k_%4d.png -i sintel_trailer-audio.flac -c:a libvo_aacenc -ac 1 -b:a 32k -ar 22050 -c:v libx264 -pix_fmt yuv420p -profile:v baseline -level 13 -b:v 200K -r 12 -g 36 -f hls -hls_time 10 -hls_list_size 999 -s 320x180 ts/320x180.m3u8
// 					$command =array_merge(
// 								array(
// 										'-re',
// 										'-i',
// 										$transcoded_mp2_file,
// 										'-map', '0',
// 										'-codec', 'copy',
// 										'-f', 'segment',
// 										'-segment_list', $HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'.m3u8',
// 										'-segment_list_flags', '+live',
// 										'-segment_time', '10',
// 										$HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'%03d.ts',
// 										'2>&1'
// 								)

// /*Original
// 										'-i',
// 										$DestRandVideoName,
// 										'-vcodec', 'copy',
// 										'-pix_fmt',
// 										'yuv420p',
// 										'-profile:v',
// 										'baseline',
// 										'-level',
// 										'13'
// 								),
// 								$customParams,
// 								array(
// 										'-f',
// 										'hls',
// 										'-hls_time',
// 										'10',
// 										'-hls_list_size',
// 										'999',
// 										$HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'.m3u8',
// 										'2>&1'
// 								)
// */


// 					);
// 					$cmd = join(" ",$command);
// 					$cmd = $ffmpegcmd ." ".$cmd;
// 					$html .= "Generating HLS \n<br>\n\n";
// 					$pass = 0;
// 					$output_start_time = date("Y-m-d H:i:s");
// 					try{
// 						$op = shell_exec($cmd);
// error_log("**********************************************".PHP_EOL);
// error_log("FFMPEG HLS CMD -----> $cmd".PHP_EOL);
// error_log("**********************************************".PHP_EOL);
// error_log("OUTPUT OF FFMPEG HLS CMD -----> $op".PHP_EOL);
// error_log("**********************************************".PHP_EOL);
// 						//echo "$cmd<br><br><br>";
// 						//echo "$op<br><br><br>";
// 						$pass = 1;
// 						// echo $driver->command($command);
// 					}
// 					catch(Exception $e){$pass = 0; $errstr = $e->getMessage();}
// 					$fs = 0;
// 					foreach(glob($HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'*.ts') as $filename){
// 						$fs += filesize($filename);
// 						$short_filename = explode("hls/", $filename);
// 						error_log("filename ------> " . $filename . PHP_EOL);
// 						error_log("short_filename ------> " . $short_filename[1] . PHP_EOL);
// 						//Put to S3 here...
// 						$message_data = array (
// 							"s3file_name"=>$short_filename[1],
// 							"file"=>$filename,
// 							"user_id"=>$this->user_id,
// 							"media_id"=>"placeholder",
// 							"content_type"=>"video/MP2T", //specific to webm for aws metadata
// 							"s3path"=>$this->user_id.'/media/hls/',
// 						);
// 						$this->memreas_aws_transcoder->s3videoUpload($message_data);
// 					}

// 					//Put to S3 here...
// 					$message_data = array (
// 						"s3file_name"=>$original_file_name.'.m3u8',
// 						"file"=>$HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'.m3u8',
// 						"user_id"=>$this->user_id,
// 						"media_id"=>"placeholder",
// 						"content_type"=>"application/x-mpegURL", //specific to webm for aws metadata
// 						"s3path"=>$this->user_id.'/media/hls/',
// 					);
// 					$this->memreas_aws_transcoder->s3videoUpload($message_data);

// 					$hlsarr = array(
// 						"ffmpeg_cmd"=>$cmd,
// 						"ffmpeg_cmd_output"=>$op,
// 						"output_size"=>$fs+filesize($HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'.m3u8'),
// 						"pass_fail"=>$pass,
// 						"error_message"=>$errstr,
// 						"output_start_time"=>$output_start_time,
// 						"output_end_time"=>date("Y-m-d H:i:s"),
// 					);
// 					$html .= '<a href="/'.$WebHome.$ConvertedDirectory.$hls.$NewVideoName.'.m3u8'.'" alt="Thumbnail">HLS</a><br>';
// 				}
// ////////////////////////
// // webm section
// ////////////////////////
// if (isset($_POST['encoding_webm']) || (!$isUpload)) {

// 	$command =array_merge(
// 			array(
// 					'-i',
// 					$DestRandVideoName,
// 					'-c:v', 'libvpx',
// 					'-b:v', '1M',
// 					'-c:a', 'libvorbis'
// 			),
				
// 			//'-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),
// 			//$ae,
// 			//$customParams,
// 			array($HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.webm','2>&1')
// 	);

// 	$cmd = join(" ",$command);
// 	$cmd = $ffmpegcmd ." ".$cmd;
// 	$html .= "Generating Webm\n<br>\n\n";
// 	$pass = 0;
// 	$output_start_time = date("Y-m-d H:i:s");
// 	try{
// 		$op = shell_exec($cmd);
// 		error_log("**********************************************".PHP_EOL);
// 		error_log("FFMPEG WEBMM CMD -----> $cmd".PHP_EOL);
// 		error_log("**********************************************".PHP_EOL);
// 		error_log("OUTPUT OF FFMPEG WEBM CMD -----> $op".PHP_EOL);
// 		error_log("**********************************************".PHP_EOL);
// 		//echo "$cmd<br><br><br>";
// 		//echo "$op<br><br><br>";
// 		$pass = 1;
// 		// echo $driver->command($command);
// 	}
// 	catch(Exception $e){$pass = 0; $errstr = $e->getMessage(); error_log("error string ---> " . $errstr . PHP_EOL);}

// 	//Put to S3 here...
// 	$message_data = array (
// 	"s3file_name"=>$original_file_name.'.webm',
// 	"file"=>$HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.webm',
// 	"user_id"=>$this->user_id,
// 	"media_id"=>"placeholder",
// 	"content_type"=>"video/ogg", //specific to webm for aws metadata
// 						"s3path"=>$this->user_id.'/media/webm/',
// 	);
// 	$this->memreas_aws_transcoder->s3videoUpload($message_data);

// 	$webmarr = array(
// 			"ffmpeg_cmd"=>$cmd,
// 			"ffmpeg_cmd_output"=>$op,
// 			"output_size"=>filesize($HomeDirectory.$ConvertedDirectory.$webm.$NewVideoName.'.webm'),
// 			"pass_fail"=>$pass,
// 			"error_message"=>$errstr,
// 			"output_start_time"=>$output_start_time,
// 			"output_end_time"=>date("Y-m-d H:i:s"),
// 					);
// 					$html .= '<a href="/'.$WebHome.$ConvertedDirectory.$webm.$NewVideoName.'.webm'.'" alt="Thumbnail">Webm</a><br>';
// }

// $html .= "Completed <br>";

// $tnstring = join("\",\n		\"",$tns);
// 				$tnstring = '"'.$tnstring.'"';

// 				$tsstring;

// 	$ts;
// 	if (isset($_POST['encoding_hls']) || (!$isUpload)) {
// 			foreach(glob($HomeDirectory.$ConvertedDirectory.$hls.$NewVideoName.'*.ts') as $filename){
// 			$ts[] = $WebHome.$DestinationDirectory.$hls.basename($filename);
// 	}

// 	$tsstring = join("\",\n		\"",$ts);
// 		$tsstring = '"'.$tsstring.'"';
// 	}

// 	$transcode_end_time = date("Y-m-d H:i:s");
			
// ////////////////////////
// // 1080p section
// ////////////////////////
// if (isset($_POST['encoding_1080']) || (!$isUpload)) {
// 	$customParams = '';

// 	$customParams[]  = '-vf';
// 	$customParams[]  = '"scale=640:360"'; // Format the video size

// 	$customParams[]  = '-b:v';
// 	$customParams[]  = '700k'; // Format the video bit rate

// 	$customParams[]  = '-r';
// 	$customParams[]  = $videoFramerate;	// Format the video frame rate

// 	if ($videoDeinterlace) {
// 		$customParams[] = '-deinterlace ';	// Deinterlace the video
// 	}
// 	if ($audioEnabled) {
// 		$customParams[]  = '-ar';
// 		$customParams[]  = $audioSamplerate;	// Audio sample rate
			
// 		$customParams[]  = '-ab';
// 		$customParams[]  = $audioBitrate.'k';	// Audio bit rate
			
// 		$customParams[]  = '-ac';
// 		$customParams[]  = $audioChannels;	// Audio Channels
// 		$ae = '';
// 		$ae[] = '-acodec';
// 		$ae[] =	'libfaac';
// 	}
// 	else
// 	{
// 		$customParams[]  = '-an'; // Disable audio
// 	}


// 	$customParams[]  = '-f';
// 	$customParams[]  = 'mp4';
// 	//'-vcodec', 'mpeg2video'
// 	//$command =array_merge(array('-y', '-i',$DestRandVideoName,'-c:v', 'libx264', '-preset', 'ultrafast', '-qp', '0','-movflags', 'frag_keyframe+empty_moov'),$ae,$customParams,array($HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'p1080.mp4','2>&1'));

// 	//https://wiki.archlinux.org/index.php/FFmpeg#Encoding_examples
// 	// ffmpeg -i video.mpg -acodec libvorbis -aq 8 -ar 48000 -vcodec mpeg4 -pass 2 -vpre vhq -b 3000k output.mp4
// 	$transcoded_1080p_file = $HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4';
// 	$command =array_merge(
// 			array(
// 					'-i',
// 					$DestRandVideoName,
// 					//'-vcodec', 'mpeg4',
// 					//'-c:v', 'mpeg4',
// 					//'-vtag', 'xvid',
// 					'-vcodec', 'mpeg4',
// 					'-acodec', 'libvorbis',
// 	'-aq', '8',
// 					'-ar', '48000',
// 					'-pass', '2',
// 					'-b:v', '3000k',

// 					//'-fpre', '/memreas_ffmpeg_install/ffmpeg_build/share/ffmpeg/libavcodec-vhq.ffpreset',
// 					//'-vpre', 'vhq',
// 	//Preset data
// 	'-vtag', 'DX50',
// 	'-mbd', '2',
// 	'-trellis', '2',
// 	'-flags', '+cbp+mv0',
// 	'-pre_dia_size', '4',
// 	'-dia_size', '4',
// 					'-precmp', '4',
// 					'-cmp', '4',
// 					'-subcmp', '4',
// 					'-preme', '2',
// 					'-quantizer_noise_shaping', '2',
						
// 			),
				
// 									//'-vsync', '1', '-bt', '50k','-movflags', 'frag_keyframe+empty_moov'),
// 									//$ae,
// 									//$customParams,
// 									array($transcoded_1080p_file,'2>&1')
				
// 					);

// 											$cmd = join(" ",$command);
// 											$cmd = $ffmpegcmd ." ".$cmd;
// 	$html .= "Generating MPEG4 (1080)\n<br>";
// 	$pass = 0;
// 	$output_start_time = date("Y-m-d H:i:s");
// 	try{
// 	$op = shell_exec($cmd);
// 			//echo "$cmd<br><br><br>";
// 	//echo "$op<br><br><br>";
// 	error_log("**********************************************".PHP_EOL);
// error_log("FFMPEG 1080P CMD -----> $cmd".PHP_EOL);
// error_log("**********************************************".PHP_EOL);
// 	error_log("OUTPUT OF FFMPEG 1080P CMD -----> $op".PHP_EOL);
// 	error_log("**********************************************".PHP_EOL);
// 	$pass = 1;
// 	// echo $driver->command($command);
// 	}
// 	catch(Exception $e){$pass = 0; $errstr = $e->getMessage();}

// 	//Put to S3 here...
// 	$message_data = array (
// 	"s3file_name"=>$original_file_name.'.mp4',
// 						"file"=>$HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4',
// 	"user_id"=>$this->user_id,
// 	"media_id"=>"placeholder",
// 	"content_type"=>"video/mp4", //specific to mp4 for aws metadata
// 	"s3path"=>$this->user_id.'/media/1080p/',
// 			);
// 			$this->memreas_aws_transcoder->s3videoUpload($message_data);

// 			$p1080arr = array(
// 			"ffmpeg_cmd"=>$cmd,
// 			"ffmpeg_cmd_output"=>$op,
// 			"output_size"=>filesize($HomeDirectory.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4'),
// 			"pass_fail"=>$pass,
// 			"error_message"=>$errstr,
// 					"output_start_time"=>$output_start_time,
// 					"output_end_time"=>date("Y-m-d H:i:s"),
// 			);

// 			$html .= '<a href="/'.$WebHome.$ConvertedDirectory.$p1080.$NewVideoName.'.mp4'.'" alt="Thumbnail">MPEG 1080</a><br>';
// }

?>
