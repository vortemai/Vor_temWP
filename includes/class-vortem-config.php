<?php
/**
 * Vortem Configuration Class
 *
 * Centralizes all external endpoints and configuration settings
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Configuration
 */
class Vortem_Config {

	/**
	 * API Server Configuration
	 */
	const API_SERVERS = array(
		'primary'    => 'https://c.vortem.ai',
		'fallback_1' => 'https://c.vortem.ai',
		'fallback_2' => 'https://c.vortem.ai',
		'fallback_3' => 'https://c.vortem.ai',
		'fallback_4' => 'https://c.vortem.ai',
	);

	/**
	 * API Endpoints
	 */
	const API_ENDPOINTS = array(
		'health'                                   => '/health',
		'health_alt_1'                             => '/api/health',
		'health_alt_2'                             => '/v1/health',
		'health_alt_3'                             => '/api/v1/health',
		// Regular product endpoints
		'product_get_product'                      => '/api/v1/product/get_product',
		'product_get_product_basic'                => '/api/v1/product/get_product_basic',
		'product_get_from_cat'                     => '/api/v1/product/get_from_cat',
		'product_get_categories'                   => '/api/v1/product/get_categories',
		'product_exist'                            => '/api/v1/product/exist',
		// Top product endpoints
		'product_basic'                            => '/api/v1/product/top/basic_info',
		'product_detailed'                         => '/api/v1/product/top/get_product',
		'product_top_exist'                        => '/api/v1/product/top/exist',
		'product_top_get_from_cat'                 => '/api/v1/product/top/get_from_cat',
		// Imported products endpoints
		'product_top_imported'                     => '/api/v1/customer/product/top/imported',
		'product_top_imported_delete'              => '/api/v1/customer/product/top/imported/{_id}',
		// Currency endpoints
		'product_currency_code'                    => '/api/v1/product/currency_code',
		'product_currency'                         => '/api/v1/product/currency',
		'product_currency_get'                     => '/api/v1/product/currency',
		'product_currency_put'                     => '/api/v1/product/currency',
		// AliExpress endpoints
		'aliexpress_auth_authorize'                => '/api/v1/customer/aliexpress/auth/authorize',
		'aliexpress_auth_status'                   => '/api/v1/customer/aliexpress/auth/status',
		'aliexpress_auth_delete'                   => '/api/v1/customer/aliexpress/auth',
		// Email Marketing endpoints
		'email_marketing_base'                     => '/api/v1/customer/email_marketing',
		'email_marketing_me'                       => '/api/v1/customer/email_marketing/me',
		'email_marketing_search'                   => '/api/v1/customer/email_marketing/search',
		'email_marketing_get'                      => '/api/v1/customer/email_marketing/:id',
		'email_marketing_create'                   => '/api/v1/customer/email_marketing',
		'email_marketing_update'                   => '/api/v1/customer/email_marketing/:id',
		'email_marketing_delete'                   => '/api/v1/customer/email_marketing',
		'email_marketing_bulk_delete'              => '/api/v1/customer/email_marketing/bulk',
		'email_marketing_send'                     => '/api/v1/customer/email_marketing/send/:id',
		'email_marketing_send_list'                => '/api/v1/customer/email_marketing/send_list/:id',
		'email_marketing_status'                   => '/api/v1/customer/email_marketing/status/:id',
		'email_marketing_useg'                     => '/api/v1/customer/email_marketing/useg',
		'email_marketing_email_lists'              => '/api/v1/customer/email_marketing/email_lists',
		'email_marketing_emails_list'              => '/api/v1/customer/email_marketing/emails_list',
		// BI Analytics Hub endpoints
		'bi_analytics_charts_base'                 => '/api/v1/customer/product/dashboard/charts',
		'bi_analytics_charts_kpi'                  => '/api/v1/customer/product/dashboard/charts/kpi',
		'bi_analytics_charts_keywords_performance' => '/api/v1/customer/product/dashboard/charts/keywords-performance',
		'bi_analytics_charts_price_rating'         => '/api/v1/customer/product/dashboard/charts/price-rating',
		'bi_analytics_charts_sentiment'            => '/api/v1/customer/product/dashboard/charts/sentiment',
		'bi_analytics_charts_market_comparison'    => '/api/v1/customer/product/dashboard/charts/market-comparison',
		'bi_analytics_charts_category_comparison'  => '/api/v1/customer/product/dashboard/charts/category-comparison',
		'bi_analytics_charts_trend_index'          => '/api/v1/customer/product/dashboard/charts/trend-index',
		'bi_analytics_charts_trend_status'         => '/api/v1/customer/product/dashboard/charts/trend-status',
		'bi_analytics_charts_top_ads'              => '/api/v1/customer/product/dashboard/charts/top-ads',
		'bi_analytics_products_base'               => '/api/v1/customer/product/dashboard/products',
		'bi_analytics_products_suggested_pricing'  => '/api/v1/customer/product/dashboard/products/suggested_pricing',
		'bi_analytics_products_trend_products'     => '/api/v1/customer/product/dashboard/products/trend_products',
		'bi_analytics_products_tiktok'             => '/api/v1/customer/product/dashboard/tiktok',
		// Security endpoints
		'security_wordpress'                       => '/api/v1/customer/security/wordpress/plugin',
		'security_wordpress_match'                 => '/api/v1/customer/security/wordpress/plugin/match',
		'security_wordpress_theme'                 => '/api/v1/customer/security/wordpress/theme',
		'security_wordpress_theme_match'           => '/api/v1/customer/security/wordpress/theme/match',
		'security_wordpress_wp_core'               => '/api/v1/customer/security/wordpress/wp-core',
		'security_wordpress_wp_core_match'         => '/api/v1/customer/security/wordpress/wp-core/match',
		// Page Speed endpoints
		'page_speed_wordpress'                     => '/api/v1/customer/page_speed/wordpress',
		'page_speed_wordpress_refetch'             => '/api/v1/customer/page_speed/wordpress/refetch',
	);

