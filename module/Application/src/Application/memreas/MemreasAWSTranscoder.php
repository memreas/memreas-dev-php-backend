<?php
namespace Application\memreas;

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
//memreas models
use Application\Model\MemreasConstants;

error_reporting(E_ALL & ~E_NOTICE);

class MemreasAWSTranscoder {

    private $aws = null;
    private $s3 = null;
    private $bucket = null;
    private $sns = null;
    private $topicArn = null;
    private $awsTranscode = null;

    public function __construct() {
        //print "In MemreasAWSTranscoder constructor <br>";
        error_log("Inside MemreasAWSTranscoder contructor...", 0);



        error_log("Exit MemreasAWSTranscoder constructor", 0);
        //print "Exit MemreasAWSTranscoder constructor <br>";
    }

    //function snsProcessMediaSubscribe ($user_id, $media_id, $content_type, $s3path, $s3file_name, $isVideo = false) {
    function snsProcessMediaSubscribe($message_data) {

        if ($message_data['isVideo']) {
            //Transcode, fetch thumbnail and resize as needed
            $result = $this->awsTranscodeExec($message_data);
        } else {
            //Fetch image and create thumbnails
            //added by Sufalam STRAT

            $s3file_name = $message_data['s3file_name'];
            $user_id = $message_data['user_id'];
            $media_id = $message_data['media_id'];
            $content_type = $message_data['content_type'];
            $s3path = $message_data['s3path'];
            //END
            //Saving image ugh - need to find way to not write to disk....
            //$dirPath = dirname(__DIR__)."/media/79x80/";
            $dirPath = dirname(__DIR__) . "/media/";
            $file = $dirPath . $s3file_name;
            $s3file = $s3path . $s3file_name;
//            echo "s3file=$s3file<br/>file=$file<br/>dirpath=$dirPath<br/>s3path=$s3path<br/>s3path=$s3path<br>content=$content_type<br/>file_name=$s3file_name";
            $result = $this->s3->getObject(array(
                'Bucket' => S3BUCKET,
                'Key' => $s3file,
                'SaveAs' => $file
            ));

            //Use the s3upload code to resize and load to s3		
            //$media_id add by sufalam
            $paths = $this->s3upload($user_id, $media_id, $s3file_name, $content_type, $file);

            ////////////////////////////////////////////////////////////////////
            //Store to database here...
            $query = "SELECT metadata FROM media WHERE media_id = '$media_id'";
            $result = mysql_query($query) or die("SELECT FROM MEDIA FAILED");
            $row = mysql_fetch_array($result);

            $metadata = json_decode($row['metadata'], true);
            $metadata['S3_files']['79x80'] = $paths['79x80_Path'];
            $metadata['S3_files']['448x306'] = $paths['448x306_Path'];
            $metadata['S3_files']['98x78'] = $paths['98x78_Path'];
            $json = json_encode($metadata);
            $query = "UPDATE media SET metadata = '$json' WHERE media_id = '$media_id'";
            $result = mysql_query($query) or die("UPDATE MEDIA FAILED");
        }
        return $result;
    }



//END
}



