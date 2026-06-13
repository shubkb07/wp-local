<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
require_once __DIR__ . '/wp-resolve/solve.php';

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '3z+&%|(<89Fo7/,Dnzu0VV5Q.Kp?)A:9<K_^V1VtIBLBaS.ml+l,cFpsq9M?>PL ');
define('SECURE_AUTH_KEY', 'u8oLs6Apw@v+LMVHWe.ih|OL*Ceu-Y9Bj:$Ra~T7)0cbv{f%a%jR|X8d&:J6U2io');
define('LOGGED_IN_KEY', '1{7usvAUU6Qi[>Vc|/g0,bV^}cYlf&)1fGux7FUbhQ.hr<k)vw[bX+%IB^(QZaR&');
define('NONCE_KEY', '>@xh=LbC?|B Bo1RGNmj<Ur1<f]wc?7GmnrD!?dHjz!X[PX6,S,!Sg35^a-e9Qw[');
define('AUTH_SALT', 'z>s%=^M,#K}!4#h5b<Q3@/dbE}m0vl;8eD*^~IINE$F`1 |,1/<&vBl~1Ozbhdyf');
define('SECURE_AUTH_SALT', 'L%OoCrJj;,3f6%,R]-+fG{F^WJ/{g{h)suGZWJy7enQge%aW0ly/v!QmX+Pdp}=q');
define('LOGGED_IN_SALT', '8GAtaW;Jw#s@SNPcg5O8kA6M656mCe@scdCP 2oEN!a`z1VaQ6f]$aa5oPA2aFtD');
define('NONCE_SALT', '-Lj/WUF!=W_DA;6VbjWI:&0X!iYOdD&%%zTwg)5*b`Zt===.w(HY.Z4&0iQ$eW82');
define('WP_CACHE_KEY_SALT', 'rN5h;H2Z;%?r-wSbRRo?FVD&W;i<Gr2>2nOsjgUB^&@<-O(#7deFs^JdE]BDpTKV');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', false);
}
/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
