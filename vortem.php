<?php
/**
 * Plugin Name: Vortem AI
 * Plugin URI: https://vortem.ai/
 * Description: Stop Managing Tools. Start Growing an Empire.The All-in-One Intelligent Ecosystem Where Every Store You Add Makes The Entire Network Smarter and More Profitable.
 * Version: 1.0.14
 * Author: vortem.ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vortem-ai
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.2
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'VORTEM_PLUGIN_FILE', __FILE__ );
define( 'VORTEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VORTEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VORTEM_VERSION', '1.0.14' );
define( 'VORTEM_MIN_PHP_VERSION', '7.4' );
define( 'VORTEM_MIN_WP_VERSION', '6.0' );
define( 'VORTEM_MIN_WC_VERSION', '8.0' );
define( 'VORTEM_LUCIDE_VERSION', '1.7.0' ); // To be updated when lucide.js is updated

/**
 * Log a debug message only when WP_DEBUG is enabled.
 *
 * The single guarded entry point used for plugin debug logging. WordPress.org
 * Plugin Check flags `error_log()` calls; routing every plugin debug write
 * through this helper means there is exactly one phpcs:ignore in the whole
 * codebase, and the call is a no-op in production unless the site owner
 * explicitly enables WP_DEBUG.
 *
 * @param string $message Log message.
 * @return void
 */
function vortem_log( $message ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}
	if ( is_array( $message ) || is_object( $message ) ) {
		$message = wp_json_encode( $message );
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- guarded by WP_DEBUG above; intentional debug logger
	error_log( 'Vortem: ' . (string) $message );
}

// Declare WooCommerce feature compatibility.
// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+
// - custom_order_tables: HPOS (orders read/written via wc_get_order / WC CRUD only).
// - cart_checkout_blocks: the plugin does not extend the cart or checkout flow,
// so it is compatible with the block-based Cart and Checkout out of the box.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Main Vortem AI Plugin Class
 */
class Vortem_AI {

