<?php
namespace Semla\Rest;

use Semla\Data_Access\Competition_Gateway;
use Semla\Data_Access\Competition_Group_Gateway;

/**
 * Handle semla-admin/ REST requests
 */
class Admin_Services {
	public static function leagues_cups( \WP_REST_Request $request ) {
		add_action( 'litespeed_tag_finalize', [Rest::class, 'add_cache_tags'] );
		$data = Competition_Group_Gateway::get_leagues_and_cups();
		if ($data === false) {
			return Rest::db_error();
		}
		return $data;
	}

	public static function competition_update( \WP_REST_Request $request ) {
		$comp_id = $request->get_param('comp_id');
		// might want to validate the comp_id here
		$params = $request->get_json_params();

		$response = [];
		if (isset($params['remarks'])) {
			$remarks = trim($params['remarks']);
			$affected = Competition_Gateway::update_remarks($comp_id, $remarks);
			if ($affected === false) {
				return Rest::db_error();
			}
			if ($affected) $response['remarks'] = $remarks;
		}
		return $response;
	}
}