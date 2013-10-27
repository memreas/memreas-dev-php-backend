<?php
namespace memreas;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;
use memreas\RmWorkDir;
use memreas\UUID;

error_reporting(E_ALL & ~E_NOTICE);

class AWSManagerReceiver {

    private $aws = null;
    private $s3 = null;
    private $bucket = null;
    private $sns = null;
    private $topicArn = null;
    private $awsTranscode = null;
    private $service_locator = null;
    private $dbAdapter = null;
    private $temp_job_uuid_dir = null;

    public function __construct($service_locator) {
        //print "In AWSManagerReceiver constructor <br>";
        error_log("Inside AWSManagerReceiver contructor..." . PHP_EOL);

		try {
			$this->service_locator = $service_locator;
			$this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
			//UUID::getInstance($this->dbAdapter); //construct the UUID singleton...
			//$this->dbAdapter = $service_locator->get('memreasbackenddb');
			
			$this->aws = Aws::factory(array(
						'key' => 'AKIAJMXGGG4BNFS42LZA',
						'secret' => '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',
						'region' => 'us-east-1'
			));

			//Fetch the S3 class
			$this->s3 = $this->aws->get('s3');

			//Fetch the AWS Elastic Transcoder class
			$this->awsTranscode = $this->aws->get('ElasticTranscoder');

			//Set the bucket
			$this->bucket = "memreasdev";

			//Fetch the SNS class
			$this->sns = $this->aws->get('sns');

			//Set the topicArn
			$this->topicArn = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
		} catch (Exception $e) {
 		   error_log('Caught exception: ' . $e->getMessage() . PHP_EOL);
		}

        error_log("Exit AWSManagerReceiver constructor", 0);
        //print "Exit AWSManagerReceiver constructor <br>";
    }

