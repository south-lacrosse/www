#!/bin/bash
cd $(dirname "$0")/../sub/dmarc-srg || exit
php utils/fetch_reports.php
