<?php
namespace Semla\Admin;
/**
 * Display debug log on admin page
 */
class Debug_Log_Page {
	public static function render_page() {
		if (!current_user_can('manage_options'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		} ?>
<div class="wrap">
<h1>Debug Log</h1>
<?php
        $log_path = ini_get( 'error_log' );
        if (!$log_path) {
            echo "<p>No error log file set</p>";
        } else {
            echo "<p>Log file: $log_path</p>\n";
            if (isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] === 'delete_log') {
                Admin_Menu::validate_nonce('semla_delete_log');
                @unlink($log_path);
                Admin_Menu::dismissible_success_message('Log file deleted');
            } ?>
<p><a class="button-secondary" href="<?= wp_nonce_url('?page=semla_debug_log&action=delete_log', 'semla_delete_log') ?>">Delete Log File</a></p>
<?php
             if (file_exists($log_path)) { ?>
<div class="postbox">
    <div class="inside">
        <pre><?php @readfile($log_path); ?></pre>
    </div>
</div>
<?php
            }
        }
        echo "</div>\n";
    }
}