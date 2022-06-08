<?php
namespace Semla;

use Semla\Admin\User_Profile_Extras;
use Semla\Rest\Rest;
use Semla\Utils\SMTP;

/**
 * The core functionality of the plugin.
 */
class App {
	public static $post_types = [
		[ 
			'post_type' => 'clubs',
			'name' => 'Club',
			'args' => [
				'menu_icon' => 'dashicons-shield',
			],
			'dashicon_code' => 'f332'
		],
		/* Note: history pages won't be shown in the WP Admin menu because of the capabilities set.
		 * To enable people to edit either remove the capability_type so anyone with access to
		 * posts can edit, or add the required capabilities to a role. e.g. using WP-CLI
		 * wp cap add administrator edit_histories edit_others_histories delete_histories publish_histories read_private_histories delete_private_histories delete_published_histories delete_others_histories edit_private_histories edit_published_histories
		 */
		[
			'post_type' => 'history',
			'name' => 'History Page',
			'args' => [
				'menu_icon' => 'dashicons-media-spreadsheet',
				'exclude_from_search' => true,
				'capability_type' => ['history','histories'],
				// no revisions as history pages are generated from our tables and won't need revisions
				'supports' => [
					'title',
					'editor',
				]
			],
			'dashicon_code' => 'f495'
		],
	];

	/**
	 * Initialise the plugin. Called from init hook.
	 * 
	 * IMPORTANT: if you add new custom post types or rewrite rules, then they
	 * must be flushed before they will be effective. Go into Settings->Permalinks
	 * and Save Changes, or use WP-CLI 'wp rewrite flush'. This should also be done
	 * when the plugin is activated for the first time.
	 *
	 * Also rules need to go in init so they are loaded on both admin and public
	 * pages, otherwise they will not be saved.
	 */
	public static function init() {
		// Remove comments/trackbacks
		remove_post_type_support('page', 'comments');
		remove_post_type_support('post', 'comments');
		remove_post_type_support('attachment', 'comments');
		remove_post_type_support('post', 'trackbacks');
		add_filter('comments_open', '__return_false', 20, 2);
		add_filter('pings_open', '__return_false', 20, 2);

		foreach (self::$post_types as $post_type) {
			self::register_post_type($post_type['post_type'],
				$post_type['name'], $post_type['args']);
		}

		// Remove rewrite rules for the legacy comment feed, post type comment pages,
		// author pages, trackback, attachment pages, embeds
		add_filter( 'rewrite_rules_array', function($rules) {
			foreach ( $rules as $regex => $query ) {
				if ( false !== strpos( $regex, '|commentsrss2' ) ) {
					$new_k = str_replace( '|commentsrss2', '', $regex );
					unset( $rules[ $regex ] );
					$rules[ $new_k ] = $query;
				} else if ( preg_match( '/(attachment|trackback|comment|author\/)/', $regex )
				|| preg_match( '/attachment|embed=true/', $query ) ) {
					unset( $rules[ $regex ] );
				}
			}
			return $rules;
		}, 99 );

		// use encrypted SMTP to send emails, and monitor it
		if (defined('SMTP_USER') && defined('SMTP_PASS')) {
			add_action( 'phpmailer_init', [SMTP::class, 'phpmailer_init'], 999 );
			add_action( 'wp_mail_failed', [SMTP::class, 'mail_failed'] );
			add_action( 'wp_mail_succeeded', [SMTP::class, 'mail_succeeded'] );
		}

		if (is_admin_bar_showing()) {
			// Remove comments from admin bar menu. This is the most efficient way
			// to remove as it stops the wp_admin_bar_comments_menu function running,
			// so stops any database access
			add_action('add_admin_bar_menus', function() {
				remove_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu', 40 );
				remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
			});
			add_action('wp_before_admin_bar_render', function() {
				// Add our help screens
				require __DIR__.'/admin-bar-menu.php';
			});
		}
		
		// don't rewrite content to fancy quotes, used on front and back ends
		add_filter('run_wptexturize', '__return_false');
		// Allow auto updates even though the WordPress folder is under Git control
		// All WordPress code is marked as untracked in the .gitignore file
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_true' );

		
		if (is_admin()) {
			App_Admin::init();
			return;
		}
		// Note that when we get here this could either be a public facing
		// page, or a REST request
		App_Public::init();
		add_action('rest_api_init', [Rest::class, 'init']);

		// lets make sure users can only login with email/password
		// this stops username guessing, so author might display Fred Smith
		// for username fred_smith
		remove_filter('authenticate', 'wp_authenticate_username_password', 20);
		User_Profile_Extras::init();
	}

