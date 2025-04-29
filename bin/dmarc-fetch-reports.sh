#!/bin/bash
cd $(dirname "$0")/../sub/dmarc-srg || exit
/opt/alt/php83/usr/bin/php utils/fetch_reports.php
