<?php
namespace Semla\Data_Access;
use stdClass;
/**
 * Data access for History
 */
class History_Gateway {
    /**
     * Returns an array of row counts for all history tables
     */
    public static function get_stats() {
        global $wpdb;
        // need accurate counts so cannot use TABLE_ROWS column as that is inaccurate
        // for InnoDB tables
		$rows = $wpdb->get_results(
            'SELECT TABLE_NAME FROM information_schema.TABLES 
			WHERE TABLE_CATALOG = "def" AND TABLE_SCHEMA = "' . DB_NAME . '"
				AND TABLE_TYPE = "BASE TABLE"
				AND TABLE_NAME LIKE "slh_%"');
        if ($wpdb->last_error) return false;
        if (count($rows) === 0) return [];
        // build our SQL - might as well do it all in one query
        $sql = [];
        foreach ($rows as $row) {
            $sql[] = "SELECT '$row->TABLE_NAME' AS table_name, COUNT(*) AS row_count FROM $row->TABLE_NAME";
        }
        return $wpdb->get_results(implode("\nUNION ", $sql));
    }
}
