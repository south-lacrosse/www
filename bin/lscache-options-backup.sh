#!/bin/bash

# Backup the LiteSpeed cache options to the south-lacrosse/wordpress repo if changed

echo 'Backing up the LiteSpeed cache options (if needed)'
COMMAND='wp litespeed-option export --filename=lscache_wp_options.txt --path=$WP_DIR'
source $(dirname "$0")/wp-git-repo.sh
