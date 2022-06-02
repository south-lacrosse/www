<?php
namespace Semla\Data_Access;
/**
 * Database utilities
 */
class DB_Util {
	private static $tables_to_rename = [];
	private static $sql = [];

	/**
	 * Create a new table. The $sql must be the table definition SQL which
	 * would go inside the brackets.
	 * @return boolean success
	 */
	public static function create_table($table_name, $sql, $drop = true) {
		global $wpdb;
		if ($drop) {
			$result = $wpdb->query("DROP TABLE IF EXISTS `$table_name`");
			if ($result === false)
				return false;
		}
		$result = $wpdb->query("CREATE TABLE IF NOT EXISTS `$table_name` (
			$sql
           ) ENGINE = InnoDB " . $wpdb->get_charset_collate());
		if ($result === false)
			return false;
		return true;
	}

	/**
	 * add a table to rename later - see on_success().
	 */
	public static function add_table_to_rename($table) {
		self::$tables_to_rename[] = $table;
	}
	
	public static function add_sql_on_success($sql) {
		self::$sql[] = $sql;
	}

	/**
	 * run the renames as specified with add_table_to_rename(), and any sql
	 * from add_sql_on_success().
 	 * 
	 * Renames: The current table will be renamed to backup_<table>
	 * (if it exists), new_ will be renamed to slc_<table>
	 */
	public static function on_success() {
		global $wpdb;

		// get a list of slc_ tables to we can decide if they need to be renamed to backup_
		$rows = $wpdb->get_results(
            'SELECT TABLE_NAME FROM information_schema.TABLES 
			WHERE TABLE_CATALOG = "def" AND TABLE_SCHEMA = "' . DB_NAME . '"
				AND TABLE_TYPE = "BASE TABLE"
				AND TABLE_NAME LIKE "slc_%"');
        if ($wpdb->last_error)
			return new \WP_Error('sql', 'Failed to list current tables : SQL error: ' . $wpdb->last_error);
		$slc_tables = [];
		foreach ($rows as $row) {
			$slc_tables[$row->TABLE_NAME] = 1;
		}

		$result = $wpdb->query('DROP TABLE IF EXISTS backup_' 
			. implode(', backup_', self::$tables_to_rename));
		if ($result === false)
			return new \WP_Error('sql', 'Failed to drop backup tables : SQL error: ' . $wpdb->last_error);

		// We rename the tables individually so as not to lock too much at a time. If this
		// causes problems then the code should be changes to create a big SQL statement of
		// all renames, and execute that.
		foreach (self::$tables_to_rename as $table) {
			$backup = "backup_$table";
			$new = "new_$table";
			$current = "slc_$table";
			if ( array_key_exists($current, $slc_tables) ) {
				$result = $wpdb->query("RENAME TABLE $current TO $backup, $new TO $current;");
			} else {
				$result = $wpdb->query("RENAME TABLE $new TO $current;");
			}
			if ($result === false) {
				return new \WP_Error('sql', 'Failed to rename table '. $table .' SQL error: ' . $wpdb->last_error);
			}
		}
		foreach (self::$sql as $sql) {
			$result = $wpdb->query($sql);
			if ($result === false) {
				return new \WP_Error('sql', 'Failed to run sql "' . $sql . '" SQL error: ' . $wpdb->last_error);
			}
		}
        return true;
	}

	public static function db_error() {
		do_action( 'litespeed_control_set_nocache', 'nocache due to database error' );
		return '<p>Database error - please retry, or if the fault continues contact the webmaster.</p>';
	}
}
