<?php
/**
 * Vortem Analytics Class
 *
 * Handles analytics and metrics functionality for Analytics section
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Analytics
 */
class Vortem_Analytics {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from $wpdb->prefix; heavy reads cached via transients where appropriate.

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register REST API routes
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | Declares HPOS compatibility and hooks into WooCommerce order/product events
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

		// External Dependency: WooCommerce - Hooks into order lifecycle events for cache invalidation
		add_action( 'woocommerce_new_order', array( $this, 'clear_order_cache' ), 10 );
		add_action( 'woocommerce_update_order', array( $this, 'clear_order_cache' ), 10 );
		add_action( 'woocommerce_delete_order', array( $this, 'clear_order_cache' ), 10 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'clear_order_cache' ), 10 );

		// External Dependency: WooCommerce - Hooks into product update events for cache invalidation
		add_action( 'woocommerce_update_product', array( $this, 'clear_product_cache' ), 10 );
		add_action( 'woocommerce_new_product', array( $this, 'clear_product_cache' ), 10 );
	}

	/**
	 * Declare WooCommerce compatibility.
	 */
	public function declare_woocommerce_compatibility() {
		// External Dependency: WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+
		// Declares compatibility with HPOS and with the block-based Cart/Checkout.
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', VORTEM_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', VORTEM_PLUGIN_FILE, true );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'vortem/v1',
			'/metrics/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_metrics' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/export/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_export_csv' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);

		// Overview dashboard chart endpoints
		register_rest_route(
			'vortem/v1',
			'/overview/revenue-daily',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_revenue_daily' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(
					'days' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return $param >= 1 && $param <= 90;
						},
					),
				),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/overview/products-sparkline',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_products_sparkline' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/overview/sentiment',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_sentiment' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/overview/security-vulns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_security_vulns' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/overview/insights-performance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_insights_performance' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'vortem/v1',
			'/overview/emails-total',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_overview_emails_total' ),
				'permission_callback' => array( $this, 'rest_permission' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * REST API permission callback.
	 */
	public function rest_permission( $request ) {
		return vortem_current_user_can_manage();
	}

	/**
	 * REST API endpoint to get all metrics.
	 */
	public function rest_metrics( $request ) {
		// Try to get cached data first (5 minutes cache) - v2 includes orders_processing/orders_completed
		$cache_key = 'vortem_analytics_metrics_v2_' . get_current_user_id();
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			// Ensure orders breakdown exists (backfill for older cache formats)
			if ( ! isset( $cached['woocommerce']['orders_processing'] ) || ! isset( $cached['woocommerce']['orders_completed'] ) ) {
				$cached['woocommerce']['orders_processing'] = $this->get_orders_processing();
				$cached['woocommerce']['orders_completed']  = $this->get_orders_total();
			}
			// Always recalculate products_count to use imported products count (not cached)
			if ( function_exists( 'vortem_get_imported_products_count' ) ) {
				$cached['woocommerce']['products_count'] = vortem_get_imported_products_count();
			}
			// Remove comments_total from cached data if it exists (for backward compatibility)
			if ( isset( $cached['wordpress']['comments_total'] ) ) {
				unset( $cached['wordpress']['comments_total'] );
			}
			return rest_ensure_response( $cached );
		}

		// Collect all metrics organized by category
		$metrics = array(
			'woocommerce' => array(
				'orders_total'      => $this->get_orders_total(),
				'orders_processing' => $this->get_orders_processing(),
				'orders_completed'  => $this->get_orders_total(),
				'orders_today'      => $this->get_orders_today(),
				'revenue_total'     => $this->get_revenue_total(),
				'revenue_today'     => $this->get_revenue_today(),
				'revenue_last_30d'  => $this->get_revenue_last_30d(),
				'avg_order_value'   => $this->get_avg_order_value(),
				'low_stock_count'   => $this->get_low_stock_count(),
				'pending_orders'    => $this->get_pending_orders(),
				'failed_orders'     => $this->get_failed_orders(),
				'refunded_orders'   => $this->get_on_hold_orders(),
				'products_count'    => function_exists( 'vortem_get_imported_products_count' ) ? vortem_get_imported_products_count() : $this->get_products_count(),
				'cart_abandonment'  => $this->get_cart_abandonment(),
			),
			'wordpress'   => array(
				'users_total' => $this->get_users_total(),
				'users_today' => $this->get_users_today(),
				'posts_total' => $this->get_posts_total(),
				'pages_total' => $this->get_pages_total(),
			),
		);

		// Remove comments_total if it exists (for backward compatibility with cached data)
		if ( isset( $metrics['wordpress']['comments_total'] ) ) {
			unset( $metrics['wordpress']['comments_total'] );
		}

		// Cache for 5 minutes
		set_transient( $cache_key, $metrics, 5 * MINUTE_IN_SECONDS );

		$response = rest_ensure_response( $metrics );
		$response->header( 'Cache-Control', 'max-age=30' );

		return $response;
	}

	/**
	 * REST API endpoint to export metrics as CSV.
	 */
	public function rest_export_csv( $request ) {
		// Get metrics
		$cache_key = 'vortem_analytics_metrics_' . get_current_user_id();
		$metrics   = get_transient( $cache_key );
		if ( false === $metrics ) {
			$metrics = array(
				'woocommerce' => array(
					'orders_total'     => $this->get_orders_total(),
					'orders_today'     => $this->get_orders_today(),
					'revenue_total'    => $this->get_revenue_total(),
					'revenue_today'    => $this->get_revenue_today(),
					'revenue_last_30d' => $this->get_revenue_last_30d(),
					'avg_order_value'  => $this->get_avg_order_value(),
					'low_stock_count'  => $this->get_low_stock_count(),
					'pending_orders'   => $this->get_pending_orders(),
					'failed_orders'    => $this->get_failed_orders(),
					'refunded_orders'  => $this->get_on_hold_orders(),
					'products_count'   => function_exists( 'vortem_get_imported_products_count' ) ? vortem_get_imported_products_count() : $this->get_products_count(),
					'cart_abandonment' => $this->get_cart_abandonment(),
				),
				'wordpress'   => array(
					'users_total' => $this->get_users_total(),
					'users_today' => $this->get_users_today(),
					'posts_total' => $this->get_posts_total(),
					'pages_total' => $this->get_pages_total(),
				),
			);
		}

		// Convert to CSV
		$csv_output = "Category,Metric,Value\n";
		foreach ( $metrics as $category => $category_metrics ) {
			foreach ( $category_metrics as $key => $value ) {
				$label = str_replace( '_', ' ', ucwords( $key, '_' ) );
				if ( is_array( $value ) ) {
					$value = isset( $value['title'] ) ? $value['title'] . ' (' . $value['qty'] . ')' : implode( ', ', $value );
				}
				$csv_output .= $this->esc_csv( ucfirst( $category ) ) . ',' . $this->esc_csv( $label ) . ',' . $this->esc_csv( $value ) . "\n";
			}
		}

		$response = rest_ensure_response( $csv_output );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="vortem-analytics-export-' . gmdate( 'Y-m-d' ) . '.csv"' );

		return $response;
	}

	/**
	 * Helper function to escape CSV values.
	 */
	private function esc_csv( $value ) {
		$value = (string) $value;
		if ( false !== strpos( $value, ',' ) || false !== strpos( $value, '"' ) || false !== strpos( $value, "\n" ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * REST API: Daily revenue for last N days (Overview Chart 1).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_revenue_daily( $request ) {
		$days = $request->get_param( 'days' );
		$days = absint( $days );
		if ( $days < 1 || $days > 90 ) {
			$days = 30;
		}

		$cache_key = 'vortem_overview_revenue_daily_' . $days . '_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$series  = $this->get_daily_revenue_data( $days );
		$total   = $this->get_revenue_total();
		$payload = array(
			'total'  => $total,
			'series' => $series,
		);
		set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $payload );
	}

	/**
	 * Get daily revenue/orders for chart (last N days).
	 *
	 * @param int $days Number of days.
	 * @return array Array of { day, value } objects.
	 */
	private function get_daily_revenue_data( $days ) {
		global $wpdb;

		if ( ! function_exists( 'WC' ) ) {
			return $this->empty_daily_chart_data( $days );
		}

		$orders_table = $wpdb->prefix . 'wc_orders';
		$using_hpos   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table;

		$start      = strtotime( '-' . $days . ' days' );
		$start_date = gmdate( 'Y-m-d', $start );

		if ( $using_hpos ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(date_created_gmt) as order_date, SUM(total_amount) as total
                    FROM {$orders_table}
                    WHERE status = %s AND type = %s
                    AND date_created_gmt >= %s
                    GROUP BY DATE(date_created_gmt)
                    ORDER BY order_date ASC",
					'wc-completed',
					'shop_order',
					$start_date . ' 00:00:00'
				),
				ARRAY_A
			);
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from trusted $wpdb
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(p.post_date) as order_date, SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = %s AND p.post_status = %s
                    AND DATE(p.post_date) >= %s AND pm.meta_key = %s
                    GROUP BY DATE(p.post_date)
                    ORDER BY order_date ASC",
					'shop_order',
					'wc-completed',
					$start_date,
					'_order_total'
				),
				ARRAY_A
			);
		}

		$by_date = array();
		foreach ( $results as $row ) {
			$by_date[ $row['order_date'] ] = floatval( $row['total'] );
		}

		$data = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$d      = gmdate( 'Y-m-d', strtotime( "+{$i} days", $start ) );
			$label  = ( $i + 1 ) <= $days ? 'Day ' . ( $i + 1 ) : gmdate( 'M j', strtotime( $d ) );
			$data[] = array(
				'day'   => $label,
				'value' => isset( $by_date[ $d ] ) ? round( $by_date[ $d ], 2 ) : 0,
			);
		}

		return $data;
	}

	/**
	 * Empty chart data when WooCommerce not available.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	private function empty_daily_chart_data( $days ) {
		$data = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$data[] = array(
				'day'   => 'Day ' . ( $i + 1 ),
				'value' => 0,
			);
		}
		return $data;
	}

	/**
	 * REST API: Products sparkline data (Overview Chart 2).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_products_sparkline( $request ) {
		$cache_key = 'vortem_overview_products_sparkline_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		global $wpdb;
		$products_table = $wpdb->prefix . 'vortem_products';
		$days           = 12;

		// Try to get products count by created_at for last N days from vortem_products
		$start      = strtotime( '-' . $days . ' days' );
		$start_date = gmdate( 'Y-m-d', $start );

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $products_table ) ) === $products_table;

		if ( $table_exists ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) as d, COUNT(*) as c
                    FROM {$products_table}
                    WHERE created_at >= %s
                    GROUP BY DATE(created_at)
                    ORDER BY d ASC",
					$start_date . ' 00:00:00'
				),
				ARRAY_A
			);
		} else {
			$results = array();
		}

		$by_date = array();
		foreach ( $results as $row ) {
			$by_date[ $row['d'] ] = absint( $row['c'] );
		}

		$sparkline = array();
		$wc_count  = $this->get_products_count();
		for ( $i = 0; $i < $days; $i++ ) {
			$d           = gmdate( 'Y-m-d', strtotime( "+{$i} days", $start ) );
			$cnt         = isset( $by_date[ $d ] ) ? $by_date[ $d ] : 0;
			$sparkline[] = array( 'value' => $cnt );
		}

		$imported = function_exists( 'vortem_get_imported_products_count' ) ? vortem_get_imported_products_count() : 0;

		$response = array(
			'value'          => $wc_count,
			'value_imported' => $imported,
			'sparkline'      => $sparkline,
		);
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response );
	}

	/**
	 * REST API: Sentiment distribution (Overview Chart 3).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_sentiment( $request ) {
		$cache_key = 'vortem_overview_sentiment_' . get_current_user_id();
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		global $wpdb;
		$products_table = $wpdb->prefix . 'vortem_products';
		$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $products_table ) ) === $products_table;

		$positive = 0;
		$neutral  = 0;
		$negative = 0;

		if ( $table_exists ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_data FROM {$wpdb->prefix}vortem_products WHERE meta_data IS NOT NULL AND meta_data != %s",
					''
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$meta = json_decode( $row['meta_data'], true );
				if ( ! is_array( $meta ) || empty( $meta['sentiment_summary'] ) ) {
					continue;
				}
				$s         = $meta['sentiment_summary'];
				$positive += isset( $s['positive'] ) ? floatval( $s['positive'] ) : 0;
				$neutral  += isset( $s['neutral'] ) ? floatval( $s['neutral'] ) : 0;
				$negative += isset( $s['negative'] ) ? floatval( $s['negative'] ) : 0;
			}
		}

		$total = $positive + $neutral + $negative;
		if ( $total > 0 ) {
			$positive = round( 100 * $positive / $total, 1 );
			$neutral  = round( 100 * $neutral / $total, 1 );
			$negative = round( 100 * $negative / $total, 1 );
		} else {
			$positive = 0;
			$neutral  = 0;
			$negative = 0;
		}

		$data = array(
			'positive' => $positive,
			'neutral'  => $neutral,
			'negative' => $negative,
		);
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $data );
	}

	/**
	 * REST API: Security total vulnerabilities (cached from security page scan).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_security_vulns( $request ) {
		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return rest_ensure_response( array( 'total' => 0 ) );
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

		$api_server          = Vortem_Config::get_primary_api_server();
		$all_vulnerabilities = array();

		// Prepare headers
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

		// Fetch plugin vulnerabilities
		$plugin_api_url  = Vortem_Config::build_api_url( $api_server, 'security_wordpress_match' );
		$plugin_response = wp_remote_get( $plugin_api_url, $args );

		if ( ! is_wp_error( $plugin_response ) ) {
			$plugin_response_code = wp_remote_retrieve_response_code( $plugin_response );
			if ( $plugin_response_code >= 200 && $plugin_response_code < 300 ) {
				$plugin_response_body = wp_remote_retrieve_body( $plugin_response );
				$plugin_decoded       = json_decode( $plugin_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $plugin_decoded ) ) {
					foreach ( $plugin_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( is_array( $item ) ) {
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
			if ( $theme_response_code >= 200 && $theme_response_code < 300 ) {
				$theme_response_body = wp_remote_retrieve_body( $theme_response );
				$theme_decoded       = json_decode( $theme_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $theme_decoded ) ) {
					foreach ( $theme_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( is_array( $item ) ) {
							$all_vulnerabilities[] = $item;
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
			if ( $wp_core_response_code >= 200 && $wp_core_response_code < 300 ) {
				$wp_core_response_body = wp_remote_retrieve_body( $wp_core_response );
				$wp_core_decoded       = json_decode( $wp_core_response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $wp_core_decoded ) ) {
					foreach ( $wp_core_decoded as $item ) {
						if ( isset( $item['message'] ) && $item['message'] === 'success' ) {
							continue;
						}
						if ( is_array( $item ) ) {
							$all_vulnerabilities[] = $item;
						}
					}
				}
			}
		}

		$total = count( $all_vulnerabilities );
		return rest_ensure_response( array( 'total' => absint( $total ) ) );
	}

	/**
	 * REST API: Insights performance score (fetched directly from API).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_insights_performance( $request ) {
		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return rest_ensure_response(
				array(
					'desktop' => null,
					'mobile'  => null,
					'average' => null,
				)
			);
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

		// Get API base URL
		$api_base_url = rtrim( Vortem_Config::get_primary_api_server(), '/' );
		$endpoint     = Vortem_Config::get_api_endpoint( 'page_speed_wordpress' );
		$url          = get_site_url();
		$api_url      = $api_base_url . $endpoint . '?url=' . rawurlencode( $url ) . '&locale=en';

		// Prepare headers
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		// Make API request
		$args = array(
			'method'    => 'GET',
			'headers'   => $headers,
			'timeout'   => 60,
			'sslverify' => true,
		);

		$response = wp_remote_request( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response(
				array(
					'desktop' => null,
					'mobile'  => null,
					'average' => null,
				)
			);
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			return rest_ensure_response(
				array(
					'desktop' => null,
					'mobile'  => null,
					'average' => null,
				)
			);
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded_response ) ) {
			return rest_ensure_response(
				array(
					'desktop' => null,
					'mobile'  => null,
					'average' => null,
				)
			);
		}

		// Extract performance scores
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

		return rest_ensure_response( $perf_data );
	}

	/**
	 * REST API: Emails total (sent + list count).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_overview_emails_total( $request ) {
		$cache_key = 'vortem_overview_emails_total_' . get_current_user_id();
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$total_sent  = 0;
		$total_lists = 0;
		try {
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-email-marketing-api.php';
			$api  = new Vortem_Email_Marketing_Api();
			$resp = $api->get_email_lists();
			if ( is_wp_error( $resp ) ) {
				$resp = array();
			}
			$lists = array();
			if ( isset( $resp['data'] ) && is_array( $resp['data'] ) ) {
				$lists = $resp['data'];
			} elseif ( isset( $resp['email_lists'] ) && is_array( $resp['email_lists'] ) ) {
				$lists = $resp['email_lists'];
			} elseif ( is_array( $resp ) ) {
				$lists = $resp;
			}
			$total_lists = count( $lists );
			foreach ( $lists as $list ) {
				if ( ! is_array( $list ) ) {
					continue;
				}
				$recipients = isset( $list['email_recipients'] ) ? $list['email_recipients'] : array();
				$sent       = isset( $list['sent_count'] ) ? absint( $list['sent_count'] ) : 0;
				if ( $sent <= 0 && is_array( $recipients ) ) {
					foreach ( $recipients as $r ) {
						if ( ! empty( $r['sent_at'] ) ) {
							++$sent;
						}
					}
				}
				$total_sent += $sent;
			}
		} catch ( Exception $e ) {
			vortem_log( 'Email lists summary fetch failed: ' . $e->getMessage() );
		}

		$out = array(
			'sent'  => $total_sent,
			'lists' => $total_lists,
			'total' => $total_sent + $total_lists,
		);
		set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $out );
	}

	/**
	 * Clear all analytics transients.
	 */
	public function clear_cache() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_vortem_analytics_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_vortem_analytics_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_vortem_overview_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_vortem_overview_' ) . '%'
			)
		);
		// Also clear comments_total cache specifically
		delete_transient( 'vortem_analytics_comments_total' );
	}

	/**
	 * Clear order-related cache when orders change.
	 */
	public function clear_order_cache() {
		// Clear order-related transients
		delete_transient( 'vortem_analytics_orders_processing' );
		delete_transient( 'vortem_analytics_orders_total' );
		delete_transient( 'vortem_analytics_orders_today_' . current_time( 'Y-m-d' ) );
		delete_transient( 'vortem_analytics_revenue_total' );
		delete_transient( 'vortem_analytics_revenue_today_' . current_time( 'Y-m-d' ) );
		delete_transient( 'vortem_analytics_revenue_30d_' . current_time( 'Y-m-d' ) );
		delete_transient( 'vortem_analytics_avg_order_value' );
		delete_transient( 'vortem_analytics_pending_orders' );
		delete_transient( 'vortem_analytics_failed_orders' );
		delete_transient( 'vortem_analytics_refunded_orders' );
		delete_transient( 'vortem_analytics_cart_abandonment' );

		// Clear the main metrics cache for all users
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_vortem_analytics_metrics_' ) . '%'
			)
		);
	}

	/**
	 * Clear product-related cache when products change.
	 */
	public function clear_product_cache() {
		delete_transient( 'vortem_analytics_low_stock' );
		delete_transient( 'vortem_analytics_products_count' );

		// Clear the main metrics cache for all users
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_vortem_analytics_metrics_' ) . '%'
			)
		);
	}

	// ============================================================================
	// METRIC HELPER FUNCTIONS
	// ============================================================================

	/**
	 * Get total number of users.
	 */
	private function get_users_total() {
		$cache_key = 'vortem_analytics_users_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$counts = count_users();
		$total  = isset( $counts['total_users'] ) ? absint( $counts['total_users'] ) : 0;

		set_transient( $cache_key, $total, 10 * MINUTE_IN_SECONDS );
		return $total;
	}

	/**
	 * Get users registered today.
	 */
	private function get_users_today() {
		global $wpdb;
		$cache_key = 'vortem_analytics_users_today_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$today = current_time( 'Y-m-d' );
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} WHERE DATE(user_registered) = %s",
				$today
			)
		);

		$count = absint( $count );
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total number of processing orders.
	 *
	 * @return int
	 */
	private function get_orders_processing() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_orders_processing';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		// External Dependency: WooCommerce - wc_get_orders() for querying processing orders count
		$orders_query = wc_get_orders(
			array(
				'limit'    => 1,
				'status'   => array( 'wc-processing' ),
				'paginate' => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total number of completed orders.
	 */
	private function get_orders_total() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_orders_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'    => 1,
				'status'   => array( 'wc-completed' ),
				'paginate' => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get orders created today (completed orders).
	 */
	private function get_orders_today() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_orders_today_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$today = strtotime( 'today' );
		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'        => 1,
				'status'       => array( 'wc-completed' ),
				'date_created' => '>=' . $today,
				'paginate'     => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total revenue from completed orders.
	 */
	private function get_revenue_total() {
		if ( ! function_exists( 'WC' ) ) {
			return 0.0;
		}

		$cache_key = 'vortem_analytics_revenue_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return floatval( $cached );
		}

		// Use database aggregation for efficient revenue calculation
		global $wpdb;

		// Check if using HPOS (High-Performance Order Storage)
		$orders_table      = $wpdb->prefix . 'wc_orders';
		$orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
		$using_hpos        = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table;

		if ( $using_hpos ) {
			// HPOS: Use orders table directly
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix
			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(total_amount) FROM {$orders_table}
                    WHERE status = %s AND type = %s",
					'wc-completed',
					'shop_order'
				)
			);
		} else {
			// Legacy: Use postmeta aggregation
			// Note: post_status stores the order status, _order_total stores the total
			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(meta_value AS DECIMAL(10,2)))
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = %s
                    AND p.post_status = %s
                    AND pm.meta_key = %s",
					'shop_order',
					'wc-completed',
					'_order_total'
				)
			);
		}

		$revenue = $revenue ? floatval( $revenue ) : 0.0;
		set_transient( $cache_key, $revenue, 10 * MINUTE_IN_SECONDS );
		return $revenue;
	}

	/**
	 * Get revenue from completed orders created today.
	 */
	private function get_revenue_today() {
		if ( ! function_exists( 'WC' ) ) {
			return 0.0;
		}

		$cache_key = 'vortem_analytics_revenue_today_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return floatval( $cached );
		}

		$today_start = strtotime( 'today' );
		$today_end   = strtotime( 'tomorrow' ) - 1;

		// Use database aggregation for efficient revenue calculation
		global $wpdb;

		// Check if using HPOS (High-Performance Order Storage)
		$orders_table = $wpdb->prefix . 'wc_orders';
		$using_hpos   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table;

		if ( $using_hpos ) {
			// HPOS: Use orders table directly
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix
			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(total_amount) FROM {$orders_table}
                    WHERE status = %s
                    AND type = %s
                    AND date_created_gmt >= %s
                    AND date_created_gmt <= %s",
					'wc-completed',
					'shop_order',
					gmdate( 'Y-m-d H:i:s', $today_start ),
					gmdate( 'Y-m-d H:i:s', $today_end )
				)
			);
		} else {
			// Legacy: Use postmeta aggregation with date filter
			$today_date = current_time( 'Y-m-d' );
			$revenue    = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(meta_value AS DECIMAL(10,2)))
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = %s
                    AND p.post_status = %s
                    AND DATE(p.post_date) = %s
                    AND pm.meta_key = %s",
					'shop_order',
					'wc-completed',
					$today_date,
					'_order_total'
				)
			);
		}

		$revenue = $revenue ? floatval( $revenue ) : 0.0;
		set_transient( $cache_key, $revenue, HOUR_IN_SECONDS );
		return $revenue;
	}

	/**
	 * Get revenue from completed orders in last 30 days.
	 */
	private function get_revenue_last_30d() {
		if ( ! function_exists( 'WC' ) ) {
			return 0.0;
		}

		$cache_key = 'vortem_analytics_revenue_30d_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return floatval( $cached );
		}

		$thirty_days_ago = strtotime( '-30 days' );

		// Use database aggregation for efficient revenue calculation
		global $wpdb;

		// Check if using HPOS (High-Performance Order Storage)
		$orders_table = $wpdb->prefix . 'wc_orders';
		$using_hpos   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table;

		if ( $using_hpos ) {
			// HPOS: Use orders table directly
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted $wpdb->prefix
			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(total_amount) FROM {$orders_table}
                    WHERE status = %s
                    AND type = %s
                    AND date_created_gmt >= %s",
					'wc-completed',
					'shop_order',
					gmdate( 'Y-m-d H:i:s', $thirty_days_ago )
				)
			);
		} else {
			// Legacy: Use postmeta aggregation with date filter
			$date_from = gmdate( 'Y-m-d', $thirty_days_ago );
			$revenue   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(meta_value AS DECIMAL(10,2)))
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = %s
                    AND p.post_status = %s
                    AND DATE(p.post_date) >= %s
                    AND pm.meta_key = %s",
					'shop_order',
					'wc-completed',
					$date_from,
					'_order_total'
				)
			);
		}

		$revenue = $revenue ? floatval( $revenue ) : 0.0;
		set_transient( $cache_key, $revenue, HOUR_IN_SECONDS );
		return $revenue;
	}

	/**
	 * Get average order value.
	 */
	private function get_avg_order_value() {
		if ( ! function_exists( 'WC' ) ) {
			return 0.0;
		}

		$cache_key = 'vortem_analytics_avg_order_value';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return floatval( $cached );
		}

		$total_revenue = $this->get_revenue_total();
		$total_orders  = $this->get_orders_total();

		if ( 0 === $total_orders ) {
			return 0.0;
		}

		$avg = $total_revenue / $total_orders;
		$avg = floatval( $avg );
		set_transient( $cache_key, $avg, 10 * MINUTE_IN_SECONDS );
		return $avg;
	}

	/**
	 * Get count of products with low stock.
	 * For variable products, sums all variation stock quantities.
	 * Product is considered low stock if total stock is less than 10.
	 */
	private function get_low_stock_count() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_low_stock';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$low_stock_threshold = 10;

		global $wpdb;

		// Single SQL query: count published products whose _stock is between 1 and
		// the threshold. Covers simple products and variable-product variations
		// that manage stock. Much faster than the N+1 wc_get_product() loop.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics metric with transient cache above
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
				 INNER JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id AND pm_manage.meta_key = '_manage_stock' AND pm_manage.meta_value = 'yes'
				 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_stock_status' AND pm_status.meta_value = 'instock'
				 WHERE p.post_type IN ('product', 'product_variation')
				 AND p.post_status = 'publish'
				 AND CAST(pm_stock.meta_value AS SIGNED) > 0
				 AND CAST(pm_stock.meta_value AS SIGNED) < %d",
				$low_stock_threshold
			)
		);

		$count = absint( $count );
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get count of pending orders.
	 */
	private function get_pending_orders() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_pending_orders';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'    => 1,
				'status'   => array( 'wc-pending' ),
				'paginate' => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get count of failed orders.
	 */
	private function get_failed_orders() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_failed_orders';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'    => 1,
				'status'   => array( 'wc-failed' ),
				'paginate' => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get on-hold orders count.
	 *
	 * Note: Response key remains `refunded_orders` for backward-compatibility
	 * with the analytics dashboard JS, but the metric actually represents
	 * orders with the `wc-on-hold` status.
	 */
	private function get_on_hold_orders() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_refunded_orders';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'    => 1,
				'status'   => array( 'wc-on-hold' ),
				'paginate' => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total published products count.
	 */
	private function get_products_count() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_products_count';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$counts = wp_count_posts( 'product' );
		$count  = isset( $counts->publish ) ? absint( $counts->publish ) : 0;

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total views from _post_views_count meta values.
	 */
	private function get_views_total() {
		$cache_key = 'vortem_analytics_views_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;

		// Sum _post_views_count meta values from wp_postmeta
		$views = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta}
                WHERE meta_key = %s",
				'_post_views_count'
			)
		);

		// If no views found or meta doesn't exist, return 0 or N/A will be handled by frontend
		$views = $views ? absint( $views ) : 0;

		set_transient( $cache_key, $views, 10 * MINUTE_IN_SECONDS );
		return $views;
	}

	/**
	 * Get total number of published posts.
	 */
	private function get_posts_total() {
		$cache_key = 'vortem_analytics_posts_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$counts = wp_count_posts( 'post' );
		$count  = isset( $counts->publish ) ? absint( $counts->publish ) : 0;

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get total number of published pages.
	 */
	private function get_pages_total() {
		$cache_key = 'vortem_analytics_pages_total';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$counts = wp_count_posts( 'page' );
		$count  = isset( $counts->publish ) ? absint( $counts->publish ) : 0;

		set_transient( $cache_key, $count, 10 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get cart abandonment count (pending orders older than 15 minutes).
	 */
	private function get_cart_abandonment() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_cart_abandonment';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		$fifteen_min_ago = strtotime( '-15 minutes' );
		// Use paginate parameter for efficient counting
		$orders_query = wc_get_orders(
			array(
				'limit'        => 1,
				'status'       => 'pending',
				'date_created' => '<' . $fifteen_min_ago,
				'paginate'     => true,
			)
		);

		$count = isset( $orders_query->total ) ? absint( $orders_query->total ) : 0;
		set_transient( $cache_key, $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Get customers registered in last 30 days (users with role 'customer').
	 */
	private function get_customers_last_30d() {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cache_key = 'vortem_analytics_customers_30d_' . current_time( 'Y-m-d' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return absint( $cached );
		}

		global $wpdb;

		// Count users with role 'customer' where user_registered >= NOW() - INTERVAL 30 DAY
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Get all users with customer role registered in last 30 days
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = %s
                AND (um.meta_value LIKE %s OR um.meta_value LIKE %s)
                AND u.user_registered >= %s",
				$wpdb->prefix . 'capabilities',
				'%"customer"%',
				'%s:8:"customer";%',
				$thirty_days_ago
			)
		);

		$count = absint( $count );
		set_transient( $cache_key, $count, HOUR_IN_SECONDS );
		return $count;
	}

	   // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
}
