#!/bin/sh
#
# Install Calibre2OPDS from GitLab.com project.
#
# Requires curl, jq, tar, rsync.
#
# By default only compares, add option `--install` to actually copy files.
#
set -e
set -o pipefail

###
### Tweak following parameters if application location changes
###
PROJECT_ID=51044862   # GitLab.com project ID
APP_NAME=calibre_opds # Destination app directory name

### Other settings
GITLAB_API_URL=https://gitlab.com/api/v4/
TEMP_DIR=/tmp/install-calibre2opds.$$
TEMP_TARBALL="$TEMP_DIR/calibre_opds.tar.gz"
TEMP_APPDIR="$TEMP_DIR/calibre_opds"

### Check parameters
RSYNC_OPTS=--dry-run
APPS_DIR=""
for arg in "$@"
do
    if [ "${arg##--}" != "$arg" ]
    then
        case "$arg" in
        "--install")
           RSYNC_OPTS=""
           ;;
        *)
           echo "Unknown option $arg" >&2
           exit 1
           ;;
        esac
        continue
    fi
    if [ -n "$APPS_DIR" ]
    then
        echo "Too many arguments" >&2
        exit 1
    fi
    APPS_DIR="$arg"
    if [ ! -d "$APPS_DIR" ]
    then
        echo "Applications directory '$arg' is not a directory" >&2
        exit 1
    fi
    APP_OWNER=$(stat -c '%U:%G' "$APPS_DIR")
done
if [ -z "$APPS_DIR" ]
then
    echo "Usage: $0 [--install] <apps-dir>"
    exit 1
fi

### Installation
mkdir -p "$TEMP_DIR"
trap "rm -rf \"$TEMP_DIR\"" EXIT

tarball_url=$(curl -sL "$GITLAB_API_URL/projects/$PROJECT_ID/releases/permalink/latest" | jq -r '.assets.links[0].url')
curl -sL -o "$TEMP_TARBALL" "$tarball_url"
tar -xzf "$TEMP_TARBALL" --no-same-permissions -C "$TEMP_DIR"
chown -R "$APP_OWNER" "$TEMP_APPDIR"
rsync -av --delete $RSYNC_OPTS "$TEMP_APPDIR/" "$APPS_DIR/$APP_NAME/"
if [ -z "$RSYNC_OPTS" ]
then
    echo "##################################################"
    echo "##          APPLICATION IS INSTALLED!           ##"
    echo "## Don't forget to disable and reenable the app ##"
    echo "## for NC to detect the update.                 ##"
    echo "##################################################"
fi
