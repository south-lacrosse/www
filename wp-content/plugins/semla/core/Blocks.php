<?php
namespace Semla;
/**
 * Rendering of a couple of blocks
 */
class Blocks {
	public static function location($atts, $content) {
		if (isset($atts['latLong'])) {
            $content = str_replace('!MAP!',
                '<iframe class="gmap" data-url="https://www.google.com/maps/embed/v1/place?q='
				. $atts['latLong'] . '&amp;zoom=15&amp;key=' . get_option('semla_gapi_key')
                . '" title="Google Map" allowFullScreen></iframe>',$content);
            return $content;
		}
		return '';
	}

    public static function toc($atts, $content) {
        self::enqueue_script('toct','1.0',true);
        return $content;
    }

    private static function enqueue_script($src,$ver,$async=false) {
        wp_enqueue_script( 'semla-' . $src,
            plugins_url('js/' . $src . SEMLA_MIN . '.js' . ($async ? '#async' : '')
                , __DIR__), [],  $ver, true );
    }
}
