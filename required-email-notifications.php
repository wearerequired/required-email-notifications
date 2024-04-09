<?php
/**
 * Plugin Name: Email Notifications
 * Plugin URI: https://github.com/wearerequired/required-email-notifications
 * Description: Email notifications queue and management.
 * Version: 3.0.0-alpha
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Author: required gmbh
 * Author URI: https://required.com
 * Update URI: false
 * Text Domain: rplusnotifications
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Copyright (c) 2024 required (email: info@required.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
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
