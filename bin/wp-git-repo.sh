#!/bin/bash

# This script goes to the ~/wordpress-config dir, which should contain the repo
# git@github.com:south-lacrosse/wordpress-config.git. If it doesn't exist then
# the repo will be cloned.

# Then run $COMMAND from that directory, and update the repository if needed. If
# commands need to access the WordPress directory they should use $WP_DIR.

# Must only be called from other scripts

[[ -z $COMMAND ]] && { echo 'Error: No command specified'; exit 1; }

BIN=$(dirname "${BASH_SOURCE[0]}")
WP_DIR=$(realpath "$BIN/..")

if [[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" $WP_DIR/wp-config.php) ]]; then
	echo 'Error: This script must only be run on the production website'
	exit 1
fi

if [[ ! -d ~/wordpress-config ]]; then
	echo 'No wordpress-config repo...cloning'
	git clone git@github.com:south-lacrosse/wordpress-config.git ~/wordpress-config || exit 1
	chmod 700 ~/wordpress-config
fi

cd ~/wordpress-config || exit 1
if [[ -z $(git remote -v|grep git@github.com:south-lacrosse/wordpress-config.git) ]]; then
	echo 'Error: ~/wordpress-config dir is not set up for the correct remote repository'
	exit 1
fi

eval "$COMMAND"

if [[ $(git status --porcelain) ]]; then
	echo 'Local repository changed, commiting to git and pushing to remote'
	git add . || exit 1
	git commit -m "Automated commit on $(date +%F-%H%M%S)" || exit 1
	git push || exit 1
	echo 'Repository successfully updated'
else
	echo 'Nothing changed'
fi
