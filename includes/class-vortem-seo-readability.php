<?php
/**
 * Vortem SEO Readability
 *
 * Computes a Flesch Reading Ease score plus a set of structural copy checks
 * (sentence length, paragraph length, subheading distribution, passive voice,
 * transition words, consecutive sentence starters). Pure PHP, no external
 * dependencies, no API calls.
 *
 * @package VortemAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem_SEO_Readability
 */
class Vortem_SEO_Readability {

	const STATUS_GOOD    = 'good';
	const STATUS_OK      = 'ok';
	const STATUS_BAD     = 'bad';
	const STATUS_UNKNOWN = 'unknown';

	/**
	 * Analyze a chunk of text/HTML.
	 *
	 * @param string $html Raw text (HTML allowed; structure is used for
	 *                     paragraph + subheading checks before stripping).
	 * @return array
	 */
	public static function analyze( $html ) {
		$html  = (string) $html;
		$plain = self::normalize( $html );

		$words     = self::count_words( $plain );
		$sentences = self::count_sentences( $plain );
		$syllables = self::count_syllables( $plain );

		if ( $words < 1 || $sentences < 1 ) {
			return self::empty_result();
		}

		$avg_sentence_length    = $words / $sentences;
		$avg_syllables_per_word = $syllables / $words;

		$score = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables_per_word );
		$score = max( 0.0, min( 100.0, $score ) );

		$grade = self::level_for_score( $score );

		$sentence_list = self::split_sentences( $plain );

		$checks = array(
			self::check_sentence_length( $sentence_list ),
			self::check_paragraph_length( $html ),
			self::check_subheading_distribution( $html ),
			self::check_passive_voice( $sentence_list ),
			self::check_transition_words( $sentence_list ),
			self::check_consecutive_sentences( $sentence_list ),
		);

		$summary = self::summarize_checks( $checks );

