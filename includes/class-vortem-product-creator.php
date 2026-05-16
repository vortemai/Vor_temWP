<?php
/**
 * Vortem Product Creator Class
 *
 * Creates or updates WooCommerce products from API data
 * Based on the robust implementation from vortem.ai-new
 *
 * External Dependencies Used:
 * - WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | wc_get_product(), wc_clean(), wc_string_to_bool(), WC_Product_Attribute, WC_Product_Variation, wc_delete_product_transients()
 * - WordPress HTTP API - wp_remote_get(), wp_remote_retrieve_response_code(), wp_remote_retrieve_body() for downloading product images
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Product Creator
 */
class Vortem_Product_Creator {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Product sync operations; table names from $wpdb.


	/**
	 * Create product - wrapper method for backward compatibility
	 *
	 * @param array $product_data Product data
	 * @param array $options Options array (unused, kept for compatibility)
	 * @return int|WP_Error Product ID on success, WP_Error on failure
	 */
	public function create_product( $product_data, $options = array() ) {

		// Call the actual implementation
		$result = $this->create_product_from_api( $product_data );

		// Convert result format to match expected interface
		if ( isset( $result['success'] ) && $result['success'] && isset( $result['product_id'] ) ) {
			return (int) $result['product_id'];
		} else {
			$error_message = isset( $result['message'] ) ? $result['message'] : 'Failed to create product';
			return new WP_Error( 'product_creation_failed', $error_message );
		}
	}

	/**
	 * Create or update a product from API data
	 *
	 * @param array $api_product Product data from API
	 * @param array $options Optional options array (e.g., 'skip_transient_check' => true)
	 * @return array Result array with success status and product ID
	 */
	public function create_product_from_api( $api_product, $options = array() ) {
		try {
			$sku = wc_clean( $api_product['sku'] ?? $api_product['product_id'] ?? '' );
			if ( empty( $sku ) ) {
				throw new Exception( 'Product SKU is required.' );
			}

			// LOCK MECHANISM: Prevent concurrent imports of the same SKU to avoid race conditions
			// Skip lock check if skip_transient_check option is set
			$skip_lock_check = isset( $options['skip_transient_check'] ) && $options['skip_transient_check'];
			$lock_key        = 'vortem_product_lock_' . md5( $sku );
			$lock_duration   = 60; // 60 seconds

			// Check if another process is already importing this SKU (unless skip_transient_check is set)
			if ( ! $skip_lock_check && get_transient( $lock_key ) ) {

				// Wait briefly to allow the other process to complete
				usleep( 500000 ); // 0.5 seconds

				// Check again if product was created during the wait
				global $wpdb;
				$product_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} as p
                     JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
                     WHERE p.post_type IN ('product', 'product_variation')
                     AND pm.meta_key = '_sku' AND pm.meta_value = %s
                     LIMIT 1",
						$sku
					)
				);

