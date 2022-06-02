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
			WHERE history_page != '' AND history_group_page
			AND EXISTS (SELECT * FROM sl_competition c
				WHERE c.group_id = cg.id
                AND c.has_history = 1)");
	}

    public static function get_all( $args = [] ) {
        global $wpdb;

        $defaults = [
            'number'     => 25,
            'offset'     => 0,
            'orderby'    => 'id',
            'order'      => 'ASC',
        ];
        $args      = wp_parse_args( $args, $defaults );
        return $wpdb->get_results( 'SELECT * FROM sl_competition_group ORDER BY '
            . $args['orderby'] .' ' . $args['order'] 
            .' LIMIT ' . $args['offset'] . ', ' . $args['number'] );
    }

    public static function get_count() {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM sl_competition_group' );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM sl_competition_group WHERE id = %d', $id )
            , ARRAY_A );
    }

    public static function insert_update( $id, $args = [] ) {
        global $wpdb;
        if ( !$id ) {
            return $wpdb->insert( 'sl_competition_group', $args );
        }
        return $wpdb->update( 'sl_competition_group', $args, ['id' => $id] );
    }

    /**
     * @return bool|string true if ok to delete, error message if not
     */
    public static function validate_delete( $id ) {
        global $wpdb;
        $result = $wpdb->get_var(
            $wpdb->prepare('SELECT EXISTS (SELECT * FROM sl_competition
                WHERE group_id = %d)', $id));
        if ($result === null) {
            return 'Error checking if group  has competitions';
        }
        if ($result) {
            return 'Cannot delete: group has existing competitions';
        }
        return true;
      }    

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( 'sl_competition_group', [ 'id' => $id ], [ '%d' ] );
      }    
}