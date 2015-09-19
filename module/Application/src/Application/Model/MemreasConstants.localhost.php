<?php
// ///////////////////////////////
// Author: John Meah
// Copyright memreas llc 2015
// ///////////////////////////////
namespace Application\Model;

class MemreasConstants
{

    const AWS_APPKEY = 'AKIAJMXGGG4BNFS42LZA';

    const AWS_APPSEC = 'xQfYNvfT0Ar+Wm/Gc4m6aacPwdT5Ors9YHE/d38H';

    const AWS_APPREG = 'us-east-1';

    const MEMREAS_TRANSCODER_FFMPEG = '/var/www/memreas_ffmpeg_install/bin/ffmpeg';

    const MEMREAS_TRANSCODER_FFPROBE = '/var/www/memreas_ffmpeg_install/bin/ffprobe';

    const MEMREAS_TRANSCODER_FFMPEG_LOCAL = '/var/www/memreas_ffmpeg_install/bin/ffmpeg';

    const MEMREAS_TRANSCODER_FFPROBE_LOCAL = '/var/www/memreas_ffmpeg_install/bin/ffprobe';

    const SIGNURLS = false;

    const EXPIRES = 3600;
    // 1 hour should be enough to transcode
    const MEMREASDB = 'memreasintdb';

    const MEMREASBEDB = 'memreasbackenddb';

    const S3BUCKET = "memreasdevsec";

    const S3HLSBUCKET = "memreasdevhls";

    const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-bewq';

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

    const CLOUDFRONT_STREAMING_HOST = 'http://s1iq2cbtodqqky.cloudfront.net/';

    const CLOUDFRONT_DOWNLOAD_HOST = 'http://d1ckv7o9k6o3x9.cloudfront.net/';
}