#!/bin/bash
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
function build() {
    if [ ! -d "$DIRECTORY" ]; then
        mkdir $MAGENTODIR;
    fi
    IFS=$' \t\n'
    cat fileslist | while read real temppath; do
        if [ -z "$MAGENTODIR" ]; then
            if [ ! -d "$temppath" ]; then
                echo "Creating $temppath";
                mkdir -p $temppath;
            fi
        else
            filename="${temppath##*/}"
            tempdir="${temppath:0:${#temppath} - ${#filename}}"
            url=$MAGENTODIR$tempdir;
            if [ ! -d "$url" ]; then
                echo "Creating $url";
                mkdir -p $url;
            fi
        fi
        if [[ -d $temppath ]]; then
            url=$MAGENTODIR$(dirname "${temppath}")
        else
            url=$MAGENTODIR$temppath
        fi
        echo "Linking $real to $url";
        ln -s "$real" "$url";
    done
}
function deploy() {
    IFS=$' \t\n'
    cat fileslist | while read real temppath; do
        $real = $real;
        if [ -z "$MAGENTODIR" ]; then
            if [ ! -d "$temppath" ]; then
                echo "Creating $temppath";
                mkdir -p $temppath;
            fi
        else
            filename="${temppath##*/}"
            tempdir="${temppath:0:${#temppath} - ${#filename}}"
            url=$MAGENTODIR$tempdir;
            if [ ! -d "$url" ]; then
                echo "Creating $url";
                mkdir -p $url;
            fi
        fi
        if [[ -d $temppath ]]; then
            url=$MAGENTODIR$(dirname "${temppath}")
        else
            url=$MAGENTODIR$temppath
        fi
        echo "Copying $DIR$real to $url";
        cp -r DIR$real $url;
    done
}

if [ -z "$1" ]
then
    echo "Choose what you want to do first.";
    echo "---------------------------------";
    echo "Build - Dev for symlink to different files and folders.";
    echo "Deploy - Copy files to correct maps for release.";
elif [ "$1" = "build" ];
then
    if [ -z "$2" ]
    then
        MAGENTODIR="./build"
    else
        MAGENTODIR=$2
    fi
    build;
elif [ "$1" = "deploy" ];
then
    if [ -z "$2" ]
    then
        MAGENTODIR="./build"
    else
        MAGENTODIR=$2
    fi
    deploy;
fi
