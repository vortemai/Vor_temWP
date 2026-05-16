<?php
/**
 * Vortem Autoloader Class
 *
 * Handles automatic loading of plugin classes
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Autoloader
 */
class Vortem_Autoloader {

	/**
	 * Class map for manual mapping
	 *
	 * @var array
	 */
	private static $class_map = array(
		'Vortem_Config'              => 'includes/class-vortem-config.php',
		'Vortem_Database'            => 'includes/class-vortem-database.php',
		'Vortem_Database_CLI'        => 'includes/class-vortem-database-cli.php',
		'Vortem_Api_Client'          => 'includes/class-vortem-api-client.php',
		'Vortem_Email_Marketing_Api' => 'includes/class-vortem-email-marketing-api.php',
		'Vortem_Product_Manager'     => 'includes/class-vortem-product-manager.php',
		'Vortem_Order_Manager'       => 'includes/class-vortem-order-manager.php',
		'Vortem_Security'            => 'includes/class-vortem-security.php',
		'Vortem_Admin'               => 'admin/class-vortem-admin.php',
	);

	/**
	 * Initialize autoloader
	 */
	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes
	 *
	 * @param string $class_name Class name to load
	 */
	public static function autoload( $class_name ) {
		// Only handle Vortem classes
		if ( strpos( $class_name, 'Vortem_' ) !== 0 ) {
			return;
		}

		// Check class map first
		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file_path = VORTEM_PLUGIN_DIR . self::$class_map[ $class_name ];
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return;
			}
		}

		// Convert class name to file name
		$file_name = self::class_to_filename( $class_name );

		// Try different locations
		$locations = array(
			'includes/',
			'admin/',
			'includes/managers/',
			'includes/api/',
		);

		foreach ( $locations as $location ) {
			$file_path = VORTEM_PLUGIN_DIR . $location . $file_name;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return;
			}
		}
	}

	/**
	 * Convert class name to file name
	 *
	 * @param string $class_name Class name
	 * @return string File name
	 */
	private static function class_to_filename( $class_name ) {
		// Convert Vortem_Class_Name to class-vortem-class-name.php
		$file_name = strtolower( $class_name );
		$file_name = str_replace( '_', '-', $file_name );
		$file_name = 'class-' . $file_name . '.php';

		return $file_name;
	}

	/**
	 * Add class to class map
	 *
	 * @param string $class_name Class name
	 * @param string $file_path File path relative to plugin directory
	 */
	public static function add_class( $class_name, $file_path ) {
		self::$class_map[ $class_name ] = $file_path;
	}

	/**
	 * Get class map
	 *
	 * @return array Class map
	 */
	public static function get_class_map() {
		return self::$class_map;
	}
}