    function snsProcessMediaSubscribe($message_data) {
    
    	try {

	error_log("Inside snsProcessMediaSubscribe ..." . PHP_EOL);            
			if ($message_data['isVideo']) {
				//Transcode, fetch thumbnail and resize as needed
				$result = $this->awsTranscodeExec($message_data);
			} 
			else {
	error_log("Inside snsProcessMediaSubscribe else ..." . PHP_EOL);            
	error_log("Inside snsProcessMediaSubscribe  else message_data ---> " . print_r($message_data,true). PHP_EOL);            
				//Fetch image and create thumbnails
				$s3file_name = $message_data['s3file_name'];
				$user_id = $message_data['user_id'];
				$media_id = $message_data['media_id'];
				$content_type = $message_data['content_type'];
				$s3path = $message_data['s3path'];
				//END
	error_log("Inside snsProcessMediaSubscribe set variables..." . PHP_EOL);            

				//Saving image ugh - need to find way to not write to disk....
				//Create a temp job dir to create the thumbnails...
				//$temp_job_uuid_dir = UUID::getInstance()->fetchUUID();
				$this->temp_job_uuid_dir = date("Y.m.d") . '_' . uniqid();
	error_log("Inside snsProcessMediaSubscribe temp_job_uuid_dir ----> " . $this->temp_job_uuid_dir . PHP_EOL);            
				$dirPath = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir . MemreasConstants::IMAGES_PATH;
	error_log("Inside snsProcessMediaSubscribe dirPath ----> " . $dirPath . PHP_EOL);            
				if (!file_exists($dirPath)) {
					mkdir($dirPath, 0777, true);
				}
	error_log("Inside snsProcessMediaSubscribe dirPath ----> $dirPath" . PHP_EOL);            
				$file = $dirPath . $s3file_name;
				$s3file = $s3path . $s3file_name;
	//            echo "s3file=$s3file<br/>file=$file<br/>dirpath=$dirPath<br/>s3path=$s3path<br/>s3path=$s3path<br>content=$content_type<br/>file_name=$s3file_name";
	error_log("About to fetch from S3" . PHP_EOL);            
	error_log("About to fetch from S3 -> MemreasConstants::S3BUCKET " . MemreasConstants::S3BUCKET  . PHP_EOL);            
	error_log("About to fetch from S3 -> s3file " . $s3file  . PHP_EOL);            
	error_log("About to fetch from S3 -> file " . $file  . PHP_EOL); 
				try {
	error_log("Inside try"  . PHP_EOL); 
					$result = $this->s3->getObject(array(
						'Bucket' => MemreasConstants::S3BUCKET,
						'Key' => $s3file,
						'SaveAs' => $file
					));
					chmod($file, 0777);
	error_log("Inside try - just got file..."  . PHP_EOL); 
	error_log("Inside try - result ---> ..." . print_r($result, true) . PHP_EOL); 
	error_log("Inside try - is the result above?????..."  . PHP_EOL); 
					
				} catch(Aws\S3\Exception\S3Exception $e) {
				    error_log("Caught S3 exception: $e->getMessage()" . PHP_EOL);
				    throw $e;
				}
				
				
	error_log("Fetched from S3 -> file " . $file . PHP_EOL);            
				
	error_log("Inside snsProcessMediaSubscribe dirPath ----> $dirPath" . PHP_EOL);            
	error_log("Inside snsProcessMediaSubscribe file ----> $file" . PHP_EOL);            
	error_log("Inside snsProcessMediaSubscribe s3file ----> $s3file" . PHP_EOL);            
	error_log("Inside snsProcessMediaSubscribe result ----> $result" . PHP_EOL);            

				//Use the s3upload code to resize and load to s3		
				$paths = $this->s3upload($user_id, $dirPath, $media_id, $s3file_name, $content_type, $file);

				////////////////////////////////////////////////////////////////////
				//Retrieve media entry here...
				$media = $this->dbAdapter->find('Application\Entity\Media', $media_id);
				$metadata = json_decode($media->metadata, true);
	error_log("Inside snsProcessMediaSubscribe metadata ----> $metadata" . PHP_EOL);            
				$metadata['S3_files']['79x80'] = $paths['79x80_Path'];
				$metadata['S3_files']['448x306'] = $paths['448x306_Path'];
				$metadata['S3_files']['98x78'] = $paths['98x78_Path'];
				$json = json_encode($metadata);
	error_log("Inside snsProcessMediaSubscribe json ----> $json" . PHP_EOL);            
				$media->metadata = $json;
	error_log("Inside snsProcessMediaSubscribe metadata ----> $metadata" . PHP_EOL);            
				$this->dbAdapter->persist($media);
				$this->dbAdapter->flush();

				//Remove the work directory
				$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir;
				$dirRemoved = new RmWorkDir($dir);
			}
	        return $result;
        
		} catch (Exception $e) {
		    error_log("Caught exception: $e->getMessage()" . PHP_EOL);
		}
    }

