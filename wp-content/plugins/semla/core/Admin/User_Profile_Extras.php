<?php
namespace Semla\Admin;
/**
 * Handle extra user profile fields, and disabling of users
 */
class User_Profile_Extras {
	public static function init() {
		add_filter('authenticate', function ( $user, $username, $password ) {
			if (!$user || is_wp_error( $user )) return $user;
			if ( $user->semla_disable_user ) {
				return new \WP_Error('user_disabled', 'Your user id has been disabled');
			}
			return $user;
		}, 30, 3 );
	}

	/**
	 * Set up all the hooks for Admin screens
	 */
	public static function init_admin() {
		// display on own profile and for updating other users
		add_action('show_user_profile', [self::class, 'show_fields']);
		add_action('edit_user_profile', [self::class, 'show_fields']);

		// and again for the update part
		add_action( 'personal_options_update',  [self::class, 'save_fields'] ); 
		add_action( 'edit_user_profile_update',  [self::class, 'save_fields'] );
	}

	/**
	 * Add extra functionality to Users list
	 */
	public static function init_users() {
		add_action( 'manage_users_custom_column', function($output, $column_name, $user_ID) {
			if ( $column_name === 'semla_user_disabled' ) {
				if ( get_user_meta( $user_ID, 'semla_disable_user', true ) == 1 ) {
					return 'Yes';
				}
				return '';
			}
		}, 10, 3 );
		add_filter( 'manage_users_columns', function($defaults) {
			$defaults['semla_user_disabled'] = 'Disabled';
			return $defaults;
		});
		add_action( 'admin_footer-users.php', function() {
			echo '<style type="text/css">.fixed .column-semla_user_disabled{width:74px;text-align:center}</style>';
		});
	}

	/**
	 * Hook to display our extra information
	 */
	public static function show_fields(\WP_User $user) {
		// don't show for the main Administrator
		if ($user->ID === 1)
			return;
		?>
<table class="form-table">
	<tbody>
		<tr>
			<th>
				<label for="semla_disable_user">Disable User Account</label>
			</th>
			<td>
				<input type="checkbox" name="semla_disable_user" id="semla_disable_user" value="1"<?php checked( 1, $user->semla_disable_user ); if (!current_user_can('edit_users')) echo ' disabled'; ?> />
				<span class="description">If checked, the user cannot login with this account.</span>
			</td>
		</tr>
	<tbody>
</table>
<?php		
		return;
	}
	
	public static function save_fields( $user_id ) {
		// check user_id = 1 so we don't disable the main Administrator
		if ( ! current_user_can( 'edit_users' ) || $user_id === 1)
			return;
		if ( !isset( $_POST['semla_disable_user'] ) ) {
			delete_user_meta( $user_id, 'semla_disable_user' );
		} else {
			update_user_meta( $user_id, 'semla_disable_user', 1 );
		}
	}
}
