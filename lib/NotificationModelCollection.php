<?php

namespace Rplus\Notifications;

use Iterator;
use WP_Query;

/**
 * Class NotificationModelCollection
 *
 * Loads and manages collections of NotificationModels
 *
 * @package Rplus\Notifications
 */
class NotificationModelCollection implements Iterator {

	private $collection = [];

	/**
	 * Load notifications by given options and save them in local property
	 *
	 * @param array $options
	 * @uses WP_Query
	 */
	public function __construct( array $options ) {

		// load wordpress posts with given defined options (via WP_Query())
		$args = wp_parse_args( $options, [
			'post_type'      => NotificationModel::$post_type,
			'posts_per_page' => - 1,
			'nopaging'       => true,
			'meta_query'     => [
				// load all notifications with send_on is in the past
				[
					'key'     => 'rplus_send_on',
					'value'   => date( 'Y-m-d H:i:s' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
			'fields'         => 'ids',
		] );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->get_posts() as $id ) {
				$this->collection[] = new NotificationModel( $id );
			}
		} else {
			// no notifications found
		}

		/* Restore original Post Data */
		wp_reset_postdata();
	}

	/**
	 * Reset collection pointer
	 */
	public function rewind() {
		reset( $this->collection );
	}

	/**
	 * get current collection item
	 *
	 * @return NotificationModel
	 */
	public function current() {
		return current( $this->collection );
	}

	/**
	 * Get current items key
	 *
	 * @return mixed
	 */
	public function key() {
		return key( $this->collection );
	}

	/**
	 * Get next collection item
	 *
	 * @return NotificationModel
	 */
	public function next() {
		return next( $this->collection );
	}

	/**
	 * Check's if we have more items in collection
	 *
	 * @return bool
	 */
	public function valid() {
		return ( $this->current() !== false );
	}
}
