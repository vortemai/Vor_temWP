<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap bi-analytics-hub-dashboard">
	<!-- Decorative background elements -->
	<div class="background-elements">
		<div class="bg-blur bg-blur-1"></div>
		<div class="bg-blur bg-blur-2"></div>
		<div class="bg-blur bg-blur-3"></div>
	</div>

	<div class="container">
		<!-- Header -->
		<div class="header-section">
			<div class="header-badge">
				<div class="badge-line"></div>
				<span class="badge-text"><?php echo esc_html__( 'ANALYTICS DASHBOARD', 'vortem-ai' ); ?></span>
			</div>
			<h1 class="main-title">
				<?php echo esc_html__( 'Business Intelligence', 'vortem-ai' ); ?>
				<span class="title-gradient"><?php echo esc_html__( 'Analytics Hub', 'vortem-ai' ); ?></span>
			</h1>
			<p class="page-description">
				<?php echo esc_html__( 'Comprehensive analytics and insights powered by advanced data intelligence for data-driven decision making', 'vortem-ai' ); ?>
			</p>
		</div>

		<!-- KPI Performance - Featured Section -->
		<div class="section">
			<div class="section-header">
				<h2 class="section-title">
					<span class="title-line title-line-blue"></span>
					<?php echo esc_html__( 'Performance Overview', 'vortem-ai' ); ?>
				</h2>
				<p class="section-description"><?php echo esc_html__( 'Key performance indicators at a glance', 'vortem-ai' ); ?></p>
			</div>
			<div class="chart-wrapper featured">
				<div id="kpi-radar-container" class="chart-container">
					<div class="loading-skeleton">
						<div class="skeleton-header"></div>
						<div class="skeleton-content"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Analytics Insights - Two Column Grid -->
		<div class="section">
			<div class="section-header">
				<h2 class="section-title">
					<span class="title-line title-line-purple"></span>
					<?php echo esc_html__( 'Analytics Insights', 'vortem-ai' ); ?>
				</h2>
				<p class="section-description"><?php echo esc_html__( 'Deep dive into performance metrics', 'vortem-ai' ); ?></p>
			</div>
			<div class="grid-2-col">
				<div class="chart-wrapper">
					<div id="keywords-performance-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
				<div class="chart-wrapper">
					<div id="price-rating-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Customer Intelligence - Two Column Grid -->
		<div class="section">
			<div class="section-header">
				<h2 class="section-title">
					<span class="title-line title-line-pink"></span>
					<?php echo esc_html__( 'Customer Intelligence', 'vortem-ai' ); ?>
				</h2>
				<p class="section-description"><?php echo esc_html__( 'Understand customer behavior and trends', 'vortem-ai' ); ?></p>
			</div>
			<div class="grid-2-col">
				<div class="chart-wrapper">
					<div id="customer-sentiment-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
				<div class="chart-wrapper">
					<div id="trend-status-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Market Analysis - Full Width Featured -->
		<div class="section">
			<div class="section-header">
				<h2 class="section-title">
					<span class="title-line title-line-green"></span>
					<?php echo esc_html__( 'Market Analysis', 'vortem-ai' ); ?>
				</h2>
				<p class="section-description"><?php echo esc_html__( 'Comprehensive market comparison and category insights', 'vortem-ai' ); ?></p>
			</div>
			<div class="grid-1-col">
				<div class="chart-wrapper featured">
					<div id="market-comparison-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
				<div class="chart-wrapper">
					<div id="category-comparison-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Data Tables Section -->
		<div class="section">
			<div class="section-header">
				<h2 class="section-title">
					<span class="title-line title-line-indigo"></span>
					<?php echo esc_html__( 'Data Intelligence', 'vortem-ai' ); ?>
				</h2>
				<p class="section-description"><?php echo esc_html__( 'Detailed analytics tables and pricing insights', 'vortem-ai' ); ?></p>
			</div>
			<div class="data-tables">
				<div class="chart-wrapper">
					<div id="trend-index-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
				<div class="chart-wrapper">
					<div id="suggested-pricing-container" class="chart-container">
						<div class="loading-skeleton">
							<div class="skeleton-header"></div>
							<div class="skeleton-content"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

