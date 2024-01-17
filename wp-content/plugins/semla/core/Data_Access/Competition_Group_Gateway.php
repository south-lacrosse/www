<?php
namespace Semla\Data_Access;
/**
 * Data access for competition groups. Groups can be a league (e.g. SEMLA or
 * Local), or a group of cups, e.g flags. We can then display the winners on
 * the same page, or display all tables for a league
 */
class Competition_Group_Gateway {

	public static function get_leagues() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT id, name, page, history_page, grid_page
			 FROM sl_competition_group WHERE type="league"');
	}

	public static function get_leagues_and_cups() {
		global $wpdb;
		$res = $wpdb->get_results(
			'SELECT id, type, name FROM sl_competition_group
			 WHERE type IN ("league","cup") AND history_only = 0
			 ORDER BY id');
		if ($wpdb->last_error) return false;
		return $res;
	}

	public static function get_history_competition_groups() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT id, name, type, history_page FROM sl_competition_group cg
			WHERE history_page != '' AND history_group_page");
	}
}
