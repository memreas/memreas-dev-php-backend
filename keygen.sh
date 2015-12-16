#!/bin/sh
BASE_PATH=$1
BASE_URL=$2
openssl rand 16 > $BASE_PATHfile.key
echo $BASE_URL/file.key > $BASE_PATHfile.keyinfo
echo $BASE_PATHfile.key >> $BASE_PATHfile.keyinfo
echo $(openssl rand -hex 16) >> $BASE_PATHfile.keyinfo

#BASE_URL=${1:-'.'}
#openssl rand 16 > file.key
#echo $BASE_URL/file.key > file.keyinfo
#echo file.key >> file.keyinfo
#echo $(openssl rand -hex 16) >> file.keyinfo