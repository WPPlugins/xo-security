<?php
/*
Plugin Name: XO Security
Plugin URI: https://xakuro.com/wordpress/xo-security/
Description: XO Security is a plugin to enhance login related security.
Author: Xakuro System
Author URI: https://xakuro.com/
License: GPLv2
Version: 1.6.2
Text Domain: xo-security
Domain Path: /languages/
*/

define( 'XO_SECURITY_LOGINLOG_TABLE_NAME', 'xo_security_loginlog' );
define( 'XO_SECURITY_DIR', plugin_dir_path( __FILE__ ) );
define( 'XO_SECURITY_VERSION', '1.6.2' );

load_plugin_textdomain( 'xo-security', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once( XO_SECURITY_DIR . 'main.php' );
new XO_Security;

if ( is_admin() ) {
	require_once( XO_SECURITY_DIR . 'admin.php' );
	new XO_Security_Admin;
}

register_activation_hook( __FILE__, 'XO_Security::activation' );
register_uninstall_hook( __FILE__, 'XO_Security::uninstall' );
