<?php
namespace Semla\CLI;
/**
 * Media related tasks.
 */
use \WP_CLI;
class Media_Command {
	/**
	 * Refresh attachment filesize meta data.
	 *
	 * If you have optimized images, or images generated for other attachments,
	 * outside of WordPress without changing their dimensions, then this command
	 * will update the filesize metadata for the new file sizes, including any
	 * image sizes like thumbnail. Filesize metadata may not exist for
	 * attachments loaded prior to WP 6.0.
	 *
	 * [<attachment-id>...]
	 * : One or more IDs of the attachments to refresh.
	 *
	 * [--dry-run]
	 * : Check images needing filesize updates without performing the operation.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message. Confirmation only shows when no IDs passed as arguments.
	 *
	 */
	public function filesizes($args, $assoc_args) {
		global $wpdb;

		if ( empty( $args ) ) {
			WP_CLI::confirm( 'Do you really want to refresh all attachment file sizes?', $assoc_args );
		}

		$dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' );
		if ($dry_run) {
			$will_be = ' will be';
		} else {
			$will_be = '';
		}

		if ($args) {
			$post_in = array_unique( array_map( 'absint', $args ) );
			sort( $post_in );
			$where = ' AND ID IN (' . implode( ',', $post_in ) . ')';
		} else {
			$where = '';
		}

		$attachment_ids = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type = 'attachment'
			$where
			ORDER BY ID"
		);
		Util::db_check();
		$count  = count($attachment_ids);

		if ( ! $count ) {
			WP_CLI::warning( 'No attachments found.' );
			return;
		}
		WP_CLI::log("Found $count attachment(s) to refresh filesizes");

		// Load and cache all metadata so we don't do individual selects
		update_postmeta_cache($attachment_ids);

		$number = $successes = 0;
		foreach ($attachment_ids as $attachment_id) {
			$number++;
			$progress = "$number/$count";
			$metadata_changed = false;
			$message = '';

			$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$file = get_attached_file($attachment_id, true);
			$dir = pathinfo($file, PATHINFO_DIRNAME) . '/';
			$metadata = wp_get_attachment_metadata($attachment_id);

			$filesize = wp_filesize($file);
			if (!$filesize) {
				WP_CLI::warning("Attachment file $file does not exist (ID $attachment_id).");
			} elseif (!isset($metadata['filesize']) || $metadata['filesize'] !== $filesize) {
				$message .= "\n  filesize $attached_file from "
					. ($metadata['filesize'] ?? 'unset') . " to $filesize";
				$metadata['filesize'] = $filesize;
				$metadata_changed = true;
			}

			if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
				foreach ($metadata['sizes'] as $size_name => $size_meta) {
					if (isset($size_meta['file'])) {
						$file = $dir . $size_meta['file'];
						$filesize = wp_filesize($file);
						if (!$filesize) {
							WP_CLI::warning("Attachment for size $size_name file $file does not exist (ID $attachment_id).");
						} elseif (!isset($size_meta['filesize']) || $size_meta['filesize'] !== $filesize) {
							$message .= "\n    $size_name filesize {$size_meta['file']} from "
								. ($size_meta['filesize'] ?? 'unset') . " to $filesize";
							$metadata['sizes'][$size_name]['filesize'] = $filesize;
							$metadata_changed = true;
						}
					}
				}
			}

