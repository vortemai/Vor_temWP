// External Library: React 18.3.1 (Meta/Open Source) - https://reactjs.org/ | License: MIT | Bundled locally in assets/vendor/react/
// External Library: ReactDOM 18.3.1 (Meta/Open Source) - https://reactjs.org/ | License: MIT | Bundled locally in assets/vendor/react/
// This file uses React hooks (useState, useEffect, useRef, Suspense, lazy) and ReactDOM.createRoot() for tab-based analytics UI
(function() {
    'use strict';

    // External Library: React - Destructuring React hooks for component state management
    const { useState, useEffect, Suspense, lazy } = React;

    // Loading Fallback Component
    function LoadingFallback() {
        return React.createElement('div', { className: 'vortem-tab-loading' },
            React.createElement('p', null, vortemAnalyticsTabsConfig.strings.loading)
        );
    }

    // Analytics Tab Component - wraps existing mega-dash.js
    function AnalyticsTab() {
        const containerRef = React.useRef(null);
        const [isInitialized, setIsInitialized] = React.useState(false);

        useEffect(() => {
            if (isInitialized || !containerRef.current) return;

            // Create the analytics dashboard HTML structure
            const clearCacheUrl = vortemAnalyticsTabsConfig.clearCacheUrl;
            const exportUrl = vortemAnalyticsTabsConfig.exportUrl;

            containerRef.current.innerHTML = `
                <div class="wrap" id="mega-dash-app">
                    <div class="megadash-header">
                        <div class="megadash-header-left">
                            <h1 class="megadash-title">
                                <span class="dashicons dashicons-chart-area"></span>
                                ${vortemAnalyticsTabsConfig.strings.analytics}
                            </h1>
                            <span class="megadash-last-updated" id="megadash-last-updated">
                                ${vortemAnalyticsTabsConfig.strings.loading}
                            </span>
                        </div>
                    </div>

                    <div class="megadash-dashboard">
                        <section class="megadash-section megadash-section-woocommerce" id="megadash-woocommerce">
                            <div class="megadash-section-header">
                                <h2 class="megadash-section-title">
                                    <span class="dashicons dashicons-cart"></span>
                                    ${vortemAnalyticsTabsConfig.strings.woocommerceAnalytics}
                                </h2>
                                <div class="megadash-section-header-right">
                                    <button type="button" class="megadash-btn megadash-btn-refresh" id="megadash-refresh-btn" aria-label="${vortemAnalyticsTabsConfig.strings.refresh}">
                                        <span class="dashicons dashicons-update" id="megadash-refresh-icon"></span>
                                    </button>
                                    <a href="${clearCacheUrl}" class="megadash-btn megadash-btn-secondary">
                                        ${vortemAnalyticsTabsConfig.strings.clearCache}
                                    </a>
                                    <a href="${exportUrl}" class="megadash-btn megadash-btn-secondary" id="megadash-export-btn" download>
                                        <span class="dashicons dashicons-download"></span>
                                        ${vortemAnalyticsTabsConfig.strings.exportCsv}
                                    </a>
                                </div>
                            </div>
                            <div class="megadash-charts-container" id="megadash-woocommerce-charts"></div>
                            <div class="megadash-grid megadash-grid-woocommerce" id="megadash-woocommerce-grid">
                                <div class="megadash-card megadash-skeleton">
                                    <div class="megadash-card-icon"></div>
                                    <div class="megadash-card-label"></div>
                                    <div class="megadash-card-value"></div>
                                </div>
                            </div>
                        </section>

                        <section class="megadash-section megadash-section-wordpress" id="megadash-wordpress">
                            <div class="megadash-section-header">
                                <h2 class="megadash-section-title">
                                    <span class="dashicons dashicons-wordpress"></span>
                                    ${vortemAnalyticsTabsConfig.strings.wordpressAnalytics}
                                </h2>
                            </div>
                            <div class="megadash-charts-container" id="megadash-wordpress-charts"></div>
                            <div class="megadash-grid megadash-grid-wordpress" id="megadash-wordpress-grid">
                                <div class="megadash-card megadash-skeleton">
                                    <div class="megadash-card-icon"></div>
                                    <div class="megadash-card-label"></div>
                                    <div class="megadash-card-value"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            `;

            // Set up vortemMegadashData for the existing script
            // Use existing vortemMegadashData if available (from wp_localize_script), otherwise create new one
            const megadashStrings = (typeof vortemAnalyticsTabsStrings !== 'undefined' && vortemAnalyticsTabsStrings)
                ? vortemAnalyticsTabsStrings
                : (typeof vortemAnalyticsTabsMegadashStrings !== 'undefined' && vortemAnalyticsTabsMegadashStrings)
                    ? vortemAnalyticsTabsMegadashStrings
                    : (window.vortemMegadashData && window.vortemMegadashData.strings) || {};
            
            if (typeof window.vortemMegadashData === 'undefined') {
                window.vortemMegadashData = {
                    ajax_url: vortemAnalyticsTabsConfig.restUrl,
                    nonce: vortemAnalyticsTabsConfig.nonce,
                    refresh_interval: 30000,
                    locale: vortemAnalyticsTabsConfig.locale,
                    currency_symbol: vortemAnalyticsTabsConfig.currencySymbol,
                    currency_pos: vortemAnalyticsTabsConfig.currencyPos,
                    current_language: vortemAnalyticsTabsConfig.locale ? vortemAnalyticsTabsConfig.locale.split('_')[0] : 'en',
                    strings: megadashStrings
                };
            } else {
                // Update existing vortemMegadashData with current config values if needed
                // Merge localized strings with any existing values
                window.vortemMegadashData.ajax_url = window.vortemMegadashData.ajax_url || vortemAnalyticsTabsConfig.restUrl;
                window.vortemMegadashData.nonce = window.vortemMegadashData.nonce || vortemAnalyticsTabsConfig.nonce;
                window.vortemMegadashData.locale = window.vortemMegadashData.locale || vortemAnalyticsTabsConfig.locale;
                window.vortemMegadashData.currency_symbol = window.vortemMegadashData.currency_symbol || vortemAnalyticsTabsConfig.currencySymbol;
                window.vortemMegadashData.currency_pos = window.vortemMegadashData.currency_pos || vortemAnalyticsTabsConfig.currencyPos;
                window.vortemMegadashData.current_language = window.vortemMegadashData.current_language || (vortemAnalyticsTabsConfig.locale ? vortemAnalyticsTabsConfig.locale.split('_')[0] : 'en');
                // Merge strings - use existing if available, otherwise use from megadashStrings
                if (!window.vortemMegadashData.strings || Object.keys(window.vortemMegadashData.strings).length === 0) {
                    window.vortemMegadashData.strings = megadashStrings;
                } else {
                    // Merge: existing strings take precedence, but fill in missing ones from megadashStrings
                    window.vortemMegadashData.strings = { ...megadashStrings, ...window.vortemMegadashData.strings };
                }
            }

            // Set up navigation tab switching
            const setupNavigation = () => {
                const navTabs = containerRef.current.querySelectorAll('.megadash-nav-tab');
                navTabs.forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetTab = tab.getAttribute('data-tab');
                        
                        // Update active state
                        navTabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        
                        // Trigger parent React component to switch tabs
                        if (window.vortemAnalyticsTabsApp && typeof window.vortemAnalyticsTabsApp.switchTab === 'function') {
                            window.vortemAnalyticsTabsApp.switchTab(targetTab);
                        } else {
                            // Fallback: update URL and reload
                            const url = new URL(window.location);
                            // BI Analytics Hub page condition commented out
                            const page = /* targetTab === 'bi-analytics-hub' ? 'vortem-bi-analytics-hub' : */ 'vortem-analytics';
                            url.searchParams.set('page', page);
                            window.location.href = url.toString();
                        }
                    });
                });
            };

            // Load and initialize the analytics script if not already loaded
            const initAnalytics = () => {
                if (typeof window.MegaDash !== 'undefined') {
                    // Destroy existing instance if any
                    if (window.vortemMegaDashInstance) {
                        if (window.vortemMegaDashInstance.destroy) {
                            window.vortemMegaDashInstance.destroy();
                        }
                    }
                    // Create new instance
                    window.vortemMegaDashInstance = new MegaDash();
                    setIsInitialized(true);
                } else {
                    // Script not loaded yet, load it
                    const script = document.createElement('script');
                    script.src = vortemAnalyticsTabsConfig.pluginUrl + 'assets/js/mega-dash.js';
                    script.onload = () => {
                        if (typeof MegaDash !== 'undefined') {
                            window.vortemMegaDashInstance = new MegaDash();
                            setIsInitialized(true);
                        }
                    };
                    document.head.appendChild(script);
                }
            };

            // Wait a bit for DOM to be ready
            setTimeout(() => {
                setupNavigation();
                initAnalytics();
            }, 100);
        }, [isInitialized]);

        return React.createElement('div', { ref: containerRef, className: 'vortem-analytics-tab' });
    }

    // BI Analytics Hub Tab Component - wraps existing bi-analytics-hub.js - commented out
    /*
    function BIAnalyticsHubTab() {
        const containerRef = React.useRef(null);
        const initializationRef = React.useRef(false);

        useEffect(() => {
            if (!containerRef.current) return;
            
            // Reset initialization flag when component mounts/remounts
            initializationRef.current = false;

            // Create the BI Analytics Hub HTML structure
            containerRef.current.innerHTML = `
                <div class="wrap bi-analytics-hub-dashboard">
                    <div class="background-elements">
                        <div class="bg-blur bg-blur-1"></div>
                        <div class="bg-blur bg-blur-2"></div>
                        <div class="bg-blur bg-blur-3"></div>
                    </div>

                    <div class="container">
                        <div class="megadash-header">
                            <div class="megadash-header-left">
                                <h1 class="megadash-title">
                                    <span class="dashicons dashicons-chart-area"></span>
                                    ${vortemAnalyticsTabsConfig.strings.biAnalyticsHub}
                                </h1>
                                <span class="megadash-last-updated" id="bi-analytics-last-updated">
                                    ${vortemAnalyticsTabsConfig.strings.loading}
                                </span>
                            </div>
                            <div class="megadash-header-right">
                                <div class="modern-header-actions">
                                    <nav class="tabs" role="tablist">
                                        <button class="tab megadash-nav-tab" data-tab="analytics" role="tab">${vortemAnalyticsTabsConfig.strings.analytics}</button>
                                        <button class="tab megadash-nav-tab active" data-tab="bi-analytics-hub" role="tab">${vortemAnalyticsTabsConfig.strings.biAnalyticsHub}</button>
                                    </nav>
                                </div>
                            </div>
                        </div>

                        <div class="section">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <span class="title-line title-line-blue"></span>
                                    ${vortemAnalyticsTabsConfig.strings.performanceOverview}
                                </h2>
                                <p class="section-description">${vortemAnalyticsTabsConfig.strings.keyPerformanceIndicators}</p>
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

                        <div class="section">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <span class="title-line title-line-purple"></span>
                                    ${vortemAnalyticsTabsConfig.strings.analyticsInsights}
                                </h2>
                                <p class="section-description">${vortemAnalyticsTabsConfig.strings.deepDiveMetrics}</p>
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

                        <div class="section">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <span class="title-line title-line-pink"></span>
                                    ${vortemAnalyticsTabsConfig.strings.customerIntelligence}
                                </h2>
                                <p class="section-description">${vortemAnalyticsTabsConfig.strings.understandCustomerBehavior}</p>
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

                        <div class="section">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <span class="title-line title-line-green"></span>
                                    ${vortemAnalyticsTabsConfig.strings.marketAnalysis}
                                </h2>
                                <p class="section-description">${vortemAnalyticsTabsConfig.strings.comprehensiveMarketComparison}</p>
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

                        <div class="section">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <span class="title-line title-line-indigo"></span>
                                    ${vortemAnalyticsTabsConfig.strings.dataIntelligence}
                                </h2>
                                <p class="section-description">${vortemAnalyticsTabsConfig.strings.detailedAnalyticsTables}</p>
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
            `;
            
            // Set up tab navigation for BI Analytics header
            const setupBIAnalyticsTabs = () => {
                const navTabs = containerRef.current.querySelectorAll('.megadash-nav-tab');
                navTabs.forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetTab = tab.getAttribute('data-tab');
                        
                        // Update active state
                        navTabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        
                        // Trigger parent React component to switch tabs
                        if (window.vortemAnalyticsTabsApp && typeof window.vortemAnalyticsTabsApp.switchTab === 'function') {
                            window.vortemAnalyticsTabsApp.switchTab(targetTab);
                        } else {
                            // Fallback: update URL and reload
                            const url = new URL(window.location);
                            let page = 'vortem-analytics';
                            if (targetTab === 'bi-analytics-hub') {
                                page = 'vortem-bi-analytics-hub';
                            }
                            url.searchParams.set('page', page);
                            window.location.href = url.toString();
                        }
                    });
                });
            };
            
            // Set up biAnalyticsHubConfig BEFORE loading script
            // This must be set before the script runs, as the script checks for it immediately
            // Get current language for BI Analytics Hub
            const currentLanguage = vortemAnalyticsTabsConfig.locale ? vortemAnalyticsTabsConfig.locale.split('_')[0] : 'en';
            
            // Merge strings from vortemAnalyticsTabsConfig with any existing biAnalyticsHubConfig strings
            // Preserve all existing strings from PHP localization (if available)
            const existingStrings = (window.biAnalyticsHubConfig && window.biAnalyticsHubConfig.strings) || {};
            const mergedStrings = {
                ...existingStrings,
                // Add page-level strings from vortemAnalyticsTabsConfig (these override existing if present)
                analyticsDashboard: vortemAnalyticsTabsConfig.strings.analyticsDashboard,
                businessIntelligence: vortemAnalyticsTabsConfig.strings.businessIntelligence,
                analyticsHub: vortemAnalyticsTabsConfig.strings.analyticsHub,
                comprehensiveAnalytics: vortemAnalyticsTabsConfig.strings.comprehensiveAnalytics,
                performanceOverview: vortemAnalyticsTabsConfig.strings.performanceOverview,
                keyPerformanceIndicators: vortemAnalyticsTabsConfig.strings.keyPerformanceIndicators,
                analyticsInsights: vortemAnalyticsTabsConfig.strings.analyticsInsights,
                deepDiveMetrics: vortemAnalyticsTabsConfig.strings.deepDiveMetrics,
                customerIntelligence: vortemAnalyticsTabsConfig.strings.customerIntelligence,
                understandCustomerBehavior: vortemAnalyticsTabsConfig.strings.understandCustomerBehavior,
                marketAnalysis: vortemAnalyticsTabsConfig.strings.marketAnalysis,
                comprehensiveMarketComparison: vortemAnalyticsTabsConfig.strings.comprehensiveMarketComparison,
                dataIntelligence: vortemAnalyticsTabsConfig.strings.dataIntelligence,
                detailedAnalyticsTables: vortemAnalyticsTabsConfig.strings.detailedAnalyticsTables
            };
            
            // Set the config synchronously to ensure it's available before script initialization
            window.biAnalyticsHubConfig = {
                apiBaseUrl: vortemAnalyticsTabsConfig.apiBaseUrl,
                proxyUrl: vortemAnalyticsTabsConfig.proxyUrl,
                nonce: vortemAnalyticsTabsConfig.nonce,
                current_language: currentLanguage,
                strings: mergedStrings
            };
            
            // Force config to be set immediately (no async delay)
            // This ensures the script sees the config when it initializes
            if (typeof window.biAnalyticsHubConfig !== 'undefined') {
                VortemLogger.log('BI Analytics Hub: Config set with', Object.keys(mergedStrings).length, 'translation strings');
            }

            // Set up tab navigation
            setTimeout(setupBIAnalyticsTabs, 50);
            
            // Sync tab states when component mounts
            // This ensures BI Analytics tabs show correct active state
            const syncTabStates = () => {
                if (!containerRef.current) return;
                
                // Get current active tab from parent component
                let currentActiveTab = 'bi-analytics-hub'; // Default to BI Analytics since this component is mounted
                if (window.vortemAnalyticsTabsApp && typeof window.vortemAnalyticsTabsApp.getActiveTab === 'function') {
                    currentActiveTab = window.vortemAnalyticsTabsApp.getActiveTab();
                }
                
                const navTabs = containerRef.current.querySelectorAll('.megadash-nav-tab');
                navTabs.forEach(tab => {
                    const tabValue = tab.getAttribute('data-tab');
                    if (tabValue === currentActiveTab) {
                        tab.classList.add('active');
                    } else {
                        tab.classList.remove('active');
                    }
                });
            };
            
            // Sync immediately and after delays to ensure DOM is ready
            setTimeout(syncTabStates, 100);
            setTimeout(syncTabStates, 300);

            // Load and initialize the BI Analytics Hub script if not already loaded
            const initBIAnalytics = () => {
                // Ensure container exists first
                const container = containerRef.current?.querySelector('.bi-analytics-hub-dashboard');
                if (!container) {
                    VortemLogger.warn('BI Analytics Hub: Container not found, retrying...');
                    setTimeout(initBIAnalytics, 100);
                    return;
                }

                // Check if we've already started initialization for this mount
                if (initializationRef.current) {
                    // Already initializing, skip
                    return;
                }

                initializationRef.current = true;

                if (typeof window.BIAnalyticsHubInitialized === 'undefined') {
                    // Script not loaded yet, load it
                    const script = document.createElement('script');
                    script.src = vortemAnalyticsTabsConfig.pluginUrl + 'assets/js/bi-analytics-hub.js';
                    script.onload = () => {
                        window.BIAnalyticsHubInitialized = true;
                        
                        // Wait a bit for script to fully initialize, then trigger charts
                        setTimeout(() => {
                            if (typeof window.initBIAnalyticsCharts === 'function') {
                                window.initBIAnalyticsCharts();
                            } else {
                                VortemLogger.error('BI Analytics Hub: initBIAnalyticsCharts function not found');
                            }
                        }, 100);
                    };
                    script.onerror = () => {
                        VortemLogger.error('BI Analytics Hub: Failed to load script');
                        initializationRef.current = false;
                    };
                    document.head.appendChild(script);
                } else {
                    // Script already loaded, ensure config is updated first
                    // Wait a bit to ensure config is fully set before re-initializing
                    setTimeout(() => {
                        // Clear data and re-initialize charts with updated translations
                        if (typeof window.clearBIAnalyticsChartData === 'function') {
                            window.clearBIAnalyticsChartData();
                        }
                        
                        // Verify config is set before initializing
                        if (typeof window.biAnalyticsHubConfig === 'undefined' || !window.biAnalyticsHubConfig.strings) {
                            VortemLogger.warn('BI Analytics Hub: Config not ready, retrying...');
                            setTimeout(() => {
                                if (typeof window.initBIAnalyticsCharts === 'function') {
                                    window.initBIAnalyticsCharts();
                                }
                            }, 100);
                            return;
                        }
                        
                        // Re-initialize charts with fresh data and translations
                        if (typeof window.initBIAnalyticsCharts === 'function') {
                            window.initBIAnalyticsCharts();
                        } else {
                            VortemLogger.error('BI Analytics Hub: initBIAnalyticsCharts function not found after script load');
                        }
                    }, 200);
                }
            };

            // Wait a bit for DOM to be ready
            setTimeout(initBIAnalytics, 150);
            
            // Cleanup function to reset initialization when component unmounts
            return () => {
                initializationRef.current = false;
            };
        }, []); // Empty dependency array - run on mount/unmount

        return React.createElement('div', { ref: containerRef, className: 'vortem-bi-analytics-hub-tab' });
    }
    */

    // Lazy load components - only load when tab is active
    const LazyAnalyticsTab = lazy(() => {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ default: AnalyticsTab });
            }, 0);
        });
    });

    // BI Analytics Hub lazy loader - commented out
    /*
    const LazyBIAnalyticsHubTab = lazy(() => {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ default: BIAnalyticsHubTab });
            }, 0);
        });
    });
    */

    // Main Analytics Tabs App Component
    function AnalyticsTabsApp() {
        const [activeTab, setActiveTab] = useState(vortemAnalyticsTabsConfig.initialTab || 'analytics');

        // Expose switchTab function globally for navigation buttons
        useEffect(() => {
            if (typeof window !== 'undefined') {
                window.vortemAnalyticsTabsApp = {
                    switchTab: (tab) => {
                        setActiveTab(tab);
                    },
                    getActiveTab: () => {
                        return activeTab;
                    }
                };
            }
            return () => {
                if (typeof window !== 'undefined' && window.vortemAnalyticsTabsApp) {
                    delete window.vortemAnalyticsTabsApp;
                }
            };
        }, [activeTab]);

        useEffect(() => {
            // Update URL without page reload when tab changes
            const url = new URL(window.location);
            let page = 'vortem-analytics';
            // BI Analytics Hub condition commented out
            /* if (activeTab === 'bi-analytics-hub') {
                page = 'vortem-bi-analytics-hub';
            } else */
            url.searchParams.set('page', page);
            window.history.pushState({}, '', url);

            // Update navigation tab active state in both headers
            // Use a function that retries if tabs aren't found immediately
            const updateTabStates = (attempt = 0) => {
                const navTabs = document.querySelectorAll('.megadash-nav-tab');
                
                if (navTabs.length === 0 && attempt < 10) {
                    // Tabs not found yet, retry after a short delay
                    setTimeout(() => updateTabStates(attempt + 1), 50);
                    return;
                }
                
                navTabs.forEach(tab => {
                    const tabValue = tab.getAttribute('data-tab');
                    if (tabValue === activeTab) {
                        tab.classList.add('active');
                    } else {
                        tab.classList.remove('active');
                    }
                });
            };
            
            updateTabStates();
        }, [activeTab]);

        const handleTabChange = (tab) => {
            setActiveTab(tab);
        };

        const renderTabContent = () => {
            if (activeTab === 'analytics') {
                return React.createElement(Suspense, { fallback: React.createElement(LoadingFallback) },
                    React.createElement(LazyAnalyticsTab)
                );
            } /* BI Analytics Hub tab rendering commented out
            else if (activeTab === 'bi-analytics-hub') {
                return React.createElement(Suspense, { fallback: React.createElement(LoadingFallback) },
                    React.createElement(LazyBIAnalyticsHubTab)
                );
            } */
            return null;
        };

        return React.createElement('div', { className: 'vortem-analytics-tabs-container' },
            React.createElement('div', { className: 'vortem-tab-content' },
                renderTabContent()
            )
        );
    }

    // Initialize the app when DOM is ready and React is available
    function initializeApp() {
        const rootElement = document.getElementById('vortem-analytics-tabs-root');
        if (!rootElement) {
            return;
        }

        // Wait for React to be available
        if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
            setTimeout(initializeApp, 100);
            return;
        }

        try {
            const root = ReactDOM.createRoot(rootElement);
            root.render(React.createElement(AnalyticsTabsApp));
        } catch (error) {
            VortemLogger.error('Error initializing Analytics Tabs App:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApp);
    } else {
        // Small delay to ensure React is loaded
        setTimeout(initializeApp, 50);
    }
})();
