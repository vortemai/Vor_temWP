<?php
/**
 * Vortem Database Class
 *
 * Handles database operations for the plugin
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Database
 */
class Vortem_Database {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Core DB operations; table names from $wpdb->prefix.

	/**
	 * Database version
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Create all database tables
	 *
	 * @return array Result of table creation
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$results         = array();

		// Products table
		$products_table = $wpdb->prefix . 'vortem_products';
		$products_sql   = "CREATE TABLE $products_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vortem_product_id varchar(50) NOT NULL,
            woo_product_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL,
            description longtext,
            sku varchar(100) DEFAULT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            regular_price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            stock_quantity int(11) DEFAULT NULL,
            stock_status varchar(20) DEFAULT 'instock',
            weight decimal(8,2) DEFAULT NULL,
            length decimal(8,2) DEFAULT NULL,
            width decimal(8,2) DEFAULT NULL,
            height decimal(8,2) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            tags text,
            images text,
            attributes text,
            meta_data longtext,
            sync_status varchar(20) DEFAULT 'pending',
            sync_date datetime DEFAULT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY vortem_product_id (vortem_product_id),
            KEY woo_product_id (woo_product_id),
            KEY sku (sku),
            KEY sync_status (sync_status),
            KEY sync_date (sync_date),
            KEY created_at (created_at),
            KEY name (name),
            KEY price (price),
            KEY stock_status (stock_status)
        ) $charset_collate;";

		// Note: dbDelta() is available in admin/AJAX context

		// Create tables with error handling
		$tables_to_create = array(
			'products' => array(
				'sql'   => $products_sql,
				'table' => $products_table,
			),
		);

		foreach ( $tables_to_create as $table_name => $table_info ) {
			try {
				$result = dbDelta( $table_info['sql'] );

				if ( empty( $result ) ) {
					$results[ $table_name ] = array(
						'success' => false,
						'message' => 'Failed to create table: ' . esc_html( $table_name ),
					);
				} else {
					$results[ $table_name ] = array(
						'success' => true,
						'message' => 'Table created successfully: ' . esc_html( $table_name ),
						'result'  => $result,
					);
				}
			} catch ( Exception $e ) {
				$results[ $table_name ] = array(
					'success' => false,
					'message' => 'Exception creating table ' . esc_html( $table_name ) . ': ' . esc_html( $e->getMessage() ),
				);
			}
		}

		// Update database version
		update_option( 'vortem_db_version', self::DB_VERSION );

		return $results;
	}

	/**
	 * Drop all database tables
	 */
	public static function drop_tables() {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change is intentional on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vortem_products" );

		delete_option( 'vortem_db_version' );
	}

	/**
	 * Check if database tables exist
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'vortem_products',
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			);
			if ( $result !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate and fix database structure
	 *
	 * @return array Result of validation and fixes
	 */
	public static function validate_and_fix_database() {
		global $wpdb;

		$results = array(
			'tables_checked' => 0,
			'tables_fixed'   => 0,
			'errors'         => array(),
			'fixes'          => array(),
		);

		// Check if tables exist
		if ( ! self::tables_exist() ) {
			$create_results     = self::create_tables();
			$results['fixes'][] = 'Created missing database tables';
			++$results['tables_fixed'];
			return $results;
		}

		// Validate products table structure
		$products_table      = $wpdb->prefix . 'vortem_products';
		$products_validation = self::validate_table_structure( $products_table, 'products' );
		++$results['tables_checked'];

		if ( ! $products_validation['valid'] ) {
			$fix_result = self::fix_table_structure( $products_table, 'products' );
			if ( $fix_result['success'] ) {
				$results['fixes'][] = 'Fixed products table structure';
				++$results['tables_fixed'];
			} else {
				$results['errors'][] = 'Failed to fix products table: ' . $fix_result['message'];
			}
		}

		// Update database version
		update_option( 'vortem_db_version', self::DB_VERSION );

		return $results;
	}

