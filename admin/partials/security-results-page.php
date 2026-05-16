<?php
/**
 * Security Results page template
 *
 * @package VortemAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- These are HTML class attributes for frontend styling, not PHP class declarations. The wrapper div uses the vortem- prefix for namespacing.
?>

<div class="wrap vortem-security-results-wrap" style="margin: 0; padding: 0;">
	<div class="container">
		<!-- Header -->
		<div class="header">
			<div class="header-content">
				<div class="header-left">
					<div class="header-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<div>
						<h1 class="header-title"><?php esc_html_e( 'Security Results', 'vortem-ai' ); ?></h1>
						<p class="header-subtitle"><?php esc_html_e( 'Plugin security vulnerabilities and issues', 'vortem-ai' ); ?></p>
					</div>
				</div>
				<div class="header-actions">
					<button class="btn-secondary" id="vortem-security-results-refresh" title="<?php esc_attr_e( 'Refresh', 'vortem-ai' ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<polyline points="23 4 23 10 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span class="btn-text"><?php esc_html_e( 'Refresh', 'vortem-ai' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Loading State -->
		<div id="vortem-security-results-loading" class="loading-state">
			<div class="loading-spinner"></div>
			<p><?php esc_html_e( 'Loading security data...', 'vortem-ai' ); ?></p>
		</div>

		<!-- Error State -->
		<div id="vortem-security-results-error" class="error-state hidden">
			<div class="error-icon">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>
			<h3 class="error-title"><?php esc_html_e( 'Failed to load security data', 'vortem-ai' ); ?></h3>
			<p class="error-message" id="vortem-security-results-error-message"></p>
			<button class="btn-primary" id="vortem-security-results-retry">
				<?php esc_html_e( 'Retry', 'vortem-ai' ); ?>
			</button>
		</div>

		<!-- Filters Section -->
		<div id="vortem-security-results-filters" class="filters-section hidden">
			<div class="filters-wrapper">
				<label for="vortem-security-results-severity-filter" class="filter-label">
					<?php esc_html_e( 'Severity:', 'vortem-ai' ); ?>
					<select id="vortem-security-results-severity-filter" class="filter-select">
						<option value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></option>
						<option value="CRITICAL"><?php esc_html_e( 'Critical', 'vortem-ai' ); ?></option>
						<option value="HIGH"><?php esc_html_e( 'High', 'vortem-ai' ); ?></option>
						<option value="MEDIUM"><?php esc_html_e( 'Medium', 'vortem-ai' ); ?></option>
						<option value="LOW"><?php esc_html_e( 'Low', 'vortem-ai' ); ?></option>
					</select>
				</label>

				<label for="vortem-security-results-issue-type-filter" class="filter-label">
					<?php esc_html_e( 'Issue Type:', 'vortem-ai' ); ?>
					<select id="vortem-security-results-issue-type-filter" class="filter-select">
						<option value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></option>
					</select>
				</label>

				<label for="vortem-security-results-score-filter" class="filter-label">
					<?php esc_html_e( 'Score:', 'vortem-ai' ); ?>
					<select id="vortem-security-results-score-filter" class="filter-select">
						<option value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></option>
						<option value="0-3"><?php esc_html_e( '0.0 - 3.9', 'vortem-ai' ); ?></option>
						<option value="4-6"><?php esc_html_e( '4.0 - 6.9', 'vortem-ai' ); ?></option>
						<option value="7-8"><?php esc_html_e( '7.0 - 8.9', 'vortem-ai' ); ?></option>
						<option value="9-10"><?php esc_html_e( '9.0 - 10.0', 'vortem-ai' ); ?></option>
					</select>
				</label>

				<label for="vortem-security-results-plugin-filter" class="filter-label">
					<?php esc_html_e( 'Plugin:', 'vortem-ai' ); ?>
					<select id="vortem-security-results-plugin-filter" class="filter-select">
						<option value="all"><?php esc_html_e( 'All', 'vortem-ai' ); ?></option>
					</select>
				</label>

				<button class="btn-secondary" id="vortem-security-results-clear-filters">
					<?php esc_html_e( 'Clear Filters', 'vortem-ai' ); ?>
				</button>
			</div>
		</div>

		<!-- Statistics Cards -->
		<div id="vortem-security-results-stats" class="stats-grid hidden">
			<div class="stat-card">
				<div class="stat-icon stat-icon-danger">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="stat-content">
					<p class="stat-title"><?php esc_html_e( 'Total Issues', 'vortem-ai' ); ?></p>
					<p class="stat-value" id="totalIssues">0</p>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon stat-icon-warning">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="stat-content">
					<p class="stat-title"><?php esc_html_e( 'Critical', 'vortem-ai' ); ?></p>
					<p class="stat-value" id="criticalIssues">0</p>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon stat-icon-info">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="stat-content">
					<p class="stat-title"><?php esc_html_e( 'High', 'vortem-ai' ); ?></p>
					<p class="stat-value" id="highIssues">0</p>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon stat-icon-success">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<div class="stat-content">
					<p class="stat-title"><?php esc_html_e( 'Medium', 'vortem-ai' ); ?></p>
					<p class="stat-value" id="mediumIssues">0</p>
				</div>
			</div>
		</div>

		<!-- Results Table -->
		<div id="vortem-security-results-table-container" class="table-container hidden">
			<div class="table-wrapper">
				<table class="security-results-table" id="vortem-security-results-table">
					<thead>
						<tr>
							<th class="sortable" data-sort="cve">
								<div class="th-content">
									<span><?php esc_html_e( 'CVE', 'vortem-ai' ); ?></span>
									<svg class="sort-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M7 13l5 5 5-5M7 6l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
							</th>
							<th class="sortable" data-sort="severity">
								<div class="th-content">
									<span><?php esc_html_e( 'Severity', 'vortem-ai' ); ?></span>
									<svg class="sort-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M7 13l5 5 5-5M7 6l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
							</th>
							<th class="sortable" data-sort="cvss_score">
								<div class="th-content">
									<span><?php esc_html_e( 'CVSS Score', 'vortem-ai' ); ?></span>
									<svg class="sort-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M7 13l5 5 5-5M7 6l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
							</th>
							<th class="sortable" data-sort="cwe">
								<div class="th-content">
									<span><?php esc_html_e( 'Issue Type (CWE)', 'vortem-ai' ); ?></span>
									<svg class="sort-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M7 13l5 5 5-5M7 6l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
							</th>
							<th><?php esc_html_e( 'Plugin', 'vortem-ai' ); ?></th>
							<th><?php esc_html_e( 'Description', 'vortem-ai' ); ?></th>
							<th class="sortable" data-sort="published_date">
								<div class="th-content">
									<span><?php esc_html_e( 'Published', 'vortem-ai' ); ?></span>
									<svg class="sort-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M7 13l5 5 5-5M7 6l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</div>
							</th>
							<th><?php esc_html_e( 'Actions', 'vortem-ai' ); ?></th>
						</tr>
					</thead>
					<tbody id="vortem-security-results-table-body">
						<!-- Table rows will be populated by JavaScript -->
					</tbody>
				</table>
			</div>

			<!-- Empty State -->
			<div id="vortem-security-results-empty" class="empty-state hidden">
				<div class="empty-icon">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
				<h3 class="empty-title"><?php esc_html_e( 'No security issues found', 'vortem-ai' ); ?></h3>
				<p class="empty-description"><?php esc_html_e( 'All plugins appear to be secure', 'vortem-ai' ); ?></p>
			</div>

			<!-- Pagination -->
			<div id="vortem-security-results-pagination" class="security-pagination"></div>
		</div>
	</div>

	<!-- Details Modal -->
	<div id="vortem-security-results-modal" class="security-modal">
		<div class="modal-overlay"></div>
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title" id="vortem-security-results-modal-title"><?php esc_html_e( 'Security Issue Details', 'vortem-ai' ); ?></h2>
				<button id="vortem-security-results-modal-close" class="modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'vortem-ai' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
			<div class="modal-body" id="vortem-security-results-modal-body">
				<!-- Modal content will be populated by JavaScript -->
			</div>
		</div>
	</div>
</div>

<?php
// Enqueue security results JavaScript
wp_enqueue_script(
	'vortem-security-results',
	VORTEM_PLUGIN_URL . 'assets/js/security-results.js',
	array( 'jquery' ),
	VORTEM_VERSION,
	true
);

// Localize script with configuration data
require_once VORTEM_PLUGIN_DIR . 'includes/class-vortem-config.php';
$vortem_api_server = Vortem_Config::get_primary_api_server();
$vortem_api_url    = Vortem_Config::build_api_url( $vortem_api_server, 'security_wordpress_match' );

wp_localize_script(
	'vortem-security-results',
	'vortemSecurityResultsConfig',
	array(
		'apiUrl'  => $vortem_api_url,
		'nonce'   => wp_create_nonce( 'vortem_security_results_nonce' ),
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'strings' => array(
			'loading'         => __( 'Loading security data...', 'vortem-ai' ),
			'error'           => __( 'Failed to load security data', 'vortem-ai' ),
			'noIssues'        => __( 'No security issues found', 'vortem-ai' ),
			'viewDetails'     => __( 'View Details', 'vortem-ai' ),
			'close'           => __( 'Close', 'vortem-ai' ),
			'references'      => __( 'References', 'vortem-ai' ),
			'published'       => __( 'Published', 'vortem-ai' ),
			'lastModified'    => __( 'Last Modified', 'vortem-ai' ),
			'affectedVersion' => __( 'Affected Version', 'vortem-ai' ),
			'fixedVersion'    => __( 'Fixed Version', 'vortem-ai' ),
		),
	)
);
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
?>
