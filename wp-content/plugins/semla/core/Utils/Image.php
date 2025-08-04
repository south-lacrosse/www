<?php
/**
 * Image handling, mainly filters which are added in various classes.
 *
 * WordPress doesn't handle SVGs by default as they consider them a security
 * hole, which they are if any user can upload them. However, we don't have many
 * users and we trust them, so it's fine for us.
 */
namespace Semla\Utils;

class Image {
	private static $thumbnail_width;
	private static $thumbnail_height;

	/*
	 * Hook to wp_calculate_image_srcset_meta to stop WP adding srcset on
	 * thumbnail (or smaller) images, as they should never be a different size
	*/
	public static function no_thumbnail_srcset($image_meta, $size_array, $image_src, $attachment_id ) {
		if (isset($image_meta['sizes']['thumbnail'])) {
			if ($image_meta['sizes']['thumbnail']['width'] >= $size_array[0]
			&& $image_meta['sizes']['thumbnail']['height'] >= $size_array[1]) {
				unset($image_meta['sizes']);
			}
			return $image_meta;
		}
		// an image may not have a thumbnail size if the original image is less
		// than or equal to the thumbnail width/height
 		if (!self::$thumbnail_width) {
			self::$thumbnail_height = (int) get_option( 'thumbnail_size_h' ) ?? 150;
			self::$thumbnail_width = (int) get_option( 'thumbnail_size_w' ) ?? 150;
		}
		// if we are looking for the thumbnail
		if (self::$thumbnail_width >= $size_array[0]
		&& self::$thumbnail_height >= $size_array[1]) {
			unset($image_meta['sizes']);
		}
		return $image_meta;
	}

	/**
	 * Hook to upload_mimes to allow SVGs. Might want to restrict depending on
	 * role/capability
	 */
	public static function allow_svg_mimes($mime_types) {
		$mime_types['svg'] = 'image/svg+xml';
		return $mime_types;
	}

	/**
	 * WP doesn't automatically store SVG width and height, which then means
	 * they don't display correctly. Hook to wp_generate_attachment_metadata
	 */
	public static function generate_svg_attachment_metadata($metadata, $attachment_id) {
		if ('image/svg+xml' === get_post_mime_type( $attachment_id )) {
			$file = get_attached_file($attachment_id);
			$metadata['file'] = _wp_relative_upload_path( $file );

			$xml = simplexml_load_file($file);
			if (!$xml) return $metadata;
			$attr = $xml->attributes();
			$viewbox = explode(' ', $attr->viewBox);
			$metadata['width'] = isset($attr->width) && preg_match('/\d+/', $attr->width, $value)
				? (int) $value[0] : (count($viewbox) == 4 ? (int) $viewbox[2] : null);
			$metadata['height'] = isset($attr->height) && preg_match('/\d+/', $attr->height, $value)
				? (int) $value[0] : (count($viewbox) == 4 ? (int) $viewbox[3] : null);
			unset($metadata['sizes']);
			return $metadata;
		}
		return $metadata;
	}

	/**
	 * Filter for wp_get_missing_image_subsizes to make sure we never generate
	 * other sized images for SVGs
	 */
	public static function no_svg_missing_image_subsizes($missing_sizes, $image_meta, $attachment_id) {
		if ('image/svg+xml' === get_post_mime_type( $attachment_id )) {
			return [];
		}
		return $missing_sizes;
	}

	/**
	 * Hook to wp_calculate_image_srcset_meta to stop srcset being generated,
	 * and make sure to remove once done
	 */
	public static function disable_image_srcset($image_meta) {
		unset($image_meta['sizes']);
		return $image_meta;
	}
}
