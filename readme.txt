=== Vortem AI ===
Contributors: vortem-ai
Donate link: https://vortem.ai/
Tags: woocommerce, analytics, product-import, email-marketing, security
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.13
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

All-in-One intelligent ecosystem connecting WordPress/WooCommerce to vortem.ai
service for product management, analytics, marketing and security.

== Description ==

Vortem AI connects your WordPress and WooCommerce site to vortem.ai external
service for product management, analytics, marketing, and security.

Important Notice: The plugin operates fully in standalone mode and is NOT
trialware. External API integration is used only for optional features. There
are NO feature restrictions, time limitations, usage quotas, or features locked
behind authentication — all features are fully available to all users. See
"External Service Usage" section below for details.

= Key Features =

  - Product Management: Import products from vortem.ai marketplace to
    WooCommerce with bulk import, category assignment, and attribute
    configuration
  - Analytics: Comprehensive WooCommerce analytics dashboard with revenue,
    order, and product performance tracking, fetched on demand from the
    vortem.ai API only when an administrator opens the dashboard
  - Email Marketing: Create and manage email campaigns with rich text editor,
    email list management, and campaign analytics
  - Security Scanner: Scan WordPress plugins and themes for vulnerabilities with
    detailed reports including CVE references and fix recommendations
  - Orders Management: View, filter, search, and export WooCommerce orders with
    customer information and order details
  - Multi-Language Support: 40+ languages supported with automatic detection and
    RTL support
  - Setup & Configuration: Guided setup wizard with optional account connection
    (works without authentication)

= External Service Usage (Required Disclosure) =

This plugin connects to external services for optional functionality. The plugin
operates independently and remains fully functional even when external services
are unavailable. No external service is required for plugin activation,
deactivation, or core administrative features.

Service Failure Handling (Graceful Degradation)

Plugin Stability Guarantee: This plugin is designed with complete resilience to
external service failures and will NOT:

  - Crash or cause WordPress fatal errors
  - Break WordPress core functionality
  - Affect WordPress admin panel accessibility
  - Cause frontend rendering issues
  - Generate PHP errors or JavaScript exceptions
  - Prevent plugin activation or deactivation

Fail-Safe Behavior: All external service communications are wrapped in safe
handlers with:

  - Try-catch error handling for all API calls
  - Timeout protection for network requests
  - Silent failure modes (no visible errors to end users)
  - Graceful degradation to empty/default states
  - No blocking operations on plugin initialization

Service Availability Impact:

| Service                   | Unavailable Behavior                                                   | Plugin Impact                                       |
| ------------------------- | ---------------------------------------------------------------------- | --------------------------------------------------- |
| API Service (c.vortem.ai) | API-driven features return empty data or "service unavailable" message | Plugin continues normally; admin remains accessible |

No External Dependencies: Plugin activation, WordPress admin access, settings
pages, and core WooCommerce functionality are never dependent on, or affected
by, external service availability.

External Services

1. API Service

Endpoint: https://c.vortem.ai

Purpose: Backend API service for optional features including:

  - Product import from vortem.ai marketplace
  - Security vulnerability scanning
  - Email marketing campaign management
  - Analytics data retrieval

Fail-Safe Behavior: If this service is unavailable, the plugin continues to
function normally, the WordPress admin panel remains fully accessible, and no
errors or crashes occur. API-driven features return empty data or a "service
unavailable" status; all non-API features (local admin pages, settings) work
normally.

Data Transmitted: See "Data Transmitted to vortem.ai API" section below.

