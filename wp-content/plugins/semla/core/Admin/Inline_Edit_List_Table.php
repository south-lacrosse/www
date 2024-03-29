<?php
namespace Semla\Admin;
/**
 * Base class for list tables that can edit inline
 */

if ( ! class_exists ( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class Inline_Edit_List_Table extends \WP_List_Table {
	// All subclasses should add this to the CSS so that edited rows fade back in
	const ROW_TRANSITION_CSS = '#the-list>tr{transition:opacity 1s linear}';

	/**
	 * Render complete page for inline edit list table.
	 *
	 * If the rest endpoint starts with 'wp/' then the javascript will update
	 * the post/custom post metadata. All input fields must have 'data-meta' set
	 * to the meta data key.
	 */
	public function render_inline_edit_page($title, $rest_endpoint) {
		$this->prepare_items();
		?>
<div class="wrap">
	<h1><?= $title ?></h1>
<?php
		$this->display();
		$this->inline_edit();
	?>
</div>
<?php
		wp_enqueue_script( 'semla-inline-edit',
			plugins_url('js/inline-edit' . SEMLA_MIN . '.js', dirname(__DIR__)),
			['wp-a11y'], '1.0', true );
		$vars = [
			'url' => rest_url($rest_endpoint),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'wpMeta' => str_starts_with($rest_endpoint, 'wp/')
		];
		wp_add_inline_script('semla-inline-edit', 'const semlaEdit=' . json_encode( $vars ), 'before' );
	}

	protected function get_table_classes() {
		return [ 'widefat', 'fixed', 'striped', $this->_args['plural'] ];
	}

	protected function column_default( $item, $column_name ) {
		return $item->$column_name;
	}

	/**
	 * Outputs the hidden row displayed when inline editing
	 */
	protected function inline_edit() {
		?>
		<table style="display:none"><tbody id="inlineedit">

		<tr id="inline-edit" class="inline-edit-row">
			<td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
			<div class="inline-edit-wrapper">

			<fieldset>
				<legend class="inline-edit-legend">Edit</legend>
				<p class="inline-edit-title" style="font-size:16px;font-weight:600"></p>
				<?php $this->inline_edit_fields(); ?>
			</fieldset>
			<div class="inline-edit-save submit">
				<button type="button" class="save button button-primary">Update</button>
				<button type="button" class="cancel button">Cancel</button>

				<span class="spinner"></span>

				<?php
				wp_admin_notice('<p class="error"></p>',
					[
						'type'               => 'error',
						'additional_classes' => [ 'notice-alt', 'inline', 'hidden' ],
						'paragraph_wrap'     => false,
					]);
				?>
			</div>
			</div>
			</td>
		</tr>
		</tbody></table>
		<?php
	}

	/**
	 * Fields for the inline edit row.
	 *
	 * All input tags must have their name attribute set as this is used to
	 * send/receive name/value pairs to the server, and the data-colname must
	 * match the value on the corresponding table cell (i.e the column title).
	 *
	 * See subclasses for examples.
	 */
	protected function inline_edit_fields() {
		die( 'function Inline_Edit_List_Table::protected function inline_edit_fields() must be overridden in a subclass.' );
	}
}