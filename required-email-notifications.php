<?php
/*
Plugin Name: Email Notifications
Plugin URI: https://github.com/wearerequired/required-email-notifications
Description: Email notifications queue and management.
Version: 2.0.0
Author: required gmbh
Author URI: https://required.com
Text Domain: rplusnotifications
License: GPL
Copyright: required gmbh
*/

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

if ( ! class_exists( '\Rplus\Notifications\NotificationController' ) ) {
	trigger_error( sprintf( '%s does not exist. Check Composer\'s autoloader.', '\Rplus\Notifications\NotificationController' ), E_USER_WARNING );

	return;
}

define( 'Rplus\Notifications\PLUGIN_FILE', __FILE__ );
define( 'Rplus\Notifications\PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin activation and deactivation
 */
register_activation_hook( __FILE__, 'req_notifications_plugin_activate' );
register_deactivation_hook( __FILE__, 'req_notifications_plugin_deactivate' );
if ( ! function_exists( 'req_notifications_plugin_activate' ) ) {
	function req_notifications_plugin_activate() {
		\Rplus\Notifications\NotificationController::_activate();
	}
}
if ( ! function_exists( 'req_notifications_plugin_deactivate' ) ) {
	function req_notifications_plugin_deactivate() {
		\Rplus\Notifications\NotificationController::_deactivate();
	}
}

/**
 * Get instance of Email Notification Controller
 *
 * @return \Rplus\Notifications\NotificationController
 */
function req_notifications() {
	return \Rplus\Notifications\NotificationController::get_instance();
}

// instantiate me
$rplus_notifications = req_notifications();
