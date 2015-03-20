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
        url=$MAGENTODIR$temppath
        FILES=($real)
        URL=($url)
        for file in "${FILES[@]}"
        do
            if [[ -L "$URL" ]]
            then
                echo "Link already exist: $URL";
            else
                echo "Linking $DIR/$file to $URL";
                ln -s "$DIR/$file" "$URL";
            fi
        done
    done
}
function deploy() {
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
        FILES=($real)
        for file in "${FILES[@]}"
        do
          echo "Copying "$DIR/$file" to $MAGENTODIR$temppath";
          cp -r "$DIR/$file" $MAGENTODIR$temppath;
        done
        
    done
}

if [ -z "$1" ]
then
    echo "Choose what you want to do first.";
    echo "---------------------------------";
    echo "build [magento dir] - Dev for symlink to different files and folders.";
    echo "deploy [build dir] - Copy files to correct maps for release.";
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
    if [ ! -z "$2" ]
    then
        MAGENTODIR=$2
        deploy;
    else
        echo "You need to specific a dir.";
        echo "Type fyndman deploy build/ as example";
    fi
fi
