<?php
/**
 * Utility methods for network stuff called from multiple places
 *
 * If we will be doing multiple curl requests per session then this class
 * should be rewritten to reuse the curl handle as that is much more
 * efficient.
 */
namespace Semla\Utils;

class Net_Util {
	/**
	 * Get file from a remote URL.
	 * @return mixed string on success, WP_Error on failure.
	 */
	public static function get_url($url, $expected_content_type = '') {
		$ch = self::get_curl($url);
		if (is_wp_error($ch)) $ch;

		curl_setopt_array($ch, [
			CURLOPT_HEADER => 0, // no headers
			// Fail the cURL request if response code = 400 (like 404 errors)
			CURLOPT_FAILONERROR => true,
			CURLOPT_ENCODING => '', // all supported encodings
		]);

		$data = curl_exec($ch);

		if (curl_errno($ch)) {
			return new \WP_Error('curl', 'cURL error ' . curl_errno($ch) . ': ' . curl_error($ch));
		}
		$resp = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if ($resp != 200) {
			return new \WP_Error('curl', "invalid HTTP response code: $resp");
		}
		if ($expected_content_type) {
			$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			if ($expected_content_type !== $content_type) {
				return new \WP_Error('curl_unexpected_response_type',
					'Unexpected content type getting remote content.' .
					" Expected $expected_content_type, received $content_type, URL $url");
			}
		}
		return $data;
	 }

	/**
	 * Get curl handle with basic setup
	 */
	private static function get_curl($url) {
		$ch = curl_init();
		if (!$ch) {
			return new \WP_Error('no_curl', 'Couldn\'t initialize a cURL handle');
		}
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT => 60,
		]);
		// Do not check the SSL certificates for Windows as it can cause problems unless
		// there's a certificates file specified
		if (PHP_OS_FAMILY === 'Windows' && !ini_get('curl.cainfo')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		// curl_setopt($ch, CURLOPT_VERBOSE, true); // debug
		return $ch;
	 }

	 /**
	 * See if a remote URL exists
	 *
	 * @return mixed true if exists, false if not, string if error
	 */
	public static function url_exists($url) {
		$ch = self::get_curl($url);
		if (is_wp_error($ch)) return $ch;
		curl_setopt_array($ch, [
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => true,
		]);

		curl_exec($ch);
		if (curl_errno($ch)){   // should be 0
			return new \WP_Error('curl', 'cURL error ' . curl_errno($ch) . ': ' . curl_error($ch));
		}
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($code == 200) return true;
		if ($code == 404) return false;
		return new \WP_Error('curl', "Failed: http error code: $code url: $url");
	 }
}
