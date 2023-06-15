#!/usr/bin/env bash

if [ $# -lt 1 ]; then
	echo "usage: $0 <version>"
	exit 1
fi

TAG=${1-latest}

# Absolute path to this script, e.g. /home/user/bin/foo.sh
SCRIPT=$(readlink -f "$0")

# Absolute path this script is in, thus /home/user/bin
SCRIPTPATH=$(dirname "$SCRIPT")

cd $SCRIPTPATH/..

# Build plugin
npm install
npm run build

BRANCH=release
FOLDER=build

# Move to other directory
if [ -d "$FOLDER" ]; then rm -Rf $FOLDER; fi
git clone --branch $BRANCH git@github.com:importwp/importwp.git $FOLDER
cd $FOLDER
git rm -rf .
rsync -av .. . --exclude '.git' --exclude '.github' --exclude 'bin' --exclude "$FOLDER" --exclude 'node_modules' --exclude 'src' --exclude 'tests' --exclude 'vendor' --exclude '.babelrc' --exclude '.gitattributes' --exclude '.gitignore' --exclude '.phpcs.xml.dist' --exclude '.phpunit.result.cache' --exclude '.travis.yml' --exclude 'composer.json' --exclude 'composer.lock' --exclude 'package-lock.json' --exclude 'package.json' --exclude 'phpunit.xml.dist' --exclude 'webpack.config.js'

# Set version numbers
sed -i -e "s/__STABLE_TAG__/$TAG/g" readme.txt
sed -i -e "s/__STABLE_TAG__/$TAG/g" jc-importer.php

# Confirm pushing of build.
while true; do

read -p "Do you want to push build v$TAG? (y/n) " yn

case $yn in 
	[yY] ) echo ok, we will proceed;
		break;;
	[nN] ) echo exiting...;
		exit;;
	* ) echo invalid response;;
esac

done

# Commit and push
git add -A
git commit -m "Build v$TAG"
git push -u origin $BRANCH