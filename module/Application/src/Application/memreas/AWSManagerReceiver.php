<?php
namespace Application\memreas;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;
use Application\memreas\RmWorkDir;

error_reporting(E_ALL & ~E_NOTICE);

class AWSManagerReceiver {

    protected $aws = null;
    protected $s3 = null;
    protected $sns = null;
    protected $sqs = null;
    protected $topicArn = null;
    protected $awsTranscode = null;
    protected $service_locator = null;
    protected $dbAdapter = null;
    protected $temp_job_uuid = null;

    public function __construct($service_locator) {
        //error_log("Inside AWSManagerReceiver contructor..." . PHP_EOL);

		try {
			$this->service_locator = $service_locator;
			$this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
			
			$this->aws = Aws::factory(array(
						'key' => 'AKIAJMXGGG4BNFS42LZA',
						'secret' => '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',
						'region' => 'us-east-1'
			));

			//Fetch the S3 class
			$this->s3 = $this->aws->get('s3');

			//Fetch the AWS Elastic Transcoder class
			$this->awsTranscode = $this->aws->get('ElasticTranscoder');

			//Fetch the SNS class
			$this->sns = $this->aws->get('sns');

			//Fetch the SQS class
			$this->sqs = $this->aws->get('sqs');
			
			//Set the topicArn
			$this->topicArn = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
		} catch (Exception $e) {
 		   error_log('Caught exception: ' . $e->getMessage() . PHP_EOL);
		}

        //error_log("Exit AWSManagerReceiver constructor", 0);
        //print "Exit AWSManagerReceiver constructor <br>";
    }

    function snsProcessMediaSubscribe($message_data) {
    
    	try {
    		
//error_log("Inside snsProcessMediaSubscribe ..." . PHP_EOL);            
			if ($message_data['is_video'] || $message_data['is_audio']) {
				//Transcode, fetch thumbnail and resize as needed
				if ($message_data['memreastranscoder']) {
//error_log("Inside snsProcessMediaSubscribe message_data[memreastranscoder] ..." . $message_data['memreastranscoder']. PHP_EOL);
					$message_data['is_image'] = 0;
					$memreasTranscoder = new MemreasTranscoder($this);
					$memreas_transcoder_tables = new MemreasTranscoderTables($this->service_locator);
					$result = $memreasTranscoder->exec($message_data, $memreas_transcoder_tables, $this->service_locator, false);
				/*
				 * Legacy aws elastic transcoder code
				 */	
				//} else {
				//$result = $this->awsTranscodeExec($message_data);
				}
			} else { //It's an image just resize and store thumbnails
//error_log("Inside snsProcessMediaSubscribe else it's an image..." . PHP_EOL);            
				/*
				 * 5-SEP-2014 
				 * moved thumnail creation to a single function
				 */
				$message_data['is_image'] = 1;
				$memreasTranscoder = new MemreasTranscoder($this);
				$memreas_transcoder_tables = new MemreasTranscoderTables($this->service_locator);
				$result = $memreasTranscoder->exec($message_data, $memreas_transcoder_tables, $this->service_locator, false);

				return $result;
			}
        
		} catch (Exception $e) {
		    error_log("Caught exception: $e->getMessage()" . PHP_EOL);
			//Remove the work directory
			$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid;
			$dirRemoved = new RmWorkDir($dir);
		    return false;
		}
    }

    function pullMediaFromS3($s3file, $file) {
	try {
			$result = $this->s3->getObject(array(
				'Bucket' => MemreasConstants::S3BUCKET,
				'Key' => $s3file,
				'SaveAs' => $file
			));
		} catch(Aws\S3\Exception\S3Exception $e) {
			error_log("Caught S3 exception: $e->getMessage()" . PHP_EOL);
			throw $e;
		}
		return true;
    }

    function pushThumbnailsToS3($dir, $s3path) {
    	$keyPrefix = $s3path;
    	$options = array(
    			//'params'      => array('ACL' => 'public-read'),
    			'concurrency' => 20,
    			//'debug'       => true
    	);
    	
error_log("dir ----> ".$dir.PHP_EOL);    	
		$result = $this->s3->uploadDirectory($dir, MemreasConstants::S3BUCKET, $keyPrefix, $options);
    }
    
    function copyMediaInS3($bucket, $target, $source) {
    	$result = $this->s3->copyObject(array(
    			'Bucket'               => $bucket,
    			'Key'                  => $target,
    			//'CopySource'           => "{".$bucket."}/{".$source."}",
    			'CopySource'           => $bucket.'/'.$source,
    			'ServerSideEncryption' => 'AES256',
    	));
    	return $result;
    }
    
    function pushMediaToS3($file, $s3file, $content_type, $isVideo = false) {
        $body = EntityBody::factory(fopen($file, 'r+'));
        /////////////////////////////////////////////////////////////////////
        //Upload images - section
        $uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket(MemreasConstants::S3BUCKET)
                ->setHeaders(array('Content-Type' => $content_type))
        		->setOption('CacheControl', 'max-age=3600')
                ->setKey($s3file)
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $result = $uploader->upload();
//            error_log( "Upload complete.\n", 0);
        } catch (MultipartUploadException $e) {
            $uploader->abort();
            error_log( "Upload failed.\n", 0);
        }
        
