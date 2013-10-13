<?php
namespace memreas;
//require_once 'config.php';
//$vendor_autoloader = dirname(__DIR__) . '/' . 'vendor/autoload.php';
//require $vendor_autoloader;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;

error_reporting(E_ALL & ~E_NOTICE);

class AWSManager {

    private $aws = null;
    private $s3 = null;
    private $bucket = null;
    private $sns = null;
    private $topicArn = null;
    private $awsTranscode = null;
    private $service_locator = null;
    private $dbAdapter = null;

    public function __construct($service_locator) {
        //print "In AWSManager constructor <br>";
        error_log("Inside AWSManager contructor..." . PHP_EOL);

		try {
			$this->service_locator = $service_locator;
error_log("Inside AWSManager contructor - got service_locator ..." . PHP_EOL);
			$this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
			//$this->dbAdapter = $service_locator->get('memreasbackenddb');
error_log("Inside AWSManager contructor - got dbAdapter ..." . PHP_EOL);
			$this->aws = Aws::factory(array(
						'key' => 'AKIAJMXGGG4BNFS42LZA',
						'secret' => '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',
						'region' => 'us-east-1'
			));
error_log("Inside AWSManager contructor - got aws ..." . PHP_EOL);

			//Fetch the S3 class
			$this->s3 = $this->aws->get('s3');
error_log("Inside AWSManager contructor - got s3 ..." . PHP_EOL);

			//Fetch the AWS Elastic Transcoder class
			$this->awsTranscode = $this->aws->get('ElasticTranscoder');
error_log("Inside AWSManager contructor - got ElasticTranscoder ..." . PHP_EOL);

			//Set the bucket
			$this->bucket = "memreasdev";

			//Fetch the SNS class
			$this->sns = $this->aws->get('sns');
error_log("Inside AWSManager contructor - got sns ..." . PHP_EOL);

			//Set the topicArn
			$this->topicArn = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
		} catch (Exception $e) {
 		   error_log('Caught exception: ' . $e->getMessage() . PHP_EOL);
		}

        error_log("Exit AWSManager constructor", 0);
        //print "Exit AWSManager constructor <br>";
    }

    function snsProcessMediaPublish($message_data) {

        $json = json_encode($message_data);
        error_log("INPUT JSON ----> " . $json);

        //Debug without Topic publish
        $result = $this->snsProcessMediaSubscribe($message_data);
/*
		$result = $this->sns->publish(array(
           'TopicArn' => $this->topicArn,
            'Message'  => $json,
            'Subject'  => 'snsProcessMediaPublish',
        	));
*/
        return $result;
    }

    function snsProcessMediaSubscribe($message_data) {

error_log("Inside snsProcessMediaSubscribe ..." . PHP_EOL);            
        if ($message_data['isVideo']) {
            //Transcode, fetch thumbnail and resize as needed
            $result = $this->awsTranscodeExec($message_data);
        } else {
error_log("Inside snsProcessMediaSubscribe else ..." . PHP_EOL);            
            //Fetch image and create thumbnails
            $s3file_name = $message_data['s3file_name'];
            $user_id = $message_data['user_id'];
            $media_id = $message_data['media_id'];
            $content_type = $message_data['content_type'];
            $s3path = $message_data['s3path'];
            //END
            //Saving image ugh - need to find way to not write to disk....
            //$dirPath = dirname(__DIR__)."/media/79x80/";
            //$dirPath = dirname(__DIR__) . "/media/";
            $dirPath = getcwd() . "/data/media/";
error_log("Inside snsProcessMediaSubscribe dirPath ----> $dirPath" . PHP_EOL);            
            $file = $dirPath . $s3file_name;
            $s3file = $s3path . $s3file_name;
//            echo "s3file=$s3file<br/>file=$file<br/>dirpath=$dirPath<br/>s3path=$s3path<br/>s3path=$s3path<br>content=$content_type<br/>file_name=$s3file_name";
            $result = $this->s3->getObject(array(
                'Bucket' => S3BUCKET,
                'Key' => $s3file,
                'SaveAs' => $file
            ));
error_log("Inside snsProcessMediaSubscribe dirPath ----> $dirPath" . PHP_EOL);            
error_log("Inside snsProcessMediaSubscribe file ----> $file" . PHP_EOL);            
error_log("Inside snsProcessMediaSubscribe s3file ----> $s3file" . PHP_EOL);            
error_log("Inside snsProcessMediaSubscribe result ----> $result" . PHP_EOL);            

            //Use the s3upload code to resize and load to s3		
            //$media_id add by sufalam
            $paths = $this->s3upload($user_id, $media_id, $s3file_name, $content_type, $file);

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
        }
        return $result;
    }

