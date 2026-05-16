<?php
/**
 * Vortem SEO
 *
 * Renders frontend SEO metadata (title, meta description, Open Graph,
 * Twitter card, canonical) on singular views from plugin-owned post meta.
 *
 * Replaces the prior dependency on Yoast SEO. Meta keys are vortem-prefixed
 * so they never collide with Yoast, RankMath, AIOSEO, or SEOPress storage.
 *
 * @package VortemAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem_SEO
 */
class Vortem_SEO {

	const META_TITLE     = '_vortem_seo_title';
	const META_DESC      = '_vortem_seo_metadesc';
	const META_FOCUSKW   = '_vortem_seo_focuskw';
	const META_OG_IMAGE  = '_vortem_seo_og_image';
	const META_CANONICAL = '_vortem_seo_canonical';

	/**
	 * Register hooks.
	 *
	 * Skipped entirely when another major SEO plugin is active so we never
	 * fight over the document title or duplicate tags in <head>.
	 */
	public function init() {
		if ( $this->another_seo_plugin_active() ) {
			return;
		}

		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 15 );
		add_filter( 'wp_title', array( $this, 'filter_wp_title' ), 15, 2 );
		add_action( 'wp_head', array( $this, 'render_head_tags' ), 1 );
	}

	/**
	 * Detect competing SEO plugins so we don't emit duplicate tags.
	 *
	 * @return bool
	 */
	private function another_seo_plugin_active() {
		return defined( 'WPSEO_VERSION' )            // Yoast SEO
			|| class_exists( 'WPSEO_Meta' )          // Yoast SEO (older).
			|| defined( 'RANK_MATH_VERSION' )        // Rank Math.
			|| defined( 'AIOSEO_VERSION' )           // All in One SEO.
			|| defined( 'SEOPRESS_VERSION' );        // SEOPress.
	}

	/**
	 * Override the document title on singular views when we have one.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		$custom = $this->get_singular_meta( self::META_TITLE );
		if ( '' === $custom ) {
			return $title;
		}
		return $this->expand_placeholders( $custom );
	}

	/**
	 * Legacy wp_title() filter for themes that still call it directly.
	 *
	 * @param string $title Current title.
	 * @param string $sep   Separator (unused).
	 * @return string
	 */
	public function filter_wp_title( $title, $sep = '' ) {
		unset( $sep );
		$custom = $this->get_singular_meta( self::META_TITLE );
		if ( '' === $custom ) {
			return $title;
		}
		return $this->expand_placeholders( $custom );
	}

	/**
	 * Emit meta description, Open Graph, Twitter card, and canonical tags.
	 */
	public function render_head_tags() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$description = $this->get_description( $post_id );
		$title       = $this->get_title_for_meta( $post_id );
		$canonical   = $this->get_canonical( $post_id );
		$og_type     = $this->guess_og_type( $post_id );
		$image_url   = $this->get_og_image( $post_id );
		$site_name   = get_bloginfo( 'name' );
		$locale      = str_replace( '_', '-', (string) get_locale() );

		echo "\n<!-- Vortem AI SEO -->\n";

		if ( '' !== $description ) {
			printf(
				"<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}

		if ( '' !== $canonical ) {
			printf(
				"<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( $canonical )
			);
		}

		if ( '' !== $title ) {
			printf(
				"<meta property=\"og:title\" content=\"%s\" />\n",
				esc_attr( $title )
			);
		}
		if ( '' !== $description ) {
			printf(
				"<meta property=\"og:description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}
		printf(
			"<meta property=\"og:type\" content=\"%s\" />\n",
			esc_attr( $og_type )
		);
		if ( '' !== $canonical ) {
			printf(
				"<meta property=\"og:url\" content=\"%s\" />\n",
				esc_url( $canonical )
			);
		}
		if ( '' !== $site_name ) {
			printf(
				"<meta property=\"og:site_name\" content=\"%s\" />\n",
				esc_attr( $site_name )
			);
		}
		if ( '' !== $locale ) {
			printf(
				"<meta property=\"og:locale\" content=\"%s\" />\n",
				esc_attr( $locale )
			);
		}
		if ( '' !== $image_url ) {
			printf(
				"<meta property=\"og:image\" content=\"%s\" />\n",
				esc_url( $image_url )
			);
		}

		echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
		if ( '' !== $title ) {
			printf(
				"<meta name=\"twitter:title\" content=\"%s\" />\n",
				esc_attr( $title )
			);
		}
		if ( '' !== $description ) {
			printf(
				"<meta name=\"twitter:description\" content=\"%s\" />\n",
				esc_attr( $description )
			);
		}
		if ( '' !== $image_url ) {
			printf(
				"<meta name=\"twitter:image\" content=\"%s\" />\n",
				esc_url( $image_url )
			);
		}

		echo "<!-- / Vortem AI SEO -->\n";
	}

	/**
	 * Read the custom SEO title for the current singular view.
	 *
	 * @param string $meta_key Meta key.
	 * @return string
	 */
	private function get_singular_meta( $meta_key ) {
		if ( ! is_singular() ) {
			return '';
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return '';
		}
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Title used in OG / Twitter tags. Falls back to the post title.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_title_for_meta( $post_id ) {
		$custom = get_post_meta( $post_id, self::META_TITLE, true );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			return $this->expand_placeholders( trim( $custom ) );
		}
		return wp_strip_all_tags( get_the_title( $post_id ) );
	}

	/**
	 * Meta description. Falls back to the post excerpt, then trimmed content.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_description( $post_id ) {
		$custom = get_post_meta( $post_id, self::META_DESC, true );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			return $this->expand_placeholders( trim( $custom ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$excerpt = (string) $post->post_excerpt;
		if ( '' !== trim( $excerpt ) ) {
			return wp_strip_all_tags( $excerpt );
		}

		$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$content = preg_replace( '/\s+/u', ' ', $content );
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}
		return mb_substr( $content, 0, 160 );
	}

	/**
	 * Canonical URL. Falls back to the post permalink.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_canonical( $post_id ) {
		$custom = get_post_meta( $post_id, self::META_CANONICAL, true );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			return esc_url_raw( trim( $custom ) );
		}
		$permalink = get_permalink( $post_id );
		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * OG image URL. Custom override, then featured image, then site icon.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_og_image( $post_id ) {
		$custom = get_post_meta( $post_id, self::META_OG_IMAGE, true );
		if ( is_string( $custom ) && '' !== trim( $custom ) ) {
			return esc_url_raw( trim( $custom ) );
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$thumb = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
			if ( $thumb ) {
				return $thumb;
			}
		}

		$icon = get_site_icon_url( 512 );
		return is_string( $icon ) ? $icon : '';
	}

	/**
	 * Choose an og:type appropriate for the post type.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function guess_og_type( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type ) {
			return 'product';
		}
		if ( 'post' === $post_type ) {
			return 'article';
		}
		return 'website';
	}

	/**
	 * Expand a small set of Yoast-style placeholders in saved templates so
	 * imported strings like "%%title%% - %%sitename%%" still resolve.
	 *
	 * @param string $value Raw template.
	 * @return string
	 */
	private function expand_placeholders( $value ) {
		if ( false === strpos( $value, '%%' ) ) {
			return $value;
		}

		$post_id  = is_singular() ? (int) get_queried_object_id() : 0;
		$title    = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : '';
		$sitename = (string) get_bloginfo( 'name' );
		$tagline  = (string) get_bloginfo( 'description' );
		$sep      = '-';

		$replacements = array(
			'%%title%%'        => $title,
			'%%sitename%%'     => $sitename,
			'%%site_name%%'    => $sitename,
			'%%sitedesc%%'     => $tagline,
			'%%page%%'         => '',
			'%%pagetotal%%'    => '',
			'%%pagenumber%%'   => '',
			'%%sep%%'          => $sep,
			'%%primary_category%%' => '',
			'%%excerpt%%'      => $post_id ? wp_strip_all_tags( get_the_excerpt( $post_id ) ) : '',
		);

		$out = strtr( $value, $replacements );
		$out = (string) preg_replace( '/\s{2,}/', ' ', $out );
		return trim( $out, " \t\n\r\0\x0B" . $sep );
	}
}
