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

		$assoc_args = array_merge( [
			'fields' =>
				'ID,post_title,post_type,post_status,post_name,post_modified_gmt,post_author,user_login',
			'format' => 'table',
		], $assoc_args );

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

		$select = $from = $where = '';
		if ( 'ids' !== $assoc_args['format']
		&& str_contains($assoc_args['fields'], 'user_login')) {
			$select = ', user_login';
			$from = ", $wpdb->users AS u";
			$where = ' AND u.ID = post_author';
		}

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT p.ID, post_title, post_type, post_status, post_name, post_modified_gmt, post_author$select
			FROM $wpdb->posts AS p$from
			WHERE post_type IN ('post','page','clubs','revision')
			AND NOT (post_status = 'inherit' AND post_name LIKE '%autosave%')
			AND post_modified_gmt BETWEEN %s AND %s $where
			ORDER BY post_modified_gmt, p.ID", $assoc_args['start'], $assoc_args['end']));

		if ($email) {
			ob_start();
		}
		if ( 'ids' === $assoc_args['format'] ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
		} else {
			if (!$email && $assoc_args['format'] === 'table') {
				echo "$title\n";
			}
			WP_CLI\Utils\format_items( $assoc_args['format'], $rows, explode( ',', $assoc_args['fields'] ) );
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

		$assoc_args = array_merge( [
			'fields' => 'ID,user_login,user_email,login,expiration,ip',
			'format' => 'table',
		], $assoc_args );

		$rows = $wpdb->get_results(
			"SELECT u.ID, u.user_login, u.user_email, m.meta_value
			FROM wp_users u, wp_usermeta m
			WHERE m.meta_key = 'session_tokens'
			AND u.ID = m.user_id;", ARRAY_A);

		if ( 'ids' === $assoc_args['format'] ) {
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

		usort($results, static function($a, $b) {
			$cmp = strcmp($a['login'], $b['login']); // date
			if ($cmp) return $cmp;
			return $a['ID'] - $b['ID'];
		});

		WP_CLI\Utils\format_items( $assoc_args['format'], $results, explode( ',', $assoc_args['fields'] ) );
	}
}
