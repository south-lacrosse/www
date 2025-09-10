<?php
namespace Semla\Rest;
use Semla\Utils\Image;
/**
 * Handle REST requests
 *
 * semla/v1 endpoints:
 *      /clubs - html list of all clubs, with links to pages with club's
 *               services and team's services
 *      /clubs.gpx - GPS data in gpx format
 *      /clubs/Bath - details of all REST services for club
 *      /clubs/Bath/fixtures - html snippet, .json, or .js to embed
 *      /clubs/Bath/tables - html snippet, .json, or .js to embed
 *
 *      /teams - html list of all teams, with links to fixtures and tables
 *      /teams/Bath - details of all REST services for team
 *      /teams/Bath/fixtures - html snippet, .json, or .js to embed
 *      /teams/Bath/fixtures.ics - calendar
 *      /teams/Bath/tables - html snippet, .json, or .js to embed
 *
 * semla-admin/v1: JSON endpoints for the admin area
 *      /leagues-cups - get list of current leagues/cup for or custom data block
 *      /teams/Bath - update team abbreviation/minimal
 *      /competition/id - update competition data
 *
 * Note: the previous version used "+" for space in the club/team names. That is
 * a bad practice, so now "_" is used, but since it may have been used for calendars
 * the "+" urls are still allowed.
 */
class Rest {
	const TAG_LINE = '<p class="sl-tagline"><small>Data provided by <a href="https://www.southlacrosse.org.uk/">southlacrosse.org.uk</a></small></p>';
	const CONTENT_TYPES = [
		'.gpx' => 'application/gpx+xml',
		'.ics' => 'text/calendar',
		'.js' => 'application/javascript',
		'.json' => 'application/json',
	];
	const SEMLA_BASE = 'semla/v1';
	const SEMLA_PREFIX = '/semla/v1/';
	const SEMLA_ADMIN_BASE = 'semla-admin/v1';
	public static $cache_tags = ['semla_data']; // can be overridden by routes
	public static $cors_header = false;