        //Set the content type 
        //$opt = array();     
        //$opt['CacheControl'] = 'max-age=3600';
                   	
        return $result;
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
        $dirPath = getcwd() . "/data/" . $user_id . "/media/";
        //$dirPath = $job_dir;
        //if (!file_exists($dirPath)) {
		//    mkdir($dirPath, 0777, true);
		//}
        $splitter = explode("thumbnail/", $s3file);
//error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $thumbnail_name = $splitter[1];
        $splitter = explode($thumbnail_name, $s3file);
//error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $path = $splitter[0];
        $thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;
//error_log("Inside fetchResizeUpload - thumbnail_file --> " . $thumbnail_file);                	

        //$file = $dirPath . $thumbnail_name;
        $file = $job_dir . $thumbnail_name;
//error_log("Inside fetchResizeUpload - about to get " . $s3file);                	
//error_log("Inside fetchResizeUpload - about to save locally as " . $file);                	
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
        //Saving image
        //$dirPath = getcwd() . "/data/" . $user_id . "/media/" . $height . "x" . $width . "/";
        $dirPath = getcwd() . "/data/" . $user_id . "/media/" . $height . "x" . $width . "/";
        $job_sub_dir = $job_dir . $height . "x" . $width . "/";
        if (!file_exists($job_sub_dir)) {
			$oldumask = umask(0);
		    mkdir($job_sub_dir, 01777, true);
			umask($oldumask);
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
				$this->temp_job_uuid = date("Y.m.d") . '_' . uniqid();
				$dirPath = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid . MemreasConstants::MEDIA_PATH;
				if (!file_exists($dirPath)) {
					$oldumask = umask(0);
					mkdir($dirPath, 01777, true);
					umask($oldumask);
				}

				//Time to fetch files and store in metadata
				// It's completed ... get list object...
				////////////////////////////////////////////////////////////////////
				//Fetch the metadata object
				//$query = "SELECT metadata FROM media WHERE media_id = '$media_id'";
				//$result = mysql_query($query) or die("SELECT FROM MEDIA FAILED");
				//$row = mysql_fetch_array($result);
				//$metadata = json_decode($row['metadata'], true);
			
				$media = $this->dbAdapter->find('Application\Entity\Media', $media_id);
				$metadata = json_decode($media->metadata, true);

				////////////////////////////////////////////////////////////////////
				//Fetch the list of files from S3 and add to metadata
				$_1080p_thumbnails = array();
				$objectsIterator = $this->s3->getIterator('ListObjects', array(
					'Bucket' => MemreasConstants::S3BUCKET,
					'Prefix' => $s3path . '1080p/'
				));
				foreach ($objectsIterator as $object) {
					if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
						$_1080p_thumbnails[]['Full'] = $object['Key'];
						$s3output_path = $s3path . "1080p/thumbnail/79x80/";
						$_1080p_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 79, 80);
						$s3output_path = $s3path . "1080p/thumbnail/448x306/";
						$_1080p_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 448, 306);
						$s3output_path = $s3path . "1080p/thumbnail/98x78/";
						$_1080p_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $dirPath, $object['Key'], $s3output_path, 98, 78);
					} else if (strpos($object['Key'], 'thumbnail/') === false) {
//	error_log("1080p Section inside else ..... object[key] --->  ". $object['Key'] . PHP_EOL);
						$metadata['S3_files']['1080p'] = $object['Key'];
					}
				}
				$metadata['S3_files']['1080p_thumbails'] = $_1080p_thumbnails;
//	error_log("set 1080p thumnails" . json_encode($metadata['S3_files']['1080p_thumbails']). PHP_EOL);


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
					} else if (strpos($object['Key'], 'thumbnail/') === false) {
						$metadata['S3_files']['hls'] = $object['Key'];
//	error_log("HLS Section inside else ..... object[key] --->  ". $object['Key'] . PHP_EOL);
					}
				}
				$metadata['S3_files']['hls_thumbnails'] = $hls_thumbnails;
				$metadata['S3_files']['hls_ts'] = $hls_ts;
//	error_log("set hls_thumbnails" . json_encode($metadata['S3_files']['hls_thumbnails']). PHP_EOL);
//	error_log("set hls_ts" . json_encode($metadata['S3_files']['hls_ts']). PHP_EOL);


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
					} else if (strpos($object['Key'], 'thumbnail/') === false) {
//	error_log("web Section inside else ..... object[key] --->  ". $object['Key'] . PHP_EOL);
						$metadata['S3_files']['web'] = $object['Key'];
					}
				}
				$metadata['S3_files']['web_thumbnails'] = $web_thumbnails;
//	error_log("set web_thumbnails" . json_encode($metadata['S3_files']['web_thumbnails']). PHP_EOL);

				////////////////////////////////////////////////
				//Update the database with the updated metadata
				$now = date('Y-m-d H:i:s');
				$json = json_encode($metadata);
//	error_log("About to set metadata ---> " . $json . PHP_EOL);
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
			$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid;
			$dirRemoved = new RmWorkDir($dir);

			return $result;
		} catch (Exception $e) {
		    error_log("Caught exception: $e->getMessage()" . PHP_EOL);
			//Remove the work directory
			$dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid;
			$dirRemoved = new RmWorkDir($dir);
		}
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
}//END



