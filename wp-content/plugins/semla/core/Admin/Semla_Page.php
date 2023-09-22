<?php
namespace Semla\Admin;

use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Data_Access\Fixtures_Sheet_Gateway;
use Semla\Data_Access\Tiebreaker_Gateway;

/**
 * Update Fixtures admin page.
 * 3 tabs - update, tiebreakers, flags fixtures formulas
 */
class Semla_Page {

	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
	<h1>SEMLA Admin Menu</h1>
<?php
		$fixtures_sheet_id = get_option('semla_fixtures_sheet_id');
		if (!$fixtures_sheet_id) {
			Admin_Menu::dismissible_error_message('No Google Fixtures Sheet specified yet.');
		}

		if (isset( $_GET[ 'action' ] )) {
			switch ($_GET[ 'action' ]) {
				case 'update':
				case 'update-all':
					Admin_Menu::validate_nonce('semla_'.$_GET[ 'action' ]);
					$result = (new Fixtures_Sheet_Gateway())->update( $_GET[ 'action' ]);
					if (is_wp_error($result)) {
						Admin_Menu::dismissible_error_message('Update failed (no data has been changed):<br>'
							. implode('<br>', $result->get_error_messages()));
					} else {
						Admin_Menu::dismissible_success_message('Update successful:<br>'
							. implode('<br>', $result));
						self::purge_lscache();
					}
					break;
				case 'revert':
					Admin_Menu::validate_nonce('semla_revert');
					$result = Fixtures_Sheet_Gateway::revert();
					if (is_wp_error($result)) {
						Admin_Menu::dismissible_error_message('Revert failed (no data has been changed):<br>'
							. implode('<br>', $result->get_error_messages()));
					} else {
						Admin_Menu::dismissible_success_message($result);
						self::purge_lscache();
					}
					break;
			}
		}
		$active_tab = Admin_Menu::render_tabs('semla',
			['update' => 'Update Fixtures', 'tiebreaker' => 'Tiebreakers','formulas' => 'Flags Fixtures Formulas']);
		$page_and_tab = "?page=semla&tab=$active_tab";
?>
	<div id="poststuff">
		<?php
			switch ($active_tab) {
				case 'tiebreaker':
					Tiebreaker_Gateway::show_tiebreakers();
					break;
				case 'update':
					require __DIR__ . '/views/semla-update-tab.php';
					break;
				case 'formulas':
					$rows = $fixtures_sheet_id ? Cup_Draw_Gateway::get_cup_fixtures_for_sheet() : false;
					require __DIR__ . '/views/semla-formulas-tab.php';
					break;
			}
		?>
	</div>
</div>
		<?php
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

	public static function load() {
		if ( ! current_user_can( 'manage_semla' ) ) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		// remove args from query string in browser so update doesn't re-run. Needs
		// to be before render_page as the filter will already have run
		add_filter('removable_query_args', function($args) {
			return array_merge($args, ['action','_wpnonce']);
		});
	}
}
