#!/bin/bash
#Purpose = ssh or sftp into ec2 instance
#Created on 19-SEP-2015
#Author = John Meah
#Version 1.0

set -v
ffmpeg_cmd=$1;
$ffmpeg_cmd
status=$?
echo "exit stats - $status"