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

		// Headers
		$cc         = array();
		$bcc        = array();
		$reply_to   = array();
		$from_email = null;
		$from_name  = null;

		if ( ! empty( $headers ) ) {
			if ( ! is_array( $headers ) ) {
				// Explode the headers out, so this function can take both
				// string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}

			// If it's actually got contents.
			if ( ! empty( $tempheaders ) ) {
				// Iterate through the raw headers.
				foreach ( (array) $tempheaders as $header ) {
					if ( false === strpos( $header, ':' ) ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( [ "'", '"' ], '', $parts[1] ) );
						}
						continue;
					}

					// Explode them out.
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew.
					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a From: header if it's there.
						case 'from':
							$bracket_pos = strpos( $content, '<' );
							if ( false !== $bracket_pos ) {
								// Text before the bracketed email is the "From" name.
								if ( $bracket_pos > 0 ) {
									$from_name = substr( $content, 0, $bracket_pos - 1 );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );
								}

								$from_email = substr( $content, $bracket_pos + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );

								// Avoid setting an empty $from_email.
							} elseif ( '' !== trim( $content ) ) {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list( $type ) = explode( ';', $content );
								$content_type = trim( $type );
							} elseif ( '' !== trim( $content ) ) {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						case 'reply-to':
							$reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );
							break;
					}
				}
			}
		}

		// If we don't have a content-type from the input headers
		if ( ! isset( $content_type ) ) {
			$content_type = 'text/plain';
		}

		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		$notification = req_notifications()->addNotification();
		$notification
			->setAdapter( apply_filters( 'rplus_notifications.wp_mail_default_adapter', 'Mandrill' ) )
			->setSubject( $subject )
			->setBody( $message )
			->setContentType( $content_type );

		foreach ( $reply_to as $recipient ) {
			$notification->addReplyTo( $recipient );
		}

		foreach ( $to as $recipient ) {
			$notification->addRecipient( $recipient );
		}

		foreach ( $cc as $recipient ) {
			$notification->addCcRecipient( $recipient );
		}

		foreach ( $bcc as $recipient ) {
			$notification->addBccRecipient( $recipient );
		}

		if ( null !== $from_email ) {
			$notification->setSender( $from_email, $from_name );
		}

		// Add attachments, if exist
		if ( count( $attachments ) ) {
			foreach ( $attachments as $attachment_name => $attachment_path ) {
				$attachment_name = is_string( $attachment_name ) ? $attachment_name : '';

				$notification->addAttachment( $attachment_path, $attachment_name );
			}
		}

		// Save and process this notification.
		$notification->save();

		// When use queue is active, don't send the mail, just save it, will be processed via cronjob.
		$use_queue = (bool) get_option( 'rplus_notifications_adapters_mandrill_override_use_queue' );
		if ( ! $use_queue ) {
			$notification->process();
		}

		if ( $notification->getState() === \Rplus\Notifications\NotificationState::ERROR ) {
			// When the message couldn't be sent, try the native WordPress method.
			$fallback_enabled = apply_filters( 'rplus_notifications.enable_fallback_to_native_wp_mail', true );
			if ( $fallback_enabled ) {
				return \Rplus\Notifications\NotificationController::wp_mail_native(
					$original_arguments['to'],
					$original_arguments['subject'],
					$original_arguments['message'],
					$original_arguments['headers'],
					$original_arguments['attachments']
				);
			}

			return false;
		}

		return true;
	} catch ( \Exception $e ) {
		// When the message couldn't be sent, try the native WordPress method.
		$fallback_enabled = apply_filters( 'rplus_notifications.enable_fallback_to_native_wp_mail', true );
		if ( $fallback_enabled ) {
			return \Rplus\Notifications\NotificationController::wp_mail_native(
				$original_arguments['to'],
				$original_arguments['subject'],
				$original_arguments['message'],
				$original_arguments['headers'],
				$original_arguments['attachments']
			);
		}

		return false;
	}
}