	/**
	 * Plugin instance
	 *
	 * @var Vortem_AI
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Vortem_AI
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Translations are loaded automatically by WordPress for plugins hosted
		// on WordPress.org since WP 6.7 (just-in-time loading), so the plugin
		// no longer registers `load_plugin_textdomain` itself.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'check_dependencies' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wpadmin_content_layout' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		// Uninstall cleanup is handled by the top-level uninstall.php file.
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		// Capabilities are added once during activation (see activate()).
		// No need to call setup_capabilities() on every init.

		// Initialize autoloader
		$this->init_autoloader();

		// Initialize translation manager (must be early)
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-translation-manager.php';
		Vortem_Translation_Manager::init();

		// Load i18n helper functions
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-i18n.php';

		// Load core classes
		$this->load_core_classes();

		// Initialize product fetcher (replaces old product manager)
		$this->init_product_fetcher();

		// Initialize analytics (needed for REST API routes, not just admin)
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-analytics.php';
		new Vortem_Analytics();

		// Initialize plugin inspector (for Security page)
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();
		add_action( 'rest_api_init', array( $plugin_inspector, 'register_rest_routes' ) );

		// Initialize in-house frontend SEO output.
		$seo = new Vortem_SEO();
		$seo->init();

		// Initialize admin
		if ( is_admin() ) {
			$this->init_admin();

			// Vortem SEO meta box on the product editor (admin + AJAX context).
			$seo_meta_box = new Vortem_SEO_Meta_Box();
			$seo_meta_box->init();
		}

		// Initialize AJAX handlers (always needed)
		$this->init_ajax_handlers();

		// Automatically clear past-due actions on plugin init
		$this->auto_clear_past_due_actions();
	}

	/**
	 * Check plugin dependencies
	 */
	public function check_dependencies() {
		// Show activation error if exists
		$activation_error = get_option( 'vortem_activation_error' );
		if ( $activation_error ) {
			add_action(
				'admin_notices',
				function () use ( $activation_error ) {
					?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'vortem.ai Activation Error', 'vortem-ai' ); ?></strong></p>
					<p><?php echo esc_html( $activation_error ); ?></p>
				</div>
					<?php
				}
			);
			delete_option( 'vortem_activation_error' );
		}

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'show_woocommerce_notice' ) );
		}

		if ( ! $this->is_php_version_compatible() ) {
			add_action( 'admin_notices', array( $this, 'show_php_version_notice' ) );
		}

		if ( ! $this->is_wp_version_compatible() ) {
			add_action( 'admin_notices', array( $this, 'show_wp_version_notice' ) );
		}
	}

	/**
	 * Setup capabilities for user roles
	 * Adds vortem_manage capability to administrator, editor, and shop_manager roles
	 */
	private function setup_capabilities() {
		// Define roles that should have access to Vortem plugin
		$allowed_roles = array( 'administrator', 'editor', 'shop_manager' );

		foreach ( $allowed_roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'vortem_manage' );
			}
		}
	}

	/**
	 * Flush rewrite rules once when flagged by a product import.
	 *
	 * The transient is set by get_or_create_attribute() so the expensive
	 * flush_rewrite_rules() call happens on the next admin page load
	 * instead of during the AJAX import request itself.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_transient( 'vortem_flush_rewrite_rules' ) ) {
			delete_transient( 'vortem_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | Checks if WooCommerce plugin is active
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if PHP version is compatible
	 *
	 * @return bool
	 */
	private function is_php_version_compatible() {
		return version_compare( PHP_VERSION, VORTEM_MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Check if WordPress version is compatible
	 *
	 * @return bool
	 */
	private function is_wp_version_compatible() {
		$current_wp_version = get_bloginfo( 'version' );
		return version_compare( $current_wp_version, VORTEM_MIN_WP_VERSION, '>=' );
	}

	/**
	 * Check if setup is completed
	 *
	 * @return bool
	 */
	private function is_setup_completed() {
		return get_option( 'vortem_setup_completed', false );
	}

	/**
	 * Show WooCommerce dependency notice
	 */
	public function show_woocommerce_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'vortem', 'vortem-ai' ); ?></strong>
				<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'vortem-ai' ); ?>
				<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
					<?php esc_html_e( 'Install WooCommerce', 'vortem-ai' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Show PHP version notice
	 */
	public function show_php_version_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'vortem', 'vortem-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %1$s: Minimum required PHP version, %2$s: Current PHP version */
					esc_html__( 'requires PHP version %1$s or higher. You are running version %2$s.', 'vortem-ai' ),
					esc_html( VORTEM_MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show WordPress version notice
	 */
	public function show_wp_version_notice() {
		$current_wp_version = get_bloginfo( 'version' );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'vortem', 'vortem-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %1$s: Minimum required WordPress version, %2$s: Current WordPress version */
					esc_html__( 'requires WordPress version %1$s or higher. You are running version %2$s.', 'vortem-ai' ),
					esc_html( VORTEM_MIN_WP_VERSION ),
					esc_html( $current_wp_version )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show setup wizard notice
	 */
	public function show_setup_wizard_notice() {
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Welcome to vortem.ai!', 'vortem-ai' ); ?></strong>
				<?php esc_html_e( 'Complete the setup wizard to connect your account and get started.', 'vortem-ai' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vortem-setup-wizard' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Start Setup Wizard', 'vortem-ai' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize autoloader
	 */
	private function init_autoloader() {
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-autoloader.php';
		Vortem_Autoloader::init();
	}

	/**
	 * Load core classes
	 */
	private function load_core_classes() {
		// Core classes will be loaded by autoloader
		// This method can be used for additional initialization
	}


	/**
	 * Initialize product fetcher
	 */
	private function init_product_fetcher() {
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-fetcher.php';
		new Vortem_Product_Fetcher();
	}


	/**
	 * Initialize admin
	 */
	private function init_admin() {
		require_once VORTEM_PLUGIN_DIR . 'admin/class-vortem-admin.php';
		new Vortem_Admin();

		// Initialize setup wizard
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-setup-wizard.php';
		new Vortem_Setup_Wizard();
	}

	/**
	 * Initialize AJAX handlers
	 */
	private function init_ajax_handlers() {
		require_once VORTEM_PLUGIN_DIR . 'admin/class-vortem-admin.php';

		add_action( 'wp_ajax_vortem_import_fetched_products', array( 'Vortem_Product_Fetcher', 'ajax_import_fetched_products' ) );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Check dependencies before activation
		if ( ! $this->is_php_version_compatible() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			// Use add_option to store error message instead of wp_die
			add_option(
				'vortem_activation_error',
				sprintf(
				// translators: %1$s: Minimum required PHP version, %2$s: Current PHP version
					esc_html__( 'vortem.ai requires PHP version %1$s or higher. You are running version %2$s.', 'vortem-ai' ),
					esc_html( VORTEM_MIN_PHP_VERSION ),
					esc_html( PHP_VERSION )
				)
			);
			return;
		}

		if ( ! $this->is_wp_version_compatible() ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			$current_wp_version = get_bloginfo( 'version' );
			// Use add_option to store error message instead of wp_die
			add_option(
				'vortem_activation_error',
				sprintf(
				// translators: %1$s: Minimum required WordPress version, %2$s: Current WordPress version
					esc_html__( 'vortem.ai requires WordPress version %1$s or higher. You are running version %2$s.', 'vortem-ai' ),
					esc_html( VORTEM_MIN_WP_VERSION ),
					esc_html( $current_wp_version )
				)
			);
			return;
		}

		// Setup capabilities for user roles
		$this->setup_capabilities();

		// Create database tables
		$this->create_database_tables();

		// Set default options
		$this->set_default_options();

		// Find and store first admin/editor user for imports (no automatic user creation)
		$this->set_import_user();

		// Schedule cron jobs
		$this->schedule_cron_jobs();

		// Make sure no auto-sync hook is left hanging from a previous install.
		wp_clear_scheduled_hook( 'vortem_sync_products' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled cron jobs
		$this->clear_cron_jobs();

		// Flush rewrite rules
		flush_rewrite_rules();
	}


	/**
	 * Create database tables
	 */
	private function create_database_tables() {
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-database.php';
		Vortem_Database::create_tables();
	}

	/**
	 * Set default options
	 */
	private function set_default_options() {
		$default_options = array(
			'vortem_sync_enabled'      => true,
			'vortem_sync_interval'     => 'hourly',
			'vortem_products_per_page' => 16,
		);

		foreach ( $default_options as $option_name => $default_value ) {
			if ( get_option( $option_name ) === false ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Schedule cron jobs
	 */
	private function schedule_cron_jobs() {
		// Product sync is disabled for manual control.
		// Products only sync when the user clicks "Sync Products".
	}

	/**
	 * Clear cron jobs
	 */
	private function clear_cron_jobs() {
		// Check if hook is scheduled before clearing
		$sync_products_next = wp_next_scheduled( 'vortem_sync_products' );
		if ( $sync_products_next ) {
			wp_clear_scheduled_hook( 'vortem_sync_products' );
		}
	}

	/**
	 * Set import user - finds first Administrator or Editor user
	 * No automatic user creation - uses existing users only
	 */
	private function set_import_user() {
		// Try to get first Administrator user
		$admin_users = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( ! empty( $admin_users ) ) {
			update_option( 'vortem_bot_user_id', $admin_users[0]->ID );
			return;
		}

		// If no admin, try to get first Editor user
		$editor_users = get_users(
			array(
				'role'    => 'editor',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( ! empty( $editor_users ) ) {
			update_option( 'vortem_bot_user_id', $editor_users[0]->ID );
			return;
		}

		// If no admin or editor found, clear the option
		delete_option( 'vortem_bot_user_id' );
	}

	/**
	 * Clear automatic sync cron job (for manual control)
	 */
	public function disable_automatic_sync() {
		wp_clear_scheduled_hook( 'vortem_sync_products' );
	}

	/**
	 * Remove default #wpcontent horizontal padding on all wp-admin screens (LTR: left, RTL: right).
	 *
	 * @param string $hook Current admin page hook (unused).
	 * @return void
	 */
	public function enqueue_wpadmin_content_layout( $hook ) {
		unset( $hook );
		wp_enqueue_style(
			'vortem-wpadmin-content',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-wpadmin-content.css',
			array(),
			VORTEM_VERSION
		);
	}

	/**
	 * Enqueue admin assets (CSS and JS)
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load scripts on Vortem admin pages
		if ( strpos( $hook, 'vortem' ) === false ) {
			return;
		}

		$allowed_pages = array(
			'vortem-owerview',
			'vortem-products',
			'vortem-orders',
			'vortem-analytics',
			'vortem-email-marketing',
			'vortem-insights',
			'vortem-security',
			'vortem-settings',
			'vortem-setup-wizard',
			'vortem-session',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; page value whitelisted below
		$page_param   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$current_page = in_array( $page_param, $allowed_pages, true ) ? $page_param : '';

		// Enqueue common admin CSS files
		$this->enqueue_admin_styles( $current_page, $hook );

		// Enqueue common admin JS files
		$this->enqueue_admin_scripts( $current_page, $hook );

		// Enqueue page-specific assets
		$this->enqueue_page_specific_assets( $current_page, $hook );
	}

	/**
	 * Enqueue common admin styles
	 *
	 * @param string $current_page Current admin page
	 * @param string $hook Current admin hook
	 * @return void
	 */
	private function enqueue_admin_styles( $current_page, $hook ) {
		// Base pagination CSS (dependency for main admin CSS)
		wp_enqueue_style(
			'vortem-pagination',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-pagination.css',
			array(),
			VORTEM_VERSION
		);

		// Main admin CSS (depends on pagination CSS)
		wp_enqueue_style(
			'vortem-admin',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-new.css',
			array( 'vortem-pagination' ),
			VORTEM_VERSION
		);
	}

	/**
	 * Enqueue common admin scripts
	 *
	 * @param string $current_page Current admin page
	 * @param string $hook Current admin hook
	 * @return void
	 */
	private function enqueue_admin_scripts( $current_page, $hook ) {
		// Logger utility (no dependencies, loaded in header)
		wp_enqueue_script(
			'vortem-logger',
			VORTEM_PLUGIN_URL . 'assets/js/vortem-logger.js',
			array(),
			VORTEM_VERSION,
			false
		);

		// Main admin JS (depends on jQuery and logger, loaded in footer)
		wp_enqueue_script(
			'vortem-admin',
			VORTEM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'vortem-logger' ),
			VORTEM_VERSION,
			true
		);

		// Button handler script (depends on jQuery and main admin script, loaded in footer)
		wp_enqueue_script(
			'vortem-buttons',
			VORTEM_PLUGIN_URL . 'assets/js/vortem-buttons.js',
			array( 'jquery', 'vortem-admin' ),
			VORTEM_VERSION,
			true
		);

		// Localize main admin script
		wp_localize_script(
			'vortem-admin',
			'vortem_admin',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'vortem_admin_nonce' ),
				'nonce_import'          => wp_create_nonce( 'vortem_import_products' ),
				'nonce_details'         => wp_create_nonce( 'vortem_get_product_details' ),
				'nonce_refresh_imports' => wp_create_nonce( 'vortem_refresh_imports_counter' ),
				'products_per_page'     => get_option( 'vortem_products_per_page', 16 ),
				'is_development'        => Vortem_Config::is_development(),
			)
		);
	}

	/**
	 * Enqueue page-specific assets
	 *
	 * @param string $current_page Current admin page
	 * @param string $hook Current admin hook
	 * @return void
	 */
	private function enqueue_page_specific_assets( $current_page, $hook ) {
		// Analytics page - handled by React tabs
		if ( $current_page === 'vortem-analytics' || strpos( $hook, 'vortem-analytics' ) !== false ) {
			// Assets are now enqueued in admin class for React-based tabs
			return;
		}

		// Orders page
		if ( $current_page === 'vortem-orders' || strpos( $hook, 'vortem-orders' ) !== false ) {
			$this->enqueue_orders_assets();
			return;
		}

		// Email Marketing page
		if ( $current_page === 'vortem-email-marketing' || strpos( $hook, 'vortem-email-marketing' ) !== false ) {
			$this->enqueue_email_marketing_assets();
			return;
		}

		// Insights page
		if ( $current_page === 'vortem-insights' || strpos( $hook, 'vortem-insights' ) !== false ) {
			$this->enqueue_insights_assets();
			return;
		}

		// Setup Wizard page
		if ( $current_page === 'vortem-setup-wizard' || strpos( $hook, 'vortem-setup-wizard' ) !== false ) {
			$this->enqueue_setup_wizard_assets();
			return;
		}

		// Security page
		if ( $current_page === 'vortem-security' || strpos( $hook, 'vortem-security' ) !== false ) {
			$this->enqueue_security_assets();
			return;
		}
	}

	/**
	 * Enqueue analytics page assets
	 *
	 * @return void
	 */
	private function enqueue_analytics_assets() {
		// External Library: Chart.js 4.5.1 (Chart.js Contributors) - https://www.chartjs.org/ | License: MIT | Bundled locally in assets/vendor/chart.js/ | Used for analytics chart rendering
		wp_enqueue_script(
			'chart-js',
			VORTEM_PLUGIN_URL . 'assets/vendor/chart.js/chart.js',
			array(),
			'4.5.1',
			true
		);

		// Analytics CSS
		wp_enqueue_style(
			'vortem-analytics-dash',
			VORTEM_PLUGIN_URL . 'assets/css/mega-dash.css',
			array(),
			VORTEM_VERSION
		);

		// Analytics JS (depends on Chart.js, loaded in footer)
		wp_enqueue_script(
			'vortem-analytics-dash',
			VORTEM_PLUGIN_URL . 'assets/js/mega-dash.js',
			array( 'chart-js' ),
			VORTEM_VERSION,
			true
		);

		// Localize script with currency data
		$currency_symbol = '';
		$currency_pos    = 'left';
		// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | Retrieves WooCommerce currency symbol and position for analytics
		if ( function_exists( 'WC' ) ) {
			$currency_symbol = get_woocommerce_currency_symbol();
			$currency_pos    = get_option( 'woocommerce_currency_pos', 'left' );
		}

		wp_localize_script(
			'vortem-analytics-dash',
			'vortemData',
			array(
				'ajax_url'         => rest_url( 'vortem/v1/metrics/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'refresh_interval' => 30000,
				'locale'           => get_locale(),
				'currency_symbol'  => $currency_symbol,
				'currency_pos'     => $currency_pos,
			)
		);
	}

	/**
	 * Enqueue orders page assets
	 *
	 * @return void
	 */
	private function enqueue_orders_assets() {
		// External Library: Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | Used for UI icon rendering
		wp_enqueue_script(
			'lucide-icons',
			VORTEM_PLUGIN_URL . 'assets/vendor/lucide/lucide.js',
			array(),
			VORTEM_LUCIDE_VERSION,
			false
		);

		// Orders CSS
		wp_enqueue_style(
			'vortem-orders',
			VORTEM_PLUGIN_URL . 'assets/css/orders.css',
			array(),
			VORTEM_VERSION
		);

		// Orders JS (depends on jQuery and Lucide, loaded in footer)
		wp_enqueue_script(
			'vortem-orders',
			VORTEM_PLUGIN_URL . 'assets/js/orders.js',
			array( 'jquery', 'lucide-icons' ),
			VORTEM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'vortem-orders',
			'vortemOrders',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'vortem_orders_nonce' ),
				'currencySymbol'   => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
				'currencyPos'      => function_exists( 'get_option' ) ? get_option( 'woocommerce_currency_pos', 'left' ) : 'left',
				'strings'          => array(
					'no_orders_found' => __( 'No orders found', 'vortem-ai' ),
					'total_orders'    => __( 'Total Orders', 'vortem-ai' ),
					'current_page'    => __( 'Current Page', 'vortem-ai' ),
					'orders_per_page' => __( 'Orders Per Page', 'vortem-ai' ),
					'loading_orders'  => __( 'Loading orders...', 'vortem-ai' ),
					'previous'        => __( 'Previous', 'vortem-ai' ),
					'next'            => __( 'Next', 'vortem-ai' ),
					'page'            => __( 'Page', 'vortem-ai' ),
					'of'              => __( 'of', 'vortem-ai' ),
					'orders'          => __( 'orders', 'vortem-ai' ),
				),
				'current_language' => class_exists( 'Vortem_Translation_Manager' ) ? Vortem_Translation_Manager::get_current_language() : 'en',
			)
		);

		// Initialize Lucide icons after page load
		wp_add_inline_script(
			'vortem-orders',
			'
            (function() {
                function initLucideIcons() {
                    if (typeof lucide !== "undefined" && lucide && typeof lucide.createIcons === "function") {
                        lucide.createIcons();
                    } else if (typeof window.lucide !== "undefined" && window.lucide && typeof window.lucide.createIcons === "function") {
                        window.lucide.createIcons();
                    }
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(initLucideIcons, 300);
                    });
                } else {
                    setTimeout(initLucideIcons, 300);
                }
            })();
        ',
			'after'
		);
	}

	/**
	 * Enqueue email marketing page assets
	 *
	 * @return void
	 */
	private function enqueue_email_marketing_assets() {
		// WordPress editor and media scripts
		wp_enqueue_editor();
		wp_enqueue_media();

		// Email marketing CSS
		wp_enqueue_style(
			'vortem-email-marketing',
			VORTEM_PLUGIN_URL . 'assets/css/email-marketing.css',
			array(),
			VORTEM_VERSION
		);

		// Email marketing JS (depends on jQuery and editor, loaded in footer)
		wp_enqueue_script(
			'vortem-email-marketing',
			VORTEM_PLUGIN_URL . 'assets/js/email-marketing.js',
			array( 'jquery', 'editor' ),
			VORTEM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'vortem-email-marketing',
			'vortemEmailMarketing',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vortem_email_marketing_nonce' ),
			)
		);
	}

	/**
	 * Enqueue insights page assets
	 *
	 * @return void
	 */
	private function enqueue_insights_assets() {
		// Insights CSS
		wp_enqueue_style(
			'vortem-insights',
			VORTEM_PLUGIN_URL . 'assets/css/insights.css',
			array(),
			VORTEM_VERSION
		);

		// Insights JS (loaded in footer)
		wp_enqueue_script(
			'vortem-insights',
			VORTEM_PLUGIN_URL . 'assets/js/insights.js',
			array(),
			VORTEM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'vortem-insights',
			'vortemInsights',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vortem_insights_nonce' ),
				'siteUrl' => get_site_url(),
			)
		);
	}

	/**
	 * Enqueue setup wizard page assets
	 *
	 * @return void
	 */
	private function enqueue_setup_wizard_assets() {
		// Setup Wizard CSS
		wp_enqueue_style(
			'vortem-wizard',
			VORTEM_PLUGIN_URL . 'assets/css/wizard.css',
			array(),
			VORTEM_VERSION
		);

		// Setup Wizard JS (depends on jQuery, loaded in footer)
		wp_enqueue_script(
			'vortem-wizard',
			VORTEM_PLUGIN_URL . 'assets/js/wizard.js',
			array( 'jquery' ),
			VORTEM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'vortem-wizard',
			'vortemWizard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vortem_wizard_nonce' ),
				'strings' => array(
					'loading'  => __( 'Loading...', 'vortem-ai' ),
					'error'    => __( 'An error occurred. Please try again.', 'vortem-ai' ),
					'success'  => __( 'Success!', 'vortem-ai' ),
					'next'     => __( 'Next', 'vortem-ai' ),
					'previous' => __( 'Previous', 'vortem-ai' ),
					'complete' => __( 'Complete Setup', 'vortem-ai' ),
					'save'     => __( 'Save & Continue', 'vortem-ai' ),
					'accept'   => __( 'Accept & Continue', 'vortem-ai' ),
				),
			)
		);
	}

	/**
	 * Enqueue security page assets
	 *
	 * @return void
	 */
	private function enqueue_security_assets() {
		// Security CSS
		wp_enqueue_style(
			'vortem-security',
			VORTEM_PLUGIN_URL . 'assets/css/security.css',
			array(),
			VORTEM_VERSION
		);

		// Get plugin data for JavaScript
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();
		$plugins          = $plugin_inspector->get_plugin_data();
		$themes           = $plugin_inspector->get_theme_data();

		// Get API URLs
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
		$vortem_api_server      = Vortem_Config::get_primary_api_server();
		$vortem_plugin_api_url  = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress' );
		$vortem_theme_api_url   = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_theme' );
		$vortem_wp_core_api_url = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_wp_core' );
		$vortem_api_url         = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_match' );

		// Security JS (depends on jQuery, loaded in footer)
		wp_enqueue_script(
			'vortem-security',
			VORTEM_PLUGIN_URL . 'assets/js/security.js',
			array( 'jquery' ),
			VORTEM_VERSION,
			true
		);

		// Get translation strings
		$translation_strings = array();
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$translation_strings = Vortem_Translation_Manager::get_js_strings();
		}

		// Get WordPress version
		$current_wp_version = get_bloginfo( 'version' );

		// Localize script with plugin data and translations
		wp_localize_script( 'vortem-security', 'vortemSecurityPlugins', $plugins );
		wp_localize_script( 'vortem-security', 'vortemSecurityThemes', $themes );
		// Use wp_add_inline_script for arbitrary data (non-localization)
		wp_add_inline_script( 'vortem-security', 'var vortemWpVersion = ' . wp_json_encode( $current_wp_version ) . ';', 'before' );
		wp_localize_script(
			'vortem-security',
			'vortemSecurityConfig',
			array(
				'apiUrl'       => $vortem_api_url,
				'pluginApiUrl' => $vortem_plugin_api_url,
				'themeApiUrl'  => $vortem_theme_api_url,
				'wpCoreApiUrl' => $vortem_wp_core_api_url,
			)
		);
		wp_localize_script(
			'vortem-security',
			'vortemSecurityStrings',
			array(
				'version'                           => __( 'Version', 'vortem-ai' ),
				'author'                            => __( 'Author', 'vortem-ai' ),
				'description'                       => __( 'Description', 'vortem-ai' ),
				'file'                              => __( 'File', 'vortem-ai' ),
				'plugin_uri'                        => __( 'Plugin URI', 'vortem-ai' ),
				'last_modified'                     => __( 'Last Modified', 'vortem-ai' ),
				'requires_wp_version'               => __( 'Requires WP Version', 'vortem-ai' ),
				'requires_php'                      => __( 'Requires PHP', 'vortem-ai' ),
				'active'                            => __( 'Active', 'vortem-ai' ),
				'inactive'                          => __( 'Inactive', 'vortem-ai' ),
				'read_more'                         => __( 'Read more', 'vortem-ai' ),
				'read_less'                         => __( 'Read less', 'vortem-ai' ),
				'vulnerabilities'                   => __( 'Vulnerabilities', 'vortem-ai' ),
				'vulnerability'                     => __( 'Vulnerability', 'vortem-ai' ),
				'published'                         => __( 'Published', 'vortem-ai' ),
				'references'                        => __( 'References', 'vortem-ai' ),
				'your_wp_core_is_secure'            => __( 'Your WordPress core is secure!', 'vortem-ai' ),
				'wordpress_core'                    => __( 'WordPress Core', 'vortem-ai' ),
				'no_security_vulnerabilities_found' => __( 'No security vulnerabilities found', 'vortem-ai' ),
				'your_themes_are_secure'            => __( 'Your themes are secure!', 'vortem-ai' ),
				'your_plugins_are_secure'           => __( 'Your plugins are secure!', 'vortem-ai' ),
				'theme_uri'                         => __( 'Theme URI', 'vortem-ai' ),
				'stylesheet'                        => __( 'Stylesheet', 'vortem-ai' ),
				// Card labels
				'version_label'                     => __( 'VERSION', 'vortem-ai' ),
				'author_label'                      => __( 'AUTHOR', 'vortem-ai' ),
				'requires_wp_label'                 => __( 'REQUIRES WP', 'vortem-ai' ),
				'requires_php_label'                => __( 'REQUIRES PHP', 'vortem-ai' ),
				'last_updated_label'                => __( 'Last updated:', 'vortem-ai' ),
				'view_page'                         => __( 'View Page', 'vortem-ai' ),
				// Overview tab strings
				'total_vulnerabilities'             => __( 'Total Vulnerabilities', 'vortem-ai' ),
				'critical'                          => __( 'Critical', 'vortem-ai' ),
				'high'                              => __( 'High', 'vortem-ai' ),
				'medium'                            => __( 'Medium', 'vortem-ai' ),
				'low'                               => __( 'Low', 'vortem-ai' ),
				'secure_items'                      => __( 'Secure Items', 'vortem-ai' ),
				'core'                              => __( 'Core', 'vortem-ai' ),
				'items_requiring_attention'         => __( 'Items Requiring Attention', 'vortem-ai' ),
				'recent_vulnerabilities'            => __( 'Recent Vulnerabilities', 'vortem-ai' ),
				'no_items_at_risk'                  => __( 'No items at risk', 'vortem-ai' ),
				'no_recent_vulnerabilities'         => __( 'No recent vulnerabilities', 'vortem-ai' ),
				// Overview tooltips
				'all_security_issues'               => __( 'All Security Issues', 'vortem-ai' ),
				'across_all_items'                  => __( 'Across all plugins, themes & core', 'vortem-ai' ),
				'of_installed_items_affected'       => __( 'of installed items affected', 'vortem-ai' ),
				'critical_severity'                 => __( 'Critical Severity', 'vortem-ai' ),
				'requires_immediate_attention'      => __( 'Requires immediate attention', 'vortem-ai' ),
				'high_severity'                     => __( 'High Severity', 'vortem-ai' ),
				'address_asap'                      => __( 'Address as soon as possible', 'vortem-ai' ),
				'medium_severity'                   => __( 'Medium Severity', 'vortem-ai' ),
				'schedule_for_review'               => __( 'Schedule for review', 'vortem-ai' ),
				'low_severity'                      => __( 'Low Severity', 'vortem-ai' ),
				'monitor_and_plan_fix'              => __( 'Monitor and plan fix', 'vortem-ai' ),
				'of_all_vulnerabilities'            => __( 'of all vulnerabilities', 'vortem-ai' ),
				'of_items_are_clean'                => __( 'of items are clean', 'vortem-ai' ),
				// Status messages
				'scanning'                          => __( 'Scanning...', 'vortem-ai' ),
				'checking'                          => __( 'Checking...', 'vortem-ai' ),
				'loading'                           => __( 'Loading...', 'vortem-ai' ),
				'your_site_is_secure'               => __( 'Your site is secure', 'vortem-ai' ),
				'no_known_vulnerabilities'          => __( 'No known vulnerabilities detected', 'vortem-ai' ),
				'critical_vulnerabilities_found'    => __( 'Critical vulnerabilities detected!', 'vortem-ai' ),
				'immediate_action_required'         => __( 'Immediate action is required. Please update or remove affected items as soon as possible.', 'vortem-ai' ),
				'high_vulnerabilities_found'        => __( 'High severity vulnerabilities detected', 'vortem-ai' ),
				'review_soon'                       => __( 'Please review and address these issues soon.', 'vortem-ai' ),
				'medium_vulnerabilities_found'      => __( 'Medium severity vulnerabilities detected', 'vortem-ai' ),
				'schedule_review'                   => __( 'Schedule time to review and fix these issues.', 'vortem-ai' ),
				'low_vulnerabilities_found'         => __( 'Low severity vulnerabilities detected', 'vortem-ai' ),
				'monitor_issues'                    => __( 'Monitor these issues and plan fixes when convenient.', 'vortem-ai' ),
				'analyzing_security'                => __( 'Analyzing your WordPress installation for security vulnerabilities', 'vortem-ai' ),
				'security_vulnerabilities_detected' => __( 'Security vulnerabilities detected in WordPress version. Update to latest version is recommended.', 'vortem-ai' ),
			)
		);
		wp_localize_script(
			'vortem-security',
			'vortemSecurityAjax',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'vortem_security_nonce' ),
				'sending'   => __( 'Sending...', 'vortem-ai' ),
				'send_data' => __( 'Send to API', 'vortem-ai' ),
			)
		);
	}

	/**
	 * Automatically clear past-due actions without UI
	 * Uses transient lock to prevent running too frequently and avoid deadlocks
	 */
	public function auto_clear_past_due_actions() {
		// External Library: Action Scheduler (WooCommerce/Automattic) - https://actionscheduler.org/ | License: GPLv3+ | Bundled with WooCommerce | Used for background task scheduling
		if ( ! class_exists( 'ActionScheduler_Store' ) ) {
			return;
		}

		// Use transient lock to prevent running too frequently (every 5 minutes max)
		$lock_key = 'vortem_clear_actions_lock';
		if ( get_transient( $lock_key ) ) {
			return; // Already ran recently, skip to avoid deadlocks
		}

		// Set lock for 5 minutes
		set_transient( $lock_key, true, 5 * MINUTE_IN_SECONDS );

		// Add random delay (0-2 seconds) to reduce race conditions
		usleep( wp_rand( 0, 2000000 ) );

		try {
			$store = ActionScheduler_Store::instance();

			// Get past-due actions with smaller batch to reduce lock time
			$past_due_actions = $store->query_actions(
				array(
					'status'   => 'pending',
					'date'     => array(
						'compare' => '<',
						'value'   => current_time( 'mysql' ),
					),
					'per_page' => 20, // Smaller batch to reduce deadlock risk
				)
			);

			$past_due_count = count( $past_due_actions );

			if ( $past_due_count > 0 ) {
				$cleared_count = 0;

				foreach ( $past_due_actions as $action_id ) {
					try {
						// Cancel the action with retry logic
						$store->cancel_action( $action_id );
						++$cleared_count;
					} catch ( Exception $e ) {
						// Skip this action if it fails (might be locked)
						continue;
					}
				}
			}
		} catch ( Exception $e ) {
			// Handle deadlock specifically
			$error_message = $e->getMessage();
			if ( strpos( $error_message, 'Deadlock' ) !== false || strpos( $error_message, 'Lock wait timeout' ) !== false ) {
				// Delete lock to allow retry on next request
				delete_transient( $lock_key );
			}
		}
	}
}

