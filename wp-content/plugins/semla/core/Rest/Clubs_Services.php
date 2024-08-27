<?php
namespace Semla\Rest;
use Semla\Data_Access\Club_Gateway;
use Semla\Data_Access\Club_Team_Gateway;
use Semla\Data_Access\DB_Util;
use Semla\Data_Access\Rest_Fixtures_Gateway;
use Semla\Data_Access\Table_Gateway;
/**
 * Handle club REST requests
 *
 *	  /clubs - html list of all clubs, with links to clubs fixtures and tables, and teams
 *	  /clubs.gpx - GPS data in gpx format
 *	  /clubs/Bath - just points to tables & fixtures
 *	  /clubs/Bath/fixtures
 *	  /clubs/Bath/tables
 */
class Clubs_Services {
	/**
	 * Get and validate the club
	 * @return string|\WP_Error club, or error if unknown
	 */
	private static function get_club(\WP_REST_Request $request) {
		$club = Rest::decode_club_team($request->get_param('club'));
		$valid = Club_Team_Gateway::validate_club($club);
		if ($valid === null) return Rest::db_error();
		if ($valid) return $club;
		return new \WP_Error('unknown_club', 'The requested club is unknown',  ['status' => 404]);

	}

	public static function clubs_list( \WP_REST_Request $request ) {
		$title = 'Clubs';
		$clubs = Club_Team_Gateway::get_clubs_teams();
		ob_start();
		require __DIR__.'/views/info-header.php';
		if ($clubs === false) {
			echo DB_Util::db_error();
		} else {
			require __DIR__.'/views/clubs-list.php';
		}
		require __DIR__.'/views/info-footer.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function clubs_gpx( \WP_REST_Request $request ) {
		$request['extension'] = '.gpx';
		Rest::$cors_header = true;
		$clubs = Club_Gateway::get_clubs();
		Rest::$cache_tags = ['semla_clubs'];
		ob_start();
		require __DIR__ . '/views/clubs-gpx.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function club_info( \WP_REST_Request $request ) {
		Rest::$cors_header = true;
		$club = self::get_club($request);
		if (is_wp_error($club)) return $club;
		$title = 'Services for ' . $club . ' Club';
		$parent = 'Clubs';
		$type = 'club';
		ob_start();
		require __DIR__.'/views/info-header.php';
		require __DIR__.'/views/services.php';
		require __DIR__.'/views/info-footer.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function club_fixtures_tables( \WP_REST_Request $request ) {
		Rest::$cors_header = true;
		$club = self::get_club($request);
		if (is_wp_error($club)) return $club;
		$extension = empty($request['extension']) ? '.html' : $request['extension'];
		// .js sends html, the javascript to display it is added in Rest->pre_serve_request
		if ($extension === '.js') $extension = '.html';
		if ($request['type'] === 'tables') {
			$data = Table_Gateway::get_tables_for_team_club('club',$club,$extension);
			if ($data === false) return Rest::db_error();
			if ($extension === '.html') {
				$data = Rest_Util::update_tables_classes_for_rest($data, isset($request['classes']));
			}
		} else {
			$data = Rest_Fixtures_Gateway::get_fixtures('club',$club,$extension);
			if ($data === false) return Rest::db_error();
		}
		if ($extension !== '.json') {
			$data .= Rest::TAG_LINE;
		}
		return new \WP_REST_Response($data);
	}
}
