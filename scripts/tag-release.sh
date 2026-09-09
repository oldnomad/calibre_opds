#!/bin/sh
set -e

VER="$1"

if [ -z "$VER" ]
then
    echo "Usage: $0 <version>"
    exit 1
fi

if [ -z "$(echo "$VER" | grep -Px '\d+\.\d+\.\d+')" ]
then
    echo "Version should be in format x.y.z"
    exit 1
fi
if [ "$(xpath -q -e '/info/version/text()' appinfo/info.xml)" != "$VER" ]
then
    echo "Version in appinfo/info.xml wasn't updated"
    exit 1
fi
CHLOG_HEADER=$(grep -F "## $VER - " CHANGELOG.md)
if [ -z "$CHLOG_HEADER" ]
then
    echo "Changelog section for version is missing"
    exit 1
fi
CHLOG_DATE="${CHLOG_HEADER#* - }"
if [ "$CHLOG_DATE" != $(date -I) ]
then
    echo "Changelog date was not updated"
    exit 1
fi
if [ -n "$(git status -u --porcelain)" ]
then
    echo "Git work directory is not clean"
    exit 1
fi
git tag "v$VER"
