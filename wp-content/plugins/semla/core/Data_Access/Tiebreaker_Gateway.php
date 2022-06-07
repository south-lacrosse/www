<?php
namespace Semla\Data_Access;
/**
 * Data access for head 2 head tables
 */
class Tiebreaker_Gateway {

    public static function show_tiebreakers() {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT c.name, h.original_points, h.position, h.original_position,
                h.team, h.points, h.goal_diff, h.wins
            FROM slc_tiebreaker h, sl_competition c
            WHERE c.id = h.comp_id
            ORDER BY c.seq, h.position', ARRAY_A);
        if ($wpdb->last_error) {
            echo "<p>Database error: $wpdb->last_error</p>";
            return;
        }
        if (!$rows) {
            echo '<p>There are no tiebreakers.</p>';
            return;
        }
        require __DIR__ . '/views/tiebreaker-table.php';
    }
        
    public static function create_table() {
        $result = DB_Util::create_table('new_tiebreaker',
            '`comp_id` SMALLINT UNSIGNED NOT NULL,
            `position` TINYINT UNSIGNED NOT NULL,
            `original_position` TINYINT UNSIGNED NOT NULL,
            `team` VARCHAR(50) NOT NULL,
            `original_points` SMALLINT NOT NULL,
            `points` SMALLINT NOT NULL,
            `goal_diff` SMALLINT NOT NULL,
            `wins` SMALLINT NOT NULL,
            PRIMARY KEY (`comp_id`, `position`),
            UNIQUE KEY `team_comp` (`team`,`comp_id`)');
        if ($result === false) return false;
        DB_Util::add_table_to_rename('tiebreaker');
        return true;
    }

    public static function save($comp_id, $start, $points, $tiebreaker) {
        global $wpdb;
        $query = 'INSERT INTO new_tiebreaker (comp_id, position, original_position,
            team, original_points, points, goal_diff, wins) VALUES ';
        foreach ($tiebreaker as $team => $data) {
            $values[] = $wpdb->prepare(
                "($comp_id,$start,$data[position],%s,$points,$data[points],$data[goal_diff],$data[wins])", $team);
            $start++;
        }
        $query .= implode( ",\n", $values );
        $result = $wpdb->query($query);
        if ($result === false) return false;
        return true;
    }
}