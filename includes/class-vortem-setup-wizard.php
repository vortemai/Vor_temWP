<?php
/**
 * Vortem Setup Wizard Class
 *
 * Handles the initial setup process for the plugin
 *
 * External Dependencies Used:
 * - WooCommerce (Automattic) - https://woocommerce.com/ | License: GPLv3+ | class_exists('WooCommerce') check
 * - WordPress HTTP API - wp_remote_request(), wp_remote_retrieve_response_code(), wp_remote_retrieve_body() for setup API calls
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load configuration class
require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

/**
 * Vortem Setup Wizard
 */
class Vortem_Setup_Wizard {

	/**
	 * Wizard steps
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Current step
	 *
	 * @var int
	 */
	private $current_step = 1;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_steps();
		$this->init_hooks();
	}

	/**
	 * Initialize wizard steps
	 */
	private function init_steps() {
		$this->steps = array(
			1 => array(
				'title'       => __( 'Welcome', 'vortem-ai' ),
				'description' => __( 'Welcome to vortem.ai Plugin', 'vortem-ai' ),
				'template'    => 'welcome',
			),
			2 => array(
				'title'       => __( 'Configuration', 'vortem-ai' ),
				'description' => __( 'Set your preferences', 'vortem-ai' ),
				'template'    => 'configuration',
			),
			3 => array(
				'title'       => __( 'Terms', 'vortem-ai' ),
				'description' => __( 'Accept terms', 'vortem-ai' ),
				'template'    => 'terms',
			),
			4 => array(
				'title'       => __( 'Complete', 'vortem-ai' ),
				'description' => __( 'Setup completed', 'vortem-ai' ),
				'template'    => 'complete',
			),
		);
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_wizard_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_vortem_wizard_next', array( $this, 'ajax_next_step' ) );
		add_action( 'wp_ajax_vortem_wizard_prev', array( $this, 'ajax_prev_step' ) );
		add_action( 'wp_ajax_vortem_wizard_accept_terms', array( $this, 'ajax_accept_terms' ) );
		add_action( 'wp_ajax_vortem_wizard_complete', array( $this, 'ajax_complete_wizard' ) );
		add_action( 'wp_ajax_vortem_wizard_switch_currency', array( $this, 'ajax_switch_currency' ) );
		add_action( 'wp_ajax_vortem_wizard_get_currencies', array( $this, 'ajax_get_currencies' ) );
		add_action( 'wp_ajax_vortem_wizard_restart', array( $this, 'ajax_restart_wizard' ) );
	}

	/**
	 * Add wizard page to admin menu
	 *
	 * The setup wizard can be skipped. The plugin remains usable without an account.
	 * When not connected, requests may be processed anonymously by the external service.
	 */
	public function add_wizard_page() {
		// Only show wizard if setup is not completed
		if ( ! $this->is_setup_completed() ) {
			add_submenu_page(
				null,
				__( 'Setup Wizard', 'vortem-ai' ),
				__( 'Setup Wizard', 'vortem-ai' ),
				'vortem_manage',
				'vortem-setup-wizard',
				array( $this, 'wizard_page' )
			);
		}
	}

	/**
	 * Enqueue wizard scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		// Debug: Log the hook name

		// Check if we're on the setup wizard page
		if ( strpos( $hook, 'vortem-setup-wizard' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'vortem-wizard',
			VORTEM_PLUGIN_URL . 'assets/js/wizard.js',
			array( 'jquery' ),
			VORTEM_VERSION,
			true
		);

		wp_enqueue_style(
			'vortem-wizard',
			VORTEM_PLUGIN_URL . 'assets/css/wizard.css',
			array(),
			VORTEM_VERSION
		);

		// Get current currency
		$current_currency = get_option( 'vortem_customer_currency', get_option( 'vortem_currency', 'USD' ) );

		$terms_accepted = get_option( 'vortem_terms_accepted', false );
		wp_localize_script(
			'vortem-wizard',
			'vortemWizard',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'vortem_wizard_nonce' ),
				'currentCurrency' => $current_currency,
				'termsAccepted'   => $terms_accepted,
				'hasSessionToken' => true,
				'strings'         => array(
					'loading'         => __( 'Loading...', 'vortem-ai' ),
					'error'           => __( 'An error occurred. Please try again.', 'vortem-ai' ),
					'success'         => __( 'Success!', 'vortem-ai' ),
					'next'            => __( 'Next', 'vortem-ai' ),
					'previous'        => __( 'Previous', 'vortem-ai' ),
					'back'            => __( 'Back', 'vortem-ai' ),
					'complete'        => __( 'Complete Setup', 'vortem-ai' ),
					'save'            => __( 'Save & Continue', 'vortem-ai' ),
					'accept'          => __( 'Accept & Continue', 'vortem-ai' ),
					'selectCurrency'  => __( 'Select Currency', 'vortem-ai' ),
					'search_currency' => __( 'Search by country or currency code', 'vortem-ai' ),
					'Restart'         => __( 'Restart', 'vortem-ai' ),
					'Restart Wizard'  => __( 'Restart Wizard', 'vortem-ai' ),
					'This will reset the setup process. Are you sure?' => __( 'This will reset the setup process. Are you sure?', 'vortem-ai' ),
					'Confirm Restart' => __( 'Confirm Restart', 'vortem-ai' ),
					'You can connect your account from Settings → Vortem Login → AliExpress Integration.' => __( 'You can connect your account from Settings → Vortem Login → AliExpress Integration.', 'vortem-ai' ),
				),
			)
		);
	}

	/**
	 * Display wizard page
	 */
	public function wizard_page() {
		// Clear any error notices that might have been set from other pages
		delete_transient( 'vortem_settings_error_notice' );
		delete_transient( 'vortem_settings_success_notice' );
		delete_transient( 'vortem_admin_success_notice' );
		delete_transient( 'vortem_admin_error_notice' );
		delete_transient( 'vortem_setup_complete_notice' );
		delete_transient( 'vortem_products_success_notice' );
		delete_transient( 'vortem_products_error_notice' );
		delete_transient( 'vortem_products_warning_notice' );

		// Step is validated below (1..N) and used only for display; no form submission.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL param for wizard step, validated below
		$this->current_step = isset( $_GET['step'] ) ? intval( wp_unslash( $_GET['step'] ) ) : 1;

		if ( $this->current_step < 1 || $this->current_step > count( $this->steps ) ) {
			$this->current_step = 1;
		}

		// Check if current language is RTL
		$is_rtl = false;
		if ( class_exists( 'Vortem_Translation_Manager' ) ) {
			$is_rtl = Vortem_Translation_Manager::is_rtl();
		}
		$rtl_class    = $is_rtl ? 'vortem-wizard-rtl' : '';
		$total_steps  = count( $this->steps );
		$is_last_step = $this->current_step === $total_steps;
		$logo_url     = VORTEM_PLUGIN_URL . 'assets/images/logo.png';
		$docs_url     = class_exists( 'Vortem_Config' ) ? Vortem_Config::get_docs_url() : '#';

		?>
		<div class="vortem-wizard-container">
			<div class="vortem-wizard-card vortem-wizard-split <?php echo esc_attr( $rtl_class ); ?>">

				<!-- Left rail: brand + vertical step nav + footer -->
				<aside class="wizard-rail" aria-label="<?php esc_attr_e( 'Setup progress', 'vortem-ai' ); ?>">
					<div class="wizard-rail-brand">
						<span class="wizard-rail-logo" aria-hidden="true">
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
						</span>
						<span class="wizard-rail-wordmark"><?php esc_html_e( 'Vortem', 'vortem-ai' ); ?></span>
					</div>

					<div class="wizard-rail-counter">
						<span class="wizard-rail-counter-eyebrow"><?php esc_html_e( 'Setup', 'vortem-ai' ); ?></span>
						<span class="wizard-rail-counter-value">
						<?php
						if ( $is_last_step ) {
							esc_html_e( 'All done', 'vortem-ai' );
						} else {
							/* translators: 1: current step number, 2: total step count */
							printf( esc_html__( 'Step %1$d of %2$d', 'vortem-ai' ), (int) $this->current_step, (int) $total_steps );
						}
						?>
						</span>
					</div>

					<ol class="wizard-rail-steps" role="list">
						<?php foreach ( $this->steps as $step_num => $step ) : ?>
							<?php
							$state_class = '';
							if ( $step_num === $this->current_step ) {
								$state_class = 'is-active';
							} elseif ( $step_num < $this->current_step ) {
								$state_class = 'is-done';
							} else {
								$state_class = 'is-pending';
							}
							?>
							<li class="wizard-rail-step <?php echo esc_attr( $state_class ); ?>"
								<?php
								if ( $step_num === $this->current_step ) :
									?>
									aria-current="step"<?php endif; ?>>
								<span class="wizard-rail-step-indicator" aria-hidden="true">
									<?php if ( $step_num < $this->current_step ) : ?>
										<svg viewBox="0 0 16 16" width="11" height="11" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3.5 8.5 6.5 11.5 12.5 4.5"></polyline></svg>
									<?php else : ?>
										<?php echo esc_html( (string) $step_num ); ?>
									<?php endif; ?>
								</span>
								<span class="wizard-rail-step-label"><?php echo esc_html( $step['title'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>

					<div class="wizard-rail-footer">
						<?php if ( $is_last_step ) : ?>
							<button type="button" class="wizard-rail-link" id="restart-wizard">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
								<span><?php esc_html_e( 'Restart Wizard', 'vortem-ai' ); ?></span>
							</button>
						<?php else : ?>
							<a class="wizard-rail-link" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
								<span><?php esc_html_e( 'Need help?', 'vortem-ai' ); ?></span>
							</a>
						<?php endif; ?>
					</div>

					<span class="wizard-rail-orb" aria-hidden="true"></span>
				</aside>

				<!-- Right pane: step content + actions -->
				<section class="wizard-pane">
					<div class="wizard-content-wrapper">
						<?php $this->render_step_content( $this->current_step ); ?>
					</div>

					<?php if ( ! $is_last_step ) : ?>
					<div class="wizard-navigation">
						<div class="wizard-nav-left">
							<?php if ( $this->current_step > 1 ) : ?>
								<button type="button" class="wizard-button wizard-button-secondary wizard-prev">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
									<span><?php esc_html_e( 'Back', 'vortem-ai' ); ?></span>
								</button>
							<?php endif; ?>
						</div>
						<div class="wizard-nav-right">
							<?php if ( $this->current_step === 3 ) : ?>
								<button type="button" class="wizard-button wizard-button-primary wizard-next" disabled>
									<span><?php esc_html_e( 'Accept & Continue', 'vortem-ai' ); ?></span>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
								</button>
							<?php else : ?>
								<button type="button" class="wizard-button wizard-button-primary wizard-next">
									<span><?php esc_html_e( 'Next Step', 'vortem-ai' ); ?></span>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
								</button>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
				</section>
			</div>
		</div>

		<!-- Restart Wizard Confirmation Modal -->
		<div id="restart-wizard-modal" class="vortem-wizard-modal" style="display: none;">
			<div class="vortem-wizard-modal-overlay"></div>
			<div class="vortem-wizard-modal-content">
				<div class="vortem-wizard-modal-header">
					<h3 class="vortem-wizard-modal-title"><?php esc_html_e( 'Confirm Restart', 'vortem-ai' ); ?></h3>
				</div>
				<div class="vortem-wizard-modal-body">
					<p><?php esc_html_e( 'This will reset the setup process. Are you sure?', 'vortem-ai' ); ?></p>
				</div>
				<div class="vortem-wizard-modal-footer">
					<button type="button" class="wizard-button wizard-button-secondary" id="restart-wizard-cancel">
						<?php esc_html_e( 'Cancel', 'vortem-ai' ); ?>
					</button>
					<button type="button" class="wizard-button wizard-button-primary" id="restart-wizard-confirm">
						<?php esc_html_e( 'Restart', 'vortem-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render step content
	 */
	private function render_step_content( $step ) {
		$step_data = $this->steps[ $step ];

		switch ( $step_data['template'] ) {
			case 'welcome':
				$this->render_welcome_step();
				break;
			case 'configuration':
				$this->render_configuration_step();
				break;
			case 'terms':
				$this->render_terms_step();
				break;
			case 'complete':
				$this->render_complete_step();
				break;
		}
	}

	/**
	 * Render welcome step
	 */
	private function render_welcome_step() {
		?>
		<div class="step-content welcome-step">
			<header class="step-header">
				<span class="step-eyebrow"><?php esc_html_e( 'Welcome', 'vortem-ai' ); ?></span>
				<h1 class="step-title"><?php esc_html_e( 'Let\'s get you started', 'vortem-ai' ); ?></h1>
				<p class="step-subtitle"><?php esc_html_e( 'Complete setup in about 2 minutes.', 'vortem-ai' ); ?></p>
			</header>

			<div class="setup-info-green" role="note">
				<svg class="setup-info-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="12" r="10"></circle>
					<path d="M12 16v-4"></path>
					<path d="M12 8h.01"></path>
				</svg>
				<div class="setup-info-text">
					<p class="setup-info-bold"><?php esc_html_e( 'Vortem\'s product import features need an AliExpress account.', 'vortem-ai' ); ?></p>
					<p><?php esc_html_e( 'You\'ll connect it later under Settings → Vortem → AliExpress Integration. No account yet? You can sign up from there too.', 'vortem-ai' ); ?></p>
				</div>
			</div>

			<div class="welcome-features-grid">
				<div class="welcome-feature-card">
					<div class="welcome-feature-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
							<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"></path>
						</svg>
					</div>
					<h3 class="welcome-feature-title"><?php esc_html_e( 'Quick Setup', 'vortem-ai' ); ?></h3>
					<p class="welcome-feature-description"><?php esc_html_e( 'Up and running in minutes.', 'vortem-ai' ); ?></p>
				</div>
				<div class="welcome-feature-card">
					<div class="welcome-feature-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
							<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
						</svg>
					</div>
					<h3 class="welcome-feature-title"><?php esc_html_e( 'Secure & Reliable', 'vortem-ai' ); ?></h3>
					<p class="welcome-feature-description"><?php esc_html_e( 'Built with WordPress security best practices.', 'vortem-ai' ); ?></p>
				</div>
				<div class="welcome-feature-card">
					<div class="welcome-feature-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
							<path d="m12 14 4-4"></path>
							<path d="M3.34 19a10 10 0 1 1 17.32 0"></path>
						</svg>
					</div>
					<h3 class="welcome-feature-title"><?php esc_html_e( 'Optimized Performance', 'vortem-ai' ); ?></h3>
					<p class="welcome-feature-description"><?php esc_html_e( 'Lightweight and tuned for WordPress.', 'vortem-ai' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render configuration step
	 */
	private function render_configuration_step() {
		$current_currency = get_option( 'vortem_currency', 'USD' );
		?>
		<div class="step-content configuration-step">
			<header class="step-header">
				<span class="step-eyebrow"><?php esc_html_e( 'Store basics', 'vortem-ai' ); ?></span>
				<h1 class="step-title"><?php esc_html_e( 'Pick your store currency', 'vortem-ai' ); ?></h1>
				<p class="step-subtitle"><?php esc_html_e( 'This is what your customers see at checkout. You can change it later.', 'vortem-ai' ); ?></p>
			</header>

			<div class="configuration-form">
				<div class="configuration-field">
					<label for="wizard-currency-select" class="configuration-label">
						<span class="vortem-wizard-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
						</span>
						<span><?php esc_html_e( 'Currency', 'vortem-ai' ); ?></span>
					</label>
					<div class="vortem-custom-currency-select" id="wizard-custom-currency-select" role="combobox" aria-haspopup="listbox" aria-expanded="false" tabindex="0">
						<div class="vortem-currency-select-display" id="wizard-currency-select-display">
							<span class="vortem-currency-flag-placeholder" aria-hidden="true"></span>
							<div class="vortem-currency-text">
								<div class="currency-line-1"><?php esc_html_e( 'Select Currency', 'vortem-ai' ); ?></div>
								<div class="currency-line-2"></div>
							</div>
							<svg class="vortem-currency-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
						</div>
						<div class="vortem-currency-select-dropdown" id="wizard-currency-select-dropdown" role="listbox">
							<div class="vortem-currency-loading"><?php esc_html_e( 'Loading currencies...', 'vortem-ai' ); ?></div>
							<div class="vortem-currency-dropdown-list"></div>
						</div>
					</div>
					<p class="configuration-helper">
						<span class="configuration-helper-bullet" aria-hidden="true">•</span>
						<span><?php esc_html_e( 'Used on product cards, cart, and checkout.', 'vortem-ai' ); ?></span>
					</p>
					<input type="hidden" id="wizard-currency-select" data-current="<?php echo esc_attr( $current_currency ); ?>" value="<?php echo esc_attr( $current_currency ); ?>" />
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render terms step
	 */
	private function render_terms_step() {
		$terms_url = class_exists( 'Vortem_Config' ) ? Vortem_Config::get_terms_url() : '#';
		?>
		<div class="step-content terms-step">
			<header class="step-header">
				<span class="step-eyebrow"><?php esc_html_e( 'Almost there', 'vortem-ai' ); ?></span>
				<h1 class="step-title"><?php esc_html_e( 'Review and accept', 'vortem-ai' ); ?></h1>
			</header>

			<div class="terms-content-box">
				<div class="terms-scroll" tabindex="0">
					<div class="terms-section">
						<h3 class="terms-section-title"><?php esc_html_e( '1. Service Availability', 'vortem-ai' ); ?></h3>
						<p class="terms-section-text"><?php esc_html_e( 'While we strive to maintain high service availability, we cannot guarantee uninterrupted service. We reserve the right to perform maintenance and updates as needed.', 'vortem-ai' ); ?></p>
					</div>
					<div class="terms-section">
						<h3 class="terms-section-title"><?php esc_html_e( '2. Support', 'vortem-ai' ); ?></h3>
						<p class="terms-section-text"><?php esc_html_e( 'Technical support is provided through our support channels. Response times may vary based on the complexity of the issue.', 'vortem-ai' ); ?></p>
					</div>
					<div class="terms-section">
						<h3 class="terms-section-title"><?php esc_html_e( '3. Updates', 'vortem-ai' ); ?></h3>
						<p class="terms-section-text"><?php esc_html_e( 'Plugin updates are provided to improve functionality and security. It is recommended to keep the plugin updated to the latest version.', 'vortem-ai' ); ?></p>
					</div>
				</div>
				<a class="terms-readmore" href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener noreferrer">
					<span><?php esc_html_e( 'Read the full Terms', 'vortem-ai' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
				</a>
			</div>

			<div class="terms-consent-stack">
				<label class="terms-checkbox-label" for="accept-terms">
					<input type="checkbox" id="accept-terms" class="terms-checkbox-input">
					<span class="terms-checkbox-custom" aria-hidden="true"></span>
					<span class="terms-checkbox-text">
						<span class="terms-checkbox-title"><?php esc_html_e( 'I accept the Terms & Conditions', 'vortem-ai' ); ?></span>
						<span class="terms-checkbox-description"><?php esc_html_e( 'You agree to be bound by these terms.', 'vortem-ai' ); ?></span>
					</span>
				</label>

				<label class="terms-checkbox-label" for="accept-data-processing">
					<input type="checkbox" id="accept-data-processing" class="terms-checkbox-input">
					<span class="terms-checkbox-custom" aria-hidden="true"></span>
					<span class="terms-checkbox-text">
						<span class="terms-checkbox-title"><?php esc_html_e( 'I consent to data processing', 'vortem-ai' ); ?></span>
						<span class="terms-checkbox-description">
						<?php
							echo wp_kses_post( __( 'I understand and agree that this plugin sends my site data (site URL, WordPress version, plugin version, currency preference, WooCommerce product data when importing products, order shipping information when processing orders, and plugin/theme metadata when using security features) to vortem.ai servers (https://c.vortem.ai) for processing, analytics, and service delivery. No data is sent before you provide explicit consent, and the plugin remains functional if you decline (features that depend on vortem.ai will simply be unavailable). Sending an inventory of installed plugins/themes for vulnerability lookup follows the same pattern used by Wordfence, Patchstack, WPScan, and Jetpack Protect.', 'vortem-ai' ) );
						?>
						</span>
					</span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render complete step
	 */
	private function render_complete_step() {
		$current_currency        = get_option( 'vortem_customer_currency', get_option( 'vortem_currency', 'USD' ) );
		$terms_accepted          = get_option( 'vortem_terms_accepted', false );
		$data_processing_consent = get_option( 'vortem_data_processing_consent', false );
		$docs_url                = class_exists( 'Vortem_Config' ) ? Vortem_Config::get_docs_url() : '#';
		?>
		<div class="step-content complete-step">
			<div class="complete-burst" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
			</div>

			<h1 class="step-title complete-title"><?php esc_html_e( 'You\'re all set!', 'vortem-ai' ); ?></h1>
			<p class="step-subtitle complete-description">
				<?php esc_html_e( 'Vortem is configured and ready. Connect AliExpress from Settings whenever you\'re ready.', 'vortem-ai' ); ?>
			</p>

			<div class="complete-summary">
				<div class="complete-summary-item">
					<span class="complete-summary-label"><?php esc_html_e( 'Currency', 'vortem-ai' ); ?></span>
					<span class="complete-summary-value"><?php echo esc_html( strtoupper( $current_currency ) ); ?></span>
				</div>
				<div class="complete-summary-item">
					<span class="complete-summary-label"><?php esc_html_e( 'Terms', 'vortem-ai' ); ?></span>
					<span class="complete-summary-value complete-summary-success">
						<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<?php echo esc_html( $terms_accepted ? __( 'Accepted', 'vortem-ai' ) : __( 'Pending', 'vortem-ai' ) ); ?>
					</span>
				</div>
				<div class="complete-summary-item">
					<span class="complete-summary-label"><?php esc_html_e( 'Data processing', 'vortem-ai' ); ?></span>
					<span class="complete-summary-value complete-summary-success">
						<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
						<?php echo esc_html( $data_processing_consent ? __( 'Consented', 'vortem-ai' ) : __( 'Pending', 'vortem-ai' ) ); ?>
					</span>
				</div>
			</div>

			<div class="complete-actions">
				<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="complete-button complete-button-secondary">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M15 3h6v6"></path>
						<path d="M10 14 21 3"></path>
						<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
					</svg>
					<span><?php esc_html_e( 'Documentation', 'vortem-ai' ); ?></span>
				</a>
				<a href="#" class="complete-button complete-button-primary" id="complete-and-go-to-dashboard">
					<span><?php esc_html_e( 'Go to Dashboard', 'vortem-ai' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for next step
	 */
	public function ajax_next_step() {

		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$current_step = isset( $_POST['current_step'] ) ? intval( wp_unslash( $_POST['current_step'] ) ) : 0;
		$next_step    = $current_step + 1;

		if ( $next_step > count( $this->steps ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid step.', 'vortem-ai' ) ) );
		}

		// When moving from step 3 (Terms) to step 4, save terms acceptance before redirecting
		if ( $current_step === 3 ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above
			$accepted = isset( $_POST['terms_accepted'] ) && sanitize_text_field( wp_unslash( $_POST['terms_accepted'] ) ) === 'true';
			if ( ! $accepted ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Please accept the terms and conditions and data processing consent before proceeding.', 'vortem-ai' ) ) );
			}
			update_option( 'vortem_terms_accepted', true );
			update_option( 'vortem_terms_accepted_date', current_time( 'mysql' ) );
			update_option( 'vortem_data_processing_consent', true );
			update_option( 'vortem_data_processing_consent_date', current_time( 'mysql' ) );
		}

		$redirect_url = admin_url( 'admin.php?page=vortem-setup-wizard&step=' . $next_step );

		wp_send_json_success(
			array(
				'next_step'    => $next_step,
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * AJAX handler for previous step
	 */
	public function ajax_prev_step() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$current_step = isset( $_POST['current_step'] ) ? intval( wp_unslash( $_POST['current_step'] ) ) : 0;
		$prev_step    = $current_step - 1;

		if ( $prev_step < 1 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid step.', 'vortem-ai' ) ) );
		}

		wp_send_json_success(
			array(
				'prev_step'    => $prev_step,
				'redirect_url' => admin_url( 'admin.php?page=vortem-setup-wizard&step=' . $prev_step ),
			)
		);
	}

	/**
	 * AJAX handler for authenticating with Vortem API
	 * No session token generation required - authentication is handled via Referrer header on API calls
	 */
	public function ajax_authenticate() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
		}

		// Get currency from POST or use saved/default
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

		// Trim whitespace and check if empty
		$currency = trim( $currency );

		if ( empty( $currency ) ) {
			// Try to get from saved options
			$currency = get_option( 'vortem_customer_currency', '' );
			if ( empty( $currency ) ) {
				$currency = get_option( 'vortem_currency', '' );
			}
		}

		// Final fallback: if still empty or invalid, use USD as default
		$currency = trim( $currency );
		if ( empty( $currency ) ) {
			$currency = 'USD';
		}

		// Save currency options locally. The plugin keeps its own currency
		// preference in `vortem_currency`; the WooCommerce store currency is
		// intentionally left under the merchant's control.
		update_option( 'vortem_currency', $currency );
		update_option( 'vortem_customer_currency', $currency );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Configuration saved successfully', 'vortem-ai' ),
			)
		);
	}

	/**
	 * AJAX handler for accepting terms
	 */
	public function ajax_accept_terms() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$accepted = isset( $_POST['accepted'] ) && sanitize_text_field( wp_unslash( $_POST['accepted'] ) ) === 'true';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$data_processing_accepted = isset( $_POST['data_processing_accepted'] ) && sanitize_text_field( wp_unslash( $_POST['data_processing_accepted'] ) ) === 'true';

		if ( ! $accepted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Terms must be accepted.', 'vortem-ai' ) ) );
		}

		if ( ! $data_processing_accepted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Data processing consent must be accepted.', 'vortem-ai' ) ) );
		}

		update_option( 'vortem_terms_accepted', true );
		update_option( 'vortem_terms_accepted_date', current_time( 'mysql' ) );
		update_option( 'vortem_data_processing_consent', true );
		update_option( 'vortem_data_processing_consent_date', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Terms and data processing consent accepted successfully', 'vortem-ai' ),
			)
		);
	}

	/**
	 * AJAX handler for completing wizard
	 */
	public function ajax_complete_wizard() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'vortem-ai' ) ) );
		}

		// Check if terms are accepted (DB or from client - user reached step 4 so they must have accepted)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above
		$accepted_from_client    = isset( $_POST['accepted'] ) && sanitize_text_field( wp_unslash( $_POST['accepted'] ) ) === 'true';
		$terms_accepted          = get_option( 'vortem_terms_accepted', false );
		$data_processing_consent = get_option( 'vortem_data_processing_consent', false );
		if ( ! $terms_accepted && $accepted_from_client ) {
			update_option( 'vortem_terms_accepted', true );
			update_option( 'vortem_terms_accepted_date', current_time( 'mysql' ) );
			$terms_accepted = true;
		}
		if ( ! $data_processing_consent && $accepted_from_client ) {
			update_option( 'vortem_data_processing_consent', true );
			update_option( 'vortem_data_processing_consent_date', current_time( 'mysql' ) );
			$data_processing_consent = true;
		}
		if ( ! $terms_accepted || ! $data_processing_consent ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please accept the terms and conditions and data processing consent before completing setup.', 'vortem-ai' ) ) );
		}

		// Get currency from POST or use saved/default
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

		// Trim whitespace and check if empty
		$currency = trim( $currency );

		if ( empty( $currency ) ) {
			// Try to get from saved options
			$currency = get_option( 'vortem_customer_currency', '' );
			if ( empty( $currency ) ) {
				$currency = get_option( 'vortem_currency', '' );
			}
		}

		// Final fallback: if still empty or invalid, use USD as default
		$currency = trim( $currency );
		if ( empty( $currency ) ) {
			$currency = 'USD';
		}

		// Save currency if provided
		if ( ! empty( $currency ) ) {
			// Update local currency options. The plugin's currency is stored
			// in `vortem_currency`; the WooCommerce store currency is left
			// unchanged so the merchant remains in full control.
			update_option( 'vortem_currency', $currency );
			update_option( 'vortem_customer_currency', $currency );

			// Update currency on API
			require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
			$api_client = new Vortem_Api_Client();
			$result     = $api_client->update_currency( $currency );

			if ( is_wp_error( $result ) ) {
				vortem_log( 'Setup wizard: currency sync to API failed: ' . $result->get_error_message() );
			}
		}

		$this->mark_setup_completed();

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Setup completed successfully', 'vortem-ai' ),
				'redirect_url' => admin_url( 'admin.php?page=vortem-owerview' ),
			)
		);
	}

	/**
	 * Check if setup is completed
	 */
	public function is_setup_completed() {
		return get_option( 'vortem_setup_completed', false );
	}

	/**
	 * Mark setup as completed
	 */
	public function mark_setup_completed() {
		update_option( 'vortem_setup_completed', true );
		update_option( 'vortem_setup_completed_date', current_time( 'mysql' ) );
	}

	/**
	 * Reset setup completion (for debugging/testing)
	 */
	public function reset_setup_completion() {
		delete_option( 'vortem_setup_completed' );
		delete_option( 'vortem_setup_completed_date' );
	}

	/**
	 * AJAX handler for switching currency in setup wizard
	 */
	public function ajax_switch_currency() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		// Get currency from POST data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		if ( ! isset( $_POST['currency'] ) || empty( $_POST['currency'] ) ) {
			wp_send_json_error( array( 'message' => 'Currency parameter is required' ) );
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by check_ajax_referer above
		$currency = sanitize_text_field( wp_unslash( $_POST['currency'] ) );

		// Get supported currencies from API to validate
		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
		$api_client          = new Vortem_Api_Client();
		$currencies_response = $api_client->fetch_currency_codes_public();

		if ( is_wp_error( $currencies_response ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Failed to validate currency: ', 'vortem-ai' ) . esc_html( $currencies_response->get_error_message() ),
				)
			);
			return;
		}

		// Extract currency data
		$currencies_data = isset( $currencies_response['data'] ) ? $currencies_response['data'] : $currencies_response;

		// Validate currency exists in API response
		$currency_found = false;
		if ( is_array( $currencies_data ) ) {
			foreach ( $currencies_data as $currency_item ) {
				$currency_code = isset( $currency_item['currency_code'] ) ? $currency_item['currency_code'] :
								( isset( $currency_item['code'] ) ? $currency_item['code'] : '' );
				if ( $currency_code === $currency ) {
					$currency_found = true;
					break;
				}
			}
		}

		if ( ! $currency_found ) {
			wp_send_json_error( array( 'message' => 'Unsupported currency' ) );
			return;
		}

		// Update local currency options first. The plugin's currency is
		// stored in `vortem_currency`; the WooCommerce store currency is left
		// unchanged so the merchant remains in full control.
		update_option( 'vortem_currency', $currency );
		update_option( 'vortem_customer_currency', $currency );

		// Try to update currency on API
		$result = $api_client->update_currency( $currency );

		if ( is_wp_error( $result ) ) {
			vortem_log( 'Setup wizard: currency sync to API failed: ' . $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'  => 'Currency changed successfully',
				'currency' => $currency,
			)
		);
	}

	/**
	 * AJAX handler for getting currencies from API (public endpoint)
	 */
	public function ajax_get_currencies() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-api-client.php';
		$api_client          = new Vortem_Api_Client();
		$currencies_response = $api_client->fetch_currency_codes_public();

		if ( is_wp_error( $currencies_response ) ) {
			wp_send_json_error(
				array(
					'message'    => $currencies_response->get_error_message(),
					'error_code' => $currencies_response->get_error_code(),
				)
			);
			return;
		}

		// Extract data field from API response if it exists
		$currency_codes = is_array( $currencies_response ) && isset( $currencies_response['data'] )
			? $currencies_response['data']
			: $currencies_response;

		wp_send_json_success( $currency_codes );
	}

	/**
	 * AJAX handler for restarting wizard
	 */
	public function ajax_restart_wizard() {
		check_ajax_referer( 'vortem_wizard_nonce', 'nonce' );

		if ( ! vortem_current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'vortem-ai' ) ) );
		}

		// Reset all wizard settings to defaults. Drop the language opt-in marker so the
		// translation manager re-derives vortem_language from get_locale() on next init.
		delete_option( 'vortem_language' );
		delete_option( 'vortem_language_manually_set' );
		update_option( 'vortem_currency', 'USD' );
		update_option( 'vortem_customer_currency', 'USD' );
		delete_option( 'vortem_terms_accepted' );
		delete_option( 'vortem_terms_accepted_date' );
		delete_option( 'vortem_setup_completed' );
		delete_option( 'vortem_setup_completed_date' );

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Wizard has been reset successfully.', 'vortem-ai' ),
				'redirect_url' => admin_url( 'admin.php?page=vortem-setup-wizard&step=1' ),
			)
		);
	}
}

