<?php
/**
 * Vortem SEO meta box partial.
 *
 * Expects the following from Vortem_SEO_Meta_Box::render_meta_box():
 *
 * @var int    $post_id          Current product ID.
 * @var array  $values           Map of {title, metadesc, focuskw, og_image, canonical}.
 * @var string $vortem_remote_id Remote Vortem product ID, '' if not imported.
 * @var string $preview_url      Permalink for snippet preview.
 * @var string $site_name        Site name shown in the title preview tail.
 * @var array  $readability      Output of Vortem_SEO_Readability::analyze().
 *
 * @package VortemAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_nonce_field( Vortem_SEO_Meta_Box::NONCE_ACTION, Vortem_SEO_Meta_Box::NONCE_FIELD );
?>
<div class="vortem-seo-tabs" role="tablist">
	<button type="button" class="vortem-seo-tab is-active" role="tab" aria-selected="true" aria-controls="vortem-seo-panel-seo" data-tab="seo">
		<?php esc_html_e( 'SEO', 'vortem-ai' ); ?>
	</button>
	<button type="button" class="vortem-seo-tab" role="tab" aria-selected="false" aria-controls="vortem-seo-panel-readability" data-tab="readability">
		<?php esc_html_e( 'Readability', 'vortem-ai' ); ?>
	</button>
</div>

<div id="vortem-seo-panel-seo" class="vortem-seo-tab-panel" role="tabpanel">
	<div class="vortem-seo-fields">
		<div>
			<label for="vortem_seo_title"><?php esc_html_e( 'SEO title', 'vortem-ai' ); ?></label>
			<input type="text" id="vortem_seo_title" name="vortem_seo_title" value="<?php echo esc_attr( $values['title'] ); ?>" maxlength="200" />
			<p class="description"><?php esc_html_e( 'Shown in the browser tab and in search results. Leave blank to use the product name.', 'vortem-ai' ); ?></p>
		</div>

		<div>
			<label for="vortem_seo_metadesc"><?php esc_html_e( 'Meta description', 'vortem-ai' ); ?></label>
			<textarea id="vortem_seo_metadesc" name="vortem_seo_metadesc" rows="3" maxlength="320"><?php echo esc_textarea( $values['metadesc'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Snippet shown under the title in search results. Aim for 120–160 characters.', 'vortem-ai' ); ?></p>
		</div>

		<div>
			<label for="vortem_seo_focuskw"><?php esc_html_e( 'Focus keyphrase', 'vortem-ai' ); ?></label>
			<input type="text" id="vortem_seo_focuskw" name="vortem_seo_focuskw" value="<?php echo esc_attr( $values['focuskw'] ); ?>" maxlength="200" />
			<p class="description"><?php esc_html_e( 'The primary phrase this product should rank for. Used by Vortem when re-generating SEO copy.', 'vortem-ai' ); ?></p>
		</div>

		<div>
			<label for="vortem_seo_og_image"><?php esc_html_e( 'Social share image URL', 'vortem-ai' ); ?></label>
			<input type="url" id="vortem_seo_og_image" name="vortem_seo_og_image" value="<?php echo esc_attr( $values['og_image'] ); ?>" placeholder="https://" />
			<p class="description"><?php esc_html_e( 'Override the Open Graph / Twitter image. Leave blank to use the product featured image.', 'vortem-ai' ); ?></p>
		</div>

		<div>
			<label for="vortem_seo_canonical"><?php esc_html_e( 'Canonical URL', 'vortem-ai' ); ?></label>
			<input type="url" id="vortem_seo_canonical" name="vortem_seo_canonical" value="<?php echo esc_attr( $values['canonical'] ); ?>" placeholder="<?php echo esc_attr( $preview_url ); ?>" />
			<p class="description"><?php esc_html_e( 'Override the canonical URL. Leave blank to use this product\'s permalink.', 'vortem-ai' ); ?></p>
		</div>

		<div>
			<h4 style="margin:8px 0 4px;"><?php esc_html_e( 'Search snippet preview', 'vortem-ai' ); ?></h4>
			<div class="vortem-seo-preview" aria-live="polite">
				<div class="vortem-seo-preview-url"><?php echo esc_html( $preview_url ); ?></div>
				<div id="vortem-seo-preview-title" class="vortem-seo-preview-title"><?php echo esc_html( '' !== $values['title'] ? $values['title'] : get_the_title( $post_id ) ); ?></div>
				<div id="vortem-seo-preview-desc" class="vortem-seo-preview-desc"><?php echo esc_html( $values['metadesc'] ); ?></div>
			</div>
		</div>

		<div class="vortem-seo-regenerate-row">
			<?php if ( '' !== $vortem_remote_id ) : ?>
				<button type="button"
					class="button button-secondary"
					id="vortem-seo-regenerate"
					data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
					<?php esc_html_e( 'Regenerate from Vortem', 'vortem-ai' ); ?>
				</button>
				<span id="vortem-seo-regenerate-status"></span>
				<p class="description" style="flex-basis:100%;margin-top:4px;">
					<?php esc_html_e( 'Re-fetches SEO title, meta description, and focus keyphrase from Vortem. Does not overwrite the product name, description, or tags.', 'vortem-ai' ); ?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Regenerate is only available for products imported from Vortem.', 'vortem-ai' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="vortem-seo-panel-readability" class="vortem-seo-tab-panel" role="tabpanel" hidden>
	<div id="vortem-seo-readability">
		<div class="vortem-seo-readability-header">
			<span class="vortem-seo-readability-badge" style="background-color: <?php echo esc_attr( $readability['color'] ); ?>;">
				<?php echo esc_html( $readability['label'] ); ?>
			</span>
			<div class="vortem-seo-readability-score-wrap">
				<span class="vortem-seo-readability-score"><?php echo esc_html( (string) $readability['score'] ); ?></span>
				<span class="vortem-seo-readability-score-label"><?php esc_html_e( 'Flesch Reading Ease', 'vortem-ai' ); ?></span>
			</div>
		</div>

		<p class="vortem-seo-readability-advice"><?php echo esc_html( $readability['advice'] ); ?></p>

		<h4 class="vortem-seo-readability-section-title"><?php esc_html_e( 'Analysis results', 'vortem-ai' ); ?></h4>
		<ul class="vortem-seo-readability-checks" id="vortem-seo-readability-checks">
			<?php foreach ( $readability['checks'] as $vortem_check ) : ?>
				<li class="vortem-seo-check vortem-seo-check--<?php echo esc_attr( $vortem_check['status'] ); ?>" data-check-id="<?php echo esc_attr( $vortem_check['id'] ); ?>">
					<span class="vortem-seo-check-dot" aria-hidden="true"></span>
					<span class="vortem-seo-check-body">
						<strong class="vortem-seo-check-label"><?php echo esc_html( $vortem_check['label'] ); ?>:</strong>
						<span class="vortem-seo-check-detail"><?php echo esc_html( $vortem_check['detail'] ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>

		<h4 class="vortem-seo-readability-section-title"><?php esc_html_e( 'Statistics', 'vortem-ai' ); ?></h4>
		<div class="vortem-seo-readability-stats">
			<div>
				<strong data-stat="words"><?php echo esc_html( (string) $readability['words'] ); ?></strong>
				<span><?php esc_html_e( 'Words', 'vortem-ai' ); ?></span>
			</div>
			<div>
				<strong data-stat="sentences"><?php echo esc_html( (string) $readability['sentences'] ); ?></strong>
				<span><?php esc_html_e( 'Sentences', 'vortem-ai' ); ?></span>
			</div>
			<div>
				<strong data-stat="syllables"><?php echo esc_html( (string) $readability['syllables'] ); ?></strong>
				<span><?php esc_html_e( 'Syllables', 'vortem-ai' ); ?></span>
			</div>
			<div>
				<strong data-stat="avg_sentence_length"><?php echo esc_html( (string) $readability['avg_sentence_length'] ); ?></strong>
				<span><?php esc_html_e( 'Avg. words / sentence', 'vortem-ai' ); ?></span>
			</div>
			<div>
				<strong data-stat="avg_syllables_per_word"><?php echo esc_html( (string) $readability['avg_syllables_per_word'] ); ?></strong>
				<span><?php esc_html_e( 'Avg. syllables / word', 'vortem-ai' ); ?></span>
			</div>
		</div>

		<p class="vortem-seo-readability-meta">
			<?php esc_html_e( 'Computed from the saved product description. Click Recalculate after editing the description to refresh.', 'vortem-ai' ); ?>
		</p>

		<button type="button"
			class="button button-secondary"
			id="vortem-seo-recalculate-readability"
			data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
			<?php esc_html_e( 'Recalculate from current editor', 'vortem-ai' ); ?>
		</button>
		<span id="vortem-seo-readability-status"></span>
	</div>
</div>
