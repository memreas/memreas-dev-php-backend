#!/bin/bash
#Purpose = update ffmpeg on fedora
#Created on 12-SEP-2015
#Author = John Meah
#Version 1.0

#vars
cmd="" 
base_dir="/var/www/memreas_ffmpeg_install.bak/"
source_dir="${base_dir}ffmpeg_sources/"
build_dir="${base_dir}ffmpeg_build/"
bin_dir="${base_dir}bin/"

####################################
# remove old files and dependencies
####################################
cmd="rm -rf $build_dir $bin_dir{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}"
$cmd
cmd="sudo yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel"
$cmd

##############
# Update Yasm
##############
cmd="cd ${source_dir}yasm"
$cmd
#cmd="make distclean"
#$cmd
cmd="git pull"
$cmd
cmd="./configure --prefix=\"$build_dir\" --bindir=\"$bin_dir\""
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd

exit 1

##############
# Update x264
##############
cmd="cd ${source_dir}x264"
$cmd
cmd="make distclean"
$cmd
cmd="git pull"
$cmd
cmd="./configure --prefix=\"$build_dir\" --bindir=\"$bin_dir\" --enable-static"
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd


##############
# Update x265
##############
cmd="cd ${source_dir}x265"
$cmd
cmd="rm -rf ${source_dir}x265/build/linux/*"
$cmd
cmd="hg update"
$cmd
cmd="cd ${source_dir}x265/build/linux"
$cmd
cmd="cmake -G \"Unix Makefiles\" -DCMAKE_INSTALL_PREFIX=\"$build_dir\" -DENABLE_SHARED:bool=off ../../source"
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd

#################
# Update fdk_aac
#################
cmd="cd ${source_dir}fdk_aac"
$cmd
cmd="make distclean"
$cmd
cmd="git pull"
$cmd
cmd="./configure --prefix=\"$build_dir\" --disable-shared"
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd

#################
# Update libvpx
#################
cmd="cd ${source_dir}libvpx"
$cmd
cmd="make clean"
$cmd
cmd="git pull"
$cmd
cmd="./configure --prefix=\"$build_dir\" --disable-examples"
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd

#################
# Update ffmpeg
#################
cmd="cd ${source_dir}ffmpeg"
$cmd
cmd="make distclean"
$cmd
cmd="git pull"
$cmd
cmd="PKG_CONFIG_PATH=\"${build_dir}lib/pkgconfig\" ./configure --prefix=\"$build_dir\" --extra-cflags=\"-I ${build_dir}include\" --extra-ldflags=\"-L ${build_dir}lib\" --bindir=\"$bin_dir\" --pkg-config-flags=\"--static\" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265"
$cmd
cmd="make"
$cmd
cmd="make install"
$cmd


#END
