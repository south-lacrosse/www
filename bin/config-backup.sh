#!/bin/bash

# Backup the WordPress config to the south-lacrosse/wordpress repo if changed

echo 'Backing up wp-config.php (if needed)'
COMMAND='cp ../../../wp-config.php .'
source $(dirname "$0")/wp-git-repo.sh
