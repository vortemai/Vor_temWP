// WordPress AJAX Configuration
// These variables will be localized from PHP

// State Management
let isLoading = false;
let lighthouseData = null;

// DOM Elements - will be initialized in DOMContentLoaded
let refreshBtn, refreshIcon, refreshText, retryBtn;
let errorContainer, errorMessage, loadingContainer, resultsContainer, emptyContainer;
let mobileSection, desktopSection;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  // Initialize DOM Elements
  refreshBtn = document.getElementById('refreshBtn');
  refreshIcon = document.getElementById('refreshIcon');
  refreshText = document.getElementById('refreshText');
  retryBtn = document.getElementById('retryBtn');
  
  errorContainer = document.getElementById('errorContainer');
  errorMessage = document.getElementById('errorMessage');
  loadingContainer = document.getElementById('loadingContainer');
  resultsContainer = document.getElementById('resultsContainer');
  emptyContainer = document.getElementById('emptyContainer');
  
  mobileSection = document.getElementById('mobileSection');
  desktopSection = document.getElementById('desktopSection');

  // Only initialize if elements exist
  if (refreshBtn) {
    initializeEventListeners();
    fetchLighthouseData();
  }
});

// Event Listeners
function initializeEventListeners() {
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      fetchLighthouseData();
    });
  }

  if (retryBtn) {
    retryBtn.addEventListener('click', () => {
      fetchLighthouseData();
    });
  }
}

// API Functions
async function fetchLighthouseData() {
  if (isLoading) return;

  isLoading = true;
  setLoadingState(true);
  hideError();
  hideResults();
  hideEmpty();

  try {
    // Use WordPress AJAX
    const formData = new FormData();
    formData.append('action', 'vortem_fetch_lighthouse_data');
    formData.append('nonce', vortemAnalyticsData.nonce);

    const response = await fetch(vortemAnalyticsData.ajaxUrl, {
      method: 'POST',
      body: formData
    });

    const result = await response.json();
    
    // Console log the full response for debugging
    VortemLogger.log('=== Lighthouse API Response ===');
    VortemLogger.log('Full Response:', result);
    VortemLogger.log('Success:', result.success);
    VortemLogger.log('Data:', result.data);
    if (result.data) {
      VortemLogger.log('Mobile Data:', result.data.mobile_data);
      VortemLogger.log('Desktop Data:', result.data.desktop_data);
      if (result.data._debug) {
        VortemLogger.log('Debug Info:', result.data._debug);
      }
    }
    VortemLogger.log('==============================');

    if (!result.success) {
      throw new Error(result.data?.message || 'Failed to fetch Lighthouse data');
    }

    lighthouseData = result.data;
    
    setLoadingState(false);
    displayResults(result.data);
  } catch (error) {
    VortemLogger.error('Error fetching Lighthouse data:', error);
    setLoadingState(false);
    showError(error.message || 'Failed to analyze the website. Please try again.');
  } finally {
    isLoading = false;
  }
}

// UI State Management
function setLoadingState(loading) {
  if (loading) {
    if (refreshBtn) refreshBtn.disabled = true;
    if (refreshIcon) refreshIcon.classList.add('fa-spin');
    if (refreshText) refreshText.textContent = 'Analyzing...';
    if (loadingContainer) loadingContainer.classList.remove('hidden');
  } else {
    if (refreshBtn) refreshBtn.disabled = false;
    if (refreshIcon) refreshIcon.classList.remove('fa-spin');
    if (refreshText) refreshText.textContent = 'Refresh Analysis';
    if (loadingContainer) loadingContainer.classList.add('hidden');
  }
}

function showError(message) {
  if (errorMessage) errorMessage.textContent = message;
  if (errorContainer) errorContainer.classList.remove('hidden');
  hideResults();
  hideEmpty();
}

function hideError() {
  if (errorContainer) errorContainer.classList.add('hidden');
}

function hideLoading() {
  if (loadingContainer) loadingContainer.classList.add('hidden');
}

function showResults() {
  if (resultsContainer) resultsContainer.classList.remove('hidden');
  hideError();
  hideEmpty();
  hideLoading();
}

function hideResults() {
  if (resultsContainer) resultsContainer.classList.add('hidden');
}

function showEmpty() {
  if (emptyContainer) emptyContainer.classList.remove('hidden');
  hideResults();
  hideError();
  hideLoading();
}

function hideEmpty() {
  if (emptyContainer) emptyContainer.classList.add('hidden');
}

