<?php
/**
 * Vortem Translation Manager
 *
 * Handles language selection, translation loading, and RTL support
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vortem Translation Manager
 */
class Vortem_Translation_Manager {

	/**
	 * Supported languages
	 *
	 * @var array
	 */
	private static $supported_languages = array(
		// 1. English
		'en' => array(
			'name'         => 'English',
			'native_name'  => 'English',
			'country_name' => 'United States',
			'country_code' => 'US',
			'rtl'          => false,
			'locale'       => 'en_US',
		),
		// 2. Chinese
		'zh' => array(
			'name'         => 'Chinese',
			'native_name'  => '中文',
			'country_name' => 'China',
			'country_code' => 'CN',
			'rtl'          => false,
			'locale'       => 'zh_CN',
		),
		// 3. Spanish
		'es' => array(
			'name'         => 'Spanish',
			'native_name'  => 'Español',
			'country_name' => 'Spain',
			'country_code' => 'ES',
			'rtl'          => false,
			'locale'       => 'es_ES',
		),
		// 4. German
		'de' => array(
			'name'         => 'German',
			'native_name'  => 'Deutsch',
			'country_name' => 'Germany',
			'country_code' => 'DE',
			'rtl'          => false,
			'locale'       => 'de_DE',
		),
		// 5. French
		'fr' => array(
			'name'         => 'French',
			'native_name'  => 'Français',
			'country_name' => 'France',
			'country_code' => 'FR',
			'rtl'          => false,
			'locale'       => 'fr_FR',
		),
		// 6. Japanese
		'ja' => array(
			'name'         => 'Japanese',
			'native_name'  => '日本語',
			'country_name' => 'Japan',
			'country_code' => 'JP',
			'rtl'          => false,
			'locale'       => 'ja',
		),
		// 7. Russian
		'ru' => array(
			'name'         => 'Russian',
			'native_name'  => 'Русский',
			'country_name' => 'Russia',
			'country_code' => 'RU',
			'rtl'          => false,
			'locale'       => 'ru_RU',
		),
		// 8. Portuguese
		'pt' => array(
			'name'         => 'Portuguese',
			'native_name'  => 'Português',
			'country_name' => 'Portugal',
			'country_code' => 'PT',
			'rtl'          => false,
			'locale'       => 'pt_BR',
		),
		// 9. Turkish
		'tr' => array(
			'name'         => 'Turkish',
			'native_name'  => 'Türkçe',
			'country_name' => 'Turkey',
			'country_code' => 'TR',
			'rtl'          => false,
			'locale'       => 'tr_TR',
		),
		// 10. Hindi
		'hi' => array(
			'name'         => 'Hindi',
			'native_name'  => 'हिन्दी',
			'country_name' => 'India',
			'country_code' => 'IN',
			'rtl'          => false,
			'locale'       => 'hi_IN',
		),
		// 11. Persian/Farsi
		'fa' => array(
			'name'         => 'Persian',
			'native_name'  => 'فارسی',
			'country_name' => 'Iran',
			'country_code' => 'IR',
			'rtl'          => true,
			'locale'       => 'fa_IR',
		),
		// 12. Urdu
		'ur' => array(
			'name'         => 'Urdu',
			'native_name'  => 'اردو',
			'country_name' => 'Pakistan',
			'country_code' => 'PK',
			'rtl'          => true,
			'locale'       => 'ur_PK',
		),
		// 13. Dutch
		'nl' => array(
			'name'         => 'Dutch',
			'native_name'  => 'Nederlands',
			'country_name' => 'Netherlands',
			'country_code' => 'NL',
			'rtl'          => false,
			'locale'       => 'nl_NL',
		),
		// 14. Italian
		'it' => array(
			'name'         => 'Italian',
			'native_name'  => 'Italiano',
			'country_name' => 'Italy',
			'country_code' => 'IT',
			'rtl'          => false,
			'locale'       => 'it_IT',
		),
		// 15. Korean
		'ko' => array(
			'name'         => 'Korean',
			'native_name'  => '한국어',
			'country_name' => 'South Korea',
			'country_code' => 'KR',
			'rtl'          => false,
			'locale'       => 'ko_KR',
		),
		// 16. Arabic
		'ar' => array(
			'name'         => 'Arabic',
			'native_name'  => 'العربية',
			'country_name' => 'Saudi Arabia',
			'country_code' => 'SA',
			'rtl'          => true,
			'locale'       => 'ar',
		),
		// 17. Swedish
		'sv' => array(
			'name'         => 'Swedish',
			'native_name'  => 'Svenska',
			'country_name' => 'Sweden',
			'country_code' => 'SE',
			'rtl'          => false,
			'locale'       => 'sv_SE',
		),
		// 18. Norwegian
		'no' => array(
			'name'         => 'Norwegian',
			'native_name'  => 'Norsk',
			'country_name' => 'Norway',
			'country_code' => 'NO',
			'rtl'          => false,
			'locale'       => 'nb_NO',
		),
		// 19. Finnish
		'fi' => array(
			'name'         => 'Finnish',
			'native_name'  => 'Suomi',
			'country_name' => 'Finland',
			'country_code' => 'FI',
			'rtl'          => false,
			'locale'       => 'fi_FI',
		),
		// 20. Icelandic
		'is' => array(
			'name'         => 'Icelandic',
			'native_name'  => 'Íslenska',
			'country_name' => 'Iceland',
			'country_code' => 'IS',
			'rtl'          => false,
			'locale'       => 'is_IS',
		),
		// 21. Hebrew
		'he' => array(
			'name'         => 'Hebrew',
			'native_name'  => 'עברית',
			'country_name' => 'Israel',
			'country_code' => 'IL',
			'rtl'          => true,
			'locale'       => 'he_IL',
		),
		// 22. Tajik
		'tj' => array(
			'name'         => 'Tajik',
			'native_name'  => 'Тоҷикӣ',
			'country_name' => 'Tajikistan',
			'country_code' => 'TJ',
			'rtl'          => false,
			'locale'       => 'tg_TJ',
		),
		// 23. Greek
		'gr' => array(
			'name'         => 'Greek',
			'native_name'  => 'Ελληνικά',
			'country_name' => 'Greece',
			'country_code' => 'GR',
			'rtl'          => false,
			'locale'       => 'el_GR',
		),
		// 24. Serbian
		'rs' => array(
			'name'         => 'Serbian',
			'native_name'  => 'Српски',
			'country_name' => 'Serbia',
			'country_code' => 'RS',
			'rtl'          => false,
			'locale'       => 'sr_RS',
		),
		// 25. Uzbek
		'uz' => array(
			'name'         => 'Uzbek',
			'native_name'  => 'Oʻzbekcha',
			'country_name' => 'Uzbekistan',
			'country_code' => 'UZ',
			'rtl'          => false,
			'locale'       => 'uz_UZ',
		),
		// 26. Polish
		'pl' => array(
			'name'         => 'Polish',
			'native_name'  => 'Polski',
			'country_name' => 'Poland',
			'country_code' => 'PL',
			'rtl'          => false,
			'locale'       => 'pl_PL',
		),
		// 27. Thai
		'th' => array(
			'name'         => 'Thai',
			'native_name'  => 'ไทย',
			'country_name' => 'Thailand',
			'country_code' => 'TH',
			'rtl'          => false,
			'locale'       => 'th',
		),
		// 28. Indonesian
		'id' => array(
			'name'         => 'Indonesian',
			'native_name'  => 'Bahasa Indonesia',
			'country_name' => 'Indonesia',
			'country_code' => 'ID',
			'rtl'          => false,
			'locale'       => 'id_ID',
		),
		// 29. Somali
		'so' => array(
			'name'         => 'Somali',
			'native_name'  => 'Soomaali',
			'country_name' => 'Somalia',
			'country_code' => 'SO',
			'rtl'          => false,
			'locale'       => 'so_SO',
		),
		// 30. Ukrainian
		'ua' => array(
			'name'         => 'Ukrainian',
			'native_name'  => 'Українська',
			'country_name' => 'Ukraine',
			'country_code' => 'UA',
			'rtl'          => false,
			'locale'       => 'uk_UA',
		),
		// 31. Bengali (Bangladesh)
		'bd' => array(
			'name'         => 'Bengali',
			'native_name'  => 'বাংলা',
			'country_name' => 'Bangladesh',
			'country_code' => 'BD',
			'rtl'          => false,
			'locale'       => 'bn_BD',
		),
		// 32. Vietnamese
		'vn' => array(
			'name'         => 'Vietnamese',
			'native_name'  => 'Tiếng Việt',
			'country_name' => 'Vietnam',
			'country_code' => 'VN',
			'rtl'          => false,
			'locale'       => 'vi_VN',
		),
		// 33. Malay (Malaysia)
		'my' => array(
			'name'         => 'Malay',
			'native_name'  => 'Bahasa Melayu',
			'country_name' => 'Malaysia',
			'country_code' => 'MY',
			'rtl'          => false,
			'locale'       => 'ms_MY',
		),
		// 34. Bulgarian
		'bg' => array(
			'name'         => 'Bulgarian',
			'native_name'  => 'Български',
			'country_name' => 'Bulgaria',
			'country_code' => 'BG',
			'rtl'          => false,
			'locale'       => 'bg_BG',
		),
		// 35. Croatian
		'hr' => array(
			'name'         => 'Croatian',
			'native_name'  => 'Hrvatski',
			'country_name' => 'Croatia',
			'country_code' => 'HR',
			'rtl'          => false,
			'locale'       => 'hr_HR',
		),
		// 36. Slovak
		'sk' => array(
			'name'         => 'Slovak',
			'native_name'  => 'Slovenčina',
			'country_name' => 'Slovakia',
			'country_code' => 'SK',
			'rtl'          => false,
			'locale'       => 'sk_SK',
		),
		// 37. Slovenian
		'si' => array(
			'name'         => 'Slovenian',
			'native_name'  => 'Slovenščina',
			'country_name' => 'Slovenia',
			'country_code' => 'SI',
			'rtl'          => false,
			'locale'       => 'sl_SI',
		),
		// 38. Bosnian
		'ba' => array(
			'name'         => 'Bosnian',
			'native_name'  => 'Bosanski',
			'country_name' => 'Bosnia and Herzegovina',
			'country_code' => 'BA',
			'rtl'          => false,
			'locale'       => 'bs_BA',
		),
		// 39. Amharic (Ethiopia)
		'et' => array(
			'name'         => 'Amharic',
			'native_name'  => 'አማርኛ',
			'country_name' => 'Ethiopia',
			'country_code' => 'ET',
			'rtl'          => false,
			'locale'       => 'am_ET',
		),
		// 40. Belarusian
		'by' => array(
			'name'         => 'Belarusian',
			'native_name'  => 'Беларуская',
			'country_name' => 'Belarus',
			'country_code' => 'BY',
			'rtl'          => false,
			'locale'       => 'be_BY',
		),
	);