	/**
	 * Validate table structure
	 *
	 * @param string $table_name
	 * @param string $table_type
	 * @return array
	 */
	private static function validate_table_structure( $table_name, $table_type ) {
		global $wpdb;

		// Only the plugin's own table is supported; bail otherwise so the query below uses a literal table name.
		if ( $table_name !== $wpdb->prefix . 'vortem_products' ) {
			return array(
				'valid'            => false,
				'missing_columns'  => array(),
				'existing_columns' => array(),
			);
		}

		$required_columns = array();

		if ( $table_type === 'products' ) {
			$required_columns = array(
				'id',
				'vortem_product_id',
				'woo_product_id',
				'name',
				'description',
				'sku',
				'price',
				'regular_price',
				'sale_price',
				'stock_quantity',
				'stock_status',
				'weight',
				'length',
				'width',
				'height',
				'category',
				'tags',
				'images',
				'attributes',
				'meta_data',
				'sync_status',
				'sync_date',
				'last_updated',
				'created_at',
			);
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DESCRIBE cannot use placeholders; table name is the plugin's own literal.
		$existing_columns = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}vortem_products" );

		$missing_columns = array_diff( $required_columns, $existing_columns );

		return array(
			'valid'            => empty( $missing_columns ),
			'missing_columns'  => $missing_columns,
			'existing_columns' => $existing_columns,
		);
	}

	/**
	 * Fix table structure by recreating it
	 *
	 * @param string $table_name
	 * @param string $table_type
	 * @return array
	 */
	private static function fix_table_structure( $table_name, $table_type ) {
		global $wpdb;

		// Only the plugin's own table is supported; bail otherwise so the queries below use literal table names.
		if ( $table_name !== $wpdb->prefix . 'vortem_products' ) {
			return array(
				'success' => false,
				'message' => 'Unsupported table',
			);
		}

		try {
			// Backup existing data
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading own table for backup before recreate.
			$existing_data = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vortem_products", ARRAY_A );

			// Drop and recreate table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema change is intentional during repair.
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vortem_products" );

			// Recreate using create_tables method
			$create_results = self::create_tables();

			// Restore data if it existed
			if ( ! empty( $existing_data ) && isset( $create_results[ $table_type ]['success'] ) && $create_results[ $table_type ]['success'] ) {
				foreach ( $existing_data as $row ) {
					$wpdb->insert( $table_name, $row );
				}
			}

			return array(
				'success'       => true,
				'message'       => 'Table structure fixed successfully',
				'rows_restored' => count( $existing_data ),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get database version
	 *
	 * @return string
	 */
	public static function get_db_version() {
		return get_option( 'vortem_db_version', '0.0.0' );
	}

	/**
	 * Check if database needs update
	 *
	 * @return bool
	 */
	public static function needs_update() {
		return version_compare( self::get_db_version(), self::DB_VERSION, '<' );
	}

	/**
	 * Update database tables
	 */
	public static function update_tables() {
		if ( self::needs_update() ) {
			self::create_tables();
		}
	}

	/**
	 * Get detailed table information
	 *
	 * @param string $table_name Table name (products, license)
	 * @return array|false Table information or false if not found
	 */
	public static function get_table_info( $table_name ) {
		global $wpdb;

		$table_map = array(
			'products' => $wpdb->prefix . 'vortem_products',
		);

		if ( ! isset( $table_map[ $table_name ] ) ) {
			return false;
		}

		$table = $table_map[ $table_name ];

		// Check if table exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( ! $exists ) {
			return false;
		}

		// Get table structure (only the plugin's own table is in $table_map, so use a literal name).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DESCRIBE cannot use placeholders; literal table name.
		$columns = $wpdb->get_results( "DESCRIBE {$wpdb->prefix}vortem_products", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW INDEX cannot use placeholders; literal table name.
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}vortem_products", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- COUNT(*) on the plugin's own table.
		$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vortem_products" );

		return array(
			'table_name' => $table,
			'exists'     => true,
			'columns'    => $columns,
			'indexes'    => $indexes,
			'row_count'  => intval( $row_count ),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata query, table name passed as a prepared string parameter.
			'engine'     => $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) ),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only metadata query, table name passed as a prepared string parameter.
			'charset'    => $wpdb->get_var( $wpdb->prepare( 'SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1', $table ) ),
		);
	}

	/**
	 * Get all tables information
	 *
	 * @return array All tables information
	 */
	public static function get_all_tables_info() {
		$tables = array( 'products' );
		$info   = array();

		foreach ( $tables as $table ) {
			$info[ $table ] = self::get_table_info( $table );
		}

		return $info;
	}

