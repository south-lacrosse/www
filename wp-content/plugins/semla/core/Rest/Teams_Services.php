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
		$team = Rest::decode_club_team($request->get_param('team'));
		if (Club_Team_Gateway::validate_team($team)) return $team;
		return new \WP_Error('unknown_team', 'The requested team is unknown',  ['status' => 404]);
	}

	public static function teams_list( \WP_REST_Request $request ) {
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
		Rest::$cors_header = true;
		$request['extension'] = '.ics';
		$team = Rest::decode_club_team($request->get_param('team'));
		// Might want to stick this in the database
		$alias = [
			'Hillcroft' => 'Hillcroft 1',
			'Purley' => 'Purley Raptors',
		];
		if (isset($alias[$team])) {
			$team = $alias[$team];
		}

		if (Club_Team_Gateway::validate_team($team)) {
			$ics = Rest_Fixtures_Gateway::get_fixtures_ics($team);
			if (!$ics) return Rest::db_error();
		} else {
			$removed_calendars = @include __DIR__ . '/team-removed-calendars.php';
			if (!$removed_calendars
			|| !isset($removed_calendars[$team])) {
				return new \WP_Error('unknown_team', 'The requested team is unknown',  ['status' => 404]);
			}
			// See https://github.com/south-lacrosse/www-dev/blob/main/docs/webmaster-tasks.md#calendars
			Rest_Fixtures_Gateway::log_fixtures_ics($team);
			ob_start();
			require __DIR__ . '/views/rest-unsubscribe-fixtures.ics.php';
			$ics = ob_get_clean();
		}
		return new \WP_REST_Response($ics);
	}

	public static function team_fixtures_tables( \WP_REST_Request $request ) {
		Rest::$cors_header = true;
		$team = self::get_team($request);
		if (is_wp_error($team)) return $team;
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

	public static function admin_update( \WP_REST_Request $request ) {
		$team = self::get_team($request);
		if (is_wp_error($team)) return $team;
		$params = $request->get_json_params();

		$response = [];
		if (isset($params['abbrev'])) {
			$abbrev = trim($params['abbrev']);
			$affected = Club_Team_Gateway::update_abbrev($team, $abbrev);
			if ($affected === false) {
				return Rest::db_error();
			}
			if ($affected) $response['abbrev'] = $abbrev;
		}
		if (isset($params['minimal'])) {
			$minimal = trim($params['minimal']);
			$affected = Club_Team_Gateway::update_minimal($team, $minimal);
			if ($affected === false) {
					return Rest::db_error();
			}
			if ($affected) $response['minimal'] = $minimal;
		}
		return $response;
	}
}
