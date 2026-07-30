<?php
/**
 * Production WordPress configuration for the isolated DigitalOcean runtime.
 *
 * Secrets are loaded from a root-owned file outside the public webroot.
 */

$secrets_file = '/home/afullbeard/private/wordpress-secrets.json';

if ( ! is_readable( $secrets_file ) ) {
	http_response_code( 503 );
	exit( 'WordPress is not configured.' );
}

$secrets_json = file_get_contents( $secrets_file );
$secrets      = is_string( $secrets_json ) ? json_decode( $secrets_json, true ) : null;

if ( ! is_array( $secrets ) ) {
	http_response_code( 503 );
	exit( 'WordPress is not configured.' );
}

$db_password = $secrets['db_password'] ?? null;
$salts       = $secrets['salts'] ?? null;

if ( ! is_string( $db_password ) || '' === $db_password || ! is_array( $salts ) ) {
	http_response_code( 503 );
	exit( 'WordPress is not configured.' );
}

define( 'DB_NAME', 'afullbeard_wp' );
define( 'DB_USER', 'afullbeard_wp' );
define( 'DB_PASSWORD', $db_password );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$salt_names = array(
	'AUTH_KEY',
	'SECURE_AUTH_KEY',
	'LOGGED_IN_KEY',
	'NONCE_KEY',
	'AUTH_SALT',
	'SECURE_AUTH_SALT',
	'LOGGED_IN_SALT',
	'NONCE_SALT',
	'WP_CACHE_KEY_SALT',
);

foreach ( $salt_names as $salt_name ) {
	$salt_value = $salts[ $salt_name ] ?? null;

	if ( ! is_string( $salt_value ) || '' === $salt_value ) {
		http_response_code( 503 );
		exit( 'WordPress is not configured.' );
	}

	define( $salt_name, $salt_value );
}

unset( $db_password, $salt_name, $salt_names, $salt_value, $salts, $secrets, $secrets_file, $secrets_json );

$table_prefix = 'wp_';

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'FORCE_SSL_ADMIN', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
