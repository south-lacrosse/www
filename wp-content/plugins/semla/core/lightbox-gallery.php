<?php
/**
 * Handle galleries with "Lightbox" style. Currently this is done by using the
 * GLightbox script, which is enqueued here. We also need to add data-sizes so
 * that we can display images at their optimum size in the lightbox.
 *
 * Note: this functionality should be removed once the native WordPress lightbox
 * has navigation added, as in 6.8.1 you have to close one image and open the
 * next
 */
$ver = '3.3.1';
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
 * For all gallery blocks with Lightbox style, set data-size on each image inner
 * block. The next filter will then add that to the a tag when the image is
 * rendered.
 */
add_filter('render_block_data', function($parsed_block) {
	if ($parsed_block['blockName'] !== 'core/gallery'
	|| !isset($parsed_block['innerBlocks'])
	|| !isset($parsed_block['attrs']['className'])
	|| !str_contains(($parsed_block['attrs']['className']), 'is-style-lightbox')) {
		return $parsed_block;
	}

	foreach ($parsed_block['innerBlocks'] as &$image_block) {
		if ($image_block['blockName'] !== 'core/image'
		|| isset($image_block['attrs']['data-sizes'])
		|| !isset($image_block['attrs']['linkDestination'])
		|| $image_block['attrs']['linkDestination'] !== 'media' ) {
			continue;
		}
		$image = wp_get_attachment_image_src($image_block['attrs']['id'],[1024,1024]);
		if ($image) {
			$w = $image[1];
			$image_block['attrs']['data-sizes'] = "(max-width: {$w}px) 100vw, {$w}px";
		}

	}
	return $parsed_block;
});


/**
 * If the above filter has added data-size to the image block, then add it to
 * the a tag.
 */
add_filter('render_block_core/image', function($content, $block) {
	if (!isset($block['attrs']['data-sizes'])) return $content;

	$processor = new WP_HTML_Tag_Processor( $content );
	$processor->next_tag( 'a' );
	// Add the data-sizes="" attribute to the a element for GLightbox
	$processor->set_attribute( 'data-sizes', $block['attrs']['data-sizes'] );
	return $processor->get_updated_html();
}, 10, 2);
