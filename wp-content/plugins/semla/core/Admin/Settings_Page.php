<?php
namespace Semla\Admin;

use Semla\App_Public;
use Semla\Utils\Net_Util;
use Semla\Utils\Util;
/**
 * SEMLA Settings admin page
 */
class Settings_Page {
	const POINTS = ['W' => 'Win', 'D' => 'Draw', 'L' => 'Loss',
		'C' => 'Conceded', 'C24' => 'Conceded within 24 hours'];

	public static function render_page() {
		if (!current_user_can('manage_options'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
	<h1>SEMLA Settings</h1>
<?php
		if ( strtolower( $_SERVER['REQUEST_METHOD'] ) === 'post' ) {
			Admin_Menu::validate_nonce('semla_settings');
		}
		$fixtures_sheet_id = get_option('semla_fixtures_sheet_id');
		if (isset($_POST['fixtures_sheet_id'])) {
			$new = trim($_POST['fixtures_sheet_id']);
			if ($new != $fixtures_sheet_id) {
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
		$gapi_key = get_option('semla_gapi_key');
		if (isset($_POST['gapi_key'])) {
			$new = trim($_POST['gapi_key']);
			if ($new != $gapi_key) {
				$gapi_key = $new;
				if (!preg_match('/^[a-zA-Z0-9\-]*$/', $gapi_key)) {
					$gapi_key = esc_attr($gapi_key);
					Admin_Menu::dismissible_error_message('The Google API Key is in an invalid format. Please enter a new value.');
				} else {
					update_option('semla_gapi_key', $gapi_key, 'no');
					Admin_Menu::dismissible_success_message('Google API Key updated');
					do_action( 'litespeed_purge', ['semla_data', 'semla_calendar']);
				}
			}
		}
		$points = get_option('semla_points');
		if (isset($_POST['W'])) {
			$points_new = [];
			$numeric = true;
			foreach (self::POINTS as $key => $val) {
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

?>
	<form method="post">
		<table class="form-table" role="presentation"><tbody>
		<tr>
			<th scope="row"><label for="fixtures_sheet_id">Fixtures Google Sheet ID</label></th>
			<td><input name="fixtures_sheet_id" type="text" id="fixtures_sheet_id" value="<?= $fixtures_sheet_id ?>" class="regular-text" minlength="10">
			<p>You can get the id from the address of the sheet, which will be something like https://docs.google.com/spreadsheets/d/{id here}/edit. Also make sure the Sheet is shared so anyone can view.</p></td>
		</tr>
		<tr>
			<th scope="row"><label for="gapi_key">Google API key</label></th>
			<td><input name="gapi_key" type="text" id="gapi_key" value="<?= $gapi_key ?>" class="regular-text" minlength="10">
			<p>You should already have an API key, but if not <a href="https://developers.google.com/maps/documentation/javascript/get-api-key">follow these instructions</a></p></td>
		</tr>
		</tbody></table>
		<h2>Points</h2>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach (self::POINTS as $key => $val) : ?>
		<tr>
			<th scope="row"><label for="<?= $key ?>"><?= $val ?></label></th>
			<td><input name="<?= $key ?>" type="number" id="<?= $key ?>" value="<?php if (isset($points[$key])) printf('%d',$points[$key]) ?>" class="small-text">
		</tr>
		<?php endforeach;?>
		</tbody></table>
		<p class="submit">
			<?php wp_nonce_field('semla_settings') ?>
			<input type="submit" class="button-primary" name="update" value="Update options" />
		</p>
	</form>
</div>
<?php
	}
}
