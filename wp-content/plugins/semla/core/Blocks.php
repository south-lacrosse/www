<?php
namespace Semla;
/**
 * Rendering of a couple of blocks
 */
class Blocks {

	public static function contact($attrs) {
		$html = '<div class="wp-block-semla-contact'
			. ( $attrs['sameLine'] ? ' avf-same-line' : '')
			. '"><div class="avf-name">'
			. htmlentities($attrs['role'] ?? '', ENT_COMPAT|ENT_SUBSTITUTE) . '</div><div class="avf-value">';
		$value = htmlentities($attrs['name'] ?? '', ENT_COMPAT|ENT_SUBSTITUTE);
		if (!empty($attrs['email'])) {
			if ($value) $value .= '<br>';
			$email = esc_attr($attrs['email']);
			$value .= "<a href=\"mailto:$email\">$email</a>";
		}
		if (!empty($attrs['tel'])) {
			if ($value) $value .= '<br>';
			$tel = esc_attr($attrs['tel']);
			$value .= '<a href="tel:' . str_replace(' ','',$tel) . '">' . $tel . '</a>';
		}
		$html .= "$value</div></div>\n";
		return $html;
	}


	public static function club_title( $attrs, $content, $block ) {
		if ( ! isset( $block->context['postId'] ) ) return '';
		$post_ID = $block->context['postId'];

		add_filter('max_srcset_image_width', [self::class, 'max_srcset_image_width']);
		$featured_image = get_the_post_thumbnail( $post_ID, 'thumbnail',
			['class' => 'club-icon-img'] );
		remove_filter('max_srcset_image_width', [self::class, 'max_srcset_image_width']);
		if ( ! $featured_image ) {
			return $content;
		}
		return '<div class="wp-block-semla-club-title">' . "\n"
			. '<div class="club-icon">' . "\n$featured_image\n</div>\n"
			. '<div class="club-title-content">' . "\n"
			. "$content\n</div>\n</div>\n";
	}
	public static function max_srcset_image_width() {
		return 1;
	}

	public static function location($attrs, $content, $block) {
		$start = strpos($content, '<p>') + 3;
		$end = strpos($content, '</p>', $start);
		$addr = substr($content, $start, $end-$start);

		if (!isset($attrs['mapperLinks']) || $attrs['mapperLinks']) {
			// Find the map block inside the location to get the latLong from there
			$latLong = false;
			foreach ($block->parsed_block['innerBlocks'] as $innerBlock) {
				if ($innerBlock['blockName'] === 'semla/map') {
					$latLong = $innerBlock['attrs']['latLong'] ?? false;
					break;
				}
			}
			if ($latLong) {
				$content = substr($content, 0, $end)
					. ' <a href="https://citymapper.com/directions?endcoord='  . $latLong
					. '&endname=' . urlencode(get_the_title() . ' Lacrosse Club')
					. '&endaddress=' . urlencode(html_entity_decode($addr))
					. '" title="CityMapper directions"><i class="icon icon-citymapper"></i></a>'
					. substr($content,$end);
			}
		}
		return $content;
	}

	public static function map($attrs, $content) {
		if (!isset($attrs['latLong'])) return '';
		return '<div class="wp-block-semla-map">'
			. '<button class="acrd-btn" data-toggle="collapse" aria-expanded="false">Map and Directions</button>'
			. '<div class="acrd-content">'
			. '<iframe class="gmap" data-url="https://www.google.com/maps/embed/v1/place?q='
			. $attrs['latLong'] . '&amp;zoom=15&amp;key=' . get_option('semla_gapi_key')
			. '" title="Google Map" allowFullScreen></iframe>' . $content . '</div></div>';
	}

	/**
	 * Filter to override post-date core block. For "modified" display type, and
	 * displayed on Club page (i.e. with entry-meta class) then always display
	 * modified date (core block does not display if = post date).
	 */
	public static function render_post_date($block_content, $block, $instance) {
		if ($block['attrs']['displayType'] ?? '' ===  'modified'
		&& str_contains($block['attrs']['className'] ?? '', 'entry-meta')) {
			if (empty($block_content)) {
				$post_ID          = $instance->context['postId'];
				$attributes       = $block['attrs'];
				$formatted_date   = get_the_modified_date( empty( $attributes['format'] ) ? '' : $attributes['format'], $post_ID );
				$unformatted_date = esc_attr( get_the_modified_date( 'c', $post_ID ) );

				return sprintf(
					'<div class="wp-block-post-date__modified-date %1$s wp-block-post-date">Modified: <time datetime="%2$s">%3$s</time></div>',
					$attributes['className'],
					$unformatted_date,
					$formatted_date
				);
			}
			$block_content = str_replace('<time', 'Modified: <time', $block_content);
		}
		return $block_content;
	}

	public static function website( $attrs ) {
		if (!isset($attrs['url'])) return '';
		$url = $attrs['url'];

		// Remove protocol and www prefixes.
		$pretty_url = preg_replace('/^(?:https?:)\/\/(?:www\.)?/', '', $url );
		// Ends with / and only has that single slash, strip it.
		$pos = strpos($pretty_url,'/');
		if ($pos !== -1 && $pos === strlen($pretty_url) - 1) {
			$pretty_url = substr($pretty_url, 0, -1);
		}

		return '<p class="wp-block-semla-website">Website: <a href="' . $url
			. '">' . $pretty_url . "</a></p>\n";
	}
}