    function fetchResizeUpload($message_data, $job_dir, $s3file, $s3output_path, $height, $width) {

        error_log("Inside fetchResizeUpload for $height" . "x" . "$width");
        //Fetch image and create thumbnails
        //S3 media details
        $user_id = $message_data['user_id'];
        $media_id = $message_data['media_id'];
        $content_type = $message_data['content_type'];
        $s3path = $message_data['s3path'];
        $s3file_name = $message_data['s3file_name'];

        //Local server data
        //Saving image ugh - need to find way to not write to disk....
        $dirPath = getcwd() . "/data/" . $user_id . "/media/";
        //$dirPath = $job_dir;
        //if (!file_exists($dirPath)) {
		//    mkdir($dirPath, 0777, true);
		//}
        $splitter = explode("thumbnail/", $s3file);
error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $thumbnail_name = $splitter[1];
        $splitter = explode($thumbnail_name, $s3file);
error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $path = $splitter[0];
        $thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;
error_log("Inside fetchResizeUpload - thumbnail_file --> " . $thumbnail_file);                	

        //$file = $dirPath . $thumbnail_name;
        $file = $job_dir . $thumbnail_name;
error_log("Inside fetchResizeUpload - about to get " . $s3file);                	
error_log("Inside fetchResizeUpload - about to save locally as " . $file);                	
        $result = $this->s3->getObject(array(
            'Bucket' => MemreasConstants::S3BUCKET,
            'Key' => $s3file,
            'SaveAs' => $file
        ));

        /////////////////////////////////////////////////////////////////////
        //Resize images - section
        $layer = ImageWorkshop::initFromPath($file);
        //$layer->resizeInPixel($height, $width, true, 0, 0, 'MM');  //Maintains image
        $layer->resizeInPixel($height, $width);

        //Saving image ugh
        //$dirPath = getcwd() . "/data/" . $user_id . "/media/" . $height . "x" . $width . "/";
        $dirPath = getcwd() . "/data/" . $user_id . "/media/" . $height . "x" . $width . "/";
        $job_sub_dir = $job_dir . $height . "x" . $width . "/";
        if (!file_exists($job_sub_dir)) {
		    mkdir($job_sub_dir, 0777, true);
		}

        $createFolders = true;
        $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
        $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
        $layer->save($job_sub_dir, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality);
        //$file = $dirPath . $thumbnail_name;
        $file = $job_sub_dir . $thumbnail_name;

        $body = EntityBody::factory(fopen($file, 'r+'));
//error_log("Inside fetchResizeUpload - thumbnail_file is  --> " . $thumbnail_file);                	
        /////////////////////////////////////////////////////////////////////
        //Upload images - section
        $uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket(MemreasConstants::S3BUCKET)
                ->setMinPartSize(10 * Size::MB)
                ->setOption('ContentType', $content_type)
                ->setKey($thumbnail_file)
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $uploader->upload();
            //error_log( "Upload complete.\n", 0);
        } catch (MultipartUploadException $e) {
            $uploader->abort();
            //error_log( "Upload failed.\n", 0);
        }

