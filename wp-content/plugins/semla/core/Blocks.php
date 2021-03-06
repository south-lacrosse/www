<?php
namespace Semla;
/**
 * Rendering of a couple of blocks
 */
class Blocks {
	public static function location($atts, $content) {
		$start = strpos($content, '<p>') + 3;
		$end = strpos($content, '</p>',$start);
		$addr = substr($content,$start,$end-$start);

		if (isset($atts['latLong'])) {
			// basic postcode extract - it just meets the format, no need for anything fancy
			if (preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/', $addr, $matches)) {
				if (preg_match('/^(B\d|BR|BS|DA|CF|CR|E\d|EN|HA|IG|KT|N\d|NW|RM|SE|SM|SW|TW|UB|W\d|WD)/', $matches[0])) {
					// Postcodes CityMapper works in
					// London: BR|DA|CR|E\d|EN|HA|IG|KT|N\d|NW|RM|SE|SM|SW|TW|UB|W\d|WD,
					// B\d=Birmingham, CF=Cardiff, BS=Bristol
					$content = substr($content,0,$end)
						. ' <a href="https://citymapper.com/directions?endcoord=' . $atts['latLong']
						. '&endname=' . urlencode(get_the_title() . ' Lacrosse Club')
						. '&endaddress=' . urlencode(html_entity_decode($addr)) . '"><i class="icon icon-citymapper"></i></a>'
						. substr($content,$end);
				}
			}
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