// Initialize the plugin
Vortem_AI::get_instance();

/**
 * Check if current user can manage Vortem plugin
 * Allows administrator, editor, and shop_manager roles
 *
 * @return bool True if user has access, false otherwise
 */
function vortem_current_user_can_manage() {
	return current_user_can( 'vortem_manage' ) || current_user_can( 'manage_options' );
}

/**
 * Action Scheduler optimizations to prevent deadlocks
 */
// Reduce concurrent Action Scheduler batches to prevent deadlocks
add_filter(
	'action_scheduler_queue_runner_concurrent_batches',
	function () {
		return 1; // Only run 1 batch at a time
	}
);

// Reduce batch size to minimize lock duration
add_filter(
	'action_scheduler_queue_runner_batch_size',
	function () {
		return 10; // Smaller batches
	}
);

// Increase time between batches
add_filter(
	'action_scheduler_queue_runner_time_limit',
	function () {
		return 20; // 20 seconds per batch
	}
);

// (The legacy `wp_clear_scheduled_hook( 'vortem_sync_products' )` activation
// helper was consolidated into Vortem_AI::activate() in 1.0.11 so the plugin
// has a single activation entry point.)

// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | Filters WooCommerce checkout fields to make phone required for AliExpress orders
add_filter( 'woocommerce_checkout_fields', 'vortem_make_phone_required' );
function vortem_make_phone_required( $fields ) {
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['required'] = true;
	}
	return $fields;
}

/**
 * Get count of products imported through the plugin (have _vortem_product_id, _vortem_imported, or _vortem_synced meta).
 * Uses a single COUNT query to avoid unbounded meta_query results.
 *
 * @return int
 */
function vortem_get_imported_products_count() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bounded COUNT; table names from $wpdb
	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key IN (%s, %s, %s)
			WHERE p.post_type = 'product' AND p.post_status IN ('publish','draft','private')",
			'_vortem_product_id',
			'_vortem_imported',
			'_vortem_synced'
		)
	);
	return $count;
}