<?php  
/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////

namespace Application\Model;
class MemreasConstants {
        const MEMREASDB     = 'memreasdevdb';
        const S3BUCKET     	= "memreasdev";
        const TOPICARN		= "arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker";
        const ORIGINAL_URL	= "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index_json.php";
        //const ORIGINAL_URL	= "http://192.168.1.13/eventapp_zend2.1/webservices/index_json.php";
        const MEDIA_URL	= "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/addmediaevent.php";
        //const MEDIA_URL	= "http://192.168.1.13/eventapp_zend2.1/webservices/addmediaevent.php";
        const UUID_URL	= "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/addmediaevent.php";
        //const UUID_URL	= "http://192.168.1.13/eventapp_zend2.1/webservices/generateUUID_json.php";
        const URL			= "/index";

        //const SITEURL			= "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1";
        const SITE_URL			= "/data/media/";
        const DIR_PATH			= "/data/media/userimage/";
        const FOLDER_PATH		= "/data/media/";
        const FOLDER_AUDIO		= "upload_audio";
        const FOLDER_VIDEO		= "uploadVideo";
        const VIDEO				= "/data/media/uploadVideo";
        const AUDIO				= "/data/media/upload_audio";


        const CLOUDFRONT_STREAMING_HOST		= 'http://s1iq2cbtodqqky.cloudfront.net/';
        const CLOUDFRONT_DOWNLOAD_HOST		= 'http://d1ckv7o9k6o3x9.cloudfront.net/';
        const MEMREAS_TRANSCODER_URL		= 'http://memreas-rest-backend.localhost/index/transcoder';
        const MEMREAS_TRANSCODER_TOPIC_ARN	= 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker';

}