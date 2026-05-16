// External Library: Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | SVG icon paths from lucide-react used for insights stat cards and audit items
// Translation helper function
function getTranslatedString(key, fallback) {
  if (typeof vortemInsights !== 'undefined' && vortemInsights.strings && vortemInsights.strings[key]) {
    return vortemInsights.strings[key];
  }
  return fallback;
}

// Insights Dashboard Implementation
class PageSpeedDashboard {
  constructor() {
    this.data = null;
    this.currentDevice = 'desktop';
    this.auditsExpanded = false;
    this.lastUpdatedDotsInterval = null;
    this.insightsExpanded = false;
    this.initialAuditsToShow = 4;
    this.initialInsightsToShow = 4;
    this.ajaxUrl = (typeof vortemInsights !== 'undefined' && vortemInsights.ajaxUrl) ? vortemInsights.ajaxUrl : '';
    this.nonce = (typeof vortemInsights !== 'undefined' && vortemInsights.nonce) ? vortemInsights.nonce : '';
    // Use site URL from PHP (dynamic); ensure trailing slash
    const baseUrl = (typeof vortemInsights !== 'undefined' && vortemInsights.siteUrl) ? vortemInsights.siteUrl : (typeof window !== 'undefined' && window.location && window.location.origin ? window.location.origin : '');
    this.currentUrl = baseUrl ? baseUrl.replace(/\/?$/, '/') : '';
    
    this.init();
  }

  init() {
    this.setupEventListeners();
    // Set desktop as default active device
    this.setDevice('desktop');
    this.loadData();
  }

  setupEventListeners() {
    // Device toggle buttons
    const btnDesktop = document.getElementById('btn-desktop');
    const btnMobile = document.getElementById('btn-mobile');
    
    if (btnDesktop) {
      btnDesktop.addEventListener('click', () => {
        this.setDevice('desktop');
      });
    }
    
    if (btnMobile) {
      btnMobile.addEventListener('click', () => {
        this.setDevice('mobile');
      });
    }

    // Refresh button
    const btnRefresh = document.getElementById('btn-refresh');
    if (btnRefresh) {
      btnRefresh.addEventListener('click', () => {
        this.refetchData();
      });
    }

    // Expand/collapse buttons
    const btnAuditsExpand = document.getElementById('btn-audits-expand');
    if (btnAuditsExpand) {
      btnAuditsExpand.addEventListener('click', () => {
        this.toggleAudits();
      });
    }

    // Retry buttons
    const btnRetry = document.getElementById('btn-retry');
    if (btnRetry) {
      btnRetry.addEventListener('click', () => {
        this.loadData();
      });
    }

    const btnRetryEmpty = document.getElementById('btn-retry-empty');
    if (btnRetryEmpty) {
      btnRetryEmpty.addEventListener('click', () => {
        this.loadData();
      });
    }
  }

