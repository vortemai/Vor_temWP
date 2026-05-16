<?php
/**
 * Vortem Import Handler Class
 *
 * Handles product import from Vortem backend instead of sync
 *
 * External Dependencies Used:
 * - WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | wc_get_product() for retrieving imported product details
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Import Handler
 */
class Vortem_Import_Handler {

	/**
	 * API client instance
	 *
	 * @var Vortem_Api_Client
	 */
	private $api_client;


	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_client = new Vortem_Api_Client();

		// Hook into admin AJAX for import functionality
		add_action( 'wp_ajax_vortem_import_product', array( $this, 'ajax_import_product' ) );
		add_action( 'wp_ajax_vortem_get_import_status', array( $this, 'ajax_get_import_status' ) );
		add_action( 'wp_ajax_vortem_reset_import', array( $this, 'ajax_reset_import' ) );

		// Register REST API endpoint for backend communication
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Register the import endpoint
		register_rest_route(
			'vortem/v1',
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_import_request' ),
				'permission_callback' => array( $this, 'check_import_permissions' ),
				'args'                => array(
					'product_data' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => 'JSON string containing complete product data',
					),
					'images'       => array(
						'required'    => false,
						'type'        => 'object',
						'description' => 'Base64 encoded images with file keys',
					),
				),
			)
		);
	}

	/**
	 * Check import permissions
	 * No authentication required - only checks user capabilities
	 */
	public function check_import_permissions( $request ) {
		// Check if current user has required capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_products' ) ) {
			return new WP_Error( 'forbidden', 'You do not have permission to import products.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle import request from backend
	 */
	public function handle_import_request( $request ) {
		try {
			// Check if current user has required capabilities
			if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_products' ) ) {
				return new WP_Error( 'forbidden', 'You do not have permission to import products.', array( 'status' => 403 ) );
			}

			// Get current user info for logging
			$current_user = wp_get_current_user();
			$imported_by  = $current_user->user_login;

			$product_data_json = $request->get_param( 'product_data' );
			$images_data       = $request->get_param( 'images' );

			if ( ! $product_data_json ) {
				return new WP_Error( 'missing_product_data', 'Product data is required.', array( 'status' => 400 ) );
			}

			$product_data_json = wp_unslash( $product_data_json );
			$data              = json_decode( $product_data_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'invalid_json', 'Invalid JSON in product data: ' . json_last_error_msg(), array( 'status' => 400 ) );
			}

			// Process images if provided
			$processed_images = array();
			if ( ! empty( $images_data ) && is_array( $images_data ) ) {
				$processed_images = $this->process_import_images( $images_data );
			}

			// Add processed images to product data
			$data['image_paths'] = $processed_images;

			// Create WooCommerce product using existing product creator
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-creator.php';
			$product_creator = new Vortem_Product_Creator();

			$result = $product_creator->create_product( $data, array() );

			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'code'    => $result->get_error_code(),
						'message' => $result->get_error_message(),
					),
					$result->get_error_data()['status'] ?? 500
				);
			}

			// Get product details for response
			$product = wc_get_product( $result );

			// If theme allows video injection and product has video, inject video link to description
			if ( $this->should_inject_video_to_description() ) {
				$this->inject_video_to_description( $result );
			}

			$response_data = array(
				'success'          => true,
				'message'          => 'Product imported successfully',
				'product_id'       => $result,
				'product_name'     => $product->get_name(),
				'product_sku'      => $product->get_sku(),
				'product_url'      => get_permalink( $result ),
				'images_processed' => count( $processed_images ),
				'imported_by'      => $imported_by,
			);

			return new WP_REST_Response( $response_data, 201 );

		} catch ( Exception $e ) {
			return new WP_Error( 'import_error', 'Import failed: ' . $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Process import images
	 */
	private function process_import_images( $images_data ) {
		$processed_images = array();

		foreach ( $images_data as $key => $image_data ) {
			if ( empty( $image_data ) || ! is_string( $image_data ) ) {
				continue;
			}

			// Decode base64 image. The decoded payload is then validated with
			// getimagesizefromstring() before any disk write, so an attacker
			// cannot smuggle non-image content past this check.
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding incoming product image payloads from the trusted API; output is validated immediately below.
			$image_binary = base64_decode( $image_data );
			if ( $image_binary === false ) {
				continue;
			}

			// Validate image type
			$image_info = getimagesizefromstring( $image_binary );
			if ( $image_info === false ) {
				continue;
			}

			// Generate unique filename
			$upload_dir     = wp_upload_dir();
			$file_extension = 'jpg'; // Default to jpg
			if ( isset( $image_info['mime'] ) ) {
				$mime_to_ext    = array(
					'image/jpeg' => 'jpg',
					'image/png'  => 'png',
					'image/gif'  => 'gif',
					'image/webp' => 'webp',
				);
				$file_extension = $mime_to_ext[ $image_info['mime'] ] ?? 'jpg';
			}

			$unique_filename = $key . '_' . time() . '.' . $file_extension;

			// Ensure upload directory exists
			if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
				continue;
			}

			// Create file path
			$file_path = $upload_dir['path'] . '/' . sanitize_file_name( $unique_filename );

			// Validate that file path is within upload directory
			$real_upload_path = realpath( $upload_dir['basedir'] );
			$real_file_path   = realpath( dirname( $file_path ) );
			if ( $real_file_path === false || $real_upload_path === false || strpos( $real_file_path, $real_upload_path ) !== 0 ) {
				continue;
			}

			// Save image file. The destination path is realpath-validated
			// above to be inside wp_upload_dir().
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a validated image payload into wp_upload_dir().
			$file_saved = file_put_contents( $file_path, $image_binary );

			if ( $file_saved === false ) {
				continue;
			}

			// Prepare attachment data
			$attachment = array(
				'post_mime_type' => $image_info['mime'],
				'post_title'     => sanitize_file_name( $key ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			// Insert attachment into database
			$attachment_id = wp_insert_attachment( $attachment, $file_path );

			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_file( $file_path ); // Clean up file
				continue;
			}

			// Generate attachment metadata
			// Note: wp_generate_attachment_metadata() is available in admin/AJAX context
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			if ( ! is_wp_error( $attachment_data ) ) {
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
			}

			// Store the attachment ID for the product creator
			$processed_images[ $key ] = $attachment_id;
		}

		return $processed_images;
	}

	/**
	 * AJAX handler for importing a single product
	 * Note: This method has been disabled as the /api/v1/product/import endpoint has been removed.
	 * Use the fetch + import workflow instead via ajax_import_fetched_products.
	 */
	public function ajax_import_product() {
		wp_send_json_error( 'This import method has been disabled. Please use the "Fetch Products" and "Import Fetched Products" workflow instead.' );
	}

	/**
	 * AJAX handler for getting import status
	 */
	public function ajax_get_import_status() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
			return;
		}

		$status = array(
			'last_import'   => get_option( 'vortem_last_import_date', null ),
			'total_imports' => $this->get_total_imports_count(),
		);

		wp_send_json_success( $status );
	}

	/**
	 * AJAX handler for resetting import status
	 */
	public function ajax_reset_import() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
			return;
		}

		delete_option( 'vortem_last_import_date' );
		delete_option( 'vortem_import_count' );

		wp_send_json_success( 'Import status reset successfully' );
	}

	/**
	 * Get total imports count
	 */
	private function get_total_imports_count() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- COUNT query; tables from $wpdb
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts}
				WHERE post_type = 'product'
				AND post_status = %s
				AND ID IN (
					SELECT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key = %s
					AND meta_value = %s
				)",
				'draft',
				'_vortem_imported',
				'1'
			)
		);

		return intval( $count );
	}

	/**
	 * Check if Woodmart theme is active
	 *
	 * @return bool True if Woodmart theme is active
	 */
	private function is_woodmart_theme_active() {
		$current_theme = wp_get_theme();
		$theme_name    = $current_theme->get( 'Name' );
		$template      = $current_theme->get( 'Template' );

		return (
			stripos( $theme_name, 'woodmart' ) !== false ||
			stripos( $template, 'woodmart' ) !== false ||
			function_exists( 'woodmart_get_opt' )
		);
	}

	/**
	 * Check if video should be injected to description based on active theme and settings
	 *
	 * Excludes: Vortem Clothes, Vortem Cosmetic, xstore
	 * Includes: All other themes
	 *
	 * @return bool True if video should be injected
	 */
	private function should_inject_video_to_description() {
		// Check global setting first - if disabled, don't inject video
		$add_video_to_description = get_option( 'vortem_add_video_to_description', true );
		if ( ! $add_video_to_description ) {
			return false;
		}

		$current_theme = wp_get_theme();
		$theme_name    = $current_theme->get( 'Name' );
		$template      = $current_theme->get( 'Template' );

		// Convert to lowercase for case-insensitive comparison
		$theme_name_lower = strtolower( $theme_name );
		$template_lower   = strtolower( $template );

		// Excluded themes
		$excluded_themes = array( 'vortem clothes', 'vortem cosmetic', 'xstore' );

		// Check if current theme is in excluded list
		foreach ( $excluded_themes as $excluded ) {
			if ( stripos( $theme_name_lower, $excluded ) !== false || stripos( $template_lower, $excluded ) !== false ) {
				return false;
			}
		}

		// For all other themes, allow video injection
		return true;
	}

	/**
	 * Check if video should be downloaded for excluded themes
	 *
	 * @return bool True if video should be downloaded for excluded themes
	 */
	private function should_download_video_for_excluded_themes() {
		return get_option( 'vortem_download_video_for_excluded_themes', true );
	}

	/**
	 * Check if current theme is in excluded list
	 *
	 * @return bool True if current theme is excluded
	 */
	private function is_excluded_theme() {
		$current_theme = wp_get_theme();
		$theme_name    = $current_theme->get( 'Name' );
		$template      = $current_theme->get( 'Template' );

		// Convert to lowercase for case-insensitive comparison
		$theme_name_lower = strtolower( $theme_name );
		$template_lower   = strtolower( $template );

		// Excluded themes
		$excluded_themes = array( 'vortem clothes', 'vortem cosmetic', 'xstore' );

		// Check if current theme is in excluded list
		foreach ( $excluded_themes as $excluded ) {
			if ( stripos( $theme_name_lower, $excluded ) !== false || stripos( $template_lower, $excluded ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inject video link to the beginning of product description
	 *
	 * @param int $product_id Product ID
	 */
	private function inject_video_to_description( $product_id ) {
		// Check if video should be injected based on theme
		if ( ! $this->should_inject_video_to_description() ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$video_url = get_post_meta( $product_id, '_vortem_video_url', true );
		if ( empty( $video_url ) || ! filter_var( $video_url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		$description = $product->get_description();
		if ( empty( $description ) ) {
			$description = '';
		}

		if ( strpos( $description, $video_url ) !== false ) {
			return;
		}

		$video_html      = '<p><a href="' . esc_url( $video_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Watch Product Video', 'vortem-ai' ) . '</a></p>';
		$new_description = $video_html . "\n\n" . $description;

		$product->set_description( $new_description );
		$product->save();
	}
}
