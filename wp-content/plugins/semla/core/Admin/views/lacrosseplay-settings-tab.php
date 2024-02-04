<?php

use Semla\Admin\Admin_Menu;
use Semla\Data_Access\Lacrosse_Play_Gateway;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	Admin_Menu::validate_nonce('semla_lacrosseplay_settings');
}

if (isset($_POST['competition_id'])) {
	$new = trim($_POST['competition_id']);
	if ($new !== $lp_competition_id) {
		if (!preg_match('/^\d+$/', $new)) {
			$lp_competition_id = esc_attr($new);
			Admin_Menu::dismissible_error_message('The Competition ID is in an invalid format. Please enter a new value.');
		} else {
			$lp_competition_id = $new;
			$name = (new Lacrosse_Play_Gateway())->get_competition_name((int)$lp_competition_id);
			if (is_wp_error($name)) {
				Admin_Menu::dismissible_error_message('Error in checking if the Competition ID exists: ' . $res->get_error_message());
			} elseif ( $name === false ) {
				Admin_Menu::dismissible_error_message('The Competition ID does not exist');
			} else {
				update_option('semla_lp_competition_id', $lp_competition_id, 'no');
				Admin_Menu::dismissible_success_message('The Competition ID was updated, the new competition name is ' .
					esc_html($name) . '. Now update the Fixtures.');
			}
		}
	}
}
?>
<div class="notice notice-warning inline">
<p><b>Important:</b> If you are changing the Competition ID for a new season make sure that the previous
season's data has been archived by running the <a href="https://github.com/south-lacrosse/www-dev/blob/main/docs/end-season.md">End of Season processing</a>.</p>
</div>
<form method="post">
	<table class="form-table" role="presentation"><tbody>
	<tr>
		<th scope="row"><label for="competition_id">Competition ID</label></th>
		<td><input name="competition_id" type="number" step="1" min="1" id="competition_id" value="<?= $lp_competition_id ?>" class="small-text"></td>
	</tr>
	</tbody></table>
<p class="submit">
	<?php wp_nonce_field('semla_lacrosseplay_settings') ?>
	<input type="submit" name="update" id="submit" class="button button-primary" value="Save Changes">
</p>
