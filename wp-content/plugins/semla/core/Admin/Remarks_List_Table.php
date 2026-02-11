<?php
namespace Semla\Admin;

use Semla\Data_Access\Competition_Gateway;

/**
 * List table for current competition remarks
 */
class Remarks_List_Table extends Inline_Edit_List_Table {
	/**
	 * Called when the admin page containing this list table is loaded
	 */
	public static function load() {
		if (!current_user_can('manage_semla')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		add_action( 'admin_head', function() {
			echo "\n<style>"
				. parent::ROW_TRANSITION_CSS
				. '</style>';
		});
		get_current_screen()->add_help_tab( [
			'id'      => 'overview',
			'title'   => 'Overview',
			'content' => '<p>Remarks appear under the competition on the respective '
				. 'league table or cup competition in its SEMLA Data block. Examples '
				. 'are when a team withdraws from the league, or a league table is '
				. 'ordered in a non-standard way. Note that the remarks are rendered '
				. 'as a paragraph, but you can add line breaks if needed.</p>',
		] );
	}

	/**
	 * Menu page callback to render output
	 */
	public static function render_page() {
		// create here rather than in load() so the screen options don't show
		$list_table = new Remarks_List_Table();
		$list_table->render_inline_edit_page(
			'Competition Remarks', 'semla-admin/v1/competitions/');
	}

	public function __construct() {
		parent::__construct([
			'singular' => 'remark',
			'plural'   => 'remarks'
		] );
	}

	protected function get_table_classes() {
		return [ 'widefat', 'striped', $this->_args['plural'] ];
	}

	protected function display_tablenav($which) {
		return;
	}

	protected function column_name( $item ) {
		return '<button type="button" class="button-link row-title editinline" aria-label="Edit &#8220;'
			. esc_attr( $item->name ) . '&#8221; inline" aria-expanded="false">'
			. $item->name . "</button>";
	}

	public function get_columns() {
		return [
			'name'	=> 'Competition', // column 'name' cannot be hidden
			'remarks'  => 'Remarks',
		];
	}

	public function single_row( $item ) {
		echo '<tr id="comp-', $item->id, '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], // no hidden columns
			$this->get_sortable_columns(), 'name' ];
		$this->items = Competition_Gateway::get_current_remarks();
	}

	/**
	 * Outputs the editable fields for inline editing
	 */
	protected function inline_edit_fields() {
		?>
				<div class="inline-edit-col">
				<label>
					<span class="title">Remarks</span>
					<span class="input-text-wrap"><textarea style="width:100%" rows="5" name="remarks" data-colname="Remarks" class="ptitle"></textarea></span>
				</label>
				</div>
		<?php
	}
}