// Display Functions
function displayResults(data) {
  if (!data || (!data.mobile_data && !data.desktop_data)) {
    showEmpty();
    return;
  }

  showResults();

  if (data.mobile_data) {
    displayDeviceData('mobile', data.mobile_data);
    if (mobileSection) mobileSection.classList.remove('hidden');
  } else {
    if (mobileSection) mobileSection.classList.add('hidden');
  }

  if (data.desktop_data) {
    displayDeviceData('desktop', data.desktop_data);
    if (desktopSection) desktopSection.classList.remove('hidden');
  } else {
    if (desktopSection) desktopSection.classList.add('hidden');
  }
}

function displayDeviceData(device, data) {
  // Set URL and Time
  const urlElement = document.getElementById(`${device}Url`);
  const timeElement = document.getElementById(`${device}Time`);
  
  if (urlElement && data.requested_url) {
    urlElement.textContent = data.requested_url;
  }
  
  if (timeElement && data.analysis_time) {
    timeElement.textContent = new Date(data.analysis_time).toLocaleString('en-US');
  }

  // Core Web Vitals
  if (data.critical_data) {
    const critical = data.critical_data;
    
    // Performance Score
    if (critical.performance_score) {
      displayScoreMetric(`${device}PerformanceScore`, 'Performance Score', critical.performance_score);
    }
    
    // Core Web Vitals
    if (critical.lab_data_core_vitals) {
      const vitals = critical.lab_data_core_vitals;
      
      if (vitals.LCP) {
        displayScoreMetric(`${device}LCP`, 'LCP (Largest Contentful Paint)', vitals.LCP);
      }
      
      if (vitals.CLS) {
        displayScoreMetric(`${device}CLS`, 'CLS (Cumulative Layout Shift)', vitals.CLS);
      }
      
      if (vitals.TTFB) {
        displayScoreMetric(`${device}TTFB`, 'TTFB (Time to First Byte)', vitals.TTFB);
      }
    }
    
    // Core Vitals Status
    if (critical.field_data_core_vitals && critical.field_data_core_vitals.status) {
      const statusElement = document.getElementById(`${device}CoreVitalsStatus`);
      if (statusElement) {
        statusElement.innerHTML = `
          <p class="core-vitals-status-text">
            <strong>Core Web Vitals Status:</strong> ${critical.field_data_core_vitals.status}
          </p>
        `;
      }
    }
  }

  // Additional Metrics
  if (data.important_data) {
    const important = data.important_data;
    
    // Other Lighthouse Scores
    if (important.other_lighthouse_scores) {
      const scores = important.other_lighthouse_scores;
      
      if (scores.accessibility) {
        displayScoreMetric(`${device}Accessibility`, 'Accessibility', scores.accessibility);
      }
      
      if (scores.best_practices) {
        displayScoreMetric(`${device}BestPractices`, 'Best Practices', scores.best_practices);
      }
      
      if (scores.seo) {
        displayScoreMetric(`${device}SEO`, 'SEO', scores.seo);
      }
    }
    
    // Key Diagnostics
    if (important.key_diagnostics) {
      const diagnostics = important.key_diagnostics;
      
      if (diagnostics.speed_index) {
        displayScoreMetric(`${device}SpeedIndex`, 'Speed Index', diagnostics.speed_index);
      }
      
      if (diagnostics.total_blocking_time) {
        displayScoreMetric(`${device}TotalBlockingTime`, 'Total Blocking Time', diagnostics.total_blocking_time);
      }
    }
  }
}

function displayScoreMetric(elementId, label, scoreData) {
  const element = document.getElementById(elementId);
  if (!element || !scoreData) return;

  const score = scoreData.value || 0;
  const description = scoreData.description || '';
  const threshold = scoreData.good_threshold || '';

  // Determine score color class
  let scoreClass = 'score-red';
  if (score >= 90) {
    scoreClass = 'score-green';
  } else if (score >= 50) {
    scoreClass = 'score-yellow';
  }

  element.innerHTML = `
    <div class="score-metric-header">
      <div class="score-metric-label">${escapeHtml(label)}</div>
      <div class="score-metric-value ${scoreClass}">${score}</div>
    </div>
    ${description ? `<div class="score-metric-description">${escapeHtml(description)}</div>` : ''}
    ${threshold ? `<div class="score-metric-threshold">Threshold: ${escapeHtml(threshold)}</div>` : ''}
  `;
}

// Utility Functions
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

