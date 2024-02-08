<?php
namespace Semla;

use Semla\Utils\Block_Util;

/**
 * Server side rendering of SEMLA Calendar block
 */
class Block_Calendar {
	private static $calEnhanced;

	/**
	 * Enqueue scripts, for enhanced calendars add CSS to the <head>
	 */
	public static function do_header( $atts )  {
		if (isset($atts['cid'])) {
			wp_enqueue_script( 'google-api', 'https://apis.google.com/js/api.js',
				[], null, true );
			wp_enqueue_script( 'semla-cal',
				plugins_url('js/cal' . SEMLA_MIN . '.js', __DIR__),
				['google-api'], '1.2', true );
			$enhanced = $atts['enhanced'] ?? false;
			if ($enhanced) {
				$tagColor = $atts['tagsList'] ?? [];
				self::$calEnhanced = 'true';
				if ($tagColor) {
					$html = '<style>';
					self::$calEnhanced .= ';window.semla.calTags={';
					foreach ($tagColor as $key => $val) {
						$key++;
						$tag = trim($val['tag']);
						$color = $val['color'];
						$html .= "mark.semla__tag$key{background-color:$color}";
						if ($key > 1) {
							self::$calEnhanced .= ',';
						}
						self::$calEnhanced .= "'$tag':$key";
					}
					self::$calEnhanced .= '}';
					$html .= "</style>\n";
					add_action('wp_head', function() use ($html) {
						echo $html;
					});
				}
			} else {
				self::$calEnhanced = 'false';
			}
			Block_Util::preconnect_hints(['apis.google.com', 'content.googleapis.com']);
		}
	}

	public static function render_callback( $atts )  {
		if (isset($atts['cid'])) {
			$cid = $atts['cid'];
			$html = '<noscript><p>You need Javascript enabled to be able to see the calendar.</p></noscript>' . "\n";
			$html .= '<div id="semla_cal"><div class="spinner-border" style="margin-left:10em"><span class="screen-reader-text">Loading</span></div></div>' . "\n"
				. '<script>window.semla=window.semla||{};window.semla.gapi="'
				. get_option('semla_gapi_key') . '";window.semla.cid="' . $cid . '";window.semla.calEnhanced='
				. self::$calEnhanced . ";</script>\n";
			$html .= '<p>You can <a rel="nofollow" href="https://calendar.google.com/calendar?cid='
				. str_replace('=','',base64_encode($cid)) . '">add to your Google Calendar</a>, or if you'
				. ' have other calendar software then you can subscribe to:</p>' . "\n";
			$html.=  '<p class="alignwide"><em>https://calendar.google.com/calendar/ical/'
			. urlencode($cid) . '/public/basic.ics</em></p>' . "\n";
			return $html;
		}
	}
}