    function fetchResizeUpload($message_data, $s3file, $s3output_path, $height, $width) {

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
        //$dirPath = dirname(__DIR__)."/media/79x80/";
        $dirPath = dirname(__DIR__) . "/media/";
        $splitter = explode("thumbnail/", $s3file);
//error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $thumbnail_name = $splitter[1];
        $splitter = explode($thumbnail_name, $s3file);
//error_log("Inside fetchResizeUpload - splitter --> " . print_r($splitter, true));                	
        $path = $splitter[0];
        $thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;
//error_log("Inside fetchResizeUpload - thumbnail_file --> " . $thumbnail_file);                	

        $file = $dirPath . $thumbnail_name;
//error_log("Inside fetchResizeUpload - about to get " . $s3file);                	
//error_log("Inside fetchResizeUpload - about to save locally as " . $file);                	
        $result = $this->s3->getObject(array(
            'Bucket' => S3BUCKET,
            'Key' => $s3file,
            'SaveAs' => $file
        ));

        /////////////////////////////////////////////////////////////////////
        //Resize images - section
        $layer = ImageWorkshop::initFromPath($file);
        //$layer->resizeInPixel($height, $width, true, 0, 0, 'MM');  //Maintains image
        $layer->resizeInPixel($height, $width);

        //Saving image ugh
        $dirPath = dirname(__DIR__) . "/media/" . $height . "x" . $width . "/";
        $createFolders = true;
        $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
        $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
        $layer->save($dirPath, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality);
        $file = $dirPath . $thumbnail_name;

//error_log("Inside fetchResizeUpload - resized and saved local file is now  --> " . $file);                	

        $body = EntityBody::factory(fopen($file, 'r+'));
        //$s3_media_folder = "$s3output_path";
        //$s3_media_path = $s3_media_folder . $s3file_name;
        //$ec2_media_path = $dirPath . $s3file_name;
//error_log("Inside fetchResizeUpload - thumbnail_file is  --> " . $thumbnail_file);                	
        /////////////////////////////////////////////////////////////////////
        //Upload images - section
        $uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket(S3BUCKET)
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

            //Time to fetch files and store in metadata
            // It's completed ... get list object...
            ////////////////////////////////////////////////////////////////////
            //Fetch the metadata object
            $query = "SELECT metadata FROM media WHERE media_id = '$media_id'";
            $result = mysql_query($query) or die("SELECT FROM MEDIA FAILED");
            $row = mysql_fetch_array($result);
            $metadata = json_decode($row['metadata'], true);

            error_log("metadata before -----------> " . $row['metadata']);

            ////////////////////////////////////////////////////////////////////
            //Fetch the list of files form S3
            $_1080p_thumbnails = array();
            $objectsIterator = $this->s3->getIterator('ListObjects', array(
                'Bucket' => S3BUCKET,
                'Prefix' => $s3path . '1080p/'
            ));
            foreach ($objectsIterator as $object) {
//               old condition if (strpos($object['Key'], 'thumbnail') !== false) {
                //condition added by Sufalam STRAT
                if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
                    $_1080p_thumbnails[]['Full'] = $object['Key'];
                    $s3output_path = $s3path . "1080p/thumbnail/79x80/";
                    $_1080p_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 79, 80);
                    $s3output_path = $s3path . "1080p/thumbnail/448x306/";
                    $_1080p_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 448, 306);
                    $s3output_path = $s3path . "1080p/thumbnail/98x78/";
                    $_1080p_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 98, 78);
                } else {
                    $metadata['S3_files']['1080p'] = $object['Key'];
                }
            }
            $metadata['S3_files']['1080p_thumbails'] = $_1080p_thumbnails;
            //resize and put to s3


            $hls_thumbnails = array();
            $hls_ts = array();
            $objectsIterator = $this->s3->getIterator('ListObjects', array(
                'Bucket' => S3BUCKET,
                'Prefix' => $s3path . 'hls/'
            ));
            foreach ($objectsIterator as $object) {
//                   if (strpos($object['Key'], 'thumbnail') !== false) 
//                condition added by Sufalam STRAT
                if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
                    $hls_thumbnails[]['Full'] = $object['Key'];
                    $s3output_path = $s3path . "hls/thumbnail/79x80/";
                    $hls_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 79, 80);
                    $s3output_path = $s3path . "hls/thumbnail/448x306/";
                    $hls_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 448, 306);
                    $s3output_path = $s3path . "hls/thumbnail/98x78/";
                    $hls_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 98, 78);
                } else if (strpos($object['Key'], '.ts') !== false) {
                    $hls_ts[] = $object['Key'];
                } else {
                    $metadata['S3_files']['hls'] = $object['Key'];
                }
                $metadata['S3_files']['hls_thumbnails'] = $hls_thumbnails;
                $metadata['S3_files']['hls_ts'] = $hls_ts;
            }

            $web_thumbnails = array();
            $objectsIterator = $this->s3->getIterator('ListObjects', array(
                'Bucket' => S3BUCKET,
                'Prefix' => $s3path . 'web/'
            ));
            foreach ($objectsIterator as $object) {
//                                if (strpos($object['Key'], 'thumbnail') !== false) 
//                condition added by Sufalam STRAT
                if (strpos($object['Key'], 'thumbnail/' . $s3file_name) !== false) {
                    $web_thumbnails[]['Full'] = $object['Key'];
                    $s3output_path = $s3path . "web/thumbnail/79x80/";
                    $web_thumbnails[]['79x80'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 79, 80);
                    $s3output_path = $s3path . "web/thumbnail/448x306/";
                    $web_thumbnails[]['448x306'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 448, 306);
                    $s3output_path = $s3path . "web/thumbnail/98x78/";
                    $web_thumbnails[]['98x78'] = $this->fetchResizeUpload($message_data, $object['Key'], $s3output_path, 98, 78);
                } else {
                    $metadata['S3_files']['web'] = $object['Key'];
                }
                $metadata['S3_files']['web_thumbnails'] = $web_thumbnails;
            }

            ////////////////////////////////////////////////
            //Update the database with the updated metadata
            $json = json_encode($metadata);
            error_log("metadata after -----------> " . $json);
            $query = "UPDATE media SET metadata = '$json' WHERE media_id = '$media_id'";
            $result = mysql_query($query) or die("UPDATE MEDIA FAILED");
        } else {
            die("TRANSCODE ERROR: Job Id is not set!");
        }

        return $result;

        error_log("Exit awsTranscode ...", 0);
    }

    function s3upload($user_id, $media_id, $s3file_name, $content_type, $file, $isVideo = false) {

        error_log("Enter s3upload");

        $s3_media_folder = null;
        $s3_media_path = null;
        $image79x80 = null;
        $image448x306 = null;
        $image98x78 = null;
        $paths = array();


        $metadata = null;
        if ($isVideo) {
            //Do Nothing ... topic covers...
            //$this->awsTranscodeExec($media_id, $s3_media_folder, $s3file_name); 
        } else {

            /////////////////////////////////////////////////////////////////////
            //Resize images - 79x80 section
            $layer = ImageWorkshop::initFromPath($file);
            //$layer->resizeInPixel(79, 80, true, 0, 0, 'MM');  //Maintains image
            $layer->resizeInPixel(79, 80);

            //Saving image ugh
            //$dirPath = dirname(__DIR__) . "/media/79x80/";
            $dirPath = getcwd() . "/data/media/79x80/";
            $createFolders = true;
            $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
            $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
            $layer->save($dirPath, $s3file_name, $createFolders, $backgroundColor, $imageQuality);

            //S3 Folder Setup
            $s3_media_folder = "$user_id/images/79x80/";
            $s3_media_path = $s3_media_folder . $s3file_name;
            $ec2_media_path = $dirPath . $s3file_name;
            $body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

            $uploader = UploadBuilder::newInstance()
                    ->setClient($this->s3)
                    ->setSource($body)
                    ->setBucket(S3BUCKET)
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
            $dirPath = getcwd() . "/data/media/448x306/";
            $createFolders = true;
            $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
            $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
            $layer->save($dirPath, $s3file_name, $createFolders, $backgroundColor, $imageQuality);

            //S3 Folder Setup
            $s3_media_folder = "$user_id/images/448x306/";
            $s3_media_path = $s3_media_folder . $s3file_name;
            $ec2_media_path = $dirPath . $s3file_name;
            $body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

            $uploader = UploadBuilder::newInstance()
                    ->setClient($this->s3)
                    ->setSource($body)
                    ->setBucket(S3BUCKET)
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
            $dirPath = getcwd() . "/data/media/98x78/";
            $createFolders = true;
            $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
            $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
            $layer->save($dirPath, $s3file_name, $createFolders, $backgroundColor, $imageQuality);

            //S3 Folder Setup
            $s3_media_folder = "$user_id/images/98x78/";
            $s3_media_path = $s3_media_folder . $s3file_name;
            $ec2_media_path = $dirPath . $s3file_name;
            $body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

            $uploader = UploadBuilder::newInstance()
                    ->setClient($this->s3)
                    ->setSource($body)
                    ->setBucket(S3BUCKET)
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
        }

        //error_log("Exit s3upload");
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

//added by Sufalam STRAT
    public function webserviceUpload($user_id, $s3file_name, $content_type) {
        //$dirPath = dirname(__DIR__) . "/media/";
        $dirPath = getcwd() . MemreasConstants::DIR_PATH;
        $file = $dirPath . $s3file_name;
        $layer = ImageWorkshop::initFromPath($file);
        //Saving image ugh
        $createFolders = true;
        $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
        $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to 100%)
        $layer->save($dirPath, $s3file_name, $createFolders, $backgroundColor, $imageQuality);


        $s3_media_folder = "$user_id/image/";

        $s3_media_path = $s3_media_folder . $s3file_name;
        $ec2_media_path = $dirPath . $s3file_name;
        $body = EntityBody::factory(fopen($ec2_media_path, 'r+'));

        $uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket(S3BUCKET)
                ->setMinPartSize(10 * Size::MB)
                ->setOption('ContentType', $content_type)
                ->setKey($s3_media_path)
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            $uploader->abort();
        }

        return $s3_media_path;
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
  ->setBucket(S3BUCKET)
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


