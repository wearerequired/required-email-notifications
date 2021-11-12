<?php

namespace Rplus\Notifications;

use WP_Query;

const RETENTION_OPTION             = 'rplus_notifications_retention';
const RETENTION_PERIOD_OPTION      = 'rplus_notifications_retention_period';
const RETENTION_PERIOD_UNIT_OPTION = 'rplus_notifications_retention_period_unit';
const DELETE_CRON_ACTION           = 'rplus_notifications_delete_cron';

/**
 * Class description
 *
 * @author Stefan Pasch <stefan@codeschmiede.de>
 * @Date   : 16.08.13 16:15
 */
final class NotificationController {

	/**
	 * Current instance.
	 *
	 * @var \Rplus\Notifications\NotificationController
	 */
	private static $instance = null;

	/**
	 * Return an instance of the NotificationController class, or create one if none exist yet.
	 *
	 * @return \Rplus\Notifications\NotificationController|null
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * bind all needed filters & actions
	 */
	private function __construct() {

		add_action( 'init', [ $this, 'init' ] );
		add_filter( 'cron_schedules', [ $this, 'extend_cron_schedules' ] );

		add_action( 'rplus_notification_cron_hook', [ $this, 'cron' ] );

		add_action( 'add_option_' . RETENTION_OPTION, [ $this, 'update_schedule_delete_emails' ], 10, 0 );
		add_action( 'update_option_' . RETENTION_OPTION, [ $this, 'update_schedule_delete_emails' ], 10, 0 );
		add_action( 'delete_option_' . RETENTION_OPTION, [ $this, 'update_schedule_delete_emails' ], 10, 0 );

		add_action( DELETE_CRON_ACTION, [ $this, 'process_retention_period_for_emails' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
			add_action( 'admin_init', [ $this, 'plugin_page_init' ] );
		}

		// Check if WordPress emails should be sent via Mandrill adapter.
		$wp_mail_override = (bool) get_option( 'rplus_notifications_adapters_mandrill_override_wp_mail' );
		if ( $wp_mail_override && NotificationAdapterMandrill::isConfigured() ) { // TODO: Support SendGrid adapter.
			add_filter( 'pre_wp_mail', [ $this, 'process_wp_mail' ], 5, 2 );
		}
	}

	/**
	 * Handler for wp_mail() to send emails via notification queue.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts {
	 *     Array of the `wp_mail()` arguments.
	 *
	 *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
	 *     @type string          $subject     Email subject.
	 *     @type string          $message     Message contents.
	 *     @type string|string[] $headers     Additional headers.
	 *     @type string|string[] $attachments Paths to files to attach.
	 * }
	 * @return bool Whether the email was sent successfully.
	 */
	public function process_wp_mail( $return, $atts ) {
		// Bail, if something else has used the filter.
		if ( null !== $return ) {
			return $return;
		}

		try {

			if ( isset( $atts['to'] ) ) {
				$to = $atts['to'];
			}

			if ( ! \is_array( $to ) ) {
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

			if ( ! \is_array( $attachments ) ) {
				$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
			}

			// Headers.
			$cc       = [];
			$bcc      = [];
			$reply_to = [];

			if ( empty( $headers ) ) {
				$headers = [];
			} else {
				if ( ! \is_array( $headers ) ) {
					// Explode the headers out, so this function can take
					// both string headers and an array of headers.
					$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
				} else {
					$tempheaders = $headers;
				}
				$headers = [];

				// If it's actually got contents.
				if ( ! empty( $tempheaders ) ) {
					// Iterate through the raw headers.
					foreach ( (array) $tempheaders as $header ) {
						if ( strpos( $header, ':' ) === false ) {
							/*if ( false !== stripos( $header, 'boundary=' ) ) {
								$parts    = preg_split( '/boundary=/i', trim( $header ) );
								$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
							}*/
							continue;
						}
						// Explode them out.
						list( $name, $content ) = explode( ':', trim( $header ), 2 );

						// Cleanup crew.
						$name    = trim( $name );
						$content = trim( $content );

						switch ( strtolower( $name ) ) {
							// Mainly for legacy -- process a "From:" header if it's there.
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
									list( $type, $charset_content ) = explode( ';', $content );
									$content_type                   = trim( $type );
									/*if ( false !== stripos( $charset_content, 'charset=' ) ) {
										$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
									} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
										$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
										$charset  = '';
									}*/

									// Avoid setting an empty $content_type.
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
							default:
								// Add it to our grand headers array.
								$headers[ trim( $name ) ] = trim( $content );
								break;
						}
					}
				}
			}

			// If we don't have a content-type from the input headers.
			if ( ! isset( $content_type ) ) {
				$content_type = 'text/plain';
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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

			if ( isset( $from_email ) ) {
				$notification->setSender( $from_email, $from_name ?? null );
			}

			// Add attachments, if exist.
			if ( ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment_name => $attachment_path ) {
					$attachment_name = \is_string( $attachment_name ) ? $attachment_name : '';

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
					return $this->wp_mail_native(
						$atts['to'],
						$atts['subject'],
						$atts['message'],
						$atts['headers'],
						$atts['attachments']
					);
				}

				return false;
			}

			return true;
		} catch ( \Exception $e ) {
			// When the message couldn't be sent, try the native WordPress method.
			$fallback_enabled = apply_filters( 'rplus_notifications.enable_fallback_to_native_wp_mail', true );
			if ( $fallback_enabled ) {
				return $this->wp_mail_native(
					$atts['to'],
					$atts['subject'],
					$atts['message'],
					$atts['headers'],
					$atts['attachments']
				);
			}

			return false;
		}
	}

	/**
	 * Extends the default cron schedules.
	 *
	 * @param array $schedules An array of non-default cron schedules. Default empty.
	 * @return array An array of non-default cron schedules.
	 */
	public function extend_cron_schedules( $schedules ) {
		$schedules['every_5_minutes'] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'rplusnotifications' ),
		];

		return $schedules;
	}

