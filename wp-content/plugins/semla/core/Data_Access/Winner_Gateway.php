<?php
namespace Semla\Data_Access;
use Semla\Render\Winners_Renderer;
use Semla\Utils\Util;

/**
 * Data access for the History winners
 */
class Winner_Gateway {

	/**
	 * Return html for winners
	 */
	public static function get_winners($comp) {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT year, winner, runner_up, result, has_data
			FROM slh_winner
			WHERE comp_id = $comp->id ORDER BY year DESC");
		if ($wpdb->last_error) return false;
		if (count($rows) === 0) return '';
		if ($comp->group_id) {
			$next_prevs = $wpdb->get_results(
				"SELECT * FROM (
					(SELECT 'p' AS class, name, history_page FROM sl_competition
					WHERE group_id = $comp->group_id AND seq < $comp->seq
					AND history_page != ''
					ORDER BY seq DESC LIMIT 1)
					UNION ALL
					(SELECT 'n' AS class, name, history_page FROM sl_competition
					WHERE group_id = $comp->group_id AND seq > $comp->seq
					AND history_page != ''
					ORDER BY seq ASC LIMIT 1)
					) A");
			if ($wpdb->last_error) return false;
		}
		$fragment = $comp->section_name ? '#' . Util::make_id($comp->section_name) : '';
		ob_start();
		require __DIR__ . '/views/winners.php';
		$html = ob_get_clean();
		return $html;
	}

	/**
	 * Return html for competition group winners (i.e. league,
	 * or cup group like flags)
	 */
	public static function get_group_winners($group_id) {
		global $wpdb;

		$group = $wpdb->get_row( $wpdb->prepare(
			'SELECT type, page,
				(select count(*) FROM sl_competition c
					WHERE c.group_id = g.id) AS count
			FROM sl_competition_group g WHERE g.id = %d', $group_id));
		if ($wpdb->last_error) return false;
		if ($group->count > 4) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT w.comp_id, w.year, w.winner, w.has_data,
					COALESCE(c.section_name, c.name) AS name
				FROM slh_winner AS w, sl_competition AS c
				WHERE c.id = w.comp_id AND c.group_id = %d
				ORDER BY w.year DESC, c.seq', $group_id));
			$order_by = '';
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT w.comp_id, w.year, w.winner, w.has_data
				FROM slh_winner AS w, sl_competition AS c
				WHERE c.id = w.comp_id AND c.group_id = %d
				ORDER BY w.year DESC', $group_id));
			$order_by= ' ORDER BY seq, id';
		}
		if ($wpdb->last_error) return false;
		if (count($rows) === 0) return '';
		$col = ($group->type === 'league') ? '' : ', history_page';
		$comps = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, COALESCE(section_name, name) AS name$col
			FROM sl_competition
			WHERE group_id = %d$order_by", $group_id));
		if ($wpdb->last_error) return false;
		ob_start();
		if ($group->count > 4) {
			$comp_by_id = [];
			foreach ($comps as $comp) {
				$comp_by_id[$comp->id] = $comp;
			}
			Winners_Renderer::winners_variable($group->type, $comp_by_id, $rows, $group->page);
		} else {
			Winners_Renderer::winners_fixed($group->type, $comps, $rows, $group->page);
		}
		$html = ob_get_clean();
		return $html;
	}

	// TODO: Code below here is just an initial mock up - need to change table names etc

	function get_all_winners( $args = array() ) {
        global $wpdb;

        $defaults = [
            'number'     => 20,
            'offset'     => 0,
            'orderby'    => 'team',
            'order'      => 'ASC',
        ];
        $args      = wp_parse_args( $args, $defaults );
        $cache_key = 'team_abbrev-all';
        $items     = wp_cache_get( $cache_key, '' );
        if ( false === $items ) {
            $items = $wpdb->get_results( 'SELECT * FROM sl_team_abbrev ORDER BY ' . $args['orderby'] .' ' . $args['order'] .' LIMIT ' . $args['offset'] . ', ' . $args['number'] );
            wp_cache_set( $cache_key, $items, '' );
        }
        return $items;
    }

    static function get_winner_count() {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM sl_team_abbrev' );
    }

    static function get_winner( $id = 0 ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM sl_team_abbrev WHERE id = %d', $id ) );
    }}
