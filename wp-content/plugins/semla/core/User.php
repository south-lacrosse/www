<?php
/**
 * User hooks and filters
 */
namespace Semla;

class User {
	/**
	 * Custom authentication
	 * Block users with no roles or in author-blocked from logging in
	 */
	public static function authenticate( $user, $username, $password ) {
		if (!$user || is_wp_error( $user )) return $user;
		if (count($user->roles) === 0
		|| in_array( 'author-blocked', $user->roles) ) {
			return new \WP_Error('user_disabled', '<strong>Error:</strong> Your username/email does not have permission to login.');
		}
		return $user;
	}

	public static function allow_password_reset( $allow, $user_ID ) {
		if (!$allow) return $allow;
		$user = get_userdata($user_ID);
		if (in_array( 'author-blocked', $user->roles) ) {
			return false;
		}
		return $allow;
	}

	/**
	 * Return our customised reset password message
	 */
	public static function retrieve_password_message( $message, $key, $user_login, $user_data ) {
		$message = "Someone has requested a password reset for the SEMLA website account for "
			. "$user_data->user_email.\r\n\r\n"
			. "If this was a mistake ignore this email and nothing will happen.\r\n\r\n"
			. "To reset your password visit the following address:\r\n\r\n"
			. network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' )
			. "\r\n";

		if ( ! is_user_logged_in() ) {
			$requester_ip = $_SERVER['REMOTE_ADDR'];
			if ( $requester_ip ) {
				$message .= "\r\nThis password reset request originated from the IP address $requester_ip.\r\n";
			}
		}
		return $message;
	}

	public static function wp_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
		// extract the URL to set password as the key isn't passed
		if (preg_match('/\r\n(https?:\/\/\S+)\r\n/', $wp_new_user_notification_email['message'], $match)) {
			$wp_new_user_notification_email['message']
				= "An account for you has been created on the SEMLA website.\r\n\r\n"
				. "Email: $user->user_email\r\n\r\n"
				. "You will need to login with this exact email address.\r\n\r\n"
				. "To set your password visit the following address:\r\n\r\n"
				. $match[1] . "\r\n\r\n"
				. "or login at " . wp_login_url() . "\r\n";
		}
		return $wp_new_user_notification_email;
	}

	// New user screen
	public static function init_user() {
		add_action( 'admin_notices', function() { ?>
<div class="notice notice-warning is-dismissible">
<p><strong>IMPORTANT:</strong> Before adding a new user please make sure you read the
<a target="_blank" href="https://south-lacrosse.github.io/wp-help/users.html">specific SEMLA help page</a>.</p>
</div>
<?php
		});
	}

	public static function delete_user_form($current_user, $user_ids ) {
		global $users_have_content, $wpdb;
		if (!$users_have_content) return;
		if ( $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_author IN( " . implode( ',', $user_ids ) . ' )
			AND post_type = "post"
			LIMIT 1'
		) ) {
?>
<div class="notice notice-error">
<p><strong>WARNING:</strong> Don't delete users who are authors of posts! Instead set their
Role to "Blocked Author" so they can't login, but still keep their author credits. See the
<a target="_blank" href="https://south-lacrosse.github.io/wp-help/users.html#deleting-users">SEMLA help page</a>
for details.</p>
</div>
<?php
		}
	}
}
