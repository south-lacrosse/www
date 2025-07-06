<?php
/**
 * Handle galleries with "Lightbox" style. Currently this is done by using the
 * GLightbox script, which is enqueued here. We also need to add data-srcset and
 * data-sizes so that we can display images at their optimum size in the
 * lightbox.
 *
 * Note: this functionality should be removed once the native WordPress lightbox
 * has navigation added, as in 6.8.1 you have to close one image and open the
 * next
 */
$ver = '3.3.1a'; // glightbox version, add a letter if you change glightbox-gallery.js
$base_url = plugins_url('/', __DIR__);
wp_enqueue_style( 'glightbox', $base_url .'css/glightbox.min.css',
	[], $ver);
// for production our script is compressed and bundled with GLightbox so we only
// download 1 script instead of 2.
if (SEMLA_MIN) {
	wp_enqueue_script( 'glightbox',	$base_url . 'js/glightbox.bundle.min.js', [], $ver, true );
} else {
	wp_enqueue_script( 'glightbox',	$base_url . 'js/glightbox.min.js', [], $ver, true );
	wp_enqueue_script( 'glightbox-gallery',	$base_url . 'js/glightbox-gallery.js',
		['glightbox'], $ver, true );
}

/**
 * For all gallery blocks with Lightbox style mark all contained images so we
 * can add sizes and srcset in the next filter.
 */
add_filter('render_block_data', function($parsed_block, $source_block, $parent_block) {
	if ($parsed_block['blockName'] !== 'core/gallery'
	|| !isset($parsed_block['innerBlocks'])
	|| !isset($parsed_block['attrs']['className'])
	|| !str_contains(($parsed_block['attrs']['className']), 'is-style-lightbox')) {
		return $parsed_block;
	}

	foreach ($parsed_block['innerBlocks'] as &$image_block) {
		if ($image_block['blockName'] !== 'core/image'
		|| !isset($image_block['attrs']['linkDestination'])
		|| $image_block['attrs']['linkDestination'] !== 'media' ) {
			continue;
		}
		$image_block['attrs']['semla-lightbox'] = true;
	}
	return $parsed_block;
}, 10, 3);

/**
 * If the above filter has marked this image as being in a gallery which should
 * have a lightbox then add data-size and data-srcset to the a tag, as GLightbox
 * uses these to set the lightbox image "size" and "srcset" attributes.
 */
add_filter('render_block_core/image', function($content, $block) {
	if (!isset($block['attrs']['semla-lightbox'])) return $content;

	$attachment_id = $block['attrs']['id'];
	$image = wp_get_attachment_image_src($attachment_id, 'full');
	if (!$image) return $content;

	list( $src, $width, $height ) = $image;
	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( is_array( $image_meta ) ) {
		$size_array = [ $width, $height ];
		$srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attachment_id );
		$sizes      = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
		if ( $srcset && $sizes ) {
			$processor = new WP_HTML_Tag_Processor( $content );
			$processor->next_tag( 'a' );
			$processor->set_attribute( 'data-srcset', $srcset );
			$processor->set_attribute( 'data-sizes', $sizes );
			return $processor->get_updated_html();
		}
	}
	return $content;
}, 10, 2);