	/**
	 * @param string $post_type the post type to create
	 * @param mixed $name name to use in labels, so 'New Name'. Can be a string, in which case plural add 's',
	 * 		or array of singular and plural
	 * @param array $args arguments. Can pass them all in, or use defaults for all args and labels
	 */
	private static function register_post_type(string $post_type, $name, array $args) {
		if ( is_array( $name ) ) {
			list( $single_name, $plural_name ) = $name;
		} else {
			$single_name = $name;
			$plural_name = $name . 's';
		}

		// default args
		$args = array_merge([
			'public' => true,
			'has_archive' => false,
			'rewrite' => [
				'paged' => false,
				'feed' => false
			],
			'capability_type' => 'page',
			'map_meta_cap' => true,
			// need for Gutenberg editor
			'show_in_rest' => true,
			'supports' => [
				'title',
				'editor',
				'revisions'
			]
		], $args);
		
		// might as well always do this, as WP will set these anyway
		if (!isset($args['labels'])) {
			$args['labels'] = [
				'name' => $plural_name,
				'singular_name' => $single_name,
				'add_new' => 'Add New',
				'add_new_item' => "Add a New $single_name",
				'edit_item' => "Edit $single_name",
				'new_item' => "New $single_name",
				'view_item' => "View $single_name",
				'view_items' => "View $plural_name",
				'search_items' => "Search $plural_name",
				'not_found' => "No $plural_name Found",
				'not_found_in_trash' => "No $plural_name Found In Trash",
				'parent_item_colon' => "Parent $single_name",
				'all_items' => "All $plural_name",
				'archives' => "All $plural_name",
				'attributes' => "$single_name Attributes",
				'insert_into_item' => "Insert into $single_name",
				'uploaded_to_this_item' => "Uploaded to this $single_name",
				'filter_items_list' => "Filter $plural_name list",
				'items_list_navigation' => "$plural_name list navigation",
				'items_list' =>  "$plural_name list",
				'menu_name' => $plural_name,
				'item_published' => "$single_name published.",
				'item_published_privately' => "$single_name published privately.",
				'item_reverted_to_draft' => "$single_name reverted to draft.",
				'item_scheduled' => "$single_name scheduled.",
				'item_updated' => "$single_name updated.",
				'name_admin_bar' => $single_name,
			];
		}
		register_post_type($post_type, $args);

		if (is_admin()) {
			// change "Enter Post name here" placeholder
			$placeholder = $args['placeholder'] ?? "Enter $single_name name here";
			add_filter('enter_title_here', function(string $title) use ($post_type, $placeholder) {
				if  (get_current_screen()->post_type === $post_type) {
					return $placeholder;
				}
				return $title;
			});
		}
	}

	/**
	 * Register blocks. Don't do this if is_admin as that will run our blocks on the edit
	 * post page, and that will run the queries, and enqueue scripts, which we don't want.
	 * The blocks with server side rendering will be inserted into the edit post screen
	 * using REST requests anyway
	 */
	public static function register_blocks() {
		register_block_type( 'semla/data', [
			'attributes' => [
				'src' => [
					'type' => 'string'
				]
			],
			'render_callback' => [Block_Data::class, 'render_callback'],
		]);
		register_block_type( 'semla/calendar', [
			'render_callback' => [Block_Calendar::class, 'render_callback'],
		]);
		register_block_type( 'semla/toc', [
			'render_callback' => [Blocks::class, 'toc'],
		]);
		register_block_type( 'semla/location', [
			'render_callback' => [Blocks::class, 'location'],
		]);
	}
}
