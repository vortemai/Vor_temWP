<?php
/**
 * Vortem Internationalization Helper
 *
 * Thin compatibility shims for the legacy custom translation system. The plugin
 * now uses the standard WordPress gettext pipeline (load_plugin_textdomain +
 * .po/.mo files in /languages); these helpers simply forward to `__()` and
 * friends with the `vortem-ai` text domain so existing call sites keep working.
 *
 * New code should call `__()`, `esc_html__()`, `esc_attr__()`, `_e()`, `_x()`,
 * `_n()` directly — translation tooling (translate.wordpress.org, WP-CLI i18n)
 * cannot extract strings hidden behind these wrappers.
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get translated string.
 *
 * @param string $text    Source/English text (used as the gettext msgid).
 * @param string $default Optional fallback text. If provided, it is used as the
 *                        msgid; the original $text is treated as a legacy
 *                        translation key and ignored for lookup.
 * @return string
 */
function vortem_translate( $text, $default = '' ) {
	$msgid = ! empty( $default ) ? $default : $text;
	// Variable msgid is unavoidable here because legacy call sites pass dynamic
	// values; this wrapper exists only to keep them functional during the
	// transition to direct __() calls.
    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain
	return __( $msgid, 'vortem-ai' );
}

/**
 * Echo translated, escaped string.
 *
 * @param string $text   Source/English text.
 * @param string $domain Ignored — kept for backwards compatibility with legacy
 *                       call sites that passed a fallback string here.
 */
function vortem_translate_e( $text, $domain = 'vortem-ai' ) {
	echo esc_html( vortem_translate( $text, $domain ) );
}

/**
 * Get translated string with context.
 *
 * NOTE: The standard `_x()` requires a literal context. Legacy call sites pass
 * dynamic context values, so we route through the no-context translator.
 *
 * @param string $text    Source/English text.
 * @param string $context Translation context (unused).
 * @param string $domain  Ignored — see vortem_translate_e().
 * @return string
 */
function vortem_translate_x( $text, $context, $domain = 'vortem-ai' ) {
	return vortem_translate( $text, $domain );
}

/**
 * Get translated string with number (singular/plural switch).
 *
 * @param string $single Single form.
 * @param string $plural Plural form.
 * @param int    $number Count.
 * @param string $domain Ignored — see vortem_translate_e().
 * @return string
 */
function vortem_translate_n( $single, $plural, $number, $domain = 'vortem-ai' ) {
	$text = ( (int) $number === 1 ) ? $single : $plural;
	return vortem_translate( $text, $domain );
}

/**
 * Translate a format string and apply sprintf arguments.
 *
 * @param string $format Format string.
 * @param mixed  ...$args Sprintf arguments.
 * @return string
 */
function vortem_translate_f( $format, ...$args ) {
	$translated_format = vortem_translate( $format );
	return sprintf( $translated_format, ...$args );
}

/**
 * Translate license / order status values.
 *
 * Uses string literals so WordPress i18n tooling can extract the strings.
 *
 * @param string $status Status value to translate.
 * @return string Translated status.
 */
function vortem_translate_status( $status ) {
	$status_lower = strtolower( $status );

	$status_translations = array(
		'active'     => __( 'Active', 'vortem-ai' ),
		'inactive'   => __( 'Inactive', 'vortem-ai' ),
		'expired'    => __( 'Expired', 'vortem-ai' ),
		'suspended'  => __( 'Suspended', 'vortem-ai' ),
		'pending'    => __( 'Pending', 'vortem-ai' ),
		'cancelled'  => __( 'Cancelled', 'vortem-ai' ),
		'valid'      => __( 'Valid', 'vortem-ai' ),
		'invalid'    => __( 'Invalid', 'vortem-ai' ),
		'trial'      => __( 'Trial', 'vortem-ai' ),
		'error'      => __( 'Error', 'vortem-ai' ),
		'n/a'        => __( 'N/A', 'vortem-ai' ),
		// WooCommerce order statuses
		'processing' => __( 'Processing', 'vortem-ai' ),
		'completed'  => __( 'Completed', 'vortem-ai' ),
		'on-hold'    => __( 'On Hold', 'vortem-ai' ),
		'refunded'   => __( 'Refunded', 'vortem-ai' ),
		'failed'     => __( 'Failed', 'vortem-ai' ),
	);

	if ( isset( $status_translations[ $status_lower ] ) ) {
		return $status_translations[ $status_lower ];
	}

	// Return original with proper capitalization if no translation found.
	return ucfirst( $status );
}
