<?php
/**
 * Utility methods called from multiple blocks
 */
namespace Semla\Utils;

class Block_Util {
	public static function change_page_title($page_title, $h1_title = null) {
		if ($h1_title == null) $h1_title = $page_title;
		if ($page_title) {
			add_filter( 'single_post_title', function($title) use ($page_title) {
				return $page_title;
			});
		}
		if ($h1_title) {
			global $post;
			$post_id = $post->ID;
			add_filter( 'the_title', function($title, $id) use ($h1_title, $post_id) {
				if ($post_id != $id || !apply_filters( 'semla_change_the_title', true )) {
					return $title;
				}
				return $h1_title;
			}, 10, 2);
		}
	}

	/**
	 * Add preconnect hints for an array list of domains - no http:// or trailing /,
	 * so things like maps.googleapis.com
	 * Will also remove any dns-prefetch hints added by WordPress
	 */
	public static function preconnect_hints($preconnect) {
		add_filter( 'wp_resource_hints', function ( $hints, $relation_type ) use ($preconnect) {
			if ( 'preconnect' === $relation_type ) {
				foreach ( $preconnect as $url ) {
					$hints[] = "https://$url";
				}
			} elseif ( 'dns-prefetch' === $relation_type ) {
				foreach ( $preconnect as $url ) {
					if ( ( $key = array_search( $url, $hints ) ) !== false ) {
						unset( $hints[ $key ] );
					}
				}
			}
			return $hints;
		}, 10, 2 );
	}
}
