<?php
namespace Semla;
use Semla\Admin\Admin_Menu;
use Semla\Utils\Image;
/**
 * Handling initialisation for the admin pages
 */
class App_Admin {
	// post types to show in the dashboard "At a Glance" meta box, and their
	// dashicon codes
	private const POST_TYPES = [
		'clubs' => 'f332', 'history' => 'f495'
	];

	public static function init() {
		// if clubs have changed then purge pages/rest routes which use club data
		add_action('save_post_clubs', function() {
			do_action('litespeed_purge', 'semla_clubs');
		});
		add_filter('upload_mimes', [Image::class, 'allow_svg_mimes'], 10, 1);

		if (defined('DOING_AJAX') && DOING_AJAX) {
			if (!empty($_POST['action']) && $_POST['action'] ==='inline-save'
			&& !empty($_POST['post_type'])) {
				self::add_modified_column($_POST['post_type']);
			}
			// short circuit to avoid running anything not needed in AJAX
			// IMPORTANT: make sure anything which can be called from AJAX is
			// above here!
			return;
		}

		// get rid of useless stuff added to page
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('admin_print_styles', 'print_emoji_styles');
		add_filter('emoji_svg_url', '__return_false'); // stops prefetch being added
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		// stop queries to count comments, which are called when menu is displayed
		add_filter('wp_count_comments', function() {
			return (object) [
				'approved'       => 0,
				'moderated'      => 0,
				'spam'           => 0,
				'trash'          => 0,
				'post-trashed'   => 0,
				'total_comments' => 0,
				'all'            => 0,
			];
		});

		// add our admin menu pages
		Admin_Menu::init();

		// Defer some setup until we know what screen this is
		add_action('current_screen', function(\WP_Screen $screen) {
			switch ($screen->base) {
				case 'dashboard' :
					self::init_dashboard();
					break;
				case 'edit': // post/page/cpt list screen
					self::init_edit($screen);
					break;
				case 'post': // post/page editor
					add_filter( 'default_content', [self::class, 'default_content'], 10, 2);
					add_filter('litespeed_bypass_metabox', '__return_true');
					break;
				case 'user':
					User::init_user();
					break;
				case 'users':
					add_action( 'delete_user_form', [User::class, 'delete_user_form'], 10, 2);
					break;
				case 'edit-comments':
				case 'options-discussion':
					wp_redirect(admin_url());
					exit;
				case 'options-reading':
					// Remove the blog_public option from the Reading screen, as we
					// override it by WP_ENVIRONMENT_TYPE. We can't actually remove the option,
					// so we do the next best thing and hide it!
					add_action('admin_head', function() {
						echo '<style>.option-site-visibility,#tab-link-site-visibility{display:none}</style>' . "\n";
					});
					break;
			}
		});

		add_action ('enqueue_block_editor_assets',  [self::class, 'enqueue_block_editor_assets']);
		add_action ('enqueue_block_assets',  [self::class, 'enqueue_block_assets']);
		add_action('admin_menu', function() {
			// Removes comments/discussion from admin menu
			remove_menu_page( 'edit-comments.php' );
			remove_submenu_page( 'options-general.php', 'options-discussion.php' );
			// change Clubs default to only show published
			global $submenu;
			$url = 'edit.php?post_type=clubs';
			if (isset($submenu[ $url ])) {
				foreach( $submenu[ $url ] as $key => $value ) {
					if( $value[2] === $url ) {
						$submenu[$url][$key][2] = "$url&post_status=publish";
						break;
					}
				}
			}
		});
	}

	/**
	 * Test if the user wants to duplicate an existing page/post/CPT, and if so
	 * set the content of the new post. A 'Duplicate' link is added to each post in
	 * the relevant list table, and links to the new-post page with the duplicate
	 * query var set.
	 */
	public static function default_content($post_content, $post) {
		if ( isset( $_GET['duplicate'] ) ) {
			$duplicate = absint( $_GET['duplicate'] );
			if ($duplicate && current_user_can( 'edit_post', $duplicate ))  {
				$duplicated_post = get_post( $duplicate );
				if ( $duplicated_post instanceof \WP_Post ) {
					return $duplicated_post->post_content;
				}
			}
		}
		return $post_content;
	}

