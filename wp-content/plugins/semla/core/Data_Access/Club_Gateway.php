<?php
namespace Semla\Data_Access;
/**
 * Data access for Clubs post type
 */
class Club_Gateway {
	/**
	 * Get a WP_Query for all published clubs
	 */
	public static function get_all_clubs_query() {
		return new \WP_Query([
			'post_type' => 'clubs',
			'post_status' => 'publish',
			'nopaging' => true,
			'orderby' => 'title',
			'order' => 'ASC',
			// don't count rows, as we'll get them all anyway
			'no_found_rows' => true,
			 // don't load and cache the post meta or term data for each post
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		]);
	}

	public static function clubs_list($format) {
		$query = self::get_all_clubs_query();

		if (!$query->have_posts()) return '';
		ob_start();
		require __DIR__ . "/views/clubs-$format.php";
		wp_reset_postdata();
		return ob_get_clean();
	}

	public static function clubs_map() {
		$query = self::get_all_clubs_query();

		if (!$query->have_posts()) return '';
		ob_start();
		require __DIR__ . '/views/clubs-map.php';
		wp_reset_postdata();
		return ob_get_clean();
	}

	public static function get_club_emails($one_per_club) {
		$query = self::get_all_clubs_query();

		if (!$query->have_posts()) return '';
		$emails = [];
		while ($query->have_posts()) {
			$query->the_post();
			$club = get_the_title();
			$content = get_the_content();
			// social links
			if (preg_match('/{"url":"mailto:([^"]+)"/', $content, $matches)) {
				$emails[$matches[1]] = ['club' => $club, 'role' => 'General Contact', 'name'=> ''];
				if ($one_per_club) continue;
			}
			if ($count = preg_match_all('/<div class="avf-name">([^<]*)<\/div><div class="avf-value">([^<]*)[^!]*<a [^>]*href="mailto:([^"]*)"/',
				$content, $matches)) {
				for ($i = 0; $i < $count; $i++) {
					$email = $matches[3][$i];
					if (!isset($emails[$email])) {
						$emails[$email] = [
							'club' => $club,
							'role' => trim($matches[1][$i]),
							'name' => trim($matches[2][$i])
						];
					}
					if ($one_per_club) break;
				}
			}
		}
		wp_reset_postdata();
		return $emails;
	}

	public static function get_club_slugs() {
		global $wpdb;
		$res = $wpdb->get_col('SELECT post_name FROM wp_posts
			WHERE post_type = "clubs" AND post_status = "publish"');
		if ($wpdb->last_error) return false;
		return $res;
	}
}
