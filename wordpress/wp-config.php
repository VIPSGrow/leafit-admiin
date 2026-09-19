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
/** The name of the database for WordPress */
define( 'DB_NAME', 'tbd' );

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
define( 'AUTH_KEY',         '3.>bJb2r<+YZI>x>9hVV4LU,lK+Su_Jk$RDvXt!RZT-1Os7T4j4u8QX:oLP!09nP' );
define( 'SECURE_AUTH_KEY',  'HBn*+08pLn;K.<HbcFF9[6*B/-]Pc@:,U $#3C<(VE.N)@(u.,3[e)mC#@ddxH%v' );
define( 'LOGGED_IN_KEY',    'Gv/T`MFhrb$24C39$jXb(VDF(&eKB_?y1WY(;Os$&+jodA.Jr[#*wGF2Pg(oQREs' );
define( 'NONCE_KEY',        'W2tM! OF/1~]@`K3CW?D@}@:b)jlE Mqm!ahERE|cYFw?ed=o#C*=vf&JwNOO!+.' );
define( 'AUTH_SALT',        '`;MGactxa~%FK[9{_~j%s?C_lL1!4pUDag)`d{dmQo(XC~m6y-k[XEDj_%w!:Mhg' );
define( 'SECURE_AUTH_SALT', 'PRo0IE>G`rL&+}_4&t#ci#jlUB??v@9b:V|zhpZDSFG.2+N~Odb4I_kcSiua6hI@' );
define( 'LOGGED_IN_SALT',   'o9|Mng1.KK@ll]x>M9!ry,vBU?/@2)2>nQ12A808(N/F:8l!kPuHc_L@M/g-E4?U' );
define( 'NONCE_SALT',       '5ynhWiD4fg%,Q8W;-5?#_+b|OxKcIwP*>&kLjD1wpp%kL$qTf 08J;(.w^21/JHx' );

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