	/**
	 * Verify table structure matches expected schema
	 *
	 * @param string $table_name Table name
	 * @return array Verification results
	 */
	public static function verify_table_structure( $table_name ) {
		$table_info = self::get_table_info( $table_name );

		if ( ! $table_info ) {
			return array(
				'valid'   => false,
				'message' => "Table $table_name does not exist",
			);
		}

		$expected_columns = self::get_expected_columns( $table_name );
		$actual_columns   = array_column( $table_info['columns'], 'Field' );

		$missing_columns = array_diff( $expected_columns, $actual_columns );
		$extra_columns   = array_diff( $actual_columns, $expected_columns );

		$expected_indexes = self::get_expected_indexes( $table_name );
		$actual_indexes   = array_unique( array_column( $table_info['indexes'], 'Key_name' ) );

		$missing_indexes = array_diff( $expected_indexes, $actual_indexes );

		$is_valid = empty( $missing_columns ) && empty( $missing_indexes );

		return array(
			'valid'           => $is_valid,
			'table_name'      => $table_name,
			'missing_columns' => $missing_columns,
			'extra_columns'   => $extra_columns,
			'missing_indexes' => $missing_indexes,
			'row_count'       => $table_info['row_count'],
			'engine'          => $table_info['engine'],
			'charset'         => $table_info['charset'],
		);
	}

	/**
	 * Get expected columns for a table
	 *
	 * @param string $table_name Table name
	 * @return array Expected column names
	 */
	private static function get_expected_columns( $table_name ) {
		$expected = array(
			'products' => array(
				'id',
				'vortem_product_id',
				'woo_product_id',
				'name',
				'description',
				'sku',
				'price',
				'regular_price',
				'sale_price',
				'stock_quantity',
				'stock_status',
				'weight',
				'length',
				'width',
				'height',
				'category',
				'tags',
				'images',
				'attributes',
				'meta_data',
				'sync_status',
				'sync_date',
				'last_updated',
				'created_at',
			),
		);

		return isset( $expected[ $table_name ] ) ? $expected[ $table_name ] : array();
	}

	/**
	 * Get expected indexes for a table
	 *
	 * @param string $table_name Table name
	 * @return array Expected index names
	 */
	private static function get_expected_indexes( $table_name ) {
		$expected = array(
			'products' => array(
				'PRIMARY',
				'vortem_product_id',
				'woo_product_id',
				'sku',
				'sync_status',
				'sync_date',
				'created_at',
				'name',
				'price',
				'stock_status',
			),
		);

		return isset( $expected[ $table_name ] ) ? $expected[ $table_name ] : array();
	}

	/**
	 * Verify all tables structure
	 *
	 * @return array Verification results for all tables
	 */
	public static function verify_all_tables() {
		$tables  = array( 'products' );
		$results = array();

		foreach ( $tables as $table ) {
			$results[ $table ] = self::verify_table_structure( $table );
		}

		return $results;
	}

	/**
	 * Get table statistics
	 *
	 * @return array Table statistics
	 */
	public static function get_table_statistics() {
		global $wpdb;

		$stats = array();

		// Products table stats
		$products_table = $wpdb->prefix . 'vortem_products';
		if ( self::table_exists( $products_table ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $products_table from $wpdb->prefix
			$stats['products'] = array(
				'total'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$products_table}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- literal table name from $wpdb->prefix
				'synced'  => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE sync_status = %s", 'synced' ) ),
				'pending' => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE sync_status = %s", 'pending' ) ),
				'failed'  => $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$products_table} WHERE sync_status = %s", 'failed' ) ),
			);
		}

		return $stats;
	}

	/**
	 * Check if a specific table exists
	 *
	 * @param string $table_name Full table name
	 * @return bool
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check; $table_name from whitelist
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $result === $table_name;
	}

	/**
	 * Repair table if needed
	 *
	 * @param string $table_name Table name
	 * @return array Repair results
	 */
	public static function repair_table( $table_name ) {
		global $wpdb;

		$table_map = array(
			'products' => $wpdb->prefix . 'vortem_products',
		);

		if ( ! isset( $table_map[ $table_name ] ) ) {
			return array(
				'success' => false,
				'message' => "Unknown table: $table_name",
			);
		}

		$table = $table_map[ $table_name ];

		if ( ! self::table_exists( $table ) ) {
			return array(
				'success' => false,
				'message' => "Table $table does not exist",
			);
		}

		// Repair table (only the plugin's own table is in $table_map, so use a literal name).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- REPAIR TABLE cannot use placeholders; literal table name.
		$result = $wpdb->query( "REPAIR TABLE {$wpdb->prefix}vortem_products" );

		if ( $result === false ) {
			return array(
				'success' => false,
				'message' => "Failed to repair table $table",
			);
		}

		return array(
			'success' => true,
			'message' => "Table $table repaired successfully",
		);
	}

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
}
