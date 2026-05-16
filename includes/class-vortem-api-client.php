<?php
/**
 * Vortem API Client Class
 *
 * Handles API communication with Vortem backend
 * Uses WordPress HTTP API (wp_remote_get, wp_remote_request) for external HTTP requests
 *
 * External Dependencies Used:
 * - WordPress HTTP API (wp_remote_get, wp_remote_request, wp_remote_retrieve_*) - WordPress Core
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load configuration class
require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

/**
 * Vortem API Client
 */
class Vortem_Api_Client {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Customer server URL
	 *
	 * @var string
	 */
	private $customer_server_url;

	/**
	 * Whether the administrator has granted data-processing consent in the
	 * Setup Wizard. WordPress.org guidelines require all phone-home calls to
	 * be gated behind this — the only exception is the public currency-list
	 * endpoint, which transmits no PII.
	 *
	 * @return bool
	 */
	public static function has_consent() {
		return (bool) get_option( 'vortem_data_processing_consent', false );
	}

	/**
	 * Build the standard consent-required WP_Error returned by every external
	 * HTTP entry point when consent has not yet been granted.
	 *
	 * @return WP_Error
	 */
	public static function consent_required_error() {
		return new WP_Error(
			'vortem_no_consent',
			esc_html__( 'External API calls are disabled until you complete the Setup Wizard and accept the data processing consent.', 'vortem-ai' ),
			array( 'status' => 451 )
		);
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize customer server URL using config
		$this->customer_server_url = Vortem_Config::get_primary_api_server();

		// Allow overriding API URL via a wp-config constant or WP option.
		// Constants take precedence; defining VORTEM_API_URL in wp-config.php
		// is the recommended way for site owners to point at a staging server.
		$override_api_url = defined( 'VORTEM_API_URL' ) ? constant( 'VORTEM_API_URL' ) : '';
		if ( ! $override_api_url ) {
			$override_api_url = get_option( 'vortem_api_url' );
		}

		if ( $override_api_url && is_string( $override_api_url ) ) {
			$this->api_url = rtrim( $override_api_url, '/' );
		} else {
			// Default to customer server
			$this->api_url = rtrim( $this->customer_server_url, '/' );
		}
	}

	/**
	 * Automatically detect the correct API URL
	 * Tries customer server with various fallback addresses
	 *
	 * @return string
	 */
	private function detect_api_url() {
		// Always prefer configured customer server by default
		$default = rtrim( $this->customer_server_url, '/' );
		if ( $this->is_server_available( $default ) ) {
			return $default;
		}
		// Use fallbacks from config
		$fallbacks = Vortem_Config::get_api_servers();
		foreach ( $fallbacks as $url ) {
			if ( $this->is_server_available( $url ) ) {
				return $url;
			}
		}
		// If none available, still return default so errors are consistent
		return $default;
	}

