<?php

namespace Rplus\Notifications;

/**
 * Class NotificationAdapterMandrill
 *
 * default adapter for handling Notifications
 */
class NotificationAdapterMandrill implements NotificationAdapter {

	/**
	 * The Mandrill object
	 *
	 * @var \MailchimpTransactional\ApiClient|null
	 */
	private $mandrill = null;

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
			if ( \defined( 'RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY' ) ) {
				$api_key = RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY;
			} else {
				$api_key = get_option( 'rplus_notifications_adapters_mandrill_apikey' );
			}

			$this->mandrill = new \MailchimpTransactional\ApiClient();
			$this->mandrill->setApiKey( $api_key );

			// check if we got a valid API Key
			/** @var \MailchimpTransactional\Api\UsersApi */
			$users_api = $this->mandrill->users;
			$users_api->ping();

			// when ping works, the API Key is valid.
			$this->valid_api_key = true;

		} catch ( \Exception $e ) {
			$this->valid_api_key = false;
			$this->error         = \get_class( $e ) . ' - ' . $e->getMessage();
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

		// produce api call data for the message
		$message = [
			'subject'    => $model->getSubject(),
			'from_email' => $model->getSenderEmail(),
			'from_name'  => $model->getSenderName(),
			'headers'    => [
				'Reply-To' => $model->getSenderEmail(),
			],
			'to'         => [],
		];

		if ( 'text/html' === $model->getContentType() ) {
			$message['html'] = $model->getBody();
		} else {
			$message['text'] = $model->getBody();
		}

		// Add recipients.
		foreach ( $model->getRecipient() as $recipient ) {
			$message['to'][] = [
				'email' => $recipient[0],
				'name'  => $recipient[1],
				'type'  => 'to',
			];
		}

		foreach ( $model->getCcRecipient() as $recipient ) {
			$message['to'][] = [
				'email' => $recipient[0],
				'name'  => $recipient[1],
				'type'  => 'cc',
			];
		}

		foreach ( $model->getBccRecipient() as $recipient ) {
			$message['to'][] = [
				'email' => $recipient[0],
				'name'  => $recipient[1],
				'type'  => 'bcc',
			];
		}

		foreach ( $model->getReplyTo() as $recipient ) {
			$message['headers']['Reply-To'] = $recipient[0];
		}

		// add all file attachments
		if ( false !== $model->getAttachments() ) {

			foreach ( $model->getAttachments() as $attachment_name => $attachment_path ) {
				$message['attachments'][] = [
					'type'    => $model->getFileMimeType( $attachment_path ),
					'name'    => $attachment_name,
					'content' => base64_encode( file_get_contents( $attachment_path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				];
			}
		}

		/** @var \MailchimpTransactional\Api\MessagesApi $messages_api */
		$messages_api = $this->mandrill->messages;
		$response     = $messages_api->send( [ 'message' => $message ] );

		if ( $response instanceof \GuzzleHttp\Exception\RequestException ) {
			if ( $response->hasResponse() ) {
				$response_content = $response->getResponse()->getBody()->getContents();
				$response_json    = json_decode( $response_content );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$message = $response_json->message;
				} else {
					$message = $response_content;
				}
			} else {
				$message = $response->getMessage();
			}

			$this->error = \get_class( $response ) . ' - ' . $message;
			$model->setState( NotificationState::ERROR );

			return false;
		}

		if ( ! \is_array( $response ) || ! isset( $response[0] ) ) {
			$this->error = \is_string( $response ) ? $response : 'Unknown response';
			$model->setState( NotificationState::ERROR );

			return false;
		}

		// We only send one message, so we only need the first element.
		$response = $response[0];

		if ( ! $response instanceof \stdClass || ! isset( $response->status ) ) {
			$this->error = \is_string( $response ) ? $response : 'Unknown response';
			$model->setState( NotificationState::ERROR );

			return false;
		}

		if ( 'invalid' === $response->status ) {
			$this->error = 'Sending status was invalid - ' . json_encode( $response );
			$model->setState( NotificationState::ERROR );

			return false;
		}

		if ( 'rejected' === $response->status ) {
			$this->error = 'Email rejected, reason: ' . $response->reject_reason;
			$model->setState( NotificationState::ERROR );

			return false;
		}

		// Status is "sent", "queued", or "scheduled".
		update_post_meta( $model->getId(), 'rplus_mandrill_response', $response );

		$model->setState( NotificationState::COMPLETE );

		return true;
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
		if ( ! \defined( 'RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY' ) && ! get_option( 'rplus_notifications_adapters_mandrill_apikey' ) ) {
			return false;
		}

		if ( ! get_option( 'rplus_notifications_sender_email' ) ) {
			return false;
		}

		return true;
	}
}
