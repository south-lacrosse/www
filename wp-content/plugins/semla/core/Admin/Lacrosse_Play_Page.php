<?php
namespace Semla\Admin;

use Semla\Data_Access\Lacrosse_Play_Config;
use Semla\Data_Access\Lacrosse_Play_Gateway;

/**
 * Admin page to update Fixtures from England Lacrosse's LacrossePlay API.
 * Possible 2 tabs - update, settings (for admins)
 */
class Lacrosse_Play_Page {
	const OPTIONS = ['fixtures' => 'Fixtures', 'tables' => 'League tables', 'flags' => 'Flags'];

	public static function render_page() {
		if (!current_user_can('manage_semla')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
	<h1>SEMLA LacrossePlay Fixtures</h1>
<?php
		if (!class_exists(Lacrosse_Play_Config::class)) {
			Admin_Menu::notice('notice-error',
				'LacrossePlay configuration file Lacrosse_Play_Config.php does not exist. '
				. 'Please contact the Webmaster.');
		} else {
			$lp_competition_id = get_option('semla_lp_competition_id');
			if (!current_user_can('manage_options')) {
				self::update_actions();
				require __DIR__ . '/views/lacrosseplay-update-tab.php';
			} else {
				// only administrators see settings
				$tabs = ['update' => 'Update Fixtures','settings' => 'Settings'];
				$active_tab = Admin_Menu::render_tabs('semla', $tabs );
				$page_and_tab = "?page=semla&tab=$active_tab";
				switch ($active_tab) {
					case 'update':
						self::update_actions($lp_competition_id);
						require __DIR__ . '/views/lacrosseplay-update-tab.php';
						break;
					case 'settings':
						require __DIR__ . '/views/lacrosseplay-settings-tab.php';
						break;
				}
			}
		}
	?>
</div>
		<?php
	}

	private static function update_actions() {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST'
		|| !isset($_POST['action'])) {
			return;
		}
		Admin_Menu::validate_nonce('semla_update');

		if ($_POST['action'] === 'Update Selected') {
			$data = array_intersect_key( $_POST, self::OPTIONS );
			if (empty($data)) return;
			$load_competition_data = false;
		} else if ($_POST['action'] === 'Update Everything') {
			$data = array_map('__return_true', self::OPTIONS);
			$load_competition_data = true;
		} else {
			return;
		}

		$gateway = new Lacrosse_Play_Gateway();
		$result = $gateway->update($load_competition_data, $data);
		if (is_wp_error($result)) {
			Admin_Menu::dismissible_error_message('Update failed (no data has been changed):<br>'
				. implode('<br>', $result->get_error_messages()));
		} else {
			Admin_Menu::dismissible_success_message('Update successful:<br>'
				. implode('<br>', $result));
			self::purge_lscache();
		}
	}

	/**
	 * Purging lscache here as the WP-CLI and web based versions of Litespeed
	 * work differently, purge_url works, but purge doesn't, so we handle the
	 * purging differently for each.
	 */
	private static function purge_lscache() {
		// remove all pages with the semla_data block from the cache
		do_action('litespeed_purge_url', '/');
		do_action('litespeed_purge', 'semla_data');
		// if purging the tag doesn't work then purge entire cache with:
		// do_action( 'litespeed_purge_all' );
	}
}
