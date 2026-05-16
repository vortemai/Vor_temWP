<?php
/**
 * Vortem SEO Meta Box
 *
 * Adds an editable Vortem SEO panel to the WooCommerce product edit screen.
 * Lets the merchant inspect or override the imported SEO fields and re-fetch
 * fresh content from the c.vortem.ai SEO endpoint for a single product.
 *
 * @package VortemAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem_SEO_Meta_Box
 */
class Vortem_SEO_Meta_Box {

	const NONCE_ACTION             = 'vortem_seo_meta_save';
	const NONCE_FIELD              = 'vortem_seo_nonce';
	const REGEN_NONCE_ACTION       = 'vortem_regenerate_seo';
	const READABILITY_NONCE_ACTION = 'vortem_seo_readability';

	/**
	 * Wire up hooks.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_vortem_regenerate_seo', array( $this, 'ajax_regenerate' ) );
		add_action( 'wp_ajax_vortem_seo_readability', array( $this, 'ajax_readability' ) );
	}

	/**
	 * Register the meta box on the product edit screen.
	 */
	public function register_meta_box() {
		add_meta_box(
			'vortem-seo',
			__( 'Vortem SEO', 'vortem-ai' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box body.
	 *
	 * @param WP_Post $post Current product post.
	 */
	public function render_meta_box( $post ) {
		$post_id          = (int) $post->ID;
		$values           = array(
			'title'     => (string) get_post_meta( $post_id, Vortem_SEO::META_TITLE, true ),
			'metadesc'  => (string) get_post_meta( $post_id, Vortem_SEO::META_DESC, true ),
			'focuskw'   => (string) get_post_meta( $post_id, Vortem_SEO::META_FOCUSKW, true ),
			'og_image'  => (string) get_post_meta( $post_id, Vortem_SEO::META_OG_IMAGE, true ),
			'canonical' => (string) get_post_meta( $post_id, Vortem_SEO::META_CANONICAL, true ),
		);
		$vortem_remote_id = (string) get_post_meta( $post_id, '_vortem_product_id', true );
		$preview_url      = get_permalink( $post_id );
		$site_name        = get_bloginfo( 'name' );
		$readability      = Vortem_SEO_Readability::analyze( (string) $post->post_content );

		include VORTEM_PLUGIN_DIR . 'admin/partials/vortem-seo-meta-box.php';
	}

	/**
	 * Persist meta box values on product save.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( $post && 'product' !== $post->post_type ) {
			return;
		}

		$fields = array(
			'vortem_seo_title'     => array( Vortem_SEO::META_TITLE, 'text' ),
			'vortem_seo_metadesc'  => array( Vortem_SEO::META_DESC, 'textarea' ),
			'vortem_seo_focuskw'   => array( Vortem_SEO::META_FOCUSKW, 'text' ),
			'vortem_seo_og_image'  => array( Vortem_SEO::META_OG_IMAGE, 'url' ),
			'vortem_seo_canonical' => array( Vortem_SEO::META_CANONICAL, 'url' ),
		);

		foreach ( $fields as $input_name => $config ) {
			list( $meta_key, $type ) = $config;

			if ( ! isset( $_POST[ $input_name ] ) ) {
				$clean = '';
			} elseif ( 'url' === $type ) {
				$clean = esc_url_raw( wp_unslash( $_POST[ $input_name ] ) );
			} elseif ( 'textarea' === $type ) {
				$clean = sanitize_textarea_field( wp_unslash( $_POST[ $input_name ] ) );
			} else {
				$clean = sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) );
			}

			if ( '' === $clean ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $clean );
			}
		}
	}

	/**
	 * Enqueue inline JS for the regen button + snippet preview, on the
	 * product editor only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_register_script( 'vortem-seo-meta-box', '', array( 'jquery' ), VORTEM_VERSION, true );
		wp_enqueue_script( 'vortem-seo-meta-box' );
		wp_localize_script(
			'vortem-seo-meta-box',
			'vortemSeoMetaBox',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( self::REGEN_NONCE_ACTION ),
				'readabilityNonce' => wp_create_nonce( self::READABILITY_NONCE_ACTION ),
				'i18n'             => array(
					'regenerating'       => __( 'Regenerating…', 'vortem-ai' ),
					'regenerate'         => __( 'Regenerate from Vortem', 'vortem-ai' ),
					'success'            => __( 'SEO fields refreshed from Vortem.', 'vortem-ai' ),
					'failure'            => __( 'Could not refresh SEO fields:', 'vortem-ai' ),
					'analyzing'          => __( 'Analyzing…', 'vortem-ai' ),
					'recalculate'        => __( 'Recalculate from current editor', 'vortem-ai' ),
					'readabilityFailure' => __( 'Could not analyze readability:', 'vortem-ai' ),
				),
			)
		);
		wp_add_inline_script( 'vortem-seo-meta-box', $this->inline_script() );

		wp_register_style( 'vortem-seo-meta-box', false, array(), VORTEM_VERSION );
		wp_enqueue_style( 'vortem-seo-meta-box' );
		wp_add_inline_style( 'vortem-seo-meta-box', $this->inline_style() );
	}

	/**
	 * AJAX handler: re-fetch SEO content for the current product.
	 *
	 * Updates only the four Vortem meta keys; never overwrites the product
	 * name, description, or tags (the import path owns those).
	 */
	public function ajax_regenerate() {
		if ( ! check_ajax_referer( self::REGEN_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please reload the page.', 'vortem-ai' ) ),
				400
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $post_id <= 0 || 'product' !== get_post_type( $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid product.', 'vortem-ai' ) ),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to edit this product.', 'vortem-ai' ) ),
				403
			);
		}

		$vortem_remote_id = (string) get_post_meta( $post_id, '_vortem_product_id', true );
		if ( '' === $vortem_remote_id ) {
			wp_send_json_error(
				array( 'message' => __( 'This product was not imported from Vortem (no remote product ID stored).', 'vortem-ai' ) ),
				400
			);
		}

		if ( ! Vortem_Api_Client::has_consent() ) {
			$err = Vortem_Api_Client::consent_required_error();
			wp_send_json_error(
				array( 'message' => $err->get_error_message() ),
				403
			);
		}

		$api      = new Vortem_Api_Client();
		$seo_data = $api->get_product_seo_content( $vortem_remote_id );

		if ( is_wp_error( $seo_data ) ) {
			wp_send_json_error(
				array( 'message' => $seo_data->get_error_message() ),
				502
			);
		}

		if ( ! is_array( $seo_data ) || empty( $seo_data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No SEO data received from Vortem.', 'vortem-ai' ) ),
				502
			);
		}

		$updated = array();

		if ( ! empty( $seo_data['keyphrase'] ) ) {
			$value = sanitize_text_field( $seo_data['keyphrase'] );
			update_post_meta( $post_id, Vortem_SEO::META_FOCUSKW, $value );
			$updated['focuskw'] = $value;
		}

		if ( ! empty( $seo_data['meta_description'] ) ) {
			$value = sanitize_textarea_field( $seo_data['meta_description'] );
			update_post_meta( $post_id, Vortem_SEO::META_DESC, $value );
			$updated['metadesc'] = $value;
		}

		$meta_title = '';
		if ( ! empty( $seo_data['seo_meta_title'] ) ) {
			$meta_title = $seo_data['seo_meta_title'];
		} elseif ( ! empty( $seo_data['yoast_seo_title'] ) ) {
			$meta_title = $seo_data['yoast_seo_title'];
		}
		if ( '' !== $meta_title ) {
			$value = sanitize_text_field( $meta_title );
			update_post_meta( $post_id, Vortem_SEO::META_TITLE, $value );
			$updated['title'] = $value;
		}

		wp_send_json_success(
			array(
				'message' => __( 'SEO fields refreshed.', 'vortem-ai' ),
				'fields'  => $updated,
			)
		);
	}

	/**
	 * AJAX handler: recompute readability from the editor's current content.
	 *
	 * Reads HTML from the request and returns the full Vortem_SEO_Readability
	 * analysis. No data is stored — purely a read-only computation.
	 */
	public function ajax_readability() {
		if ( ! check_ajax_referer( self::READABILITY_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please reload the page.', 'vortem-ai' ) ),
				400
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to analyze this product.', 'vortem-ai' ) ),
				403
			);
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$result  = Vortem_SEO_Readability::analyze( $content );
		wp_send_json_success( $result );
	}

	/**
	 * Inline JS body. Kept as a heredoc string so wp_add_inline_script can
	 * attach it to the registered handle (no separate JS file ships).
	 *
	 * @return string
	 */
	private function inline_script() {
		return <<<'JS'
(function ($) {
    'use strict';

    function updatePreview() {
        var title = $('#vortem_seo_title').val() || $('#title').val() || '';
        var desc  = $('#vortem_seo_metadesc').val() || '';
        $('#vortem-seo-preview-title').text(title);
        $('#vortem-seo-preview-desc').text(desc);
    }

    $(document).on('input change', '#vortem_seo_title, #vortem_seo_metadesc, #title', updatePreview);
    $(updatePreview);

    $(document).on('click', '.vortem-seo-tab', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var target = $btn.data('tab');
        $('.vortem-seo-tab').removeClass('is-active').attr('aria-selected', 'false');
        $btn.addClass('is-active').attr('aria-selected', 'true');
        $('.vortem-seo-tab-panel').attr('hidden', 'hidden');
        $('#vortem-seo-panel-' + target).removeAttr('hidden');
    });

    function readEditorContent() {
        if (typeof window.wp !== 'undefined' && wp.data && wp.data.select) {
            try {
                var editor = wp.data.select('core/editor');
                if (editor && typeof editor.getEditedPostContent === 'function') {
                    var blockContent = editor.getEditedPostContent();
                    if (blockContent) {
                        return blockContent;
                    }
                }
            } catch (err) { /* fall through */ }
        }
        if (typeof window.tinymce !== 'undefined') {
            var ed = window.tinymce.get('content');
            if (ed && !ed.isHidden()) {
                return ed.getContent();
            }
        }
        var $ta = $('#content');
        if ($ta.length) {
            return $ta.val();
        }
        return '';
    }

    function renderReadability(data) {
        var $panel = $('#vortem-seo-readability');
        if (!$panel.length || !data) {
            return;
        }
        $panel.find('.vortem-seo-readability-badge')
            .text(data.label)
            .css('background-color', data.color);
        $panel.find('.vortem-seo-readability-score').text(data.score);
        $panel.find('.vortem-seo-readability-advice').text(data.advice);
        $panel.find('[data-stat="words"]').text(data.words);
        $panel.find('[data-stat="sentences"]').text(data.sentences);
        $panel.find('[data-stat="syllables"]').text(data.syllables);
        $panel.find('[data-stat="avg_sentence_length"]').text(data.avg_sentence_length);
        $panel.find('[data-stat="avg_syllables_per_word"]').text(data.avg_syllables_per_word);

        var $checks = $('#vortem-seo-readability-checks');
        if ($checks.length && Array.isArray(data.checks)) {
            $checks.empty();
            data.checks.forEach(function (chk) {
                var $li = $('<li/>', {
                    'class': 'vortem-seo-check vortem-seo-check--' + chk.status,
                    'data-check-id': chk.id
                });
                $li.append($('<span/>', { 'class': 'vortem-seo-check-dot', 'aria-hidden': 'true' }));
                var $body = $('<span/>', { 'class': 'vortem-seo-check-body' });
                $body.append($('<strong/>', { 'class': 'vortem-seo-check-label', text: chk.label + ':' }));
                $body.append(' ');
                $body.append($('<span/>', { 'class': 'vortem-seo-check-detail', text: chk.detail }));
                $li.append($body);
                $checks.append($li);
            });
        }
    }

    $(document).on('click', '#vortem-seo-recalculate-readability', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var $status = $('#vortem-seo-readability-status');
        var postId  = $btn.data('post-id');
        var content = readEditorContent();
        var original = $btn.text();
        $btn.prop('disabled', true).text(vortemSeoMetaBox.i18n.analyzing);
        $status.removeClass('notice notice-error notice-success').empty();

        $.post(vortemSeoMetaBox.ajaxUrl, {
            action: 'vortem_seo_readability',
            nonce: vortemSeoMetaBox.readabilityNonce,
            post_id: postId,
            content: content
        }).done(function (resp) {
            if (resp && resp.success && resp.data) {
                renderReadability(resp.data);
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : vortemSeoMetaBox.i18n.readabilityFailure;
                $status.addClass('notice notice-error').text(msg);
            }
        }).fail(function (xhr) {
            var msg = vortemSeoMetaBox.i18n.readabilityFailure;
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = vortemSeoMetaBox.i18n.readabilityFailure + ' ' + xhr.responseJSON.data.message;
            }
            $status.addClass('notice notice-error').text(msg);
        }).always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });

    $(document).on('click', '#vortem-seo-regenerate', function (e) {
        e.preventDefault();
        var $btn    = $(this);
        var $status = $('#vortem-seo-regenerate-status');
        var postId  = $btn.data('post-id');
        if (!postId) {
            return;
        }
        var original = $btn.text();
        $btn.prop('disabled', true).text(vortemSeoMetaBox.i18n.regenerating);
        $status.removeClass('notice-error notice-success').empty();

        $.post(vortemSeoMetaBox.ajaxUrl, {
            action: 'vortem_regenerate_seo',
            nonce: vortemSeoMetaBox.nonce,
            post_id: postId
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.fields) {
                if (typeof resp.data.fields.title !== 'undefined') {
                    $('#vortem_seo_title').val(resp.data.fields.title);
                }
                if (typeof resp.data.fields.metadesc !== 'undefined') {
                    $('#vortem_seo_metadesc').val(resp.data.fields.metadesc);
                }
                if (typeof resp.data.fields.focuskw !== 'undefined') {
                    $('#vortem_seo_focuskw').val(resp.data.fields.focuskw);
                }
                updatePreview();
                $status.addClass('notice notice-success').text(vortemSeoMetaBox.i18n.success);
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : vortemSeoMetaBox.i18n.failure;
                $status.addClass('notice notice-error').text(msg);
            }
        }).fail(function (xhr) {
            var msg = vortemSeoMetaBox.i18n.failure;
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = vortemSeoMetaBox.i18n.failure + ' ' + xhr.responseJSON.data.message;
            }
            $status.addClass('notice notice-error').text(msg);
        }).always(function () {
            $btn.prop('disabled', false).text(original);
        });
    });
}(jQuery));
JS;
	}

	/**
	 * Inline CSS body for the snippet preview + regen status.
	 *
	 * @return string
	 */
	private function inline_style() {
		return '
.vortem-seo-tabs { display: flex; gap: 0; border-bottom: 1px solid #c3c4c7; margin: -6px -12px 16px; padding: 0 12px; }
.vortem-seo-tab { background: transparent; border: 0; border-bottom: 3px solid transparent; padding: 10px 14px; font-size: 14px; font-weight: 600; color: #50575e; cursor: pointer; }
.vortem-seo-tab:hover { color: #1d2327; }
.vortem-seo-tab.is-active { color: #2271b1; border-bottom-color: #2271b1; }
.vortem-seo-tab-panel[hidden] { display: none; }
.vortem-seo-fields { display: grid; gap: 12px; }
.vortem-seo-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
.vortem-seo-fields input[type="text"],
.vortem-seo-fields input[type="url"],
.vortem-seo-fields textarea { width: 100%; }
.vortem-seo-fields .description { color: #646970; font-style: italic; margin: 4px 0 0; }
.vortem-seo-preview { border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 14px; background: #fff; max-width: 640px; }
.vortem-seo-preview-url { color: #006621; font-size: 14px; word-break: break-all; }
.vortem-seo-preview-title { color: #1a0dab; font-size: 18px; line-height: 1.3; margin: 4px 0; word-break: break-word; }
.vortem-seo-preview-desc { color: #4d5156; font-size: 13px; line-height: 1.45; word-break: break-word; }
.vortem-seo-regenerate-row { display: flex; align-items: center; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
.vortem-seo-regenerate-row .notice { padding: 6px 10px; margin: 0; }
.vortem-seo-readability-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.vortem-seo-readability-badge { display: inline-block; padding: 8px 14px; border-radius: 999px; color: #fff; font-weight: 700; font-size: 14px; min-width: 110px; text-align: center; }
.vortem-seo-readability-score-wrap { display: flex; flex-direction: column; line-height: 1.2; }
.vortem-seo-readability-score { font-size: 28px; font-weight: 700; color: #1d2327; }
.vortem-seo-readability-score-label { font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.05em; }
.vortem-seo-readability-advice { color: #50575e; margin: 4px 0 12px; }
.vortem-seo-readability-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px 16px; margin: 8px 0 16px; }
.vortem-seo-readability-stats > div { padding: 8px 10px; background: #f6f7f7; border-radius: 4px; }
.vortem-seo-readability-stats strong { display: block; font-size: 18px; color: #1d2327; }
.vortem-seo-readability-stats span { color: #646970; font-size: 12px; }
.vortem-seo-readability-meta { color: #646970; font-style: italic; font-size: 12px; }
.vortem-seo-readability-section-title { margin: 16px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: #50575e; }
.vortem-seo-readability-checks { list-style: none; margin: 0 0 16px; padding: 0; display: grid; gap: 8px; }
.vortem-seo-check { display: flex; align-items: flex-start; gap: 10px; padding: 8px 10px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
.vortem-seo-check-dot { flex: 0 0 auto; width: 12px; height: 12px; border-radius: 50%; margin-top: 4px; background: #c3c4c7; }
.vortem-seo-check--good .vortem-seo-check-dot { background: #00a32a; }
.vortem-seo-check--ok .vortem-seo-check-dot { background: #dba617; }
.vortem-seo-check--bad .vortem-seo-check-dot { background: #d63638; }
.vortem-seo-check--unknown .vortem-seo-check-dot { background: #8c8f94; }
.vortem-seo-check-body { color: #1d2327; font-size: 13px; line-height: 1.45; }
.vortem-seo-check-label { color: #1d2327; }
.vortem-seo-check-detail { color: #50575e; }
';
	}
}