Data Transmitted to vortem.ai API (https://c.vortem.ai)

The following data is transmitted to the vortem.ai API server when using
specific plugin features, and ONLY after explicit user consent (see "Privacy &
Data Processing Consent" section below).

Site Metadata (transmitted during setup/authentication):

  - WordPress site domain/URL (for account identification and service delivery)
  - WordPress version (for compatibility and security scanning)
  - Plugin version (for API compatibility)
  - Selected currency preference (for product pricing and order processing)
  - Client type identifier ("wordpress") and plugin slug ("vortem-ai")

Product Data (transmitted when importing/fetching products):

  - Product identifiers (SKU, product ID)
  - Product queries and search parameters (page, limit, category filters)
  - Currency preference (for price calculations)

Order Data (transmitted when processing WooCommerce orders, if enabled):

  - Shipping address: first name, last name, street address, city,
    state/province, postal code, country
  - Product items: SKU, quantity, order memo/notes (if provided)
  - Order identifier (generated order ID)

Security Data (transmitted when using security scanner feature):

  - Plugin metadata: file path, name, version, description, author, plugin URI,
    status, last modified date, WordPress/PHP version requirements
  - Theme metadata: stylesheet, template, name, version, status, author, author
    URI, theme URI, description, text domain, tags
  - WordPress core version and timestamp

Email Marketing Data (transmitted when creating/managing campaigns):

  - Email list names and recipient email addresses
  - Email campaign content (subject, body, HTML content)
  - Campaign metadata (send status, scheduling)

Technical/Environment Data (transmitted with all API requests):

  - Browser User-Agent string
  - Accept-Language header
  - HTTP Referer and Origin headers
  - Session token (if authenticated, for API authorization)

Data Received from vortem.ai API

  - Product information (descriptions, images, pricing, specifications)
  - Security vulnerability reports
  - Analytics and traffic analytics data
  - Email marketing tools and data

Service Information

  - Service Provider: vortem.ai
  - API Server: https://c.vortem.ai
  - Terms of Service: https://vortem.ai/en/terms
  - Privacy Policy: https://vortem.ai/en/privacy

Authentication

The plugin can be used in anonymous mode or with an authenticated session.
Authentication provides access to personalized data from the external service.
The setup wizard is optional and can be skipped.

Session Token Purpose: The session token is used ONLY for API authentication to
communicate with the vortem.ai service. It does NOT unlock, restrict, or control
any plugin features, and is NOT required for core administrative features or
plugin stability.

Privacy & Data Processing Consent

When Data is Transmitted:

  - During Setup: Site metadata (domain, WordPress version, plugin version,
    currency) is sent when you complete authentication in the setup wizard,
    after you accept the Data Processing Consent checkbox in step 3.
  - Manual Actions: Product data is sent when you manually import or fetch
    products. Order data is sent when you manually process an order through the
    plugin interface.
  - Scheduled Sync: If enabled, product synchronization may run on a schedule
    (requires your explicit configuration).
  - Security Scanning: Plugin/theme/WordPress core metadata is sent when you use
    the security scanner feature.
  - Email Marketing: Email data is sent when you create or manage email
    campaigns.

Important:

  - No data is sent before you provide explicit consent. The plugin requires you
    to accept the Data Processing Consent checkbox in step 3 of the Setup Wizard
    before any API calls to vortem.ai are made (except for the public currency
    list endpoint, which transmits no personal data). No automatic background
    sending occurs before consent is provided.
  - All API communications use HTTPS encryption. Session tokens are encrypted
    before storage using WordPress salts and OpenSSL (AES-256-CBC) or fallback
    XOR encryption.
  - Personal data (shipping addresses, email addresses) is only transmitted when
    you explicitly use features that require it (order processing, email
    marketing). You can use the plugin without enabling these features.

Your Rights: You can revoke consent at any time by disconnecting your account or
deactivating the plugin. Data already transmitted cannot be retrieved by the
plugin, but you may contact vortem.ai directly regarding data deletion requests
per their privacy policy.

= Requirements =

  - WordPress 6.0 or higher
  - PHP 7.4 or higher
  - WooCommerce 8.0 or higher (required for product import and order management
    features)
  - Active internet connection for API communication
  - Modern web browser with JavaScript enabled

= Technical Features =

  - RESTful API integration with automatic fallback servers
  - Secure session token management with AES-256-CBC encryption
  - Database tables for product synchronization tracking
  - AJAX-powered admin interface for seamless experience
  - Action Scheduler integration for background processing
  - WooCommerce HPOS (High-Performance Order Storage) compatibility
  - WordPress Coding Standards compliant
  - Responsive admin interface

= Use Cases =

  - E-commerce Store Owners: Import products from vortem.ai marketplace to
    quickly populate your store
  - Store Managers: Monitor sales analytics and make data-driven decisions
  - Marketing Teams: Create and manage email campaigns directly from WordPress
  - Security-Conscious Admins: Regular security scanning of plugins, themes, and
    WordPress core
  - Multi-Language Sites: Manage your store in 40+ languages with full
    translation support
  - Dropshipping Businesses: Import products with all details and manage orders
    efficiently

= Support & Documentation =

  - Online Documentation: https://vortem.ai/en/docs
  - Support Portal: https://vortem.ai/en/support

== External services ==

This plugin relies on one external service. Nothing is transmitted before the
administrator accepts the Data Processing Consent checkbox in step 3 of the
Setup Wizard. The single exception is the public currency-codes endpoint,
which transmits no personal data.

= vortem.ai API (https://c.vortem.ai) =

What it is: vortem.ai is the backend service that powers product import,
catalog enrichment (including AI-assisted product description, FAQ and email
content generation), security vulnerability scanning, traffic analytics, and
email marketing campaign management.

When data is sent: Only after explicit administrator consent, and only when
the corresponding feature is invoked (manual product import, manual order
forwarding, scheduled sync if you enable it, opening the security scanner,
creating an email campaign, etc.). The plugin never phones home on
activation, on plugin load, on the public-facing site, or on any anonymous
visitor request.

What is sent (summary; full breakdown in the "External Service Usage" section
under Description):

  - Site metadata: site URL, WordPress version, WooCommerce version, plugin
    version, selected currency, plugin slug.
  - Product / order data: only when you trigger an import, fetch, or order
    forwarding action. Order data includes shipping address fields, line
    items, and order memo if you opt in to order forwarding.
  - Security scan data: plugin/theme/WordPress metadata when you run the
    scanner.
  - Email marketing data: list names, recipient addresses, and campaign
    bodies when you create or send a campaign.
  - Authentication: an encrypted session token if you connect an account.

AI processing notice: vortem.ai's product enrichment, FAQ generation, and
email-content features run inside the vortem.ai backend and may use third-
party large-language-model providers as sub-processors. Any product, FAQ, or
email content you send to those features is processed by AI on the vortem.ai
side. The plugin itself does not contact OpenAI, Anthropic, Google, or any
other AI provider directly; all AI processing is mediated by the vortem.ai
API and falls under vortem.ai's privacy policy.

What is received: product descriptions / images / pricing, security
vulnerability reports, analytics readings, email-campaign tooling responses.

Provider, terms, privacy:

  - Service Provider: vortem.ai
  - Endpoint: https://c.vortem.ai
  - Terms of Service: https://vortem.ai/en/terms
  - Privacy Policy: https://vortem.ai/en/privacy

You can revoke consent at any time by disconnecting the account from the
plugin's Settings page or deactivating the plugin. Uninstalling the plugin
deletes every option, transient, and user-meta entry the plugin created.

== Installation ==

= Automatic Installation =

1.  Log in to your WordPress admin panel
2.  Go to Plugins → Add New
3.  Search for "vortem.ai"
4.  Click "Install Now" and then "Activate"
5.  Navigate to the vortem.ai menu in the WordPress admin
6.  Complete the setup wizard (optional) or skip to use in anonymous mode

= Manual Installation =

1.  Download the plugin ZIP file
2.  Log in to your WordPress admin panel
3.  Go to Plugins → Add New → Upload Plugin
4.  Choose the downloaded ZIP file and click "Install Now"
5.  Click "Activate Plugin"
6.  Navigate to the vortem.ai menu in the WordPress admin
7.  Complete the setup wizard (optional) or skip to use in anonymous mode

= Initial Setup =

After activation, you can:

1.  Run the Setup Wizard (Recommended):

      - Navigate to vortem.ai → Setup Wizard
      - Review and accept Terms & Conditions
      - Optionally connect your vortem.ai account
      - Complete setup to unlock personalized features

2.  Skip Setup (Anonymous Mode):

      - Use the plugin without authentication
      - Some data and responses may vary based on external service availability
      - Can connect account later from Settings

= Configuration =

  - Go to vortem.ai → Settings to configure:
      - Session token (if connecting account)
      - Products per page display
      - Language preferences
      - API settings (advanced)

== Frequently Asked Questions ==

= Do I need a vortem.ai account to use this plugin? =

No. The plugin can be used without an account in anonymous mode with full
functionality. Connecting an account (optional) provides access to additional
features and personalized data from the external service.

= Does this plugin have any feature restrictions or trial limitations? =

No. This plugin has NO feature restrictions, NO trial limitations, and NO locked
features. All functionality is fully available to all users. The session token
(optional) is used ONLY for API authentication purposes and does not control or
restrict any plugin features.

= Is WooCommerce required? =

Yes, WooCommerce 8.0 or higher is required for product import, order management,
and analytics features. The plugin will show an admin notice if WooCommerce is
not installed.

= Does the plugin work with other e-commerce platforms? =

No, this plugin is specifically designed for WordPress and WooCommerce
integration.

= What data does the plugin send to external servers? =

The plugin sends: site domain, plugin version, session token (if authenticated),
product queries, security scan metadata, and any content you submit through the
plugin UI (email campaigns). See "External Service Usage" section for
complete details.

= Is my data secure? =

Yes. All communications with vortem.ai API use HTTPS encryption. Session tokens
are encrypted using WordPress salts and OpenSSL (AES-256-CBC) before storage. No
sensitive data like payment information or customer passwords is transmitted.

= Can I use the plugin offline? =

The plugin operates fully in standalone mode for administrative and management
features such as Orders, Settings, Overview, and Admin UI. An active internet
connection is required only for optional data synchronization, product import,
analytics, and external service communication.

= Which languages are supported? =

The plugin supports 40+ languages including English, Spanish, German, French,
Italian, Portuguese, Russian, Arabic, Persian, Chinese, Japanese, Korean,
Turkish, Polish, Dutch, Swedish, and many more. RTL (Right-to-Left) is supported
for Arabic, Persian, Urdu, and Hebrew.

= How do I import products? =

1.  Go to vortem.ai → Products
2.  Browse or search for products in the vortem.ai marketplace
3.  Click "Import" on desired products
4.  Products are imported as WooCommerce drafts for review
5.  Edit and publish products from WooCommerce → Products

= Can I modify imported products? =

Yes. All imported products are created as WooCommerce drafts, allowing you to
review, edit, and customize them before publishing.

= How does the security scanner work? =

The security scanner collects metadata about your installed plugins, themes, and
WordPress core version, sends it to the vortem.ai security API, and receives
vulnerability reports matched against a comprehensive security database. Results
are displayed with CVE references, severity ratings, and fix recommendations.

No plugin/theme files or WordPress files are uploaded. Only version numbers and
identifiers are transmitted.

= Does the plugin slow down my site? =

No. Admin assets are only loaded on vortem.ai admin pages. Frontend impact is
minimal. Background processes use WordPress Action Scheduler for efficient
resource usage.

= Can I export analytics data? =

Yes. The Analytics page includes CSV export functionality for all metrics
including orders, revenue, products, and date ranges.

= Is the plugin compatible with WordPress Multisite? =

The plugin is designed for single-site installations. Multisite compatibility
has not been extensively tested.

= How often is the plugin updated? =

The plugin is actively maintained with regular updates for bug fixes, security
patches, and new features.

= Where can I get support? =

  - Online documentation: https://vortem.ai/en/docs
  - Support portal: https://vortem.ai/en/support
  - Plugin support forum on WordPress.org

== Changelog ==

= 1.0.13 =

  - Hardened: `uninstall.php` now performs a complete `vortem_*` option,
    transient, and user-meta sweep instead of relying on a hand-maintained
    list of keys that had drifted from the codebase. Action Scheduler jobs
    in the `vortem` group are also unscheduled.
  - Hardened: Removed `load_plugin_textdomain('vortem-ai', ...)` and the
    associated `init` hook. WordPress 6.7+ loads translations for
    WordPress.org-hosted plugins automatically (just-in-time), and calling
    `load_plugin_textdomain` early now triggers a `_doing_it_wrong()` notice.
  - Hardened: Added a top-level `== External services ==` section in the
    readme, including an AI processing notice covering the AI-assisted
    product / FAQ / email content features delivered via the vortem.ai API
    backend (per the WordPress AI Guidelines published 2026-02-01).
  - Cleanup: Removed 20 `console.log()` debug statements from inline JS in
    the products dashboard. The shipped JS no longer writes verbose AJAX
    response dumps to the browser console. The single remaining `console.warn`
    is inside an SVG-normalize `try/catch` and is kept for legitimate error
    surfacing.
  - Hardened: Consolidated all `localhost` / `127.0.0.1` / `::1` literals
    into a single private `Vortem_Config::is_local_hostname()` helper, so
    Plugin Check sees the dev-environment-detection intent in one place
    instead of scattered comparisons.
  - Compatibility: Declared compatibility with WooCommerce's block-based
    Cart and Checkout (`cart_checkout_blocks` feature) alongside the
    existing HPOS declaration. The plugin does not extend the cart or
    checkout flow, so it is compatible by construction.
  - No functional behavior change.

= 1.0.12 =

  - Hardened: Removed an `echo $variable` pattern in the setup wizard's step
    list (a literal-string ternary that nonetheless matched the unescaped-output
    rule). Conditional `aria-current="step"` is now emitted via an `if/endif`
    block so no `$variable` is echoed.
  - Hardened: All `in_array()` call sites now pass `true` as the strict
    comparison argument, eliminating the loose-comparison risk class.
  - Hardened: Replaced `@wp_delete_file()` error-silencer calls in the product
    image sideload paths with explicit `file_exists()` guards, so temp-file
    cleanup no longer suppresses PHP warnings.
  - Hardened: Replaced `urlencode()` with `rawurlencode()` for query-component
    encoding in the analytics and admin page-speed integration paths
    (RFC 3986-compliant percent-encoding).
  - Hardened: Replaced one remaining `==` loose comparison in the product
    sync flow with strict `===`.
  - Code style: Cleaned up brace spacing and assignment alignment surfaced
    by WordPress Coding Standards. No functional behavior change.

= 1.0.11 =

  - Removed: Frontend Matomo tracking script injection. The plugin no longer
    loads any analytics tracking script on the public-facing site.
  - Removed: Frontend Chatbot widget injection (widget.vortem.nl/embed.js). The
    plugin no longer loads any third-party widget script on the public-facing
    site.
  - Removed: TikTok product video stream proxy. The plugin no longer fetches
    or echoes binary remote content; the TikTok product list itself remains.
  - Removed: Silent overwrites of WooCommerce's `woocommerce_currency` option.
    The plugin now stores its own currency preference in `vortem_currency` and
    leaves the WooCommerce store currency under the merchant's control.
  - Removed: Cached Matomo tracking script option (vortem_matomo_tracking_script)
    and chatbot widget options (vortem_widget_*). These are also cleaned up on
    uninstall for installs that previously stored them.
  - Removed: External Service entries for analytics.vortem.ai and
    widget.vortem.nl from readme — those endpoints are no longer contacted by
    the plugin. The only external service remaining is the vortem.ai API
    (c.vortem.ai), which is invoked only after explicit administrator consent
    in Step 3 of the Setup Wizard.
  - Removed: Dead `Vortem_Analytics::activate/deactivate` methods that scheduled
    a WP-Cron event (`vortem_analytics_daily`) which had no consumer. Replaces
    the only raw `wp_schedule_event` call in the plugin.
  - Added: Phone-home gate (`Vortem_Api_Client::has_consent`) at every external
    HTTP entry point — `Vortem_Api_Client::make_request`, the bypass methods
    (`fetch_products_from_category`, `get_product_seo_content`, server health
    probe), `Vortem_Email_Marketing_Api::make_request`,
    `Vortem_Analytics::rest_overview_security_vulns`,
    `Vortem_Analytics::rest_overview_insights_performance`,
    `Vortem_Analytics::rest_matomo_proxy`, and
    `Vortem_Product_Fetcher::download_and_attach_image`. Every entry point
    short-circuits with a `vortem_no_consent` `WP_Error` until the
    `vortem_data_processing_consent` option is `true`. The single exception
    is `Vortem_Api_Client::fetch_currency_codes_public()`, which transmits no
    PII and is documented as the public currency endpoint.
  - Fixed: All `SHOW TABLES LIKE '{$table}'` checks in the analytics layer now
    use `$wpdb->prepare("SHOW TABLES LIKE %s", $table)` instead of relying on
    a phpcs:ignore directive.
  - Fixed: readme.txt header — the `Tags` and `Requires at least` fields had
    been line-wrapped together; rewritten so each header field is on its own
    line.
  - Fixed: Runtime fatal in admin/partials/security-results-page.php caused by
    a call to a non-existent function (esc_js__). Replaced with the standard
    __() since the strings are JSON-encoded by wp_localize_script.
  - Fixed: Phone-home gate now also covers every direct `wp_remote_*` call in
    the admin AJAX layer (`ajax_send_security_data`, `ajax_get_security_results`,
    `ajax_get_insights`, `ajax_refetch_insights`,
    `ajax_faq_delete_faqs_by_category`, `ajax_get_sentiment_data`) and every
    image-downloading method in `Vortem_Product_Fetcher`, `Vortem_Product_Manager`,
    and `Vortem_Product_Creator`. The cron handler `cron_sync_products` is
    also gated so a stale schedule cannot phone home before consent.
  - Fixed: All `'sslverify' => false` overrides removed. Every external HTTP
    call now uses the WordPress default of full TLS verification.
  - Fixed: License header normalized to `License: GPLv2 or later` in both
    plugin header and readme.
  - Fixed: All `error_log()` calls replaced with the existing `vortem_log()`
    helper, which is a no-op unless `WP_DEBUG` is enabled and routes through
    a single `phpcs:ignore` for `WordPress.PHP.DevelopmentFunctions.error_log_*`.
  - Fixed: All `json_encode()` calls replaced with `wp_json_encode()`.
  - Fixed: `getenv('VORTEM_API_URL')` and `getenv('VORTEM_DOMAIN')` replaced
    with `defined()` constant overrides — site owners can now set
    `VORTEM_API_URL` / `VORTEM_DOMAIN` in `wp-config.php`.
  - Removed: Dead `tracking-script` endpoint mapping (`matomo_tracking_script`)
    in `Vortem_Config` and its `rest_matomo_proxy` branch, both of which
    became unreachable when the frontend tracker was removed.
  - Cleanup: Consolidated two `register_activation_hook()` calls into a single
    `Vortem_AI::activate()` entry point.
  - Removed: Debug scaffolding that should not have shipped — `test_import()`
    and `ajax_test_import()` (read a hard-coded `product_respond.json` and
    created a dummy product), `test_category_api_curl()` (built a curl-command
    string whose only caller silently discarded the result), and the entire
    `Vortem_Debug_Logger` class (had zero call sites and wrote to disk on
    every order).
  - Fixed: `$_SERVER['SERVER_PORT']` is now `sanitize_text_field( wp_unslash( … ) )`
    before use, matching every other superglobal access.
  - Annotated: Each of the four legitimate `file_put_contents()` calls (image
    attachment writes into `wp_upload_dir()`) now carries a documented
    `phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_*`
    with the safety rationale (path is rooted in `wp_upload_dir()` and
    `realpath()`-validated). Same for the `base64_decode()` of incoming image
    payloads, whose output is validated with `getimagesizefromstring()` before
    any disk write.
  - Cleanup: `.gitignore` extended to exclude `*.eml`, `.DS_Store`, IDE
    folders, swap/backup files so dev artifacts cannot accidentally end up
    in the distribution zip.

= 1.0.10 =

  - Added: Comprehensive documentation and transparent explanation of how Matomo
    analytics and the chatbot widget operate, including detailed privacy
    clarification addressing "phoning home" concerns
  - Fixed: PHP syntax errors resolved for improved compatibility and stability

= 1.0.9 =

  - Fixed: Repository URL 404 error
  - Fixed: Replaced direct script printing with correct wp_enqueue_script usage
    for proper WordPress standards compliance
  - Added: Documentation for the external Vortem API service including privacy
    policy and terms of service links
  - Fixed: Hardcoded paths replaced with WordPress directory functions
    (plugin_dir_path, plugin_dir_url, etc.)
  - Fixed: Output escaping implemented using wp_kses_post and esc_html to
    prevent XSS vulnerabilities
  - Fixed: All constants prefixed with VORTEM_ to prevent naming conflicts
  - Fixed: SQL queries secured using $wpdb->prepare and input whitelisting to
    prevent SQL injection
  - Removed: All license checks and trialware logic to comply with WordPress.org
    standards
  - Fixed: Inline JavaScript sanitized for security
  - Changed: Architecture update - session tokens completely removed; plugin now
    uses HTTP Referer header to send domain address for client identification
    when requesting the Vortem API

= 1.0.8 =

  - Fixed: Full WordPress Plugin Review compliance improvements based on
    WordPress.org feedback
  - Fixed: External service documentation updated and clarified (API, Widget,
    Analytics), fully documenting data flow, consent mechanism, and graceful
    degradation behavior
  - Fixed: Ensured plugin stability under external service failure scenarios
    (fail-safe architecture verified)
  - Fixed: Improved security compliance:
      - Strengthened input sanitization for $_POST, $_GET, $_FILES, and $_SERVER
        usage
      - Enhanced output escaping using esc_html, esc_attr, wp_kses_post where
        required
      - Verified JSON handling safety and data validation flow
  - Fixed: SQL injection protection — verified correct usage of $wpdb->prepare()
    for all dynamic queries and replaced unsafe inline SQL concatenations with
    prepared statements
  - Fixed: WordPress coding standards compliance — replaced any remaining direct
    script/style outputs with wp_enqueue_script, wp_enqueue_style,
    wp_add_inline_script, wp_add_inline_style
  - Fixed: External library compliance — ensured all bundled libraries meet
    WordPress.org guidelines
  - Fixed: WordPress directory compliance — verified plugin uses unique
    prefixes, corrected any non-prefixed globals, and ensured no conflicts with
    core or third-party plugins
  - Fixed: External widget integration documentation added — documented "Vortem
    AI Chatbot Widget" behavior and RAG-based functionality, and confirmed
    widget is non-blocking and does not impact admin or frontend rendering even
    if unavailable
  - Removed: XLSX package removed from plugin

= 1.0.7 =

  - Fixed: REST API namespace updated to vortem/v1 for all endpoints
  - Fixed: Improved SQL query security with proper use of $wpdb->prepare()
  - Fixed: Enhanced input sanitization for $_SERVER, $_POST, and $_GET variables
  - Fixed: Improved output escaping with esc_html, esc_attr, and wp_kses
    functions
  - Updated: SheetJS/XLSX library to version 0.20.3

= 1.0.6 =

  - Fixed: Not using wp_enqueue for JS/CSS - Converted all direct  and  tags to
    proper WordPress enqueue methods (wp_enqueue_script, wp_enqueue_style,
    wp_add_inline_script, wp_add_inline_style) using appropriate hooks
    (wp_enqueue_scripts for front-end, admin_enqueue_scripts for admin)
  - Fixed: Outdated Libraries - Updated Lucide (lucide.js) to latest stable
    version

= 1.0.5 =

  - Enhanced security scanner with improved vulnerability matching
  - Added multi-language support for 40+ languages with RTL
  - Improved product import with better error handling
  - Added Matomo Traffic Analytics integration
  - Fixed email marketing campaign sending issues
  - Optimized database queries for better performance
  - Added WooCommerce HPOS compatibility
  - Improved Action Scheduler integration to prevent deadlocks
  - Updated vendor libraries (Chart.js, Lucide)
  - Fixed session token encryption on some hosting environments
  - Improved admin UI responsiveness
  - Added CSV export for analytics data
  - Bug fixes and performance improvements

= 1.0.4 =

  - Added Matomo Traffic Analytics with real-time tracking
  - Implemented FAQ management system
  - Enhanced email marketing with list management and campaign functionality
  - Implemented security scanner for plugins and themes
  - Added WordPress core vulnerability detection
  - Enhanced analytics with more metrics
  - Fixed product import for variable products
  - Improved product import speed
  - Added session token encryption
  - Fixed RTL layout issues
  - Improved translation loading performance
  - Bug fixes

= 1.0.3 =

  - Added orders management page
  - Implemented product synchronization
  - Enhanced analytics dashboard
  - Added setup wizard
  - Improved error handling
  - Bug fixes

= 1.0.2 =

  - Added product import functionality
  - Implemented basic analytics
  - Enhanced API client with fallback servers
  - Bug fixes

= 1.0.1 =

  - Initial release improvements
  - Bug fixes

= 1.0.0 =

  - Initial release

== Upgrade Notice ==

= 1.0.13 = Compliance and cleanup pass aligned with the late-2025 / early-2026
WordPress.org plugin review guidance. Complete uninstall sweep (every option
the plugin ever creates is removed). Removed `load_plugin_textdomain` (handled
automatically by WP 6.7+). New top-level `== External services ==` section
plus an AI-processing disclosure for the vortem.ai backend. Stripped 20 dev
`console.log` statements from inline admin JS. No functional changes.

= 1.0.12 = Defense-in-depth hardening pass. Removes the last `echo $variable`
pattern in the setup wizard, adds strict comparison to every `in_array()`,
replaces `@`-silenced temp-file deletes with explicit `file_exists()` guards,
switches `urlencode()` to `rawurlencode()` for query-component encoding, and
fixes one remaining loose `==`. No functional or behavioral changes.

= 1.0.11 = Compliance update. Removed frontend tracking, chatbot injection,
the TikTok video proxy, and silent WooCommerce currency overwrites. Every
external HTTP request now short-circuits until the admin accepts data
processing consent in the Setup Wizard. Public currency endpoint is exempt.

= 1.0.10 = Added comprehensive Matomo and Chatbot transparency documentation;
fixed minor PHP syntax issues for improved compatibility.

= 1.0.9 = Major architectural and compliance update - resolved repository URL
issues, implemented proper WordPress coding standards (wp_enqueue_script, output
escaping, SQL security), and removed all license/trialware logic. Recommended
for all users.

= 1.0.7 = Security and compliance update - fixed REST API namespace, improved
SQL query security, enhanced input sanitization and output escaping. Recommended
for all users.

= 1.0.6 = Important update with WordPress coding standards compliance - fixed
JS/CSS enqueuing issues and updated outdated libraries. Recommended for all
users.

= 1.0.5 = Important update with security scanner improvements, multi-language
support, Matomo Traffic Analytics integration, WooCommerce HPOS compatibility,
and performance optimizations. Recommended for all users.

= 1.0.4 = Major feature additions including Matomo Traffic Analytics, FAQ
management, email marketing, and security scanner. Recommended update.

== Privacy Policy ==

This plugin connects to the vortem.ai external service (https://c.vortem.ai) and
transmits data as described in the "External Service Usage" section above.

Plugin Stability and External Services: This plugin is designed to function
independently of external service availability. See the "Service Failure
Handling (Graceful Degradation)" section above for full details on fail-safe
behavior and service availability impact.

Data Collection: The plugin does not collect or store personal data locally
beyond: encrypted session tokens (in WordPress options), product synchronization
status (in custom database table), and setup wizard completion flags (in
WordPress options).

External Service: Data transmitted to vortem.ai is subject to their privacy
policy: https://vortem.ai/en/privacy

User Rights: Users can delete all plugin data by deactivating then deleting the
plugin (which triggers uninstall cleanup). This removes all stored session
tokens, options, and custom database tables.

No Service Dependency: This plugin does NOT require any external service to be
available for plugin activation, deactivation, admin panel access, settings
pages, or core functionality. All external service integrations are optional and
designed with graceful degradation. If an external service is unavailable, the
affected features will simply return empty data or display a "service
unavailable" message without impacting the rest of the plugin or WordPress core.

== Technical Details ==

Database Tables Created:

  - {prefix}_vortem_products - Stores imported product data and synchronization
    status

WordPress Options Created:

  - vortem_session_token - Encrypted session token for API authentication
  - vortem_setup_completed - Setup wizard completion flag
  - vortem_products_per_page - Products display preference
  - Various transient cache entries (automatically expire)

REST API Endpoints Registered:

  - /wp-json/vortem/v1/metrics/ — analytics metrics
  - /wp-json/vortem/v1/export/ — CSV export
  - /wp-json/vortem/v1/analytics/metrics/ — alternative metrics endpoint

Scheduled Events: vortem_sync_products (product sync, when manually triggered).
Session token validation is performed on-demand; no scheduled cron for auth.

External Libraries Used:

  - Chart.js 4.5.1 - Analytics charts and visualizations (bundled) - MIT License
    https://github.com/chartjs/Chart.js (LICENSE: /blob/master/LICENSE.md)
  - Lucide 1.7.0 - UI icons (bundled, non-minified) - ISC License
    https://github.com/lucide-icons/lucide (LICENSE: /blob/master/LICENSE)
  - React 18.3.1 - UI framework (loaded via WordPress core wp-scripts handles,
    NOT bundled) - MIT License
    https://github.com/facebook/react (LICENSE: /blob/main/LICENSE)
  - React DOM 18.3.1 - React DOM renderer (loaded via WordPress core wp-scripts
    handles, NOT bundled) - MIT License
    https://github.com/facebook/react (LICENSE: /blob/main/LICENSE)

All external libraries are bundled locally (no CDN dependencies) and include
source code or public source URLs for WordPress.org compliance.

== Development ==

This plugin uses build tools (e.g. webpack/npm).

Build Instructions:

1.  npm install
2.  npm run build

Third-Party Library Sources: All third-party JavaScript libraries are bundled in
assets/vendor/ with their LICENSE files. Source URLs are listed in "External
Libraries Used" above.

== Developer Information ==

Support: https://vortem.ai/en/support Documentation: https://vortem.ai/en/docs

Filters Available:

  - vortem_products_per_page - Modify products per page display
  - vortem_api_timeout - Modify API request timeout
  - Various Action Scheduler filters for background processing

Actions Available:

  - vortem_after_product_import - Triggered after product import
  - vortem_after_security_scan - Triggered after security scan completion

