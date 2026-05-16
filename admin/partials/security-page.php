<?php
/**
 * Security page template - Redesigned with teal gradient hero header
 *
 * @package VortemAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build API URLs using config
require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';

$vortem_api_server     = Vortem_Config::get_primary_api_server();
$vortem_plugin_api_url = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress' );
$vortem_theme_api_url  = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_theme' );
$vortem_api_url        = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_match' );
?>
<div class="security-workspace-wrap">
	<!-- Background orbs -->
	<div class="security-bg-orb security-bg-orb-1" aria-hidden="true"></div>
	<div class="security-bg-orb security-bg-orb-2" aria-hidden="true"></div>

	<!-- Hero header (gradient, matching overview design) -->
	<header class="security-hero">
		<div class="security-hero-inner">
			<div class="security-hero-left">
				<div class="security-hero-logo">
					<?php
					$vortem_logo_path = defined( 'VORTEM_PLUGIN_DIR' ) ? VORTEM_PLUGIN_DIR . 'assets/images/logo.png' : dirname( dirname( __DIR__ ) ) . '/assets/images/logo.png';
					$vortem_logo_url  = defined( 'VORTEM_PLUGIN_URL' ) ? VORTEM_PLUGIN_URL . 'assets/images/logo.png' : '';
					if ( ! empty( $vortem_logo_url ) && file_exists( $vortem_logo_path ) ) :
						?>
						<img src="<?php echo esc_url( $vortem_logo_url ); ?>" alt="" class="security-hero-logo-img" />
						<?php
					else :
						$vortem_logo_placeholder = 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="%2308B8B8"><rect width="64" height="64" rx="12" fill="%2308B8B8" opacity=".15"/><path d="M32 18l12 8v12l-12 8-12-8V26l12-8z" fill="%2308B8B8"/></svg>' );
						?>
						<img src="<?php echo esc_url( $vortem_logo_placeholder ); ?>" alt="" class="security-hero-logo-img" />
					<?php endif; ?>
				</div>
				<div class="security-hero-text">
					<span class="security-hero-eyebrow"><?php echo esc_html__( 'Security Center', 'vortem-ai' ); ?></span>
					<h1 class="security-hero-title"><?php echo esc_html__( 'Security', 'vortem-ai' ); ?></h1>
				</div>
			</div>
			<div class="security-hero-right">
				<span class="security-hero-live-badge">
					<span class="security-hero-live-dot" aria-hidden="true"></span>
					<?php echo esc_html__( 'Live', 'vortem-ai' ); ?>
				</span>
				<span class="security-hero-date">
					<?php echo esc_html( wp_date( 'M j, Y' ) ); ?>
				</span>
				<span class="vortem-security-last-updated-value" id="vortem-security-last-updated-value" style="display:none;"><?php echo esc_html( current_time( 'F j, Y \a\t g:i A' ) ); ?></span>
			</div>
		</div>
		<span class="security-hero-orb" aria-hidden="true"></span>
	</header>

	<!-- Main content -->
	<div class="security-main">
		<!-- Stats Cards -->
		<div class="vortem-security-stats-section">
			<div class="vortem-security-stats-grid">
				<!-- Total Plugins: Package (Lucide) -->
				<div class="vortem-stat-card" data-type="total-plugins">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Total Plugins', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="totalPlugins"><?php echo esc_html( count( $plugins ) ); ?></p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M16.5 9.4L7.55 4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M3.27 6.96L12 12.01l8.73-5.05" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M12 22.08V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
				<!-- Active Plugins: CheckCircle (Lucide) -->
				<div class="vortem-stat-card" data-type="active-plugins">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Active Plugins', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="activePlugins">
						<?php
						echo esc_html(
							count(
								array_filter(
									$plugins,
									function ( $p ) {
										return 'active' === $p['status'];
									}
								)
							)
						);
						?>
						</p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
				<!-- Inactive Plugins: XCircle (Lucide) -->
				<div class="vortem-stat-card" data-type="inactive-plugins">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Inactive Plugins', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="inactivePlugins">
						<?php
						echo esc_html(
							count(
								array_filter(
									$plugins,
									function ( $p ) {
										return 'inactive' === $p['status'];
									}
								)
							)
						);
						?>
						</p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m15 9-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
				<!-- Total Themes: Palette (Lucide) -->
				<div class="vortem-stat-card" data-type="total-themes">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Total Themes', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="totalThemes"><?php echo esc_html( count( $themes ) ); ?></p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
							<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
							<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
							<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
							<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
				<!-- Active Themes: CheckCircle (Lucide) -->
				<div class="vortem-stat-card" data-type="active-themes">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Active Themes', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="activeThemes">
						<?php
						echo esc_html(
							count(
								array_filter(
									$themes,
									function ( $t ) {
										return 'active' === $t['status'];
									}
								)
							)
						);
						?>
						</p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
				<!-- Inactive Themes: XCircle (Lucide) -->
				<div class="vortem-stat-card" data-type="inactive-themes">
					<div class="vortem-stat-content">
						<p class="vortem-stat-label"><?php esc_html_e( 'Inactive Themes', 'vortem-ai' ); ?></p>
						<p class="vortem-stat-value" id="inactiveThemes">
						<?php
						echo esc_html(
							count(
								array_filter(
									$themes,
									function ( $t ) {
										return 'inactive' === $t['status'];
									}
								)
							)
						);
						?>
						</p>
					</div>
					<div class="vortem-stat-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m15 9-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
				</div>
			</div>
		</div>

		<!-- Tab Section -->
		<div class="vortem-security-tab-section">
			<div class="vortem-security-tab-section-content">
				<!-- Tabs and Filters Row -->
				<div class="vortem-security-tabs-filters-row">
					<!-- Tabs -->
					<div class="vortem-security-tabs-container">
						<!-- Overview: LayoutGrid (Lucide) -->
						<button class="vortem-security-tab-btn active" data-tab="overview">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Overview', 'vortem-ai' ); ?></span>
						</button>
						<!-- Plugins: Package (Lucide) -->
						<button class="vortem-security-tab-btn" data-tab="plugins">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M16.5 9.4L7.55 4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M3.27 6.96L12 12.01l8.73-5.05" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M12 22.08V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Plugins', 'vortem-ai' ); ?></span>
							<span class="vortem-tab-count" id="pluginsTabCount"><?php echo esc_html( count( $plugins ) ); ?></span>
						</button>
						<!-- Themes: Palette (Lucide) -->
						<button class="vortem-security-tab-btn" data-tab="themes">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
								<circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
								<circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
								<circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
								<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Themes', 'vortem-ai' ); ?></span>
							<span class="vortem-tab-count" id="themesTabCount"><?php echo esc_html( count( $themes ) ); ?></span>
						</button>
						<!-- Core: Box (Lucide) -->
						<button class="vortem-security-tab-btn" data-tab="core">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M3.27 6.96L12 12.01l8.73-5.05" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M12 22.08V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<span><?php esc_html_e( 'Core', 'vortem-ai' ); ?></span>
						</button>
					</div>

					<!-- Filters (only show for plugins/themes tabs) -->
					<div class="vortem-security-filters-container" id="vortem-security-filters-container" style="display: none;">
						<!-- Search Input -->
						<div class="vortem-security-search-wrapper">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="vortem-search-icon">
								<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<input type="text" id="vortem-security-search" class="vortem-security-search-input" placeholder="<?php esc_attr_e( 'Search plugins...', 'vortem-ai' ); ?>">
						</div>

						<!-- Filter by -->
						<div class="vortem-filter-group">
							<div class="vortem-filter-icon-wrapper">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<span class="vortem-filter-label"><?php esc_html_e( 'Filter by:', 'vortem-ai' ); ?></span>
							<div class="vortem-filter-select-wrap" data-select-id="vortem-security-status-filter">
								<select id="vortem-security-status-filter" class="vortem-filter-select-native" aria-hidden="true" tabindex="-1">
									<option value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></option>
									<option value="active"><?php esc_html_e( 'Active', 'vortem-ai' ); ?></option>
									<option value="inactive"><?php esc_html_e( 'Inactive', 'vortem-ai' ); ?></option>
								</select>
								<button type="button" class="vortem-filter-select-trigger" aria-haspopup="listbox" aria-expanded="false">
									<span class="vortem-filter-select-value"><?php esc_html_e( 'All', 'vortem-ai' ); ?></span>
									<svg class="vortem-filter-select-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>
								<div class="vortem-filter-select-dropdown" role="listbox" hidden>
									<div class="vortem-filter-select-option" role="option" data-value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></div>
									<div class="vortem-filter-select-option" role="option" data-value="active"><?php esc_html_e( 'Active', 'vortem-ai' ); ?></div>
									<div class="vortem-filter-select-option" role="option" data-value="inactive"><?php esc_html_e( 'Inactive', 'vortem-ai' ); ?></div>
								</div>
							</div>
						</div>

						<!-- Sort By: ArrowDownUp (Lucide) - blue theme per workspace -->
						<div class="vortem-filter-group vortem-filter-group--sort">
							<div class="vortem-filter-icon-wrapper">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="m3 16 4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M7 20V4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="m21 8-4-4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M17 4v16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<span class="vortem-filter-label"><?php esc_html_e( 'Sort by:', 'vortem-ai' ); ?></span>
							<div class="vortem-filter-select-wrap" data-select-id="vortem-security-sort">
								<select id="vortem-security-sort" class="vortem-filter-select-native" aria-hidden="true" tabindex="-1">
									<option value="name"><?php esc_html_e( 'Name', 'vortem-ai' ); ?></option>
									<option value="date"><?php esc_html_e( 'Date', 'vortem-ai' ); ?></option>
									<option value="author"><?php esc_html_e( 'Author', 'vortem-ai' ); ?></option>
								</select>
								<button type="button" class="vortem-filter-select-trigger" aria-haspopup="listbox" aria-expanded="false">
									<span class="vortem-filter-select-value"><?php esc_html_e( 'Name', 'vortem-ai' ); ?></span>
									<svg class="vortem-filter-select-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>
								<div class="vortem-filter-select-dropdown" role="listbox" hidden>
									<div class="vortem-filter-select-option" role="option" data-value="name"><?php esc_html_e( 'Name', 'vortem-ai' ); ?></div>
									<div class="vortem-filter-select-option" role="option" data-value="date"><?php esc_html_e( 'Date', 'vortem-ai' ); ?></div>
									<div class="vortem-filter-select-option" role="option" data-value="author"><?php esc_html_e( 'Author', 'vortem-ai' ); ?></div>
								</div>
							</div>
						</div>

						<!-- View Toggle: Grid3x3 + List (Lucide) -->
						<div class="vortem-view-toggle">
							<button class="vortem-view-toggle-btn active" data-view="grid" id="cardViewBtn">
								<!-- Lucide Grid3x3 (h-3.5 w-3.5 = 14px) -->
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect width="18" height="18" x="3" y="3" rx="2"/>
									<path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
								</svg>
							</button>
							<button class="vortem-view-toggle-btn" data-view="list" id="tableViewBtn">
								<!-- Lucide List (h-3.5 w-3.5 = 14px) -->
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
								</svg>
							</button>
						</div>
					</div>
				</div>

				<!-- Tab Content -->
				<div class="vortem-security-tab-content">
					<!-- Overview Tab -->
					<div class="vortem-tab-panel active" id="tab-overview">
						<!-- Skeleton Loading -->
						<div class="vortem-overview-skeleton-wrapper" id="overview-skeleton">
							<!-- Summary Cards Skeleton -->
							<div class="vortem-overview-summary-skeleton">
								<?php for ( $vortem_i = 0; $vortem_i < 6; $vortem_i++ ) : ?>
								<div class="vortem-skeleton-card">
									<div class="vortem-skeleton vortem-skeleton-icon"></div>
									<div class="vortem-skeleton-content">
										<div class="vortem-skeleton vortem-skeleton-label"></div>
										<div class="vortem-skeleton vortem-skeleton-value"></div>
									</div>
								</div>
								<?php endfor; ?>
							</div>
							
							<!-- Status Banner Skeleton -->
							<div class="vortem-skeleton-status-banner">
								<div class="vortem-skeleton vortem-skeleton-status-icon"></div>
								<div class="vortem-skeleton-status-content">
									<div class="vortem-skeleton vortem-skeleton-status-title"></div>
									<div class="vortem-skeleton vortem-skeleton-status-desc"></div>
								</div>
							</div>
							
							<!-- Risk Items Skeleton -->
							<div class="vortem-skeleton-section">
								<div class="vortem-skeleton-section-header">
									<div class="vortem-skeleton vortem-skeleton-section-icon"></div>
									<div class="vortem-skeleton vortem-skeleton-section-title"></div>
									<div class="vortem-skeleton vortem-skeleton-section-count"></div>
								</div>
								<div class="vortem-skeleton-list">
									<?php for ( $vortem_i = 0; $vortem_i < 3; $vortem_i++ ) : ?>
									<div class="vortem-skeleton-list-item">
										<div class="vortem-skeleton vortem-skeleton-list-icon"></div>
										<div class="vortem-skeleton-list-content">
											<div class="vortem-skeleton vortem-skeleton-list-name"></div>
											<div class="vortem-skeleton vortem-skeleton-list-detail"></div>
										</div>
										<div class="vortem-skeleton vortem-skeleton-list-badge"></div>
									</div>
									<?php endfor; ?>
								</div>
							</div>
							
							<!-- Recent Vulnerabilities Skeleton -->
							<div class="vortem-skeleton-section">
								<div class="vortem-skeleton-section-header">
									<div class="vortem-skeleton vortem-skeleton-section-icon"></div>
									<div class="vortem-skeleton vortem-skeleton-section-title"></div>
								</div>
								<div class="vortem-skeleton-list">
									<?php for ( $vortem_i = 0; $vortem_i < 3; $vortem_i++ ) : ?>
									<div class="vortem-skeleton-list-item">
										<div class="vortem-skeleton vortem-skeleton-list-icon"></div>
										<div class="vortem-skeleton-list-content">
											<div class="vortem-skeleton vortem-skeleton-list-name"></div>
											<div class="vortem-skeleton vortem-skeleton-list-detail"></div>
										</div>
										<div class="vortem-skeleton vortem-skeleton-list-badge"></div>
									</div>
									<?php endfor; ?>
								</div>
							</div>
						</div>

						<!-- Actual Content (hidden until loaded) -->
						<div class="vortem-overview-content-wrapper" id="overview-content">
						<!-- Overview Summary Cards -->
						<div class="vortem-overview-summary">
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-total-vulns" data-tooltip-id="total-vulns">
								<div class="vortem-overview-card-icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'Total Vulnerabilities', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-total-vulns-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-total-vulns"></div>
							</div>
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-critical-vulns" data-tooltip-id="critical-vulns">
								<div class="vortem-overview-card-icon critical">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'Critical', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-critical-vulns-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-critical-vulns"></div>
							</div>
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-high-vulns" data-tooltip-id="high-vulns">
								<div class="vortem-overview-card-icon high">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'High', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-high-vulns-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-high-vulns"></div>
							</div>
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-medium-vulns" data-tooltip-id="medium-vulns">
								<div class="vortem-overview-card-icon medium">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'Medium', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-medium-vulns-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-medium-vulns"></div>
							</div>
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-low-vulns" data-tooltip-id="low-vulns">
								<div class="vortem-overview-card-icon low">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'Low', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-low-vulns-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-low-vulns"></div>
							</div>
							<div class="vortem-overview-summary-card vortem-has-tooltip" id="overview-secure-items" data-tooltip-id="secure-items">
								<div class="vortem-overview-card-icon secure">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-card-content">
									<p class="vortem-overview-card-label"><?php esc_html_e( 'Secure Items', 'vortem-ai' ); ?></p>
									<p class="vortem-overview-card-value" id="overview-secure-items-value">0</p>
								</div>
								<div class="vortem-card-tooltip" id="tooltip-secure-items"></div>
							</div>
						</div>

						<!-- Security Status Banner -->
						<div class="vortem-overview-status-banner" id="overview-status-banner">
							<div class="vortem-overview-status-content">
								<div class="vortem-overview-status-icon">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-overview-status-text">
									<h3 class="vortem-overview-status-title" id="overview-status-title"><?php esc_html_e( 'Scanning...', 'vortem-ai' ); ?></h3>
									<p class="vortem-overview-status-description" id="overview-status-description"><?php esc_html_e( 'Analyzing your WordPress installation for security vulnerabilities', 'vortem-ai' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Items at Risk Section -->
						<div class="vortem-overview-risk-section">
							<div class="vortem-overview-section-header">
								<h3 class="vortem-overview-section-title">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Items Requiring Attention', 'vortem-ai' ); ?>
									<span class="vortem-overview-section-count" id="overview-risk-count">0</span>
								</h3>
							</div>
							<div class="vortem-overview-risk-list" id="overview-risk-list">
								<div class="vortem-overview-risk-empty">
									<p><?php esc_html_e( 'No items at risk', 'vortem-ai' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Recent Vulnerabilities Section -->
						<div class="vortem-overview-recent-section">
							<div class="vortem-overview-section-header">
								<h3 class="vortem-overview-section-title">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M3 3h18v18H3zM3 9h18M9 3v18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<?php esc_html_e( 'Recent Vulnerabilities', 'vortem-ai' ); ?>
								</h3>
							</div>
							<div class="vortem-overview-recent-list" id="overview-recent-list">
								<div class="vortem-overview-recent-empty">
									<p><?php esc_html_e( 'No recent vulnerabilities', 'vortem-ai' ); ?></p>
								</div>
							</div>
						</div>
						</div><!-- End vortem-overview-content-wrapper -->
					</div>

					<!-- Plugins Tab -->
					<div class="vortem-tab-panel" id="tab-plugins">
						<div class="vortem-security-grid" id="vortem-security-list"></div>
						<div id="emptyState" class="vortem-empty-state" style="display: none;">
							<div class="vortem-empty-icon">
								<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<h3 class="vortem-empty-title"><?php esc_html_e( 'No plugins found', 'vortem-ai' ); ?></h3>
							<p class="vortem-empty-description"><?php esc_html_e( 'Try adjusting your filters or search terms', 'vortem-ai' ); ?></p>
						</div>
						<div id="vortem-security-pagination" class="vortem-security-pagination"></div>
					</div>

					<!-- Themes Tab -->
					<div class="vortem-tab-panel" id="tab-themes">
						<div class="vortem-security-grid" id="vortem-security-themes-list"></div>
						<div id="emptyStateThemes" class="vortem-empty-state" style="display: none;">
							<div class="vortem-empty-icon">
								<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</div>
							<h3 class="vortem-empty-title"><?php esc_html_e( 'No themes found', 'vortem-ai' ); ?></h3>
							<p class="vortem-empty-description"><?php esc_html_e( 'Try adjusting your filters or search terms', 'vortem-ai' ); ?></p>
						</div>
						<div id="vortem-security-themes-pagination" class="vortem-security-pagination"></div>
					</div>

					<!-- Core Tab -->
					<div class="vortem-tab-panel" id="tab-core">
						<div class="vortem-wp-core-card">
							<div class="vortem-wp-core-header">
								<div class="vortem-wp-core-icon-wrapper">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
								<div class="vortem-wp-core-title-wrapper">
									<h3 class="vortem-wp-core-title"><?php esc_html_e( 'WordPress Vulnerability', 'vortem-ai' ); ?></h3>
									<p class="vortem-wp-core-version"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>
								</div>
								<span class="vortem-wp-core-badge is-loading" id="vortem-wp-core-badge">Checking</span>
							</div>
							<p class="vortem-wp-core-description">
								<?php esc_html_e( 'Security vulnerabilities detected in WordPress version. Update to latest version is recommended.', 'vortem-ai' ); ?>
							</p>
							<div class="vortem-wp-core-stats" id="vortem-wp-core-stats" style="display: none;">
								<div class="vortem-wp-core-stat-item" data-severity="critical">
									<div class="vortem-wp-core-stat-label"><?php esc_html_e( 'Critical', 'vortem-ai' ); ?></div>
									<div class="vortem-wp-core-stat-value">0</div>
								</div>
								<div class="vortem-wp-core-stat-item" data-severity="medium">
									<div class="vortem-wp-core-stat-label"><?php esc_html_e( 'Medium', 'vortem-ai' ); ?></div>
									<div class="vortem-wp-core-stat-value">0</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Vulnerabilities Dialog -->
	<div id="vortem-vulnerabilities-modal" class="vortem-vulnerabilities-modal" style="display: none;">
		<div class="vortem-vuln-modal-overlay"></div>
		<div class="vortem-vuln-modal-content">
			<!-- Header -->
			<div class="vortem-vuln-modal-header">
				<div class="vortem-vuln-modal-header-content">
					<div class="vortem-vuln-modal-header-left">
						<button class="vortem-vuln-modal-back" id="vortem-vulnerabilities-modal-close">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M19 12H5M12 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
						<div>
							<h1 class="vortem-vuln-modal-title" id="vuln-modal-title"><?php esc_html_e( 'Vulnerabilities', 'vortem-ai' ); ?></h1>
							<p class="vortem-vuln-modal-subtitle"><?php esc_html_e( 'Vulnerability Details', 'vortem-ai' ); ?></p>
						</div>
					</div>
					<div class="vortem-vuln-modal-header-right">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="vortem-vuln-alert-icon">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span class="vortem-vuln-modal-count" id="vuln-modal-count">0 Total Vulnerabilities</span>
					</div>
				</div>
			</div>

			<!-- Filter Section -->
			<div class="vortem-vuln-filter-section">
				<div class="vortem-vuln-filter-section-content">
					<span class="vortem-vuln-filter-label"><?php esc_html_e( 'Filter by Severity:', 'vortem-ai' ); ?></span>
					<select id="vuln-severity-filter" class="vortem-vuln-filter-select">
						<option value="all"><?php esc_html_e( 'All Severities', 'vortem-ai' ); ?></option>
						<option value="critical"><?php esc_html_e( 'Critical', 'vortem-ai' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'vortem-ai' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'vortem-ai' ); ?></option>
						<option value="low"><?php esc_html_e( 'Low', 'vortem-ai' ); ?></option>
					</select>
					<span class="vortem-vuln-filter-count">
						<?php esc_html_e( 'Showing', 'vortem-ai' ); ?> <span class="vortem-vuln-filter-count-value" id="vuln-filter-count-value">0</span> <?php esc_html_e( 'of', 'vortem-ai' ); ?> <span class="vortem-vuln-filter-total" id="vuln-filter-total">0</span> <?php esc_html_e( 'vulnerabilities', 'vortem-ai' ); ?>
					</span>
				</div>
			</div>

			<!-- Vulnerabilities List -->
			<div id="vuln-modal-list" class="vortem-vuln-modal-list">
				<!-- Vulnerabilities will be populated here -->
			</div>
		</div>
	</div>
</div>