	/**
	 * Check if a server is available by testing the health endpoint
	 *
	 * @param string $base_url
	 * @return bool
	 */
	private function is_server_available( $base_url ) {
		// Phone-home gate: skip the health probe entirely until the admin has consented.
		if ( ! self::has_consent() ) {
			return false;
		}

		$health_paths = Vortem_Config::get_health_endpoints();

		foreach ( $health_paths as $path ) {
			$health_url = rtrim( $base_url, '/' ) . $path;

			// WordPress HTTP API: wp_remote_get() - Checks server health endpoint availability
			$response = wp_remote_get(
				$health_url,
				array(
					'timeout'   => 3,
					'sslverify' => true,
					'headers'   => array(
						'Content-Type' => 'application/json',
						'Referer'      => home_url(),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( $status_code === 200 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make API request
	 *
	 * All requests use simple headers: Content-Type and Referer only
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data Request data
	 * @param string $method HTTP method
	 * @param array  $custom_headers Custom headers to include
	 * @return array|WP_Error
	 */
	public function make_request( $endpoint, $data = array(), $method = 'GET', $custom_headers = array() ) {
		// Phone-home gate: refuse every API call until the admin has consented.
		if ( ! self::has_consent() ) {
			return self::consent_required_error();
		}

		// Build URL properly - endpoint already starts with /, so just concatenate
		$url = rtrim( $this->api_url, '/' ) . $endpoint;

		// Simple headers with only Content-Type and Referer
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		// Merge custom headers (custom headers will override defaults if needed)
		$headers = array_merge( $headers, $custom_headers );

		$args = array(
			'method'    => $method,
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => true,
		);

		if ( ! empty( $data ) ) {
			if ( $method === 'GET' ) {
				$url .= '?' . http_build_query( $data );
			} else {
				$args['body'] = wp_json_encode( $data );
			}
		}

		// WordPress HTTP API: wp_remote_request() - Makes HTTP request to Vortem API server
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();

			// Provide more user-friendly error messages
			if ( strpos( $error_message, 'Failed to connect' ) !== false ) {
				return new WP_Error( 'connection_failed', 'Unable to connect to Vortem server. Please check if the server is running and accessible.' );
			} elseif ( strpos( $error_message, 'cURL error' ) !== false ) {
				return new WP_Error( 'curl_error', 'Network connection error. Please check your internet connection and server configuration.' );
			} else {
				return new WP_Error( 'api_error', 'API request failed: ' . $error_message );
			}
		}

		$body             = wp_remote_retrieve_body( $response );
		$status_code      = wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		if ( $status_code >= 400 ) {
			// Try to extract error message from response body
			$error_message = 'API request failed with status: ' . $status_code;
			if ( ! empty( $body ) ) {
				$decoded_error = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
					if ( isset( $decoded_error['message'] ) ) {
						$error_message .= ' - ' . $decoded_error['message'];
					} elseif ( isset( $decoded_error['error'] ) ) {
						$error_message .= ' - ' . $decoded_error['error'];
					} else {
						$error_message .= ' - Response: ' . substr( $body, 0, 200 );
					}
				} else {
					$error_message .= ' - Response: ' . substr( $body, 0, 200 );
				}
			}
			return new WP_Error(
				'api_error',
				$error_message,
				array(
					'status_code'   => $status_code,
					'response_body' => $body,
				)
			);
		}

		$decoded_response = json_decode( $body, true );

		// Debug JSON decoding
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response: ' . json_last_error_msg() );
		}

		return $decoded_response;
	}

	/**
	 * Validate auth for settings page
	 * No authentication required - always returns success
	 *
	 * @return array|WP_Error
	 */
	public function validate_auth_for_settings() {
		return array(
			'valid'   => true,
			'status'  => 'active',
			'message' => esc_html__( 'Plugin is ready to use', 'vortem-ai' ),
		);
	}

	/**
	 * Validate auth using customer server
	 * No authentication required - always returns success
	 *
	 * @param array $additional_data Additional data to send with validation (deprecated, kept for backward compatibility)
	 * @return array|WP_Error
	 */
	public function validate_auth( $additional_data = array() ) {
		return array(
			'valid'   => true,
			'status'  => 'active',
			'message' => esc_html__( 'Plugin is ready to use', 'vortem-ai' ),
		);
	}

	/**
	 * Fetch basic products from backend API (for listing/preview)
	 * Uses top products endpoint: GET /api/v1/product/top/basic_info
	 * Query parameters: ?page=1&per_page=10
	 *
	 * @param array $params Fetch parameters (per_page, page)
	 * @return array|WP_Error
	 */
	public function fetch_basic_products( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL from top products endpoint (product_basic uses /api/v1/product/top/basic_info)
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_basic' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Product basic endpoint not configured' );
		}

		// Prepare query parameters for GET request: ?page=1&per_page=10
		$query_params = array(
			'per_page' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 10,
			'page'     => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
		);

		// Make GET request to top products endpoint with query parameters
		$response = $this->make_request( $endpoint_url, $query_params, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch detailed products from backend API (for import)
	 *
	 * @param array $params Fetch parameters
	 * @return array|WP_Error
	 */
	public function fetch_products( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Prepare request data (support page, limit, and product_id)
		$default_limit = get_option( 'vortem_products_per_page', 16 );
		$request_data  = array(
			'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : $default_limit,
			'page'  => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
		);

		// Add product_id parameter if provided
		if ( isset( $params['product_id'] ) && ! empty( $params['product_id'] ) ) {
			$request_data['product_id'] = sanitize_text_field( $params['product_id'] );
		}

		// Add currency parameter if available (ensures products are fetched with correct currency)
		$currency = get_option( 'vortem_customer_currency', get_option( 'vortem_currency', 'USD' ) );
		if ( ! empty( $currency ) ) {
			$request_data['customer_currency'] = sanitize_text_field( $currency );
		}

		// Determine endpoint based on whether product_id is provided
		// When product_id is provided, use /api/v1/product/get_product (for imports)
		// Otherwise, use /api/v1/product/get_product for pagination
		// Note: Both cases use the same endpoint, as per API requirements
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_detailed' );

		// Validate endpoint URL
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Product endpoint not configured: product_detailed' );
		}

		$response = $this->make_request( $endpoint_url, $request_data, 'POST', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch top product categories that exist
	 * Returns paginated list of existing categories (vortem_cat_ID) from the external_top_product collection
	 *
	 * @param array $params Fetch parameters (page, limit)
	 * @return array|WP_Error
	 */
	public function fetch_top_categories_exist( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Prepare request data (support page and limit)
		$request_data = array(
			'page'  => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
			'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 10,
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_top_exist' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Product top exist endpoint not configured' );
		}

		// Make API request to the top categories exist endpoint (GET method)
		$response = $this->make_request( $endpoint_url, $request_data, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch top products from a specific category
	 * Returns top products filtered by category ID from top products collection with pagination
	 *
	 * @param array $params Fetch parameters (cat_id, page, limit)
	 * @return array|WP_Error
	 */
	public function fetch_products_from_category( $params = array() ) {
		// Phone-home gate.
		if ( ! self::has_consent() ) {
			return self::consent_required_error();
		}

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Check if category ID is provided
		if ( empty( $params['cat_id'] ) ) {
			return new WP_Error( 'no_category_id', 'Category ID is required' );
		}

		// Prepare request data (page and limit as query params, cat_id in body)
		$query_params = array(
			'page'  => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
			'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 16,
		);

		// Body data for POST request
		$body_data = array(
			'cat_id' => sanitize_text_field( $params['cat_id'] ),
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_top_get_from_cat' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Product top get from cat endpoint not configured' );
		}

		// Build URL with query parameters
		$url = $this->api_url . '/' . ltrim( $endpoint_url, '/' );
		if ( ! empty( $query_params ) ) {
			$url .= '?' . http_build_query( $query_params );
		}

		// Prepare headers
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		$args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $body_data ),
			'timeout'   => 30,
			'sslverify' => true,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return $response;
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = 'API request failed with status: ' . $status_code;
			if ( ! empty( $body ) ) {
				$decoded_error = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
					if ( isset( $decoded_error['message'] ) ) {
						$error_message .= ' - ' . $decoded_error['message'];
					} else {
						$error_message .= ' - Response: ' . substr( $body, 0, 200 );
					}
				} else {
					$error_message .= ' - Response: ' . substr( $body, 0, 200 );
				}
			}
			return new WP_Error(
				'api_error',
				$error_message,
				array(
					'status_code'   => $status_code,
					'response_body' => $body,
				)
			);
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response: ' . json_last_error_msg() );
		}

		return $decoded_response;
	}

	/**
	 * Validate API endpoint
	 *
	 * @return array|WP_Error
	 */
	public function validate_endpoint() {

		// First try health check endpoint
		$health_response = $this->make_request( Vortem_Config::get_api_endpoint( 'health' ), array(), 'GET' );

		if ( ! is_wp_error( $health_response ) ) {
			return array(
				'success'  => true,
				'message'  => 'Endpoint is valid and accessible (health check passed)',
				'response' => $health_response,
			);
		}

		// If health check fails, try the basic products endpoint with minimal data
		$test_data = array(
			'limit' => 1,
			'page'  => 1,
		);

		$response = $this->make_request( Vortem_Config::get_api_endpoint( 'product_basic' ), $test_data, 'POST', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'success'  => true,
			'message'  => 'Endpoint is valid and accessible (basic products endpoint test passed)',
			'response' => $response,
		);
	}

	/**
	 * Fetch currency codes from API
	 *
	 * @return array|WP_Error
	 */
	public function fetch_currency_codes() {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency_code' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Currency code endpoint not configured' );
		}

