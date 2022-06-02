<?php
/**
 * Utility methods for network stuff called from multiple places
 */
namespace Semla\Utils;

class Net_Util {
    /**
     * Get file from a remote URL.
     * @return mixed string on success, WP_Error on failure.
     */
    public static function get_url($url, $follow_redirects = true) {
        $ch = self::get_curl($url);
        if (is_wp_error($ch)) {
            return $ch;
        }
 
        curl_setopt($ch, CURLOPT_HEADER, 0); // no headers
        // Fail the cURL request if response code = 400 (like 404 errors)
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        if (!$follow_redirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        }
 
        $data = curl_exec($ch);
 
        if (curl_errno($ch)) {
            $err = new \WP_Error('curl', 'cURL error ' . curl_errno($ch) . ': ' . curl_error($ch));
            curl_close($ch);
            return $err;
        }
        $resp = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($resp != 200) {
            $err = new \WP_Error('curl', 'invalid HTTP response code: ' . $resp);
            curl_close($ch);
            return $err;
        }
        curl_close($ch);
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
        curl_setopt($ch, CURLOPT_URL, $url);
        // Return the actual result of the curl result instead of success code
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Follow redirects, if any
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // 0 = indefinite
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // timeout of the actual request
        // Do not check the SSL certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
        if (is_wp_error($ch)) {
            return $ch;
        }
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // don't need body

        curl_exec($ch);
        if (curl_errno($ch)){   // should be 0
            $err = new \WP_Error('curl', 'cURL error ' . curl_errno($ch) . ': ' . curl_error($ch));
            curl_close($ch);
            return $err;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code == 200) {
            return true;
        }
        if ($code == 404) {
            return false;
        }
        return new \WP_Error('curl', "Failed: http error code: $code url: $url");
     }
}
