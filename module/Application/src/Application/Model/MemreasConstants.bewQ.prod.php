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
	const SIGNURLS = false;
	const EXPIRES = 36000; // 10 hour should be enough to transcode
		
	const MEMREASDB     = 'memreasintdb';
    const MEMREASBEDB     = 'memreasbackenddb';
    const S3BUCKET     	= "memreasprdsec";
    //const TOPICARN		= "arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int";
	const QUEUEURL 		= 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasprod-backend-worker';
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
}