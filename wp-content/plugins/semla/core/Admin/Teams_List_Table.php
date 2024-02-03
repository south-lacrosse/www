<?php
namespace Semla\Admin;

use Semla\Data_Access\Club_Team_Gateway;
/**
 * List table for current teams
 */
class Teams_List_Table extends Inline_Edit_List_Table {
	private static $list_table;
	/**
	 * Called when the admin page containing this list table is loaded
	 */
	public static function load() {
		if (!current_user_can('manage_semla')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		self::$list_table = new Teams_List_Table();
		add_action( 'admin_head', function() {
			echo "\n<style>"
				. parent::ROW_TRANSITION_CSS
				. '.fixed .column-abbrev{width:25%}.fixed .column-minimal{width:15%}'
				. '</style>';
		});
		get_current_screen()->add_help_tab( [
			'id'      => 'overview',
			'title'   => 'Overview',
			'content' => '<p>You may need to clear the cache before changes take effect, and
			if your changes effect history pages you should regenerate them.</p>
			<p><b>Abbreviation:</b> used in various places instead of the full team name,
			including places like the flags grid where a long name would overflow the
			available space. Try to keep to 10 characters, but up to 15 or so should be fine.</p>
			<p><b>Minimal:</b> used on the league table grid, so will ideally be 3 characters.</p>',
		] );
	}

	/**
	 * Menu page callback to render output
	 */
	public static function render_page() {
		self::$list_table->render_inline_edit_page('Teams', 'semla-admin/v1/teams/');
	}

	public function __construct() {
		parent::__construct([
			'singular' => 'team',
			'plural'   => 'teams'
		] );
	}

	protected function display_tablenav($which) {
		return;
	}

	protected function column_name( $item ) {
		return '<button type="button" class="button-link row-title editinline" aria-label="Edit &#8220;'
			. esc_attr( $item->team ) . '&#8221; inline" aria-expanded="false">'
			. $item->team . "</button>";
	}

	public function get_columns() {
		return [
			'name'	=> 'Team', // column 'name' cannot be hidden
			'abbrev'  => 'Abbreviation',
			'minimal'  => 'Minimal',
		];
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(), 'name' ];
		$this->items = Club_Team_Gateway::get_current_teams_meta();
	}

	/**
	 * Outputs the editable fields for inline editing
	 */
	protected function inline_edit_fields() {
		?>
				<div class="inline-edit-col" style="max-width:60ch">
				<label>
					<span class="title">Abbreviation</span>
					<span class="input-text-wrap"><input type="text" name="abbrev" data-colname="Abbreviation" class="ptitle" maxlength="30" /></span>
				</label>
				<label>
					<span class="title">Minimal</span>
					<span class="input-text-wrap"><input type="text" name="minimal" data-colname="Minimal" class="ptitle" maxlength="10" /></span>
				</label>
				</div>
		<?php
	}
}