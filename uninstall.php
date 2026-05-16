<?php
/**
 * Uninstall handler for Vortem AI.
 *
 * Called by WordPress when the plugin is deleted via the admin Plugins
 * screen. Removes every option, transient, and user meta the plugin
 * creates. Leaves WooCommerce / WordPress core options untouched.
 *
 * Runs in a stripped-down context: the plugin's classes are NOT loaded
 * here, so this file only relies on WordPress core globals/functions.
 *
 * @package VortemAI
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Sweep every option whose key begins with the plugin prefix. Cheaper and
// more correct than a hand-maintained list, which has drifted before.
if ( isset( $wpdb->options ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'vortem_' ) . '%'
		)
	);

	// Same sweep for transients (vortem_* and any cached variants).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_' . $wpdb->esc_like( 'vortem_' ) . '%',
			'_transient_timeout_' . $wpdb->esc_like( 'vortem_' ) . '%'
		)
	);
}

// Sweep user meta the plugin attached.
if ( isset( $wpdb->usermeta ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'vortem_' ) . '%'
		)
	);
}

// Unregister settings related to the wizard/settings page.
if ( function_exists( 'unregister_setting' ) ) {
	unregister_setting( 'vortem_settings', 'vortem_products_per_page' );
}

// Unschedule any pending Action Scheduler jobs the plugin may have queued.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'vortem' );
}
