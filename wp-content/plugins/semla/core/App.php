<?php
namespace Semla;

use Semla\Rest\Rest;
use Semla\Utils\SMTP;
use Semla\Utils\Image;

/**
 * The core functionality of the plugin.
 */
class App {
	/**
	 * Initialise the plugin. Called from init hook.
	 *
	 * IMPORTANT: if you add new custom post types or change the rewrite rules,
	 * then they must be flushed before they will be effective. Go into
	 * Settings->Permalinks and Save Changes, or use WP-CLI 'wp rewrite flush'.
	 */
	public static function init() {
		// Remove comments/trackbacks
		remove_post_type_support('page', 'comments');
		remove_post_type_support('post', 'comments');
		remove_post_type_support('attachment', 'comments');
		remove_post_type_support('post', 'trackbacks');
		add_filter('comments_open', '__return_false', 20, 2);
		add_filter('pings_open', '__return_false', 20, 2);

		self::register_post_type('clubs', 'Club', [
			'menu_icon' => 'dashicons-shield',
			'supports' => [
				'title',
				'editor',
				'revisions',
				'thumbnail'
			],
		]);
		/* Note: history pages won't be shown in the WP Admin menu because of the capabilities set.
		 * To enable people to edit either remove the capability_type so anyone with access to
		 * posts can edit, or add the required capabilities to a role. e.g. using WP-CLI
		 * wp cap add administrator edit_histories edit_others_histories delete_histories publish_histories read_private_histories delete_private_histories delete_published_histories delete_others_histories edit_private_histories edit_published_histories
		 */
		self::register_post_type('history', 'History Page', [
			'menu_icon' => 'dashicons-media-spreadsheet',
			'exclude_from_search' => true,
			'capability_type' => ['history','histories'],
			// no revisions as history pages are generated from our tables and won't need revisions
			'supports' => [
				'title',
				'editor',
			]
		]);

		// Remove rewrite rules we don't need or don't want, namely comment
		// pages and feeds, author pages, trackbacks, attachment pages, embeds,
		// post formats. This reduces the number of rewrite rules from ~129 to
		// ~28.

		// favicon is already handled outside WP
		// wp-app and wp-register are deprecated and we don't need to handle them

		// The filter must always be added as rewrite rules may be flushed
		// (regenerated) on both admin and public pages.
		add_filter( 'rewrite_rules_array', function($rules) {
			// remove all feeds or just legacy feeds
			$query_remove_regex = '/attachment|embed=true|post_format=|feed=' .
				(SEMLA_FEEDS ? 'old' : '') . '/';
			foreach ( $rules as $regex => $query ) {
				if ( preg_match( '/attachment|trackback|comment|author\/|favicon|wp-app\\\.php|wp-register\.php|index\.php\/api/', $regex )
				|| preg_match( $query_remove_regex, $query ) ) {
					unset( $rules[ $regex ] );
				}
			}
			return $rules;
		}, 99 );
		// since we've removed author and attachment pages make sure we can't link to them
		add_filter( 'author_link', '__return_empty_string' );
		add_filter( 'attachment_link', '__return_empty_string' );

		// use encrypted SMTP to send emails, and monitor it
		if (defined('SMTP_USER') && defined('SMTP_PASS')) {
			add_action( 'phpmailer_init', [SMTP::class, 'phpmailer_init'], 999 );
			add_action( 'wp_mail_failed', [SMTP::class, 'mail_failed'] );
			add_action( 'wp_mail_succeeded', [SMTP::class, 'mail_succeeded'] );
		}

		if (is_admin_bar_showing()) {
			require __DIR__ . '/admin-bar.php';
		}

		// don't rewrite content to fancy quotes, used on front and back ends
		add_filter('run_wptexturize', '__return_false');
		// Allow auto updates even though the WordPress folder is under Git control
		// All WordPress code is marked as untracked in the .gitignore file
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_true' );

		$block_dir = dirname(__DIR__) . '/blocks';
		// wp_register_block_metadata_collection is added in WP 6.7
		// remove this test if we don't ever need to go back to 6.6 or below
		if (function_exists('wp_register_block_metadata_collection')) {
			wp_register_block_metadata_collection($block_dir, "$block_dir/blocks-manifest.php");
		}
		register_block_type_from_metadata( $block_dir . '/attr-value' );
		register_block_type_from_metadata( $block_dir . '/calendar', [
			'render_callback' => [Block_Calendar::class, 'render_callback'],
		]);
		register_block_type_from_metadata( $block_dir . '/contact', [
			'render_callback' => [Blocks::class, 'contact'],
		]);
		register_block_type_from_metadata( $block_dir . '/club-title', [
			'render_callback' => [Blocks::class, 'club_title'],
		]);
		register_block_type_from_metadata( $block_dir . '/data', [
			'render_callback' => [Block_Data::class, 'render_callback'],
		]);
		register_block_type_from_metadata( $block_dir . '/location', [
			'render_callback' => [Blocks::class, 'location'],
		]);
		register_block_type_from_metadata( $block_dir . '/map', [
			'render_callback' => [Blocks::class, 'map'],
		]);
		register_block_type_from_metadata( $block_dir . '/toc' );
		register_block_type_from_metadata( $block_dir . '/website', [
			'render_callback' => [Blocks::class, 'website'],
		]);
		add_filter('render_block_core/post-date', [Blocks::class, 'render_post_date'], 0, 3);

		// we have all urls correctly set to https, so stop unnecessary logic running and a database lookup
		remove_filter('the_content', 'wp_replace_insecure_home_url');
		remove_filter('the_excerpt', 'wp_replace_insecure_home_url');
		remove_filter('widget_text_content', 'wp_replace_insecure_home_url');
		remove_filter('wp_get_custom_css', 'wp_replace_insecure_home_url');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		add_filter('allow_password_reset', [User::class, 'allow_password_reset'], 10, 2);
		add_filter('retrieve_password_message', [User::class, 'retrieve_password_message'], 10, 4);
		add_filter('wp_new_user_notification_email', [User::class, 'wp_new_user_notification_email'], 10, 3);

		add_filter('wp_generate_attachment_metadata', [Image::class, 'generate_svg_attachment_metadata'], 10, 2 );
		add_filter('wp_get_missing_image_subsizes', [Image::class, 'no_svg_missing_image_subsizes'], 10, 3);

		if (is_admin()) {
			App_Admin::init();
			return;
		}
		// Note that when we get here this could either be a public facing
		// page, or a REST request
		App_Public::init();
		add_action('rest_api_init', [Rest::class, 'init']);
	}

