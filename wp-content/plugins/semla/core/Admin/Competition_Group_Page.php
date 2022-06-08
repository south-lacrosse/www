<?php
namespace Semla\Admin;

use Semla\Data_Access\Competition_Group_Gateway;

class Competition_Group_Page {
	private static $action = false;
	private static $list_table;
	private static $fields;
	private static $errors;
	const PAGE_URL = '?page=semla_cg';

	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		switch (self::$action) {
			case 'edit':
			case 'new':
				require __DIR__ . '/views/league-form.php';
				break;
			default:
				require __DIR__ . '/views/league-list.php';
		}
	}

	/**
	 * Called if we are on this menu page, before anything is displayed
	 * This is a good place to handle crud, so we can add success/error messages, do redirects etc.
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_semla' ) ) {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			self::$action = $_REQUEST['action'];
		} elseif ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			self::$action = $_REQUEST['action2'];
		} else {
			self::$action = 'list';
		}
		switch (self::$action) {
			case 'edit':
				if ( isset( $_POST['submit'] ) ) {
					Admin_Menu::validate_nonce('semla_cg');
					self::create_update();
				} else {
					$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
					if (!$id) {
						self::$action = 'list';
					} else {
						self::$fields = Competition_Group_Gateway::get($id);
						if (!self::$fields) {
							wp_redirect(self::sendback() . '&error=' . urlencode('League does not exist.'));
							exit;
						}
					}
				}
				return;
			case 'new':
				if ( isset( $_POST['submit'] ) ) {
					Admin_Menu::validate_nonce('semla_cg');
					self::create_update();
				} else {
					self::$fields = [
						'id' => 0,
						'name' => '',
						'suffix' => '',
					];
				}
				return;
			case 'delete':
				Admin_Menu::validate_nonce('semla_cg');
				$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
				if (!$id) {
					wp_redirect(self::PAGE_URL);
					exit;
				}
				$ok_to_delete = Competition_Group_Gateway::validate_delete($id);
				if ($ok_to_delete !== true) {
					wp_redirect(self::sendback() . '&error=' . urlencode($ok_to_delete));
					exit;
				}
				if (!Competition_Group_Gateway::delete($id)) {
					wp_die('Error in delete');
				}
				wp_redirect(self::sendback() . '&deleted=1');
				exit;
		}
		if (self::$action === 'list') {
			if ( isset( $_POST['s'] ) && strlen( $_POST['s'] ) ) {
				wp_redirect( add_query_arg('s',$_POST['s'],self::PAGE_URL));
				exit;
			}
			// need to prepare here as it may redirect if we have deleted itema and the page is > max page
			self::$list_table = new Competition_Group_List_Table();
			self::$list_table->prepare_items();
		}
	}

	private static function sendback() {
		$sendback = wp_get_referer();
		if ( ! $sendback ) {
			return self::PAGE_URL;
		} else {
			// note: WordPress auto removes these query args in the browser if the are sent back!
			return remove_query_arg( ['action','id','ids','deleted','update'], $sendback );
		}		
	}

	private static function create_update() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		$name = isset( $_REQUEST['name'] ) ? sanitize_text_field( $_REQUEST['name'] ) : '';
		$suffix = isset( $_REQUEST['suffix'] ) ? sanitize_text_field( $_REQUEST['suffix'] ) : '';
		self::$fields = [
			'name' => $name,
			'suffix' => $suffix,
		];

		if ( ! $name ) {
			self::$errors[] = 'Error: Name is required';
		} else if ( self::$action === 'new' && Competition_Group_Gateway::get($name)) {
			self::$errors[] = 'League already exists.';
		}
		if ( !preg_match('/^[a-z0-9\-]*$/', $suffix )) {
			self::$errors[] = 'Error: Suffix must contain letter, numbers, or hyphen only';
		}
		// bail out if error found - they will be displayed on the form
		if ( self::$errors ) {
			return;
		}
		$id = Competition_Group_Gateway::insert_update( $id, self::$fields );
		if ( !Competition_Group_Gateway::insert_update( $id, self::$fields ) ) {
			global $wpdb;
			self::$errors[] = $wpdb->last_error;
			return;
		}
		wp_redirect( self::PAGE_URL . '&update=' . self::$action );
		exit;
	}
}
