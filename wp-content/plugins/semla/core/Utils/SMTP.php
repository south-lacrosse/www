<?php
/**
 * Change WordPress mail functions to use SMTP
 */
namespace Semla\Utils;

class SMTP {
	// Change the WordPress mailer to use secure SMTP
	public static function phpmailer_init( $phpmailer ) {
		$args = self::get_args();
		if (!$args) return;

		$phpmailer->isSMTP();
		foreach ($args as $key =>$value) {
			$phpmailer->{$key} = $value;
		}
		if(isset($_POST['semla_send_test_email'])) {
			$phpmailer->SMTPDebug = 4;
			$phpmailer->Debugoutput = 'html';
		}
	}

	/**
	 * Called from this class and also SMTP_Page so it can display the args
	 * @return array an array of SMTP configuration arguments
	 */
	public static function get_args() {
		if (!defined('SMTP_USER') || !defined('SMTP_PASS')) return false;

		$args['Host'] = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
		$args['SMTPAuth'] = true;
		$args['Port'] = defined('SMTP_PORT') ? SMTP_PORT : 587;
		$args['Username'] = SMTP_USER;
		$args['Password'] = SMTP_PASS;
		$args['SMTPSecure'] = 'tls';
		$args['From'] = defined('SMTP_FROM') ? SMTP_FROM : SMTP_USER;
		if (defined('SMTP_NAME')) {
			$args['FromName'] = SMTP_NAME;
		} elseif (wp_get_environment_type() === 'production') {
			$args['FromName'] = 'SEMLA';
		} else {
			$url = get_option( 'siteurl' );
			if (preg_match('!^https?://([^\.]*)\.!', $url, $matches)) {
				$args['FromName'] = "SEMLA ($matches[1])";
			} else {
				$args['FromName'] = "SEMLA ($url)";
			}
		}
		return $args;
	}
	
	public static function mail_failed( $error ) {
		self::log('Failed: ' . print_r($error,true));
	}

	public static function mail_succeeded( $mail_data ) {
		self::log('Mail sent: To=' . implode(',',$mail_data['to']) . ', Subject=['
			.$mail_data['subject'] . ']');
	}

	private static function log( $str ) {
		@file_put_contents(self::get_log_filename(), date('[d-M-Y G:i:s e] ')
			. $str . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	public static function get_log_filename() {
		return __DIR__ . '/smtp.log';
	}
}
