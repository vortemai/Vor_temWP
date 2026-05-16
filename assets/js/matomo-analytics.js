(function() {
    'use strict';

    if (typeof window.MatomoAnalytics !== 'undefined') {
        return; // Already initialized
    }

    const MatomoAnalytics = {
        config: null,
        currentPeriod: 'day',
        currentDate: 'today',
        data: {},

        init: function() {
            this.config = window.vortemAnalyticsTabsConfig || {};
            this.setupEventListeners();
            this.loadData();
        },

        setupEventListeners: function() {
            const periodSelect = document.getElementById('matomo-period-select');
            if (periodSelect) {
                periodSelect.addEventListener('change', (e) => {
                    const [period, date] = e.target.value.split(',');
                    this.currentPeriod = period;
                    this.currentDate = date;
                    this.loadData();
                });
            }
        },

        async loadData() {
            try {
                await Promise.all([
                    this.loadVisitsSummary(),
                    this.loadPageUrls(),
                    this.loadPageTitles(),
                    this.loadCountryStats(),
                    this.loadCityStats(),
                    this.loadEntryPages(),
                    this.loadExitPages(),
                    this.loadRealtimeVisit(),
                ]);
                this.updateLastUpdated();
            } catch (error) {
                VortemLogger.error('Error loading Matomo data:', error);
                this.showError('Error loading data. Please try again.');
            }
        },

        async fetchEndpoint(endpoint, params = {}) {
            const queryParams = new URLSearchParams();
            if (this.currentPeriod) queryParams.append('period', this.currentPeriod);
            if (this.currentDate) queryParams.append('date', this.currentDate);
            
            Object.keys(params).forEach(key => {
                queryParams.append(key, params[key]);
            });

            const url = `${this.config.matomoProxyUrl}${endpoint}?${queryParams.toString()}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                // Handle non-JSON responses (like tracking script)
                return await response.text();
            }
        },

        async loadVisitsSummary() {
            try {
                const data = await this.fetchEndpoint('visits-summary');
                this.data.visitsSummary = data;
                this.renderSummaryCards(data);
            } catch (error) {
                VortemLogger.error('Error loading visits summary:', error);
            }
        },

        async loadPageUrls() {
            try {
                const data = await this.fetchEndpoint('page-urls');
                this.data.pageUrls = data;
                this.renderTable('top-pages-table', data.data || [], [
                    { key: 'label', label: 'Page URL' },
                    { key: 'nb_visits', label: 'Visits' },
                    { key: 'nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'nb_actions', label: 'Actions' },
                    { key: 'bounce_rate', label: 'Bounce Rate' },
                    { key: 'avg_time_on_page', label: 'Avg. Time (s)' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading page URLs:', error);
            }
        },

        async loadPageTitles() {
            try {
                const data = await this.fetchEndpoint('page-titles');
                this.data.pageTitles = data;
                this.renderTable('top-titles-table', data.data || [], [
                    { key: 'label', label: 'Page Title' },
                    { key: 'nb_visits', label: 'Visits' },
                    { key: 'nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'nb_actions', label: 'Actions' },
                    { key: 'bounce_rate', label: 'Bounce Rate' },
                    { key: 'avg_time_on_page', label: 'Avg. Time (s)' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading page titles:', error);
            }
        },

        async loadCountryStats() {
            try {
                const data = await this.fetchEndpoint('country-stats');
                this.data.countryStats = data;
                this.renderTable('countries-table', data.data || [], [
                    { key: 'label', label: 'Country' },
                    { key: 'nb_visits', label: 'Visits' },
                    { key: 'nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'nb_actions', label: 'Actions' },
                    { key: 'bounce_rate', label: 'Bounce Rate' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading country stats:', error);
            }
        },

        async loadCityStats() {
            try {
                const data = await this.fetchEndpoint('city-stats');
                this.data.cityStats = data;
                this.renderTable('cities-table', data.data || [], [
                    { key: 'label', label: 'City' },
                    { key: 'nb_visits', label: 'Visits' },
                    { key: 'nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'nb_actions', label: 'Actions' },
                    { key: 'bounce_rate', label: 'Bounce Rate' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading city stats:', error);
            }
        },

        async loadEntryPages() {
            try {
                const data = await this.fetchEndpoint('entry-page-urls');
                this.data.entryPages = data;
                this.renderTable('entry-pages-table', data.data || [], [
                    { key: 'label', label: 'Entry Page' },
                    { key: 'entry_nb_visits', label: 'Visits' },
                    { key: 'entry_nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'entry_nb_actions', label: 'Actions' },
                    { key: 'bounce_rate', label: 'Bounce Rate' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading entry pages:', error);
            }
        },

        async loadExitPages() {
            try {
                const data = await this.fetchEndpoint('exit-page-urls');
                this.data.exitPages = data;
                this.renderTable('exit-pages-table', data.data || [], [
                    { key: 'label', label: 'Exit Page' },
                    { key: 'exit_nb_visits', label: 'Visits' },
                    { key: 'exit_nb_uniq_visitors', label: 'Unique Visitors' },
                    { key: 'exit_rate', label: 'Exit Rate' },
                ]);
            } catch (error) {
                VortemLogger.error('Error loading exit pages:', error);
            }
        },

        async loadRealtimeVisit() {
            try {
                const data = await this.fetchEndpoint('realtime-visit', { lastMinutes: 30 });
                this.data.realtimeVisit = data;
                // Display realtime data if needed
                if (data && (data.visits > 0 || data.visitors > 0)) {
                    const container = document.getElementById('matomo-realtime-visits');
                    if (container) {
                        container.innerHTML = `
                            <div class="matomo-realtime-card">
                                <div class="matomo-realtime-item">
                                    <span class="matomo-realtime-label">${this.config.strings?.visitors || 'Visitors'}:</span>
                                    <span class="matomo-realtime-value">${data.visitors || 0}</span>
                                </div>
                                <div class="matomo-realtime-item">
                                    <span class="matomo-realtime-label">${this.config.strings?.visits || 'Visits'}:</span>
                                    <span class="matomo-realtime-value">${data.visits || 0}</span>
                                </div>
                                <div class="matomo-realtime-item">
                                    <span class="matomo-realtime-label">${this.config.strings?.actions || 'Actions'}:</span>
                                    <span class="matomo-realtime-value">${data.actions || 0}</span>
                                </div>
                            </div>
                        `;
                    }
                }
            } catch (error) {
                VortemLogger.error('Error loading realtime visit:', error);
            }
        },

        renderSummaryCards(data) {
            const container = document.getElementById('matomo-summary-cards');
            if (!container) return;

            const cards = [
                {
                    icon: 'dashicons-visibility',
                    label: this.config.strings?.visits || 'Visits',
                    value: data.nb_visits || 0,
                },
                {
                    icon: 'dashicons-groups',
                    label: this.config.strings?.uniqueVisitors || 'Unique Visitors',
                    value: data.nb_uniq_visitors || 0,
                },
                {
                    icon: 'dashicons-chart-line',
                    label: this.config.strings?.actions || 'Actions',
                    value: data.nb_actions || 0,
                },
                {
                    icon: 'dashicons-clock',
                    label: this.config.strings?.avgTimeOnSite || 'Avg. Time on Site',
                    value: this.formatTime(data.avg_time_on_site || 0),
                },
                {
                    icon: 'dashicons-arrow-left-alt',
                    label: this.config.strings?.bounceRate || 'Bounce Rate',
                    value: data.bounce_rate || '0%',
                },
                {
                    icon: 'dashicons-admin-links',
                    label: this.config.strings?.actions + ' / ' + this.config.strings?.visits || 'Actions/Visit',
                    value: (data.nb_actions_per_visit || 0).toFixed(1),
                },
            ];

            container.innerHTML = cards.map(card => `
                <div class="matomo-card">
                    <div class="matomo-card-icon">
                        <span class="dashicons ${card.icon}"></span>
                    </div>
                    <div class="matomo-card-content">
                        <div class="matomo-card-label">${card.label}</div>
                        <div class="matomo-card-value">${card.value}</div>
                    </div>
                </div>
            `).join('');
        },

        renderTable(containerId, data, columns) {
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!data || data.length === 0) {
                container.innerHTML = `<div class="matomo-empty">${this.config.strings?.noData || 'No data available'}</div>`;
                return;
            }

            const table = document.createElement('table');
            table.className = 'matomo-table';

            // Header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            columns.forEach(col => {
                const th = document.createElement('th');
                th.textContent = col.label;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);

            // Body
            const tbody = document.createElement('tbody');
            data.slice(0, 10).forEach(row => {
                const tr = document.createElement('tr');
                columns.forEach(col => {
                    const td = document.createElement('td');
                    let value = row[col.key];
                    
                    // Handle null/undefined values
                    if (value === null || value === undefined) {
                        value = '-';
                    }
                    
                    // Format specific values
                    if (col.key === 'avg_time_on_page' && typeof value === 'number') {
                        value = this.formatTime(value);
                    } else if (typeof value === 'string' && value.includes('%')) {
                        // Keep percentage values as is
                        value = value;
                    } else if (typeof value === 'number') {
                        // Format numbers
                        value = value.toLocaleString();
                    }
                    
                    td.textContent = value;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);

            container.innerHTML = '';
            container.appendChild(table);
        },

        formatTime(seconds) {
            if (!seconds || seconds === 0) return '0s';
            if (seconds < 60) return Math.round(seconds) + 's';
            const minutes = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            return `${minutes}m ${secs}s`;
        },

        updateLastUpdated() {
            const element = document.getElementById('matomo-last-updated');
            if (element) {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                element.textContent = `${this.config.strings?.last_updated || 'Last updated:'} ${timeString}`;
            }
        },

        showError(message) {
            const container = document.getElementById('matomo-summary-cards');
            if (container) {
                container.innerHTML = `<div class="matomo-error">${message}</div>`;
            }
        },
    };

    window.MatomoAnalytics = MatomoAnalytics;
})();