	public static function enqueue_block_editor_assets() {
		$screen_base = get_current_screen()->base;
		$plugin_dir = dirname(__DIR__);
		$base_url = plugins_url('/', __DIR__);

		$asset_file = include "$plugin_dir/blocks/core/index.asset.php";
		$dependencies = $asset_file['dependencies'];
		// by adding wp-edit-... as a dependency we make sure our script
		// runs after the core blocks have been created, so we can then
		// remove or change them if we want
		$edit_dependency = match($screen_base) {
			'post' => 'wp-edit-post',
			'widgets' => 'wp-edit-widgets',
			'customize' => 'wp-customize-widgets',
			'site-editor' => 'wp-edit-site',
			default => false
		};
		if ($edit_dependency && !in_array($edit_dependency, $dependencies)) {
			$dependencies[] = $edit_dependency;
		};
		wp_enqueue_script('semla-blocks-core',
			$base_url . 'blocks/core/index.js', $dependencies, $asset_file['version']);

		if ($screen_base === 'post' || $screen_base === 'site-editor') {
			if ($screen_base === 'post') {
				$asset_file = include "$plugin_dir/blocks/editor/index.asset.php";
				$dependencies = $asset_file['dependencies'];
				wp_enqueue_script('semla-blocks-editor',
					$base_url . 'blocks/editor/index.js', $dependencies, $asset_file['version']);
			}

			wp_add_inline_script('semla-map-editor-script',
				'window.semla=window.semla||{};window.semla.gapi="'
					. get_option('semla_gapi_key') . '"',
				'before');
		}
	}

	/**
	 * Styles enqueued here will be loaded into the editor iframe if it is used,
	 * as well as the edit post page
	 */
	public static function enqueue_block_assets() {
		$plugin_dir = dirname(__DIR__);
		$base_url = plugins_url('/', __DIR__);
		$asset_file = include "$plugin_dir/blocks/core/index.asset.php";
		wp_enqueue_style('semla-blocks-core',
			$base_url . 'blocks/core/index.css', [], $asset_file['version']);

		$screen_base = get_current_screen()->base;
		if ($screen_base === 'post' || $screen_base === 'site-editor') {
			wp_enqueue_style('semla-flags',
				$base_url . 'css/flags' . SEMLA_MIN . '.css', [], '1.1');
			wp_enqueue_style('semla-clubs-grid',
				$base_url . 'css/clubs-grid' . SEMLA_MIN . '.css', [], '1.0');
			wp_enqueue_style('semla-clubs-list',
				$base_url . 'css/clubs-list' . SEMLA_MIN . '.css', [], '1.1');
		}
	}

	private static function init_dashboard() {
		// Stop comments query in site activity panel
		// If theme uses comment templates then this filter should be run on
		// front end too to stop any queries running
		add_filter('comments_pre_query', function($comment_data,$query) {
			if ($comment_data) return $comment_data;
			if ($query->query_vars['count'] ?? false) return 0;
			return [];
		}, 10, 2);

		add_action('wp_dashboard_setup', function() {
			remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
			remove_meta_box('dashboard_primary', 'dashboard', 'side');
			// No Limit Login Attempts Reloaded widget if you're not an admin
			if ( !current_user_can( 'manage_options' ) ) {
				remove_meta_box('llar_stats_widget', 'dashboard', 'normal');
			}
		});

		/** Show our custom post types on the dashboard. */
		add_filter('dashboard_glance_items', function($items = []) {
			foreach( self::POST_TYPES as $type => $dashicon ) {
				if( ! post_type_exists( $type ) ) continue;
				$num_posts = wp_count_posts( $type );
				if( $num_posts ) {
					$published = intval( $num_posts->publish );
					$post_type = get_post_type_object( $type );

					$text = '%s ' . ($published === 1 ? $post_type->labels->singular_name : $post_type->labels->name);
					$text = sprintf( $text, number_format_i18n( $published ) );

					if ( current_user_can( $post_type->cap->edit_posts ) ) {
						$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
					} else {
						$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";
					}
				}
			}
			return $items;
		}, 10, 1 );

		add_action('admin_head', function() {
			echo '<style>';
			/** Add styles to show the correct icons for our custom post types */
			foreach( self::POST_TYPES as $post_type => $dashicon ) {
				echo "#dashboard_right_now a.$post_type-count:before,"
					. "#dashboard_right_now span.$post_type-count:before{content:"
					. "\"\\$dashicon\";}";
			}
			echo "</style>\n";
		});
	}