  async refetchData() {
    this.showLoading();
    
    const refreshIcon = document.querySelector('.insights-refresh-icon');
    const btnRefresh = document.getElementById('btn-refresh');
    
    if (refreshIcon) {
      refreshIcon.classList.add('spinning');
    }
    if (btnRefresh) {
      btnRefresh.disabled = true;
    }
    
    try {
      const formData = new FormData();
      formData.append('action', 'vortem_refetch_insights');
      formData.append('nonce', this.nonce);
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 180000);
      
      const response = await fetch(this.ajaxUrl, {
        method: 'POST',
        body: formData,
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      
      if (response && response.ok) {
        const result = await response.json();
        
        if (result.success && result.data) {
          if (result.data.desktop_data || result.data.mobile_data) {
            this.data = result.data;
            setTimeout(() => this.render(), 100);
          } else {
            await this.loadData();
          }
        } else {
          const errorMessage = result.data && result.data.message 
            ? result.data.message 
            : getTranslatedString('failed_to_refetch', 'Failed to refetch data');
          this.showError(errorMessage);
        }
      } else {
        const errorText = await response.text();
        VortemLogger.error('Refetch API Error:', errorText);
        const errorMessage = getTranslatedString('failed_to_refetch', 'Failed to refetch data') + ': ' + (response.statusText || getTranslatedString('unknown_error', 'Unknown error'));
        this.showError(errorMessage);
      }
    } catch (error) {
      VortemLogger.error('Error refetching Insights data:', error);
      this.showError(error.message || getTranslatedString('failed_to_refetch_error', 'Failed to refetch data. Please check your connection and try again.'));
    } finally {
      if (refreshIcon) {
        refreshIcon.classList.remove('spinning');
      }
      if (btnRefresh) {
        btnRefresh.disabled = false;
      }
    }
  }

  async loadData() {
    this.showLoading();
    
    const refreshIcon = document.querySelector('.insights-refresh-icon');
    if (refreshIcon) {
      refreshIcon.classList.add('spinning');
    }
    
    try {
      const formData = new FormData();
      formData.append('action', 'vortem_get_insights');
      formData.append('nonce', this.nonce);
      formData.append('url', this.currentUrl);
      
      const response = await fetch(this.ajaxUrl, {
        method: 'POST',
        body: formData
      });
      
      if (response && response.ok) {
        const result = await response.json();
        
        if (result.success && result.data) {
          this.data = result.data;
          setTimeout(() => {
            this.render();
          }, 100);
        } else {
          const errorMessage = result.data && result.data.message 
            ? result.data.message 
            : getTranslatedString('failed_to_load_insights', 'Failed to load Insights data');
          this.showError(errorMessage);
        }
      } else {
        const errorText = await response.text();
        VortemLogger.error('API Error:', errorText);
        this.showError(getTranslatedString('failed_to_load_insights', 'Failed to load Insights data') + ': ' + (response.statusText || getTranslatedString('unknown_error', 'Unknown error')));
      }
    } catch (error) {
      VortemLogger.error('Error loading Insights data:', error);
      this.showError(error.message || getTranslatedString('failed_to_load_insights_error', 'Failed to load Insights data. Please check your connection and try again.'));
    } finally {
      if (refreshIcon) {
        refreshIcon.classList.remove('spinning');
      }
    }
  }

  setDevice(device) {
    this.currentDevice = device;
    
    const btnDesktop = document.getElementById('btn-desktop');
    const btnMobile = document.getElementById('btn-mobile');
    
    if (btnDesktop) {
      btnDesktop.classList.toggle('active', device === 'desktop');
    }
    if (btnMobile) {
      btnMobile.classList.toggle('active', device === 'mobile');
    }
    
    this.render();
  }

  startLastUpdatedDotsAnimation() {
    this.stopLastUpdatedDotsAnimation();
    const el = document.getElementById('insights-last-updated-value');
    if (!el) return;
    el.classList.add('insights-last-updated-loading');
    let count = 0;
    this.lastUpdatedDotsInterval = setInterval(() => {
      count = (count % 3) + 1;
      el.textContent = '.'.repeat(count);
    }, 350);
  }

  stopLastUpdatedDotsAnimation() {
    if (this.lastUpdatedDotsInterval) {
      clearInterval(this.lastUpdatedDotsInterval);
      this.lastUpdatedDotsInterval = null;
    }
    const el = document.getElementById('insights-last-updated-value');
    if (el) el.classList.remove('insights-last-updated-loading');
  }

  showLoading() {
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const emptyState = document.getElementById('empty-state');
    const main = document.querySelector('.insights-main');
    
    if (errorState) errorState.style.display = 'none';
    if (emptyState) emptyState.style.display = 'none';
    if (loadingState) loadingState.style.display = 'flex';
    if (main) main.style.display = 'none';
    this.startLastUpdatedDotsAnimation();
  }

  showError(message) {
    this.stopLastUpdatedDotsAnimation();
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const errorMessage = document.getElementById('error-message');
    const emptyState = document.getElementById('empty-state');
    const main = document.querySelector('.insights-main');
    
    if (loadingState) loadingState.style.display = 'none';
    if (errorState) errorState.style.display = 'flex';
    if (errorMessage) errorMessage.textContent = message;
    if (emptyState) emptyState.style.display = 'none';
    if (main) main.style.display = 'none';
    const el = document.getElementById('insights-last-updated-value');
    if (el) el.textContent = '—';
  }

  showEmpty() {
    this.stopLastUpdatedDotsAnimation();
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const emptyState = document.getElementById('empty-state');
    const main = document.querySelector('.insights-main');
    
    if (loadingState) loadingState.style.display = 'none';
    if (errorState) errorState.style.display = 'none';
    if (emptyState) emptyState.style.display = 'flex';
    if (main) main.style.display = 'none';
    const el = document.getElementById('insights-last-updated-value');
    if (el) el.textContent = '—';
  }

  render() {
    if (!this.data) {
      this.showEmpty();
      return;
    }

    // Check if new API structure exists (dashboard)
    const isNewStructure = this.data.dashboard && this.data.dashboard[this.currentDevice];
    
    let deviceData, result, categories;
    
    if (isNewStructure) {
      // New API structure: dashboard.desktop or dashboard.mobile
      deviceData = this.data.dashboard[this.currentDevice];
      result = deviceData;
      categories = deviceData.coreWebVitals || {};
    } else {
      // Old API structure: desktop_data/mobile_data with lighthouseResult
      deviceData = this.currentDevice === 'desktop' 
        ? this.data.desktop_data 
        : this.data.mobile_data;

      if (!deviceData || !deviceData.lighthouseResult) {
        this.showEmpty();
        return;
      }

      result = deviceData.lighthouseResult;
      categories = result.categories;
    }

    // Hide loading/error states
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const emptyState = document.getElementById('empty-state');
    const main = document.querySelector('.insights-main');
    
    if (loadingState) loadingState.style.display = 'none';
    if (errorState) errorState.style.display = 'none';
    if (emptyState) emptyState.style.display = 'none';
    if (main) main.style.display = 'block';

    // Update header
    this.renderHeader(deviceData, isNewStructure);

    // Render quick stats
    if (isNewStructure) {
      this.renderQuickStatsNew(deviceData);
    } else {
      this.renderQuickStats(result);
    }

    // Render core web vitals
    if (isNewStructure) {
      this.renderCoreWebVitalsNew(deviceData.coreWebVitals);
    } else {
      this.renderCoreWebVitals(categories);
    }

    // Render audits
    if (isNewStructure) {
      this.renderAuditsNew(deviceData.performanceAudits);
    } else {
      this.renderAudits(result);
    }

    // Render quick insights
    if (isNewStructure) {
      this.renderQuickInsightsNew(deviceData.quickInsights);
    } else {
      this.renderQuickInsights(result);
    }

    // Render config
    if (isNewStructure) {
      this.renderConfigNew(deviceData.configuration);
    } else {
      this.renderConfig(result);
    }

    // Update refresh button spinner
    const refreshIcon = document.querySelector('.insights-refresh-icon');
    if (refreshIcon) {
      refreshIcon.classList.remove('spinning');
    }
  }

  renderHeader(deviceData, isNewStructure) {
    const lastUpdatedElement = document.getElementById('insights-last-updated-value');
    if (!lastUpdatedElement) return;
    this.stopLastUpdatedDotsAnimation();
    // Use lastUpdated from API (dashboard.header.lastUpdated) - date only, no time
    const lastUpdated = this.data?.dashboard?.header?.lastUpdated;
    lastUpdatedElement.textContent = lastUpdated || '—';
  }

  renderQuickStatsNew(deviceData) {
    const container = document.getElementById('insights-quick-stats');
    if (!container) return;
    
    container.innerHTML = '';

    const topLevelMetrics = deviceData.topLevelMetrics || {};
    
    const stats = [
      {
        label: getTranslatedString('page_load', 'Page Load'),
        value: topLevelMetrics.pageLoad ? topLevelMetrics.pageLoad.value : 'N/A',
        change: topLevelMetrics.pageLoad ? topLevelMetrics.pageLoad.change : '-',
        iconBg: '#044466',
        bgColor: 'rgba(4, 68, 102, 0.1)',
        borderColor: 'rgba(4, 68, 102, 0.2)'
      },
      {
        label: getTranslatedString('requests', 'Requests'),
        value: topLevelMetrics.requests ? topLevelMetrics.requests.value : 'N/A',
        change: topLevelMetrics.requests ? topLevelMetrics.requests.change : '-',
        iconBg: '#089E9B',
        bgColor: 'rgba(8, 158, 155, 0.1)',
        borderColor: 'rgba(8, 158, 155, 0.2)'
      },
      {
        label: getTranslatedString('total_size', 'Total Size'),
        value: topLevelMetrics.totalSize ? topLevelMetrics.totalSize.value : 'N/A',
        change: topLevelMetrics.totalSize ? topLevelMetrics.totalSize.change : '-',
        iconBg: '#DC2626',
        bgColor: 'rgba(220, 38, 38, 0.1)',
        borderColor: 'rgba(220, 38, 38, 0.2)'
      },
      {
        label: getTranslatedString('first_contentful_paint', 'First Contentful Paint'),
        value: topLevelMetrics.firstContentfulPaint ? topLevelMetrics.firstContentfulPaint.value : 'N/A',
        change: topLevelMetrics.firstContentfulPaint ? topLevelMetrics.firstContentfulPaint.change : '-',
        iconBg: '#044466',
        bgColor: 'rgba(4, 68, 102, 0.1)',
        borderColor: 'rgba(4, 68, 102, 0.2)'
      }
    ];

    this.renderStatsCards(container, stats);
  }

  renderQuickStats(result) {
    const container = document.getElementById('insights-quick-stats');
    if (!container) return;
    
    container.innerHTML = '';

    const diagnostics = result.audits['diagnostics'];
    if (!diagnostics || !diagnostics.details || !diagnostics.details.items || diagnostics.details.items.length === 0) {
      return;
    }

    const diag = diagnostics.details.items[0];
    const totalByteWeight = result.audits['total-byte-weight'];
    const fcp = result.audits['first-contentful-paint'];
    
    const stats = [
      {
        label: getTranslatedString('page_load', 'Page Load'),
        value: fcp ? this.formatMetricValue(fcp.displayValue || fcp.numericValue, fcp.numericUnit) : 'N/A',
        change: '-15%',
        iconBg: '#044466',
        bgColor: 'rgba(4, 68, 102, 0.1)',
        borderColor: 'rgba(4, 68, 102, 0.2)'
      },
      {
        label: getTranslatedString('requests', 'Requests'),
        value: diag.numRequests ? diag.numRequests.toString() : 'N/A',
        change: '-8%',
        iconBg: '#089E9B',
        bgColor: 'rgba(8, 158, 155, 0.1)',
        borderColor: 'rgba(8, 158, 155, 0.2)'
      },
      {
        label: getTranslatedString('total_size', 'Total Size'),
        value: totalByteWeight ? this.formatBytes(totalByteWeight.numericValue) : 'N/A',
        change: '-22%',
        iconBg: '#DC2626',
        bgColor: 'rgba(220, 38, 38, 0.1)',
        borderColor: 'rgba(220, 38, 38, 0.2)'
      },
      {
        label: getTranslatedString('first_contentful_paint', 'First Contentful Paint'),
        value: fcp ? this.formatMetricValue(fcp.displayValue || fcp.numericValue, fcp.numericUnit) : 'N/A',
        change: '-12%',
        iconBg: '#044466',
        bgColor: 'rgba(4, 68, 102, 0.1)',
        borderColor: 'rgba(4, 68, 102, 0.2)'
      }
    ];

    this.renderStatsCards(container, stats);
  }

  renderStatsCards(container, stats) {

    stats.forEach((stat, index) => {
      const card = document.createElement('div');
      card.className = 'insights-stat-card';
      card.style.background = `linear-gradient(to bottom right, ${stat.bgColor}, ${stat.bgColor.replace('0.1', '0.05')})`;
      card.style.borderColor = stat.borderColor;
      
      // Lucide React icons - exact SVG paths matching React App
      const iconSvg = index === 0 ? 
        // Clock icon (Page Load)
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 1.5rem; height: 1.5rem; max-width: 1.5rem; max-height: 1.5rem;"><circle cx="12" cy="12" r="10" stroke="white" fill="none"></circle><polyline points="12 6 12 12 16 14" stroke="white" fill="none"></polyline></svg>' :
        index === 1 ? 
        // Target icon (Requests) - concentric circles
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 1.5rem; height: 1.5rem; max-width: 1.5rem; max-height: 1.5rem;"><circle cx="12" cy="12" r="10" stroke="white" fill="none"></circle><circle cx="12" cy="12" r="6" stroke="white" fill="none"></circle><circle cx="12" cy="12" r="2" stroke="white" fill="none"></circle></svg>' :
        index === 2 ? 
        // BarChart3 icon (Total Size) - vertical bars chart
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 1.5rem; height: 1.5rem; max-width: 1.5rem; max-height: 1.5rem;"><path d="M3 3v18h18" stroke="white" fill="none"></path><path d="M18 17V9" stroke="white" fill="none"></path><path d="M13 17V5" stroke="white" fill="none"></path><path d="M8 17v-3" stroke="white" fill="none"></path></svg>' :
        // Zap icon (First Contentful Paint) - lightning bolt
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 1.5rem; height: 1.5rem; max-width: 1.5rem; max-height: 1.5rem;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" stroke="white" fill="none"></polygon></svg>';
      
      card.innerHTML = `
        <p class="insights-stat-label">${stat.label}</p>
        <div class="insights-stat-content">
          <div class="insights-stat-value-wrapper">
            <p class="insights-stat-value">${stat.value}</p>
            <span class="insights-stat-change">(${stat.change})</span>
          </div>
          <div class="insights-stat-icon-wrapper" style="background: ${stat.iconBg};">
            ${iconSvg}
          </div>
        </div>
      `;
      container.appendChild(card);
    });
  }

  renderCoreWebVitalsNew(coreWebVitals) {
    const container = document.getElementById('insights-core-web-vitals');
    if (!container) return;
    
    container.innerHTML = '';

    const categoryOrder = ['performance', 'accessibility', 'bestPractices', 'seo'];
    const categoryTitles = {
      'performance': getTranslatedString('performance', 'Performance'),
      'accessibility': getTranslatedString('accessibility', 'Accessibility'),
      'bestPractices': getTranslatedString('best_practices', 'Best Practices'),
      'seo': getTranslatedString('seo', 'SEO')
    };

    categoryOrder.forEach((categoryKey) => {
      const category = coreWebVitals[categoryKey];
      if (!category) return;

      const score = category.score / 100; // Convert from 0-100 to 0-1
      const scorePercent = category.score;
      const scoreClass = this.getScoreClass(score);
      const scoreLabel = scorePercent;
      const scoreColor = score >= 0.9 ? '#10b981' : score >= 0.5 ? '#f59e0b' : '#ef4444';

      const circumference = 2 * Math.PI * 52;
      const offset = circumference - (circumference * scorePercent / 100);

      const card = document.createElement('div');
      card.className = 'insights-vital-card';
      card.innerHTML = `
        <div class="insights-vital-content">
          <div class="insights-vital-circle-wrapper">
            <svg class="insights-vital-svg" viewBox="0 0 120 120">
              <circle class="insights-vital-circle-bg" cx="60" cy="60" r="52"></circle>
              <circle 
                class="insights-vital-circle-progress ${scoreClass}" 
                cx="60" 
                cy="60" 
                r="52"
                stroke-dasharray="${circumference}"
                stroke-dashoffset="${offset}"
                style="stroke: ${scoreColor};"
              ></circle>
            </svg>
            <div class="insights-vital-center">
              <div class="insights-vital-center-circle">
                <span class="insights-vital-score ${scoreClass}" style="color: ${scoreColor};">${scoreLabel}</span>
              </div>
            </div>
          </div>
          <h3 class="insights-vital-name">${categoryTitles[categoryKey] || categoryKey}</h3>
          <p class="insights-vital-description">${category.description || ''}</p>
        </div>
      `;
      container.appendChild(card);
    });
  }

  renderCoreWebVitals(categories) {
    const container = document.getElementById('insights-core-web-vitals');
    if (!container) return;
    
    container.innerHTML = '';

    const categoryOrder = ['performance', 'accessibility', 'best-practices', 'seo'];
    const categoryTitles = {
      'performance': getTranslatedString('performance', 'Performance'),
      'accessibility': getTranslatedString('accessibility', 'Accessibility'),
      'best-practices': getTranslatedString('best_practices', 'Best Practices'),
      'seo': getTranslatedString('seo', 'SEO')
    };

    categoryOrder.forEach((categoryKey, index) => {
      const category = categories[categoryKey];
      if (!category) return;

      const score = category.score;
      const scorePercent = score !== null ? Math.round(score * 100) : 0;
      const scoreClass = this.getScoreClass(score);
      const scoreLabel = this.getScoreLabel(score);
      const scoreColor = score >= 0.9 ? '#10b981' : score >= 0.5 ? '#f59e0b' : '#ef4444';

      const circumference = 2 * Math.PI * 52;
      const offset = circumference - (circumference * scorePercent / 100);

      const card = document.createElement('div');
      card.className = 'insights-vital-card';
      card.innerHTML = `
        <div class="insights-vital-content">
          <div class="insights-vital-circle-wrapper">
            <svg class="insights-vital-svg" viewBox="0 0 120 120">
              <circle class="insights-vital-circle-bg" cx="60" cy="60" r="52"></circle>
              <circle 
                class="insights-vital-circle-progress ${scoreClass}" 
                cx="60" 
                cy="60" 
                r="52"
                stroke-dasharray="${circumference}"
                stroke-dashoffset="${offset}"
                style="stroke: ${scoreColor};"
              ></circle>
            </svg>
            <div class="insights-vital-center">
              <div class="insights-vital-center-circle">
                <span class="insights-vital-score ${scoreClass}" style="color: ${scoreColor};">${scoreLabel}</span>
              </div>
            </div>
          </div>
          <h3 class="insights-vital-name">${categoryTitles[categoryKey] || category.title}</h3>
          <p class="insights-vital-description">${category.description || ''}</p>
        </div>
      `;
      container.appendChild(card);
    });
  }

  renderAuditsNew(performanceAudits) {
    const container = document.getElementById('insights-audits-list');
    if (!container) return;
    
    container.innerHTML = '';

    if (!performanceAudits || performanceAudits.length === 0) {
      container.innerHTML = '<p style="color: #475569; font-size: 0.875rem;">' + getTranslatedString('no_audit_data', 'No audit data available') + '</p>';
      return;
    }

    const audits = performanceAudits.map(audit => ({
      ...audit,
      score: audit.score / 100 // Convert from 0-100 to 0-1
    }));

    const hasMore = audits.length > this.initialAuditsToShow;
    const auditsToShow = this.auditsExpanded 
      ? audits 
      : audits.slice(0, this.initialAuditsToShow);

    auditsToShow.forEach(audit => {
      const item = this.createAuditItemNew(audit);
      container.appendChild(item);
    });

    // Show/hide expand button
    const expandControl = document.getElementById('audits-expand');
    const expandText = document.getElementById('audits-expand-text');
    if (hasMore && expandControl && expandText) {
      expandControl.style.display = 'flex';
      const remaining = audits.length - this.initialAuditsToShow;
      expandText.textContent = this.auditsExpanded 
        ? getTranslatedString('view_less', 'View Less')
        : getTranslatedString('view_more', 'View More') + ' (' + remaining + ' ' + getTranslatedString('more', 'more') + ')';
    } else if (expandControl) {
      expandControl.style.display = 'none';
    }
  }

  createAuditItemNew(audit) {
    const score = audit.score;
    const scorePercent = score !== null ? Math.round(score * 100) : 0;
    const status = score >= 0.9 ? 'passed' : 'warning';
    const icon = score >= 0.9 
      ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 100%; height: 100%;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="white" fill="none"></path><polyline points="22 4 12 14.01 9 11.01" stroke="white" fill="none"></polyline></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 100%; height: 100%;"><circle cx="12" cy="12" r="10" stroke="white" fill="none"></circle><line x1="12" y1="8" x2="12" y2="12" stroke="white" fill="none"></line><line x1="12" y1="16" x2="12.01" y2="16" stroke="white" fill="none"></line></svg>';

    const improvement = audit.change || (scorePercent < 100 ? `+${100 - scorePercent}%` : '');

    const item = document.createElement('div');
    item.className = 'insights-audit-item';
    item.innerHTML = `
      <div class="insights-audit-content-wrapper">
        <div class="insights-audit-icon-wrapper ${status}">
          <div class="insights-audit-icon">${icon}</div>
        </div>
        <div class="insights-audit-content">
          <div class="insights-audit-header">
            <h4 class="insights-audit-title">${audit.title}</h4>
            <div class="insights-audit-meta">
              ${improvement ? `<span class="insights-audit-improvement">${improvement}</span>` : ''}
              ${score !== null ? `<span class="insights-audit-score ${status}">${scorePercent}</span>` : ''}
            </div>
          </div>
          ${audit.description ? `<p class="insights-audit-description">${audit.description}</p>` : ''}
        </div>
      </div>
    `;
    return item;
  }

  renderAudits(result) {
    const container = document.getElementById('insights-audits-list');
    if (!container) return;
    
    container.innerHTML = '';

    const performanceCategory = result.categories.performance;
    if (!performanceCategory) {
      container.innerHTML = '<p style="color: #475569; font-size: 0.875rem;">' + getTranslatedString('no_audit_data', 'No audit data available') + '</p>';
      return;
    }

    const auditRefs = (performanceCategory.auditRefs || []).filter(
      ref => ref.group !== 'metrics' && ref.group !== 'insights'
    );

    const audits = auditRefs
      .map(ref => {
        const audit = result.audits[ref.id];
        return audit ? { ...audit, weight: ref.weight || 0 } : null;
      })
      .filter(audit => audit !== null)
      .sort((a, b) => {
        if (a.score === null && b.score === null) return b.weight - a.weight;
        if (a.score === null) return 1;
        if (b.score === null) return -1;
        if (a.score !== b.score) return a.score - b.score;
        return b.weight - a.weight;
      })
      .slice(0, 15);

    if (audits.length === 0) {
      container.innerHTML = '<p style="color: #475569; font-size: 0.875rem;">' + getTranslatedString('no_audits', 'No audits available') + '</p>';
      return;
    }

    const hasMore = audits.length > this.initialAuditsToShow;
    const auditsToShow = this.auditsExpanded 
      ? audits 
      : audits.slice(0, this.initialAuditsToShow);

    auditsToShow.forEach(audit => {
      const item = this.createAuditItem(audit);
      container.appendChild(item);
    });

    // Show/hide expand button
    const expandControl = document.getElementById('audits-expand');
    const expandText = document.getElementById('audits-expand-text');
    if (hasMore && expandControl && expandText) {
      expandControl.style.display = 'flex';
      const remaining = audits.length - this.initialAuditsToShow;
      expandText.textContent = this.auditsExpanded 
        ? getTranslatedString('view_less', 'View Less')
        : getTranslatedString('view_more', 'View More') + ' (' + remaining + ' ' + getTranslatedString('more', 'more') + ')';
    } else if (expandControl) {
      expandControl.style.display = 'none';
    }
  }

  createAuditItem(audit) {
    const score = audit.score;
    const scorePercent = score !== null ? Math.round(score * 100) : 0;
    const status = score >= 0.9 ? 'passed' : score >= 0.5 ? 'warning' : 'warning';
    // CheckCircle2 icon for passed, AlertCircle for warning - exact lucide-react paths
    const icon = score >= 0.9 
      ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 100%; height: 100%;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="white" fill="none"></path><polyline points="22 4 12 14.01 9 11.01" stroke="white" fill="none"></polyline></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white; width: 100%; height: 100%;"><circle cx="12" cy="12" r="10" stroke="white" fill="none"></circle><line x1="12" y1="8" x2="12" y2="12" stroke="white" fill="none"></line><line x1="12" y1="16" x2="12.01" y2="16" stroke="white" fill="none"></line></svg>';

    const improvement = scorePercent < 100 ? `+${100 - scorePercent}%` : '';

    const item = document.createElement('div');
    item.className = 'insights-audit-item';
    item.innerHTML = `
      <div class="insights-audit-content-wrapper">
        <div class="insights-audit-icon-wrapper ${status}">
          <div class="insights-audit-icon">${icon}</div>
        </div>
        <div class="insights-audit-content">
          <div class="insights-audit-header">
            <h4 class="insights-audit-title">${audit.title}</h4>
            <div class="insights-audit-meta">
              ${improvement ? `<span class="insights-audit-improvement">${improvement}</span>` : ''}
              ${score !== null ? `<span class="insights-audit-score ${status}">${scorePercent}</span>` : ''}
            </div>
          </div>
          ${audit.description ? `<p class="insights-audit-description">${audit.description}</p>` : ''}
        </div>
      </div>
    `;
    return item;
  }

  renderQuickInsightsNew(quickInsights) {
    const container = document.getElementById('insights-quick-insights-list');
    if (!container) return;
    
    container.innerHTML = '';

    const insights = [
      {
        label: quickInsights.largestScoreJump ? quickInsights.largestScoreJump.label : 'Your Largest Score Jump',
        value: quickInsights.largestScoreJump ? quickInsights.largestScoreJump.value : 'N/A',
        bgColor: 'rgba(8, 184, 184, 0.05)',
        borderColor: 'rgba(8, 184, 184, 0.3)',
        textColor: '#044466'
      },
      {
        label: quickInsights.bestMetric ? quickInsights.bestMetric.label : 'Best Metric',
        value: quickInsights.bestMetric ? quickInsights.bestMetric.value : 'N/A',
        bgColor: 'rgba(16, 185, 129, 0.05)',
        borderColor: 'rgba(16, 185, 129, 0.2)',
        textColor: '#059669'
      }
    ];

    insights.forEach((insight, index) => {
      const item = document.createElement('div');
      item.className = 'insights-quick-insight-item';
      if (index === 0) {
        item.style.background = `linear-gradient(to right, rgba(8, 184, 184, 0.05), rgba(8, 184, 184, 0.02))`;
        item.style.borderColor = 'rgba(8, 184, 184, 0.3)';
        item.style.color = '#044466';
      } else {
        item.style.background = `linear-gradient(to right, rgba(16, 185, 129, 0.05), rgba(16, 185, 129, 0.02))`;
        item.style.borderColor = 'rgba(16, 185, 129, 0.2)';
        item.style.color = '#059669';
      }
      item.innerHTML = `
        <p class="insights-quick-insight-label" style="color: ${insight.textColor};">${insight.label}</p>
        <p class="insights-quick-insight-value" style="color: ${insight.textColor};">${insight.value}</p>
      `;
      container.appendChild(item);
    });
  }

  renderQuickInsights(result) {
    const container = document.getElementById('insights-quick-insights-list');
    if (!container) return;
    
    container.innerHTML = '';

    // Try to find First Input Delay metric
    let fidValue = 'N/A';
    const fidAudit = result.audits['max-potential-fid'];
    if (fidAudit && fidAudit.score !== null) {
      fidValue = `First Input Delay (${Math.round(fidAudit.score * 100)})`;
    } else {
      // Fallback: get best score from categories
      const categories = result.categories;
      let bestScore = 0;
      let bestCategory = '';
      Object.keys(categories).forEach(key => {
        if (categories[key].score !== null && categories[key].score > bestScore) {
          bestScore = categories[key].score;
          bestCategory = categories[key].title;
        }
      });
      if (bestCategory) {
        fidValue = `${bestCategory} (${Math.round(bestScore * 100)})`;
      }
    }

    const insights = [
      {
        label: 'Your Largest Score Jump',
        value: '+12 points this week',
        bgColor: 'rgba(8, 184, 184, 0.05)',
        borderColor: 'rgba(8, 184, 184, 0.3)',
        textColor: '#044466',
        gradientFrom: 'rgba(139, 92, 246, 0.05)',
        gradientTo: 'rgba(139, 92, 246, 0.02)'
      },
      {
        label: 'Best Metric',
        value: fidValue,
        bgColor: 'rgba(16, 185, 129, 0.05)',
        borderColor: 'rgba(16, 185, 129, 0.2)',
        textColor: '#059669',
        gradientFrom: 'rgba(16, 185, 129, 0.05)',
        gradientTo: 'rgba(16, 185, 129, 0.02)'
      }
    ];

    insights.forEach((insight, index) => {
      const item = document.createElement('div');
      item.className = 'insights-quick-insight-item';
      if (index === 0) {
        item.style.background = `linear-gradient(to right, rgba(8, 184, 184, 0.05), rgba(8, 184, 184, 0.02))`;
        item.style.borderColor = 'rgba(8, 184, 184, 0.3)';
        item.style.color = '#044466';
      } else {
        item.style.background = `linear-gradient(to right, rgba(16, 185, 129, 0.05), rgba(16, 185, 129, 0.02))`;
        item.style.borderColor = 'rgba(16, 185, 129, 0.2)';
        item.style.color = '#059669';
      }
      item.innerHTML = `
        <p class="insights-quick-insight-label" style="color: ${insight.textColor};">${insight.label}</p>
        <p class="insights-quick-insight-value" style="color: ${insight.textColor};">${insight.value}</p>
      `;
      container.appendChild(item);
    });
  }

  renderConfigNew(configuration) {
    const configDevice = document.getElementById('config-device');
    const configLocale = document.getElementById('config-locale');
    const configChannel = document.getElementById('config-channel');
    
    if (configDevice) {
      configDevice.textContent = configuration.device || (this.currentDevice === 'desktop' ? getTranslatedString('desktop', 'Desktop') : getTranslatedString('mobile', 'Mobile'));
    }
    if (configLocale) {
      configLocale.textContent = configuration.locale || 'En-US';
    }
    if (configChannel) {
      configChannel.textContent = (configuration.channel || 'LR').toUpperCase();
    }
  }

  renderConfig(result) {
    const configDevice = document.getElementById('config-device');
    const configLocale = document.getElementById('config-locale');
    const configChannel = document.getElementById('config-channel');
    
    const config = result.configSettings || {};
    
    if (configDevice) {
      configDevice.textContent = this.currentDevice === 'desktop' ? getTranslatedString('desktop', 'Desktop') : getTranslatedString('mobile', 'Mobile');
    }
    if (configLocale) {
      configLocale.textContent = config.locale || 'En-US';
    }
    if (configChannel) {
      configChannel.textContent = (config.channel || 'LR').toUpperCase();
    }
  }

  toggleAudits() {
    this.auditsExpanded = !this.auditsExpanded;
    const chevron = document.querySelector('#btn-audits-expand .insights-chevron-icon');
    if (chevron) {
      chevron.classList.toggle('up', this.auditsExpanded);
    }
    this.render();
  }

  // Utility functions
  getScoreClass(score) {
    if (score === null) return 'poor';
    if (score >= 0.9) return 'excellent';
    if (score >= 0.5) return 'needs-improvement';
    return 'poor';
  }

  getScoreLabel(score) {
    if (score === null) return getTranslatedString('n_a', 'N/A');
    return Math.round(score * 100).toString();
  }

  getScoreStatus(score) {
    if (score === null) return getTranslatedString('no_data_available', 'No data available');
    if (score >= 0.9) return getTranslatedString('excellent', 'Excellent');
    if (score >= 0.5) return getTranslatedString('needs_improvement', 'Needs Improvement');
    return getTranslatedString('poor', 'Poor');
  }

  formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${(bytes / Math.pow(k, i)).toFixed(2)} ${sizes[i]}`;
  }

  formatMetricValue(value, unit) {
    if (!value) return 'N/A';
    if (unit === 'millisecond') {
      const ms = parseFloat(value);
      if (ms < 1000) return `${Math.round(ms)} ms`;
      return `${(ms / 1000).toFixed(2)} s`;
    }
    if (unit === 'byte') {
      return this.formatBytes(value);
    }
    return value.toString();
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  if (typeof vortemInsights !== 'undefined') {
    new PageSpeedDashboard();
  } else {
    if (typeof VortemLogger !== 'undefined') {
      VortemLogger.error('Insights Dashboard: vortemInsights object not found');
    } else {
      VortemLogger.error('Insights Dashboard: vortemInsights object not found');
    }
  }
});