	/**
	 * Sends an email using the WordPress native wp_mail() function.
	 *
	 * @param string|string[] $to          Array or comma-separated list of email addresses to send message.
	 * @param string          $subject     Email subject.
	 * @param string          $message     Message contents.
	 * @param string|string[] $headers     Optional. Additional headers.
	 * @param string|string[] $attachments Optional. Paths to files to attach.
	 * @return bool Whether the email was sent successfully.
	 */
	private function wp_mail_native( $to, $subject, $message, $headers = '', $attachments = [] ) {
		if ( WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Email Notifications -> wp_mail_native(): $to ($subject)\n" );
		}

		// Remove the filter added by the override option.
		$has_filter = has_filter( 'pre_wp_mail', [ $this, 'process_wp_mail' ] );
		if ( $has_filter ) {
			remove_filter( 'pre_wp_mail', [ $this, 'process_wp_mail' ], 5 );
		}

		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( $has_filter ) {
			add_filter( 'pre_wp_mail', [ $this, 'process_wp_mail' ], 5, 2 );
		}

		return $sent;
	}

	/**
	 * Plugin's Option page
	 */
	public function add_plugin_page() {
		add_options_page(
			__( 'Email Notifications Settings', 'rplusnotifications' ),
			__( 'Email Notifications', 'rplusnotifications' ),
			'manage_options',
			'rplus-notifications',
			[ $this, 'create_admin_page' ]
		);
	}

