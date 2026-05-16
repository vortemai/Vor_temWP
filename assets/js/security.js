/**
 * Security Page JavaScript (Plugin Inspector)
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and AJAX
 */
(function($) {
	'use strict';

	let allPlugins = [];
	let allThemes = [];
	let filteredItems = []; // Combined plugins and themes
	let currentPage = 1;
	const itemsPerPageCard = 9;
	const itemsPerPageTable = 10;
	let securityResultsData = []; // Store vulnerability data globally
	let securityDataLoaded = false; // Track if security data has been loaded
	let currentType = 'plugins'; // Current tab: 'plugins', 'themes', or 'wp-core'
	let currentResultsType = 'plugins'; // Current results tab: 'plugins', 'themes', or 'wp-core'
	let wpCoreData = null; // Store WordPress core data

	$(document).ready(function() {
		VortemLogger.log('Security: DOM Ready');
		
		// Initialize plugins and themes data from the page
		initializePlugins();
		initializeThemes();
		initializeWpCore();
		
		// Set initial search placeholder based on active tab
		const strings = window.vortemSecurityStrings || {};
		const activeTab = $('.vortem-security-tab-btn.active').data('tab') || 'plugins';
		if (activeTab === 'plugins' || activeTab === 'themes') {
			const placeholder = activeTab === 'plugins' 
				? (strings.search_plugins || 'Search plugins...')
				: (strings.search_themes || 'Search themes...');
			$('#vortem-security-search').attr('placeholder', placeholder);
		}

		// New tab switching for security tabs (overview, plugins, themes, core)
		$('.vortem-security-tab-btn').on('click', function() {
			const tab = $(this).data('tab');
			VortemLogger.log('Security: Switching to tab:', tab);
			
			// Update tab buttons
			$('.vortem-security-tab-btn').removeClass('active');
			$(this).addClass('active');
			
			// Update tab panels
			$('.vortem-tab-panel').removeClass('active');
			$('#tab-' + tab).addClass('active');
			
			// Show/hide filters based on tab
			if (tab === 'plugins' || tab === 'themes') {
				$('#vortem-security-filters-container').show();
				// Update search placeholder
				const strings = window.vortemSecurityStrings || {};
				const placeholder = tab === 'plugins' 
					? (strings.search_plugins || 'Search plugins...')
					: (strings.search_themes || 'Search themes...');
				$('#vortem-security-search').attr('placeholder', placeholder);
			} else {
				$('#vortem-security-filters-container').hide();
			}
			
			// Render content for the active tab
			if (tab === 'plugins' || tab === 'themes') {
				currentType = tab === 'plugins' ? 'plugins' : 'themes';
				currentPage = 1;
				filterAndRender();
			} else if (tab === 'overview') {
				// Update overview when switching to overview tab
				updateOverviewTab();
			}
			
			// If switching to results tab, fetch vulnerability data
			if (tab === 'results') {
				VortemLogger.log('Security: Results tab clicked, securityDataLoaded:', securityDataLoaded);
				
				const $loadingEl = $('#vortem-security-results-loading');
				VortemLogger.log('Security: Loading element exists:', $loadingEl.length);
				VortemLogger.log('Security: Loading element HTML:', $loadingEl.html());
				
				// Show loading immediately when switching to results tab
				if (!securityDataLoaded) {
					// Show loading state immediately with inline style to override everything
					$loadingEl.removeClass('hidden').css('display', 'flex');
					$('#vortem-security-results-error').addClass('hidden').css('display', 'none');
					$('#vortem-security-results-container').addClass('hidden').css('display', 'none');
					$('#vortem-security-results-tabs-wrapper').addClass('hidden').css('display', 'none');
					
					VortemLogger.log('Security: Loading classes after show:', $loadingEl.attr('class'));
					VortemLogger.log('Security: Loading is visible:', $loadingEl.is(':visible'));
					VortemLogger.log('Security: Loading display:', $loadingEl.css('display'));
					
					VortemLogger.log('Security: Fetching security results...');
					fetchSecurityResults();
				} else {
					// Data already loaded, just display it
					VortemLogger.log('Security: Displaying cached results:', securityResultsData.length);
					displaySecurityResults(securityResultsData);
				}
			}
		});

		// Fetch vulnerability data on page load (for showing counts in plugin cards)
		// Fetch in background to populate securityResultsData
		setTimeout(function() {
			fetchSecurityResultsSilently();
		}, 1000);

		// View toggle (both old and new selectors)
		$('#cardViewBtn, #tableViewBtn, .vortem-view-toggle-btn').on('click', function() {
			const view = $(this).data('view');
			switchView(view);
		});

		// Search functionality
		$('#vortem-security-search').on('input', function() {
			filterAndRender();
		});

		// Filter by status
		$('#vortem-security-status-filter').on('change', function() {
			filterAndRender();
		});

		// Sort functionality
		$('#vortem-security-sort').on('change', function() {
			filterAndRender();
		});

		// Custom dropdown for Filter by / Sort by (workspace-style select)
		initVortemFilterSelects();

		// Core tab card: initial state from current security data (or "—" until loaded)
		updateCoreTabCard();

		// Type tab switching (Plugins/Themes/WP Core) - for overview section (legacy support)
		$('.security-type-tab[data-type]').on('click', function() {
			const type = $(this).data('type');
			$('.security-type-tab[data-type]').removeClass('active');
			$(this).addClass('active');
			currentType = type;
			currentPage = 1;
			filterAndRender();
		});
		
		// Handle vulnerabilities badge clicks
		$(document).on('click', '.vortem-vulnerabilities-badge', function() {
			const itemName = $(this).data('plugin-name');
			const itemType = $(this).data('item-type') || 'plugin';
			openVulnerabilitiesDialog(itemName, itemType);
		});

		// View Page link: open CVE modal instead of external URL
		$(document).on('click', '.vortem-card-view-page', function(e) {
			const $link = $(this);
			const pluginName = $link.data('plugin-name');
			const themeName = $link.data('theme-name');
			if (pluginName || themeName) {
				e.preventDefault();
				openVulnerabilitiesDialog(pluginName || themeName, themeName ? 'theme' : 'plugin');
			}
		});
		
		// Handle overview summary card clicks to open vulnerabilities modal
		$(document).on('click', '#overview-total-vulns', function() {
			const totalVulns = parseInt($('#overview-total-vulns-value').text()) || 0;
			if (totalVulns > 0 && securityResultsData && securityResultsData.length > 0) {
				openAllVulnerabilitiesDialog();
			}
		});
		
		$(document).on('click', '#overview-critical-vulns', function() {
			const criticalVulns = parseInt($('#overview-critical-vulns-value').text()) || 0;
			if (criticalVulns > 0 && securityResultsData && securityResultsData.length > 0) {
				openAllVulnerabilitiesDialog('critical');
			}
		});
		
		$(document).on('click', '#overview-high-vulns', function() {
			const highVulns = parseInt($('#overview-high-vulns-value').text()) || 0;
			if (highVulns > 0 && securityResultsData && securityResultsData.length > 0) {
				openAllVulnerabilitiesDialog('high');
			}
		});
		
		$(document).on('click', '#overview-medium-vulns', function() {
			const mediumVulns = parseInt($('#overview-medium-vulns-value').text()) || 0;
			if (mediumVulns > 0 && securityResultsData && securityResultsData.length > 0) {
				openAllVulnerabilitiesDialog('medium');
			}
		});
		
		$(document).on('click', '#overview-low-vulns', function() {
			const lowVulns = parseInt($('#overview-low-vulns-value').text()) || 0;
			if (lowVulns > 0 && securityResultsData && securityResultsData.length > 0) {
				openAllVulnerabilitiesDialog('low');
			}
		});
		
		// Close vulnerabilities modal
		$('#vortem-vulnerabilities-modal-close').on('click', function() {
			$('#vortem-vulnerabilities-modal').hide();
		});

		// Core tab badge: open same CVE list modal as plugins (when issues exist)
		function openWpCoreVulnerabilitiesFromBadge(e) {
			if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			if (e.type === 'keydown') {
				e.preventDefault();
			}
			const $badge = $('#vortem-wp-core-badge');
			if (!$badge.length || !$badge.hasClass('is-clickable')) {
				return;
			}
			const count = securityResultsData.filter(function(v) {
				return (v.type || '') === 'wp-core';
			}).length;
			if (count < 1) {
				return;
			}
			const strings = window.vortemSecurityStrings || {};
			const coreName = strings.wordpress_core || 'WordPress Core';
			openVulnerabilitiesDialog(coreName, 'wp-core');
		}
		$(document).on('click', '#vortem-wp-core-badge', openWpCoreVulnerabilitiesFromBadge);
		$(document).on('keydown', '#vortem-wp-core-badge', openWpCoreVulnerabilitiesFromBadge);
		
		// Vulnerabilities severity filter
		$('#vuln-severity-filter').on('change', function() {
			filterVulnerabilities();
		});

		// Results type tab switching (Plugins/Themes/WP Core) - for results section
		$('.security-type-tab[data-results-type]').on('click', function() {
			const type = $(this).data('results-type');
			$('.security-type-tab[data-results-type]').removeClass('active');
			$(this).addClass('active');
			currentResultsType = type;
			displaySecurityResults(securityResultsData);
		});

		// Refresh button
		$('#vortem-security-refresh').on('click', function() {
			location.reload();
		});

		// Table sorting
		$('.security-table th.sortable').on('click', function() {
			const sortBy = $(this).data('sort');
			sortPlugins(sortBy);
			updateTableSortIndicator($(this), sortBy);
			currentPage = 1;
			renderTable();
			renderPagination();
		});

		// Description expand/collapse
		$(document).on('click', '.description-toggle-btn', function() {
			const $btn = $(this);
			const $descItem = $btn.closest('.description-item');
			const $descContent = $descItem.find('.description-content');
			const $descText = $descContent.find('.description-text');
			const $descFull = $descContent.find('.description-full');
			const isExpanded = $btn.attr('aria-expanded') === 'true';

			const strings = window.vortemSecurityStrings || {};
			const readMoreText = strings.read_more || 'Read more';
			const readLessText = strings.read_less || 'Read less';
			if (isExpanded) {
				$descText.show();
				$descFull.hide();
				$btn.text(readMoreText).attr('aria-expanded', 'false');
				$descItem.removeClass('expanded');
			} else {
				$descText.hide();
				$descFull.show();
				$btn.text(readLessText).attr('aria-expanded', 'true');
				$descItem.addClass('expanded');
			}
		});

		// Modal close handlers
		$('#vortem-plugin-modal-close').on('click', function() {
			closePluginModal();
		});

		$('#vortem-plugin-modal').on('click', function(e) {
			if ($(e.target).hasClass('modal-overlay')) {
				closePluginModal();
			}
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#vortem-plugin-modal').hasClass('active')) {
				closePluginModal();
			}
		});

		// Initial render - render immediately if plugins or themes are available
		// Use a small delay to ensure DOM and data are ready, but make it as fast as possible
		function doInitialRender() {
			if (allPlugins.length > 0 || allThemes.length > 0) {
				VortemLogger.log('Security: Rendering', allPlugins.length, 'plugins and', allThemes.length, 'themes');
				filterAndRender();
				
				// Automatically send plugin and theme data to API after page is fully loaded
				setTimeout(function() {
					sendDataToAPI();
				}, 500);
			} else {
				VortemLogger.warn('Security: No plugins or themes available for initial render');
				// Show empty state
				$('#emptyState').removeClass('hidden');
				$('#vortem-security-list').empty();
				$('#securityTableBody').empty();
			}
		}
		
		// Try immediate render
		if (allPlugins.length > 0 || allThemes.length > 0) {
			doInitialRender();
		} else {
			// If no plugins/themes yet, wait a very short time and try again
			setTimeout(doInitialRender, 50);
		}

		// Initialize overview tab on page load
		if ($('#tab-overview').hasClass('active')) {
			VortemLogger.log('Security: Overview tab is active on page load');
			if (typeof requestAnimationFrame !== 'undefined') {
				requestAnimationFrame(function() {
					updateOverviewTab();
				});
			} else {
				updateOverviewTab();
			}
		} else {
			VortemLogger.log('Security: Overview tab is NOT active on page load');
		}
	});

	/**
	 * Initialize plugins data
	 */
	function initializePlugins() {
		// Get plugins from the PHP template - try multiple times if needed
		var attempts = 0;
		var maxAttempts = 10;
		
		function tryInitialize() {
			if (typeof window.vortemSecurityPlugins !== 'undefined' && Array.isArray(window.vortemSecurityPlugins)) {
				allPlugins = window.vortemSecurityPlugins;
				VortemLogger.log('Security: Initialized plugins count:', allPlugins.length);
				return true;
			}
			return false;
		}
		
		// Try immediate initialization
		if (!tryInitialize()) {
			// If not available, wait a bit and try again (for script loading order issues)
			var checkInterval = setInterval(function() {
				attempts++;
				if (tryInitialize() || attempts >= maxAttempts) {
					clearInterval(checkInterval);
					if (allPlugins.length === 0 && attempts >= maxAttempts) {
						VortemLogger.warn('Security: No plugins found after', maxAttempts, 'attempts');
					}
				}
			}, 50);
		}
		
		// Mark all plugins with type
		allPlugins.forEach(function(plugin) {
			plugin.type = 'plugin';
		});
	}

	/**
	 * Initialize themes data
	 */
	function initializeThemes() {
		// Get themes from the PHP template
		var attempts = 0;
		var maxAttempts = 10;
		
		function tryInitialize() {
			if (typeof window.vortemSecurityThemes !== 'undefined' && Array.isArray(window.vortemSecurityThemes)) {
				allThemes = window.vortemSecurityThemes;
				VortemLogger.log('Security: Initialized themes count:', allThemes.length);
				return true;
			}
			return false;
		}
		
		// Try immediate initialization
		if (!tryInitialize()) {
			var checkInterval = setInterval(function() {
				attempts++;
				if (tryInitialize() || attempts >= maxAttempts) {
					clearInterval(checkInterval);
					if (allThemes.length === 0 && attempts >= maxAttempts) {
						VortemLogger.warn('Security: No themes found after', maxAttempts, 'attempts');
					}
				}
			}, 50);
		}
		
		// Mark all themes with type
		allThemes.forEach(function(theme) {
			theme.type = 'theme';
		});
	}

	/**
	 * Initialize WordPress core data
	 */
	function initializeWpCore() {
		// Get WordPress version from the page
		const wpVersion = (typeof window.vortemWpVersion !== 'undefined' && window.vortemWpVersion) ? window.vortemWpVersion : '';
		
		if (wpVersion) {
			const strings = window.vortemSecurityStrings || {};
			wpCoreData = {
				name: strings.wordpress_core || 'WordPress Core',
				version: wpVersion,
				status: 'active',
				author: 'WordPress',
				description: strings.wordpress_core ? (strings.wordpress_core + ' installation') : 'WordPress core installation',
				type: 'wp-core'
			};
			VortemLogger.log('Security: Initialized WordPress Core - Version:', wpVersion);
		} else {
			VortemLogger.warn('Security: WordPress version not available for core data');
		}
	}

	/**
	 * Get severity string from a vulnerability item (critical|high|medium|low)
	 */
	function getSeverityFromVuln(item) {
		let severity = 'low';
		if (item.severity) {
			const t = String(item.severity).toLowerCase().trim();
			if (t === 'critical' || t === 'high' || t === 'medium' || t === 'low') severity = t;
		}
		// Flat wp-core/match (and similar): numeric cvss_score when severity missing or nonstandard.
		if (severity === 'low' && item.cvss_score != null && item.cvss_score !== '') {
			const score = parseFloat(item.cvss_score);
			if (!isNaN(score)) {
				if (score >= 9.0) severity = 'critical';
				else if (score >= 7.0) severity = 'high';
				else if (score >= 4.0) severity = 'medium';
			}
		}
		if (severity === 'low' && item.classification && Array.isArray(item.classification)) {
			const cvssItem = item.classification.find(function(c) { return c && c.key === 'CVSS'; });
			if (cvssItem && cvssItem.value) {
				const v = String(cvssItem.value).trim();
				const m = v.match(/\(([^)]+)\)/i);
				if (m) {
					const t = m[1].toLowerCase().trim();
					if (t === 'critical' || t === 'high' || t === 'medium' || t === 'low') severity = t;
				} else {
					const num = v.match(/(\d+\.?\d*)/);
					if (num) {
						const score = parseFloat(num[1]);
						if (score >= 9.0) severity = 'critical';
						else if (score >= 7.0) severity = 'high';
						else if (score >= 4.0) severity = 'medium';
					}
				}
			}
		}
		return severity;
	}

	/**
	 * Update Core tab card from securityResultsData (wp-core match endpoint).
	 * No data / no vulns: show "No issues". Has vulns: show "X Found" and severity stat items.
	 */
	function updateCoreTabCard() {
		const $badge = $('#vortem-wp-core-badge');
		const $stats = $('#vortem-wp-core-stats');
		if (!$badge.length) return;

		const strings = window.vortemSecurityStrings || {};
		const noIssuesText = strings.your_wp_core_is_secure || 'No issues';

		if (!securityDataLoaded) {
			const rawChecking = strings.checking || 'Checking...';
			// Remove trailing dots/ellipsis so CSS can animate dots (Checking., Checking.., Checking...)
			const checkingBase = String(rawChecking).replace(/[.\u2026]+\s*$/g, '').trim() || 'Checking';
			$badge.addClass('is-loading').removeClass('is-clickable').removeAttr('aria-label role tabindex');
			$badge.text(checkingBase);
			$stats.hide();
			return;
		}

		$badge.removeClass('is-loading');
		const wpCoreVulns = securityResultsData.filter(function(v) { return (v.type || '') === 'wp-core'; });
		const total = wpCoreVulns.length;

		if (total === 0) {
			$badge.removeClass('is-clickable').removeAttr('aria-label role tabindex');
			$badge.text(noIssuesText);
			$stats.hide();
			return;
		}

		const coreLabel = strings.wordpress_core || 'WordPress Core';
		const foundWord = strings.found || 'Found';
		$badge
			.addClass('is-clickable')
			.attr('role', 'button')
			.attr('tabindex', '0')
			.attr('aria-label', coreLabel + ', ' + total + ' ' + foundWord);
		$badge.text(total + ' Found');
		const counts = { critical: 0, high: 0, medium: 0, low: 0 };
		wpCoreVulns.forEach(function(item) {
			const s = getSeverityFromVuln(item);
			if (counts.hasOwnProperty(s)) counts[s]++;
		});
		$stats.find('.vortem-wp-core-stat-item[data-severity="critical"] .vortem-wp-core-stat-value').text(counts.critical);
		$stats.find('.vortem-wp-core-stat-item[data-severity="medium"] .vortem-wp-core-stat-value').text(counts.medium);
		$stats.show();
	}

	/**
	 * Switch between grid and list view (workspace-style: same cards, layout changes)
	 * Grid = multi-column; List = single column stacked (like security workspace space-y-4)
	 */
	function switchView(view) {
		// Update button states
		$('.view-btn, .vortem-view-toggle-btn').removeClass('active');
		$('.view-btn[data-view="' + view + '"], .vortem-view-toggle-btn[data-view="' + view + '"]').addClass('active');

		// Single container: toggle list layout class on the grid (never hide the grid)
		const gridSelector = currentType === 'themes' ? '#vortem-security-themes-list' : '#vortem-security-list';
		const $grid = $(gridSelector);
		if (view === 'list' || view === 'table') {
			$grid.addClass('vortem-security-grid--list');
		} else {
			$grid.removeClass('vortem-security-grid--list');
		}

		currentPage = 1;
		// Always render cards; list view only changes layout via CSS
		renderCards();
		renderPagination();
	}

	/**
	 * Custom Filter/Sort dropdowns (workspace-style): sync trigger text, open/close, sync with native select.
	 */
	function initVortemFilterSelects() {
		var $wraps = $('.vortem-filter-select-wrap');
		if (!$wraps.length) return;

		function syncTriggerText($wrap) {
			var $select = $wrap.find('.vortem-filter-select-native');
			var val = $select.val();
			var $opt = $select.find('option:selected');
			var text = $opt.length ? $opt.text() : val;
			$wrap.find('.vortem-filter-select-value').text(text);
			$wrap.find('.vortem-filter-select-option').attr('aria-selected', false);
			$wrap.find('.vortem-filter-select-option[data-value="' + val + '"]').attr('aria-selected', true);
		}

		function closeAll() {
			$wraps.each(function() {
				$(this).attr('data-open', 'false');
				$(this).find('.vortem-filter-select-dropdown').attr('hidden', true);
				$(this).find('.vortem-filter-select-trigger').attr('aria-expanded', false);
			});
		}

		$wraps.each(function() {
			var $wrap = $(this);
			var $select = $wrap.find('.vortem-filter-select-native');
			var $trigger = $wrap.find('.vortem-filter-select-trigger');
			var $dropdown = $wrap.find('.vortem-filter-select-dropdown');
			var $options = $wrap.find('.vortem-filter-select-option');

			syncTriggerText($wrap);

			$trigger.on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var isOpen = $wrap.attr('data-open') === 'true';
				closeAll();
				if (!isOpen) {
					$wrap.attr('data-open', 'true');
					$dropdown.attr('hidden', false);
					$trigger.attr('aria-expanded', true);
				}
			});

			$options.on('click', function(e) {
				e.preventDefault();
				var val = $(this).data('value');
				$select.val(val);
				syncTriggerText($wrap);
				closeAll();
				$select.trigger('change');
			});
		});

		$(document).on('click', function(e) {
			if (!$(e.target).closest('.vortem-filter-select-wrap').length) {
				closeAll();
			}
		});
	}

	/**
	 * Filter and render plugins and themes
	 */
	function filterAndRender() {
		const searchTerm = $('#vortem-security-search').val().toLowerCase();
		const statusFilter = $('#vortem-security-status-filter').val();
		const sortBy = $('#vortem-security-sort').val();

		// Get items based on current type tab
		let allItems = [];
		if (currentType === 'themes') {
			allItems = allThemes;
		} else if (currentType === 'wp-core') {
			// For wp-core, create a single item array
			allItems = wpCoreData ? [wpCoreData] : [];
		} else {
			allItems = allPlugins;
		}

		// Filter items
		filteredItems = allItems.filter(function(item) {
			// Search filter
			const matchesSearch = !searchTerm || 
				item.name.toLowerCase().includes(searchTerm) ||
				(item.description && item.description.toLowerCase().includes(searchTerm)) ||
				(item.author && item.author.toLowerCase().includes(searchTerm));

			// Status filter
			const matchesStatus = statusFilter === 'all' || item.status === statusFilter;

			return matchesSearch && matchesStatus;
		});

		// Sort items
		sortItems(sortBy);

		// Update statistics
		updateStatistics();

		// Reset pagination when filters change
		currentPage = 1;

		// Sync grid container list/grid class with current view
		const $activeViewBtn = $('.view-btn.active, .vortem-view-toggle-btn.active');
		const currentView = $activeViewBtn.length > 0 ? $activeViewBtn.data('view') : 'grid';
		const gridSelector = currentType === 'themes' ? '#vortem-security-themes-list' : '#vortem-security-list';
		const $grid = $(gridSelector);
		if (currentView === 'list' || currentView === 'table') {
			$grid.addClass('vortem-security-grid--list');
		} else {
			$grid.removeClass('vortem-security-grid--list');
		}

		// Always render cards (list view = same cards, different layout via CSS)
		renderCards();

		// Render pagination
		renderPagination();

		// Show/hide empty state for current tab only
		if (filteredItems.length === 0) {
			$('#vortem-security-list').empty();
			$('#vortem-security-themes-list').empty();
			if (currentType === 'themes') {
				$('#emptyState').addClass('hidden');
				$('#emptyStateThemes').removeClass('hidden').show();
			} else {
				$('#emptyState').removeClass('hidden').show();
				$('#emptyStateThemes').addClass('hidden');
			}
		} else {
			$('#emptyState').addClass('hidden');
			$('#emptyStateThemes').addClass('hidden');
		}
	}

	/**
	 * Sort items (plugins and themes)
	 */
	function sortItems(sortBy) {
		filteredItems.sort(function(a, b) {
			const aName = (a && a.name) ? String(a.name) : '';
			const bName = (b && b.name) ? String(b.name) : '';
			const aAuthor = (a && a.author) ? String(a.author) : '';
			const bAuthor = (b && b.author) ? String(b.author) : '';
			const aStatus = (a && a.status) ? String(a.status) : '';
			const bStatus = (b && b.status) ? String(b.status) : '';

			switch (sortBy) {
				case 'name':
					return aName.localeCompare(bName);
				case 'author':
					return aAuthor.localeCompare(bAuthor);
				case 'date': {
					// Prefer WP's last_modified; fall back to lastModified if present.
					const aDate = Date.parse((a && (a.last_modified || a.lastModified)) ? String(a.last_modified || a.lastModified) : '');
					const bDate = Date.parse((b && (b.last_modified || b.lastModified)) ? String(b.last_modified || b.lastModified) : '');
					// Newest first
					return (isNaN(bDate) ? 0 : bDate) - (isNaN(aDate) ? 0 : aDate);
				}
				case 'version':
					return compareVersions(a && a.version ? String(a.version) : '', b && b.version ? String(b.version) : '');
				case 'status':
					return aStatus.localeCompare(bStatus);
				default:
					return 0;
			}
		});
	}

	/**
	 * Compare version strings
	 */
	function compareVersions(a, b) {
		const aStr = String(a || '');
		const bStr = String(b || '');
		const aParts = aStr.split('.').map(function(part) {
			const n = parseInt(part, 10);
			return isNaN(n) ? 0 : n;
		});
		const bParts = bStr.split('.').map(function(part) {
			const n = parseInt(part, 10);
			return isNaN(n) ? 0 : n;
		});
		const maxLength = Math.max(aParts.length, bParts.length);

		for (let i = 0; i < maxLength; i++) {
			const aPart = aParts[i] || 0;
			const bPart = bParts[i] || 0;

			if (aPart < bPart) return -1;
			if (aPart > bPart) return 1;
		}

		return 0;
	}

	/**
	 * Update statistics
	 */
	function updateStatistics() {
		// Plugin statistics
		const totalPlugins = allPlugins.length;
		const activePlugins = allPlugins.filter(p => p.status === 'active').length;
		const inactivePlugins = totalPlugins - activePlugins;

		$('#totalPlugins').text(totalPlugins);
		$('#activePlugins').text(activePlugins);
		$('#inactivePlugins').text(inactivePlugins);

		// Theme statistics
		const totalThemes = allThemes.length;
		const activeThemes = allThemes.filter(t => t.status === 'active').length;
		const inactiveThemes = totalThemes - activeThemes;

		$('#totalThemes').text(totalThemes);
		$('#activeThemes').text(activeThemes);
		$('#inactiveThemes').text(inactiveThemes);

		// Update tab counts
		$('#pluginsTabCount').text(totalPlugins);
		$('#themesTabCount').text(totalThemes);
		$('#wpCoreTabCount').text(wpCoreData ? '1' : '0');
	}

	/**
	 * Render cards view - Updated for new design
	 */
	function renderCards() {
		// Determine which grid to use based on current tab
		const gridSelector = currentType === 'themes' ? '#vortem-security-themes-list' : '#vortem-security-list';
		const $grid = $(gridSelector);
		if ($grid.length === 0) {
			VortemLogger.error('Security: Card grid container not found');
			return;
		}
		$grid.empty();

		if (filteredItems.length === 0) {
			VortemLogger.log('Security: No items to render in card view');
			const emptySelector = currentType === 'themes' ? '#emptyStateThemes' : '#emptyState';
			$(emptySelector).show();
			return;
		}

		// Hide empty state
		$('#emptyState, #emptyStateThemes').hide();

		// Items per page: list view uses table count (workspace-style)
		const currentView = $('.view-btn.active, .vortem-view-toggle-btn.active').data('view') || 'grid';
		const perPage = (currentView === 'list' || currentView === 'table') ? itemsPerPageTable : itemsPerPageCard;

		// Calculate pagination
		const startIndex = (currentPage - 1) * perPage;
		const endIndex = startIndex + perPage;
		const paginatedItems = filteredItems.slice(startIndex, endIndex);

		paginatedItems.forEach(function(item) {
			const isTheme = item.type === 'theme';
			const isCore = item.type === 'wp-core';
			let vulnCount = 0;
			const cardClass = isTheme ? 'vortem-theme-card' : 'vortem-plugin-card';
			const $card = $('<div>').addClass(cardClass);
			
			// Header
			const headerClass = isTheme ? 'vortem-theme-card-header' : 'vortem-plugin-card-header';
			const $header = $('<div>').addClass(headerClass);
			
			const titleClass = isTheme ? 'vortem-theme-card-title' : 'vortem-plugin-card-title';
			const $title = $('<h3>').addClass(titleClass).text(item.name);
			
			const badgesClass = isTheme ? 'vortem-theme-card-badges' : 'vortem-plugin-card-badges';
			const $badges = $('<div>').addClass(badgesClass);
			
			// Status badge
			const statusText = item.status === 'active' 
				? (window.vortemSecurityStrings && window.vortemSecurityStrings.active ? window.vortemSecurityStrings.active : 'Active')
				: (window.vortemSecurityStrings && window.vortemSecurityStrings.inactive ? window.vortemSecurityStrings.inactive : 'Inactive');
			const $statusBadge = $('<span>')
				.addClass('vortem-status-badge')
				.addClass(item.status)
				.html('<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> ' + statusText);
			$badges.append($statusBadge);
			
			// Vulnerability count badge
			if (!securityDataLoaded) {
				// Show checking state (same as Core tab: base text + animated dots via CSS ::after)
				const strings = window.vortemSecurityStrings || {};
				const rawChecking = strings.checking || 'Checking...';
				const checkingBase = String(rawChecking).replace(/[.\u2026]+\s*$/g, '').trim() || 'Checking';
				const $checkingBadge = $('<span>')
					.addClass('vortem-status-badge is-loading')
					.text(checkingBase);
				$badges.append($checkingBadge);
			} else {
				// Count vulnerabilities
				const itemVulns = securityResultsData.filter(function(vuln) {
					if (isTheme) {
						const vulnThemeName = (vuln.customer_theme || vuln.customer_theme_name || '').toLowerCase();
						return vuln.type === 'theme' && vulnThemeName === (item.name || '').toLowerCase();
					} else if (isCore) {
						return vuln.type === 'wp-core';
					} else {
						const vulnPluginName = vuln.customer_plugin_name || vuln.customer_plugin || '';
						return vuln.type === 'plugin' && vulnPluginName.toLowerCase() === (item.name || '').toLowerCase();
					}
				});
				vulnCount = itemVulns.length;
				
				if (vulnCount > 0) {
					const strings = window.vortemSecurityStrings || {};
					const vulnerabilitiesText = strings.vulnerabilities || 'Vulnerabilities';
					const badgeItemType = isTheme ? 'theme' : (isCore ? 'wp-core' : 'plugin');
					const $vulnBadge = $('<button>')
						.addClass('vortem-vulnerabilities-badge')
						.attr('data-plugin-name', item.name)
						.attr('data-item-type', badgeItemType)
						.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> ' + vulnCount + ' ' + vulnerabilitiesText);
					$badges.append($vulnBadge);
				}
			}
			
			$header.append($title);
			$header.append($badges);
			$card.append($header);

			// Description
			if (item.description) {
				const descClass = isTheme ? 'vortem-theme-card-description' : 'vortem-plugin-card-description';
				const $description = $('<p>').addClass(descClass).text(item.description);
				$card.append($description);
			}
			
			// Info Grid
			const infoGridClass = isTheme ? 'vortem-theme-card-info-grid' : 'vortem-plugin-card-info-grid';
			const $infoGrid = $('<div>').addClass(infoGridClass);
			
			const strings = window.vortemSecurityStrings || {};
			
			// Version
			if (item.version) {
				const $versionItem = $('<div>').addClass('vortem-info-item');
				$versionItem.append($('<p>').addClass('vortem-info-label').text(strings.version_label || 'VERSION'));
				$versionItem.append($('<p>').addClass('vortem-info-value').text(item.version));
				$infoGrid.append($versionItem);
			}
			
			// Author
			if (item.author) {
				const $authorItem = $('<div>').addClass('vortem-info-item blue');
				$authorItem.append($('<p>').addClass('vortem-info-label').text(strings.author_label || 'AUTHOR'));
				$authorItem.append($('<p>').addClass('vortem-info-value').text(item.author));
				$infoGrid.append($authorItem);
			}
			
			// Requires WP
			if (item.requires_wp_version) {
				const $wpItem = $('<div>').addClass('vortem-info-item green');
				$wpItem.append($('<p>').addClass('vortem-info-label').text(strings.requires_wp_label || 'REQUIRES WP'));
				$wpItem.append($('<p>').addClass('vortem-info-value').text(item.requires_wp_version));
				$infoGrid.append($wpItem);
			}
			
			// Requires PHP
			if (item.requires_php) {
				const $phpItem = $('<div>').addClass('vortem-info-item yellow');
				$phpItem.append($('<p>').addClass('vortem-info-label').text(strings.requires_php_label || 'REQUIRES PHP'));
				$phpItem.append($('<p>').addClass('vortem-info-value').text(item.requires_php));
				$infoGrid.append($phpItem);
			}
			
			$card.append($infoGrid);
			// Footer
			const footerClass = isTheme ? 'vortem-theme-card-footer' : 'vortem-plugin-card-footer';
			const $footer = $('<div>').addClass(footerClass);
			
			// Last updated
			if (item.last_modified) {
				const formattedDate = formatDate(item.last_modified);
				const lastUpdatedLabel = strings.last_updated_label || 'Last updated:';
				const $lastUpdated = $('<p>').addClass('vortem-card-last-updated')
					.html(lastUpdatedLabel + ' <span>' + escapeHtml(formattedDate) + '</span>');
				$footer.append($lastUpdated);
			}
			
			// View page link only when item has vulnerabilities (opens CVE modal)
			const uri = isTheme ? item.theme_uri : item.plugin_uri;
			if (uri && securityDataLoaded && vulnCount > 0) {
				const viewPageText = strings.view_page || 'View Page';
				const $viewLink = $('<a>')
					.addClass('vortem-card-view-page')
					.attr('href', uri)
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.attr('data-plugin-name', isTheme ? '' : item.name)
					.attr('data-theme-name', isTheme ? item.name : '')
					.html(viewPageText + ' <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
				$footer.append($viewLink);
			}
			
			$card.append($footer);
			$grid.append($card);
		});
	}
	
	/**
	 * Open vulnerabilities dialog for all vulnerabilities (from overview cards)
	 * @param {string} [severityFilter] - Optional severity filter: 'critical', 'high', 'medium', 'low'
	 */
	function openAllVulnerabilitiesDialog(severityFilter) {
		const strings = window.vortemSecurityStrings || {};
		let allVulns = securityResultsData || [];
		
		// Filter by severity if specified
		if (severityFilter) {
			allVulns = allVulns.filter(function(vuln) {
				const severity = getSeverityFromVuln(vuln);
				return severity === severityFilter.toLowerCase();
			});
		}
		
		// Set modal title
		const vulnerabilitiesText = strings.vulnerabilities || 'Vulnerabilities';
		if (severityFilter) {
			const severityText = severityFilter.charAt(0).toUpperCase() + severityFilter.slice(1);
			$('#vuln-modal-title').text(severityText + ' ' + vulnerabilitiesText);
		} else {
			$('#vuln-modal-title').text('All ' + vulnerabilitiesText);
		}
		
		const totalVulnsText = strings.total_vulnerabilities || 'Total Vulnerabilities';
		$('#vuln-modal-count').text(allVulns.length + ' ' + totalVulnsText);
		$('#vuln-filter-total').text(allVulns.length);
		
		// Reset severity filter dropdown to 'all' or the selected severity
		if (severityFilter) {
			$('#vuln-severity-filter').val(severityFilter.toLowerCase());
		} else {
			$('#vuln-severity-filter').val('all');
		}
		
		// Store current vulnerabilities for filtering
		window.currentVulnerabilities = allVulns;
		
		// Render vulnerabilities
		filterVulnerabilities();
		
		// Show modal
		$('#vortem-vulnerabilities-modal').show();
	}
	
	/**
	 * Open vulnerabilities dialog
	 * @param {string} itemName - Plugin or theme name
	 * @param {string} [itemType] - 'plugin' or 'theme'; defaults to 'plugin'
	 */
	function openVulnerabilitiesDialog(itemName, itemType) {
		const type = itemType || 'plugin';
		const nameLower = (itemName || '').toLowerCase();
		const itemVulns = securityResultsData.filter(function(vuln) {
			if (vuln.type !== type) return false;
			if (type === 'wp-core') {
				return true;
			}
			if (type === 'theme') {
				return (vuln.customer_theme || vuln.customer_theme_name || '').toLowerCase() === nameLower;
			}
			const vulnPluginName = vuln.customer_plugin_name || vuln.customer_plugin || '';
			return vulnPluginName.toLowerCase() === nameLower;
		});
		
		const strings = window.vortemSecurityStrings || {};
		const modalTitle = type === 'wp-core' ? (strings.wordpress_core || 'WordPress Core') : itemName;
		$('#vuln-modal-title').text(modalTitle);
		const totalVulnsText = strings.total_vulnerabilities || 'Total Vulnerabilities';
		$('#vuln-modal-count').text(itemVulns.length + ' ' + totalVulnsText);
		$('#vuln-filter-total').text(itemVulns.length);
		
		// Reset severity filter dropdown
		$('#vuln-severity-filter').val('all');
		
		// Store current vulnerabilities for filtering
		window.currentVulnerabilities = itemVulns;
		
		// Render vulnerabilities
		filterVulnerabilities();
		
		// Show modal
		$('#vortem-vulnerabilities-modal').show();
	}
	
	/**
	 * Filter vulnerabilities by severity
	 */
	function filterVulnerabilities() {
		const strings = window.vortemSecurityStrings || {};
		const severityFilter = $('#vuln-severity-filter').val();
		const allVulns = window.currentVulnerabilities || [];
		
		const filtered = severityFilter === 'all' 
			? allVulns 
			: allVulns.filter(function(vuln) {
				return (vuln.severity || '').toUpperCase() === severityFilter.toUpperCase();
			});
		
		$('#vuln-filter-count-value').text(filtered.length);
		
		// Render vulnerabilities
		const $list = $('#vuln-modal-list');
		$list.empty();
		
		if (filtered.length === 0) {
			$list.html('<div class="vortem-empty-state"><div class="vortem-empty-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><p style="color: #6B7280; font-weight: 500;">No vulnerabilities found with the selected filter.</p></div>');
			return;
		}
		
		filtered.forEach(function(vuln) {
			const severity = (vuln.severity || 'LOW').toLowerCase();
			const $item = $('<div>').addClass('vortem-vuln-item');
			
			// Header
			const $header = $('<div>').addClass('vortem-vuln-item-header');
			const $titleWrapper = $('<div>');
			// Use new `cve` field, fall back to legacy `cve_id`/`title`
			const vulnTitle = vuln.cve || vuln.cve_id || vuln.title || 'N/A';
			$titleWrapper.append($('<h4>').addClass('vortem-vuln-item-title').text(vulnTitle));
			const cvssLabel = strings.cvss_score_label || 'CVSS Score:';
			// Prefer `cvss_score` from new spec, fall back to legacy `cvss`
			const cvssValue = vuln.cvss_score !== undefined ? vuln.cvss_score : (vuln.cvss !== undefined ? vuln.cvss : 'N/A');
			$titleWrapper.append($('<div>').addClass('vortem-vuln-item-cvss')
				.html('<span class="vortem-vuln-item-cvss-label">' + cvssLabel + '</span> <span class="vortem-vuln-item-cvss-value">' + cvssValue + '</span>'));
			$header.append($titleWrapper);
			$header.append($('<span>').addClass('vortem-vuln-item-severity').addClass(severity).text(severity.toUpperCase()));
			$item.append($header);
			
			// Description
			if (vuln.description) {
				$item.append($('<p>').addClass('vortem-vuln-item-description').text(vuln.description));
			}
			
		// Details
		const $details = $('<div>').addClass('vortem-vuln-item-details');
		const cweLabel = strings.cwe_label || 'CWE:';
		const publishedLabel = strings.published_label || 'Published:';
		const lastModifiedLabel = strings.last_modified_label || 'Last Modified:';
		const referencesLabel = strings.references_label || 'References:';
		const affectedVersionLabel = strings.affected_version_label || 'Affected Version:';
		const fixedVersionLabel = strings.fixed_version_label || 'Fixed Version:';

		// Affected plugin/theme version (new field from the v1 plugin spec)
		const affectedVersion = vuln.customer_plugin_version || vuln.customer_theme_version || vuln.customer_wordpress_version || '';
		if (affectedVersion) {
			$details.append($('<div>').addClass('vortem-vuln-detail-item')
				.html('<span class="vortem-vuln-detail-label">' + affectedVersionLabel + '</span> <span class="vortem-vuln-detail-value vortem-vuln-detail-value-primary">' + escapeHtml(affectedVersion) + '</span>'));
		}

		// Fixed version (new spec uses `fixed_version`, legacy used `fixed_in`)
		const fixedVersion = vuln.fixed_version || vuln.fixed_in || '';
		if (fixedVersion) {
			$details.append($('<div>').addClass('vortem-vuln-detail-item')
				.html('<span class="vortem-vuln-detail-label">' + fixedVersionLabel + '</span> <span class="vortem-vuln-detail-value">' + escapeHtml(fixedVersion) + '</span>'));
		}

		if (vuln.cwe) {
			$details.append($('<div>').addClass('vortem-vuln-detail-item')
				.html('<span class="vortem-vuln-detail-label">' + cweLabel + '</span> <span class="vortem-vuln-detail-value vortem-vuln-detail-value-primary">' + escapeHtml(vuln.cwe) + '</span>'));
		}
		// New spec uses `published_date`; keep legacy `published` fallback
		const publishedDate = vuln.published_date || vuln.published;
		if (publishedDate) {
			$details.append($('<div>').addClass('vortem-vuln-detail-item')
				.html('<span class="vortem-vuln-detail-label">' + publishedLabel + '</span> <span class="vortem-vuln-detail-value">' + escapeHtml(publishedDate) + '</span>'));
		}
		// New spec uses `last_modified`; keep legacy `last_modified_date`/`lastModified` fallbacks
		const lastModifiedDate = vuln.last_modified || vuln.last_modified_date || vuln.lastModified;
		if (lastModifiedDate) {
			$details.append($('<div>').addClass('vortem-vuln-detail-item')
				.html('<span class="vortem-vuln-detail-label">' + lastModifiedLabel + '</span> <span class="vortem-vuln-detail-value">' + escapeHtml(lastModifiedDate) + '</span>'));
		}
		$item.append($details);
		
		// References - support both array of objects {url, label} and array of strings
		if (vuln.references && vuln.references.length > 0) {
			const $refs = $('<div>').addClass('vortem-vuln-item-references');
			$refs.append($('<span>').addClass('vortem-vuln-references-label').text(referencesLabel));
			const $refList = $('<div>').addClass('vortem-vuln-references-list');
			vuln.references.forEach(function(ref) {
				// Handle both object format {url, label} and string format (direct URL)
				let refUrl, refLabel;
				if (typeof ref === 'string') {
					refUrl = ref;
					refLabel = ref;
				} else {
					refUrl = ref.url || ref.link || '#';
					refLabel = ref.label || ref.value || refUrl;
				}
				$refList.append($('<a>').addClass('vortem-vuln-reference-link')
					.attr('href', refUrl)
					.attr('target', '_blank')
					.attr('rel', 'noopener noreferrer')
					.text(refLabel));
			});
			$refs.append($refList);
			$item.append($refs);
		}
			
			$list.append($item);
		});
	}

	/**
	 * Render table view
	 */
	function renderTable() {
		const $tbody = $('#securityTableBody');
		if ($tbody.length === 0) {
			VortemLogger.error('Security: Table body container not found');
			return;
		}
		$tbody.empty();

		if (filteredItems.length === 0) {
			VortemLogger.log('Security: No items to render in table view');
			return;
		}

		// Calculate pagination
		const startIndex = (currentPage - 1) * itemsPerPageTable;
		const endIndex = startIndex + itemsPerPageTable;
		const paginatedItems = filteredItems.slice(startIndex, endIndex);

		paginatedItems.forEach(function(item) {
			const $row = $('<tr>');
			const isTheme = item.type === 'theme';
			const isCore = item.type === 'wp-core';
			
			$row.append($('<td>').addClass('table-plugin-name').text(item.name));
			$row.append($('<td>').addClass('table-version').text(item.version || '—'));
			const strings = window.vortemSecurityStrings || {};
			const statusText = item.status === 'active' 
				? (strings.active || 'Active')
				: (strings.inactive || 'Inactive');
			$row.append($('<td>').html(
				$('<span>')
					.addClass('security-status')
					.addClass('security-status-' + item.status)
					.text(statusText)
			));
			$row.append($('<td>').addClass('table-author').text(isCore ? 'WordPress' : (item.author || '—')));
			
			const $actions = $('<td>').addClass('table-actions');
			const $viewBtn = $('<button>')
				.addClass('table-action-btn')
				.attr('title', 'View Details')
				.attr('type', 'button')
				.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>')
				.on('click', function() {
					if (isCore) {
						openWpCoreModal(item);
					} else if (isTheme) {
						openThemeModal(item);
					} else {
						openPluginModal(item);
					}
				});
			$actions.append($viewBtn);
			$row.append($actions);

			$tbody.append($row);
		});
	}

	/**
	 * Update table sort indicator
	 */
	function updateTableSortIndicator($th, sortBy) {
		$('.security-table th.sortable').removeClass('sort-asc sort-desc');
		// For simplicity, we'll just add a class - you can enhance this to track asc/desc
		$th.addClass('sort-asc');
	}

	/**
	 * Render pagination controls
	 */
	function renderPagination() {
		// Determine which pagination container to use
		const paginationSelector = currentType === 'themes' ? '#vortem-security-themes-pagination' : '#vortem-security-pagination';
		const $pagination = $(paginationSelector);
		$pagination.empty();

		if (filteredItems.length === 0) {
			$pagination.hide();
			// Hide other pagination too
			$('#vortem-security-pagination, #vortem-security-themes-pagination').hide();
			return;
		}

		const currentView = $('.view-btn.active, .vortem-view-toggle-btn.active').data('view') || 'grid';
		const itemsPerPage = (currentView === 'list' || currentView === 'table') ? itemsPerPageTable : itemsPerPageCard;
		const totalPages = Math.ceil(filteredItems.length / itemsPerPage);

		if (totalPages <= 1) {
			$pagination.hide();
			return;
		}

		$pagination.show();

		// Get translation strings
		const strings = window.vortemSecurityStrings || {};

		const $paginationContainer = $('<div>').addClass('pagination-container');
		
		// Previous button
		const $prevBtn = $('<button>')
			.addClass('pagination-btn')
			.addClass('pagination-prev')
			.text(strings.previous || 'Previous')
			.attr('type', 'button')
			.prop('disabled', currentPage === 1);
		
		if (currentPage > 1) {
			$prevBtn.on('click', function() {
				currentPage--;
				renderCards();
				renderPagination();
				var $tab = $('.vortem-security-tab-section');
				if ($tab.length) {
					$('html, body').animate({ scrollTop: $tab.offset().top - 80 }, 300);
				}
			});
		}

		$paginationContainer.append($prevBtn);

		// Page numbers
		const $pageNumbers = $('<div>').addClass('pagination-numbers');
		
		// Calculate which page numbers to show
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
				renderCards();
				renderPagination();
				var $tab = $('.vortem-security-tab-section');
				if ($tab.length) {
					$('html, body').animate({ scrollTop: $tab.offset().top - 80 }, 300);
				}
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
					renderCards();
					renderPagination();
					var $tab = $('.vortem-security-tab-section');
					if ($tab.length) {
						$('html, body').animate({ scrollTop: $tab.offset().top - 80 }, 300);
					}
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
				renderCards();
				renderPagination();
				var $tab = $('.vortem-security-tab-section');
				if ($tab.length) {
					$('html, body').animate({ scrollTop: $tab.offset().top - 80 }, 300);
				}
			});
			$pageNumbers.append($lastPage);
		}

		$paginationContainer.append($pageNumbers);

		// Next button
		const $nextBtn = $('<button>')
			.addClass('pagination-btn')
			.addClass('pagination-next')
			.text(strings.next || 'Next')
			.attr('type', 'button')
			.prop('disabled', currentPage === totalPages);
		
		if (currentPage < totalPages) {
			$nextBtn.on('click', function() {
				currentPage++;
				renderCards();
				renderPagination();
				var $tab = $('.vortem-security-tab-section');
				if ($tab.length) {
					$('html, body').animate({ scrollTop: $tab.offset().top - 80 }, 300);
				}
			});
		}

		$paginationContainer.append($nextBtn);

		// Page info
		const startIndex = (currentPage - 1) * itemsPerPage + 1;
		const endIndex = Math.min(currentPage * itemsPerPage, filteredItems.length);
		const showingText = strings.showing || 'Showing';
		const ofText = strings.of || 'of';
		const $pageInfo = $('<div>').addClass('pagination-info')
			.text(`${showingText} ${startIndex}-${endIndex} ${ofText} ${filteredItems.length}`);

		/* Left: page info | Right: pagination buttons */
		$pagination.append($pageInfo);
		$pagination.append($paginationContainer);
	}

	/**
	 * Open plugin details modal
	 */
	function openPluginModal(plugin) {
		const $modal = $('#vortem-plugin-modal');
		const $modalContent = $modal.find('.modal-content');
		
		// Get translation strings
		const strings = window.vortemSecurityStrings || {};
		const activeText = strings.active || 'Active';
		const inactiveText = strings.inactive || 'Inactive';
		
		// Populate modal with plugin data
		$modalContent.find('.modal-plugin-name').text(plugin.name || '—');
		$modalContent.find('.modal-plugin-version').text(plugin.version || '—');
		$modalContent.find('.modal-plugin-status').text(plugin.status === 'active' ? activeText : inactiveText)
			.removeClass('security-status-active security-status-inactive')
			.addClass('security-status-' + plugin.status);
		$modalContent.find('.modal-plugin-author').text(plugin.author || '—');
		$modalContent.find('.modal-plugin-description').text(plugin.description || '—');
		$modalContent.find('.modal-plugin-file').text(plugin.file || '—');
		
		// Plugin URI
		const $pluginUriContainer = $modalContent.find('.modal-plugin-uri');
		$pluginUriContainer.empty();
		if (plugin.plugin_uri) {
			const $link = $('<a>')
				.attr('href', plugin.plugin_uri)
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(plugin.plugin_uri);
			$pluginUriContainer.append($link);
		} else {
			$pluginUriContainer.text('—');
		}
		
		// Last Modified
		if (plugin.last_modified) {
			const formattedDate = formatDate(plugin.last_modified);
			$modalContent.find('.modal-plugin-last-modified').text(formattedDate);
		} else {
			$modalContent.find('.modal-plugin-last-modified').text('—');
		}
		
		// Requires WP Version
		$modalContent.find('.modal-plugin-requires-wp').text(plugin.requires_wp_version || '—');
		
		// Requires PHP
		$modalContent.find('.modal-plugin-requires-php').text(plugin.requires_php || '—');
		
		// Show modal
		$modal.addClass('active');
		$('body').addClass('modal-open');
	}

	/**
	 * Close plugin details modal
	 */
	function closePluginModal() {
		const $modal = $('#vortem-plugin-modal');
		$modal.removeClass('active');
		$('body').removeClass('modal-open');
	}

	/**
	 * Open theme details modal
	 */
	function openThemeModal(theme) {
		const $modal = $('#vortem-plugin-modal');
		const $modalContent = $modal.find('.modal-content');
		
		// Get translation strings
		const strings = window.vortemSecurityStrings || {};
		const activeText = strings.active || 'Active';
		const inactiveText = strings.inactive || 'Inactive';
		
		// Populate modal with theme data
		$modalContent.find('.modal-plugin-name').text(theme.name || '—');
		$modalContent.find('.modal-plugin-version').text(theme.version || '—');
		$modalContent.find('.modal-plugin-status').text(theme.status === 'active' ? activeText : inactiveText)
			.removeClass('security-status-active security-status-inactive')
			.addClass('security-status-' + theme.status);
		$modalContent.find('.modal-plugin-author').text(theme.author || '—');
		$modalContent.find('.modal-plugin-description').text(theme.description || '—');
		$modalContent.find('.modal-plugin-file').text(theme.stylesheet || '—');
		
		// Theme URI
		const $pluginUriContainer = $modalContent.find('.modal-plugin-uri');
		$pluginUriContainer.empty();
		if (theme.theme_uri) {
			const $link = $('<a>')
				.attr('href', theme.theme_uri)
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(theme.theme_uri);
			$pluginUriContainer.append($link);
		} else {
			$pluginUriContainer.text('—');
		}
		
		// Last Modified - not available for themes
		$modalContent.find('.modal-plugin-last-modified').text('—');
		
		// Requires WP Version - not available for themes
		$modalContent.find('.modal-plugin-requires-wp').text('—');
		
		// Requires PHP - not available for themes
		$modalContent.find('.modal-plugin-requires-php').text('—');
		
		// Show modal
		$modal.addClass('active');
		$('body').addClass('modal-open');
	}

	/**
	 * Open WordPress core details modal
	 */
	function openWpCoreModal(core) {
		const $modal = $('#vortem-plugin-modal');
		const $modalContent = $modal.find('.modal-content');
		
		// Get translation strings
		const strings = window.vortemSecurityStrings || {};
		
		// Populate modal with WordPress core data
		const wpCoreName = strings.wordpress_core || 'WordPress Core';
		$modalContent.find('.modal-plugin-name').text(core.name || wpCoreName);
		$modalContent.find('.modal-plugin-version').text(core.version || '—');
		$modalContent.find('.modal-plugin-status').text(strings.active || 'Active')
			.removeClass('security-status-active security-status-inactive')
			.addClass('security-status-active');
		$modalContent.find('.modal-plugin-author').text('WordPress');
		$modalContent.find('.modal-plugin-description').text(core.description || (wpCoreName + ' installation'));
		$modalContent.find('.modal-plugin-file').text(wpCoreName);
		
		// Plugin URI - not applicable for core
		$modalContent.find('.modal-plugin-uri').text('—');
		
		// Last Modified - not applicable for core
		$modalContent.find('.modal-plugin-last-modified').text('—');
		
		// Requires WP Version - not applicable for core
		$modalContent.find('.modal-plugin-requires-wp').text('—');
		
		// Requires PHP - not applicable for core
		$modalContent.find('.modal-plugin-requires-php').text('—');
		
		// Show modal
		$modal.addClass('active');
		$('body').addClass('modal-open');
	}

	/**
	 * Show toast notification (creates element dynamically if needed)
	 */
	function showToast(message, type = 'success') {
		// Check if toast element exists, create if not
		let $toast = $('#vortem-security-toast');
		if ($toast.length === 0) {
			$toast = $('<div id="vortem-security-toast" class="vortem-security-toast"></div>');
			$('body').append($toast);
		}

		// Set message and type
		$toast.text(message);
		$toast.removeClass('success error').addClass(type);

		// Show toast with animation
		$toast.css({
			'position': 'fixed',
			'bottom': '24px',
			'right': '24px',
			'padding': '14px 20px',
			'border-radius': '10px',
			'background': type === 'error' ? 'rgba(239, 68, 68, 0.95)' : 'rgba(16, 185, 129, 0.95)',
			'color': '#ffffff',
			'box-shadow': '0 4px 12px rgba(0, 0, 0, 0.15)',
			'z-index': '100002',
			'max-width': '400px',
			'min-width': '300px',
			'font-size': '14px',
			'line-height': '1.5',
			'opacity': '0',
			'transform': 'translateY(20px)',
			'transition': 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
			'pointer-events': 'none'
		});

		// Trigger animation
		setTimeout(function() {
			$toast.css({
				'opacity': '1',
				'transform': 'translateY(0)',
				'pointer-events': 'auto'
			});
		}, 10);

		// Auto-hide after 3 seconds
		setTimeout(function() {
			$toast.css({
				'opacity': '0',
				'transform': 'translateY(20px)'
			});
			setTimeout(function() {
				$toast.remove();
			}, 300);
		}, 3000);
	}


	/**
	 * Send plugin data to external API
	 * Called automatically after page load
	 * Makes separate requests for plugins and themes directly to the external API
	 */
	function sendDataToAPI() {
		const config = window.vortemSecurityConfig || {};

		if (allPlugins.length === 0 && allThemes.length === 0) {
			VortemLogger.warn('Security: No plugins or themes to send');
			return;
		}

		// Send plugins data separately
		if (allPlugins.length > 0 && config.pluginApiUrl) {
			sendPluginsToAPI(config);
		}

		// Send themes data separately
		if (allThemes.length > 0 && config.themeApiUrl) {
			sendThemesToAPI(config);
		}

		// Send wp-core data separately
		if (config.wpCoreApiUrl) {
			sendWpCoreToAPI(config);
		}
	}

	/**
	 * Send plugins data directly to external API
	 */
	function sendPluginsToAPI(config) {
		// Format plugins data according to API requirements
		const formattedPlugins = allPlugins.map(function(plugin) {
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

		// Make direct fetch request to external API (visible in browser network tab)
		fetch(config.pluginApiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			body: JSON.stringify(requestData)
		})
		.then(function(response) {
			if (response.ok) {
				VortemLogger.log('Security: Plugin data sent successfully');
				return response.json();
			} else {
				throw new Error('HTTP ' + response.status + ': ' + response.statusText);
			}
		})
		.catch(function(error) {
			VortemLogger.warn('Security: Error sending plugin data:', error.message || error);
		});
	}

	/**
	 * Send wp-core data directly to external API
	 */
	function sendWpCoreToAPI(config) {
		// Get WordPress version from global (real value from PHP)
		const wpVersion = (typeof window.vortemWpVersion !== 'undefined' && window.vortemWpVersion) ? window.vortemWpVersion : '';
		if (!wpVersion) {
			VortemLogger.warn('Security: WordPress version not available. Make sure vortemWpVersion is set from PHP.');
			return;
		}

		// POST /wp-core: body matches API spec (only `wordpres-version`, same spelling as backend).
		const requestData = {
			'wordpres-version': wpVersion
		};

		VortemLogger.log('Security: Sending WP Core data - Version:', wpVersion);

		// Make direct fetch request to external API
		fetch(config.wpCoreApiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			body: JSON.stringify(requestData)
		})
		.then(function(response) {
			if (response.ok) {
				VortemLogger.log('Security: WP Core data sent successfully - Version:', wpVersion);
				return response.json();
			} else {
				throw new Error('HTTP ' + response.status + ': ' + response.statusText);
			}
		})
		.catch(function(error) {
			VortemLogger.warn('Security: Error sending wp-core data:', error.message || error);
		});
	}

	/**
	 * Send themes data directly to external API
	 */
	function sendThemesToAPI(config) {
		// POST /customer/security/wordpress/theme — themes[]: stylesheet, template, name, version, status only (see API spec).
		const formattedThemes = allThemes.map(function(theme) {
			const ss = theme.stylesheet != null && theme.stylesheet !== false ? String(theme.stylesheet).toLowerCase() : '';
			const tpl = theme.template != null && theme.template !== false ? String(theme.template).toLowerCase() : '';
			const displayName = theme.name != null && theme.name !== false ? String(theme.name) : '';
			return {
				stylesheet: ss,
				template: tpl,
				name: displayName,
				version: theme.version !== null && theme.version !== false ? String(theme.version) : '',
				status: (theme.status && (theme.status === 'active' || theme.status === 'inactive')) ? theme.status : 'inactive'
			};
		});

		const requestData = {
			themes: formattedThemes
		};

		// Make direct fetch request to external API (visible in browser network tab)
		// Note: JavaScript JSON.stringify doesn't escape forward slashes by default,
		// which matches PHP's JSON_UNESCAPED_SLASHES behavior
		fetch(config.themeApiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Referer': window.location.href
			},
			body: JSON.stringify(requestData)
		})
		.then(function(response) {
			if (response.ok) {
				VortemLogger.log('Security: Theme data sent successfully');
				return response.json();
			} else {
				throw new Error('HTTP ' + response.status + ': ' + response.statusText);
			}
		})
		.catch(function(error) {
			VortemLogger.warn('Security: Error sending theme data:', error.message || error);
		});
	}

	/**
	 * Update the "Last updated" label in the security header (workspace-style)
	 */
	function updateSecurityLastUpdated() {
		const el = document.getElementById('vortem-security-last-updated-value');
		if (!el) return;
		const d = new Date();
		const datePart = new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(d);
		const timePart = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(d);
		el.textContent = datePart + ' at ' + timePart;
	}

	/**
	 * Fetch security scan results from API via WordPress AJAX (to avoid CORS issues)
	 */
	function fetchSecurityResults() {
		VortemLogger.log('Security: fetchSecurityResults called');
		const ajaxData = window.vortemSecurityAjax || {};
		
		if (!ajaxData.ajax_url || !ajaxData.nonce) {
			VortemLogger.error('Security: AJAX configuration missing');
			showResultsError('AJAX configuration missing');
			return;
		}

		const $loading = $('#vortem-security-results-loading');
		VortemLogger.log('Security: Loading element found:', $loading.length);
		VortemLogger.log('Security: Loading element classes before:', $loading.attr('class'));
		
		// Show loading state - hide everything else with inline styles
		$loading.removeClass('hidden').css('display', 'flex');
		$('#vortem-security-results-error').addClass('hidden').css('display', 'none');
		$('#vortem-security-results-container').addClass('hidden').css('display', 'none');
		$('#vortem-security-results-tabs-wrapper').addClass('hidden').css('display', 'none');
		
		VortemLogger.log('Security: Loading element classes after:', $loading.attr('class'));
		VortemLogger.log('Security: Loading element is visible:', $loading.is(':visible'));

		// Make AJAX request through WordPress (server-side proxy to avoid CORS)
		$.ajax({
			url: ajaxData.ajax_url,
			type: 'POST',
			data: {
				action: 'vortem_get_security_results',
				nonce: ajaxData.nonce
			},
			dataType: 'json',
			timeout: 30000,
			success: function(response) {
				$('#vortem-security-results-loading').addClass('hidden');
				securityDataLoaded = true; // Mark as loaded
				if (response.success && Array.isArray(response.data) && response.data.length > 0) {
					securityResultsData = response.data; // Store globally
					displaySecurityResults(response.data);
					updateSecurityLastUpdated();
					updateCoreTabCard();
					// Re-render cards to update vulnerability counts
					if ($('#vortem-security-list').length > 0) {
						renderCards();
					}
					// Update overview tab if active
					if ($('#tab-overview').hasClass('active')) {
						updateOverviewTab();
					}
				} else if (response.success && Array.isArray(response.data)) {
					securityResultsData = []; // Store empty array
					displaySecurityResults([]);
					updateSecurityLastUpdated();
					updateCoreTabCard();
					// Re-render cards to update vulnerability counts
					if ($('#vortem-security-list').length > 0) {
						renderCards();
					}
					// Update overview tab if active
					if ($('#tab-overview').hasClass('active')) {
						updateOverviewTab();
					}
				} else {
					securityResultsData = []; // Store empty array
					showResultsError(response.data && response.data.message ? response.data.message : 'Failed to load security scan results.');
					$('#vortem-security-results-tabs-wrapper').addClass('hidden');
					updateCoreTabCard();
					// Re-render cards to update vulnerability counts
					if ($('#vortem-security-list').length > 0) {
						renderCards();
					}
				}
			},
			error: function(xhr, status, error) {
				$('#vortem-security-results-loading').addClass('hidden');
				securityDataLoaded = true; // Mark as loaded even on error
				let errorMessage = 'Failed to load security scan results.';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMessage = xhr.responseJSON.data.message;
				} else if (xhr.status === 0) {
					errorMessage = 'Unable to connect to server. Please check your connection.';
				} else if (xhr.status === 401) {
					errorMessage = 'Authentication failed. Please check your session token.';
				} else if (xhr.status === 403) {
					errorMessage = 'Access denied. Please check your session token permissions.';
				} else if (xhr.status >= 500) {
					errorMessage = 'Server error. Please try again later.';
				}
				showResultsError(errorMessage);
				$('#vortem-security-results-tabs-wrapper').addClass('hidden');
				securityResultsData = [];
				updateCoreTabCard();
				// Re-render cards to update vulnerability counts
				if ($('#vortem-security-list').length > 0) {
					renderCards();
				}
			}
		});
	}

	/**
	 * Fetch security results silently (without showing loading state)
	 */
	function fetchSecurityResultsSilently() {
		const ajaxData = window.vortemSecurityAjax || {};
		
		if (!ajaxData.ajax_url || !ajaxData.nonce) {
			return;
		}

		// Make AJAX request silently (no loading state)
		$.ajax({
			url: ajaxData.ajax_url,
			type: 'POST',
			data: {
				action: 'vortem_get_security_results',
				nonce: ajaxData.nonce
			},
			dataType: 'json',
			timeout: 30000,
			success: function(response) {
				securityDataLoaded = true; // Mark as loaded
				if (response.success && Array.isArray(response.data)) {
					securityResultsData = response.data; // Store globally
					updateSecurityLastUpdated();
					updateCoreTabCard();
					// Re-render cards to update vulnerability counts
					if ($('#vortem-security-list').length > 0) {
						renderCards();
					}
					// Update overview tab if active
					if ($('#tab-overview').hasClass('active')) {
						updateOverviewTab();
					}
				} else {
					securityResultsData = []; // Store empty array
					updateCoreTabCard();
					// Re-render cards to update vulnerability counts
					if ($('#vortem-security-list').length > 0) {
						renderCards();
					}
					// Update overview tab if active
					if ($('#tab-overview').hasClass('active')) {
						updateOverviewTab();
					}
				}
			},
			error: function() {
				securityDataLoaded = true; // Mark as loaded even on error
				securityResultsData = []; // Store empty array on error
				updateCoreTabCard();
				// Re-render cards to update vulnerability counts
				if ($('#vortem-security-list').length > 0) {
					renderCards();
				}
			}
		});
	}

	/**
	 * Display security scan results
	 */
	function displaySecurityResults(results) {
		VortemLogger.log('Security: displaySecurityResults called with results:', results ? results.length : 0);
		// Hide loading and error states with inline styles
		$('#vortem-security-results-loading').addClass('hidden').css('display', 'none');
		$('#vortem-security-results-error').addClass('hidden').css('display', 'none');
		
		const $container = $('#vortem-security-results-container');
		$container.empty();

		if (!results || results.length === 0) {
			const strings = window.vortemSecurityStrings || {};
			let secureMessage = '';
			if (currentResultsType === 'themes') {
				secureMessage = strings.your_themes_are_secure || 'Your themes are secure!';
			} else if (currentResultsType === 'wp-core') {
				secureMessage = strings.your_wp_core_is_secure || 'Your WordPress core is secure!';
			} else {
				secureMessage = strings.your_plugins_are_secure || 'Your plugins are secure!';
			}
			$container.html(
				'<div class="vortem-results-empty">' +
				'<div class="empty-icon">' +
				'<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg></div>' +
				'<h3 class="empty-title">' + (strings.no_security_vulnerabilities_found || 'No security vulnerabilities found') + '</h3>' +
				'<p class="empty-description">' + secureMessage + '</p>' +
				'</div>'
			);
			$container.removeClass('hidden').css('display', 'grid');
			$('#vortem-security-results-tabs-wrapper').addClass('hidden').css('display', 'none');
			return;
		}

		// Filter results by current tab type
		const filteredResults = results.filter(function(item) {
			const itemType = item.type || 'plugin';
			return (currentResultsType === 'themes' && itemType === 'theme') || 
			       (currentResultsType === 'plugins' && itemType === 'plugin') ||
			       (currentResultsType === 'wp-core' && itemType === 'wp-core');
		});

		// Count items for each tab
		const pluginCount = results.filter(function(item) {
			return (item.type || 'plugin') === 'plugin';
		}).length;
		const themeCount = results.filter(function(item) {
			return (item.type || 'plugin') === 'theme';
		}).length;
		const wpCoreCount = results.filter(function(item) {
			return (item.type || 'plugin') === 'wp-core';
		}).length;

		// Update tab counts
		$('#resultsPluginsTabCount').text(pluginCount);
		$('#resultsThemesTabCount').text(themeCount);
		$('#resultsWpCoreTabCount').text(wpCoreCount);

		// Show tabs if we have results
		if (results.length > 0) {
			$('#vortem-security-results-tabs-wrapper').removeClass('hidden').css('display', 'block');
		}

		if (filteredResults.length === 0) {
			const strings = window.vortemSecurityStrings || {};
			let secureMessage = '';
			if (currentResultsType === 'themes') {
				secureMessage = strings.your_themes_are_secure || 'Your themes are secure!';
			} else if (currentResultsType === 'wp-core') {
				secureMessage = strings.your_wp_core_is_secure || 'Your WordPress core is secure!';
			} else {
				secureMessage = strings.your_plugins_are_secure || 'Your plugins are secure!';
			}
			$container.html(
				'<div class="vortem-results-empty">' +
				'<div class="empty-icon">' +
				'<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg></div>' +
				'<h3 class="empty-title">' + (strings.no_security_vulnerabilities_found || 'No security vulnerabilities found') + '</h3>' +
				'<p class="empty-description">' + secureMessage + '</p>' +
				'</div>'
			);
			$container.removeClass('hidden').css('display', 'grid');
			return;
		}

		// Group results by item (plugin, theme, or wp-core) and count by severity
		const itemsMap = {};
		filteredResults.forEach(function(item) {
			let itemName = '';
			let itemType = item.type || 'plugin';
			
			if (itemType === 'theme') {
				itemName = item.customer_theme || item.customer_theme_name || item.matched_theme?.theme_name || 'Unknown Theme';
			} else if (itemType === 'wp-core') {
				itemName = 'WordPress Core';
			} else {
				itemName = item.customer_plugin_name || item.customer_plugin || 'Unknown Plugin';
			}
			
			if (!itemsMap[itemName]) {
				itemsMap[itemName] = {
					name: itemName,
					type: itemType,
					vulnerabilities: [],
					severityCounts: {
						critical: 0,
						high: 0,
						medium: 0,
						low: 0
					}
				};
			}
			itemsMap[itemName].vulnerabilities.push(item);
			
			// Count by severity - use direct severity field from API, or extract from CVSS
			let severity = 'low';
			
			// First, check if severity is directly provided in the response
			if (item.severity) {
				const severityText = String(item.severity).toLowerCase().trim();
				if (severityText === 'critical') {
					severity = 'critical';
				} else if (severityText === 'high') {
					severity = 'high';
				} else if (severityText === 'medium') {
					severity = 'medium';
				} else if (severityText === 'low') {
					severity = 'low';
				}
			}
			
			// If no direct severity field, try to extract from CVSS classification
			if (severity === 'low' && item.classification && Array.isArray(item.classification)) {
				const cvssItem = item.classification.find(function(c) {
					return c && c.key === 'CVSS';
				});
				if (cvssItem && cvssItem.value) {
					const cvssValue = String(cvssItem.value).trim();
					
					// Try to extract severity from parentheses (e.g., "5.9 (medium)")
					const severityMatch = cvssValue.match(/\(([^)]+)\)/i);
					if (severityMatch) {
						const severityText = severityMatch[1].toLowerCase().trim();
						if (severityText === 'critical') {
							severity = 'critical';
						} else if (severityText === 'high') {
							severity = 'high';
						} else if (severityText === 'medium') {
							severity = 'medium';
						} else if (severityText === 'low') {
							severity = 'low';
						}
					}
					
					// If no severity in parentheses, extract from numeric score
					if (severity === 'low' && !severityMatch) {
						const scoreMatch = cvssValue.match(/(\d+\.?\d*)/);
						if (scoreMatch) {
							const cvssScore = parseFloat(scoreMatch[1]);
							if (!isNaN(cvssScore)) {
								if (cvssScore >= 9.0) {
									severity = 'critical';
								} else if (cvssScore >= 7.0) {
									severity = 'high';
								} else if (cvssScore >= 4.0) {
									severity = 'medium';
								}
							}
						}
					}
				}
			}
			
			// Increment the appropriate severity count
			if (severity === 'critical') {
				itemsMap[itemName].severityCounts.critical++;
			} else if (severity === 'high') {
				itemsMap[itemName].severityCounts.high++;
			} else if (severity === 'medium') {
				itemsMap[itemName].severityCounts.medium++;
			} else if (severity === 'low') {
				itemsMap[itemName].severityCounts.low++;
			}
		});

		// Create cards for each item
		Object.values(itemsMap).forEach(function(item) {
			const $card = createPluginCard(item);
			$container.append($card);
		});

		$container.removeClass('hidden').css('display', 'grid');
	}

	/**
	 * Create a plugin/theme card with severity counts
	 */
	function createPluginCard(item) {
		const $card = $('<div>').addClass('vortem-plugin-card');
		
		// Card header
		const $header = $('<div>').addClass('plugin-card-header');
		$header.append($('<h3>').addClass('plugin-card-title').text(item.name));
		const totalVulns = item.vulnerabilities.length;
		const strings = window.vortemSecurityStrings || {};
		const vulnerabilityText = totalVulns === 1 ? (strings.vulnerability || 'Vulnerability') : (strings.vulnerabilities || 'Vulnerabilities');
		$header.append($('<span>').addClass('plugin-vuln-count-badge').text(totalVulns + ' ' + vulnerabilityText));
		$card.append($header);

		// Severity counts - always show all severity levels with their counts
		const $severityCounts = $('<div>').addClass('plugin-severity-counts');
		
		// Always display all severity levels, even if count is 0
		$severityCounts.append(createSeverityBadge('Critical', item.severityCounts.critical || 0, 'critical'));
		$severityCounts.append(createSeverityBadge('High', item.severityCounts.high || 0, 'high'));
		$severityCounts.append(createSeverityBadge('Medium', item.severityCounts.medium || 0, 'medium'));
		$severityCounts.append(createSeverityBadge('Low', item.severityCounts.low || 0, 'low'));
		
		$card.append($severityCounts);

		// View Details button
		const $button = $('<button>')
			.addClass('btn-primary')
			.addClass('view-vuln-details-btn')
			.attr('data-plugin-name', item.name)
			.text('View All Vulnerabilities');
		$card.append($button);

		// Store item data for modal
		$card.data('plugin-data', item);

		return $card;
	}

	/**
	 * Create a severity badge
	 */
	function createSeverityBadge(label, count, severity) {
		const $badge = $('<div>').addClass('severity-badge').addClass('severity-' + severity);
		$badge.append($('<span>').addClass('severity-label').text(label));
		$badge.append($('<span>').addClass('severity-count').text(count));
		return $badge;
	}

	/**
	 * Show results error
	 */
	function showResultsError(message) {
		$('#vortem-security-results-error-message').text(message);
		$('#vortem-security-results-error').removeClass('hidden');
		$('#vortem-security-results-container').addClass('hidden');
		$('#vortem-security-results-tabs-wrapper').addClass('hidden');
	}

	// Retry button handler
	$(document).on('click', '#vortem-security-results-retry', function() {
		fetchSecurityResults();
	});

	// View vulnerabilities button handler
	$(document).on('click', '.view-vuln-details-btn', function() {
		const $card = $(this).closest('.vortem-plugin-card');
		const pluginData = $card.data('plugin-data');
		if (pluginData) {
			openVulnerabilitiesModal(pluginData);
		}
	});

	// Vulnerability count badge click handler (from Security Overview cards)
	$(document).on('click', '.security-vuln-clickable', function() {
		const itemName = $(this).data('item-name') || $(this).data('plugin-name');
		const itemType = $(this).data('item-type') || 'plugin';
		if (!itemName) {
			return;
		}

		// Get item vulnerability data
		const itemData = getItemVulnerabilityDataByName(itemName, itemType);
		if (!itemData || !itemData.vulnerabilities || itemData.vulnerabilities.length === 0) {
			return;
		}

		// Switch to results tab
		const $resultsTab = $('.tab[data-tab="results"]');
		if ($resultsTab.length > 0) {
			$resultsTab.trigger('click');
			
			// Wait for tab to switch and data to load, then open modal
			setTimeout(function() {
				// Ensure we have the latest data
				if (securityResultsData.length === 0) {
					// If no data, fetch it first
					fetchSecurityResults(true);
					// Wait for data to load
					const checkInterval = setInterval(function() {
						if (securityResultsData.length > 0) {
							clearInterval(checkInterval);
							const updatedItemData = getItemVulnerabilityDataByName(itemName, itemType);
							if (updatedItemData) {
								openVulnerabilitiesModal(updatedItemData);
							}
						}
					}, 100);
				} else {
					// Open modal with current data
					openVulnerabilitiesModal(itemData);
				}
			}, 500);
		}
	});

	// Modal close handlers
	$('#vortem-vulnerabilities-modal-close').on('click', function() {
		closeVulnerabilitiesModal();
	});

	$('#vortem-vulnerabilities-modal').on('click', function(e) {
		if ($(e.target).hasClass('modal-overlay')) {
			closeVulnerabilitiesModal();
		}
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && $('#vortem-vulnerabilities-modal').hasClass('active')) {
			closeVulnerabilitiesModal();
		}
	});

	// Severity filter handler
	$('#vuln-severity-filter').on('change', function() {
		filterModalVulnerabilities();
	});

	// Store current plugin data for modal
	let currentModalPluginData = null;

	/**
	 * Open vulnerabilities modal
	 */
	function openVulnerabilitiesModal(pluginData) {
		currentModalPluginData = pluginData;
		const $modal = $('#vortem-vulnerabilities-modal');
		const $modalTitle = $('#vuln-modal-title');
		const $modalList = $('#vuln-modal-list');
		
		// Set title
		const strings = window.vortemSecurityStrings || {};
		const vulnerabilitiesText = strings.vulnerabilities || 'Vulnerabilities';
		$modalTitle.text(pluginData.name + ' - ' + vulnerabilitiesText);
		
		// Reset filter
		$('#vuln-severity-filter').val('all');
		
		// Render all vulnerabilities
		renderModalVulnerabilities(pluginData.vulnerabilities);
		
		// Show modal
		$modal.addClass('active');
		$('body').addClass('modal-open');
	}

	/**
	 * Close vulnerabilities modal
	 */
	function closeVulnerabilitiesModal() {
		const $modal = $('#vortem-vulnerabilities-modal');
		$modal.removeClass('active');
		$('body').removeClass('modal-open');
		currentModalPluginData = null;
	}

	/**
	 * Get item vulnerability data by item name and type
	 */
	function getItemVulnerabilityDataByName(itemName, itemType) {
		// Group results by item and count by severity
		const itemsMap = {};
		securityResultsData.forEach(function(item) {
			let itemNameKey = '';
			if (itemType === 'theme') {
				itemNameKey = item.customer_theme || item.customer_theme_name || item.matched_theme?.theme_name || 'Unknown Theme';
			} else if (itemType === 'wp-core') {
				const strings = window.vortemSecurityStrings || {};
				itemNameKey = strings.wordpress_core || 'WordPress Core';
			} else {
				itemNameKey = item.customer_plugin_name || item.customer_plugin || 'Unknown Plugin';
			}
			
			// Only process items of the matching type
			if (item.type !== itemType) {
				return;
			}
			
			if (!itemsMap[itemNameKey]) {
				itemsMap[itemNameKey] = {
					name: itemNameKey,
					type: itemType,
					vulnerabilities: [],
					severityCounts: {
						critical: 0,
						high: 0,
						medium: 0,
						low: 0
					}
				};
			}
			itemsMap[itemNameKey].vulnerabilities.push(item);
			
			// Count by severity - use direct severity field from API, or extract from CVSS
			let severity = 'low';
			
			// First, check if severity is directly provided in the response
			if (item.severity) {
				const severityText = String(item.severity).toLowerCase().trim();
				if (severityText === 'critical') {
					severity = 'critical';
				} else if (severityText === 'high') {
					severity = 'high';
				} else if (severityText === 'medium') {
					severity = 'medium';
				} else if (severityText === 'low') {
					severity = 'low';
				}
			}
			
			// If no direct severity field, try to extract from CVSS classification
			if (severity === 'low' && item.classification && Array.isArray(item.classification)) {
				const cvssItem = item.classification.find(function(c) {
					return c && c.key === 'CVSS';
				});
				if (cvssItem && cvssItem.value) {
					const cvssValue = String(cvssItem.value).trim();
					
					// Try to extract severity from parentheses (e.g., "5.9 (medium)")
					const severityMatch = cvssValue.match(/\(([^)]+)\)/i);
					if (severityMatch) {
						const severityText = severityMatch[1].toLowerCase().trim();
						if (severityText === 'critical') {
							severity = 'critical';
						} else if (severityText === 'high') {
							severity = 'high';
						} else if (severityText === 'medium') {
							severity = 'medium';
						} else if (severityText === 'low') {
							severity = 'low';
						}
					}
					
					// If no severity in parentheses, extract from numeric score
					if (severity === 'low' && !severityMatch) {
						const scoreMatch = cvssValue.match(/(\d+\.?\d*)/);
						if (scoreMatch) {
							const cvssScore = parseFloat(scoreMatch[1]);
							if (!isNaN(cvssScore)) {
								if (cvssScore >= 9.0) {
									severity = 'critical';
								} else if (cvssScore >= 7.0) {
									severity = 'high';
								} else if (cvssScore >= 4.0) {
									severity = 'medium';
								}
							}
						}
					}
				}
			}
			
			// Increment the appropriate severity count
			if (severity === 'critical') {
				itemsMap[itemNameKey].severityCounts.critical++;
			} else if (severity === 'high') {
				itemsMap[itemNameKey].severityCounts.high++;
			} else if (severity === 'medium') {
				itemsMap[itemNameKey].severityCounts.medium++;
			} else if (severity === 'low') {
				itemsMap[itemNameKey].severityCounts.low++;
			}
		});
		
		// Find matching item (case-insensitive)
		for (const key in itemsMap) {
			if (key.toLowerCase() === itemName.toLowerCase()) {
				return itemsMap[key];
			}
		}
		
		return null;
	}

	/**
	 * Render vulnerabilities in modal
	 */
	function renderModalVulnerabilities(vulnerabilities) {
		const $modalList = $('#vuln-modal-list');
		$modalList.empty();

		if (!vulnerabilities || vulnerabilities.length === 0) {
			$modalList.html('<p class="no-vulns">No vulnerabilities found.</p>');
			return;
		}

		vulnerabilities.forEach(function(vuln) {
			const $vulnItem = createVulnerabilityItem(vuln);
			$modalList.append($vulnItem);
		});
	}

	/**
	 * Create a vulnerability item for modal
	 */
	function createVulnerabilityItem(vuln) {
		const $item = $('<div>').addClass('vuln-modal-item').attr('data-severity', (vuln.severity || '').toLowerCase());
		
		// Left border indicator
		const severity = (vuln.severity || '').toLowerCase();
		$item.addClass('severity-border-' + severity);
		
		// CVE ID and severity header (new spec uses `cve`; legacy fallbacks kept)
		const $header = $('<div>').addClass('vuln-item-header');
		const cveLabel = vuln.cve || vuln.cve_id || vuln.title || 'N/A';
		$header.append($('<span>').addClass('vuln-cve-id').text(cveLabel));
		$header.append($('<span>').addClass('vuln-severity').addClass('severity-' + severity).text(vuln.severity || 'UNKNOWN'));
		// CVSS: prefer `cvss_score` from new spec, fall back to legacy `cvss`
		const cvssValueRaw = vuln.cvss_score !== undefined ? vuln.cvss_score : vuln.cvss;
		const cvssDisplay = typeof cvssValueRaw === 'string' ? cvssValueRaw : (cvssValueRaw !== undefined && cvssValueRaw !== null ? cvssValueRaw : '0.0');
		$header.append($('<span>').addClass('vuln-cvss').text('CVSS: ' + cvssDisplay));
		$item.append($header);
		
		// Title (for wp-core vulnerabilities)
		if (vuln.title && !vuln.cve && !vuln.cve_id) {
			$item.append($('<h4>').addClass('vuln-title').text(vuln.title));
		}
		
		// Description
		if (vuln.description) {
			$item.append($('<p>').addClass('vuln-description').text(vuln.description));
		}

		const strings = window.vortemSecurityStrings || {};

		// Affected plugin/theme version (new field from the v1 plugin spec)
		const affectedVersion = vuln.customer_plugin_version || vuln.customer_theme_version || vuln.customer_wordpress_version || '';
		if (affectedVersion) {
			const affectedVersionLabel = strings.affected_version_label || 'Affected Version';
			$item.append($('<div>').addClass('vuln-meta').html('<strong>' + escapeHtml(affectedVersionLabel) + ':</strong> ' + escapeHtml(affectedVersion)));
		}

		// CWE
		if (vuln.cwe) {
			$item.append($('<div>').addClass('vuln-meta').html('<strong>CWE:</strong> ' + escapeHtml(vuln.cwe)));
		}

		// Fixed version (new spec uses `fixed_version`, legacy `fixed_in`)
		const fixedVersion = vuln.fixed_version || vuln.fixed_in || '';
		if (fixedVersion) {
			const fixedVersionLabel = strings.fixed_version_label || 'Fixed Version';
			$item.append($('<div>').addClass('vuln-meta').html('<strong>' + escapeHtml(fixedVersionLabel) + ':</strong> ' + escapeHtml(fixedVersion)));
		}

		// Dates
		const $metaRow = $('<div>').addClass('vuln-meta-row');
		const publishedDate = vuln.published_date || vuln.published;
		if (publishedDate) {
			const publishedLabel = strings.published || 'Published';
			$metaRow.append($('<span>').addClass('vuln-meta').html('<strong>' + escapeHtml(publishedLabel) + ':</strong> ' + formatDate(publishedDate)));
		}
		const lastModified = vuln.last_modified || vuln.lastModified;
		if (lastModified) {
			const lastModifiedLabel = strings.last_modified || 'Last Modified';
			$metaRow.append($('<span>').addClass('vuln-meta').html('<strong>' + escapeHtml(lastModifiedLabel) + ':</strong> ' + formatDate(lastModified)));
		}
		if ($metaRow.children().length > 0) {
			$item.append($metaRow);
		}
		
		// References
		if (vuln.references && vuln.references.length > 0) {
			const strings = window.vortemSecurityStrings || {};
			const $refsList = $('<div>').addClass('vuln-references');
			const referencesLabel = strings.references || 'References';
			$refsList.append($('<strong>').text(referencesLabel + ': '));
			vuln.references.forEach(function(ref, index) {
				const $link = $('<a>').attr('href', ref).attr('target', '_blank').attr('rel', 'noopener noreferrer').text('Link ' + (index + 1));
				$refsList.append($link);
				if (index < vuln.references.length - 1) {
					$refsList.append($('<span>').text(', '));
				}
			});
			$item.append($refsList);
		}
		
		return $item;
	}

	/**
	 * Filter vulnerabilities in modal by severity
	 */
	function filterModalVulnerabilities() {
		if (!currentModalPluginData) {
			return;
		}

		const selectedSeverity = $('#vuln-severity-filter').val();
		const $items = $('.vuln-modal-item');

		if (selectedSeverity === 'all') {
			$items.show();
		} else {
			$items.each(function() {
				const $item = $(this);
				const itemSeverity = $item.attr('data-severity');
				if (itemSeverity === selectedSeverity) {
					$item.show();
				} else {
					$item.hide();
				}
			});
		}
	}

	/**
	 * Format date string to readable format
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

	/**
	 * Hide skeleton and show content
	 */
	function hideOverviewSkeleton() {
		var $skeleton = $('#overview-skeleton');
		var $content = $('#overview-content');
		var $tabOverview = $('#tab-overview');
		
		if ($skeleton.length && $content.length) {
			// Add hiding class for fade out animation
			$skeleton.addClass('hiding');
			
			// After animation, hide skeleton and show content
			setTimeout(function() {
				$skeleton.hide();
				$tabOverview.addClass('vortem-overview-loaded');
			}, 300);
		}
	}

	/**
	 * Show skeleton (used when data is loading)
	 */
	function showOverviewSkeleton() {
		var $skeleton = $('#overview-skeleton');
		var $tabOverview = $('#tab-overview');
		
		$skeleton.removeClass('hiding').show();
		$tabOverview.removeClass('vortem-overview-loaded');
	}

	/**
	 * Update overview tab with statistics and lists
	 */
	function updateOverviewTab() {
		VortemLogger.log('Security: updateOverviewTab called, securityDataLoaded:', securityDataLoaded);

		if (!securityDataLoaded) {
			// Show skeleton loading state
			showOverviewSkeleton();
			return;
		}
		
		// Hide skeleton and show content
		hideOverviewSkeleton();
		
		VortemLogger.log('Security: Processing overview data, total vulnerabilities:', securityResultsData.length);

		// Calculate statistics
		const severityCounts = { critical: 0, high: 0, medium: 0, low: 0 };
		const itemsWithVulns = new Set();
		const totalItems = allPlugins.length + allThemes.length + (wpCoreData ? 1 : 0);

		securityResultsData.forEach(function(vuln) {
			const severity = getSeverityFromVuln(vuln);
			if (severityCounts.hasOwnProperty(severity)) {
				severityCounts[severity]++;
			}

			const vulnType = vuln.type || 'plugin';

			// Track items with vulnerabilities (new spec uses `customer_plugin_name`)
			const vulnPluginName = vuln.customer_plugin_name || vuln.customer_plugin;
			if (vulnType === 'plugin' && vulnPluginName) {
				itemsWithVulns.add('plugin:' + vulnPluginName.toLowerCase());
			} else if (vulnType === 'theme' && (vuln.customer_theme || vuln.customer_theme_name)) {
				const tn = vuln.customer_theme || vuln.customer_theme_name;
				itemsWithVulns.add('theme:' + tn.toLowerCase());
			} else if (vulnType === 'wp-core') {
				itemsWithVulns.add('wp-core');
			}
		});

		const totalVulns = securityResultsData.length;
		const secureItems = totalItems - itemsWithVulns.size;

		// Update summary cards
		$('#overview-total-vulns-value').text(totalVulns);
		$('#overview-critical-vulns-value').text(severityCounts.critical);
		$('#overview-high-vulns-value').text(severityCounts.high);
		$('#overview-medium-vulns-value').text(severityCounts.medium);
		$('#overview-low-vulns-value').text(severityCounts.low);
		$('#overview-secure-items-value').text(secureItems);
		
		// Add clickable class to cards that have vulnerabilities
		if (totalVulns > 0) {
			$('#overview-total-vulns').addClass('vortem-card-clickable');
		} else {
			$('#overview-total-vulns').removeClass('vortem-card-clickable');
		}
		
		if (severityCounts.critical > 0) {
			$('#overview-critical-vulns').addClass('vortem-card-clickable');
		} else {
			$('#overview-critical-vulns').removeClass('vortem-card-clickable');
		}
		
		if (severityCounts.high > 0) {
			$('#overview-high-vulns').addClass('vortem-card-clickable');
		} else {
			$('#overview-high-vulns').removeClass('vortem-card-clickable');
		}
		
		if (severityCounts.medium > 0) {
			$('#overview-medium-vulns').addClass('vortem-card-clickable');
		} else {
			$('#overview-medium-vulns').removeClass('vortem-card-clickable');
		}
		
		if (severityCounts.low > 0) {
			$('#overview-low-vulns').addClass('vortem-card-clickable');
		} else {
			$('#overview-low-vulns').removeClass('vortem-card-clickable');
		}
		
		// Add/remove clickable class based on whether cards have vulnerabilities
		$('#overview-total-vulns').toggleClass('vortem-card-clickable', totalVulns > 0);
		$('#overview-critical-vulns').toggleClass('vortem-card-clickable', severityCounts.critical > 0);
		$('#overview-high-vulns').toggleClass('vortem-card-clickable', severityCounts.high > 0);
		$('#overview-medium-vulns').toggleClass('vortem-card-clickable', severityCounts.medium > 0);
		$('#overview-low-vulns').toggleClass('vortem-card-clickable', severityCounts.low > 0);
		
		// Update tooltips for summary cards with detailed information
		// Calculate percentage of items affected (not vulnerabilities count)
		const itemsAffected = itemsWithVulns.size;
		const totalPercent = totalItems > 0 ? Math.min(((itemsAffected / totalItems) * 100).toFixed(1), 100) : 0;
		const securePercent = totalItems > 0 ? ((secureItems / totalItems) * 100).toFixed(1) : 0;
		const tooltipStrings = window.vortemSecurityStrings || {};
		
		$('#tooltip-total-vulns').html(
			'<strong>' + (tooltipStrings.all_security_issues || 'All Security Issues') + '</strong>' +
			'<span>' + (tooltipStrings.across_all_items || 'Across all plugins, themes & core') + '</span>' +
			'<span>' + totalPercent + '% ' + (tooltipStrings.of_installed_items_affected || 'of installed items affected') + '</span>'
		);
		
		const criticalPercent = totalVulns > 0 ? ((severityCounts.critical / totalVulns) * 100).toFixed(1) : 0;
		$('#tooltip-critical-vulns').html(
			'<strong>' + (tooltipStrings.critical_severity || 'Critical Severity') + '</strong>' +
			'<span>' + (tooltipStrings.requires_immediate_attention || 'Requires immediate attention') + '</span>' +
			'<span>' + criticalPercent + '% ' + (tooltipStrings.of_all_vulnerabilities || 'of all vulnerabilities') + '</span>'
		);
		
		const highPercent = totalVulns > 0 ? ((severityCounts.high / totalVulns) * 100).toFixed(1) : 0;
		$('#tooltip-high-vulns').html(
			'<strong>' + (tooltipStrings.high_severity || 'High Severity') + '</strong>' +
			'<span>' + (tooltipStrings.address_asap || 'Address as soon as possible') + '</span>' +
			'<span>' + highPercent + '% ' + (tooltipStrings.of_all_vulnerabilities || 'of all vulnerabilities') + '</span>'
		);
		
		const mediumPercent = totalVulns > 0 ? ((severityCounts.medium / totalVulns) * 100).toFixed(1) : 0;
		$('#tooltip-medium-vulns').html(
			'<strong>' + (tooltipStrings.medium_severity || 'Medium Severity') + '</strong>' +
			'<span>' + (tooltipStrings.schedule_for_review || 'Schedule for review') + '</span>' +
			'<span>' + mediumPercent + '% ' + (tooltipStrings.of_all_vulnerabilities || 'of all vulnerabilities') + '</span>'
		);
		
		const lowPercent = totalVulns > 0 ? ((severityCounts.low / totalVulns) * 100).toFixed(1) : 0;
		$('#tooltip-low-vulns').html(
			'<strong>' + (tooltipStrings.low_severity || 'Low Severity') + '</strong>' +
			'<span>' + (tooltipStrings.monitor_and_plan_fix || 'Monitor and plan fix') + '</span>' +
			'<span>' + lowPercent + '% ' + (tooltipStrings.of_all_vulnerabilities || 'of all vulnerabilities') + '</span>'
		);
		
		$('#tooltip-secure-items').html(
			'<strong>' + (tooltipStrings.your_site_is_secure || 'Your site is secure!') + '</strong>' +
			'<span>' + (tooltipStrings.no_known_vulnerabilities || 'No known vulnerabilities detected') + '</span>' +
			'<span>' + securePercent + '% ' + (tooltipStrings.of_items_are_clean || 'of items are clean') + '</span>'
		);

		// Update security status banner
		const strings = window.vortemSecurityStrings || {};
		let statusText = '';
		let statusDescription = '';
		let statusClass = '';
		const $statusBanner = $('#overview-status-banner');
		
		if (totalVulns === 0) {
			statusText = strings.your_site_is_secure || 'Your site is secure!';
			statusDescription = strings.all_items_are_secure || 'All your plugins, themes, and WordPress core are secure and up to date.';
			statusClass = 'secure';
		} else if (severityCounts.critical > 0) {
			statusText = strings.critical_vulnerabilities_found || 'Critical vulnerabilities detected!';
			statusDescription = strings.immediate_action_required || 'Immediate action is required. Please update or remove affected items as soon as possible.';
			statusClass = 'critical';
		} else if (severityCounts.high > 0) {
			statusText = strings.high_vulnerabilities_found || 'High severity vulnerabilities found';
			statusDescription = strings.action_recommended || 'Action is recommended. Please review and update affected items.';
			statusClass = 'high';
		} else {
			statusText = strings.some_vulnerabilities_found || 'Some vulnerabilities found';
			statusDescription = strings.review_recommended || 'Review recommended. Monitor and update affected items when possible.';
			statusClass = 'medium';
		}
		
		$statusBanner.removeClass('secure critical high medium').addClass(statusClass);
		$('#overview-status-title').text(statusText);
		$('#overview-status-description').text(statusDescription);

		// Update items at risk list
		updateRiskItemsList(itemsWithVulns, severityCounts);
		
		// Update recent vulnerabilities list
		updateRecentVulnerabilitiesList();
	}


	/**
	 * Update risk items list
	 */
	function updateRiskItemsList(itemsWithVulns, severityCounts) {
		const $riskList = $('#overview-risk-list');
		const $riskCount = $('#overview-risk-count');
		
		if (itemsWithVulns.size === 0) {
			$riskList.html('<div class="vortem-overview-risk-empty"><p>' + (window.vortemSecurityStrings?.no_items_at_risk || 'No items at risk') + '</p></div>');
			$riskCount.text('0');
			return;
		}
		
		$riskCount.text(itemsWithVulns.size);
		$riskList.empty();
		
		// Group vulnerabilities by item
		const itemsMap = {};
		securityResultsData.forEach(function(vuln) {
			let itemName = '';
			let itemType = vuln.type || 'plugin';
			
			if (itemType === 'theme') {
				itemName = vuln.customer_theme || vuln.customer_theme_name || 'Unknown Theme';
			} else if (itemType === 'wp-core') {
				itemName = 'WordPress Core';
			} else {
				itemName = vuln.customer_plugin_name || vuln.customer_plugin || 'Unknown Plugin';
			}
			
			const itemKey = itemType + ':' + itemName.toLowerCase();
			if (!itemsMap[itemKey]) {
				itemsMap[itemKey] = {
					name: itemName,
					type: itemType,
					vulnerabilities: [],
					severityCounts: { critical: 0, high: 0, medium: 0, low: 0 }
				};
			}
			
			itemsMap[itemKey].vulnerabilities.push(vuln);
			const severity = getSeverityFromVuln(vuln);
			if (itemsMap[itemKey].severityCounts.hasOwnProperty(severity)) {
				itemsMap[itemKey].severityCounts[severity]++;
			}
		});
		
		// Sort by severity (critical first, then high, etc.)
		const sortedItems = Object.values(itemsMap).sort(function(a, b) {
			if (a.severityCounts.critical !== b.severityCounts.critical) {
				return b.severityCounts.critical - a.severityCounts.critical;
			}
			if (a.severityCounts.high !== b.severityCounts.high) {
				return b.severityCounts.high - a.severityCounts.high;
			}
			if (a.severityCounts.medium !== b.severityCounts.medium) {
				return b.severityCounts.medium - a.severityCounts.medium;
			}
			return b.vulnerabilities.length - a.vulnerabilities.length;
		});
		
		// Render items (limit to top 10)
		sortedItems.slice(0, 10).forEach(function(item) {
			const $item = $('<div>').addClass('vortem-overview-risk-item');
			
			// Determine highest severity
			let highestSeverity = 'low';
			if (item.severityCounts.critical > 0) {
				highestSeverity = 'critical';
			} else if (item.severityCounts.high > 0) {
				highestSeverity = 'high';
			} else if (item.severityCounts.medium > 0) {
				highestSeverity = 'medium';
			}
			
			// Icon
			const iconSvg = item.type === 'theme' 
				? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				: item.type === 'wp-core'
				? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16.5 9.4L7.55 4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.27 6.96L12 12.01l8.73-5.05" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 22.08V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			
			const $left = $('<div>').addClass('vortem-overview-risk-item-left');
			$left.append($('<div>').addClass('vortem-overview-risk-item-icon').html(iconSvg));
			
			const $info = $('<div>').addClass('vortem-overview-risk-item-info');
			$info.append($('<p>').addClass('vortem-overview-risk-item-name').text(item.name));
			const typeText = item.type === 'wp-core' ? 'WordPress Core' : (item.type === 'theme' ? 'Theme' : 'Plugin');
			$info.append($('<p>').addClass('vortem-overview-risk-item-type').text(typeText));
			$left.append($info);
			
			const $right = $('<div>').addClass('vortem-overview-risk-item-right');
			const $badges = $('<div>').addClass('vortem-overview-risk-item-badges');
			
			if (item.severityCounts.critical > 0) {
				$badges.append($('<span>').addClass('vortem-overview-risk-badge critical').text('Critical'));
			}
			if (item.severityCounts.high > 0) {
				$badges.append($('<span>').addClass('vortem-overview-risk-badge high').text('High'));
			}
			
			$right.append($badges);
			$right.append($('<span>').addClass('vortem-overview-risk-item-count').text(item.vulnerabilities.length + ' vuln' + (item.vulnerabilities.length !== 1 ? 's' : '')));
			
			$item.append($left);
			$item.append($right);
			
			// Add click handler to open vulnerabilities modal
			$item.on('click', function() {
				const itemName = item.name;
				const itemType = item.type === 'wp-core' ? 'wp-core' : (item.type === 'theme' ? 'theme' : 'plugin');
				openVulnerabilitiesDialog(itemName, itemType);
			});
			
			$riskList.append($item);
		});
	}

	/**
	 * Update recent vulnerabilities list
	 */
	function updateRecentVulnerabilitiesList() {
		const $recentList = $('#overview-recent-list');
		
		if (securityResultsData.length === 0) {
			$recentList.html('<div class="vortem-overview-recent-empty"><p>' + (window.vortemSecurityStrings?.no_recent_vulnerabilities || 'No recent vulnerabilities') + '</p></div>');
			return;
		}
		
		$recentList.empty();
		
		// Sort by published date (most recent first) and take top 5
		const sortedVulns = securityResultsData
			.filter(function(vuln) {
				return vuln.published_date || vuln.published || vuln.last_modified || vuln.last_modified_date || vuln.lastModified;
			})
			.sort(function(a, b) {
				const aDate = new Date(a.published_date || a.published || a.last_modified || a.last_modified_date || a.lastModified || 0);
				const bDate = new Date(b.published_date || b.published || b.last_modified || b.last_modified_date || b.lastModified || 0);
				return bDate - aDate;
			})
			.slice(0, 5);
		
		if (sortedVulns.length === 0) {
			// If no dates, just show first 5
			sortedVulns.push(...securityResultsData.slice(0, 5));
		}
		
		sortedVulns.forEach(function(vuln) {
			const severity = getSeverityFromVuln(vuln);
			const itemName = vuln.customer_plugin_name || vuln.customer_plugin || vuln.customer_theme || vuln.customer_theme_name || 'WordPress Core';
			const itemType = vuln.type || 'plugin';
			
			const $item = $('<div>').addClass('vortem-overview-recent-item');
			
			const $left = $('<div>').addClass('vortem-overview-recent-item-left');
			$left.append($('<div>').addClass('vortem-overview-recent-item-icon').html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'));
			
			const $info = $('<div>').addClass('vortem-overview-recent-item-info');
			$info.append($('<p>').addClass('vortem-overview-recent-item-name').text(vuln.cve || vuln.cve_id || vuln.title || 'Vulnerability'));
			$info.append($('<p>').addClass('vortem-overview-recent-item-type').text(itemName + ' · ' + itemType));
			$left.append($info);
			
			const $right = $('<div>').addClass('vortem-overview-recent-item-right');
			$right.append($('<span>').addClass('vortem-overview-recent-badge').addClass(severity).text(severity.toUpperCase()));
			
			$item.append($left);
			$item.append($right);
			
			// Add click handler
			$item.on('click', function() {
				openVulnerabilitiesDialog(itemName, itemType);
			});
			
			$recentList.append($item);
		});
	}
})(jQuery);
