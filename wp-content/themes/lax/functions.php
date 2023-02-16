<?php
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 *
 * Note on theme.json (since comments aren't allowed in json files). That file
 * seriously restricts the ability of the editor to change the styling of the site,
 * so you can't change font size, colors etc. If you want to enable that then you
 * may need to update this theme, or use another one.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 * @package Lax
 */

// default to no sidebar, can be overridden in wp-config.php
if (!defined('LAX_SIDEBAR')) define('LAX_SIDEBAR', false);
define('LAX_ACTIVE_SIDEBAR', LAX_SIDEBAR && is_active_sidebar('sidebar-1'));

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
add_action('after_setup_theme', function() {
	global $content_width;

	// Set the $content_width for things such as video embeds.
	// Don't make too wide otherwise the video will take up an inordinate amount
	// of space on the page
	if ( !isset( $content_width ) ) {
		$content_width = 640;
	}

	// don't add new global styles added in WP 5.8 for blocks
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );

	// Don't allow users to add new block templates in the editor
	remove_theme_support( 'block-templates' );

	// Let WordPress manage the document title.
	add_theme_support('title-tag');

	add_theme_support('responsive-embeds');

	// Add default posts RSS feed links to head.
	// add_theme_support('automatic-feed-links');
	// If above enabled need to disable comments RSS feed link
	// add_filter('feed_links_show_comments_feed', '__return_false');

	// register that this theme supports the SEMLA plugin
	//  check the plugin for current_theme_supports to see where used
	add_theme_support('semla');

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
	 */
	// add_theme_support('post-thumbnails');

	register_nav_menus([
		'main' => 'Main Navigation',
		'popular' => 'Popular Links',
		'social' => 'Social Links',
	]);

	/*
	 * Switch default core markup to output valid HTML5.
	 * Not used 'comment-list', 'comment-form',
	 */
	add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

	// Add theme support for selective refresh for widgets.
	// add_theme_support('customize-selective-refresh-widgets');

	if (is_admin()) {
		lax_admin();
		return;
	}
	if ( defined('WP_CLI') ) {
		add_action('semla_clear_menu_cache', 'lax_delete_menu_cache', 10, 0);
	}

	// Public facing init
	add_filter('excerpt_length', function ($length) {
		return 35;
	});
	add_filter('excerpt_more', function ($more) {
		return '...';
	});
});

function lax_admin() {
	// add_action('current_screen', function(\WP_Screen $screen) {
	// 	if ($screen->base === 'post') { // edit post/page/etc
	add_action ('enqueue_block_editor_assets', function() {
		add_theme_support('editor-styles');
		// TODO: should probably split styles need for editor and not, e.g.
		// all menu styling isn't needed for the editor
		add_editor_style('style' . SEMLA_MIN . '.css');
	});

	// if menu changes get rid of our cached versions
	add_action('wp_update_nav_menu', 'lax_delete_menu_cache', 10, 0);
	add_action('semla_clear_menu_cache', 'lax_delete_menu_cache', 10, 0);
}

add_action('init', function() {
	$block_styles = [
		'core/table' => [
			'lined'         => 'Lined',
			'boxed-striped' => 'Boxed and striped',
		],
	];
	foreach ( $block_styles as $block => $styles ) {
		foreach ( $styles as $style_name => $style_label ) {
			register_block_style(
				$block,
				[
					'name'  => $style_name,
					'label' => $style_label,
				]
			);
		}
	}
});

add_action('wp_enqueue_scripts', function() {
	wp_enqueue_style('lax-style', get_stylesheet_directory_uri() . '/style' . SEMLA_MIN . '.css'
		, [], '1.2');
	if (is_admin_bar_showing()) {
		wp_enqueue_style('lax-admin-bar', get_stylesheet_directory_uri() . '/admin-bar' . SEMLA_MIN . '.css'
		, ['lax-style'], '1.0');
	}
});

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
if (LAX_SIDEBAR) {
	add_action('widgets_init', function() {
		register_sidebar([
			'name'          => 'Sidebar',
			'id'            => 'sidebar-1',
			'description'   => 'Add widgets here.',
			'before_widget' => '<section id="%1$s" class="%2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		]);
	});
}

function lax_delete_menu_cache() {
	@unlink(__DIR__.'/template-parts/menu-main.html');
	@unlink(__DIR__.'/template-parts/menu-popular.html');
	@unlink(__DIR__.'/template-parts/menu-social.html');
}

if (!is_admin() && !defined('WP_CLI')) {
	require __DIR__.'/inc/template-functions.php';
}
