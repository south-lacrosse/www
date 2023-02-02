<?php
/**
 * The base configuration for WordPress, with SEMLA specific setup.
 * Never commit wp-config.php to a public repository, though this sample is OK.
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
define( 'DB_NAME', 'local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', 'localhost' );

define('DB_CHARSET', 'utf8mb4');
# Note: DB_COLLATE should usually be left blank, but we need to specify this on Hostinger
# because of the way WordPress checks capabilities - and it doesn't think
# utf8mb4_unicode_520_ci is available, even though it is available and is the best collation,
# and will be selected on other systems.

# This causes us problems as the sl_ tables were created with utf8mb4_unicode_520_ci,
# but when we recreate the slc_ tables they will have a different collation, and if you join
# you get "illegal mix of collations" errors.

# So, since we have to keep all tables the same we have to set the collate, so it's best to
# just set it to utf8mb4_unicode_520_ci everywhere.
define( 'DB_COLLATE', 'utf8mb4_unicode_520_ci' );


/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**#@-*/

$table_prefix  = 'wp_';

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/**
 * Hardcoding WP_SITEURL and WP_HOME saves a couple of database lookups for every page.
 *
 * Always set WP_ENVIRONMENT_TYPE. This is important as we filter the blog_public option
 * which restricts search engines etc. depending on this setting, to ensure the live site always
 * has it set to 1, and staging/development always have 0 (even if we copy over a production
 * database). Note: The Local development tool automatically sets this to 'local', which is
 * for usually development machines not reachable from the internet.
 */
// Local Server ---------------------------------------------
define('WP_SITEURL', 'https://dev.southlacrosse.org.uk');
define('WP_ENVIRONMENT_TYPE','development'); // Comment out if using Local
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true); // true to write to wp-content/debug.log, or location of log file
// define('BE_MEDIA_FROM_PRODUCTION_URL', 'https://www.southlacrosse.org.uk'); // get media from production, if plugin installed

// if SEMLA .css & .js files should be served minimized. Defaults to '.min' to minimize, '' otherwise
// define('SEMLA_MIN', '');
// End Local Server  -----------------------------------------

// Live Server ---------------------------------------------
define('WP_SITEURL', 'https://www.southlacrosse.org.uk');
define('WP_ENVIRONMENT_TYPE','production');
define('WP_DEBUG', false);

// To track down errors in production comment out WP_DEBUG above, and uncomment
// the 3 following defines.
// Note: if WP_DEBUG_LOG is true then errors are written to wp-content/debug.log,
// or location of log file. Make 100% sure this file cannot be served by the web
// server as bots will try to access it

// define('WP_DEBUG', true);
// define('WP_DEBUG_DISPLAY', false);
// define('WP_DEBUG_LOG', true);

define('WP_CACHE', true); // if LSCache installed on host, though it should add the line when enabled

// Only set Google Analytics id in production
define('SEMLA_ANALYTICS', 'UA-xxxxxxxx-y');

// Set SMTP_USER and SMTP_PASS on production & staging sites, or on development when testing
// If not set the Local development tool will intercept emails in Mailhog
define('SMTP_USER', '<user to send emails>' );
define('SMTP_PASS', '<password for sending wordpress emails>');

# define('SMTP_HOST', 'smtp.hostinger.com' ); // The hostname of the mail server, defaults to hostinger
# define('SMTP_FROM', '<from address>' ); defaults to SMTP_USER
# NAME defaults to 'SEMLA' for prod, otherwise 'SEMLA (stg|dev)' using 1st part of site URL
# define('SMTP_NAME', 'FROM_NAME');
// End Live Server -----------------------------------------

define('WP_HOME',WP_SITEURL);

/**
 * Change uploads to a different folder. Has to be relative to ABSPATH
 */
define('UPLOADS', 'media');

define('DISALLOW_FILE_EDIT', true);

define('WP_POST_REVISIONS', 5);
define('AUTOSAVE_INTERVAL', 120); // seconds, default 60

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

// If WordPress isn't yet compatible with a very recent version of PHP in
// development, and WP_DEBUG is true, you might get deprecated errors. To
// ignore those uncomment use the call below.
// Make sure to comment it out again when WordPress catches up!
// error_reporting( E_ALL ^ E_DEPRECATED );
