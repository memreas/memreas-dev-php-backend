#!/bin/bash
#Purpose = update ffmpeg on fedora
#Created on 12-SEP-2015
#Author = John Meah
#Version 1.0

#vars
cmd="" 
base_dir="/var/www/memreas_ffmpeg_install.bak/"
source_dir="$base_dir"+"ffmpeg_sources/"
build_dir="$base_dir"+"ffmpeg_build/"
bin_dir="$base_dir"+"bin/"

####################################
# remove old files and dependencies
####################################
cmd="rm -rf $build_dir $bin_dir{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}"
echo $cmd;
cmd="sudo yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel"
echo $cmd;

##############
# Update Yasm
##############
cmd="cd $source_dir"+"yasm"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$build_dir\" --bindir=\"$bin_dir\""
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

##############
# Update x264
##############
cmd="cd $source_dir"+"x264"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$build_dir\" --bindir=\"$bin_dir\" --enable-static"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;


##############
# Update x265
##############
cmd="cd $source_dir"+"x265"
echo $cmd;
cmd="rm -rf $source_dir"+"x265/build/linux/*"
echo $cmd;
cmd="hg update"
echo $cmd;
cmd="cd $source_dir"+"x265/build/linux"
echo $cmd;
cmd="cmake -G \"Unix Makefiles\" -DCMAKE_INSTALL_PREFIX=\"$build_dir\" -DENABLE_SHARED:bool=off ../../source"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update fdk_aac
#################
cmd="cd $source_dir"+"fdk_aac"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$build_dir\" --disable-shared"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update libvpx
#################
cmd="cd $source_dir"+"libvpx"
echo $cmd;
cmd="make clean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="./configure --prefix=\"$build_dir\" --disable-examples"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;

#################
# Update ffmpeg
#################
cmd="cd $source_dir"+"ffmpeg"
echo $cmd;
cmd="make distclean"
echo $cmd;
cmd="git pull"
echo $cmd;
cmd="PKG_CONFIG_PATH=\"$build_dir"+"lib/pkgconfig\" ./configure --prefix=\"$build_dir\" --extra-cflags=\"-I $build_dir"+"include\" --extra-ldflags=\"-L $build_dir"+"lib\" --bindir=\"$bin_dir\" --pkg-config-flags=\"--static\" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265"
echo $cmd;
cmd="make"
echo $cmd;
cmd="make install"
echo $cmd;


#END
