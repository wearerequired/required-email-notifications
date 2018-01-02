<?php

namespace Rplus\Notifications;

/**
 * Class NotificationState
 *
 * Contants all possible states of NotificationModel 's
 */
abstract class NotificationState {

	/**
	 * Notification is new, next should be processed
	 */
	const ISNEW = 10;

	/**
	 * Notification is processed, waiting for response
	 */
	const INPROGRESS = 20;

	/**
	 * Notification couldn't be processed, there where errors
	 */
	const ERROR = 30;

	/**
	 * Notification is completed successfull
	 */
	const COMPLETE = 40;

	/**
	 * Notification was aborted, no further processing
	 */
	const ABORT = 50;
}
