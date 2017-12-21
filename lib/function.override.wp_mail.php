<?php
/**
 * wp_mail function with used Mandrill Adapter
 */

/**
 * Override default WordPress wp_mail method and send mails via the Mandrill Adapter.
 *
 * @param string|array $to          Array or comma-separated list of email addresses to send message.
 * @param string       $subject     Email subject
 * @param string       $message     Message contents
 * @param string|array $headers     Optional. Additional headers.
 * @param string|array $attachments Optional. Files to attach.
 * @return bool Whether the email contents were sent successfully.
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
	$original_arguments = compact( 'to', 'subject', 'message', 'headers', 'attachments' );

	try {
		$atts = apply_filters( 'wp_mail', $original_arguments );

		if ( isset( $atts['to'] ) ) {
			$to = $atts['to'];
		}

		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}

		if ( isset( $atts['subject'] ) ) {
			$subject = $atts['subject'];
		}

		if ( isset( $atts['message'] ) ) {
			$message = $atts['message'];
		}

		if ( isset( $atts['headers'] ) ) {
			$headers = $atts['headers'];
		}

		if ( isset( $atts['attachments'] ) ) {
			$attachments = $atts['attachments'];
		}

		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
		}

		$notification = req_notifications()->addNotification();
		$notification
			->setAdapter( 'Mandrill' )
			->setSubject( $subject )
			->setBody( $message );

		foreach ( $to as $recipient ) {
			$notification->addRecipient( $recipient );
		}

		// Add attachments, if exist
		if ( count( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				$notification->addAttachment( $attachment );
			}
		}

		// save and process this notification
		$notification->save();

		// when use queue is active, don't send the mail, just save it, will be processed via cronjob
		$use_queue = (bool) get_option( 'rplus_notifications_adapters_mandrill_override_use_queue' );
		if ( ! $use_queue ) {
			$notification->process();
		}

		// When the message couldn't be sent via mandrill, try the native WordPress method.
		if ( $notification->getState() === \Rplus\Notifications\NotificationState::ERROR ) {
			return \Rplus\Notifications\NotificationController::wp_mail_native(
				$original_arguments['to'],
				$original_arguments['subject'],
				$original_arguments['message'],
				$original_arguments['headers'],
				$original_arguments['attachments']
			);
		}

		return true;

	} catch ( Exception $e ) {
		// When the message couldn't be sent via mandrill, try the native WordPress method.
		return \Rplus\Notifications\NotificationController::wp_mail_native(
			$original_arguments['to'],
			$original_arguments['subject'],
			$original_arguments['message'],
			$original_arguments['headers'],
			$original_arguments['attachments']
		);
	}
}
