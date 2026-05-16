/**
 * Security Results Page JavaScript
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation, AJAX, and table rendering
 */
(function($) {
	'use strict';

	let securityData = [];
	let filteredData = [];
	let currentPage = 1;
	const itemsPerPage = 20;
	let currentSort = { field: null, direction: 'asc' };

	const config = window.vortemSecurityResultsConfig || {};

	function vortemResultsAffectedItemLabel(item) {
		const t = item && item.type ? String(item.type).toLowerCase() : '';
		if (t === 'core' || t === 'wp-core') {
			return 'WordPress Core';
		}
		return item.customer_plugin_name || item.customer_plugin || item.customer_theme || item.customer_theme_name || (item.matched_theme && item.matched_theme.theme_name) || '';
	}

	function vortemResultsAffectedItemVersion(item) {
		const t = item && item.type ? String(item.type).toLowerCase() : '';
		if (t === 'core' || t === 'wp-core') {
			return item.customer_wordpress_version || '';
		}
		return item.customer_plugin_version || item.customer_theme_version || '';
	}

	$(document).ready(function() {
		// Load data on page load
		loadSecurityData();

		// Refresh button
		$('#vortem-security-results-refresh').on('click', function() {
			loadSecurityData();
		});

		// Retry button
		$('#vortem-security-results-retry').on('click', function() {
			loadSecurityData();
		});

		// Filter handlers
		$('#vortem-security-results-severity-filter, #vortem-security-results-issue-type-filter, #vortem-security-results-score-filter, #vortem-security-results-plugin-filter').on('change', function() {
			applyFilters();
		});

		// Clear filters
		$('#vortem-security-results-clear-filters').on('click', function() {
			$('#vortem-security-results-severity-filter').val('all');
			$('#vortem-security-results-issue-type-filter').val('all');
			$('#vortem-security-results-score-filter').val('all');
			$('#vortem-security-results-plugin-filter').val('all');
			applyFilters();
		});

		// Table sorting
		$('.security-results-table th.sortable').on('click', function() {
			const sortField = $(this).data('sort');
			if (sortField) {
				sortData(sortField);
				updateSortIndicator($(this), sortField);
				currentPage = 1;
				renderTable();
				renderPagination();
			}
		});

		// Modal close handlers
		$('#vortem-security-results-modal-close, #vortem-security-results-modal .modal-overlay').on('click', function() {
			closeModal();
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#vortem-security-results-modal').hasClass('active')) {
				closeModal();
			}
		});
	});

	/**
	 * Load security data from API
	 */
	function loadSecurityData() {
		showLoading();
		hideError();
		hideTable();

		// Get plugin and theme data via AJAX
		if (config.ajaxUrl) {
			// Get plugin data
			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vortem_get_plugin_data',
					nonce: config.nonce
				},
				success: function(pluginResponse) {
					const plugins = (pluginResponse.success && pluginResponse.data && pluginResponse.data.plugins) ? pluginResponse.data.plugins : [];

					// Get theme data
					$.ajax({
						url: config.ajaxUrl,
						type: 'POST',
						data: {
							action: 'vortem_get_theme_data',
							nonce: config.nonce
						},
						success: function(themeResponse) {
							const themes = (themeResponse.success && themeResponse.data && themeResponse.data.themes) ? themeResponse.data.themes : [];

							// Send both plugin and theme data to API
							sendDataToAPI(plugins, themes);
						},
						error: function() {
							// Send only plugin data if theme data fails
							sendDataToAPI(plugins, []);
						}
					});
				},
				error: function() {
					// Try to get theme data even if plugin data fails
					$.ajax({
						url: config.ajaxUrl,
						type: 'POST',
						data: {
							action: 'vortem_get_theme_data',
							nonce: config.nonce
						},
						success: function(themeResponse) {
							const themes = (themeResponse.success && themeResponse.data && themeResponse.data.themes) ? themeResponse.data.themes : [];
							sendDataToAPI([], themes);
						},
						error: function() {
							// Send empty data if both fail
							sendDataToAPI([], []);
						}
					});
				}
			});
		} else {
			// No AJAX URL available, send empty data
			sendDataToAPI([], []);
		}
	}

	/**
	 * Send plugin and theme data to API first (POST), then fetch results (GET)
	 */
	function sendDataToAPI(plugins, themes) {
		let pluginSuccess = false;
		let themeSuccess = false;

		// Function to check if both requests are done and fetch results
		function checkAndFetchResults() {
			if ((plugins.length === 0 || pluginSuccess) && (themes.length === 0 || themeSuccess)) {
				VortemLogger.log('Data sent successfully, fetching results...');
				sendSecurityRequest();
			}
		}

		// Send plugins data if available
		if (plugins.length > 0) {
			sendPluginsToAPI(plugins, function(success) {
				pluginSuccess = success;
				checkAndFetchResults();
			});
		} else {
			pluginSuccess = true;
		}

		// Send themes data if available
		if (themes.length > 0) {
			sendThemesToAPI(themes, function(success) {
				themeSuccess = success;
				checkAndFetchResults();
			});
		} else {
			themeSuccess = true;
		}

		// If no data to send, fetch results immediately
		if (plugins.length === 0 && themes.length === 0) {
			sendSecurityRequest();
		}
	}

	/**
	 * Send plugins data to external API
	 */
	function sendPluginsToAPI(plugins, callback) {
		// Format plugins data according to API requirements
		const formattedPlugins = plugins.map(function(plugin) {
			return {
				file: plugin.file || '',
				name: plugin.name || '',
				version: plugin.version || '',
				description: plugin.description || '',
				author: plugin.author || '',
				plugin_uri: plugin.plugin_uri || '',
				status: plugin.status || 'inactive',
				last_modified: plugin.last_modified || '',
				requires_wp_version: plugin.requires_wp_version || '',
				requires_php: plugin.requires_php || ''
			};
		});

		const requestData = {
			plugins: formattedPlugins
		};

		// Build plugin API URL (POST endpoint)
		const pluginApiUrl = config.apiUrl.replace('/plugin/match', '/plugin');

		// Make direct fetch request to external API
		fetch(pluginApiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			body: JSON.stringify(requestData)
		})
		.then(function(response) {
			if (response.ok) {
				VortemLogger.log('Plugin data sent successfully');
				callback(true);
			} else {
				throw new Error('HTTP ' + response.status + ': ' + response.statusText);
			}
		})
		.catch(function(error) {
			VortemLogger.warn('Error sending plugin data:', error.message || error);
			callback(false);
		});
	}

	/**
	 * Send themes data to external API
	 */
	function sendThemesToAPI(themes, callback) {
		const formattedThemes = themes.map(function(theme) {
			const ss = theme.stylesheet != null && theme.stylesheet !== false ? String(theme.stylesheet).toLowerCase() : '';
			const tpl = theme.template != null && theme.template !== false ? String(theme.template).toLowerCase() : '';
			const displayName = theme.name != null && theme.name !== false ? String(theme.name) : '';
			return {
				stylesheet: ss,
				template: tpl,
				name: displayName,
				version: theme.version != null && theme.version !== false ? String(theme.version) : '',
				status: (theme.status && (theme.status === 'active' || theme.status === 'inactive')) ? theme.status : 'inactive'
			};
		});

		const requestData = {
			themes: formattedThemes
		};

		// Build theme API URL (POST endpoint)
		const themeApiUrl = config.apiUrl.replace('/plugin/match', '/theme');

		// Make direct fetch request to external API
		fetch(themeApiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			body: JSON.stringify(requestData)
		})
		.then(function(response) {
			if (response.ok) {
				VortemLogger.log('Theme data sent successfully');
				callback(true);
			} else {
				throw new Error('HTTP ' + response.status + ': ' + response.statusText);
			}
		})
		.catch(function(error) {
			VortemLogger.warn('Error sending theme data:', error.message || error);
			callback(false);
		});
	}

	/**
	 * Send security request to API (GET results)
	 */
	function sendSecurityRequest() {
		$.ajax({
			url: config.apiUrl,
			type: 'GET',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			dataType: 'json',
			timeout: 30000,
			success: function(response) {
				if (Array.isArray(response)) {
					securityData = response;
					filteredData = [...securityData];
					populateFilters();
					updateStatistics();
					applyFilters();
					hideLoading();
					showTable();
				} else {
					showError('Invalid response format from server.');
				}
			},
			error: function(xhr, status, error) {
				let errorMessage = 'Failed to load security data.';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMessage = xhr.responseJSON.message;
				} else if (xhr.status === 0) {
					errorMessage = 'Unable to connect to server. Please check your connection.';
				} else if (xhr.status === 401) {
					errorMessage = 'Authentication failed. Please complete the setup wizard.';
				} else if (xhr.status === 403) {
					errorMessage = 'Access denied. Please check your session token.';
				} else if (xhr.status >= 500) {
					errorMessage = 'Server error. Please try again later.';
				}
				showError(errorMessage);
			}
		});
	}

	/**
	 * Populate filter dropdowns
	 */
	function populateFilters() {
		// Populate issue type filter
		const issueTypes = [...new Set(securityData.map(item => item.cwe).filter(Boolean))];
		const $issueTypeFilter = $('#vortem-security-results-issue-type-filter');
		issueTypes.sort().forEach(function(cwe) {
			$issueTypeFilter.append($('<option>').val(cwe).text(cwe));
		});

		// Populate plugin/theme filter (new spec uses `customer_plugin_name`)
		const items = [...new Set(securityData.map(function(item) { return vortemResultsAffectedItemLabel(item); }).filter(Boolean))];
		const $pluginFilter = $('#vortem-security-results-plugin-filter');
		items.sort().forEach(function(item) {
			$pluginFilter.append($('<option>').val(item).text(item));
		});
	}

	/**
	 * Apply filters to data
	 */
	function applyFilters() {
		const severityFilter = $('#vortem-security-results-severity-filter').val();
		const issueTypeFilter = $('#vortem-security-results-issue-type-filter').val();
		const scoreFilter = $('#vortem-security-results-score-filter').val();
		const pluginFilter = $('#vortem-security-results-plugin-filter').val();

		filteredData = securityData.filter(function(item) {
			// Severity filter
			if (severityFilter !== 'all' && String(item.severity || '').toLowerCase() !== String(severityFilter).toLowerCase()) {
				return false;
			}

			// Issue type filter
			if (issueTypeFilter !== 'all' && item.cwe !== issueTypeFilter) {
				return false;
			}

			// Score filter
			if (scoreFilter !== 'all') {
				// Support both cvss and cvss_score fields
				const cvssValue = item.cvss_score !== undefined ? item.cvss_score : item.cvss;
				const score = parseFloat(cvssValue) || 0;
				if (scoreFilter === '0-3' && (score < 0 || score >= 4)) {
					return false;
				} else if (scoreFilter === '4-6' && (score < 4 || score >= 7)) {
					return false;
				} else if (scoreFilter === '7-8' && (score < 7 || score >= 9)) {
					return false;
				} else if (scoreFilter === '9-10' && (score < 9 || score > 10)) {
					return false;
				}
			}

			// Plugin/Theme filter (new spec uses `customer_plugin_name`)
			const itemName = vortemResultsAffectedItemLabel(item);
			if (pluginFilter !== 'all' && itemName !== pluginFilter) {
				return false;
			}

			return true;
		});

		// Apply current sort
		if (currentSort.field) {
			sortData(currentSort.field, false);
		}

		currentPage = 1;
		updateStatistics();
		renderTable();
		renderPagination();
	}

	/**
	 * Sort data
	 */
	function sortData(field, updateIndicator = true) {
		if (currentSort.field === field) {
			currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
		} else {
			currentSort.field = field;
			currentSort.direction = 'asc';
		}

		filteredData.sort(function(a, b) {
			let aVal;
			let bVal;

			// Map sort fields to the new plugin spec, falling back to legacy names
			if (field === 'cve_id' || field === 'cve') {
				aVal = a.cve || a.cve_id;
				bVal = b.cve || b.cve_id;
			} else if (field === 'cvss' || field === 'cvss_score') {
				aVal = a.cvss_score !== undefined ? a.cvss_score : a.cvss;
				bVal = b.cvss_score !== undefined ? b.cvss_score : b.cvss;
			} else if (field === 'published' || field === 'published_date') {
				aVal = a.published_date || a.published;
				bVal = b.published_date || b.published;
			} else if (field === 'lastModified' || field === 'last_modified') {
				aVal = a.last_modified || a.lastModified || a.last_modified_date;
				bVal = b.last_modified || b.lastModified || b.last_modified_date;
			} else {
				aVal = a[field];
				bVal = b[field];
			}

			// Handle different data types
			if (field === 'cvss' || field === 'cvss_score') {
				aVal = parseFloat(aVal) || 0;
				bVal = parseFloat(bVal) || 0;
			} else if (
				field === 'published' || field === 'published_date' ||
				field === 'lastModified' || field === 'last_modified'
			) {
				aVal = new Date(aVal).getTime() || 0;
				bVal = new Date(bVal).getTime() || 0;
			} else {
				aVal = String(aVal || '').toLowerCase();
				bVal = String(bVal || '').toLowerCase();
			}

			if (aVal < bVal) {
				return currentSort.direction === 'asc' ? -1 : 1;
			}
			if (aVal > bVal) {
				return currentSort.direction === 'asc' ? 1 : -1;
			}
			return 0;
		});

		if (updateIndicator) {
			const $th = $(`.security-results-table th[data-sort="${field}"]`);
			updateSortIndicator($th, field);
		}
	}

	/**
	 * Update sort indicator
	 */
	function updateSortIndicator($th, field) {
		$('.security-results-table th.sortable').removeClass('sort-asc sort-desc');
		if (currentSort.field === field) {
			$th.addClass('sort-' + currentSort.direction);
		}
	}

	/**
	 * Update statistics
	 */
	function updateStatistics() {
		const total = filteredData.length;
		const critical = filteredData.filter(item => item.severity === 'CRITICAL').length;
		const high = filteredData.filter(item => item.severity === 'HIGH').length;
		const medium = filteredData.filter(item => item.severity === 'MEDIUM').length;

		$('#totalIssues').text(total);
		$('#criticalIssues').text(critical);
		$('#highIssues').text(high);
		$('#mediumIssues').text(medium);
	}

	/**
	 * Render table
	 */
	function renderTable() {
		const $tbody = $('#vortem-security-results-table-body');
		$tbody.empty();

		if (filteredData.length === 0) {
			$('#vortem-security-results-empty').removeClass('hidden');
			return;
		}

		$('#vortem-security-results-empty').addClass('hidden');

		// Calculate pagination
		const startIndex = (currentPage - 1) * itemsPerPage;
		const endIndex = startIndex + itemsPerPage;
		const paginatedData = filteredData.slice(startIndex, endIndex);

		paginatedData.forEach(function(item) {
			const $row = $('<tr>');

			// CVE (new spec uses `cve`; fall back to legacy `cve_id`)
			const cveValue = item.cve || item.cve_id || '';
			$row.append($('<td>').addClass('table-cve-id').html(
				$('<a>').attr('href', 'https://cve.mitre.org/cgi-bin/cvename.cgi?name=' + escapeHtml(cveValue))
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.text(cveValue || '—')
			));

			// Severity
			const severityClass = 'severity-' + (item.severity || 'unknown').toLowerCase();
			$row.append($('<td>').html(
				$('<span>').addClass('severity-badge').addClass(severityClass).text(item.severity || '—')
			));

			// CVSS Score (new spec uses `cvss_score`)
			const cvssValue = item.cvss_score !== undefined ? item.cvss_score : item.cvss;
			const cvss = parseFloat(cvssValue) || 0;
			const scoreClass = cvss >= 9 ? 'score-critical' : cvss >= 7 ? 'score-high' : cvss >= 4 ? 'score-medium' : 'score-low';
			const cvssDisplay = cvss > 0 ? cvss.toFixed(1) : 'N/A';
			$row.append($('<td>').html(
				$('<span>').addClass('score-badge').addClass(scoreClass).text(cvssDisplay)
			));

			// Issue Type (CWE)
			$row.append($('<td>').addClass('table-cwe').text(item.cwe || '—'));

			// Plugin/Theme (new spec uses `customer_plugin_name` and includes `customer_plugin_version`)
			const itemName = vortemResultsAffectedItemLabel(item) || '—';
			const itemVersion = vortemResultsAffectedItemVersion(item);
			const $pluginCell = $('<td>').addClass('table-plugin');
			$pluginCell.append($('<span>').addClass('plugin-name').text(itemName));
			if (itemVersion) {
				$pluginCell.append($('<span>').addClass('plugin-version').text(' v' + itemVersion));
			}
			$row.append($pluginCell);

			// Description
			const description = item.description || '—';
			const maxLength = 100;
			const truncatedDesc = description.length > maxLength ? description.substring(0, maxLength) + '...' : description;
			$row.append($('<td>').addClass('table-description').html(
				$('<span>').addClass('description-text').text(truncatedDesc)
			));

			// Published (new spec uses `published_date`)
			const publishedRaw = item.published_date || item.published;
			$row.append($('<td>').addClass('table-published').text(formatDate(publishedRaw)));

			// Actions
			const $actions = $('<td>').addClass('table-actions');
			const $viewBtn = $('<button>')
				.addClass('table-action-btn')
				.attr('title', config.strings.viewDetails || 'View Details')
				.attr('type', 'button')
				.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>')
				.on('click', function() {
					openModal(item);
				});
			$actions.append($viewBtn);
			$row.append($actions);

			$tbody.append($row);
		});
	}

	/**
	 * Render pagination
	 */
	function renderPagination() {
		const $pagination = $('#vortem-security-results-pagination');
		$pagination.empty();

		if (filteredData.length === 0) {
			$pagination.hide();
			return;
		}

		const totalPages = Math.ceil(filteredData.length / itemsPerPage);

		if (totalPages <= 1) {
			$pagination.hide();
			return;
		}

		$pagination.show();

		const $paginationContainer = $('<div>').addClass('pagination-container');
		
		// Previous button
		const $prevBtn = $('<button>')
			.addClass('pagination-btn')
			.addClass('pagination-prev')
			.text('Previous')
			.attr('type', 'button')
			.prop('disabled', currentPage === 1);
		
		if (currentPage > 1) {
			$prevBtn.on('click', function() {
				currentPage--;
				renderTable();
				renderPagination();
				$('html, body').animate({ scrollTop: $('#vortem-security-results-table-container').offset().top - 100 }, 300);
			});
		}

		$paginationContainer.append($prevBtn);

		// Page numbers
		const $pageNumbers = $('<div>').addClass('pagination-numbers');
		
		let startPage = Math.max(1, currentPage - 2);
		let endPage = Math.min(totalPages, currentPage + 2);

		if (currentPage <= 3) {
			endPage = Math.min(5, totalPages);
		}
		if (currentPage >= totalPages - 2) {
			startPage = Math.max(1, totalPages - 4);
		}

		// First page
		if (startPage > 1) {
			const $firstPage = $('<button>')
				.addClass('pagination-btn')
				.addClass('pagination-number')
				.text('1')
				.attr('type', 'button');
			$firstPage.on('click', function() {
				currentPage = 1;
				renderTable();
				renderPagination();
				$('html, body').animate({ scrollTop: $('#vortem-security-results-table-container').offset().top - 100 }, 300);
			});
			$pageNumbers.append($firstPage);
			if (startPage > 2) {
				$pageNumbers.append($('<span>').addClass('pagination-ellipsis').text('...'));
			}
		}

		// Page number buttons
		for (let i = startPage; i <= endPage; i++) {
			const $pageBtn = $('<button>')
				.addClass('pagination-btn')
				.addClass('pagination-number')
				.text(i)
				.attr('type', 'button')
				.toggleClass('active', i === currentPage);
			
			if (i !== currentPage) {
				$pageBtn.on('click', function() {
					currentPage = i;
					renderTable();
					renderPagination();
					$('html, body').animate({ scrollTop: $('#vortem-security-results-table-container').offset().top - 100 }, 300);
				});
			}
			
			$pageNumbers.append($pageBtn);
		}

		// Last page
		if (endPage < totalPages) {
			if (endPage < totalPages - 1) {
				$pageNumbers.append($('<span>').addClass('pagination-ellipsis').text('...'));
			}
			const $lastPage = $('<button>')
				.addClass('pagination-btn')
				.addClass('pagination-number')
				.text(totalPages)
				.attr('type', 'button');
			$lastPage.on('click', function() {
				currentPage = totalPages;
				renderTable();
				renderPagination();
				$('html, body').animate({ scrollTop: $('#vortem-security-results-table-container').offset().top - 100 }, 300);
			});
			$pageNumbers.append($lastPage);
		}

		$paginationContainer.append($pageNumbers);

		// Next button
		const $nextBtn = $('<button>')
			.addClass('pagination-btn')
			.addClass('pagination-next')
			.text('Next')
			.attr('type', 'button')
			.prop('disabled', currentPage === totalPages);
		
		if (currentPage < totalPages) {
			$nextBtn.on('click', function() {
				currentPage++;
				renderTable();
				renderPagination();
				$('html, body').animate({ scrollTop: $('#vortem-security-results-table-container').offset().top - 100 }, 300);
			});
		}

		$paginationContainer.append($nextBtn);

		// Page info
		const startIndex = (currentPage - 1) * itemsPerPage + 1;
		const endIndex = Math.min(currentPage * itemsPerPage, filteredData.length);
		const $pageInfo = $('<div>').addClass('pagination-info')
			.text(`Showing ${startIndex}-${endIndex} of ${filteredData.length}`);

		$pagination.append($paginationContainer);
		$pagination.append($pageInfo);
	}

	/**
	 * Open details modal
	 */
	function openModal(item) {
		const $modal = $('#vortem-security-results-modal');
		const $modalBody = $('#vortem-security-results-modal-body');
		const $modalTitle = $('#vortem-security-results-modal-title');

		const cveValue = item.cve || item.cve_id || '';
		$modalTitle.text(cveValue || 'Security Issue Details');
		$modalBody.empty();

		// Build modal content
		const content = [];
		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">CVE:</div>');
		content.push('<div class="modal-detail-value">');
		content.push('<a href="https://cve.mitre.org/cgi-bin/cvename.cgi?name=' + escapeHtml(cveValue) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(cveValue || '—') + '</a>');
		content.push('</div>');
		content.push('</div>');

		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">Severity:</div>');
		content.push('<div class="modal-detail-value">');
		const severityClass = 'severity-' + (item.severity || 'unknown').toLowerCase();
		content.push('<span class="severity-badge ' + severityClass + '">' + escapeHtml(item.severity || '—') + '</span>');
		content.push('</div>');
		content.push('</div>');

		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">CVSS Score:</div>');
		content.push('<div class="modal-detail-value">');
		// New spec uses `cvss_score`; legacy `cvss` kept as fallback
		const cvssValue = item.cvss_score !== undefined ? item.cvss_score : item.cvss;
		const cvss = parseFloat(cvssValue) || 0;
		const scoreClass = cvss >= 9 ? 'score-critical' : cvss >= 7 ? 'score-high' : cvss >= 4 ? 'score-medium' : 'score-low';
		const cvssDisplay = cvss > 0 ? cvss.toFixed(1) : 'N/A';
		content.push('<span class="score-badge ' + scoreClass + '">' + cvssDisplay + '</span>');
		content.push('</div>');
		content.push('</div>');

		if (item.cwe) {
			content.push('<div class="modal-detail-row">');
			content.push('<div class="modal-detail-label">Issue Type (CWE):</div>');
			content.push('<div class="modal-detail-value">' + escapeHtml(item.cwe) + '</div>');
			content.push('</div>');
		}

		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">Plugin:</div>');
		const itemName = item.customer_plugin_name || item.customer_plugin || item.customer_theme || item.customer_theme_name || item.matched_theme?.theme_name || '—';
		content.push('<div class="modal-detail-value">' + escapeHtml(itemName) + '</div>');
		content.push('</div>');

		const itemVersion = item.customer_plugin_version || item.customer_theme_version || '';
		if (itemVersion) {
			content.push('<div class="modal-detail-row">');
			content.push('<div class="modal-detail-label">' + (config.strings.affectedVersion || 'Affected Version') + ':</div>');
			content.push('<div class="modal-detail-value">' + escapeHtml(itemVersion) + '</div>');
			content.push('</div>');
		}

		const fixedVersion = item.fixed_version || item.fixed_in || '';
		if (fixedVersion) {
			content.push('<div class="modal-detail-row">');
			content.push('<div class="modal-detail-label">' + (config.strings.fixedVersion || 'Fixed Version') + ':</div>');
			content.push('<div class="modal-detail-value">' + escapeHtml(fixedVersion) + '</div>');
			content.push('</div>');
		}

		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">Description:</div>');
		content.push('<div class="modal-detail-value">' + escapeHtml(item.description || '—') + '</div>');
		content.push('</div>');

		const publishedRaw = item.published_date || item.published;
		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">' + (config.strings.published || 'Published') + ':</div>');
		content.push('<div class="modal-detail-value">' + escapeHtml(formatDate(publishedRaw) || '—') + '</div>');
		content.push('</div>');

		const lastModifiedRaw = item.last_modified || item.last_modified_date || item.lastModified;
		content.push('<div class="modal-detail-row">');
		content.push('<div class="modal-detail-label">' + (config.strings.lastModified || 'Last Modified') + ':</div>');
		content.push('<div class="modal-detail-value">' + escapeHtml(formatDate(lastModifiedRaw) || '—') + '</div>');
		content.push('</div>');

		if (item.references && item.references.length > 0) {
			content.push('<div class="modal-detail-row">');
			content.push('<div class="modal-detail-label">' + (config.strings.references || 'References') + ':</div>');
			content.push('<div class="modal-detail-value">');
			content.push('<ul class="references-list">');
			item.references.forEach(function(ref) {
				content.push('<li><a href="' + escapeHtml(ref) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(ref) + '</a></li>');
			});
			content.push('</ul>');
			content.push('</div>');
			content.push('</div>');
		}

		$modalBody.html(content.join(''));
		$modal.addClass('active');
		$('body').addClass('modal-open');
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		const $modal = $('#vortem-security-results-modal');
		$modal.removeClass('active');
		$('body').removeClass('modal-open');
	}

	/**
	 * Show loading state
	 */
	function showLoading() {
		$('#vortem-security-results-loading').show();
		$('#vortem-security-results-error').addClass('hidden');
		$('#vortem-security-results-filters').addClass('hidden');
		$('#vortem-security-results-stats').addClass('hidden');
		$('#vortem-security-results-table-container').addClass('hidden');
	}

	/**
	 * Hide loading state
	 */
	function hideLoading() {
		$('#vortem-security-results-loading').hide();
	}

	/**
	 * Show error state
	 */
	function showError(message) {
		$('#vortem-security-results-error-message').text(message);
		$('#vortem-security-results-error').removeClass('hidden');
		$('#vortem-security-results-loading').hide();
		$('#vortem-security-results-filters').addClass('hidden');
		$('#vortem-security-results-stats').addClass('hidden');
		$('#vortem-security-results-table-container').addClass('hidden');
	}

	/**
	 * Hide error state
	 */
	function hideError() {
		$('#vortem-security-results-error').addClass('hidden');
	}

	/**
	 * Show table
	 */
	function showTable() {
		$('#vortem-security-results-filters').removeClass('hidden');
		$('#vortem-security-results-stats').removeClass('hidden');
		$('#vortem-security-results-table-container').removeClass('hidden');
	}

	/**
	 * Hide table
	 */
	function hideTable() {
		$('#vortem-security-results-filters').addClass('hidden');
		$('#vortem-security-results-stats').addClass('hidden');
		$('#vortem-security-results-table-container').addClass('hidden');
	}

	/**
	 * Format date string
	 */
	function formatDate(dateString) {
		if (!dateString) {
			return '—';
		}
		
		try {
			const date = new Date(dateString);
			if (isNaN(date.getTime())) {
				return dateString;
			}
			
			const options = {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit'
			};
			
			return date.toLocaleDateString('en-US', options);
		} catch (e) {
			return dateString;
		}
	}

	/**
	 * Escape HTML
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}
})(jQuery);

