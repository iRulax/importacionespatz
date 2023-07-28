<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'importacionespatz' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

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
define( 'AUTH_KEY',         '$(mCSlg,u}luhCQTY7Mi)-(eTK:En$;Tq*AbfsBO ]XrS+NkA9^karVu*z)Z8rGe' );
define( 'SECURE_AUTH_KEY',  'B~/t% rXjoRm7Ed_|V]4pX.#oPH9O3l<o~Fsye]18wFD]e%1aJ{#q4R}`{pAFmq?' );
define( 'LOGGED_IN_KEY',    'lX o:EXZ(rmxN:h.tQSf7R)(kYxxHy^<7}}j}c/?3cj+}bG4l%})Yb%+Ri2^?mrO' );
define( 'NONCE_KEY',        'o <A^|7,PA&<SIZ{DSzm!:YWEQGk5kDc5T3sV%%Y|>Ns7mbEJQz%g[Wo7]6*)]#)' );
define( 'AUTH_SALT',        '>)/{H=@y^x<y]!I6f<`[t$nh@uwT@#6CHhxttWPbr3UaERpIp[b:qx_l.G,+2$l&' );
define( 'SECURE_AUTH_SALT', 'ps`=0k)LL!M>G*9I63gz}q]7Kz*uX![D>9w,}4&uGisxB:4IP$YA-.#jFMNpnQ(T' );
define( 'LOGGED_IN_SALT',   'KTj+`xGvuP];&d0elbv49cizotWV&DU*i7Vhyg~fI=f4:Q1cd@68`e]C KrPMy7~' );
define( 'NONCE_SALT',       'CVa,J17[1M 9Knznpz!JgDs#v.}.aDR,^vT;]r{F&qcq35x9sDB.j1*WWqiwIyG/' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
