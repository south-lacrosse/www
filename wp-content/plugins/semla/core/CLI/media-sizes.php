<?php
/**
 * Reload file sizes for image attachments, and all the sub sizes (e.g.
 * thumbnail). Sizes may not exist for images loaded prior to WP 6.0.
 */
global $wpdb;

$updated_meta = 0;
$updated_sizes = 0;
$updated_sub_sizes = 0;

$attachment_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->prefix}posts
	 WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);

foreach ($attachment_ids as $attachment_id) {
	$metadata_changed = false;
	$file = get_attached_file($attachment_id, true);
	$dir = pathinfo($file, PATHINFO_DIRNAME) . '/';
	$metadata = wp_get_attachment_metadata($attachment_id);

	$filesize = wp_filesize($file);
	if (!$filesize) {
		WP_CLI::error("Attachment file $file does not exist.", false);
	} elseif (!isset($metadata['filesize']) || $metadata['filesize'] !== $filesize) {
		WP_CLI::log("filesize updated: id=$attachment_id {$metadata['file']}, from "
			. ($metadata['filesize'] ?? 'unset') . " to $filesize");
		$metadata['filesize'] = $filesize;
		$updated_sizes++;
		$metadata_changed = true;
	}

	if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
		foreach ($metadata['sizes'] as $size_name => $size_meta) {
			if (isset($size_meta['file'])) {
				$file = $dir . $size_meta['file'];
				$filesize = wp_filesize($file);
				if (!$filesize) {
					WP_CLI::error("  Attachment sub-size file $file does not exist.", false);
				} elseif (!isset($size_meta['filesize']) || $size_meta['filesize'] !== $filesize) {
					WP_CLI::log("  sub-sizes filesize updated: {$size_meta['file']}, from "
						. ($size_meta['filesize'] ?? 'unset') . " to $filesize");
					$metadata['sizes'][$size_name]['filesize'] = $filesize;
					$updated_sub_sizes++;
					$metadata_changed = true;
				}
			}
		}
	}

	if ($metadata_changed) {
		$updated_meta++;
		wp_update_attachment_metadata( $attachment_id,  $metadata );
	}
}
if ($updated_meta) {
	WP_CLI::log("Updated: total meta=$updated_meta, sizes=$updated_sizes, sub_sizes=$updated_sub_sizes");
} else {
	WP_CLI::log('Completed - nothing updated');
}