	public static function init() {
		global $wpdb;
		// make sure even if WP_DEBUG/WP_DEBUG_DISPLAY are true we don't display errors as
		// that will invalidate returned JSON
		$wpdb->hide_errors();
		// Next 2 filters are duplicated in App_Admin. If more are added consider
		// moving to common file
		// if clubs have changed then purge pages/rest routes which use club data
		add_action('save_post_clubs', function() {
			do_action( 'litespeed_purge', 'semla_clubs' );
		});
		add_filter('upload_mimes', [Image::class, 'allow_svg_mimes'], 10, 1);

		// Make sure we can update our meta data on the clubs post type.
		// Note: we do this here rather than when the post type is created so
		// that custom fields are only available in REST, and users cannot enter
		// them on the edit screen.
		add_post_type_support('clubs', 'custom-fields');
		register_post_meta('clubs', 'lacrosseplay_club', [
			'type'         => 'string',
			'show_in_rest' => true,
			'single'       => true,
			'sanitize_callback' => 'sanitize_text_field',
		]);

		// Remove the oembed/1.0/embed REST route.
		add_filter( 'rest_endpoints', function( $endpoints ) {
			unset( $endpoints['/oembed/1.0/embed'] );
			return $endpoints;
		});
		// Disable handling of internal embeds in oembed/1.0/proxy REST route.
		add_filter( 'oembed_response_data', '__return_false' );
		// Remove filter of the oEmbed result before any HTTP requests are made, i.e.
		// if trying to embed from this site
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

		// block REST access if not logged in, but not for 'semla' endpoints
		add_filter( 'rest_authentication_errors', function( $result ) {
			global $wp;
			if ( ! empty( $result ) ) {
				return $result;
			}
			if ( ! is_user_logged_in()
			&& ! str_starts_with($wp->query_vars['rest_route'], '/semla/') ) {
				return new \WP_Error( 'rest_not_logged_in', 'API request is only supported for authenticated users', ['status' => 401] );
			}
			return $result;
		});

		// send our responses in the right format
		add_filter( 'rest_pre_serve_request', [self::class, 'pre_serve_request'], 10, 4 );

		register_rest_route( self::SEMLA_ADMIN_BASE, '/leagues-cups', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Admin_Services::class, 'leagues_cups'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_ADMIN_BASE, '/teams/(?P<team>[\w+_~&%.-]+)', [
			'methods' => 'PUT',
			'callback' => [Teams_Services::class, 'admin_update'],
			'permission_callback' => [self::class, 'permissions_manage_semla']
		]);
		register_rest_route( self::SEMLA_ADMIN_BASE, '/competitions/(?P<comp_id>\d+)', [
			'methods' => 'PUT',
			'callback' => [Admin_Services::class, 'competition_update'],
			'permission_callback' => [self::class, 'permissions_manage_semla']
		]);

		register_rest_route( self::SEMLA_BASE, '/teams', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'teams_list'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w+_~&%.-]+)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'team_info'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w+_~&%.-]+)/fixtures.ics', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'team_fixtures_ics'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w+_~&%.-]+)/(?P<type>fixtures|tables)(?P<extension>|.js|.json)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class,'team_fixtures_tables'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class, 'clubs_list'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs.gpx', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class, 'clubs_gpx'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs/(?P<club>[\w+_%.-]+)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class, 'club_info'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs/(?P<club>[\w+_%.-]+)/(?P<type>fixtures|tables)(?P<extension>|.js|.json)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class,'club_fixtures_tables'],
			'permission_callback' => '__return_true',
		]);
	}

	/**
	 * Hooks into the REST API output to send the response correctly. By default
	 * WordPress will format the response as json, so here we make sure it's sent
	 * as html or whatever is specified.
	 *
	 * We also send JSON responses to our own requests as we want to encode the json
	 * so that numeric fields aren't sent as text, which won't happen using the WordPress
	 * way as the PHP mysql interface always returns strings.
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param WP_HTTP_Response  $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request   $request Request used to generate the response.
	 * @param WP_REST_Server    $server  Server instance.
	 * @return true
	 */
	public static function pre_serve_request( $served, $result, $request, $server ) {
		if ( $served
		|| !str_starts_with($request->get_route(), self::SEMLA_PREFIX) ) {
			return $served;
		}
		if (!empty($request['extension'])) {
			if ($result->status != 200 && $request['extension'] == '.ics') {
				$content_type = 'text/plain';
			} else {
				$content_type = self::CONTENT_TYPES[$request['extension']];
			}
		} elseif ($result->status != 200) {
			// content type may not have been set if validation failed so set here
			$extension = pathinfo($request->get_route(), PATHINFO_EXTENSION);
			$content_type = match($extension) {
				'' => 'text/html',
				'json' => 'application/json',
				default => 'text/plain'
			};
		} else {
			$content_type = 'text/html';
		}


		if (! headers_sent() ) {
			$server->send_header( 'Content-Type',
				$content_type . '; charset=utf-8' );
			$server->send_header( 'X-Robots-Tag', 'noindex,nofollow' );
			if ($content_type === 'text/html') {
				send_frame_options_header();
			}
			if (self::$cors_header) {
				$server->send_header( 'Access-Control-Allow-Origin', '*' );
			}
			if ($result->status != 200) {
				status_header( $result->status );
			}
		}

		$data = $result->get_data();
		// Bail if there's no data
		if ( ! $data ) {
			status_header( 500 );
			die( get_status_header_desc( 500 ) );
		}
		if ($result->status != 200) {
			if ($content_type === 'application/json') {
				echo json_encode($data);
			} else {
				$err = 'Error code: ' . $data['code'] . ', message: ' . $data['message'];
				if ($content_type === 'text/html') {
					echo "<p>$err</p>";
				} else {
					echo $err;
				}
			}
			return true;
		}
		add_action( 'litespeed_tag_finalize', [self::class, 'add_cache_tags'] );
		if ($request['extension'] === '.js') {
			$data = strtr($data, [
				"\r\n" => "\n",
				'"' => "'"
			]);
			$data = json_encode($data, JSON_UNESCAPED_SLASHES);
			if (isset($request['async'])) {
				$url = home_url($_SERVER['REQUEST_URI']);
				$data = trim($data,'"');
				require __DIR__ . '/views/embed' . SEMLA_MIN . '.js';
			} else {
				echo "document.write($data);";
			}
		} else {
			echo $data;
		}
		return true;
	}


	/**
	 * Encode team name so it can be used in the path part of a REST URL
	 */
	public static function encode_club_team($value) {
		// Apache will just reject an encoded "/" (%2F) for security reasons, so we
		// converted it ~, and the following decode_club_team() will reverse that
	   return str_replace([' ','/'],['_','~'], $value);
	}

	public static function decode_club_team($value) {
		// Allow old format with + for spaces, as well as the better new version with _.
		// Some calendar apps replace + with %2b, so we need to decode the url first.
		// Apache will just reject encoded "/" (%2F) for security reasons, so we will have
		// converted it ~, so reverse that here too.
		return str_replace(['_','+','~'],[' ',' ','/'],urldecode($value));
	}


	public static function get_calendar_url($team) {
		return site_url( rest_get_url_prefix() . self::SEMLA_PREFIX . 'teams/'
			. self::encode_club_team($team) . '/fixtures.ics');
	}

	public static function add_cache_tags() {
		do_action( 'litespeed_tag_add', self::$cache_tags );
	}

	public static function db_error() {
		do_action( 'litespeed_control_set_nocache', 'nocache due to database error' );
		return new \WP_Error('database_error', 'Database error, please retry.',  ['status' => 500]);
	}

	public static function permissions_manage_semla() {
		return current_user_can( 'manage_semla' );
	}
}