	/**
	 * Admin Options page html
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<h1><?php echo get_admin_page_title(); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'rplus-notifications-email' );
				do_settings_sections( 'rplus-notifications' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Admin Options settings, sections and fields
	 */
	public function plugin_page_init() {
		register_setting( 'rplus-notifications-email', 'rplus_notifications_sender_email' );
		register_setting( 'rplus-notifications-email', 'rplus_notifications_sender_name' );
		register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_sendgrid_apikey' );
		register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_apikey' );
		register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_override_wp_mail' );
		register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_override_use_queue' );

		register_setting(
			'rplus-notifications-email',
			RETENTION_OPTION,
			[
				'type'              => 'string',
				'default'           => 'keep',
				'sanitize_callback' => null, // Added below due to missing second argument, see https://core.trac.wordpress.org/ticket/15335.
			]
		);
		add_filter( 'sanitize_option_' . RETENTION_OPTION, [ $this, 'sanitize_retention_option' ], 10, 2 );

		register_setting(
			'rplus-notifications-email',
			RETENTION_PERIOD_OPTION,
			[
				'type'              => 'integer',
				'default'           => 7,
				'sanitize_callback' => null, // Added below due to missing second argument, see https://core.trac.wordpress.org/ticket/15335.
			]
		);
		add_filter( 'sanitize_option_' . RETENTION_PERIOD_OPTION, [ $this, 'sanitize_retention_period_option' ], 10, 2 );

		register_setting(
			'rplus-notifications-email',
			RETENTION_PERIOD_UNIT_OPTION,
			[
				'type'              => 'string',
				'default'           => 'days',
				'sanitize_callback' => null, // Added below due to missing second argument, see https://core.trac.wordpress.org/ticket/15335.
			]
		);
		add_filter( 'sanitize_option_' . RETENTION_PERIOD_UNIT_OPTION, [ $this, 'sanitize_retention_period_unit_option' ], 10, 2 );

		add_settings_section(
			'rplus_notifications_settings',
			__( 'Settings', 'rplusnotifications' ),
			function() {
				_e( 'Configure Email Sender', 'rplusnotifications' );
			},
			'rplus-notifications'
		);

		add_settings_section(
			'rplus_notifications_adapters',
			__( 'Adapters', 'rplusnotifications' ),
			function() {
				_e( 'Configure Adapters', 'rplusnotifications' );
			},
			'rplus-notifications'
		);

		add_settings_field(
			'rplus_notifications_sender_email',
			__( 'Sender Email', 'rplusnotifications' ),
			function() {
				?>
				<input type="text" class="regular-text" id="rplus_notifications_sender_email"
					name="rplus_notifications_sender_email"
					value="<?php echo esc_attr( get_option( 'rplus_notifications_sender_email' ) ); ?>" />
				<?php
			},
			'rplus-notifications',
			'rplus_notifications_settings'
		);

		add_settings_field(
			'rplus_notifications_sender_name',
			__( 'Sender Name', 'rplusnotifications' ),
			function() {
				?>
				<input type="text" class="regular-text" id="rplus_notifications_sender_name"
					name="rplus_notifications_sender_name"
					value="<?php echo esc_attr( get_option( 'rplus_notifications_sender_name' ) ); ?>" />
				<?php
			},
			'rplus-notifications',
			'rplus_notifications_settings'
		);

		add_settings_field(
			'rplus_notifications_retention_policy',
			__( 'Retention Policy for Emails', 'rplusnotifications' ),
			function() {
				$retention_option             = get_option( RETENTION_OPTION );
				$retention_period_option      = get_option( RETENTION_PERIOD_OPTION );
				$retention_period_unit_option = get_option( RETENTION_PERIOD_UNIT_OPTION );
				?>
				<fieldset id="rplus-notifications-retention-policy">
					<legend class="screen-reader-text">
						<span><?php _e( 'Retention Policy for Emails', 'rplusnotifications' ); ?></span>
					</legend>

					<input
						type="radio"
						name="<?php echo esc_attr( RETENTION_OPTION ); ?>"
						id="<?php echo esc_attr( RETENTION_OPTION ); ?>-keep"
						value="keep"
						<?php checked( 'keep', $retention_option ); ?>
					>
					<label for="<?php echo esc_attr( RETENTION_OPTION ); ?>-keep"><?php _e( 'Keep data', 'rplusnotifications' ); ?></label>

					<br>

					<input
						type="radio"
						name="<?php echo esc_attr( RETENTION_OPTION ); ?>"
						id="<?php echo esc_attr( RETENTION_OPTION ); ?>-delete"
						value="delete"
						<?php checked( 'delete', $retention_option ); ?>
					/>
					<label for="<?php echo esc_attr( RETENTION_OPTION ); ?>-delete"><?php _e( 'Delete emails older than', 'rplusnotifications' ); ?></label>

					<input
						type="number"
						name="<?php echo esc_attr( RETENTION_PERIOD_OPTION ); ?>"
						id="<?php echo esc_attr( RETENTION_PERIOD_OPTION ); ?>"
						value="<?php echo esc_attr( $retention_period_option ); ?>"
						min="1"
						max="999"
					>
					<label for="<?php echo esc_attr( RETENTION_PERIOD_OPTION ); ?>" class="screen-reader-text"><?php _e( 'Time period', 'rplusnotifications' ); ?></label>

					<select id="<?php echo esc_attr( RETENTION_PERIOD_UNIT_OPTION ); ?>" name="<?php echo esc_attr( RETENTION_PERIOD_UNIT_OPTION ); ?>">
						<option value="days"<?php selected( 'days', $retention_period_unit_option ); ?>><?php _e( 'Days', 'rplusnotifications' ); ?></option>
						<option value="weeks"<?php selected( 'weeks', $retention_period_unit_option ); ?>><?php _e( 'Weeks', 'rplusnotifications' ); ?></option>
						<option value="months"<?php selected( 'months', $retention_period_unit_option ); ?>><?php _e( 'Months', 'rplusnotifications' ); ?></option>
					</select>
					<label for="<?php echo esc_attr( RETENTION_PERIOD_UNIT_OPTION ); ?>" class="screen-reader-text"><?php _e( 'Time unit', 'rplusnotifications' ); ?></label>

					<?php
					$next = wp_next_scheduled( DELETE_CRON_ACTION );
					if ( $next ) {
						$next_local = $next + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
						printf(
							/* translators: %s: date of next run */
							'<p class="description">' . __( 'Next cleanup run: %s', 'rplusnotifications' ) . '</p>',
							date_i18n( __( 'M j, Y @ H:i', 'rplusnotifications' ), $next_local )
						);
						?>
						<?php
					}
					?>
				</fieldset>
				<?php
			},
			'rplus-notifications',
			'rplus_notifications_settings'
		);

		add_settings_field(
			'rplus_notifications_adapters_sendgrid_apikey',
			__( 'SendGrid API Key', 'rplusnotifications' ),
			function() {
				if ( defined( 'RPLUS_NOTIFICATIONS_ADAPTER_SENDGRID_API_KEY' ) ) {
					?><input type="text" class="regular-text" readonly value="***************<?php echo substr( RPLUS_NOTIFICATIONS_ADAPTER_SENDGRID_API_KEY, -4 )?>">
					<p class="description"><?php esc_html_e( 'SendGrid API Key ist via wp-config.php definiert', 'rplusnotifications' ); ?></p>
					<input type="hidden" id="rplus_notifications_adapters_mandrill_apikey"
							 name="rplus_notifications_adapters_sendgrid_apikey" value=""/><?php
				} else {
					?><input type="text" class="regular-text" id="rplus_notifications_adapters_sendgrid_apikey"
							 name="rplus_notifications_adapters_sendgrid_apikey"
							 value="<?php echo esc_attr( get_option( 'rplus_notifications_adapters_sendgrid_apikey' ) ); ?>" /><?php
				}
			},
			'rplus-notifications',
			'rplus_notifications_adapters'
		);

		add_settings_field(
			'rplus_notifications_adapters_mandrill_apikey',
			__( 'Mandrill API Key', 'rplusnotifications' ),
			function() {
				if ( defined( 'RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY' ) ) {
					?><input type="text" class="regular-text" readonly value="***************<?php echo substr( RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY, -4 )?>">
					<p class="description"><?php esc_html_e( 'Mandrill API Key ist via wp-config.php definiert', 'rplusnotifications' ); ?></p>
					<input type="hidden" id="rplus_notifications_adapters_mandrill_apikey"
							 name="rplus_notifications_adapters_mandrill_apikey" value=""/><?php
				} else {
					?><input type="text" class="regular-text" id="rplus_notifications_adapters_mandrill_apikey"
							 name="rplus_notifications_adapters_mandrill_apikey"
							 value="<?php echo esc_attr( get_option( 'rplus_notifications_adapters_mandrill_apikey' ) ); ?>" /><?php
				}
			},
			'rplus-notifications',
			'rplus_notifications_adapters'
		);

		add_settings_field(
			'rplus_notifications_adapters_mandrill_override_wp_mail',
			__( 'WordPress Mails', 'rplusnotifications' ),
			function() {
				?>
				<label for="rplus_notifications_adapters_mandrill_override_wp_mail">
					<input type="hidden" name="rplus_notifications_adapters_mandrill_override_wp_mail" value="0">
					<input name="rplus_notifications_adapters_mandrill_override_wp_mail" type="checkbox"
						   id="rplus_notifications_adapters_mandrill_override_wp_mail"
						   value="1" <?php checked( get_option( 'rplus_notifications_adapters_mandrill_override_wp_mail' ), '1' ); ?>>
					<?php _e( 'Alle WordPress Mails über Mandrill verschicken', 'rplusnotifications' ); ?>
				</label>
				<?php
			},
			'rplus-notifications',
			'rplus_notifications_adapters'
		);

		add_settings_field(
			'rplus_notifications_adapters_mandrill_override_use_queue',
			__( 'Delay sending (performance)', 'rplusnotifications' ),
			function() {
				?>
				<label for="rplus_notifications_adapters_mandrill_override_use_queue">
					<input type="hidden" name="rplus_notifications_adapters_mandrill_override_use_queue" value="0">
					<input name="rplus_notifications_adapters_mandrill_override_use_queue" type="checkbox"
						   id="rplus_notifications_adapters_mandrill_override_use_queue"
						   value="1" <?php checked( get_option( 'rplus_notifications_adapters_mandrill_override_use_queue' ), '1' ); ?>>
					<?php _e( 'Die E-Mail nicht direkt verschicken, sondern in der Queue ablegen und per CronJob verschicken, dies verbesser die Performance.', 'rplusnotifications' ); ?>
				</label>
				<?php
			},
			'rplus-notifications',
			'rplus_notifications_adapters'
		);
	}

