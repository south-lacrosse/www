<?php
/**
 * Add modified column to posts/pages/clubs list
 */
if ($screen->post_type === 'post' || $screen->post_type == 'page') {
	$hook = $screen->post_type . 's';
} else {
	$hook = $screen->post_type . '_posts';
}

// Note: this file is only loaded if we are on the post list (edit.php) page
add_action('admin_enqueue_scripts', function() {
	echo '<style>.fixed .column-modified{width:14%}</style>';
});
add_filter( "manage_{$hook}_columns", function($columns) {
	$columns['modified'] = 'Modified';
	return $columns;
});
add_action( "manage_{$hook}_custom_column", function ($column_name, $post_id) {
	if ( $column_name === 'modified' ) {
		$author = get_the_modified_author();
		if ( $author ) {
			the_modified_date('Y/m/d g:i a');
			echo "<br>by $author";
		}
		echo '</p>';
	}
}, 10, 2);

add_filter( "manage_{$screen->id}_sortable_columns", function ($columns) {
	$columns['modified'] = 'modified';
	return $columns;
});