		return array(
			'score'                  => round( $score, 1 ),
			'level'                  => $grade['level'],
			'label'                  => $grade['label'],
			'advice'                 => $grade['advice'],
			'color'                  => $grade['color'],
			'words'                  => $words,
			'sentences'              => $sentences,
			'syllables'              => $syllables,
			'avg_sentence_length'    => round( $avg_sentence_length, 1 ),
			'avg_syllables_per_word' => round( $avg_syllables_per_word, 2 ),
			'checks'                 => $checks,
			'summary'                => $summary,
		);
	}

	/**
	 * Map a Flesch score to a level + display metadata.
	 *
	 * @param float $score Flesch Reading Ease score (0–100).
	 * @return array
	 */
	public static function level_for_score( $score ) {
		if ( $score >= 90 ) {
			return array(
				'level'  => 'very-easy',
				'label'  => __( 'Very easy', 'vortem-ai' ),
				'advice' => __( 'Easily understood by an average 11-year-old.', 'vortem-ai' ),
				'color'  => '#0a7d2a',
			);
		}
		if ( $score >= 80 ) {
			return array(
				'level'  => 'easy',
				'label'  => __( 'Easy', 'vortem-ai' ),
				'advice' => __( 'Conversational English for consumers. Great for product copy.', 'vortem-ai' ),
				'color'  => '#1f8a3a',
			);
		}
		if ( $score >= 70 ) {
			return array(
				'level'  => 'fairly-easy',
				'label'  => __( 'Fairly easy', 'vortem-ai' ),
				'advice' => __( 'Plain English. Most shoppers will follow without effort.', 'vortem-ai' ),
				'color'  => '#3a9b4a',
			);
		}
		if ( $score >= 60 ) {
			return array(
				'level'  => 'standard',
				'label'  => __( 'Standard', 'vortem-ai' ),
				'advice' => __( 'Readable by ages 13–15. Good baseline for product pages.', 'vortem-ai' ),
				'color'  => '#cba73d',
			);
		}
		if ( $score >= 50 ) {
			return array(
				'level'  => 'fairly-difficult',
				'label'  => __( 'Fairly difficult', 'vortem-ai' ),
				'advice' => __( 'Tighten long sentences and prefer shorter words.', 'vortem-ai' ),
				'color'  => '#d18a2a',
			);
		}
		if ( $score >= 30 ) {
			return array(
				'level'  => 'difficult',
				'label'  => __( 'Difficult', 'vortem-ai' ),
				'advice' => __( 'Most general shoppers will struggle. Use the checks below to find what to fix.', 'vortem-ai' ),
				'color'  => '#c8521e',
			);
		}
		return array(
			'level'  => 'very-confusing',
			'label'  => __( 'Very confusing', 'vortem-ai' ),
			'advice' => __( 'The Flesch formula is harsh on dense marketing copy. The checks below show exactly what to tweak.', 'vortem-ai' ),
			'color'  => '#a8261a',
		);
	}

	/**
	 * Aggregate per-check statuses into a counters block.
	 *
	 * @param array $checks Output of analyze() checks.
	 * @return array {good, ok, bad, unknown}
	 */
	private static function summarize_checks( $checks ) {
		$summary = array(
			self::STATUS_GOOD    => 0,
			self::STATUS_OK      => 0,
			self::STATUS_BAD     => 0,
			self::STATUS_UNKNOWN => 0,
		);
		foreach ( $checks as $c ) {
			if ( isset( $c['status'], $summary[ $c['status'] ] ) ) {
				++$summary[ $c['status'] ];
			}
		}
		return $summary;
	}

	// ------------------------------------------------------------------
	// Individual checks
	// ------------------------------------------------------------------

	/**
	 * Sentence-length check. Threshold: more than 25% of sentences over 20
	 * words is considered too many long sentences.
	 *
	 * @param array $sentences Pre-split sentence array.
	 * @return array
	 */
	private static function check_sentence_length( $sentences ) {
		$label = __( 'Sentence length', 'vortem-ai' );
		$total = count( $sentences );
		if ( $total < 1 ) {
			return self::check( 'sentence_length', $label, self::STATUS_UNKNOWN, __( 'Add more text to evaluate.', 'vortem-ai' ) );
		}

		$long = 0;
		foreach ( $sentences as $s ) {
			if ( self::count_words( $s ) > 20 ) {
				++$long;
			}
		}
		$pct = ( $long / $total ) * 100;

		if ( $pct <= 25 ) {
			return self::check(
				'sentence_length',
				$label,
				self::STATUS_GOOD,
				/* translators: 1: percent value 2: count of long sentences */
				sprintf( __( 'Great — only %1$d%% of sentences are over 20 words (%2$d total).', 'vortem-ai' ), (int) round( $pct ), $long )
			);
		}
		if ( $pct <= 35 ) {
			return self::check(
				'sentence_length',
				$label,
				self::STATUS_OK,
				/* translators: 1: percent value 2: count of long sentences */
				sprintf( __( '%1$d%% of sentences are over 20 words (%2$d total). Consider shortening a few.', 'vortem-ai' ), (int) round( $pct ), $long )
			);
		}
		return self::check(
			'sentence_length',
			$label,
			self::STATUS_BAD,
			/* translators: 1: percent value 2: count of long sentences */
			sprintf( __( '%1$d%% of sentences are over 20 words (%2$d total) — try splitting them up.', 'vortem-ai' ), (int) round( $pct ), $long )
		);
	}

	/**
	 * Paragraph-length check. Threshold: paragraphs over 150 words are
	 * flagged as too long.
	 *
	 * @param string $html Raw HTML.
	 * @return array
	 */
	private static function check_paragraph_length( $html ) {
		$label      = __( 'Paragraph length', 'vortem-ai' );
		$paragraphs = self::split_paragraphs( $html );
		if ( empty( $paragraphs ) ) {
			return self::check( 'paragraph_length', $label, self::STATUS_UNKNOWN, __( 'No paragraphs detected.', 'vortem-ai' ) );
		}

		$too_long = 0;
		$max      = 0;
		foreach ( $paragraphs as $p ) {
			$wc = self::count_words( $p );
			if ( $wc > $max ) {
				$max = $wc;
			}
			if ( $wc > 150 ) {
				++$too_long;
			}
		}

		if ( 0 === $too_long ) {
			return self::check(
				'paragraph_length',
				$label,
				self::STATUS_GOOD,
				/* translators: %d: longest paragraph word count */
				sprintf( __( 'No paragraphs over 150 words. Longest is %d words. Great.', 'vortem-ai' ), $max )
			);
		}
		if ( 1 === $too_long ) {
			return self::check(
				'paragraph_length',
				$label,
				self::STATUS_OK,
				/* translators: %d: longest paragraph word count */
				sprintf( __( 'One paragraph is over 150 words (longest: %d). Consider splitting it.', 'vortem-ai' ), $max )
			);
		}
		return self::check(
			'paragraph_length',
			$label,
			self::STATUS_BAD,
			/* translators: 1: count of long paragraphs 2: longest paragraph word count */
			sprintf( __( '%1$d paragraphs are over 150 words (longest: %2$d). Break them into smaller chunks.', 'vortem-ai' ), $too_long, $max )
		);
	}

	/**
	 * Subheading-distribution check. Threshold: any text run over 300 words
	 * without a subheading is flagged.
	 *
	 * @param string $html Raw HTML.
	 * @return array
	 */
	private static function check_subheading_distribution( $html ) {
		$label    = __( 'Subheading distribution', 'vortem-ai' );
		$sections = self::split_at_subheadings( $html );

		$total_words = 0;
		$max_run     = 0;
		foreach ( $sections as $section ) {
			$wc           = self::count_words( $section );
			$total_words += $wc;
			if ( $wc > $max_run ) {
				$max_run = $wc;
			}
		}

		if ( $total_words < 300 ) {
			return self::check(
				'subheading_distribution',
				$label,
				self::STATUS_GOOD,
				__( 'Text is short enough that subheadings are not required.', 'vortem-ai' )
			);
		}

		if ( $max_run <= 300 ) {
			return self::check(
				'subheading_distribution',
				$label,
				self::STATUS_GOOD,
				/* translators: %d: longest section word count */
				sprintf( __( 'Subheadings break the copy into readable chunks (longest section: %d words).', 'vortem-ai' ), $max_run )
			);
		}
		if ( $max_run <= 500 ) {
			return self::check(
				'subheading_distribution',
				$label,
				self::STATUS_OK,
				/* translators: %d: longest section word count */
				sprintf( __( 'One section runs %d words without a subheading. Add an H2/H3 to break it up.', 'vortem-ai' ), $max_run )
			);
		}
		return self::check(
			'subheading_distribution',
			$label,
			self::STATUS_BAD,
			/* translators: %d: longest section word count */
			sprintf( __( 'A section runs %d words without a subheading — too long. Add headings every 200–300 words.', 'vortem-ai' ), $max_run )
		);
	}

	/**
	 * Passive-voice check. Heuristic: sentences containing a "be" auxiliary
	 * followed by a past participle form. Threshold: more than 15% bad.
	 *
	 * @param array $sentences Pre-split sentence array.
	 * @return array
	 */
	private static function check_passive_voice( $sentences ) {
		$label = __( 'Passive voice', 'vortem-ai' );
		$total = count( $sentences );
		if ( $total < 1 ) {
			return self::check( 'passive_voice', $label, self::STATUS_UNKNOWN, __( 'Add more text to evaluate.', 'vortem-ai' ) );
		}

		$irregulars = 'taken|given|made|written|broken|spoken|chosen|driven|frozen|stolen|forgotten|hidden|known|grown|shown|thrown|drawn|seen|built|brought|bought|caught|taught|sought|sent|spent|kept|left|felt|held|told|sold|paid|laid|said|read|put|cut|set|let|hit|met|lost|found|done|gone|been|had';
		$pattern    = '/\b(?:am|is|are|was|were|be|been|being)\s+(?:\w+(?:ed|en)|' . $irregulars . ')\b/i';

		$passive = 0;
		foreach ( $sentences as $s ) {
			if ( preg_match( $pattern, $s ) ) {
				++$passive;
			}
		}
		$pct = ( $passive / $total ) * 100;

		if ( $pct <= 10 ) {
			return self::check(
				'passive_voice',
				$label,
				self::STATUS_GOOD,
				/* translators: %d: percent value */
				sprintf( __( 'Only %d%% of sentences look passive. Active and direct.', 'vortem-ai' ), (int) round( $pct ) )
			);
		}
		if ( $pct <= 15 ) {
			return self::check(
				'passive_voice',
				$label,
				self::STATUS_OK,
				/* translators: %d: percent value */
				sprintf( __( '%d%% of sentences look passive. A few more in active voice would help.', 'vortem-ai' ), (int) round( $pct ) )
			);
		}
		return self::check(
			'passive_voice',
			$label,
			self::STATUS_BAD,
			/* translators: %d: percent value */
			sprintf( __( '%d%% of sentences look passive. Rewrite some in active voice ("we ship" instead of "is shipped").', 'vortem-ai' ), (int) round( $pct ) )
		);
	}

	/**
	 * Transition-words check. Threshold: at least 30% of sentences should
	 * contain a transition word or phrase.
	 *
	 * @param array $sentences Pre-split sentence array.
	 * @return array
	 */
	private static function check_transition_words( $sentences ) {
		$label = __( 'Transition words', 'vortem-ai' );
		$total = count( $sentences );
		if ( $total < 1 ) {
			return self::check( 'transition_words', $label, self::STATUS_UNKNOWN, __( 'Add more text to evaluate.', 'vortem-ai' ) );
		}

		$transitions = array(
			'however',
			'therefore',
			'moreover',
			'furthermore',
			'in addition',
			'additionally',
			'for example',
			'for instance',
			'in conclusion',
			'as a result',
			'consequently',
			'meanwhile',
			'nevertheless',
			'nonetheless',
			'thus',
			'hence',
			'specifically',
			'in particular',
			'first',
			'firstly',
			'second',
			'secondly',
			'third',
			'thirdly',
			'finally',
			'lastly',
			'next',
			'then',
			'afterward',
			'subsequently',
			'previously',
			'besides',
			'indeed',
			'in fact',
			'of course',
			'namely',
			'such as',
			'similarly',
			'likewise',
			'in contrast',
			'on the other hand',
			'in summary',
			'to summarize',
			'altogether',
			'all in all',
			'overall',
			'because',
			'since',
			'although',
			'though',
			'even though',
			'while',
			'whereas',
			'unless',
			'until',
			'before',
			'after',
			'as soon as',
			'when',
			'wherever',
			'in order to',
			'so that',
			'given that',
			'provided that',
			'rather',
			'instead',
			'whereas',
			'whenever',
			'first of all',
			'second of all',
			'on the contrary',
			'by contrast',
			'in comparison',
			'also',
		);

		$escaped = array_map(
			static function ( $t ) {
				return preg_quote( $t, '/' );
			},
			$transitions
		);
		$pattern = '/\b(?:' . implode( '|', $escaped ) . ')\b/i';

		$hits = 0;
		foreach ( $sentences as $s ) {
			if ( preg_match( $pattern, $s ) ) {
				++$hits;
			}
		}
		$pct = ( $hits / $total ) * 100;

		if ( $pct >= 30 ) {
			return self::check(
				'transition_words',
				$label,
				self::STATUS_GOOD,
				/* translators: %d: percent value */
				sprintf( __( '%d%% of sentences contain transition words. Great connective flow.', 'vortem-ai' ), (int) round( $pct ) )
			);
		}
		if ( $pct >= 20 ) {
			return self::check(
				'transition_words',
				$label,
				self::STATUS_OK,
				/* translators: %d: percent value */
				sprintf( __( '%d%% of sentences contain transition words. A few more would improve flow (target: 30%%).', 'vortem-ai' ), (int) round( $pct ) )
			);
		}
		return self::check(
			'transition_words',
			$label,
			self::STATUS_BAD,
			/* translators: %d: percent value */
			sprintf( __( 'Only %d%% of sentences contain transition words — try to use more (e.g. however, therefore, for example).', 'vortem-ai' ), (int) round( $pct ) )
		);
	}

	/**
	 * Consecutive-sentences check. Flags 3+ sentences in a row that start
	 * with the same word.
	 *
	 * @param array $sentences Pre-split sentence array.
	 * @return array
	 */
	private static function check_consecutive_sentences( $sentences ) {
		$label = __( 'Consecutive sentences', 'vortem-ai' );
		if ( count( $sentences ) < 3 ) {
			return self::check( 'consecutive_sentences', $label, self::STATUS_GOOD, __( 'Not enough sentences to repeat.', 'vortem-ai' ) );
		}

		$prev_word = '';
		$run       = 1;
		$max_run   = 1;
		$repeats   = 0;
		foreach ( $sentences as $s ) {
			$first = self::first_word( $s );
			if ( '' !== $first && $first === $prev_word ) {
				++$run;
				if ( 3 === $run ) {
					++$repeats;
				}
				if ( $run > $max_run ) {
					$max_run = $run;
				}
			} else {
				$run = 1;
			}
			$prev_word = $first;
		}

		if ( 0 === $repeats ) {
			return self::check(
				'consecutive_sentences',
				$label,
				self::STATUS_GOOD,
				__( 'No three consecutive sentences start with the same word. Nice variety.', 'vortem-ai' )
			);
		}
		return self::check(
			'consecutive_sentences',
			$label,
			self::STATUS_BAD,
			/* translators: 1: count of runs, 2: max run length */
			sprintf( __( '%1$d run(s) of sentences start with the same word (longest: %2$d in a row). Vary the openings.', 'vortem-ai' ), $repeats, $max_run )
		);
	}

	// ------------------------------------------------------------------
	// Text helpers
	// ------------------------------------------------------------------

	/**
	 * Build a check result row.
	 *
	 * @param string $id     Stable identifier.
	 * @param string $label  Display label.
	 * @param string $status One of STATUS_*.
	 * @param string $detail Human-readable explanation.
	 * @return array
	 */
	private static function check( $id, $label, $status, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'status' => $status,
			'detail' => $detail,
		);
	}

	/**
	 * Empty-state fallback when there isn't enough text to score.
	 *
	 * @return array
	 */
	private static function empty_result() {
		return array(
			'score'                  => 0.0,
			'level'                  => 'unknown',
			'label'                  => __( 'Not enough text', 'vortem-ai' ),
			'advice'                 => __( 'Add a longer product description to get a readability score.', 'vortem-ai' ),
			'color'                  => '#646970',
			'words'                  => 0,
			'sentences'              => 0,
			'syllables'              => 0,
			'avg_sentence_length'    => 0.0,
			'avg_syllables_per_word' => 0.0,
			'checks'                 => array(),
			'summary'                => array(
				self::STATUS_GOOD    => 0,
				self::STATUS_OK      => 0,
				self::STATUS_BAD     => 0,
				self::STATUS_UNKNOWN => 0,
			),
		);
	}

	/**
	 * Strip HTML/shortcodes, decode entities, collapse whitespace.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function normalize( $text ) {
		$text = (string) $text;
		$text = strip_shortcodes( $text );
		// Replace block-level closers with a space so words don't fuse.
		$text = (string) preg_replace( '#</(?:p|div|li|h[1-6]|br|tr|td|th|blockquote)\s*>#i', ' ', $text );
		$text = (string) preg_replace( '#<br\s*/?>#i', ' ', $text );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Split content into paragraph strings (plain text per paragraph).
	 *
	 * @param string $html Raw HTML.
	 * @return string[]
	 */
	private static function split_paragraphs( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return array();
		}

		// Block editor "paragraph" comments + <p> tags both delimit paragraphs.
		// For non-HTML input, fall back to double-newline splits.
		$has_html = false !== stripos( $html, '<p' ) || false !== stripos( $html, '</p>' );
		if ( $has_html ) {
			if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $matches ) ) {
				$paragraphs = array();
				foreach ( $matches[1] as $chunk ) {
					$plain = self::normalize( $chunk );
					if ( '' !== $plain ) {
						$paragraphs[] = $plain;
					}
				}
				return $paragraphs;
			}
		}

		$plain = self::normalize( $html );
		$parts = preg_split( '/\n{2,}/', $plain );
		if ( ! is_array( $parts ) ) {
			return '' !== $plain ? array( $plain ) : array();
		}
		$parts = array_filter( array_map( 'trim', $parts ) );
		return array_values( $parts );
	}

	/**
	 * Split content into sections separated by H1–H6 boundaries. Returns the
	 * plain-text body of each section (heading text itself excluded).
	 *
	 * @param string $html Raw HTML.
	 * @return string[]
	 */
	private static function split_at_subheadings( $html ) {
		$html  = (string) $html;
		$split = preg_split( '#<h[1-6]\b[^>]*>.*?</h[1-6]>#is', $html );
		if ( ! is_array( $split ) ) {
			$plain = self::normalize( $html );
			return '' !== $plain ? array( $plain ) : array();
		}
		$out = array();
		foreach ( $split as $section ) {
			$plain = self::normalize( $section );
			if ( '' !== $plain ) {
				$out[] = $plain;
			}
		}
		return $out;
	}

	/**
	 * Split plain text into individual sentence strings.
	 *
	 * @param string $text Normalized plain text.
	 * @return string[]
	 */
	private static function split_sentences( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return array();
		}
		$parts = preg_split( '/(?<=[.!?])\s+(?=[\p{Lu}\p{N}"“\'])/u', $text );
		if ( ! is_array( $parts ) ) {
			return array( $text );
		}
		$out = array();
		foreach ( $parts as $p ) {
			$p = trim( $p );
			if ( '' !== $p ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Count word tokens (Unicode-aware).
	 *
	 * @param string $text Normalized text.
	 * @return int
	 */
	private static function count_words( $text ) {
		if ( '' === $text ) {
			return 0;
		}
		$matches = preg_match_all( '/[\p{L}\p{N}]+(?:[\'\-][\p{L}\p{N}]+)*/u', $text );
		return false === $matches ? 0 : (int) $matches;
	}

	/**
	 * Count sentence-like terminators. Treats sequences of ., !, ? as one.
	 *
	 * @param string $text Normalized text.
	 * @return int
	 */
	private static function count_sentences( $text ) {
		if ( '' === $text ) {
			return 0;
		}
		$matches = preg_match_all( '/[.!?]+(?:\s|$)/u', $text );
		$count   = false === $matches ? 0 : (int) $matches;
		return $count > 0 ? $count : 1;
	}

	/**
	 * Sum estimated syllables across all word tokens.
	 *
	 * @param string $text Normalized text.
	 * @return int
	 */
	private static function count_syllables( $text ) {
		if ( '' === $text ) {
			return 0;
		}
		$total = 0;
		if ( preg_match_all( '/[\p{L}]+(?:[\'\-][\p{L}]+)*/u', $text, $matches ) ) {
			foreach ( $matches[0] as $word ) {
				$total += self::syllables_for_word( $word );
			}
		}
		return $total;
	}

	/**
	 * Heuristic English syllable count for a single word.
	 *
	 * @param string $word A single word token.
	 * @return int
	 */
	private static function syllables_for_word( $word ) {
		$word  = mb_strtolower( $word, 'UTF-8' );
		$parts = preg_split( "/[\\'\\-]+/u", $word );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			$parts = array( $word );
		}

		$total = 0;
		foreach ( $parts as $part ) {
			$part = (string) preg_replace( '/[^a-z]/', '', $part );
			if ( '' === $part ) {
				continue;
			}

			$groups = preg_match_all( '/[aeiouy]+/', $part );
			$count  = false === $groups ? 0 : (int) $groups;

			if ( $count > 1 && 'e' === substr( $part, -1 ) ) {
				--$count;
			}
			if ( strlen( $part ) > 2 && 'le' === substr( $part, -2 ) && ! self::is_vowel( $part[ strlen( $part ) - 3 ] ) ) {
				++$count;
			}

			$total += max( 1, $count );
		}
		return $total;
	}

	/**
	 * First lowercased word of a sentence, or '' if none.
	 *
	 * @param string $sentence Sentence text.
	 * @return string
	 */
	private static function first_word( $sentence ) {
		if ( ! preg_match( '/[\p{L}\p{N}]+/u', $sentence, $m ) ) {
			return '';
		}
		return mb_strtolower( $m[0], 'UTF-8' );
	}

	/**
	 * Test whether a character is an English vowel (y inclusive).
	 *
	 * @param string $char Single ASCII letter.
	 * @return bool
	 */
	private static function is_vowel( $char ) {
		return false !== strpos( 'aeiouy', $char );
	}
}
