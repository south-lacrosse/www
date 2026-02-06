<?php
namespace Semla\Rest;
/**
 * Utilities for REST requests
 */
class Rest_Util {
	/**
	 * Alter the html produced for tables to remove or amend classes for REST
	 * requests.
	 */
	public static function update_tables_classes_for_rest($html, $extra_classes) {
		$html = strtr($html, [
			'scrollable' => 'sl-wrapper',
			'table-data'=> 'sl-league-table',
			'divider' => 'sl-divider'
			]);
		if ($extra_classes) {
			// hide-small classes etc. are changed to add prefix
			$html = strtr($html ,[
				'"left"' => '"sl-team"',
				'hide-sml' => 'sl-hide-small',
				'"points"' => '"sl-points"'
			]);
		} else {
			$html = strtr($html ,[
				' class="left"' => '',
				' class="hide-sml"' => '',
				' class="points"' => '',
			]);
		}
		return $html;
	}

	public static function json_encode($value) {
		$str = json_encode($value);
		// PHP mysql interface always returns strings, so convert numeric
		// looking fields into appropriate types
		// Note: not using JSON_NUMERIC_CHECK on json_encode as that
		// replaces "1.09" with 1.090000000001
		$str = preg_replace('/:"(\d+(\.\d+|))"/',':$1',$str);
		// convert snake_case to camelCase
		return preg_replace_callback('/_([a-z])/',
			function ($matches) { return strtoupper($matches[1]); },
			$str);
	}
}
