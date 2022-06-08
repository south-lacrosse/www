#!/bin/bash

# Setup all file permisions on a WordPress install

cd $(dirname "$0")/..

# assume php is run as a cgi, so only the owner needs access
find . -not \( -path './.git' -prune \) -type f -name '*.php' -exec chmod 600 {} \;
find . -not \( -path './.git' -prune \) -type f -name '*.sh' -exec chmod 700 {} \;

find . -not \( -path './.git' -prune \) -type d -exec chmod 755 {} \;
# need to ignore files we've already set to 600, but everything else needs to be
# world readable so it can be read by the webserver
find . -not \( -path './.git' -prune \) -type f -not -name '*.php' -not -name '*.sh' -exec chmod 644 {} \;

# If we have .pl files, we need to make sure they are executable
#find . -not \( -path './.git' -prune \) -type f -name '*.pl' -exec chmod +x {} \;

chmod 700 bin
chmod 700 .git
test -f .gitconfig && chmod 600 .gitconfig
