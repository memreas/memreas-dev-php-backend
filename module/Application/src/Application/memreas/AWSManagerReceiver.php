<?php
namespace Application\memreas;
use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;
use Application\memreas\RmWorkDir;
use Application\memreas\Mlog;

class AWSManagerReceiver
{

    protected $aws = null;

    protected $s3 = null;

    protected $service_locator = null;

    protected $dbAdapter = null;

    protected $temp_job_uuid = null;

    public $memreasTranscoder = null;

    public function __construct ($service_locator, $message_data)
    {
        try {
            Mlog::addone(__CLASS__ . __METHOD__ . '$message_data', 
                    $message_data);
            
            $this->service_locator = $service_locator;
            $this->dbAdapter = $service_locator->get(
                    'doctrine.entitymanager.orm_default');
            $this->aws = Aws::factory(
                    array(
                            'key' => MemreasConstants::AWS_APPKEY,
                            'secret' => MemreasConstants::AWS_APPSEC,
                            'region' => MemreasConstants::AWS_APPREG
                    ));
            
            // Fetch the S3 class
            $this->s3 = $this->aws->get('s3');
            // Fetch transcoder
            if ($message_data['is_video'] || $message_data['is_audio']) {
                $message_data['is_image'] = 0;
            } else { // It's an image just resize and store thumbnails
                $message_data['is_image'] = 1;
            }
            $this->memreasTranscoder = new MemreasTranscoder($this, 
                    $this->service_locator);
        } catch (Exception $e) {
            Mlog::addone(__FILE__ . __METHOD__ . 'Caught exception: ', 
                    $e->getMessage());
        }
    }

    function snsProcessMediaSubscribe ($message_data)
    {
        try {
            // Mlog::addone ( __FILE__ . __METHOD__, '...' );
            $result = $this->memreasTranscoder->exec($message_data, false);
            return $result;
        } catch (Exception $e) {
            Mlog::addone(__FILE__ . __METHOD__ . 'Caught exception: ', 
                    $e->getMessage());
            // Remove the work directory
            $dir = getcwd() . MemreasConstants::DATA_PATH . $this->temp_job_uuid;
            $dirRemoved = new RmWorkDir($dir);
            return false;
        }
    }

    function pullMediaFromS3 ($s3file, $file)
    {
        Mlog::addone(__FILE__ . __METHOD__ . '::pulling s3file', $s3file);
        $result = $this->s3->getObject(
                array(
                        'Bucket' => MemreasConstants::S3BUCKET,
                        'Key' => $s3file,
                        'SaveAs' => $file
                ));
        Mlog::addone(__FILE__ . __METHOD__ . '::finished pullMediaFromS3', 
                $file);
        return true;
    }

    function pushThumbnailsToS3 ($dir, $s3path)
    {
        $keyPrefix = $s3path;
        $options = array(
                // 'params' => array('ACL' => 'public-read'),
                'concurrency' => 20,
                'ServerSideEncryption',
                'AES256'
        );
        
        $result = $this->s3->uploadDirectory($dir, MemreasConstants::S3BUCKET, 
                $keyPrefix, $options);
    }

    function copyMediaInS3 ($bucket, $target, $source)
    {
        $result = $this->s3->copyObject(
                array(
                        'Bucket' => $bucket,
                        'Key' => $target,
                        // 'CopySource' => "{".$bucket."}/{".$source."}",
                        'CopySource' => $bucket . '/' . $source,
                        'ServerSideEncryption' => 'AES256'
                ));
        return $result;
    }

    function pushMediaToS3 ($file, $s3file, $content_type, $isVideo = false, 
            $bucket = MemreasConstants::S3BUCKET)
    {
        // Use default bucket
        // $body = EntityBody::factory(fopen($file, 'r+'));
        /*
         * Upload images - section
         */
        try {
            $this->s3->putObject(
                    [
                            'Bucket' => $bucket,
                            'Key' => $s3file,
                            'Body' => fopen($file, 'r'),
                            'ServerSideEncryption' => 'AES256',
                            'CacheControl' => 'max-age=3600',
                            'ContentType' => $content_type
                    ]);
        } catch (\Exception $e) {
            Mlog::addone(__FILE__ . __METHOD__ . 'Caught exception: ', 
                    $e->getMessage());
            throw $e;
        }
        return $result;
    }

    function fetchResizeUpload ($message_data, $job_dir, $s3file, $s3output_path, 
            $height, $width)
    {
        /*
         * Fetch image and create thumbnails
         */
        $user_id = $message_data['user_id'];
        $media_id = $message_data['media_id'];
        $content_type = $message_data['content_type'];
        $s3path = $message_data['s3path'];
        $s3file_name = $message_data['s3file_name'];
        
        /*
         * Local server data
         */
        $dirPath = getcwd() . "/data/" . $user_id . "/media/";
        $splitter = explode("thumbnail/", $s3file);
        $thumbnail_name = $splitter[1];
        $splitter = explode($thumbnail_name, $s3file);
        $path = $splitter[0];
        $thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;
        
        $file = $job_dir . $thumbnail_name;
        $result = $this->s3->getObject(
                array(
                        'Bucket' => MemreasConstants::S3BUCKET,
                        'Key' => $s3file,
                        'SaveAs' => $file
                ));
        
        /*
         * Resize images - section
         */
        $layer = ImageWorkshop::initFromPath($file);
        // $layer->resizeInPixel($height, $width, true, 0, 0, 'MM'); //Maintains
        // image
        $layer->resizeInPixel($height, $width);
        $dirPath = getcwd() . "/data/" . $user_id . "/media/" . $height . "x" .
                 $width . "/";
        $job_sub_dir = $job_dir . $height . "x" . $width . "/";
        if (! file_exists($job_sub_dir)) {
            $oldumask = umask(0);
            mkdir($job_sub_dir, 01777, true);
            umask($oldumask);
        }
        
        $createFolders = true;
        $backgroundColor = null; // transparent, only for PNG (otherwise it will
                                 // be white if set null)
        $imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0 to
                            // 100%)
        $layer->save($job_sub_dir, $thumbnail_name, $createFolders, 
                $backgroundColor, $imageQuality);
        $file = $job_sub_dir . $thumbnail_name;
        
        $body = EntityBody::factory(fopen($file, 'r+'));
        /*
         * Upload images - section
         */
        $uploader = UploadBuilder::newInstance()->setClient($this->s3)
            ->setSource($body)
            ->setBucket(MemreasConstants::S3BUCKET)
            ->setMinPartSize(10 * Size::MB)
            ->setOption('ContentType', $content_type)
            ->setOption('ServerSideEncryption', 'AES256')
            ->setKey($thumbnail_file)
            ->build();
        
        /*
         * Modified - Perform the upload to S3. Abort the upload if something
         * goes wrong
         */
        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            $uploader->abort();
            Mlog::addone(__FILE__ . __METHOD__ . 'Caught exception: ', 
                    $e->getMessage());
        }
        
        return $thumbnail_file;
    }
}//END



