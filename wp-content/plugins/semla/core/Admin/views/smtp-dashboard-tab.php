<?php
use Semla\Utils\SMTP;

$args = SMTP::get_args();
if (!$args) : ?>
<p>SMTP_USER and/or SMTP_PASS are not defined in <code>wp-config.php</code> so cannot
display the SMTP configuration.</p>
<?php else : ?>
<h2>Send a Test Email</h2>
<form method="post">
	<table class="form-table" role="presentation"><tbody>
	<tr>
		<th scope="row"><label for="send_to">Send To</label></th>
		<td><input name="send_to" type="email" id="send_to" value="<?= esc_attr($send_to) ?>" required class="regular-text">
	</tr>
	</tbody></table>
	<p class="submit">
		<?php wp_nonce_field('semla_smtp') ?>
		<input type="submit" class="button-primary" name="semla_send_test_email" value="Send Test Email" />
	</p>
</form>
<h2>SMTP Configuration</h2>
<p>Note: this can only be changed by editing the <code>wp-config.php</code> file. See
<code>wp-config-semla.php</code>for all settings.</p>
<table class="widefat striped" role="presentation">
<tbody>
<?php foreach ($args as $key => $value) : ?>
<tr><td><?= $key ?></td><td><?= $value ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif;
