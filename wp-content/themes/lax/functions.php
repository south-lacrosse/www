<?php
/**
 * Theme functions
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 */
define('SEMLA_PARTS', __DIR__ . '/template-parts/');
 /**
 * Set up theme defaults and register support for various WordPress features.
 *
 * after_setup_theme runs before the init hook, which is too late for some
 * features, such as indicating support for post thumbnails.
 */
add_action('after_setup_theme', function() {
	global $content_width;

	// stops query to find site logo - we don't use it
	remove_filter( 'theme_mod_custom_logo', '_override_custom_logo_theme_mod' );

	// Set the $content_width for things such as video embeds.
	// Don't make too wide otherwise the video will take up an inordinate amount
	// of space on the page
	if ( !isset( $content_width ) ) {
		$content_width = 640;
	}

	// Let WordPress manage the document title in <head>
	add_theme_support('title-tag');
	add_theme_support('responsive-embeds');
	if (defined('SEMLA_FEEDS') && SEMLA_FEEDS) {
		add_theme_support('automatic-feed-links');
		add_filter('feed_links_show_comments_feed','__return_false');
	}
	// Don't output 'type="text/css"' etc on styles/scripts
	add_theme_support('html5', ['style', 'script']);
	// register that this theme supports the SEMLA plugin
	//  check the plugin for current_theme_supports to see where used
	add_theme_support('semla');
	// Enable post thumbnails (featured images). Post/page support removed later
	add_theme_support('post-thumbnails');
	// remove core bundled patterns
	remove_theme_support( 'core-block-patterns' );
	// don't allow access to the template editor (in editor can click templates then "add template")
	remove_theme_support( 'block-templates' );

	register_nav_menus([
		'main' => 'Main Navigation',
		'popular' => 'Popular Links',
		'social' => 'Social Links',
	]);

	add_action('widgets_init', function() {
		register_sidebar([
			'name'          => 'News/Archives Sidebar',
			'id'            => 'sidebar-posts',
			'description'   => 'Sidebar on list of posts (news, categories, or tags)',
			'before_widget' => '',
			'after_widget'  => '',
		]);
	});

	if (is_admin()) {
		lax_admin();
		return;
	}
	if ( defined('WP_CLI') ) {
		add_action('semla_clear_menu_cache', 'lax_delete_menu_cache', 10, 0);
	}

	// Public facing init
	add_action('wp_head', 'lax_favicons');
	add_action('login_head', 'lax_favicons');
	add_action('semla_favicons', 'lax_favicons', 10, 0);
	add_filter('excerpt_length', function ($length) {
		return 35;
	});
	add_filter('excerpt_more', function ($more) {
		return '...';
	});
});

function lax_favicons() {
	// different favicons per environment to help stop accidentally changing the wrong site
	$env = wp_get_environment_type();
	if ($env === 'production') { ?>
<link rel="icon" type="image/png" sizes="96x96" href="/icon-96.png">
<link rel="icon" type="image/svg+xml" sizes="any" href="/icon.svg">
<link rel="icon" href="/favicon.ico">
<?php
	} else {
		$icon = get_theme_file_uri() . "/img/favicon-$env.svg";
		echo '<link rel="icon" type="image/svg+xml" href="', $icon, '">', "\n";
	}
	if (is_login()) {
		add_filter( 'login_headerurl', function() {
			return home_url();
		},10,0 );
		if ($env === 'production') {
			$icon = get_stylesheet_directory_uri() . '/img/logo.svg';
			$extra = ';width:135px;height:84px;background-size:135px 84px';
		} else {
			$extra = '';
		}
		echo "<style>#login h1 a{background-image:url($icon)$extra}</style>\n";
	}
}

function lax_admin() {
	add_theme_support('editor-styles');
	add_editor_style('editor-style' . SEMLA_MIN . '.css');
	add_action ('enqueue_block_assets',  function() {
		wp_enqueue_style('semla-editor',
			get_stylesheet_directory_uri() . '/editor' . SEMLA_MIN . '.css', [], '1.4');
	});
	// if menu changes get rid of our cached versions
	add_action('wp_update_nav_menu', 'lax_delete_menu_cache', 10, 0);
	// customizer does not run wp_update_nav_menu, so to be on the safe side
	// delete the menu cache whenever anything saved. Not performant, but the
	// customizer is hardly (if ever) used anyway, otherwise this should be
	// improved
	add_action('customize_save_after', 'lax_delete_menu_cache', 10, 0);
	add_action('semla_clear_menu_cache', 'lax_delete_menu_cache', 10, 0);
	add_action('admin_head', 'lax_favicons');

	// Block styles can just be registered in Admin as long as they don't
	// register a stylesheet or inline styles
	$block_styles = [
		'core/gallery' => [
			'lightbox'      => 'Lightbox',
		],
		'core/media-text' => [
			'flush'     => 'Flush',
		],
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
}

add_action('init', function() {
	remove_post_type_support('page', 'thumbnail');
	remove_post_type_support('post', 'thumbnail');
});

add_action('wp_enqueue_scripts', function() {
	wp_enqueue_style('lax-style', get_stylesheet_directory_uri() . '/style' . SEMLA_MIN . '.css'
		, [], '1.5.10');
	if (is_admin_bar_showing()) {
		wp_enqueue_style('lax-admin-bar', get_stylesheet_directory_uri() . '/admin-bar' . SEMLA_MIN . '.css'
		, ['lax-style'], '1.1');
	}
});

function lax_delete_menu_cache() {
	@unlink(__DIR__.'/template-parts/menu-main.html');
	@unlink(__DIR__.'/template-parts/menu-popular.html');
	@unlink(__DIR__.'/template-parts/menu-social.html');
}

if (!is_admin()) {
	require __DIR__.'/inc/template-functions.php';
}
