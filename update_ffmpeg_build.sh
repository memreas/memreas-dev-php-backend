#!/bin/bash -v
#Purpose = update ffmpeg on fedora
#Created on 12-SEP-2015
#Author = John Meah
#Version 1.0

#vars
cmd="" 
base_dir="/var/www/memreas_ffmpeg_install.bak"
source_dir="${base_dir}/ffmpeg_sources"
build_dir="${base_dir}/ffmpeg_build"
bin_dir="${base_dir}/bin"

rm -rf $base_dir
mkdir $base_dir
mkdir $source_dir
mkdir $build_dir
mkdir $bin_dir

base_dir="/var/www/memreas_ffmpeg_install.bak/"
source_dir="${base_dir}ffmpeg_sources/"
build_dir="${base_dir}ffmpeg_build/"
bin_dir="${base_dir}bin/"

####################################
# remove old files and dependencies
####################################
# rm -rf $source_dir ${build_dir ${bin_dir{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}
yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel

##############
# Install Yasm
##############
cd $source_dir
git clone --depth 1 git://github.com/yasm/yasm.git
cd yasm
autoreconf -fiv
$cmd="./configure --prefix=\"${build_dir}\" --bindir=\"${bin_dir}\""
$cmd
make
make install
make distclean


##############
# Install x264
##############
$cmd="cd ${source_dir}"
$cmd
$cmd="git clone --depth 1 git://git.videolan.org/x264"
$cmd
$cmd="cd x264"
$cmd
$cmd="./configure --prefix=\"${build_dir}\" --bindir=\"${bin_dir}\" --enable-static"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


# Install x265
##############
$cmd="cd $source_dir"
$cmd
$cmd="hg clone https://bitbucket.org/multicoreware/x265"
$cmd
$cmd="cd ${source_dir}x265/build/linux"
$cmd
$cmd="cmake -G \"Unix Makefiles\" -DCMAKE_INSTALL_PREFIX=\"${build_dir}\" -DENABLE_SHARED:bool=off ../../source"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd

##############
# Install aac
##############
$cmd="cd $source_dir"
$cmd
$cmd="git clone --depth 1 git://git.code.sf.net/p/opencore-amr/fdk-aac"
$cmd
$cmd="cd fdk-aac"
$cmd
$cmd="autoreconf -fiv"
$cmd
$cmd="./configure --prefix=\"${build_dir}\" --disable-shared"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


####################
# Install libmp3lame
####################
$cmd="cd $source_dir"
$cmd
$cmd="curl -L -O http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz"
$cmd
$cmd="tar xzvf lame-3.99.5.tar.gz"
$cmd
$cmd="cd lame-3.99.5"
$cmd
$cmd="./configure --prefix=\"${build_dir}\" --bindir=\"${bin_dir}\" --disable-shared --enable-nasm"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


####################
# Install libmopus
####################
$cmd="cd $source_dir"
$cmd
$cmd="git clone git://git.opus-codec.org/opus.git"
$cmd
$cmd="cd opus"
$cmd
$cmd="autoreconf -fiv"
$cmd
$cmd="./configure --prefix=\"${build_dir}\" --disable-shared"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


####################
# Install libopus
####################
$cmd="cd $source_dir"
$cmd
$cmd="curl -O http://downloads.xiph.org/releases/ogg/libogg-1.3.2.tar.gz"
$cmd
$cmd="tar xzvf libogg-1.3.2.tar.gz"
$cmd
$cmd="cd libogg-1.3.2"
$cmd
$cmd="./configure --prefix="${build_dir" --disable-shared"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


####################
# Install libvoribis
####################
$cmd="cd $source_dir"
$cmd
$cmd="curl -O http://downloads.xiph.org/releases/vorbis/libvorbis-1.3.4.tar.gz"
$cmd
$cmd="tar xzvf libvorbis-1.3.4.tar.gz"
$cmd
$cmd="cd libvorbis-1.3.4"
$cmd
$cmd="LDFLAGS=\"-L ${build_dir}lib\" CPPFLAGS=\"-I ${build_dir}include\" ./configure --prefix=\"${build_dir}\" --with-ogg=\"${build_dir}\" --disable-shared"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd


####################
# Install ffmpeg
####################
$cmd="cd $source_dir"
$cmd
$cmd="git clone --depth 1 git://source.ffmpeg.org/ffmpeg"
$cmd
$cmd="cd ffmpeg"
$cmd
$cmd="PKG_CONFIG_PATH=\"${build_dir)lib/pkgconfig\" ./configure --prefix=\"${build_dir)\" --extra-cflags=\"-I ${build_dir)include\" --extra-ldflags=\"-L ${build_dir)lib\" --bindir=\"${bin_dir}\" --pkg-config-flags="--static" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265"
$cmd
$cmd="make"
$cmd
$cmd="make install"
$cmd
$cmd="make distclean"
$cmd
$cmd="hash -r"
$cmd

#END
