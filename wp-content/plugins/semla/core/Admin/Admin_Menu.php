<?php
namespace Semla\Admin;
/**
 * Admin menu functions
 */
class Admin_Menu {
	public static function init() {
		add_action('admin_menu', function() {
			$hook_suffix = add_menu_page('SEMLA Admin Page', 'SEMLA', 'manage_semla', 'semla',
				[Semla_Page::class, 'render_page'],
				'dashicons-shield-alt',	30);
			add_action( 'load-' . $hook_suffix, [Semla_Page::class, 'load'] );

			// $hook_suffix = add_submenu_page('semla', 'Team Abbreviations', 'Team Abbreviations', 'manage_semla',
			// 	'semla_team_abbrev', [Team_Abbrev_Page::class, 'render_page'] );
			// add_action( 'load-' . $hook_suffix, [Team_Abbrev_Page::class, 'handle_actions'] );

			// $hook_suffix = add_submenu_page('semla', 'Winners', 'Winners', 'manage_semla',
			// 	'semla_winner', [Winner_Page::class, 'render_page'] );
			// add_action( 'load-' . $hook_suffix, [Winner_Page::class, 'handle_actions'] );

			// $hook_suffix = add_submenu_page('semla', 'Leagues', 'Leagues', 'manage_semla',
			// 	'semla_cg', [Competition_Group_Page::class, 'render_page'] );
			// add_action( 'load-' . $hook_suffix, [Competition_Group_Page::class, 'handle_actions'] );

			// $hook_suffix = add_submenu_page('semla', 'Competitions', 'Competitions', 'manage_semla',
			// 	'semla_competition', [Competition_Page::class, 'render_page'] );
			// add_action( 'load-' . $hook_suffix, [Competition_Page::class, 'handle_actions'] );

			add_submenu_page('semla', 'Settings', 'Settings', 'manage_semla',
				'semla_settings', [Settings_Page::class, 'render_page'] );

			$hook_suffix = add_submenu_page('semla', 'Cache', 'Cache', 'manage_semla',
				'semla_cache', [Cache_Page::class, 'render_page'] );
			add_action( 'load-' . $hook_suffix, [self::class, 'remove_action_query_arg'] );


			// The rest are only allowed by administrators
			add_options_page('SMTP', 'SMTP', 'manage_options','semla_smtp',
				[SMTP_Page::class, 'render_page'] );
			add_management_page( 'PHP Info', 'PHP Info', 'manage_options', 'semla_phpinfo',
				[Php_Info_Page::class, 'render_page']);
			// Show debug log
			$hook_suffix = add_management_page( 'Debug Log', 'Debug Log', 'manage_options', 'semla_debug_log',
				[Debug_Log_Page::class, 'render_page']);
			add_action( 'load-' . $hook_suffix, [self::class, 'remove_action_query_arg'] );
		});
	}

	// Utility functions
	/**
	 * Render tabs, and return active tab, page and tab
	 */
	public static function render_tabs($page, $tabs) {
		if (isset( $_GET[ 'tab' ] )) {
			$active_tab = $_GET[ 'tab' ];
			if (!isset($tabs[$active_tab])) {
				$active_tab = array_key_first($tabs);
			}
		} else {
			$active_tab = array_key_first($tabs);
		}
		?>
	<h2 class="nav-tab-wrapper"><?php
		foreach ($tabs as $tab_slug => $tab) {
			echo '<a href="?page='. $page . '&tab=' . $tab_slug . '" class="nav-tab'
				. ($active_tab === $tab_slug ? ' nav-tab-active' : '') . '">' . $tab . '</a>';
		}?>
	</h2>
<?php
		return $active_tab;
	}

	public static function dismissible_error_message($message) {
		?>
<div class="notice notice-error is-dismissible">
<p><?= $message ?></p>
</div>
<?php
	}

	public static function dismissible_success_message($message) {
		?>
<div class="notice notice-success is-dismissible">
<p><?= $message ?></p>
</div>
<?php
	}

	public static function validate_nonce($action) {
		if ( ! isset( $_REQUEST['_wpnonce'] ) 
			|| ! wp_verify_nonce( $_REQUEST['_wpnonce'], $action ) ) {
		   print 'The link you followed has expired.';
		   exit;
		}
	}

	public static function remove_action_query_arg() {
		// remove args from query string in browser so update doesn't re-run. Needs
		// to be before render_page as the filter will already have run 
		add_filter('removable_query_args', function($args) {
			return array_merge($args, ['action','_wpnonce']);
		});						
	}
}
