<?php
if (self::$action === 'edit') {
	$title = 'Update';
} else {
	$title = 'Add New';
}
extract(self::$fields);
?>
<div class="wrap">
	<h1><?= $title ?> League</h1>
<?php
if (self::$errors) {
	echo '<div class="notice notice-error is-dismissible"><p>' . implode('<br>', self::$errors) . '</p></div>';
}
?>
	<form method="post">
		<table class="form-table">
			<tbody>
				<tr class="row-name">
					<th scope="row">
						<label for="name">Name <span class="description">(required)</span></label>
					</th>
					<td>
						<input type="text" name="name" id="name" class="regular-text" placeholder="Name" value="<?= esc_attr($name) ?>" required="required" />
				   </td>
				</tr>
				<tr class="row-suffix">
					<th scope="row">
						<label for="suffix">Suffix <span class="description">(required)</span></label>
					</th>
					<td>
						<input type="text" name="suffix" id="suffix" class="regular-text" placeholder="Suffix" value="<?= esc_attr($suffix) ?>" required="required" />
						<p>Suffix for tables, fixtures-grid pages. For example the Local league
						has a suffix of "-local" so that tables are at /tables-local and fixtures grid is at /fixtures-grid-local.
						For the SEMLA league this should be blank, so that gets pages with no suffix.</p> 
						<p>Make sure you create the appropriate pages with the correct
						SEMLA Data block.</p> 
					</td>
				</tr>
			 </tbody>
		</table>
		<?php wp_nonce_field( 'semla_cg' ); ?>
		<?php submit_button( $title . ' League', 'primary', 'submit' ); ?>
	</form>
</div>
