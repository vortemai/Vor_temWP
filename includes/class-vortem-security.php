<?php
/**
 * Vortem Security Utilities
 *
 * Provides whitelist-based input validation for SQL queries and other security checks
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Security Utilities Class
 */
class Vortem_Security {

	/**
	 * Allowed orderby fields for products table queries
	 *
	 * @var array
	 */
	private static $allowed_product_orderby = array(
		'id',
		'created_at',
		'name',
		'price',
		'sync_date',
		'sync_status',
		'vortem_product_id',
		'woo_product_id',
		'sku',
		'stock_status',
	);

	/**
	 * Allowed order values
	 *
	 * @var array
	 */
	private static $allowed_order = array( 'ASC', 'DESC' );

	/**
	 * Allowed sync status values
	 *
	 * @var array
	 */
	private static $allowed_sync_status = array(
		'pending',
		'synced',
		'failed',
		'running',
		'all',
	);

	/**
	 * Allowed post status values for WooCommerce products
	 *
	 * @var array
	 */
	private static $allowed_post_status = array(
		'publish',
		'draft',
		'pending',
		'private',
		'trash',
		'all',
	);

	/**
	 * Allowed product types
	 *
	 * @var array
	 */
	private static $allowed_product_types = array(
		'simple',
		'variable',
		'grouped',
		'external',
		'variation',
	);

	/**
	 * Allowed bulk actions
	 *
	 * @var array
	 */
	private static $allowed_bulk_actions = array(
		'import',
		'sync',
		'delete',
		'update',
	);

	/**
	 * Allowed sort fields for orders
	 *
	 * @var array
	 */
	private static $allowed_orderby = array(
		'date',
		'ID',
		'title',
		'modified',
		'menu_order',
		'order_number',
	);

	/**
	 * Allowed table names (for table name validation)
	 *
	 * @var array
	 */
	private static $allowed_tables = array(
		'vortem_products',
	);

	/**
	 * Validate and sanitize orderby parameter using whitelist
	 *
	 * @param string $orderby The orderby value from user input
	 * @param string $default The default value if not in whitelist
	 * @param string $table_prefix Optional table prefix for field validation
	 * @return string The validated orderby value
	 */
	public static function validate_orderby( $orderby, $default = 'created_at', $table_prefix = '' ) {
		$orderby = sanitize_text_field( $orderby );
		$orderby = stripslashes( $orderby );

		// If table prefix is provided, validate with prefix
		if ( ! empty( $table_prefix ) ) {
			$allowed = array_map(
				function ( $field ) use ( $table_prefix ) {
					return $table_prefix . '.' . $field;
				},
				self::$allowed_product_orderby
			);

			if ( in_array( $orderby, $allowed, true ) ) {
				return $orderby;
			}
		}

		// Direct field validation
		if ( in_array( $orderby, self::$allowed_product_orderby, true ) ) {
			return $orderby;
		}

		return $default;
	}

	/**
	 * Validate and sanitize order parameter using whitelist
	 *
	 * @param string $order The order value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated order value (ASC or DESC)
	 */
	public static function validate_order( $order, $default = 'DESC' ) {
		$order = strtoupper( sanitize_text_field( $order ) );
		$order = stripslashes( $order );

		if ( in_array( $order, self::$allowed_order, true ) ) {
			return $order;
		}

		return $default;
	}

	/**
	 * Validate and sanitize sync_status parameter using whitelist
	 *
	 * @param string $status The status value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated status value
	 */
	public static function validate_sync_status( $status, $default = 'all' ) {
		$status = sanitize_text_field( $status );
		$status = stripslashes( $status );

		if ( in_array( $status, self::$allowed_sync_status, true ) ) {
			return $status;
		}

		return $default;
	}

	/**
	 * Validate and sanitize post_status parameter using whitelist
	 *
	 * @param string $post_status The post_status value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated post_status value
	 */
	public static function validate_post_status( $post_status, $default = 'all' ) {
		$post_status = sanitize_text_field( $post_status );
		$post_status = stripslashes( $post_status );

		if ( in_array( $post_status, self::$allowed_post_status, true ) ) {
			return $post_status;
		}

		return $default;
	}

	/**
	 * Validate and sanitize product_type parameter using whitelist
	 *
	 * @param string $product_type The product_type value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated product_type value
	 */
	public static function validate_product_type( $product_type, $default = 'simple' ) {
		$product_type = sanitize_text_field( $product_type );
		$product_type = stripslashes( $product_type );

		if ( in_array( $product_type, self::$allowed_product_types, true ) ) {
			return $product_type;
		}

		return $default;
	}

	/**
	 * Validate and sanitize bulk_action parameter using whitelist
	 *
	 * @param string $action The action value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated action value
	 */
	public static function validate_bulk_action( $action, $default = '' ) {
		$action = sanitize_text_field( $action );
		$action = stripslashes( $action );

		if ( in_array( $action, self::$allowed_bulk_actions, true ) ) {
			return $action;
		}

		return $default;
	}

