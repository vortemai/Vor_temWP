<?php
/**
 * Insights Page Template
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="insights-workspace-wrap">
	<!-- Background orbs -->
	<div class="insights-bg-orb insights-bg-orb-1" aria-hidden="true"></div>
	<div class="insights-bg-orb insights-bg-orb-2" aria-hidden="true"></div>

	<!-- Hero header banner -->
	<header class="insights-hero">
		<div class="insights-hero-inner">
			<div class="insights-hero-left">
				<div class="insights-hero-logo">
					<?php
					$vortem_logo_path = defined( 'VORTEM_PLUGIN_DIR' ) ? VORTEM_PLUGIN_DIR . 'assets/images/logo.png' : dirname( dirname( __DIR__ ) ) . '/assets/images/logo.png';
					$vortem_logo_url  = defined( 'VORTEM_PLUGIN_URL' ) ? VORTEM_PLUGIN_URL . 'assets/images/logo.png' : '';
					if ( ! empty( $vortem_logo_url ) && file_exists( $vortem_logo_path ) ) :
						?>
						<img src="<?php echo esc_url( $vortem_logo_url ); ?>" alt="" class="insights-hero-logo-img" />
						<?php
					else :
						$vortem_logo_placeholder = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="%2308B8B8"><rect width="64" height="64" rx="12" fill="%2308B8B8" opacity=".15"/><path d="M32 18l12 8v12l-12 8-12-8V26l12-8z" fill="%2308B8B8"/></svg>' );
						?>
						<img src="<?php echo esc_url( $vortem_logo_placeholder ); ?>" alt="" class="insights-hero-logo-img" />
					<?php endif; ?>
				</div>
				<div class="insights-hero-text">
					<span class="insights-hero-eyebrow"><?php echo esc_html__( 'Performance', 'vortem-ai' ); ?></span>
					<h1 class="insights-hero-title"><?php echo esc_html__( 'Insights', 'vortem-ai' ); ?></h1>
				</div>
			</div>
			<div class="insights-hero-right">
				<span class="insights-hero-live-badge">
					<span class="insights-hero-live-dot" aria-hidden="true"></span>
					<?php echo esc_html__( 'Live', 'vortem-ai' ); ?>
				</span>
				<span class="insights-hero-date">
					<?php echo esc_html( wp_date( 'M j, Y' ) ); ?>
				</span>
				<span class="insights-last-updated-value" id="insights-last-updated-value" style="display:none;"></span>
			</div>
		</div>
		<span class="insights-hero-orb" aria-hidden="true"></span>
	</header>

	<main class="insights-main">
		<!-- Quick Stats -->
		<div class="insights-quick-stats" id="insights-quick-stats">
			<!-- Stats will be inserted here -->
		</div>

		<!-- Device Selection Bar -->
		<div class="insights-device-bar">
			<div class="insights-device-bar-content">
				<div class="insights-device-bar-spacer"></div>
				
				<!-- Segmented Control -->
				<div class="insights-segmented-control">
					<button class="insights-segmented-btn active" data-device="desktop" id="btn-desktop">
						<svg xmlns="http://www.w3.org/2000/svg" class="insights-segmented-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
							<line x1="8" y1="21" x2="16" y2="21"></line>
							<line x1="12" y1="17" x2="12" y2="21"></line>
						</svg>
						<span class="insights-segmented-text"><?php echo esc_html__( 'Desktop', 'vortem-ai' ); ?></span>
					</button>
					<button class="insights-segmented-btn" data-device="mobile" id="btn-mobile">
						<svg xmlns="http://www.w3.org/2000/svg" class="insights-segmented-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
							<line x1="12" y1="18" x2="12.01" y2="18"></line>
						</svg>
						<span class="insights-segmented-text"><?php echo esc_html__( 'Mobile', 'vortem-ai' ); ?></span>
					</button>
				</div>

				<!-- Refresh Button -->
				<div class="insights-refresh-wrapper">
					<button class="insights-refresh-btn" id="btn-refresh" title="<?php echo esc_attr__( 'Refresh data', 'vortem-ai' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" class="insights-refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
							<path d="M21 3v5h-5"></path>
							<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
							<path d="M8 16H3v5"></path>
						</svg>
					</button>
				</div>
			</div>
		</div>

		<!-- Core Web Vitals -->
		<div class="insights-section">
			<h2 class="insights-section-title">
				<div class="insights-section-title-bar"></div>
				<?php echo esc_html__( 'Core Web Vitals', 'vortem-ai' ); ?>
			</h2>
			<div class="insights-core-web-vitals" id="insights-core-web-vitals">
				<!-- Core Web Vitals will be inserted here -->
			</div>
		</div>

		<!-- Main Content Grid -->
		<div class="insights-content-grid">
			<!-- Performance Audits -->
			<div class="insights-audits-section">
				<div class="insights-card">
					<div class="insights-card-header">
						<h3 class="insights-card-title">
							<div class="insights-section-title-bar"></div>
							<?php echo esc_html__( 'Performance Audits', 'vortem-ai' ); ?>
						</h3>
					</div>
					<div class="insights-card-content">
						<div class="insights-audits-list" id="insights-audits-list">
							<!-- Audits will be inserted here -->
						</div>
						<div class="insights-expand-control" id="audits-expand" style="display: none;">
							<button class="insights-expand-btn" id="btn-audits-expand">
								<svg class="insights-chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<polyline points="6 9 12 15 18 9"></polyline>
								</svg>
								<span id="audits-expand-text"><?php echo esc_html__( 'View More', 'vortem-ai' ); ?></span>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Sidebar -->
			<div class="insights-sidebar">
				<!-- Quick Insights -->
				<div class="insights-card">
					<div class="insights-card-header">
						<div class="insights-insight-icon-wrapper">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 1rem; height: 1rem; max-width: 1rem; max-height: 1rem;"><circle cx="12" cy="12" r="10" stroke="white" fill="none"></circle><line x1="12" y1="8" x2="12" y2="12" stroke="white" fill="none"></line><line x1="12" y1="16" x2="12.01" y2="16" stroke="white" fill="none"></line></svg>
						</div>
						<h3 class="insights-card-title"><?php echo esc_html__( 'Quick Insights', 'vortem-ai' ); ?></h3>
					</div>
					<div class="insights-card-content">
						<div class="insights-quick-insights-list" id="insights-quick-insights-list">
							<!-- Quick insights will be inserted here -->
						</div>
					</div>
				</div>

				<!-- Config -->
				<div class="insights-card">
					<div class="insights-card-header">
						<h3 class="insights-card-title">
							<div class="insights-section-title-bar"></div>
							<?php echo esc_html__( 'Config', 'vortem-ai' ); ?>
						</h3>
					</div>
					<div class="insights-card-content">
						<div class="insights-config-list">
							<div class="insights-config-item">
								<span class="insights-config-label"><?php echo esc_html__( 'Device', 'vortem-ai' ); ?></span>
								<span class="insights-config-value" id="config-device">-</span>
							</div>
							<div class="insights-config-item">
								<span class="insights-config-label"><?php echo esc_html__( 'Locale', 'vortem-ai' ); ?></span>
								<span class="insights-config-value" id="config-locale">-</span>
							</div>
							<div class="insights-config-item">
								<span class="insights-config-label"><?php echo esc_html__( 'Channel', 'vortem-ai' ); ?></span>
								<span class="insights-config-value" id="config-channel">-</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<!-- Loading State -->
	<div class="insights-loading-state" id="loading-state" style="display: none;">
		<div class="insights-spinner"></div>
		<p><?php echo esc_html__( 'Loading Insights data...', 'vortem-ai' ); ?></p>
	</div>

	<!-- Error State -->
	<div class="insights-error-state" id="error-state" style="display: none;">
		<div class="insights-error-icon-wrapper">
			<svg class="insights-error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<circle cx="12" cy="12" r="10"></circle>
				<line x1="12" y1="8" x2="12" y2="12"></line>
				<line x1="12" y1="16" x2="12.01" y2="16"></line>
			</svg>
		</div>
		<h3><?php echo esc_html__( 'Error Loading Insights Data', 'vortem-ai' ); ?></h3>
		<p id="error-message"><?php echo esc_html__( 'An error occurred while fetching Insights analysis data.', 'vortem-ai' ); ?></p>
		<button class="insights-btn-outline" id="btn-retry"><?php echo esc_html__( 'Retry', 'vortem-ai' ); ?></button>
	</div>

	<!-- Empty State -->
	<div class="insights-empty-state" id="empty-state" style="display: none;">
		<div class="insights-empty-icon-wrapper">
			<svg class="insights-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<circle cx="12" cy="12" r="10"></circle>
				<line x1="12" y1="8" x2="12" y2="12"></line>
				<line x1="12" y1="16" x2="12.01" y2="16"></line>
			</svg>
		</div>
		<h3><?php echo esc_html__( 'No Insights Data Available', 'vortem-ai' ); ?></h3>
		<p><?php echo esc_html__( 'No performance analysis data is available. This could mean the analysis has not been run yet, or there was an error fetching the data.', 'vortem-ai' ); ?></p>
		<button class="insights-btn-outline" id="btn-retry-empty"><?php echo esc_html__( 'Retry', 'vortem-ai' ); ?></button>
	</div>
</div>
