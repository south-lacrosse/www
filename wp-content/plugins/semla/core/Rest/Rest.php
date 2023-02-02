<?php
namespace Semla\Rest;

use Semla\Data_Access\Competition_Group_Gateway;
/**
 * Handle REST requests
 *
 * semla/v1 endpoints:
 *      /clubs - html list of all clubs, with links to pages with club's
 *               services and team's services
 *      /clubs.txt - text list of all clubs
 *      /clubs.gpx - GPS data in gpx format
 *      /clubs/Bath - details of all REST services for club
 *      /clubs/Bath/fixtures - html snippet, .json, or .js to embed
 *      /clubs/Bath/tables - html snippet, .json, or .js to embed
 *
 *      /teams - html list of all teams, with links to fixtures and tables
 *      /teams/Bath - details of all REST services for team
 *      /teams/Bath/fixtures - html snippet, .json, or .js to embed
 *      /teams/Bath/fixtures.ics - calendar
 *        also aliased using add_rewrite_rule in App.php to URI /fixtures_Bath.ics
 *        If the endpoint changes make sure to update that rule, and flush rewrite rules
 *      /teams/Bath/tables - html snippet, .json, or .js to embed
 *
 * semla-admin/v1: JSON endpoints to supply data for our custom blocks
 *      /leagues-cups
  */
class Rest {
	const TAG_LINE = '<p class="sl-tagline"><small>Data provided by <a href="https://www.southlacrosse.org.uk/">southlacrosse.org.uk</a></small></p>';
	const CONTENT_TYPES = [
		'.gpx' => 'application/gpx+xml',
		'.ics' => 'text/calendar',
		'.js' => 'application/javascript',
		'.json' => 'application/json',
		'.txt' => 'text/plain',
	];
	const SEMLA_BASE = 'semla/v1';
	const SEMLA_PREFIX = '/semla/v1/';
	const SEMLA_ADMIN_BASE = 'semla-admin/v1';
	public static $cache_tags = ['semla_data']; // can be overridden by routes
	public static $cors_header = false;

	public static function init() {
		// if clubs have changed then purge pages/rest routes which use club data
		add_action( 'save_post_clubs', function() {
			do_action( 'litespeed_purge', 'semla_clubs' );
		});

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
			if ( ! empty( $result ) ) {
				return $result;
			}
			if ( ! is_user_logged_in()
			&& ! preg_match('!^/(\?rest_route=|' . rest_get_url_prefix() . ')/semla/!', $_SERVER['REQUEST_URI']) ) {
				return new \WP_Error( 'rest_not_logged_in', 'API Requests are only supported for authenticated requests.', ['status' => 401] );
			}
			return $result;
		});

		// send our responses in the right format
		add_filter( 'rest_pre_serve_request', [self::class, 'pre_serve_request'], 10, 4 );

		register_rest_route( self::SEMLA_ADMIN_BASE, '/leagues-cups', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [self::class, 'leagues_cups'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route( self::SEMLA_BASE, '/teams', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'teams_list'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w\+ ]+)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'team_info'],
			'args' => [
				'team' => [
					'required'	=> true,
					'type'		=> 'string',
					'validate_callback' => [Teams_Services::class, 'validate_team']
				]
			],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w\+% ]+)/fixtures.ics', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class, 'team_fixtures_ics'],
			'args' => [
				'team' => [
					'validate_callback' => [Teams_Services::class, 'validate_team']
				]
			],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/teams/(?P<team>[\w\+ ]+)/(?P<type>fixtures|tables)(?P<extension>|.js|.json)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Teams_Services::class,'team_fixtures_tables'],
			'args' => [
				'team' => [
					'validate_callback' => [Teams_Services::class, 'validate_team']
				]
			],
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
		register_rest_route( self::SEMLA_BASE, '/clubs.txt', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class, 'clubs_txt'],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs/(?P<club>[\w\+ ]+)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class, 'club_info'],
			'args' => [
				'club' => [
					'validate_callback' => [Clubs_Services::class, 'validate_club']
				]
			],
			'permission_callback' => '__return_true',
		]);
		register_rest_route( self::SEMLA_BASE, '/clubs/(?P<club>[\w\+ ]+)/(?P<type>fixtures|tables)(?P<extension>|.js|.json)', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [Clubs_Services::class,'club_fixtures_tables'],
			'args' => [
				'club' => [
					'validate_callback' => [Clubs_Services::class, 'validate_club']
				]
			],
			'permission_callback' => '__return_true',
		]);
	}

	/**
	 * Checks if the HTTP headers allow the correct content type e.g. text/html,
	 *  if not then it's an error
	 * @return \WP_Error, or false if valid
	 */
	public static function validate_content_type( \WP_REST_Request $request ) {
		if (!empty($request['extension'])) {
			$allowed_content_type = self::CONTENT_TYPES[$request['extension']];
		} else {
			$allowed_content_type = 'text/html';
		}
		$accept = $request->get_header('accept');
		if ($accept && strpos($accept, '*/*') === false
		&& strpos($accept, $allowed_content_type) === false) {
			return new \WP_Error('content_type_not_supported', 'The requested content type is not supported',  ['status' => 501]);
		}
		$request['content_type'] = $allowed_content_type;
		return false;
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
		// handle our own errors. JSON and js will get json, text/html gets html,
		// everything else gets a text response
		if ($result->status != 200) {
			if (str_starts_with($request['content_type'], 'app') ) {
				$request['content_type'] = 'application/json';
			} elseif ($request['content_type'] !== 'text/html') {
				$request['content_type'] = 'text/plain';
			}
		}

		if (! headers_sent() ) {
			$server->send_header( 'Content-Type',
				$request['content_type'] . '; charset=utf-8' );
			$server->send_header( 'X-Robots-Tag', 'noindex,nofollow' );
			if ($request['content_type'] === 'text/html') {
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
			if ($request['content_type'] === 'application/json') {
				echo json_encode($data);
			} else {
				$err = 'Error code: ' . $data['code'] . ', message: ' . $data['message'];
				if ($request['content_type'] === 'text/html') {
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

	public static function leagues_cups( \WP_REST_Request $request ) {
		add_action( 'litespeed_tag_finalize', [self::class, 'add_cache_tags'] );
		$data = Competition_Group_Gateway::get_leagues_and_cups();
		if ($data === false) {
			return Rest::db_error();
		}
		return $data;
	}

	public static function add_cache_tags() {
		do_action( 'litespeed_tag_add', self::$cache_tags );
	}

	public static function db_error() {
		do_action( 'litespeed_control_set_nocache', 'nocache due to database error' );
		return new \WP_Error('database_error', 'Database error, please retry.',  ['status' => 500]);
	}
}
