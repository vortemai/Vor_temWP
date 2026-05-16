<?php
/**
 * Vortem Product Fetcher Class
 *
 * Handles fetching products from backend API and importing them to WordPress as drafts
 *
 * External Dependencies Used:
 * - WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | wc_get_product(), wc_get_products(), wc_clean(), WC_Product_Simple, WC_Product_Attribute
 * - WordPress HTTP API - wp_remote_get(), wp_remote_retrieve_response_code(), wp_remote_retrieve_body() for downloading product images
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Product Fetcher
 */
class Vortem_Product_Fetcher {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names from $wpdb->prefix; sync operations.

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
	}

	/**
	 * Fetch basic products from backend API (for listing/preview)
	 *
	 * @param array $params Fetch parameters
	 * @return array
	 */
	public function fetch_basic_products( $params = array() ) {

		try {
			// Fetch basic products from API
			$response = $this->api_client->fetch_basic_products( $params );

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to fetch products: ' . esc_html( $response->get_error_message() ),
				);
			}

			// Check if response is valid
			if ( ! is_array( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid API response format',
				);
			}

			// Check if the API response indicates success
			if ( ! isset( $response['success'] ) || ! $response['success'] ) {
				$error_message = 'Unknown error occurred';
				if ( isset( $response['message'] ) ) {
					$error_message = $response['message'];
				} elseif ( isset( $response['error'] ) ) {
					$error_message = $response['error'];
				}
				return array(
					'success' => false,
					'message' => $error_message,
				);
			}

			$products = isset( $response['products'] ) ? $response['products'] : array();

			// Enrich products with import status from database
			global $wpdb;
			foreach ( $products as &$product ) {
				// Get product ID
				$product_id = isset( $product['product_id'] ) ? $product['product_id'] : ( isset( $product['sku'] ) ? $product['sku'] : '' );

				if ( ! empty( $product_id ) ) {
					// Check if product has been imported
					$woo_product_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT woo_product_id FROM {$wpdb->prefix}vortem_products WHERE vortem_product_id = %s",
							$product_id
						)
					);

					// Add woo_product_id to product data if it exists
					if ( ! empty( $woo_product_id ) ) {
						$product['woo_product_id'] = $woo_product_id;
						// Add preview URL for WooCommerce products
						$preview_url = get_preview_post_link( $woo_product_id );
						if ( $preview_url ) {
							$product['preview_url'] = $preview_url;
						}
					}
				}
			}
			unset( $product ); // Unset reference to avoid issues

			$total_found  = isset( $response['total_found'] ) ? intval( $response['total_found'] ) : count( $products );
			$returned     = isset( $response['returned'] ) ? intval( $response['returned'] ) : count( $products );
			$current_page = isset( $params['page'] ) ? intval( $params['page'] ) : ( isset( $response['page'] ) ? intval( $response['page'] ) : 1 );
			$limit        = isset( $params['limit'] ) ? intval( $params['limit'] ) : get_option( 'vortem_products_per_page', 16 );
			$total_pages  = isset( $response['total_pages'] ) ? intval( $response['total_pages'] ) : ( ( $total_found > 0 && $limit > 0 ) ? ceil( $total_found / $limit ) : 1 );

			return array(
				'success'     => true,
				'products'    => $products,
				'total_found' => $total_found,
				'returned'    => $returned,
				'page'        => $current_page,
				'total_pages' => $total_pages,
				'limit'       => $limit,
				'message'     => 'Successfully fetched ' . count( $products ) . ' basic products',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Exception occurred: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Fetch detailed products from backend API (for import)
	 *
	 * @param array $params Fetch parameters
	 * @return array
	 */
	public function fetch_products( $params = array() ) {

		try {
			// Fetch products from API
			$response = $this->api_client->fetch_products( $params );

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to fetch products: ' . esc_html( $response->get_error_message() ),
				);
			}

			// Check if response is valid
			if ( ! is_array( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid API response format',
				);
			}

			// Check if the API response indicates success
			if ( ! isset( $response['success'] ) || ! $response['success'] ) {
				$error_message = 'Unknown error occurred';
				if ( isset( $response['message'] ) ) {
					$error_message = $response['message'];
				} elseif ( isset( $response['error'] ) ) {
					$error_message = $response['error'];
				}
				return array(
					'success' => false,
					'message' => $error_message,
				);
			}

			$products = isset( $response['products'] ) ? $response['products'] : array();

			$total_found = isset( $response['total_found'] ) ? $response['total_found'] : count( $products );
			$returned    = isset( $response['returned'] ) ? $response['returned'] : count( $products );

			return array(
				'success'     => true,
				'products'    => $products,
				'total_found' => $total_found,
				'returned'    => $returned,
				'message'     => 'Successfully fetched ' . count( $products ) . ' products',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Exception occurred: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Validate API endpoint
	 *
	 * @return array
	 */
	public function validate_endpoint() {

		try {
			$response = $this->api_client->validate_endpoint();

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Endpoint validation failed: ' . esc_html( $response->get_error_message() ),
				);
			}

			return array(
				'success' => true,
				'message' => 'Endpoint is valid and accessible',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Validation exception: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Import fetched products to WordPress as drafts
	 *
	 * @param array $products Products to import
	 * @return array
	 */
	public function import_products_to_wordpress( $products ) {

		// Check if WooCommerce is active
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return array(
				'success' => false,
				'message' => 'WooCommerce is not active. Please activate WooCommerce plugin first.',
			);
		}

		if ( empty( $products ) ) {
			return array(
				'success' => false,
				'message' => 'No products to import',
			);
		}

		$imported_count = 0;
		$failed_count   = 0;
		$errors         = array();

		foreach ( $products as $product_data ) {
			try {
				$result = $this->import_single_product( $product_data );

				if ( $result['success'] ) {
					++$imported_count;
				} else {
					++$failed_count;
					$errors[] = $product_data['product_id'] . ': ' . $result['message'];
				}
			} catch ( Exception $e ) {
				++$failed_count;
				$errors[] = $product_data['product_id'] . ': Exception - ' . $e->getMessage();
			}
		}

		return array(
			'success'         => $imported_count > 0,
			'imported_count'  => $imported_count,
			'failed_count'    => $failed_count,
			'total_processed' => count( $products ),
			'errors'          => $errors,
			'message'         => "Imported {$imported_count} products successfully, {$failed_count} failed",
		);
	}

	/**
	 * Import single product to WordPress as draft
	 *
	 * @param array $product_data Product data from API
	 * @return array
	 */
	private function import_single_product( $product_data ) {

		try {
			// Use the robust product creator
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-creator.php';
			$creator = new Vortem_Product_Creator();

			$result = $creator->create_product_from_api( $product_data );

			if ( $result['success'] ) {
				return $result;
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Exception: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Convert API product data to WordPress format
	 *
	 * @param array $api_product API product data
	 * @return array WordPress product data
	 */
	private function convert_api_product_to_wp_format( $api_product ) {
		// Extract pricing from variations
		$regular_price  = '0.00';
		$sale_price     = null;
		$stock_quantity = 0;

		if ( isset( $api_product['variations'] ) && is_array( $api_product['variations'] ) && ! empty( $api_product['variations'] ) ) {
			$variation      = $api_product['variations'][0];
			$regular_price  = isset( $variation['price'] ) ? $variation['price'] : '0.00';
			$sale_price     = isset( $variation['sale_price'] ) && ! empty( $variation['sale_price'] ) ? $variation['sale_price'] : null;
			$stock_quantity = isset( $variation['stock'] ) ? intval( $variation['stock'] ) : 0;
		} elseif ( isset( $api_product['price']['original'] ) ) {
			$regular_price = $api_product['price']['original'];
			$sale_price    = isset( $api_product['price']['sale'] ) ? $api_product['price']['sale'] : null;
		}

		return array(
			'name'              => $api_product['title'] ?? 'Untitled Product',
			'description'       => $api_product['description'] ?? '',
			'short_description' => '',
			'sku'               => $api_product['sku'] ?? $api_product['product_id'],
			'regular_price'     => $regular_price,
			'sale_price'        => $sale_price,
			'stock_quantity'    => $stock_quantity,
			'stock_status'      => $stock_quantity > 0 ? 'instock' : 'outofstock',
			'manage_stock'      => true,
			'weight'            => $api_product['weight'] ?? '0.5',
			'length'            => $api_product['dimensions']['length'] ?? '10',
			'width'             => $api_product['dimensions']['width'] ?? '10',
			'height'            => $api_product['dimensions']['height'] ?? '5',
			'categories'        => $api_product['categories'] ?? array(),
			'tags'              => array(),
			'images'            => $api_product['images'] ?? array(),
			'variations'        => $api_product['variations'] ?? array(),
			'attributes'        => $api_product['attributes'] ?? array(),
			'status'            => 'draft', // Import as draft
		);
	}

	/**
	 * Create WordPress product
	 *
	 * @param array $product_data Product data
	 * @return int|WP_Error Product ID or error
	 */
	private function create_wp_product( $product_data ) {

		// Check if WooCommerce is active
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active' );
		}

		// CRITICAL FIX: Check if product already exists by SKU to prevent duplicate
		global $wpdb;
		$existing_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} as p
             JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
             WHERE p.post_type IN ('product', 'product_variation')
             AND pm.meta_key = '_sku' AND pm.meta_value = %s
             LIMIT 1",
				$product_data['sku']
			)
		);

		if ( $existing_product_id ) {
			return (int) $existing_product_id; // Return existing product ID
		}

		$product = new WC_Product_Simple();

		// Set basic product data
		$product->set_name( $product_data['name'] );
		$product->set_description( $product_data['description'] );
		$product->set_short_description( $product_data['short_description'] );
		$product->set_sku( $product_data['sku'] );
		$product->set_regular_price( $product_data['regular_price'] );

		if ( $product_data['sale_price'] ) {
			$product->set_sale_price( $product_data['sale_price'] );
		}

		$product->set_status( 'draft' ); // Import as draft
		$product->set_catalog_visibility( 'visible' );
		$product->set_featured( false );
		$product->set_virtual( false );
		$product->set_downloadable( false );

		// Stock management
		$product->set_manage_stock( $product_data['manage_stock'] );
		$product->set_stock_quantity( $product_data['stock_quantity'] );
		$product->set_stock_status( $product_data['stock_status'] );

		// Physical dimensions
		$product->set_weight( $product_data['weight'] );
		$product->set_length( $product_data['length'] );
		$product->set_width( $product_data['width'] );
		$product->set_height( $product_data['height'] );

		// Add Vortem meta
		$product->add_meta_data( '_vortem_product_id', $product_data['sku'], true );
		$product->add_meta_data( '_vortem_imported', true, true );
		$product->add_meta_data( '_vortem_import_date', current_time( 'mysql' ), true );
		$product_id = $product->save();

		return $product_id;
	}

	/**
	 * Update existing WordPress product
	 *
	 * @param WC_Product $product Existing product
	 * @param array      $product_data New product data
	 */
	private function update_wp_product( $product, $product_data ) {
		$product->set_name( $product_data['name'] );
		$product->set_description( $product_data['description'] );
		$product->set_short_description( $product_data['short_description'] );
		$product->set_regular_price( $product_data['regular_price'] );

		if ( $product_data['sale_price'] ) {
			$product->set_sale_price( $product_data['sale_price'] );
		}

		$product->set_status( 'draft' ); // Keep as draft
		$product->set_manage_stock( $product_data['manage_stock'] );
		$product->set_stock_quantity( $product_data['stock_quantity'] );
		$product->set_stock_status( $product_data['stock_status'] );

		$product->set_weight( $product_data['weight'] );
		$product->set_length( $product_data['length'] );
		$product->set_width( $product_data['width'] );
		$product->set_height( $product_data['height'] );

		$product->add_meta_data( '_vortem_updated', true, true );
		$product->add_meta_data( '_vortem_update_date', current_time( 'mysql' ), true );

		$product->save();
	}

	/**
	 * Handle product images
	 *
	 * @param int   $product_id Product ID
	 * @param array $product_data Product data
	 */
	private function handle_product_images( $product_id, $product_data ) {
		if ( empty( $product_data['images'] ) ) {
			return;
		}

		$images      = $product_data['images'];
		$gallery_ids = array();

		// Handle main image
		if ( isset( $images['main'] ) && ! empty( $images['main'] ) ) {
			$attachment_id = $this->download_and_attach_image( $images['main'], $product_id, $product_data['sku'] );
			if ( $attachment_id ) {
				set_post_thumbnail( $product_id, $attachment_id );
			}
		}

		// Handle gallery images
		if ( isset( $images['gallery'] ) && is_array( $images['gallery'] ) ) {
			foreach ( $images['gallery'] as $image_url ) {
				if ( ! empty( $image_url ) ) {
					$attachment_id = $this->download_and_attach_image( $image_url, $product_id, $product_data['sku'] );
					if ( $attachment_id ) {
						$gallery_ids[] = $attachment_id;
					}
				}
			}
		}

		// Set gallery images
		if ( ! empty( $gallery_ids ) ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
		}
	}

	/**
	 * Download and attach image to product
	 *
	 * @param string $image_url Image URL
	 * @param int    $product_id Product ID
	 * @param string $sku Product SKU for filename
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_and_attach_image( $image_url, $product_id, $sku = '' ) {
		// Phone-home gate: refuse to download remote images until consent is granted.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return false;
		}

		// Check if image already exists
		$existing_attachment = $this->get_attachment_by_url( $image_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		// Download image
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

		// Generate filename using SKU and timestamp
		if ( ! empty( $sku ) ) {
			$sanitized_sku = sanitize_file_name( $sku );
			$filename      = $sanitized_sku . '_' . time() . '.' . $file_extension;
		} else {
			$filename = 'vortem-product-' . $product_id . '_' . time() . '.' . $file_extension;
		}
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['path'] . '/' . sanitize_file_name( $filename );

		// Validate that file path is within upload directory
		$real_upload_path = realpath( $upload_dir['basedir'] );
		$real_file_path   = realpath( dirname( $file_path ) );
		if ( $real_file_path === false || $real_upload_path === false || strpos( $real_file_path, $real_upload_path ) !== 0 ) {
			return false;
		}

		// Save image. The destination path is rooted in wp_upload_dir() and
		// verified above with realpath() to be inside the uploads directory.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a downloaded image attachment to the uploads dir; WP_Filesystem is overkill for a single binary write.
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
	 * Handle product categories
	 *
	 * @param int   $product_id Product ID
	 * @param array $product_data Product data
	 */
	private function handle_product_categories( $product_id, $product_data ) {
		if ( empty( $product_data['categories'] ) ) {
			return;
		}

		$category_ids = array();
		$categories   = $product_data['categories'];

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
			// Handle old format: array of category name strings (backward compatibility)
			if ( is_array( $categories ) ) {
				foreach ( $categories as $category_name ) {
					if ( empty( $category_name ) || ! is_string( $category_name ) ) {
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
		}
	}

	/**
	 * Extract category name from category data (supports both old and new formats)
	 *
	 * @param mixed $categories Category data
	 * @return string|null Category name or null
	 */
	private function extract_category_name_from_data( $categories ) {
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
	 * Handle product attributes
	 *
	 * @param int   $product_id Product ID
	 * @param array $product_data Product data
	 */
	private function handle_product_attributes( $product_id, $product_data ) {
		if ( empty( $product_data['attributes'] ) || ! is_array( $product_data['attributes'] ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product_attributes = array();

		foreach ( $product_data['attributes'] as $attribute ) {
			if ( isset( $attribute['name'] ) && isset( $attribute['value'] ) ) {
				$attribute_name  = sanitize_title( $attribute['name'] );
				$attribute_value = sanitize_text_field( $attribute['value'] );

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
		}

		if ( ! empty( $product_attributes ) ) {
			$product->set_attributes( $product_attributes );
			$product->save();
		}
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
	 * Handle product variations
	 *
	 * @param int   $product_id Product ID
	 * @param array $product_data Product data
	 */
	private function handle_product_variations( $product_id, $product_data ) {
		if ( empty( $product_data['variations'] ) || ! is_array( $product_data['variations'] ) ) {
			return;
		}

		// Convert to variable product if we have variations
		$product = wc_get_product( $product_id );
		if ( $product && $product->get_type() === 'simple' ) {
			// Convert to variable product
			$product->set_type( 'variable' );
			$product->save();
		}

		foreach ( $product_data['variations'] as $variation_data ) {
			$this->create_product_variation( $product_id, $variation_data );
		}
	}

	/**
	 * Create product variation
	 *
	 * @param int   $product_id Product ID
	 * @param array $variation_data Variation data
	 */
	private function create_product_variation( $product_id, $variation_data ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_sku( $variation_data['sku'] ?? '' );
		$variation->set_regular_price( $variation_data['price'] ?? '0.00' );

		if ( isset( $variation_data['sale_price'] ) && ! empty( $variation_data['sale_price'] ) ) {
			$variation->set_sale_price( $variation_data['sale_price'] );
		}

		$variation->set_status( 'draft' ); // Import as draft
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( $variation_data['stock'] ?? 0 );
		$variation->set_stock_status( $variation_data['stock'] > 0 ? 'instock' : 'outofstock' );

		// Set attributes
		if ( isset( $variation_data['attributes'] ) && is_array( $variation_data['attributes'] ) ) {
			foreach ( $variation_data['attributes'] as $attr_name => $attr_value ) {
				$taxonomy = 'pa_' . sanitize_title( $attr_name );
				$variation->set_attribute( $taxonomy, $attr_value );
			}
		}

		$variation->save();
	}

	/**
	 * AJAX handler for fetching products
	 */
	public static function ajax_fetch_products() {

		try {
			check_ajax_referer( 'vortem_admin_nonce', 'nonce' );
			if ( ! vortem_current_user_can_manage() ) {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
					)
				);
			}
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
			$limit = Vortem_Security::validate_limit( isset( $_POST['limit'] ) ? wp_unslash( $_POST['limit'] ) : get_option( 'vortem_products_per_page', 16 ), 16, 1, 100 );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
			$page    = Vortem_Security::validate_page( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1, 1 );
			$fetcher = new self();
			$result  = $fetcher->fetch_basic_products(
				array(
					'limit' => $limit,
					'page'  => $page,
				)
			);
			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Exception: ' . esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler for validating endpoint
	 */
	public static function ajax_validate_endpoint() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$fetcher = new self();
		$result  = $fetcher->validate_endpoint();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for importing fetched products
	 */
	public static function ajax_import_fetched_products() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		// Check if WooCommerce is active first
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce is not active. Please activate WooCommerce plugin first.' ) );
		}

		// Test WooCommerce functionality first
		try {
			$test_product = new WC_Product_Simple();
			$test_product->set_name( 'Test Import Product' );
			$test_product->set_sku( 'test-import-' . time() );
			$test_product->set_regular_price( '1.00' );
			$test_product->set_status( 'draft' );
			$test_result = $test_product->save();

			if ( is_wp_error( $test_result ) ) {
				wp_send_json_error( array( 'message' => 'WooCommerce test failed: ' . esc_html( $test_result->get_error_message() ) ) );
			}

			// Delete the test product
			wp_delete_post( $test_result, true );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'WooCommerce test exception: ' . esc_html( $e->getMessage() ) ) );
		}

		// Fetch products directly from API instead of reading from file
		$fetcher    = new self();
		$api_client = new Vortem_Api_Client();

		// Get the limit and page from the request or use defaults from settings
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$limit = Vortem_Security::validate_limit( isset( $_POST['limit'] ) ? wp_unslash( $_POST['limit'] ) : get_option( 'vortem_products_per_page', 16 ), 16, 1, 100 );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Input is validated and sanitized by Vortem_Security::validate_* methods
		$page = Vortem_Security::validate_page( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1, 1 );

		$response = $api_client->fetch_products(
			array(
				'limit' => $limit,
				'page'  => $page,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Failed to fetch products: ' . esc_html( $response->get_error_message() ) ) );
		}

		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			$error_message = isset( $response['message'] ) ? $response['message'] : 'Unknown error occurred';
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		if ( ! isset( $response['products'] ) || ! is_array( $response['products'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid product data from API.' ) );
		}

		$result = $fetcher->import_products_to_wordpress( $response['products'] );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for importing single product
	 */
	public static function ajax_import_single_product() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';

		if ( empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product ID is required' ) );
		}

		try {
			$fetcher = new self();
			$result  = $fetcher->import_single_product_to_wordpress( $product_id );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Exception: ' . esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler for deleting single product
	 */
	public static function ajax_delete_single_product() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';

		if ( empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product ID is required' ) );
		}

		try {
			$fetcher = new self();
			$result  = $fetcher->delete_single_product_from_wordpress( $product_id );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Exception: ' . esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Import single product to WordPress
	 *
	 * @param string $product_id Product ID
	 * @param string $import_type Import type: 'normal' or 'seo'
	 * @return array
	 */
	public function import_single_product_to_wordpress( $product_id, $import_type = 'normal' ) {

		try {
			// Fetch specific product from API using product_id parameter
			$api_client = new Vortem_Api_Client();

			// Use product_id parameter to fetch specific product directly
			$params = array( 'product_id' => $product_id );

			$response = $api_client->fetch_products( $params );

			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to fetch product: ' . esc_html( $response->get_error_message() ),
				);
			}

			if ( ! isset( $response['success'] ) || ! $response['success'] ) {
				$error_message = isset( $response['message'] ) ? $response['message'] : 'Failed to fetch product from API';
				if ( isset( $response['error'] ) ) {
					$error_message = $response['error'];
				}
				return array(
					'success' => false,
					'message' => $error_message . ' (Product ID: ' . $product_id . ')',
				);
			}

			// Extract product data from response
			$product_data = null;

			// Check if response has products array
			if ( isset( $response['products'] ) && is_array( $response['products'] ) && ! empty( $response['products'] ) ) {
				// Get first product from array
				$product_data = $response['products'][0];
			} elseif ( isset( $response['product'] ) && is_array( $response['product'] ) ) {
				// Some APIs return single product directly
				$product_data = $response['product'];
			} else {
				// Response might be the product data directly
				$product_data = $response;
			}

			if ( ! $product_data || ! is_array( $product_data ) ) {
				return array(
					'success' => false,
					'message' => 'Product not found in API response',
				);
			}

			// Import the product to WooCommerce
			$result = $this->import_single_product( $product_data );

			if ( $result['success'] ) {
				$woo_product_id = $result['product_id'] ?? null;

				if ( $woo_product_id ) {
					// Save full product data to database
					global $wpdb;
					$table = $wpdb->prefix . 'vortem_products';

					// Convert product data to standard format for database
					require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-product-manager.php';
					$product_manager   = new Vortem_Product_Manager();
					$converted_product = $product_manager->convert_standard_product_data( $product_data );

					// Check if product already exists in database
					$existing = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$table} WHERE sku = %s OR vortem_product_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table from $wpdb->prefix
							$product_id,
							$product_id
						)
					);

					// Prepare full product data for database
					$sku  = $converted_product['sku'] ?? $product_id;
					$data = array(
						'vortem_product_id' => $sku,
						'woo_product_id'    => $woo_product_id,
						'sku'               => $sku,
						'name'              => $converted_product['title'] ?? 'Untitled Product',
						'description'       => $converted_product['description'] ?? '',
						'price'             => $converted_product['regular_price'] ?? '0.00',
						'regular_price'     => $converted_product['regular_price'] ?? '0.00',
						'sale_price'        => $converted_product['sale_price'] ?? null,
						'stock_quantity'    => $converted_product['stock_quantity'] ?? 0,
						'stock_status'      => $converted_product['stock_status'] ?? 'instock',
						'weight'            => $converted_product['weight'] ?? null,
						'length'            => $converted_product['dimensions']['length'] ?? null,
						'width'             => $converted_product['dimensions']['width'] ?? null,
						'height'            => $converted_product['dimensions']['height'] ?? null,
						'category'          => $this->extract_category_name_from_data( $converted_product['categories'] ?? null ),
						'tags'              => isset( $converted_product['tags'] ) ? wp_json_encode( $converted_product['tags'] ) : null,
						'images'            => isset( $converted_product['images'] ) ? wp_json_encode( $converted_product['images'] ) : null,
						'attributes'        => isset( $converted_product['attributes'] ) ? wp_json_encode( $converted_product['attributes'] ) : null,
						'meta_data'         => wp_json_encode( $converted_product ),
						'sync_status'       => 'synced',
						'sync_date'         => current_time( 'mysql' ),
						'last_updated'      => current_time( 'mysql' ),
					);

					if ( $existing ) {
						// Update existing record with full data
						$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
					} else {
						// Insert new record with full data
						$data['created_at'] = current_time( 'mysql' );
						$wpdb->insert( $table, $data );
					}
				}

				// Try to get and store the imported _id for future deletion
				$this->store_imported_product_id( $product_id, $woo_product_id );

				// If SEO import is requested, fetch SEO content and update product
				if ( $import_type === 'seo' && $woo_product_id ) {
					$seo_result = $this->update_product_with_seo_content( $woo_product_id, $product_id );
					if ( is_wp_error( $seo_result ) ) {
						// Log error but don't fail the import - product was imported successfully
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							vortem_log( 'SEO update failed for product ' . $product_id . ': ' . $seo_result->get_error_message() );
						}
					}
				}

				return array(
					'success'       => true,
					'message'       => 'Product imported successfully' . ( $import_type === 'seo' ? ' with SEO optimization' : '' ),
					'product_id'    => $woo_product_id,
					'wp_product_id' => $woo_product_id,
					'is_duplicate'  => isset( $result['is_duplicate'] ) ? $result['is_duplicate'] : false,
					'skipped'       => isset( $result['skipped'] ) ? $result['skipped'] : false,
				);
			} else {
				return $result;
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => 'Exception: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Store the imported product _id in WooCommerce product meta for efficient deletion
	 *
	 * @param string $product_id Vortem product ID
	 * @param int    $woo_product_id WooCommerce product ID
	 */
	private function store_imported_product_id( $product_id, $woo_product_id ) {
		try {
			// Try to find the _id from imported products API
			if ( ! class_exists( 'Vortem_Api_Client' ) ) {
				require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
			}

			$api_client = new Vortem_Api_Client();
			$found_id   = null;

			// Search through imported products to find the _id
			$page  = 1;
			$limit = 50; // Smaller page size for faster search

			while ( $page <= 5 ) { // Limit pages to avoid long searches
				$imported_response = $api_client->fetch_imported_products(
					array(
						'page'  => $page,
						'limit' => $limit,
					)
				);

				if ( is_wp_error( $imported_response ) ) {
					break; // API error, stop trying
				}

				if ( isset( $imported_response['success'] ) && $imported_response['success'] &&
					isset( $imported_response['products'] ) && is_array( $imported_response['products'] ) ) {

					foreach ( $imported_response['products'] as $product ) {
						if ( isset( $product['product_id'] ) && (string) $product['product_id'] === (string) $product_id ) {
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

			// If we found the _id, store it in the WooCommerce product meta
			if ( $found_id && $woo_product_id ) {
				update_post_meta( $woo_product_id, '_vortem_imported_id', $found_id );
				vortem_log( 'Stored imported ID ' . $found_id . ' for product ' . $product_id . ' (WooCommerce ID: ' . $woo_product_id . ')' );
			}
		} catch ( Exception $e ) {
			// Don't fail the import if we can't store the _id
			vortem_log( 'Failed to store imported ID for product ' . $product_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Check if product is already imported
	 *
	 * @param string $product_id Product ID
	 * @return bool
	 */
	public function is_product_imported( $product_id ) {
		global $wpdb;

		$wp_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_vortem_product_id' AND meta_value = %s",
				$product_id
			)
		);

		return ! empty( $wp_product_id );
	}

	/**
	 * AJAX handler for checking product import status
	 */
	public static function ajax_check_product_status() {
		check_ajax_referer( 'vortem_admin_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unauthorized', 'vortem-ai' ),
				)
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';

		if ( empty( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Product ID is required' ) );
		}

		$fetcher     = new self();
		$is_imported = $fetcher->is_product_imported( $product_id );

		wp_send_json_success(
			array(
				'product_id'  => $product_id,
				'is_imported' => $is_imported,
			)
		);
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

		// Get featured image
		$featured_image_id = get_post_thumbnail_id( $product_id );
		if ( $featured_image_id ) {
			$media['images'][] = $featured_image_id;
		}

		// Get gallery images
		$gallery_images = get_post_meta( $product_id, '_product_image_gallery', true );
		if ( $gallery_images ) {
			$gallery_ids = explode( ',', $gallery_images );
			foreach ( $gallery_ids as $image_id ) {
				$image_id = trim( $image_id );
				if ( $image_id && is_numeric( $image_id ) && ! in_array( $image_id, $media['images'], true ) ) {
					$media['images'][] = intval( $image_id );
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
			if ( $variation_image_id && ! in_array( $variation_image_id, $media['images'], true ) ) {
				$media['images'][] = $variation_image_id;
			}
		}

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
	 * Delete imported product from WordPress
	 *
	 * @param string $product_id Product ID
	 * @return array
	 */
	public function delete_single_product_from_wordpress( $product_id ) {

		global $wpdb;

		// Find the WordPress product ID by Vortem product ID
		$wp_product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_vortem_product_id' AND meta_value = %s",
				$product_id
			)
		);

		if ( ! $wp_product_id ) {
			return array(
				'success' => false,
				'message' => 'Product not found in WordPress',
			);
		}

		// Get media count before deletion for response
		$media_info   = $this->get_all_product_media( $wp_product_id );
		$media_result = array(
			'deleted_images' => count( $media_info['images'] ),
			'deleted_videos' => count( $media_info['videos'] ),
			'errors'         => array(),
		);

		// Use comprehensive delete function to completely remove product and all related data
		// This will handle media deletion and all database cleanup
		$this->delete_product_completely( $wp_product_id );

		return array(
			'success'        => true,
			'message'        => 'Product deleted successfully',
			'product_id'     => $product_id,
			'wp_product_id'  => $wp_product_id,
			'deleted_images' => $media_result['deleted_images'],
			'deleted_videos' => $media_result['deleted_videos'],
		);
	}

	/**
	 * Update product with SEO content from API
	 *
	 * @param int    $woo_product_id WooCommerce product ID
	 * @param string $product_id Original product SKU/ID
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	private function update_product_with_seo_content( $woo_product_id, $product_id ) {
		try {
			// Get the product object
			$product = wc_get_product( $woo_product_id );
			if ( ! $product || ! $product->exists() ) {
				return new WP_Error( 'product_not_found', 'Product not found in WooCommerce' );
			}

			// Fetch SEO content from API
			$api_client = new Vortem_Api_Client();
			$seo_data   = $api_client->get_product_seo_content( $product_id );

			if ( is_wp_error( $seo_data ) ) {
				return $seo_data;
			}

			// Check if we have SEO data
			if ( ! is_array( $seo_data ) || empty( $seo_data ) ) {
				return new WP_Error( 'no_seo_data', 'No SEO data received from API' );
			}

			// Update product title with seo_title if available
			if ( ! empty( $seo_data['seo_title'] ) ) {
				$product->set_name( wc_clean( $seo_data['seo_title'] ) );
			}

			// Update product description with seo_description if available
			if ( ! empty( $seo_data['seo_description'] ) ) {
				$product->set_description( wp_kses_post( $seo_data['seo_description'] ) );
			}

			// Save the product to persist title and description changes
			$product->save();

			// Update product tags if available
			if ( ! empty( $seo_data['tags'] ) ) {
				// Handle tags - can be comma-separated string or array
				$tags = $seo_data['tags'];
				if ( is_string( $tags ) ) {
					// Split comma-separated tags and clean them
					$tags = array_map( 'trim', explode( ',', $tags ) );
				}

				if ( is_array( $tags ) && ! empty( $tags ) ) {
					// Clean and sanitize tags
					$clean_tags = array_map( 'wc_clean', $tags );
					$clean_tags = array_filter( $clean_tags ); // Remove empty values

					if ( ! empty( $clean_tags ) ) {
						// Set tags - WordPress will handle tag creation automatically
						wp_set_object_terms( $woo_product_id, $clean_tags, 'product_tag' );
					}
				}
			}

			// Persist SEO fields to plugin-owned post meta. Rendered on the
			// frontend by Vortem_SEO; vortem-prefixed keys avoid colliding
			// with Yoast / Rank Math / AIOSEO / SEOPress storage.
			if ( ! empty( $seo_data['keyphrase'] ) ) {
				update_post_meta( $woo_product_id, Vortem_SEO::META_FOCUSKW, sanitize_text_field( $seo_data['keyphrase'] ) );
			}

			if ( ! empty( $seo_data['meta_description'] ) ) {
				update_post_meta( $woo_product_id, Vortem_SEO::META_DESC, sanitize_text_field( $seo_data['meta_description'] ) );
			}

			// API still ships the field as `yoast_seo_title` for backwards
			// compatibility; accept the new `seo_meta_title` name too.
			$meta_title = '';
			if ( ! empty( $seo_data['seo_meta_title'] ) ) {
				$meta_title = $seo_data['seo_meta_title'];
			} elseif ( ! empty( $seo_data['yoast_seo_title'] ) ) {
				$meta_title = $seo_data['yoast_seo_title'];
			}
			if ( '' !== $meta_title ) {
				update_post_meta( $woo_product_id, Vortem_SEO::META_TITLE, sanitize_text_field( $meta_title ) );
			}

			// Update database record with SEO data if it exists
			global $wpdb;
			$table    = $wpdb->prefix . 'vortem_products';
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE woo_product_id = %d",
					$woo_product_id
				)
			);

			if ( $existing ) {
				$update_data = array();
				if ( ! empty( $seo_data['seo_title'] ) ) {
					$update_data['name'] = wc_clean( $seo_data['seo_title'] );
				}
				if ( ! empty( $seo_data['seo_description'] ) ) {
					$update_data['description'] = wp_kses_post( $seo_data['seo_description'] );
				}
				if ( ! empty( $seo_data['tags'] ) ) {
					$tags                = is_array( $seo_data['tags'] ) ? $seo_data['tags'] : explode( ',', $seo_data['tags'] );
					$update_data['tags'] = wp_json_encode( array_map( 'wc_clean', $tags ) );
				}

				if ( ! empty( $update_data ) ) {
					$update_data['last_updated'] = current_time( 'mysql' );
					$wpdb->update( $table, $update_data, array( 'id' => $existing->id ) );
				}
			}

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'seo_update_exception', 'Exception while updating SEO content: ' . $e->getMessage() );
		}
	}

    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
}