	/**
	 * External Service URLs
	 */
	const EXTERNAL_URLS = array(
		'vortem_home'    => 'https://vortem.ai/',
		'vortem_terms'   => 'https://vortem.ai/en/terms',
		'vortem_privacy' => 'https://vortem.ai/en/privacy',
		'vortem_support' => 'https://c.vortem.ai/en/panel/feedback',
		'vortem_account' => 'https://c.vortem.ai/en/panel/profile',
		'vortem_docs'    => 'https://vortem.ai/en/docs',
	);

	/**
	 * Local Development URLs
	 */
	const LOCAL_URLS = array(
		'api_server' => 'https://c.vortem.ai',
	);

	/**
	 * WordPress Admin URLs
	 */
	const ADMIN_URLS = array(
		'admin_panel'          => '/wp-admin',
		'plugin_settings'      => '/wp-admin/admin.php?page=vortem-settings',
		'analytics'            => 'admin.php?page=vortem-analytics',
		'products'             => 'admin.php?page=vortem-products',
		'woocommerce_settings' => '/wp-admin/admin.php?page=wc-settings',
	);

	/**
	 * Get primary API server URL
	 *
	 * @return string
	 */
	public static function get_primary_api_server() {
		return self::API_SERVERS['primary'];
	}

	/**
	 * Get all API server URLs as array
	 *
	 * @return array
	 */
	public static function get_api_servers() {
		return array_values( self::API_SERVERS );
	}

	/**
	 * Get API server URLs with override support
	 *
	 * @return array
	 */
	public static function get_api_servers_with_override() {
		$servers = array();

		// Check for wp-config constant override, then WP option fallback.
		$override_api_url = defined( 'VORTEM_API_URL' ) ? constant( 'VORTEM_API_URL' ) : '';
		if ( ! $override_api_url ) {
			$override_api_url = get_option( 'vortem_api_url' );
		}

		if ( $override_api_url && is_string( $override_api_url ) ) {
			$servers[] = rtrim( $override_api_url, '/' );
		}

		// Add default servers
		$servers = array_merge( $servers, self::get_api_servers() );

		// Remove duplicates while preserving order
		return array_values( array_unique( $servers ) );
	}

	/**
	 * Get API endpoint URL
	 *
	 * @param string $endpoint_key Endpoint key from API_ENDPOINTS
	 * @return string
	 */
	public static function get_api_endpoint( $endpoint_key ) {
		if ( ! isset( self::API_ENDPOINTS[ $endpoint_key ] ) ) {
			return '';
		}

		return self::API_ENDPOINTS[ $endpoint_key ];
	}

	/**
	 * Get health check endpoints
	 *
	 * @return array
	 */
	public static function get_health_endpoints() {
		return array(
			self::API_ENDPOINTS['health'],
			self::API_ENDPOINTS['health_alt_1'],
			self::API_ENDPOINTS['health_alt_2'],
			self::API_ENDPOINTS['health_alt_3'],
		);
	}

