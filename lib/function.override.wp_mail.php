<?php
/**
 * wp_mail function with used Mandrill Adapter
 */

/**
 * Override default WordPress wp_mail method and send mails via the Mandrill Adapter.
 *
 * @param $to
 * @param $subject
 * @param $message
 * @param string $headers
 * @param array $attachments
 * @return bool|void
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
    try {

        // Compact the input, apply the filters, and extract them back out
        extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

        if ( !is_array($attachments) )
            $attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );

        $notification = req_notifications()->addNotification();
        $notification->setAdapter( 'Mandrill' )
            ->setSubject( $subject )
            ->setBody( $message )
            ->addRecipient( $to );

        // Add attachments, if exist
        if ( count( $attachments ) ) {
            foreach ( $attachments as $attachment ) {

                $notification->addAttachment( $attachment );

            }
        }

        // save and process this notification
        $notification->save();

        // when use queue is active, don't send the mail, just save it, will be processed via cronjob
        $use_queue = get_option( 'rplus_notifications_adapters_mandrill_override_use_queue' );
        if ( $use_queue != 1 ) {

            $notification->process();

        }

        // when the message couldn't be sent via mandrill, try the native WordPress method
        if ( $notification->getState() === \Rplus\Notifications\NotificationState::ERROR ) {

            return \Rplus\Notifications\NotificationController::wp_mail_native( $to, $subject, $message, $headers, $attachments );

        }

        return true;

        // when the message couldn't be sent via mandrill, try the native WordPress method
    } catch ( Exception $e ) {

        return \Rplus\Notifications\NotificationController::wp_mail_native( $to, $subject, $message, $headers, $attachments );

    }
}