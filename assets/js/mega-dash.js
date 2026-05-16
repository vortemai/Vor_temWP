/**
 * Mega Dash - Analytics Dashboard JavaScript
 * Instance-based dashboard with charts and separated sections
 *
 * External Libraries Used:
 * - Chart.js 4.5.1 (Chart.js Contributors) - https://www.chartjs.org/ | License: MIT | Bundled locally in assets/vendor/chart.js/ | Used for bar and doughnut chart rendering
 */

(function () {
  "use strict";

  // Check if required data is available
  if (typeof window.vortemMegadashData === "undefined") {
    VortemLogger.error("Mega Dash: vortemMegadashData is not defined");
    return;
  }

  if (typeof Chart === "undefined") {
    VortemLogger.error("Mega Dash: Chart.js is not loaded");
    return;
  }

  const { ajax_url, nonce, refresh_interval, currency_symbol, currency_pos, current_language, strings } =
    window.vortemMegadashData || {};
  
  // Function to decode HTML entities
  function decodeHtmlEntities(text) {
    if (!text) return text;
    const textarea = document.createElement("textarea");
    textarea.innerHTML = text;
    return textarea.value;
  }
  
  // Decode currency symbol if it's HTML-escaped
  const decodedCurrencySymbol = decodeHtmlEntities(currency_symbol || '$');
  
  // Function to get translated string
  function getTranslatedString(key, fallback) {
    return (strings && strings[key]) ? strings[key] : fallback;
  }
  
  // Function to format number - always use English numerals
  function formatNumberForLanguage(num) {
    if (num === null || num === undefined || num === '') {
      return num;
    }
    return String(num);
  }

  /**
   * Mega Dash Dashboard Instance
   */
  class MegaDash {
    constructor() {
      this.currentData = null;
      this.previousData = null;
      this.refreshTimer = null;
      this.charts = {};

      // DOM elements
      this.elements = {
        refreshBtn: document.getElementById("megadash-refresh-btn"),
        refreshIcon: document.getElementById("megadash-refresh-icon"),
        lastUpdated: document.getElementById("megadash-last-updated"),
        exportBtn: document.getElementById("megadash-export-btn"),
        woocommerceGrid: document.getElementById("megadash-woocommerce-grid"),
        wordpressGrid: document.getElementById("megadash-wordpress-grid"),
        woocommerceCharts: document.getElementById(
          "megadash-woocommerce-charts"
        ),
        wordpressCharts: document.getElementById("megadash-wordpress-charts"),
      };

      // Card configurations by category
      this.cardConfigs = {
        woocommerce: [
          {
            key: "orders_total",
            label: getTranslatedString("total_orders", "Total Orders"),
            icon: "dashicons-cart",
            type: "orders",
            format: "number",
          },
          {
            key: "orders_today",
            label: getTranslatedString("orders_today", "Orders Today"),
            icon: "dashicons-cart",
            type: "orders",
            format: "number",
          },
          {
            key: "revenue_total",
            label: getTranslatedString("total_revenue", "Total Revenue"),
            icon: "dashicons-money-alt",
            type: "revenue",
            format: "currency",
          },
          {
            key: "revenue_today",
            label: getTranslatedString("revenue_today", "Revenue Today"),
            icon: "dashicons-money-alt",
            type: "revenue",
            format: "currency",
          },
          {
            key: "revenue_last_30d",
            label: getTranslatedString("revenue_30_days", "Revenue (30 Days)"),
            icon: "dashicons-chart-line",
            type: "revenue",
            format: "currency",
          },
          {
            key: "avg_order_value",
            label: getTranslatedString("avg_order_value", "Avg Order Value"),
            icon: "dashicons-chart-bar",
            type: "revenue",
            format: "currency",
          },
          {
            key: "low_stock_count",
            label: getTranslatedString("low_stock_items", "Low stock items less than 10 pieces"),
            icon: "dashicons-warning",
            type: "products",
            format: "number",
          },
          {
            key: "pending_orders",
            label: getTranslatedString("pending_orders", "Pending Orders"),
            icon: "dashicons-clock",
            type: "orders",
            format: "number",
          },
          {
            key: "failed_orders",
            label: getTranslatedString("failed_orders", "Failed Orders"),
            icon: "dashicons-dismiss",
            type: "orders",
            format: "number",
          },
          {
            key: "refunded_orders",
            label: getTranslatedString("refunded_orders", "On hold"),
            icon: "dashicons-undo",
            type: "orders",
            format: "number",
          },
          {
            key: "products_count",
            label: getTranslatedString("total_products", "Total Products"),
            icon: "dashicons-products",
            type: "products",
            format: "number",
          },
          {
            key: "cart_abandonment",
            label: getTranslatedString("cart_abandonment", "Cart Abandonment"),
            icon: "dashicons-saved",
            type: "orders",
            format: "number",
          },
        ],
        wordpress: [
          {
            key: "users_total",
            label: getTranslatedString("total_users", "Total Users"),
            icon: "dashicons-groups",
            type: "users",
            format: "number",
          },
          {
            key: "posts_total",
            label: getTranslatedString("total_posts", "Total Posts"),
            icon: "dashicons-admin-post",
            type: "users",
            format: "number",
          },
          {
            key: "pages_total",
            label: getTranslatedString("total_pages", "Total Pages"),
            icon: "dashicons-admin-page",
            type: "users",
            format: "number",
          },
        ],
      };

      this.init();
    }

    /**
     * Initialize the dashboard
     */
    init() {
      this.setupEventListeners();
      this.loadStaticData();

      // Set up auto-refresh if interval is defined
      if (refresh_interval && refresh_interval > 0) {
        this.refreshTimer = setInterval(() => {
          this.loadStaticData(false);
        }, refresh_interval);
      }
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
      if (this.elements.refreshBtn) {
        this.elements.refreshBtn.addEventListener("click", () =>
          this.handleRefresh()
        );
      }
      if (this.elements.exportBtn) {
        this.elements.exportBtn.addEventListener("click", (e) =>
          this.handleExport(e)
        );
      }
    }

    /**
     * Handle manual refresh
     */
    handleRefresh() {
      this.loadStaticData(true);
    }

    /**
     * Load real data from REST API
     * @param {boolean} showSpinner - Whether to show loading spinner
     */
    loadStaticData(showSpinner = false) {
      if (showSpinner && this.elements.refreshIcon) {
        this.elements.refreshIcon.classList.add("spinning");
      }

      // Fetch real data from REST API
      if (!ajax_url) {
        VortemLogger.error("Mega Dash: REST API URL not defined");
        if (showSpinner && this.elements.refreshIcon) {
          this.elements.refreshIcon.classList.remove("spinning");
        }
        return;
      }

      fetch(ajax_url, {
        method: "GET",
        headers: {
          "X-WP-Nonce": nonce || "",
        },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          // Remove comments_total from data if it exists (for backward compatibility with cached data)
          if (data.wordpress && data.wordpress.comments_total !== undefined) {
            delete data.wordpress.comments_total;
          }
          
          this.previousData = this.currentData;
          this.currentData = data;
          this.renderDashboard(data);
          this.updateLastUpdated();
          if (showSpinner && this.elements.refreshIcon) {
            this.elements.refreshIcon.classList.remove("spinning");
          }
        })
        .catch((error) => {
          VortemLogger.error("Mega Dash: Error fetching data:", error);
          this.showError(
            getTranslatedString("failed_to_load", "Failed to load analytics data. Please refresh the page.")
          );
          if (showSpinner && this.elements.refreshIcon) {
            this.elements.refreshIcon.classList.remove("spinning");
          }
        });
    }

    /**
     * Render the entire dashboard
     * @param {Object} data - Metrics data organized by category
     */
    renderDashboard(data) {
      // Remove comments_total from wordpress data if it exists (for backward compatibility with cached data)
      if (data.wordpress && data.wordpress.comments_total !== undefined) {
        delete data.wordpress.comments_total;
      }
      
      if (data.woocommerce) {
        this.renderSection("woocommerce", data.woocommerce);
        this.renderCharts("woocommerce", data.woocommerce);
      }
      if (data.wordpress) {
        this.renderSection("wordpress", data.wordpress);
        this.renderCharts("wordpress", data.wordpress);
      }
    }

    /**
     * Render a section with cards
     * @param {string} category - Section category (woocommerce/wordpress)
     * @param {Object} data - Section metrics data
     */
    renderSection(category, data) {
      const grid = this.elements[`${category}Grid`];
      if (!grid) return;

      grid.innerHTML = "";

      const configs = this.cardConfigs[category] || [];
      configs.forEach((config) => {
        // Skip comments_total if it exists in data (for backward compatibility)
        if (config.key === 'comments_total') {
          return;
        }
        const value = data[config.key];
        if (value === undefined) return;

        const card = this.createCard(config, value);
        grid.appendChild(card);
      });
    }

    /**
     * Render charts for a section
     * @param {string} category - Section category
     * @param {Object} data - Section metrics data
     */
    renderCharts(category, data) {
      const chartsContainer = this.elements[`${category}Charts`];
      if (!chartsContainer) return;

      // Remove comments_total from data if it exists (for backward compatibility with cached data)
      if (category === "wordpress" && data.comments_total !== undefined) {
        delete data.comments_total;
      }

      // Clear existing charts
      chartsContainer.innerHTML = "";

      if (category === "woocommerce") {
        // Revenue Chart
        if (
          data.revenue_total !== undefined ||
          data.revenue_today !== undefined ||
          data.revenue_last_30d !== undefined
        ) {
          this.createRevenueChart(chartsContainer, data);
        }

        // Orders Status Chart
        if (data.orders_total !== undefined) {
          this.createOrdersStatusChart(chartsContainer, data);
        }
      } else if (category === "wordpress") {
        // Users Growth Chart
        if (data.users_total !== undefined || data.users_today !== undefined) {
          this.createUsersChart(chartsContainer, data);
        }
      }
    }

    /**
     * Create revenue chart
     * @param {HTMLElement} container - Container element
     * @param {Object} data - Metrics data
     */
    createRevenueChart(container, data) {
      const chartWrapper = document.createElement("div");
      chartWrapper.className = "megadash-chart-wrapper";
      const canvas = document.createElement("canvas");
      chartWrapper.appendChild(canvas);
      container.appendChild(chartWrapper);

      const ctx = canvas.getContext("2d");
      const chartId = "revenue-chart";

      // Destroy existing chart if it exists
      if (this.charts[chartId]) {
        this.charts[chartId].destroy();
      }

      const totalRevenueLabel = getTranslatedString("total_revenue", "Total Revenue");
      const revenueTodayLabel = getTranslatedString("revenue_today", "Revenue Today");
      const revenue30DaysLabel = getTranslatedString("revenue_30_days", "Revenue (30 Days)");
      const revenueLabel = getTranslatedString("revenue", "Revenue");
      
      this.charts[chartId] = new Chart(ctx, {
        type: "bar",
        data: {
          labels: [totalRevenueLabel, revenueTodayLabel, revenue30DaysLabel],
          datasets: [
            {
              label: revenueLabel,
              data: [
                data.revenue_total || 0,
                data.revenue_today || 0,
                data.revenue_last_30d || 0,
              ],
              backgroundColor: [
                "rgba(16, 185, 129, 0.85)",
                "rgba(245, 158, 11, 0.85)",
                "rgba(139, 92, 246, 0.85)",
              ],
              borderColor: [
                "rgba(16, 185, 129, 1)",
                "rgba(245, 158, 11, 1)",
                "rgba(139, 92, 246, 1)",
              ],
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          datasets: {
            bar: {
              barPercentage: 0.5,
              categoryPercentage: 0.8,
            },
          },
          plugins: {
            legend: {
              display: false,
            },
            title: {
              display: true,
              text: getTranslatedString("revenue_overview", "Revenue Overview"),
              color:
                getComputedStyle(document.body).getPropertyValue(
                  "--mega-text"
                ) || "#1a1a1a",
              font: {
                size: 16,
                weight: "bold",
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-text-muted"
                  ) || "#6b7280",
                callback: (value) => {
                  return this.formatCurrency(value);
                },
              },
              grid: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-border"
                  ) || "#e5e7eb",
              },
            },
            x: {
              ticks: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-text-muted"
                  ) || "#6b7280",
              },
              grid: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-border"
                  ) || "#e5e7eb",
              },
            },
          },
        },
      });
    }

    /**
     * Create orders status chart
     * @param {HTMLElement} container - Container element
     * @param {Object} data - Metrics data
     */
    createOrdersStatusChart(container, data) {
      const chartWrapper = document.createElement("div");
      chartWrapper.className = "megadash-chart-wrapper";
      const canvas = document.createElement("canvas");
      chartWrapper.appendChild(canvas);
      container.appendChild(chartWrapper);

      const ctx = canvas.getContext("2d");
      const chartId = "orders-status-chart";

      // Destroy existing chart if it exists
      if (this.charts[chartId]) {
        this.charts[chartId].destroy();
      }

      const pendingLabel = getTranslatedString("pending", "Pending");
      const failedLabel = getTranslatedString("failed", "Failed");
      const refundedLabel = getTranslatedString("refunded", "On hold");
      const totalOrdersLabel = getTranslatedString("total_orders", "Total Orders");
      
      this.charts[chartId] = new Chart(ctx, {
        type: "doughnut",
        data: {
          labels: [totalOrdersLabel, pendingLabel, failedLabel, refundedLabel],
          datasets: [
            {
              data: [
                data.orders_total || 0,
                data.pending_orders || 0,
                data.failed_orders || 0,
                data.refunded_orders || 0,
              ],
              backgroundColor: [
                "rgba(16, 185, 129, 0.8)",
                "rgba(245, 158, 11, 0.8)",
                "rgba(239, 68, 68, 0.8)",
                "rgba(139, 92, 246, 0.8)",
              ],
              borderColor: [
                "rgba(16, 185, 129, 1)",
                "rgba(245, 158, 11, 1)",
                "rgba(239, 68, 68, 1)",
                "rgba(139, 92, 246, 1)",
              ],
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-text"
                  ) || "#1a1a1a",
                padding: 15,
              },
            },
            title: {
              display: true,
              text: getTranslatedString("orders_status", "Orders Status"),
              color:
                getComputedStyle(document.body).getPropertyValue(
                  "--mega-text"
                ) || "#1a1a1a",
              font: {
                size: 16,
                weight: "bold",
              },
            },
          },
        },
      });
    }

    /**
     * Create users chart
     * @param {HTMLElement} container - Container element
     * @param {Object} data - Metrics data
     */
    createUsersChart(container, data) {
      const chartWrapper = document.createElement("div");
      chartWrapper.className = "megadash-chart-wrapper";
      const canvas = document.createElement("canvas");
      chartWrapper.appendChild(canvas);
      container.appendChild(chartWrapper);

      const ctx = canvas.getContext("2d");
      const chartId = "users-chart";

      // Destroy existing chart if it exists
      if (this.charts[chartId]) {
        this.charts[chartId].destroy();
      }

      // Remove comments_total from data if it exists (for backward compatibility)
      if (data.comments_total !== undefined) {
        delete data.comments_total;
      }

      const totalUsersLabel = getTranslatedString("total_users", "Total Users");
      const totalPostsLabel = getTranslatedString("total_posts", "Total Posts");
      const totalPagesLabel = getTranslatedString("total_pages", "Total Pages");
      const wordpressMetricsLabel = getTranslatedString("wordpress_metrics", "WordPress Metrics");

      this.charts[chartId] = new Chart(ctx, {
        type: "bar",
        data: {
          labels: [
            totalUsersLabel,
            totalPostsLabel,
            totalPagesLabel,
          ],
          datasets: [
            {
              label: wordpressMetricsLabel,
              data: [
                data.users_total || 0,
                data.posts_total || 0,
                data.pages_total || 0,
              ],
              backgroundColor: [
                "rgba(16, 185, 129, 0.85)",
                "rgba(239, 68, 68, 0.85)",
                "rgba(139, 92, 246, 0.85)",
              ],
              borderColor: [
                "rgba(16, 185, 129, 1)",
                "rgba(239, 68, 68, 1)",
                "rgba(139, 92, 246, 1)",
              ],
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          datasets: {
            bar: {
              barPercentage: 0.5,
              categoryPercentage: 0.8,
            },
          },
          plugins: {
            legend: {
              display: false,
            },
            title: {
              display: true,
              text: getTranslatedString("wordpress_activity", "WordPress Activity"),
              color:
                getComputedStyle(document.body).getPropertyValue(
                  "--mega-text"
                ) || "#1a1a1a",
              font: {
                size: 16,
                weight: "bold",
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1,
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-text-muted"
                  ) || "#6b7280",
              },
              grid: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-border"
                  ) || "#e5e7eb",
              },
            },
            x: {
              ticks: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-text-muted"
                  ) || "#6b7280",
              },
              grid: {
                color:
                  getComputedStyle(document.body).getPropertyValue(
                    "--mega-border"
                  ) || "#e5e7eb",
              },
            },
          },
        },
      });
    }

    /**
     * Create a metric card element
     * @param {Object} config - Card configuration
     * @param {*} value - Card value
     * @return {HTMLElement} Card element
     */
    createCard(config, value) {
      const card = document.createElement("div");
      card.className = "megadash-card";
      card.setAttribute("data-type", config.type);

      const formattedValue = this.formatValue(value, config.format);
      const change = this.calculateChange(config.key, value);
      const changeHtml = change
        ? `<div class="megadash-card-change ${change.class}">${change.text}</div>`
        : "";

      const numericValue = this.getNumericValue(value);
      const valueElement =
        numericValue !== null
          ? `<div class="megadash-card-value megadash-count-up" data-target="${numericValue}" data-format="${config.format}">0</div>`
          : `<div class="megadash-card-value">${formattedValue}</div>`;

      card.innerHTML = `
				<div class="megadash-card-icon">
					<span class="dashicons ${config.icon}"></span>
				</div>
				<div class="megadash-card-label">${this.escapeHtml(config.label)}</div>
				${valueElement}
				${changeHtml}
			`;

      // Animate count-up if numeric
      if (numericValue !== null) {
        const valueEl = card.querySelector(".megadash-card-value");
        if (valueEl) {
          this.animateCountUp(valueEl, numericValue, config.format);
        }
      }

      return card;
    }

    /**
     * Format value based on type
     * @param {*} value - Value to format
     * @param {string} format - Format type
     * @return {string} Formatted value
     */
    formatValue(value, format) {
      if (value === null || value === undefined) {
        return "N/A";
      }

      switch (format) {
        case "currency":
          return this.formatCurrency(value);
        case "percentage":
          return this.formatPercentage(value);
        case "product":
          if (typeof value === "object" && value.title) {
            const qty = formatNumberForLanguage(value.qty || 0);
            return `${this.escapeHtml(value.title)} (${qty})`;
          }
          return "N/A";
        case "number":
        default:
          return this.formatNumber(value);
      }
    }

    /**
     * Format number with commas
     * @param {number} num - Number to format
     * @return {string} Formatted number
     */
    formatNumber(num) {
      if (typeof num !== "number") {
        return String(num);
      }
      const formatted = Math.round(num).toLocaleString();
      return formatNumberForLanguage(formatted);
    }

    /**
     * Format currency
     * @param {number} amount - Amount to format
     * @return {string} Formatted currency
     */
    formatCurrency(amount) {
      if (typeof amount !== "number") {
        return String(amount);
      }
      const formatted = Math.abs(amount).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      const formattedWithArabic = formatNumberForLanguage(formatted);
      if (currency_pos === "right") {
        return `${formattedWithArabic} ${decodedCurrencySymbol}`;
      }
      return `${decodedCurrencySymbol}${formattedWithArabic}`;
    }

    /**
     * Format percentage
     * @param {number} rate - Rate to format
     * @return {string} Formatted percentage
     */
    formatPercentage(rate) {
      if (typeof rate !== "number") {
        return formatNumberForLanguage("0") + "%";
      }
      const formatted = rate.toFixed(2);
      return `${formatNumberForLanguage(formatted)}%`;
    }

    /**
     * Get numeric value from mixed input
     * @param {*} value - Value to extract number from
     * @return {number|null} Numeric value or null
     */
    getNumericValue(value) {
      if (typeof value === "number") {
        return value;
      }
      if (typeof value === "object" && value.qty) {
        return value.qty;
      }
      return null;
    }

    /**
     * Calculate percentage change from previous data
     * @param {string} key - Metric key
     * @param {*} currentValue - Current value
     * @return {Object|null} Change object or null
     */
    calculateChange(key, currentValue) {
      if (!this.previousData) {
        return null;
      }

      // Check both categories
      const prevWoo = this.previousData.woocommerce?.[key];
      const prevWp = this.previousData.wordpress?.[key];
      const previousValue = prevWoo !== undefined ? prevWoo : prevWp;

      if (previousValue === undefined || previousValue === null) {
        return null;
      }

      const prevNum = this.getNumericValue(previousValue);
      const current = this.getNumericValue(currentValue);

      if (prevNum === null || current === null || prevNum === 0) {
        return null;
      }

      const change = ((current - prevNum) / prevNum) * 100;
      const absChange = Math.abs(change);

      if (absChange < 0.01) {
        return null;
      }

      const sign = change > 0 ? "+" : "";
      const formattedChange = formatNumberForLanguage(change.toFixed(1));
      const text = `${sign}${formattedChange}%`;
      const className = change > 0 ? "positive" : "negative";

      return {
        text: text,
        class: className,
      };
    }

    /**
     * Animate count-up effect
     * @param {HTMLElement} element - Element to animate
     * @param {number} target - Target number
     * @param {string} format - Format type
     */
    animateCountUp(element, target, format) {
      if (!element || target === null || target === 0) {
        element.textContent = this.formatValue(target, format);
        return;
      }

      if (!format) {
        format = element.getAttribute("data-format") || "number";
      }

      const duration = 1500;
      const steps = 60;
      const increment = target / steps;
      let step = 0;

      const timer = setInterval(() => {
        step++;
        const current = (target / steps) * step;

        if (step >= steps || current >= target) {
          element.textContent = this.formatValue(target, format);
          clearInterval(timer);
        } else {
          const formatted = this.formatValue(Math.floor(current), format);
          element.textContent = formatted;
        }
      }, duration / steps);
    }

    /**
     * Update last updated timestamp
     */
    updateLastUpdated() {
      if (!this.elements.lastUpdated) return;
      const now = new Date();
      const timeString = now.toLocaleTimeString();
      const lastUpdatedText = getTranslatedString("last_updated", "Last updated:");
      this.elements.lastUpdated.textContent = `${lastUpdatedText} ${formatNumberForLanguage(timeString)}`;
    }

    /**
     * Handle CSV export
     * @param {Event} e - Click event
     */
    handleExport(e) {
      e.preventDefault();
      if (!this.currentData) {
        this.showError(getTranslatedString("no_data_export", "No data available to export"));
        return;
      }

      // Convert data to CSV
      let csv = "Category,Metric,Value\n";
      for (const [category, metrics] of Object.entries(this.currentData)) {
        for (const [key, value] of Object.entries(metrics)) {
          const label = key
            .replace(/_/g, " ")
            .replace(/\b\w/g, (l) => l.toUpperCase());
          let formattedValue = value;
          if (typeof value === "object" && value.title) {
            formattedValue = `${value.title} (${value.qty || 0})`;
          } else if (typeof value === "number") {
            formattedValue = value.toLocaleString();
          }
          csv += `"${category}","${label}","${formattedValue}"\n`;
        }
      }

      // Download CSV
      const blob = new Blob([csv], { type: "text/csv" });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `mega-dash-export-${
        new Date().toISOString().split("T")[0]
      }.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
      const errorDiv = document.createElement("div");
      errorDiv.className = "notice notice-error vortem-plugin-notice";
      errorDiv.style.cssText =
        "margin: 20px 0; padding: 12px; background: #f0f0f1; border-left: 4px solid #d63638;";
      errorDiv.textContent = message;
      const container = document.getElementById("mega-dash-app");
      if (container) {
        container.insertBefore(errorDiv, container.firstChild);
        setTimeout(() => {
          errorDiv.remove();
        }, 5000);
      }
    }

    /**
     * Escape HTML
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }
  }

  // Initialize dashboard when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      window.vortemMegadashInstance = new MegaDash();
    });
  } else {
    window.vortemMegadashInstance = new MegaDash();
  }
})();
