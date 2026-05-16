<?php
/**
 * WP-CLI Commands for Vortem Database
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load if WP-CLI is available
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Vortem Database WP-CLI Commands
 */
class Vortem_Database_CLI {

	/**
	 * Show database table information
	 *
	 * ## OPTIONS
	 *
	 * [<table>]
	 * : Specific table name (products, orders). If not provided, shows all tables.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db info
	 *     wp vortem db info products
	 *     wp vortem db info --format=json
	 *
	 * @when after_wp_load
	 */
	public function info( $args, $assoc_args ) {
		$table  = isset( $args[0] ) ? $args[0] : null;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( $table ) {
			$info = Vortem_Database::get_table_info( $table );

			if ( ! $info ) {
				WP_CLI::error( "Table '$table' not found or does not exist." );
			}

			$this->display_table_info( $info, $format );
		} else {
			$all_info = Vortem_Database::get_all_tables_info();
			$this->display_all_tables_info( $all_info, $format );
		}
	}

	/**
	 * Verify database table structure
	 *
	 * ## OPTIONS
	 *
	 * [<table>]
	 * : Specific table name (products, orders). If not provided, verifies all tables.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db verify
	 *     wp vortem db verify products
	 *     wp vortem db verify --format=json
	 *
	 * @when after_wp_load
	 */
	public function verify( $args, $assoc_args ) {
		$table  = isset( $args[0] ) ? $args[0] : null;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( $table ) {
			$verification = Vortem_Database::verify_table_structure( $table );
			$this->display_verification_results( array( $table => $verification ), $format );
		} else {
			$verification = Vortem_Database::verify_all_tables();
			$this->display_verification_results( $verification, $format );
		}
	}

	/**
	 * Show database statistics
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db stats
	 *     wp vortem db stats --format=json
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$stats  = Vortem_Database::get_table_statistics();

		$this->display_statistics( $stats, $format );
	}

	/**
	 * Create database tables
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Force recreation of tables even if they exist
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db create
	 *     wp vortem db create --force
	 *
	 * @when after_wp_load
	 */
	public function create( $args, $assoc_args ) {
		$force = isset( $assoc_args['force'] ) ? $assoc_args['force'] : false;

		if ( ! $force && Vortem_Database::tables_exist() ) {
			WP_CLI::warning( 'Tables already exist. Use --force to recreate them.' );
			return;
		}

		WP_CLI::log( 'Creating Vortem database tables...' );

		$results = Vortem_Database::create_tables();

		$success_count = 0;
		$error_count   = 0;

		foreach ( $results as $table => $result ) {
			if ( $result['success'] ) {
				WP_CLI::success( $result['message'] );
				++$success_count;
			} else {
				WP_CLI::error( $result['message'] );
				++$error_count;
			}
		}

		if ( $error_count === 0 ) {
			WP_CLI::success( 'All tables created successfully!' );
		} else {
			WP_CLI::error( "$error_count table(s) failed to create." );
		}
	}

	/**
	 * Drop database tables
	 *
	 * ## OPTIONS
	 *
	 * [--confirm]
	 * : Confirm that you want to drop all tables
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db drop --confirm
	 *
	 * @when after_wp_load
	 */
	public function drop( $args, $assoc_args ) {
		$confirm = isset( $assoc_args['confirm'] ) ? $assoc_args['confirm'] : false;

		if ( ! $confirm ) {
			WP_CLI::error( 'This will permanently delete all Vortem tables and data. Use --confirm to proceed.' );
		}

		WP_CLI::log( 'Dropping Vortem database tables...' );

		Vortem_Database::drop_tables();

		WP_CLI::success( 'All Vortem tables dropped successfully!' );
	}

