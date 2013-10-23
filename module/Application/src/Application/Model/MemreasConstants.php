<?php  
/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////

namespace Application\Model;
class MemreasConstants {
        const MEMREASDB     = 'memreasintdb';
        const S3BUCKET     	= "memreasdev";
        const TOPICARN		= "arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int";
        const ORIGINAL_URL	= "http://memreasint.elasticbeanstalk.com/app/";
        const MEDIA_URL		= "http://memreasint.elasticbeanstalk.com/app/?action=addmediaevent";
        const URL			= "/index";

        //const SITEURL			= "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1";
        const DATA_PATH			= "/data/";
        const MEDIA_PATH		= "/media/";
        const IMAGES_PATH		= "/media/images/";
        const USERIMAGE_PATH	= "/media/userimage/";
        const FOLDER_PATH		= "/data/media/";
        const FOLDER_AUDIO		= "upload_audio";
        const FOLDER_VIDEO		= "uploadVideo";
        const VIDEO				= "/data/media/uploadVideo";
        const AUDIO				= "/data/media/upload_audio";


        const CLOUDFRONT_STREAMING_HOST		= 'http://s1iq2cbtodqqky.cloudfront.net/';
        const CLOUDFRONT_DOWNLOAD_HOST		= 'http://d1ckv7o9k6o3x9.cloudfront.net/';
        const MEMREAS_TRANSCODER_URL		= 'http://memreasbackend.elasticbeanstalk.com/';
        //const MEMREAS_TRANSCODER_URL		= 'http://192.168.1.13/memreas-dev-php-backend/app/';
        //const MEMREAS_TRANSCODER_TOPIC_ARN	= 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';

}