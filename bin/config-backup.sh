#!/bin/bash

# Backup the WordPress config & .htaccess to the south-lacrosse/wordpress repo if changed

echo 'Backing up wp-config.php and .htaccess (if needed)'
COMMAND='cp ../../../{wp-config.php,.htaccess} .'
source $(dirname "$0")/wp-git-repo.sh
