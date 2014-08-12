<?php  
/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////

namespace Application\Model;
class MemreasConstants {
        
	const MEMREAS_TRANSCODER_FFMPEG = '/var/app/memreas_ffmpeg_install/bin/ffmpeg';
	const MEMREAS_TRANSCODER_FFPROBE = '/var/app/memreas_ffmpeg_install/bin/ffprobe';
	const MEMREAS_TRANSCODER_FFMPEG_LOCAL = '/usr/local/Cellar/ffmpeg/ffmpeg';
	const MEMREAS_TRANSCODER_FFPROBE_LOCAL = '/usr/local/Cellar/ffmpeg/ffprobe';
	
	const MEMREASDB     = 'memreasintdb';
    const MEMREASBEDB     = 'memreasbackenddb';
    const S3BUCKET     	= "memreasdev";
    //const TOPICARN		= "arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int";
	const QUEUEURL 		= 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-bew2';
    const ORIGINAL_URL	= "http://memreasdev-ws-elastic.elasticbeanstalk.com/";
    const MEDIA_URL		= "http://memreasdev-ws-elastic.elasticbeanstalk.com/?action=addmediaevent";
    const URL			= "/index";

    const DATA_PATH			= "/data/";
    const MEDIA_PATH		= "/media/";
    const IMAGES_PATH		= "/images/";
    const THUMBNAILS_PATH	= "/images/thumbnails/";
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