#!/bin/bash

# Backup the media dir to the GitHub south-lacrosse/media repo if changed

cd $(dirname "$0")/../media || exit 1
if [[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" ../wp-config.php) ]]; then
	echo 'Error: This script must only be run on the production website'
	exit 1
fi

echo 'Media backup (if needed)'

if [[ -z $(git remote -v|grep git@github.com:south-lacrosse/media.git) ]]; then
	echo 'Error: media dir is not set up for the correct remote repository'
	exit 1
fi

if [[ $(git status --porcelain) ]]; then
	echo 'Media dir changed, commiting to git and pushing to remote'
	git add . || exit
	git commit -m "Automated commit $(date +%F-%H%M%S)" || exit
	git push || exit
	echo 'Repository successfully updated.'
else
	echo 'Media dir is unchanged.'
fi
