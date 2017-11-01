<?php

namespace Rplus\Notifications;

/**
 * Class description
 *
 * @author Stefan Pasch <stefan@codeschmiede.de>
 * @Date: 16.08.13 16:15
 */
final class NotificationController {

    private static $instance = null;
    private static $conflict = false;

    /**
     * Singleton magizzle
     * @return null|RequiredEmailNotifications
     */
    public static function get_instance() {

        if ( null === self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;

    }

    /**
     * bind all needed filters & actions
     */
    private function __construct() {

        add_action( 'init', array( $this, 'init' ) );

        add_action( 'rplus_notification_cron_hook', array($this, 'cron') );

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $this, 'plugin_page_init' ) );
        }

        // check if wordpress mails should be sent via mandrill adapter
        $wp_mail_override = get_option( 'rplus_notifications_adapters_mandrill_override_wp_mail' );
        if ( !empty( $wp_mail_override ) && ( $wp_mail_override == 1 ) ) {

            if ( function_exists('wp_mail') ) {
                self::$conflict = true;
                add_action( 'admin_notices', array( __CLASS__, 'wpMailOverrideConflictNotice' ) );
                return;
            }

            if ( NotificationAdapterMandrill::isConfigured() ) {

                // cause we're namespacing everything, we need to include this function in a separate file
                // that's the only chance to make it visible in the global namespace
                require dirname( __FILE__ ) . '/function.override.wp_mail.php';

            }

        }

    }

    /**
     * Send mail with the WordPress native wp_mail method
     *
     * @param $to
     * @param $subject
     * @param $message
     * @param string $headers
     * @param array $attachments
     */
    public static function wp_mail_native( $to, $subject, $message, $headers = '', $attachments = array() ) {

        error_log( "\nrequired+ E-Mail Notifications -> wp_mail_native(): $to ($subject)\n" );

        require dirname( __FILE__ ) . '/legacy/function.wp_mail.php';

    }

    /**
     * Shows a notice
     *
     * wp_mail could not be overriden cause its already defined somewhere else
     */
    public static function wpMailOverrideConflictNotice() {
        ?>
        <div class="error">
            <p>
                <?php _e('required+ E-Mail Notifications: wp_mail has been declared by another process or plugin, so you won\'t be able to use the Mandrill Adapter for all WordPress Mails until the problem is solved.', 'rplusnotifications'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Plugin's Option page
     */
    public function add_plugin_page() {
        add_options_page( 'r+ Notification Options', 'r+ Notifications', 'manage_options', 'rplus-notifications', array( $this, 'create_admin_page' ) );
    }

    /**
     * Admin Options page html
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e('r+ Notifications', 'rplusnotifications'); ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rplus-notifications-email' );
                do_settings_sections( 'rplus-notifications' );
                ?>
                <?php submit_button(); ?>
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
        register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_apikey' );
        register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_override_wp_mail' );
        register_setting( 'rplus-notifications-email', 'rplus_notifications_adapters_mandrill_override_use_queue' );

        add_settings_section(
            'rplus_notifications_email',
            __('Settings', 'rplusnotifications'),
            function () {
                _e('Configure E-Mail Sender', 'rplusnotifications');
            },
            'rplus-notifications'
        );

        add_settings_section(
            'rplus_notifications_adapters',
            __('Adapters', 'rplusnotifications'),
            function () {
                _e('Configure Adapters', 'rplusnotifications');
            },
            'rplus-notifications'
        );

        add_settings_field(
            'rplus_notifications_sender_email',
            __('Sender E-Mail', 'rplusnotifications'),
            function () {
                ?><input type="text" class="regular-text" id="rplus_notifications_sender_email" name="rplus_notifications_sender_email" value="<?php echo get_option( 'rplus_notifications_sender_email' ); ?>" /><?php
            },
            'rplus-notifications',
            'rplus_notifications_email'
        );

        add_settings_field(
            'rplus_notifications_sender_name',
            __('Sender Name', 'rplusnotifications'),
            function() {
                ?><input type="text" class="regular-text" id="rplus_notifications_sender_name" name="rplus_notifications_sender_name" value="<?php echo get_option( 'rplus_notifications_sender_name' ); ?>" /><?php
            },
            'rplus-notifications',
            'rplus_notifications_email'
        );

        add_settings_field(
            'rplus_notifications_adapters_mandrill_apikey',
            __('Madrill API Key', 'rplusnotifications'),
            function() {
            	if ( defined( 'RPLUS_NOTIFICATIONS_ADAPTER_MANDRILL_API_KEY' ) ) {
            		_e( 'Mandrill API Key ist via wp-config.php gesetzt.', 'rplusnotifications' );
		            ?><input type="hidden" id="rplus_notifications_adapters_mandrill_apikey" name="rplus_notifications_adapters_mandrill_apikey" value="" /><?php
				} else {
		            ?><input type="text" class="regular-text" id="rplus_notifications_adapters_mandrill_apikey" name="rplus_notifications_adapters_mandrill_apikey" value="<?php echo get_option( 'rplus_notifications_adapters_mandrill_apikey' ); ?>" /><?php
				}
            },
            'rplus-notifications',
            'rplus_notifications_adapters'
        );

        add_settings_field(
            'rplus_notifications_adapters_mandrill_override_wp_mail',
            __('WordPress Mails', 'rplusnotifications'),
            function() {
                ?>
                <label for="rplus_notifications_adapters_mandrill_override_wp_mail">
                    <input type="hidden" name="rplus_notifications_adapters_mandrill_override_wp_mail" value="0">
                    <input name="rplus_notifications_adapters_mandrill_override_wp_mail" type="checkbox" id="rplus_notifications_adapters_mandrill_override_wp_mail" value="1" <?php checked( get_option( 'rplus_notifications_adapters_mandrill_override_wp_mail' ), '1' ); ?>>
                    <?php _e( 'Alle WordPress Mails über Mandrill verschicken', 'rplusnotifications'); ?>
                </label>
                <?php
            },
            'rplus-notifications',
            'rplus_notifications_adapters'
        );

        add_settings_field(
            'rplus_notifications_adapters_mandrill_override_use_queue',
            __('Delay sending (performance)', 'rplusnotifications'),
            function() {
                ?>
                <label for="rplus_notifications_adapters_mandrill_override_use_queue">
                    <input type="hidden" name="rplus_notifications_adapters_mandrill_override_use_queue" value="0">
                    <input name="rplus_notifications_adapters_mandrill_override_use_queue" type="checkbox" id="rplus_notifications_adapters_mandrill_override_use_queue" value="1" <?php checked( get_option( 'rplus_notifications_adapters_mandrill_override_use_queue' ), '1' ); ?>>
                    <?php _e( 'Die E-Mail nicht direkt verschicken, sondern in der Queue ablegen und per CronJob verschicken, dies verbesser die Performance.', 'rplusnotifications'); ?>
                </label>
            <?php
            },
            'rplus-notifications',
            'rplus_notifications_adapters'
        );
    }

    /**
     * make some initialisation
     */
    public function init() {

        // register the post type with wordpress
        NotificationModel::register();

    }

    /**
     * no cloning allowed, we just wan't to have only one instance of this class
     */
    private final function __clone() {}

    /**
     * Plugin activation
     */
    public static function _activate() {
        wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'rplus_notification_cron_hook' );

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
        $collection = new NotificationModelCollection( array(
            'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'meta_query' => array(
                // load all notifications with send_on is in the past
                array(
                    'key' => 'rplus_send_on',
                    'value' => date('Y-m-d H:i:s'),
                    'compare' => '<',
                    'type' => 'DATETIME'
                ),
                array(
                    'key' => 'rplus_state',
                    'value' => NotificationState::ISNEW,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                )
            )
        ) );

        while ($collection->valid()) {

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

}

