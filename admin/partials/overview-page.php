<?php
/**
 * Overview page template - Wizard-matched dashboard design
 *
 * @package VortemAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vortem_products_url        = isset( $vortem_products_url ) ? $vortem_products_url : admin_url( 'admin.php?page=vortem-products' );
$vortem_analytics_url       = isset( $vortem_analytics_url ) ? $vortem_analytics_url : admin_url( 'admin.php?page=vortem-analytics' );
$vortem_email_marketing_url = isset( $vortem_email_marketing_url ) ? $vortem_email_marketing_url : admin_url( 'admin.php?page=vortem-email-marketing' );
$vortem_insights_url        = isset( $vortem_insights_url ) ? $vortem_insights_url : admin_url( 'admin.php?page=vortem-insights' );
$vortem_orders_url          = isset( $vortem_orders_url ) ? $vortem_orders_url : admin_url( 'admin.php?page=vortem-orders' );
$vortem_security_url        = isset( $vortem_security_url ) ? $vortem_security_url : admin_url( 'admin.php?page=vortem-security' );
$vortem_settings_url        = isset( $vortem_settings_url ) ? $vortem_settings_url : admin_url( 'admin.php?page=vortem-settings' );
$vortem_dashboard_dir       = isset( $vortem_dashboard_dir ) ? $vortem_dashboard_dir : 'ltr';
?>

<div class="overview-workspace-wrap">
	<!-- Background orbs -->
	<div class="overview-bg-orb overview-bg-orb-1" aria-hidden="true"></div>
	<div class="overview-bg-orb overview-bg-orb-2" aria-hidden="true"></div>

	<!-- Hero header banner -->
	<header class="overview-hero">
		<div class="overview-hero-inner">
			<div class="overview-hero-left">
				<div class="overview-hero-logo">
					<?php
					$vortem_logo_path = defined( 'VORTEM_PLUGIN_DIR' ) ? VORTEM_PLUGIN_DIR . 'assets/images/logo.png' : dirname( dirname( __DIR__ ) ) . '/assets/images/logo.png';
					$vortem_logo_url  = defined( 'VORTEM_PLUGIN_URL' ) ? VORTEM_PLUGIN_URL . 'assets/images/logo.png' : '';
					if ( ! empty( $vortem_logo_url ) && file_exists( $vortem_logo_path ) ) :
						?>
						<img src="<?php echo esc_url( $vortem_logo_url ); ?>" alt="" class="overview-hero-logo-img" />
						<?php
					else :
						$vortem_logo_placeholder = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="%2308B8B8"><rect width="64" height="64" rx="12" fill="%2308B8B8" opacity=".15"/><path d="M32 18l12 8v12l-12 8-12-8V26l12-8z" fill="%2308B8B8"/></svg>' );
						?>
						<img src="<?php echo esc_url( $vortem_logo_placeholder ); ?>" alt="" class="overview-hero-logo-img" />
					<?php endif; ?>
				</div>
				<div class="overview-hero-text">
					<span class="overview-hero-eyebrow"><?php echo esc_html__( 'Dashboard', 'vortem-ai' ); ?></span>
					<h1 class="overview-hero-title"><?php echo esc_html__( 'Overview', 'vortem-ai' ); ?></h1>
				</div>
			</div>
			<div class="overview-hero-right">
				<span class="overview-hero-live-badge">
					<span class="overview-hero-live-dot" aria-hidden="true"></span>
					<?php echo esc_html__( 'Live', 'vortem-ai' ); ?>
				</span>
				<span class="overview-hero-date">
					<?php echo esc_html( wp_date( 'M j, Y' ) ); ?>
				</span>
			</div>
		</div>
		<span class="overview-hero-orb" aria-hidden="true"></span>
	</header>

	<!-- Main content -->
	<div class="overview-main">
		<?php if ( ! empty( $admin ) ) : ?>
			<?php $admin->show_all_custom_notices(); ?>
		<?php endif; ?>

		<!-- Metric Cards -->
		<section class="overview-section overview-section-metrics" aria-label="<?php echo esc_attr__( 'Key metrics', 'vortem-ai' ); ?>">
			<div class="overview-metrics-grid">
				<a href="<?php echo esc_url( $vortem_insights_url ); ?>" class="overview-metric-card-link">
					<div class="overview-metric-card overview-metric-card-has-tooltip overview-metric-card-loading" data-metric="perf" data-perf-desktop="" data-perf-mobile="" data-tooltip-id="perf">
						<div class="overview-metric-icon-box overview-metric-icon-teal" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>
						</div>
						<div class="overview-metric-body">
							<p class="overview-metric-card-label"><?php echo esc_html__( 'Performance', 'vortem-ai' ); ?></p>
							<div class="overview-metric-card-value-wrap">
								<span class="overview-metric-card-spinner" aria-hidden="true"></span>
								<p class="overview-metric-card-value overview-metric-card-value-perf" id="overviewMetricPerf">&mdash;</p>
							</div>
						</div>
						<div class="overview-metric-card-tooltip" id="tooltip-perf"></div>
					</div>
				</a>

				<a href="<?php echo esc_url( $vortem_security_url ); ?>" class="overview-metric-card-link">
					<div class="overview-metric-card overview-metric-card-loading" data-metric="vuln">
						<div class="overview-metric-icon-box overview-metric-icon-amber" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
						</div>
						<div class="overview-metric-body">
							<p class="overview-metric-card-label"><?php echo esc_html__( 'Vulnerabilities', 'vortem-ai' ); ?></p>
							<div class="overview-metric-card-value-wrap">
								<span class="overview-metric-card-spinner" aria-hidden="true"></span>
								<p class="overview-metric-card-value" id="overviewMetricVuln">&mdash;</p>
							</div>
						</div>
					</div>
				</a>

				<a href="<?php echo esc_url( $vortem_email_marketing_url ); ?>" class="overview-metric-card-link">
					<div class="overview-metric-card overview-metric-card-loading" data-metric="emails">
						<div class="overview-metric-icon-box overview-metric-icon-teal" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						</div>
						<div class="overview-metric-body">
							<p class="overview-metric-card-label"><?php echo esc_html__( 'Emails Sent', 'vortem-ai' ); ?></p>
							<div class="overview-metric-card-value-wrap">
								<span class="overview-metric-card-spinner" aria-hidden="true"></span>
								<p class="overview-metric-card-value" id="overviewMetricEmails">&mdash;</p>
							</div>
						</div>
					</div>
				</a>

				<a href="<?php echo esc_url( $vortem_products_url ); ?>" class="overview-metric-card-link">
					<div class="overview-metric-card overview-metric-card-loading" data-metric="products">
						<div class="overview-metric-icon-box overview-metric-icon-green" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
						</div>
						<div class="overview-metric-body">
							<p class="overview-metric-card-label"><?php echo esc_html__( 'Imported Products', 'vortem-ai' ); ?></p>
							<div class="overview-metric-card-value-wrap">
								<span class="overview-metric-card-spinner" aria-hidden="true"></span>
								<p class="overview-metric-card-value" id="overviewMetricProducts">&mdash;</p>
							</div>
						</div>
					</div>
				</a>
			</div>
		</section>

		<!-- Today's Focus -->
		<section class="overview-section overview-section-focus">
			<div class="overview-focus-banner">
				<svg class="overview-focus-banner-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
				<div class="overview-focus-banner-text">
					<p class="overview-focus-banner-bold"><?php echo esc_html__( "Today's Focus", 'vortem-ai' ); ?></p>
				</div>
			</div>
			<div class="overview-focus-grid">
				<div class="overview-focus-card">
					<div class="overview-focus-card-icon overview-focus-card-icon-news" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/>
							<path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6z"/>
						</svg>
					</div>
					<div class="overview-focus-card-body">
						<p class="overview-focus-card-title"><?php echo esc_html__( 'Vortem.ai Plugin Overview', 'vortem-ai' ); ?></p>
						<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_docs' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-focus-card-cta">
							<?php echo esc_html__( 'Read More', 'vortem-ai' ); ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
						</a>
					</div>
				</div>
				<div class="overview-focus-card">
					<div class="overview-focus-card-icon overview-focus-card-icon-event" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
							<line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
							<line x1="3" y1="10" x2="21" y2="10"/>
						</svg>
					</div>
					<div class="overview-focus-card-body">
						<p class="overview-focus-card-title"><?php echo esc_html__( 'Check your dashboard regularly', 'vortem-ai' ); ?></p>
						<a href="<?php echo esc_url( $vortem_analytics_url ); ?>" class="overview-focus-card-cta">
							<?php echo esc_html__( 'View Analytics', 'vortem-ai' ); ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
					</div>
				</div>
				<div class="overview-focus-card">
					<div class="overview-focus-card-icon overview-focus-card-icon-achievement" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="8" r="7"/>
							<polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
						</svg>
					</div>
					<div class="overview-focus-card-body">
						<p class="overview-focus-card-title"><?php echo esc_html__( 'Vortem recognized as Top AI Platform 2026', 'vortem-ai' ); ?></p>
					</div>
				</div>
			</div>
		</section>

		<!-- Two-column: Order Statistics + Recent Activity -->
		<section class="overview-section overview-two-col">
			<div class="overview-panel overview-panel-orders">
				<div class="overview-panel-header">
					<span class="overview-panel-eyebrow"><?php echo esc_html__( 'Commerce', 'vortem-ai' ); ?></span>
					<h3 class="overview-panel-title">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
						<?php echo esc_html__( 'Order Statistics', 'vortem-ai' ); ?>
					</h3>
				</div>
				<div class="overview-stats-grid" id="overviewOrderStats">
					<div class="overview-stat-pill">
						<p class="overview-stat-pill-label"><?php echo esc_html__( "Today's Orders Completed", 'vortem-ai' ); ?></p>
						<span class="overview-stat-pill-value" data-metric="orders_today">&mdash;</span>
					</div>
					<div class="overview-stat-pill">
						<p class="overview-stat-pill-label"><?php echo esc_html__( 'Total Orders Completed', 'vortem-ai' ); ?></p>
						<span class="overview-stat-pill-value" data-metric="orders_total">&mdash;</span>
					</div>
					<div class="overview-stat-pill">
						<p class="overview-stat-pill-label"><?php echo esc_html__( 'Total Revenue', 'vortem-ai' ); ?></p>
						<span class="overview-stat-pill-value" data-metric="revenue_total">&mdash;</span>
					</div>
					<div class="overview-stat-pill">
						<p class="overview-stat-pill-label"><?php echo esc_html__( 'Imported Products', 'vortem-ai' ); ?></p>
						<span class="overview-stat-pill-value" data-metric="products_count">&mdash;</span>
					</div>
				</div>
			</div>

			<div class="overview-panel overview-panel-activity">
				<div class="overview-panel-header">
					<span class="overview-panel-eyebrow"><?php echo esc_html__( 'Timeline', 'vortem-ai' ); ?></span>
					<h3 class="overview-panel-title"><?php echo esc_html__( 'Recent Activity', 'vortem-ai' ); ?></h3>
				</div>
				<div class="overview-activity-list" id="overviewRecentActivity">
					<div class="overview-activity-row">
						<div class="overview-activity-dot overview-activity-dot-teal" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
						</div>
						<div class="overview-activity-body">
							<p class="overview-activity-text"><?php echo esc_html__( 'Overview dashboard loaded', 'vortem-ai' ); ?></p>
							<p class="overview-activity-time"><?php echo esc_html__( 'Just now', 'vortem-ai' ); ?></p>
						</div>
					</div>
					<div class="overview-activity-row">
						<div class="overview-activity-dot overview-activity-dot-primary" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						</div>
						<div class="overview-activity-body">
							<p class="overview-activity-text"><?php echo esc_html__( 'You can manage products, analytics, and email marketing from here', 'vortem-ai' ); ?></p>
							<p class="overview-activity-time"><?php echo esc_html__( 'Ready', 'vortem-ai' ); ?></p>
						</div>
					</div>
					<div class="overview-activity-row">
						<div class="overview-activity-dot overview-activity-dot-gray" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
						</div>
						<div class="overview-activity-body">
							<p class="overview-activity-text"><?php echo esc_html__( 'Configure plugin settings and API keys', 'vortem-ai' ); ?></p>
							<a href="<?php echo esc_url( $vortem_settings_url ); ?>" class="overview-activity-link"><?php echo esc_html__( 'Settings', 'vortem-ai' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Insights & Alerts -->
		<section class="overview-section overview-section-alerts" id="overviewAlerts">
			<div class="overview-callout overview-callout-info">
				<span class="overview-callout-icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				</span>
				<p class="overview-callout-text"><?php echo esc_html__( 'Vortem.ai plugin is ready. You can manage products, analytics, and email marketing from here.', 'vortem-ai' ); ?></p>
			</div>
		</section>

		<!-- Two-column: Resources + Navigation -->
		<section class="overview-section overview-two-col">
			<div class="overview-panel overview-panel-resources">
				<div class="overview-panel-header">
					<span class="overview-panel-eyebrow"><?php echo esc_html__( 'External', 'vortem-ai' ); ?></span>
					<h3 class="overview-panel-title"><?php echo esc_html__( 'Vortem.ai official website', 'vortem-ai' ); ?></h3>
					<p class="overview-panel-desc"><?php echo esc_html__( 'Navigate, learn, and manage your account', 'vortem-ai' ); ?></p>
				</div>

				<div class="overview-link-tier">
					<span class="overview-link-tier-label"><?php echo esc_html__( 'Quick Access', 'vortem-ai' ); ?></span>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_home' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-primary">
						<span class="overview-link-icon-box overview-link-icon-teal">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'Home', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Return to dashboard home', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-filled"><?php echo esc_html__( 'Go to Home', 'vortem-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_account' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-primary">
						<span class="overview-link-icon-box overview-link-icon-teal">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'My Account', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Profile, settings, and preferences', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-filled"><?php echo esc_html__( 'Manage', 'vortem-ai' ); ?></span>
					</a>
				</div>

				<div class="overview-link-tier">
					<span class="overview-link-tier-label"><?php echo esc_html__( 'Help & Learning', 'vortem-ai' ); ?></span>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_support' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-secondary">
						<span class="overview-link-icon-box overview-link-icon-teal overview-link-icon-sm">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'Support', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Get help, submit tickets, or contact support', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-outline"><?php echo esc_html__( 'Get Support', 'vortem-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_docs' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-secondary">
						<span class="overview-link-icon-box overview-link-icon-teal overview-link-icon-sm">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'Documentation', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Developer & user guides', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-outline"><?php echo esc_html__( 'Read Docs', 'vortem-ai' ); ?></span>
					</a>
				</div>

				<div class="overview-link-tier">
					<span class="overview-link-tier-label"><?php echo esc_html__( 'Legal & Policies', 'vortem-ai' ); ?></span>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_terms' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-tertiary">
						<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'Terms of Service', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Usage terms and conditions', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-ghost"><?php echo esc_html__( 'View', 'vortem-ai' ); ?></span>
					</a>
					<a href="<?php echo esc_url( Vortem_Config::get_external_url( 'vortem_privacy' ) ); ?>" target="_blank" rel="noopener noreferrer" class="overview-link-row overview-link-row-tertiary">
						<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
							<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
						</span>
						<span class="overview-link-inner">
							<span class="overview-link-name"><?php echo esc_html__( 'Privacy Policy', 'vortem-ai' ); ?></span>
							<span class="overview-link-desc"><?php echo esc_html__( 'Data protection and privacy', 'vortem-ai' ); ?></span>
						</span>
						<span class="overview-link-btn overview-link-btn-ghost"><?php echo esc_html__( 'View', 'vortem-ai' ); ?></span>
					</a>
				</div>
			</div>

			<div class="overview-panel overview-panel-nav">
				<div class="overview-panel-header">
					<span class="overview-panel-eyebrow"><?php echo esc_html__( 'Plugin', 'vortem-ai' ); ?></span>
					<h3 class="overview-panel-title"><?php echo esc_html__( 'Navigation to vortem WordPress', 'vortem-ai' ); ?></h3>
					<p class="overview-panel-desc"><?php echo esc_html__( 'Access key features and settings', 'vortem-ai' ); ?></p>
				</div>

				<nav class="overview-nav-list">
					<div class="overview-nav-tier">
						<span class="overview-link-tier-label"><?php echo esc_html__( 'Core', 'vortem-ai' ); ?></span>
						<a href="<?php echo esc_url( $vortem_products_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-teal">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><polyline points="9 12 11 14 15 10"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Products', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'Manage catalog and inventory', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
						<a href="<?php echo esc_url( $vortem_analytics_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-teal">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Analytics', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'Business insights and metrics', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
					</div>

					<div class="overview-nav-tier">
						<span class="overview-link-tier-label"><?php echo esc_html__( 'Growth & Intelligence', 'vortem-ai' ); ?></span>
						<a href="<?php echo esc_url( $vortem_email_marketing_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Email Marketing', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'Campaigns and automation', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
						<a href="<?php echo esc_url( $vortem_insights_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Insights', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'AI-powered recommendations', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
						<a href="<?php echo esc_url( $vortem_orders_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Orders', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'Manage orders and shipments', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
					</div>

					<div class="overview-nav-tier">
						<span class="overview-link-tier-label"><?php echo esc_html__( 'System', 'vortem-ai' ); ?></span>
						<a href="<?php echo esc_url( $vortem_security_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Security', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'Access control and monitoring', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
						<a href="<?php echo esc_url( $vortem_settings_url ); ?>" class="overview-nav-row">
							<span class="overview-link-icon-box overview-link-icon-gray overview-link-icon-sm">
								<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
							</span>
							<span class="overview-link-inner">
								<span class="overview-link-name"><?php echo esc_html__( 'Settings', 'vortem-ai' ); ?></span>
								<span class="overview-link-desc"><?php echo esc_html__( 'System configuration', 'vortem-ai' ); ?></span>
							</span>
							<svg class="overview-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
						</a>
					</div>
				</nav>
			</div>
		</section>
	</div>
</div>
