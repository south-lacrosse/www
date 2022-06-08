<?php
$message = '';
$deleted = isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0;
if ($deleted) {
	$message = "$deleted leagues deleted";
} else {
	$update = isset( $_REQUEST['update'] ) ? sanitize_text_field( $_REQUEST['update'] ) : '';
	if ($update) {
		if ($update === 'new') {
			$message = "League added";
		} else {
			$message = "League updated";
		}
	}
}
?>
<div class="wrap">
<h2>Leagues <a href="<?= self::PAGE_URL ?>&action=new" class="add-new-h2">Add New</a></h2>
<?php
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
<?php self::$list_table->display(); ?>
</form>
</div>
