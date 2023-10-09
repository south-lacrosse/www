<?php

use Semla\Utils\Util;

$fixtures_datetime = get_option('semla_fixtures_datetime');
if ($fixtures_datetime) { ?>
<p><b>Last update:</b> <?= $fixtures_datetime ?></p>
<?php } ?>
<p><?php if ($fixtures_sheet_id) { ?>
The fixtures will be loaded from <a href="<?= Util::get_fixtures_sheet_url($fixtures_sheet_id) ?>edit">the Fixtures Google Sheet</a>.
<?php } ?>
See also <a href="https://github.com/south-lacrosse/www-dev/blob/main/docs/fixtures-sheet-format.md">Information on the Format of the Google Sheet</a>.</p>
<?php
if (current_user_can('manage_options'))  : ?>
<p>To use a different Google Sheet, or change the points per win/draw/loss, go to the <a href="?page=semla_settings">Settings Page</a>.</p>
<?php else : ?>
<p>Note: the Google Sheet used, and points per win/draw/loss, can only be changed by Administrators.</p>
<?php endif;
if ($fixtures_sheet_id) {?>
<p>Only update everything at the beginning of the season, or if you have changed any of the divisions or teams.</p>
<p><a class="button-primary" href="<?= wp_nonce_url($page_and_tab .'&action=update','semla_update') ?>">Update fixtures & flags</a>
<a class="button-secondary" href="<?= wp_nonce_url($page_and_tab .'&action=update-all','semla_update-all') ?>">Update everything</a></p>
<h2>In Case Of Problems</h2>
<p>If there are problems with the last update of the fixtures (and it only works on the
last update), you can revert to the previous fixtures using the button below, with the caveat
that it won't work if the teams or divisions were changed. You should really fix the spreadsheet
by replacing that with a working version (File->Version History->See Version History), but
this method quickly fixes any bad data so the website is functioning while you fix the
problem (and hopefully testing using the staging site before loading again).</p>
<p><a class="button-secondary" href="<?= wp_nonce_url($page_and_tab .'&action=revert','semla_revert') ?>">Revert fixtures & flags</a></p>
<?php }
