<?php

namespace Press_Sync;

use WPackio\Enqueue;

/**
 * The Dashboard.
 *
 * @since 0.1.0
 */
class Dashboard {

	/**
	 * Parent plugin class.
	 *
	 * @var   Press_Sync
	 * @since 0.1.0
	 */
	protected $plugin = null;

	/**
	 * Objects to sync from the request.
	 *
	 * @var string
	 * @since 0.7.0
	 */
	private $objects_to_sync;

	/**
	 * Next page in request.
	 *
	 * @var int
	 * @since 0.7.0
	 */
	private $next_page;

	const ADVANCED_OPTIONS = [
		'ps_options',
		'ps_request_buffer_time',
		'ps_start_object_offset',
		'ps_only_sync_missing',
		'ps_testing_post',
		'ps_skip_assets',
		'ps_preserve_ids',
		'ps_fix_terms',
		'ps_delta_date',
		'ps_content_threshold',
		'ps_partial_terms',
		'ps_page_size',
	];

	/**
	 * @var Enqueue
	 */
	private $enqueue;

	/**
	 * The Constructor.
	 *
	 * @param Press_Sync $plugin Main plugin object.
	 *
	 * @since 0.1.0
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->enqueue = new Enqueue(
			'pressSync',
			'dist',
			'0.9.2',
			'plugin',
			$this->plugin->get_plugin_path(), // Plugin location, pass false in case of theme.
		);

		$this->hooks();
		$this->init();
	}

	/**
	 * Add our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {

		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_notices', array( $this, 'error_notice' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX Requests.
		add_action( 'wp_ajax_get_objects_to_sync_count', array( $this, 'get_objects_to_sync_count_via_ajax' ) );
		add_action( 'wp_ajax_sync_wp_data', array( $this, 'sync_wp_data_via_ajax' ) );
		add_action( 'wp_ajax_get_order_to_sync_all', array( $this, 'get_order_to_sync_all_via_ajax' ) );
	}

	/**
	 * Initialize the current request.
	 *
	 * @since 0.7.0
	 */
	public function init() {
		$objects_to_sync = get_option( 'ps_objects_to_sync' );
		$next_page       = 1;

		if ( isset( $_REQUEST['objects_to_sync'] ) ) {
			$this->objects_to_sync = filter_var( $_REQUEST['objects_to_sync'], FILTER_SANITIZE_STRING );
		}

		if ( isset( $_REQUEST['paged'] ) ) {
			$this->next_page = filter_var( $_REQUEST['paged'], FILTER_VALIDATE_INT );
		}
	}

	/**
	 * Initialize the Press Sync menu page.
	 *
	 * @since 0.1.0
	 */
	public function add_menu_page() {
		add_management_page(
			__( 'Press Sync', 'press-sync' ),
			__( 'Press Sync &trade;', 'press-sync' ),
			'manage_options',
			'press-sync',
			array( $this, 'show_menu_page' )
		);
	}

	/**
	 * Display the menu page in the 'Tools' section.
	 *
	 * @since 0.1.0
	 */
	public function show_menu_page() {

		$selected_tab = isset( $_REQUEST['tab'] ) ? 'dashboard/' . $_REQUEST['tab'] : 'dashboard/dashboard';
		$this->plugin->include_page( $selected_tab );
	}

	/**
	 * Load all of the scripts for the dashboard.
	 *
	 * @since 0.1.0
	 */
	public function load_scripts() {
		$this->enqueue->enqueue( 'press-sync', 'main', [
			'css' => false,
			'media' => 'all',
		] );

		wp_localize_script( 'press-sync', 'press_sync', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Registers the Press Sync settings via WP.
	 *
	 * @since 0.3.0
	 */
	public function register_settings() {

		// Post Sync.
		register_setting( 'press-sync', 'ps_allowed_post_types' );

		register_setting( 'press-sync-bulk-sync', 'ps_sync_method' );
		register_setting( 'press-sync-bulk-sync', 'ps_objects_to_sync' );
		register_setting( 'press-sync-bulk-sync', 'ps_options_to_sync' );
		register_setting( 'press-sync-bulk-sync', 'ps_duplicate_action' );
		register_setting( 'press-sync-bulk-sync', 'ps_force_update' );
		register_setting( 'press-sync-bulk-sync', 'ps_ignore_comments' );

		// Credentials page.
		register_setting( 'press-sync', 'ps_key' );
		register_setting( 'press-sync', 'ps_remote_domain' );
		register_setting( 'press-sync', 'ps_remote_query_args' );
		register_setting( 'press-sync', 'ps_remote_key' );

		// Advanced page.
		foreach ( self::ADVANCED_OPTIONS as $option ) {
			register_setting( 'press-sync-advanced', $option );
		}
	}

	/**
	 * Displays an error notice if the local press sync key is not set.
	 *
	 * @since 0.1.0
	 */
	public function error_notice() {

		$press_sync_key = get_option( 'ps_key' );

		if ( $press_sync_key ) {
			return;
		}

		?>
        <div class="update-nag notice is-dismissible">
            <p><?php
				_e(
					sprintf(
						'<strong>PressSync:</strong> You must define your PressSync key before you can receive updates from another WordPress site. <a href="%s">Set it now</a>',
						admin_url( 'tools.php?page=press-sync&tab=credentials' ) ),
					'press-sync'
				); ?></p>
        </div>
		<?php
	}

	/**
	 * Get the total number of objects to sync
	 *
	 * @return JSON
	 * @since 0.1.0
	 */
	public function get_objects_to_sync_count_via_ajax() {

		$this->plugin->parse_sync_settings();
		$objects_to_sync = get_option( 'ps_objects_to_sync' );

		wp_send_json_success( array(
			'objects_to_sync' => $objects_to_sync,
			'total_objects'   => $this->plugin->count_objects_to_sync( $objects_to_sync ),
			'page_size'       => $this->plugin->settings['ps_page_size'],
		) );
	}

	/**
	 * Get the order to sync all objects.
	 *
	 * @return JSON
	 * @since 0.6.1
	 */
	public function get_order_to_sync_all_via_ajax() {

		// Get all of the objects to sync in the order that we need them.
		$order_to_sync_all = apply_filters( 'press_sync_order_to_sync_all', array() );

		wp_send_json_success( $order_to_sync_all );
	}

	/**
	 * Sync the data via AJAX
	 *
	 * @return JSON
	 * @since 0.1.0
	 */
	public function sync_wp_data_via_ajax() {
		$settings = array(
			'objects_to_sync' => $this->objects_to_sync,
		);

		// Generate a new session on the first page of syncing.
		if ( 1 === absint( $this->next_page ) ) {
			delete_option( "ps_synced_post_session_{$this->objects_to_sync}" );
		}

		wp_send_json_success( $this->plugin->sync_object( $this->objects_to_sync, $settings, $this->next_page, true ) );
	}
}
