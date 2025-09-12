<?php
namespace Semla\CLI;
/**
 * Media related tasks.
 *
 * ## EXAMPLE
 *
 *     # Refresh media file sizes meta data
 *     $ wp semla-media sizes
 *     Updated: total meta=1, sizes=1, sub_sizes=3
 */
use \WP_CLI;
class Media_Command {
	/**
	 * Refresh image file sizes meta data.
	 *
	 * If you have optimized images outside of WordPress without changing their
	 * dimensions, then this command will update the size metadata for the new
	 * file sizes, including any image sizes like thumbnail. Size metadata may
	 * not exist for images loaded prior to WP 6.0.
	 */
	public function sizes() {
		global $wpdb;

		$updated_meta = 0;
		$updated_sizes = 0;
		$updated_sub_sizes = 0;

		$attachment_ids = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);
		Util::db_check();

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
	}

	/**
	 * Find unused images.
	 *
	 * [--check-revisions]
	 * : Check revisions for images. Defaults to true; pass
	 * `--no-check-revisions` to disable.
	 *
	 * Note if the image is on a revision and the image is deleted, then if the
	 * post is reverted to that revision then the image will be missing.
	 *
	 * [--include-attached]
	 * : List images even if they are attached to a parent post. Defaults to
	 * true; pass `--no-include-attached` to disable, which you should do if the
	 * theme displays attachments without them being in the content.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * @subcommand unused-images
	 */
	public function unused_images($args, $assoc_args) {
		global $wpdb;

		$assoc_args = array_merge( [
			'fields' => 'ID,post_parent,post_title,guid',
			'format' => 'table',
		], $assoc_args );

		$post_types = "'clubs','post','page','wp_block'";
		if (WP_CLI\Utils\get_flag_value( $assoc_args, 'check-revisions', true )) {
			$post_types .= ",'revision'";
		}
		$parent_sql = WP_CLI\Utils\get_flag_value( $assoc_args, 'include-attached', true )
			? '' : 'AND i.post_parent = 0';

		// extract all image ids from designated post types
		$image_ids = [];
		$rows = $wpdb->get_results("SELECT post_content FROM $wpdb->posts
				WHERE post_type IN ($post_types)
				AND post_content LIKE '%class=\"wp-image-%';");
		if ($rows) {
			foreach ($rows as $row) {
				if (preg_match_all('/class="wp-image-(\d+)/', $row->post_content, $matches)) {
					foreach ($matches[1] as $image_id) {
						$image_ids[$image_id] = 1;
					}
				}
			}

			// We generate a NOT IN clause to exclude the images we found, with the
			// alternative being to to select all rows and test against the image
			// ids later.

			// On the downside this might create a very large list to send to the
			// DB, but on the upside the DB doesn't have to transfer data for all
			// those images we aren't interested in.
			$image_ids = array_keys($image_ids);
			sort($image_ids);
			$not_in = 'AND i.ID NOT IN (' . implode(',', $image_ids) . ')';
		} else {
			$not_in = '';
		}

		$rows = $wpdb->get_results(
			"SELECT i.ID, i.post_parent, i.post_title, i.guid
			FROM $wpdb->posts i
			WHERE i.post_type = 'attachment' $parent_sql
			$not_in
			AND NOT EXISTS (SELECT * FROM $wpdb->postmeta pm
				WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = i.ID);");
		if ( 'ids' === $assoc_args['format'] ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
			return;
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, explode( ',', $assoc_args['fields'] ) );
	}

	/**
	 * Validate attachments and their metadata against the filesystem.
	 *
	 * Lists media where there is an attachment or image size metadata but no
	 * corresponding file, and files in the filesystem without an attachment or
	 * any image size metadata (i.e thumbnail or 150x150).
	 *
	 * [--delete]
	 * : Delete media files with no attachment, and no image size metadata.
	 */
	public function attachments($args, $assoc_args) {
		global $wpdb;
		$attachments = $no_metadata = [];
		$delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delete', false );

		// first load array add media files, and meta data
		$rows = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value AS attached_file,
				pm2.meta_value AS attachment_meta
				FROM wp_postmeta pm, wp_postmeta pm2
				WHERE pm2.post_id = pm.post_id
				AND pm.meta_key ='_wp_attached_file'
				AND pm2.meta_key = '_wp_attachment_metadata'");
		Util::db_check();
		foreach ($rows as $row) {
			$attachments[$row->attached_file] = $row->post_id;

			if (!$row->attachment_meta) continue;
			$metadata = @unserialize( trim( $row->attachment_meta ) );
			$dir = pathinfo($row->attached_file, PATHINFO_DIRNAME) . '/';

			if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
				foreach ($metadata['sizes'] as $size_name => $size_meta) {
					if (isset($size_meta['file'])) {
						$file = $dir . $size_meta['file'];
						$attachments[$file] = $row->post_id;
					}
				}
			}
		}
		unset($rows);

		$media_dir = wp_get_upload_dir()['basedir'];
		// now check the filesystem against those images
		$rdi = new \RecursiveDirectoryIterator($media_dir,
			\FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS);
		$rcfi = new \RecursiveCallbackFilterIterator($rdi, function ($current) {
			return $current->getFileName()[0] !== '.';
		});
		$iter = new \RecursiveIteratorIterator($rcfi);
		foreach ($iter as $file_info) {
			$path = $iter->getSubPathname();
			if (isset($attachments[$path])) {
				$attachments[$path] = true;
			} else {
				$no_metadata[$path] = 1;
			}
		}

		$not_in_filesystem = [];
		foreach ($attachments as $attachment => $id) {
			if ($id !== true) {
				$not_in_filesystem[] = "$id $attachment";
			}
		}
		if (count($not_in_filesystem) > 0) {
			echo "Attachment without file in filesystem\n";
			echo "-------------------------------------\n";
			echo implode("\n", $not_in_filesystem);
			echo "\n";
		}

		if (count($no_metadata) > 0) {
			if (count($not_in_filesystem) > 0) {
				echo "\n";
			}
			echo "Files with no attachment";
			if ($delete) echo ' (files deleted)';
			echo "\n------------------------\n";
			foreach ($no_metadata as $file => $value) {
				if ($delete) {
					@unlink("$media_dir/$file");
				}
				echo "$file\n";
			}
		}
	}



	/**
	 * Featured image information for posts
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 */
	public function featured($args, $assoc_args) {
		global $wpdb;

		$assoc_args = array_merge( [
			'fields' => 'ID,post_name,file,width,height,sizes',
			'format' => 'table',
		], $assoc_args );

		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_name, pm2.meta_value
			FROM wp_postmeta pm, wp_postmeta pm2, wp_posts p
			WHERE pm.meta_key = '_thumbnail_id'
			AND p.ID = pm.post_id
			AND pm2.post_id = pm.meta_value
			AND pm2.meta_key = '_wp_attachment_metadata';");
		if ( 'ids' === $assoc_args['format'] ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
			return;
		}
		foreach ($rows as $row) {
			$metadata = @unserialize( trim( $row->meta_value ) );
			unset($row->meta_value);
			$row->file = $metadata['file'] ?? '?';
			$row->height = $metadata['height'] ?? '?';
			$row->width = $metadata['width'] ?? '?';
			$sizes = [];
			if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
				foreach ($metadata['sizes'] as $size_name => $size_meta) {
					$sizes[] = "$size_name: {$size_meta['width']}x{$size_meta['height']}";
				}
			}
			$row->sizes = implode(', ', $sizes);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, explode( ',', $assoc_args['fields'] ) );
	}
}
