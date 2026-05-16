<?php
/**
 * Vortem Admin Class
 *
 * Handles admin interface functionality
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Admin
 */
class Vortem_Admin {
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from $wpdb->prefix; user input passed to prepare().

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );

		// Automatically clear past-due actions on admin init
		add_action( 'admin_init', array( $this, 'auto_clear_past_due_actions' ) );

		// Validate and fix database on admin init
		add_action( 'admin_init', array( $this, 'validate_database_on_admin_init' ) );

		// Handle analytics cache clearing
		add_action( 'admin_init', array( $this, 'handle_clear_analytics_cache' ) );

		// Show draft products in WordPress admin Products section
		add_action( 'pre_get_posts', array( $this, 'show_draft_products_in_admin' ) );

		// Display product video status in WooCommerce product edit page
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'display_product_video_status' ) );

		// When a WooCommerce product is permanently deleted from Products list,
		// also delete the corresponding Vortem record and remote imported product (if any)
		add_action( 'before_delete_post', array( $this, 'handle_woocommerce_product_delete' ), 10, 1 );

		// Register AJAX actions
		add_action( 'wp_ajax_vortem_edit_product', array( $this, 'ajax_edit_product' ) );
		add_action( 'wp_ajax_vortem_save_product', array( $this, 'ajax_save_product' ) );
		add_action( 'wp_ajax_vortem_get_product_details', array( $this, 'ajax_get_product_details' ) );
		add_action( 'wp_ajax_vortem_refresh_imports_counter', array( $this, 'ajax_refresh_imports_counter' ) );
		add_action( 'wp_ajax_vortem_validate_endpoint', array( $this, 'ajax_validate_endpoint' ) );
		add_action( 'wp_ajax_vortem_fetch_products', array( $this, 'ajax_fetch_products' ) );
		add_action( 'wp_ajax_vortem_fetch_trend_products', array( $this, 'ajax_fetch_trend_products' ) );
		add_action( 'wp_ajax_vortem_fetch_tiktok_products', array( $this, 'ajax_fetch_tiktok_products' ) );
		add_action( 'wp_ajax_vortem_get_top_categories_exist', array( $this, 'ajax_get_top_categories_exist' ) );
		add_action( 'wp_ajax_vortem_get_imported_product_categories', array( $this, 'ajax_get_imported_product_categories' ) );
		add_action( 'wp_ajax_vortem_get_categories', array( $this, 'ajax_get_categories' ) );
		add_action( 'wp_ajax_vortem_import_single_product', array( $this, 'ajax_import_single_product' ) );
		add_action( 'wp_ajax_vortem_delete_single_product', array( $this, 'ajax_delete_single_product' ) );
		add_action( 'wp_ajax_vortem_check_product_status', array( $this, 'ajax_check_product_status' ) );

		// Email Marketing AJAX actions
		add_action( 'wp_ajax_vortem_em_get_emails', array( $this, 'ajax_em_get_emails' ) );
		add_action( 'wp_ajax_vortem_em_search_emails', array( $this, 'ajax_em_search_emails' ) );
		add_action( 'wp_ajax_vortem_em_get_email', array( $this, 'ajax_em_get_email' ) );
		add_action( 'wp_ajax_vortem_em_get_email_status', array( $this, 'ajax_em_get_email_status' ) );
		add_action( 'wp_ajax_vortem_em_create_email', array( $this, 'ajax_em_create_email' ) );
		add_action( 'wp_ajax_vortem_em_update_email', array( $this, 'ajax_em_update_email' ) );
		add_action( 'wp_ajax_vortem_em_delete_email', array( $this, 'ajax_em_delete_email' ) );
		add_action( 'wp_ajax_vortem_em_bulk_delete_emails', array( $this, 'ajax_em_bulk_delete_emails' ) );
		add_action( 'wp_ajax_vortem_em_send_email', array( $this, 'ajax_em_send_email' ) );
		add_action( 'wp_ajax_vortem_em_get_useg', array( $this, 'ajax_em_get_useg' ) );
		add_action( 'wp_ajax_vortem_em_get_email_lists', array( $this, 'ajax_em_get_email_lists' ) );
		add_action( 'wp_ajax_vortem_em_create_email_list', array( $this, 'ajax_em_create_email_list' ) );
		add_action( 'wp_ajax_vortem_em_update_email_list', array( $this, 'ajax_em_update_email_list' ) );
		add_action( 'wp_ajax_vortem_em_delete_email_list', array( $this, 'ajax_em_delete_email_list' ) );
		add_action( 'wp_ajax_vortem_get_currency_codes', array( $this, 'ajax_get_currency_codes' ) );
		add_action( 'wp_ajax_vortem_get_current_currency', array( $this, 'ajax_get_current_currency' ) );
		add_action( 'wp_ajax_vortem_update_currency', array( $this, 'ajax_update_currency' ) );

		// Insights AJAX actions
		add_action( 'wp_ajax_vortem_get_insights', array( $this, 'ajax_get_insights' ) );
		add_action( 'wp_ajax_vortem_refetch_insights', array( $this, 'ajax_refetch_insights' ) );
		add_action( 'wp_ajax_vortem_em_send_email_list', array( $this, 'ajax_em_send_email_list' ) );

		// Orders AJAX actions
		add_action( 'wp_ajax_vortem_get_orders', array( $this, 'ajax_get_orders' ) );
		add_action( 'wp_ajax_vortem_search_orders', array( $this, 'ajax_search_orders' ) );
		add_action( 'wp_ajax_vortem_get_order_details', array( $this, 'ajax_get_order_details' ) );
		add_action( 'wp_ajax_vortem_send_order_to_aliexpress', array( $this, 'ajax_send_order_to_aliexpress' ) );

		// AliExpress AJAX actions
		add_action( 'wp_ajax_vortem_get_aliexpress_auth_url', array( $this, 'ajax_get_aliexpress_auth_url' ) );
		add_action( 'wp_ajax_vortem_get_aliexpress_auth_status', array( $this, 'ajax_get_aliexpress_auth_status' ) );
		add_action( 'wp_ajax_vortem_disconnect_aliexpress', array( $this, 'ajax_disconnect_aliexpress' ) );
		add_action( 'wp_ajax_vortem_get_imported_products_count', array( $this, 'ajax_get_imported_products_count' ) );

		// Sentiment data AJAX action for overview dashboard
		add_action( 'wp_ajax_vortem_get_sentiment_data', array( $this, 'ajax_get_sentiment_data' ) );

		// Security page AJAX action - send plugin data to API
		add_action( 'wp_ajax_vortem_send_security_data', array( $this, 'ajax_send_security_data' ) );

		// Security Results page AJAX action - get plugin data
		add_action( 'wp_ajax_vortem_get_plugin_data', array( $this, 'ajax_get_plugin_data' ) );
		add_action( 'wp_ajax_vortem_get_theme_data', array( $this, 'ajax_get_theme_data' ) );

		// Security Scan Results AJAX action - fetch vulnerability data
		add_action( 'wp_ajax_vortem_get_security_results', array( $this, 'ajax_get_security_results' ) );

		// Render navigation dropdown menu on all Vortem pages
		add_action( 'admin_footer', array( $this, 'render_navigation_dropdown' ) );
	}

	/**
	 * Custom notice shower for vortem-page-content
	 */
	public function show_custom_notice( $message, $type = 'info' ) {
		$notice_class = 'vortem-custom-notice vortem-plugin-notice vortem-notice-' . $type;
		echo '<div class="' . esc_attr( $notice_class ) . '">';
		echo '<p>' . wp_kses_post( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Show all custom notices for current page
	 */
	public function show_all_custom_notices() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Check for various notice transients
		$notices = array();

		// Setup complete notice
		$setup_notice = get_transient( 'vortem_setup_complete_notice' );
		if ( $setup_notice ) {
			$notices[] = array(
				'message' => $setup_notice,
				'type'    => 'success',
			);
			delete_transient( 'vortem_setup_complete_notice' );
		}

		// Products page notices
		if ( $current_page === 'vortem-products' || $current_page === 'vortem-orders' ) {
			$products_success = get_transient( 'vortem_products_success_notice' );
			if ( $products_success ) {
				$notices[] = array(
					'message' => $products_success,
					'type'    => 'success',
				);
				delete_transient( 'vortem_products_success_notice' );
			}
			$products_error = get_transient( 'vortem_products_error_notice' );
			if ( $products_error ) {
				$notices[] = array(
					'message' => $products_error,
					'type'    => 'error',
				);
				delete_transient( 'vortem_products_error_notice' );
			}
			$products_warning = get_transient( 'vortem_products_warning_notice' );
			if ( $products_warning ) {
				$notices[] = array(
					'message' => $products_warning,
					'type'    => 'warning',
				);
				delete_transient( 'vortem_products_warning_notice' );
			}
		}

		// Display all notices
		foreach ( $notices as $notice ) {
			$this->show_custom_notice( $notice['message'], $notice['type'] );
		}
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Use the plugin URL constant for the icon path
		$icon_url = VORTEM_PLUGIN_URL . 'assets/images/icon.webp';

		// Always add the main menu page (dashboard)
		add_menu_page(
			__( 'vortem.ai', 'vortem-ai' ),
			__( 'Vortem.ai', 'vortem-ai' ),
			'vortem_manage',
			'vortem-owerview',
			array( $this, 'dashboard_page' ),
			$icon_url,
			58
		);

		// Add dashboard submenu (always visible)
		// Use same slug as main menu to replace the auto-created duplicate
		add_submenu_page(
			'vortem-owerview',
			__( 'Overview', 'vortem-ai' ),
			__( 'Overview', 'vortem-ai' ),
			'vortem_manage',
			'vortem-owerview',
			array( $this, 'dashboard_page' ),
			0
		);

		// Only add other submenu pages if setup is completed
		if ( $this->is_setup_completed() ) {
			add_submenu_page(
				'vortem-owerview',
				__( 'Products', 'vortem-ai' ),
				__( 'Products', 'vortem-ai' ),
				'vortem_manage',
				'vortem-products',
				array( $this, 'products_page' ),
				1
			);

			add_submenu_page(
				'vortem-products',
				__( 'Orders', 'vortem-ai' ),
				__( 'Orders', 'vortem-ai' ),
				'vortem_manage',
				'vortem-orders',
				array( $this, 'wc_orders_page' ),
				1
			);

			add_submenu_page(
				'vortem-owerview',
				__( 'Analytics', 'vortem-ai' ),
				__( 'Analytics', 'vortem-ai' ),
				'vortem_manage',
				'vortem-analytics',
				array( $this, 'analytics_tabs_page' ),
				3
			);

			add_submenu_page(
				'vortem-owerview',
				__( 'Email Marketing', 'vortem-ai' ),
				__( 'Email Marketing', 'vortem-ai' ),
				'vortem_manage',
				'vortem-email-marketing',
				array( $this, 'email_marketing_page' ),
				5
			);

			add_submenu_page(
				'vortem-owerview',
				__( 'Insights', 'vortem-ai' ),
				__( 'Insights', 'vortem-ai' ),
				'vortem_manage',
				'vortem-insights',
				array( $this, 'insights_page' ),
				6
			);

			add_submenu_page(
				'vortem-owerview',
				__( 'Security', 'vortem-ai' ),
				__( 'Security', 'vortem-ai' ),
				'vortem_manage',
				'vortem-security',
				array( $this, 'security_page' ),
				8
			);

			add_submenu_page(
				'vortem-owerview',
				__( 'Settings', 'vortem-ai' ),
				__( 'Settings', 'vortem-ai' ),
				'vortem_manage',
				'vortem-settings',
				array( $this, 'settings_page' ),
				10
			);

		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load scripts on Vortem admin pages
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

		// Only load scripts on Vortem admin pages
		// Check both hook name and current page parameter
		if ( strpos( $hook, 'vortem-ai' ) === false && strpos( $hook, 'vortem-' ) === false && ! $this->is_vortem_admin_page() ) {
			return;
		}

		// Enqueue navigation dropdown CSS and JS on all Vortem pages
		wp_enqueue_style(
			'vortem-nav-dropdown',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-nav-dropdown.css',
			array(),
			VORTEM_VERSION
		);

		wp_enqueue_script(
			'vortem-nav-dropdown',
			VORTEM_PLUGIN_URL . 'assets/js/vortem-nav-dropdown.js',
			array( 'jquery' ),
			VORTEM_VERSION,
			true
		);

		// Load React-based analytics tabs page assets
		if ( $current_page === 'vortem-analytics' || strpos( $hook, 'vortem-analytics' ) !== false ) {
			// React / ReactDOM are provided by WordPress core via the
			// 'react' and 'react-dom' script handles; we depend on those
			// below instead of bundling our own copy.

			// External Library: Chart.js 4.5.1 (Chart.js Contributors) - https://www.chartjs.org/ | License: MIT | Bundled locally in assets/vendor/chart.js/ | Used for analytics chart rendering
			wp_enqueue_script(
				'chart-js',
				VORTEM_PLUGIN_URL . 'assets/vendor/chart.js/chart.js',
				array(),
				'4.5.1',
				true
			);

			// Enqueue analytics CSS (for Analytics tab)
			wp_enqueue_style(
				'vortem-analytics-dash',
				VORTEM_PLUGIN_URL . 'assets/css/mega-dash.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue tabs navigation CSS
			wp_enqueue_style(
				'vortem-analytics-tabs',
				VORTEM_PLUGIN_URL . 'assets/css/analytics-tabs.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue the React app script (depends on React, ReactDOM, and Chart.js)
			wp_enqueue_script(
				'vortem-analytics-tabs',
				VORTEM_PLUGIN_URL . 'assets/js/analytics-tabs.js',
				array( 'react', 'react-dom', 'chart-js' ),
				VORTEM_VERSION,
				true
			);

			// Localize script with currency data (static mode - no API calls)
			$currency_symbol = '';
			$currency_pos    = 'left';
			if ( function_exists( 'WC' ) ) {
				$currency_symbol = get_woocommerce_currency_symbol();
				$currency_pos    = get_option( 'woocommerce_currency_pos', 'left' );
			}

			// Get current language
			$current_language = 'en';
			if ( class_exists( 'Vortem_Translation_Manager' ) ) {
				$current_language = Vortem_Translation_Manager::get_current_language();
			}

			// Prepare vortem strings for both scripts
			$vortem_strings = array(
				'total_orders'       => __( 'Total Orders', 'vortem-ai' ),
				'orders_today'       => __( 'Orders Today', 'vortem-ai' ),
				'total_revenue'      => __( 'Total Revenue', 'vortem-ai' ),
				'revenue_today'      => __( 'Revenue Today', 'vortem-ai' ),
				'revenue_30_days'    => __( 'Revenue (30 Days)', 'vortem-ai' ),
				'avg_order_value'    => __( 'Avg Order Value', 'vortem-ai' ),
				'low_stock_items'    => __( 'Low stock items less than 10 pieces', 'vortem-ai' ),
				'pending_orders'     => __( 'Pending Orders', 'vortem-ai' ),
				'failed_orders'      => __( 'Failed Orders', 'vortem-ai' ),
				'refunded_orders'    => __( 'On hold', 'vortem-ai' ),
				'total_products'     => __( 'Total Products', 'vortem-ai' ),
				'cart_abandonment'   => __( 'Cart Abandonment', 'vortem-ai' ),
				'total_users'        => __( 'Total Users', 'vortem-ai' ),
				'users_today'        => __( 'Users Today', 'vortem-ai' ),
				'total_views'        => __( 'Total Views', 'vortem-ai' ),
				'total_posts'        => __( 'Total Posts', 'vortem-ai' ),
				'total_pages'        => __( 'Total Pages', 'vortem-ai' ),
				'pending'            => __( 'Pending', 'vortem-ai' ),
				'failed'             => __( 'Failed', 'vortem-ai' ),
				'refunded'           => __( 'On hold', 'vortem-ai' ),
				'last_updated'       => __( 'Last updated:', 'vortem-ai' ),
				'failed_to_load'     => __( 'Failed to load analytics data. Please refresh the page.', 'vortem-ai' ),
				'no_data_export'     => __( 'No data available to export', 'vortem-ai' ),
				'revenue_overview'   => __( 'Revenue Overview', 'vortem-ai' ),
				'orders_status'      => __( 'Orders Status', 'vortem-ai' ),
				'wordpress_activity' => __( 'WordPress Activity', 'vortem-ai' ),
				'revenue'            => __( 'Revenue', 'vortem-ai' ),
				'wordpress_metrics'  => __( 'WordPress Metrics', 'vortem-ai' ),
			);

			wp_localize_script(
				'vortem-analytics-dash',
				'vortemData',
				array(
					'ajax_url'         => rest_url( 'vortem/v1/metrics/' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'refresh_interval' => 30000, // 30 seconds in milliseconds
					'locale'           => get_locale(),
					'currency_symbol'  => $currency_symbol,
					'currency_pos'     => $currency_pos,
					'current_language' => $current_language,
					'strings'          => $vortem_strings,
				)
			);

			// Also localize the analytics-tabs script with vortem strings
			wp_localize_script(
				'vortem-analytics-tabs',
				'vortemAnalyticsTabsStrings',
				$vortem_strings
			);

			// Mega Dash reads window.vortemMegadashData (translated via .mo + __() above)
			wp_localize_script(
				'vortem-analytics-tabs',
				'vortemMegadashData',
				array(
					'ajax_url'         => rest_url( 'vortem/v1/metrics/' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'refresh_interval' => 30000,
					'locale'           => get_locale(),
					'currency_symbol'  => $currency_symbol,
					'currency_pos'     => $currency_pos,
					'current_language' => $current_language,
					'strings'          => $vortem_strings,
				)
			);

			// Get API base URL from settings for BI Analytics Hub
			$api_base_url = get_option( 'vortem_bi_analytics_hub_api_base_url', Vortem_Config::get_primary_api_server() );

			// Prepare BI Analytics Hub strings so they're available when navigating via React tabs
			$bi_analytics_hub_strings = array(
				'product'                       => __( 'Product', 'vortem-ai' ),
				'average'                       => __( 'Average', 'vortem-ai' ),
				'no_products_found'             => __( 'No products found', 'vortem-ai' ),
				'close'                         => __( 'Close', 'vortem-ai' ),
				'all_kpi_products'              => __( 'All KPI Products', 'vortem-ai' ),
				'top_kpi_products'              => __( 'Top KPI Products', 'vortem-ai' ),
				'product_id'                    => __( 'Product ID', 'vortem-ai' ),
				'product_title'                 => __( 'Product Title', 'vortem-ai' ),
				'base_price'                    => __( 'Base Price', 'vortem-ai' ),
				'low_risk_price'                => __( 'Low-Risk Price', 'vortem-ai' ),
				'competitive_price'             => __( 'Competitive Price', 'vortem-ai' ),
				'high_risk_price'               => __( 'High-Risk Price', 'vortem-ai' ),
				'untitled_product'              => __( 'Untitled Product', 'vortem-ai' ),
				'all_trend_index_products'      => __( 'All Trend Index Products', 'vortem-ai' ),
				'trend_index_by_product'        => __( 'Trend Index by Product', 'vortem-ai' ),
				'product_name'                  => __( 'Product Name', 'vortem-ai' ),
				'price_vs_rating'               => __( 'Price vs Rating', 'vortem-ai' ),
				'price'                         => __( 'Price', 'vortem-ai' ),
				'rating'                        => __( 'Rating', 'vortem-ai' ),
				'reviews'                       => __( 'Reviews', 'vortem-ai' ),
				'ctr'                           => __( 'CTR', 'vortem-ai' ),
				'cvr'                           => __( 'CVR', 'vortem-ai' ),
				'average_price'                 => __( 'Average Price', 'vortem-ai' ),
				'average_rating'                => __( 'Average Rating', 'vortem-ai' ),
				'product_count'                 => __( 'Product Count', 'vortem-ai' ),
				'avg_price'                     => __( 'Avg Price', 'vortem-ai' ),
				'avg_rating'                    => __( 'Avg Rating', 'vortem-ai' ),
				'failed_to_load'                => __( 'Failed to load', 'vortem-ai' ),
				'products'                      => __( 'products', 'vortem-ai' ),
				'product_singular'              => __( 'product', 'vortem-ai' ),
				'fpsi'                          => __( 'FPSI', 'vortem-ai' ),
				'trend_index'                   => __( 'Trend Index', 'vortem-ai' ),
				'profitability'                 => __( 'Profitability', 'vortem-ai' ),
				'competition'                   => __( 'Competition', 'vortem-ai' ),
				'demand_stability'              => __( 'Demand Stability', 'vortem-ai' ),
				'view_all'                      => __( 'View All', 'vortem-ai' ),
				'price_rating_data'             => __( 'price rating data', 'vortem-ai' ),
				'total_reviews'                 => __( 'Total Reviews', 'vortem-ai' ),
				'avg_reviews'                   => __( 'Avg Reviews', 'vortem-ai' ),
				'overall_sentiment'             => __( 'Overall Sentiment', 'vortem-ai' ),
				'positive'                      => __( 'Positive', 'vortem-ai' ),
				'neutral'                       => __( 'Neutral', 'vortem-ai' ),
				'negative'                      => __( 'Negative', 'vortem-ai' ),
				'trend_status_overview'         => __( 'Trend Status Overview', 'vortem-ai' ),
				'rising'                        => __( 'Rising', 'vortem-ai' ),
				'stable'                        => __( 'Stable', 'vortem-ai' ),
				'declining'                     => __( 'Declining', 'vortem-ai' ),
				'sentiment_data'                => __( 'sentiment data', 'vortem-ai' ),
				'trend_status_data'             => __( 'trend status data', 'vortem-ai' ),
				'status'                        => __( 'Status', 'vortem-ai' ),
				'category'                      => __( 'Category', 'vortem-ai' ),
				'count'                         => __( 'Count', 'vortem-ai' ),
				'percentage'                    => __( 'Percentage', 'vortem-ai' ),
				'market_comparison_by_source'   => __( 'Market Comparison (by Source)', 'vortem-ai' ),
				'market'                        => __( 'Market', 'vortem-ai' ),
				'best_seller'                   => __( 'Best seller', 'vortem-ai' ),
				'movers_and_shakers'            => __( 'Movers and shakers', 'vortem-ai' ),
				'new_releases'                  => __( 'New releases', 'vortem-ai' ),
				'market_comparison_data'        => __( 'market comparison data', 'vortem-ai' ),
				'category_comparison'           => __( 'Category Comparison', 'vortem-ai' ),
				'category_comparison_all_data'  => __( 'Category Comparison - All Data', 'vortem-ai' ),
				'categories'                    => __( 'Categories', 'vortem-ai' ),
				'metrics'                       => __( 'Metrics', 'vortem-ai' ),
				'avg_sold'                      => __( 'Avg Sold', 'vortem-ai' ),
				'avg_fpsi'                      => __( 'Avg FPSI', 'vortem-ai' ),
				'na'                            => __( 'N/A', 'vortem-ai' ),
				'value'                         => __( 'value', 'vortem-ai' ),
				'unknown'                       => __( 'Unknown', 'vortem-ai' ),
				'category_comparison_data'      => __( 'category comparison data', 'vortem-ai' ),
				'category_amazon_device'        => __( 'Amazon Device', 'vortem-ai' ),
				'category_appliances'           => __( 'Appliances', 'vortem-ai' ),
				'category_arts_crafts'          => __( 'Arts, Crafts & Sewing', 'vortem-ai' ),
				'category_automotive'           => __( 'Automotive', 'vortem-ai' ),
				'category_baby_products'        => __( 'Baby Products', 'vortem-ai' ),
				'category_baby'                 => __( 'Baby', 'vortem-ai' ),
				'category_beauty'               => __( 'Beauty & Personal Care', 'vortem-ai' ),
				'category_cell_phones'          => __( 'Cell Phones & Accessories', 'vortem-ai' ),
				'category_clothing_shoes'       => __( 'Clothing, Shoes & Jewelry', 'vortem-ai' ),
				'category_collectibles'         => __( 'Collectibles & Fine Art', 'vortem-ai' ),
				'category_electronics'          => __( 'Electronics', 'vortem-ai' ),
				'category_health_household'     => __( 'Health & Household', 'vortem-ai' ),
				'category_home_kitchen'         => __( 'Home & Kitchen', 'vortem-ai' ),
				'category_industrial'           => __( 'Industrial & Scientific', 'vortem-ai' ),
				'category_musical_instruments'  => __( 'Musical Instruments', 'vortem-ai' ),
				'category_no_category'          => __( 'No category found', 'vortem-ai' ),
				'category_office_products'      => __( 'Office Products', 'vortem-ai' ),
				'category_patio_lawn'           => __( 'Patio, Lawn & Garden', 'vortem-ai' ),
				'category_safety_security'      => __( 'Safety & Security', 'vortem-ai' ),
				'category_tools_home'           => __( 'Tools & Home Improvement', 'vortem-ai' ),
				'category_toys_games'           => __( 'Toys & Games', 'vortem-ai' ),
				'suggested_pricing'             => __( 'Suggested Pricing', 'vortem-ai' ),
				'all_suggested_pricing'         => __( 'All Suggested Pricing', 'vortem-ai' ),
				'suggested_pricing_data'        => __( 'suggested pricing data', 'vortem-ai' ),
				'search_products'               => __( 'Search products...', 'vortem-ai' ),
				'search_products_or_categories' => __( 'Search products or categories...', 'vortem-ai' ),
				'last_updated'                  => __( 'Last updated:', 'vortem-ai' ),
			);
		}

		// Load orders page-specific assets
		if ( $current_page === 'vortem-orders' || strpos( $hook, 'vortem-orders' ) !== false ) {
			// External Library: Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | Used for UI icon rendering on orders page
			wp_enqueue_script(
				'lucide-icons',
				VORTEM_PLUGIN_URL . 'assets/vendor/lucide/lucide.js',
				array(),
				VORTEM_LUCIDE_VERSION,
				false
			);

			// Enqueue orders CSS
			wp_enqueue_style(
				'vortem-orders',
				VORTEM_PLUGIN_URL . 'assets/css/orders.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue orders JS (after Lucide is loaded)
			wp_enqueue_script(
				'vortem-orders',
				VORTEM_PLUGIN_URL . 'assets/js/orders.js',
				array( 'jquery', 'lucide-icons' ),
				VORTEM_VERSION,
				true
			);

			// Localize script for AJAX
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

			// Add inline script to ensure icons initialize after page load
			wp_add_inline_script(
				'vortem-orders',
				'
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(function() {
                            if (typeof lucide !== "undefined" && lucide && typeof lucide.createIcons === "function") {
                                lucide.createIcons();
                            } else if (typeof window.lucide !== "undefined" && window.lucide && typeof window.lucide.createIcons === "function") {
                                window.lucide.createIcons();
                            }
                        }, 300);
                    });
                } else {
                    setTimeout(function() {
                        if (typeof lucide !== "undefined" && lucide && typeof lucide.createIcons === "function") {
                            lucide.createIcons();
                        } else if (typeof window.lucide !== "undefined" && window.lucide && typeof window.lucide.createIcons === "function") {
                            window.lucide.createIcons();
                        }
                    }, 300);
                }
            ',
				'after'
			);

			// Don't return - continue loading tab controller assets for orders page
		}

		// Load products page-specific assets (for both vortem-products and vortem-orders pages)
		if ( $current_page === 'vortem-products' || $current_page === 'vortem-orders' || strpos( $hook, 'vortem-products' ) !== false || strpos( $hook, 'vortem-orders' ) !== false ) {
			// Enqueue products page CSS
			wp_enqueue_style(
				'vortem-products-page',
				VORTEM_PLUGIN_URL . 'assets/css/products-page.css',
				array(),
				VORTEM_VERSION
			);

			// Add inline style for panel visibility
			wp_add_inline_style(
				'vortem-products-page',
				'
                /* Ensure panels are hidden by default to prevent layout issues on hard refresh */
                #vortem-products-app .panel {
                    display: none !important;
                }
                #vortem-products-app .panel.active {
                    display: block !important;
                }
            '
			);

			// Enqueue email marketing CSS for tab navigation styles
			wp_enqueue_style(
				'vortem-email-marketing',
				VORTEM_PLUGIN_URL . 'assets/css/email-marketing.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue orders CSS (loaded for orders tab, but JS will be lazy-loaded)
			wp_enqueue_style(
				'vortem-orders',
				VORTEM_PLUGIN_URL . 'assets/css/orders.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue tab controller for lazy loading components
			wp_enqueue_script(
				'vortem-tab-controller',
				VORTEM_PLUGIN_URL . 'assets/js/components/tab-controller.js',
				array( 'jquery' ),
				VORTEM_VERSION,
				true
			);

			// Enqueue products inline script (contains all products page functionality)
			wp_enqueue_script(
				'vortem-products-inline',
				VORTEM_PLUGIN_URL . 'assets/js/vortem-products-inline.js',
				array( 'jquery', 'vortem-admin' ),
				VORTEM_VERSION,
				true
			);

			// Localize script with data needed for lazy loading
			wp_localize_script(
				'vortem-tab-controller',
				'vortemTabData',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'vortem_products_nonce' ),
					'baseUrl'         => VORTEM_PLUGIN_URL . 'assets/js/',
					'ordersScriptUrl' => VORTEM_PLUGIN_URL . 'assets/js/orders.js',
					'lucideIconsUrl'  => VORTEM_PLUGIN_URL . 'assets/vendor/lucide/lucide.js',
				)
			);

			// Localize products data for products component
			wp_localize_script(
				'vortem-tab-controller',
				'vortemProducts',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'vortem_products_nonce' ),
				)
			);

			// Localize orders data for orders component (will be used when orders tab is loaded)
			wp_localize_script(
				'vortem-tab-controller',
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
						'pending_payment' => __( 'Pending payment', 'vortem-ai' ),
						'processing'      => __( 'Processing', 'vortem-ai' ),
						'on_hold'         => __( 'On hold', 'vortem-ai' ),
						'completed'       => __( 'Completed', 'vortem-ai' ),
						'cancelled'       => __( 'Cancelled', 'vortem-ai' ),
						'refunded'        => __( 'On hold', 'vortem-ai' ),
						'failed'          => __( 'Failed', 'vortem-ai' ),
						'draft'           => __( 'Draft', 'vortem-ai' ),
					),
					'current_language' => class_exists( 'Vortem_Translation_Manager' ) ? Vortem_Translation_Manager::get_current_language() : 'en',
				)
			);
		}

		// Load email marketing-specific assets
		if ( $current_page === 'vortem-email-marketing' || strpos( $hook, 'vortem-email-marketing' ) !== false ) {
			// Enqueue WordPress editor scripts for TinyMCE
			wp_enqueue_editor();
			wp_enqueue_media();

			// Enqueue email marketing CSS
			wp_enqueue_style(
				'vortem-email-marketing',
				VORTEM_PLUGIN_URL . 'assets/css/email-marketing.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue email marketing JS
			wp_enqueue_script(
				'vortem-email-marketing',
				VORTEM_PLUGIN_URL . 'assets/js/email-marketing.js',
				array( 'jquery', 'editor' ),
				VORTEM_VERSION,
				true
			);

			// Get current language for translations
			$current_language = 'en';
			if ( class_exists( 'Vortem_Translation_Manager' ) ) {
				$current_language = Vortem_Translation_Manager::get_current_language();
			}

			// Localize script for AJAX and translations
			wp_localize_script(
				'vortem-email-marketing',
				'vortemEmailMarketing',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'vortem_email_marketing_nonce' ),
					'strings'         => array(
						'total'                   => __( 'Total', 'vortem-ai' ),
						'sent'                    => __( 'Sent', 'vortem-ai' ),
						'read'                    => __( 'Read', 'vortem-ai' ),
						'revision'                => __( 'Revision', 'vortem-ai' ),
						'sent_emails'             => __( 'Sent Emails', 'vortem-ai' ),
						'export_excel'            => __( 'Export Excel', 'vortem-ai' ),
						'updated'                 => __( 'Updated', 'vortem-ai' ),
						'recipients'              => __( 'Recipients', 'vortem-ai' ),
						'failed'                  => __( 'Failed', 'vortem-ai' ),
						'pending'                 => __( 'Pending', 'vortem-ai' ),
						'show'                    => __( 'Show', 'vortem-ai' ),
						'edit'                    => __( 'Edit', 'vortem-ai' ),
						'delete'                  => __( 'Delete', 'vortem-ai' ),
						'create_new_email'        => __( 'Create New Email', 'vortem-ai' ),
						'send_email'              => __( 'Send Email', 'vortem-ai' ),
						'update_send'             => __( 'Update & Send', 'vortem-ai' ),
						'sending'                 => __( 'Sending...', 'vortem-ai' ),
						'edit_email'              => __( 'Edit Email', 'vortem-ai' ),
						'email_sent_success'      => __( 'Your email has been sent successfully.', 'vortem-ai' ),
						'select'                  => __( 'Select', 'vortem-ai' ),
						'exit_select'             => __( 'Exit Select', 'vortem-ai' ),
						'to'                      => __( 'To:', 'vortem-ai' ),
						'created'                 => __( 'Created:', 'vortem-ai' ),
						'no_data'                 => __( 'No data available', 'vortem-ai' ),
						'loading'                 => __( 'Loading...', 'vortem-ai' ),
						'error'                   => __( 'Error', 'vortem-ai' ),
						'success'                 => __( 'Success', 'vortem-ai' ),
						'confirm_action'          => __( 'Confirm Action', 'vortem-ai' ),
						'are_you_sure'            => __( 'Are you sure you want to proceed?', 'vortem-ai' ),
						'confirm'                 => __( 'Confirm', 'vortem-ai' ),
						'cancel'                  => __( 'Cancel', 'vortem-ai' ),
						'create_email_list'       => __( 'Create Email List', 'vortem-ai' ),
						'update_email_list'       => __( 'Update Email List', 'vortem-ai' ),
						'update_send_list'        => __( 'Update & Send', 'vortem-ai' ),
						'send'                    => __( 'Send', 'vortem-ai' ),
						'left'                    => __( 'left', 'vortem-ai' ),
						'resets'                  => __( 'resets', 'vortem-ai' ),
						'email_sent'              => __( 'Email Sent', 'vortem-ai' ),
						'ok'                      => __( 'OK', 'vortem-ai' ),
						'status_sent'             => __( 'Sent', 'vortem-ai' ),
						'status_failed'           => __( 'Failed', 'vortem-ai' ),
						'status_pending'          => __( 'Pending', 'vortem-ai' ),
						'sent_uppercase'          => __( 'SENT', 'vortem-ai' ),
						'sent_with_colon'         => __( 'Sent:', 'vortem-ai' ),
						'failed_with_colon'       => __( 'Failed:', 'vortem-ai' ),
						'pending_with_colon'      => __( 'Pending:', 'vortem-ai' ),
						'sent_with_checkmark'     => __( 'Sent', 'vortem-ai' ),
						'email_sent_toast'        => __( 'Email sent', 'vortem-ai' ),
						'successfully_sent'       => __( 'Successfully sent', 'vortem-ai' ),
						'email_s'                 => __( 'email(s)', 'vortem-ai' ),
						'email_list_sent_success' => __( 'Your email list has been sent successfully.', 'vortem-ai' ),
						'failed_uppercase'        => __( 'FAILED', 'vortem-ai' ),
						'pending_uppercase'       => __( 'PENDING', 'vortem-ai' ),
						'total_lists'             => __( 'Total Lists', 'vortem-ai' ),
						'total_recipients'        => __( 'Total Recipients', 'vortem-ai' ),
					),
					'currentLanguage' => $current_language,
				)
			);

			return; // Don't load regular admin styles on email marketing page
		}

		if ( $current_page === 'vortem-insights' || strpos( $hook, 'vortem-insights' ) !== false ) {
			// Enqueue insights CSS
			wp_enqueue_style(
				'vortem-insights',
				VORTEM_PLUGIN_URL . 'assets/css/insights.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue insights JS
			wp_enqueue_script(
				'vortem-insights',
				VORTEM_PLUGIN_URL . 'assets/js/insights.js',
				array(),
				VORTEM_VERSION,
				true
			);

			// Get current language for translations
			$current_language = 'en';
			if ( class_exists( 'Vortem_Translation_Manager' ) ) {
				$current_language = Vortem_Translation_Manager::get_current_language();
			}

			// Get API URL and endpoints for refetch functionality
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
			$api_url          = rtrim( Vortem_Config::get_primary_api_server(), '/' );
			$refetch_endpoint = Vortem_Config::get_api_endpoint( 'page_speed_wordpress_refetch' );
			$plugin_version   = defined( 'VORTEM_VERSION' ) ? VORTEM_VERSION : '1.0.6';

			wp_localize_script(
				'vortem-insights',
				'vortemInsights',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'vortem_insights_nonce' ),
					'siteUrl'         => get_site_url(),
					'apiUrl'          => $api_url,
					'refetchEndpoint' => $refetch_endpoint,
					'pluginVersion'   => $plugin_version,
					'strings'         => array(
						'last_analyzed'                 => __( 'Last analyzed:', 'vortem-ai' ),
						'last_updated'                  => __( 'Last updated:', 'vortem-ai' ),
						/* translators: %s: date/time when the item was last updated */
						'last_updated_s'                => __( 'Last updated: %s', 'vortem-ai' ),
						'last_updated_loading'          => __( 'Last updated: Loading...', 'vortem-ai' ),
						'loading'                       => __( 'Loading...', 'vortem-ai' ),
						'performance'                   => __( 'Performance', 'vortem-ai' ),
						'accessibility'                 => __( 'Accessibility', 'vortem-ai' ),
						'best_practices'                => __( 'Best Practices', 'vortem-ai' ),
						'seo'                           => __( 'SEO', 'vortem-ai' ),
						'page_load'                     => __( 'Page Load', 'vortem-ai' ),
						'requests'                      => __( 'Requests', 'vortem-ai' ),
						'total_size'                    => __( 'Total Size', 'vortem-ai' ),
						'first_contentful_paint'        => __( 'First Contentful Paint', 'vortem-ai' ),
						'desktop'                       => __( 'Desktop', 'vortem-ai' ),
						'mobile'                        => __( 'Mobile', 'vortem-ai' ),
						'no_performance_metrics'        => __( 'No performance metrics available', 'vortem-ai' ),
						'no_audit_data'                 => __( 'No audit data available', 'vortem-ai' ),
						'no_audits'                     => __( 'No audits available', 'vortem-ai' ),
						'view_less'                     => __( 'View Less', 'vortem-ai' ),
						'view_more'                     => __( 'View More', 'vortem-ai' ),
						'more'                          => __( 'more', 'vortem-ai' ),
						'no_insights'                   => __( 'No insights available', 'vortem-ai' ),
						'no_performance_issues'         => __( 'No performance issues detected. Your site is performing well!', 'vortem-ai' ),
						'wasted'                        => __( 'Wasted:', 'vortem-ai' ),
						'initializing'                  => __( 'Initializing...', 'vortem-ai' ),
						'connecting_to_server'          => __( 'Connecting to server...', 'vortem-ai' ),
						'fetching_insights_data'        => __( 'Fetching insights data...', 'vortem-ai' ),
						'analyzing_performance_metrics' => __( 'Analyzing performance metrics...', 'vortem-ai' ),
						'processing_audits_insights'    => __( 'Processing audits and insights...', 'vortem-ai' ),
						'finalizing_results'            => __( 'Finalizing results...', 'vortem-ai' ),
						'complete'                      => __( 'Complete!', 'vortem-ai' ),
						'failed_to_load_insights'       => __( 'Failed to load Insights data', 'vortem-ai' ),
						'failed_to_load_insights_error' => __( 'Failed to load Insights data. Please check your connection and try again.', 'vortem-ai' ),
						'failed_to_refetch'             => __( 'Failed to refetch data', 'vortem-ai' ),
						'failed_to_refetch_error'       => __( 'Failed to refetch data. Please check your connection and try again.', 'vortem-ai' ),
						'no_data_available'             => __( 'No data available', 'vortem-ai' ),
						'excellent'                     => __( 'Excellent', 'vortem-ai' ),
						'needs_improvement'             => __( 'Needs Improvement', 'vortem-ai' ),
						'poor'                          => __( 'Poor', 'vortem-ai' ),
						'unknown_error'                 => __( 'Unknown error', 'vortem-ai' ),
						'unknown'                       => __( 'Unknown', 'vortem-ai' ),
						'n_a'                           => __( 'N/A', 'vortem-ai' ),
					),
					'currentLanguage' => $current_language,
				)
			);

			return; // Don't load regular admin styles on insights page
		}

		// Load security page-specific assets
		if ( $current_page === 'vortem-security' || strpos( $hook, 'vortem-security' ) !== false ) {
			// Enqueue security CSS
			wp_enqueue_style(
				'vortem-security',
				VORTEM_PLUGIN_URL . 'assets/css/security.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue security JS
			wp_enqueue_script(
				'vortem-security',
				VORTEM_PLUGIN_URL . 'assets/js/security.js',
				array( 'jquery', 'vortem-logger' ),
				VORTEM_VERSION,
				true // Load in footer
			);

			// Enqueue security results JS (for showing vulnerability results)
			wp_enqueue_script(
				'vortem-security-results',
				VORTEM_PLUGIN_URL . 'assets/js/security-results.js',
				array( 'jquery', 'vortem-security' ),
				VORTEM_VERSION,
				true
			);

			// Get plugin inspector instance to fetch plugin and theme data
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
			$plugin_inspector = Vortem_Plugin_Inspector::get_instance();

			// Get plugin data
			$plugins_data = $plugin_inspector->get_plugin_data();

			// Get theme data
			$themes_data = $plugin_inspector->get_theme_data();

			// Get WordPress version
			global $wp_version;
			$wordpress_version = $wp_version;

			// Get API URLs
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
			$api_server     = Vortem_Config::get_primary_api_server();
			$plugin_api_url = Vortem_Config::build_api_url( $api_server, 'security_wordpress' );
			$theme_api_url  = Vortem_Config::build_api_url( $api_server, 'security_wordpress_theme' );
			$wpcore_api_url = Vortem_Config::build_api_url( $api_server, 'security_wordpress_wp_core' );

			// Get current language for translations
			$current_language = 'en';
			if ( class_exists( 'Vortem_Translation_Manager' ) ) {
				$current_language = Vortem_Translation_Manager::get_current_language();
			}

			// Localize security config (API URLs)
			wp_localize_script(
				'vortem-security',
				'vortemSecurityConfig',
				array(
					'pluginApiUrl' => $plugin_api_url,
					'themeApiUrl'  => $theme_api_url,
					'wpCoreApiUrl' => $wpcore_api_url,
				)
			);

			// Localize plugins data
			wp_localize_script( 'vortem-security', 'vortemSecurityPlugins', $plugins_data );

			// Localize themes data
			wp_localize_script( 'vortem-security', 'vortemSecurityThemes', $themes_data );

			// Localize WordPress version
			wp_localize_script( 'vortem-security', 'vortemWpVersion', $wordpress_version );

			// Localize AJAX config
			wp_localize_script(
				'vortem-security',
				'vortemSecurityAjax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'vortem_security_nonce' ),
				)
			);

			// Localize translation strings
			wp_localize_script(
				'vortem-security',
				'vortemSecurityStrings',
				array(
					'active'                            => __( 'Active', 'vortem-ai' ),
					'inactive'                          => __( 'Inactive', 'vortem-ai' ),
					'version'                           => __( 'Version', 'vortem-ai' ),
					'author'                            => __( 'Author', 'vortem-ai' ),
					'description'                       => __( 'Description', 'vortem-ai' ),
					'file'                              => __( 'File', 'vortem-ai' ),
					'plugin_uri'                        => __( 'Plugin URI', 'vortem-ai' ),
					'last_modified'                     => __( 'Last Modified', 'vortem-ai' ),
					'requires_wp_version'               => __( 'Requires WP Version', 'vortem-ai' ),
					'requires_php'                      => __( 'Requires PHP', 'vortem-ai' ),
					'stylesheet'                        => __( 'Stylesheet', 'vortem-ai' ),
					'theme_uri'                         => __( 'Theme URI', 'vortem-ai' ),
					'wordpress_core'                    => __( 'WordPress Core', 'vortem-ai' ),
					'read_more'                         => __( 'Read more', 'vortem-ai' ),
					'read_less'                         => __( 'Read less', 'vortem-ai' ),
					'vulnerability'                     => __( 'Vulnerability', 'vortem-ai' ),
					'vulnerabilities'                   => __( 'Vulnerabilities', 'vortem-ai' ),
					'checking'                          => __( 'Checking...', 'vortem-ai' ),
					'type'                              => __( 'Type', 'vortem-ai' ),
					'status'                            => __( 'Status', 'vortem-ai' ),
					'your_plugins_are_secure'           => __( 'Your plugins are secure!', 'vortem-ai' ),
					'your_themes_are_secure'            => __( 'Your themes are secure!', 'vortem-ai' ),
					'your_wp_core_is_secure'            => __( 'Your WordPress core is secure!', 'vortem-ai' ),
					'no_security_vulnerabilities_found' => __( 'No security vulnerabilities found', 'vortem-ai' ),
					'published'                         => __( 'Published', 'vortem-ai' ),
					'references'                        => __( 'References', 'vortem-ai' ),
					// New translation strings for security page
					'total_vulnerabilities'             => __( 'Total Vulnerabilities', 'vortem-ai' ),
					'cvss_score_label'                  => __( 'CVSS Score:', 'vortem-ai' ),
					'published_label'                   => __( 'Published:', 'vortem-ai' ),
					'last_modified_label'               => __( 'Last Modified:', 'vortem-ai' ),
					'references_label'                  => __( 'References:', 'vortem-ai' ),
					'cwe_label'                         => __( 'CWE:', 'vortem-ai' ),
					'affected_version_label'            => __( 'Affected Version:', 'vortem-ai' ),
					'fixed_version_label'               => __( 'Fixed Version:', 'vortem-ai' ),
					'version_label'                     => __( 'VERSION', 'vortem-ai' ),
					'author_label'                      => __( 'AUTHOR', 'vortem-ai' ),
					'requires_wp_label'                 => __( 'REQUIRES WP', 'vortem-ai' ),
					'requires_php_label'                => __( 'REQUIRES PHP', 'vortem-ai' ),
					'last_updated_label'                => __( 'Last updated:', 'vortem-ai' ),
					'view_page'                         => __( 'View Page', 'vortem-ai' ),
					'search_plugins'                    => __( 'Search plugins...', 'vortem-ai' ),
					'search_themes'                     => __( 'Search themes...', 'vortem-ai' ),
					'previous'                          => __( 'Previous', 'vortem-ai' ),
					'next'                              => __( 'Next', 'vortem-ai' ),
					'showing'                           => __( 'Showing', 'vortem-ai' ),
					'of'                                => __( 'of', 'vortem-ai' ),
					'found'                             => __( 'Found', 'vortem-ai' ),
					// Overview tab strings (summary cards & lists)
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
					// Overview tooltip strings (for the 6 summary cards)
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
					'no_known_vulnerabilities'          => __( 'No known vulnerabilities detected', 'vortem-ai' ),
					'loading'                           => __( 'Loading...', 'vortem-ai' ),
					// Overview status translations
					'scanning'                          => __( 'Scanning...', 'vortem-ai' ),
					'analyzing_wordpress'               => __( 'Analyzing your WordPress installation for security vulnerabilities', 'vortem-ai' ),
					'your_site_is_secure'               => __( 'Your site is secure!', 'vortem-ai' ),
					'all_items_are_secure'              => __( 'All your plugins, themes, and WordPress core are secure and up to date.', 'vortem-ai' ),
					'critical_vulnerabilities_found'    => __( 'Critical vulnerabilities detected!', 'vortem-ai' ),
					'immediate_action_required'         => __( 'Immediate action is required. Please update or remove affected items as soon as possible.', 'vortem-ai' ),
					'high_vulnerabilities_found'        => __( 'High severity vulnerabilities found', 'vortem-ai' ),
					'action_recommended'                => __( 'Action is recommended. Please review and update affected items.', 'vortem-ai' ),
					'some_vulnerabilities_found'        => __( 'Some vulnerabilities found', 'vortem-ai' ),
					'review_recommended'                => __( 'Review recommended. Monitor and update affected items when possible.', 'vortem-ai' ),
					'currentLanguage'                   => $current_language,
				)
			);

			return; // Don't load regular admin styles on security page
		}

		// Load settings page-specific assets
		if ( $current_page === 'vortem-settings' || strpos( $hook, 'vortem-settings' ) !== false ) {
			// Enqueue settings CSS
			wp_enqueue_style(
				'vortem-settings',
				VORTEM_PLUGIN_URL . 'assets/css/setting.css',
				array(),
				VORTEM_VERSION
			);

			// Enqueue settings validation JS
			wp_enqueue_script(
				'vortem-settings-validation',
				VORTEM_PLUGIN_URL . 'assets/js/vortem-settings-validation.js',
				array( 'jquery', 'vortem-admin' ),
				VORTEM_VERSION,
				true
			);

			// Add inline style for AliExpress modal
			wp_add_inline_style(
				'vortem-settings',
				'
                .vortem-aliexpress-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 100000;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                }
                .vortem-aliexpress-modal-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.7);
                }
                .vortem-aliexpress-modal-content {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: #fff;
                    border-radius: 4px;
                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                    min-width: 400px;
                    max-width: 500px;
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }
                .vortem-aliexpress-modal-header {
                    padding: 20px 24px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .vortem-aliexpress-modal-header h3 {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #1d2327;
                }
                .vortem-aliexpress-modal-close {
                    background: none;
                    border: none;
                    font-size: 28px;
                    line-height: 1;
                    color: #787c82;
                    cursor: pointer;
                    padding: 0;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .vortem-aliexpress-modal-close:hover {
                    color: #1d2327;
                }
                .vortem-aliexpress-modal-body {
                    padding: 24px;
                    flex: 1;
                    overflow-y: auto;
                }
                .vortem-aliexpress-modal-body p {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.5;
                    color: #1d2327;
                }
                .vortem-aliexpress-modal-footer {
                    padding: 16px 24px;
                    border-top: 1px solid #ddd;
                    display: flex;
                    justify-content: flex-end;
                    align-items: center;
                    gap: 10px;
                }
                .vortem-aliexpress-modal-footer .button {
                    margin: 0;
                }
                .vortem-aliexpress-modal-footer > div {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
            '
			);
		}

		wp_enqueue_style(
			'vortem-pagination',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-pagination.css',
			array(),
			VORTEM_VERSION
		);

		// Enqueue overview dashboard CSS and JS for overview page
		if ( $current_page === 'vortem-owerview' || $current_page === 'vortem-overview' || strpos( $hook, 'vortem-owerview' ) !== false || strpos( $hook, 'vortem-overview' ) !== false ) {
			wp_enqueue_style(
				'vortem-overview-dashboard',
				VORTEM_PLUGIN_URL . 'assets/css/overview-dashboard.css',
				array(),
				VORTEM_VERSION
			);

			wp_enqueue_script(
				'vortem-overview-dashboard',
				VORTEM_PLUGIN_URL . 'assets/js/overview-dashboard.js',
				array( 'jquery' ),
				VORTEM_VERSION,
				true
			);

			wp_localize_script(
				'vortem-overview-dashboard',
				'vortemOverview',
				array(
					'restUrl'   => rest_url( 'vortem/v1/' ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'endpoints' => array(
						'securityVulns'       => 'overview/security-vulns',
						'insightsPerformance' => 'overview/insights-performance',
						'emailsTotal'         => 'overview/emails-total',
						'metrics'             => 'metrics/',
					),
					'i18n'      => array(
						'average'     => __( 'Average', 'vortem-ai' ),
						'desktop'     => __( 'Desktop', 'vortem-ai' ),
						'mobile'      => __( 'Mobile', 'vortem-ai' ),
						'performance' => __( 'Performance', 'vortem-ai' ),
					),
				)
			);
		}

		wp_enqueue_style(
			'vortem-admin',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-new.css',
			array( 'vortem-pagination' ),
			VORTEM_VERSION // Force cache refresh
		);

		// Add inline CSS as backup to ensure styling always loads
		wp_add_inline_style(
			'vortem-admin',
			'
            /* Session Token Eye Toggle Button */
            #toggle-session-visibility {
                color: #666 !important;
                transition: color 0.2s ease !important;
            }
            #toggle-session-visibility:hover:not(:disabled) {
                color: #2271b1 !important;
            }
            #toggle-session-visibility svg {
                vertical-align: middle !important;
            }
            
            /* Full-width Dashboard and Products - Same as Email Marketing section */
            body.admin_page_vortem-owerview #wpcontent,
            body.admin_page_vortem-overview #wpcontent,
            body.admin_page_vortem-products #wpcontent {
                padding-left: 0 !important;
            }
            
            body.admin_page_vortem-owerview .wrap,
            body.admin_page_vortem-overview .wrap,
            body.admin_page_vortem-products .wrap {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .vortem-page-wrapper.dashboard,
            .vortem-page-wrapper.products {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border-radius: 10px 10px 10px 10px !important;
                box-shadow: none !important;
                border: none !important;
            }
            
            .vortem-page-wrapper.dashboard .vortem-page-header,
            .vortem-page-wrapper.products .vortem-page-header {
                border-radius: 10px 10px 0 0 !important;
            }
            
            .vortem-page-wrapper.dashboard .vortem-page-content,
            .vortem-page-wrapper.products .vortem-page-content {
                padding: 20px !important;
                border-radius: 0 0 10px 10px !important;
            }
            
            /* Main wrapper - centered with proper spacing */
            .vortem-page-wrapper {
                max-width: 1200px !important;
                margin: 30px auto !important;
                background: #ffffff !important;
                border-radius: 12px !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important;
                overflow: hidden !important;
                border: 1px solid #e8e8e8 !important;
            }
            
            /* Header styling - improved colors and spacing */
            .vortem-page-header {
                background: linear-gradient(135deg, #044466 0%, #0099d6 100%) !important;
                color: white !important;
                padding: 40px 30px !important;
                text-align: center !important;
                position: relative !important;
            }
            
            .vortem-page-header::before {
                content: "" !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%) !important;
                pointer-events: none !important;
            }
            
            .vortem-page-header .vortem-header {
                background: transparent !important;
                margin-bottom: 0 !important;
                padding: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 20px !important;
                position: relative !important;
                z-index: 1 !important;
            }
            
            .vortem-page-header .vortem-header h1 {
                color: white !important;
                margin: 0 !important;
                font-size: 32px !important;
                font-weight: 700 !important;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
                letter-spacing: -0.5px !important;
            }
            
            .vortem-page-header .vortem-logo {
                width: 72px !important;
                height: 72px !important;
                object-fit: contain !important;
                filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2)) !important;
            }
            
            /* Content area - improved padding and spacing */
            .vortem-page-content {
                padding: 15px 30px 15px 30px !important;
                    min-height: 330px !important;
                background: #fafbfc !important;
            }
            
            /* Reset main content containers */
            .vortem-dashboard,
            .vortem-products,
            .vortem-analytics,
            .vortem-settings {
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Content boxes - improved styling */
            .vortem-stats,
            .vortem-site-navigation,
            .products-stats,
            .products-actions,
            .orders-stats,
            .orders-actions,
            .form-table {
                background: #ffffff !important;
                border: 1px solid #e8e8e8 !important;
                border-radius: 12px !important;
                padding: 30px !important;
                margin-bottom: 25px !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
                transition: all 0.3s ease !important;
            }
            
            /* Hover effects for content boxes */
            .vortem-stats:hover,
            .vortem-site-navigation:hover,
            .products-stats:hover,
            .products-actions:hover,
            .orders-stats:hover,
            .orders-actions:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
            }
            
            /* Stats grid improvements */
            .vortem-stats {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
                gap: 25px !important;
                padding: 35px !important;
            }
            
            .stat-box {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
                border: 1px solid #e8e8e8 !important;
                border-radius: 10px !important;
                padding: 25px !important;
                text-align: center !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
                transition: all 0.3s ease !important;
            }
            
            .stat-box:hover {
                transform: translateY(-3px) !important;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1) !important;
            }
            
            .stat-box h3 {
                margin: 0 0 15px 0 !important;
                font-size: 14px !important;
                color: #6c757d !important;
                text-transform: uppercase !important;
                letter-spacing: 1px !important;
                font-weight: 600 !important;
            }
            
            .stat-value {
                font-size: 28px !important;
                font-weight: 700 !important;
                color: #044466 !important;
                margin: 0 !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
            }
            
            /* Site navigation improvements */
            .vortem-site-navigation {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
                border: 1px solid #e8e8e8 !important;
            }
            
            .vortem-site-navigation h2 {
                margin: 0 0 15px 0 !important;
                font-size: 22px !important;
                color: #2c3e50 !important;
                font-weight: 700 !important;
            }
            
            .vortem-site-navigation p {
                margin: 0 0 25px 0 !important;
                color: #6c757d !important;
                font-size: 15px !important;
                line-height: 1.6 !important;
            }
            
            /* Form table improvements */
            .form-table {
                background: #ffffff !important;
                border-radius: 12px !important;
                overflow: hidden !important;
            }
            
            .form-table th {
                background: #f8f9fa !important;
                border-bottom: 2px solid #e8e8e8 !important;
                padding: 20px !important;
                font-weight: 700 !important;
                color: #2c3e50 !important;
                font-size: 15px !important;
            }
            
            .form-table td {
                padding: 20px !important;
                border-bottom: 1px solid #f0f0f1 !important;
                background: #ffffff !important;
            }
            
            /* Notice improvements */
            .notice {
                border-radius: 8px !important;
                padding: 20px !important;
                margin: 25px 0 !important;
                border-left: 4px solid !important;
            }
            
            .notice-success {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
                border-left-color: #28a745 !important;
                color: #155724 !important;
            }
            
            .notice-warning {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
                border-left-color: #ffc107 !important;
                color: #856404 !important;
            }
            
            .notice-error {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
                border-left-color: #dc3545 !important;
                color: #721c24 !important;
            }
            
            .notice-info {
                background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%) !important;
                border-left-color: #17a2b8 !important;
                color: #0c5460 !important;
            }
            
            /* Position notice-info elements at top of vortem-page-content with full width */
            .vortem-page-content .notice.notice-info {
                position: relative !important;
                width: 100% !important;
                margin: 0 0 25px 0 !important;
                border-radius: 8px !important;
                padding: 20px !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
                background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%) !important;
                border-left: 4px solid #17a2b8 !important;
                color: #0c5460 !important;
                font-size: 14px !important;
                line-height: 1.5 !important;
            }
            
            /* Also target all notice-info elements on Vortem pages */
            .wrap .notice.notice-info {
                position: relative !important;
                width: 96% !important;
                margin: 0 0 25px 0 !important;
                border-radius: 8px !important;
                padding: 20px !important;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
                background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%) !important;
                border-left: 4px solid #17a2b8 !important;
                color: #0c5460 !important;
                font-size: 14px !important;
                line-height: 1.5 !important;
            }
            
            /* Custom Vortem notice styling */
            .vortem-custom-notice {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
                font-size: 14px !important;
                line-height: 1.5 !important;
                border-radius: 8px !important;
                padding: 20px !important;
                margin: 0 0 25px 0 !important;
                border-left: 4px solid !important;
            }
            
            .vortem-custom-notice p {
                margin: 0 !important;
            }
            
            .vortem-custom-notice .button {
                margin-top: 10px !important;
            }
            
            .vortem-notice-success {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
                border-left-color: #28a745 !important;
                color: #155724 !important;
            }
            
            .vortem-notice-error {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
                border-left-color: #dc3545 !important;
                color: #721c24 !important;
            }
            
            .vortem-notice-warning {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
                border-left-color: #ffc107 !important;
                color: #856404 !important;
            }
            
            .vortem-notice-info {
                background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%) !important;
                border-left-color: #17a2b8 !important;
                color: #0c5460 !important;
            }
            
            /* Auto-fade-out animation for plugin notices */
            .vortem-plugin-notice {
                transition: opacity 0.5s ease-out, transform 0.5s ease-out !important;
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
            
            .vortem-plugin-notice.vortem-fading-out {
                opacity: 0 !important;
                transform: translateY(-10px) !important;
                pointer-events: none !important;
            }
            
            /* RTL compatibility for fade-out */
            .rtl .vortem-plugin-notice.vortem-fading-out,
            [dir="rtl"] .vortem-plugin-notice.vortem-fading-out {
                transform: translateY(-10px) !important;
            }
            
        '
		);

		// Add inline JavaScript to remove external notices as a final safeguard
		wp_add_inline_script(
			'vortem-admin',
			'
            (function() {
                function removeExternalNotices() {
                    var notices = document.querySelectorAll(".notice:not(.vortem-custom-notice):not([class*=\"vortem-notice\"])");
                    notices.forEach(function(notice) { notice.remove(); });
                    var updateNags = document.querySelectorAll(".update-nag:not(.vortem-custom-notice):not([class*=\"vortem-notice\"])");
                    updateNags.forEach(function(nag) { nag.remove(); });
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", removeExternalNotices);
                } else {
                    removeExternalNotices();
                }
                setTimeout(removeExternalNotices, 100);
                setTimeout(removeExternalNotices, 500);
                setTimeout(removeExternalNotices, 1000);
                var observer = new MutationObserver(removeExternalNotices);
                if (document.body) {
                    observer.observe(document.body, { childList: true, subtree: true });
                }
            })();
        ',
			'after'
		);

		// Enqueue logger utility first (no dependencies)
		wp_enqueue_script(
			'vortem-logger',
			VORTEM_PLUGIN_URL . 'assets/js/vortem-logger.js',
			array(),
			VORTEM_VERSION,
			false // Load in header so it's available early
		);

		// Enqueue main admin script
		wp_enqueue_script(
			'vortem-admin',
			VORTEM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'vortem-logger' ),
			VORTEM_VERSION, // Force cache refresh
			true
		);

		// Enqueue clean button handler script
		wp_enqueue_script(
			'vortem-buttons',
			VORTEM_PLUGIN_URL . 'assets/js/vortem-buttons.js',
			array( 'jquery', 'vortem-admin' ),
			VORTEM_VERSION, // Force cache refresh
			true
		);

		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'vortem_admin_nonce' );

		// Get current language
		$current_language = 'en';
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$current_language = Vortem_Translation_Manager::get_current_language();
		}

		$current_currency = get_option( 'vortem_currency', 'USD' );

		$localize_data = array(
			'ajax_url'              => $ajax_url,
			'nonce'                 => $nonce,
			'current_language'      => $current_language,
			'nonce_import'          => wp_create_nonce( 'vortem_import_products' ),
			'nonce_details'         => wp_create_nonce( 'vortem_get_product_details' ),
			'nonce_refresh_imports' => wp_create_nonce( 'vortem_refresh_imports_counter' ),
			'products_per_page'     => get_option( 'vortem_products_per_page', 16 ),
			'currency_code'         => $current_currency,
			'is_development'        => Vortem_Config::is_development(),
			'site_url'              => home_url( '/' ),
			'plugin_url'            => VORTEM_PLUGIN_URL,
			'strings'               => array(
				'import'                                   => $this->get_translated_string_with_svg( '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import', 'import' ),
				'delete'                                   => $this->get_translated_string_with_svg( '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete', 'delete' ),
				'price'                                    => __( 'Price', 'vortem-ai' ),
				'new'                                      => __( 'NEW', 'vortem-ai' ),
				'added'                                    => __( 'Added', 'vortem-ai' ),
				'exists'                                   => __( 'Exists', 'vortem-ai' ),
				'showing'                                  => __( 'Showing', 'vortem-ai' ),
				'please_wait_import'                       => __( 'Please wait while we import your product', 'vortem-ai' ),
				'importing_product_draft'                  => __( 'Importing product as draft...', 'vortem-ai' ),
				'will_create_product'                      => __( 'This will create the product in WooCommerce', 'vortem-ai' ),
				'importing_product_seo'                    => __( 'Importing product with SEO optimization...', 'vortem-ai' ),
				'seo_processing_items'                     => __( 'Processing SEO optimizations: Keyphrase, SEO Title, SEO Description, Meta Description, Tags, Meta Title, and Headings', 'vortem-ai' ),
				'seo_item_keyphrase'                       => __( 'Keyphrase', 'vortem-ai' ),
				'seo_item_seo_title'                       => __( 'SEO Title', 'vortem-ai' ),
				'seo_item_seo_description'                 => __( 'SEO Description', 'vortem-ai' ),
				'seo_item_meta_description'                => __( 'Meta Description', 'vortem-ai' ),
				'seo_item_tags'                            => __( 'Tags', 'vortem-ai' ),
				'seo_item_meta_title'                      => __( 'Meta Title', 'vortem-ai' ),
				'seo_item_headings'                        => __( 'Headings', 'vortem-ai' ),
				'checking_product_status'                  => __( 'Checking product status...', 'vortem-ai' ),
				'please_wait_check'                        => __( 'Please wait while we check if the product exists', 'vortem-ai' ),
				'loading_products'                         => __( 'Loading products...', 'vortem-ai' ),
				'Active'                                   => __( 'Active', 'vortem-ai' ),
				'Not Set'                                  => __( 'Not Set', 'vortem-ai' ),
				'Error'                                    => __( 'Error', 'vortem-ai' ),
				'preview'                                  => __( 'Preview', 'vortem-ai' ),
				'Positive'                                 => __( 'Positive', 'vortem-ai' ),
				'Neutral'                                  => __( 'Neutral', 'vortem-ai' ),
				'Negative'                                 => __( 'Negative', 'vortem-ai' ),
				'Rising'                                   => __( 'Rising', 'vortem-ai' ),
				'Stable'                                   => __( 'Stable', 'vortem-ai' ),
				'Declining'                                => __( 'Declining', 'vortem-ai' ),
				'sales'                                    => __( 'sales', 'vortem-ai' ),
				'Loading trend products...'                => __( 'Loading trend products...', 'vortem-ai' ),
				'Loading TikTok products...'               => __( 'Loading TikTok products...', 'vortem-ai' ),
				'Error loading TikTok products:'           => __( 'Error loading TikTok products:', 'vortem-ai' ),
				'Failed to load TikTok products:'          => __( 'Failed to load TikTok products:', 'vortem-ai' ),
				'View on Amazon'                           => __( 'View on Amazon', 'vortem-ai' ),
				'Release:'                                 => __( 'Release:', 'vortem-ai' ),
				'Price N/A'                                => __( 'Price N/A', 'vortem-ai' ),
				'Previous'                                 => __( 'Previous', 'vortem-ai' ),
				'Next'                                     => __( 'Next', 'vortem-ai' ),
				'In Stock'                                 => __( 'In Stock', 'vortem-ai' ),
				'Sentiment'                                => __( 'Sentiment', 'vortem-ai' ),
				'Showing'                                  => __( 'Showing', 'vortem-ai' ),
				'products'                                 => __( 'products', 'vortem-ai' ),
				'of'                                       => __( 'of', 'vortem-ai' ),
				'Popularity'                               => __( 'Popularity', 'vortem-ai' ),
				'Impressions'                              => __( 'Impressions', 'vortem-ai' ),
				'Engagement'                               => __( 'Engagement', 'vortem-ai' ),
				'Performance'                              => __( 'Performance', 'vortem-ai' ),
				'Videos'                                   => __( 'Videos', 'vortem-ai' ),
				'Hashtags'                                 => __( '# Hashtags', 'vortem-ai' ),
				'ViewRate6s'                               => __( 'View Rate (6s)', 'vortem-ai' ),
				'Cost'                                     => __( 'Cost', 'vortem-ai' ),
				'Page'                                     => __( 'Page', 'vortem-ai' ),
				// Products page strings
				'importing'                                => __( 'Importing...', 'vortem-ai' ),
				'products_imported_successfully'           => __( 'Products imported successfully!', 'vortem-ai' ),
				'unknown_error_occurred'                   => __( 'Unknown error occurred', 'vortem-ai' ),
				'import_failed'                            => __( 'Import failed: ', 'vortem-ai' ),
				'import_failed_please_try_again'           => __( 'Import failed. Please try again.', 'vortem-ai' ),
				'import_to_woocommerce'                    => __( 'Import to WooCommerce', 'vortem-ai' ),
				'already_imported'                         => __( 'Already Imported', 'vortem-ai' ),
				'product_imported_successfully'            => __( 'Product imported successfully!', 'vortem-ai' ),
				'please_select_a_bulk_action'              => __( 'Please select a bulk action.', 'vortem-ai' ),
				'please_select_at_least_one_product'       => __( 'Please select at least one product.', 'vortem-ai' ),
				'are_you_sure_import_selected'             => __( 'Are you sure you want to import the selected products to WooCommerce?', 'vortem-ai' ),
				'are_you_sure_delete_selected'             => __( 'Are you sure you want to permanently delete the selected products? This will remove them from the database and WordPress, including all images. This action cannot be undone.', 'vortem-ai' ),
				'are_you_sure_restore_selected'            => __( 'Are you sure you want to restore the selected products from trash?', 'vortem-ai' ),
				'are_you_sure_permanently_delete_selected' => __( 'Are you sure you want to permanently delete the selected products? This action cannot be undone.', 'vortem-ai' ),
				'processing'                               => __( 'Processing...', 'vortem-ai' ),
				'bulk_action_completed_successfully'       => __( 'Bulk action completed successfully!', 'vortem-ai' ),
				'bulk_action_failed'                       => __( 'Bulk action failed: ', 'vortem-ai' ),
				'bulk_action_failed_please_try_again'      => __( 'Bulk action failed. Please try again.', 'vortem-ai' ),
				'apply'                                    => __( 'Apply', 'vortem-ai' ),
				'are_you_sure_permanently_delete_this'     => __( 'Are you sure you want to permanently delete this product? This will remove it from the database and WordPress, including all images. This action cannot be undone.', 'vortem-ai' ),
				'deleting'                                 => __( 'Deleting...', 'vortem-ai' ),
				'failed_to_trash_product'                  => __( 'Failed to trash product: ', 'vortem-ai' ),
				'trash'                                    => __( 'Trash', 'vortem-ai' ),
				'failed_to_trash_product_please_try_again' => __( 'Failed to trash product. Please try again.', 'vortem-ai' ),
				'are_you_sure_restore_this'                => __( 'Are you sure you want to restore this product from trash?', 'vortem-ai' ),
				'restoring'                                => __( 'Restoring...', 'vortem-ai' ),
				'failed_to_restore_product'                => __( 'Failed to restore product: ', 'vortem-ai' ),
				'restore'                                  => __( 'Restore', 'vortem-ai' ),
				'failed_to_restore_product_please_try_again' => __( 'Failed to restore product. Please try again.', 'vortem-ai' ),
				'failed_to_import_product_as_draft'        => __( 'Failed to import product as draft: ', 'vortem-ai' ),
				'failed_to_import_product_as_draft_please_try_again' => __( 'Failed to import product as draft. Please try again.', 'vortem-ai' ),
				'failed_to_get_product_details'            => __( 'Failed to get product details.', 'vortem-ai' ),
				'loading'                                  => __( 'Loading...', 'vortem-ai' ),
				'failed_to_load_product_details'           => __( 'Failed to load product details.', 'vortem-ai' ),
				'load_more_categories'                     => __( 'Load More Categories', 'vortem-ai' ),
				'all_categories'                           => __( 'All Categories', 'vortem-ai' ),
				'minimum_price_greater_than_maximum'       => __( 'Minimum price cannot be greater than maximum price', 'vortem-ai' ),
				'product_id_not_found'                     => __( 'Product ID not found', 'vortem-ai' ),
				'are_you_sure_delete_this'                 => __( 'Are you sure you want to delete this product?', 'vortem-ai' ),
				'product_deleted_successfully'             => __( 'Product deleted successfully', 'vortem-ai' ),
				'delete_failed'                            => __( 'Delete failed: ', 'vortem-ai' ),
				'delete_failed_please_try_again'           => __( 'Delete failed. Please try again.', 'vortem-ai' ),
				'show_all_products'                        => __( 'Show All Products', 'vortem-ai' ),
				'show_all_added_products'                  => __( 'Show All Added Products', 'vortem-ai' ),
				'loading_trend_products'                   => __( 'Loading trend products...', 'vortem-ai' ),
				'failed_to_load_tiktok_products'           => __( 'Failed to load TikTok products:', 'vortem-ai' ),
				'error_loading_tiktok_products'            => __( 'Error loading TikTok products:', 'vortem-ai' ),
				'in_stock'                                 => __( 'In Stock', 'vortem-ai' ),
				'price_na'                                 => __( 'Price N/A', 'vortem-ai' ),
				'sentiment'                                => __( 'Sentiment', 'vortem-ai' ),
				'popularity'                               => __( 'Popularity', 'vortem-ai' ),
				'impressions'                              => __( 'Impressions', 'vortem-ai' ),
				'engagement'                               => __( 'Engagement', 'vortem-ai' ),
				'performance'                              => __( 'Performance', 'vortem-ai' ),
				'videos'                                   => __( 'Videos', 'vortem-ai' ),
				'hashtags'                                 => __( '# Hashtags', 'vortem-ai' ),
				'view_rate_6s'                             => __( 'View Rate (6s)', 'vortem-ai' ),
				'cost'                                     => __( 'Cost', 'vortem-ai' ),
				'page'                                     => __( 'Page', 'vortem-ai' ),
				'previous'                                 => __( 'Previous', 'vortem-ai' ),
				'next'                                     => __( 'Next', 'vortem-ai' ),
				'delete_confirmation'                      => __( 'Are you sure you want to delete this product?', 'vortem-ai' ),
				'error_loading_details'                    => __( 'Error loading details', 'vortem-ai' ),
				// Settings page strings
				'products_per_page_min_error'              => __( 'Products per page must be at least 1. Please enter a valid value.', 'vortem-ai' ),
				'products_per_page_max_error'              => __( 'Products per page cannot exceed 100. Please enter a value between 1 and 100.', 'vortem-ai' ),
				'products_per_page_min_title'              => __( 'Products per page must be at least 1', 'vortem-ai' ),
				'products_per_page_max_title'              => __( 'Products per page cannot exceed 100', 'vortem-ai' ),
			),
		);

		wp_localize_script( 'vortem-admin', 'vortem_admin', $localize_data );
		wp_localize_script( 'vortem-buttons', 'vortem_admin', $localize_data );

		// Add inline CSS for product cards and styling fixes
		wp_add_inline_style(
			'vortem-admin',
			'
            
            /* Product Card Styles - Fixed Layout - INLINE CSS */
            .vortem-product-card {
                border: 1px solid #e1e1e1 !important;
                border-radius: 12px !important;
                background: #ffffff !important;
                overflow: hidden !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
                transition: all 0.3s ease !important;
                display: flex !important;
                flex-direction: column !important;
                position: relative !important;
                min-height: 600px !important;
                max-height: 792px !important;
                margin-bottom: 20px !important;
            }

            .vortem-product-card:hover {
                box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
                transform: translateY(-2px) !important;
            }

            .product-image-container {
                width: 100% !important;
                height: 265px !important;
                overflow: hidden !important;
                background: #f5f5f5 !important;
                position: relative !important;
                margin-bottom: 15px !important;
            }

            .product-image-container img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                transition: transform 0.3s ease !important;
            }

            .product-status-badge {
                position: absolute !important;
                top: 12px !important;
                right: 12px !important;
                padding: 6px 12px !important;
                border-radius: 20px !important;
                font-size: 11px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                z-index: 10 !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                background: #0073aa !important;
                color: white !important;
            }

            .no-image-placeholder {
                width: 100% !important;
                height: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color: #999 !important;
                font-size: 14px !important;
                background: #f5f5f5 !important;
            }

            .vortem-product-card:hover .product-image-container img {
                transform: scale(1.05) !important;
            }

            .product-content-section {
                padding: 20px !important;
                flex-grow: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 15px !important;
            }

            .product-title-section {
                margin-bottom: 15px !important;
            }

            .product-title-section h3 {
                margin: 0 0 8px 0 !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                color: #1d2327 !important;
                line-height: 1.4 !important;
                display: -webkit-box !important;
                -webkit-line-clamp: 2 !important;
                -webkit-box-orient: vertical !important;
                overflow: hidden !important;
            }

            .product-meta-section {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
                margin-bottom: 15px !important;
            }

            .product-sku-container {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding: 8px 12px !important;
                background: #f9f9f9 !important;
                border-radius: 6px !important;
                border: 1px solid #e1e1e1 !important;
            }

            .product-sku-label {
                color: #666 !important;
                font-size: 12px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }

            .product-sku-value {
                color: #333 !important;
                font-size: 12px !important;
                font-family: monospace !important;
                font-weight: 500 !important;
            }

            .product-price-container {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding: 8px 12px !important;
                background: linear-gradient(135deg, #f0f7ff 0%, #e3f2fd 100%) !important;
                border-radius: 6px !important;
                border-left: 3px solid #2271b1 !important;
            }

            .product-price-label {
                color: #2271b1 !important;
                font-size: 12px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }

            .product-price-value {
                color: #2271b1 !important;
                font-size: 16px !important;
                font-weight: 700 !important;
            }

            .product-description-section {
                margin-bottom: 20px !important;
                flex-grow: 1 !important;
            }

            .product-description-text {
                color: #666 !important;
                font-size: 12px !important;
                margin: 0 !important;
                line-height: 1.4 !important;
                display: -webkit-box !important;
                -webkit-line-clamp: 4 !important;
                -webkit-box-orient: vertical !important;
                overflow: hidden !important;
            }

            .product-actions-section {
                margin-top: auto !important;
                padding-top: 15px !important;
                border-top: 1px solid #f0f0f1 !important;
            }

            .product-actions-buttons {
                display: flex !important;
                gap: 10px !important;
                flex-wrap: wrap !important;
            }

            .product-actions-buttons .button {
                flex: 1 !important;
                min-width: 120px !important;
                text-align: center !important;
                padding: 8px 12px !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                border-radius: 6px !important;
                transition: all 0.3s ease !important;
            }

            .import-product-btn {
                background: #0073aa !important;
                border-color: #0073aa !important;
                color: white !important;
            }

            .import-product-btn:hover {
                background: #005a87 !important;
                border-color: #005a87 !important;
                color: white !important;
            }

            .delete-product-btn {
                background: #dc3232 !important;
                border-color: #dc3232 !important;
                color: white !important;
            }

            .delete-product-btn:hover {
                background: #a00 !important;
                border-color: #a00 !important;
                color: white !important;
            }

            /* Products Grid Layout */
            .products-grid {
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important;
                gap: 24px !important;
                margin-top: 24px !important;
                padding: 0 !important;
                transition: opacity 0.3s ease;
            }
            
            .products-grid .vortem-product-card {
                transition: transform 0.3s ease, opacity 0.3s ease;
            }

            /* Responsive Design for Product Cards */
            @media (max-width: 768px) {
                .products-grid {
                    grid-template-columns: 1fr !important;
                    gap: 16px !important;
                }
                
                .vortem-product-card {
                    min-height: 500px !important;
                    max-height: 550px !important;
                }
                
                .product-image-container {
                    height: 200px !important;
                }
                
                .product-content-section {
                    padding: 15px !important;
                }
                
                .product-actions-buttons {
                    flex-direction: column !important;
                }
                
                .product-actions-buttons .button {
                    min-width: auto !important;
                }
            }
            
        '
		);

		// Add inline CSS for toast animations and force table layout
		wp_add_inline_style(
			'vortem-admin',
			'
            @keyframes slideInDown {
                from { transform: translateY(-100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .vortem-toast {
                animation: slideInDown 0.5s ease-out, fadeIn 0.5s ease-out;
                position: relative;
                z-index: 9999;
            }

            /* Vortem AI Site Navigation Buttons */
            .vortem-site-navigation {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 25px;
                margin-bottom: 30px;
            }
            
            .vortem-site-navigation h2 {
                margin: 0 0 10px 0;
                font-size: 18px;
                color: #1d2327;
                font-weight: 600;
            }
            
            .vortem-site-navigation p {
                margin: 0 0 20px 0;
                color: #646970;
                font-size: 14px;
            }
            
            .vortem-site-buttons {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .vortem-site-btn {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 15px 20px;
                background: #fff;
                border: 2px solid #e1e1e1;
                border-radius: 8px;
                text-decoration: none;
                color: #1d2327;
                font-weight: 500;
                font-size: 14px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            
            .vortem-site-btn:hover {
                border-color: #2271b1;
                background: #f0f7ff;
                color: #2271b1;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
                text-decoration: none;
            }
            
            .vortem-site-btn .btn-icon {
                font-size: 20px;
                line-height: 1;
            }
            
            .vortem-site-btn .btn-text {
                font-weight: 600;
            }
            
            /* Individual button colors */
            .vortem-btn-home:hover {
                border-color: #00a0d2;
                background: #e8f4fd;
                color: #00a0d2;
            }
            
            .vortem-btn-account:hover {
                border-color: #46b450;
                background: #e8f5e8;
                color: #46b450;
            }
            
            .vortem-btn-support:hover {
                border-color: #ff6b35;
                background: #fff2ed;
                color: #ff6b35;
            }
            
            .vortem-btn-docs:hover {
                border-color: #8b5cf6;
                background: #f3f0ff;
                color: #8b5cf6;
            }
            
            .vortem-btn-terms:hover {
                border-color: #64748b;
                background: #f1f5f9;
                color: #64748b;
            }
            
            /* Responsive design for buttons */
            @media (max-width: 768px) {
                .vortem-site-buttons {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }
                
                .vortem-site-btn {
                    padding: 12px 16px;
                    font-size: 13px;
                }
                
                .vortem-site-btn .btn-icon {
                    font-size: 18px;
                }
            }
        '
		);
	}

	/**
	 * Show auth success notice
	 */
	private function show_auth_success_notice() {
		$validation_success = get_option( 'vortem_auth_success', false );
		$validation_time    = get_option( 'vortem_auth_success_time', '' );

		if ( $validation_success && $validation_time ) {
			// Show notice for 24 hours after validation
			$validation_timestamp = strtotime( $validation_time );
			$current_timestamp    = time();

			if ( ( $current_timestamp - $validation_timestamp ) < 86400 ) { // 24 hours
				// Check if setup is completed
				$setup_completed = $this->is_setup_completed();

				if ( $setup_completed ) {
					$message = '<strong>' . esc_html__( 'Setup Completed Successfully!', 'vortem-ai' ) . '</strong><br>' .
								esc_html__( 'Your Vortem.ai plugin is now active and ready to use.', 'vortem-ai' );
				} else {
					$message = '<strong>' . esc_html__( 'Setup Incomplete', 'vortem-ai' ) . '</strong><br>' .
								esc_html__( 'Please complete the setup wizard to activate your plugin.', 'vortem-ai' );
				}

				$message .= '<br><em>' . sprintf(
					/* translators: %s: Validation date and time */
					esc_html__( 'Validated on: %s', 'vortem-ai' ),
					wp_date( 'F j, Y \a\t g:i A', strtotime( $validation_time ) )
				) . '</em>';

				// Clear the success flag after showing
				delete_option( 'vortem_auth_success' );
				delete_option( 'vortem_auth_success_time' );
			}
		}
	}

	/**
	 * Check if setup wizard is completed
	 */
	private function is_setup_completed() {
		return get_option( 'vortem_setup_completed', false );
	}

	/**
	 * Check if current page is a Vortem admin page
	 *
	 * @return bool
	 */
	private function is_vortem_admin_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$vortem_pages = array(
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
		return in_array( $current_page, $vortem_pages, true );
	}

	/**
	 * Render navigation dropdown menu on all Vortem pages
	 */
	public function render_navigation_dropdown() {
		// Only render on Vortem admin pages
		if ( ! $this->is_vortem_admin_page() ) {
			return;
		}

		// Don't render on setup wizard page - keep it clean and distraction-free
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $current_page === 'vortem-setup-wizard' ) {
			return;
		}

		// Get the admin menu items
		global $menu, $submenu;
		$menu_items = array();

		// Get current page slug early for use throughout this function
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Find the Vortem main menu
		$vortem_menu_slug = 'vortem-owerview';
		if ( isset( $submenu[ $vortem_menu_slug ] ) ) {
			foreach ( $submenu[ $vortem_menu_slug ] as $item ) {
				// Skip if user doesn't have permission
				if ( ! current_user_can( $item[1] ) ) {
					continue;
				}

				// Strip HTML tags from menu title for display
				$title = wp_strip_all_tags( $item[0] );
				$slug  = $item[2];

				// Skip Orders as it's now a sub-item under Products
				if ( $slug === 'vortem-orders' ) {
					continue;
				}

				// Check if this is the Products category
				if ( $slug === 'vortem-products' ) {
					// This is the Products category with sub-items
					$menu_items[] = array(
						'title'        => __( 'Products Pages', 'vortem-ai' ),
						'url'          => admin_url( 'admin.php?page=' . $slug ),
						'slug'         => $slug,
						'current'      => ( $current_page_slug === $slug ),
						'has_children' => true,
						'children'     => array(
							array(
								'title'   => __( 'Products', 'vortem-ai' ),
								'url'     => admin_url( 'admin.php?page=' . $slug ),
								'slug'    => $slug,
								'current' => ( $current_page_slug === $slug ),
							),
							array(
								'title'   => __( 'Orders', 'vortem-ai' ),
								'url'     => admin_url( 'admin.php?page=vortem-orders' ),
								'slug'    => 'vortem-orders',
								'current' => ( $current_page_slug === 'vortem-orders' ),
							),
						),
					);
				} elseif ( $slug === 'vortem-analytics' ) {
					// This is the Analytics category with sub-items
					$menu_items[] = array(
						'title'        => __( 'Analytics Pages', 'vortem-ai' ),
						'url'          => admin_url( 'admin.php?page=' . $slug ),
						'slug'         => $slug,
						'current'      => ( $current_page_slug === $slug ),
						'has_children' => true,
						'children'     => array(
							array(
								'title'   => __( 'Shop Analytics', 'vortem-ai' ),
								'url'     => admin_url( 'admin.php?page=' . $slug ),
								'slug'    => $slug,
								'current' => ( $current_page_slug === $slug ),
							),
						),
					);
				} else {
					// Regular menu item
					$menu_items[] = array(
						'title'        => $title,
						'url'          => admin_url( 'admin.php?page=' . $slug ),
						'slug'         => $slug,
						'current'      => ( $current_page_slug === $slug ),
						'has_children' => false,
					);
				}
			}
		}

		// Get current page slug (already sanitized above)
		$current_page = $current_page_slug;

		// Determine text direction from current locale (no plugin-specific language picker).
		$is_rtl         = class_exists( 'Vortem_Translation_Manager' ) ? Vortem_Translation_Manager::is_rtl() : is_rtl();
		$text_direction = $is_rtl ? 'rtl' : 'ltr';

		// Get current currency
		$current_currency = get_option( 'vortem_currency', 'USD' );
		?>
		<div class="vortem-nav-sidebar-wrapper" dir="<?php echo esc_attr( $text_direction ); ?>">
			<!-- Toggle Button - Fixed in corner -->
			<button class="vortem-nav-sidebar-toggle" id="vortem-nav-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle Navigation Menu', 'vortem-ai' ); ?>" aria-expanded="false">
				<span class="dashicons dashicons-menu"></span>
			</button>
			
			<!-- Sidebar Overlay -->
			<div class="vortem-nav-sidebar-overlay" id="vortem-nav-sidebar-overlay"></div>
			
			<!-- Sidebar -->
			<div class="vortem-nav-sidebar" id="vortem-nav-sidebar" role="menu">
				<button class="vortem-nav-sidebar-close" id="vortem-nav-sidebar-close" aria-label="<?php esc_attr_e( 'Close Menu', 'vortem-ai' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<div class="vortem-nav-sidebar-header">
					<h2 class="vortem-nav-sidebar-title"><?php esc_html_e( 'Navigation', 'vortem-ai' ); ?></h2>
					<div class="vortem-nav-sidebar-header-actions">
						<div class="vortem-nav-currency-switcher">
							<button class="vortem-nav-currency-button" id="vortem-nav-currency-button" aria-label="<?php esc_attr_e( 'Change Currency', 'vortem-ai' ); ?>" aria-expanded="false">
								<span class="dashicons dashicons-money-alt"></span>
								<span class="vortem-nav-currency-code"><?php echo esc_html( strtoupper( $current_currency ) ); ?></span>
							</button>
							<div class="vortem-nav-currency-dropdown" id="vortem-nav-currency-dropdown">
								<div class="vortem-nav-currency-loading">
									<span class="spinner is-active"></span>
									<span><?php esc_html_e( 'Loading currencies...', 'vortem-ai' ); ?></span>
								</div>
								<div class="vortem-nav-currency-search-wrapper" style="display: none;">
									<span class="dashicons dashicons-search"></span>
									<input type="text" class="vortem-nav-currency-search-input" placeholder="<?php esc_attr_e( 'Search currencies...', 'vortem-ai' ); ?>" autocomplete="off">
								</div>
								<div class="vortem-nav-currency-list"></div>
							</div>
						</div>
					</div>
				</div>
				<ul class="vortem-nav-sidebar-list">
					<?php foreach ( $menu_items as $item ) : ?>
						<?php if ( isset( $item['has_children'] ) && $item['has_children'] ) : ?>
							<li class="vortem-nav-sidebar-item vortem-nav-sidebar-item-has-children 
							<?php
							echo esc_attr(
								( $item['current'] || ( isset( $item['children'] ) && array_filter(
									$item['children'],
									function ( $child ) {
										return $child['current'];
									}
								) ) ) ? 'current' : ''
							);
							?>
																									">
								<a href="<?php echo esc_url( $item['url'] ); ?>" class="vortem-nav-sidebar-link vortem-nav-sidebar-link-parent" role="menuitem">
									<span class="vortem-nav-sidebar-link-text"><?php echo esc_html( $item['title'] ); ?></span>
									<span class="vortem-nav-sidebar-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
								</a>
								<ul class="vortem-nav-sidebar-submenu">
									<?php foreach ( $item['children'] as $child ) : ?>
										<li class="vortem-nav-sidebar-submenu-item <?php echo esc_attr( $child['current'] ? 'current' : '' ); ?>">
											<a href="<?php echo esc_url( $child['url'] ); ?>" class="vortem-nav-sidebar-link vortem-nav-sidebar-link-child" role="menuitem">
												<?php echo esc_html( $child['title'] ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</li>
						<?php else : ?>
							<li class="vortem-nav-sidebar-item <?php echo esc_attr( $item['current'] ? 'current' : '' ); ?>">
								<a href="<?php echo esc_url( $item['url'] ); ?>" class="vortem-nav-sidebar-link" role="menuitem">
									<?php echo esc_html( $item['title'] ); ?>
								</a>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirect to setup wizard if not completed
	 */
	private function check_setup_completion() {
		if ( ! $this->is_setup_completed() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vortem-setup-wizard' ) );
			exit;
		}
	}

	/**
	 * Initialize settings
	 */
	public function init_settings() {
		register_setting(
			'vortem_settings',
			'vortem_products_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 20,
			)
		);
		register_setting(
			'vortem_settings',
			'vortem_bi_analytics_hub_api_base_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => Vortem_Config::get_primary_api_server(),
			)
		);
		register_setting(
			'vortem_settings',
			'vortem_add_video_to_description',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'vortem_settings',
			'vortem_download_video_for_excluded_themes',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
	}

	/**
	 * Dashboard page
	 */
	public function dashboard_page() {
		// Check if setup is completed - redirect to wizard if not completed
		$this->check_setup_completion();

		// Get menu URLs
		$vortem_products_url        = admin_url( 'admin.php?page=vortem-products' );
		$vortem_analytics_url       = admin_url( 'admin.php?page=vortem-analytics' );
		$vortem_email_marketing_url = admin_url( 'admin.php?page=vortem-email-marketing' );
		$vortem_insights_url        = admin_url( 'admin.php?page=vortem-insights' );
		$vortem_orders_url          = admin_url( 'admin.php?page=vortem-orders' );
		$vortem_security_url        = admin_url( 'admin.php?page=vortem-security' );
		$vortem_settings_url        = admin_url( 'admin.php?page=vortem-settings' );

		// Get RTL direction for dashboard
		$is_rtl = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl = Vortem_Translation_Manager::is_rtl();
		}
		$vortem_dashboard_dir = $is_rtl ? 'rtl' : 'ltr';
		?>
		<div class="wrap vortem-overview-wrap" dir="<?php echo esc_attr( $vortem_dashboard_dir ); ?>">
			<div class="overview-dashboard-container overview-workspace-design" dir="<?php echo esc_attr( $vortem_dashboard_dir ); ?>">
					<?php
					require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
					$admin        = $this;
					$show_notices = true;
					include VORTEM_PLUGIN_DIR . 'admin/partials/overview-page.php';
					?>
			</div>
		</div>
		
		<?php
		// Prepare dashboard inline script
		$dashboard_script = "
        jQuery(document).ready(function($) {
            // Function to format number - always use English numerals
            function formatNumberForLanguage(num) {
                if (num === null || num === undefined || num === '') {
                    return num;
                }
                return String(num);
            }
            
            // Fetch auth status from API on Dashboard page load via WordPress AJAX (to avoid CORS)
            (function() {
                // Only fetch if we have vortem_admin object and nonce
                if (typeof vortem_admin !== 'undefined' && vortem_admin.nonce) {
                }
            })();
        });
        ";

		// Add inline script via wp_add_inline_script
		wp_add_inline_script( 'vortem-admin', $dashboard_script );
		?>
		
		<?php
	}

	/**
	 * Products page
	 */
	public function products_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Debug: Log that products_page is called

		// Get setup status
		$setup_status = $this->is_setup_completed() ? 'Active' : 'Not Set';

		// Get WooCommerce order statuses for orders tab
		$order_statuses = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$order_statuses = wc_get_order_statuses();
		}

		// Determine active tab from current page (consistent with analytics pattern)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'vortem-products';
		$active_tab   = ( $current_page === 'vortem-orders' ) ? 'orders' : 'products';

		// Get RTL direction for products page
		$is_rtl = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl = Vortem_Translation_Manager::is_rtl();
		}
		$products_dir = $is_rtl ? 'rtl' : 'ltr';
		?>
		<div class="wrap vortem-products-wrap" dir="<?php echo esc_attr( $products_dir ); ?>">
			<div id="vortem-products-app" dir="<?php echo esc_attr( $products_dir ); ?>">
				<main class="tab-panels">
					<!-- Products Panel -->
					<section id="panel-products" class="panel <?php echo esc_attr( $active_tab === 'products' ? 'active' : '' ); ?>" aria-labelledby="Products">
						<div class="vortem-page-wrapper products">
							<div class="vortem-page-content">
								<div class="modern-header">
									<div class="modern-header-main">
										<div class="icon-pill">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
										</div>
										<div>
											<h1 class="title"><?php esc_html_e( 'Vortem.ai Products', 'vortem-ai' ); ?></h1>
											<p class="subtitle"><?php esc_html_e( 'Manage and view your product inventory', 'vortem-ai' ); ?></p>
										</div>
									</div>
									<div class="modern-header-actions">
										<nav class="tabs" role="tablist">
											<button class="tab <?php echo esc_attr( $active_tab === 'products' ? 'active' : '' ); ?>" data-tab="products" role="tab"><?php echo esc_html__( 'Products', 'vortem-ai' ); ?></button>
											<button class="tab <?php echo esc_attr( $active_tab === 'orders' ? 'active' : '' ); ?>" data-tab="orders" role="tab"><?php echo esc_html__( 'Orders', 'vortem-ai' ); ?></button>
										</nav>
									</div>
								</div>
								<!-- Custom notices at the top of page content -->
								<?php $this->show_all_custom_notices(); ?>
								
								<!-- Notices will be shown dynamically based on endpoint validation status -->
								
								<div class="vortem-products">
				<!-- Status Messages -->
				<div id="status-messages" style="margin-top: 20px;"></div>
				
				<!-- Product Dashboard with Tabs -->
				<div class="product-dashboard" style="margin-top: 30px; position: relative;">
					<!-- Tab Navigation -->
					<div class="product-tabs-navigation" style="margin-bottom: 24px; border-bottom: 2px solid #e5e7eb;">
						<button class="product-tab-btn active" data-tab="top-products" role="tab">
							<?php esc_html_e( 'Top Products AliExpress', 'vortem-ai' ); ?>
						</button>
						<button class="product-tab-btn" data-tab="trend-products" role="tab">
							<?php esc_html_e( 'Trend Products Amazon', 'vortem-ai' ); ?>
						</button>
						<button class="product-tab-btn" data-tab="tiktok-products" role="tab">
							<?php esc_html_e( 'Trend Products TikTok', 'vortem-ai' ); ?>
						</button>
					</div>
					
					<!-- Top Products Tab Panel -->
					<div id="top-products-panel" class="product-tab-panel active">
						<!-- Refresh Products Button -->
						<div style="margin-bottom: 20px; display: flex;align-items: center;justify-content: space-between;">
							<div class="category-filter-container">
								<div class="vortem-category-dropdown" id="category-filter-wrapper">
									<button type="button" class="vortem-category-button" id="category-filter-button">
										<span class="category-button-text"><?php esc_html_e( 'All Categories', 'vortem-ai' ); ?></span>
										<i data-lucide="chevron-down" class="category-chevron"></i>
									</button>
									<div class="vortem-category-menu" id="category-filter-menu">
										<div class="category-menu-item" data-category-id="">
											<span><?php esc_html_e( 'All Categories', 'vortem-ai' ); ?></span>
										</div>
										<!-- Categories will be loaded here -->
									</div>
								</div>
								<input type="hidden" id="category-filter" value="">
							</div>
							<div style="margin: 0 5px 0 5px; display: flex; align-items: center; gap: 10px;">
							<div class="top-products-imported-box">
								<span class="top-products-imported-label"><?php esc_html_e( 'All Imported Products', 'vortem-ai' ); ?></span>
								<span class="top-products-imported-value" id="top-products-imported-count"><?php echo esc_html( $this->get_imported_products_count() ); ?></span>
							</div>
							<button type="button" id="refresh-products-grid" class="button button-primary">
								<?php esc_html_e( 'Refresh Products', 'vortem-ai' ); ?>
							</button>
							<button type="button" id="fetch-new-products" class="button button-secondary vortem-sort-toggle" style="transition: all 0.3s ease;">
								<span class="sort-toggle-text"><?php esc_html_e( 'Show All Added Products', 'vortem-ai' ); ?></span>
							</button>
							</div>
						</div>
						
						<div id="product-dashboard-content">
							<!-- Skeleton Loader - Initially visible -->
							<div id="products-skeleton-loader" class="products-skeleton-container">
								<div class="products-grid skeleton-grid">
									<!-- Generate 16 skeleton cards (default products per page) -->
									<?php for ( $i = 0; $i < 16; $i++ ) : ?>
									<div class="vortem-product-card skeleton-product-card">
										<div class="product-image-container skeleton-image-container">
											<div class="skeleton-image"></div>
										</div>
										<div class="product-content-section">
											<div class="skeleton-category-badge"></div>
											<div class="product-title-section">
												<div class="skeleton-text skeleton-title"></div>
												<div class="skeleton-text skeleton-title-short"></div>
											</div>
											<div class="product-price-container">
												<div class="skeleton-text skeleton-price"></div>
											</div>
											<div class="product-actions-section">
												<div class="skeleton-button"></div>
											</div>
										</div>
									</div>
									<?php endfor; ?>
								</div>
							</div>
							<!-- Content will be dynamically loaded here -->
						</div>
					</div>
					
					<!-- Trend Products Tab Panel -->
					<div id="trend-products-panel" class="product-tab-panel">
						<div id="trend-products-container">
							<!-- Trend products will be loaded here -->
						</div>
					</div>
					
					<!-- TikTok Products Tab Panel -->
					<div id="tiktok-products-panel" class="product-tab-panel">
						<div id="tiktok-products-container">
							<!-- TikTok products will be loaded here -->
						</div>
					</div>
				</div>
								
							</div>
						</div>
					</section>

					<!-- Orders Panel — same wrapper as Products so products-page.css tokens/style the header -->
					<section id="panel-orders" class="panel <?php echo esc_attr( $active_tab === 'orders' ? 'active' : '' ); ?>" aria-labelledby="Orders">
						<div class="vortem-page-wrapper products">
							<div class="vortem-page-content">
								<div class="vortem-orders-wrap">
									<div id="vortem-orders-app" class="container">
								<!-- Header -->
								<div class="modern-header">
									<div class="modern-header-main">
										<div class="icon-pill">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M6 2L3 6v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6l-3-4H6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M3 6h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
												<path d="M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
										</div>
										<div>
											<h1 class="title"><?php echo esc_html__( 'Orders', 'vortem-ai' ); ?></h1>
											<p class="subtitle"><?php echo esc_html__( 'View and manage your store orders', 'vortem-ai' ); ?></p>
										</div>
									</div>
									<div class="modern-header-actions">
										<nav class="tabs" role="tablist">
											<button class="tab <?php echo esc_attr( $active_tab === 'products' ? 'active' : '' ); ?>" data-tab="products" role="tab"><?php echo esc_html__( 'Products', 'vortem-ai' ); ?></button>
											<button class="tab <?php echo esc_attr( $active_tab === 'orders' ? 'active' : '' ); ?>" data-tab="orders" role="tab"><?php echo esc_html__( 'Orders', 'vortem-ai' ); ?></button>
										</nav>
									</div>
								</div>

								<div class="top-controls">
									<div class="search-wrap">
										<svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
										<input id="search-orders" class="input search-input-orders" type="search" placeholder="<?php echo esc_attr__( 'Search orders by number, customer name, or email...', 'vortem-ai' ); ?>" />
									</div>
									<div class="right-controls">
										<button class="btn-secondary" id="refresh-orders" title="<?php echo esc_attr__( 'Refresh Orders', 'vortem-ai' ); ?>">
											<i data-lucide="refresh-cw"></i>
											<span class="btn-text"><?php echo esc_html__( 'Refresh', 'vortem-ai' ); ?></span>
										</button>
										<button id="toggle-recent-orders" class="btn btn-ghost" title="<?php echo esc_attr__( 'Sort by date', 'vortem-ai' ); ?>" aria-label="<?php echo esc_attr__( 'Toggle sort order', 'vortem-ai' ); ?>">
											<svg id="sort-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M12 2v20M12 2l4 4M12 2L8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											</svg>
											<?php echo esc_html__( 'Sort by Date', 'vortem-ai' ); ?>
										</button>
										<select id="filter-status" class="input">
											<option value="all"><?php echo esc_html__( 'All Statuses', 'vortem-ai' ); ?></option>
											<?php foreach ( $order_statuses as $status_key => $status_label ) : ?>
												<option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<div class="date-filter-wrapper">
											<label for="filter-date-from" class="date-filter-label"><?php echo esc_html__( 'Start Date', 'vortem-ai' ); ?></label>
											<input type="date" id="filter-date-from" class="input" />
										</div>
										<div class="date-filter-wrapper">
											<label for="filter-date-to" class="date-filter-label"><?php echo esc_html__( 'End Date', 'vortem-ai' ); ?></label>
											<input type="date" id="filter-date-to" class="input" />
										</div>
									</div>
								</div>

								<div id="orders-stats" class="stats"></div>

								<div class="table-wrap" id="orders-table-wrap">
									<table class="table" id="orders-table">
										<thead>
											<tr>
												<th style="width:80px;"><?php echo esc_html__( 'Order', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Date', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Status', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Customer', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Items', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Total', 'vortem-ai' ); ?></th>
												<th><?php echo esc_html__( 'Payment', 'vortem-ai' ); ?></th>
												<th style="width:100px;"><?php echo esc_html__( 'Actions', 'vortem-ai' ); ?></th>
											</tr>
										</thead>
										<tbody id="orders-tbody">
											<tr>
												<td colspan="8" class="loading">
													<div class="spinner"></div>
													<?php echo esc_html__( 'Loading orders...', 'vortem-ai' ); ?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>

								<div id="pagination-container" class="pagination-container"></div>
							</div>

							<!-- Order Details Modal -->
							<div class="modal" id="order-details-modal" aria-hidden="true" role="dialog" aria-labelledby="order-details-modal-title">
								<div class="modal-backdrop" data-close="order-details-modal"></div>
								<div class="modal-dialog modal-large">
									<div class="modal-header">
										<h3 id="order-details-modal-title"><?php echo esc_html__( 'Order Details', 'vortem-ai' ); ?></h3>
										<button class="btn btn-icon" id="close-order-details-modal" aria-label="Close">
											<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
												<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
												<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
											</svg>
										</button>
									</div>
									<div class="modal-body" id="order-details-content">
										<div class="loading">
											<div class="spinner"></div>
											<?php echo esc_html__( 'Loading order details...', 'vortem-ai' ); ?>
										</div>
									</div>
								</div>
							</div>

							<!-- Toast Notification -->
							<div id="toast" class="toast" role="status" aria-live="polite"></div>
								</div>
							</div>
						</div>
					</section>
				</main>
			</div>
		</div>

		<!-- Product Details Modal -->
		<div id="product-details-modal" class="modal" style="display: none;">
			<div class="modal-content">
				<span class="close">&times;</span>
				<div id="product-details-content"></div>
			</div>
		</div>

		<!-- Modern Loading Overlay -->
		<div id="vortem-loading-overlay" class="vortem-loading-overlay" style="display: none;">
			<div class="vortem-loading-container">
				<div class="vortem-spinner">
					<div class="vortem-v-logo">
						<svg viewBox="0 0 100 100" class="vortem-v-svg">
							<path d="M20 20 L50 80 L80 20" stroke="#0073aa" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round">
								<animate attributeName="stroke-dasharray" values="0,200;200,0;0,200" dur="2s" repeatCount="indefinite"/>
								<animate attributeName="stroke-dashoffset" values="0;-200;0" dur="2s" repeatCount="indefinite"/>
							</path>
						</svg>
					</div>
				</div>
				<div class="vortem-loading-text"><?php esc_html_e( 'Processing...', 'vortem-ai' ); ?></div>
				<div class="vortem-loading-subtext"><?php esc_html_e( 'Please wait while we import your product', 'vortem-ai' ); ?></div>
				<ul class="vortem-loading-items" style="display: none;"></ul>
			</div>
		</div>

		<!-- Delete Product Confirmation Modal -->
		<div id="delete-product-modal" class="vortem-delete-modal" style="display: none;">
			<div class="vortem-modal-overlay"></div>
			<div class="vortem-modal-content vortem-modal-content-sm">
				<div class="vortem-confirm-content">
					<div class="vortem-confirm-icon">
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
							<line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
					<h3 class="vortem-confirm-title" id="delete-product-modal-title"><?php echo esc_html__( 'Delete Product', 'vortem-ai' ); ?></h3>
					<p class="vortem-confirm-message" id="delete-product-modal-message"><?php echo esc_html__( 'Are you sure you want to delete this product?', 'vortem-ai' ); ?></p>
					<div class="vortem-confirm-actions">
						<button class="button button-secondary" id="cancel-delete-product-btn"><?php echo esc_html__( 'Cancel', 'vortem-ai' ); ?></button>
						<button class="button button-primary button-danger" id="confirm-delete-product-btn"><?php echo esc_html__( 'Delete', 'vortem-ai' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Import Method Selection Modal -->
		<div id="import-method-modal" class="vortem-import-method-modal" style="display: none;">
			<div class="vortem-modal-overlay"></div>
			<div class="vortem-modal-content vortem-modal-content-sm">
				<div class="vortem-import-method-content">
					<div class="vortem-import-method-icon">
						<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h3 class="vortem-import-method-title"><?php echo esc_html__( 'Select Import Method', 'vortem-ai' ); ?></h3>
					<p class="vortem-import-method-message"><?php echo esc_html__( 'Choose how you want to import this product:', 'vortem-ai' ); ?></p>
					<div class="vortem-import-method-options">
						<button class="button button-primary vortem-import-option" id="normal-import-btn" data-import-type="normal">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
								<path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php echo esc_html__( 'Normal Import', 'vortem-ai' ); ?>
							<span class="import-option-description"><?php echo esc_html__( 'Import product with original title and description', 'vortem-ai' ); ?></span>
						</button>
						<button class="button button-secondary vortem-import-option" id="seo-import-btn" data-import-type="seo">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
								<path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php echo esc_html__( 'SEO Import', 'vortem-ai' ); ?>
							<span class="import-option-description"><?php echo esc_html__( 'Import with SEO-optimized title, description, and tags', 'vortem-ai' ); ?></span>
						</button>
					</div>
					<div class="vortem-import-method-actions">
						<button class="button button-secondary" id="cancel-import-method-btn"><?php echo esc_html__( 'Cancel', 'vortem-ai' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		
					</div>
				</div>
			</div>

		<!-- Modern minimal creative design styles are loaded via products-page.css -->

		<?php
		/*
		 * The following block contains inline JavaScript that must be processed
		 * as a template (it relies on esc_js/esc_html__ translations and other
		 * PHP-side data). Instead of emitting a raw <script> tag (which
		 * violates WordPress Coding Standards and the Plugin Handbook
		 * recommendation to always go through the enqueue pipeline), we
		 * buffer the rendered output and attach it to the already-registered
		 * "vortem-products-inline" script handle via wp_add_inline_script().
		 *
		 * Purely static logic lives in assets/js/vortem-products-inline.js,
		 * which is enqueued in enqueue_admin_scripts() with the proper page
		 * guard. Translations / nonces used here are provided through
		 * wp_localize_script( 'vortem-admin', 'vortem_admin', ... ).
		 */
		ob_start();
		?>
		jQuery(document).ready(function($) {
			// Define showNotice function at the very beginning so it's available everywhere
			window.showNotice = function(message, type) {
				type = type || 'info';
				var noticeClass = 'notice notice-' + type + ' is-dismissible vortem-plugin-notice';
				var noticeHtml = '<div class="' + noticeClass + '" style="margin: 20px 0; padding: 15px;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
				
				// Try to find vortem-page-content or wrap, otherwise use body
				var container = $('.vortem-page-content').first();
				if (container.length === 0) {
					container = $('.wrap').first();
				}
				if (container.length === 0) {
					container = $('body');
				}
				
				// Prepend notice to container
				container.prepend(noticeHtml);
				
				// Make dismissible
				$(document).trigger('wp-updates-notice-added');
				
				// Auto-dismiss after 5 seconds for success messages
				if (type === 'success') {
					setTimeout(function() {
						$('.notice.is-dismissible').first().fadeOut(function() {
							$(this).remove();
						});
					}, 5000);
				}
			};
			
			// Function to format number - always use English numerals
			function formatNumberForLanguage(num) {
				if (num === null || num === undefined || num === '') {
					return num;
				}
				return String(num);
			}
			
			// Fetch auth status from API via WordPress AJAX (to avoid CORS)
			(function() {
				// Only fetch if we have vortem_admin object and nonce
				if (typeof vortem_admin !== 'undefined' && vortem_admin.nonce) {
				}
			})();
			
			// Function to update imported products count via AJAX
			(function() {
				function updateImportedProductsCount() {
					if (typeof vortem_admin !== 'undefined' && vortem_admin.nonce) {
						$.ajax({
							url: vortem_admin.ajax_url,
							type: 'POST',
							data: {
								action: 'vortem_get_imported_products_count',
								nonce: vortem_admin.nonce
							},
							success: function(response) {
								if (response.success && response.data && response.data.count !== undefined) {
									var importedProductsEl = document.getElementById('imported-products');
									var topProductsImportedEl = document.getElementById('top-products-imported-count');
									if (importedProductsEl) {
										var count = parseInt(response.data.count) || 0;
										importedProductsEl.textContent = formatNumberForLanguage(count.toLocaleString());
									}
									if (topProductsImportedEl) {
										var count = parseInt(response.data.count) || 0;
										topProductsImportedEl.textContent = formatNumberForLanguage(count.toLocaleString());
									}
								}
							},
							error: function(xhr, status, error) {
								if (typeof VortemLogger !== 'undefined') {
									VortemLogger.error('Error fetching imported products count:', error);
								}
							}
						});
					}
				}
				
				// Update imported products count on page load
				updateImportedProductsCount();
				
				// Set up periodic updates every 30 seconds for all stats
				setInterval(function() {
					// Update auth status
					
					// Update imported products count
					updateImportedProductsCount();
				}, 30000); // Update every 30 seconds
			})();
			
			// Loading overlay functions
			var defaultProcessingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.processing) ? vortem_admin.strings.processing : 'Processing...';
			var defaultSubmessageText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_import) ? vortem_admin.strings.please_wait_import : 'Please wait while we import your product';
			function showLoading(message, submessage) {
				message = message || defaultProcessingText;
				submessage = submessage || defaultSubmessageText;
				$('.vortem-loading-text').text(message);
				$('.vortem-loading-subtext').text(submessage);
				$('#vortem-loading-overlay').show();
				$('body').css('overflow', 'hidden'); // Prevent scrolling
			}

			function hideLoading() {
				$('#vortem-loading-overlay').hide();
				$('body').css('overflow', 'auto'); // Restore scrolling
			}

			// Show delete confirmation modal
			function showDeleteConfirmationModal(message, callback) {
				var modal = $('#delete-product-modal');
				var modalMessage = $('#delete-product-modal-message');
				
				if (modal.length === 0) {
					// Fallback to confirm if modal doesn't exist
					if (confirm(message)) {
						callback();
					}
					return;
				}

				modalMessage.text(message);
				modal.show();

				// Remove previous event handlers
				$('#confirm-delete-product-btn, #cancel-delete-product-btn, .vortem-modal-overlay').off('click');

				// Handle confirm button
				$('#confirm-delete-product-btn').on('click', function() {
					modal.hide();
					callback();
				});

				// Handle cancel button and overlay
				$('#cancel-delete-product-btn, .vortem-modal-overlay').on('click', function() {
					modal.hide();
				});
			}

			// Force vortem actions to always be visible
			$('.vortem-actions').css({
				'opacity': '1',
				'visibility': 'visible',
				'display': 'flex'
			});
			

			// Import Products
			$('#import-products').on('click', function() {
				var button = $(this);
				button.prop('disabled', true).text('<?php esc_html_e( 'Importing...', 'vortem-ai' ); ?>');
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_import_products',
						nonce: vortem_admin.nonce_import
					},
					success: function(response) {
						if (response.success) {
							showNotice('<?php esc_html_e( 'Products imported successfully!', 'vortem-ai' ); ?>', 'success');
							location.reload();
						} else {
							// Extract error message from response
							var errorMsg = '<?php esc_html_e( 'Unknown error occurred', 'vortem-ai' ); ?>';
							if (response.data) {
								if (typeof response.data === 'string') {
									errorMsg = response.data;
								} else if (response.data.message) {
									errorMsg = response.data.message;
								} else {
									errorMsg = JSON.stringify(response.data);
								}
							}
							showNotice('<?php esc_html_e( 'Import failed: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
						}
					},
					error: function() {
						showNotice('<?php esc_html_e( 'Import failed. Please try again.', 'vortem-ai' ); ?>', 'error');
					},
					complete: function() {
						button.prop('disabled', false).text('<?php esc_html_e( 'Import to WooCommerce', 'vortem-ai' ); ?>');
					}
				});
			});

			// Import Single Product
			$('.import-single').on('click', function() {
				var sku = $(this).data('sku');
				var button = $(this);
				button.prop('disabled', true).text('<?php esc_html_e( 'Importing...', 'vortem-ai' ); ?>');
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_import_single',
						sku: sku,
						nonce: vortem_admin.nonce
					},
					success: function(response) {
						if (response.success) {
							// Update button to show "Already Imported" and disable it
							button.text('<?php esc_html_e( 'Already Imported', 'vortem-ai' ); ?>')
									.prop('disabled', true)
									.css({
										'background': '#28a745',
										'color': 'white',
										'cursor': 'not-allowed'
									});
							
							// Show success message
							showNotice('<?php esc_html_e( 'Product imported successfully!', 'vortem-ai' ); ?>', 'success');
							
							// No page reload - user can continue working
						} else {
							// Extract error message from response
							var errorMsg = '<?php esc_html_e( 'Unknown error occurred', 'vortem-ai' ); ?>';
							if (response.data) {
								if (typeof response.data === 'string') {
									errorMsg = response.data;
								} else if (response.data.message) {
									errorMsg = response.data.message;
								} else {
									errorMsg = JSON.stringify(response.data);
								}
							}
							showNotice('<?php esc_html_e( 'Import failed: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
						}
					},
					error: function() {
						showNotice('<?php esc_html_e( 'Import failed. Please try again.', 'vortem-ai' ); ?>', 'error');
					},
					complete: function() {
						// Only reset button if import failed (button is not disabled)
						if (!button.prop('disabled')) {
							button.prop('disabled', false).text('<?php esc_html_e( 'Import', 'vortem-ai' ); ?>');
						}
					}
				});
			});

			// Bulk Actions
			$('#doaction').on('click', function(e) {
				e.preventDefault();
				var action = $('#bulk-action-selector-top').val();
				var selectedProducts = $('.product-checkbox:checked').map(function() {
					return $(this).val();
				}).get();
				
				if (action === '-1') {
					showNotice('<?php esc_html_e( 'Please select a bulk action.', 'vortem-ai' ); ?>', 'warning');
					return;
				}
				
				if (selectedProducts.length === 0) {
					showNotice('<?php esc_html_e( 'Please select at least one product.', 'vortem-ai' ); ?>', 'warning');
					return;
				}
				
				var confirmMessage = '';
				switch(action) {
					case 'import':
						confirmMessage = '<?php esc_html_e( 'Are you sure you want to import the selected products to WooCommerce?', 'vortem-ai' ); ?>';
						break;
					case 'trash':
						confirmMessage = '<?php esc_html_e( 'Are you sure you want to permanently delete the selected products? This will remove them from the database and WordPress, including all images. This action cannot be undone.', 'vortem-ai' ); ?>';
						break;
					case 'restore':
						confirmMessage = '<?php esc_html_e( 'Are you sure you want to restore the selected products from trash?', 'vortem-ai' ); ?>';
						break;
					case 'delete':
						confirmMessage = '<?php esc_html_e( 'Are you sure you want to permanently delete the selected products? This action cannot be undone.', 'vortem-ai' ); ?>';
						break;
				}
				
				if (confirm(confirmMessage)) {
					var button = $(this);
					button.prop('disabled', true).val('<?php esc_html_e( 'Processing...', 'vortem-ai' ); ?>');
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'vortem_bulk_action',
							bulk_action: action,
							product_ids: selectedProducts,
							nonce: vortem_admin.nonce
						},
						success: function(response) {
							if (response.success) {
								showNotice(response.data.message || '<?php esc_html_e( 'Bulk action completed successfully!', 'vortem-ai' ); ?>', 'success');
								location.reload();
							} else {
								var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
								showNotice('<?php esc_html_e( 'Bulk action failed: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
							}
						},
						error: function() {
							showNotice('<?php esc_html_e( 'Bulk action failed. Please try again.', 'vortem-ai' ); ?>', 'error');
						},
						complete: function() {
							button.prop('disabled', false).val('<?php esc_html_e( 'Apply', 'vortem-ai' ); ?>');
						}
					});
				}
			});

			// Select All Checkbox
			$('#cb-select-all-1').on('change', function() {
				$('.product-checkbox').prop('checked', $(this).prop('checked'));
			});

			// Individual Checkbox Change
			$('.product-checkbox').on('change', function() {
				var totalCheckboxes = $('.product-checkbox').length;
				var checkedCheckboxes = $('.product-checkbox:checked').length;
				$('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
			});

			// Trash Product
			$('.trash-product').on('click', function() {
				var sku = $(this).data('sku');
				var button = $(this);
				
				if (confirm('<?php esc_html_e( 'Are you sure you want to permanently delete this product? This will remove it from the database and WordPress, including all images. This action cannot be undone.', 'vortem-ai' ); ?>')) {
					button.prop('disabled', true).text('<?php esc_html_e( 'Deleting...', 'vortem-ai' ); ?>');
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'vortem_trash_product',
							sku: sku,
							nonce: vortem_admin.nonce
						},
						success: function(response) {
							if (response.success) {
								location.reload();
							} else {
								var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
								showNotice('<?php esc_html_e( 'Failed to trash product: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
								button.prop('disabled', false).text('<?php esc_html_e( 'Trash', 'vortem-ai' ); ?>');
							}
						},
						error: function() {
							showNotice('<?php esc_html_e( 'Failed to trash product. Please try again.', 'vortem-ai' ); ?>', 'error');
							button.prop('disabled', false).text('<?php esc_html_e( 'Trash', 'vortem-ai' ); ?>');
						}
					});
				}
			});

			// Restore Product
			$('.restore-product').on('click', function() {
				var sku = $(this).data('sku');
				var button = $(this);
				
				if (confirm('<?php esc_html_e( 'Are you sure you want to restore this product from trash?', 'vortem-ai' ); ?>')) {
					button.prop('disabled', true).text('<?php esc_html_e( 'Restoring...', 'vortem-ai' ); ?>');
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'vortem_restore_product',
							sku: sku,
							nonce: vortem_admin.nonce
						},
						success: function(response) {
							if (response.success) {
								location.reload();
							} else {
								var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
								showNotice('<?php esc_html_e( 'Failed to restore product: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
								button.prop('disabled', false).text('<?php esc_html_e( 'Restore', 'vortem-ai' ); ?>');
							}
						},
						error: function() {
							showNotice('<?php esc_html_e( 'Failed to restore product. Please try again.', 'vortem-ai' ); ?>', 'error');
							button.prop('disabled', false).text('<?php esc_html_e( 'Restore', 'vortem-ai' ); ?>');
						}
					});
				}
			});

			// Edit Product
			$('.edit-product').on('click', function() {
				var sku = $(this).data('sku');
				var button = $(this);
				
				var checkingStatusText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.checking_product_status) ? vortem_admin.strings.checking_product_status : 'Checking product status...';
				var pleaseWaitCheckText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_check) ? vortem_admin.strings.please_wait_check : 'Please wait while we check if the product exists';
				showLoading(checkingStatusText, pleaseWaitCheckText);
				
				// Make AJAX call to get product details and find WooCommerce ID
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_get_product_json',
						sku: sku,
						nonce: vortem_admin.nonce_details
					},
					success: function(response) {
						if (response.success && response.data.woo_product_exists) {
							hideLoading();
							// Redirect to WooCommerce product edit page
							var editUrl = '<?php echo esc_url( admin_url( 'post.php' ) ); ?>?post=' + response.data.woo_product_id + '&action=edit';
							window.open(editUrl, '_blank');
						} else {
							// Import as draft first, then redirect
							var importingDraftText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing_product_draft) ? vortem_admin.strings.importing_product_draft : 'Importing product as draft...';
							var pleaseWaitImportText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_import) ? vortem_admin.strings.please_wait_import : 'Please wait while we import your product';
							showLoading(importingDraftText, pleaseWaitImportText);
							
							$.ajax({
								url: vortem_admin.ajax_url,
								type: 'POST',
								data: {
									action: 'vortem_import_as_draft',
									sku: sku,
									nonce: vortem_admin.nonce
								},
								success: function(importResponse) {
									if (importResponse.success) {
										hideLoading();
										// Don't reload page, just show success message and update UI
										showMessage('Product imported successfully!', 'success');
									} else {
										hideLoading();
										showNotice('<?php esc_html_e( 'Failed to import product as draft: ', 'vortem-ai' ); ?>' + importResponse.data, 'error');
									}
								},
								error: function() {
									hideLoading();
									showNotice('<?php esc_html_e( 'Failed to import product as draft. Please try again.', 'vortem-ai' ); ?>', 'error');
								}
							});
						}
					},
					error: function() {
						hideLoading();
						showNotice('<?php esc_html_e( 'Failed to get product details.', 'vortem-ai' ); ?>', 'error');
					}
				});
			});

			// View Product Details
			$('.view-details').on('click', function() {
				var sku = $(this).data('sku');
				$('#product-details-modal').show();
				$('#product-details-content').html('<p><?php esc_html_e( 'Loading...', 'vortem-ai' ); ?></p>');
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_get_product_details',
						sku: sku,
						nonce: vortem_admin.nonce_details
					},
					success: function(response) {
						if (response.success) {
							$('#product-details-content').html(response.data);
						} else {
							$('#product-details-content').html('<p><?php esc_html_e( 'Failed to load product details.', 'vortem-ai' ); ?></p>');
						}
					}
				});
			});

			// Close Modal
			$('.close').on('click', function() {
				$('#product-details-modal').hide();
			});

			// Store filter state globally (must be declared before any handlers that use it)
			var filterState = {
				showImportedOnly: false,
				currentPage: 1,
				selectedCategory: '',
				categoriesPage: 1,
				categoriesLimit: 50
			};
			
			// Get category ID from URL on page load
			function getCategoryFromURL() {
				var urlParams = new URLSearchParams(window.location.search);
				var categoryId = urlParams.get('category');
				return categoryId || '';
			}
			
			// Update URL with category parameter
			function updateURLWithCategory(categoryId) {
				var url = new URL(window.location);
				if (categoryId) {
					url.searchParams.set('category', categoryId);
				} else {
					url.searchParams.delete('category');
				}
				// Use pushState to update URL without reload
				window.history.pushState({category: categoryId}, '', url.toString());
			}
			
			// Initialize category from URL on page load
			var urlCategoryId = getCategoryFromURL();
			if (urlCategoryId) {
				filterState.selectedCategory = urlCategoryId;
				$('#category-filter').val(urlCategoryId);
				// Show price filter if category is selected from URL
				// $('#price-filter-container').css('display', 'flex');
			} else {
				// Hide price filter if no category is selected (All Categories)
				// $('#price-filter-container').css('display', 'none');
			}
			
			// Fetch categories - API categories for all products, WooCommerce categories for imported products
			function fetchCategories(showImportedOnly, page, append) {
				showImportedOnly = showImportedOnly !== undefined ? showImportedOnly : filterState.showImportedOnly;
				page = page || filterState.categoriesPage || 1;
				append = append !== undefined ? append : false;
				
				if (showImportedOnly) {
					// For imported products, use WooCommerce categories
					var action = 'vortem_get_imported_product_categories';
					var ajaxData = {
						action: action,
						nonce: vortem_admin.nonce
					};
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: ajaxData,
						success: function(response) {
							if (response.success && response.data) {
								var categoryMenu = $('#category-filter-menu');
								if (!append) {
									categoryMenu.find('.category-menu-item:not(:first)').remove();
								}
								
								var categories = response.data.categories || response.data.data?.categories || [];
								
								categories.forEach(function(category) {
									var displayText = category.name;
									if (category.count !== undefined && category.count !== null && !isNaN(category.count)) {
										displayText += ' (' + category.count + ')';
									}

									$('<div class="category-menu-item"></div>')
										.attr('data-category-id', category.id)
										.append($('<span></span>').text(displayText))
										.appendTo(categoryMenu);
								});
								
								VortemLogger.log('Categories loaded:', categories.length, '(WooCommerce)');
							}
						},
						error: function(xhr, status, error) {
							VortemLogger.error('Error fetching categories:', error);
						}
					});
				} else {
					// For all products, use new API categories endpoint with pagination
					var action = 'vortem_get_categories';
					var ajaxData = {
						action: action,
						nonce: vortem_admin.nonce,
						page: page,
						limit: filterState.categoriesLimit || 50
					};
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: ajaxData,
						success: function(response) {
							if (response.success && response.data) {
								var categoryMenu = $('#category-filter-menu');
								if (!append) {
									categoryMenu.find('.category-menu-item:not(:first)').remove();
								}
								
								// Handle different response structures:
								// 1) response.data is array directly: [{category_id, category_name, subcategories}]
								// 2) response.data.categories is array: {categories: [{category_id, category_name, subcategories}]}
								var categories = [];
								if (Array.isArray(response.data)) {
									categories = response.data;
								} else if (response.data.categories && Array.isArray(response.data.categories)) {
									categories = response.data.categories;
								} else if (response.data.data && Array.isArray(response.data.data)) {
									categories = response.data.data;
								}
								
								categories.forEach(function(category) {
									var catId = category.category_id || category.cat_id || category.vortem_cat_ID || category.id || '';
									var catName = category.category_name || category.cat_name || category.vortem_cat || category.name || '';
									if (!catId && catId !== 0) { return; }

									var subs = category.subcategories || category.children || [];
									var hasSubs = subs && subs.length > 0;

									var $menuItem = $('<div class="category-menu-item category-main-item"></div>')
										.attr('data-category-id', catId)
										.toggleClass('has-subcategories', hasSubs);

									$('<span class="category-name"></span>').text(catName).appendTo($menuItem);

									if (hasSubs) {
										$menuItem.append('<i data-lucide="chevron-right" class="category-arrow"></i>');
										var $submenu = $('<div class="category-submenu"></div>');

										// "All <Category>" affordance — lets the user filter by the parent
										// category itself without picking a specific subcategory.
										$('<div class="category-submenu-item category-submenu-all"></div>')
											.attr('data-category-id', catId)
											.append($('<span class="subcategory-name"></span>').text('<?php echo esc_js( __( 'All', 'vortem-ai' ) ); ?>' + ' ' + catName))
											.appendTo($submenu);

										subs.forEach(function(subcategory) {
											var subId = subcategory.category_id || subcategory.cat_id || subcategory.vortem_cat_ID || subcategory.id || '';
											var subName = subcategory.category_name || subcategory.cat_name || subcategory.vortem_cat || subcategory.name || '';
											var subCount = subcategory.product_count || subcategory.count || 0;
											if (!subId && subId !== 0) { return; }

											var $subItem = $('<div class="category-submenu-item"></div>')
												.attr('data-category-id', subId);
											$('<span class="subcategory-name"></span>').text(subName).appendTo($subItem);
											if (subCount) {
												$('<span class="subcategory-count"></span>').text('(' + subCount + ')').appendTo($subItem);
											}
											$submenu.append($subItem);
										});

										$menuItem.append($submenu);
									}

									categoryMenu.append($menuItem);
								});
								
								// Add pagination controls if there are more pages
								if (response.data.total_pages && response.data.total_pages > page) {
									var loadMoreBtn = categoryMenu.find('.category-load-more');
									if (loadMoreBtn.length === 0) {
										loadMoreBtn = $('<div class="category-load-more" style="padding: 10px; text-align: center; border-top: 1px solid rgba(96, 165, 250, 0.2); cursor: pointer; color: #60a5fa; font-weight: 500;"><?php esc_html_e( 'Load More Categories', 'vortem-ai' ); ?></div>');
										categoryMenu.append(loadMoreBtn);
										loadMoreBtn.on('click', function() {
											fetchCategories(false, page + 1, true);
										});
									}
								} else {
									categoryMenu.find('.category-load-more').remove();
								}
								
								// Re-initialize Lucide icons
								if (typeof lucide !== 'undefined') {
									lucide.createIcons();
								}
								
								VortemLogger.log('Categories loaded:', categories.length, '(API, page ' + page + ')');
							}
						},
						error: function(xhr, status, error) {
							VortemLogger.error('Error fetching categories:', error);
						}
					});
				}
			}
			
			// Initialize categories on page load
			fetchCategories(false);
			
			// If category is in URL, fetch products for that category on page load
			if (urlCategoryId) {
				// Wait for categories to load, then find and select the category
				setTimeout(function() {
					// Find and select the category in the dropdown
					var categoryItem = $('.category-menu-item[data-category-id="' + urlCategoryId + '"]');
					if (categoryItem.length === 0) {
						// Try to find in subcategories
						categoryItem = $('.category-submenu-item[data-category-id="' + urlCategoryId + '"]');
					}
					
					if (categoryItem.length > 0) {
						// Update button text
						var categoryName = categoryItem.find('.category-name, .subcategory-name, span').first().text().trim();
						if (categoryName) {
							$('#category-filter-button .category-button-text').text(categoryName);
						}
					}
					
					// Fetch products for the category
					fetchProductsWithFilter(1, filterState.showImportedOnly);
				}, 500); // Wait 500ms for categories to load
			}
			
			// --- Category dropdown: click-based accordion ----------------------
			// Replaces the prior hover-driven submenu / subcategories-box mechanism,
			// which auto-closed after 400ms and was unusable on touch devices.

			var allCategoriesLabel = '<?php echo esc_js( __( 'All Categories', 'vortem-ai' ) ); ?>';

			function closeCategoryMenu() {
				$('#category-filter-menu').removeClass('active');
				$('.category-main-item.open').removeClass('open');
			}

			function applyCategoryFilter(categoryId, buttonText) {
				$('#category-filter-button .category-button-text').text(buttonText || allCategoriesLabel);
				$('#category-filter').val(categoryId);
				filterState.selectedCategory = categoryId;
				updateURLWithCategory(categoryId);
				closeCategoryMenu();
				fetchProductsWithFilter(1, filterState.showImportedOnly);
			}

			// Toggle the dropdown open/closed
			$('#category-filter-button').on('click', function(e) {
				e.stopPropagation();
				var $menu = $('#category-filter-menu');
				var willOpen = !$menu.hasClass('active');
				$menu.toggleClass('active', willOpen);
				if (!willOpen) {
					$('.category-main-item.open').removeClass('open');
				}
			});

			// Keep wheel scroll contained inside the menu (prevents page scroll jumps)
			$('#category-filter-menu').on('wheel', function(e) {
				var $menu = $(this);
				var scrollTop = $menu.scrollTop();
				var scrollHeight = $menu[0].scrollHeight;
				var clientHeight = $menu[0].clientHeight;
				var deltaY = e.originalEvent.deltaY;
				if ((scrollTop <= 0 && deltaY < 0) || (scrollTop + clientHeight >= scrollHeight && deltaY > 0)) {
					e.preventDefault();
				}
			});

			// Close menu when clicking outside the wrapper
			$(document).on('click', function(e) {
				if (!$(e.target).closest('#category-filter-wrapper').length) {
					closeCategoryMenu();
				}
			});
			
			// Click on a category row.
			// - Has subcategories  → toggle accordion open/closed (don't filter, don't close menu)
			// - No subcategories   → apply that category as the filter and close menu
			// Subcategory clicks are caught by a separate handler below and short-circuited here.
			$(document).on('click', '.category-menu-item', function(e) {
				if ($(e.target).closest('.category-submenu-item').length > 0) {
					return; // delegated to the subcategory handler
				}

				e.stopPropagation();
				var $item = $(this);
				var categoryId = $item.attr('data-category-id') || '';

				if ($item.hasClass('has-subcategories')) {
					var willOpen = !$item.hasClass('open');
					$('.category-main-item.open').not($item).removeClass('open');
					$item.toggleClass('open', willOpen);
					return;
				}

				// Leaf category, or the "All Categories" row.
				var label = categoryId
					? $item.find('.category-name, span').first().text().trim()
					: allCategoriesLabel;
				applyCategoryFilter(categoryId, label);
			});

			// Subcategory click → apply filter for that subcategory and close.
			// `.category-submenu-all` is the synthetic "All <Parent>" row; it carries the parent's id.
			$(document).on('click', '.category-submenu-item', function(e) {
				e.stopPropagation();
				e.preventDefault();
				var $item = $(this);
				var categoryId = $item.attr('data-category-id') || '';
				var name = $item.find('.subcategory-name').text().trim();
				var count = $item.find('.subcategory-count').text().trim();
				var label = count ? (name + ' ' + count) : name;
				applyCategoryFilter(categoryId, label);
			});
			
			// Category filter change handler (for backward compatibility)
			$('#category-filter').on('change', function() {
				var selectedCategory = $(this).val();
				filterState.selectedCategory = selectedCategory;
				
				// Reset to page 1 when category changes
				fetchProductsWithFilter(1, filterState.showImportedOnly);
			});
			
			// Refresh Products Button
			$('#refresh-products-grid').on('click', function() {
				VortemLogger.log('Refresh products grid clicked');
				var button = $(this);
				
				// Add loading class and disable button
				button.addClass('loading').prop('disabled', true);
				
				// Reset filter state when refreshing
				filterState.showImportedOnly = false;
				filterState.selectedCategory = '';
				
				// Reset category filter dropdown
				$('#category-filter').val('');
				
				// Refresh categories for all products view
				fetchCategories(false);
				
				// Fetch products with reset filter state and callback to remove loading
				fetchProductsWithFilter(1, false, function() {
					button.removeClass('loading').prop('disabled', false);
				});
			});
			
			// Helper function to get current page number from pagination controls
			function getCurrentPageNumber() {
				var activePageBtn = $('.vortem-pagination-btn.button-primary');
				if (activePageBtn.length) {
					var page = parseInt(activePageBtn.data('page'));
					if (page && page > 0) {
						return page;
					}
				}
				return 1; // Default to page 1 if cannot determine
			}
			
			// Helper function to fetch products with current filter state
			function fetchProductsWithFilter(page, showImportedOnly, onComplete) {
				var productsPerPage = vortem_admin.products_per_page || 16;
				page = page || getCurrentPageNumber() || 1;
				showImportedOnly = showImportedOnly !== undefined ? showImportedOnly : filterState.showImportedOnly;
				
				// Update filter state
				filterState.showImportedOnly = showImportedOnly;
				filterState.currentPage = page;
				
				// Show skeleton loader and hide existing content
				var dashboardContent = $('#product-dashboard-content');
				var skeletonLoader = $('#products-skeleton-loader');
				
				// Hide existing products grid and pagination
				dashboardContent.find('.products-grid:not(.skeleton-grid), .vortem-pagination, .vortem-show-more-container, .notice').hide();
				
				// Show skeleton loader
				skeletonLoader.show();
				
				// Prepare AJAX data
				var ajaxData = {
					action: 'vortem_fetch_products',
					nonce: vortem_admin.nonce,
					limit: productsPerPage,
					page: page,
					show_imported_only: showImportedOnly ? 1 : 0
				};
				
				// Add category_id if a category is selected (convert to string as API expects string)
				if (filterState.selectedCategory) {
					ajaxData.category_id = String(filterState.selectedCategory);
					VortemLogger.log('Fetching products for category_id:', ajaxData.category_id);
				} else {
					VortemLogger.log('No category selected, fetching all products');
				}
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: ajaxData,
					success: function(response) {
						if (response.success && response.data) {
							// Check for products in different possible locations
							var products = null;
							if (response.data.products && Array.isArray(response.data.products)) {
								products = response.data.products;
							} else if (response.data.data && Array.isArray(response.data.data)) {
								products = response.data.data;
							} else if (Array.isArray(response.data)) {
								products = response.data;
							}

							if (!products || products.length === 0) {
								dashboardContent.css('opacity', '1');
								dashboardContent.html('<div class="notice notice-warning"><p><strong>⚠️ No Products Found</strong></p><p>No products were found for the selected category. Please try another category.</p></div>');
								return;
							}

							var totalFound = response.data.total_found || products.length;
							var currentPage = response.data.page || page;
							var totalPages = response.data.total_pages || Math.ceil(totalFound / productsPerPage);
							var productCount = products.length;
							
							// Show success notice in status-messages container
							if (typeof showAutoFetchSuccess === 'function') {
								showAutoFetchSuccess(productCount);
							} else {
								// Fallback: define function inline if not available
								var noticeHtml = '<div class="notice notice-success vortem-auto-fetch-notice is-dismissible" style="margin: 20px 0;">' +
									'<p><strong>✅ Products Fetched Successfully!</strong></p>' +
									'<p>Successfully loaded ' + productCount + ' products from the API.</p>' +
									'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
									'</div>';
								$('#status-messages').empty().html(noticeHtml);
								$(document).trigger('wp-updates-notice-added');
								setTimeout(function() {
									$('.vortem-auto-fetch-notice').fadeOut(500, function() {
										$(this).remove();
									});
								}, 5000);
							}
							
							// Hide skeleton loader
							skeletonLoader.hide();
							
							displayProductsGrid(products, {
								currentPage: currentPage,
								totalPages: totalPages,
								totalFound: totalFound,
								limit: productsPerPage
							});
							
							// Update button state
							var toggleButton = $('#fetch-new-products');
							if (toggleButton.length) {
								if (showImportedOnly) {
									toggleButton.addClass('active');
									toggleButton.find('.sort-toggle-text').text('<?php echo esc_js( __( 'Show All Products', 'vortem-ai' ) ); ?>');
								} else {
									toggleButton.removeClass('active');
									toggleButton.find('.sort-toggle-text').text('<?php echo esc_js( __( 'Show All Added Products', 'vortem-ai' ) ); ?>');
								}
							}
							
							// Smooth fade in
							setTimeout(function() {
								dashboardContent.css('opacity', '1');
							}, 100);
						} else {
							// Hide skeleton loader on error
							skeletonLoader.hide();
							dashboardContent.css('opacity', '1');
							dashboardContent.html('<div class="notice notice-error"><p><strong>❌ Failed to fetch products:</strong> ' + (response.data.message || 'Unknown error') + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						VortemLogger.error('Fetch products error:', xhr.responseText);
						// Hide skeleton loader on error
						skeletonLoader.hide();
						dashboardContent.css('opacity', '1');
						dashboardContent.html('<div class="notice notice-error"><p><strong>❌ Connection error:</strong> ' + error + '</p></div>');
					},
					complete: function() {
						// Hide skeleton loader on complete
						skeletonLoader.hide();
						// Call completion callback if provided
						if (typeof onComplete === 'function') {
							onComplete();
						}
					}
				});
			}
			
			// Toggle Product Filter Button - API-based filtering
			$('#fetch-new-products').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var button = $(this);
				// Use filterState instead of button class to avoid race conditions
				var currentPage = getCurrentPageNumber();
				var newShowImportedOnly = !filterState.showImportedOnly;
				
				// Update filter state immediately
				filterState.showImportedOnly = newShowImportedOnly;
				
				// Update button state immediately to prevent race conditions
				if (newShowImportedOnly) {
					button.addClass('active');
					button.find('.sort-toggle-text').text('<?php echo esc_js( __( 'Show All Products', 'vortem-ai' ) ); ?>');
				} else {
					button.removeClass('active');
					button.find('.sort-toggle-text').text('<?php echo esc_js( __( 'Show All Added Products', 'vortem-ai' ) ); ?>');
				}
				
				// Reset category filter when switching views
				filterState.selectedCategory = '';
				$('#category-filter').val('');
				$('#category-filter-button .category-button-text').text('<?php esc_html_e( 'All Categories', 'vortem-ai' ); ?>');
				
				// Fetch appropriate categories for the new view
				fetchCategories(newShowImportedOnly);
				
				// Fetch products with new filter state
				fetchProductsWithFilter(currentPage, newShowImportedOnly);
			});
			
			// Product Tabs Navigation (Top Products / Trend Products)
			$('.product-tab-btn').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var button = $(this);
				var targetTab = button.data('tab');
				
				// Remove active class from all tabs and panels
				$('.product-tab-btn').removeClass('active');
				$('.product-tab-panel').removeClass('active');
				
				// Add active class to clicked tab
				button.addClass('active');
				
				// Show corresponding panel
				if (targetTab === 'top-products') {
					$('#top-products-panel').addClass('active');
				} else if (targetTab === 'trend-products') {
					$('#trend-products-panel').addClass('active');
					
					// Load trend products if not already loaded
					if ($('#trend-products-container').children().length === 0) {
						loadTrendProducts();
					}
				} else if (targetTab === 'tiktok-products') {
					$('#tiktok-products-panel').addClass('active');
					
					// Load TikTok products if not already loaded
					if ($('#tiktok-products-container').children().length === 0) {
						loadTikTokProducts();
					}
				}
			});
			
			// Function to load trend products from API
			function loadTrendProducts(page) {
				page = page || 1;
				var limit = 20;
				var container = $('#trend-products-container');
				
				// Show loading state
				var loadingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['Loading trend products...']) ? vortem_admin.strings['Loading trend products...'] : 'Loading trend products...';
				container.html('<div class="trend-products-loading" style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #60a5fa; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 20px; color: #64748b;">' + loadingText + '</p></div>');
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'vortem_fetch_trend_products',
						nonce: vortem_admin.nonce,
						page: page,
						limit: limit
					},
					success: function(response) {
						if (response.success && response.data) {
							var products = [];
							var responseData = response.data;
							
							// Handle different response formats
							if (Array.isArray(responseData)) {
								// Response is directly an array of products
								products = responseData;
							} else if (Array.isArray(responseData.products)) {
								// Response has products array
								products = responseData.products;
							} else if (Array.isArray(responseData.data)) {
								// Response has nested data array
								products = responseData.data;
							}
							
							renderTrendProducts(products, responseData);
						} else {
							var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
							container.html('<div class="trend-products-error" style="text-align: center; padding: 40px;"><p style="color: #ef4444;">Failed to load trend products: ' + errorMsg + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						container.html('<div class="trend-products-error" style="text-align: center; padding: 40px;"><p style="color: #ef4444;">Error loading trend products: ' + error + '</p></div>');
					}
				});
			}
			
			// Function to render trend products
			function renderTrendProducts(products, responseData) {
				var container = $('#trend-products-container');
				
				if (!products || products.length === 0) {
					container.html('<div class="trend-products-empty" style="text-align: center; padding: 40px;"><p style="color: #64748b;">No trend products found.</p></div>');
					return;
				}
				
				var html = '<div class="trend-products-grid">';
				
				products.forEach(function(product) {
					// Backend now returns flat fields ({title, price, url, rating, reviews, category, availability}).
					// Older nested shape (pb_info / market_info / sentiment_summary / product_selection_metrics) is
					// still tolerated as a fallback so a backend rollback doesn't break the panel.
					var pbInfo = product.pb_info || {};
					var marketInfo = product.market_info || {};
					var sentimentSummary = product.sentiment_summary || {};
					var productMetrics = product.product_selection_metrics || {};

					var title = product.title || pbInfo.title || 'Untitled Product';
					var image = product.main_image_url || pbInfo.baseImage || product.image || '';
					var url = product.url || pbInfo.url || '#';
					var finalPrice = (typeof product.price === 'number') ? product.price : (pbInfo.finalPrice || 0);
					var originalPrice = pbInfo.originalPrice || 0;
					var discount = pbInfo.discount || 0;
					var currency = pbInfo.currency || product.currency || '$';
					var rating = (typeof product.rating === 'number') ? product.rating : (pbInfo.score || 0);
					var category = product.category || pbInfo.category || '';
					var availability = product.availability || marketInfo.Availability || '';
					var reviewCount = (typeof product.reviews === 'number') ? product.reviews : (sentimentSummary.review_count || 0);
					var positiveSentiment = sentimentSummary.positive || 0;
					var neutralSentiment = sentimentSummary.neutral || 0;
					var negativeSentiment = sentimentSummary.negative || 0;
					var totalSentiment = positiveSentiment + neutralSentiment + negativeSentiment;
					var positivePercent = totalSentiment > 0 ? Math.round((positiveSentiment / totalSentiment) * 100) : 0;
					var neutralPercent = totalSentiment > 0 ? Math.round((neutralSentiment / totalSentiment) * 100) : 0;
					var negativePercent = totalSentiment > 0 ? Math.round((negativeSentiment / totalSentiment) * 100) : 0;

					var ti = productMetrics.TI || 0;
					var pi = productMetrics.PI || 0;
					var ssi = productMetrics.SSI || 0;
					var hasMetrics = (ti > 0 || pi > 0 || ssi > 0);

					html += '<div class="trend-product-card">';

					// Image container
					html += '<div class="trend-product-image-container">';
					if (image) {
						html += '<img src="' + image + '" alt="' + title.replace(/"/g, '&quot;') + '" class="trend-product-image" onerror="this.src=\'/images/placeholder-template.svg\'">';
					} else {
						html += '<div class="trend-product-image-placeholder"><img src="/images/placeholder-template.svg" alt="Placeholder"></div>';
					}
					if (discount > 0) {
						html += '<div class="trend-product-badge trend-product-badge-discount">-' + discount + '%</div>';
					}
					if (availability && availability !== 'N/A') {
						var inStockText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['In Stock']) ? vortem_admin.strings['In Stock'] : 'In Stock';
						var availabilityText = (availability === 'In Stock' || availability === 'in stock') ? inStockText : availability;
						html += '<div class="trend-product-badge trend-product-badge-availability">' + availabilityText + '</div>';
					}
					html += '</div>';
					
					// Content
					html += '<div class="trend-product-content">';
					html += '<h3 class="trend-product-title">' + title + '</h3>';
					
					if (category && category !== 'N/A') {
						var categoryName = category.split('>').pop()?.trim() || category;
						html += '<div class="trend-category-badge">' + categoryName + '</div>';
					}
					
					if (rating > 0) {
						html += '<div class="trend-product-rating">';
						html += '<div class="trend-rating-stars">';
						html += '<svg class="trend-rating-star" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
						html += '<span class="trend-rating-value">' + rating.toFixed(1) + '</span>';
						html += '</div>';
						if (reviewCount > 0) {
							html += '<span class="trend-rating-count">(' + reviewCount + ' reviews)</span>';
						}
						html += '</div>';
					}
					
					// Price
					html += '<div class="trend-product-price">';
					if (finalPrice > 0) {
						html += '<span class="trend-price-final">' + currency + finalPrice.toFixed(2) + '</span>';
						if (discount > 0 && originalPrice > 0) {
							html += '<span class="trend-price-original">' + currency + originalPrice.toFixed(2) + '</span>';
						}
					} else {
						var priceNAText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['Price N/A']) ? vortem_admin.strings['Price N/A'] : 'Price N/A';
						html += '<span class="trend-price-na">' + priceNAText + '</span>';
					}
					html += '</div>';
					
					// Sentiment
					if (totalSentiment > 0) {
						var sentimentText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Sentiment) ? vortem_admin.strings.Sentiment : 'Sentiment';
						html += '<div class="trend-sentiment-section">';
						html += '<div class="trend-sentiment-header">';
						html += '<span class="trend-sentiment-label">' + sentimentText + ':</span>';
						html += '<span class="trend-sentiment-percentages">' + positivePercent + '% | ' + neutralPercent + '% | ' + negativePercent + '%</span>';
						html += '</div>';
						html += '<div class="trend-sentiment-bar">';
						if (positivePercent > 0) {
							html += '<div class="trend-sentiment-bar-positive" style="width: ' + positivePercent + '%" title="' + positivePercent + '% Positive"></div>';
						}
						if (neutralPercent > 0) {
							html += '<div class="trend-sentiment-bar-neutral" style="width: ' + neutralPercent + '%" title="' + neutralPercent + '% Neutral"></div>';
						}
						if (negativePercent > 0) {
							html += '<div class="trend-sentiment-bar-negative" style="width: ' + negativePercent + '%" title="' + negativePercent + '% Negative"></div>';
						}
						html += '</div>';
						html += '</div>';
					}
					
					// Metrics (only when the legacy product_selection_metrics block is present)
					if (hasMetrics) {
						html += '<div class="trend-metrics-section">';
						html += '<div class="trend-metric-item"><svg class="trend-metric-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg><span class="trend-metric-label">TI:</span><span class="trend-metric-value">' + ti.toFixed(2) + '</span></div>';
						html += '<div class="trend-metric-item"><svg class="trend-metric-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg><span class="trend-metric-label">PI:</span><span class="trend-metric-value">' + pi.toFixed(2) + '</span></div>';
						html += '<div class="trend-metric-item"><svg class="trend-metric-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg><span class="trend-metric-label">SSI:</span><span class="trend-metric-value">' + ssi.toFixed(2) + '</span></div>';
						html += '</div>';
					}
					
					// Amazon link
					if (url && url !== '#') {
						var viewOnAmazonText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['View on Amazon']) ? vortem_admin.strings['View on Amazon'] : 'View on Amazon';
						html += '<div class="trend-product-link">';
						html += '<a href="' + url + '" target="_blank" rel="noopener noreferrer">';
						html += '<span>' + viewOnAmazonText + '</span>';
						html += '<svg class="trend-product-link-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
						html += '</a>';
						html += '</div>';
					}
					
					html += '</div>'; // content
					html += '</div>'; // card
				});
				
				html += '</div>';
				
				// Results count
				var totalFound = responseData?.total_found || products.length;
				var showingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Showing) ? vortem_admin.strings.Showing : 'Showing';
				var ofText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.of) ? vortem_admin.strings.of : 'of';
				var productsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.products) ? vortem_admin.strings.products : 'products';
				html += '<div class="trend-results-count">';
				html += '<span>' + showingText + ' <strong>' + products.length + '</strong>';
				if (totalFound > products.length) {
					html += ' ' + ofText + ' <strong>' + totalFound + '</strong>';
				}
				html += ' ' + productsText + '</span>';
				html += '</div>';
				
				container.html(html);
			}
			
			// Function to load TikTok products from API
			function loadTikTokProducts(page) {
				page = page || 1;
				var limit = 12;
				var container = $('#tiktok-products-container');
				
				// Show loading state
				var loadingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['Loading TikTok products...']) ? vortem_admin.strings['Loading TikTok products...'] : 'Loading TikTok products...';
				container.html('<div class="tiktok-products-loading" style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #60a5fa; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 20px; color: #64748b;">' + loadingText + '</p></div>');
				
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'vortem_fetch_tiktok_products',
						nonce: vortem_admin.nonce,
						page: page,
						limit: limit
					},
					success: function(response) {
						if (response.success && response.data) {
							var products = [];
							var responseData = response.data;
							
							// Handle different response formats
							if (Array.isArray(responseData)) {
								products = responseData;
							} else if (Array.isArray(responseData.products)) {
								products = responseData.products;
							} else if (Array.isArray(responseData.data)) {
								products = responseData.data;
							}
							
							renderTikTokProducts(products, responseData);
						} else {
							var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
							var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['Failed to load TikTok products:']) ? vortem_admin.strings['Failed to load TikTok products:'] : 'Failed to load TikTok products:';
							container.html('<div class="tiktok-products-error" style="text-align: center; padding: 40px;"><p style="color: #ef4444;">' + failedText + ' ' + errorMsg + '</p></div>');
						}
					},
					error: function(xhr, status, error) {
						var errorText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings['Error loading TikTok products:']) ? vortem_admin.strings['Error loading TikTok products:'] : 'Error loading TikTok products:';
						container.html('<div class="tiktok-products-error" style="text-align: center; padding: 40px;"><p style="color: #ef4444;">' + errorText + ' ' + error + '</p></div>');
					}
				});
			}
			
			// Function to render TikTok products
			function renderTikTokProducts(products, responseData) {
				var container = $('#tiktok-products-container');

				if (!products || products.length === 0) {
					container.html('<div class="tiktok-products-empty" style="text-align: center; padding: 40px;"><p style="color: #64748b;">No TikTok products found.</p></div>');
					return;
				}

				var pageText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Page) ? vortem_admin.strings.Page : 'Page';
				var previousText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Previous) ? vortem_admin.strings.Previous : 'Previous';
				var nextText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Next) ? vortem_admin.strings.Next : 'Next';
				var ofText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.of) ? vortem_admin.strings.of : 'of';

				function escapeHtml(value) {
					return $('<div/>').text(value == null ? '' : String(value)).html();
				}

				function toHttps(url) {
					if (!url) return '';
					return url.replace(/^http:\/\//i, 'https://');
				}

				var html = '<div class="tiktok-products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px;">';

				products.forEach(function(product) {
					var streamUrl = toHttps(product.stream_url || '');
					var category = product.category || '';
					var hashtags = Array.isArray(product.hashtags) ? product.hashtags : (product.related_hashtags || []);

					html += '<div class="tiktok-product-card" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: flex; flex-direction: column;">';

					// Thumbnail (video first frame; user can press play to preview)
					html += '<div class="tiktok-product-thumb" style="position: relative; width: 100%; aspect-ratio: 9 / 16; background: #0f172a; overflow: hidden;">';
					if (streamUrl) {
						html += '<video src="' + escapeHtml(streamUrl) + '" preload="metadata" controls muted playsinline style="width: 100%; height: 100%; object-fit: cover; display: block; background: #0f172a;"></video>';
					} else {
						html += '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 12px;">No preview</div>';
					}
					html += '</div>';

					// Body: hashtags then category
					html += '<div class="tiktok-product-body" style="padding: 14px 14px 16px; display: flex; flex-direction: column; gap: 10px;">';

					if (hashtags.length > 0) {
						html += '<div class="tiktok-product-hashtags" style="display: flex; flex-wrap: wrap; gap: 6px;">';
						hashtags.slice(0, 6).forEach(function(tag) {
							html += '<span style="display: inline-block; padding: 3px 9px; background: #eff6ff; color: #2563eb; border-radius: 999px; font-size: 12px; font-weight: 500;">' + escapeHtml(tag) + '</span>';
						});
						if (hashtags.length > 6) {
							html += '<span style="padding: 3px 4px; font-size: 12px; color: #6b7280;">+' + (hashtags.length - 6) + '</span>';
						}
						html += '</div>';
					}

					if (category) {
						html += '<div class="tiktok-product-category" style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600;">' + escapeHtml(category) + '</div>';
					}

					html += '</div>'; // body
					html += '</div>'; // card
				});

				html += '</div>'; // grid

				// Pagination
				var currentPage = (responseData && responseData.page) ? parseInt(responseData.page, 10) : 1;
				var totalPages = (responseData && responseData.total_pages) ? parseInt(responseData.total_pages, 10) : 1;

				if (totalPages > 1) {
					var firstText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.First) ? vortem_admin.strings.First : 'First';
					var lastText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Last) ? vortem_admin.strings.Last : 'Last';
					var goText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.Go) ? vortem_admin.strings.Go : 'Go';

					var atFirst = currentPage <= 1;
					var atLast = currentPage >= totalPages;

					var btnBase = 'padding: 8px 12px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; color: #1f2937; min-width: 44px;';
					var btnEnabled = btnBase + ' cursor: pointer;';
					var btnDisabled = btnBase + ' cursor: not-allowed; opacity: 0.45;';

					html += '<div class="tiktok-pagination" style="margin-top: 8px; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px;">';

					html += '<button type="button" class="tiktok-pagination-btn" data-page="1" ' + (atFirst ? 'disabled' : '') + ' style="' + (atFirst ? btnDisabled : btnEnabled) + '" title="' + escapeHtml(firstText) + '">&laquo;</button>';
					html += '<button type="button" class="tiktok-pagination-btn" data-page="' + Math.max(1, currentPage - 1) + '" ' + (atFirst ? 'disabled' : '') + ' style="' + (atFirst ? btnDisabled : btnEnabled) + '" title="' + escapeHtml(previousText) + '">&lsaquo; ' + escapeHtml(previousText) + '</button>';

					html += '<span style="padding: 8px 4px; font-size: 14px; color: #6b7280; display: inline-flex; align-items: center; gap: 6px;">';
					html += escapeHtml(pageText);
					html += '<input type="number" class="tiktok-pagination-input" min="1" max="' + totalPages + '" value="' + currentPage + '" style="width: 64px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; text-align: center; color: #1f2937;" />';
					html += escapeHtml(ofText) + ' <strong style="color: #1f2937;">' + totalPages + '</strong>';
					html += '</span>';

					html += '<button type="button" class="tiktok-pagination-go" style="' + btnEnabled + '">' + escapeHtml(goText) + '</button>';

					html += '<button type="button" class="tiktok-pagination-btn" data-page="' + Math.min(totalPages, currentPage + 1) + '" ' + (atLast ? 'disabled' : '') + ' style="' + (atLast ? btnDisabled : btnEnabled) + '" title="' + escapeHtml(nextText) + '">' + escapeHtml(nextText) + ' &rsaquo;</button>';
					html += '<button type="button" class="tiktok-pagination-btn" data-page="' + totalPages + '" ' + (atLast ? 'disabled' : '') + ' style="' + (atLast ? btnDisabled : btnEnabled) + '" title="' + escapeHtml(lastText) + '">&raquo;</button>';

					html += '</div>';
				}

				container.html(html);

				// Replace broken videos (e.g. corrupt source returning HTML instead of MP4)
				// with a clean placeholder so the user doesn't see the browser's native error UI.
				container.find('.tiktok-product-thumb video').each(function() {
					var video = this;
					var swap = function() {
						var $thumb = $(video).closest('.tiktok-product-thumb');
						if (!$thumb.length || $thumb.data('vortemFallbackApplied')) return;
						$thumb.data('vortemFallbackApplied', true);
						$thumb.html('<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;text-align:center;padding:12px;">Preview unavailable</div>');
					};
					video.addEventListener('error', swap, true);
					// Some browsers fire `stalled`/`emptied` instead of `error` for malformed bytes.
					video.addEventListener('abort', swap, true);
				});

				$('.tiktok-pagination-btn').on('click', function() {
					if ($(this).is(':disabled')) return;
					var page = parseInt($(this).data('page'), 10);
					if (!isNaN(page) && page >= 1) {
						loadTikTokProducts(page);
					}
				});

				function gotoInputPage() {
					var $input = container.find('.tiktok-pagination-input');
					if (!$input.length) return;
					var requested = parseInt($input.val(), 10);
					if (isNaN(requested)) return;
					var max = parseInt($input.attr('max'), 10) || 1;
					var clamped = Math.min(Math.max(requested, 1), max);
					$input.val(clamped);
					if (clamped !== currentPage) {
						loadTikTokProducts(clamped);
					}
				}

				$('.tiktok-pagination-go').on('click', gotoInputPage);
				$('.tiktok-pagination-input').on('keydown', function(e) {
					if (e.key === 'Enter' || e.keyCode === 13) {
						e.preventDefault();
						gotoInputPage();
					}
				});
			}

			// Function to display products grid with new styling and pagination
			function displayProductsGrid(products, paginationData) {
				// Hide skeleton loader
				$('#products-skeleton-loader').hide();

				if (!products || !Array.isArray(products) || products.length === 0) {
					$('#product-dashboard-content').html('<div class="notice notice-warning"><p><strong>⚠️ No Products Found</strong></p><p>No products were found for the selected category.</p></div>');
					return;
				}

				paginationData = paginationData || { currentPage: 1, totalPages: 1, totalFound: products.length, limit: vortem_admin.products_per_page || 16 };
				
				VortemLogger.log('=== DISPLAYING PRODUCTS GRID ===');
				VortemLogger.log('Products data:', products);
				VortemLogger.log('CSS classes available:', $('.vortem-product-card').length);
				
				// Update filter state current page
				filterState.currentPage = paginationData.currentPage || 1;
				
				// Preserve button state based on filter state (don't reset it)
				// Button state is managed by fetchProductsWithFilter function
				
				// Check if CSS is loaded
				var testElement = $('<div class="vortem-product-card"></div>');
				$('body').append(testElement);
				var computedStyle = window.getComputedStyle(testElement[0]);
				VortemLogger.log('CSS loaded check - border:', computedStyle.border);
				testElement.remove();
				
				if (!products || products.length === 0) {
					// Hide skeleton loader
					$('#products-skeleton-loader').hide();
					$('#product-dashboard-content').html('<div class="notice notice-warning"><p><strong>No Products Found</strong></p><p>No products were retrieved. Please try fetching new products.</p></div>');
					return;
				}
				
				// Helper function to format numbers with commas
				function formatNumberWithCommas(num, decimals) {
					if (num === null || num === undefined || isNaN(num)) {
						return '0';
					}
					var parts = num.toFixed(decimals).split('.');
					parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
					return parts.join('.');
				}
				
				var html = '<div class="products-grid">';
				
				products.forEach(function(product) {
					var imageUrl = product.images && product.images.main ? product.images.main : '';
					
					// Price handling - support price range and sale price
					var priceOriginal = null;
					var priceSale = null;
					var priceLow = null;
					var priceHigh = null;
					var currency = (typeof vortem_admin !== 'undefined' && vortem_admin.currency_code) ? vortem_admin.currency_code : 'USD';
					
					if (product.variations && product.variations.length > 0) {
						priceSale = parseFloat(product.variations[0].price);
					} else if (product.price) {
						// Check for high_price and low_price fields (range format)
						if (product.price.low_price) {
							priceLow = parseFloat(product.price.low_price);
						}
						if (product.price.high_price) {
							priceHigh = parseFloat(product.price.high_price);
						}
						// Fallback to sale/original format
						if (product.price.sale) {
							priceSale = parseFloat(product.price.sale);
						}
						if (product.price.original) {
							priceOriginal = parseFloat(product.price.original);
						}
						if (product.price.currency) {
							currency = product.price.currency;
						}
					}
					
					var productId = product.product_id || product.sku;
					var productTitle = product.title || 'Untitled Product';
					var category = product.vortem_cat || '';
					
					// Determine status badge text based on import status
					var addedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.added) ? vortem_admin.strings.added : 'Added';
					var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : 'NEW';
					var statusBadgeText = (product.woo_product_id && product.woo_product_id !== '') ? addedText : newText;
					var statusBadgeClass = (product.woo_product_id && product.woo_product_id !== '') ? 'status-added' : 'status-new';
					
					// Sales count
					var salescount = product.salescount || product.sales_count || product.salesCount || '0';
					
					// Create modern minimal product card
					html += '<div class="vortem-product-card" data-product-id="' + productId + '">';
					
					// Image container
					html += '<div class="product-image-container">';
					html += '<div class="product-status-badge ' + statusBadgeClass + '">' + statusBadgeText + '</div>';
					// Preview button (only for products added to WooCommerce)
					if (product.woo_product_id && product.woo_product_id !== '') {
						var previewUrl = product.preview_url || (typeof vortem_admin !== 'undefined' && vortem_admin.site_url ? vortem_admin.site_url + '?p=' + product.woo_product_id + '&preview=true' : '#');
						var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : '<?php echo esc_js( __( 'Preview', 'vortem-ai' ) ); ?>';
						html += '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + product.woo_product_id + '">' + previewText + '</a>';
					}
					if (imageUrl) {
						html += '<img src="' + imageUrl + '" alt="' + productTitle + '" loading="lazy" onerror="var img=this; img.onerror=null; img.style.display=\'none\'; var placeholder=document.createElement(\'div\'); placeholder.className=\'no-image-placeholder\'; placeholder.innerHTML=\'<svg width=\\\'64\\\' height=\\\'64\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' xmlns=\\\'http://www.w3.org/2000/svg\\\' style=\\\'opacity: 0.3; margin-bottom: 8px;\\\'><path d=\\\'M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21\\\' stroke=\\\'currentColor\\\' stroke-width=\\\'2\\\' stroke-linecap=\\\'round\\\' stroke-linejoin=\\\'round\\\'/></svg><span>No Image Available</span>\'; img.parentNode.appendChild(placeholder);">';
					} else {
						html += '<div class="no-image-placeholder"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3; margin-bottom: 8px;"><path d="M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>No Image Available</span></div>';
					}
					html += '</div>';
					
					// Product content section
					html += '<div class="product-content-section">';
					
					// Category badge (if available)
					if (category) {
						var categoryParts = category.split('/');
						var categoryName = categoryParts[categoryParts.length - 1] || category;
						html += '<div class="product-category-badge">' + categoryName + '</div>';
					}
					
					// Product title
					html += '<div class="product-title-section">';
					html += '<h3>' + productTitle + '</h3>';
					html += '</div>';
					
					// Price section - modern minimal design with sales badge on the same line
					html += '<div class="product-price-container">';
					html += '<div class="product-price-wrapper">';
					// Display price range if both low_price and high_price are available
					if (priceLow !== null && priceHigh !== null) {
						html += '<span class="product-price-value">' + formatNumberWithCommas(priceLow, 1) + ' - ' + formatNumberWithCommas(priceHigh, 1) + ' ' + currency + '</span>';
					} else if (priceLow === null && priceHigh !== null) {
						// Display only high_price if low_price is null
						html += '<span class="product-price-value">' + formatNumberWithCommas(priceHigh, 2) + ' ' + currency + '</span>';
					} else if (priceSale !== null) {
						html += '<span class="product-price-value">' + formatNumberWithCommas(priceSale, 2) + ' ' + currency + '</span>';
						if (priceOriginal !== null && priceOriginal > priceSale) {
							html += '<span class="product-price-original">' + formatNumberWithCommas(priceOriginal, 2) + ' ' + currency + '</span>';
						}
					} else if (priceOriginal !== null) {
						html += '<span class="product-price-value">' + formatNumberWithCommas(priceOriginal, 2) + ' ' + currency + '</span>';
					} else {
						html += '<span class="product-price-value">N/A</span>';
					}
					html += '</div>';
					
					// Sales count badge on the right side
					if (salescount && salescount !== '0') {
						var salesText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.sales) ? vortem_admin.strings.sales : 'sales';
						html += '<div class="product-sales-badge">' + salescount + ' ' + salesText + '</div>';
					}
					html += '</div>';
					
					// Action buttons
					html += '<div class="product-actions-section">';
					html += '<div class="product-actions-buttons">';
					
					var isImported = product.woo_product_id && product.woo_product_id !== '';
					
					// Get _id if available (for imported products from Show All Added Products)
					var productIdValue = productId;
					var productIdValueAttr = 'data-product-id="' + productIdValue + '"';
					var productIdAttr = productIdValueAttr;
					if (product._id) {
						productIdAttr += ' data-api-id="' + product._id + '"';
					}
					
					if (isImported) {
						html += '<button type="button" class="button button-secondary delete-product-btn" ' + productIdAttr + '><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <?php echo esc_js( __( 'Delete', 'vortem-ai' ) ); ?></button>';
						html += '<button type="button" class="button button-primary import-product-btn" data-product-id="' + productId + '" style="display: none;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <?php echo esc_js( __( 'Import', 'vortem-ai' ) ); ?></button>';
					} else {
						html += '<button type="button" class="button button-primary import-product-btn" data-product-id="' + productId + '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <?php echo esc_js( __( 'Import', 'vortem-ai' ) ); ?></button>';
						html += '<button type="button" class="button button-secondary delete-product-btn" ' + productIdAttr + ' style="display: none;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> <?php echo esc_js( __( 'Delete', 'vortem-ai' ) ); ?></button>';
					}
					
					html += '</div>';
					html += '</div>';
					html += '</div>'; // End content section
					html += '</div>'; // End card
				});
				
				html += '</div>';
				
				// Add pagination controls
				html += buildPaginationControls(paginationData);
				
				$('#product-dashboard-content').html(html);
				
				// Hide skeleton loader after content is loaded
				$('#products-skeleton-loader').hide();
				
				// Attach pagination event handlers
				attachPaginationHandlers();
				
				// Force apply styles after HTML is inserted
				setTimeout(function() {
					forceApplyStyles();
				}, 100);
				
				// Check import status for products (only if not showing imported only)
				// This ensures status badges and preview buttons are updated correctly
				if (!filterState.showImportedOnly) {
					checkProductsImportStatusInAdmin(products);
				}
				
				VortemLogger.log('Products grid displayed successfully');
			}
			
			// Check import status for products in admin page
			function checkProductsImportStatusInAdmin(products) {
				if (!products || !Array.isArray(products) || products.length === 0) {
					return;
				}
				
				VortemLogger.log("Checking import status for products in admin...");
				
				products.forEach(function (product) {
					var productId = product.product_id || product.sku;
					// Use SKU from product, or fallback to product_id (without AE_ prefix if present)
					var sku = product.sku || product.product_id || "";
					// Remove AE_ prefix from SKU if present for better matching
					if (sku && sku.indexOf('AE_') === 0) {
						sku = sku.substring(3);
					}
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: "POST",
						data: {
							action: "vortem_check_product_status",
							nonce: vortem_admin.nonce,
							product_id: productId,
							sku: sku,
						},
						success: function (response) {
							if (response.success) {
								// Try to find card by productId (with or without AE_ prefix)
								var card = $('.vortem-product-card[data-product-id="' + productId + '"]');
								
								// If not found and productId has "AE_" prefix, try without it
								if (card.length === 0 && productId.indexOf('AE_') === 0) {
									var productIdWithoutPrefix = productId.substring(3); // Remove "AE_" prefix
									card = $('.vortem-product-card[data-product-id="' + productIdWithoutPrefix + '"]');
								}
								
								// If still not found and productId doesn't have "AE_" prefix, try with it
								if (card.length === 0 && productId.indexOf('AE_') !== 0) {
									var productIdWithPrefix = 'AE_' + productId;
									card = $('.vortem-product-card[data-product-id="' + productIdWithPrefix + '"]');
								}
								
								if (card.length === 0) {
									VortemLogger.error("Card not found for product:", productId);
									return;
								}
								
								var importBtn = card.find(".import-product-btn");
								var deleteBtn = card.find(".delete-product-btn");
								var imageContainer = card.find(".product-image-container");
								
								// If product exists in WooCommerce (even if not properly imported), show Delete button
								if (response.data.exists_in_woocommerce || response.data.is_imported) {
									// Hide import button and show delete button
									importBtn.hide();
									deleteBtn.show();
									
									// Update status badge
									var statusBadge = card.find(".product-status-badge");
									var addedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.added) ? vortem_admin.strings.added : 'Added';
									var existsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.exists) ? vortem_admin.strings.exists : 'Exists';
									if (response.data.is_imported) {
										// Properly imported
										statusBadge.removeClass("status-new").addClass("status-added").text(addedText).css({
											background: "rgb(70, 180, 80)",
											color: "white",
											display: "block",
										});
									} else {
										// Exists but not properly imported
										statusBadge.text(existsText).css({
											background: "#f56e28",
											color: "white",
											display: "block",
										});
									}
									
									// Add preview button if product has woo_product_id
									if (response.data.woo_product_id && imageContainer.length > 0) {
										var existingPreview = imageContainer.find('.product-preview-button');
										if (existingPreview.length === 0) {
											var previewUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.site_url) ? vortem_admin.site_url + '?p=' + response.data.woo_product_id + '&preview=true' : '#';
											var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
											var previewButton = '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + response.data.woo_product_id + '">' + previewText + '</a>';
											imageContainer.prepend(previewButton);
											VortemLogger.log("Preview button added for product:", productId);
										}
									}
								}
							}
						},
						error: function (xhr, status, error) {
							VortemLogger.error("Status check error for", productId, ":", error);
						},
					});
				});
			}
			
			// Build pagination controls HTML
			function buildPaginationControls(paginationData) {
				var currentPage = paginationData.currentPage || 1;
				var totalPages = paginationData.totalPages || 1;
				var totalFound = paginationData.totalFound || 0;
				
				var showingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.showing) ? vortem_admin.strings.showing : 'Showing';
				var productsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.products) ? vortem_admin.strings.products : 'products';
				var ofText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.of) ? vortem_admin.strings.of : 'of';
				
				if (totalPages <= 1) {
					return '<div class="vortem-pagination-info">' + showingText + ' ' + totalFound + ' ' + productsText + '</div>';
				}
				
				var html = '<div class="vortem-pagination">';
				
				// Pagination info
				var startItem = ((currentPage - 1) * (paginationData.limit || 12)) + 1;
				var endItem = Math.min(currentPage * (paginationData.limit || 12), totalFound);
				html += '<div class="vortem-pagination-info">';
				html += showingText + ' ' + startItem + '-' + endItem + ' ' + ofText + ' ' + totalFound + ' ' + productsText;
				html += '</div>';
				
				// Pagination controls
				html += '<div class="vortem-pagination-controls">';
				
				// Previous button
				var previousText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.previous) ? vortem_admin.strings.previous : '← Previous';
				if (currentPage > 1) {
					html += '<button type="button" class="button vortem-pagination-btn" data-page="' + (currentPage - 1) + '">' + previousText + '</button>';
				} else {
					html += '<button type="button" class="button vortem-pagination-btn" disabled>' + previousText + '</button>';
				}
				
				// Page numbers (show up to 5 pages around current page)
				var startPage = Math.max(1, currentPage - 2);
				var endPage = Math.min(totalPages, currentPage + 2);
				
				if (startPage > 1) {
					html += '<button type="button" class="button vortem-pagination-btn" data-page="1">1</button>';
					if (startPage > 2) {
						html += '<span>...</span>';
					}
				}
				
				for (var i = startPage; i <= endPage; i++) {
					if (i === currentPage) {
						html += '<button type="button" class="button button-primary vortem-pagination-btn" data-page="' + i + '" disabled>' + i + '</button>';
					} else {
						html += '<button type="button" class="button vortem-pagination-btn" data-page="' + i + '">' + i + '</button>';
					}
				}
				
				if (endPage < totalPages) {
					if (endPage < totalPages - 1) {
						html += '<span>...</span>';
					}
					html += '<button type="button" class="button vortem-pagination-btn" data-page="' + totalPages + '">' + totalPages + '</button>';
				}
				
				// Next button
				var nextText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.next) ? vortem_admin.strings.next : 'Next →';
				if (currentPage < totalPages) {
					html += '<button type="button" class="button vortem-pagination-btn" data-page="' + (currentPage + 1) + '">' + nextText + '</button>';
				} else {
					html += '<button type="button" class="button vortem-pagination-btn" disabled>' + nextText + '</button>';
				}
				
				html += '</div>';
				html += '</div>';
				
				return html;
			}
			
			// Attach pagination event handlers
			function attachPaginationHandlers() {
				$(document).off('click', '.vortem-pagination-btn');
				$(document).on('click', '.vortem-pagination-btn', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					var button = $(this);
					if (button.prop('disabled')) {
						return;
					}
					
					var page = parseInt(button.data('page'));
					if (!page || page < 1) {
						return;
					}
					
					VortemLogger.log('Pagination: Loading page', page);
					
					// Fetch products with current filter state preserved
					fetchProductsWithFilter(page, filterState.showImportedOnly);
				});
			}

			// Handle Import button click - REMOVED: Now handled by vortem-buttons.js with modal
			// The import functionality is now in vortem-buttons.js which shows a modal first
			
			// Handle Delete button click (dynamically created buttons)
			$(document).on('click', '.delete-product-btn', function() {
				var button = $(this);
				var productId = button.data('product-id');
				var apiId = button.data('api-id'); // Get _id from data attribute if available
				
				if (!productId) {
					showNotice('<?php esc_html_e( 'Product ID not found', 'vortem-ai' ); ?>', 'error');
					return;
				}
				
				var deleteMessage = '<?php esc_html_e( 'Are you sure you want to delete this product?', 'vortem-ai' ); ?>';
				
				// Show modal instead of confirm
				showDeleteConfirmationModal(deleteMessage, function() {
					VortemLogger.log('Delete button clicked for product:', productId);
					if (apiId) {
						VortemLogger.log('API ID (_id) found:', apiId);
					}
					
					var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="23.562" opacity="0.9"/></svg> Deleting...';
					button.prop('disabled', true).html(deletingText);
					
					$.ajax({
						url: vortem_admin.ajax_url,
						type: 'POST',
						data: {
							action: 'vortem_delete_single_product',
							product_id: productId,
							api_id: apiId || '',
							nonce: vortem_admin.nonce
						},
						success: function(response) {
							if (response.success) {
								showNotice('<?php esc_html_e( 'Product deleted successfully', 'vortem-ai' ); ?>', 'success');
								
								// Hide delete button and show import button
								button.removeClass('deleting').hide();
								var importBtn = button.siblings('.import-product-btn');
								if (importBtn.length) {
									// Reset button state - remove importing class and set correct text
									var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import';
									importBtn.removeClass('importing').prop('disabled', false).html(importText);
									importBtn.show();
								}
								
								// Remove preview button
								var productCard = button.closest('.vortem-product-card');
								if (productCard.length) {
									var previewButton = productCard.find('.product-preview-button');
									if (previewButton.length) {
										previewButton.remove();
									}
									
									// Update status badge to "NEW"
									var statusBadge = productCard.find('.product-status-badge');
									var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : '<?php echo esc_js( __( 'NEW', 'vortem-ai' ) ); ?>';
									if (statusBadge.length) {
										statusBadge.removeClass('status-added').addClass('status-new').text(newText).css({
											background: 'rgb(0, 115, 170)',
											color: 'white',
											display: 'block'
										});
									}
								}
							} else {
								var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
								button.prop('disabled', false).html(deleteText);
								var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
								showNotice('<?php esc_html_e( 'Delete failed: ', 'vortem-ai' ); ?>' + errorMsg, 'error');
							}
						},
						error: function() {
							var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
							button.prop('disabled', false).html(deleteText);
							showNotice('<?php esc_html_e( 'Delete failed. Please try again.', 'vortem-ai' ); ?>', 'error');
						}
					});
				});
			});

			$(window).on('click', function(event) {
				if (event.target.id === 'product-details-modal') {
					$('#product-details-modal').hide();
				}
			});
		});
		<?php
		$vortem_products_inline_js = ob_get_clean();
		if ( ! empty( $vortem_products_inline_js ) ) {
			// Attach to the already-enqueued "vortem-products-inline" handle
			// (registered in enqueue_admin_scripts() only on Vortem products
			// pages). This keeps WordPress responsible for printing, ordering
			// and honouring script dependencies - no raw <script> tag is ever
			// emitted from PHP output.
			wp_add_inline_script( 'vortem-products-inline', $vortem_products_inline_js );
		}
		?>

		<!-- SEO Import Success Modal -->
		<div class="modal" id="seo-import-modal" aria-hidden="true" role="dialog" aria-labelledby="seo-import-modal-title">
			<div class="modal-backdrop" data-close="seo-import-modal"></div>
			<div class="modal-dialog alert-dialog">
				<div class="alert-header">
					<div class="alert-icon success">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h3 id="seo-import-modal-title"><?php echo esc_html__( 'SEO Import Completed', 'vortem-ai' ); ?></h3>
				</div>
				<div class="alert-body">
					<p id="seo-import-message"><?php echo esc_html__( 'The following SEO optimizations have been applied:', 'vortem-ai' ); ?></p>
					<ul id="seo-import-items" style="list-style: none; padding: 0; margin: 15px 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ Keyphrase', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ SEO Title', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ SEO Description', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ Meta Description', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ Tags', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
							<strong><?php echo esc_html__( '✓ Meta Title', 'vortem-ai' ); ?></strong>
						</li>
						<li style="padding: 8px 0;">
							<strong><?php echo esc_html__( '✓ Headings', 'vortem-ai' ); ?></strong>
						</li>
					</ul>
				</div>
				<div class="alert-actions">
					<button class="btn btn-primary" id="seo-import-ok">
						<?php echo esc_html__( 'OK', 'vortem-ai' ); ?>
						<span id="seo-import-countdown" style="margin-left: 8px; font-weight: normal; opacity: 0.7;"></span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Orders page
	 *
	 * Displays analytics dashboard with data fetched exclusively from local
	 * WordPress/WooCommerce database. No external APIs are used.
	 */
	public function analytics_tabs_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Determine initial tab from URL
		      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'vortem-analytics';
		$initial_tab  = 'analytics';

		// Get URLs for cache clearing and export
		$clear_cache_url = add_query_arg(
			array(
				'megadash_clear_cache' => '1',
				'_wpnonce'             => wp_create_nonce( 'megadash_clear_cache' ),
			),
			admin_url( 'admin.php?page=vortem-analytics' )
		);

		$export_url = add_query_arg(
			array(
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			rest_url( 'vortem/v1/export/' )
		);

		$api_base_url = '';

		// Currency settings
		$currency_symbol = '';
		$currency_pos    = 'left';
		if ( function_exists( 'WC' ) ) {
			$currency_symbol = get_woocommerce_currency_symbol();
			$currency_pos    = get_option( 'woocommerce_currency_pos', 'left' );
		}

		// Get RTL direction for analytics page
		$is_rtl_analytics = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl_analytics = Vortem_Translation_Manager::is_rtl();
		}
		$analytics_dir = $is_rtl_analytics ? 'rtl' : 'ltr';
		?>
		<div class="wrap" id="vortem-analytics-tabs-app" dir="<?php echo esc_attr( $analytics_dir ); ?>">
			<div id="vortem-analytics-tabs-root"></div>
		</div>
		<?php
		// Prepare config data for inline script
		$analytics_config = array(
			'initialTab'     => $initial_tab,
			'pluginUrl'      => VORTEM_PLUGIN_URL,
			'clearCacheUrl'  => $clear_cache_url,
			'exportUrl'      => $export_url,
			'apiBaseUrl'     => $api_base_url,
			'currencySymbol' => $currency_symbol,
			'currencyPos'    => $currency_pos,
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'restUrl'        => rest_url( 'vortem/v1/metrics/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'adminNonce'     => wp_create_nonce( 'vortem_admin_nonce' ),
			'locale'         => get_locale(),
			'strings'        => array(
				'analytics'                     => __( 'Shop Analytics', 'vortem-ai' ),
				'biAnalyticsHub'                => __( 'BI Analytics Hub', 'vortem-ai' ),
				'loading'                       => __( 'Loading...', 'vortem-ai' ),
				'refresh'                       => __( 'Refresh', 'vortem-ai' ),
				'clearCache'                    => __( 'Clear Cache', 'vortem-ai' ),
				'exportCsv'                     => __( 'Export CSV', 'vortem-ai' ),
				'woocommerceAnalytics'          => __( 'WooCommerce Analytics', 'vortem-ai' ),
				'wordpressAnalytics'            => __( 'WordPress Analytics', 'vortem-ai' ),
				'analyticsDashboard'            => __( 'ANALYTICS DASHBOARD', 'vortem-ai' ),
				'businessIntelligence'          => __( 'Business Intelligence', 'vortem-ai' ),
				'analyticsHub'                  => __( 'Analytics Hub', 'vortem-ai' ),
				'comprehensiveAnalytics'        => __( 'Comprehensive analytics and insights powered by advanced data intelligence for data-driven decision making', 'vortem-ai' ),
				'performanceOverview'           => __( 'Performance Overview', 'vortem-ai' ),
				'keyPerformanceIndicators'      => __( 'Key performance indicators at a glance', 'vortem-ai' ),
				'analyticsInsights'             => __( 'Analytics Insights', 'vortem-ai' ),
				'deepDiveMetrics'               => __( 'Deep dive into performance metrics', 'vortem-ai' ),
				'customerIntelligence'          => __( 'Customer Intelligence', 'vortem-ai' ),
				'understandCustomerBehavior'    => __( 'Understand customer behavior and trends', 'vortem-ai' ),
				'marketAnalysis'                => __( 'Market Analysis', 'vortem-ai' ),
				'comprehensiveMarketComparison' => __( 'Comprehensive market comparison and category insights', 'vortem-ai' ),
				'dataIntelligence'              => __( 'Data Intelligence', 'vortem-ai' ),
				'detailedAnalyticsTables'       => __( 'Detailed analytics tables and pricing insights', 'vortem-ai' ),
				'last_updated'                  => __( 'Last updated:', 'vortem-ai' ),
				'noData'                        => __( 'No data available', 'vortem-ai' ),
				'errorLoadingData'              => __( 'Error loading data', 'vortem-ai' ),
			),
		);

		// Add inline script using wp_add_inline_script
		wp_add_inline_script(
			'vortem-admin',
			'window.vortemAnalyticsTabsConfig = ' . wp_json_encode( $analytics_config ) . ';',
			'before'
		);
	}

	public function orders_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		$clear_cache_url = add_query_arg(
			array(
				'vortem_clear_cache' => '1',
				'_wpnonce'           => wp_create_nonce( 'vortem_clear_cache' ),
			),
			admin_url( 'admin.php?page=vortem-analytics' )
		);

		$export_url = add_query_arg(
			array(
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			rest_url( 'vortem/v1/export/' )
		);

		// Get RTL direction for vortem page
		$is_rtl_orders = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl_orders = Vortem_Translation_Manager::is_rtl();
		}
		$vortem_dir = $is_rtl_orders ? 'rtl' : 'ltr';
		?>
		<div class="wrap" id="vortem-app" dir="<?php echo esc_attr( $vortem_dir ); ?>">
			<div class="vortem-header">
				<div class="vortem-header-left">
					<h1 class="vortem-title">
						<span class="dashicons dashicons-chart-area"></span>
						<?php echo esc_html__( 'Analytics', 'vortem-ai' ); ?>
					</h1>
					<span class="vortem-last-updated" id="vortem-last-updated">
						<?php echo esc_html__( 'Loading...', 'vortem-ai' ); ?>
					</span>
				</div>
				<div class="vortem-header-right">
					<button type="button" class="vortem-btn vortem-btn-refresh" id="vortem-refresh-btn" aria-label="<?php echo esc_attr__( 'Refresh', 'vortem-ai' ); ?>">
						<span class="dashicons dashicons-update" id="vortem-refresh-icon"></span>
					</button>
					<a href="<?php echo esc_url( $clear_cache_url ); ?>" class="vortem-btn vortem-btn-secondary">
						<?php echo esc_html__( 'Clear Cache', 'vortem-ai' ); ?>
					</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="vortem-btn vortem-btn-secondary" id="vortem-export-btn" download>
						<span class="dashicons dashicons-download"></span>
						<?php echo esc_html__( 'Export CSV', 'vortem-ai' ); ?>
					</a>
				</div>
			</div>

			<div class="vortem-dashboard">
				<!-- WooCommerce Section -->
				<section class="vortem-section vortem-section-woocommerce" id="vortem-woocommerce">
					<div class="vortem-section-header">
						<h2 class="vortem-section-title">
							<span class="dashicons dashicons-cart"></span>
							<?php echo esc_html__( 'WooCommerce Analytics', 'vortem-ai' ); ?>
						</h2>
					</div>
					<div class="vortem-charts-container" id="vortem-woocommerce-charts"></div>
					<div class="vortem-grid vortem-grid-woocommerce" id="vortem-woocommerce-grid">
						<!-- Cards will be populated by JavaScript -->
						<div class="vortem-card vortem-skeleton">
							<div class="vortem-card-icon"></div>
							<div class="vortem-card-label"></div>
							<div class="vortem-card-value"></div>
						</div>
					</div>
				</section>

				<!-- WordPress Section -->
				<section class="vortem-section vortem-section-wordpress" id="vortem-wordpress">
					<div class="vortem-section-header">
						<h2 class="vortem-section-title">
							<span class="dashicons dashicons-wordpress"></span>
							<?php echo esc_html__( 'WordPress Analytics', 'vortem-ai' ); ?>
						</h2>
					</div>
					<div class="vortem-charts-container" id="vortem-wordpress-charts"></div>
					<div class="vortem-grid vortem-grid-wordpress" id="vortem-wordpress-grid">
						<!-- Cards will be populated by JavaScript -->
						<div class="vortem-card vortem-skeleton">
							<div class="vortem-card-icon"></div>
							<div class="vortem-card-label"></div>
							<div class="vortem-card-value"></div>
						</div>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Email Marketing page
	 */
	public function email_marketing_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Load the email marketing HTML template
		include VORTEM_PLUGIN_DIR . 'admin/partials/email-marketing-page.php';
	}

	/**
	 * WooCommerce Orders page
	 *
	 * Displays orders similar to WooCommerce's native orders page
	 */
	public function wc_orders_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Check if WooCommerce is active
		if ( ! function_exists( 'WC' ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is required to view orders.', 'vortem-ai' ) . '</p></div></div>';
			return;
		}

		// Render products page with orders tab active (consistent with analytics pattern)
		// The products_page() function will detect we're on vortem-orders and show orders tab
		$this->products_page();
	}

	/**
	 * Insights page
	 */
	public function insights_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Load the insights HTML template
		include VORTEM_PLUGIN_DIR . 'admin/partials/insights-page.php';
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Debug: Log that settings_page is called

		// Handle form submissions
		$this->handle_form_submissions();

		// Handle reset setup (debug only)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug-only feature with WP_DEBUG check
		if ( isset( $_GET['reset_setup'] ) && sanitize_text_field( wp_unslash( $_GET['reset_setup'] ) ) === '1' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->reset_setup_completion();
		}

		// Show auth success notice
		$this->show_auth_success_notice();

		// Get RTL direction for settings page
		$is_rtl_settings = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl_settings = Vortem_Translation_Manager::is_rtl();
		}
		$settings_dir = $is_rtl_settings ? 'rtl' : 'ltr';

		$vortem_logo_path = defined( 'VORTEM_PLUGIN_DIR' ) ? VORTEM_PLUGIN_DIR . 'assets/images/logo.png' : '';
		$vortem_logo_url  = defined( 'VORTEM_PLUGIN_URL' ) ? VORTEM_PLUGIN_URL . 'assets/images/logo.png' : '';
		?>
		<div class="settings-workspace-wrap" dir="<?php echo esc_attr( $settings_dir ); ?>">
			<!-- Background orbs -->
			<div class="settings-bg-orb settings-bg-orb-1" aria-hidden="true"></div>
			<div class="settings-bg-orb settings-bg-orb-2" aria-hidden="true"></div>

			<!-- Hero header banner -->
			<header class="settings-hero">
				<div class="settings-hero-inner">
					<div class="settings-hero-left">
						<div class="settings-hero-logo">
							<?php if ( ! empty( $vortem_logo_url ) && ! empty( $vortem_logo_path ) && file_exists( $vortem_logo_path ) ) : ?>
								<img src="<?php echo esc_url( $vortem_logo_url ); ?>" alt="" class="settings-hero-logo-img" />
							<?php else : ?>
								<img src="<?php echo esc_url( 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="%2308B8B8"><rect width="64" height="64" rx="12" fill="%2308B8B8" opacity=".15"/><path d="M32 18l12 8v12l-12 8-12-8V26l12-8z" fill="%2308B8B8"/></svg>' ) ); ?>" alt="" class="settings-hero-logo-img" />
							<?php endif; ?>
						</div>
						<div class="settings-hero-text">
							<span class="settings-hero-eyebrow"><?php esc_html_e( 'Configuration', 'vortem-ai' ); ?></span>
							<h1 class="settings-hero-title"><?php esc_html_e( 'Settings', 'vortem-ai' ); ?></h1>
						</div>
					</div>
					<div class="settings-hero-right">
						<span class="settings-hero-live-badge">
							<span class="settings-hero-live-dot" aria-hidden="true"></span>
							<?php esc_html_e( 'Live', 'vortem-ai' ); ?>
						</span>
						<span class="settings-hero-date">
							<?php echo esc_html( wp_date( 'M j, Y' ) ); ?>
						</span>
					</div>
				</div>
				<span class="settings-hero-orb" aria-hidden="true"></span>
			</header>

			<!-- Main content -->
			<div class="settings-main">
				<!-- Custom notices at the top of page content -->
				<?php $this->show_all_custom_notices(); ?>

				<form method="post" action="" id="vortem-settings-form" class="vortem-settings-form">
					<?php wp_nonce_field( 'vortem_setup_auth', 'vortem_nonce' ); ?>
					<?php
					settings_fields( 'vortem_settings' );
					do_settings_sections( 'vortem_settings' );
					?>

					<div class="vortem-settings-cards">

							<!-- Card: Display -->
							<section class="vortem-settings-card" data-section="display">
								<header class="vortem-settings-card-head">
									<span class="vortem-settings-card-icon" aria-hidden="true">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/>
											<rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/>
											<rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/>
											<rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/>
										</svg>
									</span>
									<div class="vortem-settings-card-titles">
										<h2 class="vortem-settings-card-title"><?php esc_html_e( 'Display', 'vortem-ai' ); ?></h2>
										<p class="vortem-settings-card-sub"><?php esc_html_e( 'How products and currency are presented in your store.', 'vortem-ai' ); ?></p>
									</div>
								</header>
								<div class="vortem-settings-card-body">

									<div class="vortem-settings-row">
										<div class="vortem-settings-row-meta">
											<label class="vortem-settings-row-label" for="vortem_products_per_page"><?php esc_html_e( 'Products per page', 'vortem-ai' ); ?></label>
											<p class="vortem-settings-row-help"><?php esc_html_e( 'Default: 16. Range: 1–100.', 'vortem-ai' ); ?></p>
										</div>
										<div class="vortem-settings-row-control">
											<input type="number" name="vortem_products_per_page" id="vortem_products_per_page" value="<?php echo esc_attr( get_option( 'vortem_products_per_page', 16 ) ); ?>" min="1" max="100" class="vortem-settings-num" />
										</div>
									</div>

									<div class="vortem-settings-row">
										<div class="vortem-settings-row-meta">
											<span class="vortem-settings-row-label"><?php esc_html_e( 'Currency', 'vortem-ai' ); ?></span>
											<p class="vortem-settings-row-help"><?php esc_html_e( 'Currency used across plugin pricing. Click "Update" to save your selection.', 'vortem-ai' ); ?></p>
										</div>
										<div class="vortem-settings-row-control vortem-settings-row-control--currency">
											<?php $current_currency = get_option( 'vortem_currency', 'USD' ); ?>
											<div id="vortem-currency-wrapper">
												<div class="vortem-currency-row">
													<div class="vortem-custom-currency-select" id="vortem-custom-currency-select">
														<div class="vortem-currency-select-display" id="vortem-currency-select-display">
															<span class="vortem-currency-flag-placeholder"></span>
															<span class="vortem-currency-text"><?php esc_html_e( 'Loading currencies...', 'vortem-ai' ); ?></span>
															<span class="dashicons dashicons-arrow-down-alt2"></span>
														</div>
														<div class="vortem-currency-select-dropdown" id="vortem-currency-select-dropdown" style="display: none;">
															<div class="vortem-currency-loading"><?php esc_html_e( 'Loading currencies...', 'vortem-ai' ); ?></div>
														</div>
													</div>
													<input type="hidden" name="vortem_currency" id="vortem_currency" value="<?php echo esc_attr( $current_currency ); ?>" />
													<input type="button" id="vortem-update-currency" class="button" value="<?php esc_attr_e( 'Update', 'vortem-ai' ); ?>" disabled />
												</div>
											</div>
										</div>
									</div>

								</div>
							</section>

							<!-- Card: Media -->
							<section class="vortem-settings-card" data-section="media">
								<header class="vortem-settings-card-head">
									<span class="vortem-settings-card-icon" aria-hidden="true">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<rect x="2" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
											<path d="M17 9.5l5-2.5v10l-5-2.5v-5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
										</svg>
									</span>
									<div class="vortem-settings-card-titles">
										<h2 class="vortem-settings-card-title"><?php esc_html_e( 'Media', 'vortem-ai' ); ?></h2>
										<p class="vortem-settings-card-sub"><?php esc_html_e( 'Control how product videos are imported and attached.', 'vortem-ai' ); ?></p>
									</div>
								</header>
								<div class="vortem-settings-card-body">

									<div class="vortem-settings-row">
										<div class="vortem-settings-row-meta">
											<label class="vortem-settings-row-label" for="vortem_add_video_to_description"><?php esc_html_e( 'Add video to product description', 'vortem-ai' ); ?></label>
											<p class="vortem-settings-row-help"><?php esc_html_e( 'When enabled, product videos are automatically added to the description. Disable to handle videos manually.', 'vortem-ai' ); ?></p>
										</div>
										<div class="vortem-settings-row-control vortem-settings-row-control--toggle">
											<label class="vortem-toggle-switch" for="vortem_add_video_to_description">
												<input type="checkbox" name="vortem_add_video_to_description" id="vortem_add_video_to_description" value="1" <?php checked( get_option( 'vortem_add_video_to_description', true ), true ); ?> />
												<span class="vortem-toggle-slider"></span>
											</label>
										</div>
									</div>

									<div class="vortem-settings-row">
										<div class="vortem-settings-row-meta">
											<label class="vortem-settings-row-label" for="vortem_download_video_for_excluded_themes"><?php esc_html_e( 'Download videos for Vortem & xstore themes', 'vortem-ai' ); ?></label>
											<p class="vortem-settings-row-help"><?php esc_html_e( 'When disabled, videos are not downloaded for Vortem Clothes, Vortem Cosmetic, and xstore themes — saves storage space.', 'vortem-ai' ); ?></p>
										</div>
										<div class="vortem-settings-row-control vortem-settings-row-control--toggle">
											<label class="vortem-toggle-switch" for="vortem_download_video_for_excluded_themes">
												<input type="checkbox" name="vortem_download_video_for_excluded_themes" id="vortem_download_video_for_excluded_themes" value="1" <?php checked( get_option( 'vortem_download_video_for_excluded_themes', true ), true ); ?> />
												<span class="vortem-toggle-slider"></span>
											</label>
										</div>
									</div>

								</div>
							</section>

							<!-- Card: Integrations -->
							<section class="vortem-settings-card" data-section="integrations">
								<header class="vortem-settings-card-head">
									<span class="vortem-settings-card-icon" aria-hidden="true">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M9 2v6m6-6v6M5 8h14v3a7 7 0 0 1-7 7 7 7 0 0 1-7-7V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M12 18v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
										</svg>
									</span>
									<div class="vortem-settings-card-titles">
										<h2 class="vortem-settings-card-title"><?php esc_html_e( 'Integrations', 'vortem-ai' ); ?></h2>
										<p class="vortem-settings-card-sub"><?php esc_html_e( 'Connect external accounts to expand sourcing and fulfillment.', 'vortem-ai' ); ?></p>
									</div>
								</header>
								<div class="vortem-settings-card-body">

									<div class="vortem-settings-row vortem-settings-row--integration">
										<div class="vortem-settings-row-meta">
											<div class="vortem-settings-integration-head">
												<span class="vortem-settings-integration-name"><?php esc_html_e( 'AliExpress', 'vortem-ai' ); ?></span>
												<span class="vortem-settings-status-pill is-checking" id="vortem-aliexpress-status">
													<span class="vortem-settings-status-dot" aria-hidden="true"></span>
													<span id="vortem-aliexpress-status-text"><?php esc_html_e( 'Checking…', 'vortem-ai' ); ?></span>
												</span>
												<span class="vortem-settings-status-detail" id="vortem-aliexpress-status-detail"></span>
											</div>
											<p class="vortem-settings-row-help"><?php esc_html_e( 'Connect your AliExpress account to enable order fulfillment and dropshipping features.', 'vortem-ai' ); ?></p>
										</div>
										<div class="vortem-settings-row-control vortem-settings-row-control--integration">
											<div id="vortem-aliexpress-integration">
												<div id="vortem-aliexpress-actions">
													<button type="button" id="vortem-connect-aliexpress" class="button button-primary" style="display: none;">
														<?php esc_html_e( 'Connect to AliExpress', 'vortem-ai' ); ?>
													</button>
													<button type="button" id="vortem-disconnect-aliexpress" class="button button-secondary" style="display: none;">
														<?php esc_html_e( 'Disconnect', 'vortem-ai' ); ?>
													</button>
												</div>
											</div>
										</div>
									</div>

								</div>
							</section>

						</div>

						<div class="vortem-settings-actions">
							<?php submit_button( esc_html__( 'Save Changes', 'vortem-ai' ), 'primary', 'submit', false ); ?>
						</div>
					</form>
				</div><!-- .settings-main -->
			</div><!-- .settings-workspace-wrap -->

		<!-- AliExpress Modal -->
		<div id="vortem-aliexpress-modal" class="vortem-aliexpress-modal" style="display: none;">
			<div class="vortem-aliexpress-modal-overlay"></div>
			<div class="vortem-aliexpress-modal-content">
				<div class="vortem-aliexpress-modal-header">
					<h3 id="vortem-aliexpress-modal-title"><?php esc_html_e( 'AliExpress', 'vortem-ai' ); ?></h3>
					<button type="button" class="vortem-aliexpress-modal-close" id="vortem-aliexpress-modal-close">&times;</button>
				</div>
				<div class="vortem-aliexpress-modal-body">
					<p id="vortem-aliexpress-modal-message"></p>
				</div>
				<div class="vortem-aliexpress-modal-footer">
					<button type="button" class="button" id="vortem-aliexpress-modal-cancel" style="display: none;"><?php esc_html_e( 'Cancel', 'vortem-ai' ); ?></button>
					<div style="display: flex; align-items: center; gap: 10px;">
						<span id="vortem-aliexpress-modal-countdown" style="display: none; font-size: 14px; color: #646970;"></span>
						<button type="button" class="button button-primary" id="vortem-aliexpress-modal-ok"><?php esc_html_e( 'OK', 'vortem-ai' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<?php
		/*
		 * Settings page inline JavaScript. Same rationale as the block in
		 * products_page(): we output-buffer the template, then hand the JS
		 * off to WordPress via wp_add_inline_script() on the already
		 * registered "vortem-settings-validation" handle (see
		 * enqueue_admin_scripts()). No raw <script> tags are emitted.
		 *
		 * Translations / nonces exposed through wp_localize_script() on
		 * "vortem-admin" are available here as window.vortem_admin.*.
		 */
		ob_start();
		?>
		jQuery(document).ready(function($) {
			// Translation strings
			var strings = {
				minError: <?php echo wp_json_encode( esc_html__( 'Products per page must be at least 1. Please enter a valid value.', 'vortem-ai' ) ); ?>,
				maxError: <?php echo wp_json_encode( esc_html__( 'Products per page cannot exceed 100. Please enter a value between 1 and 100.', 'vortem-ai' ) ); ?>,
				minTitle: <?php echo wp_json_encode( esc_html__( 'Products per page must be at least 1', 'vortem-ai' ) ); ?>,
				maxTitle: <?php echo wp_json_encode( esc_html__( 'Products per page cannot exceed 100', 'vortem-ai' ) ); ?>
			};
			
			// Validate products per page on form submission
			$('#vortem-settings-form').on('submit', function(e) {
				var productsPerPage = parseInt($('input[name="vortem_products_per_page"]').val());
				
				if (isNaN(productsPerPage) || productsPerPage < 1) {
					e.preventDefault();
					alert(strings.minError);
					$('input[name="vortem_products_per_page"]').focus();
					return false;
				}
				
				if (productsPerPage > 100) {
					e.preventDefault();
					alert(strings.maxError);
					$('input[name="vortem_products_per_page"]').focus();
					return false;
				}
			});
			
			// Real-time validation on input change
			$('input[name="vortem_products_per_page"]').on('input', function() {
				var productsPerPage = parseInt($(this).val());
				var $this = $(this);
				
				// Remove existing validation classes
				$this.removeClass('error valid');
				
				if (isNaN(productsPerPage) || productsPerPage < 1) {
					$this.addClass('error');
					$this.attr('title', strings.minTitle);
				} else if (productsPerPage > 100) {
					$this.addClass('error');
					$this.attr('title', strings.maxTitle);
				} else {
					$this.addClass('valid');
					$this.removeAttr('title');
				}
			});

			// Tab switching functionality (consistent with analytics pattern)
			$('.tab').on('click', function() {
				const tab = $(this).data('tab');
				$('.tab').removeClass('active');
				$(this).addClass('active');
				$('.panel').removeClass('active');
				$('#panel-' + tab).addClass('active');
				
				// Update URL to use separate page parameter (like analytics)
				const url = new URL(window.location);
				const page = tab === 'orders' ? 'vortem-orders' : 'vortem-products';
				url.searchParams.set('page', page);
				url.searchParams.delete('tab'); // Remove tab parameter if it exists
				window.history.pushState({}, '', url);
			});

			// Track the original currency to detect changes
			var originalCurrency = null;
			var $updateButton = $('#vortem-update-currency');
			var $updateButtonWrapper = $('.vortem-currency-button-wrapper');
			var $currencySelect = $('#vortem_currency');

			// Load currencies from API
			function loadCurrenciesFromAPI() {
				var currentCurrency = null;

				// Check if vortem_admin is available
				if (typeof vortem_admin === 'undefined') {
					VortemLogger.error('vortem_admin is not defined');
					return;
				}

				// First, fetch the current currency from API
				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_get_current_currency',
						nonce: vortem_admin.nonce
					},
					success: function(response) {
						if (response.success && response.data && response.data.customer_currency) {
							currentCurrency = response.data.customer_currency;
						}
						
						// Now fetch the currency list
						fetchCurrencyList(currentCurrency);
					},
					error: function(xhr, status, error) {
						VortemLogger.warn('Failed to fetch current currency, using default');
						// Continue to fetch currency list even if current currency fetch fails
						fetchCurrencyList(null);
					}
				});
			}

			// Fetch currency list and populate custom dropdown
			function fetchCurrencyList(currentCurrency) {
				var $dropdown = $('#vortem-currency-select-dropdown');
				var $display = $('#vortem-currency-select-display');

				// If no current currency from API, try to use the saved one
				if (!currentCurrency) {
					currentCurrency = '<?php echo esc_js( get_option( 'vortem_currency', 'USD' ) ); ?>';
				}

				// Store original currency for change detection
				originalCurrency = currentCurrency;

				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_get_currency_codes',
						nonce: vortem_admin.nonce
					},
					success: function(response) {
						if (response.success && response.data) {
							var currencies = response.data;
							var $dropdownList = $dropdown.find('.vortem-currency-dropdown-list');

							// Clear existing content except loading
							$dropdown.find('.vortem-currency-loading').hide();
							if (!$dropdownList.length) {
								$dropdown.append('<div class="vortem-currency-dropdown-list"></div>');
								$dropdownList = $dropdown.find('.vortem-currency-dropdown-list');
							}
							$dropdownList.empty();

							// Add search box
							var $searchBox = $('<div class="vortem-currency-search-wrapper">' +
								'<input type="text" class="vortem-currency-search-input" placeholder="' + 
								'<?php echo esc_js( __( 'Search by country or currency code', 'vortem-ai' ) ); ?>' + 
								'">' +
								'<span class="dashicons dashicons-search"></span>' +
								'</div>');
							$dropdownList.append($searchBox);

							// Function to convert Unicode flag format to emoji
							function unicodeFlagToEmoji(unicodeFlag) {
								if (!unicodeFlag || typeof unicodeFlag !== 'string') {
									return '';
								}
								// Parse format like "U+1F1E7 U+1F1F2" to emoji
								var codePoints = unicodeFlag.match(/U\+([0-9A-Fa-f]+)/g);
								if (!codePoints || codePoints.length < 2) {
									return '';
								}
								try {
									var firstCode = parseInt(codePoints[0].substring(2), 16);
									var secondCode = parseInt(codePoints[1].substring(2), 16);
									return String.fromCodePoint(firstCode, secondCode);
								} catch (e) {
									return '';
								}
							}

							// Function to normalize SVG content - ensure it has proper dimensions
							function normalizeSvg(svgContent) {
								if (!svgContent || typeof svgContent !== 'string') {
									return svgContent;
								}
								
								// Decode HTML entities if needed
								var decoded = svgContent;
								if (svgContent.indexOf('&lt;') !== -1 || svgContent.indexOf('&gt;') !== -1) {
									var textarea = document.createElement('textarea');
									textarea.innerHTML = svgContent;
									decoded = textarea.value;
								}
								
								// Check if it's a valid SVG
								if (!decoded.trim().match(/<svg[\s>]/i)) {
									return svgContent; // Return original if not valid SVG
								}
								
								// Parse SVG to ensure it has proper attributes
								try {
									var $temp = $('<div>').html(decoded);
									var $svg = $temp.find('svg').first();
									
									if ($svg.length === 0) {
										// Try to find SVG as root element
										$temp = $('<div>').html(decoded);
										$svg = $temp.children('svg').first();
									}
									
									if ($svg.length > 0) {
										// Ensure SVG has width and height
										if (!$svg.attr('width')) {
											$svg.attr('width', '20');
										}
										if (!$svg.attr('height')) {
											$svg.attr('height', '15');
										}
										
										// Ensure SVG has viewBox (important for proper scaling)
										if (!$svg.attr('viewBox')) {
											var width = $svg.attr('width') || '20';
											var height = $svg.attr('height') || '15';
											// Remove 'px' if present
											width = width.replace('px', '').trim();
											height = height.replace('px', '').trim();
											$svg.attr('viewBox', '0 0 ' + width + ' ' + height);
										}
										
										// Ensure preserveAspectRatio for consistent display
										if (!$svg.attr('preserveAspectRatio')) {
											$svg.attr('preserveAspectRatio', 'xMidYMid meet');
										}
										
										return $svg[0].outerHTML;
									}
								} catch (e) {
									console.warn('Failed to normalize SVG:', e);
								}
								
								return svgContent; // Return original if processing fails
							}

							// Normalize currencies to array format
							var currencyArray = [];

							if (Array.isArray(currencies)) {
								currencies.forEach(function(currency) {
									var code = currency.currency_code || currency.code || currency.id;
									var countryCurrency = currency.country_currency || currency.name || currency.currency_name || '';
									var svgContent = currency.svg_content || '';
									var countryFlag = currency.country_flag || '';

									// Normalize SVG content and use it if available, otherwise use emoji
									var flag = '';
									var flagType = 'emoji';
									var normalizedSvg = null;
									
									if (svgContent && typeof svgContent === 'string') {
										// Normalize SVG to ensure proper dimensions
										normalizedSvg = normalizeSvg(svgContent);
										if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
											// Use SVG for display
											flag = normalizedSvg;
											flagType = 'svg';
										} else if (countryFlag) {
											// Fallback to emoji if SVG is invalid
											flag = unicodeFlagToEmoji(countryFlag);
											flagType = 'emoji';
										}
									} else if (countryFlag) {
										flag = unicodeFlagToEmoji(countryFlag);
										flagType = 'emoji';
									}

									if (code) {
										currencyArray.push({
											code: code,
											name: countryCurrency || code,
											flag: flag,
											flagType: flagType,
											svgContent: normalizedSvg || svgContent
										});
									}
								});
							} else if (typeof currencies === 'object') {
								$.each(currencies, function(key, data) {
									var code = data.currency_code || key;
									var countryCurrency = data.country_currency || data.name || data.currency_name || '';
									var svgContent = data.svg_content || '';
									var countryFlag = data.country_flag || '';

									// Normalize SVG content and use it if available, otherwise use emoji
									var flag = '';
									var flagType = 'emoji';
									var normalizedSvg = null;
									
									if (svgContent && typeof svgContent === 'string') {
										// Normalize SVG to ensure proper dimensions
										normalizedSvg = normalizeSvg(svgContent);
										if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
											// Use SVG for display
											flag = normalizedSvg;
											flagType = 'svg';
										} else if (countryFlag) {
											// Fallback to emoji if SVG is invalid
											flag = unicodeFlagToEmoji(countryFlag);
											flagType = 'emoji';
										}
									} else if (countryFlag) {
										flag = unicodeFlagToEmoji(countryFlag);
										flagType = 'emoji';
									}

									if (code) {
										currencyArray.push({
											code: code,
											name: countryCurrency || code,
											flag: flag,
											flagType: flagType,
											svgContent: normalizedSvg || svgContent
										});
									}
								});
							}

							// Filter currencies to only include those starting with English letters (A-Z)
							var filteredCurrencies = currencyArray.filter(function(currency) {
								var firstChar = currency.name.charAt(0).toUpperCase();
								return firstChar >= 'A' && firstChar <= 'Z';
							});

							// Sort currencies alphabetically by name
							filteredCurrencies.sort(function(a, b) {
								return a.name.localeCompare(b.name);
							});

							// Group currencies by first letter
							var groupedCurrencies = {};
							filteredCurrencies.forEach(function(currency) {
								var firstLetter = currency.name.charAt(0).toUpperCase();
								if (!groupedCurrencies[firstLetter]) {
									groupedCurrencies[firstLetter] = [];
								}
								groupedCurrencies[firstLetter].push(currency);
							});

							// Create groups for each letter A-Z
							for (var letter = 'A'; letter <= 'Z'; letter = String.fromCharCode(letter.charCodeAt(0) + 1)) {
								if (groupedCurrencies[letter] && groupedCurrencies[letter].length > 0) {
									var $group = $('<div></div>').addClass('vortem-currency-group').attr('data-letter', letter);
									var $groupHeader = $('<div></div>').addClass('vortem-currency-group-header').text(letter);
									$group.append($groupHeader);

									groupedCurrencies[letter].forEach(function(currency) {
										var $option = $('<div></div>')
											.addClass('vortem-currency-option')
											.attr('data-currency', currency.code)
											.attr('data-name', currency.name);

										// Handle flag display
										var flagHtml = '';
										if (currency.flagType === 'svg' && currency.flag) {
											// Use normalized SVG from flag (already normalized)
											flagHtml = '<div class="vortem-currency-flag-container">' +
														'<div class="vortem-currency-flag vortem-currency-flag-svg">' + currency.flag + '</div>' +
														'</div>';
										} else if (currency.flag) {
											flagHtml = '<span class="vortem-currency-flag">' + currency.flag + '</span>';
										}

										var currencyName = currency.name ? currency.name + ' (' + currency.code + ')' : currency.code;

										$option.html(
											flagHtml +
											'<span class="vortem-currency-name">' + currencyName + '</span>'
										);

										$option.on('click', function() {
											selectCurrency(currency.code, currency.name, currency.flag, currency.flagType, currency.svgContent);
											closeCurrencyDropdown();
										});

										$group.append($option);
									});

									$dropdownList.append($group);
								}
							}

							// Add search functionality
							var $searchInput = $dropdownList.find('.vortem-currency-search-input');
							$searchInput.on('input', function() {
								var searchTerm = $(this).val().toLowerCase().trim();
								
								if (searchTerm === '') {
									// Show all groups
									$dropdownList.find('.vortem-currency-group').show();
									$dropdownList.find('.vortem-currency-option').show();
								} else {
									// Filter currencies
									var hasVisibleItems = false;
									
									$dropdownList.find('.vortem-currency-group').each(function() {
										var $group = $(this);
										var $options = $group.find('.vortem-currency-option');
										var hasMatch = false;
										
										$options.each(function() {
											var $option = $(this);
											var currencyCode = $option.data('currency').toLowerCase();
											var currencyName = $option.data('name').toLowerCase();
											
											if (currencyName.indexOf(searchTerm) !== -1 || currencyCode.indexOf(searchTerm) !== -1) {
												$option.show();
												hasMatch = true;
												hasVisibleItems = true;
											} else {
												$option.hide();
											}
										});
										
										if (hasMatch) {
											$group.show();
										} else {
											$group.hide();
										}
									});
									
									// Show "no results" message if needed
									var $noResults = $dropdownList.find('.vortem-currency-no-results');
									if (!hasVisibleItems) {
										if ($noResults.length === 0) {
											$dropdownList.append('<div class="vortem-currency-no-results">' + 
												'<?php echo esc_js( __( 'No currencies found', 'vortem-ai' ) ); ?>' + 
												'</div>');
										} else {
											$noResults.show();
										}
									} else {
										$noResults.hide();
									}
								}
							});
							
							// Prevent search input from closing dropdown
							$searchInput.on('click', function(e) {
								e.stopPropagation();
							});

							// Set initial display
							if (currentCurrency) {
								// Find the current currency and update display
								var currentCurrencyData = filteredCurrencies.find(function(currency) {
									return currency.code === currentCurrency;
								});
								if (currentCurrencyData) {
									updateCurrencyDisplay(currentCurrencyData.code, currentCurrencyData.name, currentCurrencyData.flag, currentCurrencyData.flagType, currentCurrencyData.svgContent);
								}
							}

							// Ensure dropdown is hidden after loading (should only open on user click)
							$dropdown.hide();
							$display.removeClass('active');

							// Update button state
							toggleUpdateButton();
						} else {
							$dropdown.find('.vortem-currency-loading').text('<?php esc_html_e( 'Failed to load currencies', 'vortem-ai' ); ?>');
							VortemLogger.warn('Failed to load currencies from API');
						}
					},
					error: function(xhr, status, error) {
						$dropdown.find('.vortem-currency-loading').text('<?php esc_html_e( 'Error loading currencies', 'vortem-ai' ); ?>');
						VortemLogger.error('Error loading currencies from API:', error);
					}
				});
			}

			// Function to select a currency
			function selectCurrency(code, name, flag, flagType, svgContent) {
				// Update hidden input
				$('#vortem_currency').val(code);

				// Update display
				updateCurrencyDisplay(code, name, flag, flagType, svgContent);

				// Enable update button if currency changed
				toggleUpdateButton();
			}

			// Function to update currency display
			function updateCurrencyDisplay(code, name, flag, flagType, svgContent) {
				var $display = $('#vortem-currency-select-display');
				var $flagPlaceholder = $display.find('.vortem-currency-flag-placeholder');
				var $text = $display.find('.vortem-currency-text');

				// Update flag
				$flagPlaceholder.empty();
				if (flagType === 'svg' && svgContent) {
					$flagPlaceholder.html('<div class="vortem-currency-flag-container">' +
										'<div class="vortem-currency-flag vortem-currency-flag-svg">' + svgContent + '</div>' +
										'</div>');
				} else if (flag) {
					$flagPlaceholder.html('<span class="vortem-currency-flag">' + flag + '</span>');
				}

				// Update text
				$text.text(name ? name + ' (' + code + ')' : code);
			}

			// Function to close currency dropdown
			function closeCurrencyDropdown() {
				var $dropdown = $('#vortem-currency-select-dropdown');
				var $display = $('#vortem-currency-select-display');
				$dropdown.hide();
				$display.removeClass('active');
			}

			// Handle custom dropdown toggle
			$('#vortem-currency-select-display').on('click', function() {
				var $dropdown = $('#vortem-currency-select-dropdown');
				var $display = $(this);

				if ($dropdown.is(':visible')) {
					closeCurrencyDropdown();
				} else {
					$dropdown.show();
					$display.addClass('active');
				}
			});

			// Close dropdown when clicking outside
			$(document).on('click', function(e) {
				var $customSelect = $('#vortem-custom-currency-select');
				if (!$customSelect.is(e.target) && $customSelect.has(e.target).length === 0) {
					closeCurrencyDropdown();
				}
			});

			// Enable/disable update button based on selection
			function toggleUpdateButton() {
				var selectedCurrency = $('#vortem_currency').val();
				if (selectedCurrency && selectedCurrency !== originalCurrency) {
					// Enable button when currency is different
					$updateButton.prop('disabled', false);
					$updateButtonWrapper.show();
				} else {
					// Disable button when currency matches or no selection
					$updateButton.prop('disabled', true);
					$updateButtonWrapper.show();
				}
			}

			// Load currencies on page load
			loadCurrenciesFromAPI();

			// Handle currency selection change
			$currencySelect.on('change', function() {
				toggleUpdateButton();
			});

			// Handle update button click
			$updateButton.on('click', function() {
				var selectedCurrency = $currencySelect.val();
				
				if (!selectedCurrency) {
					if (typeof showCustomNotice !== 'undefined') {
						showCustomNotice('<?php echo esc_js( __( 'Please select a currency first', 'vortem-ai' ) ); ?>', 'warning');
					} else {
						alert('<?php echo esc_js( __( 'Please select a currency first', 'vortem-ai' ) ); ?>');
					}
					return;
				}

				// Disable button and show loading state
				var $btn = $(this);
				var originalText = $btn.text();
				var updateSucceeded = false;
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Updating...', 'vortem-ai' ) ); ?>');

				$.ajax({
					url: vortem_admin.ajax_url,
					type: 'POST',
					data: {
						action: 'vortem_update_currency',
						nonce: vortem_admin.nonce,
						currency_code: selectedCurrency
					},
					success: function(response) {
						if (response.success) {
							updateSucceeded = true;
							// Update original currency to the new one
							originalCurrency = selectedCurrency;
							// Disable button until user changes currency again
							$updateButton.prop('disabled', true).text(originalText);
							
							// Show success notification (appears after "Configure your Vortem.ai settings below" notice)
							if (typeof showCustomNotice !== 'undefined') {
								showCustomNotice('<?php echo esc_js( __( 'Currency updated successfully!', 'vortem-ai' ) ); ?>', 'success');
							} else if (typeof window.showCustomNotice !== 'undefined') {
								window.showCustomNotice('<?php echo esc_js( __( 'Currency updated successfully!', 'vortem-ai' ) ); ?>', 'success');
							} else {
								// Fallback: create notice manually in the same location
								var noticeHtml = '<div class="vortem-custom-notice vortem-plugin-notice vortem-notice-success"><p><?php echo esc_js( __( 'Currency updated successfully!', 'vortem-ai' ) ); ?></p></div>';
								var $configureNotice = $('.vortem-custom-notice').filter(function() {
									return $(this).text().indexOf('Configure your Vortem.ai settings below') !== -1;
								});
								if ($configureNotice.length > 0) {
									$configureNotice.after(noticeHtml);
								} else {
									$('#vortem-settings-form').before(noticeHtml);
								}
							}
							
							// Submit the settings form so "Save Changes" runs and all settings are persisted
							$('#vortem-settings-form').submit();
						} else {
							var errorMessage = response.data?.message || '<?php echo esc_js( __( 'Failed to update currency', 'vortem-ai' ) ); ?>';
							if (typeof showCustomNotice !== 'undefined') {
								showCustomNotice('<?php echo esc_js( __( 'Error: ', 'vortem-ai' ) ); ?>' + errorMessage, 'error');
							} else if (typeof window.showCustomNotice !== 'undefined') {
								window.showCustomNotice('<?php echo esc_js( __( 'Error: ', 'vortem-ai' ) ); ?>' + errorMessage, 'error');
							} else {
								// Fallback: create notice manually in the same location
								var noticeHtml = '<div class="vortem-custom-notice vortem-plugin-notice vortem-notice-error"><p><?php echo esc_js( __( 'Error: ', 'vortem-ai' ) ); ?>' + errorMessage + '</p></div>';
								var $configureNotice = $('.vortem-custom-notice').filter(function() {
									return $(this).text().indexOf('Configure your Vortem.ai settings below') !== -1;
								});
								if ($configureNotice.length > 0) {
									$configureNotice.after(noticeHtml);
								} else {
									$('#vortem-settings-form').before(noticeHtml);
								}
							}
						}
					},
					error: function(xhr, status, error) {
						var errorMessage = '<?php echo esc_js( __( 'Error updating currency: ', 'vortem-ai' ) ); ?>' + error;
						if (typeof showCustomNotice !== 'undefined') {
							showCustomNotice(errorMessage, 'error');
						} else if (typeof window.showCustomNotice !== 'undefined') {
							window.showCustomNotice(errorMessage, 'error');
						} else {
							// Fallback: create notice manually in the same location
							var noticeHtml = '<div class="vortem-custom-notice vortem-plugin-notice vortem-notice-error"><p>' + errorMessage + '</p></div>';
							var $configureNotice = $('.vortem-custom-notice').filter(function() {
								return $(this).text().indexOf('Configure your Vortem.ai settings below') !== -1;
							});
							if ($configureNotice.length > 0) {
								$configureNotice.after(noticeHtml);
							} else {
								$('#vortem-settings-form').before(noticeHtml);
							}
						}
					},
					complete: function() {
						// Restore button text
						$btn.text(originalText);
						// Re-enable only if update failed (so user can try again); keep disabled after success until they change currency
						if (!updateSucceeded) {
							$btn.prop('disabled', false);
						}
					}
				});
			});

			// AliExpress Modal Functions
			var vortemAliExpressModal = {
				modal: jQuery('#vortem-aliexpress-modal'),
				title: jQuery('#vortem-aliexpress-modal-title'),
				message: jQuery('#vortem-aliexpress-modal-message'),
				okBtn: jQuery('#vortem-aliexpress-modal-ok'),
				cancelBtn: jQuery('#vortem-aliexpress-modal-cancel'),
				closeBtn: jQuery('#vortem-aliexpress-modal-close'),
				overlay: jQuery('.vortem-aliexpress-modal-overlay'),
				countdownEl: jQuery('#vortem-aliexpress-modal-countdown'),
				autoCloseTimer: null,
				countdownInterval: null,
				countdownValue: 0,

				show: function(options) {
					var self = this;
					var title = options.title || '<?php echo esc_js( __( 'AliExpress', 'vortem-ai' ) ); ?>';
					var message = options.message || '';
					var showCancel = options.showCancel || false;
					var onOk = options.onOk || function() {};
					var onCancel = options.onCancel || function() {};
					var autoClose = options.autoClose || false;
					var autoCloseDelay = options.autoCloseDelay || 3000;
					var showCountdown = options.showCountdown || false;
					var countdownStart = options.countdownStart || 3;

					// Clear any existing timer and interval
					if (self.autoCloseTimer) {
						clearTimeout(self.autoCloseTimer);
						self.autoCloseTimer = null;
					}
					if (self.countdownInterval) {
						clearInterval(self.countdownInterval);
						self.countdownInterval = null;
					}

					// Set content
					self.title.text(title);
					self.message.text(message);
					self.cancelBtn.toggle(showCancel);
					self.countdownEl.hide().text('');

					// Remove previous event handlers
					self.okBtn.off('click');
					self.cancelBtn.off('click');
					self.closeBtn.off('click');
					self.overlay.off('click');

					// Start countdown if enabled
					if (showCountdown && autoClose) {
						self.countdownValue = countdownStart;
						self.countdownEl.text('(' + self.countdownValue + ')').show();
						
						self.countdownInterval = setInterval(function() {
							self.countdownValue--;
							if (self.countdownValue > 1) {
								self.countdownEl.text('(' + self.countdownValue + ')');
							} else if (self.countdownValue === 1) {
								self.countdownEl.text('(1)');
							} else {
								// Countdown reached 0, close modal automatically
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
								self.hide();
								onOk();
							}
						}, 1000);
					}

					// OK button handler
					self.okBtn.on('click', function() {
						self.hide();
						if (autoClose && self.autoCloseTimer) {
							clearTimeout(self.autoCloseTimer);
							self.autoCloseTimer = null;
						}
						if (self.countdownInterval) {
							clearInterval(self.countdownInterval);
							self.countdownInterval = null;
						}
						onOk();
					});

					// Cancel button handler
					if (showCancel) {
						self.cancelBtn.on('click', function() {
							self.hide();
							if (autoClose && self.autoCloseTimer) {
								clearTimeout(self.autoCloseTimer);
								self.autoCloseTimer = null;
							}
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
							onCancel();
						});
					}

					// Close button and overlay handler (only if not confirmation)
					if (!showCancel) {
						self.closeBtn.on('click', function() {
							self.hide();
							if (autoClose && self.autoCloseTimer) {
								clearTimeout(self.autoCloseTimer);
								self.autoCloseTimer = null;
							}
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
						});
						self.overlay.on('click', function() {
							self.hide();
							if (autoClose && self.autoCloseTimer) {
								clearTimeout(self.autoCloseTimer);
								self.autoCloseTimer = null;
							}
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
						});
					} else {
						// For confirmation, close button should cancel
						self.closeBtn.on('click', function() {
							self.hide();
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
							onCancel();
						});
						self.overlay.on('click', function() {
							self.hide();
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
							onCancel();
						});
					}

					// Show modal
					self.modal.show();

					// Auto close if enabled
					if (autoClose) {
						self.autoCloseTimer = setTimeout(function() {
							if (self.countdownInterval) {
								clearInterval(self.countdownInterval);
								self.countdownInterval = null;
							}
							self.hide();
							onOk();
						}, autoCloseDelay);
					}
				},

				hide: function() {
					this.modal.hide();
					if (this.autoCloseTimer) {
						clearTimeout(this.autoCloseTimer);
						this.autoCloseTimer = null;
					}
					if (this.countdownInterval) {
						clearInterval(this.countdownInterval);
						this.countdownInterval = null;
					}
					this.countdownEl.hide().text('');
				}
			};

			// AliExpress Integration
			function setAliExpressPillState(state) {
				jQuery('#vortem-aliexpress-status')
					.removeClass('is-checking is-connected is-disconnected is-error')
					.addClass('is-' + state);
			}
			function checkAliExpressStatus() {
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'vortem_get_aliexpress_auth_status',
						nonce: '<?php echo esc_js( wp_create_nonce( 'vortem_nonce' ) ); ?>'
					},
					success: function(response) {
						var $detail = jQuery('#vortem-aliexpress-status-detail').empty();

						if (response.success && response.data) {
							if (response.data.connected) {
								var account = response.data.account || response.data.user_id || '';
								var tokenStatus = response.data.token_status || '';
								var expiresAt = response.data.expires_at_formatted || '';

								if (tokenStatus === 'expired') {
									jQuery('#vortem-aliexpress-status-text').text('<?php echo esc_js( __( 'Token expired', 'vortem-ai' ) ); ?>');
									setAliExpressPillState('error');
									if (account) { $detail.append(jQuery('<span/>').text(account)); }
									if (expiresAt) {
										if (account) { $detail.append(document.createTextNode(' · ')); }
										$detail.append(jQuery('<span/>').text('<?php echo esc_js( __( 'Expired:', 'vortem-ai' ) ); ?> ' + expiresAt));
									}
								} else {
									jQuery('#vortem-aliexpress-status-text').text('<?php echo esc_js( __( 'Connected', 'vortem-ai' ) ); ?>');
									setAliExpressPillState('connected');
									if (account) { $detail.append(jQuery('<span/>').text(account)); }
									if (tokenStatus === 'valid' && expiresAt) {
										if (account) { $detail.append(document.createTextNode(' · ')); }
										$detail.append(jQuery('<span/>').text('<?php echo esc_js( __( 'Expires:', 'vortem-ai' ) ); ?> ' + expiresAt));
									}
								}

								jQuery('#vortem-connect-aliexpress').hide();
								jQuery('#vortem-disconnect-aliexpress').show();
							} else {
								jQuery('#vortem-aliexpress-status-text').text('<?php echo esc_js( __( 'Not connected', 'vortem-ai' ) ); ?>');
								setAliExpressPillState('disconnected');
								jQuery('#vortem-connect-aliexpress').show();
								jQuery('#vortem-disconnect-aliexpress').hide();
							}
						} else {
							jQuery('#vortem-aliexpress-status-text').text('<?php echo esc_js( __( 'Not connected', 'vortem-ai' ) ); ?>');
							setAliExpressPillState('disconnected');
							jQuery('#vortem-connect-aliexpress').show();
							jQuery('#vortem-disconnect-aliexpress').hide();
						}
					},
					error: function() {
						jQuery('#vortem-aliexpress-status-detail').empty();
						jQuery('#vortem-aliexpress-status-text').text('<?php echo esc_js( __( 'Error checking status', 'vortem-ai' ) ); ?>');
						setAliExpressPillState('error');
						jQuery('#vortem-connect-aliexpress').show();
						jQuery('#vortem-disconnect-aliexpress').hide();
					}
				});
			}

			// Check status on page load
			checkAliExpressStatus();

			// Connect to AliExpress button
			jQuery('#vortem-connect-aliexpress').on('click', function() {
				var $button = jQuery(this);
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Connecting...', 'vortem-ai' ) ); ?>');
				
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'vortem_get_aliexpress_auth_url',
						nonce: '<?php echo esc_js( wp_create_nonce( 'vortem_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data && response.data.auth_url) {
							// Redirect to AliExpress OAuth page
							window.location.href = response.data.auth_url;
						} else {
							var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to get authorization URL', 'vortem-ai' ) ); ?>';
							vortemAliExpressModal.show({
								title: '<?php echo esc_js( __( 'Error', 'vortem-ai' ) ); ?>',
								message: errorMsg,
								showCancel: false
							});
							$button.prop('disabled', false).text('<?php echo esc_js( __( 'Connect to AliExpress', 'vortem-ai' ) ); ?>');
						}
					},
					error: function() {
						vortemAliExpressModal.show({
							title: '<?php echo esc_js( __( 'Error', 'vortem-ai' ) ); ?>',
							message: '<?php echo esc_js( __( 'Error connecting to AliExpress', 'vortem-ai' ) ); ?>',
							showCancel: false
						});
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Connect to AliExpress', 'vortem-ai' ) ); ?>');
					}
				});
			});

			// Disconnect AliExpress button
			jQuery('#vortem-disconnect-aliexpress').on('click', function() {
				var $button = jQuery(this);
				
				vortemAliExpressModal.show({
					title: '<?php echo esc_js( __( 'Disconnect AliExpress', 'vortem-ai' ) ); ?>',
					message: '<?php echo esc_js( __( 'Are you sure you want to disconnect your AliExpress account?', 'vortem-ai' ) ); ?>',
					showCancel: true,
					onOk: function() {
						$button.prop('disabled', true).text('<?php echo esc_js( __( 'Disconnecting...', 'vortem-ai' ) ); ?>');
						
						jQuery.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'vortem_disconnect_aliexpress',
								nonce: '<?php echo esc_js( wp_create_nonce( 'vortem_nonce' ) ); ?>'
							},
							success: function(response) {
								if (response.success) {
									checkAliExpressStatus();
								} else {
									var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to disconnect', 'vortem-ai' ) ); ?>';
									vortemAliExpressModal.show({
										title: '<?php echo esc_js( __( 'Error', 'vortem-ai' ) ); ?>',
										message: errorMsg,
										showCancel: false
									});
									$button.prop('disabled', false).text('<?php echo esc_js( __( 'Disconnect', 'vortem-ai' ) ); ?>');
								}
							},
							error: function() {
								vortemAliExpressModal.show({
									title: '<?php echo esc_js( __( 'Error', 'vortem-ai' ) ); ?>',
									message: '<?php echo esc_js( __( 'Error disconnecting AliExpress account', 'vortem-ai' ) ); ?>',
									showCancel: false
								});
								$button.prop('disabled', false).text('<?php echo esc_js( __( 'Disconnect', 'vortem-ai' ) ); ?>');
							}
						});
					},
					onCancel: function() {
						// Do nothing, just close modal
					}
				});
			});

			// Check for OAuth callback in URL parameters
			var urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('aliexpress') === 'connected' && urlParams.get('success') === 'true') {
				// Show success message modal with countdown and auto-close after 3 seconds
				vortemAliExpressModal.show({
					title: '<?php echo esc_js( __( 'Success', 'vortem-ai' ) ); ?>',
					message: '<?php echo esc_js( __( 'Successfully connected to AliExpress!', 'vortem-ai' ) ); ?>',
					showCancel: false,
					autoClose: true,
					autoCloseDelay: 3000,
					showCountdown: true,
					countdownStart: 3,
					onOk: function() {
						checkAliExpressStatus();
						// Clean up URL
						var cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
						window.history.replaceState({}, document.title, cleanUrl);
					}
				});
			} else if (urlParams.get('aliexpress') === 'error') {
				var errorMsg = urlParams.get('error') || '<?php echo esc_js( __( 'Failed to connect to AliExpress', 'vortem-ai' ) ); ?>';
				vortemAliExpressModal.show({
					title: '<?php echo esc_js( __( 'Error', 'vortem-ai' ) ); ?>',
					message: decodeURIComponent(errorMsg),
					showCancel: false,
					onOk: function() {
						checkAliExpressStatus();
						// Clean up URL
						var cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
						window.history.replaceState({}, document.title, cleanUrl);
					}
				});
			}
		});
		<?php
		$vortem_settings_inline_js = ob_get_clean();
		if ( ! empty( $vortem_settings_inline_js ) ) {
			wp_add_inline_script( 'vortem-settings-validation', $vortem_settings_inline_js );
		}
	}

	/**
	 * Security page (Plugin Inspector)
	 */
	public function security_page() {
		// Check if setup is completed
		$this->check_setup_completion();

		// Get plugin inspector instance
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();

		// Get plugin data
		$plugins = $plugin_inspector->get_plugin_data();

		// Get theme data
		$themes = $plugin_inspector->get_theme_data();

		// Include the security page template
		include VORTEM_PLUGIN_DIR . 'admin/partials/security-page.php';
	}


	/**
	 * AJAX handler to send security data (plugin information) to external API
	 */
	public function ajax_send_security_data() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			wp_send_json_error( array( 'message' => 'Data processing consent required.' ), 451 );
			return;
		}

		// Get plugin data
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();
		$plugins          = $plugin_inspector->get_plugin_data();

		// Format plugins data according to API requirements
		$formatted_plugins = array();
		foreach ( $plugins as $plugin ) {
			$formatted_plugins[] = array(
				'file'                => isset( $plugin['file'] ) ? $plugin['file'] : '',
				'name'                => isset( $plugin['name'] ) ? $plugin['name'] : '',
				'version'             => isset( $plugin['version'] ) ? $plugin['version'] : '',
				'description'         => isset( $plugin['description'] ) ? $plugin['description'] : '',
				'author'              => isset( $plugin['author'] ) ? $plugin['author'] : '',
				'plugin_uri'          => isset( $plugin['plugin_uri'] ) ? $plugin['plugin_uri'] : '',
				'status'              => isset( $plugin['status'] ) ? $plugin['status'] : 'inactive',
				'last_modified'       => isset( $plugin['last_modified'] ) ? $plugin['last_modified'] : '',
				'requires_wp_version' => isset( $plugin['requires_wp_version'] ) && ! empty( $plugin['requires_wp_version'] ) ? $plugin['requires_wp_version'] : '',
				'requires_php'        => isset( $plugin['requires_php'] ) && ! empty( $plugin['requires_php'] ) ? $plugin['requires_php'] : '',
			);
		}

		// Prepare request data for plugins
		$request_data = array(
			'plugins' => $formatted_plugins,
		);

		// Get API endpoint for plugins
		$endpoint   = Vortem_Config::get_api_endpoint( 'security_wordpress' );
		$api_server = Vortem_Config::get_primary_api_server();
		$url        = rtrim( $api_server, '/' ) . $endpoint;

		// Prepare headers
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		// Make POST request for plugins
		$args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $request_data ),
			'timeout'   => 30,
			'sslverify' => true,
		);

		$response = wp_remote_request( $url, $args );

		$plugin_success       = false;
		$plugin_error_message = '';
		$plugin_response_body = '';

		if ( is_wp_error( $response ) ) {
			$plugin_error_message = $response->get_error_message();
		} else {
			$response_code        = wp_remote_retrieve_response_code( $response );
			$plugin_response_body = wp_remote_retrieve_body( $response );
			if ( $response_code >= 200 && $response_code < 300 ) {
				$plugin_success = true;
			} else {
				$decoded_error        = json_decode( $plugin_response_body, true );
				$plugin_error_message = ( $decoded_error && isset( $decoded_error['message'] ) ) ? $decoded_error['message'] : 'API request failed with status: ' . $response_code;
			}
		}

		// Get theme data - ALL themes from WordPress
		$themes = $plugin_inspector->get_theme_data();

		// Log theme count for debugging

		// Format themes for POST /api/v1/customer/security/wordpress/theme (stylesheet, template, name, version, status only).
		$formatted_themes = array();
		foreach ( $themes as $theme ) {
			$formatted_themes[] = array(
				'stylesheet' => isset( $theme['stylesheet'] ) ? strtolower( (string) $theme['stylesheet'] ) : '',
				'template'   => isset( $theme['template'] ) ? strtolower( (string) $theme['template'] ) : '',
				'name'       => isset( $theme['name'] ) ? (string) $theme['name'] : '',
				'version'    => isset( $theme['version'] ) ? (string) $theme['version'] : '',
				'status'     => isset( $theme['status'] ) && in_array( $theme['status'], array( 'active', 'inactive' ), true ) ? $theme['status'] : 'inactive',
			);
		}

		// Log final count of themes being sent

		// Log theme names for verification (especially to check if "Bootstrap Fitness" is included)
		$theme_names = array();
		foreach ( $formatted_themes as $theme ) {
			if ( ! empty( $theme['name'] ) ) {
				$theme_names[] = $theme['name'];
			}
		}

		// Prepare request data for themes
		$theme_request_data = array(
			'themes' => $formatted_themes,
		);

		// Get API endpoint for themes
		$theme_url = Vortem_Config::build_api_url( $api_server, 'security_wordpress_theme' );

		// Make POST request for themes
		// Use JSON encoding flags to match API format exactly (no escaped slashes, unicode as-is)
		$theme_args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $theme_request_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'timeout'   => 30,
			'sslverify' => true,
		);

		$theme_response = wp_remote_request( $theme_url, $theme_args );

		$theme_success       = false;
		$theme_error_message = '';
		$theme_response_body = '';

		if ( is_wp_error( $theme_response ) ) {
			$theme_error_message = $theme_response->get_error_message();
		} else {
			$theme_response_code = wp_remote_retrieve_response_code( $theme_response );
			$theme_response_body = wp_remote_retrieve_body( $theme_response );
			if ( $theme_response_code >= 200 && $theme_response_code < 300 ) {
				$theme_success = true;
			} else {
				$decoded_error       = json_decode( $theme_response_body, true );
				$theme_error_message = ( $decoded_error && isset( $decoded_error['message'] ) ) ? $decoded_error['message'] : 'API request failed with status: ' . $theme_response_code;
			}
		}

		// Send wp-core data (WordPress version) — POST body matches API spec: only `wordpres-version`.
		$wp_version_clean     = get_bloginfo( 'version' );
		$wp_core_request_data = array(
			'wordpres-version' => $wp_version_clean,
		);

		// Get API endpoint for wp-core
		$wp_core_url = Vortem_Config::build_api_url( $api_server, 'security_wordpress_wp_core' );

		// Make POST request for wp-core
		$wp_core_args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $wp_core_request_data ),
			'timeout'   => 30,
			'sslverify' => true,
		);

		$wp_core_response = wp_remote_request( $wp_core_url, $wp_core_args );

		$wp_core_success       = false;
		$wp_core_error_message = '';
		$wp_core_response_body = '';

		if ( is_wp_error( $wp_core_response ) ) {
			$wp_core_error_message = $wp_core_response->get_error_message();
		} else {
			$wp_core_response_code = wp_remote_retrieve_response_code( $wp_core_response );
			$wp_core_response_body = wp_remote_retrieve_body( $wp_core_response );
			if ( $wp_core_response_code >= 200 && $wp_core_response_code < 300 ) {
				$wp_core_success = true;
			} else {
				$decoded_error         = json_decode( $wp_core_response_body, true );
				$wp_core_error_message = ( $decoded_error && isset( $decoded_error['message'] ) ) ? $decoded_error['message'] : 'API request failed with status: ' . $wp_core_response_code;
			}
		}

		// Return success if all requests succeeded
		if ( $plugin_success && $theme_success && $wp_core_success ) {
			$decoded_plugin_response  = json_decode( $plugin_response_body, true );
			$decoded_theme_response   = json_decode( $theme_response_body, true );
			$decoded_wp_core_response = json_decode( $wp_core_response_body, true );
			wp_send_json_success(
				array(
					'message'          => 'Plugin, theme, and WordPress core data sent successfully',
					'plugin_response'  => $decoded_plugin_response,
					'theme_response'   => $decoded_theme_response,
					'wp_core_response' => $decoded_wp_core_response,
				)
			);
		} else {
			$error_messages = array();
			if ( ! $plugin_success ) {
				$error_messages[] = 'Failed to send plugin data' . ( ! empty( $plugin_error_message ) ? ': ' . $plugin_error_message : '' );
			}
			if ( ! $theme_success ) {
				$error_messages[] = 'Failed to send theme data' . ( ! empty( $theme_error_message ) ? ': ' . $theme_error_message : '' );
			}
			if ( ! $wp_core_success ) {
				$error_messages[] = 'Failed to send WordPress core data' . ( ! empty( $wp_core_error_message ) ? ': ' . $wp_core_error_message : '' );
			}
			wp_send_json_error( array( 'message' => implode( '; ', $error_messages ) ) );
		}
	}

	/**
	 * AJAX handler to get plugin data for Security Results page
	 */
	public function ajax_get_plugin_data() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_security_results_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Get plugin data
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();
		$plugins          = $plugin_inspector->get_plugin_data();

		// Format plugins data according to API requirements
		$formatted_plugins = array();
		foreach ( $plugins as $plugin ) {
			$formatted_plugins[] = array(
				'file'                => isset( $plugin['file'] ) ? $plugin['file'] : '',
				'name'                => isset( $plugin['name'] ) ? $plugin['name'] : '',
				'version'             => isset( $plugin['version'] ) ? $plugin['version'] : '',
				'description'         => isset( $plugin['description'] ) ? $plugin['description'] : '',
				'author'              => isset( $plugin['author'] ) ? $plugin['author'] : '',
				'plugin_uri'          => isset( $plugin['plugin_uri'] ) ? $plugin['plugin_uri'] : '',
				'status'              => isset( $plugin['status'] ) ? $plugin['status'] : 'inactive',
				'last_modified'       => isset( $plugin['last_modified'] ) ? $plugin['last_modified'] : '',
				'requires_wp_version' => isset( $plugin['requires_wp_version'] ) && ! empty( $plugin['requires_wp_version'] ) ? $plugin['requires_wp_version'] : '',
				'requires_php'        => isset( $plugin['requires_php'] ) && ! empty( $plugin['requires_php'] ) ? $plugin['requires_php'] : '',
			);
		}

		wp_send_json_success( array( 'plugins' => $formatted_plugins ) );
	}

	/**
	 * AJAX handler to get theme data for Security Results page
	 */
	public function ajax_get_theme_data() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_security_results_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Get theme data
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-plugin-inspector.php';
		$plugin_inspector = Vortem_Plugin_Inspector::get_instance();
		$themes           = $plugin_inspector->get_theme_data();

		// Format themes data according to API requirements
		$formatted_themes = array();
		foreach ( $themes as $theme ) {
			$formatted_themes[] = array(
				'stylesheet'  => $theme['stylesheet'],
				'template'    => $theme['template'],
				'name'        => $theme['name'],
				'version'     => $theme['version'],
				'status'      => $theme['status'],
				'author'      => $theme['author'],
				'author_uri'  => $theme['author_uri'],
				'theme_uri'   => $theme['theme_uri'],
				'description' => $theme['description'],
				'text_domain' => $theme['text_domain'],
				'tags'        => $theme['tags'],
			);
		}

		wp_send_json_success( array( 'themes' => $formatted_themes ) );
	}

	/**
	 * AJAX handler to fetch security scan results from API
	 */
	public function ajax_get_security_results() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_security_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			wp_send_json_error( array( 'message' => 'Data processing consent required.' ), 451 );
			return;
		}

		// Load required classes
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

		$api_server          = Vortem_Config::get_primary_api_server();
		$all_vulnerabilities = array();

		// Prepare headers with simple format
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		// Send wp-core data before fetching match results (same body as POST /wp-core spec).
		$wp_version_clean     = get_bloginfo( 'version' );
		$wp_core_request_data = array(
			'wordpres-version' => $wp_version_clean,
		);

		// Get API endpoint for wp-core POST
		$wp_core_url = Vortem_Config::build_api_url( $api_server, 'security_wordpress_wp_core' );

		// Make POST request for wp-core
		$wp_core_post_args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $wp_core_request_data ),
			'timeout'   => 30,
			'sslverify' => true,
		);

		$wp_core_post_response = wp_remote_request( $wp_core_url, $wp_core_post_args );

		// Log wp-core POST result (but don't fail if it fails)
		if ( is_wp_error( $wp_core_post_response ) ) {
			vortem_log( 'Security: wp-core POST failed: ' . $wp_core_post_response->get_error_message() );
		}

		$args = array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => true,
		);

		// Fetch plugin vulnerabilities
		$plugin_api_url  = Vortem_Config::build_api_url( $api_server, 'security_wordpress_match' );
		$plugin_response = wp_remote_get( $plugin_api_url, $args );

		if ( ! is_wp_error( $plugin_response ) ) {
			$plugin_response_code = wp_remote_retrieve_response_code( $plugin_response );
			$plugin_response_body = wp_remote_retrieve_body( $plugin_response );

			if ( $plugin_response_code >= 200 && $plugin_response_code < 300 ) {
				$plugin_decoded = json_decode( $plugin_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $plugin_decoded ) ) {
					// Filter out the success message and add type indicator
					foreach ( $plugin_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( is_array( $item ) ) {
							$item['type']          = 'plugin';
							$all_vulnerabilities[] = $item;
						}
					}
				}
			}
		}

		// Fetch theme vulnerabilities
		$theme_api_url  = Vortem_Config::build_api_url( $api_server, 'security_wordpress_theme_match' );
		$theme_response = wp_remote_get( $theme_api_url, $args );

		if ( ! is_wp_error( $theme_response ) ) {
			$theme_response_code = wp_remote_retrieve_response_code( $theme_response );
			$theme_response_body = wp_remote_retrieve_body( $theme_response );

			if ( $theme_response_code >= 200 && $theme_response_code < 300 ) {
				$theme_decoded = json_decode( $theme_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $theme_decoded ) ) {
					// Filter out the success message and normalize theme data to match plugin format
					foreach ( $theme_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( is_array( $item ) ) {
							// Normalize theme vulnerability data to match plugin format
							$normalized_item       = $this->normalize_theme_vulnerability( $item );
							$all_vulnerabilities[] = $normalized_item;
						}
					}
				}
			}
		}

		// Fetch wp-core vulnerabilities
		$wp_core_api_url  = Vortem_Config::build_api_url( $api_server, 'security_wordpress_wp_core_match' );
		$wp_core_response = wp_remote_get( $wp_core_api_url, $args );

		if ( ! is_wp_error( $wp_core_response ) ) {
			$wp_core_response_code = wp_remote_retrieve_response_code( $wp_core_response );
			$wp_core_response_body = wp_remote_retrieve_body( $wp_core_response );

			if ( $wp_core_response_code >= 200 && $wp_core_response_code < 300 ) {
				$wp_core_decoded = json_decode( $wp_core_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $wp_core_decoded ) ) {
					foreach ( $wp_core_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( ! is_array( $item ) ) {
							continue;
						}
						// Flat GET .../wp-core/match (cve, customer_wordpress_version, cvss_score, type "core", etc.).
						if ( $this->is_flat_wp_core_match_item( $item ) ) {
							$all_vulnerabilities[] = $this->normalize_wp_core_vulnerability( $item );
							continue;
						}
						// Legacy nested payload (classification, affects, timeline).
						$item['type'] = 'wp-core';
						if ( isset( $item['affects'] ) && is_array( $item['affects'] ) && ! empty( $item['affects'] ) ) {
							$affect = $item['affects'][0];
							if ( isset( $affect['fixed_in'] ) ) {
								$item['fixed_in'] = $affect['fixed_in'];
							}
						}
						if ( isset( $item['classification'] ) && is_array( $item['classification'] ) ) {
							foreach ( $item['classification'] as $class ) {
								if ( isset( $class['key'] ) && $class['key'] === 'CVSS' && isset( $class['value'] ) ) {
									$item['cvss'] = $class['value'];
									if ( preg_match( '/\(([^)]+)\)/', $class['value'], $matches ) ) {
										$item['severity'] = strtoupper( $matches[1] );
									}
								}
								if ( isset( $class['key'] ) && $class['key'] === 'CWE' && isset( $class['value'] ) ) {
									$item['cwe'] = $class['value'];
								}
							}
						}
						if ( isset( $item['timeline'] ) && is_array( $item['timeline'] ) ) {
							foreach ( $item['timeline'] as $timeline ) {
								if ( isset( $timeline['key'] ) && $timeline['key'] === 'Publicly Published' && isset( $timeline['value'] ) ) {
									if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $timeline['value'], $matches ) ) {
										$item['published'] = $matches[1];
									}
								}
								if ( isset( $timeline['key'] ) && $timeline['key'] === 'Last Updated' && isset( $timeline['value'] ) ) {
									if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $timeline['value'], $matches ) ) {
										$item['lastModified'] = $matches[1];
									}
								}
							}
						}
						if ( isset( $item['source_url'] ) ) {
							$item['references'] = array( $item['source_url'] );
						}
						if ( isset( $item['miscellaneous'] ) && is_array( $item['miscellaneous'] ) ) {
							foreach ( $item['miscellaneous'] as $misc ) {
								if ( isset( $misc['key'] ) && $misc['key'] === 'WPVDB ID' && isset( $misc['value'] ) ) {
									$item['cve_id'] = 'WPVDB-' . $misc['value'];
								}
							}
						}
						$all_vulnerabilities[] = $item;
					}
				}
			}
		}

		// Cache total vulns for overview dashboard
		set_transient( 'vortem_security_total_vulns', count( $all_vulnerabilities ), 30 * MINUTE_IN_SECONDS );

		// Return combined results (always return success, even if empty array)
		wp_send_json_success( $all_vulnerabilities );
	}

	/**
	 * Whether wp-core/match row is the flat API shape (see customer security wp-core match spec).
	 *
	 * @param array $item Decoded JSON object.
	 * @return bool
	 */
	private function is_flat_wp_core_match_item( $item ) {
		if ( ! is_array( $item ) ) {
			return false;
		}
		$type = isset( $item['type'] ) ? strtolower( (string) $item['type'] ) : '';
		if ( 'core' === $type && isset( $item['cve'] ) ) {
			return true;
		}
		if ( isset( $item['cve'] ) && array_key_exists( 'customer_wordpress_version', $item ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Normalize flat wp-core/match vulnerability to the structure used by security.js / security-results.js.
	 * Preserves API field names (empty strings allowed for description, fixed_version, last_modified per backend).
	 *
	 * @param array $item Raw row from GET .../security/wordpress/wp-core/match.
	 * @return array
	 */
	private function normalize_wp_core_vulnerability( $item ) {
		$cve               = isset( $item['cve'] ) ? (string) $item['cve'] : '';
		$customer_wp       = array_key_exists( 'customer_wordpress_version', $item ) ? (string) $item['customer_wordpress_version'] : '';
		$description       = array_key_exists( 'description', $item ) ? (string) $item['description'] : '';
		$fixed_version     = array_key_exists( 'fixed_version', $item ) ? (string) $item['fixed_version'] : '';
		$severity_raw      = isset( $item['severity'] ) ? (string) $item['severity'] : 'MEDIUM';
		$severity          = strtoupper( $severity_raw );
		if ( ! in_array( $severity, array( 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW' ), true ) ) {
			$severity = 'MEDIUM';
		}
		$last_modified     = array_key_exists( 'last_modified', $item ) ? (string) $item['last_modified'] : '';
		$published_date    = array_key_exists( 'published_date', $item ) ? (string) $item['published_date'] : '';
		$cvss_score        = null;
		if ( array_key_exists( 'cvss_score', $item ) && null !== $item['cvss_score'] && '' !== $item['cvss_score'] ) {
			$cvss_score = floatval( $item['cvss_score'] );
		}
		return array(
			'type'                         => 'wp-core',
			'cve'                          => $cve,
			'cve_id'                       => $cve,
			'customer_wordpress_version'   => $customer_wp,
			'description'                  => $description,
			'fixed_version'                => $fixed_version,
			'fixed_in'                     => $fixed_version,
			'severity'                     => $severity,
			'last_modified'                => $last_modified,
			'lastModified'                 => $last_modified,
			'published_date'               => $published_date,
			'published'                    => $published_date,
			'cvss_score'                   => $cvss_score,
			'cvss'                         => $cvss_score,
			'references'                   => array(),
		);
	}

	/**
	 * Normalize theme vulnerability data to match plugin vulnerability format
	 * Theme API returns different structure than plugin API, this normalizes it
	 *
	 * @param array $item Raw theme vulnerability data from API
	 * @return array Normalized vulnerability data
	 */
	private function normalize_theme_vulnerability( $item ) {
		$normalized = array(
			'type' => 'theme',
		);

		// Flat response from GET .../security/wordpress/theme/match (customer_theme_name, cvss_score, published_date, etc.).
		if ( isset( $item['customer_theme_name'] ) || isset( $item['customer_theme_version'] ) ) {
			$cve             = isset( $item['cve'] ) ? (string) $item['cve'] : '';
			$customer_name   = isset( $item['customer_theme_name'] ) ? (string) $item['customer_theme_name'] : '';
			$customer_ver    = isset( $item['customer_theme_version'] ) ? (string) $item['customer_theme_version'] : '';
			$description     = array_key_exists( 'description', $item ) ? (string) $item['description'] : '';
			$fixed_version   = array_key_exists( 'fixed_version', $item ) ? (string) $item['fixed_version'] : '';
			$severity_raw    = isset( $item['severity'] ) ? (string) $item['severity'] : 'MEDIUM';
			$severity        = strtoupper( $severity_raw );
			if ( ! in_array( $severity, array( 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW' ), true ) ) {
				$severity = 'MEDIUM';
			}
			$published_date  = array_key_exists( 'published_date', $item ) ? (string) $item['published_date'] : '';
			$last_modified   = array_key_exists( 'last_modified', $item ) ? (string) $item['last_modified'] : '';
			$cvss_score      = null;
			if ( array_key_exists( 'cvss_score', $item ) && null !== $item['cvss_score'] && '' !== $item['cvss_score'] ) {
				$cvss_score = floatval( $item['cvss_score'] );
			}

			$normalized['cve']                    = $cve;
			$normalized['cve_id']               = $cve;
			$normalized['customer_theme_name']  = $customer_name;
			$normalized['customer_theme']        = $customer_name;
			$normalized['customer_theme_version'] = $customer_ver;
			$normalized['version']              = $customer_ver;
			$normalized['description']          = $description;
			$normalized['fixed_version']      = $fixed_version;
			$normalized['fixed_in']            = $fixed_version;
			$normalized['severity']            = $severity;
			$normalized['cvss_score']         = $cvss_score;
			$normalized['cvss']               = $cvss_score;
			$normalized['published_date']     = $published_date;
			$normalized['published']          = $published_date;
			$normalized['last_modified']      = $last_modified;
			$normalized['lastModified']       = $last_modified;
			$normalized['last_modified_date'] = $last_modified;
			$normalized['cwe']                = null;
			$normalized['references']       = array();

			return $normalized;
		}

		// Map matched_theme to customer_theme
		if ( isset( $item['matched_theme'] ) && is_array( $item['matched_theme'] ) ) {
			if ( isset( $item['matched_theme']['theme_name'] ) ) {
				$normalized['customer_theme'] = $item['matched_theme']['theme_name'];
			}
			if ( isset( $item['matched_theme']['slug'] ) ) {
				$normalized['theme_slug'] = $item['matched_theme']['slug'];
			}
			if ( isset( $item['matched_theme']['fixed_in'] ) ) {
				$normalized['fixed_in'] = $item['matched_theme']['fixed_in'];
			}
		}

		// Map title to description if no description exists
		if ( isset( $item['title'] ) ) {
			$normalized['title'] = $item['title'];
		}
		if ( isset( $item['description'] ) ) {
			$normalized['description'] = $item['description'];
		} elseif ( isset( $item['title'] ) ) {
			$normalized['description'] = $item['title'];
		}

		// Map source_url
		if ( isset( $item['source_url'] ) ) {
			$normalized['source_url'] = $item['source_url'];
		}

		// Map version
		if ( isset( $item['version'] ) ) {
			$normalized['version'] = $item['version'];
		}

		// Extract CVE ID from references
		$cve_id = null;
		if ( isset( $item['references'] ) && is_array( $item['references'] ) ) {
			foreach ( $item['references'] as $ref ) {
				if ( isset( $ref['key'] ) && $ref['key'] === 'CVE' && isset( $ref['value'] ) ) {
					$cve_id = $ref['value'];
					break;
				}
			}
		}
		$normalized['cve_id'] = $cve_id;

		// Extract CVSS score and severity from classification
		$cvss_score = null;
		$severity   = 'MEDIUM'; // Default severity
		$cwe        = null;
		$vuln_type  = null;

		if ( isset( $item['classification'] ) && is_array( $item['classification'] ) ) {
			foreach ( $item['classification'] as $class ) {
				if ( ! isset( $class['key'] ) ) {
					continue;
				}

				if ( $class['key'] === 'CVSS' && isset( $class['value'] ) ) {
					// Parse CVSS value like "6.5 (medium)" or "8.8 (high)"
					if ( preg_match( '/^([\d.]+)\s*\((\w+)\)/i', $class['value'], $matches ) ) {
						$cvss_score    = floatval( $matches[1] );
						$severity_text = strtoupper( $matches[2] );
						if ( in_array( $severity_text, array( 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW' ), true ) ) {
							$severity = $severity_text;
						}
					} else {
						// Try to extract just the number
						$cvss_score = floatval( $class['value'] );
					}
				}

				if ( $class['key'] === 'CWE' && isset( $class['value'] ) ) {
					$cwe = $class['value'];
				}

				if ( $class['key'] === 'Type' && isset( $class['value'] ) ) {
					$vuln_type = $class['value'];
				}
			}
		}

		// Determine severity from CVSS score if not already set
		if ( $cvss_score !== null && $severity === 'MEDIUM' ) {
			if ( $cvss_score >= 9.0 ) {
				$severity = 'CRITICAL';
			} elseif ( $cvss_score >= 7.0 ) {
				$severity = 'HIGH';
			} elseif ( $cvss_score >= 4.0 ) {
				$severity = 'MEDIUM';
			} else {
				$severity = 'LOW';
			}
		}

		$normalized['cvss']       = $cvss_score;
		$normalized['cvss_score'] = $cvss_score; // For compatibility with JS
		$normalized['severity']   = $severity;
		$normalized['cwe']        = $cwe;
		$normalized['vuln_type']  = $vuln_type;

		// Extract dates from timeline
		$published_date     = null;
		$last_modified_date = null;

		if ( isset( $item['timeline'] ) && is_array( $item['timeline'] ) ) {
			foreach ( $item['timeline'] as $timeline_item ) {
				if ( ! isset( $timeline_item['key'] ) || ! isset( $timeline_item['value'] ) ) {
					continue;
				}

				if ( $timeline_item['key'] === 'Publicly Published' || $timeline_item['key'] === 'Added' ) {
					// Extract date from value like "2024-12-11 (about 11 months ago)"
					if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $timeline_item['value'], $matches ) ) {
						if ( $published_date === null ) {
							$published_date = $matches[1];
						}
					}
				}

				if ( $timeline_item['key'] === 'Last Updated' ) {
					if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $timeline_item['value'], $matches ) ) {
						$last_modified_date = $matches[1];
					}
				}
			}
		}

		$normalized['published']          = $published_date;
		$normalized['published_date']     = $published_date;
		$normalized['lastModified']       = $last_modified_date;
		$normalized['last_modified_date'] = $last_modified_date;

		// Normalize references to array of URLs (for compatibility with plugin format)
		$normalized_refs = array();
		if ( isset( $item['references'] ) && is_array( $item['references'] ) ) {
			foreach ( $item['references'] as $ref ) {
				if ( isset( $ref['link'] ) ) {
					$normalized_refs[] = array(
						'url'   => $ref['link'],
						'label' => isset( $ref['value'] ) ? $ref['value'] : $ref['link'],
					);
				}
			}
		}
		// Add source_url as a reference if available
		if ( isset( $item['source_url'] ) ) {
			$normalized_refs[] = array(
				'url'   => $item['source_url'],
				'label' => 'WPVDB',
			);
		}
		$normalized['references'] = $normalized_refs;

		// Keep original data for any additional info needed
		if ( isset( $item['affects_themes'] ) ) {
			$normalized['affects_themes'] = $item['affects_themes'];
		}
		if ( isset( $item['miscellaneous'] ) ) {
			$normalized['miscellaneous'] = $item['miscellaneous'];
		}
		if ( isset( $item['proof_of_concept'] ) ) {
			$normalized['proof_of_concept'] = $item['proof_of_concept'];
		}

		if ( ! empty( $normalized['customer_theme'] ) && empty( $normalized['customer_theme_name'] ) ) {
			$normalized['customer_theme_name'] = $normalized['customer_theme'];
		}

		return $normalized;
	}

	/**
	 * Get sync statistics
	 *
	 * @return array
	 */







	/**
	 * AJAX handler for getting product details
	 */
	public function ajax_get_product_details() {
		check_ajax_referer( 'vortem_get_product_details', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! $this->is_setup_completed() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';

		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row by SKU; table from whitelist
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sku = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
				$sku
			),
			ARRAY_A
		);

		if ( ! $product ) {
			wp_send_json_error( 'Product not found' );
		}

		// Create WordPress-style product view
		$html = $this->render_product_view( $product );

		wp_send_json_success( $html );
	}

	/**
	 * AJAX handler for refreshing license status
	 */
	public function ajax_refresh_imports_counter() {

		check_ajax_referer( 'vortem_refresh_imports_counter', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! $this->is_setup_completed() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

		$data = array(
			'status'            => 'Active',
			'status_translated' => esc_html__( 'Active', 'vortem-ai' ),
			'days_left'         => esc_html__( 'Unlimited', 'vortem-ai' ),
			'validations_used'  => '0',
			'domains_used'      => '0',
			'expires_at'        => esc_html__( 'Never', 'vortem-ai' ),
			'features'          => array(),
			'imported_products' => $this->get_imported_products_count(),
			'usage'             => array(),
		);
		wp_send_json_success( $data );
	}

	/**
	 * Render product view in WordPress draft style
	 */
	private function render_product_view( $product ) {
		$html = '<div class="vortem-product-view">';

		// Header with title and status
		$html .= '<div class="product-header">';
		$html .= '<h1 class="product-title">' . esc_html( $product['name'] ) . '</h1>';
		$html .= '<div class="product-status">';
		$html .= '<span class="status-badge status-' . esc_attr( $product['sync_status'] ) . '">';
		$html .= esc_html( ucfirst( $product['sync_status'] ) );
		$html .= '</span>';
		$html .= '</div>';
		$html .= '</div>';

		// Main content area
		$html .= '<div class="product-content">';

		// Left column - Product details
		$html .= '<div class="product-details-column">';

		// Product image
		if ( ! empty( $product['images'] ) ) {
			$html           .= '<div class="product-image-section">';
			$placeholder_svg = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3; margin-bottom: 8px;"><path d="M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			$onerror_handler = "var img=this; img.onerror=null; img.style.display='none'; var placeholder=document.createElement('div'); placeholder.className='no-image-placeholder'; placeholder.innerHTML='" . esc_js( $placeholder_svg ) . "<span>No Image Available</span>'; img.parentNode.appendChild(placeholder);";
			$html           .= '<img src="' . esc_url( $product['images'] ) . '" alt="' . esc_attr( $product['name'] ) . '" class="product-main-image" style="max-width: 100%; height: auto;" loading="lazy" onerror="' . esc_attr( $onerror_handler ) . '">';
			$html           .= '</div>';
		}

		// Product information
		$html .= '<div class="product-info-section">';
		$html .= '<h2>Product Information</h2>';
		$html .= '<table class="form-table">';
		$html .= '<tr><th scope="row">SKU</th><td>' . esc_html( $product['sku'] ) . '</td></tr>';
		$html .= '<tr><th scope="row">Name</th><td>' . esc_html( $product['name'] ) . '</td></tr>';
		$html .= '<tr><th scope="row">Category</th><td>' . esc_html( $product['category'] ) . '</td></tr>';
		$html .= '<tr><th scope="row">Price</th><td>$' . esc_html( number_format( $product['price'], 2 ) ) . '</td></tr>';
		$html .= '<tr><th scope="row">Sync Status</th><td>' . esc_html( ucfirst( $product['sync_status'] ) ) . '</td></tr>';
		$html .= '<tr><th scope="row">Last Synced</th><td>' . esc_html( $product['sync_date'] ) . '</td></tr>';
		$html .= '</table>';
		$html .= '</div>';

		// Description
		if ( ! empty( $product['description'] ) ) {
			$html .= '<div class="product-description-section">';
			$html .= '<h2>Description</h2>';
			$html .= '<div class="product-description">' . wp_kses_post( $product['description'] ) . '</div>';
			$html .= '</div>';
		}

		$html .= '</div>'; // End left column

		// Right column - Actions and metadata
		$html .= '<div class="product-actions-column">';

		// Publish/Edit actions
		$html .= '<div class="product-actions">';
		$html .= '<h2>Actions</h2>';
		$html .= '<div class="action-buttons">';
		$html .= '<button type="button" class="button button-primary edit-product" data-sku="' . esc_attr( $product['sku'] ) . '">Edit Product</button>';
		$html .= '<button type="button" class="button import-to-woo" data-sku="' . esc_attr( $product['sku'] ) . '">Import to WooCommerce</button>';
		$html .= '<button type="button" class="button refresh-sync" data-sku="' . esc_attr( $product['sku'] ) . '">Refresh Sync</button>';
		$html .= '</div>';
		$html .= '</div>';

		// Product metadata
		$html .= '<div class="product-metadata">';
		$html .= '<h2>Product Data</h2>';
		$html .= '<div class="metadata-content">';
		$html .= '<p><strong>Vortem Product ID:</strong> ' . esc_html( $product['vortem_product_id'] ) . '</p>';
		$html .= '<p><strong>Created:</strong> ' . esc_html( $product['created_at'] ) . '</p>';
		$html .= '<p><strong>Last Updated:</strong> ' . esc_html( $product['last_updated'] ) . '</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>'; // End right column
		$html .= '</div>'; // End main content

		$html .= '</div>'; // End product view

		return $html;
	}

	/**
	 * AJAX handler for editing product
	 */
	public function ajax_edit_product() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! $this->is_setup_completed() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';

		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row by SKU; table from whitelist
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sku = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
				$sku
			),
			ARRAY_A
		);

		if ( ! $product ) {
			wp_send_json_error( 'Product not found' );
		}

		// Create WordPress-style edit form
		$html = $this->render_product_edit_form( $product );

		wp_send_json_success( $html );
	}

	/**
	 * Render product edit form in WordPress style
	 */
	private function render_product_edit_form( $product ) {
		$html = '<div class="vortem-product-edit">';

		// Header with title and save button
		$html .= '<div class="product-edit-header">';
		$html .= '<h1 class="product-title">Edit Product: ' . esc_html( $product['name'] ) . '</h1>';
		$html .= '<div class="product-actions">';
		$html .= '<button type="button" class="button button-primary save-product" data-sku="' . esc_attr( $product['sku'] ) . '">Save Changes</button>';
		$html .= '<button type="button" class="button cancel-edit">Cancel</button>';
		$html .= '</div>';
		$html .= '</div>';

		// Main content area
		$html .= '<div class="product-edit-content">';

		// Left column - Product details form
		$html .= '<div class="product-edit-column">';

		// Product image
		if ( ! empty( $product['images'] ) ) {
			$html           .= '<div class="product-image-section">';
			$html           .= '<h2>Product Image</h2>';
			$placeholder_svg = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3; margin-bottom: 8px;"><path d="M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			$onerror_handler = "var img=this; img.onerror=null; img.style.display='none'; var placeholder=document.createElement('div'); placeholder.className='no-image-placeholder'; placeholder.innerHTML='" . esc_js( $placeholder_svg ) . "<span>No Image Available</span>'; img.parentNode.appendChild(placeholder);";
			$html           .= '<img src="' . esc_url( $product['images'] ) . '" alt="' . esc_attr( $product['name'] ) . '" class="product-main-image" style="max-width: 300px; height: auto;" loading="lazy" onerror="' . esc_attr( $onerror_handler ) . '">';
			$html           .= '<p class="description">Current product image</p>';
			$html           .= '</div>';
		}

		// Product information form
		$html .= '<div class="product-info-section">';
		$html .= '<h2>Product Information</h2>';
		$html .= '<table class="form-table">';

		// SKU (read-only)
		$html .= '<tr><th scope="row"><label for="product_sku">SKU</label></th>';
		$html .= '<td><input type="text" id="product_sku" name="sku" value="' . esc_attr( $product['sku'] ) . '" readonly class="regular-text" /></td></tr>';

		// Product Name
		$html .= '<tr><th scope="row"><label for="product_name">Product Name *</label></th>';
		$html .= '<td><input type="text" id="product_name" name="name" value="' . esc_attr( $product['name'] ) . '" class="regular-text" required /></td></tr>';

		// Category
		$html .= '<tr><th scope="row"><label for="product_category">Category</label></th>';
		$html .= '<td><input type="text" id="product_category" name="category" value="' . esc_attr( $product['category'] ) . '" class="regular-text" /></td></tr>';

		// Price
		$current_currency     = get_option( 'vortem_currency', 'USD' );
		$supported_currencies = $this->get_supported_currencies();
		$currency_symbol      = isset( $supported_currencies[ $current_currency ] ) ? $supported_currencies[ $current_currency ]['symbol'] : '$';
		$currency_code        = $current_currency;
		$html                .= '<tr><th scope="row"><label for="product_price">Price</label></th>';
		$html                .= '<td><input type="number" id="product_price" name="price" value="' . esc_attr( $product['price'] ) . '" step="0.01" min="0" class="small-text" /> ' . esc_html( $currency_code ) . '</td></tr>';

		// Sync Status
		$html .= '<tr><th scope="row"><label for="product_sync_status">Sync Status</label></th>';
		$html .= '<td><select id="product_sync_status" name="sync_status">';
		$html .= '<option value="synced"' . selected( $product['sync_status'], 'synced', false ) . '>Synced</option>';
		$html .= '<option value="pending"' . selected( $product['sync_status'], 'pending', false ) . '>Pending</option>';
		$html .= '<option value="failed"' . selected( $product['sync_status'], 'failed', false ) . '>Failed</option>';
		$html .= '</select></td></tr>';

		$html .= '</table>';
		$html .= '</div>';

		// Description
		$html .= '<div class="product-description-section">';
		$html .= '<h2>Description</h2>';
		$html .= '<textarea id="product_description" name="description" rows="10" cols="50" class="large-text">' . esc_textarea( $product['description'] ) . '</textarea>';
		$html .= '<p class="description">Enter the product description. HTML is allowed.</p>';
		$html .= '</div>';

		$html .= '</div>'; // End left column

		// Right column - Additional options and metadata
		$html .= '<div class="product-edit-sidebar">';

		// Product data
		$html .= '<div class="product-data-section">';
		$html .= '<h2>Product Data</h2>';
		$html .= '<div class="product-data-content">';
		$html .= '<p><strong>Vortem Product ID:</strong> ' . esc_html( $product['vortem_product_id'] ) . '</p>';
		$html .= '<p><strong>Created:</strong> ' . esc_html( $product['created_at'] ) . '</p>';
		$html .= '<p><strong>Last Updated:</strong> ' . esc_html( $product['last_updated'] ) . '</p>';
		$html .= '<p><strong>Last Synced:</strong> ' . esc_html( $product['sync_date'] ) . '</p>';
		$html .= '</div>';
		$html .= '</div>';

		// Additional actions
		$html .= '<div class="product-actions-section">';
		$html .= '<h2>Actions</h2>';
		$html .= '<div class="action-buttons">';
		$html .= '<button type="button" class="button import-to-woo" data-sku="' . esc_attr( $product['sku'] ) . '">Import to WooCommerce</button>';
		$html .= '<button type="button" class="button refresh-sync" data-sku="' . esc_attr( $product['sku'] ) . '">Refresh Sync</button>';
		$html .= '<button type="button" class="button button-secondary delete-product" data-sku="' . esc_attr( $product['sku'] ) . '">Delete Product</button>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>'; // End right column
		$html .= '</div>'; // End main content

		$html .= '</div>'; // End product edit

		return $html;
	}

	/**
	 * AJAX handler for saving product changes
	 */
	public function ajax_save_product() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! $this->is_setup_completed() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

		$sku         = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$price       = isset( $_POST['price'] ) ? floatval( wp_unslash( $_POST['price'] ) ) : 0;
		$sync_status = isset( $_POST['sync_status'] ) ? sanitize_text_field( wp_unslash( $_POST['sync_status'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

		if ( empty( $sku ) || empty( $name ) ) {
			wp_send_json_error( 'SKU and Name are required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

		$data = array(
			'name'         => $name,
			'category'     => $category,
			'price'        => $price,
			'sync_status'  => $sync_status,
			'description'  => $description,
			'last_updated' => current_time( 'mysql' ),
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row update; table from whitelist
		$result = $wpdb->update( $table, $data, array( 'sku' => $sku ) );

		if ( $result === false ) {
			wp_send_json_error( esc_html__( 'Failed to update product', 'vortem-ai' ) );
		}

		wp_send_json_success( 'Product updated successfully' );
	}

	/**
	 * Static AJAX handler for editing product
	 */
	public static function ajax_edit_product_static() {
		$admin = new self();
		$admin->ajax_edit_product();
	}

	/**
	 * Static AJAX handler for saving product
	 */
	public static function ajax_save_product_static() {
		$admin = new self();
		$admin->ajax_save_product();
	}

	/**
	 * Static AJAX handler for getting product details
	 */
	public static function ajax_get_product_details_static() {
		$admin = new self();
		$admin->ajax_get_product_details();
	}

	/**
	 * Reset setup completion (for debugging/testing)
	 */
	public function reset_setup_completion() {
		// Delete all Vortem plugin options
		$vortem_options = array(
			'vortem_setup_completed',
			'vortem_setup_completed_date',
			'vortem_terms_accepted',
			'vortem_terms_accepted_date',
			'vortem_products_per_page',
			'vortem_api_url',
			'vortem_last_sync',
			'vortem_sync_status',
			'vortem_import_status',
			'vortem_product_count',
			'vortem_error_log',
			'vortem_debug_mode',
			'vortem_log_level',
		);

		foreach ( $vortem_options as $option ) {
			delete_option( $option );
		}

		// Clear any Vortem transients
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off transient cleanup on reset
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->options
				'_transient_vortem_%',
				'_transient_timeout_vortem_%'
			)
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=vortem-setup-wizard' ) );
		exit;
	}

	/**
	 * Handle form submissions
	 */
	private function handle_form_submissions() {
		// Handle settings form submission (including products per page validation)
		$wpnonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $wpnonce, 'vortem_settings-options' ) ) {
			// Validate products per page
			if ( isset( $_POST['vortem_products_per_page'] ) ) {
				$products_per_page = intval( wp_unslash( $_POST['vortem_products_per_page'] ) );

				if ( $products_per_page > 100 ) {
					// Store error notice
					set_transient( 'vortem_settings_error_notice', __( 'Products per page cannot exceed 100. Please enter a value between 1 and 100.', 'vortem-ai' ), 30 );
					return; // Prevent saving
				}

				if ( $products_per_page < 1 ) {
					// Store error notice
					set_transient( 'vortem_settings_error_notice', __( 'Products per page must be at least 1. Please enter a valid value.', 'vortem-ai' ), 30 );
					return; // Prevent saving
				}

				// Save products per page if validation passes
				update_option( 'vortem_products_per_page', $products_per_page );
			}

			// Handle currency selection
			if ( isset( $_POST['vortem_currency'] ) ) {
				$currency             = sanitize_text_field( wp_unslash( $_POST['vortem_currency'] ) );
				$supported_currencies = $this->get_supported_currencies();

				if ( isset( $supported_currencies[ $currency ] ) ) {
					update_option( 'vortem_currency', $currency );
					update_option( 'vortem_customer_currency', $currency );
				}
			}

			// Handle video settings
			$add_video_to_description = isset( $_POST['vortem_add_video_to_description'] ) ? true : false;
			update_option( 'vortem_add_video_to_description', $add_video_to_description );

			$download_video_for_excluded_themes = isset( $_POST['vortem_download_video_for_excluded_themes'] ) ? true : false;
			update_option( 'vortem_download_video_for_excluded_themes', $download_video_for_excluded_themes );

			// Store success notice
			set_transient( 'vortem_settings_success_notice', __( 'Settings saved successfully!', 'vortem-ai' ), 30 );
		}

		// Handle product import
		$import_nonce = isset( $_POST['vortem_import_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vortem_import_nonce'] ) ) : '';
		if ( isset( $_POST['import_products'] ) && wp_verify_nonce( $import_nonce, 'vortem_import_products' ) ) {
			$this->handle_import_products_form();
		}

		// Handle reset sync
		$reset_nonce = isset( $_POST['vortem_reset_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vortem_reset_nonce'] ) ) : '';
		if ( isset( $_POST['reset_sync'] ) && wp_verify_nonce( $reset_nonce, 'vortem_reset_sync' ) ) {
			$this->handle_reset_sync_form();
		}

		// Handle test API
		$test_nonce = isset( $_POST['vortem_test_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vortem_test_nonce'] ) ) : '';
		if ( isset( $_POST['test_api'] ) && wp_verify_nonce( $test_nonce, 'vortem_test_api' ) ) {
			$this->handle_test_api_form();
		}

		// Handle database validation
		$db_nonce = isset( $_POST['vortem_db_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vortem_db_nonce'] ) ) : '';
		if ( isset( $_POST['validate_database'] ) && wp_verify_nonce( $db_nonce, 'vortem_validate_db' ) ) {
			$this->handle_database_validation_form();
		}
	}

	/**
	 * Handle analytics cache clearing via admin action.
	 */
	public function handle_clear_analytics_cache() {
		// Support both vortem_clear_cache and megadash_clear_cache for compatibility
		$clear_cache = isset( $_GET['vortem_clear_cache'] ) || isset( $_GET['megadash_clear_cache'] );
		if ( ! $clear_cache || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			return;
		}

		// Verify nonce
		$nonce       = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		$nonce_valid = wp_verify_nonce( $nonce, 'vortem_clear_cache' );
		if ( ! $nonce_valid ) {
			return;
		}

		// Clear analytics cache
		if ( class_exists( 'Vortem_Analytics' ) ) {
			$analytics = new Vortem_Analytics();
			$analytics->clear_cache();
		}

		// Use Vortem custom notice system instead of admin_notices hook
		set_transient( 'vortem_settings_success_notice', esc_html__( 'Analytics cache cleared successfully!', 'vortem-ai' ), 30 );
	}


	/**
	 * Handle import products form submission
	 */
	private function handle_import_products_form() {
		try {
			// Get synced products
			$products = $this->get_synced_products();

			if ( empty( $products ) ) {
				// Store for custom notice display
				set_transient( 'vortem_products_warning_notice', __( 'No products to import. Please sync products first.', 'vortem-ai' ), 30 );
				return;
			}

			// Initialize product manager
			$product_manager = new Vortem_Product_Manager();

			$imported = 0;
			$failed   = 0;

			foreach ( $products as $product ) {
				$options = array(
					'force_sync'      => false,
					'sync_images'     => true,
					'sync_categories' => true,
					'dry_run'         => false,
				);
				$result  = $product_manager->sync_single_product( $product, $options );
				if ( $result && isset( $result['status'] ) && $result['status'] === 'synced' ) {
					++$imported;
				} else {
					++$failed;
				}
			}

			// Store for custom notice display
			// translators: %1$d is the number of products imported, %2$d is the number of failed imports
			set_transient( 'vortem_products_success_notice', sprintf( __( 'Import completed! %1$d products imported, %2$d failed.', 'vortem-ai' ), $imported, $failed ), 30 );
		} catch ( Exception $e ) {
			// Store for custom notice display
			// translators: %s is the error message
			set_transient( 'vortem_products_error_notice', sprintf( __( 'Import failed: %s', 'vortem-ai' ), $e->getMessage() ), 30 );
		}
	}


	/**
	 * Handle test API form submission
	 */
	private function handle_test_api_form() {

		try {
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
			$api_client = new Vortem_Api_Client();

			// Test all endpoints to generate comprehensive logs

			// Test 1: Basic Products
			$basic_products = $api_client->fetch_basic_products( array( 'limit' => 1 ) );

			// Test 3: Endpoint Validation
			$endpoint_validation = $api_client->validate_endpoint();

			// Store success notice
			set_transient( 'vortem_settings_success_notice', __( 'API Test Completed! Check debug.log for detailed logs. All endpoints tested successfully.', 'vortem-ai' ), 30 );

		} catch ( Exception $e ) {
			set_transient(
				'vortem_settings_error_notice',
				sprintf(
				// translators: %s: Error message
					esc_html__( 'API Test Failed: %s', 'vortem-ai' ),
					$e->getMessage()
				),
				30
			);
		}
	}

	/**
	 * Handle database validation form submission
	 */
	private function handle_database_validation_form() {

		try {
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-database.php';

			$validation_results = Vortem_Database::validate_and_fix_database();

			// Clear any existing validation transient to force re-run
			delete_transient( 'vortem_db_validated_' . get_current_user_id() );

			// Log results
			if ( ! empty( $validation_results['fixes'] ) ) {

				// Show success notice
				$fixes_message = __( 'Database structure validated and fixed:', 'vortem-ai' ) . ' ' . implode( ', ', $validation_results['fixes'] );
				set_transient( 'vortem_settings_success_notice', $fixes_message, 30 );
			} else {
				set_transient( 'vortem_settings_success_notice', __( 'Database structure is valid. No fixes needed.', 'vortem-ai' ), 30 );
			}

			if ( ! empty( $validation_results['errors'] ) ) {

				// Show error notice
				$errors_message = __( 'Database validation issues:', 'vortem-ai' ) . ' ' . implode( ', ', $validation_results['errors'] );
				set_transient( 'vortem_settings_error_notice', $errors_message, 30 );
			}
		} catch ( Exception $e ) {
			// translators: %s is the error message
			set_transient( 'vortem_settings_error_notice', sprintf( __( 'Database validation failed: %s', 'vortem-ai' ), $e->getMessage() ), 30 );
		}
	}

	/**
	 * Handle reset sync form submission
	 */
	private function handle_reset_sync_form() {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		// Check if table exists (pattern from whitelist, passed as value to prepare)
		$table_like = $wpdb->esc_like( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check; table name from whitelist
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) ) === $table;
		if ( $table_exists ) {
			// Delete all products that are not imported (woo_product_id is NULL or empty)
			// Keep only imported products (products with woo_product_id)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table; values prepared.
			$deleted_count = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}vortem_products WHERE woo_product_id IS NULL OR woo_product_id = %s",
					''
				)
			);

			// Also reset sync_status for remaining products
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table; values prepared.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}vortem_products SET sync_status = %s WHERE sync_status = %s",
					'pending',
					'synced'
				)
			);
		}

		// Store for custom notice display
		set_transient( 'vortem_products_success_notice', __( 'Sync status reset successfully! Non-imported products cleared.', 'vortem-ai' ), 30 );
	}

	/**
	 * Static AJAX handler for importing products
	 */
	public static function ajax_import_products_static() {
		$admin = new self();
		$admin->ajax_import_products();
	}

	/**
	 * Automatically clear past-due actions without UI
	 */
	public function auto_clear_past_due_actions() {
		// Only run on Vortem admin pages to avoid unnecessary processing
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( empty( $page ) || strpos( $page, 'vortem-ai' ) === false ) {
			return;
		}

		// External Library: Action Scheduler (WooCommerce/Automattic) - https://actionscheduler.org/ | License: GPLv3+ | Bundled with WooCommerce | Used for clearing past-due scheduled actions
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

	/**
	 * AJAX handler for importing products
	 */
	public function ajax_import_products() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! $this->is_setup_completed() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

		try {
			// Initialize product manager
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-manager.php';
			$product_manager = new Vortem_Product_Manager();

			// Get products from database
			global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table; value prepared.
			$products = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}vortem_products WHERE sync_status = %s",
					'synced'
				),
				ARRAY_A
			);

			if ( empty( $products ) ) {
				wp_send_json_error( 'No synced products found' );
			}

			$imported = 0;
			$failed   = 0;

			foreach ( $products as $product ) {
				$result = $product_manager->sync_single_product( $product );
				if ( $result ) {
					++$imported;
				} else {
					++$failed;
				}
			}

			wp_send_json_success( "Import completed! $imported products imported, $failed failed." );

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Import failed: ', 'vortem-ai' ) . esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Get count of imported products from WooCommerce
	 * Includes published, draft, and private products that have Vortem meta
	 * Published products are considered part of imported products
	 *
	 * @return int
	 */
	private function get_imported_products_count() {
		return vortem_get_imported_products_count();
	}


	/**
	 * AJAX handler for endpoint validation
	 */
	public function ajax_validate_endpoint() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		$api_client = new Vortem_Api_Client();
		$result     = $api_client->validate_endpoint();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'  => $result['message'],
					'response' => $result['response'],
				)
			);
		}
	}

	/**
	 * AJAX handler for fetching products
	 * Fetches products from API AND saves them to database for import
	 */
	public function ajax_fetch_products() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$limit = Vortem_Security::validate_limit( isset( $_POST['limit'] ) ? wp_unslash( $_POST['limit'] ) : get_option( 'vortem_products_per_page', 16 ), 16, 1, 100 );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$page = Vortem_Security::validate_page( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1, 1 );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$show_imported_only = Vortem_Security::validate_id( isset( $_POST['show_imported_only'] ) ? wp_unslash( $_POST['show_imported_only'] ) : 0, 0 );

		// Get category_id from POST (AJAX) or GET (URL parameter)
		// Priority: POST (AJAX request) > GET (URL parameter)
		$category_id = 0;
		if ( isset( $_POST['category_id'] ) && ! empty( $_POST['category_id'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
			$category_id = Vortem_Security::validate_category_id( wp_unslash( $_POST['category_id'] ), 0 );
		} elseif ( isset( $_GET['category'] ) && ! empty( $_GET['category'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
			$category_id = Vortem_Security::validate_category_id( wp_unslash( $_GET['category'] ), 0 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

		// If filtering for imported products only, query database directly
		if ( $show_imported_only ) {
			// Build category filter condition if category_id is provided
			$category_filter_join  = '';
			$category_filter_where = '';

			if ( ! empty( $category_id ) && is_numeric( $category_id ) ) {
				$category_id_int       = intval( $category_id );
				$category_filter_join  = "
                    INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = vp.woo_product_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                ";
				$category_filter_where = $wpdb->prepare( " AND tt.taxonomy = 'product_cat' AND tt.term_id = %d", $category_id_int );
			}

			// Get total count of imported products (only from this plugin's database)
			// Include products that have a valid WooCommerce product ID and still exist
			// This includes both published and draft products (all non-trash statuses)
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $table is from $wpdb->prefix (trusted), $category_filter vars are prepared
			$imported_count_query = "SELECT COUNT(DISTINCT vp.id) 
                FROM $table vp
                $category_filter_join
                WHERE vp.woo_product_id IS NOT NULL 
                AND vp.woo_product_id != ''
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->posts} p 
                    WHERE p.ID = vp.woo_product_id 
                    AND p.post_type = 'product'
                    AND p.post_status != 'trash'
                )
                $category_filter_where";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is built above with proper escaping
			$imported_count = $wpdb->get_var( $imported_count_query );
			$imported_count = intval( $imported_count );

			// Calculate pagination
			$offset      = ( $page - 1 ) * $limit;
			$total_pages = ( $imported_count > 0 && $limit > 0 ) ? ceil( $imported_count / $limit ) : 1;

			// Get paginated imported products from database
			// Include all products that still exist in WooCommerce (published, draft, private, etc.)
			// Published products are considered part of imported products
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $table is from $wpdb->prefix (trusted), $category_filter vars are prepared
			$imported_products_query = "SELECT DISTINCT vp.*
                FROM $table vp
                $category_filter_join
                WHERE vp.woo_product_id IS NOT NULL
                AND vp.woo_product_id != ''
                AND EXISTS (
                    SELECT 1 FROM {$wpdb->posts} p
                    WHERE p.ID = vp.woo_product_id
                    AND p.post_type = 'product'
                    AND p.post_status != 'trash'
                )
                $category_filter_where
                ORDER BY vp.sync_date DESC
                LIMIT %d OFFSET %d";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is built above with proper escaping
			$imported_products_db = $wpdb->get_results( $wpdb->prepare( $imported_products_query, $limit, $offset ), ARRAY_A );

			// Fetch imported products from API to get _id mapping
			$api_client            = new Vortem_Api_Client();
			$imported_products_map = array(); // Map product_id => _id

			// Fetch imported products from API to get _id for each product
			$api_page      = 1;
			$api_limit     = 100;
			$max_api_pages = 10; // Limit to prevent infinite loops

			while ( $api_page <= $max_api_pages ) {
				$imported_response = $api_client->fetch_imported_products(
					array(
						'page'  => $api_page,
						'limit' => $api_limit,
					)
				);

				if ( is_wp_error( $imported_response ) ) {
					// If API fails, continue without _id mapping
					break;
				}

				if ( isset( $imported_response['success'] ) && $imported_response['success'] &&
					isset( $imported_response['products'] ) && is_array( $imported_response['products'] ) ) {

					// Build mapping of product_id => _id
					foreach ( $imported_response['products'] as $api_product ) {
						if ( isset( $api_product['product_id'] ) && isset( $api_product['_id'] ) ) {
							$imported_products_map[ $api_product['product_id'] ] = $api_product['_id'];
						}
					}

					// Check if we should continue to next page
					if ( isset( $imported_response['have_next'] ) && $imported_response['have_next'] ) {
						++$api_page;
					} else {
						break; // No more pages
					}
				} else {
					break; // Invalid response format
				}
			}

			// Convert database records to product format
			$enhanced_products = array();
			foreach ( $imported_products_db as $db_product ) {
				// Verify that the WooCommerce product still exists
				$woo_product_id = $db_product['woo_product_id'];
				if ( empty( $woo_product_id ) ) {
					continue; // Skip if no woo_product_id
				}

				$woo_product = wc_get_product( $woo_product_id );
				if ( ! $woo_product || ! $woo_product->exists() ) {
					// Product was deleted from WooCommerce, clean up the database record
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row update; table from whitelist
					$wpdb->update(
						$table,
						array( 'woo_product_id' => null ),
						array( 'id' => $db_product['id'] )
					);
					continue; // Skip deleted products
				}

				// Try to get product data from meta_data if available
				$meta_data = json_decode( $db_product['meta_data'], true );

				// Build product array from database record and meta_data
				$product = array();
				if ( ! empty( $meta_data ) && is_array( $meta_data ) ) {
					// Use meta_data as base (contains full API product data)
					$product = $meta_data;
				}

				// Ensure required fields exist
				$product['product_id']  = $db_product['vortem_product_id'];
				$product['sku']         = $db_product['sku'] ?? $db_product['vortem_product_id'];
				$product['title']       = $db_product['name'];
				$product['description'] = $db_product['description'];
				// Set woo_product_id so Delete button will be shown (for both published and draft products)
				$product['woo_product_id'] = $woo_product_id;

				// Add _id from API mapping if available
				if ( isset( $imported_products_map[ $product['product_id'] ] ) ) {
					$product['_id'] = $imported_products_map[ $product['product_id'] ];
				}

				// Add preview URL for WooCommerce products
				$preview_url = get_preview_post_link( $woo_product_id );
				if ( $preview_url ) {
					$product['preview_url'] = $preview_url;
				}

				// Set price if not in meta_data
				if ( ! isset( $product['price'] ) && isset( $db_product['regular_price'] ) ) {
					$product['price'] = array( 'original' => floatval( $db_product['regular_price'] ) );
				}
				if ( ! isset( $product['variations'] ) && isset( $db_product['regular_price'] ) ) {
					$product['variations'] = array( array( 'price' => floatval( $db_product['regular_price'] ) ) );
				}

				// Set images if stored
				if ( isset( $db_product['images'] ) && ! empty( $db_product['images'] ) ) {
					$images = json_decode( $db_product['images'], true );
					if ( is_array( $images ) ) {
						$product['images'] = $images;
					}
				}

				$enhanced_products[] = $product;
			}

			wp_send_json_success(
				array(
					'products'    => $enhanced_products,
					'count'       => count( $enhanced_products ),
					'saved_to_db' => 0,
					'failed'      => 0,
					'total_found' => $imported_count, // Total count from database (only plugin-imported products)
					'page'        => $page,
					'total_pages' => $total_pages,
					'limit'       => $limit,
					'message'     => 'Retrieved ' . count( $enhanced_products ) . ' imported products',
				)
			);
			return;
		}

		// Normal mode: Fetch from API
		$api_client = new Vortem_Api_Client();

		// If category_id is provided, fetch products from that category
		if ( ! empty( $category_id ) ) {
			$result = $api_client->fetch_products_from_category(
				array(
					'cat_id' => strval( $category_id ), // Convert to string as API expects string
					'limit'  => $limit,
					'page'   => $page,
				)
			);
		} else {
			// Otherwise, fetch basic products
			$result = $api_client->fetch_basic_products(
				array(
					'limit' => $limit,
					'page'  => $page,
				)
			);
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		} else {
			// Extract products from the API response
			// Handle different response structures
			$products = array();
			if ( isset( $result['products'] ) && is_array( $result['products'] ) ) {
				$products = $result['products'];
			} elseif ( isset( $result['data']['products'] ) && is_array( $result['data']['products'] ) ) {
				$products = $result['data']['products'];
			} elseif ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
				// Maybe data is the products array directly
				$products = $result['data'];
			}

			// Extract pagination info from result (handle both endpoint formats)
			$total_found  = isset( $result['total_found'] ) ? intval( $result['total_found'] ) : ( isset( $result['returned'] ) ? intval( $result['returned'] ) : count( $products ) );
			$current_page = isset( $result['page'] ) ? intval( $result['page'] ) : $page;
			$total_pages  = isset( $result['total_pages'] ) ? intval( $result['total_pages'] ) : ( ( $total_found > 0 && $limit > 0 ) ? ceil( $total_found / $limit ) : 1 );

			// If category endpoint response, use returned count for total_found if available
			if ( ! empty( $category_id ) && isset( $result['returned'] ) && isset( $result['total_found'] ) ) {
				$total_found = intval( $result['total_found'] ); // Use total_found from category endpoint
			}

			// DO NOT save products to database during pagination
			// Products will only be saved to database when they are imported to WooCommerce

			// Enhance products with database information (woo_product_id) - only check for already imported products
			$enhanced_products = array();
			global $wpdb;
			$table = $wpdb->prefix . 'vortem_products';

			foreach ( $products as $product ) {
				$sku = $product['product_id'] ?? $product['sku'] ?? '';
				if ( ! empty( $sku ) ) {
					// Only check if product is already imported (has woo_product_id)
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-product lookup; table from whitelist
					$db_record = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT woo_product_id FROM {$table} WHERE sku = %s OR vortem_product_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
							$sku,
							$sku
						)
					);

					// Verify that the WooCommerce product actually still exists
					$woo_product_id = '';
					if ( $db_record && ! empty( $db_record->woo_product_id ) ) {
						$woo_product = wc_get_product( $db_record->woo_product_id );
						if ( $woo_product && $woo_product->exists() ) {
							$woo_product_id = $db_record->woo_product_id;
						} else {
							// Product was deleted from WooCommerce, clean up the database record
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row update; table from whitelist
							$wpdb->update(
								$table,
								array( 'woo_product_id' => null ),
								array( 'sku' => $sku )
							);
						}
					}

					// Add woo_product_id to product data only if the product exists
					if ( ! empty( $woo_product_id ) ) {
						$product['woo_product_id'] = $woo_product_id;
						// Add preview URL for WooCommerce products
						$preview_url = get_preview_post_link( $woo_product_id );
						if ( $preview_url ) {
							$product['preview_url'] = $preview_url;
						}
					}
				}
				$enhanced_products[] = $product;
			}

			wp_send_json_success(
				array(
					'products'    => $enhanced_products,
					'count'       => count( $enhanced_products ),
					'saved_to_db' => 0, // No products saved during pagination
					'failed'      => 0,
					'total_found' => $total_found,
					'page'        => $current_page,
					'total_pages' => $total_pages,
					'limit'       => $limit,
					'message'     => 'Retrieved ' . count( $enhanced_products ) . ' products',
				)
			);
		}
	}

	/**
	 * AJAX handler for fetching trend products
	 */
	public function ajax_fetch_trend_products() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		$default_limit = 20;
		$limit         = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : $default_limit;
		$page          = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;

		// Fetch from API
		$api_client = new Vortem_Api_Client();
		$result     = $api_client->fetch_trend_products(
			array(
				'limit' => $limit,
				'page'  => $page,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		} else {
			// Backend canonical shape:
			// { success: true, data: { pagination: {page, per_page, total, total_pages, ...}, data: [products] } }
			// Older/alternate shapes are kept as fallbacks so a backend rollback doesn't break the panel.
			$products   = array();
			$pagination = array();
			if ( is_array( $result ) ) {
				if ( isset( $result['data']['data'] ) && is_array( $result['data']['data'] ) ) {
					$products   = $result['data']['data'];
					$pagination = isset( $result['data']['pagination'] ) && is_array( $result['data']['pagination'] ) ? $result['data']['pagination'] : array();
				} elseif ( isset( $result[0] ) && is_array( $result[0] ) && ( isset( $result[0]['product_id'] ) || isset( $result[0]['pb_info'] ) ) ) {
					$products = $result;
				} elseif ( isset( $result['products'] ) && is_array( $result['products'] ) ) {
					$products = $result['products'];
				} elseif ( isset( $result['data'] ) && is_array( $result['data'] ) && isset( $result['data'][0] ) && is_array( $result['data'][0] ) && ( isset( $result['data'][0]['product_id'] ) || isset( $result['data'][0]['pb_info'] ) ) ) {
					$products = $result['data'];
				}
			}

			// Extract pagination info, preferring the nested `pagination` block.
			$total_found  = isset( $pagination['total'] ) ? intval( $pagination['total'] ) : ( isset( $result['total_found'] ) ? intval( $result['total_found'] ) : count( $products ) );
			$current_page = isset( $pagination['page'] ) ? intval( $pagination['page'] ) : ( isset( $result['page'] ) ? intval( $result['page'] ) : $page );
			$total_pages  = isset( $pagination['total_pages'] ) ? intval( $pagination['total_pages'] ) : ( isset( $result['total_pages'] ) ? intval( $result['total_pages'] ) : ( ( $total_found > 0 && $limit > 0 ) ? (int) ceil( $total_found / $limit ) : 1 ) );

			// Return products in a consistent structure
			wp_send_json_success(
				array(
					'products'    => $products,
					'total_found' => $total_found,
					'page'        => $current_page,
					'total_pages' => $total_pages,
					'limit'       => $limit,
				)
			);
		}
	}

	/**
	 * AJAX handler for fetching TikTok products
	 */
	public function ajax_fetch_tiktok_products() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		$default_limit = 20;
		$limit         = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : $default_limit;
		$page          = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;

		// Fetch from API
		$api_client = new Vortem_Api_Client();
		$result     = $api_client->fetch_tiktok_products(
			array(
				'limit' => $limit,
				'page'  => $page,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		} else {
			// Handle TikTok API response structure
			$products     = array();
			$total        = 0;
			$total_pages  = 1;
			$current_page = $page;
			$per_page     = $limit;

			if ( is_array( $result ) ) {
				if ( isset( $result['success'] ) && $result['success'] && isset( $result['data'] ) && is_array( $result['data'] ) && isset( $result['data'][0] ) ) {
					// New shape: data is a flat array of items (id, category, hashtags, stream_url, ...).
					$products     = $result['data'];
					$current_page = isset( $result['page'] ) ? intval( $result['page'] ) : $page;
					$per_page     = isset( $result['per_page'] ) ? intval( $result['per_page'] ) : $limit;
					$total_pages  = isset( $result['total_pages'] ) ? intval( $result['total_pages'] ) : 1;
					$total        = isset( $result['total'] ) ? intval( $result['total'] ) : ( $total_pages * $per_page );
				} elseif ( isset( $result['success'] ) && $result['success'] && isset( $result['data']['products'] ) && is_array( $result['data']['products'] ) ) {
					// Legacy shape: data.products array with metric fields.
					$data         = $result['data'];
					$products     = $data['products'];
					$total        = isset( $data['total'] ) ? intval( $data['total'] ) : count( $products );
					$total_pages  = isset( $data['total_pages'] ) ? intval( $data['total_pages'] ) : ( ( $total > 0 && $limit > 0 ) ? ceil( $total / $limit ) : 1 );
					$current_page = isset( $data['page'] ) ? intval( $data['page'] ) : $page;
				} elseif ( isset( $result['products'] ) && is_array( $result['products'] ) ) {
					$products    = $result['products'];
					$total       = isset( $result['total'] ) ? intval( $result['total'] ) : count( $products );
					$total_pages = isset( $result['total_pages'] ) ? intval( $result['total_pages'] ) : ( ( $total > 0 && $limit > 0 ) ? ceil( $total / $limit ) : 1 );
				}
			}

			// Force https on stream_url so videos don't get blocked as mixed content in the admin.
			foreach ( $products as &$product ) {
				if ( isset( $product['stream_url'] ) && is_string( $product['stream_url'] ) && 0 === strpos( $product['stream_url'], 'http://' ) ) {
					$product['stream_url'] = 'https://' . substr( $product['stream_url'], 7 );
				}
			}
			unset( $product );

			// Return products in a consistent structure
			wp_send_json_success(
				array(
					'products'    => $products,
					'total'       => $total,
					'total_found' => $total,
					'page'        => $current_page,
					'total_pages' => $total_pages,
					'limit'       => $per_page,
				)
			);
		}
	}

	/**
	 * AJAX handler for fetching top product categories that exist
	 * Returns paginated list of existing categories from the external_top_product collection
	 */
	public function ajax_get_top_categories_exist() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Security check failed',
				)
			);
			return;
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions',
				)
			);
			return;
		}

		// Get pagination parameters
		$page  = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
		$limit = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : 10;

		// Initialize API client and fetch categories
		$api_client = new Vortem_Api_Client();
		$result     = $api_client->fetch_top_categories_exist(
			array(
				'page'  => $page,
				'limit' => $limit,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
			return;
		}

		// Return the API response as-is (it should match the expected format)
		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for fetching categories from API
	 * Returns list of main categories with subcategories
	 */
	public function ajax_get_categories() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Security check failed',
				)
			);
			return;
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions',
				)
			);
			return;
		}

		// Get pagination parameters
		$page  = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
		$limit = isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : 50;

		// Initialize API client and fetch categories
		$api_client = new Vortem_Api_Client();
		$result     = $api_client->fetch_categories(
			array(
				'page'  => $page,
				'limit' => $limit,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
			return;
		}

		// Return the API response as-is
		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for fetching WooCommerce product categories used by imported products
	 * Returns list of categories that are assigned to imported products
	 */
	public function ajax_get_imported_product_categories() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Security check failed',
				)
			);
			return;
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => 'Insufficient permissions',
				)
			);
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

		// Get all WooCommerce categories that are assigned to imported products
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $table is from $wpdb->prefix (trusted)
		$categories_query = "
            SELECT DISTINCT t.term_id, t.name, COUNT(DISTINCT vp.id) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN $table vp ON vp.woo_product_id = tr.object_id
            WHERE tt.taxonomy = 'product_cat'
            AND vp.woo_product_id IS NOT NULL 
            AND vp.woo_product_id != ''
            AND EXISTS (
                SELECT 1 FROM {$wpdb->posts} p 
                WHERE p.ID = vp.woo_product_id 
                AND p.post_type = 'product'
                AND p.post_status != 'trash'
            )
            GROUP BY t.term_id, t.name
            ORDER BY t.name ASC
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is built above
		$categories = $wpdb->get_results( $categories_query, ARRAY_A );

		// Format categories to match the expected format
		$formatted_categories = array();
		foreach ( $categories as $category ) {
			$formatted_categories[] = array(
				'id'    => intval( $category['term_id'] ),
				'name'  => $category['name'],
				'count' => intval( $category['count'] ),
			);
		}

		wp_send_json_success(
			array(
				'categories' => $formatted_categories,
			)
		);
	}

	/**
	 * AJAX handler for importing single product
	 */
	public function ajax_import_single_product() {

		try {
			// Verify nonce
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Security check failed' ) );
				return;
			}

			// Check user permissions
			if ( ! vortem_current_user_can_manage() ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
				return;
			}

			// Validate product_id
			if ( ! isset( $_POST['product_id'] ) || empty( $_POST['product_id'] ) ) {
				wp_send_json_error( array( 'message' => 'Product ID is required' ) );
				return;
			}

			$product_id  = sanitize_text_field( wp_unslash( $_POST['product_id'] ) );
			$import_type = isset( $_POST['import_type'] ) ? sanitize_text_field( wp_unslash( $_POST['import_type'] ) ) : 'normal';

			// Use Product Fetcher which fetches data directly from the API
			if ( ! class_exists( 'Vortem_Product_Fetcher' ) ) {
				require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-fetcher.php';
			}

			if ( ! class_exists( 'Vortem_Product_Fetcher' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to load product fetcher class', 'vortem-ai' ) ) );
				return;
			}

			$product_fetcher = new Vortem_Product_Fetcher();

			// Import product - this method fetches fresh data from API
			$result = $product_fetcher->import_single_product_to_wordpress( $product_id, $import_type );

			if ( ! is_array( $result ) || ! isset( $result['success'] ) ) {
				wp_send_json_error(
					array(
						'message' => 'Invalid response from import process',
					)
				);
				return;
			}

			if ( ! $result['success'] ) {
				wp_send_json_error(
					array(
						'message'       => $result['message'] ?? 'Import failed',
						'import_status' => 'failed',
					)
				);
				return;
			}

			// Check if this was a duplicate/skipped import
			$is_duplicate = isset( $result['is_duplicate'] ) && $result['is_duplicate'];
			$was_skipped  = isset( $result['skipped'] ) && $result['skipped'];

			// Get the WooCommerce product ID from the result
			$woo_product_id = $result['product_id'] ?? $result['wp_product_id'] ?? null;

			if ( ! $woo_product_id ) {
				// Try to get from database as fallback
				global $wpdb;
				$table = $wpdb->prefix . 'vortem_products';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback lookup; table from whitelist
				$product_record = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT woo_product_id FROM {$table} WHERE sku = %s OR vortem_product_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
						$product_id,
						$product_id
					)
				);

				$woo_product_id = $product_record ? $product_record->woo_product_id : null;
			}

			// Verify the product exists in WooCommerce drafts
			$product_exists = false;
			if ( $woo_product_id ) {
				$product = wc_get_product( $woo_product_id );
				if ( $product && $product->exists() ) {
					$product_exists = true;
				}
			}

			// Determine import status
			$import_status = 'success';
			if ( $is_duplicate || $was_skipped ) {
				$import_status = 'duplicate';
			} elseif ( ! $product_exists && ! $woo_product_id ) {
				$import_status = 'failed';
			}

			// Build edit URL directly to ensure correct format: post.php?post=ID&action=edit
			$edit_url = null;
			if ( $woo_product_id ) {
				$edit_url = admin_url( 'post.php?post=' . intval( $woo_product_id ) . '&action=edit' );
			}

			wp_send_json_success(
				array(
					'message'        => $import_status === 'duplicate' ? 'Duplicate product' : ( $import_status === 'failed' ? 'Import failed' : 'Product imported successfully as draft' ),
					'woo_product_id' => $woo_product_id,
					'edit_url'       => $edit_url,
					'import_status'  => $import_status,
					'is_duplicate'   => $is_duplicate,
					'product_exists' => $product_exists,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'An error occurred: ', 'vortem-ai' ) . esc_html( $e->getMessage() ),
				)
			);
		} catch ( Error $e ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'A fatal error occurred: ', 'vortem-ai' ) . esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX handler for deleting single product
	 */
	public function ajax_delete_single_product() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
		$api_id     = isset( $_POST['api_id'] ) ? sanitize_text_field( wp_unslash( $_POST['api_id'] ) ) : '';

		if ( empty( $product_id ) ) {
			wp_send_json_error(
				array(
					'message' => 'Product ID is required',
				)
			);
			return;
		}

		// Use provided _id if available, otherwise fetch from API
		$found_id = ! empty( $api_id ) ? $api_id : null;

		// If _id not provided, fetch imported products to get the _id mapping
		if ( empty( $found_id ) ) {
			$api_client = new Vortem_Api_Client();

			// Fetch imported products to find the _id for this product_id
			// We'll fetch multiple pages if needed to find the product
			$page  = 1;
			$limit = 100; // Fetch more products per page to reduce API calls

			while ( $page <= 10 ) { // Limit to 10 pages to avoid infinite loops
				$imported_response = $api_client->fetch_imported_products(
					array(
						'page'  => $page,
						'limit' => $limit,
					)
				);

				if ( is_wp_error( $imported_response ) ) {
					// If we can't fetch from API, continue with WordPress deletion only
					break;
				}

				if ( isset( $imported_response['success'] ) && $imported_response['success'] &&
					isset( $imported_response['products'] ) && is_array( $imported_response['products'] ) ) {

					// Search for the product_id in the response
					foreach ( $imported_response['products'] as $product ) {
						if ( isset( $product['product_id'] ) && $product['product_id'] === $product_id ) {
							$found_id = isset( $product['_id'] ) ? $product['_id'] : null;
							break 2; // Break out of both loops
						}
					}

					// Check if we should continue to next page
					if ( isset( $imported_response['have_next'] ) && $imported_response['have_next'] ) {
						++$page;
					} else {
						break; // No more pages
					}
				} else {
					break; // Invalid response format
				}
			}
		}

		// If we found the _id, delete from API first
		if ( $found_id ) {
			$delete_response = $api_client->delete_imported_product( $found_id );

			if ( is_wp_error( $delete_response ) ) {
				vortem_log( 'Product delete: API delete failed (continuing WP deletion): ' . $delete_response->get_error_message() );
			}
		}

		// Always delete from WordPress as well
		$product_manager = new Vortem_Product_Manager();
		$result          = $product_manager->delete_single_product( $product_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'     => 'Product deleted successfully',
					'product_id'  => $product_id,
					'api_deleted' => $found_id ? true : false,
				)
			);
		}
	}

	/**
	 * Handle WooCommerce product permanent deletion from Products list
	 * Ensures the product is removed from Vortem local table and remote API.
	 *
	 * @param int $post_id WordPress post ID for the product
	 */
	public function handle_woocommerce_product_delete( $post_id ) {
		// Only run in admin area
		if ( ! is_admin() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'product' ) {
			return;
		}

		// Get the Vortem product ID and imported ID stored on the WooCommerce product
		$vortem_product_id = get_post_meta( $post_id, '_vortem_product_id', true );
		$imported_id       = get_post_meta( $post_id, '_vortem_imported_id', true );

		// Try to delete from remote API (if we have the imported ID stored)
		if ( ! empty( $imported_id ) ) {
			if ( ! class_exists( 'Vortem_Api_Client' ) ) {
				require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
			}

			$api_client      = new Vortem_Api_Client();
			$delete_response = $api_client->delete_imported_product( $imported_id );

			// Log the deletion attempt (but don't block local deletion if API fails)
			if ( is_wp_error( $delete_response ) ) {
				vortem_log( 'Failed to delete product from external API: ' . $delete_response->get_error_message() . ' (Product ID: ' . $vortem_product_id . ')' );
			} else {
				vortem_log( 'Successfully deleted product from external API (Product ID: ' . $vortem_product_id . ', Imported ID: ' . $imported_id . ')' );
			}
		} elseif ( ! empty( $vortem_product_id ) ) {
			// Fallback: If we don't have the stored _id, search for it (legacy behavior)
			vortem_log( 'No stored imported ID found, falling back to search method for product: ' . $vortem_product_id );

			if ( ! class_exists( 'Vortem_Api_Client' ) ) {
				require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
			}

			$api_client = new Vortem_Api_Client();
			$found_id   = null;

			// Fetch imported products to find the _id for this product_id
			$page  = 1;
			$limit = 100;

			while ( $page <= 10 ) { // Avoid infinite loops
				$imported_response = $api_client->fetch_imported_products(
					array(
						'page'  => $page,
						'limit' => $limit,
					)
				);

				if ( is_wp_error( $imported_response ) ) {
					// If API is not reachable, stop trying but still allow local deletion
					break;
				}

				if ( isset( $imported_response['success'] ) && $imported_response['success'] &&
					isset( $imported_response['products'] ) && is_array( $imported_response['products'] ) ) {

					foreach ( $imported_response['products'] as $product ) {
						if ( isset( $product['product_id'] ) && (string) $product['product_id'] === (string) $vortem_product_id ) {
							$found_id = isset( $product['_id'] ) ? $product['_id'] : null;
							break 2;
						}
					}

					if ( isset( $imported_response['have_next'] ) && $imported_response['have_next'] ) {
						++$page;
					} else {
						break;
					}
				} else {
					break;
				}
			}

			if ( $found_id ) {
				$delete_response = $api_client->delete_imported_product( $found_id );
				if ( is_wp_error( $delete_response ) ) {
					vortem_log( 'Failed to delete product from external API (fallback method): ' . $delete_response->get_error_message() );
				} else {
					vortem_log( 'Successfully deleted product from external API (fallback method) for product: ' . $vortem_product_id );
				}
			} else {
				vortem_log( 'Could not find imported ID for product: ' . $vortem_product_id . ' - skipping external API deletion' );
			}
		}

		// Always delete mapping from Vortem local products table
		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'woo_product_id' => $post_id ) );

		vortem_log( 'Completed WooCommerce product deletion cleanup for product ID: ' . $post_id );
	}

	/**
	 * AJAX handler for checking product import status
	 */
	public function ajax_check_product_status() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed', 'vortem-ai' ),
				)
			);
		}

		// Check user permissions
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Insufficient permissions', 'vortem-ai' ),
				)
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
		$sku        = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';

		$product_manager = new Vortem_Product_Manager();

		// Check if product exists in WooCommerce (even if not properly imported)
		$exists_check = $product_manager->check_product_exists_in_woocommerce( $product_id, $sku );

		wp_send_json_success(
			array(
				'is_imported'           => $exists_check['is_imported'],
				'exists_in_woocommerce' => $exists_check['exists'],
				'woo_product_id'        => $exists_check['woo_product_id'],
				'product_id'            => $product_id,
			)
		);
	}

	/**
	 * Validate and fix database on admin init
	 */
	public function validate_database_on_admin_init() {
		// Only run on Vortem admin pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( empty( $page ) || strpos( $page, 'vortem-ai' ) === false ) {
			return;
		}

		// Only run once per session to avoid performance issues
		if ( get_transient( 'vortem_db_validated_' . get_current_user_id() ) ) {
			return;
		}

		try {
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-database.php';

			$validation_results = Vortem_Database::validate_and_fix_database();

			// Set transient to avoid running again in same session
			set_transient( 'vortem_db_validated_' . get_current_user_id(), true, 3600 ); // 1 hour

			// Log results
			if ( ! empty( $validation_results['fixes'] ) ) {

				// Show success notice
				$fixes_message = __( 'Database structure validated and fixed:', 'vortem-ai' ) . ' ' . implode( ', ', $validation_results['fixes'] );
				set_transient( 'vortem_admin_success_notice', $fixes_message, 30 );
			}

			if ( ! empty( $validation_results['errors'] ) ) {

				// Show error notice
				$errors_message = __( 'Database validation issues:', 'vortem-ai' ) . ' ' . implode( ', ', $validation_results['errors'] );
				set_transient( 'vortem_admin_error_notice', $errors_message, 30 );
			}
		} catch ( Exception $e ) {
			set_transient(
				'vortem_admin_error_notice',
				sprintf(
				// translators: %s: Error message
					esc_html__( 'Database validation failed: %s', 'vortem-ai' ),
					$e->getMessage()
				),
				30
			);
		}
	}

	/**
	 * Show draft products in WordPress admin Products section
	 */
	public function show_draft_products_in_admin( $query ) {
		// Only modify queries in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Only modify product queries
		if ( ! $query->is_main_query() || $query->get( 'post_type' ) !== 'product' ) {
			return;
		}

		// Only modify the main products list (not search, filters, etc.)
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query modification for admin product list, no data modification
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for filter existence, no data modification
		if ( isset( $_GET['s'] ) || isset( $_GET['product_cat'] ) || isset( $_GET['product_tag'] ) || isset( $_GET['product_type'] ) ) {
			return;
		}
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Validate post_status using whitelist
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query modification, no data modification
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$current_status = Vortem_Security::validate_post_status( isset( $_GET['post_status'] ) ? wp_unslash( $_GET['post_status'] ) : '', 'all' );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// If no specific status is requested, show both published and draft products
		if ( empty( $current_status ) || $current_status === 'all' ) {
			$query->set( 'post_status', array( 'publish', 'draft' ) );
		}
	}

	/**
	 * Email Marketing AJAX Handlers
	 */
	private function verify_email_marketing_nonce() {
		if ( ! check_ajax_referer( 'vortem_email_marketing_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return false;
		}
		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return false;
		}
		return true;
	}

	/**
	 * Pre-flight validation for the email-marketing payloads.
	 *
	 * Mirrors the backend `validate:` tags so we surface a clear, friendly error
	 * before the request goes out instead of letting the user wait on a Go
	 * validator string ("CreateEmailRequest.EmailSubject 'min' tag").
	 *
	 * Backend rules: email_subject 16-256, email_content 128-8196, valid email.
	 *
	 * @param string $recipient         Single recipient (skipped when $require_recipient is false).
	 * @param string $subject           Email subject.
	 * @param string $content           Email body (HTML allowed).
	 * @param bool   $require_recipient Whether to validate the single-recipient field.
	 * @return string[] List of human-readable validation error messages; empty on success.
	 */
	private function vortem_em_validate_email_fields( $recipient, $subject, $content, $require_recipient = true ) {
		$errors  = array();
		$subject = (string) $subject;
		$content = (string) $content;

		if ( $require_recipient && ! is_email( $recipient ) ) {
			$errors[] = __( 'Recipient must be a valid email address.', 'vortem-ai' );
		}

		$subject_len = function_exists( 'mb_strlen' ) ? mb_strlen( $subject ) : strlen( $subject );
		if ( $subject_len < 16 ) {
			$errors[] = sprintf(
				/* translators: %d: current subject length in characters. */
				__( 'Subject must be at least 16 characters (current: %d).', 'vortem-ai' ),
				(int) $subject_len
			);
		} elseif ( $subject_len > 256 ) {
			$errors[] = sprintf(
				/* translators: %d: current subject length in characters. */
				__( 'Subject must not exceed 256 characters (current: %d).', 'vortem-ai' ),
				(int) $subject_len
			);
		}

		$content_len = strlen( $content );
		if ( $content_len < 128 ) {
			$errors[] = sprintf(
				/* translators: %d: current body length in characters. */
				__( 'Email body must be at least 128 characters (current: %d).', 'vortem-ai' ),
				(int) $content_len
			);
		} elseif ( $content_len > 8196 ) {
			$errors[] = sprintf(
				/* translators: %d: current body length in characters. */
				__( 'Email body must not exceed 8196 characters (current: %d).', 'vortem-ai' ),
				(int) $content_len
			);
		}

		return $errors;
	}

	/**
	 * Validate the email-list payload (multi-recipient variant).
	 *
	 * @param array  $recipients Sanitized list of recipient addresses.
	 * @param string $subject    Email subject.
	 * @param string $content    Email body.
	 * @return string[] List of human-readable validation error messages.
	 */
	private function vortem_em_validate_email_list_fields( $recipients, $subject, $content ) {
		$errors = $this->vortem_em_validate_email_fields( '', $subject, $content, false );
		if ( ! is_array( $recipients ) || empty( $recipients ) ) {
			$errors[] = __( 'At least one valid recipient is required.', 'vortem-ai' );
		}
		return $errors;
	}

	public function ajax_em_get_emails() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api    = new Vortem_Email_Marketing_Api();
		$result = $api->get_emails();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_search_emails() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$params = array(
			'q'     => isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '',
			'page'  => isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1,
			'limit' => isset( $_POST['limit'] ) ? intval( wp_unslash( $_POST['limit'] ) ) : 10,
		);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = $api->search_emails( $params );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_get_email() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => 'Email ID is required' ) );
			return;
		}
		$result = $api->get_email( $email_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_get_email_status() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => 'Email ID is required' ) );
			return;
		}
		$result = $api->get_email_status( $email_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_create_email() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_content = isset( $_POST['email_content'] ) ? wp_kses_post( wp_unslash( $_POST['email_content'] ) ) : '';
		$data          = array(
			'recipient'          => isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '',
			'email_subject'      => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_content'      => $email_content,
			'email_created_time' => isset( $_POST['email_created_time'] ) ? sanitize_text_field( wp_unslash( $_POST['email_created_time'] ) ) : current_time( 'c' ),
		);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		$validation_errors = $this->vortem_em_validate_email_fields( $data['recipient'], $data['email_subject'], $data['email_content'] );
		if ( ! empty( $validation_errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $validation_errors ),
					'errors'  => $validation_errors,
				)
			);
			return;
		}
		$result = $api->create_email( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_update_email() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => 'Email ID is required' ) );
			return;
		}
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$email_content = isset( $_POST['email_content'] ) ? wp_kses_post( wp_unslash( $_POST['email_content'] ) ) : '';
		// `email_created_time` is required by the backend UpdateEmailRequest;
		// fall back to the current time if the client didn't send one.
		$email_created_time = isset( $_POST['email_created_time'] )
			? sanitize_text_field( wp_unslash( $_POST['email_created_time'] ) )
			: current_time( 'c' );
		$data               = array(
			'email_created_time' => $email_created_time,
			'recipient'          => isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '',
			'email_subject'      => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_content'      => $email_content,
		);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		$validation_errors = $this->vortem_em_validate_email_fields( $data['recipient'], $data['email_subject'], $data['email_content'] );
		if ( ! empty( $validation_errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $validation_errors ),
					'errors'  => $validation_errors,
				)
			);
			return;
		}
		$result = $api->update_email( $email_id, $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_delete_email() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => 'Email ID is required' ) );
			return;
		}
		$result = $api->delete_email( $email_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_bulk_delete_emails() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce via verify_email_marketing_nonce(); each id sanitized via sanitize_text_field below
		// Email IDs are MongoDB ObjectIDs (24-char hex), not integers, so absint() would corrupt them.
		$raw_ids   = isset( $_POST['email_ids'] ) ? (array) wp_unslash( $_POST['email_ids'] ) : array();
		$email_ids = array_values( array_filter( array_map( 'sanitize_text_field', $raw_ids ) ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $email_ids ) ) {
			wp_send_json_error( array( 'message' => 'Email IDs are required' ) );
			return;
		}
		$result = $api->bulk_delete_emails( $email_ids );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_send_email() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => 'Email ID is required' ) );
			return;
		}
		$result = $api->send_email( $email_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_get_useg() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api    = new Vortem_Email_Marketing_Api();
		$result = $api->get_useg();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_get_email_lists() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api    = new Vortem_Email_Marketing_Api();
		$result = $api->get_email_lists();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_create_email_list() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce via verify_email_marketing_nonce(); inputs sanitized below
		// Handle email_recipients: unslash and sanitize each item (array or comma/newline-separated string)
		$email_recipients = array();
		if ( isset( $_POST['email_recipients'] ) ) {
			$raw = wp_unslash( $_POST['email_recipients'] );
			if ( is_array( $raw ) ) {
				$email_recipients = array_filter( array_map( 'sanitize_email', array_map( 'trim', $raw ) ) );
			} else {
				$parsed           = array_map( 'trim', explode( ',', str_replace( array( "\n", "\r" ), ',', sanitize_textarea_field( $raw ) ) ) );
				$email_recipients = array_filter( array_map( 'sanitize_email', $parsed ) );
			}
		}

		// Validate email content length (check raw length first, then sanitize for use)
		$email_content_raw = isset( $_POST['email_content'] ) ? wp_unslash( $_POST['email_content'] ) : '';
		$email_content     = wp_kses_post( $email_content_raw );
		$data              = array(
			'email_subject'    => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_recipients' => array_values( $email_recipients ),
			'email_content'    => $email_content,
		);
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$validation_errors = $this->vortem_em_validate_email_list_fields( $data['email_recipients'], $data['email_subject'], $data['email_content'] );
		if ( ! empty( $validation_errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $validation_errors ),
					'errors'  => $validation_errors,
				)
			);
			return;
		}
		$result = $api->create_email_list( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_update_email_list() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce via verify_email_marketing_nonce(); inputs sanitized below
		$email_list_id = isset( $_POST['email_list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_list_id'] ) ) : '';
		if ( empty( $email_list_id ) ) {
			wp_send_json_error( array( 'message' => 'Email list ID is required' ) );
			return;
		}

		// Handle email_recipients: unslash and sanitize each item (array or comma/newline-separated string)
		$email_recipients = array();
		if ( isset( $_POST['email_recipients'] ) ) {
			$raw = wp_unslash( $_POST['email_recipients'] );
			if ( is_array( $raw ) ) {
				$email_recipients = array_filter( array_map( 'sanitize_email', array_map( 'trim', $raw ) ) );
			} else {
				$parsed           = array_map( 'trim', explode( ',', str_replace( array( "\n", "\r" ), ',', sanitize_textarea_field( $raw ) ) ) );
				$email_recipients = array_filter( array_map( 'sanitize_email', $parsed ) );
			}
		}

		// Validate email content length (check raw length first, then sanitize for use)
		$email_content_raw = isset( $_POST['email_content'] ) ? wp_unslash( $_POST['email_content'] ) : '';
		$email_content     = wp_kses_post( $email_content_raw );
		$data              = array(
			'email_list_id'    => $email_list_id,
			'email_subject'    => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_recipients' => array_values( $email_recipients ),
			'email_content'    => $email_content,
		);
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$validation_errors = $this->vortem_em_validate_email_list_fields( $data['email_recipients'], $data['email_subject'], $data['email_content'] );
		if ( ! empty( $validation_errors ) ) {
			wp_send_json_error(
				array(
					'message' => implode( ' ', $validation_errors ),
					'errors'  => $validation_errors,
				)
			);
			return;
		}
		$result = $api->update_email_list( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_delete_email_list() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$list_id = isset( $_POST['email_list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_list_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $list_id ) ) {
			wp_send_json_error( array( 'message' => 'Email list ID is required' ) );
			return;
		}
		$result = $api->delete_email_list( $list_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	public function ajax_em_send_email_list() {
		if ( ! $this->verify_email_marketing_nonce() ) {
			return;
		}
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
		$api = new Vortem_Email_Marketing_Api();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_email_marketing_nonce() above
		$list_id = isset( $_POST['email_list_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_list_id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( empty( $list_id ) ) {
			wp_send_json_error( array( 'message' => 'Email list ID is required' ) );
			return;
		}
		$result = $api->send_email_list( $list_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	/**
	 * AJAX handler for Insights data
	 */
	public function ajax_get_insights() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_insights_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			wp_send_json_error( array( 'message' => 'Data processing consent required.' ), 451 );
			return;
		}

		// Get URL parameter - use current site URL as default
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified earlier in this function
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : get_site_url();

		// Get API base URL
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
		$api_base_url = rtrim( Vortem_Config::get_primary_api_server(), '/' );

		// Build endpoint URL - request English language data
		$endpoint = Vortem_Config::get_api_endpoint( 'page_speed_wordpress' );
		$api_url  = $api_base_url . $endpoint . '?url=' . rawurlencode( $url ) . '&locale=en';

		// Prepare headers with simple format
		$headers = array(
			'Content-Type'    => 'application/json',
			'Referer'         => home_url(),
			'Accept-Language' => 'en-US,en;q=0.9',
		);

		// Make API request
		$args = array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 60, // Page speed analysis can take longer
			'sslverify' => true,
		);

		$response = wp_remote_request( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = 'API request failed with status: ' . $status_code;
			$decoded_error = json_decode( $body, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
				if ( isset( $decoded_error['message'] ) ) {
					$error_message = $decoded_error['message'];
				} elseif ( isset( $decoded_error['error']['message'] ) ) {
					$error_message = $decoded_error['error']['message'];
				} elseif ( isset( $decoded_error['error'] ) ) {
					$error_message = is_array( $decoded_error['error'] ) ? $decoded_error['error']['message'] : $decoded_error['error'];
				}
			}
			wp_send_json_error(
				array(
					'message'     => $error_message,
					'status_code' => $status_code,
				)
			);
			return;
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to decode JSON response', 'vortem-ai' ) ) );
			return;
		}

		// Cache performance scores for overview dashboard
		$perf_data = array(
			'desktop' => null,
			'mobile'  => null,
			'average' => null,
		);
		$d_score   = null;
		$m_score   = null;

		// Try new API structure first: dashboard.desktop.coreWebVitals.performance.score
		if ( isset( $decoded_response['dashboard']['desktop']['coreWebVitals']['performance']['score'] ) ) {
			$d_score = floatval( $decoded_response['dashboard']['desktop']['coreWebVitals']['performance']['score'] );
		}
		if ( isset( $decoded_response['dashboard']['mobile']['coreWebVitals']['performance']['score'] ) ) {
			$m_score = floatval( $decoded_response['dashboard']['mobile']['coreWebVitals']['performance']['score'] );
		}

		// Fallback to old API structure: desktop_data/mobile_data with lighthouseResult
		if ( $d_score === null || $m_score === null ) {
			$desktop_data = isset( $decoded_response['desktop_data'] ) ? $decoded_response['desktop_data'] : null;
			$mobile_data  = isset( $decoded_response['mobile_data'] ) ? $decoded_response['mobile_data'] : null;
			if ( $d_score === null && $desktop_data && isset( $desktop_data['lighthouseResult']['categories']['performance']['score'] ) ) {
				$d_score = floatval( $desktop_data['lighthouseResult']['categories']['performance']['score'] ) * 100;
			}
			if ( $m_score === null && $mobile_data && isset( $mobile_data['lighthouseResult']['categories']['performance']['score'] ) ) {
				$m_score = floatval( $mobile_data['lighthouseResult']['categories']['performance']['score'] ) * 100;
			}
		}

		$perf_data['desktop'] = $d_score !== null ? round( $d_score ) : null;
		$perf_data['mobile']  = $m_score !== null ? round( $m_score ) : null;
		if ( $d_score !== null || $m_score !== null ) {
			$perf_data['average'] = round( ( $d_score !== null ? $d_score : 0 ) + ( $m_score !== null ? $m_score : 0 ) ) / ( ( $d_score !== null ? 1 : 0 ) + ( $m_score !== null ? 1 : 0 ) );
		}
		set_transient( 'vortem_insights_performance', $perf_data, 30 * MINUTE_IN_SECONDS );

		wp_send_json_success( $decoded_response );
	}

	/**
	 * AJAX handler for Insights refetch
	 */
	public function ajax_refetch_insights() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_insights_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			wp_send_json_error( array( 'message' => 'Data processing consent required.' ), 451 );
			return;
		}

		// Get API base URL and endpoint
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
		$api_base_url     = rtrim( Vortem_Config::get_primary_api_server(), '/' );
		$refetch_endpoint = Vortem_Config::get_api_endpoint( 'page_speed_wordpress_refetch' );
		$api_url          = $api_base_url . $refetch_endpoint;

		// Get real browser headers from user's request
		$browser_headers = $this->get_real_browser_headers();

		// Prepare headers with simple format
		$headers = array(
			'Content-Type'    => 'application/json',
			'Referer'         => home_url(),
			'User-Agent'      => $browser_headers['User-Agent'],
			'Accept'          => $browser_headers['Accept'],
			'Accept-Language' => $browser_headers['Accept-Language'],
			'Connection'      => $browser_headers['Connection'],
		);

		// Make API request with longer timeout for refetch
		$args = array(
			'method'      => 'GET',
			'headers'     => $headers,
			'timeout'     => 180, // Refetch can take longer, increase to 3 minutes
			'sslverify'   => true,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
		);

		$response = wp_remote_request( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = 'API request failed with status: ' . $status_code;
			$decoded_error = json_decode( $body, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
				if ( isset( $decoded_error['message'] ) ) {
					$error_message = $decoded_error['message'];
				} elseif ( isset( $decoded_error['error']['message'] ) ) {
					$error_message = $decoded_error['error']['message'];
				} elseif ( isset( $decoded_error['error'] ) ) {
					$error_message = is_array( $decoded_error['error'] ) ? $decoded_error['error']['message'] : $decoded_error['error'];
				}
			}
			wp_send_json_error(
				array(
					'message'     => $error_message,
					'status_code' => $status_code,
				)
			);
			return;
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to decode JSON response', 'vortem-ai' ) ) );
			return;
		}

		// Cache performance scores for overview dashboard (same logic as ajax_get_insights)
		$perf_data    = array(
			'desktop' => null,
			'mobile'  => null,
			'average' => null,
		);
		$desktop_data = isset( $decoded_response['desktop_data'] ) ? $decoded_response['desktop_data'] : null;
		$mobile_data  = isset( $decoded_response['mobile_data'] ) ? $decoded_response['mobile_data'] : null;
		$d_score      = null;
		$m_score      = null;
		if ( $desktop_data && isset( $desktop_data['lighthouseResult']['categories']['performance']['score'] ) ) {
			$d_score = floatval( $desktop_data['lighthouseResult']['categories']['performance']['score'] ) * 100;
		}
		if ( $mobile_data && isset( $mobile_data['lighthouseResult']['categories']['performance']['score'] ) ) {
			$m_score = floatval( $mobile_data['lighthouseResult']['categories']['performance']['score'] ) * 100;
		}
		$perf_data['desktop'] = $d_score !== null ? round( $d_score ) : null;
		$perf_data['mobile']  = $m_score !== null ? round( $m_score ) : null;
		if ( $d_score !== null || $m_score !== null ) {
			$perf_data['average'] = round( ( ( $d_score !== null ? $d_score : 0 ) + ( $m_score !== null ? $m_score : 0 ) ) / ( ( $d_score !== null ? 1 : 0 ) + ( $m_score !== null ? 1 : 0 ) ) );
		}
		set_transient( 'vortem_insights_performance', $perf_data, 30 * MINUTE_IN_SECONDS );

		wp_send_json_success( $decoded_response );
	}

	/**
	 * AJAX handler to get orders
	 */
	public function ajax_get_orders() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_orders_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce is not active' ) );
			return;
		}

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods below
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_orders_nonce() method
		$page      = Vortem_Security::validate_page( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1, 1 );
		$per_page  = Vortem_Security::validate_limit( isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : 20, 20, 1, 100 );
		$status    = Vortem_Security::validate_sync_status( isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : '', '' );
		$search    = Vortem_Security::validate_search( isset( $_POST['search'] ) ? wp_unslash( $_POST['search'] ) : '', '' );
		$date_from = Vortem_Security::validate_date( isset( $_POST['date_from'] ) ? wp_unslash( $_POST['date_from'] ) : '', '' );
		$date_to   = Vortem_Security::validate_date( isset( $_POST['date_to'] ) ? wp_unslash( $_POST['date_to'] ) : '', '' );
		$order     = Vortem_Security::validate_order( isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : 'ASC', 'ASC' );
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		// Build optimized query args
		$args = array(
			'orderby' => 'date',
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'return'  => 'ids',
		);

		// Add status filter
		if ( ! empty( $status ) && $status !== 'all' ) {
			$args['status'] = $status;
		}

		// Add date range filter - use proper date range when both dates are provided
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$date_from_timestamp = strtotime( $date_from . ' 00:00:00' );
			$date_to_timestamp   = strtotime( $date_to . ' 23:59:59' );
			if ( $date_from_timestamp !== false && $date_to_timestamp !== false ) {
				$args['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
			}
		} elseif ( ! empty( $date_from ) ) {
			$date_from_timestamp = strtotime( $date_from . ' 00:00:00' );
			if ( $date_from_timestamp !== false ) {
				$args['date_created'] = '>=' . $date_from_timestamp;
			}
		} elseif ( ! empty( $date_to ) ) {
			$date_to_timestamp = strtotime( $date_to . ' 23:59:59' );
			if ( $date_to_timestamp !== false ) {
				$args['date_created'] = '<=' . $date_to_timestamp;
			}
		}

		// Add search filter: use WooCommerce native search (`s`) which supports partial matches and works with HPOS/COT.
		// This searches order data indexes and common billing/shipping fields, plus numeric IDs.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Get orders with error handling
		try {
			$orders = wc_get_orders( $args );
			if ( ! is_array( $orders ) ) {
				$orders = array();
			}
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Error fetching orders: ', 'vortem-ai' ) . esc_html( $e->getMessage() ),
				)
			);
			return;
		}

		// Get total count using paginate parameter for efficient counting
		try {
			$count_args = $args;
			unset( $count_args['limit'] );
			unset( $count_args['offset'] );
			$count_args['limit']    = 1;
			$count_args['paginate'] = true;

			$orders_query = wc_get_orders( $count_args );
			$total_count  = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		} catch ( Exception $e ) {
			// Fallback: if paginate fails, use a simpler count
			try {
				$count_args = $args;
				unset( $count_args['limit'] );
				unset( $count_args['offset'] );
				$count_args['return'] = 'ids';
				$count_orders         = wc_get_orders( $count_args );
				$total_count          = is_array( $count_orders ) ? count( $count_orders ) : 0;
			} catch ( Exception $e2 ) {
				$total_count = 0;
			}
		}

		// Format orders - only load necessary data
		$formatted_orders = array();

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$formatted_orders[] = array(
				'id'                     => $order->get_id(),
				'order_number'           => $order->get_order_number(),
				'status'                 => $order->get_status(),
				'status_label'           => wc_get_order_status_name( $order->get_status() ),
				'date_created'           => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
				'date_created_formatted' => $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'total'                  => $order->get_total(),
				'total_formatted'        => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'currency'               => $order->get_currency(),
				'customer_name'          => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'customer_email'         => $order->get_billing_email(),
				'item_count'             => $order->get_item_count(),
				'payment_method'         => $order->get_payment_method_title(),
				'edit_url'               => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			);
		}

		wp_send_json_success(
			array(
				'orders'      => $formatted_orders,
				'total'       => $total_count,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_count / $per_page ),
			)
		);
	}

	/**
	 * AJAX handler to search orders
	 */
	public function ajax_search_orders() {
		// This is essentially the same as ajax_get_orders, but kept separate for clarity
		$this->ajax_get_orders();
	}

	/**
	 * AJAX handler to get order details
	 */
	public function ajax_get_order_details() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_orders_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce is not active' ) );
			return;
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;

		if ( empty( $order_id ) ) {
			wp_send_json_error( array( 'message' => 'Order ID is required' ) );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found' ) );
			return;
		}

		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'            => $item->get_name(),
				'quantity'        => $item->get_quantity(),
				'total'           => $item->get_total(),
				'total_formatted' => html_entity_decode( wp_strip_all_tags( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
				'sku'             => $product ? $product->get_sku() : '',
			);
		}

		$order_data = array(
			'id'                     => $order->get_id(),
			'order_number'           => $order->get_order_number(),
			'status'                 => $order->get_status(),
			'status_label'           => wc_get_order_status_name( $order->get_status() ),
			'date_created'           => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'date_created_formatted' => $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'total'                  => $order->get_total(),
			'total_formatted'        => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
			'currency'               => $order->get_currency(),
			'billing'                => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
			'shipping'               => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			),
			'payment_method'         => $order->get_payment_method_title(),
			'items'                  => $items,
			'item_count'             => $order->get_item_count(),
			'edit_url'               => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
		);

		wp_send_json_success( $order_data );
	}

	/**
	 * AJAX handler to send order to AliExpress
	 *
	 * @return void
	 */
	public function ajax_send_order_to_aliexpress() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_orders_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce is not active' ) );
			return;
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;

		if ( empty( $order_id ) ) {
			wp_send_json_error( array( 'message' => 'Order ID is required' ) );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found' ) );
			return;
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';

		// Get shipping address (prefer shipping, fallback to billing)
		$shipping_first_name = $order->get_shipping_first_name();
		$shipping_last_name  = $order->get_shipping_last_name();
		$shipping_address_1  = $order->get_shipping_address_1();
		$shipping_city       = $order->get_shipping_city();
		$shipping_state      = $order->get_shipping_state();
		$shipping_postcode   = $order->get_shipping_postcode();
		$shipping_country    = $order->get_shipping_country();

		// If shipping address is empty, use billing address
		if ( empty( $shipping_first_name ) && empty( $shipping_last_name ) ) {
			$shipping_first_name = $order->get_billing_first_name();
			$shipping_last_name  = $order->get_billing_last_name();
		}
		if ( empty( $shipping_address_1 ) ) {
			$shipping_address_1 = $order->get_billing_address_1();
		}
		if ( empty( $shipping_city ) ) {
			$shipping_city = $order->get_billing_city();
		}
		if ( empty( $shipping_state ) ) {
			$shipping_state = $order->get_billing_state();
		}
		if ( empty( $shipping_postcode ) ) {
			$shipping_postcode = $order->get_billing_postcode();
		}
		if ( empty( $shipping_country ) ) {
			$shipping_country = $order->get_billing_country();
		}

		// Validate required fields
		$full_name = trim( $shipping_first_name . ' ' . $shipping_last_name );
		if ( empty( $shipping_address_1 ) ) {
			wp_send_json_error( array( 'message' => 'Shipping address is required' ) );
			return;
		}
		if ( empty( $shipping_city ) ) {
			wp_send_json_error( array( 'message' => 'Shipping city is required' ) );
			return;
		}
		if ( empty( $shipping_country ) ) {
			wp_send_json_error( array( 'message' => 'Shipping country is required' ) );
			return;
		}
		if ( empty( $full_name ) ) {
			wp_send_json_error( array( 'message' => 'Full name is required' ) );
			return;
		}
		if ( empty( $shipping_state ) ) {
			wp_send_json_error( array( 'message' => 'Shipping province/state is required' ) );
			return;
		}
		if ( empty( $shipping_postcode ) ) {
			wp_send_json_error( array( 'message' => 'Shipping zip/postcode is required' ) );
			return;
		}

		// Normalize address and city (remove extra spaces and use standard format)
		$shipping_address_1 = trim( preg_replace( '/\s+/', ' ', $shipping_address_1 ) ); // Remove extra spaces
		$shipping_address_1 = preg_replace( '/\s*,\s*/', ', ', $shipping_address_1 ); // Normalize commas
		$shipping_city      = trim( $shipping_city );
		$shipping_city      = ucwords( strtolower( $shipping_city ) ); // Convert to standard format (e.g., "New York")

		// Optional fields
		$address2 = $order->get_shipping_address_2();
		if ( empty( $address2 ) ) {
			$address2 = $order->get_billing_address_2();
		}
		if ( ! empty( $address2 ) ) {
			$address2 = trim( preg_replace( '/\s+/', ' ', $address2 ) ); // Normalize address2 as well
		}

		$contact_person = $full_name;

		// Get phone number (prefer shipping, fallback to billing)
		$phone = $order->get_billing_phone();

		// Get country code mapping (ISO country code to phone country code)
		$country_phone_codes = array(
			'US' => '+1',
			'CA' => '+1', // United States and Canada
			'GB' => '+44',
			'UK' => '+44', // United Kingdom
			'AU' => '+61', // Australia
			'DE' => '+49', // Germany
			'FR' => '+33', // France
			'IT' => '+39', // Italy
			'ES' => '+34', // Spain
			'NL' => '+31', // Netherlands
			'BE' => '+32', // Belgium
			'CH' => '+41', // Switzerland
			'AT' => '+43', // Austria
			'SE' => '+46', // Sweden
			'NO' => '+47', // Norway
			'DK' => '+45', // Denmark
			'FI' => '+358', // Finland
			'PL' => '+48', // Poland
			'CZ' => '+420', // Czech Republic
			'GR' => '+30', // Greece
			'PT' => '+351', // Portugal
			'IE' => '+353', // Ireland
			'JP' => '+81', // Japan
			'CN' => '+86', // China
			'IN' => '+91', // India
			'KR' => '+82', // South Korea
			'BR' => '+55', // Brazil
			'MX' => '+52', // Mexico
			'AR' => '+54', // Argentina
			'CL' => '+56', // Chile
			'CO' => '+57', // Colombia
			'PE' => '+51', // Peru
			'ZA' => '+27', // South Africa
			'EG' => '+20', // Egypt
			'AE' => '+971', // UAE
			'SA' => '+966', // Saudi Arabia
			'IL' => '+972', // Israel
			'TR' => '+90', // Turkey
			'RU' => '+7', // Russia
			'UA' => '+380', // Ukraine
			'TH' => '+66', // Thailand
			'VN' => '+84', // Vietnam
			'ID' => '+62', // Indonesia
			'MY' => '+60', // Malaysia
			'SG' => '+65', // Singapore
			'PH' => '+63', // Philippines
			'NZ' => '+64', // New Zealand
		);

		// Parse phone number to extract mobile_no and phone_country
		$mobile_no     = '';
		$phone_country = '';

		// First, get phone_country from order country code (most reliable)
		if ( ! empty( $shipping_country ) && isset( $country_phone_codes[ $shipping_country ] ) ) {
			$phone_country = $country_phone_codes[ $shipping_country ];
		}

		if ( ! empty( $phone ) ) {
			// Remove all non-digit characters except + at the start
			$cleaned_phone = preg_replace( '/[^\d+]/', '', $phone );

			// Check if phone starts with +
			if ( strpos( $cleaned_phone, '+' ) === 0 ) {
				// If we have phone_country from country code, use it to extract mobile_no
				if ( ! empty( $phone_country ) ) {
					// Check if phone starts with our country code
					if ( strpos( $cleaned_phone, $phone_country ) === 0 ) {
						// Phone starts with our country code, remove it to get mobile_no
						$mobile_no = substr( $cleaned_phone, strlen( $phone_country ) );
					} else {
						// Phone doesn't match country code, try to extract from phone
						// Special handling for +1 (US/Canada) - always use single digit
						if ( strpos( $cleaned_phone, '+1' ) === 0 && strlen( $cleaned_phone ) > 3 ) {
							// For US/Canada, always use +1 (not +19, +191, etc.)
							$phone_country = '+1';
							$mobile_no     = substr( $cleaned_phone, 2 ); // Remove +1
						} else {
							// Try to match known country codes from longest to shortest
							$matched = false;
							for ( $len = 3; $len >= 1; $len-- ) {
								$test_code = '+' . substr( $cleaned_phone, 1, $len );
								if ( in_array( $test_code, $country_phone_codes, true ) ) {
									$phone_country = $test_code;
									$mobile_no     = substr( $cleaned_phone, 1 + $len );
									$matched       = true;
									break;
								}
							}
							if ( ! $matched ) {
								// Fallback: extract first 1-3 digits as country code
								if ( preg_match( '/^\+(\d{1,3})(.+)$/', $cleaned_phone, $matches ) ) {
									$phone_country = '+' . $matches[1];
									$mobile_no     = $matches[2];
								} else {
									$mobile_no = substr( $cleaned_phone, 1 );
								}
							}
						}
					}
				} else {
					// No country code from order, extract from phone
					// Special handling for +1 (US/Canada)
					if ( strpos( $cleaned_phone, '+1' ) === 0 && strlen( $cleaned_phone ) > 3 ) {
						$phone_country = '+1';
						$mobile_no     = substr( $cleaned_phone, 2 );
					} else {
						// Try to match known country codes from longest to shortest
						$matched = false;
						for ( $len = 3; $len >= 1; $len-- ) {
							$test_code = '+' . substr( $cleaned_phone, 1, $len );
							if ( in_array( $test_code, $country_phone_codes, true ) ) {
								$phone_country = $test_code;
								$mobile_no     = substr( $cleaned_phone, 1 + $len );
								$matched       = true;
								break;
							}
						}
						if ( ! $matched ) {
							// Fallback: extract first 1-3 digits as country code
							if ( preg_match( '/^\+(\d{1,3})(.+)$/', $cleaned_phone, $matches ) ) {
								$phone_country = '+' . $matches[1];
								$mobile_no     = $matches[2];
							} else {
								$mobile_no = substr( $cleaned_phone, 1 );
							}
						}
					}
				}
			} else {
				// No + prefix, use as mobile_no
				$mobile_no = $cleaned_phone;
				// If we have country code from order, use it
				if ( empty( $phone_country ) && ! empty( $shipping_country ) && isset( $country_phone_codes[ $shipping_country ] ) ) {
					$phone_country = $country_phone_codes[ $shipping_country ];
				}
			}
		} else {
			// No phone number, but if we have country, set phone_country
			if ( empty( $phone_country ) && ! empty( $shipping_country ) && isset( $country_phone_codes[ $shipping_country ] ) ) {
				$phone_country = $country_phone_codes[ $shipping_country ];
			}
		}

		// Validate mobile_no is required
		if ( empty( $mobile_no ) ) {
			wp_send_json_error( array( 'message' => 'Mobile number is required' ) );
			return;
		}

		// Get locale (optional)
		$locale = get_locale();
		if ( empty( $locale ) || strlen( $locale ) < 2 ) {
			$locale = '';
		}

		// Convert state code to full state name (e.g., NY -> New York)
		$province_name = $shipping_state;
		if ( ! empty( $shipping_state ) && ! empty( $shipping_country ) ) {
			if ( function_exists( 'WC' ) && WC()->countries ) {
				$states = WC()->countries->get_states( $shipping_country );
				if ( ! empty( $states ) && isset( $states[ $shipping_state ] ) ) {
					// State code found, use full name
					$province_name = $states[ $shipping_state ];
				}
				// If state code not found, use original value (might already be full name)
			}
		}

		// Build logistics address with required fields
		$logistics_address = array(
			'address'        => sanitize_text_field( $shipping_address_1 ),
			'city'           => sanitize_text_field( $shipping_city ),
			'country'        => sanitize_text_field( $shipping_country ),
			'full_name'      => sanitize_text_field( $full_name ),
			'contact_person' => sanitize_text_field( $contact_person ),
			'province'       => sanitize_text_field( $province_name ), // Use full state name, not code
			'zip'            => sanitize_text_field( $shipping_postcode ),
			'mobile_no'      => sanitize_text_field( $mobile_no ), // Required field
		);

		// Add phone_country if available (should match curl example format)
		// phone_country should always be included if we have country code
		if ( ! empty( $phone_country ) ) {
			$logistics_address['phone_country'] = sanitize_text_field( $phone_country );
		} elseif ( ! empty( $shipping_country ) && isset( $country_phone_codes[ $shipping_country ] ) ) {
			// Fallback: use country code mapping if phone_country not set from phone parsing
			$logistics_address['phone_country'] = $country_phone_codes[ $shipping_country ];
		}

		// Add optional fields only if they have values
		if ( ! empty( $address2 ) ) {
			$logistics_address['address2'] = sanitize_text_field( $address2 );
		}
		// Note: locale is NOT sent to API (removed per requirements)

		// Get product items
		$product_items = array();
		$default_memo  = 'Please handle with care';

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Get SKU directly from WooCommerce product
			$sku = $product->get_sku();

			if ( empty( $sku ) ) {
				// Skip products without SKU
				continue;
			}

			// Get order memo from item meta, order meta, customer note, or use default
			$order_memo = '';

			// Check item meta first
			$item_meta = $item->get_meta_data();
			foreach ( $item_meta as $meta ) {
				if ( strtolower( $meta->key ) === 'order_memo' || strtolower( $meta->key ) === 'memo' ) {
					$order_memo = trim( $meta->value );
					break;
				}
			}

			// If no memo found in item meta, check order meta
			if ( empty( $order_memo ) ) {
				$order_memo_meta = $order->get_meta( 'order_memo' );
				if ( ! empty( $order_memo_meta ) ) {
					$order_memo = trim( $order_memo_meta );
				}
			}

			// If still no memo, check customer note
			if ( empty( $order_memo ) ) {
				$customer_note = $order->get_customer_note();
				if ( ! empty( $customer_note ) ) {
					$order_memo = trim( $customer_note );
				}
			}

			// Use default if still empty
			if ( empty( $order_memo ) ) {
				$order_memo = $default_memo;
			}

			$product_quantity = intval( $item->get_quantity() );
			if ( $product_quantity < 1 ) {
				continue;
			}

			$product_item = array(
				'skuid'         => sanitize_text_field( $sku ),
				'product_count' => $product_quantity,
				'product_type'  => 'top',
			);

			// Add order_memo only if it's not empty (though we always have default)
			if ( ! empty( $order_memo ) ) {
				$product_item['order_memo'] = sanitize_text_field( $order_memo );
			}

			$product_items[] = $product_item;
		}

		if ( empty( $product_items ) ) {
			wp_send_json_error( array( 'message' => 'No valid products with SKU found in order' ) );
			return;
		}

		// Generate out_order_id in format: ORDER-YYYY-MM-DD-HH-MM-SS
		$order_date = $order->get_date_created();
		if ( ! $order_date ) {
			$order_date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		}
		$out_order_id = 'ORDER-' . $order_date->format( 'Y-m-d-H-i-s' );

		// Build request payload
		$request_data = array(
			'out_order_id'      => $out_order_id,
			'logistics_address' => $logistics_address,
			'product_items'     => $product_items,
		);

		// Send to API
		$api_client = new Vortem_Api_Client();
		$response   = $api_client->send_order_to_aliexpress( $request_data );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message'    => $response->get_error_message(),
					'error_code' => $response->get_error_code(),
				)
			);
			return;
		}

		// Response is already decoded as array from make_request
		$response_data = $response;

		// Check if response indicates success
		if ( isset( $response_data['success'] ) && $response_data['success'] ) {
			$message = isset( $response_data['message'] ) ? $response_data['message'] : 'Order sent to AliExpress successfully';
			if ( isset( $response_data['data']['message'] ) ) {
				$message = $response_data['data']['message'];
			}
			wp_send_json_success(
				array(
					'message' => $message,
					'data'    => $response_data,
				)
			);
		} else {
			$error_message = isset( $response_data['message'] ) ? $response_data['message'] : 'Failed to send order to AliExpress';
			if ( isset( $response_data['data']['message'] ) ) {
				$error_message = $response_data['data']['message'];
			}
			if ( isset( $response_data['data']['response']['result']['error_msg'] ) && ! empty( $response_data['data']['response']['result']['error_msg'] ) ) {
				$error_message = $response_data['data']['response']['result']['error_msg'];
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}

	/**
	 * AJAX handler to get AliExpress OAuth authorization URL
	 */
	public function ajax_get_aliexpress_auth_url() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		// Get backend API URL
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
		$api_server = Vortem_Config::get_primary_api_server();
		$endpoint   = Vortem_Config::get_api_endpoint( 'aliexpress_auth_authorize' );

		if ( empty( $endpoint ) ) {
			wp_send_json_error(
				array(
					'message' => 'AliExpress auth endpoint not configured',
				)
			);
		}

		// Build redirect URL to backend with site URL as query parameter
		$redirect_url = rtrim( $api_server, '/' ) . $endpoint . '?referer=' . rawurlencode( home_url() );

		wp_send_json_success(
			array(
				'auth_url' => $redirect_url,
			)
		);
	}

	/**
	 * AJAX handler to get AliExpress authentication status
	 */
	public function ajax_get_aliexpress_auth_status() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		$api_client = new Vortem_Api_Client();
		$response   = $api_client->get_aliexpress_auth_status();

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
				)
			);
		}

		if ( isset( $response['success'] ) && $response['success'] ) {
			// Backend uses 'authenticated' field, but we map it to 'connected' for consistency
			$authenticated = isset( $response['authenticated'] ) ? $response['authenticated'] : false;
			$connected     = isset( $response['connected'] ) ? $response['connected'] : $authenticated;

			// Extract token_info if available
			$token_info           = isset( $response['data']['token_info'] ) ? $response['data']['token_info'] : array();
			$token_status         = isset( $token_info['status'] ) ? $token_info['status'] : '';
			$expires_at_formatted = isset( $token_info['expires_at_formatted'] ) ? $token_info['expires_at_formatted'] : '';

			wp_send_json_success(
				array(
					'connected'            => $connected,
					'account'              => isset( $response['data']['account'] ) ? $response['data']['account'] : ( isset( $response['account'] ) ? $response['account'] : '' ),
					'user_id'              => isset( $response['data']['user_id'] ) ? $response['data']['user_id'] : ( isset( $response['user_id'] ) ? $response['user_id'] : '' ),
					'token_status'         => $token_status,
					'expires_at_formatted' => $expires_at_formatted,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'connected' => false,
				)
			);
		}
	}

	/**
	 * AJAX handler to disconnect AliExpress account
	 */
	public function ajax_disconnect_aliexpress() {
		check_ajax_referer( 'vortem_nonce', 'nonce' );

		$api_client = new Vortem_Api_Client();
		$response   = $api_client->delete_aliexpress_auth();

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => $response->get_error_message(),
				)
			);
		}

		if ( isset( $response['success'] ) && $response['success'] ) {
			wp_send_json_success(
				array(
					'message' => isset( $response['message'] ) ? $response['message'] : 'AliExpress account disconnected successfully',
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => isset( $response['message'] ) ? $response['message'] : 'Failed to disconnect AliExpress account',
				)
			);
		}
	}

	/**
	 * AJAX handler for getting imported products count
	 */
	public function ajax_get_imported_products_count() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		$count = $this->get_imported_products_count();

		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * AJAX handler for fetching sentiment data
	 *
	 * @return void
	 */
	public function ajax_get_sentiment_data() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			wp_send_json_error( array( 'message' => 'Data processing consent required.' ), 451 );
			return;
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

		$endpoint   = Vortem_Config::get_api_endpoint( 'bi_analytics_charts_sentiment' );
		$api_server = Vortem_Config::get_primary_api_server();
		$url        = rtrim( $api_server, '/' ) . $endpoint;

		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		$args = array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => true,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 200 && $response_code < 300 ) {
			$decoded_response = json_decode( $response_body, true );
			wp_send_json_success( $decoded_response );
		} else {
			wp_send_json_error(
				array(
					'message' => 'Failed to fetch sentiment data',
					'code'    => $response_code,
				)
			);
		}
	}

	/**
	 * AJAX handler for getting currency codes from API
	 */
	public function ajax_get_currency_codes() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';

		$api_client = new Vortem_Api_Client();

		// Fetch currency codes from API
		$currency_response = $api_client->fetch_currency_codes_public();

		if ( is_wp_error( $currency_response ) ) {
			wp_send_json_error(
				array(
					'message'    => $currency_response->get_error_message(),
					'error_code' => $currency_response->get_error_code(),
				)
			);
			return;
		}

		// Extract data field from API response if it exists
		$currency_codes = is_array( $currency_response ) && isset( $currency_response['data'] )
			? $currency_response['data']
			: $currency_response;

		wp_send_json_success( $currency_codes );
	}

	/**
	 * AJAX handler for getting current currency from API
	 */
	public function ajax_get_current_currency() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';

		$api_client       = new Vortem_Api_Client();
		$current_currency = $api_client->fetch_current_currency();

		if ( is_wp_error( $current_currency ) ) {
			wp_send_json_error(
				array(
					'message'    => $current_currency->get_error_message(),
					'error_code' => $current_currency->get_error_code(),
				)
			);
			return;
		}

		wp_send_json_success( $current_currency );
	}

	/**
	 * AJAX handler for updating currency on API
	 */
	public function ajax_update_currency() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'vortem_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			return;
		}

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Get currency code from POST data
		if ( ! isset( $_POST['currency_code'] ) || empty( $_POST['currency_code'] ) ) {
			wp_send_json_error( array( 'message' => 'Currency code is required' ) );
			return;
		}

		// Sanitize and get only currency code (e.g., USD, AFN, GBP)
		$currency_code = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency_code'] ) ) );

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';

		$api_client = new Vortem_Api_Client();
		$result     = $api_client->update_currency( $currency_code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				)
			);
			return;
		}

		// Also update local options. The plugin's currency is stored in
		// `vortem_currency`; the WooCommerce store currency is left unchanged
		// so the merchant remains in full control.
		update_option( 'vortem_currency', $currency_code );
		update_option( 'vortem_customer_currency', $currency_code );

		wp_send_json_success( $result );
	}

	/**
	 * Get real browser headers from user's request
	 * This method extracts actual browser headers to make API calls more authentic
	 *
	 * @return array Array of headers extracted from user's browser
	 */
	private function get_real_browser_headers() {
		$headers = array();

		// Get User-Agent from browser
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$headers['User-Agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		} else {
			// Fallback to default WordPress user agent
			$headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		}

		// Get Accept-Language from browser
		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$headers['Accept-Language'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
		} else {
			$headers['Accept-Language'] = 'en-US,en;q=0.9';
		}

		// Get Accept from browser
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$headers['Accept'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
		} else {
			$headers['Accept'] = 'application/json, text/plain, */*';
		}

		// Connection header - always keep-alive for performance
		$headers['Connection'] = 'keep-alive';

		return $headers;
	}

	/**
	 * Get supported currencies
	 *
	 * @return array Array of supported currencies with code, name, and symbol
	 */
	private function get_supported_currencies() {
		return array(
			'USD' => array(
				'name'   => 'US Dollar',
				'symbol' => '$',
			),
			'EUR' => array(
				'name'   => 'Euro',
				'symbol' => '€',
			),
			'GBP' => array(
				'name'   => 'British Pound',
				'symbol' => '£',
			),
			'JPY' => array(
				'name'   => 'Japanese Yen',
				'symbol' => '¥',
			),
			'CNY' => array(
				'name'   => 'Chinese Yuan',
				'symbol' => '¥',
			),
			'AUD' => array(
				'name'   => 'Australian Dollar',
				'symbol' => 'A$',
			),
			'CAD' => array(
				'name'   => 'Canadian Dollar',
				'symbol' => 'C$',
			),
			'CHF' => array(
				'name'   => 'Swiss Franc',
				'symbol' => 'CHF',
			),
			'INR' => array(
				'name'   => 'Indian Rupee',
				'symbol' => '₹',
			),
			'AED' => array(
				'name'   => 'UAE Dirham',
				'symbol' => 'د.إ',
			),
			'SAR' => array(
				'name'   => 'Saudi Riyal',
				'symbol' => '﷼',
			),
			'BRL' => array(
				'name'   => 'Brazilian Real',
				'symbol' => 'R$',
			),
			'MXN' => array(
				'name'   => 'Mexican Peso',
				'symbol' => '$',
			),
			'RUB' => array(
				'name'   => 'Russian Ruble',
				'symbol' => '₽',
			),
			'ZAR' => array(
				'name'   => 'South African Rand',
				'symbol' => 'R',
			),
			'KRW' => array(
				'name'   => 'South Korean Won',
				'symbol' => '₩',
			),
			'SGD' => array(
				'name'   => 'Singapore Dollar',
				'symbol' => 'S$',
			),
			'HKD' => array(
				'name'   => 'Hong Kong Dollar',
				'symbol' => 'HK$',
			),
			'NZD' => array(
				'name'   => 'New Zealand Dollar',
				'symbol' => 'NZ$',
			),
			'SEK' => array(
				'name'   => 'Swedish Krona',
				'symbol' => 'kr',
			),
			'NOK' => array(
				'name'   => 'Norwegian Krone',
				'symbol' => 'kr',
			),
			'DKK' => array(
				'name'   => 'Danish Krone',
				'symbol' => 'kr',
			),
			'PLN' => array(
				'name'   => 'Polish Zloty',
				'symbol' => 'zł',
			),
			'TRY' => array(
				'name'   => 'Turkish Lira',
				'symbol' => '₺',
			),
			'THB' => array(
				'name'   => 'Thai Baht',
				'symbol' => '฿',
			),
			'IDR' => array(
				'name'   => 'Indonesian Rupiah',
				'symbol' => 'Rp',
			),
			'MYR' => array(
				'name'   => 'Malaysian Ringgit',
				'symbol' => 'RM',
			),
			'PHP' => array(
				'name'   => 'Philippine Peso',
				'symbol' => '₱',
			),
			'VND' => array(
				'name'   => 'Vietnamese Dong',
				'symbol' => '₫',
			),
		);
	}

	/**
	 * Display product video status in WooCommerce product edit page
	 *
	 * Shows the video attachment or message if no video is available
	 */
	public function display_product_video_status() {
		global $post;

		if ( ! $post || $post->post_type !== 'product' ) {
			return;
		}

		$product_id = $post->ID;

		// Get video status using the product creator class
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-creator.php';
		$video_status = Vortem_Product_Creator::get_product_video_status( $product_id );

		echo '<div class="options_group">';
		echo '<p class="form-field">';
		echo '<label>' . esc_html__( 'Product Video', 'vortem-ai' ) . '</label>';

		if ( $video_status['has_video'] && $video_status['video_attachment_id'] ) {
			// Video is available
			$video_url = wp_get_attachment_url( $video_status['video_attachment_id'] );
			if ( $video_url ) {
				echo '<span class="description">';
				echo esc_html__( 'Video attached successfully. ', 'vortem-ai' );
				echo '<a href="' . esc_url( $video_url ) . '" target="_blank">' . esc_html__( 'View Video', 'vortem-ai' ) . '</a>';
				echo ' | ';
				echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $video_status['video_attachment_id'] . '&action=edit' ) ) . '">' . esc_html__( 'Edit Video', 'vortem-ai' ) . '</a>';
				echo '</span>';
			}
		} elseif ( ! empty( $video_status['message'] ) ) {
			// No video - display message
			echo '<span class="description" style="color: #666; font-style: italic;">';
			echo esc_html( $video_status['message'] );
			echo '</span>';
		} else {
			// No video status stored
			echo '<span class="description" style="color: #666; font-style: italic;">';
			echo esc_html__( 'AliExpress does not have a video for this product.', 'vortem-ai' );
			echo '</span>';
		}

		echo '</p>';
		echo '</div>';
	}

	/**
	 * Helper method to translate strings that contain SVG markup
	 * Extracts the SVG part, translates the text part, and combines them
	 *
	 * @param string $full_string The full string with SVG and text
	 * @param string $translation_key The translation key to use
	 * @return string Translated string with SVG preserved
	 */
	private function get_translated_string_with_svg( $full_string, $translation_key ) {
		// Find the position of </svg> tag
		$svg_end_pos = strpos( $full_string, '</svg>' );

		if ( $svg_end_pos === false ) {
			// No SVG found, translate the whole string
			if ( class_exists( 'Vortem_Translation_Manager' ) ) {
				return Vortem_Translation_Manager::translate( $translation_key, $full_string );
			}
			return $full_string;
		}

		// Extract SVG part (including </svg>)
		$svg_part  = substr( $full_string, 0, $svg_end_pos + 6 ); // +6 for '</svg>'
		$text_part = trim( substr( $full_string, $svg_end_pos + 6 ) );

		// Get translation for the text part
		$translated_text = $text_part;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$translated_text = Vortem_Translation_Manager::translate( $translation_key, $text_part );
		}

		// Combine SVG and translated text
		return $svg_part . ' ' . $translated_text;
	}

    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
}