        error_log("thumbnail_file ----> " . $thumbnail_file);
        return $thumbnail_file;
    }

    function awsTranscodeExec($message_data) {
        //http://docs.aws.amazon.com/elastictranscoder/latest/developerguide/create-job.html
        error_log("Inside awsTranscode ...", 0);

		try {
			$user_id = $message_data['user_id'];
			$media_id = $message_data['media_id'];
			$content_type = $message_data['content_type'];
			$s3path = $message_data['s3path'];
			$s3file_name = $message_data['s3file_name'];
			$isVideo = $message_data['isVideo'];
			$email = $message_data['email'];

			$input_file = $s3path . $s3file_name;
			$web_output_file = $s3path . "web/" . $s3file_name;
			$web_file_thumb = $s3path . 'web/thumbnail/' . $s3file_name . "-{count}";
			$_1080p_output_file = $s3path . "1080p/" . $s3file_name;
			$_1080p_file_thumb = $s3path . '1080p/thumbnail/' . $s3file_name . "-{count}";
			$hls_output_file = $s3path . "hls/" . $s3file_name;
			$hls_file_thumb = $s3path . 'hls/thumbnail/' . $s3file_name . "-{count}";
			$hls_name_file = $s3path . "hls/" . $s3file_name . '.m3u8';

			$result = $transcode_request = $this->awsTranscode->createJob(
					array(
						'PipelineId' => '1370361326621-wu3cce',
						'Input' => array(
							'Key' => $input_file,
							'FrameRate' => 'auto',
							'Resolution' => 'auto',
							'AspectRatio' => 'auto',
							'Interlaced' => 'auto',
							'Container' => 'auto'
						),
						//'OutputKeyPrefix' => 'a/ws_',
						'Outputs' =>
						array(
							array(
								'Key' => $_1080p_output_file,
								'ThumbnailPattern' => $_1080p_file_thumb,
								'Rotate' => '0',
								'PresetId' => '1351620000001-000001'
							),
							array(
								'Key' => $hls_output_file,
								'ThumbnailPattern' => $hls_file_thumb,
								'Rotate' => '0',
								'SegmentDuration' => '5',
								'PresetId' => '1351620000001-200010'
							),
							array(
								'Key' => $web_output_file,
								'ThumbnailPattern' => $web_file_thumb,
								'Rotate' => '0',
								'PresetId' => '1351620000001-100070'
							),
						),
	//						'Playlists' => array(
	//							array(
	//								//'Name' => $hls_name_file,
	//								'Name' => 'hls_name.m3u8',
	//								'Format' => 'HLSv3',
	//								'OutputKeys' => array ( '1024k' )
	//							)
	//						)
			));



			if (isset($result['Job']['Id'])) {
				while (
				($result['Job']['Outputs']['0']['Status'] != 'Complete') ||
				($result['Job']['Outputs']['1']['Status'] != 'Complete') ||
				($result['Job']['Outputs']['2']['Status'] != 'Complete')
				) {
					if ($result['Job']['Output']['Status'] == 'Error') {
	//                    echo "<pre>";print_r($result['Job']);exit;
						die("TRANSCODE ERROR: Job Id failed!");
					}
					error_log("About to sleep for 30...");
					sleep(30);
					//get the job and check the status
					$result = $this->awsTranscode->readJob(array('Id' => $result['Job']['Id']));
					error_log("*******************************");
					error_log("1080P - Job Output Status -----------> " . $result['Job']['Outputs']['0']['Status']);
					error_log("HLS - Job Output Status -----------> " . $result['Job']['Outputs']['1']['Status']);
					error_log("WEB - Job Output Status -----------> " . $result['Job']['Outputs']['2']['Status']);
				}

				//Create a temp directory for the images 
				//$this->temp_job_uuid_dir = UUID::getUUID($this->dbAdapter);
				$this->temp_job_uuid_dir = date("Y.m.d") . '_' . uniqid();
				$dirPath = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir . MemreasConstants::MEDIA_PATH;
				if (!file_exists($dirPath)) {
					mkdir($dirPath, 0777, true);
				}

				//Time to fetch files and store in metadata
				// It's completed ... get list object...
				////////////////////////////////////////////////////////////////////
				//Fetch the metadata object
				//$query = "SELECT metadata FROM media WHERE media_id = '$media_id'";
				//$result = mysql_query($query) or die("SELECT FROM MEDIA FAILED");
				//$row = mysql_fetch_array($result);
				//$metadata = json_decode($row['metadata'], true);
			
	error_log("About to find media entry by media_id -----------> $media_id" . PHP_EOL);
				$media = $this->dbAdapter->find('Application\Entity\Media', $media_id);
				$metadata = json_decode($media->metadata, true);
	error_log("Found media entry by media_id -----------> $media_id" . PHP_EOL);
	error_log("metadata before metadata -----------> " . $media->metadata . PHP_EOL);

	error_log("MemreasConstants::S3BUCKET ---> ". MemreasConstants::S3BUCKET . PHP_EOL);
	error_log("About to fetch ". $s3path . '1080p/' . PHP_EOL);



				////////////////////////////////////////////////////////////////////
				//Fetch the list of files from S3 and add to metadata
				$_1080p_thumbnails = array();
				$objectsIterator = $this->s3->getIterator('ListObjects', array(
					'Bucket' => MemreasConstants::S3BUCKET,
					'Prefix' => $s3path . '1080p/'
				));
	error_log("Fetched ". $s3path . '1080p/' . PHP_EOL);
				foreach ($objectsIterator as $object) {
	error_log("_1080p_thumbnails object ----> " . print_r($object, true) . PHP_EOL);
					if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
						$_1080p_thumbnails[]['Full'] = $object['Key'];
						$s3output_path = $s3path . "1080p/thumbnail/79x80/";
						$_1080p_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 79, 80);
						$s3output_path = $s3path . "1080p/thumbnail/448x306/";
						$_1080p_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 448, 306);
						$s3output_path = $s3path . "1080p/thumbnail/98x78/";
						$_1080p_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 98, 78);
					} else {
						$metadata['S3_files']['1080p'] = $object['Key'];
					}
				}
				$metadata['S3_files']['1080p_thumbails'] = $_1080p_thumbnails;
	error_log("set 1080p thumnails" . json_encode($metadata['S3_files']['1080p_thumbails']). PHP_EOL);


	error_log("About to fetch ". $s3path . 'hls/' . PHP_EOL);
				$hls_thumbnails = array();
				$hls_ts = array();
				$objectsIterator = $this->s3->getIterator('ListObjects', array(
					'Bucket' => MemreasConstants::S3BUCKET,
					'Prefix' => $s3path . 'hls/'
				));
				foreach ($objectsIterator as $object) {
					if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
						$hls_thumbnails[]['Full'] = $object['Key'];
						$s3output_path = $s3path . "hls/thumbnail/79x80/";
						$hls_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 79, 80);
						$s3output_path = $s3path . "hls/thumbnail/448x306/";
						$hls_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 448, 306);
						$s3output_path = $s3path . "hls/thumbnail/98x78/";
						$hls_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 98, 78);
					} else if (strpos($object['Key'], '.ts') !== false) {
						$hls_ts[] = $object['Key'];
					} else {
						$metadata['S3_files']['hls'] = $object['Key'];
					}
					$metadata['S3_files']['hls_thumbnails'] = $hls_thumbnails;
					$metadata['S3_files']['hls_ts'] = $hls_ts;
				}
	error_log("set hls_thumbnails" . json_encode($metadata['S3_files']['hls_thumbnails']). PHP_EOL);
	error_log("set hls_ts" . json_encode($metadata['S3_files']['hls_ts']). PHP_EOL);


	error_log("About to fetch ". $s3path . 'web/' . PHP_EOL);
				$web_thumbnails = array();
				$objectsIterator = $this->s3->getIterator('ListObjects', array(
					'Bucket' => MemreasConstants::S3BUCKET,
					'Prefix' => $s3path . 'web/'
				));
				foreach ($objectsIterator as $object) {
					if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
						$web_thumbnails[]['Full'] = $object['Key'];
						$s3output_path = $s3path . "web/thumbnail/79x80/";
						$web_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 79, 80);
						$s3output_path = $s3path . "web/thumbnail/448x306/";
						$web_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 448, 306);
						$s3output_path = $s3path . "web/thumbnail/98x78/";
						$web_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 98, 78);
					} else {
						$metadata['S3_files']['web'] = $object['Key'];
					}
					$metadata['S3_files']['web_thumbnails'] = $web_thumbnails;
				}
	error_log("set web_thumbnails" . json_encode($metadata['S3_files']['web_thumbnails']). PHP_EOL);

				////////////////////////////////////////////////
				//Update the database with the updated metadata
				$now = date('Y-m-d H:i:s');
				$json = json_encode($metadata);
	error_log("About to set metadata ---> " . $json . PHP_EOL);
				$media->metadata = $json;
				$media->update_date = $now;
				$this->dbAdapter->persist($media);
				$this->dbAdapter->flush();

	//            $json = json_encode($metadata);
	//            error_log("metadata after -----------> " . $json);
	//            $query = "UPDATE media SET metadata = '$json' WHERE media_id = '$media_id'";
	//            $result = mysql_query($query) or die("UPDATE MEDIA FAILED");

			} else {
				die("TRANSCODE ERROR: Job Id is not set!");
			}

			//Remove the work directory
			$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir;
			$dirRemoved = new RmWorkDir($dir);

			return $result;
		} catch (Exception $e) {
		    error_log("Caught exception: $e->getMessage()" . PHP_EOL);
			//Remove the work directory
			$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir;
			$dirRemoved = new RmWorkDir($dir);
		}
        error_log("Exit awsTranscode ...", 0);
    }

    function s3upload($user_id, $temp_job_uuid_dir, $media_id, $s3file_name, $content_type, $file, $isVideo = false) {

        error_log("Enter s3upload");

        $s3_media_folder = null;
        $s3_media_path = null;
        $image79x80 = null;
        $image448x306 = null;
        $image98x78 = null;
        $paths = array();


        $metadata = null;
		/////////////////////////////////////////////////////////////////////
		//Resize images - 79x80 section
		$layer = ImageWorkshop::initFromPath($file);
		//$layer->resizeInPixel(79, 80, true, 0, 0, 'MM');  //Maintains image
		$layer->resizeInPixel(79, 80);

		//Saving image ugh
		//$dirPath = dirname(__DIR__) . "/media/79x80/";

		// dirPath = /data/temp_uuid/media/userimage/
		//$this->temp_job_uuid_dir = UUID::getInstance()->fetchUUID();
		//$this->temp_job_uuid_dir = date("Y.m.d") . '_' . uniqid();
		$dirPath = array();
		$dirPath['79x80'] = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir . MemreasConstants::IMAGES_PATH . '79x80/';
		$dirPath['98x78'] = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir . MemreasConstants::IMAGES_PATH . '98x78/';
		$dirPath['448x306'] = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir . MemreasConstants::IMAGES_PATH . '448x306/';

		foreach ($dirPath as $key => $value) {
			if (!file_exists($value)) {
				mkdir($value, 0777, true);
			}
		}

		//delete me later...
		//if (!file_exists($dirPath)) {
		//	mkdir($dirPath, 0777, true);
		//}

		//$dirPath = getcwd() . "/data/media/79x80/";
		$createFolders = true;
		$backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
		$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
		$layer->save($dirPath['79x80'], $s3file_name, $createFolders, $backgroundColor, $imageQuality);

		//S3 Folder Setup
		$s3_media_folder = "$user_id/images/79x80/";
		$s3_media_path = $s3_media_folder . $s3file_name;
		$ec2_media_path = $dirPath['79x80'] . $s3file_name;
		$body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

		$uploader = UploadBuilder::newInstance()
				->setClient($this->s3)
				->setSource($body)
				->setBucket(MemreasConstants::S3BUCKET)
				->setMinPartSize(10 * Size::MB)
				->setOption('ContentType', $content_type)
				->setKey($s3_media_path)
				->build();

		//  Modified - Perform the upload to S3. Abort the upload if something goes wrong
		try {
			$uploader->upload();
			//error_log( "Upload complete.\n", 0);
		} catch (MultipartUploadException $e) {
			$uploader->abort();
			//error_log( "Upload failed.\n", 0);
		}

		$paths['79x80_Path'] = $s3_media_path;

		error_log("79x80 PATH ----> " . $s3_media_path);

		/////////////////////////////////////////////////////////////////////
		//Resize images - 448x306 section
		$layer = ImageWorkshop::initFromPath($file);
		//$layer->resizeInPixel(448, 306, true, 0, 0, 'MM');  //Maintains image
		$layer->resizeInPixel(448, 306);

		//Saving image ugh
		//$dirPath = dirname(__DIR__) . "/media/448x306/";
		$createFolders = true;
		$backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
		$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
		$layer->save($dirPath['448x306'], $s3file_name, $createFolders, $backgroundColor, $imageQuality);

		//S3 Folder Setup
		$s3_media_folder = "$user_id/images/448x306/";
		$s3_media_path = $s3_media_folder . $s3file_name;
		$ec2_media_path = $dirPath['448x306'] . $s3file_name;
		$body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

		$uploader = UploadBuilder::newInstance()
				->setClient($this->s3)
				->setSource($body)
				->setBucket(MemreasConstants::S3BUCKET)
				->setMinPartSize(10 * Size::MB)
				->setOption('ContentType', $content_type)
				->setKey($s3_media_path)
				->build();

		//  Modified - Perform the upload to S3. Abort the upload if something goes wrong
		try {
			$uploader->upload();
			//error_log( "Upload complete.", 0);
		} catch (MultipartUploadException $e) {
			$uploader->abort();
			//error_log( "Upload failed.", 0);
		}

		$paths['448x306_Path'] = $s3_media_path;

		error_log("448x306 PATH ----> " . $s3_media_path);


		/////////////////////////////////////////////////////////////////////
		//Resize images - 98x78 section
		$layer = ImageWorkshop::initFromPath($file);
		//$layer->resizeInPixel(98, 78, true, 0, 0, 'MM');  //Maintains image
		$layer->resizeInPixel(98, 78);

		//Saving image ugh
		//$dirPath = dirname(__DIR__) . "/media/98x78/";
		$createFolders = true;
		$backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
		$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
		$layer->save($dirPath['98x78'], $s3file_name, $createFolders, $backgroundColor, $imageQuality);

		//S3 Folder Setup
		$s3_media_folder = "$user_id/images/98x78/";
		$s3_media_path = $s3_media_folder . $s3file_name;
		$ec2_media_path = $dirPath['98x78'] . $s3file_name;
		$body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

		$uploader = UploadBuilder::newInstance()
				->setClient($this->s3)
				->setSource($body)
				->setBucket(MemreasConstants::S3BUCKET)
				->setMinPartSize(10 * Size::MB)
				->setOption('ContentType', $content_type)
				->setKey($s3_media_path)
				->build();

		//  Modified - Perform the upload to S3. Abort the upload if something goes wrong
		try {
			$uploader->upload();
			error_log("Upload complete.\n", 0);
		} catch (MultipartUploadException $e) {
			$uploader->abort();
			error_log("Upload failed.\n", 0);
		}

		$paths['98x78_Path'] = $s3_media_path;
		error_log("98x78 PATH ----> " . $s3_media_path);
		
		//Delete the work directories
		$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid_dir;
		$dirRemoved = new RmWorkDir($dir);

        //return the array of paths to the image or video
        return $paths;
    }

    //Useful but not used for now...
    function getExtension($str) {
        $i = strrpos($str, ".");
        if (!$i) {
            return "";
        }
        $l = strlen($str) - $i;
        $ext = substr($str, $i + 1, $l);
        return $ext;

        //Here you can add valid file extensions. 
        $valid_formats = array("jpg", "png", "gif", "bmp", "jpeg", "PNG", "JPG", "JPEG", "GIF", "BMP");
    }

//END
}

// End class Def 
// Dump x
//ob_start();
//var_dump($transcode_request);
//$contents = ob_get_contents();
//ob_end_clean();
//error_log($contents);

/* Code to upload video
  $s3_media_folder = "$user_id/media/";
  $s3_media_path = $s3_media_folder . $s3file_name;
  error_log ("s3_media_path ------> " . $s3_media_path);
  //$metadata = array('Content-Type' => 'video/mp4');

  //'ContentType' => $Item->getMimetype()

  $uploader = UploadBuilder::newInstance()
  ->setClient($this->s3)
  ->setSource($file)
  ->setBucket(MemreasConstants::S3BUCKET)
  ->setMinPartSize(10 * Size::MB)
  ->setOption('ContentType', $content_type)
  ->setKey($s3_media_path)
  ->build();

  //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
  try {
  $uploader->upload();
  error_log( "Upload complete.\n", 0);
  $msg = "S3 Upload Successful. check aws_s3_list_files.php to see if it loaded as $s3_media_path....";
  } catch (MultipartUploadException $e) {
  $uploader->abort();
  error_log( "Upload failed.\n", 0);
  }

  $paths['Full_Path'] = $s3_media_path;

  error_log("PATH TO ----> " . $s3_media_path);
 */
?>


