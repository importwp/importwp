#!/usr/bin/env bash

if [ $# -lt 1 ]; then
	echo "usage: $0 <version>"
	exit 1
fi

TAG=${1}

sed -i -r "s/^Stable tag: (.+)  $/Stable tag: $TAG  /g" readme.txt
sed -i -r "s/^ \* Version: (.+) $/ \* Version: $TAG /g" jc-importer.php
sed -i -r "s/^	define\('IWP_VERSION', '(.+)'\);$/	define('IWP_VERSION', '$TAG');/g" jc-importer.php