	private static function init_edit($screen) {
		if ($screen->post_type === 'clubs') {
			// check if coming back from editor, and only show published
			if (count($_GET) === 1 && !empty($_SERVER['HTTP_REFERER'])
			&& preg_match('!/wp-admin/post(-new|).php\?!',$_SERVER['HTTP_REFERER']) ) {
				// setting request URI will ensure correct page links, and also WP
				// will set the canonical link and use that to change the browser
				// address bar to match. Alternative is to redirect here, but that
				// isn't needed
				$_SERVER['REQUEST_URI'] .= '&post_status=publish';
				$_REQUEST['post_status'] = $_GET['post_status'] = 'publish';
			}
			// Change default ordering of clubs
			if ( !isset($_GET['orderby'])) {
				add_action( 'pre_get_posts', function( $query ) {
					$query->set('orderby','title');
					$query->set('order','asc');
				});
			}
			// make sure Clubs submenu item is highlighted, as we have changed
			// the submenu item earlier
			add_filter( 'submenu_file', function( $submenu_file, $parent_file ) {
				if ($submenu_file === 'edit.php?post_type=clubs') {
					return 'edit.php?post_type=clubs&post_status=publish';
				}
				return $submenu_file;
			},10,2);
			// need to make sure the sortable columns match our new order
			add_filter( "manage_{$screen->id}_sortable_columns", function ($columns) {
				return [
					'title'    => ['title', false, 'Title', 'Table ordered by Title.', 'asc'],
					'date'     => ['date', true, 'Date', 'Table ordered by Date.'],
				];
			});
		}
		self::add_modified_column($screen->post_type);
		add_filter( "manage_{$screen->id}_sortable_columns", function ($columns) {
			$columns['modified'] = ['modified', true, 'Modified Date', 'Table ordered by Modified Date.'];
			return $columns;
		});
		add_action('admin_enqueue_scripts', function() {
			echo '<style>.fixed .column-modified{width:14%}</style>';
		});

		// add link to allow users to duplicate a post
		$action_type = $screen->post_type === 'page' ? 'page' : 'post';
		add_filter("{$action_type}_row_actions", function($actions, $post) {
			if (current_user_can( 'edit_post', $post->ID )) {
				$actions['semla_duplicate'] = '<a href="post-new.php'
					. ('post' === $post->post_type ? '' : "?post_type=$post->post_type")
					. '&amp;duplicate=' . $post->ID
					. '" aria-label="Duplicate &#8220;' . esc_attr($post->post_title) . '&#8221;">Duplicate</a>';
			}
			return $actions;
		}, 10, 2);
	}

	private static function add_modified_column($post_type) {
		add_filter( "manage_{$post_type}_posts_columns", function($columns) {
			$columns['modified'] = 'Modified';
			return $columns;
		});
		add_action( "manage_{$post_type}_posts_custom_column", function ($column_name, $post_id) {
			if ( $column_name === 'modified' ) {
				$post = get_post($post_id);
				if ($post->post_modified_gmt !== $post->post_date_gmt) {
					the_modified_date('Y/m/d g:i a');
				}
			}
		}, 10, 2);
	}
}
