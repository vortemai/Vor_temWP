<?php
/**
 * Orders Page Template
 *
 * @package VortemAI
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get WooCommerce order statuses
$vortem_order_statuses = array();
if ( function_exists( 'wc_get_order_statuses' ) ) {
	$vortem_order_statuses = wc_get_order_statuses();
}
?>
<div class="wrap vortem-orders-wrap">
	<div id="vortem-orders-app" class="container">
		<!-- Header -->
		<div class="header">
			<div class="header-content">
				<div class="header-left">
					<div class="header-icon">
						<i data-lucide="shopping-bag"></i>
					</div>
					<div>
						<h1 class="header-title"><?php echo esc_html__( 'Orders', 'vortem-ai' ); ?></h1>
						<p class="header-subtitle"><?php echo esc_html__( 'View and manage your store orders', 'vortem-ai' ); ?></p>
					</div>
				</div>
				<div class="header-actions">
					<button class="btn-secondary" id="refresh-orders" title="<?php echo esc_attr__( 'Refresh Orders', 'vortem-ai' ); ?>">
						<i data-lucide="refresh-cw"></i>
						<span class="btn-text"><?php echo esc_html__( 'Refresh', 'vortem-ai' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<div class="top-controls">
			<div class="search-wrap">
				<svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="m21 21-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<input id="search-orders" class="input search-input-orders" type="search" placeholder="<?php echo esc_attr__( 'Search orders by number, customer name, or email...', 'vortem-ai' ); ?>" />
			</div>
			<div class="right-controls">
				<button id="toggle-recent-orders" class="btn-filter-toggle" title="<?php echo esc_attr__( 'Sort by date', 'vortem-ai' ); ?>" aria-label="<?php echo esc_attr__( 'Toggle sort order', 'vortem-ai' ); ?>">
					<svg id="sort-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 2v20M12 2l4 4M12 2L8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<span><?php echo esc_html__( 'Sort by Date', 'vortem-ai' ); ?></span>
				</button>
				<select id="filter-status" class="input">
					<option value="all"><?php echo esc_html__( 'All Statuses', 'vortem-ai' ); ?></option>
					<?php foreach ( $vortem_order_statuses as $vortem_status_key => $vortem_status_label ) : ?>
						<option value="<?php echo esc_attr( $vortem_status_key ); ?>"><?php echo esc_html( $vortem_status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="date-filter-wrapper">
					<label for="filter-date-from" class="date-filter-label"><?php echo esc_html__( 'Start Date', 'vortem-ai' ); ?></label>
					<input type="date" id="filter-date-from" class="input" />
				</div>
				<div class="date-filter-wrapper">
					<label for="filter-date-to" class="date-filter-label"><?php echo esc_html__( 'End Date', 'vortem-ai' ); ?></label>
					<input type="date" id="filter-date-to" class="input" />
				</div>
			</div>
		</div>

		<div id="orders-stats" class="stats"></div>

		<div class="table-wrap" id="orders-table-wrap">
			<table class="table" id="orders-table">
				<thead>
					<tr>
						<th style="width:80px;"><?php echo esc_html__( 'Order', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Date', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Customer', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Items', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Total', 'vortem-ai' ); ?></th>
						<th><?php echo esc_html__( 'Payment', 'vortem-ai' ); ?></th>
						<th style="width:100px;"><?php echo esc_html__( 'Actions', 'vortem-ai' ); ?></th>
					</tr>
				</thead>
				<tbody id="orders-tbody">
					<tr>
						<td colspan="8" class="loading">
							<div class="spinner"></div>
							<?php echo esc_html__( 'Loading orders...', 'vortem-ai' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div id="pagination-container" class="pagination-container"></div>
	</div>

	<!-- Order Details Modal -->
	<div class="modal" id="order-details-modal" aria-hidden="true" role="dialog" aria-labelledby="order-details-modal-title">
		<div class="modal-backdrop" data-close="order-details-modal"></div>
		<div class="modal-dialog modal-large">
			<div class="modal-header">
				<h3 id="order-details-modal-title"><?php echo esc_html__( 'Order Details', 'vortem-ai' ); ?></h3>
				<button class="btn btn-icon" id="close-order-details-modal" aria-label="Close">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						<line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					</svg>
				</button>
			</div>
			<div class="modal-body" id="order-details-content">
				<div class="loading">
					<div class="spinner"></div>
					<?php echo esc_html__( 'Loading order details...', 'vortem-ai' ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Toast Notification -->
	<div id="toast" class="toast" role="status" aria-live="polite"></div>
</div>

