<?php

namespace Rplus\Notifications;

use Exception;
use WP_Query;
use const Rplus\Notifications\PLUGIN_FILE;

class NotificationModel {

	/**
	 * The id of the notification, when null, its a new one
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * @var null|WP_Post
	 */
	private $post = null;

	/**
	 * Adapter class to handle execution of this notification
	 *
	 * @var NotificationAdapter
	 */
	private $adapter = null;

	/**
	 * @var string the subject (will be wordpress post title)
	 */
	private $subject = null;

	/**
	 * TO recipients for this notification.
	 *
	 * @var array
	 */
	private $recipient = [];

	/**
	 * CC recipients for this notification.
	 * @var array
	 */
	private $cc = [];

	/**
	 * BCC recipients for this notification.
	 *
	 * @var array
	 */
	private $bcc = [];

	/**
	 * Reply-To for this notification.
	 *
	 * @var array
	 */
	private $reply_to = [];

	/**
	 * @var string the message body
	 */
	private $body = null;

	/**
	 * @var string the content type
	 */
	private $content_type = 'text/html';

	/**
	 * @var array file attachments
	 */
	private $attachment = [];

	/**
	 * @var null the date the notification should be sent
	 */
	private $send_on = null;

	/**
	 * @var null the state of this notification
	 * @see NotificationState
	 */
	private $state = null;

	/**
	 * Sender name (configurable via Plugin Options)
	 *
	 * @var string
	 */
	private $sender_name = null;

	/**
	 * Sender Email (configurable via Plugin Options)
	 *
	 * @var string
	 */
	private $sender_email = null;

	/**
	 * Error message adapter was returning
	 *
	 * @var string|null
	 */
	private $error_message = null;

	/**
	 * The custom post type
	 *
	 * @var string
	 */
	public static $post_type = 'rplus_notification';

	/**
	 * instantiate a new or existing notification
	 *
	 * @param null|int $notification_id optional id of notification
	 */
	public function __construct( $notification_id = null ) {
		if ( null !== $notification_id ) {
			$this->id   = $notification_id;
			$this->post = get_post( $this->id );
			$this->loadDataFromPost();
		} else {
			// set default values (sender email & name)
			$this->sender_email = get_option( 'rplus_notifications_sender_email' );
			$this->sender_name  = get_option( 'rplus_notifications_sender_name' );
		}

	}

