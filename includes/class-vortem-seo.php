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

		$wc_product = $this->get_wc_product( $post_id );
		if ( $wc_product ) {
			$this->render_product_og_tags( $wc_product );
		}

		$twitter_card = $wc_product ? 'product' : 'summary_large_image';
		printf(
			"<meta name=\"twitter:card\" content=\"%s\" />\n",
			esc_attr( $twitter_card )
		);
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
		if ( $wc_product ) {
			$this->render_twitter_product_data( $wc_product );
		}

		if ( $wc_product ) {
			$this->render_product_jsonld( $wc_product, $title, $description, $canonical );
		}

		echo "<!-- / Vortem AI SEO -->\n";
	}

	/**
	 * Resolve a WooCommerce product object for the current post if applicable.
	 *
	 * @param int $post_id Post ID.
	 * @return WC_Product|null
	 */
	private function get_wc_product( $post_id ) {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return null;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}
		return $product;
	}

	/**
	 * Emit Open Graph product:* tags. Spec: https://ogp.me/#type_product
	 *
	 * @param WC_Product $product Product.
	 */
	private function render_product_og_tags( $product ) {
		$price = $product->get_price();
		if ( '' !== $price && null !== $price ) {
			printf(
				"<meta property=\"product:price:amount\" content=\"%s\" />\n",
				esc_attr( wc_format_decimal( $price, wc_get_price_decimals() ) )
			);
			printf(
				"<meta property=\"product:price:currency\" content=\"%s\" />\n",
				esc_attr( get_woocommerce_currency() )
			);
		}

		$availability_map = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'preorder',
		);
		$stock_status     = $product->get_stock_status();
		$availability     = isset( $availability_map[ $stock_status ] ) ? $availability_map[ $stock_status ] : 'in stock';
		printf(
			"<meta property=\"product:availability\" content=\"%s\" />\n",
			esc_attr( $availability )
		);

		printf(
			"<meta property=\"product:condition\" content=\"%s\" />\n",
			esc_attr( 'new' )
		);

		$sku = $product->get_sku();
		if ( '' !== $sku ) {
			printf(
				"<meta property=\"product:retailer_item_id\" content=\"%s\" />\n",
				esc_attr( $sku )
			);
		}

		$brand = $this->get_product_brand( $product );
		if ( '' !== $brand ) {
			printf(
				"<meta property=\"product:brand\" content=\"%s\" />\n",
				esc_attr( $brand )
			);
		}
	}

	/**
	 * Emit Twitter Product card data fields (label/data pairs).
	 *
	 * @param WC_Product $product Product.
	 */
	private function render_twitter_product_data( $product ) {
		$price = $product->get_price();
		if ( '' !== $price && null !== $price ) {
			$formatted = html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' );
			printf(
				"<meta name=\"twitter:label1\" content=\"%s\" />\n",
				esc_attr__( 'Price', 'vortem-ai' )
			);
			printf(
				"<meta name=\"twitter:data1\" content=\"%s\" />\n",
				esc_attr( $formatted )
			);
		}

		$availability_label_map = array(
			'instock'     => __( 'In stock', 'vortem-ai' ),
			'outofstock'  => __( 'Out of stock', 'vortem-ai' ),
			'onbackorder' => __( 'On backorder', 'vortem-ai' ),
		);
		$stock_status           = $product->get_stock_status();
		$availability_label     = isset( $availability_label_map[ $stock_status ] ) ? $availability_label_map[ $stock_status ] : __( 'In stock', 'vortem-ai' );
		printf(
			"<meta name=\"twitter:label2\" content=\"%s\" />\n",
			esc_attr__( 'Availability', 'vortem-ai' )
		);
		printf(
			"<meta name=\"twitter:data2\" content=\"%s\" />\n",
			esc_attr( $availability_label )
		);
	}

	/**
	 * Emit schema.org Product JSON-LD.
	 *
	 * @param WC_Product $product     Product.
	 * @param string     $title       Resolved title (custom or post title).
	 * @param string     $description Resolved meta description.
	 * @param string     $canonical   Canonical URL.
	 */
	private function render_product_jsonld( $product, $title, $description, $canonical ) {
		$availability_schema_map = array(
			'instock'     => 'https://schema.org/InStock',
			'outofstock'  => 'https://schema.org/OutOfStock',
			'onbackorder' => 'https://schema.org/PreOrder',
		);

		$images = $this->get_product_image_urls( $product );

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => '' !== $title ? $title : wp_strip_all_tags( $product->get_name() ),
			'description' => '' !== $description ? $description : wp_strip_all_tags( $product->get_short_description() ),
			'url'         => '' !== $canonical ? $canonical : get_permalink( $product->get_id() ),
		);

		if ( ! empty( $images ) ) {
			$data['image'] = 1 === count( $images ) ? $images[0] : $images;
		}

		$sku = $product->get_sku();
		if ( '' !== $sku ) {
			$data['sku'] = $sku;
		}

		$brand = $this->get_product_brand( $product );
		if ( '' !== $brand ) {
			$data['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$price = $product->get_price();
		if ( '' !== $price && null !== $price ) {
			$stock_status        = $product->get_stock_status();
			$availability_schema = isset( $availability_schema_map[ $stock_status ] ) ? $availability_schema_map[ $stock_status ] : 'https://schema.org/InStock';

			$data['offers'] = array(
				'@type'         => 'Offer',
				'price'         => wc_format_decimal( $price, wc_get_price_decimals() ),
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $availability_schema,
				'url'           => get_permalink( $product->get_id() ),
			);
		}

		if ( $product->get_rating_count() > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (int) $product->get_review_count(),
			);
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return;
		}

		// JSON-LD inside <script type="application/ld+json"> is not HTML; the
		// only character that could break out is '<', which we neutralize.
		$safe_json = str_replace( '<', '<', $json );
		wp_print_inline_script_tag( $safe_json, array( 'type' => 'application/ld+json' ) );
		echo "\n";
	}

	/**
	 * Resolve a brand label for a product.
	 *
	 * Checks the WooCommerce 6.4+ `product_brand` taxonomy first, then the
	 * common `pa_brand` attribute, then the site name as a final fallback.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_product_brand( $product ) {
		$taxonomies = array( 'product_brand', 'pa_brand' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $product->get_id(), $taxonomy );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return wp_strip_all_tags( $terms[0]->name );
			}
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Collect featured + gallery image URLs for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	private function get_product_image_urls( $product ) {
		$urls = array();

		$featured_id = $product->get_image_id();
		if ( $featured_id ) {
			$url = wp_get_attachment_image_url( $featured_id, 'full' );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		$gallery_ids = $product->get_gallery_image_ids();
		if ( is_array( $gallery_ids ) ) {
			foreach ( $gallery_ids as $gid ) {
				$url = wp_get_attachment_image_url( $gid, 'full' );
				if ( $url && ! in_array( $url, $urls, true ) ) {
					$urls[] = $url;
				}
			}
		}

		return $urls;
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
