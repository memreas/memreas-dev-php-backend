#!/bin/bash -v
#Purpose = update ffmpeg on fedora
#Created on 12-SEP-2015
#Author = John Meah
#Version 1.0

#vars
cmd="" 
base_dir="/var/www/memreas_ffmpeg_install.bak"
source_dir="$base_dir/ffmpeg_sources"
build_dir="$base_dir/ffmpeg_build"
bin_dir="$base_dir/bin"

echo $base_dir
echo $source_dir
echo $build_dir
echo $bin_dir

rm -rf $base_dir
mkdir $base_dir
mkdir $source_dir
mkdir $build_dir
mkdir $bin_dir

#base_dir+="/"
#source_dir+="/"
#build_dir+="/"
#bin_dir+="/"
build_dir_lib="$build_dir"'/lib'
build_dir_include="$build_dir"'/include'
build_dir_lib_pkgconfig="$build_dir_lib"'/pkgconfig'


echo $base_dir
echo $source_dir
echo $build_dir
echo $build_dir_lib
echo $build_dir_include
echo $build_dir_lib_pkgconfig


####################################
# remove old files and dependencies
####################################
# rm -rf $source_dir ${build_dir ${bin_dir{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}
yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel

##############
# Install Yasm
##############
cd $source_dir
pwd
git clone git://github.com/yasm/yasm.git
cd yasm
pwd
./autogen.sh
#autoreconf -fiv
./configure --prefix="$build_dir" --bindir="$bin_dir"
make
make install
make distclean
PATH=$PATH:$bin_dir
echo $PATH
sleep 10


##############
# Install x264
##############
cd $source_dir
pwd
git clone --depth 1 git://git.videolan.org/x264
cd x264
pwd
./configure --prefix="$build_dir" --bindir="$bin_dir" --enable-static
make
make install
make distclean
sleep 10


##############
# Install x265
##############
cd $source_dir
pwd
hg clone https://bitbucket.org/multicoreware/x265
cd $source_dir
pwd
cd x265/build/linux
pwd
cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="$build_dir" -DENABLE_SHARED:bool=off ../../source
make
make install
sleep 10

##############
# Install aac
##############
cd $source_dir
pwd
git clone --depth 1 git://git.code.sf.net/p/opencore-amr/fdk-aac
cd fdk-aac
pwd
autoreconf -fiv
./configure --prefix="$build_dir" --disable-shared
make
make install
make distclean
sleep 10

####################
# Install libmp3lame
####################
cd $source_dir
pwd
curl -L -O http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz
tar xzvf lame-3.99.5.tar.gz
cd lame-3.99.5
pwd
./configure --prefix="$build_dir" --bindir="$bin_dir" --disable-shared --enable-nasm
make
make install
make distclean
sleep 10

####################
# Install libopus
# - fails git clone
# - unnecessary
####################
#cd $source_dir
#pwd
#git clone git://git.opus-codec.org/opus.git
#cd opus
#pwd
#autoreconf -fiv
#./configure --prefix="$build_dir" --disable-shared
#make
#make install
#make distclean
#sleep 10

####################
# Install libogg
####################
cd $source_dir
pwd
curl -O http://downloads.xiph.org/releases/ogg/libogg-1.3.2.tar.gz
tar xzvf libogg-1.3.2.tar.gz
cd libogg-1.3.2
pwd
./configure --prefix="$build_dir" --disable-shared
make
make install
make distclean
sleep 10

####################
# Install libvorbis
####################
cd $source_dir
pwd
curl -O http://downloads.xiph.org/releases/vorbis/libvorbis-1.3.4.tar.gz
tar xzvf libvorbis-1.3.4.tar.gz
cd libvorbis-1.3.4
pwd
LDFLAGS="-L""$build_dir_lib"
CPPFLAGS="-I""$build_dir_include"
LDFLAGS="$LDFLAGS" CPPFLAGS="$CPPFLAGS" ./configure --prefix="$build_dir" --with-ogg="$build_dir" --disable-shared
make
make install
make distclean
sleep 10

####################
# Install ffmpeg
####################
cd $source_dir
pwd
git clone --depth 1 git://source.ffmpeg.org/ffmpeg
cd ffmpeg
pwd
#PKG_CONFIG_PATH="$build_dir_lib_pkgconfig" ./configure --prefix="$build_dir" --extra-cflags="-I $build_dir_include" --extra-ldflags="-L $build_dir_lib" --bindir="$bin_dir" --pkg-config-flags="--static" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265
PKG_CONFIG_PATH="$build_dir_lib_pkgconfig" ./configure --prefix="$build_dir" --extra-cflags="-I $build_dir_include" --extra-ldflags="-L $build_dir_lib" --bindir="$bin_dir" --pkg-config-flags="--static" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265
make
make install
make distclean
hash -r

#END
