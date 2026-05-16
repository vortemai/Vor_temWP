<?php
/**
 * Vortem Email Marketing API Client Class
 *
 * Handles API communication for Email Marketing endpoints
 *
 * External Dependencies Used:
 * - WordPress HTTP API - wp_remote_request(), wp_remote_retrieve_body(), wp_remote_retrieve_response_code() for email marketing API calls
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load required classes
require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

/**
 * Vortem Email Marketing API Client
 */
class Vortem_Email_Marketing_Api {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_url = rtrim( Vortem_Config::get_primary_api_server(), '/' );
	}

	/**
	 * Build endpoint URL with ID replacement
	 *
	 * @param string      $endpoint_key Endpoint key from config
	 * @param string|null $id Optional ID to replace :id placeholder
	 * @return string
	 */
	private function build_endpoint_url( $endpoint_key, $id = null ) {
		$endpoint = Vortem_Config::get_api_endpoint( $endpoint_key );
		if ( $id !== null && strpos( $endpoint, ':id' ) !== false ) {
			$endpoint = str_replace( ':id', $id, $endpoint );
		}
		return $this->api_url . $endpoint;
	}

	/**
	 * Make API request
	 *
	 * @param string     $url Full URL
	 * @param string     $method HTTP method
	 * @param array|null $data Request data
	 * @return array|WP_Error
	 */
	private function make_request( $url, $method = 'GET', $data = null ) {
		// Phone-home gate: refuse every API call until the admin has consented.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return Vortem_Api_Client::consent_required_error();
		}

		// Simple headers with only Content-Type and Referer
		$headers = array(
			'Content-Type' => 'application/json',
			'Referer'      => home_url(),
		);

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

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$body        = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 400 ) {
			$error_message = 'API request failed with status: ' . $status_code;
			$decoded_error = json_decode( $body, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_error ) ) {
				// Try to extract detailed validation error messages
				if ( isset( $decoded_error['errors'] ) && is_array( $decoded_error['errors'] ) ) {
					// Handle validation errors format: {"errors": {"Field": ["Error message"]}}
					$error_messages = array();
					foreach ( $decoded_error['errors'] as $field => $field_errors ) {
						if ( is_array( $field_errors ) ) {
							$error_messages[] = $field . ': ' . implode( ', ', $field_errors );
						} else {
							$error_messages[] = $field . ': ' . $field_errors;
						}
					}
					if ( ! empty( $error_messages ) ) {
						$error_message = implode( '; ', $error_messages );
					}
				}

				// Fallback to message or error field
				if ( ( $error_message === 'API request failed with status: ' . $status_code ) && isset( $decoded_error['message'] ) ) {
					$error_message = $decoded_error['message'];
				} elseif ( ( $error_message === 'API request failed with status: ' . $status_code ) && isset( $decoded_error['error'] ) ) {
					$error_message = $decoded_error['error'];
				}
			}
			return new WP_Error( 'api_error', $error_message, array( 'status_code' => $status_code ) );
		}

		$decoded_response = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', 'Failed to decode JSON response' );
		}

		return $this->unwrap_response( $decoded_response );
	}

	/**
	 * Normalize backend responses.
	 *
	 * Several email_marketing endpoints wrap their payload as
	 * `{ success: true, message: "...", data: { ... } }`. Unwrap to the inner
	 * `data` so callers see a single, flat shape. Surface 200-OK responses
	 * with `success: false` as a WP_Error since they otherwise slip past the
	 * status-code check above.
	 *
	 * @param mixed $response Decoded JSON payload.
	 * @return mixed|WP_Error
	 */
	private function unwrap_response( $response ) {
		if ( ! is_array( $response ) ) {
			return $response;
		}
		if ( isset( $response['success'] ) && false === $response['success'] ) {
			$message = isset( $response['message'] ) && is_string( $response['message'] ) && $response['message'] !== ''
				? $response['message']
				: ( isset( $response['error'] ) && is_string( $response['error'] ) ? $response['error'] : 'API error' );
			return new WP_Error( 'api_error', $message );
		}
		if ( isset( $response['success'], $response['data'] ) && true === $response['success'] && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		return $response;
	}

	/**
	 * Get the customer's emails and email lists.
	 *
	 * Backend: `GET /api/v1/customer/email_marketing/me` returns
	 * `{ emails, email_count, email_lists, email_list_count }`.
	 *
	 * @return array|WP_Error
	 */
	public function get_emails() {
		$url = $this->build_endpoint_url( 'email_marketing_me' );
		return $this->make_request( $url, 'GET' );
	}

	/**
	 * Search email marketing campaigns
	 *
	 * @param array $params Search parameters
	 * @return array|WP_Error
	 */
	public function search_emails( $params = array() ) {
		$url = $this->build_endpoint_url( 'email_marketing_search' );
		return $this->make_request( $url, 'GET', $params );
	}

	/**
	 * Get a specific email marketing campaign
	 *
	 * @param string $id Campaign ID
	 * @return array|WP_Error
	 */
	public function get_email( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_get', $id );
		return $this->make_request( $url, 'GET' );
	}

	/**
	 * Get email marketing campaign status
	 *
	 * @param string $id Campaign ID
	 * @return array|WP_Error
	 */
	public function get_email_status( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_status', $id );
		return $this->make_request( $url, 'GET' );
	}

	/**
	 * Create a new email marketing campaign
	 *
	 * @param array $data Campaign data
	 * @return array|WP_Error
	 */
	public function create_email( $data ) {
		$url = $this->build_endpoint_url( 'email_marketing_create' );
		return $this->make_request( $url, 'POST', $data );
	}

	/**
	 * Update an existing email marketing campaign
	 *
	 * @param string $id Campaign ID
	 * @param array  $data Campaign data
	 * @return array|WP_Error
	 */
	public function update_email( $id, $data ) {
		$url = $this->build_endpoint_url( 'email_marketing_update', $id );
		return $this->make_request( $url, 'PUT', $data );
	}

	/**
	 * Delete an email.
	 *
	 * Backend: `DELETE /api/v1/customer/email_marketing` with body
	 * `{ "email_id": "<id>" }` (no path id).
	 *
	 * @param string $id Email ID.
	 * @return array|WP_Error
	 */
	public function delete_email( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_base' );
		return $this->make_request( $url, 'DELETE', array( 'email_id' => (string) $id ) );
	}

	/**
	 * Bulk delete emails.
	 *
	 * Backend: `DELETE /api/v1/customer/email_marketing/bulk` with body
	 * `{ "list_email_id": ["<id1>", "<id2>", ...] }`.
	 *
	 * @param array $ids Email IDs.
	 * @return array|WP_Error
	 */
	public function bulk_delete_emails( $ids ) {
		$url = $this->build_endpoint_url( 'email_marketing_bulk_delete' );
		return $this->make_request(
			$url,
			'DELETE',
			array( 'list_email_id' => array_values( array_map( 'strval', (array) $ids ) ) )
		);
	}

	/**
	 * Send an email marketing campaign to its single recipient
	 *
	 * @param string $id Campaign ID
	 * @return array|WP_Error
	 */
	public function send_email( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_send', $id );
		return $this->make_request( $url, 'POST' );
	}

	/**
	 * Send an email marketing campaign to a list of recipients
	 *
	 * @param string $id Campaign ID
	 * @return array|WP_Error
	 */
	public function send_email_list( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_send_list', $id );
		return $this->make_request( $url, 'POST' );
	}

	/**
	 * Get email marketing usage statistics
	 *
	 * @return array|WP_Error
	 */
	public function get_useg() {
		$url = $this->build_endpoint_url( 'email_marketing_useg' );
		return $this->make_request( $url, 'GET' );
	}

	/**
	 * Get the user's saved email lists (campaigns with multiple recipients).
	 *
	 * @return array|WP_Error
	 */
	public function get_email_lists() {
		$url = $this->build_endpoint_url( 'email_marketing_email_lists' );
		return $this->make_request( $url, 'GET' );
	}

	/**
	 * Create a new email list (multi-recipient).
	 *
	 * Backend: `POST /api/v1/customer/email_marketing/emails_list` with body
	 * `{ email_subject, email_recipients[], email_content }`.
	 *
	 * @param array $data Email list data.
	 * @return array|WP_Error
	 */
	public function create_email_list( $data ) {
		$url = $this->build_endpoint_url( 'email_marketing_emails_list' );
		return $this->make_request( $url, 'POST', $data );
	}

	/**
	 * Update an email list.
	 *
	 * Backend: `PUT /api/v1/customer/email_marketing/emails_list` with body
	 * `{ email_list_id, email_subject, email_recipients[], email_content }`.
	 * The id is in the body, not the path.
	 *
	 * @param array $data Email list data; must include `email_list_id`.
	 * @return array|WP_Error
	 */
	public function update_email_list( $data ) {
		if ( empty( $data['email_list_id'] ) ) {
			return new WP_Error( 'missing_id', 'Email list ID is required for update.' );
		}
		$url = $this->build_endpoint_url( 'email_marketing_emails_list' );
		return $this->make_request( $url, 'PUT', $data );
	}

	/**
	 * Delete an email list.
	 *
	 * Backend: `DELETE /api/v1/customer/email_marketing/emails_list` with body
	 * `{ "email_list_id": "<id>" }`.
	 *
	 * @param string $id Email list ID.
	 * @return array|WP_Error
	 */
	public function delete_email_list( $id ) {
		$url = $this->build_endpoint_url( 'email_marketing_emails_list' );
		return $this->make_request( $url, 'DELETE', array( 'email_list_id' => (string) $id ) );
	}
}
