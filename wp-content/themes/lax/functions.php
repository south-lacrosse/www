<?php
/**
 * Theme functions
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 */

/**
 * Set up theme defaults and register support for various WordPress features.
 *
 * after_setup_theme runs before the init hook, which is too late for some
 * features, such as indicating support for post thumbnails.
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

	// Let WordPress manage the document title in <head>
	add_theme_support('title-tag');
	add_theme_support('responsive-embeds');
	// Don't output 'type="text/css"' etc on styles/scripts
	add_theme_support('html5', ['style', 'script']);
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
	add_action ('enqueue_block_editor_assets', function() {
		add_theme_support('editor-styles');
		add_editor_style('editor-style' . SEMLA_MIN . '.css');
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

function lax_delete_menu_cache() {
	@unlink(__DIR__.'/parts/menu-main.html');
	@unlink(__DIR__.'/parts/menu-popular.html');
	@unlink(__DIR__.'/parts/menu-social.html');
}

if (!is_admin() && !defined('WP_CLI')) {
	require __DIR__.'/inc/template-functions.php';
}
