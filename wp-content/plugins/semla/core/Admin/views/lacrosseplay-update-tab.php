<?php

if (!$lp_competition_id) {
	echo '<p>Cannot update the fixtures as the Competition ID is not set. Please '
		. (current_user_can('manage_options')
			? 'go to the to the <a href="?page=semla&tab=settings">Settings Tab</a>.</p>'
			: 'contact an Administrator to set it.')
		. "</p>\n";
	return;
}

?>
<p>The fixtures will be loaded for Competition ID <?= $lp_competition_id ?>.</p>
<?php
$fixtures_datetime = get_option('semla_lacrosseplay_datetime');
if ($fixtures_datetime) { ?>
<p><b>Last update:</b> <?= $fixtures_datetime ?></p>
<?php } ?>
<p>The update will be quicker and use less resources if you only select the data you have changed.<p>
<p><i>Update Everything</i> if you have changed any of the competitions, clubs, or teams, and at the beginning of the season.</p>
<form method="post" id="lacrosseplay">
<?php
foreach (self::OPTIONS as $option => $label) : ?>
<p><label>
	<input name="<?= $option ?>" type="checkbox" value="1" checked>
	<?= $label ?></label>
</p>
<?php endforeach; ?>
<p class="submit">
	<?php wp_nonce_field('semla_update') ?>
	<input type="submit" name="action" class="button button-primary" value="Update Selected">
	<input type="submit" name="action" class="button button-secondary" value="Update Everything">
</p>