	/**
	 * Registers the custom post type with wordpress
	 */
	public static function register() {
		/**
		 * Custom Post Type Labels
		 *
		 * @var array
		 */
		$labels = apply_filters( 'rplus_notifications/filter/types/notification/labels', [
			'name'                  => _x( 'Notifications', 'post type general name', 'rplusnotifications' ),
			'singular_name'         => _x( 'Notification', 'post type singular name', 'rplusnotifications' ),
			'edit_item'             => __( 'View Notification', 'rplusnotifications' ),
			'view_item'             => __( 'View Notification', 'rplusnotifications' ),
			'search_items'          => __( 'Search Notifications', 'rplusnotifications' ),
			'not_found'             => __( 'No notifications found', 'rplusnotifications' ),
			'not_found_in_trash'    => __( 'No notifications found in Trash', 'rplusnotifications' ),
			'menu_name'             => __( 'Notifications', 'rplusnotifications' ),
			'all_items'             => __( 'All Notifications', 'rplusnotifications' ),
			'filter_items_list'     => __( 'Filter notifications list', 'rplusnotifications' ),
			'items_list_navigation' => __( 'Notifications list navigation', 'rplusnotifications' ),
			'items_list'            => __( 'Notifications list', 'rplusnotifications' ),
		] );

		/**
		 * Custom Post Type Args
		 *
		 * @var array
		 */
		$args = apply_filters( 'rplus_notifications/filter/types/notification/args', [
			'labels'               => $labels,
			'hierarchical'         => false,
			'supports'             => false,
			'public'               => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'menu_position'        => 6,
			'menu_icon'            => 'dashicons-email-alt',
			'show_in_nav_menus'    => false,
			'publicly_queryable'   => false,
			'exclude_from_search'  => false,
			'has_archive'          => false,
			'query_var'            => false,
			'can_export'           => false,
			'rewrite'              => false,
			'capabilities'         => [
				'create_posts'           => 'do_not_allow',
				'delete_others_posts'    => 'manage_options',
				'delete_post'            => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'edit_others_posts'      => 'manage_options',
				'edit_post'              => 'manage_options',
				'edit_posts'             => 'manage_options',
				'edit_private_posts'     => 'manage_options',
				'edit_published_posts'   => 'manage_options',
				'publish_posts'          => 'do_not_allow',
				'read'                   => 'manage_options',
				'read_post'              => 'manage_options',
				'read_private_posts'     => 'manage_options',
			],
		] );

		/**
		 * Register our Custom Post Type with WordPress
		 */
		register_post_type( self::$post_type, $args );

		/**
		 * Hide SearchWP meta box.
		 */
		add_filter( 'searchwp_related_excluded_post_types', function( $post_types ) {
			$post_types[] = self::$post_type;
			return $post_types;
		} );

		/**
		 * Modify wp-admin columns for NotificationModel
		 *
		 * @uses add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 )
		 */
		add_filter( 'manage_edit-' . self::$post_type . '_columns', function ( $columns ) {
			$columns = [
				'cb'              => '<input type="checkbox" />',
				'title'           => __( 'Subject', 'rplusnotifications' ),
				'rplus_recipient' => __( 'Recipient', 'rplusnotifications' ),
				'rplus_state'     => __( 'State', 'rplusnotifications' ),
				'date'            => __( 'Created', 'rplusnotifications' ),
				'rplus_send_on'   => __( 'Send on', 'rplusnotifications' )
				// 'rplus_sent_on'     => __( 'Sent on', 'rplusnotifications' )
			];

			return $columns;
		} );

		/**
		 * Modify sortable columns for NotificationModel
		 *
		 * @uses add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 )
		 */
		add_filter( 'manage_edit-' . self::$post_type . '_sortable_columns', function ( $columns ) {
			$columns['rplus_recipient'] = 'rplus_recipient';
			$columns['rplus_send_on']   = 'rplus_send_on';

			return $columns;
		} );

		/**
		 * Removes "Quick Edit" action and changes label of "Edit" action to "View".
		 *
		 * @param array   $actions An array of row action links.
		 * @param WP_Post $post    The post object.
		 * @return array An array of row action links.
		 */
		add_filter( 'post_row_actions', function( $actions, $post ) {
			if ( \Rplus\Notifications\NotificationModel::$post_type !== $post->post_type ) {
				return $actions;
			}

			// Remove "Quick Edit".
			unset( $actions['inline hide-if-no-js'] );

			// Rename "Edit" to "View".
			if ( isset( $actions['edit'] ) ) {
				$title = _draft_or_post_title( $post );

				/* translators: %s: post title */
				$aria_label = esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'rplusnotifications' ), $title ) );

				$actions['edit'] = preg_replace(
					'#aria-label="(.*)"#',
					'aria-label="' . $aria_label . '"',
					$actions['edit']
				);

				$actions['edit'] = preg_replace(
					'#>(.*)</a#',
					'>' . __( 'View', 'rplusnotifications' ) . '</a',
					$actions['edit']
				);
			}

