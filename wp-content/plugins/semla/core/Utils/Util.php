<?php
/**
 * Utility methods called from multiple places
 */
namespace Semla\Utils;

class Util {
	/**
	 * Make an HTML id attribute from a string
	 */
	public static function make_id($str) {
		return urlencode(strtr($str,[
			' ' => '-', '(' => '',')' => '',
		]));
	}

	/**
	 * Format the time nicely. Seconds removed, minutes removed if
	 * zero, 24-hour to 12-hour with am/pm added
	 * @param $time time HH:MM:SS
	 * @return string formatted time
	 */
	public static function format_time($time) {
		$hms = explode(':',$time);
		if ($hms[0] > '11') {
			$am_pm = 'pm';
			if ($hms[0] !== '12') {
				$hms[0] -= 12;
			}
			$formatted = $hms[0];
		} else {
			$formatted = ltrim($hms[0], '0');
			$am_pm = 'am';
		}
		if ($hms[1] !== '00') {
			$formatted .= ':' . $hms[1];
		}
		$formatted .= $am_pm;
		return $formatted;
	}

	/**
	  * Return the fixtures sheet url for a given id, or from the options
	  * NB: does not return /edit, just up to that
	  */
	 public static function get_fixtures_sheet_url($id = '') {
		if (!$id) {
			$id = get_option('semla_fixtures_sheet_id');
		}
		return 'https://docs.google.com/spreadsheets/d/' . $id . '/';
	 }

	 /**
	  * Extract the website URL from post content
	  */
	 public static function get_the_website($post_content) {
		if (preg_match('!wp:semla/website {"url":"([^"]*)"!', $post_content, $matches)) {
			return $matches[1] ;
		}
		if (preg_match('/<a [^>]*href="([^"]*)"[^"]*>(Club |)Website/i', $post_content, $matches)) {
			return $matches[1];
		}
		return false;
	}
}
