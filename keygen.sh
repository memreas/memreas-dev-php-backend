#!/bin/sh
#BASE_URL=$1
#echo $BASE_URL
#openssl rand 16 > file.key
#echo $BASE_URL/file.key > file.keyinfo
#echo file.key >> file.keyinfo
#echo $(openssl rand -hex 16) >> file.keyinfo

openssl rand 16 > $1/file.key
echo $1/file.key > $1/file.keyinfo
echo $1/file.key >> $1/file.keyinfo
echo $(openssl rand -hex 16) >> $1/file.keyinfo