	/**
	 * Called from the init hook, but with a low priority so it runs before the
	 * actions we want to remove have run.
	 *
	 * You can see the core blocks in wp-includes\blocks\require-dynamic-blocks.php
	 */
	public static function init_early() {
		remove_action( 'init', 'register_block_core_comment_author_name' );
		remove_action( 'init', 'register_block_core_comment_content' );
		remove_action( 'init', 'register_block_core_comment_date' );
		remove_action( 'init', 'register_block_core_comment_edit_link' );
		remove_action( 'init', 'register_block_core_comment_reply_link' );
		remove_action( 'init', 'register_block_core_comment_template' );
		remove_action( 'init', 'register_block_core_comments' );
		remove_action( 'init', 'register_block_core_comments_pagination' );
		remove_action( 'init', 'register_block_core_comments_pagination_next' );
		remove_action( 'init', 'register_block_core_comments_pagination_numbers' );
		remove_action( 'init', 'register_block_core_comments_pagination_previous' );
		remove_action( 'init', 'register_block_core_comments_title' );
		remove_action( 'init', 'register_block_core_latest_comments' );
		remove_action( 'init', 'register_block_core_post_comments_count' );
		remove_action( 'init', 'register_block_core_post_comments_form' );
		remove_action( 'init', 'register_block_core_post_comments_link' );
		remove_action( 'init', 'register_legacy_post_comments_block', 21 );
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

		// WP sets these to post/page not cpt name by default
		if (!isset($args['labels'])) {
			$single_lc = strtolower($single_name);
			$plural_lc = strtolower($plural_name);
			$args['labels'] = [
				'name' => $plural_name,
				'singular_name' => $single_name,
				'add_new' => "Add New $single_name",
				'add_new_item' => "Add New $single_name",
				'edit_item' => "Edit $single_name",
				'new_item' => "New $single_name",
				'view_item' => "View $single_name",
				'view_items' => "View $plural_name",
				'search_items' => "Search $plural_name",
				'not_found' => "No $plural_lc found.",
				'not_found_in_trash' => "No $plural_lc found in Trash.",
				'parent_item_colon' => "Parent $single_name",
				'all_items' => $plural_name,
				'archives' => $plural_name,
				'attributes' => "$single_name Attributes",
				'insert_into_item' => "Insert into $single_lc",
				'uploaded_to_this_item' => "Uploaded to this $single_lc",
				'filter_items_list' => "Filter $plural_lc list",
				'items_list_navigation' => "$plural_name list navigation",
				'items_list' =>  "$plural_name list",
				'item_published' => "$single_name published.",
				'item_published_privately' => "$single_name published privately.",
				'item_reverted_to_draft' => "$single_name reverted to draft.",
				'item_scheduled' => "$single_name scheduled.",
				'item_updated' => "$single_name updated.",
				'item_link' => "$single_name Link",
				'item_link_description' => "A link to a $single_lc.",
				'menu_name' => $plural_name,
				'name_admin_bar' => $single_name,
			];
		}
		register_post_type($post_type, $args);
	}
}
