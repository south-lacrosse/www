<?php
namespace Semla\Admin;

use Semla\Utils\SMTP;

/**
 * SMTP details, test send, log files
 */
class SMTP_Page {
	private static $test_passed;

	public static function render_page() {
		if (!current_user_can('manage_options'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		?>
<div class="wrap">
<h1>SMTP</h1>
<?php
		$send_to = '';
		if ( isset($_POST['semla_send_test_email']) ) {
			Admin_Menu::validate_nonce('semla_smtp');
			$send_to = $_POST['send_to'] ?? '';
			if ($send_to) self::send_test_email($send_to);
		} elseif (isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] === 'delete_log') {
			Admin_Menu::validate_nonce('semla_delete_log');
			@unlink(SMTP::get_log_filename());
			Admin_Menu::dismissible_success_message('Log file deleted');
		}
		$active_tab = Admin_Menu::render_tabs('semla_smtp',
			['dashboard' => 'Dashboard', 'logs'=> 'Logs']);
		$page_and_tab = "?page=semla_smtp&tab=$active_tab";
		switch ($active_tab) {
			case 'dashboard':
				require __DIR__ . '/views/smtp-dashboard-tab.php';
				break;
			case 'logs':
				require __DIR__ . '/views/smtp-logs-tab.php';
				break;
		}
	?>
</div>
<?php
	}

	private static function send_test_email($send_to) {
		// don't log this
		remove_action( 'wp_mail_failed', [SMTP::class, 'mail_failed'] );
		remove_action( 'wp_mail_succeeded', [SMTP::class, 'mail_succeeded'] );

		add_action( 'wp_mail_failed', function () {
			self::$test_passed = false;
		});
		add_action( 'wp_mail_succeeded', function () {
			self::$test_passed = true;
		});
		ob_start();
		wp_mail($send_to, 'SEMLA Email Test', 'Test message');
		$test_result = ob_get_clean();
		if (self::$test_passed) {
			Admin_Menu::dismissible_success_message('Test email sent. See debug information below.<br><br>'
				. $test_result);
		} else {
			Admin_Menu::dismissible_error_message('Test email failed. See debug information below.<br><br>'
				. $test_result);

		}

	}
}