				if ( $product_id ) {
					return array(
						'success'    => true,
						'product_id' => (int) $product_id,
						'action'     => 'skipped_race',
						'message'    => 'Product created by concurrent request',
						'skipped'    => true,
					);
				}
			}

			// Set lock for this import operation (unless skip_transient_check is set)
			if ( ! $skip_lock_check ) {
				set_transient( $lock_key, true, $lock_duration );
			}

			// Check for existing product by SKU and product_id (via _vortem_product_id meta)
			global $wpdb;
			$vortem_product_id = $api_product['product_id'] ?? $api_product['id'] ?? '';

			// First check by SKU
			$existing_product_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} as p
                 JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
                 WHERE p.post_type IN ('product', 'product_variation')
                 AND pm.meta_key = '_sku' AND pm.meta_value = %s
                 LIMIT 1",
					$sku
				)
			);

			// If not found by SKU, check by product_id via _vortem_product_id meta
			if ( ! $existing_product_id && ! empty( $vortem_product_id ) ) {
				$existing_product_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} as p
                     JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id
                     WHERE p.post_type IN ('product', 'product_variation')
                     AND pm.meta_key = '_vortem_product_id' AND pm.meta_value = %s
                     LIMIT 1",
						$vortem_product_id
					)
				);
			}

			// CRITICAL FIX: If product already exists with this SKU or product_id in WooCommerce, skip completely to prevent duplicate import
			if ( $existing_product_id ) {

				// Verify the product actually exists in WooCommerce
				$existing_product = wc_get_product( $existing_product_id );
				if ( $existing_product && $existing_product->exists() ) {
					// Release lock (unless skip_transient_check is set)
					if ( ! $skip_lock_check ) {
						delete_transient( $lock_key );
					}

					// Return immediately without creating or updating anything
					return array(
						'success'      => true,
						'product_id'   => (int) $existing_product_id,
						'action'       => 'skipped_exists',
						'message'      => 'Product already exists in WooCommerce - skipping to prevent duplicate',
						'skipped'      => true,
						'is_duplicate' => true,
					);
				}
			}

			// Product doesn't exist, proceed with creating a new product
			// Determine product type based on variations
			// Create variable product if there are ANY variations
			$type    = ( ! empty( $api_product['variations'] ) && count( $api_product['variations'] ) > 0 ) ? 'variable' : 'simple';
			$product = $type === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();

			$this->set_product_data( $product, $api_product, true );

			// Handle attributes
			$this->handle_attributes( $product, $api_product );

			// Handle categories
			if ( ! empty( $api_product['categories'] ) ) {
				$cat_ids = $this->get_or_create_category_ids( $api_product['categories'] );
				if ( ! empty( $cat_ids ) ) {
					$product->set_category_ids( $cat_ids );
				}
			}

			// Save the product first to get a valid ID
			$pid = $product->save();
			if ( ! $pid ) {
				throw new Exception( 'Failed to save product.' );
			}

			$product = wc_get_product( $pid );

			// Handle images after product is saved
			$this->set_product_images( $product, $api_product );

			// Save the product again to persist the images
			$product->save();

			// Force refresh the product object to ensure all changes are loaded
			$product = wc_get_product( $pid );

			// Verify gallery images were set correctly
			$gallery_ids  = $product->get_gallery_image_ids();
			$gallery_meta = get_post_meta( $pid, '_product_image_gallery', true );

			// If gallery images still not set, try alternative method
			if ( empty( $gallery_ids ) && ! empty( $gallery_meta ) ) {
				$gallery_array = array_filter( explode( ',', $gallery_meta ) );
				if ( ! empty( $gallery_array ) ) {
					$product->set_gallery_image_ids( $gallery_array );
					$product->save();
				}
			}

			// Clear WooCommerce caches to ensure gallery images are visible
			wc_delete_product_transients( $pid );
			wp_cache_delete( $pid, 'posts' );
			wp_cache_delete( $pid, 'post_meta' );

			// Handle variations if it's a variable product
			if ( $product->is_type( 'variable' ) && ! empty( $api_product['variations'] ) ) {
				$this->create_or_update_variations( $product, $api_product );
			}

			// Handle product tags
			$product_tags = array();

			// Add existing tags if present
			if ( ! empty( $api_product['tags'] ) && is_array( $api_product['tags'] ) ) {
				$product_tags = array_merge( $product_tags, array_map( 'wc_clean', $api_product['tags'] ) );
			}

			// Handle Product_Keywords field from API response
			if ( ! empty( $api_product['Product_Keywords'] ) && is_array( $api_product['Product_Keywords'] ) ) {
				// Process keywords: trim whitespace, sanitize, convert to lowercase, and filter out empty values
				$keywords = array_map(
					function ( $keyword ) {
						return strtolower( wc_clean( trim( $keyword ) ) );
					},
					$api_product['Product_Keywords']
				);

				// Remove empty values and merge with existing tags
				$keywords     = array_filter( $keywords );
				$product_tags = array_merge( $product_tags, $keywords );
			}

			// Set all tags at once (WordPress will handle tag creation and duplicates automatically)
			if ( ! empty( $product_tags ) ) {
				// Remove duplicates and reset array keys
				$product_tags = array_unique( $product_tags );
				wp_set_object_terms( $pid, $product_tags, 'product_tag' );
			}

			// Handle custom meta data
			if ( ! empty( $api_product['meta_data'] ) ) {
				foreach ( $api_product['meta_data'] as $key => $value ) {
					$product->update_meta_data( wc_clean( $key ), maybe_serialize( $value ) );
				}
				$product->save();
			}

			// Add Vortem meta
			$product->update_meta_data( '_vortem_imported', '1' );
			// Store the actual product_id from API, not SKU
			$vortem_product_id = $api_product['product_id'] ?? $api_product['id'] ?? $sku;
			$product->update_meta_data( '_vortem_product_id', $vortem_product_id );
			$product->update_meta_data( '_vortem_import_date', current_time( 'mysql' ) );
			$product->save();

			// Ensure product is saved as draft
			wp_update_post(
				array(
					'ID'          => $pid,
					'post_status' => 'draft',
				)
			);

			// Reload and enforce draft status via WooCommerce API
			$enforce = wc_get_product( $pid );
			if ( $enforce ) {
				$enforce->set_status( 'draft' );
				$enforce->save();
			}

			// Handle product video from API response
			$this->handle_product_video( $pid, $api_product );

			// Release lock on successful creation (unless skip_transient_check is set)
			if ( ! $skip_lock_check ) {
				delete_transient( $lock_key );
			}

			return array(
				'success'    => true,
				'product_id' => $pid,
				'action'     => 'created',
				'message'    => 'Product created successfully',
			);

		} catch ( Exception $e ) {
			// Release lock on error (unless skip_transient_check is set)
			if ( isset( $lock_key ) && ! $skip_lock_check ) {
				delete_transient( $lock_key );
			}
			return array(
				'success' => false,
				'message' => 'Failed to create product: ' . esc_html( $e->getMessage() ),
			);
		}
	}

	/**
	 * Set product data from API response
	 *
	 * @param WC_Product $product WooCommerce product object
	 * @param array      $api_product API product data
	 * @param bool       $set_sku Whether to set SKU (for new products)
	 */
	private function set_product_data( $product, $api_product, $set_sku = true ) {
		$product->set_name( wc_clean( $api_product['title'] ?? 'Untitled Product' ) );

		if ( isset( $api_product['description'] ) ) {
			$product->set_description( wp_kses_post( $api_product['description'] ) );
		}

		if ( isset( $api_product['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $api_product['short_description'] ) );
		}

		if ( $set_sku ) {
			$product->set_sku( wc_clean( $api_product['sku'] ?? $api_product['product_id'] ) );
		}

		// Handle pricing
		if ( isset( $api_product['price']['original'] ) ) {
			$product->set_regular_price( wc_clean( $api_product['price']['original'] ) );
		}

		if ( isset( $api_product['price']['sale'] ) && ! empty( $api_product['price']['sale'] ) ) {
			$product->set_sale_price( wc_clean( $api_product['price']['sale'] ) );
		}

		$product->set_status( 'draft' );

		// Handle weight
		if ( isset( $api_product['weight'] ) ) {
			$product->set_weight( wc_clean( $api_product['weight'] ) );
		}

		// Handle dimensions
		if ( ! empty( $api_product['dimensions'] ) ) {
			$dims = $api_product['dimensions'];
			$product->set_length( wc_clean( $dims['length'] ?? '10' ) );
			$product->set_width( wc_clean( $dims['width'] ?? '10' ) );
			$product->set_height( wc_clean( $dims['height'] ?? '5' ) );
		}

		// Handle stock management
		if ( ! empty( $api_product['variations'] ) ) {
			// For variable products, stock is managed at variation level
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
		} else {
			// For simple products, use first variation stock or default
			$stock_quantity = 0;
			if ( ! empty( $api_product['variations'][0]['stock'] ) ) {
				$stock_quantity = intval( $api_product['variations'][0]['stock'] );
			}

			if ( $stock_quantity > 0 ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $stock_quantity );
				$product->set_stock_status( 'instock' );
			} else {
				$product->set_manage_stock( false );
				$product->set_stock_status( 'outofstock' );
			}
		}

		// Handle virtual/downloadable products
		if ( isset( $api_product['virtual'] ) ) {
			$product->set_virtual( wc_string_to_bool( $api_product['virtual'] ) );
		}
		if ( isset( $api_product['downloadable'] ) ) {
			$product->set_downloadable( wc_string_to_bool( $api_product['downloadable'] ) );
		}

		// Handle featured status
		if ( isset( $api_product['featured'] ) ) {
			$product->set_featured( wc_string_to_bool( $api_product['featured'] ) );
		}

		// Handle catalog visibility
		if ( isset( $api_product['catalog_visibility'] ) ) {
			$visibility = wc_clean( $api_product['catalog_visibility'] );
			if ( in_array( $visibility, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
				$product->set_catalog_visibility( $visibility );
			}
		} else {
			$product->set_catalog_visibility( 'visible' );
		}
	}

	/**
	 * Handle product attributes
	 *
	 * @param WC_Product $product WooCommerce product object
	 * @param array      $api_product API product data
	 */
	private function handle_attributes( $product, $api_product ) {
		$attrs = array();

		// First, check if we have variations to extract attributes from
		if ( ! empty( $api_product['variations'] ) && is_array( $api_product['variations'] ) ) {
			$variation_attributes = $this->extract_variation_attributes( $api_product['variations'] );

			if ( ! empty( $variation_attributes ) ) {
				$position = 0;
				foreach ( $variation_attributes as $attr_name => $attr_values ) {
					$wc_attr = new WC_Product_Attribute();
					$wc_attr->set_name( wc_clean( $attr_name ) );
					$wc_attr->set_options( array_map( 'wc_clean', $attr_values ) );
					$wc_attr->set_position( $position++ );
					$wc_attr->set_visible( true ); // Visible on product page
					$wc_attr->set_variation( true ); // Used for variations
					$attrs[] = $wc_attr;
				}
			}
		}

		// Also handle standard attributes if provided
		if ( ! empty( $api_product['attributes'] ) && is_array( $api_product['attributes'] ) ) {
			$position = count( $attrs );
			foreach ( $api_product['attributes'] as $attr ) {
				// Skip if this attribute is already added from variations
				$attr_name      = wc_clean( $attr['name'] );
				$already_exists = false;
				foreach ( $attrs as $existing_attr ) {
					if ( strtolower( $existing_attr->get_name() ) === strtolower( $attr_name ) ) {
						$already_exists = true;
						break;
					}
				}

				if ( $already_exists ) {
					continue;
				}

				$wc_attr = new WC_Product_Attribute();
				$wc_attr->set_name( $attr_name );

				// Handle both single values and arrays of options
				if ( is_array( $attr['value'] ) ) {
					$wc_attr->set_options( array_map( 'wc_clean', $attr['value'] ) );
				} else {
					$wc_attr->set_options( array_map( 'wc_clean', array( $attr['value'] ) ) );
				}

				$wc_attr->set_position( intval( $attr['position'] ?? $position++ ) );
				$wc_attr->set_visible( isset( $attr['visible'] ) ? wc_string_to_bool( $attr['visible'] ) : true );
				$wc_attr->set_variation( isset( $attr['variation'] ) ? wc_string_to_bool( $attr['variation'] ) : false );
				$attrs[] = $wc_attr;
			}
		}

		if ( ! empty( $attrs ) ) {
			$product->set_attributes( $attrs );
		}
	}

	/**
	 * Extract variation attributes from variation data
	 *
	 * @param array $variations Array of variations
	 * @return array Associative array of attribute names to unique values
	 */
	private function extract_variation_attributes( $variations ) {
		$attributes = array();

		foreach ( $variations as $variation ) {
			if ( empty( $variation['attributes'] ) || ! is_array( $variation['attributes'] ) ) {
				continue;
			}

			foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
				if ( empty( $attr_name ) || empty( $attr_value ) ) {
					continue;
				}

				// Initialize attribute array if not exists
				if ( ! isset( $attributes[ $attr_name ] ) ) {
					$attributes[ $attr_name ] = array();
				}

				// Add value if not already present
				if ( ! in_array( $attr_value, $attributes[ $attr_name ], true ) ) {
					$attributes[ $attr_name ][] = $attr_value;
				}
			}
		}
		return $attributes;
	}

	/**
	 * Get or create category IDs
	 *
	 * @param array|object $categories Category data (can be array of names or new format object)
	 * @return array Category IDs
	 */
	private function get_or_create_category_ids( $categories ) {
		$ids = array();

		// Handle new format: object with vortem_cat_m (main) and vortem_cat_l1 (subcategory)
		if ( ( is_array( $categories ) && isset( $categories['vortem_cat_m'] ) ) || ( is_object( $categories ) && isset( $categories->vortem_cat_m ) ) ) {
			$categories_array = (array) $categories;

			// Process main category (vortem_cat_m)
			if ( ! empty( $categories_array['vortem_cat_m'] ) ) {
				$main_cat      = is_array( $categories_array['vortem_cat_m'] ) ? $categories_array['vortem_cat_m'] : (array) $categories_array['vortem_cat_m'];
				$main_cat_name = isset( $main_cat['name'] ) ? $main_cat['name'] : '';

				if ( ! empty( $main_cat_name ) ) {
					$main_term = term_exists( $main_cat_name, 'product_cat' );
					if ( $main_term ) {
						$main_cat_id = (int) $main_term['term_id'];
					} else {
						$inserted = wp_insert_term( $main_cat_name, 'product_cat' );
						if ( ! is_wp_error( $inserted ) ) {
							$main_cat_id = (int) $inserted['term_id'];
						} else {
							$main_cat_id = null;
						}
					}

					if ( $main_cat_id ) {
						$ids[] = $main_cat_id;

						// Process subcategory (vortem_cat_l1) as child of main category
						if ( ! empty( $categories_array['vortem_cat_l1'] ) ) {
							$sub_cat      = is_array( $categories_array['vortem_cat_l1'] ) ? $categories_array['vortem_cat_l1'] : (array) $categories_array['vortem_cat_l1'];
							$sub_cat_name = isset( $sub_cat['name'] ) ? $sub_cat['name'] : '';

							if ( ! empty( $sub_cat_name ) ) {
								// Check if subcategory exists as child of main category
								$sub_term = term_exists( $sub_cat_name, 'product_cat', $main_cat_id );
								if ( $sub_term ) {
									$ids[] = (int) $sub_term['term_id'];
								} else {
									// Create subcategory as child of main category
									$inserted = wp_insert_term( $sub_cat_name, 'product_cat', array( 'parent' => $main_cat_id ) );
									if ( ! is_wp_error( $inserted ) ) {
										$ids[] = (int) $inserted['term_id'];
									}
								}
							}
						}
					}
				}
			}
		} else {
			// Handle old format: array of category name strings (backward compatibility)
			foreach ( $categories as $category_name ) {
				if ( empty( $category_name ) ) {
					continue;
				}

				$term = term_exists( $category_name, 'product_cat' );
				if ( $term ) {
					$ids[] = (int) $term['term_id'];
				} else {
					$inserted = wp_insert_term( $category_name, 'product_cat' );
					if ( ! is_wp_error( $inserted ) ) {
						$ids[] = (int) $inserted['term_id'];
					}
				}
			}
		}

		return array_unique( $ids );
	}

	/**
	 * Set product images from API data
	 *
	 * @param WC_Product $product WooCommerce product object
	 * @param array $api_product API product data
	 */
	/**
	 * Set product images from API data
	 *
	 * @param WC_Product $product WooCommerce product object
	 * @param array      $api_product API product data
	 */
	private function set_product_images( $product, $api_product ) {
		$product_name = $api_product['title'] ?? '';
		$product_id   = $product->get_id();

		// Handle main product image (itemMainPic) - now supports both file uploads and attachment IDs
		if ( ! empty( $api_product['image_paths']['itemMainPic'] ) ) {
			$image_id = $this->get_image_id( $api_product['image_paths']['itemMainPic'], array(), 'itemMainPic', $product_name );
			if ( $image_id ) {
				$product->set_image_id( $image_id );
			}
		} elseif ( ! empty( $api_product['images']['main'] ) ) {
			// Fallback to old format
			$sku           = $api_product['sku'] ?? '';
			$attachment_id = $this->download_and_attach_image( $api_product['images']['main'], $product_id, $product_name, $sku );
			if ( $attachment_id ) {
				$product->set_image_id( $attachment_id );
			}
		}

		// Handle gallery images with improved method
		$gallery_ids = $this->process_gallery_images( $api_product, $product_id, $product_name );

		// Set gallery images using multiple methods for maximum compatibility
		if ( ! empty( $gallery_ids ) ) {

			// Method 1: WooCommerce 3.0+ method
			$product->set_gallery_image_ids( $gallery_ids );

			// Method 2: Direct post meta method (for compatibility)
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );

			// Method 3: Force update using wp_update_post
			wp_update_post(
				array(
					'ID'         => $product_id,
					'meta_input' => array(
						'_product_image_gallery' => implode( ',', $gallery_ids ),
					),
				)
			);
		}

		// Handle variation images
		$variation_images = array();
		if ( ! empty( $api_product['image_paths'] ) ) {
			foreach ( $api_product['image_paths'] as $key => $path ) {
				if ( strpos( $key, 'var_' ) === 0 ) {
					$var_name = $product_name . '-' . $key;
					$image_id = $this->get_image_id( $path, array(), $key, $var_name );
					if ( $image_id ) {
						$variation_images[ $key ] = $image_id;
					}
				}
			}
		}

		// Store variation images for later use in variation creation
		if ( ! empty( $variation_images ) ) {
			// Persist as plain meta for reliable retrieval
			update_post_meta( $product_id, '_vortem_variation_images', $variation_images );
		}
	}

	/**
	 * Process gallery images from API data
	 *
	 * @param array  $api_product API product data
	 * @param int    $product_id Product ID
	 * @param string $product_name Product name
	 * @return array Array of gallery image IDs
	 */
	private function process_gallery_images( $api_product, $product_id, $product_name ) {
		$gallery_ids         = array();
		$processed_urls      = array(); // Track normalized URLs to prevent duplicates
		$processed_filenames = array(); // Track filenames to catch same image from different CDN endpoints

		// Get main image URL for exclusion from gallery
		$main_image_url = '';
		if ( ! empty( $api_product['image_paths']['itemMainPic'] ) ) {
			$main_image_url = $api_product['image_paths']['itemMainPic'];
		} elseif ( ! empty( $api_product['images']['main'] ) ) {
			$main_image_url = $api_product['images']['main'];
		}

		// Normalize main image URL for comparison
		$normalized_main_url = '';
		$main_image_filename = '';
		if ( ! empty( $main_image_url ) ) {
			$normalized_main_url = $this->normalize_image_url( $main_image_url );
			$main_image_filename = basename( wp_parse_url( $main_image_url, PHP_URL_PATH ) );
		}

		// Method 1: Check for new format gallery images (image_paths)
		if ( ! empty( $api_product['image_paths'] ) ) {
			for ( $i = 1; $i <= 10; $i++ ) { // Check up to 10 gallery images
				$gallery_key = "gallery_image_$i";
				if ( ! empty( $api_product['image_paths'][ $gallery_key ] ) ) {
					$image_path = $api_product['image_paths'][ $gallery_key ];

					// Normalize URL for duplicate checking
					$normalized_url = '';
					$image_filename = '';

					// Check if image_path is a URL
					if ( is_string( $image_path ) && filter_var( $image_path, FILTER_VALIDATE_URL ) ) {
						$normalized_url = $this->normalize_image_url( $image_path );
						$image_filename = basename( wp_parse_url( $image_path, PHP_URL_PATH ) );

						// Skip if this is the main image (by URL or filename)
						if ( ! empty( $normalized_main_url ) && $normalized_url === $normalized_main_url ) {
							continue;
						}
						if ( ! empty( $main_image_filename ) && ! empty( $image_filename ) && $image_filename === $main_image_filename ) {
							continue;
						}

						// Skip if already processed (by URL or filename)
						if ( isset( $processed_urls[ $normalized_url ] ) ) {
							continue;
						}
						if ( ! empty( $image_filename ) && isset( $processed_filenames[ $image_filename ] ) ) {
							// Reuse existing attachment ID
							$gallery_ids[] = $processed_filenames[ $image_filename ];
							continue;
						}
					}
					// Check if image_path is an attachment ID (from SEO import with base64 images)
					elseif ( is_numeric( $image_path ) ) {
						$attachment_id = intval( $image_path );
						// Verify attachment exists
						if ( get_post( $attachment_id ) && get_post_type( $attachment_id ) === 'attachment' ) {
							// Get attachment URL and filename for tracking
							$attachment_url = wp_get_attachment_url( $attachment_id );
							if ( $attachment_url ) {
								$normalized_url = $this->normalize_image_url( $attachment_url );
								$image_filename = basename( wp_parse_url( $attachment_url, PHP_URL_PATH ) );

								// Skip if this is the main image (by URL or filename)
								if ( ! empty( $normalized_main_url ) && $normalized_url === $normalized_main_url ) {
									continue;
								}
								if ( ! empty( $main_image_filename ) && ! empty( $image_filename ) && $image_filename === $main_image_filename ) {
									continue;
								}

								// Skip if already processed
								if ( isset( $processed_urls[ $normalized_url ] ) ) {
									continue;
								}
								if ( ! empty( $image_filename ) && isset( $processed_filenames[ $image_filename ] ) ) {
									continue;
								}

								// Add to gallery and track it
								$gallery_ids[]                     = $attachment_id;
								$processed_urls[ $normalized_url ] = $attachment_id;
								if ( ! empty( $image_filename ) ) {
									$processed_filenames[ $image_filename ] = $attachment_id;
								}
								continue; // Skip the get_image_id call below
							}
						}
					}

					$gallery_name = $product_name . '-gallery-' . $i;

					$image_id = $this->get_image_id( $image_path, array(), $gallery_key, $gallery_name );
					if ( $image_id ) {
						$gallery_ids[] = $image_id;
						// Track this URL and filename as processed
						if ( ! empty( $normalized_url ) ) {
							$processed_urls[ $normalized_url ] = $image_id;
						}
						if ( ! empty( $image_filename ) ) {
							$processed_filenames[ $image_filename ] = $image_id;
						}
					}
				}
			}
		}

		// Method 2: Process images.gallery array (always process if it exists, not just as fallback)
		if ( isset( $api_product['images']['gallery'] ) && is_array( $api_product['images']['gallery'] ) ) {
			foreach ( $api_product['images']['gallery'] as $index => $image_url ) {
				if ( ! empty( $image_url ) && is_string( $image_url ) ) {
					// Normalize URL for duplicate checking
					$normalized_url = $this->normalize_image_url( $image_url );
					$image_filename = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );

					// Skip if this is the main image (by URL or filename)
					if ( ! empty( $normalized_main_url ) && $normalized_url === $normalized_main_url ) {
						continue;
					}
					if ( ! empty( $main_image_filename ) && ! empty( $image_filename ) && $image_filename === $main_image_filename ) {
						continue;
					}

					// Skip if already processed (by URL or filename)
					if ( isset( $processed_urls[ $normalized_url ] ) ) {
						// Reuse existing attachment ID
						$gallery_ids[] = $processed_urls[ $normalized_url ];
						continue;
					}
					if ( ! empty( $image_filename ) && isset( $processed_filenames[ $image_filename ] ) ) {
						// Reuse existing attachment ID
						$gallery_ids[] = $processed_filenames[ $image_filename ];
						continue;
					}

					$gallery_name = $product_name . '-gallery-' . ( $index + 1 );

					$sku           = $api_product['sku'] ?? '';
					$attachment_id = $this->download_and_attach_image( $image_url, $product_id, $gallery_name, $sku );
					if ( $attachment_id ) {
						$gallery_ids[] = $attachment_id;
						// Track this URL and filename as processed
						$processed_urls[ $normalized_url ] = $attachment_id;
						if ( ! empty( $image_filename ) ) {
							$processed_filenames[ $image_filename ] = $attachment_id;
						}
					}
				}
			}
		}

		// Method 3: Check for any other gallery-related keys in image_paths
		if ( ! empty( $api_product['image_paths'] ) ) {
			foreach ( $api_product['image_paths'] as $key => $path ) {
				if ( strpos( $key, 'gallery' ) !== false && strpos( $key, 'var_' ) === false ) {
					// Normalize URL for duplicate checking
					$normalized_url = '';
					$image_filename = '';

					// Check if path is a URL
					if ( is_string( $path ) && filter_var( $path, FILTER_VALIDATE_URL ) ) {
						$normalized_url = $this->normalize_image_url( $path );
						$image_filename = basename( wp_parse_url( $path, PHP_URL_PATH ) );

						// Skip if this is the main image (by URL or filename)
						if ( ! empty( $normalized_main_url ) && $normalized_url === $normalized_main_url ) {
							continue;
						}
						if ( ! empty( $main_image_filename ) && ! empty( $image_filename ) && $image_filename === $main_image_filename ) {
							continue;
						}

						// Skip if already processed (by URL or filename)
						if ( isset( $processed_urls[ $normalized_url ] ) ) {
							// Reuse existing attachment ID
							$gallery_ids[] = $processed_urls[ $normalized_url ];
							continue;
						}
						if ( ! empty( $image_filename ) && isset( $processed_filenames[ $image_filename ] ) ) {
							// Reuse existing attachment ID
							$gallery_ids[] = $processed_filenames[ $image_filename ];
							continue;
						}
					}
					// Check if path is an attachment ID (from SEO import with base64 images)
					elseif ( is_numeric( $path ) ) {
						$attachment_id = intval( $path );
						// Verify attachment exists
						if ( get_post( $attachment_id ) && get_post_type( $attachment_id ) === 'attachment' ) {
							// Get attachment URL and filename for tracking
							$attachment_url = wp_get_attachment_url( $attachment_id );
							if ( $attachment_url ) {
								$normalized_url = $this->normalize_image_url( $attachment_url );
								$image_filename = basename( wp_parse_url( $attachment_url, PHP_URL_PATH ) );

								// Skip if this is the main image (by URL or filename)
								if ( ! empty( $normalized_main_url ) && $normalized_url === $normalized_main_url ) {
									continue;
								}
								if ( ! empty( $main_image_filename ) && ! empty( $image_filename ) && $image_filename === $main_image_filename ) {
									continue;
								}

								// Skip if already processed
								if ( isset( $processed_urls[ $normalized_url ] ) ) {
									continue;
								}
								if ( ! empty( $image_filename ) && isset( $processed_filenames[ $image_filename ] ) ) {
									continue;
								}

								// Add to gallery and track it
								$gallery_ids[]                     = $attachment_id;
								$processed_urls[ $normalized_url ] = $attachment_id;
								if ( ! empty( $image_filename ) ) {
									$processed_filenames[ $image_filename ] = $attachment_id;
								}
								continue; // Skip the get_image_id call below
							}
						}
					}

					$gallery_name = $product_name . '-' . $key;

					$image_id = $this->get_image_id( $path, array(), $key, $gallery_name );
					if ( $image_id ) {
						$gallery_ids[] = $image_id;
						// Track this URL and filename as processed
						if ( ! empty( $normalized_url ) ) {
							$processed_urls[ $normalized_url ] = $image_id;
						}
						if ( ! empty( $image_filename ) ) {
							$processed_filenames[ $image_filename ] = $image_id;
						}
					}
				}
			}
		}

		// Remove duplicates from gallery_ids array (by attachment ID)
		$gallery_ids = array_unique( $gallery_ids );
		$gallery_ids = array_values( $gallery_ids ); // Re-index array
		return $gallery_ids;
	}

	/**
	 * Get image ID from various sources (attachment ID, file path, URL)
	 *
	 * @param mixed  $image_data Image data (ID, path, or URL)
	 * @param array  $files File uploads array
	 * @param string $key Image key for identification
	 * @param string $product_name Product name for better naming
	 * @return int|false Attachment ID or false
	 */
	private function get_image_id( $image_data, $files, $key, $product_name = '' ) {
		// If image_data is already an attachment ID (integer), return it
		if ( is_numeric( $image_data ) ) {
			$attachment_id = intval( $image_data );
			// Verify the attachment exists
			if ( get_post( $attachment_id ) && get_post_type( $attachment_id ) === 'attachment' ) {
				return $attachment_id;
			}
		}

		// If image_data is a file path and we have file uploads, handle as file upload
		if ( is_string( $image_data ) && isset( $files[ $key ] ) ) {
			$img_data = array( 'file_key' => $key );
			return $this->handle_image_upload( $img_data, $files, $product_name );
		}

		// If image_data is a URL, handle as URL upload
		if ( is_string( $image_data ) && filter_var( $image_data, FILTER_VALIDATE_URL ) ) {
			$img_data = array( 'src' => $image_data );
			return $this->handle_image_upload( $img_data, $files, $product_name );
		}

		return false;
	}

	/**
	 * Handle image upload from various sources
	 *
	 * @param array  $img Image data structure
	 * @param array  $files File uploads array
	 * @param string $product_name Product name for better naming
	 * @return int|false Attachment ID or false
	 */
	private function handle_image_upload( $img, $files, $product_name = '' ) {
		if ( ! is_array( $img ) ) {
			return false;
		}

		// Add product name to meta for better naming
		if ( ! empty( $product_name ) ) {
			$img['product_name'] = $product_name;
		}

		if ( ! empty( $img['src'] ) ) {
			return $this->upload_image_from_url( $img['src'], $img );
		}

		if ( ! empty( $img['file_key'] ) && isset( $files[ $img['file_key'] ] ) ) {
			return $this->upload_image_from_file( $files[ $img['file_key'] ], $img );
		}
		return false;
	}

	/**
	 * Upload image from file upload
	 *
	 * @param array $file File upload data
	 * @param array $meta Image metadata
	 * @return int|false Attachment ID or false
	 */
	private function upload_image_from_file( $file, $meta ) {
		if ( ! is_array( $file ) || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return false;
		}

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'wp_handle_upload' ) || ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return false;
		}

		$up = wp_handle_upload( $file, array( 'test_form' => false ) );
		if ( ! empty( $up['error'] ) ) {
			return false;
		}

		$title = ! empty( $meta['title'] ) ? wc_clean( $meta['title'] ) : sanitize_file_name( pathinfo( $up['file'], PATHINFO_FILENAME ) );
		$att   = array(
			'post_mime_type' => $up['type'],
			'post_title'     => $title,
			'post_content'   => ( $meta['description'] ?? '' ),
			'post_excerpt'   => ( $meta['caption'] ?? '' ),
			'post_status'    => 'inherit',
		);

		$id = wp_insert_attachment( $att, $up['file'] );
		if ( is_wp_error( $id ) ) {
			return false;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
		if ( ! empty( $meta['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wc_clean( $meta['alt'] ) );
		}
		return $id;
	}

	/**
	 * Upload image from URL with retry mechanism
	 *
	 * @param string $url Image URL
	 * @param array  $meta Image metadata
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return int|false Attachment ID or false
	 */
	private function upload_image_from_url( $url, $meta, $retry_count = 0 ) {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Add delay between downloads (except for first image)
		if ( $retry_count === 0 ) {
			usleep( 800000 ); // 0.8 seconds delay
		}

		$max_retries = 3;

		// Download with increased timeout
		$tmp = download_url( $url, 60 ); // 60 seconds timeout
		if ( is_wp_error( $tmp ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->upload_image_from_url( $url, $meta, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to download URL after ' . ( $retry_count + 1 ) . ' attempts: ' . $url );
			}
			return false;
		}

		// Use product name from meta if available, otherwise use filename
		$title = '';
		if ( ! empty( $meta['title'] ) ) {
			$title = wc_clean( $meta['title'] );
		} elseif ( ! empty( $meta['product_name'] ) ) {
			$title = sanitize_file_name( $meta['product_name'] );
		} else {
			$title = sanitize_file_name( pathinfo( $url, PATHINFO_FILENAME ) );
		}

		$file_array = array(
			'name'     => basename( $url ),
			'tmp_name' => $tmp,
		);
		$id         = media_handle_sideload( $file_array, 0, ( $meta['description'] ?? '' ) );
		if ( is_wp_error( $id ) ) {
			if ( ! empty( $file_array['tmp_name'] ) && file_exists( $file_array['tmp_name'] ) ) {
				wp_delete_file( $file_array['tmp_name'] );
			}
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->upload_image_from_url( $url, $meta, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to sideload image after ' . ( $retry_count + 1 ) . ' attempts: ' . $url );
			}
			return false;
		}
		if ( ! empty( $meta['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wc_clean( $meta['alt'] ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			vortem_log( 'Vortem AI: Successfully uploaded image from URL: ' . $url );
		}

		return $id;
	}

	/**
	 * Download and attach image to product with retry mechanism
	 *
	 * @param string $image_url Image URL
	 * @param int    $product_id Product ID
	 * @param string $product_name Product name for filename
	 * @param string $sku Product SKU for filename
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_and_attach_image( $image_url, $product_id, $product_name = '', $sku = '', $retry_count = 0 ) {
		// Phone-home gate: do not fetch remote images until consent is granted.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return false;
		}

		// Check if image already exists
		$existing_attachment = $this->get_attachment_by_url( $image_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		// Add delay between downloads to prevent server throttling (except for first image)
		if ( $retry_count === 0 ) {
			// Add 800ms delay to prevent overwhelming the server
			usleep( 800000 ); // 0.8 seconds
		}

		// Retry mechanism: try up to 3 times
		$max_retries = 3;

		// Download image with longer timeout for external sources
		$image_data = wp_remote_get(
			$image_url,
			array(
				'timeout'     => 60, // Increased to 60 seconds
				'sslverify'   => true,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
				'httpversion' => '1.1',
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $image_data ) ) {
			// If this is not the last retry, try again
			if ( $retry_count < $max_retries ) {
				// Wait progressively longer between retries (1s, 2s, 3s)
				sleep( $retry_count + 1 );
				return $this->download_and_attach_image( $image_url, $product_id, $product_name, $sku, $retry_count + 1 );
			}

			// Fallback to cURL if wp_remote_get fails after all retries
			$image_content = $this->download_image_with_curl( $image_url );
			if ( empty( $image_content ) ) {
				return false;
			}
		} else {
			$response_code = wp_remote_retrieve_response_code( $image_data );
			if ( $response_code !== 200 ) {
				// If this is not the last retry, try again
				if ( $retry_count < $max_retries ) {
					sleep( $retry_count + 1 );
					return $this->download_and_attach_image( $image_url, $product_id, $product_name, $sku, $retry_count + 1 );
				}

				// Fallback to cURL if HTTP status is not 200 after all retries
				$image_content = $this->download_image_with_curl( $image_url );
				if ( empty( $image_content ) ) {
					return false;
				}
			} else {
				$image_content = wp_remote_retrieve_body( $image_data );
				if ( empty( $image_content ) ) {
					// If this is not the last retry, try again
					if ( $retry_count < $max_retries ) {
						sleep( $retry_count + 1 );
						return $this->download_and_attach_image( $image_url, $product_id, $product_name, $sku, $retry_count + 1 );
					}

					// Fallback to cURL if content is empty after all retries
					$image_content = $this->download_image_with_curl( $image_url );
					if ( empty( $image_content ) ) {
						return false;
					}
				}
			}
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
		// verified earlier to be inside the uploads directory.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a downloaded image attachment to the uploads dir.
		if ( file_put_contents( $file_path, $image_content ) === false ) {
			return false;
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => wp_check_filetype( $filename )['type'],
			'post_title'     => ! empty( $product_name ) ? sanitize_file_name( $product_name ) : sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return false;
		}

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $product_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $file_path );
			return false;
		}

		// Generate attachment metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );
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
	 * Download image using cURL as fallback with retry mechanism
	 *
	 * @param string $image_url Image URL
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return string|false Image content or false on failure
	 */
	private function download_image_with_curl( $image_url, $retry_count = 0 ) {
		// Phone-home gate.
		if ( ! Vortem_Api_Client::has_consent() ) {
			return false;
		}

		$max_retries = 3;

		$args = array(
			'timeout'     => 60, // Increased to 60 seconds
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
			'httpversion' => '1.1',
		);

		$response = wp_remote_get( $image_url, $args );

		if ( is_wp_error( $response ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->download_image_with_curl( $image_url, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to download image after ' . ( $retry_count + 1 ) . ' attempts: ' . $image_url );
			}
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->download_image_with_curl( $image_url, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: HTTP error ' . $http_code . ' for image: ' . $image_url );
			}
			return false;
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->download_image_with_curl( $image_url, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Empty content for image: ' . $image_url );
			}
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			vortem_log( 'Vortem AI: Successfully downloaded image: ' . $image_url );
		}
		return $content;
	}

	/**
	 * Create or update variations by matching attributes, including those in trash.
	 *
	 * This method handles two scenarios:
	 * 1. Explicit variations from API (with SKU, attributes, price, sale_price, stock)
	 * 2. Auto-generated variations from product attributes
	 *
	 * @param WC_Product_Variable &$product
	 * @param array               $api_product
	 */
	private function create_or_update_variations( &$product, $api_product ) {

		// If explicit variations are provided, use them
		if ( isset( $api_product['variations'] ) && is_array( $api_product['variations'] ) ) {
			$this->create_explicit_variations( $product, $api_product );
		} else {
			// If no explicit variations but we have variation attributes, generate them automatically
			// Check if auto-generation is enabled (default: true)
			$auto_generate = isset( $api_product['auto_generate_variations'] ) ? wc_string_to_bool( $api_product['auto_generate_variations'] ) : true;
			if ( $auto_generate ) {
				$this->generate_variations_from_attributes( $product, $api_product );
			}
		}
	}

	/**
	 * Create variations from explicit variation data
	 *
	 * @param WC_Product_Variable &$product
	 * @param array               $api_product
	 */
	private function create_explicit_variations( &$product, $api_product ) {
		$parent_id  = $product->get_id();
		$data_store = WC_Data_Store::load( 'product-variation' );

		// Get ALL children IDs, including those in trash, to build a map.
		$all_children_query                  = new WP_Query(
			array(
				'post_parent'    => $parent_id,
				'post_type'      => 'product_variation',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$existing_variation_ids_all_statuses = $all_children_query->posts;
		$processed_variation_ids             = array();

		foreach ( $api_product['variations'] as $index => $v_data ) {

			// Validate variation data
			if ( ! $this->validate_variation_data( $v_data ) ) {
				continue;
			}

			$matching_attributes = array();
			foreach ( $v_data['attributes'] as $name => $value ) {
				$sanitized_name                         = sanitize_title( $name );
				$matching_attributes[ $sanitized_name ] = $value;
			}

			// Find the variation using the data store, which is reliable for attribute matching.
			$variation_id = $data_store->find_matching_product_variation( $product, $matching_attributes );

			$variation = $variation_id ? wc_get_product( $variation_id ) : new WC_Product_Variation();
			if ( ! $variation ) {
				continue;
			}

			if ( $variation_id && get_post_status( $variation_id ) === 'trash' ) {
				wp_untrash_post( $variation_id );
				wc_delete_product_transients( $variation_id );
			}

			$variation->set_parent_id( $parent_id );
			$variation->set_attributes( $matching_attributes );

			// Only set SKU for new variations to avoid WooCommerce validation error
			if ( isset( $v_data['sku'] ) && ! $variation_id ) {
				$variation->set_sku( wc_clean( $v_data['sku'] ) );
			}

			// Handle pricing - support both 'price' and 'regular_price' keys
			if ( isset( $v_data['regular_price'] ) && ! empty( $v_data['regular_price'] ) ) {
				$variation->set_regular_price( wc_clean( $v_data['regular_price'] ) );
			} elseif ( isset( $v_data['price'] ) && ! empty( $v_data['price'] ) ) {
				$variation->set_regular_price( wc_clean( $v_data['price'] ) );
			}

			// Handle sale price - only set if not empty
			if ( isset( $v_data['sale_price'] ) && ! empty( $v_data['sale_price'] ) ) {
				$variation->set_sale_price( wc_clean( $v_data['sale_price'] ) );
			}

			// Handle stock management
			if ( isset( $v_data['stock'] ) ) {
				$stock_qty = intval( $v_data['stock'] );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $stock_qty );
				$variation->set_stock_status( $stock_qty > 0 ? 'instock' : 'outofstock' );
			} elseif ( ! $variation_id ) {
					$variation->set_manage_stock( false );
					$variation->set_stock_status( 'instock' );
			}

			// Handle variation image - prioritize API response data over stored images
			$variation_image_set = false;

			// Priority 1: Direct URL string in v_data['image'] (most common in API responses)
			if ( ! empty( $v_data['image'] ) && is_string( $v_data['image'] ) && filter_var( $v_data['image'], FILTER_VALIDATE_URL ) ) {
				$variation_name = $product->get_name() . '-variation-' . implode( '-', array_values( $matching_attributes ) );
				$attachment_id  = $this->download_and_attach_image( $v_data['image'], $parent_id, $variation_name, ( $v_data['sku'] ?? $product->get_sku() ) );
				if ( $attachment_id ) {
					$variation->set_image_id( intval( $attachment_id ) );
					$variation_image_set = true;
				}
			}

			// Priority 2: If variation image refers to a file_key that exists in image_paths
			if ( ! $variation_image_set && ! empty( $v_data['image'] ) && is_array( $v_data['image'] ) && ! empty( $v_data['image']['file_key'] ) ) {
				$file_key = $v_data['image']['file_key'];
				if ( ! empty( $api_product['image_paths'] ) && ! empty( $api_product['image_paths'][ $file_key ] ) ) {
					if ( is_numeric( $api_product['image_paths'][ $file_key ] ) ) {
						$variation->set_image_id( intval( $api_product['image_paths'][ $file_key ] ) );
						$variation_image_set = true;
					} else {
						// Try to upload based on provided image structure (src/file)
						$variation_name = $product->get_name() . '-variation-' . implode( '-', array_values( $matching_attributes ) );
						$img_id         = $this->handle_image_upload( $v_data['image'], array(), $variation_name );
						if ( $img_id ) {
							$variation->set_image_id( $img_id );
							$variation_image_set = true;
						}
					}
				}
			}

			// Priority 3: Fallback to stored variation images (from previous imports)
			if ( ! $variation_image_set ) {
				$variation_images = get_post_meta( $parent_id, '_vortem_variation_images', true );
				if ( ! empty( $variation_images ) && is_array( $variation_images ) ) {
					// Try to match variation image by attribute values
					$variation_image_key = null;
					foreach ( $matching_attributes as $attr_name => $attr_value ) {
						$possible_key = 'var_' . strtolower( str_replace( ' ', '-', $attr_value ) );
						if ( isset( $variation_images[ $possible_key ] ) && is_numeric( $variation_images[ $possible_key ] ) ) {
							$variation_image_key = $possible_key;
							break;
						}
					}

					if ( $variation_image_key && ! empty( $variation_images[ $variation_image_key ] ) ) {
						$variation->set_image_id( intval( $variation_images[ $variation_image_key ] ) );
						$variation_image_set = true;
					}
				}
			}

			$saved_id = $variation->save();
			if ( ! $saved_id ) {
				continue;
			}

			$processed_variation_ids[] = $saved_id;
		}

		if ( isset( $api_product['delete_unmatched_variations'] ) && wc_string_to_bool( $api_product['delete_unmatched_variations'] ) ) {
			$variations_to_delete = array_diff( $existing_variation_ids_all_statuses, $processed_variation_ids );
			if ( ! empty( $variations_to_delete ) ) {
				foreach ( $variations_to_delete as $id_to_delete ) {
					$variation_to_delete = wc_get_product( $id_to_delete );
					if ( $variation_to_delete ) {
						$variation_to_delete->delete( true );
					}
				}
			}
		}

		WC_Product_Variable::sync( $parent_id );
	}

	/**
	 * Generate variations automatically from variation attributes
	 *
	 * @param WC_Product_Variable &$product
	 * @param array               $api_product
	 */
	private function generate_variations_from_attributes( &$product, $api_product ) {
		$parent_id = $product->get_id();

		// Get variation attributes
		$variation_attributes = array();
		$attributes           = $product->get_attributes();

		foreach ( $attributes as $attribute ) {
			if ( $attribute->get_variation() ) {
				$variation_attributes[] = array(
					'name'    => $attribute->get_name(),
					'options' => $attribute->get_options(),
				);
			}
		}

		if ( empty( $variation_attributes ) ) {
			return;
		}

		// Generate all possible combinations
		$combinations = $this->generate_attribute_combinations( $variation_attributes );

		if ( empty( $combinations ) ) {
			return;
		}

		// Prevent performance issues with too many variations
		$max_variations = 50;
		if ( count( $combinations ) > $max_variations ) {
			$combinations = array_slice( $combinations, 0, $max_variations );
		}

		// Create variations for each combination
		foreach ( $combinations as $index => $combination ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );

			// Set attributes for this variation
			$variation_attrs = array();
			foreach ( $combination as $attr_name => $attr_value ) {
				$variation_attrs[ sanitize_title( $attr_name ) ] = $attr_value;
			}
			$variation->set_attributes( $variation_attrs );

			// Set basic pricing (inherit from parent or use default)
			if ( isset( $api_product['price']['original'] ) ) {
				$variation->set_regular_price( wc_clean( $api_product['price']['original'] ) );
			}

			// Set stock management
			$variation->set_manage_stock( false );
			$variation->set_stock_status( 'instock' );

			// Generate SKU if parent has SKU
			$parent_sku = $product->get_sku();
			if ( ! empty( $parent_sku ) ) {
				$variation_sku = $parent_sku . '-' . ( $index + 1 );
				$variation->set_sku( $variation_sku );
			}

			$variation->save();
		}

		// Sync the variable product
		WC_Product_Variable::sync( $parent_id );
	}

	/**
	 * Generate all possible combinations of attribute values
	 *
	 * @param array $variation_attributes
	 * @return array
	 */
	private function generate_attribute_combinations( $variation_attributes ) {
		if ( empty( $variation_attributes ) ) {
			return array();
		}

		$combinations = array( array() );

		foreach ( $variation_attributes as $attribute ) {
			$new_combinations = array();
			foreach ( $combinations as $combination ) {
				foreach ( $attribute['options'] as $option ) {
					$new_combination                       = $combination;
					$new_combination[ $attribute['name'] ] = $option;
					$new_combinations[]                    = $new_combination;
				}
			}
			$combinations = $new_combinations;
		}

		return $combinations;
	}

	/**
	 * Regenerate variations for an existing variable product
	 * This can be used to fix products that have attributes but no variations
	 *
	 * @param int $product_id
	 * @return bool|WP_Error
	 */
	public function regenerate_variations_for_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return new WP_Error( 'invalid_product', 'Product is not a variable product' );
		}

		// Get existing variations and delete them
		$existing_variations = $product->get_children();
		foreach ( $existing_variations as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$variation->delete( true );
			}
		}

		// Generate new variations from attributes
		$this->generate_variations_from_attributes( $product, array() );

		return true;
	}

	/**
	 * Validate variation data before processing
	 *
	 * @param array $v_data Variation data
	 * @return bool True if valid, false otherwise
	 */
	private function validate_variation_data( $v_data ) {
		if ( empty( $v_data['attributes'] ) || ! is_array( $v_data['attributes'] ) ) {
			return false;
		}

		// Check if attributes have valid values
		foreach ( $v_data['attributes'] as $name => $value ) {
			if ( empty( $name ) || empty( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Import product media (images and videos) from API response
	 *
	 * This function handles:
	 * - Setting main image as featured image
	 * - Adding gallery images (excluding duplicates from variations)
	 * - Downloading and attaching video files
	 * - Avoiding duplicate downloads for variation images
	 *
	 * @param int   $product_id WooCommerce product ID
	 * @param array $api_data API response data containing images, video_url, and variations
	 * @return array Result array with success status, counts, and any messages
	 */
	public function import_product_media( $product_id, $api_data ) {
		$result = array(
			'success'             => true,
			'featured_image_id'   => false,
			'gallery_image_ids'   => array(),
			'video_attachment_id' => false,
			'skipped_duplicates'  => 0,
			'errors'              => array(),
			'messages'            => array(),
		);

		if ( empty( $product_id ) || ! is_numeric( $product_id ) ) {
			$result['success']  = false;
			$result['errors'][] = 'Invalid product ID provided';
			return $result;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$result['success']  = false;
			$result['errors'][] = 'Product not found';
			return $result;
		}

		// Collect all variation image URLs to avoid duplicates
		$variation_image_urls = array();
		if ( ! empty( $api_data['variations'] ) && is_array( $api_data['variations'] ) ) {
			foreach ( $api_data['variations'] as $variation ) {
				if ( ! empty( $variation['image'] ) && is_string( $variation['image'] ) ) {
					$variation_image_urls[] = $variation['image'];
				}
			}
		}
		$variation_image_urls = array_unique( $variation_image_urls );

		// Handle main image (featured image)
		if ( ! empty( $api_data['images']['main'] ) ) {
			$main_image_url = $api_data['images']['main'];

			// Check if main image is a duplicate of variation image
			if ( ! in_array( $main_image_url, $variation_image_urls, true ) ) {
				$main_attachment_id = $this->download_and_attach_media( $main_image_url, $product_id, 'featured' );
				if ( $main_attachment_id ) {
					$product->set_image_id( $main_attachment_id );
					$product->save();
					$result['featured_image_id'] = $main_attachment_id;
				} else {
					$result['errors'][] = 'Failed to download main image: ' . $main_image_url;
				}
			} else {
				++$result['skipped_duplicates'];
			}
		}

		// Handle gallery images
		if ( ! empty( $api_data['images']['gallery'] ) && is_array( $api_data['images']['gallery'] ) ) {
			$gallery_urls = array_unique( $api_data['images']['gallery'] );

			foreach ( $gallery_urls as $gallery_url ) {
				if ( empty( $gallery_url ) ) {
					continue;
				}

				// Skip if this gallery image is a duplicate of variation image
				if ( in_array( $gallery_url, $variation_image_urls, true ) ) {
					++$result['skipped_duplicates'];
					continue;
				}

				// Skip if this gallery image is the same as main image
				if ( ! empty( $api_data['images']['main'] ) && $gallery_url === $api_data['images']['main'] ) {
					++$result['skipped_duplicates'];
					continue;
				}

				$gallery_attachment_id = $this->download_and_attach_media( $gallery_url, $product_id, 'gallery' );
				if ( $gallery_attachment_id ) {
					$result['gallery_image_ids'][] = $gallery_attachment_id;
				} else {
					$result['errors'][] = 'Failed to download gallery image: ' . $gallery_url;
				}
			}

			// Set gallery images on product
			if ( ! empty( $result['gallery_image_ids'] ) ) {
				$existing_gallery_ids = $product->get_gallery_image_ids();
				$all_gallery_ids      = array_unique( array_merge( $existing_gallery_ids, $result['gallery_image_ids'] ) );

				$product->set_gallery_image_ids( $all_gallery_ids );
				update_post_meta( $product_id, '_product_image_gallery', implode( ',', $all_gallery_ids ) );
				$product->save();
			}
		}

		// Update success status based on errors
		if ( ! empty( $result['errors'] ) ) {
			$result['success'] = false;
		}

		return $result;
	}

	/**
	 * Download and attach media file (image or video) to product with retry mechanism
	 *
	 * @param string $media_url URL of the media file
	 * @param int    $product_id Product ID to attach to
	 * @param string $type Type of media: 'featured', 'gallery', or 'video'
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_and_attach_media( $media_url, $product_id, $type = 'gallery', $retry_count = 0 ) {
		if ( empty( $media_url ) || ! filter_var( $media_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if attachment already exists by URL
		$existing_attachment = $this->get_attachment_by_url( $media_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		$max_retries = 3;

		// Add delay between downloads (except for first image)
		if ( $retry_count === 0 && $type === 'gallery' ) {
			usleep( 800000 ); // 0.8 seconds delay for gallery images
		}

		// Download the file using WordPress native function with increased timeout
		$timeout  = ( $type === 'video' ) ? 300 : 60; // 5 minutes for videos, 60 seconds for images
		$tmp_file = download_url( $media_url, $timeout );

		if ( is_wp_error( $tmp_file ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->download_and_attach_media( $media_url, $product_id, $type, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to download media after ' . ( $retry_count + 1 ) . ' attempts: ' . $media_url );
			}
			return false;
		}

		// Determine file extension and mime type
		$file_extension = pathinfo( wp_parse_url( $media_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( empty( $file_extension ) ) {
			// Try to determine from content type or default
			$file_extension = ( $type === 'video' ) ? 'mp4' : 'jpg';
		}

		// Generate filename
		$filename = 'vortem-product-' . $product_id . '-' . $type . '-' . time() . '.' . $file_extension;
		$filename = sanitize_file_name( $filename );

		// Prepare file array for media_handle_sideload
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			return false;
		}

		$attachment_id = media_handle_sideload( $file_array, $product_id );

		// Clean up temp file if attachment creation failed
		if ( is_wp_error( $attachment_id ) ) {
			if ( ! empty( $tmp_file ) && file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}

			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->download_and_attach_media( $media_url, $product_id, $type, $retry_count + 1 );
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to sideload media after ' . ( $retry_count + 1 ) . ' attempts: ' . $media_url . ' - Error: ' . $attachment_id->get_error_message() );
			}
			return false;
		}

		// Generate attachment metadata for images
		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return $attachment_id;
		}

		if ( $type !== 'video' ) {
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );
			if ( $attachment_data ) {
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			vortem_log( 'Vortem AI: Successfully downloaded and attached media: ' . $media_url . ' (Type: ' . $type . ', Attachment ID: ' . $attachment_id . ')' );
		}

		return $attachment_id;
	}

	/**
	 * Handle product video from API response
	 *
	 * Extracts the video_url from the API response, downloads the video file,
	 * and attaches it to the product's Product Video section in WooCommerce.
	 * If no video is available (message string), skips video import.
	 *
	 * @param int   $product_id WooCommerce product ID
	 * @param array $api_product API product data containing video_url field
	 * @return array Result array with success status, video_attachment_id, and message
	 */
	public function handle_product_video( $product_id, $api_product ) {
		$result = array(
			'success'             => false,
			'video_attachment_id' => false,
			'message'             => '',
		);

		// Check if product exists
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$result['message'] = 'Product not found';
			return $result;
		}

		// Extract video_url from API response
		$video_url = '';
		if ( isset( $api_product['video_url'] ) && ! empty( $api_product['video_url'] ) ) {
			$video_url = trim( $api_product['video_url'] );
		}

		// Check if video_url is the "no video" message string
		$no_video_message = 'AliExpress does not have a video for this product.';
		if ( empty( $video_url ) || $video_url === $no_video_message ) {
			// No video available - skip import
			$result['message'] = $no_video_message;
			$result['success'] = true; // Mark as success since skipping is expected behavior
			return $result;
		}

		// Validate that video_url is a valid URL
		if ( ! filter_var( $video_url, FILTER_VALIDATE_URL ) ) {
			$result['message'] = $no_video_message;
			$result['success'] = true; // Mark as success since invalid URL means no video
			return $result;
		}

		// Check if "Add video to product description" is disabled - if so, don't download video at all
		$add_video_to_description = get_option( 'vortem_add_video_to_description', true );
		if ( ! $add_video_to_description ) {
			$result['message'] = 'Video download disabled - Add video to description setting is off';
			$result['success'] = true; // Mark as success since skipping is expected behavior
			return $result;
		}

		// Check if current theme is excluded and video download is disabled for excluded themes
		if ( $this->is_excluded_theme() && ! $this->should_download_video_for_excluded_themes() ) {
			$result['message'] = 'Video download disabled for this theme';
			$result['success'] = true; // Mark as success since skipping is expected behavior
			return $result;
		}

		// Download and attach the video

		$video_attachment_id = $this->download_and_attach_video( $video_url, $product_id );

		if ( $video_attachment_id && ! is_wp_error( $video_attachment_id ) ) {
			// Store video in WordPress attachment format (wp_posts and wp_postmeta)
			// The video is already stored as an attachment in wp_posts with post_type='attachment' and post_mime_type='video/mp4'

			// Store video gallery meta for WooCommerce themes (woodmart and mytheme formats)
			$this->store_video_gallery_meta( $product_id, $video_attachment_id, $video_url );

			// Store in theme format (_product_video_ids) - array of video attachment IDs
			// This allows the theme to automatically select and display videos in the product gallery
			$existing_video_ids = get_post_meta( $product_id, '_product_video_ids', true );
			$video_ids          = is_array( $existing_video_ids ) ? $existing_video_ids : array();

			// Add the new video attachment ID if it's not already in the array
			if ( ! in_array( $video_attachment_id, $video_ids, true ) ) {
				$video_ids[] = intval( $video_attachment_id );
				update_post_meta( $product_id, '_product_video_ids', array_values( $video_ids ) );
			}

			// Also store in custom meta for reference
			update_post_meta( $product_id, '_vortem_product_video_id', $video_attachment_id );
			update_post_meta( $product_id, '_vortem_video_url', esc_url_raw( $video_url ) );

			// Add video tag to the beginning of product description
			$this->inject_video_tag_to_description( $product_id, $video_attachment_id );

			$result['success']             = true;
			$result['video_attachment_id'] = $video_attachment_id;
			$result['message']             = 'Video attached successfully';
		} else {
			$error_message = is_wp_error( $video_attachment_id ) ? $video_attachment_id->get_error_message() : 'Unknown error';

			$result['message'] = $no_video_message;
		}

		return $result;
	}

	/**
	 * Store video gallery meta for WooCommerce themes
	 *
	 * Stores video in the format expected by WooCommerce themes like Woodmart and MyTheme
	 * Format: serialized array with video_type, video_id, etc.
	 *
	 * @param int    $product_id Product ID
	 * @param int    $video_attachment_id Video attachment ID
	 * @param string $video_url Original video URL
	 */
	private function store_video_gallery_meta( $product_id, $video_attachment_id, $video_url ) {
		// Get video file path
		$video_file     = get_attached_file( $video_attachment_id );
		$video_url_path = '';
		if ( $video_file ) {
			$upload_dir     = wp_upload_dir();
			$video_url_path = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $video_file );
		}

		// Get video file extension
		$file_extension = pathinfo( wp_parse_url( $video_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( empty( $file_extension ) && $video_file ) {
			$file_extension = pathinfo( $video_file, PATHINFO_EXTENSION );
		}
		if ( empty( $file_extension ) ) {
			$file_extension = 'mp4'; // Default to mp4
		}

		// Woodmart format: a:1:{i:ATTACHMENT_ID;a:11:{s:10:"video_type";s:3:"mp4";...}}
		$woodmart_video_data = array(
			$video_attachment_id => array(
				'video_type'      => $file_extension,
				'video_id'        => $video_attachment_id,
				'video_url'       => $video_url_path ?: $video_url,
				'video_thumbnail' => '',
				'video_duration'  => '',
				'video_width'     => '',
				'video_height'    => '',
				'video_autoplay'  => '0',
				'video_loop'      => '0',
				'video_controls'  => '1',
				'video_muted'     => '0',
			),
		);
		update_post_meta( $product_id, 'woodmart_wc_video_gallery', $woodmart_video_data );

		// MyTheme format: a:1:{i:ATTACHMENT_ID;a:5:{s:10:"video_type";s:3:"mp4";...}}
		$mytheme_video_data = array(
			$video_attachment_id => array(
				'video_type'      => $file_extension,
				'video_id'        => $video_attachment_id,
				'video_url'       => $video_url_path ?: $video_url,
				'video_thumbnail' => '',
				'video_duration'  => '',
			),
		);
		update_post_meta( $product_id, 'mytheme_wc_video_gallery', $mytheme_video_data );

		// Also store in standard WooCommerce format
		update_post_meta( $product_id, '_product_video_gallery', $video_attachment_id );
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
	 * Check if video should be downloaded for excluded themes
	 *
	 * @return bool True if video should be downloaded for excluded themes
	 */
	private function should_download_video_for_excluded_themes() {
		return get_option( 'vortem_download_video_for_excluded_themes', true );
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
	 * Inject video HTML tag to the beginning of product description
	 *
	 * Adds a video tag with the downloaded video file to the beginning
	 * of the product description for WooCommerce products.
	 *
	 * @param int $product_id Product ID
	 * @param int $video_attachment_id Video attachment ID from WordPress media library
	 */
	private function inject_video_tag_to_description( $product_id, $video_attachment_id ) {
		// Check if video should be injected based on theme
		if ( ! $this->should_inject_video_to_description() ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Get video URL from attachment
		$video_url = wp_get_attachment_url( $video_attachment_id );
		if ( empty( $video_url ) ) {
			return;
		}

		// Get current description
		$description = $product->get_description();
		if ( empty( $description ) ) {
			$description = '';
		}

		// Check if video tag already exists in description to avoid duplicates
		// Check for the video URL or the vortem-product-video class
		if ( strpos( $description, $video_url ) !== false || strpos( $description, 'vortem-product-video' ) !== false ) {
			return;
		}

		// Get video file extension for proper MIME type
		$video_file     = get_attached_file( $video_attachment_id );
		$file_extension = 'mp4';
		if ( $video_file ) {
			$file_extension = strtolower( pathinfo( $video_file, PATHINFO_EXTENSION ) );
			if ( empty( $file_extension ) ) {
				$file_extension = 'mp4';
			}
		}

		// Create video HTML tag with inline styles
		$video_html  = '<div class="vortem-product-video" style="text-align: center; margin: 20px auto; max-width: 500px; border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #f9fafb; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">' . "\n";
		$video_html .= '<video controls width="100%" style="max-width: 100%; height: auto; display: block; border-radius: 4px; aspect-ratio: 1 / 1; object-fit: cover;">' . "\n";
		$video_html .= '<source src="' . esc_url( $video_url ) . '" type="video/' . esc_attr( $file_extension ) . '">' . "\n";
		$video_html .= '<p>' . esc_html__( 'Your browser does not support the video tag.', 'vortem-ai' ) . '</p>' . "\n";
		$video_html .= '</video>' . "\n";
		$video_html .= '</div>' . "\n\n";

		// Prepend video tag to description
		$new_description = $video_html . $description;

		// Update product description
		$product->set_description( $new_description );
		$product->save();
	}

	/**
	 * Download and attach video file to product with retry mechanism
	 *
	 * Uses WordPress-native media handling functions to download the video
	 * from the URL and attach it to the product. The video is stored in wp_posts
	 * as an attachment with post_type='attachment' and post_mime_type='video/mp4'.
	 *
	 * @param string $video_url URL of the video file
	 * @param int    $product_id Product ID to attach the video to
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure
	 */
	private function download_and_attach_video( $video_url, $product_id, $retry_count = 0 ) {
		if ( empty( $video_url ) || ! filter_var( $video_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', 'Invalid video URL provided' );
		}

		// Check if video attachment already exists by URL
		$existing_attachment = $this->get_attachment_by_url( $video_url );
		if ( $existing_attachment ) {
			return $existing_attachment;
		}

		$max_retries = 3;

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'download_url' ) ) {
			return new WP_Error( 'missing_function', 'Required WordPress media functions are not available.' );
		}

		// Use download_url to download the file with longer timeout for videos
		$tmp_file = download_url( $video_url, 300 ); // 5 minute timeout for large videos

		if ( is_wp_error( $tmp_file ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( ( $retry_count + 1 ) * 2 ); // Progressive delay: 2s, 4s, 6s
				return $this->download_and_attach_video( $video_url, $product_id, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to download video after ' . ( $retry_count + 1 ) . ' attempts: ' . $video_url . ' - Error: ' . $tmp_file->get_error_message() );
			}
			return $tmp_file;
		}

		// Get file extension from URL or default to mp4
		$file_extension = pathinfo( wp_parse_url( $video_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( empty( $file_extension ) ) {
			// Try to determine from Content-Type header or default to mp4
			$file_extension = 'mp4';
		}

		// Generate filename
		$filename = 'vortem-product-' . $product_id . '-video-' . time() . '.' . $file_extension;
		$filename = sanitize_file_name( $filename );

		// Prepare file array for media_handle_sideload
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		// Use WordPress native function to sideload the video
		$attachment_id = media_handle_sideload( $file_array, $product_id );

		// Clean up temp file if attachment creation failed
		if ( is_wp_error( $attachment_id ) ) {
			if ( ! empty( $tmp_file ) && file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}

			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( ( $retry_count + 1 ) * 2 );
				return $this->download_and_attach_video( $video_url, $product_id, $retry_count + 1 );
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to sideload video after ' . ( $retry_count + 1 ) . ' attempts: ' . $video_url . ' - Error: ' . $attachment_id->get_error_message() );
			}
			return $attachment_id;
		}

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return $attachment_id;
		}

		// Set attachment metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );
		if ( $attachment_data ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}

		// Set attachment post parent to product
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $product_id,
			)
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			vortem_log( 'Vortem AI: Successfully downloaded and attached video: ' . $video_url . ' (Attachment ID: ' . $attachment_id . ')' );
		}

		return $attachment_id;
	}

	/**
	 * Get product video status and attachment ID
	 *
	 * Retrieves the video attachment ID or message for a product
	 *
	 * @param int $product_id WooCommerce product ID
	 * @return array Array with video_attachment_id and message
	 */
	public static function get_product_video_status( $product_id ) {
		$video_attachment_id = get_post_meta( $product_id, '_product_video_gallery', true );
		$message             = get_post_meta( $product_id, '_vortem_video_message', true );

		return array(
			'video_attachment_id' => $video_attachment_id ? intval( $video_attachment_id ) : false,
			'message'             => $message ? $message : '',
			'has_video'           => ! empty( $video_attachment_id ),
		);
	}

	/**
	 * Download gallery images and add to WordPress Media Library
	 *
	 * Downloads all images from the "gallery" section. If gallery image URLs are already
	 * present in variations.image, skips downloading duplicate images and only downloads
	 * each unique image once.
	 *
	 * @param array $product_data Product data containing 'gallery' and 'variations' arrays
	 *                            Example:
	 *                            [
	 *                                'gallery' => [
	 *                                    'https://example.com/image1.jpg',
	 *                                    'https://example.com/image2.jpg'
	 *                                ],
	 *                                'variations' => [
	 *                                    ['image' => 'https://example.com/image1.jpg'], // Duplicate, will be skipped
	 *                                    ['image' => 'https://example.com/var1.jpg']
	 *                                ]
	 *                            ]
	 * @param int   $product_id WooCommerce product ID
	 * @return array Result array with:
	 *              - 'success' (bool): Whether the operation was successful
	 *              - 'gallery_ids' (array): Array of attachment IDs for gallery images
	 *              - 'downloaded_count' (int): Number of unique images downloaded
	 *              - 'skipped_count' (int): Number of duplicate images skipped
	 *              - 'errors' (array): Array of error messages if any occurred
	 */
	public function download_gallery_images( $product_data, $product_id ) {
		$result = array(
			'success'          => true,
			'gallery_ids'      => array(),
			'downloaded_count' => 0,
			'skipped_count'    => 0,
			'errors'           => array(),
		);

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			$result['success']  = false;
			$result['errors'][] = 'Required WordPress media functions are not available.';
			return $result;
		}

		// Validate product ID
		if ( empty( $product_id ) || ! is_numeric( $product_id ) ) {
			$result['success']  = false;
			$result['errors'][] = 'Invalid product ID provided';
			return $result;
		}

		// Check if gallery exists
		if ( empty( $product_data['gallery'] ) || ! is_array( $product_data['gallery'] ) ) {
			$result['errors'][] = 'No gallery images found in product data';
			return $result;
		}

		// Collect all variation image URLs to check for duplicates
		$variation_image_urls = array();
		if ( ! empty( $product_data['variations'] ) && is_array( $product_data['variations'] ) ) {
			foreach ( $product_data['variations'] as $variation ) {
				if ( ! empty( $variation['image'] ) && is_string( $variation['image'] ) ) {
					// Normalize URL for comparison (remove query strings, trailing slashes, etc.)
					$normalized_url                          = $this->normalize_image_url( $variation['image'] );
					$variation_image_urls[ $normalized_url ] = $variation['image'];
				}
			}
		}

		// Map to store URL => attachment_id for all downloaded images
		$url_to_attachment_map = array();

		// Process each gallery image URL
		foreach ( $product_data['gallery'] as $index => $gallery_url ) {
			if ( empty( $gallery_url ) || ! is_string( $gallery_url ) ) {
				continue;
			}

			// Validate URL
			if ( ! filter_var( $gallery_url, FILTER_VALIDATE_URL ) ) {
				$result['errors'][] = "Invalid gallery image URL at index {$index}: {$gallery_url}";
				continue;
			}

			// Normalize URL for comparison
			$normalized_gallery_url = $this->normalize_image_url( $gallery_url );

			// Check if this gallery URL already exists in variation images
			if ( isset( $variation_image_urls[ $normalized_gallery_url ] ) ) {
				// This image is already in variations, check if it's already downloaded
				$variation_url = $variation_image_urls[ $normalized_gallery_url ];

				// Check if already downloaded (either from variations or previous gallery processing)
				$existing_attachment = $this->get_attachment_by_url_in_media_library( $variation_url );

				if ( $existing_attachment ) {
					// Image already exists, reuse it
					$url_to_attachment_map[ $normalized_gallery_url ] = $existing_attachment;
					$result['gallery_ids'][]                          = $existing_attachment;
					++$result['skipped_count'];
					continue;
				}
			}

			// Check if we've already processed this exact URL in this batch
			if ( isset( $url_to_attachment_map[ $normalized_gallery_url ] ) ) {
				// Already downloaded in this batch, reuse
				$result['gallery_ids'][] = $url_to_attachment_map[ $normalized_gallery_url ];
				++$result['skipped_count'];
				continue;
			}

			// Check if image already exists in media library
			$existing_attachment = $this->get_attachment_by_url_in_media_library( $gallery_url );
			if ( $existing_attachment ) {
				$url_to_attachment_map[ $normalized_gallery_url ] = $existing_attachment;
				$result['gallery_ids'][]                          = $existing_attachment;
				++$result['skipped_count'];
				continue;
			}

			// Download and import image using media_sideload_image functionality
			$attachment_id = $this->sideload_image_to_media_library( $gallery_url, $product_id );

			if ( $attachment_id && is_numeric( $attachment_id ) ) {
				$url_to_attachment_map[ $normalized_gallery_url ] = intval( $attachment_id );
				$result['gallery_ids'][]                          = intval( $attachment_id );
				++$result['downloaded_count'];
			} else {
				$error_msg          = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'Unknown error';
				$result['errors'][] = "Failed to download gallery image at index {$index}: {$error_msg}";
			}
		}

		// Assign gallery images to product
		if ( ! empty( $result['gallery_ids'] ) ) {
			// Remove duplicates from gallery_ids array
			$result['gallery_ids'] = array_unique( $result['gallery_ids'] );

			// Store gallery image IDs in _product_image_gallery post meta (comma-separated)
			$gallery_ids_string = implode( ',', $result['gallery_ids'] );
			update_post_meta( $product_id, '_product_image_gallery', $gallery_ids_string );

			// Also use WooCommerce method for compatibility
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product->set_gallery_image_ids( $result['gallery_ids'] );
				$product->save();
			}
		}

		// Mark as unsuccessful if there were critical errors and no images were processed
		if ( ! empty( $result['errors'] ) && empty( $result['gallery_ids'] ) && $result['downloaded_count'] === 0 ) {
			$result['success'] = false;
		}

		return $result;
	}

	/**
	 * Download image from URL and add to WordPress Media Library with retry mechanism
	 *
	 * @param string $image_url Image URL to download
	 * @param int    $post_id Post ID to attach the image to
	 * @param int    $retry_count Current retry attempt (default: 0)
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure
	 */
	private function sideload_image_to_media_library( $image_url, $post_id, $retry_count = 0 ) {
		$max_retries = 3;

		// Add delay between downloads (except for first image)
		if ( $retry_count === 0 ) {
			usleep( 800000 ); // 0.8 seconds delay to prevent server throttling
		}

		// Download the file temporarily with increased timeout
		$tmp_file = download_url( $image_url, 60 ); // 60 seconds timeout

		if ( is_wp_error( $tmp_file ) ) {
			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 ); // Progressive delay: 1s, 2s, 3s
				return $this->sideload_image_to_media_library( $image_url, $post_id, $retry_count + 1 );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to download image to media library after ' . ( $retry_count + 1 ) . ' attempts: ' . $image_url );
			}
			return $tmp_file;
		}

		// Prepare file array for media_handle_sideload
		$file_array = array(
			'name'     => basename( wp_parse_url( $image_url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp_file,
		);

		// If filename is empty or invalid, generate one
		if ( empty( $file_array['name'] ) || ! preg_match( '/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $file_array['name'] ) ) {
			$file_extension = pathinfo( wp_parse_url( $image_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
			if ( empty( $file_extension ) ) {
				$file_extension = 'jpg'; // Default extension
			}
			$file_array['name'] = 'vortem-image-' . time() . '-' . wp_generate_password( 6, false ) . '.' . $file_extension;
		}

		// Use media_handle_sideload (which is what media_sideload_image uses internally)
		// media_sideload_image() is a wrapper that returns HTML, but we need the attachment ID
		// media_handle_sideload() is the underlying function that returns the attachment ID
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Clean up temp file if there was an error
		if ( is_wp_error( $attachment_id ) ) {
			if ( ! empty( $tmp_file ) && file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}

			// Retry if not exceeded max retries
			if ( $retry_count < $max_retries ) {
				sleep( $retry_count + 1 );
				return $this->sideload_image_to_media_library( $image_url, $post_id, $retry_count + 1 );
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				vortem_log( 'Vortem AI: Failed to sideload image after ' . ( $retry_count + 1 ) . ' attempts: ' . $image_url . ' - Error: ' . $attachment_id->get_error_message() );
			}
			return $attachment_id;
		}

		// Safety check: media functions must be available (loaded by WordPress in admin context).
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return $attachment_id;
		}

		// Generate attachment metadata
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );
		if ( $attachment_data ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}

		// Store source URL for future lookups (prevents duplicate downloads)
		update_post_meta( $attachment_id, '_vortem_source_url', $image_url );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			vortem_log( 'Vortem AI: Successfully sideloaded image to media library: ' . $image_url . ' (Attachment ID: ' . $attachment_id . ')' );
		}

		return $attachment_id;
	}

	/**
	 * Get attachment ID by image URL from Media Library
	 *
	 * Checks both the stored source URL meta and the attachment file path
	 *
	 * @param string $image_url Image URL
	 * @return int|false Attachment ID if found, false otherwise
	 */
	private function get_attachment_by_url_in_media_library( $image_url ) {
		global $wpdb;

		// Method 1: Try to find by stored source URL in post meta
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_vortem_source_url' AND meta_value = %s
             LIMIT 1",
				$image_url
			)
		);

		if ( $attachment_id ) {
			return intval( $attachment_id );
		}

		// Method 2: Try to find by normalized URL (in case URL was stored with different format)
		$normalized_url = $this->normalize_image_url( $image_url );
		$attachment_id  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_vortem_source_url' AND meta_value = %s
             LIMIT 1",
				$normalized_url
			)
		);

		if ( $attachment_id ) {
			return intval( $attachment_id );
		}

		// Method 3: Try to find by filename match
		$filename = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );
		if ( ! empty( $filename ) ) {
			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s
                 LIMIT 1",
					'%' . $wpdb->esc_like( $filename )
				)
			);

			if ( $attachment_id ) {
				// Store the source URL for future lookups
				update_post_meta( $attachment_id, '_vortem_source_url', $image_url );
				return intval( $attachment_id );
			}
		}

		return false;
	}

	/**
	 * Normalize image URL for comparison
	 *
	 * Removes query strings, normalizes protocol, and trims trailing slashes
	 * to allow accurate duplicate detection
	 *
	 * @param string $url Image URL
	 * @return string Normalized URL
	 */
	private function normalize_image_url( $url ) {
		// Parse URL to get components
		$parsed = wp_parse_url( $url );

		// Rebuild URL without query string and fragment
		$normalized = '';
		if ( isset( $parsed['scheme'] ) ) {
			$normalized .= $parsed['scheme'] . '://';
		}
		if ( isset( $parsed['host'] ) ) {
			$normalized .= $parsed['host'];
		}
		if ( isset( $parsed['path'] ) ) {
			$normalized .= rtrim( $parsed['path'], '/' );
		}

		// Normalize protocol (treat http and https as same for comparison)
		$normalized = preg_replace( '/^https?:\/\//i', '', $normalized );

		return strtolower( $normalized );
	}

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
