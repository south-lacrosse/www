<?php
/**
 * Plugin Name:  SEMLA
 * Plugin URI:   https://github.com/south-lacrosse/www
 * Description:  South of England Men's Lacrosse Association clubs, league, flags, fixtures etc.
 * Author:       SEMLA
 * Author URI:   mailto:webmaster@southlacrosse.org.uk
 * License:      GNU General Public License v2 or later
 * License URI:  http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Semla;

defined('WPINC') || die;

! defined('SEMLA_MIN') && define('SEMLA_MIN', '.min');
// Make sure the production blog is discoverable by search engines, and,
// equally important, everything else isn't. IMPORTANT: if you remove this code
// also change App_Admin as that removes the blog_public option from the admin Reading screen
! defined('SEMLA_PUBLIC')
	&& define('SEMLA_PUBLIC', wp_get_environment_type() === 'production' ? '1' : '0');
add_filter('pre_option_blog_public', function() { return SEMLA_PUBLIC; });

// Uncomment to test sitemaps in a non-production environment
// add_filter('wp_sitemaps_enabled', '__return_true');
add_filter('wp_sitemaps_add_provider', function($provider, $name) {
	return ('users' === $name) ? false :  $provider;
}, 10, 2);

// include our class loader
require_once __DIR__.'/core/autoload.php';

// move wp-json to api. Needs to be done before init action
add_filter( 'rest_url_prefix', function () {
	return 'api';
} );

add_action( 'init', [App::class, 'init_early'], 0 );
add_action( 'init', [App::class, 'init'] );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	add_action( 'cli_init', function() {
		\WP_CLI::add_command( 'semla', \Semla\CLI\Semla_Command::class );
	} );
}