	/**
	 * Validate and sanitize orderby for orders query using whitelist
	 *
	 * @param string $orderby The orderby value from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated orderby value
	 */
	public static function validate_orderby_field( $orderby, $default = 'date' ) {
		$orderby = sanitize_text_field( $orderby );
		$orderby = stripslashes( $orderby );

		if ( in_array( $orderby, self::$allowed_orderby, true ) ) {
			return $orderby;
		}

		return $default;
	}

	/**
	 * Validate table name using whitelist
	 *
	 * @param string $table_name The table name from user input
	 * @param string $default The default value if not in whitelist
	 * @return string The validated table name
	 */
	public static function validate_table_name( $table_name, $default = '' ) {
		global $wpdb;

		$table_name = sanitize_text_field( $table_name );
		$table_name = stripslashes( $table_name );

		// Build full table name with prefix
		$full_table_name = $wpdb->prefix . $table_name;

		if ( in_array( $table_name, self::$allowed_tables, true ) ) {
			return $full_table_name;
		}

		return $default;
	}

	/**
	 * Validate and sanitize category ID
	 *
	 * @param mixed $category_id The category ID from user input
	 * @param int   $default The default value if validation fails
	 * @return int The validated category ID
	 */
	public static function validate_category_id( $category_id, $default = 0 ) {
		if ( is_numeric( $category_id ) ) {
			$id = intval( $category_id );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return $default;
	}

	/**
	 * Validate and sanitize numeric ID
	 *
	 * @param mixed $id The ID from user input
	 * @param int   $default The default value if validation fails
	 * @return int The validated ID
	 */
	public static function validate_id( $id, $default = 0 ) {
		if ( is_numeric( $id ) ) {
			$id = intval( $id );
			if ( $id >= 0 ) {
				return $id;
			}
		}

		return $default;
	}

	/**
	 * Validate and sanitize limit/offset values
	 *
	 * @param mixed $value The value from user input
	 * @param int   $default The default value if validation fails
	 * @param int   $min Minimum allowed value
	 * @param int   $max Maximum allowed value
	 * @return int The validated value
	 */
	public static function validate_limit( $value, $default = 20, $min = 1, $max = 1000 ) {
		if ( is_numeric( $value ) ) {
			$value = intval( $value );
			if ( $value >= $min && $value <= $max ) {
				return $value;
			}
		}

		return $default;
	}

	/**
	 * Validate and sanitize page number
	 *
	 * @param mixed $page The page number from user input
	 * @param int   $default The default value if validation fails
	 * @return int The validated page number
	 */
	public static function validate_page( $page, $default = 1 ) {
		if ( is_numeric( $page ) ) {
			$page = intval( $page );
			if ( $page >= 1 ) {
				return $page;
			}
		}

		return $default;
	}

	/**
	 * Validate and sanitize SKU
	 *
	 * @param string $sku The SKU from user input
	 * @param string $default The default value if validation fails
	 * @return string The validated SKU
	 */
	public static function validate_sku( $sku, $default = '' ) {
		$sku = sanitize_text_field( $sku );
		$sku = stripslashes( $sku );
		$sku = trim( $sku );

		// SKU should be alphanumeric with optional dashes/underscores
		if ( ! empty( $sku ) && preg_match( '/^[a-zA-Z0-9_\-]+$/', $sku ) ) {
			return $sku;
		}

		return $default;
	}

	/**
	 * Validate and sanitize search term
	 *
	 * @param string $search The search term from user input
	 * @param string $default The default value if validation fails
	 * @return string The validated search term
	 */
	public static function validate_search( $search, $default = '' ) {
		$search = sanitize_text_field( $search );
		$search = stripslashes( $search );
		$search = trim( $search );

		// Limit search length
		if ( strlen( $search ) <= 200 ) {
			return $search;
		}

		return $default;
	}

	/**
	 * Validate and sanitize date string
	 *
	 * @param string $date The date string from user input
	 * @param string $default The default value if validation fails
	 * @return string The validated date string
	 */
	public static function validate_date( $date, $default = '' ) {
		$date = sanitize_text_field( $date );
		$date = stripslashes( $date );
		$date = trim( $date );

		// Validate date format (YYYY-MM-DD)
		if ( ! empty( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		return $default;
	}

	/**
	 * Get all allowed orderby fields
	 *
	 * @return array
	 */
	public static function get_allowed_orderby_fields() {
		return self::$allowed_product_orderby;
	}

	/**
	 * Get all allowed order values
	 *
	 * @return array
	 */
	public static function get_allowed_order_values() {
		return self::$allowed_order;
	}

	/**
	 * Get all allowed sync status values
	 *
	 * @return array
	 */
	public static function get_allowed_sync_status_values() {
		return self::$allowed_sync_status;
	}

	/**
	 * Get all allowed post status values
	 *
	 * @return array
	 */
	public static function get_allowed_post_status_values() {
		return self::$allowed_post_status;
	}

	/**
	 * Get all allowed product type values
	 *
	 * @return array
	 */
	public static function get_allowed_product_type_values() {
		return self::$allowed_product_types;
	}

	/**
	 * Get all allowed bulk action values
	 *
	 * @return array
	 */
	public static function get_allowed_bulk_action_values() {
		return self::$allowed_bulk_actions;
	}
}