	/**
	 * Language to continent mapping
	 *
	 * Used for grouping languages in UI (navigation sidebar, settings, wizard).
	 * Continent keys: europe, asia, americas, africa, other.
	 *
	 * @var array
	 */
	private static $language_continents = array(
		// Europe
		'es' => 'europe',
		'de' => 'europe',
		'fr' => 'europe',
		'it' => 'europe',
		'nl' => 'europe',
		'sv' => 'europe',
		'no' => 'europe',
		'fi' => 'europe',
		'is' => 'europe',
		'gr' => 'europe',
		'rs' => 'europe',
		'pl' => 'europe',
		'ua' => 'europe',
		'bg' => 'europe',
		'hr' => 'europe',
		'sk' => 'europe',
		'si' => 'europe',
		'ba' => 'europe',
		'by' => 'europe',
		'pt' => 'europe',
		'ru' => 'europe',

		// Asia
		'zh' => 'asia',
		'tr' => 'asia',
		'ja' => 'asia',
		'hi' => 'asia',
		'fa' => 'asia',
		'ur' => 'asia',
		'ko' => 'asia',
		'he' => 'asia',
		'tj' => 'asia',
		'uz' => 'asia',
		'th' => 'asia',
		'id' => 'asia',
		'bd' => 'asia',
		'vn' => 'asia',
		'my' => 'asia',
		'ar' => 'asia',

		// Americas
		'en' => 'americas', // United States

		// Africa
		'so' => 'africa',
		'et' => 'africa',
	);

