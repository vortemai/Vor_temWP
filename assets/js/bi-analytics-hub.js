// External Library: ApexCharts 5.10.6 (ApexCharts) - https://apexcharts.com/ | License: MIT | Bundled locally in assets/vendor/apexcharts/ | Used for BI Analytics Hub chart rendering
(function() {
    'use strict';

    // Wait for config if not immediately available (for dynamic loading scenarios)
    function waitForConfig(callback, maxAttempts = 20, attempt = 0) {
        // Check if config exists and has required properties
        if (typeof biAnalyticsHubConfig !== 'undefined' && 
            biAnalyticsHubConfig.proxyUrl && 
            biAnalyticsHubConfig.strings && 
            Object.keys(biAnalyticsHubConfig.strings).length > 0) {
            callback();
            return;
        }
        
        if (attempt >= maxAttempts) {
            VortemLogger.error('BI Analytics Hub: Configuration not found or incomplete after waiting. Make sure biAnalyticsHubConfig is set with all required strings before loading this script.');
            return;
        }
        
        setTimeout(() => waitForConfig(callback, maxAttempts, attempt + 1), 100);
    }
    
    // Start initialization - wait for config if needed
    waitForConfig(() => {
        if (typeof biAnalyticsHubConfig === 'undefined') {
            VortemLogger.error('BI Analytics Hub: Configuration not found');
            return;
        }
        initializeScript();
    });
    
    function initializeScript() {
        // Container check will be done in initCharts function
        // Don't exit early here to allow manual initialization later
        const dashboardContainer = document.querySelector('.bi-analytics-hub-dashboard');
        if (!dashboardContainer) {
            VortemLogger.log('BI Analytics Hub: Dashboard container not found on script load, will initialize when container is available');
        }

    // Translation helper function
    function getTranslatedString(key, fallback) {
        if (biAnalyticsHubConfig && biAnalyticsHubConfig.strings && biAnalyticsHubConfig.strings[key]) {
            return biAnalyticsHubConfig.strings[key];
        }
        return fallback;
    }

    /**
     * Fetch dashboard data via WordPress REST API proxy
     * Uses server-side proxy to avoid CORS issues
     */
    async function fetchDashboardData(endpoint) {
        const proxyUrl = biAnalyticsHubConfig.proxyUrl || '/wp-json/vortem/v1/bi-analytics-hub/proxy/';
        const url = `${proxyUrl}${endpoint}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': biAnalyticsHubConfig.nonce || '',
                },
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to fetch ${endpoint}: ${response.statusText}`);
            }
            
            const jsonData = await response.json();
            
            // Handle API response structure: {success: true, data: {...}}
            // Extract the inner data object if it exists
            if (jsonData && typeof jsonData === 'object' && jsonData.success && jsonData.data) {
                return jsonData.data;
            }
            
            return jsonData;
        } catch (error) {
            VortemLogger.error(`Error fetching ${endpoint}:`, error);
            throw error;
        }
    }

    /**
     * Fetch suggested pricing data via WordPress REST API proxy
     * Uses server-side proxy to avoid CORS issues
     */
    async function fetchSuggestedPricing() {
        const proxyUrl = biAnalyticsHubConfig.proxyUrl || '/wp-json/vortem/v1/bi-analytics-hub/proxy/';
        const url = `${proxyUrl}suggested_pricing`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': biAnalyticsHubConfig.nonce || '',
                },
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to fetch suggested pricing: ${response.statusText}`);
            }
            
            const jsonData = await response.json();
            
            // Handle multiple possible API response structures:
            // 1. Direct array: [{...}, {...}]
            if (Array.isArray(jsonData)) {
                return jsonData;
            }
            
            // 2. {success: true, data: [...]}
            if (jsonData && typeof jsonData === 'object' && jsonData.success && jsonData.data) {
                return Array.isArray(jsonData.data) ? jsonData.data : jsonData.data;
            }
            
            // 3. {data: [...]}
            if (jsonData && typeof jsonData === 'object' && jsonData.data) {
                return Array.isArray(jsonData.data) ? jsonData.data : jsonData.data;
            }
            
            // 4. {suggested_pricing: [...]}
            if (jsonData && typeof jsonData === 'object' && jsonData.suggested_pricing) {
                return Array.isArray(jsonData.suggested_pricing) ? jsonData.suggested_pricing : jsonData.suggested_pricing;
            }
            
            // 5. {result: [...]}
            if (jsonData && typeof jsonData === 'object' && jsonData.result) {
                return Array.isArray(jsonData.result) ? jsonData.result : jsonData.result;
            }
            
            // Fallback: return as-is (might be an object or null)
            return jsonData;
        } catch (error) {
            VortemLogger.error('Error fetching suggested pricing:', error);
            throw error;
        }
    }

    // Data storage
    let chartData = {
        kpi: null,
        keywords: null,
        priceRating: null,
        sentiment: null,
        marketComparison: null,
        categoryComparison: null,
        trendIndex: null,
        trendStatus: null,
        suggestedPricing: null,
    };

    // Error state
    let errors = {
        kpi: null,
        keywords: null,
        priceRating: null,
        sentiment: null,
        marketComparison: null,
        categoryComparison: null,
        trendIndex: null,
        trendStatus: null,
        suggestedPricing: null,
    };

    // Function to clear chart data and errors (for re-initialization)
    function clearChartData() {
        chartData = {
            kpi: null,
            keywords: null,
            priceRating: null,
            sentiment: null,
            marketComparison: null,
            categoryComparison: null,
            trendIndex: null,
            trendStatus: null,
            suggestedPricing: null,
        };
        errors = {
            kpi: null,
            keywords: null,
            priceRating: null,
            sentiment: null,
            marketComparison: null,
            categoryComparison: null,
            trendIndex: null,
            trendStatus: null,
            suggestedPricing: null,
        };
    }

    // Expose clearChartData globally
    window.clearBIAnalyticsChartData = clearChartData;

    // Helper function to get color based on theme (always light mode)
    function getThemeColors() {
        return {
            text: '#1e293b',
            textSecondary: '#64748b',
            grid: '#e5e7eb',
            background: '#ffffff',
        };
    }

    /**
     * Render KPI modal table with given products
     */
    function renderKpiModalTable(modal, products, categories, totalCount = null) {
        const tableContainer = document.getElementById('kpi-view-all-table-container');
        if (!tableContainer) return;

        // Get total count from modal if not provided
        const total = totalCount !== null ? totalCount : (modal._allProducts ? modal._allProducts.length : products.length);

        let html = `
            <div class="table-container">
                <table id="kpi-view-all-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">${getTranslatedString('product', 'Product')}</th>
                            ${categories.map(c => `<th style="text-align: center;">${c.label}</th>`).join('')}
                            <th style="text-align: center;">${getTranslatedString('average', 'Average')}</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        if (products.length === 0) {
            html += `<tr><td colspan="${categories.length + 2}" style="text-align: center; padding: 2rem; color: var(--text-tertiary, #64748b);">${getTranslatedString('no_products_found', 'No products found')}</td></tr>`;
        } else {
            products.forEach(({ item, values, avg }) => {
                const productName = String(item['pb_info.title'] || item.name || 'Unnamed');
                html += `<tr data-product-name="${productName.toLowerCase()}"><td class="font-medium">${productName}</td>`;
                values.forEach(score => {
                    let badgeClass = 'badge-slate';
                    if (score >= 4) badgeClass = 'badge-emerald';
                    else if (score >= 3) badgeClass = 'badge-amber';
                    else if (score > 0) badgeClass = 'badge-sky';
                    
                    html += `<td style="text-align: center;"><span class="badge ${badgeClass}">${score.toFixed(2)}</span></td>`;
                });
                // Add average column
                let avgBadgeClass = 'badge-slate';
                if (avg >= 4) avgBadgeClass = 'badge-emerald';
                else if (avg >= 3) avgBadgeClass = 'badge-amber';
                else if (avg > 0) avgBadgeClass = 'badge-sky';
                html += `<td style="text-align: center;"><span class="badge ${avgBadgeClass}">${avg.toFixed(2)}</span></td>`;
                html += `</tr>`;
            });
        }

        html += `</tbody></table></div>`;
        tableContainer.innerHTML = html;

        // Update results count
        updateKpiResultsCount(products.length, total);
    }

    /**
     * Filter KPI modal table based on search query
     */
    function filterKpiModalTable(modal, searchQuery, allProducts, categories) {
        let filteredProducts = allProducts;

        if (searchQuery) {
            filteredProducts = allProducts.filter(({ item }) => {
                const productName = String(item['pb_info.title'] || item.name || 'Unnamed').toLowerCase();
                return productName.includes(searchQuery);
            });
        }

        // Re-render table with filtered products
        renderKpiModalTable(modal, filteredProducts, categories, allProducts.length);
    }

    /**
     * Update results count display
     */
    function updateKpiResultsCount(filtered, total) {
        const resultsCount = document.getElementById('kpi-view-all-results-count');
        if (resultsCount) {
            if (filtered === total) {
                resultsCount.textContent = `${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            } else {
                resultsCount.textContent = `${filtered} of ${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            }
        }
    }

    /**
     * Open modal to display all KPI data
     */
    function openKpiViewAllModal(data, categories) {
        // Create modal overlay if it doesn't exist
        let modal = document.getElementById('kpi-view-all-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'kpi-view-all-modal';
            modal.className = 'kpi-view-all-modal';
            modal.innerHTML = `
                <div class="kpi-view-all-modal-overlay"></div>
                <div class="kpi-view-all-modal-content">
                    <div class="kpi-view-all-modal-header">
                        <h2>${getTranslatedString('all_kpi_products', 'All KPI Products')}</h2>
                        <button class="kpi-view-all-modal-close" aria-label="${getTranslatedString('close', 'Close')}">&times;</button>
                    </div>
                    <div class="kpi-view-all-modal-body">
                        <div class="kpi-view-all-search-container">
                            <div class="kpi-view-all-search-wrapper">
                                <input 
                                    type="text" 
                                    id="kpi-view-all-search-input" 
                                    class="kpi-view-all-search-input" 
                                    placeholder="${getTranslatedString('search_products', 'Search products...')}"
                                    autocomplete="off"
                                />
                                <span class="kpi-view-all-search-icon">🔍</span>
                            </div>
                            <div id="kpi-view-all-results-count" class="kpi-view-all-results-count"></div>
                        </div>
                        <div id="kpi-view-all-table-container" class="kpi-view-all-table-container">
                            <div class="loading-skeleton">
                                <div class="skeleton-header"></div>
                                <div class="skeleton-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close modal handlers
            const overlay = modal.querySelector('.kpi-view-all-modal-overlay');
            const closeBtn = modal.querySelector('.kpi-view-all-modal-close');
            
            overlay.addEventListener('click', closeKpiViewAllModal);
            closeBtn.addEventListener('click', closeKpiViewAllModal);
            
            // Close on Escape key (use a single handler)
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeKpiViewAllModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Store handler for cleanup if needed
            modal._escapeHandler = escapeHandler;
        }

        // Calculate all products with scores (sorted by average)
        const scored = data.map(item => {
            const values = categories.map(c => Number(item[c.key] || 0));
            const avg = values.reduce((a, b) => a + b, 0) / (values.length || 1);
            return { item, values, avg };
        });
        const allProducts = scored.sort((a, b) => b.avg - a.avg);

        // Store products data in modal for search functionality
        modal._allProducts = allProducts;
        modal._categories = categories;

        // Clear search input if it exists
        const existingSearchInput = document.getElementById('kpi-view-all-search-input');
        if (existingSearchInput) {
            existingSearchInput.value = '';
        }

        // Render table with all data
        renderKpiModalTable(modal, allProducts, categories, allProducts.length);

        // Add search functionality
        const searchInput = document.getElementById('kpi-view-all-search-input');
        if (searchInput) {
            // Remove existing listeners if any (to prevent duplicates)
            const newSearchInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newSearchInput, searchInput);
            
            const freshSearchInput = document.getElementById('kpi-view-all-search-input');
            
            freshSearchInput.addEventListener('input', function(e) {
                const searchQuery = e.target.value.toLowerCase().trim();
                filterKpiModalTable(modal, searchQuery, allProducts, categories);
            });

            // Clear search on Escape (but don't close modal if search has focus)
            freshSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && freshSearchInput.value) {
                    e.stopPropagation();
                    freshSearchInput.value = '';
                    filterKpiModalTable(modal, '', allProducts, categories);
                }
            });
            
            // Focus search input
            setTimeout(() => freshSearchInput.focus(), 100);
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close KPI View All modal
     */
    function closeKpiViewAllModal() {
        const modal = document.getElementById('kpi-view-all-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    /**
     * Update Suggested Pricing results count display
     */
    function updateSuggestedPricingResultsCount(filtered, total) {
        const resultsCount = document.getElementById('suggested-pricing-view-all-results-count');
        if (resultsCount) {
            if (filtered === total) {
                resultsCount.textContent = `${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            } else {
                resultsCount.textContent = `${filtered} of ${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            }
        }
    }

    /**
     * Render Suggested Pricing modal table
     */
    function renderSuggestedPricingModalTable(modal, products, totalCount = null) {
        const tableContainer = document.getElementById('suggested-pricing-view-all-table-container');
        if (!tableContainer) return;

        // Get total count from modal if not provided
        const total = totalCount !== null ? totalCount : (modal._allProducts ? modal._allProducts.length : products.length);

        const formatCurrency = (value) => {
            if (value === null || value === undefined || isNaN(value)) return '—';
            return `$${value.toFixed(2)}`;
        };

        let html = `
            <div class="table-container">
                <table id="suggested-pricing-view-all-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">${getTranslatedString('product_id', 'Product ID')}</th>
                            <th style="width: 35%;">${getTranslatedString('product_title', 'Product Title')}</th>
                            <th style="width: 11%; text-align: center;">${getTranslatedString('base_price', 'Base Price')}</th>
                            <th style="width: 11%; text-align: center;">${getTranslatedString('low_risk_price', 'Low-Risk Price')}</th>
                            <th style="width: 11%; text-align: center;">${getTranslatedString('competitive_price', 'Competitive Price')}</th>
                            <th style="width: 11%; text-align: center;">${getTranslatedString('high_risk_price', 'High-Risk Price')}</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        if (products.length === 0) {
            html += `<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-tertiary, #64748b);">${getTranslatedString('no_products_found', 'No products found')}</td></tr>`;
        } else {
            products.forEach(item => {
                const productName = String(item.title || getTranslatedString('untitled_product', 'Untitled Product'));
                const productId = String(item.product_id || '—');
                html += `
                    <tr data-product-name="${productName.toLowerCase()}" data-product-id="${productId.toLowerCase()}">
                        <td style="font-family: monospace; font-size: 0.75rem;">${productId}</td>
                        <td class="font-medium">${productName}</td>
                        <td style="text-align: center; font-weight: 500;">${formatCurrency(item.base_price)}</td>
                        <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.low_risk_price)}</td>
                        <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.competitive_price)}</td>
                        <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.high_risk_price)}</td>
                    </tr>
                `;
            });
        }

        html += `</tbody></table></div>`;
        tableContainer.innerHTML = html;

        // Update results count
        updateSuggestedPricingResultsCount(products.length, total);
    }

    /**
     * Filter Suggested Pricing modal table
     */
    function filterSuggestedPricingModalTable(modal, searchQuery, allProducts) {
        let filteredProducts = allProducts;

        if (searchQuery) {
            filteredProducts = allProducts.filter(item => {
                const productName = String(item.title || 'Untitled Product').toLowerCase();
                const productId = String(item.product_id || '').toLowerCase();
                return productName.includes(searchQuery) || productId.includes(searchQuery);
            });
        }

        // Re-render table with filtered products
        renderSuggestedPricingModalTable(modal, filteredProducts, allProducts.length);
    }

    /**
     * Open modal to display all Suggested Pricing data
     */
    function openSuggestedPricingViewAllModal(data) {
        // Create modal overlay if it doesn't exist
        let modal = document.getElementById('suggested-pricing-view-all-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'suggested-pricing-view-all-modal';
            modal.className = 'suggested-pricing-view-all-modal';
            modal.innerHTML = `
                <div class="suggested-pricing-view-all-modal-overlay"></div>
                <div class="suggested-pricing-view-all-modal-content">
                    <div class="suggested-pricing-view-all-modal-header">
                        <h2>${getTranslatedString('all_suggested_pricing', 'All Suggested Pricing')}</h2>
                        <button class="suggested-pricing-view-all-modal-close" aria-label="${getTranslatedString('close', 'Close')}">&times;</button>
                    </div>
                    <div class="suggested-pricing-view-all-modal-body">
                        <div class="suggested-pricing-view-all-search-container">
                            <div class="suggested-pricing-view-all-search-wrapper">
                                <input 
                                    type="text" 
                                    id="suggested-pricing-view-all-search-input" 
                                    class="suggested-pricing-view-all-search-input" 
                                    placeholder="${getTranslatedString('search_products', 'Search products...')}"
                                    autocomplete="off"
                                />
                                <span class="suggested-pricing-view-all-search-icon">🔍</span>
                            </div>
                            <div id="suggested-pricing-view-all-results-count" class="suggested-pricing-view-all-results-count"></div>
                        </div>
                        <div id="suggested-pricing-view-all-table-container" class="suggested-pricing-view-all-table-container">
                            <div class="loading-skeleton">
                                <div class="skeleton-header"></div>
                                <div class="skeleton-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close modal handlers
            const overlay = modal.querySelector('.suggested-pricing-view-all-modal-overlay');
            const closeBtn = modal.querySelector('.suggested-pricing-view-all-modal-close');
            
            overlay.addEventListener('click', closeSuggestedPricingViewAllModal);
            closeBtn.addEventListener('click', closeSuggestedPricingViewAllModal);
            
            // Close on Escape key
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeSuggestedPricingViewAllModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Store handler for cleanup if needed
            modal._escapeHandler = escapeHandler;
        }

        // Store all products data in modal for search functionality
        modal._allProducts = data;

        // Clear search input if it exists
        const existingSearchInput = document.getElementById('suggested-pricing-view-all-search-input');
        if (existingSearchInput) {
            existingSearchInput.value = '';
        }

        // Render table with all data
        renderSuggestedPricingModalTable(modal, data, data.length);

        // Add search functionality
        const searchInput = document.getElementById('suggested-pricing-view-all-search-input');
        if (searchInput) {
            // Remove existing listeners if any (to prevent duplicates)
            const newSearchInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newSearchInput, searchInput);
            
            const freshSearchInput = document.getElementById('suggested-pricing-view-all-search-input');
            
            freshSearchInput.addEventListener('input', function(e) {
                const searchQuery = e.target.value.toLowerCase().trim();
                filterSuggestedPricingModalTable(modal, searchQuery, data);
            });

            // Clear search on Escape (but don't close modal if search has focus)
            freshSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && freshSearchInput.value) {
                    e.stopPropagation();
                    freshSearchInput.value = '';
                    filterSuggestedPricingModalTable(modal, '', data);
                }
            });
            
            // Focus search input
            setTimeout(() => freshSearchInput.focus(), 100);
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close Suggested Pricing View All modal
     */
    function closeSuggestedPricingViewAllModal() {
        const modal = document.getElementById('suggested-pricing-view-all-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // KPI Radar Chart (Table View)
    async function renderKpiRadar() {
        const container = document.getElementById('kpi-radar-container');
        if (!container) return;

        // Show loading state
        if (!chartData.kpi && !errors.kpi) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('kpi');
                chartData.kpi = {
                    title: response.title || 'KPI Performance',
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.kpi = error.message;
                container.innerHTML = `<div class="error-message">Failed to load KPI data: ${error.message}</div>`;
                return;
            }
        }

        if (errors.kpi) {
            container.innerHTML = `<div class="error-message">Failed to load KPI data: ${errors.kpi}</div>`;
            return;
        }

        if (!chartData.kpi || !chartData.kpi.data || chartData.kpi.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.kpi.data;
        // Map API field names to display names
        const categories = [
            { key: 'product_selection_metrics.FPSI', label: getTranslatedString('fpsi', 'FPSI') },
            { key: 'product_selection_metrics.TI', label: getTranslatedString('trend_index', 'Trend Index') },
            { key: 'product_selection_metrics.PI', label: getTranslatedString('profitability', 'Profitability') },
            { key: 'product_selection_metrics.CA', label: getTranslatedString('competition', 'Competition') },
            { key: 'product_selection_metrics.DSI', label: getTranslatedString('demand_stability', 'Demand Stability') },
        ];
        
        // Calculate top 8 products by average score
        const scored = data.map(item => {
            const values = categories.map(c => Number(item[c.key] || 0));
            const avg = values.reduce((a, b) => a + b, 0) / (values.length || 1);
            return { item, values, avg };
        });
        const topProducts = scored.sort((a, b) => b.avg - a.avg).slice(0, 8);

        let html = `
            <div class="kpi-chart-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="chart-title" style="margin-bottom: 0;">${getTranslatedString('top_kpi_products', 'Top KPI Products')}</div>
                <button id="kpi-view-all-btn" class="kpi-view-all-btn">
                    ${getTranslatedString('view_all', 'View All')}
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>${getTranslatedString('product', 'Product')}</th>
                            ${categories.map(c => `<th>${c.label}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
        `;

        topProducts.forEach(({ item, values }) => {
            const productName = String(item['pb_info.title'] || item.name || 'Unnamed');
            html += `<tr><td class="font-medium">${productName}</td>`;
            values.forEach(score => {
                let badgeClass = 'badge-slate';
                if (score >= 4) badgeClass = 'badge-emerald';
                else if (score >= 3) badgeClass = 'badge-amber';
                else if (score > 0) badgeClass = 'badge-sky';
                
                html += `<td><span class="badge ${badgeClass}">${score.toFixed(2)}</span></td>`;
            });
            html += `</tr>`;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;

        // Add event listener for View All button
        const viewAllBtn = document.getElementById('kpi-view-all-btn');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', () => {
                openKpiViewAllModal(data, categories);
            });
        }
    }

    // Keywords Performance Chart
    async function renderKeywordsPerformance() {
        const container = document.getElementById('keywords-performance-container');
        if (!container) return;

        // Show loading state
        if (!chartData.keywords && !errors.keywords) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('keywords-performance');
                chartData.keywords = {
                    title: response.title || 'Keywords Performance',
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.keywords = error.message;
                container.innerHTML = `<div class="error-message">Failed to load keywords data: ${error.message}</div>`;
                return;
            }
        }

        if (errors.keywords) {
            container.innerHTML = `<div class="error-message">Failed to load keywords data: ${errors.keywords}</div>`;
            return;
        }

        if (!chartData.keywords || !chartData.keywords.data || chartData.keywords.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.keywords.data;
        const colors = getThemeColors();

        const scatterData = data.map(item => ({
            x: Number(item['CTR']) || 0,
            y: Number(item['CVR']) || 0,
            keyword: String(item['Keyword'] || item['Searched Keyword'] || ''),
            rank: Number(item['Rank']) || 0,
            impressions: Number(item['Impressions']) || 0,
            likes: Number(item['Likes']) || 0,
            comments: Number(item['Comments']) || 0,
            shares: Number(item['Shares']) || 0,
            popularity: Number(item['Popularity']) || 0,
            popularityChange: Number(item['Popularity Change']) || 0,
        }));

        const ctrValues = data.map(d => Number(d['CTR']) || 0);
        const cvrValues = data.map(d => Number(d['CVR']) || 0);
        const ctrMin = Math.min(...ctrValues);
        const ctrMax = Math.max(...ctrValues);
        const cvrMin = Math.min(...cvrValues);
        const cvrMax = Math.max(...cvrValues);
        const pad = (max) => (max === 0 ? 1 : max * 0.1);

        const options = {
            chart: {
                type: 'scatter',
                height: 380,
                zoom: { enabled: true, type: 'xy', autoScaleYaxis: true },
                toolbar: { show: true },
            },
            theme: { mode: 'light' },
            xaxis: {
                title: { text: `${getTranslatedString('ctr', 'CTR')} (%)` },
                labels: { formatter: (val) => `${Number(val).toFixed(2)}%` },
                min: Math.max(0, ctrMin - pad(ctrMax)),
                max: ctrMax + pad(ctrMax),
                tickAmount: 6,
            },
            yaxis: {
                title: { text: `${getTranslatedString('cvr', 'CVR')} (%)` },
                labels: { formatter: (val) => `${Number(val).toFixed(2)}%` },
                min: Math.max(0, cvrMin - pad(cvrMax)),
                max: cvrMax + pad(cvrMax),
            },
            markers: {
                size: 6,
                strokeWidth: 1,
                strokeColors: '#fff',
            },
            grid: {
                borderColor: colors.grid,
                strokeDashArray: 4,
            },
            tooltip: {
                shared: false,
                intersect: true,
                custom: function({ seriesIndex, dataPointIndex, w }) {
                    const point = w.config.series[seriesIndex].data[dataPointIndex];
                    return `
                        <div class="px-3 py-2" style="background: ${colors.background}; border: 1px solid ${colors.grid}; border-radius: 0.5rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem; color: ${colors.text};">${point.keyword} ${point.rank ? `#${point.rank}` : ''}</div>
                            <div style="font-size: 0.75rem; color: ${colors.textSecondary};">
                                <div>${getTranslatedString('ctr', 'CTR')}: ${Number(point.x).toFixed(2)}% | ${getTranslatedString('cvr', 'CVR')}: ${Number(point.y).toFixed(2)}%</div>
                                <div>Impressions: ${point.impressions?.toLocaleString() || point.impressions}</div>
                                <div>Popularity: ${point.popularity} (${point.popularityChange >= 0 ? '+' : ''}${Number(point.popularityChange).toFixed(2)}%)</div>
                                <div>Likes: ${point.likes} • Comments: ${point.comments} • Shares: ${point.shares}</div>
                            </div>
                        </div>
                    `;
                },
            },
            legend: { show: false },
            dataLabels: { enabled: false },
        };

        const series = [{ name: 'Keywords', data: scatterData }];

        container.innerHTML = '';
        new ApexCharts(container, { ...options, series }).render();
    }

    // Price Rating Bubble Chart
    async function renderPriceRating() {
        const container = document.getElementById('price-rating-container');
        if (!container) return;

        // Show loading state
        if (!chartData.priceRating && !errors.priceRating) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('price-rating');
                chartData.priceRating = {
                    title: getTranslatedString('price_vs_rating', 'Price vs Rating'),
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.priceRating = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('price_rating_data', 'price rating data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.priceRating) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('price_rating_data', 'price rating data')}: ${errors.priceRating}</div>`;
            return;
        }

        if (!chartData.priceRating || !chartData.priceRating.data || chartData.priceRating.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.priceRating.data;
        const colors = getThemeColors();

        const prices = data.map(d => Number(d.price) || 0);
        const ratings = data.map(d => Number(d.rating) || 0);
        const reviewCounts = data.map(d => Number(d.review_count) || 0);
        const priceMin = Math.min(...prices);
        const priceMax = Math.max(...prices);
        const ratingMin = Math.min(...ratings);
        const ratingMax = Math.max(...ratings);
        const minReviews = Math.min(...reviewCounts);
        const maxReviews = Math.max(...reviewCounts);
        const sizeRange = maxReviews - minReviews || 1;

        const bubbleData = data.map(item => ({
            x: Number(item.price) || 0,
            y: Number(item.rating) || 0,
            z: Number(item.review_count) || 0,
            title: String(item.title || ''),
        }));

        const markerSizes = reviewCounts.map(count => {
            if (minReviews === maxReviews) return 30;
            return 10 + ((count - minReviews) / sizeRange) * 40;
        });

        const options = {
            chart: {
                type: 'scatter',
                height: 450,
                zoom: { enabled: true, type: 'xy', autoScaleYaxis: true },
                toolbar: { show: true },
            },
            theme: { mode: 'light' },
            title: {
                text: chartData.priceRating.title,
                align: 'left',
                style: { fontSize: '18px', fontWeight: '600', color: colors.text },
            },
            xaxis: {
                title: { text: `${getTranslatedString('price', 'Price')} ($)`, style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
                labels: { formatter: (val) => `$${Number(val).toFixed(2)}`, style: { colors: colors.textSecondary } },
                min: Math.max(0, priceMin - priceMax * 0.1),
                max: priceMax + priceMax * 0.1,
                tickAmount: 8,
            },
            yaxis: {
                title: { text: getTranslatedString('rating', 'Rating'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
                labels: { formatter: (val) => Number(val).toFixed(1), style: { colors: colors.textSecondary } },
                min: Math.max(0, ratingMin - ratingMax * 0.1),
                max: Math.min(5, ratingMax + ratingMax * 0.1),
                tickAmount: 6,
            },
            markers: {
                size: markerSizes,
                strokeWidth: 2,
                strokeColors: '#fff',
                hover: { size: undefined, sizeOffset: 3 },
            },
            colors: ['#3b82f6'],
            fill: { opacity: 0.7 },
            grid: { borderColor: colors.grid, strokeDashArray: 4 },
            tooltip: {
                shared: false,
                intersect: true,
                custom: function({ seriesIndex, dataPointIndex, w }) {
                    const point = w.config.series[seriesIndex].data[dataPointIndex];
                    const truncatedTitle = point.title.length > 60 ? `${point.title.substring(0, 60)}...` : point.title;
                    return `
                        <div class="px-3 py-2" style="background: ${colors.background}; border: 1px solid ${colors.grid}; border-radius: 0.5rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem; color: ${colors.text};">${truncatedTitle}</div>
                            <div style="font-size: 0.75rem; color: ${colors.textSecondary};">
                                <div>${getTranslatedString('price', 'Price')}: <span style="font-weight: 500;">$${Number(point.x).toFixed(2)}</span></div>
                                <div>${getTranslatedString('rating', 'Rating')}: <span style="font-weight: 500;">${Number(point.y).toFixed(1)}</span></div>
                                <div>${getTranslatedString('reviews', 'Reviews')}: <span style="font-weight: 500;">${point.z?.toLocaleString() || point.z}</span></div>
                            </div>
                        </div>
                    `;
                },
            },
            legend: { show: false },
            dataLabels: { enabled: false },
        };

        const series = [{ name: 'Products', data: bubbleData }];

        container.innerHTML = '';
        new ApexCharts(container, { ...options, series }).render();
    }

    // Overall Sentiment Chart
    async function renderCustomerSentiment() {
        const container = document.getElementById('customer-sentiment-container');
        if (!container) return;

        // Show loading state
        if (!chartData.sentiment && !errors.sentiment) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('sentiment');
                chartData.sentiment = {
                    title: getTranslatedString('overall_sentiment', 'Overall Sentiment'),
                    data: response.data || { positive: 0, neutral: 0, negative: 0 }
                };
            } catch (error) {
                errors.sentiment = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('sentiment_data', 'sentiment data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.sentiment) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('sentiment_data', 'sentiment data')}: ${errors.sentiment}</div>`;
            return;
        }

        if (!chartData.sentiment || !chartData.sentiment.data) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.sentiment.data;
        const colors = getThemeColors();

        const options = {
            chart: {
                type: 'pie',
                height: 400,
                toolbar: { show: true, tools: { download: true } },
            },
            title: {
                text: chartData.sentiment.title,
                align: 'left',
                style: { fontSize: '16px', fontWeight: '600', color: colors.text },
            },
            labels: [
                getTranslatedString('positive', 'Positive'),
                getTranslatedString('neutral', 'Neutral'),
                getTranslatedString('negative', 'Negative')
            ],
            colors: ['#10b981', '#f59e0b', '#ef4444'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.5,
                    gradientToColors: ['#34d399', '#fbbf24', '#f87171'],
                    inverseColors: false,
                    opacityFrom: 0.8,
                    opacityTo: 0.6,
                    stops: [0, 100],
                },
            },
            stroke: { width: 2, colors: ['#fff'] },
            plotOptions: {
                pie: {
                    expandOnClick: true,
                    dataLabels: { offset: 0, minAngleToShowLabel: 10 },
                },
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => val.toFixed(1) + '%',
                style: { fontSize: '14px', fontWeight: '600', colors: [colors.text] },
                dropShadow: { enabled: true, color: '#fff', blur: 3, opacity: 0.5 },
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '14px',
                fontWeight: 500,
                labels: { colors: colors.textSecondary },
                markers: { width: 12, height: 12, radius: 6, strokeWidth: 0 },
                itemMargin: { horizontal: 10, vertical: 5 },
            },
            tooltip: {
                enabled: true,
                y: { formatter: (val) => val.toFixed(2) + '%' },
                theme: 'light',
            },
        };

        const series = [data.positive, data.neutral, data.negative];

        container.innerHTML = '';
        const chart = new ApexCharts(container, { ...options, series });
        chart.render();

        // Add summary stats below chart
        setTimeout(() => {
            const summary = document.createElement('div');
            summary.className = 'mt-4 flex justify-center space-x-6 flex-wrap gap-4';
            summary.innerHTML = `
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #059669;">${data.positive.toFixed(1)}%</div>
                    <div style="font-size: 0.75rem; color: #047857;">${getTranslatedString('positive', 'Positive')}</div>
                </div>
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #d97706;">${data.neutral.toFixed(1)}%</div>
                    <div style="font-size: 0.75rem; color: #b45309;">${getTranslatedString('neutral', 'Neutral')}</div>
                </div>
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #dc2626;">${data.negative.toFixed(1)}%</div>
                    <div style="font-size: 0.75rem; color: #b91c1c;">${getTranslatedString('negative', 'Negative')}</div>
                </div>
            `;
            container.appendChild(summary);
        }, 100);
    }

    // Market Comparison Chart
    async function renderMarketComparison() {
        const container = document.getElementById('market-comparison-container');
        if (!container) return;

        // Show loading state
        if (!chartData.marketComparison && !errors.marketComparison) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('market-comparison');
                chartData.marketComparison = {
                    title: getTranslatedString('market_comparison_by_source', 'Market Comparison (by Source)'),
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.marketComparison = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('market_comparison_data', 'market comparison data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.marketComparison) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('market_comparison_data', 'market comparison data')}: ${errors.marketComparison}</div>`;
            return;
        }

        if (!chartData.marketComparison || !chartData.marketComparison.data || chartData.marketComparison.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.marketComparison.data;
        const colors = getThemeColors();

        // Translate market category names
        const translateMarketName = (marketName) => {
            const name = String(marketName || 'Unknown').replace(/_/g, ' ').toLowerCase();
            if (name === 'best seller') return getTranslatedString('best_seller', 'Best seller');
            if (name === 'movers and shakers') return getTranslatedString('movers_and_shakers', 'Movers and shakers');
            if (name === 'new releases') return getTranslatedString('new_releases', 'New releases');
            return String(marketName || 'Unknown').replace(/_/g, ' ');
        };
        const categories = data.map(item => translateMarketName(item.market));
        const maxPrice = Math.max(...data.map(item => Number(item.avg_price) || 0)) * 1.2;
        const maxReviews = Math.max(...data.map(item => Number(item.total_reviews) || 0)) * 1.2;

        const options = {
            chart: {
                type: 'bar',
                height: 450,
                toolbar: { show: true, tools: { download: true } },
            },
            theme: { mode: 'light' },
            title: {
                text: chartData.marketComparison.title,
                align: 'left',
                style: { fontSize: '18px', fontWeight: '600', color: colors.text },
            },
            xaxis: {
                categories: categories,
                title: { text: getTranslatedString('market', 'Market'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
                labels: { style: { colors: colors.textSecondary } },
            },
            yaxis: [
                {
                    title: { text: `${getTranslatedString('average_price', 'Average Price')} ($)`, style: { fontSize: '12px', fontWeight: '600', color: '#3b82f6' } },
                    min: 0,
                    max: maxPrice,
                    labels: { formatter: (val) => `$${val.toFixed(2)}`, style: { colors: colors.textSecondary } },
                },
                {
                    opposite: true,
                    title: { text: getTranslatedString('average_rating', 'Average Rating'), style: { fontSize: '12px', fontWeight: '600', color: '#10b981' } },
                    min: 0,
                    max: 5,
                    labels: { formatter: (val) => val.toFixed(1), style: { colors: colors.textSecondary } },
                },
                {
                    opposite: true,
                    title: { text: getTranslatedString('total_reviews', 'Total Reviews'), style: { fontSize: '12px', fontWeight: '600', color: '#f59e0b' } },
                    min: 0,
                    max: maxReviews,
                    labels: {
                        formatter: (val) => {
                            if (val >= 1000000) return `${(val / 1000000).toFixed(1)}M`;
                            if (val >= 1000) return `${(val / 1000).toFixed(1)}K`;
                            return val.toFixed(0);
                        },
                        style: { colors: colors.textSecondary },
                    },
                },
            ],
            colors: ['#3b82f6', '#10b981', '#f59e0b'],
            fill: { opacity: 0.8 },
            stroke: { width: 1, colors: ['#fff'] },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '70%',
                    borderRadius: 4,
                    borderRadiusApplication: 'end',
                },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '14px',
                labels: { colors: colors.textSecondary },
            },
            tooltip: {
                shared: true,
                intersect: false,
                custom: function({ dataPointIndex, w }) {
                    const marketData = data[dataPointIndex];
                    if (!marketData) return '';
                    const marketName = translateMarketName(marketData.market);
                    const avgPrice = (Number(marketData.avg_price) || 0).toFixed(2);
                    const avgRating = (Number(marketData.avg_rating) || 0).toFixed(2);
                    const totalReviews = (Number(marketData.total_reviews) || 0).toLocaleString();
                    const count = Number(marketData.count) || 0;
                    return `
                        <div class="px-3 py-2" style="background: ${colors.background}; border: 1px solid ${colors.grid}; border-radius: 0.5rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem; color: ${colors.text};">${marketName}</div>
                            <div style="font-size: 0.75rem; color: ${colors.textSecondary};">
                                <div><span style="display: inline-block; width: 12px; height: 12px; background: #3b82f6; border-radius: 2px; margin-right: 0.5rem;"></span>${getTranslatedString('average_price', 'Average Price')}: <span style="font-weight: 500;">$${avgPrice}</span></div>
                                <div><span style="display: inline-block; width: 12px; height: 12px; background: #10b981; border-radius: 2px; margin-right: 0.5rem;"></span>${getTranslatedString('average_rating', 'Average Rating')}: <span style="font-weight: 500;">${avgRating}</span></div>
                                <div><span style="display: inline-block; width: 12px; height: 12px; background: #f59e0b; border-radius: 2px; margin-right: 0.5rem;"></span>${getTranslatedString('total_reviews', 'Total Reviews')}: <span style="font-weight: 500;">${totalReviews}</span></div>
                                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid ${colors.grid};">${getTranslatedString('product_count', 'Product Count')}: <span style="font-weight: 500;">${count}</span></div>
                            </div>
                        </div>
                    `;
                },
            },
            grid: { borderColor: colors.grid, strokeDashArray: 4 },
        };

        const series = [
            { name: `${getTranslatedString('average_price', 'Average Price')} ($)`, type: 'column', data: data.map(item => Number(item.avg_price) || 0) },
            { name: getTranslatedString('average_rating', 'Average Rating'), type: 'column', data: data.map(item => Number(item.avg_rating) || 0) },
            { name: getTranslatedString('total_reviews', 'Total Reviews'), type: 'column', data: data.map(item => Number(item.total_reviews) || 0) },
        ];

        container.innerHTML = '';
        new ApexCharts(container, { ...options, series }).render();
    }

    // Category Comparison Chart (Heatmap)
    async function renderCategoryComparison() {
        const container = document.getElementById('category-comparison-container');
        if (!container) return;

        // Show loading state
        if (!chartData.categoryComparison && !errors.categoryComparison) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('category-comparison');
                chartData.categoryComparison = {
                    title: getTranslatedString('category_comparison', 'Category Comparison'),
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.categoryComparison = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('category_comparison_data', 'category comparison data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.categoryComparison) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('category_comparison_data', 'category comparison data')}: ${errors.categoryComparison}</div>`;
            return;
        }

        if (!chartData.categoryComparison || !chartData.categoryComparison.data || chartData.categoryComparison.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const allData = chartData.categoryComparison.data;
        const colors = getThemeColors();

        // Limit to 6 categories for display
        const limitedData = allData.slice(0, 6);
        // Translate category names
        const translateCategoryName = (categoryName) => {
            const name = String(categoryName || 'Unknown').trim();
            // Common category translations
            const categoryMap = {
                'Amazon Device': getTranslatedString('category_amazon_device', 'Amazon Device'),
                'Appliances': getTranslatedString('category_appliances', 'Appliances'),
                'Arts, Crafts & Sewing': getTranslatedString('category_arts_crafts', 'Arts, Crafts & Sewing'),
                'Automotive': getTranslatedString('category_automotive', 'Automotive'),
                'Baby Products': getTranslatedString('category_baby_products', 'Baby Products'),
                'Baby': getTranslatedString('category_baby', 'Baby'),
                'Beauty & Personal Care': getTranslatedString('category_beauty', 'Beauty & Personal Care'),
                'Cell Phones & Accessories': getTranslatedString('category_cell_phones', 'Cell Phones & Accessories'),
                'Clothing, Shoes & Jewelry': getTranslatedString('category_clothing_shoes', 'Clothing, Shoes & Jewelry'),
                'Collectibles & Fine Art': getTranslatedString('category_collectibles', 'Collectibles & Fine Art'),
                'Electronics': getTranslatedString('category_electronics', 'Electronics'),
                'Health & Household': getTranslatedString('category_health_household', 'Health & Household'),
                'Home & Kitchen': getTranslatedString('category_home_kitchen', 'Home & Kitchen'),
                'Industrial & Scientific': getTranslatedString('category_industrial', 'Industrial & Scientific'),
                'Musical Instruments': getTranslatedString('category_musical_instruments', 'Musical Instruments'),
                'No category found': getTranslatedString('category_no_category', 'No category found'),
                'Office Products': getTranslatedString('category_office_products', 'Office Products'),
                'Patio, Lawn & Garden': getTranslatedString('category_patio_lawn', 'Patio, Lawn & Garden'),
                'Safety & Security': getTranslatedString('category_safety_security', 'Safety & Security'),
                'Tools & Home Improvement': getTranslatedString('category_tools_home', 'Tools & Home Improvement'),
                'Toys & Games': getTranslatedString('category_toys_games', 'Toys & Games'),
            };
            // Check for exact match first
            if (categoryMap[name]) return categoryMap[name];
            // Check for partial matches (for truncated names)
            for (const [key, translation] of Object.entries(categoryMap)) {
                if (name.startsWith(key) || key.startsWith(name)) {
                    return translation;
                }
            }
            return name; // Return original if no translation found
        };
        const categories = limitedData.map(item => translateCategoryName(item.category));

        const metrics = [
            { key: 'avg_price', label: `${getTranslatedString('avg_price', 'Avg Price')} ($)`, formatter: (val) => val !== null ? `$${val.toFixed(2)}` : getTranslatedString('na', 'N/A') },
            { key: 'avg_rating', label: getTranslatedString('avg_rating', 'Avg Rating'), formatter: (val) => val !== null ? val.toFixed(2) : getTranslatedString('na', 'N/A') },
            { key: 'avg_reviews', label: getTranslatedString('avg_reviews', 'Avg Reviews'), formatter: (val) => val !== null ? val.toLocaleString() : getTranslatedString('na', 'N/A') },
            { key: 'avg_sold', label: getTranslatedString('avg_sold', 'Avg Sold'), formatter: (val) => val !== null ? val.toLocaleString() : getTranslatedString('na', 'N/A') },
            { key: 'avg_fpsi', label: getTranslatedString('avg_fpsi', 'Avg FPSI'), formatter: (val) => val !== null ? val.toFixed(2) : getTranslatedString('na', 'N/A') },
        ];

        const series = metrics.map(metric => {
            const metricData = categories.map(category => {
                // Find item by matching translated category name
                const item = limitedData.find(d => translateCategoryName(d.category) === category);
                if (!item) return { x: category, y: -1000000, originalValue: null, formatter: metric.formatter };
                const value = item[metric.key];
                if (value === null || (typeof value === 'number' && isNaN(value))) {
                    return { x: category, y: -1000000, originalValue: null, formatter: metric.formatter };
                }
                return {
                    x: category,
                    y: typeof value === 'number' ? value : 0,
                    originalValue: value,
                    formatter: metric.formatter,
                };
            });
            return { name: metric.label, data: metricData };
        });

        // Create header with View All button
        const headerHtml = `
            <div class="category-comparison-chart-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="chart-title" style="margin-bottom: 0;">${chartData.categoryComparison.title}</div>
                ${allData.length > 6 ? `<button id="category-comparison-view-all-btn" class="category-comparison-view-all-btn">${getTranslatedString('view_all', 'View All')}</button>` : ''}
            </div>
        `;

        const chartContainer = document.createElement('div');
        chartContainer.id = 'category-comparison-chart-wrapper';
        chartContainer.style.width = '100%';

        const options = {
            chart: {
                type: 'heatmap',
                height: Math.max(400, metrics.length * 50 + categories.length * 30 + 200),
                toolbar: { show: true, tools: { download: true } },
            },
            theme: { mode: 'light' },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.7,
                    radius: 4,
                    useFillColorAsStroke: false,
                    strokeWidth: 3,
                    stroke: '#ffffff',
                    colorScale: {
                        ranges: [
                            { from: -1000000, to: -1, name: getTranslatedString('na', 'N/A'), color: '#e2e8f0' },
                            { from: 0, to: Infinity, name: getTranslatedString('value', 'value'), color: '#3b82f6' },
                        ],
                    },
                },
            },
            dataLabels: {
                enabled: true,
                style: { fontSize: '10px', fontWeight: '500', colors: [colors.text] },
                formatter: function(val, opts) {
                    if (val < 0) return getTranslatedString('na', 'N/A');
                    try {
                        const pointData = opts.w.config.series[opts.seriesIndex]?.data[opts.dataPointIndex];
                        if (pointData && pointData.formatter && pointData.originalValue !== undefined) {
                            return pointData.formatter(pointData.originalValue);
                        }
                    } catch (e) {}
                    if (val < 0) return getTranslatedString('na', 'N/A');
                    if (val >= 1000) return `${(val / 1000).toFixed(1)}K`;
                    return val.toFixed(2);
                },
            },
            xaxis: {
                categories: categories,
                labels: { style: { colors: colors.textSecondary, fontSize: '11px' }, rotate: -45, rotateAlways: true },
                title: { text: getTranslatedString('categories', 'Categories'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
            },
            yaxis: {
                categories: metrics.map(m => m.label),
                labels: { style: { colors: colors.textSecondary, fontSize: '11px' } },
                title: { text: getTranslatedString('metrics', 'Metrics'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
            },
            colors: ['#3b82f6'],
            grid: { borderColor: colors.grid, strokeDashArray: 4 },
            tooltip: {
                custom: function({ seriesIndex, dataPointIndex, w }) {
                    const categoryList = w.config.xaxis.categories || categories;
                    const category = categoryList[dataPointIndex];
                    const metric = metrics[seriesIndex];
                    if (!metric || !category) return '';
                    // Find original category name for data lookup
                    const originalCategory = limitedData.find(item => translateCategoryName(item.category) === category)?.category || category;
                    const categoryData = limitedData.find(item => translateCategoryName(item.category) === category);
                    if (!categoryData) return '';
                    const value = categoryData[metric.key];
                    const displayValue = metric.formatter(value);
                    const isNA = value === null || (typeof value === 'number' && isNaN(value));
                    
                    return `
                        <div class="category-comparison-tooltip" style="
                            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                            border: 1px solid #e2e8f0;
                            border-left: 4px solid ${isNA ? '#94a3b8' : '#3b82f6'};
                            border-radius: 0.75rem;
                            padding: 1rem 1.25rem;
                            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                            min-width: 200px;
                            backdrop-filter: blur(8px);
                        ">
                            <div style="
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                margin-bottom: 0.75rem;
                                padding-bottom: 0.75rem;
                                border-bottom: 1px solid #e2e8f0;
                            ">
                                <div style="
                                    width: 8px;
                                    height: 8px;
                                    border-radius: 50%;
                                    background: ${isNA ? '#94a3b8' : '#3b82f6'};
                                    flex-shrink: 0;
                                "></div>
                                <div style="
                                    font-weight: 700;
                                    font-size: 0.875rem;
                                    color: #0f172a;
                                    letter-spacing: 0.025em;
                                    text-transform: uppercase;
                                ">${category || 'Unknown'}</div>
                            </div>
                            <div style="
                                display: flex;
                                flex-direction: column;
                                gap: 0.5rem;
                            ">
                                <div style="
                                    font-size: 0.75rem;
                                    color: #64748b;
                                    font-weight: 500;
                                    margin-bottom: 0.25rem;
                                ">${metric.label}</div>
                                <div style="
                                    font-size: 1.25rem;
                                    font-weight: 700;
                                    color: ${isNA ? '#64748b' : '#1e40af'};
                                    line-height: 1.2;
                                ">${displayValue}</div>
                            </div>
                        </div>
                    `;
                },
            },
        };

        container.innerHTML = headerHtml;
        container.appendChild(chartContainer);
        new ApexCharts(chartContainer, { ...options, series }).render();

        // Add event listener for View All button
        if (allData.length > 6) {
            const viewAllBtn = document.getElementById('category-comparison-view-all-btn');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', () => {
                    openCategoryComparisonViewAllModal(allData, metrics);
                });
            }
        }
    }

    /**
     * Render Category Comparison full chart in modal
     */
    function renderCategoryComparisonModalChart(modal, data, metrics) {
        const chartContainer = document.getElementById('category-comparison-view-all-chart-container');
        if (!chartContainer) return;

        if (!data || data.length === 0) {
            chartContainer.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        // Translate category names (same function as in main chart)
        const translateCategoryName = (categoryName) => {
            const name = String(categoryName || 'Unknown').trim();
            const categoryMap = {
                'Amazon Device': getTranslatedString('category_amazon_device', 'Amazon Device'),
                'Appliances': getTranslatedString('category_appliances', 'Appliances'),
                'Arts, Crafts & Sewing': getTranslatedString('category_arts_crafts', 'Arts, Crafts & Sewing'),
                'Automotive': getTranslatedString('category_automotive', 'Automotive'),
                'Baby Products': getTranslatedString('category_baby_products', 'Baby Products'),
                'Baby': getTranslatedString('category_baby', 'Baby'),
                'Beauty & Personal Care': getTranslatedString('category_beauty', 'Beauty & Personal Care'),
                'Cell Phones & Accessories': getTranslatedString('category_cell_phones', 'Cell Phones & Accessories'),
                'Clothing, Shoes & Jewelry': getTranslatedString('category_clothing_shoes', 'Clothing, Shoes & Jewelry'),
                'Collectibles & Fine Art': getTranslatedString('category_collectibles', 'Collectibles & Fine Art'),
                'Electronics': getTranslatedString('category_electronics', 'Electronics'),
                'Health & Household': getTranslatedString('category_health_household', 'Health & Household'),
                'Home & Kitchen': getTranslatedString('category_home_kitchen', 'Home & Kitchen'),
                'Industrial & Scientific': getTranslatedString('category_industrial', 'Industrial & Scientific'),
                'Musical Instruments': getTranslatedString('category_musical_instruments', 'Musical Instruments'),
                'No category found': getTranslatedString('category_no_category', 'No category found'),
                'Office Products': getTranslatedString('category_office_products', 'Office Products'),
                'Patio, Lawn & Garden': getTranslatedString('category_patio_lawn', 'Patio, Lawn & Garden'),
                'Safety & Security': getTranslatedString('category_safety_security', 'Safety & Security'),
                'Tools & Home Improvement': getTranslatedString('category_tools_home', 'Tools & Home Improvement'),
                'Toys & Games': getTranslatedString('category_toys_games', 'Toys & Games'),
            };
            if (categoryMap[name]) return categoryMap[name];
            for (const [key, translation] of Object.entries(categoryMap)) {
                if (name.startsWith(key) || key.startsWith(name)) {
                    return translation;
                }
            }
            return name;
        };

        const colors = getThemeColors();
        const categories = data.map(item => translateCategoryName(item.category));

        const series = metrics.map(metric => {
            const metricData = categories.map(category => {
                // Find item by matching translated category name
                const item = data.find(d => translateCategoryName(d.category) === category);
                if (!item) return { x: category, y: -1000000, originalValue: null, formatter: metric.formatter };
                const value = item[metric.key];
                if (value === null || (typeof value === 'number' && isNaN(value))) {
                    return { x: category, y: -1000000, originalValue: null, formatter: metric.formatter };
                }
                return {
                    x: category,
                    y: typeof value === 'number' ? value : 0,
                    originalValue: value,
                    formatter: metric.formatter,
                };
            });
            return { name: metric.label, data: metricData };
        });

        // Calculate available height for chart
        const modalHeader = document.querySelector('.category-comparison-view-all-modal-header');
        const headerHeight = modalHeader ? modalHeader.offsetHeight : 80;
        const bodyPadding = 64; // 2rem top + 2rem bottom
        const containerPadding = 48; // 1.5rem top + 1.5rem bottom
        const availableHeight = window.innerHeight - headerHeight - bodyPadding - containerPadding;

        // Clear container and create chart wrapper
        chartContainer.innerHTML = '';
        const chartWrapper = document.createElement('div');
        chartWrapper.id = 'category-comparison-view-all-chart-wrapper';
        chartWrapper.style.width = '100%';
        chartWrapper.style.height = '100%';
        chartContainer.appendChild(chartWrapper);

        // Calculate optimal chart height to fit viewport
        const chartHeight = Math.max(400, Math.min(availableHeight, 800));

        const options = {
            chart: {
                type: 'heatmap',
                height: chartHeight,
                toolbar: { show: true, tools: { download: true } },
            },
            theme: { mode: 'light' },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.7,
                    radius: 4,
                    useFillColorAsStroke: false,
                    strokeWidth: 3,
                    stroke: '#ffffff',
                    colorScale: {
                        ranges: [
                            { from: -1000000, to: -1, name: getTranslatedString('na', 'N/A'), color: '#e2e8f0' },
                            { from: 0, to: Infinity, name: getTranslatedString('value', 'value'), color: '#3b82f6' },
                        ],
                    },
                },
            },
            dataLabels: {
                enabled: true,
                style: { fontSize: '10px', fontWeight: '500', colors: [colors.text] },
                formatter: function(val, opts) {
                    if (val < 0) return getTranslatedString('na', 'N/A');
                    try {
                        const pointData = opts.w.config.series[opts.seriesIndex]?.data[opts.dataPointIndex];
                        if (pointData && pointData.formatter && pointData.originalValue !== undefined) {
                            return pointData.formatter(pointData.originalValue);
                        }
                    } catch (e) {}
                    if (val < 0) return getTranslatedString('na', 'N/A');
                    if (val >= 1000) return `${(val / 1000).toFixed(1)}K`;
                    return val.toFixed(2);
                },
            },
            xaxis: {
                categories: categories,
                labels: { 
                    style: { colors: colors.textSecondary, fontSize: '11px' }, 
                    rotate: -45, 
                    rotateAlways: true,
                    maxHeight: 120,
                    trim: true
                },
                title: { text: getTranslatedString('categories', 'Categories'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
            },
            yaxis: {
                categories: metrics.map(m => m.label),
                labels: { style: { colors: colors.textSecondary, fontSize: '11px' } },
                title: { text: getTranslatedString('metrics', 'Metrics'), style: { fontSize: '14px', fontWeight: '600', color: colors.textSecondary } },
            },
            colors: ['#3b82f6'],
            grid: { borderColor: colors.grid, strokeDashArray: 4 },
            tooltip: {
                custom: function({ seriesIndex, dataPointIndex, w }) {
                    const categoryList = w.config.xaxis.categories || categories;
                    const category = categoryList[dataPointIndex];
                    const metric = metrics[seriesIndex];
                    if (!metric || !category) return '';
                    // Find item by matching translated category name
                    const categoryData = data.find(item => translateCategoryName(item.category) === category);
                    if (!categoryData) return '';
                    const value = categoryData[metric.key];
                    const displayValue = metric.formatter(value);
                    const isNA = value === null || (typeof value === 'number' && isNaN(value));
                    
                    return `
                        <div class="category-comparison-tooltip" style="
                            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                            border: 1px solid #e2e8f0;
                            border-left: 4px solid ${isNA ? '#94a3b8' : '#3b82f6'};
                            border-radius: 0.75rem;
                            padding: 1rem 1.25rem;
                            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                            min-width: 200px;
                            backdrop-filter: blur(8px);
                        ">
                            <div style="
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                margin-bottom: 0.75rem;
                                padding-bottom: 0.75rem;
                                border-bottom: 1px solid #e2e8f0;
                            ">
                                <div style="
                                    width: 8px;
                                    height: 8px;
                                    border-radius: 50%;
                                    background: ${isNA ? '#94a3b8' : '#3b82f6'};
                                    flex-shrink: 0;
                                "></div>
                                <div style="
                                    font-weight: 700;
                                    font-size: 0.875rem;
                                    color: #0f172a;
                                    letter-spacing: 0.025em;
                                    text-transform: uppercase;
                                ">${category || getTranslatedString('unknown', 'Unknown')}</div>
                            </div>
                            <div style="
                                display: flex;
                                flex-direction: column;
                                gap: 0.5rem;
                            ">
                                <div style="
                                    font-size: 0.75rem;
                                    color: #64748b;
                                    font-weight: 500;
                                    margin-bottom: 0.25rem;
                                ">${metric.label}</div>
                                <div style="
                                    font-size: 1.25rem;
                                    font-weight: 700;
                                    color: ${isNA ? '#64748b' : '#1e40af'};
                                    line-height: 1.2;
                                ">${displayValue}</div>
                            </div>
                        </div>
                    `;
                },
            },
        };

        // Render the chart
        new ApexCharts(chartWrapper, { ...options, series }).render();
    }

    /**
     * Open Category Comparison View All modal
     */
    function openCategoryComparisonViewAllModal(data, metrics) {
        // Create modal overlay if it doesn't exist
        let modal = document.getElementById('category-comparison-view-all-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'category-comparison-view-all-modal';
            modal.className = 'category-comparison-view-all-modal';
            modal.innerHTML = `
                <div class="category-comparison-view-all-modal-overlay"></div>
                <div class="category-comparison-view-all-modal-content">
                    <div class="category-comparison-view-all-modal-header">
                        <h2>${getTranslatedString('category_comparison_all_data', 'Category Comparison - All Data')}</h2>
                        <button class="category-comparison-view-all-modal-close" aria-label="${getTranslatedString('close', 'Close')}">&times;</button>
                    </div>
                    <div class="category-comparison-view-all-modal-body">
                        <div class="category-comparison-view-all-chart-wrapper">
                            <div id="category-comparison-view-all-chart-container" class="category-comparison-view-all-chart-container">
                                <div class="loading-skeleton">
                                    <div class="skeleton-header"></div>
                                    <div class="skeleton-content"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close modal handlers
            const overlay = modal.querySelector('.category-comparison-view-all-modal-overlay');
            const closeBtn = modal.querySelector('.category-comparison-view-all-modal-close');
            
            overlay.addEventListener('click', closeCategoryComparisonViewAllModal);
            closeBtn.addEventListener('click', closeCategoryComparisonViewAllModal);
            
            // Close on Escape key
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeCategoryComparisonViewAllModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Store handler for cleanup if needed
            modal._escapeHandler = escapeHandler;
        }

        // Show modal first to calculate dimensions
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Wait for modal to be visible, then render chart
        setTimeout(() => {
            renderCategoryComparisonModalChart(modal, data, metrics);
        }, 100);
    }

    /**
     * Close Category Comparison View All modal
     */
    function closeCategoryComparisonViewAllModal() {
        const modal = document.getElementById('category-comparison-view-all-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    /**
     * Render Trend Index modal table with given products
     */
    function renderTrendIndexModalTable(modal, products, totalCount = null) {
        const tableContainer = document.getElementById('trend-index-view-all-table-container');
        if (!tableContainer) return;

        // Get total count from modal if not provided
        const total = totalCount !== null ? totalCount : (modal._allProducts ? modal._allProducts.length : products.length);

        let html = `
            <div class="table-container">
                <table id="trend-index-view-all-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">${getTranslatedString('product_name', 'Product Name')}</th>
                            <th style="width: 25%;">${getTranslatedString('category', 'Category')}</th>
                            <th style="text-align: center; width: 15%;">${getTranslatedString('trend_index', 'Trend Index')}</th>
                            <th style="text-align: center; width: 20%;">${getTranslatedString('status', 'Status')}</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        if (products.length === 0) {
            html += `<tr><td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-tertiary, #64748b);">${getTranslatedString('no_products_found', 'No products found')}</td></tr>`;
        } else {
            products.forEach(item => {
                let statusClass = 'status-slate';
                const status = String(item.status || '').toLowerCase();
                if (status === 'stable') statusClass = 'status-stable';
                else if (status === 'rising') statusClass = 'status-rising';
                else if (status === 'falling') statusClass = 'status-falling';

                const productName = String(item.name || 'Unnamed');
                html += `<tr data-product-name="${productName.toLowerCase()}">`;
                html += `<td class="font-medium">${productName}</td>`;
                html += `<td>${String(item.category || '—')}</td>`;
                html += `<td style="text-align: center; font-weight: 500;">${typeof item.trendIndex === 'number' ? item.trendIndex.toFixed(2) : '—'}</td>`;
                const statusText = String(item.status || '—');
                let translatedStatus = statusText;
                if (statusText !== '—') {
                    const statusLower = statusText.toLowerCase();
                    if (statusLower === 'rising') translatedStatus = getTranslatedString('rising', 'Rising');
                    else if (statusLower === 'stable') translatedStatus = getTranslatedString('stable', 'Stable');
                    else if (statusLower === 'declining' || statusLower === 'falling') translatedStatus = getTranslatedString('declining', 'Declining');
                }
                html += `<td style="text-align: center;"><span class="status-badge ${statusClass}">${translatedStatus}</span></td>`;
                html += `</tr>`;
            });
        }

        html += `</tbody></table></div>`;
        tableContainer.innerHTML = html;

        // Update results count
        updateTrendIndexResultsCount(products.length, total);
    }

    /**
     * Filter Trend Index modal table based on search query
     */
    function filterTrendIndexModalTable(modal, searchQuery, allProducts) {
        let filteredProducts = allProducts;

        if (searchQuery) {
            filteredProducts = allProducts.filter(item => {
                const productName = String(item.name || 'Unnamed').toLowerCase();
                const category = String(item.category || '').toLowerCase();
                return productName.includes(searchQuery) || category.includes(searchQuery);
            });
        }

        // Re-render table with filtered products
        renderTrendIndexModalTable(modal, filteredProducts, allProducts.length);
    }

    /**
     * Update results count display
     */
    function updateTrendIndexResultsCount(filtered, total) {
        const resultsCount = document.getElementById('trend-index-view-all-results-count');
        if (resultsCount) {
            if (filtered === total) {
                resultsCount.textContent = `${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            } else {
                resultsCount.textContent = `${filtered} of ${total} ${total !== 1 ? getTranslatedString('products', 'products') : getTranslatedString('product_singular', 'product')}`;
            }
        }
    }

    /**
     * Open modal to display all Trend Index data
     */
    function openTrendIndexViewAllModal(data) {
        // Create modal overlay if it doesn't exist
        let modal = document.getElementById('trend-index-view-all-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'trend-index-view-all-modal';
            modal.className = 'trend-index-view-all-modal';
            modal.innerHTML = `
                <div class="trend-index-view-all-modal-overlay"></div>
                <div class="trend-index-view-all-modal-content">
                    <div class="trend-index-view-all-modal-header">
                        <h2>${getTranslatedString('all_trend_index_products', 'All Trend Index Products')}</h2>
                        <button class="trend-index-view-all-modal-close" aria-label="${getTranslatedString('close', 'Close')}">&times;</button>
                    </div>
                    <div class="trend-index-view-all-modal-body">
                        <div class="trend-index-view-all-search-container">
                            <div class="trend-index-view-all-search-wrapper">
                                <input 
                                    type="text" 
                                    id="trend-index-view-all-search-input" 
                                    class="trend-index-view-all-search-input" 
                                    placeholder="${getTranslatedString('search_products_or_categories', 'Search products or categories...')}"
                                    autocomplete="off"
                                />
                                <span class="trend-index-view-all-search-icon">🔍</span>
                            </div>
                            <div id="trend-index-view-all-results-count" class="trend-index-view-all-results-count"></div>
                        </div>
                        <div id="trend-index-view-all-table-container" class="trend-index-view-all-table-container">
                            <div class="loading-skeleton">
                                <div class="skeleton-header"></div>
                                <div class="skeleton-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close modal handlers
            const overlay = modal.querySelector('.trend-index-view-all-modal-overlay');
            const closeBtn = modal.querySelector('.trend-index-view-all-modal-close');
            
            overlay.addEventListener('click', closeTrendIndexViewAllModal);
            closeBtn.addEventListener('click', closeTrendIndexViewAllModal);
            
            // Close on Escape key (use a single handler)
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeTrendIndexViewAllModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Store handler for cleanup if needed
            modal._escapeHandler = escapeHandler;
        }

        // Store products data in modal for search functionality
        modal._allProducts = data;

        // Clear search input if it exists
        const existingSearchInput = document.getElementById('trend-index-view-all-search-input');
        if (existingSearchInput) {
            existingSearchInput.value = '';
        }

        // Render table with all data
        renderTrendIndexModalTable(modal, data, data.length);

        // Add search functionality
        const searchInput = document.getElementById('trend-index-view-all-search-input');
        if (searchInput) {
            // Remove existing listeners if any (to prevent duplicates)
            const newSearchInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newSearchInput, searchInput);
            
            const freshSearchInput = document.getElementById('trend-index-view-all-search-input');
            
            freshSearchInput.addEventListener('input', function(e) {
                const searchQuery = e.target.value.toLowerCase().trim();
                filterTrendIndexModalTable(modal, searchQuery, data);
            });

            // Clear search on Escape (but don't close modal if search has focus)
            freshSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && freshSearchInput.value) {
                    e.stopPropagation();
                    freshSearchInput.value = '';
                    filterTrendIndexModalTable(modal, '', data);
                }
            });
            
            // Focus search input
            setTimeout(() => freshSearchInput.focus(), 100);
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close Trend Index View All modal
     */
    function closeTrendIndexViewAllModal() {
        const modal = document.getElementById('trend-index-view-all-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Trend Index Table
    async function renderTrendIndex() {
        const container = document.getElementById('trend-index-container');
        if (!container) return;

        // Show loading state
        if (!chartData.trendIndex && !errors.trendIndex) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('trend-index');
                chartData.trendIndex = {
                    title: getTranslatedString('trend_index_by_product', 'Trend Index by Product'),
                    data: Array.isArray(response.data) ? response.data : []
                };
            } catch (error) {
                errors.trendIndex = error.message;
                container.innerHTML = `<div class="error-message">Failed to load trend index data: ${error.message}</div>`;
                return;
            }
        }

        if (errors.trendIndex) {
            container.innerHTML = `<div class="error-message">Failed to load trend index data: ${errors.trendIndex}</div>`;
            return;
        }

        if (!chartData.trendIndex || !chartData.trendIndex.data || chartData.trendIndex.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.trendIndex.data.slice(-10);
        
        let html = `
            <div class="trend-index-chart-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="chart-title" style="margin-bottom: 0;">${chartData.trendIndex.title}</div>
                <button id="trend-index-view-all-btn" class="trend-index-view-all-btn">
                    ${getTranslatedString('view_all', 'View All')}
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>${getTranslatedString('product_name', 'Product Name')}</th>
                            <th>${getTranslatedString('category', 'Category')}</th>
                            <th style="text-align: center;">${getTranslatedString('trend_index', 'Trend Index')}</th>
                            <th style="text-align: center;">${getTranslatedString('status', 'Status')}</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(item => {
            let statusClass = 'status-slate';
            const status = String(item.status || '').toLowerCase();
            if (status === 'stable') statusClass = 'status-stable';
            else if (status === 'rising') statusClass = 'status-rising';
            else if (status === 'falling') statusClass = 'status-falling';

            html += `
                <tr>
                    <td class="font-medium">${String(item.name || '—')}</td>
                    <td>${String(item.category || '—')}</td>
                    <td style="text-align: center; font-weight: 500;">${typeof item.trendIndex === 'number' ? item.trendIndex.toFixed(2) : '—'}</td>
                    <td style="text-align: center;"><span class="status-badge ${statusClass}">${String(item.status || '—')}</span></td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;

        // Add event listener for View All button
        const viewAllBtn = document.getElementById('trend-index-view-all-btn');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', () => {
                openTrendIndexViewAllModal(chartData.trendIndex.data);
            });
        }
    }

    // Trend Status Chart
    async function renderTrendStatus() {
        const container = document.getElementById('trend-status-container');
        if (!container) return;

        // Show loading state
        if (!chartData.trendStatus && !errors.trendStatus) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const response = await fetchDashboardData('trend-status');
                chartData.trendStatus = {
                    title: getTranslatedString('trend_status_overview', 'Trend Status Overview'),
                    data: response.data || { Rising: 0, Stable: 0, Declining: 0, total: 0 }
                };
            } catch (error) {
                errors.trendStatus = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('trend_status_data', 'trend status data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.trendStatus) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('trend_status_data', 'trend status data')}: ${errors.trendStatus}</div>`;
            return;
        }

        if (!chartData.trendStatus || !chartData.trendStatus.data) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.trendStatus.data;
        const colors = getThemeColors();
        const total = Number(data.total) || (Number(data.Rising) + Number(data.Stable) + Number(data.Declining));
        const percentages = {
            Rising: total > 0 ? (Number(data.Rising) / total) * 100 : 0,
            Stable: total > 0 ? (Number(data.Stable) / total) * 100 : 0,
            Declining: total > 0 ? (Number(data.Declining) / total) * 100 : 0,
        };

        const options = {
            chart: {
                type: 'pie',
                height: 400,
                toolbar: { show: true, tools: { download: true } },
            },
            title: {
                text: chartData.trendStatus.title,
                align: 'left',
                style: { fontSize: '16px', fontWeight: '600', color: colors.text },
            },
            labels: [
                getTranslatedString('rising', 'Rising'),
                getTranslatedString('stable', 'Stable'),
                getTranslatedString('declining', 'Declining')
            ],
            colors: ['#3b82f6', '#6b7280', '#ef4444'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.5,
                    gradientToColors: ['#60a5fa', '#9ca3af', '#f87171'],
                    inverseColors: false,
                    opacityFrom: 0.8,
                    opacityTo: 0.6,
                    stops: [0, 100],
                },
            },
            stroke: { width: 2, colors: ['#fff'] },
            plotOptions: {
                pie: {
                    expandOnClick: true,
                    dataLabels: { offset: 0, minAngleToShowLabel: 10 },
                },
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => val.toFixed(1) + '%',
                style: { fontSize: '14px', fontWeight: '600', colors: [colors.text] },
                dropShadow: { enabled: true, color: '#fff', blur: 3, opacity: 0.5 },
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                fontSize: '14px',
                fontWeight: 500,
                labels: { colors: colors.textSecondary },
                markers: { width: 12, height: 12, radius: 6, strokeWidth: 0 },
                itemMargin: { horizontal: 10, vertical: 5 },
            },
            tooltip: {
                enabled: true,
                custom: function({ seriesIndex, w }) {
                    const labels = [
                        getTranslatedString('rising', 'Rising'),
                        getTranslatedString('stable', 'Stable'),
                        getTranslatedString('declining', 'Declining')
                    ];
                    const dataKeys = ['Rising', 'Stable', 'Declining'];
                    const label = labels[seriesIndex];
                    const dataKey = dataKeys[seriesIndex];
                    const count = Number(data[dataKey]) || 0;
                    const percentage = percentages[dataKey] || 0;
                    return `
                        <div class="px-3 py-2" style="background: ${colors.background}; border: 1px solid ${colors.grid}; border-radius: 0.5rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem; color: ${colors.text};">${label}</div>
                            <div style="font-size: 0.75rem; color: ${colors.textSecondary};">
                                <div>${getTranslatedString('count', 'Count')}: <span style="font-weight: 500;">${count.toLocaleString()}</span></div>
                                <div>${getTranslatedString('percentage', 'Percentage')}: <span style="font-weight: 500;">${percentage.toFixed(2)}%</span></div>
                            </div>
                        </div>
                    `;
                },
            },
        };

        const series = [Number(data.Rising), Number(data.Stable), Number(data.Declining)];

        container.innerHTML = '';
        const chart = new ApexCharts(container, { ...options, series });
        chart.render();

        // Add summary stats
        setTimeout(() => {
            const summary = document.createElement('div');
            summary.className = 'mt-4 flex justify-center space-x-6 flex-wrap gap-4';
            summary.innerHTML = `
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #2563eb;">${Number(data.Rising).toLocaleString()}</div>
                    <div style="font-size: 0.75rem; color: #1d4ed8;">${getTranslatedString('rising', 'Rising')} (${percentages.Rising.toFixed(1)}%)</div>
                </div>
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #4b5563;">${Number(data.Stable).toLocaleString()}</div>
                    <div style="font-size: 0.75rem; color: #6b7280;">${getTranslatedString('stable', 'Stable')} (${percentages.Stable.toFixed(1)}%)</div>
                </div>
                <div class="text-center">
                    <div style="font-size: 1.125rem; font-weight: 700; color: #dc2626;">${Number(data.Declining).toLocaleString()}</div>
                    <div style="font-size: 0.75rem; color: #b91c1c;">${getTranslatedString('declining', 'Declining')} (${percentages.Declining.toFixed(1)}%)</div>
                </div>
            `;
            container.appendChild(summary);
        }, 100);
    }

    // Suggested Pricing Table
    async function renderSuggestedPricing() {
        const container = document.getElementById('suggested-pricing-container');
        if (!container) return;

        // Show loading state
        if (!chartData.suggestedPricing && !errors.suggestedPricing) {
            container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            
            try {
                const data = await fetchSuggestedPricing();
                
                // Debug: Log the response structure to help identify issues
                VortemLogger.log('Suggested Pricing API Response:', data);
                VortemLogger.log('Is Array:', Array.isArray(data));
                VortemLogger.log('Data Type:', typeof data);
                
                // Ensure data is an array
                let pricingData = [];
                if (Array.isArray(data)) {
                    pricingData = data;
                } else if (data && typeof data === 'object') {
                    // If it's an object, try to extract array from common keys
                    if (Array.isArray(data.data)) {
                        pricingData = data.data;
                    } else if (Array.isArray(data.suggested_pricing)) {
                        pricingData = data.suggested_pricing;
                    } else if (Array.isArray(data.result)) {
                        pricingData = data.result;
                    } else {
                        // If it's a single object, wrap it in an array
                        pricingData = [data];
                    }
                }
                
                // Log first item structure for debugging
                if (pricingData.length > 0) {
                    VortemLogger.log('First pricing item structure:', pricingData[0]);
                    VortemLogger.log('Available keys in first item:', Object.keys(pricingData[0]));
                }
                
                // Transform/normalize the data to match expected structure
                const transformedData = pricingData.map((item, index) => {
                    // Helper function to safely get nested value
                    const getValue = (obj, ...paths) => {
                        for (const path of paths) {
                            const keys = path.split('.');
                            let value = obj;
                            for (const key of keys) {
                                if (value && typeof value === 'object' && key in value) {
                                    value = value[key];
                                } else {
                                    value = undefined;
                                    break;
                                }
                            }
                            if (value !== undefined && value !== null) return value;
                        }
                        return undefined;
                    };
                    
                    // Helper function to convert to number
                    const toNumber = (val) => {
                        if (val === null || val === undefined || val === '') return 0;
                        const num = typeof val === 'string' ? parseFloat(val.replace(/[^0-9.-]/g, '')) : Number(val);
                        return isNaN(num) ? 0 : num;
                    };
                    
                    // Map various possible field names to expected structure
                    const transformed = {
                        product_id: item.product_id || item.id || item.sku || item.productId || item.productID || getValue(item, 'pb_info.product_id', 'product.product_id') || `item-${index}`,
                        title: item.title || item.name || item.product_title || item.productTitle || getValue(item, 'pb_info.title', 'product.title') || getTranslatedString('untitled_product', 'Untitled Product'),
                        base_price: toNumber(item.base_price || item.price || item.regular_price || item.basePrice || getValue(item, 'price.original', 'pricing.base_price', 'product.price')),
                        suggested_pricing: {
                            low_risk_price: toNumber(item.suggested_pricing?.low_risk_price || 
                                         item.low_risk_price || 
                                         item.lowRiskPrice || 
                                         getValue(item, 'suggested_pricing.low_risk_price', 'pricing.low_risk_price', 'pricing.lowRiskPrice') || 
                                         item.suggested_pricing?.lowRiskPrice),
                            competitive_price: toNumber(item.suggested_pricing?.competitive_price || 
                                             item.competitive_price || 
                                             item.competitivePrice || 
                                             getValue(item, 'suggested_pricing.competitive_price', 'pricing.competitive_price', 'pricing.competitivePrice') || 
                                             item.suggested_pricing?.competitivePrice),
                            high_risk_price: toNumber(item.suggested_pricing?.high_risk_price || 
                                           item.high_risk_price || 
                                           item.highRiskPrice || 
                                           getValue(item, 'suggested_pricing.high_risk_price', 'pricing.high_risk_price', 'pricing.highRiskPrice') || 
                                           item.suggested_pricing?.highRiskPrice)
                        }
                    };
                    
                    // Log transformation for first item
                    if (index === 0) {
                        VortemLogger.log('Transformed first item:', transformed);
                    }
                    
                    return transformed;
                });
                
                chartData.suggestedPricing = {
                    title: getTranslatedString('suggested_pricing', 'Suggested Pricing'),
                    data: transformedData
                };
            } catch (error) {
                VortemLogger.error('Error in renderSuggestedPricing:', error);
                errors.suggestedPricing = error.message;
                container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('suggested_pricing_data', 'suggested pricing data')}: ${error.message}</div>`;
                return;
            }
        }

        if (errors.suggestedPricing) {
            container.innerHTML = `<div class="error-message">${getTranslatedString('failed_to_load', 'Failed to load')} ${getTranslatedString('suggested_pricing_data', 'suggested pricing data')}: ${errors.suggestedPricing}</div>`;
            return;
        }

        if (!chartData.suggestedPricing || !chartData.suggestedPricing.data || chartData.suggestedPricing.data.length === 0) {
            container.innerHTML = '<div class="empty-state">No data available</div>';
            return;
        }

        const data = chartData.suggestedPricing.data.slice(-10);
        
        const formatCurrency = (value) => {
            if (value === null || value === undefined || isNaN(value)) return '—';
            return `$${value.toFixed(2)}`;
        };

        let html = `
            <div class="kpi-chart-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="chart-title" style="margin-bottom: 0;">${chartData.suggestedPricing.title}</div>
                <button id="suggested-pricing-view-all-btn" class="kpi-view-all-btn">
                    ${getTranslatedString('view_all', 'View All')}
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%;">${getTranslatedString('product_id', 'Product ID')}</th>
                            <th style="width: 40%;">${getTranslatedString('product_title', 'Product Title')}</th>
                            <th style="width: 12%; text-align: center;">${getTranslatedString('base_price', 'Base Price')}</th>
                            <th style="width: 12%; text-align: center;">${getTranslatedString('low_risk_price', 'Low-Risk Price')}</th>
                            <th style="width: 12%; text-align: center;">${getTranslatedString('competitive_price', 'Competitive Price')}</th>
                            <th style="width: 12%; text-align: center;">${getTranslatedString('high_risk_price', 'High-Risk Price')}</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(item => {
            html += `
                <tr>
                    <td style="font-family: monospace; font-size: 0.75rem;">${String(item.product_id || '—')}</td>
                    <td class="font-medium">${String(item.title || '—')}</td>
                    <td style="text-align: center; font-weight: 500;">${formatCurrency(item.base_price)}</td>
                    <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.low_risk_price)}</td>
                    <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.competitive_price)}</td>
                    <td style="text-align: center; font-weight: 500;">${formatCurrency(item.suggested_pricing?.high_risk_price)}</td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;

        // Add event listener for View All button
        const viewAllBtn = document.getElementById('suggested-pricing-view-all-btn');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', () => {
                openSuggestedPricingViewAllModal(chartData.suggestedPricing.data);
            });
        }
    }

    // Update last updated timestamp
    function updateLastUpdated() {
        const lastUpdatedElement = document.getElementById('bi-analytics-last-updated');
        if (!lastUpdatedElement) return;
        
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        const lastUpdatedText = getTranslatedString('last_updated', 'Last updated:');
        
        // Format time string - always use English numerals
        lastUpdatedElement.textContent = `${lastUpdatedText} ${timeString}`;
    }

    // Initialize all charts
    async function initCharts() {
        // Check if container exists before initializing
        const dashboardContainer = document.querySelector('.bi-analytics-hub-dashboard');
        if (!dashboardContainer) {
            VortemLogger.warn('BI Analytics Hub: Dashboard container not found, charts will not initialize');
            return;
        }
        
        // Reset all chart containers to loading state
        const chartContainers = [
            'kpi-radar-container',
            'keywords-performance-container',
            'price-rating-container',
            'customer-sentiment-container',
            'trend-status-container',
            'market-comparison-container',
            'category-comparison-container',
            'trend-index-container',
            'suggested-pricing-container'
        ];
        
        chartContainers.forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = '<div class="loading-skeleton"><div class="skeleton-header"></div><div class="skeleton-content"></div></div>';
            }
        });
        
        // Load all charts in parallel
        await Promise.all([
            renderKpiRadar(),
            renderKeywordsPerformance(),
            renderPriceRating(),
            renderCustomerSentiment(),
            renderMarketComparison(),
            renderCategoryComparison(),
            renderTrendIndex(),
            renderTrendStatus(),
            renderSuggestedPricing(),
        ]);
        
        // Update last updated timestamp after all charts are loaded
        updateLastUpdated();
    }

    // Expose initCharts globally so it can be called manually
    window.initBIAnalyticsCharts = initCharts;

    // Initialize on page load only if container exists
    const dashboardContainerOnLoad = document.querySelector('.bi-analytics-hub-dashboard');
    if (dashboardContainerOnLoad) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCharts);
        } else {
            initCharts();
        }
    } else {
        // Container doesn't exist yet, will be initialized manually
        VortemLogger.log('BI Analytics Hub: Container not found on load, will be initialized manually');
    }
    
    } // End of initializeScript function
})();

