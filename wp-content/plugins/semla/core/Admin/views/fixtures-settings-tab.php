<?php

use Semla\Admin\Admin_Menu;
use Semla\Utils\Net_Util;
use Semla\Utils\Util;

$POINTS = ['W' => 'Win', 'D' => 'Draw', 'L' => 'Loss',
		'C' => 'Conceded', 'C24' => 'Conceded within 24 hours'];
$TIEBREAK_OPTIONS = ['P' => 'Points', 'GAvg' => 'Points and Goal Average'];
?>
<div class="notice notice-warning inline">
<p><b>Important:</b> If you are changing the Fixtures Google Sheet ID for a new season make sure that the previous
season's data has been archived by running the <a href="https://github.com/south-lacrosse/www-dev/blob/main/docs/end-season.md">End of Season processing</a>.</p>
</div>
<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	Admin_Menu::validate_nonce('semla_settings_tab');
}
$fixtures_sheet_id = get_option('semla_fixtures_sheet_id');
if (isset($_POST['fixtures_sheet_id'])) {
	$new = trim($_POST['fixtures_sheet_id']);
	if ($new !== $fixtures_sheet_id) {
		$fixtures_sheet_id = $new;
		if (!preg_match('/^[a-zA-Z0-9\-_]*$/', $fixtures_sheet_id)) {
			$fixtures_sheet_id = esc_attr($fixtures_sheet_id);
			Admin_Menu::dismissible_error_message('The Fixtures Sheet ID is in an invalid format. Please enter a new value.');
		} else {
			$sheet_url = Util::get_fixtures_sheet_url($fixtures_sheet_id) . 'edit';
			$res = Net_Util::url_exists($sheet_url);
			if (is_wp_error($res)) {
				Admin_Menu::dismissible_error_message('Error in checking if Spreadsheet exists: ' . $res->get_error_message());
			} elseif ( $res === true ) {
				update_option('semla_fixtures_sheet_id', $fixtures_sheet_id, 'no');
				Admin_Menu::dismissible_success_message('The Fixtures Sheet ID was updated. Now update the Fixtures from the SEMLA Admin Menu');
			} elseif (!$res) {
				Admin_Menu::dismissible_error_message('The Fixtures Sheet at ' . $sheet_url
					. ' does not exist');
			}
		}
	}
}
$points = get_option('semla_points');
if (isset($_POST['W'])) {
	$points_new = [];
	$numeric = true;
	foreach ($POINTS as $key => $val) {
		$arg = $_POST[$key];
		if (is_numeric($arg)) {
			$points_new[$key] = (int) $arg;
		} else {
			$numeric = false;
			$points_new[$key] = $arg;
		}
	}
	if ($numeric && (!$points || array_diff_assoc($points, $points_new))) {
		update_option('semla_points', $points_new, 'no');
		Admin_Menu::dismissible_success_message('Points values updated');
		$points = $points_new;
	}
}
$tiebreak_option = get_option('semla_tiebreak');
if (isset($_POST['tiebreak'])) {
	if ($_POST['tiebreak'] !== $tiebreak_option) {
		update_option('semla_tiebreak', $_POST['tiebreak'], 'no');
		Admin_Menu::dismissible_success_message('Tiebreak option updated');
		$tiebreak_option = $_POST['tiebreak'];
	}
}
?>
<form method="post">
	<table class="form-table" role="presentation"><tbody>
	<tr>
		<th scope="row"><label for="fixtures_sheet_id">Fixtures Google Sheet ID</label></th>
		<td><input name="fixtures_sheet_id" type="text" id="fixtures_sheet_id" value="<?= $fixtures_sheet_id ?>" class="regular-text" minlength="10">
		<p>You can get the id from the address of the sheet, which will be something like https://docs.google.com/spreadsheets/d/{id here}/edit. Also make sure the Sheet is shared so anyone can view.</p></td>
	</tr>
	</tbody></table>
	<h2>Points</h2>
	<table class="form-table" role="presentation"><tbody>
	<?php foreach ($POINTS as $key => $val) : ?>
	<tr>
		<th scope="row"><label for="<?= $key ?>"><?= $val ?></label></th>
		<td><input name="<?= $key ?>" type="number" id="<?= $key ?>" value="<?php if (isset($points[$key])) printf('%d',$points[$key]) ?>" class="small-text">
	</tr>
	<?php endforeach;?>
	</tbody></table>
	<h2>Tiebreak For League Winners/Relegation</h2>
	<table class="form-table" role="presentation"><tbody>
	<tr>
		<th scope="row"><label for="tiebreak">Trigger when equal</label></th>
		<td>
			<?php foreach ($TIEBREAK_OPTIONS as $option => $label) : ?>
				<p><label>
					<input name="tiebreak" type="radio" value="<?= $option ?>" class="tog"<?php checked( $option, $tiebreak_option ); ?>>
					<?= $label ?></label>
				</p>
			<?php endforeach;?>
		</td>
	</tr>
	</tbody></table>
	<p class="description">Note: tiebreaks are evaluated on head-to-head points/goal difference/goals, then goal difference/goals.</p>
	<p class="submit">
		<?php wp_nonce_field('semla_settings_tab') ?>
		<input type="submit" class="button-primary" name="update" value="Save Changes" />
	</p>
</form>