	/**
	 * Current language
	 *
	 * @var string
	 */
	private static $current_language = 'en';

	// Translations are now handled by WordPress core via load_plugin_textdomain()
	// and standard .mo files in /languages. The legacy PHP-array `$translations`
	// cache and `load_translations()` helper were removed in favour of the standard
	// gettext pipeline.

	/**
	 * Map WordPress locale to plugin language code
	 *
	 * @param string $wp_locale WordPress locale (e.g., 'ar', 'ar_SA', 'fa_IR', 'ur_PK')
	 * @return string|false Plugin language code or false if not supported
	 */
	private static function map_wp_locale_to_plugin_lang( $wp_locale ) {
		// Get base locale (first 2 characters)
		$locale_base = substr( $wp_locale, 0, 2 );

		// Direct mapping for common locales
		$locale_map = array(
			// Full locale mappings
			'ar'          => 'ar',
			'ar_SA'       => 'ar',
			'ar_EG'       => 'ar',
			'ar_AE'       => 'ar',
			'en_US'       => 'en',
			'en_GB'       => 'en',
			'en'          => 'en',
			'tr_TR'       => 'tr',
			'tr'          => 'tr',
			'ru_RU'       => 'ru',
			'ru'          => 'ru',
			'es_ES'       => 'es',
			'es_MX'       => 'es',
			'es'          => 'es',
			'zh_CN'       => 'zh',
			'zh_TW'       => 'zh',
			'zh'          => 'zh',
			'hi_IN'       => 'hi',
			'hi'          => 'hi',
			'pt_BR'       => 'pt',
			'pt_PT'       => 'pt',
			'pt'          => 'pt',
			'ja'          => 'ja',
			'it_IT'       => 'it',
			'it'          => 'it',
			'ko_KR'       => 'ko',
			'ko'          => 'ko',
			'sv_SE'       => 'sv',
			'sv'          => 'sv',
			'nb_NO'       => 'no',
			'nn_NO'       => 'no',
			'no'          => 'no',
			'fi_FI'       => 'fi',
			'fi'          => 'fi',
			'is_IS'       => 'is',
			'is'          => 'is',
			'he_IL'       => 'he',
			'he'          => 'he',
			'tg_TJ'       => 'tj',
			'tg'          => 'tj',
			'tj'          => 'tj',
			'sr_RS'       => 'rs',
			'sr_RS_latin' => 'rs',
			'sr'          => 'rs',
			'rs_RS'       => 'rs',
			'rs'          => 'rs',
			'uz_UZ'       => 'uz',
			'uz'          => 'uz',
			'el_GR'       => 'gr',
			'el'          => 'gr',
			'gr_GR'       => 'gr',
			'gr'          => 'gr',
			'fa_IR'       => 'fa',
			'fa'          => 'fa',
			'nl_NL'       => 'nl',
			'nl'          => 'nl',
			'de_DE'       => 'de',
			'de'          => 'de',
			'fr_FR'       => 'fr',
			'fr'          => 'fr',
			'pl_PL'       => 'pl',
			'pl'          => 'pl',
			'ur_PK'       => 'ur',
			'ur'          => 'ur',
			'th_TH'       => 'th',
			'th'          => 'th',
			'id_ID'       => 'id',
			'id'          => 'id',
			'so_SO'       => 'so',
			'so'          => 'so',
			// Ukrainian
			'uk_UA'       => 'ua',
			'uk'          => 'ua',
			// Bengali (Bangladesh)
			'bn_BD'       => 'bd',
			'bn'          => 'bd',
			// Vietnamese
			'vi_VN'       => 'vn',
			'vi'          => 'vn',
			// Malay (Malaysia)
			'ms_MY'       => 'my',
			'ms'          => 'my',
			// Bulgarian
			'bg_BG'       => 'bg',
			'bg'          => 'bg',
			// Croatian
			'hr_HR'       => 'hr',
			'hr'          => 'hr',
			// Slovak
			'sk_SK'       => 'sk',
			'sk'          => 'sk',
			// Slovenian
			'sl_SI'       => 'si',
			'sl'          => 'si',
			// Bosnian
			'bs_BA'       => 'ba',
			'bs'          => 'ba',
			// Amharic (Ethiopia)
			'am_ET'       => 'et',
			'am'          => 'et',
			// Belarusian
			'be_BY'       => 'by',
			'be'          => 'by',
		);

		// Check full locale first
		if ( isset( $locale_map[ $wp_locale ] ) ) {
			$plugin_lang = $locale_map[ $wp_locale ];
			// Verify it's supported
			if ( isset( self::$supported_languages[ $plugin_lang ] ) ) {
				return $plugin_lang;
			}
		}

		// Check base locale
		if ( isset( $locale_map[ $locale_base ] ) ) {
			$plugin_lang = $locale_map[ $locale_base ];
			// Verify it's supported
			if ( isset( self::$supported_languages[ $plugin_lang ] ) ) {
				return $plugin_lang;
			}
		}

		// Try to match by checking supported languages' locale field
		foreach ( self::$supported_languages as $lang_code => $lang_data ) {
			if ( isset( $lang_data['locale'] ) ) {
				// Check if WordPress locale matches plugin locale
				if ( $wp_locale === $lang_data['locale'] || $locale_base === substr( $lang_data['locale'], 0, 2 ) ) {
					return $lang_code;
				}
			}
		}

		return false;
	}