		// Make API request to the currency code endpoint (GET method)
		$response = $this->make_request( $endpoint_url, array(), 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch currency codes from public API (without authentication)
	 * This is used in setup wizard before authentication is complete
	 *
	 * @return array|WP_Error
	 */
	public function fetch_currency_codes_public() {
		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency_code' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Currency code endpoint not configured' );
		}

		// Build full URL
		$full_url = Vortem_Config::build_api_url( Vortem_Config::get_primary_api_server(), 'product_currency_code' );
		if ( empty( $full_url ) ) {
			return new WP_Error( 'invalid_url', 'Could not build API URL' );
		}

		// Prepare headers for public request (no authorization)
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

		$response = wp_remote_request( $full_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			return new WP_Error( 'api_error', 'API request failed with status: ' . $status_code );
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON response' );
		}

		return $decoded_response;
	}

	/**
	 * Fetch current currency from API
	 *
	 * @return array|WP_Error
	 */
	public function fetch_current_currency() {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency_get' );
		if ( empty( $endpoint_url ) ) {
			// Fallback to generic product_currency endpoint
			$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency' );
		}
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Currency endpoint not configured' );
		}

		// Make API request to the currency endpoint (GET method)
		$response = $this->make_request( $endpoint_url, array(), 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Update currency on API
	 *
	 * @param string $currency_code Currency code to set (e.g., 'USD')
	 * @return array|WP_Error
	 */
	public function update_currency( $currency_code ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Validate currency code
		if ( empty( $currency_code ) ) {
			return new WP_Error( 'no_currency_code', 'Currency code is required' );
		}

		// Prepare request data
		$request_data = array(
			'customer_currency' => sanitize_text_field( $currency_code ),
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency_put' );
		if ( empty( $endpoint_url ) ) {
			// Fallback to generic product_currency endpoint
			$endpoint_url = Vortem_Config::get_api_endpoint( 'product_currency' );
		}
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Currency endpoint not configured' );
		}

		// Make API request to update currency (PUT method)
		$response = $this->make_request( $endpoint_url, $request_data, 'PUT', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Send order to AliExpress
	 *
	 * @param array $order_data Order data including logistics_address and product_items
	 * @return array|WP_Error
	 */
	public function send_order_to_aliexpress( $order_data ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Validate order data
		if ( empty( $order_data ) || ! is_array( $order_data ) ) {
			return new WP_Error( 'no_order_data', 'Order data is required' );
		}

		// Endpoint for AliExpress order
		$endpoint_url = '/api/v1/customer/aliexpress/order';

		// Make API request
		$response = $this->make_request( $endpoint_url, $order_data, 'POST', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Get AliExpress OAuth authorization URL
	 *
	 * @return array|WP_Error
	 */
	public function get_aliexpress_auth_url() {
		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'aliexpress_auth_authorize' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'AliExpress auth authorize endpoint not configured' );
		}

		// Make API request (GET method)
		$response = $this->make_request( $endpoint_url, array(), 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Get AliExpress authentication status
	 *
	 * @return array|WP_Error
	 */
	public function get_aliexpress_auth_status() {
		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'aliexpress_auth_status' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'AliExpress auth status endpoint not configured' );
		}

		// Make API request (GET method)
		$response = $this->make_request( $endpoint_url, array(), 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Delete AliExpress authentication
	 *
	 * @return array|WP_Error
	 */
	public function delete_aliexpress_auth() {
		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'aliexpress_auth_delete' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'AliExpress auth delete endpoint not configured' );
		}

		// Make API request (DELETE method)
		$response = $this->make_request( $endpoint_url, array(), 'DELETE', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch imported products from API
	 * Returns list of imported products with _id and product_id mapping
	 *
	 * @param array $params Fetch parameters (page, limit)
	 * @return array|WP_Error
	 */
	public function fetch_imported_products( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Prepare request data (support page and limit)
		$request_data = array(
			'page'  => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
			'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 10,
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_top_imported' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Imported products endpoint not configured' );
		}

		// Make API request to the imported products endpoint (GET method)
		$response = $this->make_request( $endpoint_url, $request_data, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Delete imported product from API
	 * Deletes a product using its _id from the imported products list
	 *
	 * @param string $product_id Product _id to delete
	 * @return array|WP_Error
	 */
	public function delete_imported_product( $product_id ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Validate product _id
		if ( empty( $product_id ) ) {
			return new WP_Error( 'no_product_id', 'Product _id is required' );
		}

		// Get endpoint URL and replace {_id} placeholder
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_top_imported_delete' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Delete imported product endpoint not configured' );
		}

		// Replace {_id} placeholder with actual product _id
		$endpoint_url = str_replace( '{_id}', $product_id, $endpoint_url );

		// Make API request to delete the imported product (DELETE method)
		$response = $this->make_request( $endpoint_url, array(), 'DELETE', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch trend products from API
	 *
	 * @param array $params Fetch parameters (page, limit)
	 * @return array|WP_Error
	 */
	public function fetch_trend_products( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Prepare request data. Backend expects `per_page` (not `limit`):
		// GET /api/v1/customer/product/dashboard/products/trend_products?page=1&per_page=30
		$default_limit = get_option( 'vortem_products_per_page', 20 );
		$request_data  = array(
			'page'     => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
			'per_page' => isset( $params['limit'] ) ? intval( $params['limit'] ) : $default_limit,
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'bi_analytics_products_trend_products' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Trend products endpoint not configured' );
		}

		// Make API request (GET method with query parameters)
		$response = $this->make_request( $endpoint_url, $request_data, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch TikTok products from API
	 * Returns list of trending TikTok products
	 *
	 * @param array $params Optional parameters: page, limit (page_size)
	 * @return array|WP_Error
	 */
	public function fetch_tiktok_products( $params = array() ) {

		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Prepare request data — TikTok endpoint expects `page` and `limit` query params.
		$default_limit = get_option( 'vortem_products_per_page', 12 );
		$request_data  = array(
			'page'  => isset( $params['page'] ) ? intval( $params['page'] ) : 1,
			'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : $default_limit,
		);

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'bi_analytics_products_tiktok' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'TikTok products endpoint not configured' );
		}

		// Make API request (GET method with query parameters)
		$response = $this->make_request( $endpoint_url, $request_data, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch categories from API
	 * Returns list of categories with subcategories
	 *
	 * @param array $params Optional parameters: page, limit
	 * @return array|WP_Error
	 */
	public function fetch_categories( $params = array() ) {
		// Check if API URL is available
		if ( empty( $this->api_url ) ) {
			return new WP_Error( 'no_api_url', 'No API server available. Please check your server configuration.' );
		}

		// Get endpoint URL and validate
		$endpoint_url = Vortem_Config::get_api_endpoint( 'product_get_categories' );
		if ( empty( $endpoint_url ) ) {
			return new WP_Error( 'invalid_endpoint', 'Categories endpoint not configured' );
		}

		// Prepare query parameters for pagination
		$query_params = array();
		if ( isset( $params['page'] ) ) {
			$query_params['page'] = intval( $params['page'] );
		}
		if ( isset( $params['limit'] ) ) {
			$query_params['limit'] = intval( $params['limit'] );
		}

		// Make API request to the categories endpoint (GET method)
		// make_request will automatically add query params to URL for GET requests
		$response = $this->make_request( $endpoint_url, $query_params, 'GET', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Fetch SEO content for a product
	 *
	 * @param string $product_id The original SKU/product ID (e.g., "AE_1005010280379408")
	 * @return array|WP_Error SEO content with seo_title, seo_description, and tags, or WP_Error on failure
	 */
	public function get_product_seo_content( $product_id ) {
		// Phone-home gate.
		if ( ! self::has_consent() ) {
			return self::consent_required_error();
		}

		if ( empty( $product_id ) ) {
			return new WP_Error( 'invalid_product_id', 'Product ID is required' );
		}

		// Build the endpoint URL with query parameter
		$endpoint = '/api/v1/product/top/get_product?type=seo-content';

		// Prepare request body
		$request_data = array(
			'product_id' => $product_id,
		);

		// Prepare headers
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

		// Use the SEO API endpoint (c.vortem.ai)
		$seo_api_url = 'https://c.vortem.ai';
		$url         = rtrim( $seo_api_url, '/' ) . $endpoint;

		$args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => wp_json_encode( $request_data ),
			'timeout'   => 600, // 10 minutes timeout for SEO content API
			'sslverify' => true,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = 'API request failed with status: ' . $status_code;
			if ( ! empty( $body ) ) {
				$decoded_error = json_decode( $body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
					if ( isset( $decoded_error['message'] ) ) {
						$error_message .= ' - ' . $decoded_error['message'];
					} else {
						$error_message .= ' - Response: ' . substr( $body, 0, 200 );
					}
				} else {
					$error_message .= ' - Response: ' . substr( $body, 0, 200 );
				}
			}
			return new WP_Error(
				'api_error',
				$error_message,
				array(
					'status_code'   => $status_code,
					'response_body' => $body,
				)
			);
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON response from API: ' . json_last_error_msg() );
		}

		// Check if response is successful and has data
		if ( isset( $decoded_response['success'] ) && $decoded_response['success'] && isset( $decoded_response['data'] ) ) {
			return $decoded_response['data'];
		}

		// Return error if not successful
		$error_message = isset( $decoded_response['message'] ) ? $decoded_response['message'] : 'Unknown error';
		return new WP_Error( 'api_error', $error_message, $decoded_response );
	}
}
