<?php

namespace Rplus\Notifications;

/**
 * Interface NotificationAdapter
 *
 * Each adapter has to implement this interface to ensure we have all needed methods available
 */
interface NotificationAdapter {

	/**
	 * Will execute the given object
	 *
	 * @param NotificationModel $model
	 * @return mixed
	 */
	public function execute( NotificationModel $model );

	/**
	 * Will update the given object
	 *
	 * @param NotificationModel $model
	 * @return mixed
	 */
	public function update( NotificationModel $model );

	/**
	 * Will escalate the given object
	 *
	 * @param NotificationModel $model
	 * @return mixed
	 */
	public function escalate( NotificationModel $model );

	/**
	 * Used to validate the existing data on the model
	 *
	 * @param NotificationModel $model
	 * @return mixed
	 */
	public function checkData( NotificationModel $model );

	/**
	 * Will set default values on the model, when empty
	 *
	 * @param NotificationModel $model
	 * @return mixed
	 */
	public function setDefaults( NotificationModel $model );

	/**
	 * Return error message
	 *
	 * @return string|null
	 */
	public function getErrorMessage();
}
