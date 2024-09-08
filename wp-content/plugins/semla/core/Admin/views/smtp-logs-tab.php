<?php
use Semla\Utils\SMTP;

$log_path = SMTP::get_log_filename();
?>
<p>Log file: <?= $log_path ?></p>
<p><a class="button-secondary" href="<?= wp_nonce_url($page_and_tab.'&amp;action=delete_log', 'semla_delete_log') ?>">Delete Log File</a></p>
<?php

if (file_exists($log_path)) { ?>
<div class="postbox">
	<div class="inside">
		<pre><?php @readfile($log_path); ?></pre>
	</div>
</div>
<?php
}
