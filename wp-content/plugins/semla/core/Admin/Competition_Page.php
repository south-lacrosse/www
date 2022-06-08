<?php
namespace Semla\Admin;

use Semla\Data_Access\Competition_Gateway;

class Competition_Page {
	private static $action = false;
	private static $list_table;
	private static $fields;
	private static $errors;
	const PAGE_URL = '?page=semla_competition';

	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		switch (self::$action) {
			case 'edit':
			case 'new':
				require __DIR__ . '/views/competition-form.php';
				break;
			default:
				require __DIR__ . '/views/competition-list.php';
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
					Admin_Menu::validate_nonce('semla_competition');
					self::create_update();
				} else {
					$team = isset( $_GET['team'] ) ? sanitize_text_field( $_GET['team'] ) : '';
					if (!$team) {
						self::$action = 'list';
					} else {
						self::$fields = Competition_Gateway::get($team);
						if (!self::$fields) {
							wp_redirect(self::sendback() . '&error=' . urlencode('Team does not exist.'));
							exit;
						}
					}
				}
				return;
			case 'new':
				if ( isset( $_POST['submit'] ) ) {
					Admin_Menu::validate_nonce('semla_competition');
					self::create_update();
				} else {
					self::$fields = [
						'team' => '',
						'abbrev' => '',
					];
				}
				return;
			case 'delete':
				Admin_Menu::validate_nonce('semla_competition');
				if (!Competition_Gateway::delete(sanitize_text_field($_REQUEST['team']))) {
					wp_die('Error in delete');
				}
				wp_redirect(self::sendback() . '&deleted=1');
				exit;
			case 'bulk_delete':
				Admin_Menu::validate_nonce('bulk-competitions');
				foreach ($_REQUEST['teams'] as $team) {
					if (!Competition_Gateway::delete(sanitize_text_field(urldecode($team)))) {
						wp_die('Error in bulk delete');
					}
				}
				wp_redirect(self::sendback() . '&deleted=' . count($_POST['teams']));
				exit;
		}
		if (self::$action === 'list') {
			if ( isset( $_POST['s'] ) && strlen( $_POST['s'] ) ) {
				wp_redirect( add_query_arg('s',$_POST['s'],self::PAGE_URL));
				exit;
			}
			// need to prepare here as it may redirect if we have deleted itema and the page is > max page
			self::$list_table = new Team_Abbrev_List_Table();
			self::$list_table->prepare_items();
		}
	}

	private static function sendback() {
		$sendback = wp_get_referer();
		if ( ! $sendback ) {
			return self::PAGE_URL;
		} else {
			// note: WordPress auto removes these query args in the browser if the are sent back!
			return remove_query_arg( ['deleted','update'], $sendback );
		}		
	}

	private static function create_update() {
		$name = isset( $_REQUEST['name'] ) ? sanitize_text_field( $_REQUEST['name'] ) : '';
		$suffix = isset( $_REQUEST['suffix'] ) ? sanitize_text_field( $_REQUEST['suffix'] ) : '';
		self::$fields = [
			'name' => $name,
			'suffix' => $suffix,
		];

		// some basic validation
		if ( ! $name ) {
			self::$errors[] = 'Error: Name is required';
		} else if ( self::$action === 'new' && Competition_Gateway::get($name)) {
			self::$errors[] = 'League already exists.';
		}
		if ( ! $suffix ) {
			self::$errors[] = 'Error: Suffix is required';
//		 } else if ($suffix && !get_page_by_path($suffix)) {
//			 self::$errors[] = 'Tables page does not exists.';
		}
		// bail out if error found - they will be displayed on the form
		if ( self::$errors ) {
			return;
		}
		if ( !Competition_Gateway::insert_update( self::$action === 'new', self::$fields ) ) {
			global $wpdb;
			self::$errors[] = $wpdb->last_error;
			return;
		}
		wp_redirect( self::PAGE_URL . '&update=' . self::$action );
		exit;
	}
}
