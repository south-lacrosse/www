<?php
namespace Semla;
use Semla\Data_Access\Table_Gateway;
/**
 * Handling initialisation for the public facing pages
 */
class App_Public {

	public static function init() {
		// Do most initialisation when we know which page it is
		add_action('wp', [self::class, 'wp_hook']);

		// Remove query vars we don't want, e.g. so ?embed=true won't work.
		add_filter('query_vars', function($public_query_vars) {
			return array_diff($public_query_vars, ['attachment','embed']);
		});
		// Remove author for non-REST requests as ?author=1 redirects to /author/user_name.
		// Make sure this hook runs after rest_api_loaded is run
		add_action('parse_request', function($wp) {
			unset($wp->query_vars['author']);
		}, 99 );

		// lets make sure users can only login with email/password
		// this stops username guessing, so author might display Fred Smith
		// for username fred_smith
		remove_filter('authenticate', 'wp_authenticate_username_password', 20);
		// Don't allow users with no roles or who are blocked to login
		add_filter('authenticate', [User::class, 'authenticate'], 30, 3 );
	}

	/**
	 * Hook 'wp' runs when WordPress is fully set up, so we know what the page is
	 */
	public static function wp_hook() {
		global $post;
		if (is_feed()) {
			if (is_comment_feed()) {
				wp_redirect( home_url(), 301 );
				exit;
			}
			// no WordPress version number in rss feeds
			add_filter('the_generator', '__return_false');
			return;
		}
		if (is_robots()) {
			add_filter( 'robots_txt', function( $output, $public ) {
				if ('0' === $public) return "User-agent: *\nDisallow: /\n";
				$pos = strpos($output, 'User-agent: *');
				if ($pos === false) return $output;
				$pos =+ 13;
				return substr($output,0,$pos) . "\nDisallow: /api/semla/" . substr($output, $pos);
			}, 10, 2);
			return;
		}

		// Don't send pointless headers
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wp_shortlink_wp_head');
		remove_action('template_redirect', 'wp_shortlink_header', 11);

		// Remove the REST API lines from the HTTP Header
		remove_action('template_redirect', 'rest_output_link_header', 11);
		remove_action('wp_head', 'rest_output_link_wp_head');
		// Remove oEmbed discovery links.
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');

		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_print_styles', 'print_emoji_styles');
		add_filter('emoji_svg_url', '__return_false'); // stops prefetch being added
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		// remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// Add headers that are in .htaccess, but server doesn't send for PHP requests
		send_frame_options_header();

		// semla_* are actions called from our theme
		// called from theme for history pages with flags on them
		add_action( 'semla_flags_header', function() {
			global $post;
			self::enqueue_flags_css(get_post_meta($post->ID, '_semla_max_flags_rounds', true));
		});
		add_action( 'semla_mini_tables', function() {
			echo Table_Gateway::get_mini_tables();
		});

		// Check to see if the page contains something we need to know about
		// before the page loads, so we can replace the content, change the page
		// title, or add some CSS for a block.
		if ( !is_singular() || is_front_page() || !is_object($post)) {
			return;
		}

		// Note: if the block always uses the same CSS then it should be added in the
		// register_block_type call, but for things like calendar each instance of the
		// block can have different CSS
		if (get_post_type() === 'history') {
			add_action('semla_history_breadcrumbs', [self::class, 'history_breadcrumbs']);
			// don't let WordPress mess up our HTML
			remove_filter( 'the_content', 'wpautop' );
			$post_name = get_post_field('post_name');
			if (preg_match('/results-\d\d\d\d/',$post_name)) {
				$year = substr($post_name, -4);
				Block_Data::get_instance()->fixtures_results_args($year);
				add_filter('the_content', function() {
					return Block_Data::get_instance()->fixtures_results();
				});
			}
			return;
		}
		// . in regex won't match newline, and each block declaration is on 1 line
		if (preg_match('/<!-- wp:semla\/(data|calendar) (.*) \/?-->/',
				$post->post_content, $m)) {
			$atts = json_decode($m[2], true);
			$src = $atts['src'] ?? '';
			$tag = 'semla_' . $m[1];
			if ($m[1] === 'data') {
				// parse the query args now so we can change the page title
				Block_Data::get_instance()->parse_query_args($src);
				if (str_starts_with($src,'clubs_')) {
					$tag = 'semla_clubs';
				}
			} else {
				Block_Calendar::do_header($atts);
			}
			// Add tags to cached pages
			add_action( 'litespeed_tag_finalize', function() use ($tag) {
				do_action( 'litespeed_tag_add', $tag );
			});
		}
		if (preg_match('/<!-- wp:gallery {[^}]*"className":"[^"]*is-style-lightbox/',
				$post->post_content, $m)) {
			require __DIR__ . '/lightbox-gallery.php';
		}
	}

	public static function enqueue_flags_css($rounds) {
		wp_enqueue_style( 'semla-flags', plugins_url('css/flags' . SEMLA_MIN . '.css', __DIR__),
			[], '1.1');
		if ($rounds) {
			add_filter( 'body_class', function( $classes ) use ($rounds) {
				$classes[] = "rounds-$rounds";
				return $classes;
			});
		}
	}

	public static function history_breadcrumbs() {
		global $post;
		// for fixtures grid enqueue the colhover javascript. Can do here
		// as it's added in the footer
		if (str_ends_with(get_the_title($post), ' Results Grid')) {
			wp_enqueue_script( 'semla-colhover',
				plugins_url('js/colhover' . SEMLA_MIN . '.js', __DIR__),
				[], '1.0', true );
		}
		?>
<nav><ul class="breadcrumbs nav-list">
<li><a href="/history">History</a></li>
<?php
		$breadcrumbs = get_post_meta($post->ID, '_semla_breadcrumbs', true);
		if ($breadcrumbs) {
			foreach ( $breadcrumbs as $breadcrumb ) {
				echo "<li><a href=\"/history/$breadcrumb[0]\">$breadcrumb[1]</a></li>";
			}
		}
		echo '<li>';
		add_filter('semla_change_the_title', '__return_false');
		the_title();
		remove_filter('semla_change_the_title', '__return_false');
		echo '</li></ul></nav>';
	}
}
