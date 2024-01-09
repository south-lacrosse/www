<?php
namespace Semla\Rest;
use Semla\Data_Access\Club_Team_Gateway;
use Semla\Data_Access\DB_Util;
use Semla\Data_Access\Rest_Fixtures_Gateway;
use Semla\Data_Access\Table_Gateway;
/**
 * Handle team REST requests
 *
 *  /teams - html list of all teams, with links to fixtures and tables
 *  /teams/Bath - just points to tables & fixtures
 *  /teams/Bath/fixtures - html version
 *  /teams/Bath/fixtures.ics - calendar
 *  /teams/Bath/tables
 */
class Teams_Services {
	/**
 	 * Get and validate the team
	 * @return string|\WP_Error team, or error if unknown
	 */
	private static function get_team(\WP_REST_Request $request) {
		$error = Rest::validate_content_type($request);
		if ($error) return $error;
		$team = Rest::decode_club_team($request->get_param('team'));
		if (Club_Team_Gateway::validate_team($team)) return $team;
		return new \WP_Error('unknown_team', 'The requested team is unknown',  ['status' => 404]);
	}

	public static function teams_list( \WP_REST_Request $request ) {
		$error = Rest::validate_content_type($request);
		if ($error) return $error;
		$title = 'Teams';
		$teams = Club_Team_Gateway::get_team_names();
		ob_start();
		require __DIR__.'/views/info-header.php';
		if ($teams === false) {
			echo DB_Util::db_error();
		} else {
			require __DIR__.'/views/teams-list.php';
		}
		require __DIR__.'/views/info-footer.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function team_info( \WP_REST_Request $request ) {
		$team = self::get_team($request);
		if (is_wp_error($team)) return $team;
		$title = $team . ' Team';
		$parent = 'Teams';
		$type = 'team';
		ob_start();
		require __DIR__.'/views/info-header.php';
		require __DIR__.'/views/services.php';
		require __DIR__.'/views/info-footer.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function team_fixtures_ics( \WP_REST_Request $request ) {
		$request['extension'] = '.ics';
		$team = self::get_team($request);
		if (is_wp_error($team)) return $team;
		Rest::$cors_header = true;
		$ics = Rest_Fixtures_Gateway::get_fixtures_ics($team);
		if (!$ics) return Rest::db_error();
		return new \WP_REST_Response($ics);
	}

	public static function team_fixtures_tables( \WP_REST_Request $request ) {
		$team = self::get_team($request);
		if (is_wp_error($team)) return $team;
		Rest::$cors_header = true;
		$extension = empty($request['extension']) ? '.html' : $request['extension'];
		// .js sends html, the javascript to display it is added in Rest->pre_serve_request
		if ($extension === '.js') $extension = '.html';
		if ($request['type'] === 'tables') {
			$data = Table_Gateway::get_tables_for_team_club('team',$team,$extension);
			if ($data === false) return Rest::db_error();
			if ($extension === '.html') {
				$data = Rest_Util::update_tables_classes_for_rest($data, isset($request['classes']));
			}
		} else {
			$data = Rest_Fixtures_Gateway::get_fixtures('team',$team,$extension);
			if ($data === false) return Rest::db_error();
		}
		if ($extension !== '.json') {
			$data .= Rest::TAG_LINE;
		}
		return new \WP_REST_Response($data);
	}
}
