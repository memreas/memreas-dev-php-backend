#!/bin/bash
#Purpose = ssh or sftp into ec2 instance
#Created on 4-APR-2014
#Author = John Meah
#Version 1.0

#vars
infile=$1
filename=$(basename "$infile")
extension="${filename##*.}"
filename="${filename%.*}"
echo "********************************************"
echo "transcode for h264 for $filename $extension"
echo "********************************************"
cmd="ffmpeg -i "  
cmd+=$infile
cmd+=" -c:v libx264 -profile:v high -level 4.2 -preset veryfast -threads 1 -c:a aac -strict experimental -b:a 128k  transcode/h264_"
cmd+=$infile
echo $cmd
$cmd

echo "*****************"
echo "transcode for h265"
echo "*****************"
cmd="ffmpeg -i " 
cmd+=$infile
cmd+=" -c:v libx265 -threads 1 -preset veryfast -x265-params crf=28 -c:a aac -strict experimental -b:a 128k transcode/h265_"
cmd+=$infile
echo $cmd
$cmd

echo "*****************"
echo "transcode for hls"
echo "*****************"
cmd="ffmpeg -re -y -i transcode/h264_" 
cmd+=$infile
cmd+=" -map 0 -pix_fmt yuv420p -c:v libx264 -threads 1 -profile:v high -level 4.2 -c:a aac -strict experimental -r 25 -b:v 1500k -maxrate 2000k -force_key_frames 50 -flags -global_header -f segment -segment_list_type m3u8  -segment_list  transcode/hls_"
cmd+=$filename
cmd+=".m3u8 -segment_format mpeg_ts transcode/hls_"
cmd+=$filename
cmd+="%05d.ts"
echo $cmd
$cmd
#END
