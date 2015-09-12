#!/bin/bash
#Purpose = update ffmpeg on fedora
#Created on 12-SEP-2015
#Author = John Meah
#Version 1.0

#vars
cmd="" 
base_dir="/var/www/memreas_ffmpeg_install/"
source_dir="/var/www/memreas_ffmpeg_install/ffmpeg_sources/"
build_dir="/var/www/memreas_ffmpeg_install/ffmpeg_build/"
bin_dir="/var/www/memreas_ffmpeg_install/bin/"

####################################
# remove old files and dependencies
####################################
cmd="rm -rf $base_dir/ffmpeg_build $base_dir/bin/{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}"
echo $cmd;
cmd="sudo yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel"
echo $cmd;

##############
# Update Yasm
##############
cmd="cd $base_dir/ffmpeg_sources/yasm"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$base_dir/ffmpeg_build\" --bindir=\"$base_dir/bin\""
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

##############
# Update x264
##############
cmd="cd $base_dir/ffmpeg_sources/x264"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$base_dir/ffmpeg_build\" --bindir=\"$base_dir/bin\" --enable-static"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;


##############
# Update x265
##############
cmd="cd $base_dir/ffmpeg_sources/x265"
echo $cmd;
cmd="rm -rf ~/ffmpeg_sources/x265/build/linux/*"
echo $cmd;
cmd="hg update"
echo $cmd;
cmd="cd ~/ffmpeg_sources/x265/build/linux"
echo $cmd;
cmd="cmake -G \"Unix Makefiles\" -DCMAKE_INSTALL_PREFIX=\"$base_dir/ffmpeg_build\" -DENABLE_SHARED:bool=off ../../source"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update fdk_aac
#################
cmd="cd $base_dir/ffmpeg_sources/fdk_aac"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$base_dir/ffmpeg_build\" --disable-shared"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update libvpx
#################
cmd="cd $base_dir/ffmpeg_sources/libvpx"
echo $cmd;
cmd="make clean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$base_dir/ffmpeg_build\" --disable-examples"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update ffmpeg
#################
cmd="cd $base_dir/ffmpeg_sources/ffmpeg"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="PKG_CONFIG_PATH=\"$base_dir/ffmpeg_build/lib/pkgconfig\" ./configure --prefix=\"$base_dir/ffmpeg_build\" --extra-cflags=\"-I$base_dir/ffmpeg_build/include\" --extra-ldflags=\"-L $base_dir/ffmpeg_build/lib\" --bindir=\"$base_dir/bin\" --pkg-config-flags=\"--static\" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;


#END
