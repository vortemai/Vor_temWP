<?php
/**
 * Vortem Product Manager Class
 *
 * Handles product synchronization from Vortem to WooCommerce
 *
 * External Dependencies Used:
 * - WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | wc_get_product(), wc_get_products(), wc_get_product_id_by_sku()
 * - WordPress HTTP API - wp_remote_get(), wp_remote_retrieve_response_code(), wp_remote_retrieve_body() for downloading product images
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Product Manager
 */
class Vortem_Product_Manager {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from $wpdb->prefix; product sync operations.

	/**
	 * API client instance
	 *
	 * @var Vortem_Api_Client
	 */
	private $api_client;


	/**
	 * Sync batch size
	 *
	 * @var int
	 */
	private $batch_size;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_client = new Vortem_Api_Client();
		$this->batch_size = get_option( 'vortem_batch_size', 20 );

		// Hook into WordPress cron
		add_action( 'vortem_sync_products', array( $this, 'cron_sync_products' ) );

		// Hook into admin AJAX
		add_action( 'wp_ajax_vortem_sync_products', array( $this, 'ajax_sync_products' ) );
		add_action( 'wp_ajax_vortem_get_sync_status', array( $this, 'ajax_get_sync_status' ) );
		add_action( 'wp_ajax_vortem_reset_sync', array( $this, 'ajax_reset_sync' ) );
		add_action( 'wp_ajax_vortem_trash_product', array( $this, 'ajax_trash_product' ) );
		add_action( 'wp_ajax_vortem_restore_product', array( $this, 'ajax_restore_product' ) );
		add_action( 'wp_ajax_vortem_import_single', array( $this, 'ajax_import_single' ) );
		add_action( 'wp_ajax_vortem_bulk_action', array( $this, 'ajax_bulk_action' ) );
		// Note: vortem_get_product_details is handled by admin class to avoid conflicts
		add_action( 'wp_ajax_vortem_get_product_json', array( $this, 'ajax_get_product_json' ) );
		add_action( 'wp_ajax_vortem_import_as_draft', array( $this, 'ajax_import_as_draft' ) );
		add_action( 'wp_ajax_vortem_disable_auto_sync', array( $this, 'ajax_disable_auto_sync' ) );
		add_action( 'wp_ajax_vortem_clear_sync_status', array( $this, 'ajax_clear_sync_status' ) );
		add_action( 'wp_ajax_vortem_cleanup_orphaned', array( $this, 'ajax_cleanup_orphaned' ) );
		add_action( 'wp_ajax_vortem_clear_stuck_transients', array( $this, 'ajax_clear_stuck_transients' ) );
	}

	/**
	 * Sync products from Vortem API
	 *
	 * @param array $options Sync options
	 * @return array
	 */
	public function sync_products( $options = array() ) {
		$defaults = array(
			'force_sync'      => false,
			'batch_size'      => $this->batch_size,
			'resume_from'     => 0,
			'max_products'    => null,
			'sync_images'     => true,
			'sync_categories' => true,
			'dry_run'         => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Log sync start
		$this->log_sync_event(
			'sync_started',
			array(
				'options'   => $options,
				'timestamp' => current_time( 'mysql' ),
			)
		);

		// Check if sync is already running
		if ( $this->is_sync_running() ) {
			$error_msg = 'Sync already in progress';
			$this->log_sync_event( 'sync_skipped', array( 'reason' => $error_msg ) );

			// Clear the sync status if it's been running for more than 5 minutes
			$sync_status = get_option( 'vortem_sync_status', array() );
			if ( isset( $sync_status['started_at'] ) ) {
				$started_at   = strtotime( $sync_status['started_at'] );
				$current_time = time();
				if ( ( $current_time - $started_at ) > 300 ) { // 5 minutes
					$this->set_sync_status( 'completed' );
				} else {
					return array(
						'success' => false,
						'message' => $error_msg,
					);
				}
			} else {
				return array(
					'success' => false,
					'message' => $error_msg,
				);
			}
		}

		// Mark sync as running
		$this->set_sync_status( 'running' );

		try {
			// Get products from API using new sync endpoint
			$api_params = array(
				'limit'        => $options['batch_size'],
				'category'     => isset( $options['category'] ) ? $options['category'] : '',
				'source'       => isset( $options['source'] ) ? $options['source'] : '',
				'min_price'    => isset( $options['min_price'] ) ? $options['min_price'] : 0,
				'max_price'    => isset( $options['max_price'] ) ? $options['max_price'] : 0,
				'min_score'    => isset( $options['min_score'] ) ? $options['min_score'] : 0,
				'search_query' => isset( $options['search_query'] ) ? $options['search_query'] : '',
			);

			$response = $this->api_client->sync_products( $api_params );

			// Debug logging

			if ( is_wp_error( $response ) ) {
				$error_msg = 'API error: ' . $response->get_error_message();
				$this->log_sync_event( 'sync_failed', array( 'error' => $error_msg ) );
				$this->set_sync_status( 'failed' );
				return array(
					'success' => false,
					'message' => $error_msg,
				);
			}

			// Handle new sync response format
			if ( ! isset( $response['success'] ) || ! $response['success'] ) {
				$error_msg = isset( $response['message'] ) ? $response['message'] : 'Unknown API error';
				$this->log_sync_event( 'sync_failed', array( 'error' => $error_msg ) );
				$this->set_sync_status( 'failed' );
				return array(
					'success' => false,
					'message' => $error_msg,
				);
			}

			$products       = isset( $response['products'] ) ? $response['products'] : array();
			$sync_result    = isset( $response['sync_result'] ) ? $response['sync_result'] : array();
			$total_products = isset( $sync_result['total_fetched'] ) ? $sync_result['total_fetched'] : count( $products );

			// Debug logging

			// Check if there are no products to sync
			if ( empty( $products ) && 0 === (int) $total_products ) {
				$this->set_sync_status( 'completed' );
				$this->log_sync_event( 'sync_no_products', array( 'message' => 'No new products available to sync' ) );
				return array(
					'success'         => true,
					'synced'          => 0,
					'updated'         => 0,
					'skipped'         => 0,
					'errors'          => 0,
					'total'           => 0,
					'total_available' => 0,
					'sync_date'       => current_time( 'mysql' ),
					'message'         => 'No new products available to sync. All products are already up to date.',
				);
			}

			$synced_count  = 0;
			$skipped_count = 0;
			$error_count   = 0;
			$updated_count = 0;

			// Process each product - handle new StandardProductData format
			foreach ( $products as $index => $product_data ) {

				// Convert StandardProductData format to expected format
				$converted_product = $this->convert_standard_product_data( $product_data );

				// ALWAYS save to database for display - NEVER create WooCommerce products
				$result = $this->save_product_to_database( $converted_product );

				switch ( $result['status'] ) {
					case 'synced':
						++$synced_count;
						break;
					case 'updated':
						++$updated_count;
						break;
					case 'skipped':
						++$skipped_count;
						break;
					case 'failed':
						++$error_count;
						break;
				}
			}

			// Mark sync as completed
			$this->set_sync_status( 'completed' );

			$result = array(
				'success'         => true,
				'synced'          => $synced_count,
				'updated'         => $updated_count,
				'skipped'         => $skipped_count,
				'errors'          => $error_count,
				'total'           => count( $products ),
				'total_available' => $total_products,
				'sync_date'       => current_time( 'mysql' ),
			);

			$this->log_sync_event( 'sync_completed', $result );

			return $result;

		} catch ( Exception $e ) {
			$error_msg = 'Sync exception: ' . $e->getMessage();
			$this->log_sync_event( 'sync_failed', array( 'error' => $error_msg ) );
			$this->set_sync_status( 'failed' );
			return array(
				'success' => false,
				'message' => $error_msg,
			);
		}
	}

	/**
	 * Convert StandardProductData format to expected format
	 *
	 * @param array $standard_product StandardProductData from backend
	 * @return array Converted product data
	 */
	public function convert_standard_product_data( $standard_product ) {
		// Extract pricing from API response
		$regular_price  = '0.00';
		$sale_price     = null;
		$stock_quantity = 0;

		// Check if pricing is in variations (old format)
		if ( isset( $standard_product['variations'] ) && is_array( $standard_product['variations'] ) && ! empty( $standard_product['variations'] ) ) {
			$variation      = $standard_product['variations'][0];
			$regular_price  = $variation['regular_price'];
			$sale_price     = isset( $variation['sale_price'] ) && ! empty( $variation['sale_price'] ) ? $variation['sale_price'] : null;
			$stock_quantity = isset( $variation['stock_quantity'] ) ? intval( $variation['stock_quantity'] ) : 0;
		}
		// Check if pricing is in price object (new format)
		elseif ( isset( $standard_product['price'] ) ) {
			// Check for high_price and low_price fields
			if ( isset( $standard_product['price']['high_price'] ) && ! empty( $standard_product['price']['high_price'] ) ) {
				$regular_price = $standard_product['price']['high_price'];
			} else {
				$regular_price = $standard_product['price']['original'] ?? '0.00';
			}
			if ( isset( $standard_product['price']['low_price'] ) && ! empty( $standard_product['price']['low_price'] ) ) {
				$sale_price = $standard_product['price']['low_price'];
			} else {
				$sale_price = isset( $standard_product['price']['sale'] ) && ! empty( $standard_product['price']['sale'] ) ? $standard_product['price']['sale'] : null;
			}
		}

		// Extract image URLs from API response
		$image_urls = array();
		if ( isset( $standard_product['images']['main'] ) && ! empty( $standard_product['images']['main'] ) ) {
			$image_urls[] = $standard_product['images']['main'];
		}
		if ( isset( $standard_product['images']['gallery'] ) && is_array( $standard_product['images']['gallery'] ) ) {
			$image_urls = array_merge( $image_urls, $standard_product['images']['gallery'] );
		}

		// Convert attributes to simple key-value pairs
		$attributes = array();
		if ( isset( $standard_product['attributes'] ) && is_array( $standard_product['attributes'] ) ) {
			foreach ( $standard_product['attributes'] as $attr ) {
				if ( isset( $attr['name'] ) && isset( $attr['options'] ) && ! empty( $attr['options'] ) ) {
					$attributes[ $attr['name'] ] = $attr['options'][0]; // Use first option
				}
			}
		}

		// Extract SKU from product_id (API uses product_id as SKU)
		$sku = $standard_product['product_id'] ?? $standard_product['sku'] ?? '';

		// Extract short description from description if not provided
		$short_description = $standard_product['short_description'] ?? '';
		if ( empty( $short_description ) && ! empty( $standard_product['description'] ) ) {
			// Extract first sentence or first 150 characters as short description
			$description = wp_strip_all_tags( $standard_product['description'] );
			$sentences   = preg_split( '/[.!?]+/', $description );
			if ( ! empty( $sentences[0] ) ) {
				$short_description = trim( $sentences[0] );
				if ( strlen( $short_description ) > 150 ) {
					$short_description = substr( $short_description, 0, 147 ) . '...';
				}
			}
		}

		return array(
			'id'                => $sku, // Use SKU as ID
			'title'             => $standard_product['title'] ?? $standard_product['name'] ?? 'Untitled Product',
			'description'       => $standard_product['description'] ?? '',
			'short_description' => $short_description,
			'sku'               => $sku,
			'weight'            => $standard_product['weight'] ?? '0.5',
			'length'            => $standard_product['dimensions']['length'] ?? '10',
			'width'             => $standard_product['dimensions']['width'] ?? '10',
			'height'            => $standard_product['dimensions']['height'] ?? '5',
			'categories'        => $standard_product['categories'] ?? array(),
			'tags'              => $standard_product['tags'] ?? array(),
			'images'            => array(
				'main'     => $standard_product['images']['main'] ?? '',
				'gallery'  => $standard_product['images']['gallery'] ?? array(),
				'variants' => $standard_product['images']['variants'] ?? array(),
				'urls'     => $image_urls,
			),
			'variations'        => $standard_product['variations'] ?? array(),
			'attributes'        => $attributes,
			'regular_price'     => $regular_price,
			'sale_price'        => $sale_price,
			'stock_quantity'    => $stock_quantity,
			'stock_status'      => $stock_quantity > 0 ? 'instock' : 'outofstock',
			'manage_stock'      => true,
			'featured'          => false,
			'virtual'           => false,
			'downloadable'      => false,
		);
	}

	/**
	 * Sync single product
	 *
	 * @param array $product_data Product data from API
	 * @param array $options Sync options
	 * @return array
	 */
	public function sync_single_product( $product_data, $options = array() ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'vortem_products';
		$vortem_id = $product_data['sku']; // Use SKU as the unique identifier

		// Check if this product is already being processed to prevent duplicates
		$processing_key = 'vortem_sync_processing_' . $vortem_id;
		if ( get_transient( $processing_key ) ) {
			return array(
				'status'  => 'skipped',
				'message' => 'Product is already being processed',
			);
		}

		// Set a transient to prevent duplicate processing (expires in 5 minutes)
		set_transient( $processing_key, true, 300 );

		// Check if product already exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE vortem_product_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
				$vortem_id
			)
		);

		// Skip if already synced and not forcing sync
		if ( $existing && ! isset( $options['force_sync'] ) && $existing->sync_status === 'synced' ) {
			delete_transient( $processing_key );
			return array(
				'status'  => 'skipped',
				'message' => 'Already synced',
			);
		}

		// If forcing sync and product exists, we'll update it
		$force_sync = isset( $options['force_sync'] ) && $options['force_sync'];

		// Dry run - just return what would happen
		if ( isset( $options['dry_run'] ) && $options['dry_run'] ) {
			delete_transient( $processing_key );
			return array(
				'status'  => 'synced',
				'message' => 'Dry run - would sync product',
			);
		}

		// Create or update WooCommerce product using new creator
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-creator.php';
		$product_creator = new Vortem_Product_Creator();

		// Debug logging

		// Use the correct method name with skip_transient_check option since we manage our own transient
		// Products are always created as draft automatically
		$result = $product_creator->create_product_from_api( $product_data, array( 'skip_transient_check' => true ) );

		// Check if creation was successful
		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			$error_message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
			delete_transient( $processing_key );
			return array(
				'status'  => 'failed',
				'message' => $error_message,
			);
		}

		// Get the WooCommerce product ID
		if ( ! isset( $result['product_id'] ) ) {
			delete_transient( $processing_key );
			return array(
				'status'  => 'failed',
				'message' => 'Product ID not returned from creator',
			);
		}

		$woo_product_id = $result['product_id'];

		// Verify the product was actually created in WooCommerce
		$woo_product = wc_get_product( $woo_product_id );
		if ( ! $woo_product ) {
			delete_transient( $processing_key );
			return array(
				'status'  => 'failed',
				'message' => 'WooCommerce product not found after creation',
			);
		}

		// Prepare data for database
		$regular_price  = '0.00';
		$sale_price     = null;
		$stock_quantity = 0;

		// Extract pricing from variations
		if ( isset( $product_data['variations'] ) && is_array( $product_data['variations'] ) && ! empty( $product_data['variations'] ) ) {
			$variation      = $product_data['variations'][0];
			$regular_price  = $variation['regular_price'];
			$sale_price     = isset( $variation['sale_price'] ) && ! empty( $variation['sale_price'] ) ? $variation['sale_price'] : null;
			$stock_quantity = isset( $variation['stock_quantity'] ) ? intval( $variation['stock_quantity'] ) : 0;
		}

		$data = array(
			'vortem_product_id' => $vortem_id,
			'woo_product_id'    => $woo_product_id,
			'name'              => $product_data['title'],
			'description'       => $product_data['description'],
			'sku'               => $product_data['sku'],
			'price'             => $regular_price,
			'regular_price'     => $regular_price,
			'sale_price'        => $sale_price,
			'stock_quantity'    => $stock_quantity,
			'stock_status'      => $stock_quantity > 0 ? 'instock' : 'outofstock',
			'weight'            => $product_data['weight'] ?? null,
			'length'            => $product_data['dimensions']['length'] ?? null,
			'width'             => $product_data['dimensions']['width'] ?? null,
			'height'            => $product_data['dimensions']['height'] ?? null,
			'category'          => $this->extract_category_name( $product_data['categories'] ?? null ),
			'tags'              => isset( $product_data['tags'] ) ? wp_json_encode( $product_data['tags'] ) : null,
			'images'            => isset( $product_data['images'] ) ? wp_json_encode( $product_data['images'] ) : null,
			'attributes'        => isset( $product_data['attributes'] ) ? wp_json_encode( $product_data['attributes'] ) : null,
			'meta_data'         => isset( $product_data['meta_data'] ) ? wp_json_encode( $product_data['meta_data'] ) : null,
			'sync_status'       => 'synced',
			'sync_date'         => current_time( 'mysql' ),
		);

		// Update or insert database record
		if ( $existing ) {
			$result  = $wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			$status  = 'updated';
			$message = 'Product imported and updated successfully';
		} else {
			$result  = $wpdb->insert( $table, $data );
			$status  = 'synced';
			$message = 'Product imported successfully';
		}

		if ( $result === false ) {
			$this->log_sync_event(
				'product_db_failed',
				array(
					'vortem_id' => $vortem_id,
					'woo_id'    => $woo_product_id,
					'error'     => $wpdb->last_error,
				)
			);
			delete_transient( $processing_key );
			return array(
				'status'  => 'failed',
				'message' => 'Database update failed: ' . esc_html( $wpdb->last_error ),
			);
		}

		// Verify the database was updated
		$verification = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE vortem_product_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
				$vortem_id
			)
		);

		$this->log_sync_event(
			'product_synced',
			array(
				'vortem_id' => $vortem_id,
				'woo_id'    => $woo_product_id,
				'status'    => $status,
			)
		);

		// Clear the processing transient
		delete_transient( $processing_key );

		return array(
			'status'          => $status,
			'message'         => $message,
			'woo_product_id'  => $woo_product_id,
			'woo_product_url' => get_edit_post_link( $woo_product_id ),
		);
	}

	/**
	 * Create WooCommerce product
	 *
	 * @param array $product_data Product data
	 * @param array $options Sync options
	 * @return int|WP_Error
	 */
	private function create_woocommerce_product( $product_data, $options ) {
		// Check if product already exists in WooCommerce
		$existing_product_id = $this->get_existing_woo_product( $product_data['sku'] );

		// CRITICAL FIX: If product already exists, skip to prevent duplicate
		if ( $existing_product_id ) {
			return $existing_product_id; // Return existing product ID
		}

		$product = new WC_Product_Simple();

		// Set basic product data
		$product->set_name( $product_data['title'] );
		$product->set_description( $product_data['description'] );
		$product->set_short_description( $product_data['short_description'] ?? '' );
		$product->set_sku( $product_data['sku'] );

		// Handle variations for pricing
		if ( isset( $product_data['variations'] ) && is_array( $product_data['variations'] ) && ! empty( $product_data['variations'] ) ) {
			$variation = $product_data['variations'][0]; // Use first variation
			$product->set_regular_price( $variation['regular_price'] );
			if ( isset( $variation['sale_price'] ) && ! empty( $variation['sale_price'] ) ) {
				$product->set_sale_price( $variation['sale_price'] );
			}
		} else {
			$product->set_regular_price( '0.00' ); // Default price
		}

		$product->set_status( 'draft' ); // ✅ Change to draft for sync
		$product->set_catalog_visibility( 'visible' );
		$product->set_featured( $product_data['featured'] ?? false );
		$product->set_virtual( $product_data['virtual'] ?? false );
		$product->set_downloadable( $product_data['downloadable'] ?? false );

		// Stock management
		$product->set_manage_stock( $product_data['manage_stock'] ?? true );
		$product->set_stock_quantity( $product_data['stock_quantity'] ?? 0 );
		$product->set_stock_status( $product_data['stock_status'] ?? 'instock' );

		// Physical dimensions
		if ( isset( $product_data['weight'] ) ) {
			$product->set_weight( $product_data['weight'] );
		}
		if ( isset( $product_data['length'] ) ) {
			$product->set_length( $product_data['length'] );
		}
		if ( isset( $product_data['width'] ) ) {
			$product->set_width( $product_data['width'] );
		}
		if ( isset( $product_data['height'] ) ) {
			$product->set_height( $product_data['height'] );
		}

		// Add Vortem meta
		$product->add_meta_data( '_vortem_product_id', $product_data['id'], true );
		$product->add_meta_data( '_vortem_synced', true, true );
		$product->add_meta_data( '_vortem_sync_date', current_time( 'mysql' ), true );

		// Store external API _id if available (for efficient deletion)
		if ( isset( $product_data['_id'] ) ) {
			$product->add_meta_data( '_vortem_imported_id', $product_data['_id'], true );
		}

		// Save product first to get ID
		$product_id = $product->save();

		if ( ! $product_id ) {
			return new WP_Error( 'product_creation_failed', 'Failed to create WooCommerce product' );
		}

		// Handle images if enabled
		if ( $options['sync_images'] && isset( $product_data['images'] ) && is_array( $product_data['images'] ) ) {
			$image_urls = array();

			// Extract image URLs from new format
			if ( isset( $product_data['images']['paths'] ) && is_array( $product_data['images']['paths'] ) ) {
				$image_urls = array_values( $product_data['images']['paths'] );
			}

			if ( ! empty( $image_urls ) ) {
				$this->sync_product_images( $product_id, $image_urls );
			}
		}

		// Handle categories if enabled
		if ( $options['sync_categories'] && ! empty( $product_data['categories'] ) ) {
			$this->sync_product_categories( $product_id, $product_data['categories'] );
		}

		// Handle tags
		if ( isset( $product_data['tags'] ) && is_array( $product_data['tags'] ) ) {
			$this->sync_product_tags( $product_id, $product_data['tags'] );
		}

		// Handle attributes
		if ( isset( $product_data['attributes'] ) && is_array( $product_data['attributes'] ) ) {
			$this->sync_product_attributes( $product_id, $product_data['attributes'] );
		}

		return $product_id;
	}

	/**
	 * Get existing WooCommerce product by SKU
	 *
	 * @param string $sku Product SKU
	 * @return int|false Product ID or false if not found
	 */
	private function get_existing_woo_product( $sku ) {
		global $wpdb;

		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s",
				$sku
			)
		);

		return $product_id ? intval( $product_id ) : false;
	}

	/**
	 * Sync product images
	 *
	 * @param int   $product_id WooCommerce product ID
	 * @param array $images Image URLs
	 * @return bool
	 */
	private function sync_product_images( $product_id, $images ) {
		if ( empty( $images ) || ! is_array( $images ) ) {
			return false;
		}

		$gallery_ids = array();

		foreach ( $images as $index => $image_url ) {
			if ( empty( $image_url ) ) {
				continue;
			}

			// Download and attach image
			$attachment_id = $this->download_and_attach_image( $image_url, $product_id );

			if ( $attachment_id ) {
				if ( $index === 0 ) {
					// Set first image as featured image
					set_post_thumbnail( $product_id, $attachment_id );
				} else {
					// Add to gallery
					$gallery_ids[] = $attachment_id;
				}
			}
		}

		// Set gallery images
		if ( ! empty( $gallery_ids ) ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
		}

		return true;
	}

	/**
	 * Download and attach image to product
	 *
	 * @param string $image_url Image URL
	 * @param int    $product_id Product ID
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_and_attach_image( $image_url, $product_id ) {
		// Phone-home gate: do not fetch remote images until consent is granted.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return false;
		}

		// Check if image already exists
		$existing_attachment = $this->get_attachment_by_url( $image_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		// Download image
		$upload_dir = wp_upload_dir();
		$image_data = wp_remote_get( $image_url );

		if ( is_wp_error( $image_data ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $image_data );
		if ( $response_code !== 200 ) {
			return false;
		}

		$image_content = wp_remote_retrieve_body( $image_data );
		if ( empty( $image_content ) ) {
			return false;
		}

		// Get file extension
		$file_extension = pathinfo( wp_parse_url( $image_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( empty( $file_extension ) ) {
			$file_extension = 'jpg';
		}

		// Generate filename
		$filename  = 'vortem-product-' . $product_id . '-' . time() . '.' . $file_extension;
		$file_path = $upload_dir['path'] . '/' . sanitize_file_name( $filename );

		// Validate that file path is within upload directory
		$real_upload_path = realpath( $upload_dir['basedir'] );
		$real_file_path   = realpath( dirname( $file_path ) );
		if ( $real_file_path === false || $real_upload_path === false || strpos( $real_file_path, $real_upload_path ) !== 0 ) {
			return false;
		}

		// Save image. The destination path is rooted in wp_upload_dir().
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a downloaded image attachment to the uploads dir.
		if ( file_put_contents( $file_path, $image_content ) === false ) {
			return false;
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => wp_check_filetype( $filename )['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $product_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file_path );
			return false;
		}

		// Generate attachment metadata
		// Note: wp_generate_attachment_metadata() is available in admin/AJAX context
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		if ( ! is_wp_error( $attachment_data ) ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}

		return $attachment_id;
	}

	/**
	 * Get attachment by URL
	 *
	 * @param string $url Image URL
	 * @return int|false Attachment ID or false
	 */
	private function get_attachment_by_url( $url ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s",
				$url
			)
		);

		return $attachment_id ? intval( $attachment_id ) : false;
	}

	/**
	 * Sync product categories
	 *
	 * @param int                 $product_id WooCommerce product ID
	 * @param string|array|object $categories Category data (can be array of names, string, or new format object)
	 * @return bool
	 */
	private function sync_product_categories( $product_id, $categories ) {
		if ( empty( $categories ) ) {
			return false;
		}

		$category_ids = array();

		// Handle new format: object with vortem_cat_m (main) and vortem_cat_l1 (subcategory)
		if ( is_array( $categories ) && ( isset( $categories['vortem_cat_m'] ) || isset( $categories['vortem_cat_l1'] ) ) ) {
			// Process main category (vortem_cat_m)
			if ( ! empty( $categories['vortem_cat_m'] ) ) {
				$main_cat      = is_array( $categories['vortem_cat_m'] ) ? $categories['vortem_cat_m'] : (array) $categories['vortem_cat_m'];
				$main_cat_name = isset( $main_cat['name'] ) ? $main_cat['name'] : '';

				if ( ! empty( $main_cat_name ) ) {
					$main_cat_id = $this->get_or_create_category( $main_cat_name );
					if ( $main_cat_id ) {
						$category_ids[] = $main_cat_id;

						// Process subcategory (vortem_cat_l1) as child of main category
						if ( ! empty( $categories['vortem_cat_l1'] ) ) {
							$sub_cat      = is_array( $categories['vortem_cat_l1'] ) ? $categories['vortem_cat_l1'] : (array) $categories['vortem_cat_l1'];
							$sub_cat_name = isset( $sub_cat['name'] ) ? $sub_cat['name'] : '';

							if ( ! empty( $sub_cat_name ) ) {
								// Check if subcategory exists as child of main category
								$sub_term = term_exists( $sub_cat_name, 'product_cat', $main_cat_id );
								if ( $sub_term ) {
									$category_ids[] = (int) $sub_term['term_id'];
								} else {
									// Create subcategory as child of main category
									$inserted = wp_insert_term( $sub_cat_name, 'product_cat', array( 'parent' => $main_cat_id ) );
									if ( ! is_wp_error( $inserted ) ) {
										$category_ids[] = (int) $inserted['term_id'];
									}
								}
							}
						}
					}
				}
			}
		} else {
			// Handle old format: array of category name strings or single string (backward compatibility)
			if ( is_string( $categories ) ) {
				$categories = array( $categories );
			}

			if ( is_array( $categories ) ) {
				foreach ( $categories as $category_name ) {
					if ( empty( $category_name ) ) {
						continue;
					}
					$category_id = $this->get_or_create_category( $category_name );
					if ( $category_id ) {
						$category_ids[] = $category_id;
					}
				}
			}
		}

		if ( ! empty( $category_ids ) ) {
			wp_set_object_terms( $product_id, $category_ids, 'product_cat' );
			return true;
		}

		return false;
	}

	/**
	 * Extract category name from category data (supports both old and new formats)
	 *
	 * @param mixed $categories Category data
	 * @return string|null Category name or null
	 */
	private function extract_category_name( $categories ) {
		if ( empty( $categories ) ) {
			return null;
		}

		// Handle new format: object with vortem_cat_m and vortem_cat_l1
		if ( is_array( $categories ) && ( isset( $categories['vortem_cat_m'] ) || isset( $categories['vortem_cat_l1'] ) ) ) {
			// Prefer subcategory name, fallback to main category
			if ( ! empty( $categories['vortem_cat_l1'] ) ) {
				$sub_cat = is_array( $categories['vortem_cat_l1'] ) ? $categories['vortem_cat_l1'] : (array) $categories['vortem_cat_l1'];
				if ( isset( $sub_cat['name'] ) && ! empty( $sub_cat['name'] ) ) {
					return $sub_cat['name'];
				}
			}
			if ( ! empty( $categories['vortem_cat_m'] ) ) {
				$main_cat = is_array( $categories['vortem_cat_m'] ) ? $categories['vortem_cat_m'] : (array) $categories['vortem_cat_m'];
				if ( isset( $main_cat['name'] ) && ! empty( $main_cat['name'] ) ) {
					return $main_cat['name'];
				}
			}
		}

		// Handle old format: array of category name strings
		if ( is_array( $categories ) && ! empty( $categories[0] ) ) {
			return is_string( $categories[0] ) ? $categories[0] : null;
		}

		// Handle string format
		if ( is_string( $categories ) ) {
			return $categories;
		}

		return null;
	}

	/**
	 * Get or create product category
	 *
	 * @param string $category_name Category name
	 * @return int|false Category ID or false
	 */
	private function get_or_create_category( $category_name ) {
		$category_name = sanitize_text_field( $category_name );

		// Check if category exists
		$category = get_term_by( 'name', $category_name, 'product_cat' );

		if ( $category ) {
			return $category->term_id;
		}

		// Create new category
		$result = wp_insert_term( $category_name, 'product_cat' );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $result['term_id'];
	}

	/**
	 * Sync product tags
	 *
	 * @param int   $product_id WooCommerce product ID
	 * @param array $tags Tag names
	 * @return bool
	 */
	private function sync_product_tags( $product_id, $tags ) {
		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return false;
		}

		$sanitized_tags = array_map( 'sanitize_text_field', $tags );
		wp_set_object_terms( $product_id, $sanitized_tags, 'product_tag' );

		return true;
	}

	/**
	 * Sync product attributes
	 *
	 * @param int   $product_id WooCommerce product ID
	 * @param array $attributes Product attributes
	 * @return bool
	 */
	private function sync_product_attributes( $product_id, $attributes ) {
		if ( empty( $attributes ) || ! is_array( $attributes ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$product_attributes = array();

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			$attribute_name  = sanitize_title( $attribute_name );
			$attribute_value = sanitize_text_field( $attribute_value );

			// Create attribute if it doesn't exist
			$attribute_id = $this->get_or_create_attribute( $attribute_name );

			if ( $attribute_id ) {
				$product_attributes[ 'pa_' . $attribute_name ] = array(
					'name'         => 'pa_' . $attribute_name,
					'value'        => $attribute_value,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);
			}
		}

		if ( ! empty( $product_attributes ) ) {
			$product->set_attributes( $product_attributes );
			$product->save();
			return true;
		}

		return false;
	}

	/**
	 * Get or create product attribute
	 *
	 * @param string $attribute_name Attribute name
	 * @return int|false Attribute ID or false
	 */
	private function get_or_create_attribute( $attribute_name ) {
		global $wpdb;

		$attribute_name = sanitize_title( $attribute_name );

		// Check if attribute exists
		$attribute_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
				$attribute_name
			)
		);

		if ( $attribute_id ) {
			return intval( $attribute_id );
		}

		// Create new attribute
		$result = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			array(
				'attribute_name'    => $attribute_name,
				'attribute_label'   => ucwords( str_replace( '-', ' ', $attribute_name ) ),
				'attribute_type'    => 'select',
				'attribute_orderby' => 'menu_order',
				'attribute_public'  => 1,
			)
		);

		if ( $result === false ) {
			return false;
		}

		// Clear the attribute cache so the new taxonomy is recognised.
		// Actual rewrite-rule flush is deferred to admin_init via a transient
		// flag to avoid the expensive flush during AJAX/import requests.
		delete_transient( 'wc_attribute_taxonomies' );
		wp_cache_delete( 'wc_attribute_taxonomies', 'woocommerce' );
		set_transient( 'vortem_flush_rewrite_rules', true, HOUR_IN_SECONDS );

		return $wpdb->insert_id;
	}

	/**
	 * Get synced products count
	 *
	 * @return int
	 */
	public function get_synced_products_count() {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'vortem_products' );
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE sync_status = %s",
				'synced'
			)
		);

		return intval( $count );
	}

	/**
	 * Get sync status
	 *
	 * @return array
	 */
	public function get_sync_status() {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'vortem_products' );

		$status = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- literal table name from $wpdb->prefix; no user input
			"SELECT sync_status, COUNT(*) as count FROM $table GROUP BY sync_status",
			ARRAY_A
		);

		$result = array(
			'total'   => 0,
			'synced'  => 0,
			'pending' => 0,
			'failed'  => 0,
			'skipped' => 0,
			'running' => 0,
		);

		foreach ( $status as $row ) {
			$result[ $row['sync_status'] ] = intval( $row['count'] );
			$result['total']              += intval( $row['count'] );
		}

		// Add sync running status
		$result['is_running']    = $this->is_sync_running();
		$result['last_sync']     = get_option( 'vortem_last_sync_date', null );
		$result['current_count'] = $this->get_synced_products_count();

		return $result;
	}

	/**
	 * Reset sync status
	 *
	 * @return bool
	 */
	public function reset_sync_status() {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		$result = $wpdb->update(
			$table,
			array(
				'sync_status' => 'pending',
				'sync_date'   => null,
			),
			array( 'sync_status' => 'synced' )
		);

		// Clear sync running status
		delete_option( 'vortem_sync_running' );
		delete_option( 'vortem_last_sync_date' );

		return $result !== false;
	}

	/**
	 * Check if sync is currently running
	 *
	 * @return bool
	 */
	private function is_sync_running() {
		$running    = get_option( 'vortem_sync_running', false );
		$start_time = get_option( 'vortem_sync_start_time', 0 );

		// If sync has been running for more than 1 hour, consider it failed
		if ( $running && ( time() - $start_time ) > 3600 ) {
			$this->set_sync_status( 'failed' );
			return false;
		}

		return $running;
	}

	/**
	 * Set sync status
	 *
	 * @param string $status Sync status
	 */
	private function set_sync_status( $status ) {
		if ( $status === 'running' ) {
			update_option( 'vortem_sync_running', true );
			update_option( 'vortem_sync_start_time', time() );
		} else {
			delete_option( 'vortem_sync_running' );
			delete_option( 'vortem_sync_start_time' );

			if ( $status === 'completed' ) {
				update_option( 'vortem_last_sync_date', current_time( 'mysql' ) );
			}
		}
	}

	/**
	 * Log sync event
	 *
	 * @param string $event Event type
	 * @param array  $data Event data
	 */
	private function log_sync_event( $event, $data = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event'     => $event,
			'data'      => $data,
		);

		// Store in WordPress options for recent logs
		$logs   = get_option( 'vortem_sync_logs', array() );
		$logs[] = $log_entry;

		// Keep only last 100 log entries
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'vortem_sync_logs', $logs );

		// Also log to WordPress error log
	}

	/**
	 * Get sync logs
	 *
	 * @param int $limit Number of logs to retrieve
	 * @return array
	 */
	public function get_sync_logs( $limit = 50 ) {
		$logs = get_option( 'vortem_sync_logs', array() );
		return array_slice( $logs, -$limit );
	}

	/**
	 * Clear sync logs
	 *
	 * @return bool
	 */
	public function clear_sync_logs() {
		return delete_option( 'vortem_sync_logs' );
	}

	/**
	 * Cron handler for product sync
	 */
	public function cron_sync_products() {
		// Phone-home gate: a scheduled cron must NEVER fire HTTP before consent.
		if ( ! Vortem_Api_Client::has_consent() ) {
			$this->log_sync_event( 'sync_blocked', array( 'reason' => 'Data processing consent not granted' ) );
			return;
		}

		// Check if sync is enabled
		if ( ! get_option( 'vortem_sync_enabled', true ) ) {
			$this->log_sync_event( 'sync_disabled', array( 'reason' => 'Sync disabled in settings' ) );
			return;
		}

		// Run sync with default options
		$options = array(
			'batch_size'      => $this->batch_size,
			'sync_images'     => true,
			'sync_categories' => true,
		);

		$this->log_sync_event( 'cron_sync_started', $options );
		$result = $this->sync_products( $options );
		$this->log_sync_event( 'cron_sync_completed', $result );
	}

	/**
	 * AJAX handler for product sync
	 */
	public function ajax_sync_products() {

		// Verify nonce
		check_ajax_referer( 'vortem_sync_products', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$options = array(
			'force_sync'      => isset( $_POST['force_sync'] ) ? filter_var( wp_unslash( $_POST['force_sync'] ), FILTER_VALIDATE_BOOLEAN ) : false,
			'batch_size'      => isset( $_POST['batch_size'] ) ? intval( wp_unslash( $_POST['batch_size'] ) ) : $this->batch_size,
			'max_products'    => isset( $_POST['max_products'] ) ? intval( wp_unslash( $_POST['max_products'] ) ) : null,
			'sync_images'     => isset( $_POST['sync_images'] ) ? filter_var( wp_unslash( $_POST['sync_images'] ), FILTER_VALIDATE_BOOLEAN ) : true,
			'sync_categories' => isset( $_POST['sync_categories'] ) ? filter_var( wp_unslash( $_POST['sync_categories'] ), FILTER_VALIDATE_BOOLEAN ) : true,
			'dry_run'         => isset( $_POST['dry_run'] ) ? filter_var( wp_unslash( $_POST['dry_run'] ), FILTER_VALIDATE_BOOLEAN ) : false,
		);
		$result  = $this->sync_products( $options );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for sync status
	 */
	public function ajax_get_sync_status() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$status = $this->get_sync_status();
		wp_send_json_success( $status );
	}

	/**
	 * AJAX handler for reset sync
	 */
	public function ajax_reset_sync() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$result = $this->reset_sync_status();

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Sync status reset successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to reset sync status' ) );
		}
	}

	/**
	 * Get products with pagination
	 *
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_products( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'   => 20,
			'offset'  => 0,
			'status'  => 'all',
			'search'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table            = $wpdb->prefix . 'vortem_products';
		$where_conditions = array();
		$where_values     = array();

		// Validate status using whitelist
		$status = Vortem_Security::validate_sync_status( $args['status'], 'all' );
		if ( $status !== 'all' ) {
			$where_conditions[] = 'sync_status = %s';
			$where_values[]     = $status;
		}

		// Search filter
		if ( ! empty( $args['search'] ) ) {
			$search = Vortem_Security::validate_search( $args['search'], '' );
			if ( ! empty( $search ) ) {
				$where_conditions[] = '(name LIKE %s OR sku LIKE %s OR description LIKE %s)';
				$search_term        = '%' . $wpdb->esc_like( $search ) . '%';
				$where_values[]     = $search_term;
				$where_values[]     = $search_term;
				$where_values[]     = $search_term;
			}
		}

		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		// Validate orderby and order using whitelist
		$orderby = Vortem_Security::validate_orderby( $args['orderby'], 'created_at' );
		$order   = Vortem_Security::validate_order( $args['order'], 'DESC' );

		// Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$count_query = "SELECT COUNT(*) FROM $table $where_clause";
		if ( ! empty( $where_values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic query already prepared below
			$count_query = $wpdb->prepare( $count_query, $where_values );
		}
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $count_query is prepared when needed
		$total = $wpdb->get_var( $count_query );

		// Get products
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$query        = "SELECT * FROM $table $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $args['limit'], $args['offset'] ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with $wpdb->prepare
		$products = $wpdb->get_results( $wpdb->prepare( $query, $query_values ), ARRAY_A );

		return array(
			'products' => $products,
			'total'    => intval( $total ),
			'page'     => floor( $args['offset'] / $args['limit'] ) + 1,
			'pages'    => ceil( $total / $args['limit'] ),
		);
	}

	/**
	 * Delete product and remove from WooCommerce
	 *
	 * @param int $product_id Vortem product ID
	 * @return bool
	 */
	public function delete_product( $product_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		// Get product data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$product_id
			)
		);

		if ( ! $product ) {
			return false;
		}

		// Delete WooCommerce product and all associated data if it exists
		if ( $product->woo_product_id ) {
			// Use comprehensive delete function to completely remove product and all related data
			$this->delete_product_completely( $product->woo_product_id );
		}

		// Delete from Vortem table
		$result = $wpdb->delete( $table, array( 'id' => $product_id ) );

		if ( $result !== false ) {
			$this->log_sync_event(
				'product_deleted',
				array(
					'vortem_id' => $product->vortem_product_id,
					'woo_id'    => $product->woo_product_id,
				)
			);
		}

		return $result !== false;
	}

	/**
	 * Permanently delete a product from database and WordPress
	 *
	 * @param string $sku Product SKU
	 * @return array
	 */
	public function trash_product( $sku ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		// Get product data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			)
		);

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => 'Product not found',
			);
		}

		$deleted_items = array();

		// Delete WooCommerce product and all associated data
		if ( $product->woo_product_id ) {
			// Get media count before deletion for logging
			$media_result = $this->delete_product_media( $product->woo_product_id );

			if ( $media_result['deleted_images'] > 0 ) {
				$deleted_items[] = "Images: {$media_result['deleted_images']}";
			}
			if ( $media_result['deleted_videos'] > 0 ) {
				$deleted_items[] = "Videos: {$media_result['deleted_videos']}";
			}

			// Use comprehensive delete function to completely remove product and all related data
			$this->delete_product_completely( $product->woo_product_id );

			$deleted_items[] = "WooCommerce Product ID: {$product->woo_product_id}";
		}

		// Delete from Vortem products table
		$result = $wpdb->delete( $table, array( 'sku' => $sku ) );

		if ( $result !== false ) {
			$deleted_items[] = 'Vortem Product Record';

			$this->log_sync_event(
				'product_permanently_deleted',
				array(
					'vortem_id'     => $product->vortem_product_id,
					'woo_id'        => $product->woo_product_id,
					'sku'           => $sku,
					'deleted_items' => $deleted_items,
				)
			);

			return array(
				'success' => true,
				'message' => 'Product permanently deleted. Removed: ' . implode( ', ', $deleted_items ),
			);
		}

		return array(
			'success' => false,
			'message' => 'Failed to delete product from database',
		);
	}

	/**
	 * Get all images associated with a WooCommerce product
	 *
	 * @param int $product_id WooCommerce product ID
	 * @return array Array of image attachment IDs
	 */
	private function get_product_images( $product_id ) {
		$images = array();

		// Get featured image
		$featured_image_id = get_post_thumbnail_id( $product_id );
		if ( $featured_image_id ) {
			$images[] = $featured_image_id;
		}

		// Get gallery images
		$gallery_images = get_post_meta( $product_id, '_product_image_gallery', true );
		if ( $gallery_images ) {
			$gallery_ids = explode( ',', $gallery_images );
			foreach ( $gallery_ids as $image_id ) {
				if ( $image_id && ! in_array( $image_id, $images, true ) ) {
					$images[] = $image_id;
				}
			}
		}

		// Get variation images
		$variations = wc_get_products(
			array(
				'type'   => 'product_variation',
				'parent' => $product_id,
				'limit'  => -1,
			)
		);

		foreach ( $variations as $variation ) {
			$variation_image_id = get_post_thumbnail_id( $variation->get_id() );
			if ( $variation_image_id && ! in_array( $variation_image_id, $images, true ) ) {
				$images[] = $variation_image_id;
			}
		}

		return $images;
	}

	/**
	 * Get all images and videos associated with a WooCommerce product
	 * Includes featured image, gallery images, variation images, and videos
	 *
	 * @param int $product_id WooCommerce product ID
	 * @return array Array with 'images' and 'videos' keys containing attachment IDs
	 */
	private function get_all_product_media( $product_id ) {
		$media = array(
			'images' => array(),
			'videos' => array(),
		);

		// Get all images (featured, gallery, and variation images)
		$images          = $this->get_product_images( $product_id );
		$media['images'] = $images;

		// Get product videos from various meta keys
		$video_meta_keys = array(
			'_product_video_gallery',
			'_vortem_product_video_id',
			'woodmart_wc_video_gallery',
			'mytheme_wc_video_gallery',
		);

		foreach ( $video_meta_keys as $meta_key ) {
			$video_data = get_post_meta( $product_id, $meta_key, true );

			if ( empty( $video_data ) ) {
				continue;
			}

			// Handle different video storage formats
			if ( is_numeric( $video_data ) ) {
				// Single video attachment ID
				$video_id = intval( $video_data );
				if ( $video_id && ! in_array( $video_id, $media['videos'], true ) ) {
					$media['videos'][] = $video_id;
				}
			} elseif ( is_array( $video_data ) ) {
				// Array format (e.g., woodmart_wc_video_gallery)
				foreach ( $video_data as $key => $video_info ) {
					if ( is_numeric( $key ) ) {
						// Key is the attachment ID
						$video_id = intval( $key );
						if ( $video_id && ! in_array( $video_id, $media['videos'], true ) ) {
							$media['videos'][] = $video_id;
						}
					} elseif ( isset( $video_info['video_id'] ) && is_numeric( $video_info['video_id'] ) ) {
						// Nested array with video_id
						$video_id = intval( $video_info['video_id'] );
						if ( $video_id && ! in_array( $video_id, $media['videos'], true ) ) {
							$media['videos'][] = $video_id;
						}
					}
				}
			}
		}

		// Also check for attachments directly attached to the product
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_parent'    => $product_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		foreach ( $attachments as $attachment ) {
			$mime_type = get_post_mime_type( $attachment->ID );

			if ( strpos( $mime_type, 'image/' ) === 0 ) {
				// Image attachment
				if ( ! in_array( $attachment->ID, $media['images'], true ) ) {
					$media['images'][] = $attachment->ID;
				}
			} elseif ( strpos( $mime_type, 'video/' ) === 0 ) {
				// Video attachment
				if ( ! in_array( $attachment->ID, $media['videos'], true ) ) {
					$media['videos'][] = $attachment->ID;
				}
			}
		}

		return $media;
	}

	/**
	 * Delete all media (images and videos) associated with a product
	 *
	 * @param int $product_id WooCommerce product ID
	 * @return array Array with 'deleted_images' and 'deleted_videos' counts
	 */
	private function delete_product_media( $product_id ) {
		$result = array(
			'deleted_images' => 0,
			'deleted_videos' => 0,
			'errors'         => array(),
		);

		$media = $this->get_all_product_media( $product_id );

		// Delete all images
		foreach ( $media['images'] as $image_id ) {
			$deleted = wp_delete_attachment( $image_id, true );
			if ( $deleted ) {
				++$result['deleted_images'];
			} else {
				$result['errors'][] = "Failed to delete image ID: {$image_id}";
			}
		}

		// Delete all videos
		foreach ( $media['videos'] as $video_id ) {
			$deleted = wp_delete_attachment( $video_id, true );
			if ( $deleted ) {
				++$result['deleted_videos'];
			} else {
				$result['errors'][] = "Failed to delete video ID: {$video_id}";
			}
		}

		return $result;
	}

	/**
	 * Restore a trashed WooCommerce product
	 *
	 * @param string $sku Product SKU
	 * @return array
	 */
	public function restore_product( $sku ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		// Get product data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			)
		);

		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => 'Product not found',
			);
		}

		// Check if WooCommerce product exists in trash
		if ( $product->woo_product_id ) {
			$woo_product = wc_get_product( $product->woo_product_id );
			if ( $woo_product && $woo_product->get_status() === 'trash' ) {
				// Restore from trash
				wp_untrash_post( $product->woo_product_id );

				// Update sync status back to synced
				$wpdb->update(
					$table,
					array(
						'sync_status'  => 'synced',
						'last_updated' => current_time( 'mysql' ),
					),
					array( 'sku' => $sku )
				);

				$this->log_sync_event(
					'product_restored',
					array(
						'vortem_id' => $product->vortem_product_id,
						'woo_id'    => $product->woo_product_id,
						'sku'       => $sku,
					)
				);

				return array(
					'success' => true,
					'message' => 'Product restored successfully',
				);
			}
		}

		return array(
			'success' => false,
			'message' => 'Product not found in trash',
		);
	}

	/**
	 * AJAX handler for trashing product
	 */
	public function ajax_trash_product() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! get_option( 'vortem_setup_completed', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		$result = $this->trash_product( $sku );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX handler for restoring product
	 */
	public function ajax_restore_product() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		$result = $this->restore_product( $sku );

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Get product statistics
	 *
	 * @return array
	 */
	public function get_product_statistics() {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		$stats = array(
			'total_products'   => 0,
			'synced_products'  => 0,
			'pending_products' => 0,
			'failed_products'  => 0,
			'total_value'      => 0,
			'average_price'    => 0,
			'categories_count' => 0,
			'last_sync'        => null,
		);

		// Basic counts
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from $wpdb->prefix (trusted)
		$counts = $wpdb->get_results(
			"
            SELECT sync_status, COUNT(*) as count 
            FROM $table 
            GROUP BY sync_status
        ",
			ARRAY_A
		);

		foreach ( $counts as $count ) {
			$stats['total_products']                     += intval( $count['count'] );
			$stats[ $count['sync_status'] . '_products' ] = intval( $count['count'] );
		}

		// Price statistics
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from $wpdb->prefix (trusted)
		$price_stats = $wpdb->get_row(
			"
            SELECT 
                SUM(price) as total_value,
                AVG(price) as average_price
            FROM $table 
            WHERE sync_status = 'synced' AND price > 0
        ",
			ARRAY_A
		);

		if ( $price_stats ) {
			$stats['total_value']   = floatval( $price_stats['total_value'] );
			$stats['average_price'] = floatval( $price_stats['average_price'] );
		}

		// Categories count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from $wpdb->prefix (trusted)
		$categories                = $wpdb->get_var(
			"
            SELECT COUNT(DISTINCT category) 
            FROM $table 
            WHERE sync_status = 'synced' AND category IS NOT NULL
        "
		);
		$stats['categories_count'] = intval( $categories );

		// Last sync date
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from $wpdb->prefix (trusted)
		$last_sync          = $wpdb->get_var(
			"
            SELECT MAX(sync_date) 
            FROM $table 
            WHERE sync_status = 'synced'
        "
		);
		$stats['last_sync'] = $last_sync;

		return $stats;
	}

	/**
	 * AJAX handler for importing a single product
	 */
	public function ajax_import_single() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		// Check if setup is completed
		if ( ! get_option( 'vortem_setup_completed', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Setup wizard must be completed first.', 'vortem-ai' ) ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		// Use the improved import_single_product method
		$result = $this->import_single_product( $sku );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {

			// Get the WooCommerce product ID from the database
			global $wpdb;
			$table = $wpdb->prefix . 'vortem_products';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
			$product_record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT woo_product_id FROM $table WHERE sku = %s",
					$sku
				)
			);

			$woo_product_id = $product_record ? $product_record->woo_product_id : null;
			$edit_url       = $woo_product_id ? get_edit_post_link( $woo_product_id ) : null;

			wp_send_json_success(
				array(
					'message'        => 'Product imported successfully as draft',
					'woo_product_id' => $woo_product_id,
					'edit_url'       => $edit_url,
				)
			);
		}
	}

	/**
	 * AJAX handler for bulk actions
	 */
	public function ajax_bulk_action() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		// Validate bulk action using whitelist
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$action      = Vortem_Security::validate_bulk_action( isset( $_POST['bulk_action'] ) ? wp_unslash( $_POST['bulk_action'] ) : '', '' );
		$product_ids = array();

		if ( isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item is validated by Vortem_Security::validate_sku
			foreach ( (array) wp_unslash( $_POST['product_ids'] ) as $id ) {
				$validated_id = Vortem_Security::validate_sku( $id, '' );
				if ( ! empty( $validated_id ) ) {
					$product_ids[] = $validated_id;
				}
			}
		}

		if ( empty( $product_ids ) ) {
			wp_send_json_error( 'No products selected' );
		}

		$results       = array();
		$success_count = 0;
		$error_count   = 0;

		foreach ( $product_ids as $sku ) {
			switch ( $action ) {
				case 'import':
					$result = $this->ajax_import_single_product( $sku );
					break;
				case 'trash':
					$result = $this->trash_product( $sku );
					break;
				case 'restore':
					$result = $this->restore_product( $sku );
					break;
				case 'delete':
					$result = $this->delete_product_permanently( $sku );
					break;
				default:
					$result = array(
						'success' => false,
						'message' => 'Invalid action',
					);
			}

			if ( $result['success'] ) {
				++$success_count;
			} else {
				++$error_count;
				$results[] = $sku . ': ' . $result['message'];
			}
		}

		$message = sprintf(
			'Bulk action completed. %d successful, %d failed.',
			$success_count,
			$error_count
		);

		if ( $error_count > 0 ) {
			$message .= ' Errors: ' . implode( '; ', $results );
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Helper method for importing a single product (used by bulk actions)
	 */
	private function ajax_import_single_product( $sku ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			),
			ARRAY_A
		);

		if ( ! $product_data ) {
			return array(
				'success' => false,
				'message' => 'Product not found',
			);
		}

		$converted_product = $this->convert_standard_product_data( $product_data );
		$result            = $this->sync_single_product(
			$converted_product,
			array(
				'force_sync'      => true,
				'import_as_draft' => true,
			)
		);

		return array(
			'success' => $result['status'] === 'success' || $result['status'] === 'synced' || $result['status'] === 'updated',
			'message' => $result['message'] ?? 'Import failed',
		);
	}

	/**
	 * Delete product permanently
	 */
	private function delete_product_permanently( $sku ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';

		// Get product data first to get woo_product_id
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			)
		);

		// Delete from Vortem products table
		$deleted = $wpdb->delete( $table, array( 'sku' => $sku ) );

		if ( $deleted === false ) {
			return array(
				'success' => false,
				'message' => 'Failed to delete from database',
			);
		}

		// Also delete from WooCommerce if it exists
		$woo_product_id = null;
		if ( $product && ! empty( $product->woo_product_id ) ) {
			$woo_product_id = $product->woo_product_id;
		} else {
			// Fallback: try to find by SKU
			$woo_product_id = wc_get_product_id_by_sku( $sku );
		}

		if ( $woo_product_id ) {
			// Use comprehensive delete function
			$this->delete_product_completely( $woo_product_id );
		}

		return array(
			'success' => true,
			'message' => 'Product deleted permanently',
		);
	}

	/**
	 * AJAX handler for getting product details
	 */
	public function ajax_get_product_details() {
		check_ajax_referer( 'vortem_get_product_details', 'nonce' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			),
			ARRAY_A
		);

		if ( ! $product_data ) {
			wp_send_json_error( 'Product not found' );
		}

		// Try to find WooCommerce product ID
		$woo_product_id = null;
		if ( ! empty( $product_data['woo_product_id'] ) ) {
			$woo_product_id = $product_data['woo_product_id'];
		} else {
			// Fallback: try to find by SKU
			$woo_product_id = wc_get_product_id_by_sku( $sku );
		}

		// Format product details for display as HTML
		$html  = '<div class="product-details">';
		$html .= '<h3>' . esc_html( $product_data['name'] ?? 'Unknown Product' ) . '</h3>';
		$html .= '<table class="widefat">';
		$html .= '<tr><td><strong>SKU:</strong></td><td>' . esc_html( $product_data['sku'] ) . '</td></tr>';
		$html .= '<tr><td><strong>Name:</strong></td><td>' . esc_html( $product_data['name'] ?? 'Unknown' ) . '</td></tr>';
		$html .= '<tr><td><strong>Price:</strong></td><td>$' . esc_html( $product_data['price'] ?? '0.00' ) . '</td></tr>';
		$html .= '<tr><td><strong>Category:</strong></td><td>' . esc_html( $product_data['category'] ?? 'Uncategorized' ) . '</td></tr>';
		$html .= '<tr><td><strong>Sync Status:</strong></td><td>' . esc_html( $product_data['sync_status'] ?? 'unknown' ) . '</td></tr>';
		$html .= '<tr><td><strong>Sync Date:</strong></td><td>' . esc_html( $product_data['sync_date'] ?? 'Never' ) . '</td></tr>';

		if ( $woo_product_id && wc_get_product( $woo_product_id ) ) {
			$html .= '<tr><td><strong>WooCommerce Product:</strong></td><td>';
			$html .= '<a href="' . esc_url( get_edit_post_link( $woo_product_id ) ) . '" target="_blank">Edit Product</a> | ';
			$html .= '<a href="' . esc_url( get_permalink( $woo_product_id ) ) . '" target="_blank">View Product</a>';
			$html .= '</td></tr>';
		} else {
			$html .= '<tr><td><strong>WooCommerce Product:</strong></td><td>Not imported yet</td></tr>';
		}

		$html .= '</table>';
		$html .= '</div>';

		wp_send_json_success( $html );
	}

	/**
	 * AJAX handler for getting product details as JSON (for edit button)
	 */
	public function ajax_get_product_json() {
		check_ajax_referer( 'vortem_get_product_details', 'nonce' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE sku = %s",
				$sku
			),
			ARRAY_A
		);

		if ( ! $product_data ) {
			wp_send_json_error( 'Product not found' );
		}

		// Try to find WooCommerce product ID
		$woo_product_id     = null;
		$woo_product_exists = false;

		if ( ! empty( $product_data['woo_product_id'] ) ) {
			// Verify that the WooCommerce product actually still exists
			$woo_product = wc_get_product( $product_data['woo_product_id'] );
			if ( $woo_product && $woo_product->exists() ) {
				$woo_product_id     = $product_data['woo_product_id'];
				$woo_product_exists = true;
			} else {
				// Product was deleted from WooCommerce, clean up the database record
				$wpdb->update(
					$table,
					array( 'woo_product_id' => null ),
					array( 'sku' => $sku )
				);
			}
		} else {
			// Fallback: try to find by SKU
			$woo_product_id = wc_get_product_id_by_sku( $sku );
			if ( $woo_product_id ) {
				$woo_product        = wc_get_product( $woo_product_id );
				$woo_product_exists = $woo_product && $woo_product->exists();
			}
		}

		// Format product details as JSON for JavaScript
		$details = array(
			'sku'                => $product_data['sku'],
			'name'               => $product_data['name'] ?? 'Unknown',
			'price'              => $product_data['price'] ?? '0.00',
			'category'           => $product_data['category'] ?? 'Uncategorized',
			'sync_status'        => $product_data['sync_status'] ?? 'unknown',
			'sync_date'          => $product_data['sync_date'] ?? 'Never',
			'woo_product_id'     => $woo_product_id,
			'woo_product_exists' => $woo_product_exists,
		);

		wp_send_json_success( $details );
	}

	/**
	 * AJAX handler for importing product as draft
	 */
	public function ajax_import_as_draft() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		if ( empty( $sku ) ) {
			wp_send_json_error( 'SKU is required' );
		}

		// Use the improved import_single_product method
		$result = $this->import_single_product( $sku );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		} else {

			// Get the WooCommerce product ID from the database
			global $wpdb;
			$table = $wpdb->prefix . 'vortem_products';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
			$product_record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT woo_product_id FROM $table WHERE sku = %s",
					$sku
				)
			);

			$woo_product_id = $product_record ? $product_record->woo_product_id : null;
			$edit_url       = $woo_product_id ? get_edit_post_link( $woo_product_id ) : null;

			wp_send_json_success(
				array(
					'message'        => 'Product imported as draft successfully',
					'woo_product_id' => $woo_product_id,
					'edit_url'       => $edit_url,
				)
			);
		}
	}

	/**
	 * Clean up orphaned database records (products marked as synced but WooCommerce product doesn't exist)
	 */
	public function cleanup_orphaned_records() {
		global $wpdb;

		$table          = $wpdb->prefix . 'vortem_products';
		$orphaned_count = 0;

		// Get synced products in batches to avoid unbounded memory usage
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table is from $wpdb->prefix (trusted)
		$synced_products = $wpdb->get_results(
			"SELECT * FROM $table WHERE sync_status = 'synced' LIMIT 500",
			ARRAY_A
		);

		foreach ( $synced_products as $product ) {
			$woo_product_id = $product['woo_product_id'];

			// Check if WooCommerce product actually exists
			if ( $woo_product_id && ! wc_get_product( $woo_product_id ) ) {
				// Update sync status to indicate WooCommerce product is missing
				$wpdb->update(
					$table,
					array(
						'sync_status'  => 'missing',
						'last_updated' => current_time( 'mysql' ),
					),
					array( 'id' => $product['id'] )
				);

				++$orphaned_count;

				$this->log_sync_event(
					'orphaned_record_cleaned',
					array(
						'sku'    => $product['sku'],
						'woo_id' => $woo_product_id,
					)
				);
			}
		}

		return $orphaned_count;
	}

	/**
	 * Save product to database for display only (no WooCommerce creation)
	 *
	 * @param array $product_data Product data
	 * @return array
	 */
	private function save_product_to_database( $product_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vortem_products';

		// Debug logging

		// Extract basic product info
		$sku               = $product_data['sku'] ?? '';
		$name              = $product_data['name'] ?? $product_data['title'] ?? 'Unknown Product';
		$description       = $product_data['description'] ?? '';
		$short_description = $product_data['short_description'] ?? '';
		$category          = $this->extract_category_name( $product_data['categories'] ?? null ) ?? ''; // Use category from converted data
		$price             = $product_data['regular_price'] ?? '0.00'; // Use regular_price from converted data
		$image             = '';

		// Get first image URL from converted data structure
		if ( ! empty( $product_data['images']['paths']['base'] ) ) {
			$image = $product_data['images']['paths']['base'];
		}

		// Check if product already exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE vortem_product_id = %s",
				$sku
			)
		);

		// Get table columns to avoid errors with missing columns
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DESCRIBE cannot use placeholders; literal table name.
		$columns = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}vortem_products" );

		$data = array(
			'vortem_product_id' => $sku,
			'sku'               => $sku,
			'sync_status'       => 'synced',
			'sync_date'         => current_time( 'mysql' ),
		);

		// Add last_updated if column exists
		if ( in_array( 'last_updated', $columns, true ) ) {
			$data['last_updated'] = current_time( 'mysql' );
		}

		// Add all available product data
		if ( in_array( 'name', $columns, true ) ) {
			$data['name'] = $name;
		}
		if ( in_array( 'title', $columns, true ) ) {
			$data['title'] = $name;
		}
		if ( in_array( 'description', $columns, true ) ) {
			$data['description'] = $description;
		}
		if ( in_array( 'short_description', $columns, true ) ) {
			$data['short_description'] = $short_description;
		}
		if ( in_array( 'category', $columns, true ) ) {
			$data['category'] = $category;
		}
		if ( in_array( 'price', $columns, true ) ) {
			$data['price'] = $price;
		}
		if ( in_array( 'regular_price', $columns, true ) ) {
			$data['regular_price'] = $product_data['regular_price'] ?? $price;
		}
		if ( in_array( 'sale_price', $columns, true ) ) {
			$data['sale_price'] = $product_data['sale_price'];
		}
		if ( in_array( 'stock_quantity', $columns, true ) ) {
			$data['stock_quantity'] = $product_data['stock_quantity'] ?? 0;
		}
		if ( in_array( 'stock_status', $columns, true ) ) {
			$data['stock_status'] = $product_data['stock_status'] ?? 'instock';
		}
		if ( in_array( 'weight', $columns, true ) ) {
			$data['weight'] = $product_data['weight'] ?? '0.5';
		}
		if ( in_array( 'length', $columns, true ) ) {
			$data['length'] = $product_data['length'] ?? '10';
		}
		if ( in_array( 'width', $columns, true ) ) {
			$data['width'] = $product_data['width'] ?? '10';
		}
		if ( in_array( 'height', $columns, true ) ) {
			$data['height'] = $product_data['height'] ?? '5';
		}
		if ( in_array( 'image', $columns, true ) ) {
			$data['image'] = $image;
		}

		// Store complete product data as JSON for later import
		if ( in_array( 'images', $columns, true ) ) {
			$data['images'] = wp_json_encode( $product_data['images'] ?? array() );
		}
		if ( in_array( 'attributes', $columns, true ) ) {
			$data['attributes'] = wp_json_encode( $product_data['attributes'] ?? array() );
		}
		if ( in_array( 'tags', $columns, true ) ) {
			$data['tags'] = wp_json_encode( $product_data['tags'] ?? array() );
		}
		if ( in_array( 'meta_data', $columns, true ) ) {
			// Store the full product data for complete restoration on import
			$data['meta_data'] = wp_json_encode( $product_data );
		}

		if ( $existing ) {
			// Update existing product
			$result = $wpdb->update( $table, $data, array( 'vortem_product_id' => $sku ) );
			if ( $result !== false ) {
				return array(
					'status'  => 'updated',
					'message' => 'Product updated in database for display ONLY (NO WOOCOMMERCE IMPORT)',
				);
			} else {
				return array(
					'status'  => 'failed',
					'message' => 'Failed to update product in database: ' . esc_html( $wpdb->last_error ),
				);
			}
		} else {
			// Insert new product
			if ( in_array( 'created_at', $columns, true ) ) {
				$data['created_at'] = current_time( 'mysql' );
			}
			$result = $wpdb->insert( $table, $data );
			if ( $result !== false ) {
				return array(
					'status'  => 'synced',
					'message' => 'Product saved to database for display ONLY (NO WOOCOMMERCE IMPORT)',
				);
			} else {
				return array(
					'status'  => 'failed',
					'message' => 'Failed to save product to database: ' . esc_html( $wpdb->last_error ),
				);
			}
		}
	}

	/**
	 * AJAX handler for disabling automatic sync
	 */
	public function ajax_disable_auto_sync() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		// Clear the scheduled cron job
		wp_clear_scheduled_hook( 'vortem_sync_products' );

		// Log the action
		$this->log_sync_event(
			'auto_sync_disabled',
			array(
				'reason'    => 'Manual disable by user',
				'timestamp' => current_time( 'mysql' ),
			)
		);

		wp_send_json_success( 'Automatic sync disabled successfully' );
	}

	/**
	 * AJAX handler for clearing sync status
	 */
	public function ajax_clear_sync_status() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$this->clear_sync_status();
		wp_send_json_success( 'Sync status cleared' );
	}

	/**
	 * AJAX handler for cleaning up orphaned records
	 */
	public function ajax_cleanup_orphaned() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$orphaned_count = $this->cleanup_orphaned_records();
		wp_send_json_success( "Cleaned up $orphaned_count orphaned records" );
	}

	/**
	 * Clear stuck processing transients
	 * This helps resolve "Product is already being processed" errors
	 */
	public function clear_stuck_transients() {
		global $wpdb;

		// Get all transients that start with our processing keys
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off transient cleanup
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb
				$wpdb->esc_like( '_transient_vortem_' ) . '%' . $wpdb->esc_like( '_processing_' ) . '%'
			)
		);

		$cleared_count = 0;
		foreach ( $transients as $transient ) {
			$transient_name = str_replace( '_transient_', '', $transient->option_name );
			delete_transient( $transient_name );
			++$cleared_count;
		}
		return $cleared_count;
	}

	/**
	 * AJAX handler for clearing stuck transients
	 */
	public function ajax_clear_stuck_transients() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$cleared_count = $this->clear_stuck_transients();
		wp_send_json_success( "Cleared $cleared_count stuck processing transients" );
	}

	/**
	 * Import a single product by product ID (SKU)
	 * Gets product data from database and creates WooCommerce product
	 *
	 * @param string $product_id Product SKU
	 * @return bool|WP_Error
	 */
	public function import_single_product( $product_id ) {
		global $wpdb;

		// Get product data from database
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$product_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE vortem_product_id = %s OR sku = %s",
				$product_id,
				$product_id
			),
			ARRAY_A
		);

		if ( ! $product_record ) {
			return new WP_Error( 'product_not_found', 'Product not found in database' );
		}

		// Check if already imported
		if ( ! empty( $product_record['woo_product_id'] ) ) {
			$existing_woo_product = wc_get_product( $product_record['woo_product_id'] );
			if ( $existing_woo_product ) {
				return new WP_Error( 'already_imported', 'Product already imported to WooCommerce. WooCommerce ID: ' . $product_record['woo_product_id'] );
			}
		}

		// Convert database record to product data format expected by sync_single_product
		$product_data = $this->convert_db_record_to_product_data( $product_record );

		// Import the product with force_sync and import_as_draft options
		$result = $this->sync_single_product(
			$product_data,
			array(
				'force_sync'      => true,
				'import_as_draft' => true,
			)
		);

		if ( isset( $result['status'] ) && ( $result['status'] === 'synced' || $result['status'] === 'updated' ) ) {
			return true;
		} else {
			$error_message = isset( $result['message'] ) ? $result['message'] : 'Import failed';
			return new WP_Error( 'import_failed', $error_message );
		}
	}

	/**
	 * Convert database record to product data format
	 *
	 * @param array $record Database record
	 * @return array Product data
	 */
	private function convert_db_record_to_product_data( $record ) {
		// If we have full product data stored in meta_data, use that
		if ( ! empty( $record['meta_data'] ) ) {
			$stored_data = json_decode( $record['meta_data'], true );
			if ( is_array( $stored_data ) && ! empty( $stored_data ) ) {
				// Ensure SKU is set correctly
				$stored_data['sku'] = $record['sku'];
				$stored_data['id']  = $record['vortem_product_id'];
				return $stored_data;
			}
		}

		// Parse JSON fields
		$images     = ! empty( $record['images'] ) ? json_decode( $record['images'], true ) : array();
		$attributes = ! empty( $record['attributes'] ) ? json_decode( $record['attributes'], true ) : array();
		$tags       = ! empty( $record['tags'] ) ? json_decode( $record['tags'], true ) : array();

		// Build product data structure from individual fields
		$product_data = array(
			'id'                => $record['vortem_product_id'],
			'sku'               => $record['sku'],
			'title'             => $record['name'] ?? $record['title'] ?? 'Untitled Product',
			'description'       => $record['description'] ?? '',
			'short_description' => $record['short_description'] ?? '',
			'weight'            => $record['weight'] ?? '0.5',
			'dimensions'        => array(
				'length' => $record['length'] ?? '10',
				'width'  => $record['width'] ?? '10',
				'height' => $record['height'] ?? '5',
			),
			'categories'        => ! empty( $record['category'] ) ? array( $record['category'] ) : array(),
			'tags'              => is_array( $tags ) ? $tags : array(),
			'images'            => is_array( $images ) ? $images : array(),
			'attributes'        => is_array( $attributes ) ? $attributes : array(),
			'price'             => array(
				'original' => $record['regular_price'] ?? $record['price'] ?? '0.00',
				'sale'     => $record['sale_price'] ?? null,
			),
			'regular_price'     => $record['regular_price'] ?? $record['price'] ?? '0.00',
			'sale_price'        => $record['sale_price'] ?? null,
			'stock_quantity'    => $record['stock_quantity'] ?? 0,
			'stock_status'      => $record['stock_status'] ?? 'instock',
			'manage_stock'      => true,
			'featured'          => false,
			'virtual'           => false,
			'downloadable'      => false,
			'variations'        => array(),
		);

		return $product_data;
	}

	/**
	 * Comprehensive delete function to completely remove a WooCommerce product and all its data
	 *
	 * @param int $wp_product_id WordPress product ID
	 * @return void
	 */
	private function delete_product_completely( $wp_product_id ) {
		global $wpdb;

		if ( empty( $wp_product_id ) || ! is_numeric( $wp_product_id ) ) {
			return;
		}

		// Delete all media (images and videos) before deleting the product
		$this->delete_product_media( $wp_product_id );

		// Get all variations (children) of this product first
		$variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'",
				$wp_product_id
			)
		);

		// Delete all variations and their postmeta
		if ( ! empty( $variation_ids ) ) {
			foreach ( $variation_ids as $variation_id ) {
				// Delete variation postmeta
				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $variation_id ) );

				// Delete variation from posts table
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->posts} WHERE ID = %d",
						$variation_id
					)
				);
			}
		}

		// Delete main product postmeta
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $wp_product_id ) );

		// Delete main product from posts table
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE ID = %d",
				$wp_product_id
			)
		);

		// Delete from WooCommerce product meta lookup table
		if ( class_exists( 'WC_Product' ) ) {
			$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $lookup_table is from $wpdb->prefix (trusted)
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$lookup_table} WHERE product_id = %d",
					$wp_product_id
				)
			);

			// Delete from WooCommerce product attributes lookup table
			$attributes_table = $wpdb->prefix . 'wc_product_attributes_lookup';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $attributes_table is from $wpdb->prefix (trusted)
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$attributes_table} WHERE product_id = %d OR product_or_parent_id = %d",
					$wp_product_id,
					$wp_product_id
				)
			);

			// Also delete variations from lookup tables
			if ( ! empty( $variation_ids ) ) {
				foreach ( $variation_ids as $variation_id ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $lookup_table is from $wpdb->prefix (trusted)
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$lookup_table} WHERE product_id = %d",
							$variation_id
						)
					);
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $attributes_table is from $wpdb->prefix (trusted)
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$attributes_table} WHERE product_id = %d OR product_or_parent_id = %d",
							$variation_id,
							$variation_id
						)
					);
				}
			}
		}

		// Clean up any additional WooCommerce tables if they exist
		// Delete from wp_term_relationships (product categories and tags)
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d",
				$wp_product_id
			)
		);

		// Also delete term relationships for variations
		if ( ! empty( $variation_ids ) ) {
			foreach ( $variation_ids as $variation_id ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d",
						$variation_id
					)
				);
			}
		}
	}

	/**
	 * Delete a single product from WordPress
	 *
	 * @param string $product_id
	 * @return bool|WP_Error
	 */
	public function delete_single_product( $product_id ) {
		global $wpdb;

		// First, check if product exists in WooCommerce (even if not properly imported)
		$exists_check = $this->check_product_exists_in_woocommerce( $product_id );

		if ( ! $exists_check['exists'] || ! $exists_check['woo_product_id'] ) {
			// Product doesn't exist in WooCommerce, but try to clean up our table record
			$table = $wpdb->prefix . 'vortem_products';
			$wpdb->delete( $table, array( 'vortem_product_id' => $product_id ) );
			return new WP_Error( 'product_not_found', 'Product not found in WooCommerce' );
		}

		$wp_product_id = $exists_check['woo_product_id'];

		// Use comprehensive delete function
		$this->delete_product_completely( $wp_product_id );

		// Remove the record from our table if it exists
		$table = $wpdb->prefix . 'vortem_products';
		$wpdb->delete( $table, array( 'vortem_product_id' => $product_id ) );

		return true;
	}

	/**
	 * Check if a product is imported
	 *
	 * @param string $product_id
	 * @return bool
	 */
	public function is_product_imported( $product_id ) {
		global $wpdb;

		// Check if product exists in database AND has been imported to WooCommerce (has woo_product_id)
		$table = $wpdb->prefix . 'vortem_products';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$woo_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT woo_product_id FROM $table WHERE vortem_product_id = %s",
				$product_id
			)
		);

		// Product is only considered imported if it has a WooCommerce product ID
		return ! empty( $woo_product_id );
	}

	/**
	 * Check if a product exists in WooCommerce (even if not properly imported)
	 * Checks by SKU and by _vortem_product_id meta, regardless of stock status
	 *
	 * @param string $product_id Vortem product ID
	 * @param string $sku Product SKU (optional, will be looked up if not provided)
	 * @return array Array with 'exists' => bool, 'woo_product_id' => int|false, 'is_imported' => bool
	 */
	public function check_product_exists_in_woocommerce( $product_id, $sku = '' ) {
		global $wpdb;

		$result = array(
			'exists'         => false,
			'woo_product_id' => false,
			'is_imported'    => false,
		);

		$table = $wpdb->prefix . 'vortem_products';

		// Normalize product_id: remove "AE_" prefix if present for matching
		$normalized_product_id     = $product_id;
		$product_id_with_prefix    = $product_id;
		$product_id_without_prefix = $product_id;

		if ( strpos( $product_id, 'AE_' ) === 0 ) {
			$product_id_without_prefix = substr( $product_id, 3 ); // Remove "AE_" prefix
			$normalized_product_id     = $product_id_without_prefix;
		} else {
			$product_id_with_prefix = 'AE_' . $product_id; // Add "AE_" prefix
		}

		// First, check if product is properly imported (has woo_product_id in our table)
		// Check with both formats: with and without "AE_" prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
		$woo_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT woo_product_id FROM $table WHERE (vortem_product_id = %s OR vortem_product_id = %s) AND woo_product_id IS NOT NULL AND woo_product_id != ''",
				$product_id_with_prefix,
				$product_id_without_prefix
			)
		);

		if ( ! empty( $woo_product_id ) ) {
			// Verify the product actually exists in WooCommerce
			$product = wc_get_product( $woo_product_id );
			if ( $product && $product->exists() ) {
				$result['exists']         = true;
				$result['woo_product_id'] = (int) $woo_product_id;
				$result['is_imported']    = true;
				return $result;
			}
		}

		// If not found via our table, check directly in WooCommerce by SKU
		if ( empty( $sku ) ) {
			// Try to get SKU from our table (check both formats)
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix (trusted)
			$sku = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT sku FROM $table WHERE vortem_product_id = %s OR vortem_product_id = %s LIMIT 1",
					$product_id_with_prefix,
					$product_id_without_prefix
				)
			);
		}

		if ( ! empty( $sku ) ) {
			// Check by SKU in WooCommerce (any status, including out of stock)
			$woo_product_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} as p
                 JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
                 WHERE p.post_type IN ('product', 'product_variation')
                 AND pm.meta_key = '_sku' AND pm.meta_value = %s
                 LIMIT 1",
					$sku
				)
			);

			if ( $woo_product_id ) {
				$product = wc_get_product( $woo_product_id );
				if ( $product && $product->exists() ) {
					$result['exists']         = true;
					$result['woo_product_id'] = (int) $woo_product_id;
					$result['is_imported']    = false; // Exists but not properly imported
					return $result;
				}
			}
		}

		// Also check by _vortem_product_id meta (in case product was created but not tracked properly)
		// Check with both formats: with and without "AE_" prefix
		$woo_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} as p
             JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
             WHERE p.post_type IN ('product', 'product_variation')
             AND pm.meta_key = '_vortem_product_id' 
             AND (pm.meta_value = %s OR pm.meta_value = %s)
             LIMIT 1",
				$product_id_with_prefix,
				$product_id_without_prefix
			)
		);

		if ( $woo_product_id ) {
			$product = wc_get_product( $woo_product_id );
			if ( $product && $product->exists() ) {
				$result['exists']         = true;
				$result['woo_product_id'] = (int) $woo_product_id;
				$result['is_imported']    = false; // Exists but not properly imported
				return $result;
			}
		}

		return $result;
	}

    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
}
