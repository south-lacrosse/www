<?php
namespace Semla\Admin;

use Semla\Data_Access\Winner_Gateway;

if ( ! class_exists ( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Winner_List_Table extends \WP_List_Table {

    function __construct() {
        parent::__construct([
            'singular' => 'team abbreviation',
            'plural'   => 'team abbreviations',
            'ajax'     => false
         ] );
    }

    function get_table_classes() {
        return [ 'widefat', 'fixed', 'striped', $this->_args['plural'] ];
    }

    function no_items() {
        echo  'No abbreviations found';
    }

    function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'team':
                return $item->team;
            case 'abbrev':
                return $item->abbrev;
            default:
                return isset( $item->$column_name ) ? $item->$column_name : '';
        }
    }

    function get_columns() {
        $columns = [
            'cb'           => '<input type="checkbox" />',
            'team'      => 'Team', '',
            'abbrev'      => 'Abbreviation',
        ];
        return $columns;
    }

    function column_team( $item ) {
        $actions = [];
        $actions['edit']   = '<a href="?page=semla_team_abbrev&action=edit&id=' . $item->id
            . '" data-id="' . $item->id . '" title="Edit">Edit</a>';
        $actions['delete']   = '<a href="?page=semla_team_abbrev&action=delete&id=' . $item->id
            . '" class="submitdelete" data-id="' . $item->id . '" title="Delete">Delete</a>';
        return '<a href="?page=semla_team_abbrev&action=view&id=' . $item->id
        . '"><strong>' . $item->team .  '</strong></a> ' . $this->row_actions( $actions ) ;
    }

    function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array( 'name', true ),
        );
        return $sortable_columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'trash'  => __( 'Move to Trash', '' ),
        );
        return $actions;
    }

    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="team abbreviation_id[]" value="%d" />', $item->id
        );
    }

    public function get_views_() {
        $status_links   = [];
        foreach ($this->counts as $key => $value) {
            $class = ( $key == $this->page_status ) ? 'current' : 'status-' . $key;
            $status_links[ $key ] = sprintf( 
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>', add_query_arg( ['status' => $key], '?page=sample-page'), $class, $value['label'], $value['count'] );
        }
        return $status_links;
    }

    function prepare_items() {
        $columns               = $this->get_columns();
        $hidden                = array( );
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $per_page              = 25;
        $current_page          = $this->get_pagenum();
        $offset                = ( $current_page -1 ) * $per_page;
        $this->page_status     = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '2';

        $args = array(
            'offset' => $offset,
            'number' => $per_page,
        );

        if ( isset( $_REQUEST['orderby'] ) && isset( $_REQUEST['order'] ) ) {
            $args['orderby'] = $_REQUEST['orderby'];
            $args['order']   = $_REQUEST['order'] ;
        }

        $this->items  = Winner_Gateway::get_all_team_abbrev( $args );

        $this->set_pagination_args( array(
            'total_items' => Winner_Gateway::get_team_abbrev_count(),
            'per_page'    => $per_page
        ) );
    }
}