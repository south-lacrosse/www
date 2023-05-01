<?php
namespace Semla;

use Semla\Admin\Admin_Menu;
use Semla\Admin\User_Profile_Extras;
/**
 * Handling initialisation for the admin pages
 */
class App_Admin {
	public static function init() {
		// get rid of useless stuff added to page
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('admin_print_styles', 'print_emoji_styles');
		add_filter('emoji_svg_url', '__return_false'); // stops prefetch being added
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// stop queries to count comments, which are called when menu is displayed
		add_filter('wp_count_comments', function() {
			return (object) [
				'approved'       => 0,
				'moderated'      => 0,
				'spam'           => 0,
				'trash'          => 0,
				'post-trashed'   => 0,
				'total_comments' => 0,
				'all'            => 0,
			];
		});

		// add our admin menu pages
		Admin_Menu::init();

		// Defer some setup until we know what screen this is
		add_action('current_screen', function(\WP_Screen $screen) {
			switch ($screen->base) {
				case 'dashboard' :
					self::init_dashboard();
					break;
				// case 'post' :
				// 	self::init_post();
				// 	break;
				case 'user':
				case 'user-edit':
				case 'profile':
					User_Profile_Extras::init_admin();
					break;
				case 'users':
					User_Profile_Extras::init_users();
					break;
				case 'edit-comments':
				case 'options-discussion':
					wp_redirect(admin_url());
					exit;
				case 'options-reading':
					// Remove the blog_public option from the Reading screen, as we
					// override it by WP_ENVIRONMENT_TYPE. We can't actually remove the option,
					// so we do the next best thing and hide it!
					add_action('admin_head', function() {
						echo '<style>.option-site-visibility,#tab-link-site-visibility{display:none}</style>' . "\n";
					});
					break;
			}
			// Modify heartbeat. Left on for now, but uncomment one or both options below
			//  if heartbeat causes too much server usage

			// don't use the heartbeat except for edit posts/cpt
			// if ($screen->base !== 'post') {
			//  wp_deregister_script('heartbeat');
			// }
			// Change heartbeat frequency, defaults to 60s
			// add_filter( 'heartbeat_settings', function($settings) {
			// 	$settings['interval'] = 120;
			// 	return $settings;
			// }, 99, 1 );
		});

		add_action ('enqueue_block_editor_assets',  [self::class, 'enqueue_block_editor_assets']);

		// Removes comments/discussion from admin menu
		add_action('admin_menu', function() {
			remove_menu_page( 'edit-comments.php' );
			remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		});

		add_action('admin_head', function() {
			echo '<style>.wp-admin #wpadminbar #wp-admin-bar-semla-help>.ab-item:before{'
				.'content:"\f223";top:2px;}</style>' . "\n";
		});

		add_filter( 'attachment_link', '__return_empty_string' );

		// if clubs have changed then purge pages/rest routes which use club data
		add_action('save_post_clubs', function() {
			do_action('litespeed_purge', 'semla_clubs');
		});
	}

	public static function enqueue_block_editor_assets() {
		$plugin_dir = dirname(__DIR__);
		$asset_file = include( $plugin_dir . '/blocks-core/core.asset.php');
		if ($asset_file) {
			$dependencies = $asset_file['dependencies'];
			// by adding wp-edit-{screen} as a dependency we make sure our
			// script runs after the core blocks have been created, so we
			// can then remove or change them if we want
			$edit_dependency = 'wp-edit-' . get_current_screen()->base;
			if (!in_array($edit_dependency, $dependencies)) {
				$dependencies[] = $edit_dependency;
			};
			$base_url = plugins_url('/', __DIR__);
			wp_enqueue_script('semla-blocks-core',
				$base_url . 'blocks-core/core.js', $dependencies, $asset_file['version']);
			wp_enqueue_style('semla-blocks-core',
				$base_url . 'blocks-core/core.css', [], $asset_file['version']);
			wp_enqueue_style('semla-flags',
				$base_url . 'css/flags' . SEMLA_MIN . '.css', [], '1.0');
		}
		wp_add_inline_script('semla-location-editor-script',
			'window.semla=window.semla||{};window.semla.gapi="'
				. get_option('semla_gapi_key') . '"',
			'before');
	}

	private static function init_dashboard() {
		add_action('wp_dashboard_setup', function() {
			remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
			remove_meta_box('dashboard_primary', 'dashboard', 'side');
			// No Limit Login Attempts Reloaded widget if you're not an admin
			if ( !current_user_can( 'manage_options' ) ) {
				remove_meta_box('llar_stats_widget', 'dashboard', 'normal');
			}
		});

		/** Show our custom post types on the dashboard. */
		add_filter('dashboard_glance_items', function($items = []) {
			foreach( App::$post_types as $app_post_type ) {
				$type = $app_post_type['post_type'];
				if( ! post_type_exists( $type ) ) continue;
				$num_posts = wp_count_posts( $type );
				if( $num_posts ) {
					$published = intval( $num_posts->publish );
					$post_type = get_post_type_object( $type );

					$text = '%s ' . ($published === 1 ? $post_type->labels->singular_name : $post_type->labels->name);
					$text = sprintf( $text, number_format_i18n( $published ) );

					if ( current_user_can( $post_type->cap->edit_posts ) ) {
						$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
					} else {
						$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";
					}
				}
			}
			return $items;
		}, 10, 1 );

		add_action('admin_head', function() {
			echo '<style>';
			/** Add styles to show the correct icons for our custom post types */
			foreach( App::$post_types as $app_post_type ) {
				$post_type = $app_post_type['post_type'];
				$dashicon_code = $app_post_type['dashicon_code'];
				echo "#dashboard_right_now a.$post_type-count:before,"
					. "#dashboard_right_now span.$post_type-count:before{content:"
					. "\"\\$dashicon_code\";}";
			}
			echo "</style>\n";
		});
	}
}
