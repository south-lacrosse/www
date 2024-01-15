<?php
namespace Semla\Admin;

use Semla\Data_Access\Team_Abbrev_Gateway;

if ( ! class_exists ( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table class
 */
class Team_Abbrev_List_Table extends \WP_List_Table {
	private $nonce;

	 public function __construct() {
		parent::__construct([
			'singular' => 'team_abbrev',
			'plural'   => 'team_abbrevs',
			'ajax'     => false
		 ] );
		 $this->nonce = '&_wpnonce=' . wp_create_nonce('semla_team_abbrev');
	}

	 public function get_table_classes() {
		return [ 'widefat', 'fixed', 'striped', $this->_args['plural'] ];
	}

	 public function no_items() {
		echo  'No abbreviations found';
	}

	 public function column_default( $item, $column_name ) {
		return $item->$column_name;
	}

	 public function get_columns() {
		return [
			'cb'      => '<input type="checkbox" />',
			'team'    => 'Team',
			'abbrev'  => 'Abbreviation',
		];
	}

	 public function column_team( $item ) {
		$team = urlencode($item->team);
		return '<a href="?page=semla_team_abbrev&action=edit&team=' . $team
			. '"><strong>' . $item->team .  '</strong></a>';
	}

	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}
		$team = urlencode($item->team);
		$actions = [
			'edit' => '<a href="?page=semla_team_abbrev&action=edit&team=' . $team
				. '" data-team="' . $team . '" title="Edit">Edit</a>',
			'delete' => '<a href="?page=semla_team_abbrev&action=delete&team=' . $team . $this->nonce
				. '" class="submitdelete" data-team="' . $team . '" title="Delete">Delete</a>',
		];
		return $this->row_actions( $actions ) ;
	}

	public function get_sortable_columns() {
		return ['team' => [ 'team', true ]];
	}

	 public function get_bulk_actions() {
		return ['bulk_delete'  => 'Delete'];
	}

	 public function column_cb( $item ) {
		return '<input type="checkbox" name="teams[]" value="' . urlencode($item->team). '" />';
	}

	 public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$per_page = 25;
		$current_page = $this->get_pagenum();
		$args = [
			'offset' => ( $current_page -1 ) * $per_page,
			'number' => $per_page,
		];
		if ( isset( $_REQUEST['orderby'] ) && isset( $_REQUEST['order'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
			$args['order']   = $_REQUEST['order'] ;
		}
		if ( isset($_REQUEST['s']) && strlen( $_REQUEST['s'] )) {
			$args['search'] = $_REQUEST['s'];
		}
		$this->items = Team_Abbrev_Gateway::get_all( $args );

		$this->set_pagination_args( [
			'total_items' => Team_Abbrev_Gateway::get_count( $args ),
			'per_page'	=> $per_page
		] );
	}
}
