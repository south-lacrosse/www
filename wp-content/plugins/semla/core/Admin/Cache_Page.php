<?php
namespace Semla\Admin;

use Semla\Cache;

/**
 * Delete caches if needed
 */
class Cache_Page {
	const PAGE_URL = '?page=semla_cache';

	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
<h1>Cache</h1>
<?php
		if (isset( $_GET[ 'action' ] )) {
			switch ($_GET[ 'action' ]) {
				case 'clear_cache':
					Admin_Menu::validate_nonce('semla_clear_cache');
					Cache::clear_cache();
					do_action( 'litespeed_purge', 'semla_data' );
					Admin_Menu::dismissible_success_message('The current tables/fixtures cache has been successfully cleared');
					break;
				case 'clear_cachehist':
					Admin_Menu::validate_nonce('semla_clear_cachehist');
					Cache::clear_cache('hist');
					do_action( 'litespeed_purge_posttype', 'history' );
					Admin_Menu::dismissible_success_message('The history cache has been successfully cleared');
					break;
				case 'clear_menu_cache':
					if (current_theme_supports('semla')) {
						Admin_Menu::validate_nonce('semla_clear_menu_cache');
						do_action('semla_clear_menu_cache');
						do_action( 'litespeed_purge_all' );
						Admin_Menu::dismissible_success_message('The menu cache has been successfully cleared');
					}
			}
		}

?>
<div class="postbox">
	<div class="inside">
		<p>In order to speed up processing we cache various items. In case this causes problems,
			for example when a program is changed but that change isn't seen, then you may need
			to clear the internal caches using the buttons below.</p>
	</div>
</div>
<h2>Clear SEMLA Caches</h2>
<p><a class="button-secondary" href="<?= wp_nonce_url(self::PAGE_URL . '&action=clear_cache', 'semla_clear_cache') ?>">Current tables/fixtures etc.</a>
<a class="button-secondary" href="<?= wp_nonce_url(self::PAGE_URL . '&action=clear_cachehist', 'semla_clear_cachehist') ?>">History dropdowns</a>
<?php if (current_theme_supports('semla')) { ?>
<a class="button-secondary" href="<?= wp_nonce_url(self::PAGE_URL . '&action=clear_menu_cache', 'semla_clear_menu_cache') ?>">Menu</a>
<?php } ?>
</p>
<?php if (defined('LSCWP_V')) { ?>
<p>Note: The appropriate LiteSpeed cache will also be purged, so for current fixtures all related pages
are purged, and for history dropdowns all history pages are purged, and if the
menu cache is cleared then the entire cache is purged.</p>
</div>
<?php
		}
    }
}
