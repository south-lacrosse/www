<?php
namespace Semla\Admin;

use Semla\Data_Access\Team_Abbrev_Gateway;

class Team_Abbrev_Page {
	private static $action = false;
	private static $list_table;
	private static $fields;
	private static $errors;
	const PAGE_URL = '?page=semla_team_abbrev';

	public static function render_page() {
		if (!current_user_can('manage_semla'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		switch (self::$action) {
			case 'edit':
			case 'new':
				require __DIR__ . '/views/team-abbrev-form.php';
				break;
			default:
				require __DIR__ . '/views/team-abbrev-list.php';
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
					Admin_Menu::validate_nonce('semla_team_abbrev');
					self::create_update();
				} else {
					$team = isset( $_GET['team'] ) ? sanitize_text_field( $_GET['team'] ) : '';
					if (!$team) {
						self::$action = 'list';
					} else {
						self::$fields = Team_Abbrev_Gateway::get($team);
						if (!self::$fields) {
							wp_redirect(self::sendback() . '&error=' . urlencode('Team does not exist.'));
							exit;
						}
					}
				}
				return;
			case 'new':
				if ( isset( $_POST['submit'] ) ) {
					Admin_Menu::validate_nonce('semla_team_abbrev');
					self::create_update();
				} else {
					self::$fields = [
						'team' => '',
						'abbrev' => '',
					];
				}
				return;
			case 'delete':
				Admin_Menu::validate_nonce('semla_team_abbrev');
				if (!Team_Abbrev_Gateway::delete(sanitize_text_field($_REQUEST['team']))) {
					wp_die('Error in delete');
				}
				wp_redirect(self::sendback() . '&deleted=1');
				exit;
			case 'bulk_delete':
				Admin_Menu::validate_nonce('bulk-team_abbrevs');
				foreach ($_REQUEST['teams'] as $team) {
					if (!Team_Abbrev_Gateway::delete(sanitize_text_field(urldecode($team)))) {
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
			// need to prepare here as it may redirect if we have deleted items and the page is > max page
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
			return remove_query_arg( ['action','team','deleted','update'], $sendback );
		}		
	}

	private static function create_update() {
		$team = isset( $_REQUEST['team'] ) ? sanitize_text_field( $_REQUEST['team'] ) : '';
		$abbrev = isset( $_REQUEST['abbrev'] ) ? sanitize_text_field( $_REQUEST['abbrev'] ) : '';
		self::$fields = [
			'team' => $team,
			'abbrev' => $abbrev,
		];

		if ( ! $team ) {
			self::$errors[] = 'Error: Team is required';
		} else if ( self::$action === 'new' && Team_Abbrev_Gateway::get($team)) {
			self::$errors[] = 'Abbreviation for team already exists.';
			return;
		}
		if ( ! $abbrev ) {
			self::$errors[] = 'Error: Abbreviation is required';
		}
		// bail out if error found - they will be displayed on the form
		if ( self::$errors ) {
			return;
		}
		if ( !Team_Abbrev_Gateway::insert_update( self::$action === 'new', self::$fields ) ) {
			global $wpdb;
			self::$errors[] = $wpdb->last_error;
			var_dump( self::$errors );die;
			return;
		}
		wp_redirect( self::PAGE_URL . '&update=' . self::$action );
		exit;
	}
}