	/**
	 * Repair database tables
	 *
	 * ## OPTIONS
	 *
	 * [<table>]
	 * : Specific table name (products, orders). If not provided, repairs all tables.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vortem db repair
	 *     wp vortem db repair products
	 *
	 * @when after_wp_load
	 */
	public function repair( $args, $assoc_args ) {
		$table = isset( $args[0] ) ? $args[0] : null;

		if ( $table ) {
			$result = Vortem_Database::repair_table( $table );

			if ( $result['success'] ) {
				WP_CLI::success( $result['message'] );
			} else {
				WP_CLI::error( $result['message'] );
			}
		} else {
			$tables        = array( 'products', 'orders' );
			$success_count = 0;
			$error_count   = 0;

			foreach ( $tables as $table_name ) {
				$result = Vortem_Database::repair_table( $table_name );

				if ( $result['success'] ) {
					WP_CLI::success( $result['message'] );
					++$success_count;
				} else {
					WP_CLI::error( $result['message'] );
					++$error_count;
				}
			}

			if ( $error_count === 0 ) {
				WP_CLI::success( 'All tables repaired successfully!' );
			} else {
				WP_CLI::error( "$error_count table(s) failed to repair." );
			}
		}
	}

	/**
	 * Display table information
	 *
	 * @param array  $info Table information
	 * @param string $format Output format
	 */
	private function display_table_info( $info, $format ) {
		$data = array(
			array( 'Property', 'Value' ),
			array( 'Table Name', $info['table_name'] ),
			array( 'Exists', $info['exists'] ? 'Yes' : 'No' ),
			array( 'Row Count', $info['row_count'] ),
			array( 'Engine', $info['engine'] ),
			array( 'Charset', $info['charset'] ),
			array( 'Columns', count( $info['columns'] ) ),
			array( 'Indexes', count( $info['indexes'] ) ),
		);

		WP_CLI\Utils\format_items( $format, $data, array( 'Property', 'Value' ) );
	}

	/**
	 * Display all tables information
	 *
	 * @param array  $all_info All tables information
	 * @param string $format Output format
	 */
	private function display_all_tables_info( $all_info, $format ) {
		$data = array();

		foreach ( $all_info as $table_name => $info ) {
			if ( $info ) {
				$data[] = array(
					'Table'   => $table_name,
					'Exists'  => $info['exists'] ? 'Yes' : 'No',
					'Rows'    => $info['row_count'],
					'Engine'  => $info['engine'],
					'Charset' => $info['charset'],
					'Columns' => count( $info['columns'] ),
					'Indexes' => count( $info['indexes'] ),
				);
			} else {
				$data[] = array(
					'Table'   => $table_name,
					'Exists'  => 'No',
					'Rows'    => 'N/A',
					'Engine'  => 'N/A',
					'Charset' => 'N/A',
					'Columns' => 'N/A',
					'Indexes' => 'N/A',
				);
			}
		}

		WP_CLI\Utils\format_items( $format, $data, array( 'Table', 'Exists', 'Rows', 'Engine', 'Charset', 'Columns', 'Indexes' ) );
	}

	/**
	 * Display verification results
	 *
	 * @param array  $verification Verification results
	 * @param string $format Output format
	 */
	private function display_verification_results( $verification, $format ) {
		$data = array();

		foreach ( $verification as $table_name => $result ) {
			$data[] = array(
				'Table'           => $table_name,
				'Valid'           => $result['valid'] ? 'Yes' : 'No',
				'Missing Columns' => implode( ', ', $result['missing_columns'] ),
				'Missing Indexes' => implode( ', ', $result['missing_indexes'] ),
				'Row Count'       => $result['row_count'],
				'Engine'          => $result['engine'],
				'Charset'         => $result['charset'],
			);
		}

		WP_CLI\Utils\format_items( $format, $data, array( 'Table', 'Valid', 'Missing Columns', 'Missing Indexes', 'Row Count', 'Engine', 'Charset' ) );
	}

	/**
	 * Display statistics
	 *
	 * @param array  $stats Statistics
	 * @param string $format Output format
	 */
	private function display_statistics( $stats, $format ) {
		$data = array();

		foreach ( $stats as $table_name => $table_stats ) {
			foreach ( $table_stats as $stat_name => $stat_value ) {
				$data[] = array(
					'Table'     => $table_name,
					'Statistic' => ucfirst( $stat_name ),
					'Value'     => $stat_value,
				);
			}
		}

		WP_CLI\Utils\format_items( $format, $data, array( 'Table', 'Statistic', 'Value' ) );
	}
}

// Register WP-CLI commands
WP_CLI::add_command( 'vortem db', 'Vortem_Database_CLI' );