			return $actions;
		}, 10, 2 );

		/**
		 * Modify posts query to allow sorting after custom meta values.
		 */
		add_action( 'pre_get_posts', function( $query ) {
			/** @var WP_Query $query */

			if ( ! $query->is_admin || ! $query->is_main_query() ) {
				return;
			}

			$screen = get_current_screen();

			if ( ! $screen || 'edit-' . self::$post_type !== $screen->id ) {
				return;
			}

			if ( 'rplus_recipient' === $query->get( 'orderby' ) ) {
				$query->set( 'meta_key', 'rplus_recipient' );
				$query->set( 'orderby', 'meta_value' );
			}

			if ( 'rplus_send_on' === $query->get( 'orderby' ) ) {
				$query->set( 'meta_key', 'rplus_send_on' );
				$query->set( 'orderby', 'meta_value' );
			}

			if ( '' !== $query->get( 's' ) ) {
				$search_query_where = null;

				add_filter( 'posts_search', function ( $search ) use ( &$search_query_where ) {
					remove_filter( current_filter(), __FUNCTION__ );

					$search_query_where = $search;

					return $search;
				} );

				// Use nested query to also search in post meta.
				add_filter( 'posts_where', function ( $where, $query ) use ( &$search_query_where ) {
					global $wpdb;

					$post_in_query = $wpdb->prepare(
						"( {$wpdb->posts}.ID IN ( SELECT {$wpdb->postmeta}.post_id FROM {$wpdb->postmeta} WHERE {$wpdb->postmeta}.meta_key = 'rplus_recipient' AND {$wpdb->postmeta}.meta_value LIKE %s ) )",
						'%' . $wpdb->esc_like( $query->get( 's' ) ) . '%'
					);

					// A backslash in the replacement parameter of preg_replace() must be doubled.
					$post_in_query_slashed = addcslashes( $post_in_query, '\\' );
					$search_query_where_or = preg_replace( '/^ AND \(/', ' AND (' . $post_in_query_slashed . ' OR ', $search_query_where, 1 );

					return str_replace( $search_query_where, $search_query_where_or, $where );
				}, 10, 2 );

				remove_filter( current_filter(), __FUNCTION__ );
			}
		} );

		/**
		 * Fill custom wp-admin columns for CompanyModel
		 *
		 * @uses add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 )
		 */
		add_action( 'manage_' . self::$post_type . '_posts_custom_column', function ( $column, $post_id ) {

			switch ( $column ) {
				case 'rplus_recipient':
					NotificationModel::outputRecipientsList( $post_id );
					break;

				case 'rplus_state':
					NotificationModel::outputNotificationState( $post_id );

					$last_executed = get_post_meta( $post_id, 'rplus_last_execution_time', true );
					if ( ! empty( $last_executed ) ) {
						echo '<br /><strong>' . __( 'Executed: ', 'rplusnotifications' ) . '</strong>';
						echo get_date_from_gmt( date( 'Y-m-d H:i:s', $last_executed ), 'd.m.Y H:i:s' );
					}

					break;

				case 'rplus_send_on':
					echo get_date_from_gmt( get_post_meta( $post_id, 'rplus_send_on', true ), 'd.m.Y H:i:s' );
					break;

				// Don't show anything by default
				default:
					break;
			}
		}, 10, 2 );

		add_action(
			'admin_enqueue_scripts',
			function() {
				$screen = get_current_screen();
				if ( ! isset( $screen->base, $screen->id ) || 'post' !== $screen->base || NotificationModel::$post_type !== $screen->id ) {
					return;
				}

				wp_enqueue_script(
					'rplus-notification-admin',
					plugins_url( '/assets/js/admin.js', PLUGIN_FILE ),
					[
						'wp-components',
						'wp-element',
					],
					'20200219',
					true
				);

				wp_enqueue_style(
					'rplus-notification-admin',
					plugins_url( '/assets/css/admin.css', PLUGIN_FILE ),
					[],
					'20200219'
				);
			}
		);

		/**
		 * Add Notification State meta box
		 */
		add_action( 'add_meta_boxes', function () {

			remove_meta_box( 'submitdiv', NotificationModel::$post_type, 'side' );
			remove_meta_box( 'slugdiv', NotificationModel::$post_type, 'normal' );

			add_meta_box(
				'rplus_notifications_info',
				__( 'Mail Status Info', 'rplusnotifications' ),
				function ( $post ) {

					echo '<strong>' . __( 'Recipients:', 'rplusnotifications' ) . '</strong>';
					NotificationModel::outputRecipientsList( $post->ID );

					echo '<p><strong>' . __( 'Status: ', 'rplusnotifications' ) . '</strong>';
					NotificationModel::outputNotificationState( $post->ID );
					echo '</p>';

					$last_executed = get_post_meta( $post->ID, 'rplus_last_execution_time', true );
					if ( ! empty( $last_executed ) ) {
						echo '<p><strong>' . __( 'Executed: ', 'rplusnotifications' ) . '</strong>' . get_date_from_gmt( date( 'Y-m-d H:i:s', $last_executed ), 'd.m.Y H:i:s' ) . '</p>';
					}

					echo '<p><strong>' . __( 'Used Adapter: ', 'rplusnotifications' ) . '</strong>' . get_post_meta( $post->ID, 'rplus_adapter', true ) . '</p>';

				},
				NotificationModel::$post_type,
				'side'
			);

			add_meta_box(
				'rplus_notifications_subject',
				__( 'Mail Subject', 'rplusnotifications' ),
				function ( $post ) {
					$notification = req_notifications()->getNotification( $post->ID );

					$subject_css = <<<'EOT'
					<style>
					body {
						font-size: 13px;
						line-height: 1.4em;
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					}
					</style>
					EOT;

					echo '<div id="rplus-notifications-subject" class="rplus-notifications-iframe-sandbox" data-content="' . esc_attr( $subject_css . $notification->getSubject() ) . '"></div>';
				},
				NotificationModel::$post_type,
				'normal'
			);

			add_meta_box(
				'rplus_notifications_content',
				__( 'Mail Content', 'rplusnotifications' ),
				function ( $post ) {
					$notification = req_notifications()->getNotification( $post->ID );

					echo '<div id="rplus-notifications-content" class="rplus-notifications-iframe-sandbox" data-content="' . esc_attr( $notification->getBody() ) . '"></div>';
				},
				NotificationModel::$post_type,
				'normal'
			);
		} );
	}

	/**
	 * output a ul li list of recipients
	 *
	 * @param $post_id
	 */
	public static function outputRecipientsList( $post_id ) {
		$recipients = get_post_meta( $post_id, 'rplus_recipient', true );
		if ( count( $recipients ) ) {
			echo '<ul style="margin: 0;">';
			foreach ( $recipients as $r ) {
				echo '<li>';
				echo $r[0] . ( isset( $r[1] ) ? ' (' . $r[1] . ')' : '' );
				echo '</li>';
			}
			echo '</ul>';
		}
	}

	/**
	 * Output the status of given notification
	 *
	 * @param $post_id
	 */
	public static function outputNotificationState( $post_id ) {
		switch ( get_post_meta( $post_id, 'rplus_state', true ) ) {
			case NotificationState::ISNEW:
				printf( '<strong>%s</strong> (%s)', __( 'New', 'rplusnotifications' ), __( 'to be processed', 'rplusnotifications' ) );
				break;
			case NotificationState::INPROGRESS:
				printf( '<strong>%s</strong> (%s)', __( 'In progress', 'rplusnotifications' ), __( 'still processing', 'rplusnotifications' ) );
				break;
			case NotificationState::COMPLETE:
				printf( '<strong>%s</strong>', __( 'Completed', 'rplusnotifications' ) );
				break;
			case NotificationState::ERROR:
				printf( '<strong>%s</strong> (%s)', __( 'Error', 'rplusnotifications' ), get_post_meta( $post_id, 'rplus_error_message', true ) );
				break;
		}

	}

	/**
	 * load all existing data out of wordpress post and save it to local properties
	 */
	private function loadDataFromPost() {

		// check if we have a id and post object
		if ( ! $this->id || ! $this->post ) {
			return false;
		}

		$this->setAdapter( get_post_meta( $this->id, 'rplus_adapter', true ) );
		// Check for deprecated post meta.
		$body = get_post_meta( $this->id, 'rplus_mail_body', true );
		if ( ! $body ) {
			$body = $this->post->post_content;
		}

		$this->setBody( $body );
		$this->setContentType( get_post_meta( $this->id, 'rplus_mail_content_type', true ) );
		$this->setState( get_post_meta( $this->id, 'rplus_state', true ) );
		$this->setSubject( $this->post->post_title );
		$this->setSchedule( get_post_meta( $this->id, 'rplus_send_on', true ) );
		$this->setErrorMessage( get_post_meta( $this->id, 'rplus_error_message', true ) );

		$this->sender_name  = get_post_meta( $this->id, 'rplus_sender_name', true );
		$this->sender_email = get_post_meta( $this->id, 'rplus_sender_email', true );
		$this->recipient    = get_post_meta( $this->id, 'rplus_recipient', true );
		$this->cc           = get_post_meta( $this->id, 'rplus_recipient_cc', true );
		$this->bcc          = get_post_meta( $this->id, 'rplus_recipient_bcc', true );
		$this->attachment   = get_post_meta( $this->id, 'rplus_attachment', true );
	}

	/**
	 * Set adapter class for notification
	 *
	 * @param $adapter
	 * @return NotificationModel allows chaining
	 * @throws \Exception
	 */
	public function setAdapter( $adapter ) {
		$adapter_class = '\\Rplus\\Notifications\\NotificationAdapter' . $adapter;

		if ( ! class_exists( $adapter_class ) ) {
			throw new Exception( 'Adapter class "' . $adapter . '" does not exist!' );
		}

		$this->adapter = $adapter;

		return $this;
	}

	/**
	 * Get the notification id
	 *
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get adapter class name
	 *
	 * @return NotificationAdapter
	 */
	public function getAdapter() {
		return $this->adapter;
	}

	/**
	 * Set the senders email and optional name (will overwrite the values from plugin config)
	 *
	 * @param string      $email
	 * @param null|string $name
	 */
	public function setSender( $email, $name = null ) {
		$this->sender_email = $email;
		if ( null !== $name ) {
			$this->sender_name = $name;
		}
	}

	/**
	 * get senders email
	 *
	 * @return mixed|null|string|void
	 */
	public function getSenderEmail() {
		return $this->sender_email;
	}

	/**
	 * get senders name
	 *
	 * @return mixed|null|string|void
	 */
	public function getSenderName() {
		return $this->sender_name;
	}

	/**
	 * @param string $subject
	 * @return NotificationModel allows chaining
	 */
	public function setSubject( $subject ) {
		$this->subject = $subject;

		return $this;
	}

	/**
	 * Add a notification recipient.
	 *
	 * @param string      $email the email address
	 * @param string|null $name  optional name
	 *
	 * @return NotificationModel allows chaining
	 */
	public function addRecipient( $email, $name = null ) {
		$this->recipient[] = [ $email, $name ];

		return $this;
	}

	/**
	 * Add a notification CC recipient.
	 *
	 * @param string      $email the email address
	 * @param string|null $name  optional name
	 *
	 * @return NotificationModel allows chaining
	 */
	public function addCcRecipient( $email, $name = null ) {
		$this->cc[] = [ $email, $name ];

		return $this;
	}

	/**
	 * Add a notification BCC recipient.
	 *
	 * @param string      $email the email address
	 * @param string|null $name  optional name
	 *
	 * @return NotificationModel allows chaining
	 */
	public function addBccRecipient( $email, $name = null ) {
		$this->bcc[] = [ $email, $name ];

		return $this;
	}

	/**
	 * Add a notification Reply-to.
	 *
	 * @param string      $email the email address
	 * @param string|null $name  optional name
	 *
	 * @return NotificationModel allows chaining
	 */
	public function addReplyTo( $email, $name = null ) {
		$this->reply_to[] = [ $email, $name ];

		return $this;
	}

	/**
	 * Set the notification BCC recipient, just one email address possible here
	 *
	 * @deprecated Use addBccRecipient().
	 *
	 * @param string $email the email address
	 *
	 * @return NotificationModel allows chaining
	 */
	public function setBcc( $email ) {
		$this->bcc = [ $email, null ];

		return $this;
	}

	/**
	 * get notifications bcc recipient email
	 *
	 * @deprecated Use getBccRecipient().
	 *
	 * @return string|null
	 */
	public function getBcc() {
		if ( ! $this->bcc ) {
			return null;
		}

		$bcc = reset( $this->bcc );
		return $bcc[0];
	}

	/**
	 * Set a notification message body
	 *
	 * @param string $body The message body.
	 * @return NotificationModel
	 */
	public function setBody( $body ) {
		$this->body = $body;

		return $this;
	}

	/**
	 * Set a notification message content type.
	 *
	 * @param string $content_type The message content type.
	 * @return NotificationModel
	 */
	public function setContentType( $content_type ) {
		$this->content_type = $content_type;

		return $this;
	}

	/**
	 * Add a notification attachment
	 *
	 * @param string $path Path to the attachment.
	 * @param string $name Optional. Attachment name.
	 * @throws \Exception
	 * @return NotificationModel
	 */
	public function addAttachment( $path, $name = '' ) {

		// file does not exist
		if ( ! is_file( $path ) ) {
			throw new Exception( 'There is no such file (' . $path . ').' );
		}

		// File is not readable
		if ( ! is_readable( $path ) ) {
			throw new Exception( 'File is not readable (' . $path . ').' );
		}

		if ( ! $name ) {
			$name = basename( $path );
		}

		$this->attachment[ $name ] = $path;

		return $this;
	}

	/**
	 * Get the attachments, or false if no attachments are set
	 *
	 * @return array|bool
	 */
	public function getAttachments() {
		if ( ! count( $this->attachment ) ) {
			return false;
		}

		return $this->attachment;
	}

	/**
	 * Set the notification state
	 *
	 * @param $state
	 * @return NotificationModel
	 */
	public function setState( $state ) {
		$this->state = $state;

		return $this;
	}

	/**
	 * Get the notification state
	 *
	 * @return int
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * Set the notification send date
	 *
	 * @param $date
	 * @return NotificationModel
	 */
	public function setSchedule( $date ) {
		$this->send_on = $date;

		return $this;
	}

	public function __get( $key ) {
		if ( ! isset( $this->$key ) ) {
			return false;
		}

		return $this->$key;
	}

	/**
	 * Get the notification subject
	 *
	 * @return null|string
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 * Get the notification body
	 *
	 * @return null|string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * Get the notification content type.
	 *
	 * @return string
	 */
	public function getContentType() {
		return $this->content_type;
	}

	/**
	 * Get the notification send date
	 *
	 * @return string
	 */
	public function getSchedule() {
		return $this->send_on;
	}

	/**
	 * Get the notification recipient(s).
	 *
	 * @return array
	 */
	public function getRecipient() {
		return $this->recipient;
	}

	/**
	 * Get the notification CC recipient(s).
	 *
	 * @return array
	 */
	public function getCcRecipient() {
		return $this->cc;
	}

	/**
	 * Get the notification BCC recipient(s).
	 *
	 * @return array
	 */
	public function getBccRecipient() {
		return $this->bcc;
	}
	/**
	 * Get the notification reply-to.
	 *
	 * @return array
	 */
	public function getReplyTo() {
		return $this->reply_to;
	}

	/**
	 * Set the notifications error message, probably returned by adapter
	 *
	 * @param $msg
	 * @return $this
	 */
	public function setErrorMessage( $msg ) {
		$this->error_message = $msg;

		return $this;
	}

	/**
	 * process current object
	 *
	 * should check the current state and handle it
	 * NEW => INPROGRESS
	 * INPROGRESS (check if we need to escalate) => COMPLETED / ABORTED / ERROR
	 */
	public function process() {

		// load the adapter
		$adapter = $this->loadAdapter();

		// notification is NEW
		if ( (int) $this->state === NotificationState::ISNEW ) {

			$adapter->execute( $this );

			update_post_meta( $this->id, 'rplus_last_execution_time', time() );

			// check the new state of this notification (was probably updated in adapter execute())
			if ( $this->state === NotificationState::ERROR ) {

				$this->setErrorMessage( $adapter->getErrorMessage() );
				$this->save();

			} else {

				$this->save();

			}
		}

		// when needed, implement the update and escalate stuff here

	}

	/**
	 * Get object of current adapter class
	 *
	 * @return NotificationAdapter
	 * @throws \Exception
	 */
	private function loadAdapter() {

		// exit here when no adapter is set!
		if ( empty( $this->adapter ) ) {
			throw new Exception( 'No adapter class was set, but we need one to process this notification!' );
		}

		$adapter_class = '\\Rplus\\Notifications\\NotificationAdapter' . $this->adapter;

		return new $adapter_class;
	}

	/**
	 * Save it as a WP_Post
	 */
	public function save() {

		$adapter = $this->loadAdapter();

		// make some checks for data, we need at least a subject, body and recipient
		if ( false === $adapter->checkData( $this ) ) {
			throw new Exception( 'Not all mandatory fields are set, notification can\'t be saved.' );
		}

		// set defaults (state, send_on etc.) when not defined
		$adapter->setDefaults( $this );

		$is_new = null === $this->id;

		// Create a new post.
		if ( $is_new ) {
			$this->id = wp_insert_post(
				[
					'post_status' => 'draft',
					'post_date'   => gmdate( 'Y-m-d H:i:s' ),
					'post_type'   => self::$post_type,
				]
			);

			if ( ! $this->id ) {
				return false;
			}
		}

		// Update title and body

		// Prevent content filters from corrupting HTML in post_content.
		$has_kses = ( false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		if ( $has_kses ) {
			kses_remove_filters();
		}
		$has_targeted_link_rel_filters = ( false !== has_filter( 'content_save_pre', 'wp_targeted_link_rel' ) );
		if ( $has_targeted_link_rel_filters ) {
			wp_remove_targeted_link_rel_filters();
		}

		wp_update_post(
			[
				'ID'           => $this->id,
				'post_title'   => $this->getSubject(),
				'post_content' => $this->getBody(),
			]
		);

		// Restore removed content filters.
		if ( $has_kses ) {
			kses_init_filters();
		}
		if ( $has_targeted_link_rel_filters ) {
			wp_init_targeted_link_rel_filters();
		}

		// set all other properties
		update_post_meta( $this->id, 'rplus_mail_content_type', $this->content_type );
		update_post_meta( $this->id, 'rplus_adapter', $this->adapter );
		update_post_meta( $this->id, 'rplus_sender_email', $this->sender_email );
		update_post_meta( $this->id, 'rplus_sender_name', $this->sender_name );
		update_post_meta( $this->id, 'rplus_recipient', $this->recipient );
		update_post_meta( $this->id, 'rplus_recipient_cc', $this->cc );
		update_post_meta( $this->id, 'rplus_recipient_bcc', $this->bcc );
		update_post_meta( $this->id, 'rplus_reply_to', $this->reply_to );
		update_post_meta( $this->id, 'rplus_attachment', $this->attachment );
		update_post_meta( $this->id, 'rplus_send_on', $this->send_on );
		update_post_meta( $this->id, 'rplus_state', $this->state );
		update_post_meta( $this->id, 'rplus_error_message', $this->error_message );

		// Remove deprecated post meta for older notifications.
		if ( ! $is_new ) {
			delete_post_meta( $this->id, 'rplus_mail_body' );
		}

		return true;
	}

	/**
	 * get the mime type of the specified file
	 *
	 * @param $file
	 * @return bool|mixed|string
	 */
	public function getFileMimeType( $file ) {

		// use the Finfo file extension in php, if exists
		if ( function_exists( 'finfo_file' ) ) {

			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $file );
			finfo_close( $finfo );

			return $mime;

			// use depricated php method to get mime
		} elseif ( function_exists( 'mime_content_type' ) ) {

			return mime_content_type( $file );

			// try shell command
		} elseif ( ! stristr( ini_get( 'disable_functions' ), 'shell_exec' ) ) {

			// http://stackoverflow.com/a/134930/1593459
			$file = escapeshellarg( $file );
			$mime = shell_exec( 'file -bi ' . $file );

			return $mime;

			// don't know the mime type :-(
		} else {
			return false;
		}
	}
}
