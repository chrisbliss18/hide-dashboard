<?php
/*
	Plugin Name: Hide Dashboard
	Plugin URI: https://ithemes.com
	Description: Rename the WordPress Dashboard access URLs
	Version: 0.0.1
	Text Domain: hide-dashboard
	Domain Path: /languages
	Author: iThemes.com
	Author URI: https://ithemes.com
	Network: True
	License: GPLv2
	Copyright 2014  iThemes  (email : info@ithemes.com)
*/

if ( ! class_exists( 'Hide_Dashboard_Actions' ) ) {
	require( dirname( __FILE__ ) . '/inc/class-hide-dashboard-actions.php' );
	new Hide_Dashboard_Actions( __FILE__ );
}

if ( is_admin() && ! class_exists( 'Hide_Dashboard_Admin' ) ) {
	require( dirname( __FILE__ ) . '/inc/class-hide-dashboard-admin.php' );
	new Hide_Dashboard_Admin( __FILE__ );
}