	/**
	 * Sanitizes retention option from user input.
	 *
	 * @param string $value The unsanitized option value.
	 * @param  string $option The option name.
	 * @return string The sanitized option value.
	 */
	public function sanitize_retention_option( $value, $option ) {
		$value = (string) $value;
		$value = trim( $value );

		if ( in_array( $value, [ 'keep', 'delete' ], true ) ) {
			return $value;
		}

		// Fallback to previous value.
		$value = get_option( $option );

		return $value;
	}

	/**
	 * Sanitizes retention period option from user input.
	 *
	 * @param string $value The unsanitized option value.
	 * @param  string $option The option name.
	 * @return int The sanitized option value.
	 */
	public function sanitize_retention_period_option( $value, $option ) {
		$value = (string) $value;
		$value = trim( $value );

		if ( is_numeric( $value ) ) {
			$value = (int) $value;
			if ( $value >= 1 && $value <= 999 ) {
				return $value;
			}
		}

		// Fallback to previous value.
		$value = get_option( $option );

		return $value;
	}

	/**
	 * Sanitizes retention period unit option from user input.
	 *
	 * @param string $value The unsanitized option value.
	 * @param  string $option The option name.
	 * @return int The sanitized option value.
	 */
	public function sanitize_retention_period_unit_option( $value, $option ) {
		$value = (string) $value;
		$value = trim( $value );

		if ( in_array( $value, [ 'days', 'weeks', 'months' ], true ) ) {
			return $value;
		}

		// Fallback to previous value.
		$value = get_option( $option );

		return $value;
	}

