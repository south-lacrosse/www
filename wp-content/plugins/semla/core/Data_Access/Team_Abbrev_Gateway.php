<?php
namespace Semla\Data_Access;

class Team_Abbrev_Gateway {
	public static function get_all( $args = [] ) {
		global $wpdb;

		$defaults = [
			'number'     => 25,
			'offset'     => 0,
			'orderby'    => 'team',
			'order'      => 'ASC',
		];
		$args   = wp_parse_args( $args, $defaults );
		$sql = 'SELECT * FROM sl_team_abbrev' . self::search_where($args);
		return $wpdb->get_results( $sql . ' ORDER BY '
			. $args['orderby'] .' ' . $args['order'] 
			.' LIMIT ' . $args['offset'] . ', ' . $args['number'] );
	}

	private static function search_where($args) {
		if (isset($args['search'])) {
			$search = esc_sql( $args['search'] );
			return " WHERE team LIKE '%{$search}%' OR abbrev LIKE '%{$search}%'";
		}
		return '';
	}

	public static function get_count($args) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM sl_team_abbrev' . self::search_where($args) );
	}

	public static function get( $team ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM sl_team_abbrev WHERE team = %s', $team )
			, ARRAY_A );
	}

	public static function insert_update( $insert, $args = [] ) {
		global $wpdb;
		if ( $insert ) {
			if ( $wpdb->insert( 'sl_team_abbrev', $args ) ) {
				return true;
			}
		} else {
			$team = $args['team'];
			unset( $args['team'] );
			if ( $wpdb->update( 'sl_team_abbrev', $args, ['team' => $team] ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public static function delete( $team ) {
		global $wpdb;
		return $wpdb->delete( 'sl_team_abbrev', [ 'team' => $team ], [ '%s' ] );
	  }
}
