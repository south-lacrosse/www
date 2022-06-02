<?php
$message = '';
$deleted = isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0;
if ($deleted) {
    $message = "$deleted abbreviations deleted";
} else {
    $update = isset( $_REQUEST['update'] ) ? sanitize_text_field( $_REQUEST['update'] ) : '';
    if ($update) {
        if ($update === 'new') {
            $message = "Abbreviation added";
        } else {
            $message = "Abbreviation updated";
        }
    }
}
?>
<div class="wrap">
<h1 class="wp-heading-inline">Team Abbreviations</h1>
<a href="<?= self::PAGE_URL ?>&action=new" class="page-title-action">Add New</a>
<?php
if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	echo ' <span class="subtitle">Search results for &#8220;' . $_REQUEST['s'] . '&#8221;</span>';
}

if ( $message ) {
	echo '<div class="updated notice is-dismissible"><p>' . $message . '</p></div>';
} else {
    $error = isset( $_REQUEST['error'] ) ? sanitize_text_field( $_REQUEST['error'] ) : '';
    if ($error) {
        echo '<div class="notice notice-error is-dismissible"><p>' . $error . '</p></div>';
    }
}
?>
<form method="post">
<?php
    self::$list_table->search_box( 'search', 'search_id' );
    self::$list_table->display();
?>
</form>
</div>        