			if ($metadata_changed) {
				$successes++;
				if (!$dry_run) {
					wp_update_attachment_metadata( $attachment_id,  $metadata );
				}
				WP_CLI::log("$progress Refreshed filesizes for $attached_file (ID $attachment_id).$message");
			}
		}
		WP_CLI::success( "$successes of $count attachments$will_be updated.");
	}

	/**
	 * List image meta data.
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
	public function images($args, $assoc_args) {
		global $wpdb;

		$assoc_args = array_merge( [
			'fields' => 'ID,post_title,post_name,file,filesize,post_mime_type,width,height,sizes,original_image',
			'format' => 'table',
		], $assoc_args );

		$rows = $wpdb->get_results(
			"SELECT i.ID, i.post_parent, i.post_title, i.post_name, post_mime_type,
				pmm.meta_value AS metadata
			FROM $wpdb->posts i
			LEFT JOIN $wpdb->postmeta AS pmm
			ON pmm.post_id = i.ID
			AND pmm.meta_key = '_wp_attachment_metadata'
			WHERE i.post_type = 'attachment'
			AND i.post_mime_type LIKE 'image/%'
			ORDER BY i.ID");
		Util::db_check();
		foreach ($rows as $row) {
			self::extract_metadata($row);
		}

		if ( 'ids' === $assoc_args['format'] ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
			return;
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, explode( ',', $assoc_args['fields'] ) );
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
			'fields' => 'ID,post_parent,post_title,post_name,file',
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
				AND post_content LIKE '%class=\"wp-image-%'");
		Util::db_check();
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
			"SELECT i.ID, i.post_parent, i.post_title, i.post_name, pm.meta_value AS file
			FROM $wpdb->posts i
			LEFT JOIN $wpdb->postmeta AS pm
			ON pm.post_id = i.ID
			AND pm.meta_key = '_wp_attached_file'
			WHERE i.post_type = 'attachment'
			AND i.post_mime_type LIKE 'image/%' $parent_sql
			$not_in
			AND NOT EXISTS (SELECT * FROM $wpdb->postmeta pmt
				WHERE pmt.meta_key = '_thumbnail_id' AND pmt.meta_value = i.ID)");
		Util::db_check();

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
	public function validate($args, $assoc_args) {
		global $wpdb;
		$attachments = $no_metadata = [];
		$delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delete', false );

		// first load array add media files, and meta data
		$rows = $wpdb->get_results(
			"SELECT p.ID, pm.meta_value AS attached_file, pm2.meta_value AS metadata
			FROM $wpdb->posts p
			LEFT JOIN $wpdb->postmeta pm
			ON pm.post_id = p.ID
			LEFT JOIN $wpdb->postmeta pm2
			ON pm2.post_id = p.ID
			WHERE p.post_type = 'attachment'
			AND pm.meta_key = '_wp_attached_file'
			AND pm2.meta_key = '_wp_attachment_metadata'");
		Util::db_check();
		foreach ($rows as $row) {
			if (!$row->attached_file || !$row->metadata) {
				WP_CLI::warning("Missing post meta for attachment id $row->ID");
				continue;
			}
			$metadata = unserialize( $row->metadata );
			$attachments[$row->attached_file] = $row->ID;
			$dir = dirname($row->attached_file) . '/';
			if ( ! empty($metadata['sizes']) && is_array($metadata['sizes'])) {
				foreach ($metadata['sizes'] as $size_name => $size_meta) {
					if ( ! empty($size_meta['file'])) {
						$file = $dir . $size_meta['file'];
						$attachments[$file] = $row->ID;
					}
				}
			}
			if ( ! empty( $metadata['original_image'] ) ) {
				$file = $dir . $metadata['original_image'];
				$attachments[$file] = $row->ID;
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
			WP_CLI::log('Attachment without file in filesystem');
			WP_CLI::log('-------------------------------------');
			foreach ($not_in_filesystem as $missing) {
				WP_CLI::log($missing);
			}
		}

		if (count($no_metadata) > 0) {
			if (count($not_in_filesystem) > 0) {
				WP_CLI::log('');
			}
			WP_CLI::log('Files with no attachment' . ($delete ? ' (files deleted)' : ''));
			WP_CLI::log('------------------------');;
			foreach ($no_metadata as $file => $value) {
				if ($delete) {
					@unlink("$media_dir/$file");
				}
				WP_CLI::log($file);
			}
		}

		if (count($not_in_filesystem) === 0 && count($no_metadata) === 0) {
			WP_CLI::success('All attachments are valid');
		}
	}

	/**
	 * Featured image information for posts.
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
			'fields' => 'ID,post_title,post_name,file,filesize,width,height,sizes',
			'format' => 'table',
		], $assoc_args );

		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_name, p.post_title, pm2.meta_value AS metadata
			FROM $wpdb->postmeta pm, $wpdb->postmeta pm2, $wpdb->posts p
			WHERE pm.meta_key = '_thumbnail_id'
			AND p.ID = pm.post_id
			AND pm2.post_id = pm.meta_value
			AND pm2.meta_key = '_wp_attachment_metadata'");
		if ( 'ids' === $assoc_args['format'] ) {
			echo implode( ' ', wp_list_pluck( $rows, 'ID' ) );
			return;
		}
		foreach ($rows as $row) {
			self::extract_metadata($row);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, explode( ',', $assoc_args['fields'] ) );
	}

	/**
	 * Extract image sizes and names from the meta data
	 */
	private function extract_metadata($row) {
		$metadata = @unserialize( trim( $row->metadata ) );
		unset($row->metadata);
		$row->file = $metadata['file'] ?? '?';
		$row->height = $metadata['height'] ?? '?';
		$row->width = $metadata['width'] ?? '?';
		$row->filesize = $metadata['filesize'] ?? '?';
		$sizes = [];
		if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
			foreach ($metadata['sizes'] as $size_name => $size_meta) {
				$sizes[] = "$size_name: {$size_meta['width']}x{$size_meta['height']}" .
					(isset($size_meta['filesize']) ? '(' . $size_meta['filesize'] . ')': '') ;
			}
		}
		$row->sizes = implode(', ', $sizes);
		$row->original_image = $metadata['original_image'] ?? '';
	}
}
