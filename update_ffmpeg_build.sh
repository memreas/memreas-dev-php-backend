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

rm -rf $base_dir
mkdir $base_dir
mkdir $source_dir"
mkdir $build_dir"
mkdir $bin_dir"

####################################
# remove old files and dependencies
####################################
# rm -rf $source_dir $build_dir $bin_dir{ffmpeg,ffprobe,ffserver,lame,vsyasm,x264,x265,yasm,ytasm}
yum install autoconf automake cmake gcc gcc-c++ git libtool make mercurial nasm pkgconfig zlib-devel

##############
# Install Yasm
##############
cd $source_dir
git clone --depth 1 git://github.com/yasm/yasm.git
cd yasm
autoreconf -fiv
./configure --prefix="$build_dir/ffmpeg_build" --bindir="$bind_dir"
make
make install
make distclean


##############
# Install x264
##############
cd $source_dir
git clone --depth 1 git://git.videolan.org/x264
cd x264
./configure --prefix="$build_dir" --bindir="$bin_dir" --enable-static
make
make install
make distclean



##############
# Install x265
##############
cd $source_dir
hg clone https://bitbucket.org/multicoreware/x265
cd $source_dir
cd x265/build/linux
cd "${source_dir}x265/build/linux"
cmake -G "Unix Makefiles" -DCMAKE_INSTALL_PREFIX="${build}" -DENABLE_SHARED:bool=off ../../source
make
make install


##############
# Install aac
##############
cd $source_dir
git clone --depth 1 git://git.code.sf.net/p/opencore-amr/fdk-aac
cd fdk-aac
autoreconf -fiv
./configure --prefix="$build_dir" --disable-shared
make
make install
make distclean


####################
# Install libmp3lame
####################
cd $source_dir
curl -L -O http://downloads.sourceforge.net/project/lame/lame/3.99/lame-3.99.5.tar.gz
tar xzvf lame-3.99.5.tar.gz
cd lame-3.99.5
./configure --prefix="$build_dir" --bindir="$bin_dir" --disable-shared --enable-nasm
make
make install
make distclean


####################
# Install libmopus
####################
cd $source_dir
git clone git://git.opus-codec.org/opus.git
cd opus
autoreconf -fiv
./configure --prefix="$build_dir" --disable-shared
make
make install
make distclean


####################
# Install libopus
####################
cd $source_dir
curl -O http://downloads.xiph.org/releases/ogg/libogg-1.3.2.tar.gz
tar xzvf libogg-1.3.2.tar.gz
cd libogg-1.3.2
./configure --prefix="build_dir" --disable-shared
make
make install
make distclean


####################
# Install libvoribis
####################
cd $source_dir
curl -O http://downloads.xiph.org/releases/vorbis/libvorbis-1.3.4.tar.gz
tar xzvf libvorbis-1.3.4.tar.gz
cd libvorbis-1.3.4
LDFLAGS="-L ${build_dir}lib" CPPFLAGS="-I ${build_dir}include" ./configure --prefix="${build_dir}" --with-ogg="${build_dir}" --disable-shared
make
make install
make distclean


####################
# Install ffmpeg
####################
cd $source_dir
git clone --depth 1 git://source.ffmpeg.org/ffmpeg
cd ffmpeg
PKG_CONFIG_PATH="${build)lib/pkgconfig" ./configure --prefix="${build)" --extra-cflags="-I ${build)include" --extra-ldflags="-L ${build)lib" --bindir="${bin_dir}" --pkg-config-flags="--static" --enable-gpl --enable-nonfree --enable-libfdk-aac --enable-libfreetype --enable-libmp3lame --enable-libopus --enable-libvorbis --enable-libvpx --enable-libx264 --enable-libx265
make
make install
make distclean
hash -r

#END
