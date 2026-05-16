<?php
/**
 * Plugin Inspector functionality for Vortem.ai Security page
 *
 * @package VortemAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Plugin Inspector Class
 */
class Vortem_Plugin_Inspector {

	/**
	 * Instance of this class.
	 *
	 * @var Vortem_Plugin_Inspector
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Vortem_Plugin_Inspector
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Hooks into admin_init to build plugin data cache (WordPress has already
	 * loaded get_plugins() natively at that point). REST API endpoints read
	 * from the transient cache — no manual wp-admin file includes needed.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'refresh_plugin_cache' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Refresh plugin data cache during admin_init.
	 *
	 * WordPress natively loads wp-admin/includes/plugin.php before admin_init
	 * fires, so get_plugins() is always available here. The result is cached
	 * in a transient so REST API endpoints can read it without needing to
	 * load any wp-admin files manually.
	 */
	public function refresh_plugin_cache() {
		if ( ! function_exists( 'get_plugins' ) ) {
			return;
		}

		$plugin_data = $this->build_plugin_data();
		set_transient( 'vortem_plugin_data', $plugin_data, HOUR_IN_SECONDS );
	}

	/**
	 * Get all plugin data.
	 *
	 * Returns cached plugin data when available (populated during admin_init).
	 * Falls back to direct query only when get_plugins() is already loaded.
	 *
	 * @return array Array of plugin data.
	 */
	public function get_plugin_data() {
		// Return cached data if available (set during admin_init).
		$cached = get_transient( 'vortem_plugin_data' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fallback: only works in admin context where get_plugins() is natively loaded.
		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		return $this->build_plugin_data();
	}

	/**
	 * Build plugin data array by calling get_plugins() directly.
	 *
	 * This method should only be called from admin_init context where
	 * WordPress has already natively loaded get_plugins().
	 *
	 * @return array Array of plugin data.
	 */
	private function build_plugin_data() {
		$all_plugins = get_plugins();
		$plugin_data = array();

		foreach ( $all_plugins as $plugin_file => $plugin_info ) {
			// get_plugins() returns keys relative to WP_PLUGIN_DIR (e.g. "akismet/akismet.php"),
			// so resolve against that, not this plugin's own directory.
			$plugin_path = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_file );

			// Validate that the path is within the plugins directory
			$real_plugin_dir  = realpath( WP_PLUGIN_DIR );
			$real_plugin_path = realpath( $plugin_path );
			if ( $real_plugin_path === false || $real_plugin_dir === false || strpos( $real_plugin_path, $real_plugin_dir ) !== 0 ) {
				continue; // Skip invalid paths
			}

			$is_active = is_plugin_active( $plugin_file );

			// Get RequiresWP - check multiple possible field names for compatibility
			$requires_wp = '';
			if ( isset( $plugin_info['RequiresWP'] ) && ! empty( $plugin_info['RequiresWP'] ) ) {
				$requires_wp = $plugin_info['RequiresWP'];
			} elseif ( isset( $plugin_info['Requires at least'] ) && ! empty( $plugin_info['Requires at least'] ) ) {
				$requires_wp = $plugin_info['Requires at least'];
			} elseif ( isset( $plugin_info['RequiresAtLeast'] ) && ! empty( $plugin_info['RequiresAtLeast'] ) ) {
				$requires_wp = $plugin_info['RequiresAtLeast'];
			}

			// Get RequiresPHP - check multiple possible field names for compatibility
			$requires_php = '';
			if ( isset( $plugin_info['RequiresPHP'] ) && ! empty( $plugin_info['RequiresPHP'] ) ) {
				$requires_php = $plugin_info['RequiresPHP'];
			} elseif ( isset( $plugin_info['Requires PHP'] ) && ! empty( $plugin_info['Requires PHP'] ) ) {
				$requires_php = $plugin_info['Requires PHP'];
			} elseif ( isset( $plugin_info['RequiresPHPVersion'] ) && ! empty( $plugin_info['RequiresPHPVersion'] ) ) {
				$requires_php = $plugin_info['RequiresPHPVersion'];
			}

			// Get last_modified from main plugin file
			$last_modified = '';
			if ( file_exists( $plugin_path ) ) {
				$last_modified = gmdate( 'c', filemtime( $plugin_path ) );
			}

			$data = array(
				'file'                => $plugin_file,
				'name'                => isset( $plugin_info['Name'] ) ? $plugin_info['Name'] : '',
				'version'             => isset( $plugin_info['Version'] ) ? $plugin_info['Version'] : '',
				'description'         => isset( $plugin_info['Description'] ) ? $plugin_info['Description'] : '',
				'author'              => isset( $plugin_info['Author'] ) ? $plugin_info['Author'] : '',
				'plugin_uri'          => isset( $plugin_info['PluginURI'] ) ? $plugin_info['PluginURI'] : '',
				'status'              => $is_active ? 'active' : 'inactive',
				'last_modified'       => $last_modified,
				'requires_wp_version' => $requires_wp,
				'requires_php'        => $requires_php,
			);

			$plugin_data[] = $data;
		}

		// Sort by name by default.
		usort(
			$plugin_data,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $plugin_data;
	}

	/**
	 * Get all theme data.
	 *
	 * @return array Array of theme data.
	 */
	public function get_theme_data() {
		// Get ALL themes - wp_get_themes() returns all installed themes
		// Note: wp_get_themes() is always available in WordPress core (wp-includes/theme.php)
		$all_themes    = wp_get_themes();
		$current_theme = wp_get_theme();
		$theme_data    = array();

		// Log total themes found for debugging

		foreach ( $all_themes as $stylesheet => $theme ) {
			$is_active = ( $stylesheet === $current_theme->get_stylesheet() );

			// Get theme name for logging
			$theme_name = $theme->get( 'Name' );
			if ( empty( $theme_name ) ) {
				$theme_name = $stylesheet; // Fallback to stylesheet if name is empty
			}

			// Get tags and ensure they are in array format (exactly as API example)
			$tags = $theme->get( 'Tags' );
			if ( is_string( $tags ) ) {
				// Convert comma-separated string to array
				$tags = array_map( 'trim', explode( ',', $tags ) );
				$tags = array_filter( $tags ); // Remove empty values
				$tags = array_values( $tags ); // Ensure clean array indices
			} elseif ( ! is_array( $tags ) ) {
				$tags = array();
			} else {
				// Ensure clean array indices for existing arrays
				$tags = array_values( $tags );
			}

			// Get template value
			$template_value = $theme->get_template();

			// Get theme name
			$theme_name_value = $theme->get( 'Name' );

			// Format values according to API requirements
			// API example shows: stylesheet="twentytwentyfour" (lowercase, original WordPress value)
			// API example shows: template="twentytwentyfour" (lowercase, original WordPress value)
			// API example shows: name="Twenty Twenty-Four" (preserves spaces and capitalization)
			$formatted_stylesheet = $stylesheet !== null && $stylesheet !== false ? strtolower( (string) $stylesheet ) : '';
			$formatted_template   = $template_value !== null && $template_value !== false ? strtolower( (string) $template_value ) : '';
			$formatted_name       = $theme_name_value !== null && $theme_name_value !== false ? (string) $theme_name_value : '';

			// Ensure all fields are properly formatted - ALL fields must be present (exact format from API example)
			// Field order: stylesheet, template, name, version, status, author, author_uri, theme_uri, description, text_domain, tags
			// IMPORTANT: Include ALL themes, even if some fields are empty
			$data = array(
				'stylesheet'  => $formatted_stylesheet,
				'template'    => $formatted_template,
				'name'        => $formatted_name,
				'version'     => $theme->get( 'Version' ) !== null && $theme->get( 'Version' ) !== false ? (string) $theme->get( 'Version' ) : '',
				'status'      => $is_active ? 'active' : 'inactive',
				'author'      => $theme->get( 'Author' ) !== null && $theme->get( 'Author' ) !== false ? (string) $theme->get( 'Author' ) : '',
				'author_uri'  => $theme->get( 'AuthorURI' ) !== null && $theme->get( 'AuthorURI' ) !== false ? (string) $theme->get( 'AuthorURI' ) : '',
				'theme_uri'   => $theme->get( 'ThemeURI' ) !== null && $theme->get( 'ThemeURI' ) !== false ? (string) $theme->get( 'ThemeURI' ) : '',
				'description' => $theme->get( 'Description' ) !== null && $theme->get( 'Description' ) !== false ? (string) $theme->get( 'Description' ) : '',
				'text_domain' => $theme->get( 'TextDomain' ) !== null && $theme->get( 'TextDomain' ) !== false ? (string) $theme->get( 'TextDomain' ) : '',
				'tags'        => is_array( $tags ) ? $tags : array(),
			);

			// Log each theme being added (especially for debugging missing themes)

			$theme_data[] = $data;
		}

		// Sort by name by default.
		usort(
			$theme_data,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $theme_data;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'vortem/v1',
			'/security/plugins',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_plugins' ),
				'permission_callback' => array( $this, 'rest_check_permissions' ),
			)
		);
	}

	/**
	 * Check REST API permissions.
	 *
	 * @return bool Whether user has permission.
	 */
	public function rest_check_permissions() {
		return vortem_current_user_can_manage();
	}

	/**
	 * REST API callback to get plugins.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function rest_get_plugins( $request ) {
		$plugins = $this->get_plugin_data();
		return new WP_REST_Response( $plugins, 200 );
	}
}
