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
error_log("Inside AWSManagerReceiver contructor..." . PHP_EOL);

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
    		
error_log("Inside snsProcessMediaSubscribe ..." . PHP_EOL);            
			if ($message_data['is_video'] || $message_data['is_audio']) {
				//Transcode, fetch thumbnail and resize as needed
				if ($message_data['memreastranscoder']) {
error_log("Inside snsProcessMediaSubscribe message_data[memreastranscoder] ..." . $message_data['memreastranscoder']. PHP_EOL);
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
error_log("Inside snsProcessMediaSubscribe else it's an image..." . PHP_EOL);            
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
error_log("s3file ----> ".$s3file.PHP_EOL);    	
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
error_log("finished pullMediaFromS3".PHP_EOL);    	
error_log("file--->".$file.PHP_EOL);    	
		return true;
    }

    function pushThumbnailsToS3($dir, $s3path) {
    	$keyPrefix = $s3path;
    	$options = array(
    			//'params'      => array('ACL' => 'public-read'),
    			'concurrency' => 20,
    			'ServerSideEncryption', 'AES256',
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
	        		->setOption('ServerSideEncryption', 'AES256')
	        		->setKey($s3file)
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $result = $uploader->upload();
        } catch (MultipartUploadException $e) {
            $uploader->abort();
            error_log( "Upload failed.\n", 0);
        }
        
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
        $thumbnail_name = $splitter[1];
        $splitter = explode($thumbnail_name, $s3file);
        $path = $splitter[0];
        $thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;

        $file = $job_dir . $thumbnail_name;
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
        $file = $job_sub_dir . $thumbnail_name;

        $body = EntityBody::factory(fopen($file, 'r+'));
        /////////////////////////////////////////////////////////////////////
        //Upload images - section
        $uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket(MemreasConstants::S3BUCKET)
                ->setMinPartSize(10 * Size::MB)
                ->setOption('ContentType', $content_type)
                ->setOption('ServerSideEncryption', 'AES256')
                ->setKey($thumbnail_file)
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            $uploader->abort();
            error_log( "Upload failed.\n", 0);
        }

        error_log("thumbnail_file ----> " . $thumbnail_file);
        return $thumbnail_file;
    }

}//END



