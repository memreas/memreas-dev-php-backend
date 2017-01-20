<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class MemreasConstants {
	const MEMREAS_ENV = "memreasdev-bew";
	const AWS_APPKEY = 'AKIAJZE2O2WDMXLGR27A';
	const AWS_APPSEC = 'FI09T7vRXcWx+QBE/n5ysEtZxx/DOAxkks/o2rzG';
	const AWS_APPREG = 'us-east-1';
	const MEMREAS_TRANSCODER_FFMPEG = '/var/www/memreas_ffmpeg_install/bin/ffmpeg';
	const MEMREAS_TRANSCODER_FFPROBE = '/var/www/memreas_ffmpeg_install/bin/ffprobe';
	const MEMREAS_TRANSCODER_FFMPEG_LOCAL = '/usr/local/bin/ffmpeg';
	const MEMREAS_TRANSCODER_FFPROBE_LOCAL = '/usr/local/bin/ffprobe';
	const SIGNURLS = false;
	const EXPIRES = 3600;
	const SIZE_5MB = 5000000;
	const SIZE_10MB = 10000000;
	const SIZE_100MB = 100000000;
	// 1 hour should be enough to transcode
	const MEMREASDB = 'memreasintdb';
	const MEMREASBEDB = 'memreasbackenddb';
	const S3BUCKET = "memreasdevsec";
	const S3HLSBUCKET = "memreasdevhlssec";
	const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-bewq';
	const CLOUDFRONT_HLSSTREAMING_HOST = 'https://d2cbahrg0944o.cloudfront.net/';
	
	// Redis section v3.0.7
	const REDIS_SERVER_ENDPOINT = "10.0.0.81";
	const REDIS_SERVER_USE = true;
	const REDIS_SERVER_SESSION_ONLY = true;
	const REDIS_SERVER_PORT = "6379";
	
	// 12hour handle for process
	const REDIS_CACHE_TTL = 43200;
	const URL = "/index";
	const DATA_PATH = "/data/";
	const MEDIA_PATH = "/media/";
	const IMAGES_PATH = "/images/";
	const THUMBNAILS_PATH = "/images/thumbnails/";
	const USERIMAGE_PATH = "/media/userimage/";
	const FOLDER_PATH = "/data/media/";
	const FOLDER_AUDIO = "upload_audio";
	const FOLDER_VIDEO = "uploadVideo";
	const VIDEO = "/data/media/uploadVideo";
	const AUDIO = "/data/media/upload_audio";
	public static function fetchAWS() {
		$sharedConfig = [ 
				'region' => self::AWS_APPREG,
				'version' => 'latest',
				'credentials' => [ 
						'key' => self::AWS_APPKEY,
						'secret' => self::AWS_APPSEC 
				] 
		];
		
		return new \Aws\Sdk ( $sharedConfig );
	}
}
