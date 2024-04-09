<?php
/**
 * NotificationModelCollection class
 */

namespace Rplus\Notifications;

use Iterator;
use WP_Query;

/**
 * Loads and manages collections of NotificationModels
 */
class NotificationModelCollection implements Iterator {

	/**
	 * Collection of NotificationModel objects.
	 *
	 * @var array
	 */
	private $collection = [];

	/**
	 * Load notifications by given options and save them in local property
	 *
	 * @param array $options Array of options passed to WP_Query.
	 */
	public function __construct( array $options ) {
		$args = wp_parse_args(
			$options,
			[
				'post_type'      => NotificationModel::$post_type,
				'posts_per_page' => -1,
				'nopaging'       => true,
				'meta_query'     => [
					// Load all Notifications with send_on is in the past.
					[
						'key'     => 'rplus_send_on',
						'value'   => gmdate( 'Y-m-d H:i:s' ),
						'compare' => '<',
						'type'    => 'DATETIME',
					],
				],
				'fields'         => 'ids',
			]
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->get_posts() as $id ) {
				$this->collection[] = new NotificationModel( $id );
			}
		}
	}

	/**
	 * Rewind the Iterator to the first item.
	 */
	public function rewind(): void {
		reset( $this->collection );
	}

	/**
	 * Returns the current item.
	 *
	 * @return \Rplus\Notifications\NotificationModel
	 */
	public function current(): mixed {
		return current( $this->collection );
	}

	/**
	 * Get current items key.
	 *
	 * @return mixed
	 */
	public function key(): mixed {
		return key( $this->collection );
	}

	/**
	 * Move forward to next item.
	 */
	public function next(): void {
		next( $this->collection );
	}

	/**
	 * Check's if we have more items in collection.
	 *
	 * @return bool
	 */
	public function valid(): bool {
		return $this->current() !== false;
	}
}
