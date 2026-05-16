(function() {
    'use strict';

    class TabController {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            this.activeTab = this.getInitialTab();
            this.loadedComponents = new Set();
            this.componentLoaders = {
                'products': () => this.loadComponent('products', 'products-component.js'),
                'orders': () => this.loadComponent('orders', 'orders-component.js')
            };
            
            if (this.container) {
                this.init();
            }
        }

        getInitialTab() {
            const urlParams = new URLSearchParams(window.location.search);
            const pageParam = urlParams.get('page');
            // Check page parameter first (consistent with analytics pattern)
            if (pageParam === 'vortem-orders') {
                return 'orders';
            } else if (pageParam === 'vortem-products') {
                return 'products';
            }
            // Fallback to tab parameter for backward compatibility
            const tabParam = urlParams.get('tab');
            return (tabParam === 'orders' || tabParam === 'products') ? tabParam : 'products';
        }

        init() {
            this.setupTabs();
            this.loadInitialTab();
            this.setupHistory();
        }

        setupTabs() {
            const tabButtons = this.container.querySelectorAll('.tab[data-tab]');
            tabButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const tabName = button.getAttribute('data-tab');
                    if (tabName) {
                        this.switchTab(tabName);
                    }
                });
            });
        }

        switchTab(tabName) {
            if (this.activeTab === tabName) {
                return;
            }

            this.activeTab = tabName;
            this.updateTabButtons();
            this.updateURL();
            this.loadTabContent(tabName);
        }

        updateTabButtons() {
            const tabButtons = this.container.querySelectorAll('.tab[data-tab]');
            tabButtons.forEach(button => {
                const tabName = button.getAttribute('data-tab');
                if (tabName === this.activeTab) {
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');
                } else {
                    button.classList.remove('active');
                    button.setAttribute('aria-selected', 'false');
                }
            });
        }

        updateURL() {
            const url = new URL(window.location);
            // Update page parameter (consistent with analytics pattern)
            const page = this.activeTab === 'orders' ? 'vortem-orders' : 'vortem-products';
            url.searchParams.set('page', page);
            url.searchParams.delete('tab'); // Remove tab parameter if it exists
            window.history.pushState({ tab: this.activeTab }, '', url);
        }

        setupHistory() {
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.tab) {
                    this.activeTab = e.state.tab;
                    this.updateTabButtons();
                    this.loadTabContent(this.activeTab);
                } else {
                    this.activeTab = this.getInitialTab();
                    this.updateTabButtons();
                    this.loadTabContent(this.activeTab);
                }
            });
        }

        loadInitialTab() {
            this.updateTabButtons();
            this.loadTabContent(this.activeTab);
        }

        async loadTabContent(tabName) {
            const panel = this.container.querySelector(`#panel-${tabName}`);
            if (!panel) {
                VortemLogger.error(`Panel #panel-${tabName} not found`);
                return;
            }

            // Hide all panels first to prevent layout issues
            this.hideOtherPanels(tabName);
            
            // Show the active panel
            panel.classList.add('active');
            panel.style.display = 'block';
            panel.setAttribute('aria-hidden', 'false');

            if (!this.loadedComponents.has(tabName) && this.componentLoaders[tabName]) {
                try {
                    await this.componentLoaders[tabName]();
                    this.loadedComponents.add(tabName);
                } catch (error) {
                    VortemLogger.error(`Error loading ${tabName} component:`, error);
                    this.showError(panel, `Failed to load ${tabName} component`);
                }
            } else {
                // CRITICAL FIX: Always re-initialize component when switching tabs
                // This ensures components refresh their state and show latest code changes
                // Even if component script is already loaded, we must call init() to refresh
                const componentName = tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Component';
                const component = window[componentName];
                if (component && typeof component.init === 'function') {
                    component.init(panel);
                }
            }
        }

        hideOtherPanels(activeTab) {
            const allPanels = this.container.querySelectorAll('.panel');
            allPanels.forEach(panel => {
                if (panel.id !== `panel-${activeTab}`) {
                    panel.classList.remove('active');
                    panel.style.display = 'none';
                    panel.setAttribute('aria-hidden', 'true');
                }
            });
        }

        async loadComponent(name, scriptName) {
            try {
                if (this.loadedComponents.has(name)) {
                    // Component already loaded, but still initialize it
                    const componentName = name.charAt(0).toUpperCase() + name.slice(1) + 'Component';
                    const component = window[componentName];
                    if (component && typeof component.init === 'function') {
                        const panel = this.container.querySelector(`#panel-${name}`);
                        if (panel) {
                            component.init(panel);
                        }
                    }
                    return;
                }

                const scriptUrl = vortemTabData.baseUrl + 'components/' + scriptName;
                
                // Load component script (cache-busting handled by WordPress versioning)
                const script = document.createElement('script');
                script.src = scriptUrl;
                script.type = 'text/javascript';
                
                await new Promise((resolve, reject) => {
                    script.onload = () => {
                        const componentName = name.charAt(0).toUpperCase() + name.slice(1) + 'Component';
                        const component = window[componentName];
                        
                        if (component && typeof component.init === 'function') {
                            const panel = this.container.querySelector(`#panel-${name}`);
                            if (panel) {
                                component.init(panel);
                            }
                        }
                        this.loadedComponents.add(name);
                        resolve();
                    };
                    script.onerror = () => {
                        reject(new Error(`Failed to load ${scriptName}`));
                    };
                    document.head.appendChild(script);
                });
            } catch (error) {
                VortemLogger.error(`Error loading ${name} component:`, error);
                throw error;
            }
        }

        showError(container, message) {
            container.innerHTML = `<div class="error-message" style="padding: 20px; text-align: center; color: #d63638;">
                <p>${message}</p>
            </div>`;
        }
    }

    // Initialize immediately to prevent layout flash on hard refresh
    function initTabController() {
        const app = document.getElementById('vortem-products-app');
        if (app) {
            // Hide all panels immediately before JavaScript fully loads
            const panels = app.querySelectorAll('.panel');
            panels.forEach(panel => {
                if (!panel.classList.contains('active')) {
                    panel.style.display = 'none';
                }
            });
            new TabController('vortem-products-app');
        }
    }

    if (document.readyState === 'loading') {
        // Run immediately to prevent flash
        initTabController();
        // Also run on DOMContentLoaded as backup
        document.addEventListener('DOMContentLoaded', initTabController);
    } else {
        initTabController();
    }
})();

