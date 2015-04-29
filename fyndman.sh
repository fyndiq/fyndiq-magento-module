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
    echo "Removing git- and DS_Store-related files in $MAGENTODIR";
    find ./$MAGENTODIR -name "*.git*" -print0 | xargs -0 rm -rf;
    find ./$MAGENTODIR -name "*.DS_Store*" -print0 | xargs -0 rm -rf;
    COMMIT="$(git rev-parse --short HEAD)";
    echo "Zipping the build";
    zip -r -X fyndiq-magento-$VERSION-$COMMIT.zip $MAGENTODIR*;
}

if [ -z "$1" ]
then
    echo "Choose what you want to do first.";
    echo "---------------------------------";
    echo "build [magento dir] - Dev for symlink to different files and folders.";
    echo "deploy [build dir] [versionnr] - Copy files to correct maps for release.";
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
	if [ ! -z "$3" ]
	then
	  VERSION=$3
	  deploy;
	else
	  echo "You need to specify version, type like: 1.0.0";
	fi
    else
        echo "You need to specific a dir.";
        echo "Type fyndman deploy build/ as example";
    fi
fi
