<?php
namespace Semla\Admin;

use Semla\Data_Access\Competition_Group_Gateway;

if ( ! class_exists ( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Competition_Group_List_Table extends \WP_List_Table {
	private $nonce;

	 public function __construct() {
		parent::__construct([
			'singular' => 'league',
			'plural'   => 'leagues',
			'ajax'     => false
		 ] );
		 $this->nonce = '&_wpnonce=' . wp_create_nonce('semla_cg');
	}

	 public function get_table_classes() {
		return [ 'widefat', 'fixed', 'striped', $this->_args['plural'] ];
	}

	 public function no_items() {
		echo  'No leagues found';
	}

	 public function column_default( $item, $column_name ) {
		return $item->$column_name;
	}

	 public function get_columns() {
		return [
			'name'	=> 'League Name',
			'suffix'  => 'Suffix',
		];
	}

	 public function column_name( $item ) {
		return '<a href="?page=semla_cg&action=edit&id=' . $item->id
			. '"><strong>' . $item->name .  '</strong></a>';
	}

	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}
		$actions = [
			'edit' => '<a href="?page=semla_cg&action=edit&id=' . $item->id
				. '" data-id="' . $item->id . '" title="Edit">Edit</a>',
			'delete' => '<a href="?page=semla_cg&action=delete&id=' . $item->id . $this->nonce
			. '" class="submitdelete" data-id="' . $item->id . '" title="Delete">Delete</a>',
		];
		return $this->row_actions( $actions ) ;
	}

	 public function get_sortable_columns() {
		return ['name' => [ 'name', true ]];
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

		$this->items = Competition_Group_Gateway::get_all( $args );

		$this->set_pagination_args( [
			'total_items' => Competition_Group_Gateway::get_count(),
			'per_page'	=> $per_page
		] );
	}
}
