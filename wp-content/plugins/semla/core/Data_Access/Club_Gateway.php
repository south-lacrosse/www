<?php
namespace Semla\Data_Access;
/**
 * Data access for Clubs post type
 */
class Club_Gateway {
	/**
	 * Return an array of all club posts
	 * @return WP_Post[]
	 */
	public static function get_clubs() {
		return get_posts([
			'post_type' => 'clubs',
			'post_status' => 'publish',
			'nopaging' => true,
			'orderby' => 'title',
			'order' => 'ASC',
			 // don't load and cache the post meta or term data for each post
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		]);
	}

	public static function clubs_list($format) {
		// Use WP_Query here so that we can use the post thumbnail cache. Without
		// it WordPress will run 2 queries per thumbnail
		$query = new \WP_Query([
			'post_type' => 'clubs',
			'post_status' => 'publish',
			'nopaging' => true,
			'orderby' => 'title',
			'order' => 'ASC',
			// don't need all the filters the query usually runs
			'suppress_filters' => true
		]);

		if (!$query->have_posts()) return '';
		update_post_thumbnail_cache( $query );
		ob_start();
		require __DIR__ . "/views/clubs-$format.php";
		return ob_get_clean();
	}

	public static function clubs_map() {
		$clubs = self::get_clubs();
		if (!$clubs) return '';
		ob_start();
		require __DIR__ . '/views/clubs-map.php';
		return ob_get_clean();
	}

	/**
	 * Extract all emails from club pages
	 * @return array key email, value array with club,role,name
	 */
	public static function get_club_emails($one_per_club, $officers) {
		$emails = [];
		foreach (self::get_clubs() as $club) {
			// social links
			if (preg_match('/{"url":"mailto:([^"]+)"/', $club->post_content, $matches)) {
				$emails[$matches[1]] = ['club' => $club->post_title, 'role' => 'General Contact', 'name'=> ''];
			}
			self::extract_emails($club->post_title, $club->post_content, $emails);
		}
		if ($one_per_club && $emails) {
			// get one email per club by:
			//   set sort order depending on role
			//   sort
			//   remove duplicates for each club to leave just the most important contact
			$i = 0;
			foreach ($emails as $email => $values) {
				$role = $values['role'];
				if ($role === 'Secretary') {
					$sort = '0';
				} elseif ($role === 'General Contact') {
					$sort = '1';
				} elseif (str_starts_with($role,'Chair')) {
					$sort = '2';
				} elseif ($role === 'President') {
					$sort = '3';
				} else {
					$sort = '9';
				}
				$emails[$email]['sort'] = $values['club'] . $sort . str_pad($i++, 4, '0', STR_PAD_LEFT);
			}
			uasort($emails, function($a, $b) {
				return $a['sort'] <=> $b['sort'];
			});
			$last = '';
			foreach ($emails as $email => $values) {
				if ($values['club'] === $last) {
					unset($emails[$email]);
				} else {
					$last = $values['club'];
				}
			}
		}
		if ($officers && $contact_page = get_page_by_path('contact')) {
			$site_title = get_bloginfo( 'name' );
			self::extract_emails($site_title, $contact_page->post_content, $emails);
			$admin_email = get_bloginfo( 'admin_email' );
			if (!isset($emails[$admin_email])) {
				$emails[$admin_email] = [
					'club' => $site_title,
					'role' => 'Webmaster',
					'name' => ''
				];
			}
		}
		return $emails;
	}

	private static function extract_emails($club, $content, &$emails) {
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
		}
	}

	}

	public static function get_club_slugs() {
		global $wpdb;
		$res = $wpdb->get_col("SELECT post_name FROM {$wpdb->posts}
			WHERE post_type = 'clubs' AND post_status = 'publish'");
		if ($wpdb->last_error) return false;
		return $res;
	}
}
