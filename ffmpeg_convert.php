<?php
//Purpose = ssh or sftp into ec2 instance
//Created on 4-APR-2014
//Author = John Meah
//Version 1.0

//vars
$infile = $argv[1];
$type = $argv[2];

echo "***************************************************\n";
echo "Starting ffmpeg from command line php \n";
echo "***************************************************\n";




$cmd = 'ffprobe -v error -print_format json -show_format -show_streams ' .
		 $infile;
try {
	$ffprobe_json = shell_exec($cmd);
	$ffprobe_json_array = json_decode($ffprobe_json, true);
	error_log(
			__CLASS__ . __METHOD__ . __LINE__ .
					 '::_FFPROBE_::' . $cmd . print_r($ffprobe_json,true) . PHP_EOL);
	
	$duration = $ffprobe_json_array['format']['duration'];
	$filesize = $ffprobe_json_array['format']['size'];
	error_log(
			__CLASS__ . __METHOD__ . __LINE__ .
					 '::_FFPROBE_::duration::' . $duration . '::filesize::' . $filesize . PHP_EOL);
} catch (\Exception $e) {
	error_log(
			__CLASS__ . __METHOD__ . __LINE__ . 
			"::ffprobe cmd:: $cmd \n exception:: $e->getMessage()" . PHP_EOL);
}


$path_parts = pathinfo($infile);
$filename = $path_parts['basename'];
$extension = $path_parts['extension'];

echo $path_parts['dirname'], "\n";
echo $path_parts['basename'], "\n";
echo $path_parts['extension'], "\n";
echo $path_parts['filename'], "\n"; 


if (($type == 'h264') || ($type == 'all'))  {
	echo "*********************\n";
	echo "transcode for h264";
	echo "*********************\n";
	$cmd = "ffmpeg  -i $infile -c:v libx264  -profile:v high -level 4.2 -preset veryfast -qp 5 -pix_fmt yuv420p -movflags +faststart -c:a aac -strict experimental -b:a 128k  transcode/h264_" . $infile;
	echo $cmd;
	$result = shell_exec($cmd);
	echo $result;
}

if (($type == 'h265') || ($type == 'all'))  {
	echo "********************\n";
	echo "transcode for h265";
	echo "********************\n";
	$cmd = "ffmpeg -i $infile -c:v libx265 -preset veryfast -pix_fmt yuv420p -x265-params crf=23 -c:a aac -strict experimental -b:a 128k transcode/h265_" . $infile;
	echo $cmd;
	$result = shell_exec($cmd);
	echo $result;
}


if (($type == 'hls') || ($type == 'all'))  {
	echo "*****************\n";
	echo "transcode for hls";
	echo "*****************\n";
	//$cmd = "ffmpeg  -re -y -i transcode/h264_" . $infile . " -map 0 -pix_fmt yuv420p -c:v libx264 -profile:v high -level 4.2 -preset veryfast -qp 5 -pix_fmt yuv420p -c:a aac -strict experimental -force_key_frames 50 -flags -global_header -f segment -segment_list_type m3u8  -segment_list  transcode/hls_".$filename.".m3u8 -segment_format mpeg_ts transcode/hls_".$filename."%05d.ts";
	$cmd = "ffmpeg -nostats -re -y -i " . $infile . ' -pix_fmt yuv420p -profile:v high -level 4.0 -movflags +faststart -r 25 -hls_list_size 0 -hls_time 2 -hls_allow_cache 1 -hls_flags delete_segments -hls_segment_filename ' . "transcode/hls_".$filename."%05d.ts transcode/hls_".$filename.".m3u8";
	echo $cmd;
	$result = shell_exec($cmd);
	echo $result;
}
