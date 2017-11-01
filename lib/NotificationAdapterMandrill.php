<?php

namespace Rplus\Notifications;

require_once 'adapters/Mandrill/Mandrill.php';
/**
 * Class NotificationAdapterMandrill
 *
 * default adapter for handling Notifications
 *
 * @package Rplus\Notifications
 */
class NotificationAdapterMandrill implements NotificationAdapter {

    /**
     * The Mandrill object
     *
     * @var \Mandrill|null
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
			$api_key = \get_option( 'rplus_notifications_adapters_mandrill_apikey' );
			if ( defined( 'RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY' ) ) {
				$api_key = RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY;
			}

            $this->mandrill = new \Mandrill( $api_key );

            // check if we got a valid API Key
            $this->mandrill->users->ping();

            // when ping works, the API Key is valid.
            $this->valid_api_key = true;

        } catch (\Exception $e) {

            $this->valid_api_key = false;
            $this->error = get_class($e) . ' - ' . $e->getMessage();

        }
    }

    /**
     * Execute current notification
     *
     * @param NotificationModel $model
     * @return bool|mixed
     */
    public function execute(NotificationModel $model) {

        if ( false === $this->valid_api_key ) {

            $model->setState( NotificationState::ERROR );
            return false;
        }

        // produce api call data for the message
        $message = array(
            'html' => $model->getBody(),
            'subject' => $model->getSubject(),
            'from_email' => $model->getSenderEmail(),
            'from_name' => $model->getSenderName(),
            'headers' => array('Reply-To' => $model->getSenderEmail()),
            'to' => array()
        );

        // add recipients (could be more than one)
        foreach ($model->getRecipient() as $r) {

            $message['to'][] = array(
                'email' => $r[0],
                'name' => $r[1]
            );

        }

        // blind carbon copy, for the secret agents
        if ( $model->getBcc() ) {
            $message['bcc_address'] = $model->getBcc();
        }

        // add all file attachments
        if ( false !== $model->getAttachments() ) {

            foreach ($model->getAttachments() as $attachment) {

                // get original filename
                $filename = pathinfo( $attachment );

                $message['attachments'][] = array(
                    'type' => $model->getFileMimeType( $attachment ),
                    'name' => $filename['basename'],
                    'content' => base64_encode( file_get_contents( $attachment ) )
                );
            }

        }

        try {

            $response = $this->mandrill->messages->send( $message );

            // update post with mandrill message id
            \update_post_meta( $model->getId(), 'rplus_mandrill_response', $response );

            $model->setState( NotificationState::COMPLETE );

        } catch (\Mandrill_Error $e) {

            $model->setState( NotificationState::ERROR );
            $this->error = get_class($e) . ' - ' . $e->getMessage();
            return false;

        }

    }

    /**
     * Currently, we don't need that.
     *
     * @param NotificationModel $model
     * @return mixed|void
     */
    public function update(NotificationModel $model) {

    }

    /**
     * Currently, we don't need that.
     *
     * @param NotificationModel $model
     * @return mixed|void
     */
    public function escalate(NotificationModel $model) {

    }

    /**
     * Validate the model data for using this adapter
     *
     * @param NotificationModel $model
     * @return bool|mixed
     */
    public function checkData(NotificationModel $model) {

        if ( !$model->getSubject() || !$model->getBody() || !$model->getRecipient() ) {
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
    public function setDefaults(NotificationModel $model) {
        $state = $model->getState();
        if ( empty($state) ) {
            $model->setState( NotificationState::ISNEW );
        }

        // set default send_on to NOW, means will be send right with the next cron schedule
        $send_on = $model->getSchedule();
        if ( empty($send_on) ) {
            $model->setSchedule( date('Y-m-d H:i:s') );
        }
    }

    /**
     * Check if all options are set to use this adapter
     *
     * @return bool
     */
    public static function isConfigured() {

        if ( ! \get_option( 'rplus_notifications_adapters_mandrill_apikey' ) ) {
            return false;
        }

        if ( ! \get_option( 'rplus_notifications_sender_email' ) ) {
            return false;
        }

        return true;
    }

}