	/**
	 * Initialize translation manager
	 */
	public static function init() {
		// Get stored plugin language first
		$stored_plugin_lang = get_option( 'vortem_language', 'en' );

		// Check if user has manually set the plugin language (not synced from WordPress)
		// We track this by checking if there's a flag, or by comparing stored vs WordPress
		$plugin_lang_manually_set = get_option( 'vortem_language_manually_set', false );

		// Get WordPress locale from general settings
		$wp_locale = get_locale();

		// Try to map WordPress locale to plugin language
		$wp_mapped_lang = self::map_wp_locale_to_plugin_lang( $wp_locale );

		// Sync from WordPress to plugin on init if:
		// 1. WordPress locale maps to a supported plugin language, AND
		// 2. Plugin language was NOT manually set by user
		// This ensures manual changes in plugin settings are preserved
		if ( $wp_mapped_lang !== false && ! $plugin_lang_manually_set ) {
			// WordPress language matches a plugin language and user hasn't manually set it
			// Sync to WordPress language
			self::$current_language = $wp_mapped_lang;
			// Update the stored option to match WordPress language
			update_option( 'vortem_language', $wp_mapped_lang );
		} else {
			// Either WordPress language doesn't match any plugin language,
			// OR user has manually set plugin language - use stored plugin language
			self::$current_language = $stored_plugin_lang;
		}

		// Validate language
		if ( ! isset( self::$supported_languages[ self::$current_language ] ) ) {
			self::$current_language = 'en';
		}

		// Translations themselves are loaded via load_plugin_textdomain() from
		// vortem.php (standard WordPress gettext pipeline). No custom array load
		// and no gettext filter override are needed here any more.

		// Add hooks
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_rtl_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_rtl_styles' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_rtl_body_class' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'add_rtl_admin_body_class' ) );

		// Hook to detect WordPress language changes in General Settings
		add_action( 'update_option_WPLANG', array( __CLASS__, 'sync_from_wordpress_language' ), 10, 2 );
		add_action( 'update_option_locale', array( __CLASS__, 'sync_from_wordpress_language' ), 10, 2 );
	}

	/**
	 * Sync plugin language when WordPress General Settings language changes
	 * This is called when WPLANG or locale option is updated
	 *
	 * @param mixed $old_value Old WordPress locale value
	 * @param mixed $new_value New WordPress locale value
	 */
	public static function sync_from_wordpress_language( $old_value, $new_value ) {
		// Get WordPress locale (new value)
		$wp_locale = $new_value ? $new_value : get_locale();

		// Try to map WordPress locale to plugin language
		$wp_mapped_lang = self::map_wp_locale_to_plugin_lang( $wp_locale );

		// If WordPress locale maps to a supported plugin language, sync plugin language
		if ( $wp_mapped_lang !== false ) {
			// Update plugin language to match WordPress
			update_option( 'vortem_language', $wp_mapped_lang );
			// Clear manual flag so sync continues
			delete_option( 'vortem_language_manually_set' );
			// Update current language
			self::$current_language = $wp_mapped_lang;
			// Translations are reloaded automatically by WordPress on the next
			// request because we no longer cache them in a static array.
		}
	}

	/**
	 * Get current language
	 *
	 * @return string
	 */
	public static function get_current_language() {
		return self::$current_language;
	}

	/**
	 * Get supported languages
	 *
	 * @return array
	 */
	public static function get_supported_languages() {
		return self::$supported_languages;
	}

	/**
	 * Get continent for a given language code.
	 *
	 * @param string $lang_code
	 * @return string Continent key (europe, asia, americas, africa, other)
	 */
	public static function get_language_continent( $lang_code ) {
		if ( isset( self::$language_continents[ $lang_code ] ) ) {
			return self::$language_continents[ $lang_code ];
		}

		return 'other';
	}

	/**
	 * Get languages grouped by continent.
	 *
	 * @return array Array keyed by continent with language arrays as values.
	 */
	public static function get_languages_grouped_by_continent() {
		$groups = array(
			'europe'   => array(),
			'asia'     => array(),
			'americas' => array(),
			'africa'   => array(),
			'other'    => array(),
		);

		foreach ( self::$supported_languages as $lang_code => $lang_data ) {
			$continent = self::get_language_continent( $lang_code );
			if ( ! isset( $groups[ $continent ] ) ) {
				$continent = 'other';
			}
			$groups[ $continent ][ $lang_code ] = $lang_data;
		}

		return $groups;
	}

	/**
	 * Get flag emoji for language code
	 *
	 * @param string $lang_code Language code
	 * @return string Flag emoji
	 */
	public static function get_language_flag( $lang_code ) {
		$flags = array(
			'en' => '🇺🇸', // United States
			'ar' => '🇸🇦', // Saudi Arabia
			'tr' => '🇹🇷', // Turkey
			'ru' => '🇷🇺', // Russia
			'es' => '🇪🇸', // Spain
			'zh' => '🇨🇳', // China
			'hi' => '🇮🇳', // India
			'pt' => '🇵🇹', // Portugal
			'ja' => '🇯🇵', // Japan
			'it' => '🇮🇹', // Italy
			'ko' => '🇰🇷', // South Korea
			'sv' => '🇸🇪', // Sweden
			'no' => '🇳🇴', // Norway
			'fi' => '🇫🇮', // Finland
			'is' => '🇮🇸', // Iceland
			'he' => '🇮🇱', // Israel
			'tj' => '🇹🇯', // Tajikistan
			'gr' => '🇬🇷', // Greece
			'rs' => '🇷🇸', // Serbia
			'uz' => '🇺🇿', // Uzbekistan
			'fa' => '🇮🇷', // Iran
			'nl' => '🇳🇱', // Netherlands
			'de' => '🇩🇪', // Germany
			'fr' => '🇫🇷', // France
			'ur' => '🇵🇰', // Pakistan
			'pl' => '🇵🇱', // Poland
			'th' => '🇹🇭', // Thailand
			'id' => '🇮🇩', // Indonesia
			'so' => '🇸🇴', // Somalia
			'ua' => '🇺🇦', // Ukraine
			'bd' => '🇧🇩', // Bangladesh
			'vn' => '🇻🇳', // Vietnam
			'my' => '🇲🇾', // Malaysia
			'bg' => '🇧🇬', // Bulgaria
			'hr' => '🇭🇷', // Croatia
			'sk' => '🇸🇰', // Slovakia
			'si' => '🇸🇮', // Slovenia
			'ba' => '🇧🇦', // Bosnia and Herzegovina
			'et' => '🇪🇹', // Ethiopia
			'by' => '🇧🇾', // Belarus
		);

		return isset( $flags[ $lang_code ] ) ? $flags[ $lang_code ] : '🌐';
	}

	/**
	 * Generate flag emoji from country code using Unicode
	 *
	 * @param string $country_code Two-letter country code (ISO 3166-1 alpha-2)
	 * @return string Flag emoji
	 */
	public static function generate_flag_emoji( $country_code ) {
		if ( strlen( $country_code ) !== 2 ) {
			return '🌐';
		}

		$country_code = strtoupper( $country_code );

		// Convert country code letters to regional indicator symbols
		// A = U+1F1E6, B = U+1F1E7, etc.
		$first_letter  = ord( $country_code[0] ) - ord( 'A' ) + 0x1F1E6;
		$second_letter = ord( $country_code[1] ) - ord( 'A' ) + 0x1F1E6;

		// Generate flag emoji using PHP Unicode syntax
		return mb_chr( $first_letter, 'UTF-8' ) . mb_chr( $second_letter, 'UTF-8' );
	}

	/**
	 * Get SVG flag file path from country code
	 *
	 * @param string $country_code Two-letter country code (ISO 3166-1 alpha-2)
	 * @return string SVG flag file path
	 */
	public static function get_flag_svg_path( $country_code ) {
		if ( strlen( $country_code ) !== 2 ) {
			return '';
		}

		$country_code = strtolower( $country_code );
		$svg_file     = VORTEM_PLUGIN_URL . 'assets/flags/' . $country_code . '.svg';

		return $svg_file;
	}

	/**
	 * Check if current language is RTL
	 *
	 * Checks the plugin's current language setting to determine if RTL should be applied.
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		// Check plugin's current language setting
		if ( isset( self::$supported_languages[ self::$current_language ] ) ) {
			return self::$supported_languages[ self::$current_language ]['rtl'] === true;
		}

		// Fallback to WordPress core locale if plugin language not set
		$wp_locale   = get_locale();
		$rtl_locales = array( 'ar', 'he_IL', 'fa_IR', 'ur' );
		$locale_base = substr( $wp_locale, 0, 2 );

		return in_array( $locale_base, $rtl_locales, true ) || in_array( $wp_locale, $rtl_locales, true );
	}

	/**
	 * Translate a string.
	 *
	 * Thin passthrough kept for backwards compatibility with legacy call sites
	 * that still invoke `Vortem_Translation_Manager::translate(...)`. New code
	 * should call `__()` / `esc_html__()` directly with the `vortem-ai` text
	 * domain so translation tools (translate.wordpress.org, WP-CLI i18n) can
	 * extract the strings.
	 *
	 * @param string $key     Source/English text (used as the gettext msgid).
	 * @param string $default Optional fallback text. If provided, it is used as
	 *                        the msgid; the original $key is treated as a legacy
	 *                        translation key and ignored for lookup.
	 * @return string
	 */
	public static function translate( $key, $default = '' ) {
		$msgid = ! empty( $default ) ? $default : $key;
		// Variable text domain is fine here: this is a runtime fallback for
		// legacy call sites, not a string the i18n extractor needs to find.
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain
		return function_exists( '__' ) ? __( $msgid, 'vortem-ai' ) : $msgid;
	}

	/**
	 * Enqueue RTL styles if needed
	 *
	 * Enqueues RTL styles when plugin language is set to an RTL language.
	 *
	 * @param string $hook Current admin page hook
	 */
	public static function enqueue_rtl_styles( $hook = '' ) {
		// Check if plugin language is RTL
		if ( ! self::is_rtl() ) {
			return;
		}

		// Only load on Vortem pages
		if ( is_admin() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
			$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( strpos( $current_page, 'vortem-ai' ) === false && strpos( $hook, 'vortem-ai' ) === false ) {
				return;
			}
		}

		// Enqueue RTL CSS when plugin language is RTL
		wp_enqueue_style(
			'vortem-rtl',
			VORTEM_PLUGIN_URL . 'assets/css/vortem-rtl.css',
			array(),
			VORTEM_VERSION
		);

		// Add inline style to ensure RTL is applied to plugin pages ONLY, NOT WordPress menu
		// CRITICAL: Never remove or manipulate WordPress core admin elements via JavaScript
		// Use CSS only to override styles without touching DOM structure
		$rtl_css = '
            /* Keep WordPress admin menu LTR - CRITICAL: Force LTR on all admin menu elements */
            /* This ensures WordPress core admin menu is never affected by plugin RTL settings */
            body.vortem-rtl #adminmenu,
            body.vortem-rtl #adminmenuback,
            body.vortem-rtl #adminmenuwrap,
            body.vortem-rtl #wpadminbar,
            body.vortem-rtl #wpadminbar *,
            body.vortem-rtl #adminmenu *,
            body.vortem-rtl #adminmenuback *,
            body.vortem-rtl #adminmenuwrap *,
            body.vortem-rtl #adminmenu li,
            body.vortem-rtl #adminmenu ul,
            body.vortem-rtl #adminmenu a,
            body.vortem-rtl #adminmenu .wp-submenu,
            body.vortem-rtl #adminmenu .wp-submenu *,
            body.vortem-rtl #adminmenu .wp-menu-name,
            body.vortem-rtl #adminmenu .dashicons,
            body.vortem-rtl #adminmenu .wp-menu-image {
                direction: ltr !important;
                text-align: left !important;
                unicode-bidi: embed !important;
            }
            
            /* Force RTL on Vortem plugin pages ONLY - in content area */
            /* Target only specific plugin elements, not entire #wpcontent */
            body.vortem-rtl #wpcontent .vortem-rtl,
            body.vortem-rtl #wpcontent .wrap.vortem-rtl,
            body.vortem-rtl #wpcontent .vortem-page-wrapper,
            body.vortem-rtl #wpcontent .vortem-page-content,
            body.vortem-rtl #wpcontent .vortem-products-wrap,
            body.vortem-rtl #wpcontent .overview-dashboard-container,
            body.vortem-rtl #wpcontent #vortem-products-app,
            body.vortem-rtl #wpcontent #vortem-analytics-tabs-app,
            body.vortem-rtl #wpcontent #mega-dash-app,
            body.vortem-rtl #wpcontent .vortem-orders-wrap,
            body.vortem-rtl #wpcontent .vortem-email-marketing-wrap,
            body.vortem-rtl #wpcontent .vortem-insights-wrap,
            body.vortem-rtl #wpcontent .vortem-security-wrap,
            body.vortem-rtl #wpcontent .security-workspace-wrap,
            body.vortem-rtl #wpcontent .bi-analytics-hub-dashboard {
                direction: rtl !important;
                text-align: right !important;
            }
            
            /* Ensure plugin text elements are right-aligned - ONLY within plugin containers */
            body.vortem-rtl #wpcontent .vortem-page-wrapper p,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h1,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h2,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h3,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h4,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h5,
            body.vortem-rtl #wpcontent .vortem-page-wrapper h6,
            body.vortem-rtl #wpcontent .overview-dashboard-container p,
            body.vortem-rtl #wpcontent .overview-dashboard-container h1,
            body.vortem-rtl #wpcontent .overview-dashboard-container h2,
            body.vortem-rtl #wpcontent .overview-dashboard-container h3,
            body.vortem-rtl #wpcontent .overview-dashboard-container h4,
            body.vortem-rtl #wpcontent .overview-dashboard-container h5,
            body.vortem-rtl #wpcontent .overview-dashboard-container h6,
            body.vortem-rtl #wpcontent .vortem-products-wrap p,
            body.vortem-rtl #wpcontent .vortem-products-wrap h1,
            body.vortem-rtl #wpcontent .vortem-products-wrap h2,
            body.vortem-rtl #wpcontent .vortem-products-wrap h3,
            body.vortem-rtl #wpcontent .vortem-products-wrap h4,
            body.vortem-rtl #wpcontent .vortem-products-wrap h5,
            body.vortem-rtl #wpcontent .vortem-products-wrap h6,
            body.vortem-rtl #wpcontent .vortem-page-wrapper label,
            body.vortem-rtl #wpcontent .vortem-page-wrapper td,
            body.vortem-rtl #wpcontent .vortem-page-wrapper th,
            body.vortem-rtl #wpcontent .overview-dashboard-container label,
            body.vortem-rtl #wpcontent .overview-dashboard-container td,
            body.vortem-rtl #wpcontent .overview-dashboard-container th,
            body.vortem-rtl #wpcontent .vortem-products-wrap label,
            body.vortem-rtl #wpcontent .vortem-products-wrap td,
            body.vortem-rtl #wpcontent .vortem-products-wrap th {
                text-align: right !important;
            }
        ';

		wp_add_inline_style( 'vortem-rtl', $rtl_css );
	}


	/**
	 * Add RTL class to body
	 *
	 * Adds RTL class when plugin language is set to an RTL language.
	 *
	 * @param array $classes Body classes
	 * @return array
	 */
	public static function add_rtl_body_class( $classes ) {
		// Check if plugin language is RTL
		if ( self::is_rtl() ) {
			$classes[] = 'vortem-rtl';
		}
		return $classes;
	}

	/**
	 * Add RTL class to admin body
	 *
	 * Adds RTL class when plugin language is set to an RTL language.
	 *
	 * @param string $classes Admin body classes
	 * @return string
	 */
	public static function add_rtl_admin_body_class( $classes ) {
		// Only add RTL class when plugin language is RTL
		if ( ! self::is_rtl() ) {
			return $classes;
		}

		// Restrict RTL body class to Vortem admin pages only
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page detection
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// If we are not on a Vortem admin page, do not modify the body classes
		if ( strpos( $current_page, 'vortem-ai' ) === false ) {
			return $classes;
		}

		$classes .= ' vortem-rtl';

		return $classes;
	}

	/**
	 * Get JavaScript translation strings.
	 *
	 * Legacy helper that previously surfaced the entire PHP-array translation
	 * map to JS. With the move to standard .mo files, JS strings should be
	 * registered via `wp_set_script_translations()` against the `vortem-ai`
	 * text domain. This method is retained as an empty-array fallback so
	 * existing localize calls don't break.
	 *
	 * @return array
	 */
	public static function get_js_strings() {
		return array();
	}

	/**
	 * Set language
	 *
	 * @param string $language Language code
	 * @return bool
	 */
	public static function set_language( $language ) {
		if ( ! isset( self::$supported_languages[ $language ] ) ) {
			return false;
		}

		// Save the language
		update_option( 'vortem_language', $language );
		// Mark that user has manually set the language (prevents WordPress sync)
		update_option( 'vortem_language_manually_set', true );

		self::$current_language = $language;

		return true;
	}

	// The custom `filter_gettext` / `filter_gettext_with_context` overrides were
	// removed. Translations now come exclusively from the .mo files loaded by
	// `load_plugin_textdomain('vortem-ai', ...)` in vortem.php.
}
