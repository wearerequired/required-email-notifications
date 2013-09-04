<?php
/*
Plugin Name: required+ E-Mail Notifications
Plugin URI: http://www.required.ch/
Description: E-Mail Notifications queue and management.
Version: 1.0.0
Author: required gmbh
Author URI: http://www.required.ch
License: GPL
Copyright: required gmbh
*/

/*
 * autoloader for this plugin
 */
spl_autoload_register( function( $class ) {
    $class = explode('\\', $class);

    // ignore stuff outside Rplus namespace
    if ( $class[0] !== 'Rplus') {
        return;
    }

    // strip off namespace shizzle, files are not organised in subfolders
    $_class = array_pop( $class );
    $file = __DIR__ . '/lib/' .$_class . '.php';

    if (file_exists( $file )) {
        require_once( $file );
    } else {
        throw new Exception('Can\'t find file containing class '.$class.'. When you\'ve tried to add a adapter, its probably not existent.');
    }
} );

/**
 * Plugin activation and deactivation
 */
register_activation_hook( __FILE__, 'req_notifications_plugin_activate' );
register_deactivation_hook(__FILE__, 'req_notifications_plugin_deactivate' );
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

// /*
add_action( 'init', function() {
    // ----- TESTING -----------------------------
    try {

        /* add a new notification
        $test = req_notifications()->addNotification()

            // we just support the Mandrill adapter at this time, probably others will follow
            ->setAdapter( 'Mandrill' )
            ->setSubject( 'r+ notification plugin on Fire' )
            // the body has to be a string, could be html
            ->setBody( 'Versendet mit dem required-email-notifications Plugin (Mandrill Adapter). Funktioniert dat, oder wie? :-)' )
            // more recipients are possible
            ->addRecipient( 'stefan.pasch@gmail.com', 'Stefan Pasch' )
            ->addRecipient( 'silvan@required.ch', 'Silvan Hagen' )

            // the following is optional
            ->addCcRecipient( 'email@example.com', 'Example Mail' )
            // just one bcc recipient is possible
            ->setBcc( 'blind-carbon@copy.com' )
            // more than one attachment is possible, just call this method how often you like
            ->addAttachment( '/path/to/my/attachment.ext' )
            // this is the default state which will be set
            ->setState( \Rplus\Notifications\NotificationState::ISNEW )
            // schedule is optional, when set, mail will be sent at this time (or when the next cronjob runs, after this time)
            ->setSchedule( date('Y-m-d H:i:s', strtotime( '+1 day' )) )
            // this could be defined in plugin options and optional be overridden here
            ->setSender( 'sender@example.com', 'The sender' )

            // save this notification
            ->save();
        // */

        // update a notification
        // req_notifications()->getNotification( 279 )->setSubject( 'Ich bin eine tolle Notifikation' )->save();

        // process all notifications in queue, will be called by cron
        // req_notifications()->processQueue();
    } catch (Exception $e) {

        var_dump($e);

    }
});
// */