	/**
	 * make some initialisation
	 */
	public function init() {
		load_plugin_textdomain( 'rplusnotifications', false, dirname( \Rplus\Notifications\PLUGIN_BASENAME ) . '/languages' );

		NotificationModel::register();
	}

	/**
	 * no cloning allowed, we just wan't to have only one instance of this class
	 */
	private final function __clone() {
	}

	/**
	 * Plugin activation
	 */
	public static function _activate() {
		if ( ! wp_next_scheduled( 'rplus_notification_cron_hook' ) ) {
			wp_schedule_event( time(), 'every_5_minutes', 'rplus_notification_cron_hook' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public static function _deactivate() {
		wp_clear_scheduled_hook( 'rplus_notification_cron_hook' );
	}

	/**
	 * Called via cronjob
	 * - process queue
	 */
	public function cron() {
		$this->processQueue();
	}

	/**
	 * Will start processing all Notifications in state NEW
	 */
	public function processQueue() {
		// fetch all notifications in state new
		$collection = new NotificationModelCollection( [
			'post_status' => [ 'publish', 'pending', 'draft', 'future', 'private' ],
			'meta_query'  => [
				// load all notifications with send_on is in the past
				[
					'key'     => 'rplus_send_on',
					'value'   => date( 'Y-m-d H:i:s' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				],
				[
					'key'     => 'rplus_state',
					'value'   => NotificationState::ISNEW,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		while ( $collection->valid() ) {

			$notification = $collection->current();

			$notification->process();

			$collection->next();
		}
	}

	/**
	 * create a blank NotificationModel and return it for chaining reasons.
	 * example usage:
	 *  req_notifications()->addNotification()
	 *                     ->setSubject('My Subject')
	 *                     ->setBody('My Message')
	 *                     ->addRecipient('mail@example.com')
	 *                     ->save();
	 *
	 * @return NotificationModel
	 */
	public function addNotification() {
		$notification = new NotificationModel();

		return $notification;
	}

	/**
	 * Get a notification
	 * example usage:
	 *  $my_notification = req_notifications()->getNotification( 123 );
	 *
	 *  // update notification
	 *  $my_notification = req_notifications()->getNotification( 123 )
	 *                                        ->setState( NotificationState::INPROGRESS )
	 *                                        ->save();
	 *
	 * @param $notification_id
	 * @return NotificationModel
	 */
	public function getNotification( $notification_id ) {
		$notification = new NotificationModel( $notification_id );

		return $notification;
	}

	/**
	 * Calculates the retention period for emails.
	 *
	 * @return int Calculated retention period in seconds.
	 */
	private function get_retention_period_for_emails() {
		$retention_period      = get_option( RETENTION_PERIOD_OPTION );
		$retention_period_unit = get_option( RETENTION_PERIOD_UNIT_OPTION );

		switch ( $retention_period_unit ) {
			case 'days':
				$retention_period *= DAY_IN_SECONDS;
				break;

			case 'weeks':
				$retention_period *= WEEK_IN_SECONDS;
				break;

			case 'months':
				$retention_period *= MONTH_IN_SECONDS;
				break;

			default:
				$retention_period = 0;
				break;
		}

		return $retention_period;
	}

	/**
	 * Checks for the retention period for emails and deletes them
	 * if exceeded.
	 *
	 * Only deletes them in batches of 100 posts. In case of more the
	 * event gets rescheduled 10 seconds later.
	 */
	public function process_retention_period_for_emails() {
		$retention_option = get_option( RETENTION_OPTION );
		if ( 'delete' !== $retention_option ) {
			return;
		}

		$retention_period = $this->get_retention_period_for_emails();
		if ( ! $retention_period ) {
			return;
		}

		$query = new WP_Query();

		$batch_size = apply_filters( 'rplus_notifications.retention_period_delete_batch_size', 100 );
		$args       = [
			'post_type'              => NotificationModel::$post_type,
			'post_status'            => [ 'publish', 'pending', 'draft', 'future', 'private' ],
			'date_query'             => [
				'column' => 'post_date',
				'before' => $retention_period . ' seconds ago',
			],
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'posts_per_page'         => $batch_size,
			'fields'                 => 'ids',
			'orderby'                => 'none',
		];

		$post_ids    = $query->query( $args );
		$total_count = $query->found_posts;

		// Run the delete process again in 10 seconds to delete remaining posts.
		if ( $total_count > $batch_size ) {
			wp_unschedule_hook( DELETE_CRON_ACTION );
			wp_schedule_event( time() + 10, 'daily', DELETE_CRON_ACTION );
		}

		// Delete the posts.
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Schedules or unschedules the event for deleting emails.
	 */
	public function update_schedule_delete_emails() {
		$retention_option = get_option( RETENTION_OPTION );
		if ( 'delete' !== $retention_option ) {
			wp_unschedule_hook( DELETE_CRON_ACTION );
			return;
		}

		if ( ! wp_next_scheduled( DELETE_CRON_ACTION ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', DELETE_CRON_ACTION );
		}
	}
}