	/**
	 * Get product basic endpoint URL
	 *
	 * @return string
	 */
	public static function get_product_basic_url() {
		return self::API_ENDPOINTS['product_basic'];
	}

	/**
	 * Get product detailed endpoint URL
	 *
	 * @return string
	 */
	public static function get_product_detailed_url() {
		return self::API_ENDPOINTS['product_detailed'];
	}

	/**
	 * Get external URL
	 *
	 * @param string $url_key URL key from EXTERNAL_URLS
	 * @return string
	 */
	public static function get_external_url( $url_key ) {
		if ( ! isset( self::EXTERNAL_URLS[ $url_key ] ) ) {
			return '';
		}

		return self::EXTERNAL_URLS[ $url_key ];
	}

	/**
	 * Get local development URL
	 *
	 * @param string $url_key URL key from LOCAL_URLS
	 * @return string
	 */
	public static function get_local_url( $url_key ) {
		if ( ! isset( self::LOCAL_URLS[ $url_key ] ) ) {
			return '';
		}

		return self::LOCAL_URLS[ $url_key ];
	}

	/**
	 * Get WordPress admin URL
	 *
	 * @param string $url_key URL key from ADMIN_URLS
	 * @return string
	 */
	public static function get_admin_url( $url_key ) {
		if ( ! isset( self::ADMIN_URLS[ $url_key ] ) ) {
			return '';
		}

		return self::ADMIN_URLS[ $url_key ];
	}

	/**
	 * Get full WordPress admin URL
	 *
	 * @param string $url_key URL key from ADMIN_URLS
	 * @return string
	 */
	public static function get_full_admin_url( $url_key ) {
		$path = self::get_admin_url( $url_key );
		if ( empty( $path ) ) {
			return '';
		}

		return get_site_url() . $path;
	}

	/**
	 * Get Vortem support URL
	 *
	 * @return string
	 */
	public static function get_support_url() {
		return self::get_external_url( 'vortem_support' );
	}

	/**
	 * Get Vortem account URL
	 *
	 * @return string
	 */
	public static function get_account_url() {
		return self::get_external_url( 'vortem_account' );
	}

	/**
	 * Get Vortem documentation URL
	 *
	 * @return string
	 */
	public static function get_docs_url() {
		return self::get_external_url( 'vortem_docs' );
	}

	/**
	 * Get Vortem terms URL
	 *
	 * @return string
	 */
	public static function get_terms_url() {
		return self::get_external_url( 'vortem_terms' );
	}

	/**
	 * Get Vortem analytics page URL
	 *
	 * @return string
	 */
	public static function get_analytics_url() {
		$path = self::get_admin_url( 'analytics' );
		return ! empty( $path ) ? admin_url( $path ) : admin_url( 'admin.php?page=vortem-analytics' );
	}

	/**
	 * Get Vortem products page URL
	 *
	 * @return string
	 */
	public static function get_products_url() {
		$path = self::get_admin_url( 'products' );
		return ! empty( $path ) ? admin_url( $path ) : admin_url( 'admin.php?page=vortem-products' );
	}

	/**
	 * Get WordPress site URL
	 *
	 * @return string
	 */
	public static function get_wordpress_url() {
		// Auto-detect domain from HTTP request headers
		$detected_domain = self::auto_detect_domain();
		if ( $detected_domain ) {
			return $detected_domain;
		}

		// Check for override domain as fallback (wp-config constant first).
		$override_domain = defined( 'VORTEM_DOMAIN' ) ? constant( 'VORTEM_DOMAIN' ) : '';
		if ( ! $override_domain ) {
			$override_domain = get_option( 'vortem_domain' );
		}

		if ( $override_domain && is_string( $override_domain ) ) {
			return $override_domain;
		}

		// Fallback to WordPress site URL
		$site_url = get_site_url();

		// Parse the URL to extract only the domain
		$parsed_url = wp_parse_url( $site_url );

		if ( isset( $parsed_url['host'] ) ) {
			$domain = $parsed_url['host'];
			return $domain;
		}

		// Final fallback: remove protocol and path manually
		$domain = str_replace( array( 'http://', 'https://' ), '', $site_url );
		$domain = strtok( $domain, '/' ); // Remove path if present
		return $domain;
	}

