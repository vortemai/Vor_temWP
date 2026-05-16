/**
 * Overview Dashboard - Workspace metrics data
 *
 * @package VortemAI
 */
(function () {
	'use strict';

	function getTranslatedLabel(key, fallback) {
		return (typeof vortemOverview !== 'undefined' && vortemOverview.i18n && vortemOverview.i18n[key]) ? vortemOverview.i18n[key] : fallback;
	}

	function fetchApi(path) {
		if (typeof vortemOverview === 'undefined') {
			return Promise.reject(new Error('Overview config not found'));
		}
		var url = vortemOverview.restUrl + path;
		var headers = {
			'Content-Type': 'application/json',
			'X-WP-Nonce': vortemOverview.nonce,
		};
		return fetch(url, { headers: headers }).then(function (res) {
			if (!res.ok) throw new Error('HTTP ' + res.status);
			return res.json();
		});
	}

	function formatCurrency(num) {
		if (num === null || num === undefined) return '—';
		return new Intl.NumberFormat(undefined, {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0,
		}).format(num);
	}

	function metricCardLoaded(valueEl) {
		if (!valueEl) return;
		var card = valueEl.closest && valueEl.closest('.overview-metric-card');
		if (card) card.classList.remove('overview-metric-card-loading');
	}

	function loadMetrics() {
		var formatNum = function (n) {
			if (n === null || n === undefined) return '—';
			return Number(n).toLocaleString();
		};

		if (typeof vortemOverview === 'undefined' || !vortemOverview.endpoints) return;

		// Metrics (orders, products, revenue cards) are loaded in init() via processMetrics(); here we only run vuln/perf/emails

		// Security vulns
		if (vortemOverview.endpoints.securityVulns) {
			fetchApi(vortemOverview.endpoints.securityVulns)
				.then(function (json) {
					var el = document.getElementById('overviewMetricVuln');
					if (el) {
						el.textContent = formatNum(json && json.total);
						metricCardLoaded(el);
					}
				})
				.catch(function () {
					var el = document.getElementById('overviewMetricVuln');
					if (el) {
						el.textContent = '—';
						metricCardLoaded(el);
					}
				});
		} else {
			var el = document.getElementById('overviewMetricVuln');
			if (el) {
				el.textContent = '—';
				metricCardLoaded(el);
			}
		}

		// Insights performance (average, tooltip with desktop/mobile)
		if (vortemOverview.endpoints.insightsPerformance) {
			fetchApi(vortemOverview.endpoints.insightsPerformance)
				.then(function (json) {
					var perfEl = document.getElementById('overviewMetricPerf');
					var cardEl = document.querySelector('[data-metric="perf"]');
					var perfTooltip = document.getElementById('tooltip-perf');
					var d = json && json.desktop != null ? Math.round(json.desktop) : null;
					var m = json && json.mobile != null ? Math.round(json.mobile) : null;
					var avg = null;
					if (d != null && m != null) {
						avg = Math.round((d + m) / 2);
					} else if (d != null) {
						avg = d;
					} else if (m != null) {
						avg = m;
					}
					if (perfEl) {
						perfEl.textContent = avg != null ? avg : '—';
						metricCardLoaded(perfEl);
					}
					if (cardEl) {
						if (d != null) cardEl.setAttribute('data-perf-desktop', d);
						else cardEl.setAttribute('data-perf-desktop', '');
						if (m != null) cardEl.setAttribute('data-perf-mobile', m);
						else cardEl.setAttribute('data-perf-mobile', '');
						var parts = [];
						if (d != null) parts.push(getTranslatedLabel('desktop', 'Desktop') + ': ' + d);
						if (m != null) parts.push(getTranslatedLabel('mobile', 'Mobile') + ': ' + m);
						if (avg != null) parts.unshift(getTranslatedLabel('average', 'Average') + ': ' + avg);
						cardEl.setAttribute('title', parts.length ? parts.join(' | ') : '');
					}
					if (perfTooltip) {
						var tooltipHtml = '<strong>' + getTranslatedLabel('performance', 'Performance') + '</strong>';
						var tooltipParts = [];
						if (avg != null) tooltipParts.push(getTranslatedLabel('average', 'Average') + ': ' + avg);
						if (d != null) tooltipParts.push(getTranslatedLabel('desktop', 'Desktop') + ': ' + d);
						if (m != null) tooltipParts.push(getTranslatedLabel('mobile', 'Mobile') + ': ' + m);
						if (tooltipParts.length) tooltipHtml += '<span>' + tooltipParts.join(' | ') + '</span>';
						perfTooltip.innerHTML = tooltipHtml;
					}
				})
				.catch(function () {
					var perfEl = document.getElementById('overviewMetricPerf');
					if (perfEl) {
						perfEl.textContent = '—';
						metricCardLoaded(perfEl);
					}
				});
		} else {
			var perfEl = document.getElementById('overviewMetricPerf');
			if (perfEl) {
				perfEl.textContent = '—';
				metricCardLoaded(perfEl);
			}
		}

		// Emails: total on card, tooltip = Total sent + Email lists sent
		if (vortemOverview.endpoints.emailsTotal) {
			fetchApi(vortemOverview.endpoints.emailsTotal)
				.then(function (json) {
					var total = json && json.total != null ? json.total : null;
					var el = document.getElementById('overviewMetricEmails');
					if (el) {
						el.textContent = total != null ? formatNum(total) : '—';
						metricCardLoaded(el);
					}
				})
				.catch(function () {
					var el = document.getElementById('overviewMetricEmails');
					if (el) {
						el.textContent = '—';
						metricCardLoaded(el);
					}
				});
		} else {
			var el = document.getElementById('overviewMetricEmails');
			if (el) {
				el.textContent = '—';
				metricCardLoaded(el);
			}
		}
	}

	function formatNum(n) {
		if (n === null || n === undefined) return '—';
		return Number(n).toLocaleString();
	}

	function processMetrics(json) {
		var wc = json.woocommerce || {};
		var ordersToday = wc.orders_today;
		var ordersTotal = wc.orders_total;
		var revenueTotal = wc.revenue_total;
		var productsCount = wc.products_count;

		var sel = document.querySelector('[data-metric="orders_today"]');
		if (sel) sel.textContent = formatNum(ordersToday);
		sel = document.querySelector('[data-metric="orders_total"]');
		if (sel) sel.textContent = formatNum(ordersTotal);
		sel = document.querySelector('[data-metric="revenue_total"]');
		if (sel) sel.textContent = formatCurrency(revenueTotal);
		sel = document.querySelector('[data-metric="products_count"]');
		if (sel) sel.textContent = formatNum(productsCount);

		var productsEl = document.getElementById('overviewMetricProducts');
		if (productsEl) {
			productsEl.textContent = formatNum(productsCount);
			metricCardLoaded(productsEl);
		}
	}

	function init() {
		if (typeof vortemOverview === 'undefined') {
			return;
		}

		var eps = vortemOverview.endpoints || {};

		// Fetch overview metrics and populate numeric cards.
		if (eps.metrics) {
			fetchApi(eps.metrics)
				.then(function (json) {
					processMetrics(json);
				})
				.catch(function () {
					var productsEl = document.getElementById('overviewMetricProducts');
					if (productsEl) {
						productsEl.textContent = '—';
						metricCardLoaded(productsEl);
					}
				});
		} else {
			var productsEl = document.getElementById('overviewMetricProducts');
			if (productsEl) {
				productsEl.textContent = '—';
				metricCardLoaded(productsEl);
			}
		}

		loadMetrics();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
