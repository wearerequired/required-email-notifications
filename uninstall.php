<?php

if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

delete_option( 'rplus_notifications_sender_email' );
delete_option( 'rplus_notifications_sender_name' );
delete_option( 'rplus_notifications_adapters_mandrill_apikey' );
delete_option( 'rplus_notifications_adapters_mandrill_override_wp_mail' );

wp_clear_scheduled_hook('rplus_notification_cron_hook');
