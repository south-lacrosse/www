<?php
namespace Semla\Admin;
/**
 * SEMLA Settings admin page
 */
class Settings_Page {

	public static function render_page() {
		if (!current_user_can('manage_options'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
	<h1>SEMLA Settings</h1>
<?php
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			Admin_Menu::validate_nonce('semla_settings');
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
?>
	<form method="post">
		<table class="form-table" role="presentation"><tbody>
		<tr>
			<th scope="row"><label for="gapi_key">Google API key</label></th>
			<td><input name="gapi_key" type="text" id="gapi_key" value="<?= $gapi_key ?>" class="regular-text" minlength="10">
			<p>You should already have an API key, but if not <a href="https://developers.google.com/maps/documentation/javascript/get-api-key">follow these instructions</a></p>
			<p>And don't forget to <a href="https://github.com/south-lacrosse/www-dev/blob/main/docs/webmaster-tasks.md#rotate-google-api-key">rotate the API key</a> periodically.</p>
			</td>
		</tr>
		</tbody></table>
		<p class="submit">
			<?php wp_nonce_field('semla_settings') ?>
			<input type="submit" class="button-primary" name="update" value="Save Changes" />
		</p>
	</form>
</div>
<?php
	}
}
