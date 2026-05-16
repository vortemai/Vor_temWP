// External Library: Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | Used for UI icon rendering (lucide.createIcons())
// External Library: jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation
(function() {
    'use strict';

    const OrdersComponent = {
        init: function(container) {
            // CRITICAL FIX: Always update container and allow re-initialization
            // This ensures component refreshes on every tab switch
            this.container = container;
            this.initialized = true;

            if (typeof window.vortemOrdersScriptLoaded === 'undefined') {
                this.loadOrdersScript();
            } else {
                // Always re-initialize when switching tabs to ensure fresh state
                this.initializeOrders();
            }
        },

        loadOrdersScript: function() {
            if (window.vortemOrdersScriptLoaded) {
                setTimeout(() => this.initializeOrders(), 100);
                return;
            }

            const loadScript = (url) => {
                return new Promise((resolve, reject) => {
                    // Check if script is already loaded (with or without query params)
                    const baseUrl = url.split('?')[0];
                    const existing = document.querySelector(`script[src="${url}"], script[src="${baseUrl}"], script[src^="${baseUrl}?"]`);
                    if (existing) {
                        // Script already loaded, resolve immediately
                        resolve();
                        return;
                    }
                    
                    // Load script without cache-busting for production stability
                    // Cache-busting is handled by WordPress versioning in class-vortem-admin.php
                    const script = document.createElement('script');
                    script.src = url;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            };

            Promise.all([
                loadScript(vortemTabData.lucideIconsUrl),
                loadScript(vortemTabData.ordersScriptUrl)
            ]).then(() => {
                window.vortemOrdersScriptLoaded = true;
                setTimeout(() => {
                    this.initializeOrders();
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }, 150);
            }).catch((error) => {
                VortemLogger.error('Failed to load orders dependencies:', error);
                this.showError('Failed to load orders functionality');
            });
        },

        initializeOrders: function() {
            const init = () => {
                if (typeof jQuery !== 'undefined') {
                    const $ = jQuery;
                    
                    if ($('#vortem-orders-app').length || this.container) {
                        // Update order status select options with translations
                        this.updateOrderStatusTranslations();
                        
                        if (!window.vortemOrdersInitialized) {
                            window.vortemOrdersInitialized = true;
                            
                            if (typeof window.initVortemOrders === 'function') {
                                window.initVortemOrders();
                            }
                        }
                        
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                        
                        // CRITICAL FIX: Always force reload orders when switching to this tab
                        // This ensures latest code changes and data are always visible
                        // Without this, stale cached content is displayed
                        const tbody = $('#orders-tbody');
                        if (tbody.length) {
                            // Always refresh orders data when tab is switched to
                            // This prevents stale data and ensures latest component code runs
                            if (typeof window.vortemLoadOrders === 'function') {
                                // Force reload to get fresh data and execute latest code
                                window.vortemLoadOrders(1);
                            } else {
                                // Fallback to AJAX if orders.js not loaded yet
                                this.loadOrdersData();
                            }
                        }
                    }
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                setTimeout(init, 100);
            }
        },

        updateOrderStatusTranslations: function() {
            if (typeof vortemOrders === 'undefined' || !vortemOrders.strings) {
                return;
            }

            const statusSelect = document.querySelector('#filter-status');
            if (!statusSelect) {
                return;
            }

            const strings = vortemOrders.strings;
            const statusMap = {
                'pending': strings.pending_payment || 'Pending payment',
                'processing': strings.processing || 'Processing',
                'on-hold': strings.on_hold || 'On hold',
                'completed': strings.completed || 'Completed',
                'cancelled': strings.cancelled || 'Cancelled',
                'refunded': strings.refunded || 'Refunded',
                'failed': strings.failed || 'Failed',
                'draft': strings.draft || 'Draft'
            };

            // Update each option text if translation exists
            Array.from(statusSelect.options).forEach(option => {
                if (option.value === 'all') {
                    return; // Skip "All Statuses" option
                }
                
                // Try to match the status key
                const statusKey = option.value.replace('wc-', '');
                if (statusMap[statusKey]) {
                    option.textContent = statusMap[statusKey];
                } else {
                    // Try to find translation by matching the current text
                    const currentText = option.textContent.toLowerCase().trim();
                    for (const [key, translation] of Object.entries(statusMap)) {
                        if (currentText.includes(key) || currentText === key) {
                            option.textContent = translation;
                            break;
                        }
                    }
                }
            });
        },

        ensureEventListeners: function() {
            // Force a reload of orders to ensure send buttons are rendered
            // This ensures the send button appears when switching tabs
            if (typeof jQuery !== 'undefined') {
                const $ = jQuery;
                const tbody = $('#orders-tbody');
                
                // Check if orders.js is loaded and use its loadOrders function
                // This ensures send buttons are rendered correctly
                if (typeof window.vortemLoadOrders === 'function') {
                    // Use the orders.js loadOrders function which includes send button logic
                    window.vortemLoadOrders(1);
                } else if (typeof vortemOrders !== 'undefined') {
                    // Fallback to AJAX method if orders.js function not available
                    if (tbody.length) {
                        this.loadOrdersData();
                    }
                }
            }
        },

        loadOrdersData: function() {
            if (typeof jQuery === 'undefined' || typeof vortemOrders === 'undefined') {
                return;
            }

            const $ = jQuery;
            const tbody = $('#orders-tbody');
            
            if (!tbody.length) {
                return;
            }

            tbody.html('<tr><td colspan="8" class="loading"><div class="spinner"></div>Loading orders...</td></tr>');

            $.ajax({
                url: vortemOrders.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_get_orders',
                    nonce: vortemOrders.nonce,
                    page: 1,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (response.data.html) {
                            tbody.html(response.data.html);
                        }
                    } else {
                        tbody.html('<tr><td colspan="8" class="error">Failed to load orders</td></tr>');
                    }
                },
                error: function() {
                    tbody.html('<tr><td colspan="8" class="error">Error loading orders</td></tr>');
                }
            });
        },

        showError: function(message) {
            if (this.container) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.cssText = 'padding: 20px; text-align: center; color: #d63638;';
                errorDiv.textContent = message;
                this.container.innerHTML = '';
                this.container.appendChild(errorDiv);
            }
        }
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = OrdersComponent;
    } else {
        window.OrdersComponent = OrdersComponent;
    }

    if (typeof window !== 'undefined') {
        window.OrdersComponent = OrdersComponent;
    }

    return OrdersComponent;
})();

