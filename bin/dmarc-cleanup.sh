#!/bin/bash

# DMARC Housekeeping
#  - dmarc@southlacrosse.org.uk email address
#  - dmarc tables

cd $(dirname "$0")/../sub/dmarc-srg || exit
/opt/alt/php83/usr/bin/php utils/mailbox_cleaner.php
php utils/reportlog_cleaner.php
php utils/reports_cleaner.php
echo DMARC cleanup completed
