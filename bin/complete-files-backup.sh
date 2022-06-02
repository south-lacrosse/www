#!/bin/bash

# Backup all Wordpress files (including WordPress, plugins, themes and media)
# to the home directory

BACKUP_FILE="$HOME/wp-files-$(date +%F-%H%M%S).tar.gz"
cd $(dirname "$0")

echo "Backing up all Wordpress files (including WordPress, plugins, themes and media) to $BACKUP_FILE"

# run /bin/tar so this works on Windows (tar.exe fails)
/bin/tar -czf $BACKUP_FILE --exclude-from=rsync-excludes.txt --exclude=../.git --exclude=../sub ../