	/**
	 * Get WordPress site URL with protocol (https or http)
	 *
	 * @return string
	 */
	public static function get_wordpress_url_with_protocol() {
		// Get the domain first
		$domain = self::get_wordpress_url();

		// Determine protocol (https or http)
		$protocol = 'https';

		// Use WordPress function to check if SSL (preferred method)
		if ( is_ssl() ) {
			$protocol = 'https';
		} else {
			// Check $_SERVER with proper sanitization
			if ( isset( $_SERVER['HTTPS'] ) && is_string( $_SERVER['HTTPS'] ) ) {
				$https = sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) );
				if ( $https === 'off' || empty( $https ) ) {
					$protocol = 'http';
				}
			} elseif ( isset( $_SERVER['SERVER_PORT'] ) ) {
				$raw_port = sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ) );
				if ( is_numeric( $raw_port ) ) {
					$port = absint( $raw_port );
					if ( 80 === $port ) {
						$protocol = 'http';
					}
				}
			}
		}

		// Check if site URL is available and use its protocol
		$site_url = get_site_url();
		if ( $site_url ) {
			$parsed_url = wp_parse_url( $site_url );
			if ( isset( $parsed_url['scheme'] ) ) {
				$protocol = sanitize_text_field( $parsed_url['scheme'] );
			}
		}

		return $protocol . '://' . $domain;
	}

	/**
	 * Auto-detect domain from HTTP request headers
	 *
	 * @return string|null
	 */
	private static function auto_detect_domain() {
		// Check various HTTP headers for the actual domain
		$headers_to_check = array(
			'HTTP_HOST',
			'SERVER_NAME',
			'HTTP_X_FORWARDED_HOST',
			'HTTP_X_ORIGINAL_HOST',
		);

		foreach ( $headers_to_check as $header ) {
			if ( isset( $_SERVER[ $header ] ) && ! empty( $_SERVER[ $header ] ) ) {
				$host = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// Remove port if present
				if ( strpos( $host, ':' ) !== false ) {
					$host = strtok( $host, ':' );
				}

				// Validate hostname format. Skip local-dev hosts; we want a real
				// production domain for canonical site identification.
				if ( ! empty( $host ) &&
					! self::is_local_hostname( $host ) &&
					! filter_var( $host, FILTER_VALIDATE_IP ) &&
					filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
					return $host;
				}
			}
		}

		// Check if we can get domain from current request URL
		if ( isset( $_SERVER['REQUEST_URI'] ) && isset( $_SERVER['HTTP_HOST'] ) ) {
			$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

			if ( ! empty( $http_host ) && ! empty( $request_uri ) ) {
				// Use WordPress function to get current URL
				$current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

				$parsed_url = wp_parse_url( $current_url );
				if ( isset( $parsed_url['host'] ) &&
					! self::is_local_hostname( $parsed_url['host'] ) &&
					! filter_var( $parsed_url['host'], FILTER_VALIDATE_IP ) &&
					filter_var( $parsed_url['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
					return sanitize_text_field( $parsed_url['host'] );
				}
			}
		}
		return null;
	}

	/**
	 * Get WordPress domain for API requests
	 * Returns the actual site domain (without protocol) to be sent to API
	 *
	 * @return string WordPress domain
	 */
	public static function get_wordpress_domain_for_api() {
		return self::get_wordpress_url();
	}

	/**
	 * Get phpMyAdmin URL
	 *
	 * @return string
	 */
	public static function get_phpmyadmin_url() {
		// Only available in development environment
		if ( self::is_development() ) {
			return self::get_local_url( 'phpmyadmin' );
		}

		// Not available in production
		return '';
	}

	/**
	 * Get MailHog URL
	 *
	 * @return string
	 */
	public static function get_mailhog_url() {
		// Only available in development environment
		if ( self::is_development() ) {
			return self::get_local_url( 'mailhog' );
		}

		// Not available in production
		return '';
	}

	/**
	 * Build complete API URL
	 *
	 * @param string $server_url Base server URL
	 * @param string $endpoint_key Endpoint key
	 * @return string
	 */
	public static function build_api_url( $server_url, $endpoint_key ) {
		$endpoint = self::get_api_endpoint( $endpoint_key );
		if ( empty( $endpoint ) ) {
			return '';
		}

		return rtrim( $server_url, '/' ) . $endpoint;
	}

	/**
	 * Get all configuration as array for debugging
	 *
	 * @return array
	 */
	public static function get_all_config() {
		return array(
			'api_servers'   => self::API_SERVERS,
			'api_endpoints' => self::API_ENDPOINTS,
			'external_urls' => self::EXTERNAL_URLS,
			'local_urls'    => self::LOCAL_URLS,
			'admin_urls'    => self::ADMIN_URLS,
		);
	}

	/**
	 * Validate URL format
	 *
	 * @param string $url URL to validate
	 * @return bool
	 */
	public static function is_valid_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Get current environment (development/production)
	 *
	 * @return string
	 */
	public static function get_environment() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'development';
		}

		return 'production';
	}

	/**
	 * Check if running in development mode.
	 *
	 * Returns true when WP_DEBUG is on or when the running hostname matches
	 * a local-development pattern. Used to surface dev-only URLs (phpMyAdmin,
	 * MailHog) and to keep the plugin chatty in dev environments.
	 *
	 * @return bool
	 */
	public static function is_development() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		$host = '';
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		if ( strpos( $host, ':' ) !== false ) {
			$host = strtok( $host, ':' );
		}

		return self::is_local_hostname( $host )
			|| self::is_local_hostname( (string) self::get_wordpress_url() );
	}

	/**
	 * Detect a local development hostname.
	 *
	 * The literal `localhost` / `127.0.0.1` / `::1` tokens appear in this
	 * helper only; every other check in the plugin delegates here. The
	 * helper exists to (a) skip non-canonical hosts when discovering the
	 * production site domain to send to the external API and (b) flag the
	 * running install as a development environment for dev-only UX.
	 *
	 * @param string $host Hostname without port.
	 * @return bool True if the host looks like a local-development host.
	 */
	private static function is_local_hostname( $host ) {
		if ( '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		if ( 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host ) {
			return true;
		}
		return false !== stripos( $host, 'localhost' );
	}

	/**
	 * Get appropriate API server for current environment
	 *
	 * @return string
	 */
	public static function get_environment_appropriate_server() {
		if ( self::is_development() ) {
			return self::get_primary_api_server();
		}

		// In production, prefer override or primary
		$servers = self::get_api_servers_with_override();
		return ! empty( $servers ) ? $servers[0] : self::get_primary_api_server();
	}

	/**
	 * Get BI Analytics Hub chart endpoint
	 *
	 * @param string $endpoint_name Endpoint name (e.g., 'kpi', 'keywords-performance', 'top-ads', etc.)
	 * @return string
	 */
	public static function get_bi_analytics_chart_endpoint( $endpoint_name ) {
		// Handle special case for top-ads
		if ( $endpoint_name === 'top-ads' || $endpoint_name === 'top_ads' ) {
			return self::API_ENDPOINTS['bi_analytics_charts_top_ads'];
		}

		$endpoint_key = 'bi_analytics_charts_' . str_replace( '-', '_', $endpoint_name );

		if ( isset( self::API_ENDPOINTS[ $endpoint_key ] ) ) {
			return self::API_ENDPOINTS[ $endpoint_key ];
		}

		// Fallback: build from base endpoint
		$base = self::API_ENDPOINTS['bi_analytics_charts_base'];
		return $base . '/' . $endpoint_name;
	}

	/**
	 * Get BI Analytics Hub suggested pricing endpoint
	 *
	 * @return string
	 */
	public static function get_bi_analytics_suggested_pricing_endpoint() {
		return self::API_ENDPOINTS['bi_analytics_products_suggested_pricing'];
	}

	/**
	 * Build full BI Analytics Hub API URL
	 *
	 * @param string $api_base_url Base URL for BI Analytics API (deprecated, now uses primary API server)
	 * @param string $endpoint_name Endpoint name or full endpoint path
	 * @return string
	 */
	public static function build_bi_analytics_url( $api_base_url, $endpoint_name ) {
		// Use primary API server instead of the old api_base_url option
		$api_base_url = rtrim( self::get_primary_api_server(), '/' );

		// Check if it's suggested_pricing (special case)
		if ( $endpoint_name === 'suggested_pricing' ) {
			$endpoint = self::get_bi_analytics_suggested_pricing_endpoint();
		} elseif ( $endpoint_name === 'trend_products' ) {
			// Handle trend_products endpoint
			$endpoint = self::API_ENDPOINTS['bi_analytics_products_trend_products'];
		} else {
			// Get chart endpoint
			$endpoint = self::get_bi_analytics_chart_endpoint( $endpoint_name );
		}

		return $api_base_url . $endpoint;
	}
}
