<?php
namespace Semla;
/**
 * Rendering of a couple of blocks
 */
class Blocks {
	public static function location($attrs, $content, $block) {
		$start = strpos($content, '<p>') + 3;
		$end = strpos($content, '</p>', $start);
		$addr = substr($content, $start, $end-$start);

		// basic postcode extract - it just meets the format, no need for anything fancy
		if (preg_match('/[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}/', $addr, $matches)) {
			// Postcodes CityMapper works in
			// London: BR|DA|CR|E\d|EN|HA|IG|KT|N\d|NW|RM|SE|SM|SW|TW|UB|W\d|WD,
			// B\d=Birmingham, CF=Cardiff, BS=Bristol
			if (preg_match('/^(B\d|BR|BS|DA|CF|CR|E\d|EN|HA|IG|KT|N\d|NW|RM|SE|SM|SW|TW|UB|W\d|WD)/', $matches[0])) {
				// Find the map block inside the location to get the latLong from there
				$latLong = false;
				foreach ($block->parsed_block['innerBlocks'] as $innerBlock) {
					if ($innerBlock['blockName'] === 'semla/map') {
						$latLong = $innerBlock['attrs']['latLong'] ?? false;
						break;
					}
				}
				if ($latLong) {
					$content = substr($content,0,$end)
						. ' <a href="https://citymapper.com/directions?endcoord='  . $latLong
						. '&endname=' . urlencode(get_the_title() . ' Lacrosse Club')
						. '&endaddress=' . urlencode(html_entity_decode($addr))
						. '" title="CityMapper directions"><i class="icon icon-citymapper"></i></a>'
						. substr($content,$end);
				}
			}
		}
		return $content;
	}

	public static function map($attrs, $content) {
		if (isset($attrs['latLong'])) {
			return str_replace('!MAP!',
				'<iframe class="gmap" data-url="https://www.google.com/maps/embed/v1/place?q='
				. $attrs['latLong'] . '&amp;zoom=15&amp;key=' . get_option('semla_gapi_key')
				. '" title="Google Map" allowFullScreen></iframe>', $content);
		}
		return '';
	}
}
