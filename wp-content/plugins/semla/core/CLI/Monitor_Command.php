<?php
namespace Semla\CLI;
/**
 * Monitor various WordPress functions.
 *
 * ## EXAMPLE
 *
 *     # Send a digest of posts for the last week to the webmaster
 *     $ wp monitor posts --email
 */
use \WP_CLI;
class Monitor_Command {
	/**
	 * Get a digest of post/page/club changes.
	 *
	 * [--start=<yyyy-mm-dd-hh-ii-ss>]
	 * : start modified date gmt, default midnight 8 days ago
	 *
	 * [--end=<yyyy-mm-dd-hh-ii-ss|now>]
	 * : end modified date gmt, default midnight this morning. "now" for current
	 * datetime
	 *
	 * [--email]
	 * : email the digest to the webmaster
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 */
	public function posts($args, $assoc_args) {
		global $wpdb;

		$date = new \DateTime('now',new \DateTimeZone( 'UTC' ));
		if (isset($assoc_args['end'])) {
			if ($assoc_args['end'] === 'now') {
				$assoc_args['end'] = $date->format('Y-m-d H:i:s');
			}
		} else {
			$assoc_args['end'] = $date->format('Y-m-d') . ' 00:00:00';
		}
		if (!isset($assoc_args['start'])) {
			$date->modify('-8 day');
			$assoc_args['start'] = $date->format('Y-m-d') . ' 00:00:00';
		}
		$title = 'Posts, pages, and clubs modified from ' . $assoc_args['start'] . ' to '
			. $assoc_args['end'] . ' GMT';
		$email = WP_CLI\Utils\get_flag_value( $assoc_args, 'email', false );

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT ID, post_title, post_type, post_status, post_name, post_modified_gmt
			FROM $wpdb->posts
			WHERE post_type IN ('post', 'page','clubs')
			AND post_modified_gmt BETWEEN %s AND %s
			ORDER BY post_modified_gmt", $assoc_args['start'], $assoc_args['end']));

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ($email) {
			ob_start();
		}
		if ( 'ids' === $format ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
		} else {
			$fields = WP_CLI\Utils\get_flag_value( $assoc_args, 'fields',
				[ 'ID', 'post_title', 'post_type', 'post_status', 'post_name', 'post_modified_gmt' ] );
			if (!$email && $format === 'table') {
				echo "$title\n";
			}
			WP_CLI\Utils\format_items( $format, $rows, $fields );
		}
		if ($email) {
			$result = ob_get_clean();
			wp_mail('webmaster@southlacrosse.org.uk', 'Posts digest',
				"<p>$title</p>\n<pre><code>$result</code></pre>",
				 ['Content-Type: text/html; charset=UTF-8']);
		}
	}

    /**
	 * Get list sessions of logged in users
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 */
	public function sessions($args, $assoc_args) {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT u.ID, u.user_login, u.user_email, m.meta_value
			FROM wp_users u, wp_usermeta m
			WHERE m.meta_key = 'session_tokens'
			AND u.ID = m.user_id;", ARRAY_A);

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( 'ids' === $format ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
			return;
		}
		$results = [];
		foreach ($rows as $row) {
			$tokens = maybe_unserialize( $row['meta_value'] );
			if (! is_array($tokens)) continue;
			foreach ($tokens as $token) {
				$results[] = array_merge($row, [
					'login' => wp_date('Y-m-d H:i:s', $token['login']),
					'expiration' => wp_date('Y-m-d H:i:s', $token['expiration']),
					'ip' => $token['ip'],
				]);
			}
		}
		$fields = WP_CLI\Utils\get_flag_value( $assoc_args, 'fields',
			[ 'ID', 'user_login', 'user_email', 'login', 'expiration', 'ip' ] );
		WP_CLI\Utils\format_items( $format, $results, $fields );
	}
}
