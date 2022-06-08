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
	private static function urldecode_team($team) {
		// Some calendar apps replace + with %2b, so we need to decode the url, then
		// also substitute the +
		return str_replace('+',' ',urldecode($team));
	}
	public static function validate_team($value, $request, $param) {
		return Club_Team_Gateway::validate_team(self::urldecode_team($value));
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
		$error = Rest::validate_content_type($request);
		if ($error) return $error;
		$team = $request['team'];
		$title = self::urldecode_team($team) . ' Team';
		$parent = 'Teams';
		ob_start();
		require __DIR__.'/views/info-header.php';
		require __DIR__.'/views/services.php';
		require __DIR__.'/views/info-footer.php';
		return new \WP_REST_Response(ob_get_clean());
	}

	public static function team_fixtures_ics( \WP_REST_Request $request ) {
		$request['extension'] = '.ics';
		$error = Rest::validate_content_type($request);
		if ($error) return $error;
		Rest::$cors_header = true;
		$team = self::urldecode_team($request['team']);
		$ics = Rest_Fixtures_Gateway::get_fixtures_ics($team);
		if (!$ics) return Rest::db_error();
		return new \WP_REST_Response($ics);
	}

	public static function team_fixtures_tables( \WP_REST_Request $request ) {
		$error = Rest::validate_content_type($request);
		if ($error) return $error;
		Rest::$cors_header = true;
		$team = self::urldecode_team($request['team']);
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
