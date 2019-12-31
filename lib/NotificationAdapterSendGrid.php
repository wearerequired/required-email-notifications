<?php

namespace Rplus\Notifications;

use Exception;
use SendGrid;

/**
 * Class NotificationAdapterSendGrid
 *
 * @package Rplus\Notifications
 */
class NotificationAdapterSendGrid implements NotificationAdapter {

	/**
	 * The SendGrid object
	 *
	 * @var \SendGrid|null
	 */
	private $sendgrid = null;

	/**
	 * do we have a valid api key?
	 *
	 * @var bool
	 */
	private $valid_api_key = false;

	/**
	 * possible error message
	 *
	 * @var null|string
	 */
	private $error = null;

	/**
	 * Get last error message
	 *
	 * @return null|string
	 */
	public function getErrorMessage() {
		return $this->error;
	}

	/**
	 * instantiate Mandrill object and check for valid API Key
	 */
	public function __construct() {
		try {

			// When api key is defined via constant, take that.
			if ( defined( 'RPLUS_NOTIFICATIONS_ADAPTER_SENDGRID_API_KEY' ) ) {
				$api_key = RPLUS_NOTIFICATIONS_ADAPTER_SENDGRID_API_KEY;
			} else {
				$api_key = get_option( 'rplus_notifications_adapters_sendgrid_apikey' );
			}

			$this->sendgrid = new SendGrid( $api_key );

			// when ping works, the API Key is valid.
			$this->valid_api_key = true;

		} catch ( Exception $e ) {
			$this->valid_api_key = false;
			$this->error         = get_class( $e ) . ' - ' . $e->getMessage();
		}
	}

	/**
	 * Execute current notification
	 *
	 * @param NotificationModel $model
	 * @return bool|mixed
	 */
	public function execute( NotificationModel $model ) {
		if ( false === $this->valid_api_key ) {
			$model->setState( NotificationState::ERROR );

			return false;
		}

		$email = new SendGrid\Mail\Mail();

		$email->setFrom( $model->getSenderEmail(), $model->getSenderName() );
		$email->setSubject( $model->getSubject() );
		$email->addContent( $model->getContentType(), $model->getBody() );
		$email->setReplyTo( $model->getSenderEmail(), $model->getSenderName() );

		// Add recipients.
		foreach ( $model->getRecipient() as $recipient ) {
			$email->addTo( $recipient[0], $recipient[1] );
		}

		foreach ( $model->getCcRecipient() as $recipient ) {
			$email->addCc( $recipient[0], $recipient[1] );
		}

		foreach ( $model->getBccRecipient() as $recipient ) {
			$email->addBcc( $recipient[0], $recipient[1] );
		}

		foreach ( $model->getReplyTo() as $recipient ) {
			$email->setReplyTo( $recipient[0], $recipient[1] );
		}

		// add all file attachments
		if ( false !== $model->getAttachments() ) {

			foreach ( $model->getAttachments() as $attachment_name => $attachment_path ) {
				$email->addAttachment(
					base64_encode( file_get_contents( $attachment_path ) ),
					$model->getFileMimeType( $attachment_path ),
					$attachment_name
				);
			}
		}

		try {
			$response = $this->sendgrid->send( $email );

			$response_data = [
				'status' => $response->statusCode(),
				'body'   => $response->body(),
			];

			update_post_meta( $model->getId(), 'rplus_sendgrid_response', $response_data );

			$model->setState( NotificationState::COMPLETE );

		} catch ( Exception $e ) {

			$model->setState( NotificationState::ERROR );
			$this->error = get_class( $e ) . ' - ' . $e->getMessage();

			return false;

		}
	}

	/**
	 * Currently, we don't need that.
	 *
	 * @param NotificationModel $model
	 * @return mixed|void
	 */
	public function update( NotificationModel $model ) {
	}

	/**
	 * Currently, we don't need that.
	 *
	 * @param NotificationModel $model
	 * @return mixed|void
	 */
	public function escalate( NotificationModel $model ) {
	}

	/**
	 * Validate the model data for using this adapter
	 *
	 * @param NotificationModel $model
	 * @return bool|mixed
	 */
	public function checkData( NotificationModel $model ) {
		if ( ! $model->getSubject() || ! $model->getBody() || ! $model->getRecipient() ) {
			return false;
		}

		return true;
	}

	/**
	 * Set some default model attributes
	 *
	 * @param NotificationModel $model
	 * @return mixed|void
	 */
	public function setDefaults( NotificationModel $model ) {
		$state = $model->getState();
		if ( empty( $state ) ) {
			$model->setState( NotificationState::ISNEW );
		}

		// set default send_on to NOW, means will be send right with the next cron schedule
		$send_on = $model->getSchedule();
		if ( empty( $send_on ) ) {
			$model->setSchedule( date( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * Check if all options are set to use this adapter
	 *
	 * @return bool
	 */
	public static function isConfigured() {
		if ( ! defined( 'RPLUS_NOTIFICATIONS_ADAPTER_SENDGRID_API_KEY' ) && ! get_option( 'rplus_notifications_adapters_sendgrid_apikey' ) ) {
			return false;
		}

		if ( ! get_option( 'rplus_notifications_sender_email' ) ) {
			return false;
		}

		return true;
	}
}
