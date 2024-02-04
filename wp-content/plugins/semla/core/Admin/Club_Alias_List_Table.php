<?php
namespace Semla\Admin;

use Semla\Data_Access\Club_Gateway;
/**
 * List table for LacrossePlay club names
 */
class Club_Alias_List_Table extends Inline_Edit_List_Table {
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
				. '.inline-edit-row fieldset label span.title{width:9em}'
				. '.inline-edit-row fieldset label span.input-text-wrap{margin-left:9em}'
				. '@media screen and (max-width: 782px){'
					. '.inline-edit-row fieldset label span.input-text-wrap{margin-left: 0}}'
				. '</style>';
		});
	}

	/**
	 * Menu page callback to render output
	 */
	public static function render_page() {
		$list_table = new Club_Alias_List_Table();
		$list_table->render_inline_edit_page('Club Alias', 'wp/v2/clubs/');
	}

	public function __construct() {
		parent::__construct([
			'singular' => 'club',
			'plural'   => 'clubs'
		] );
	}

	protected function get_table_classes() {
		return [ 'widefat', 'striped', $this->_args['plural'] ];
	}

	protected function display_tablenav($which) {
		return;
	}

	protected function column_title( $item ) {
		return '<button type="button" class="button-link row-title editinline" aria-label="Edit &#8220;'
			. esc_attr( $item->post_title ) . '&#8221; inline" aria-expanded="false">'
			. $item->post_title . "</button>";
	}

	public function get_columns() {
		return [
			'title'	=> 'Club', // column 'title' cannot be hidden
			'lp_club'  => 'LacrossePlay Club',
		];
	}

	public function single_row( $item ) {
		echo '<tr id="club-' . $item->ID . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(), 'title' ];
		$this->items = Club_Gateway::get_current_clubs_alias();
	}

	/**
	 * Outputs the editable fields for inline editing
	 */
	protected function inline_edit_fields() {
		?>
				<div class="inline-edit-col">
				<label>
					<span class="title">LacrossePlay Club</span>
					<span class="input-text-wrap"><input type="text" name="lacrosseplay_club" data-colname="LacrossePlay Club" class="ptitle"/></span>
				</label>
				</div>
		<?php
	}
}