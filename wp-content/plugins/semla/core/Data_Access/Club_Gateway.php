<?php
namespace Semla\Data_Access;
/**
 * Data access for Clubs
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
	public static function clubs_list() {
		$query = self::get_all_clubs_query();

		if (!$query->have_posts()) return '';
		ob_start();
		require __DIR__ . '/views/clubs-list.php';
		wp_reset_postdata();
		return ob_get_clean();
    }
    
	public static function clubs_map() {
		$query = self::get_all_clubs_query();

		if (!$query->have_posts()) return '';
		wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?v=3&key='
				. get_option('semla_gapi_key') . '&libraries=places&region=GB',
				null, null, true);
		ob_start();
		require __DIR__ . '/views/clubs-map.php';
		wp_reset_postdata();
		return ob_get_clean();
	}
}
