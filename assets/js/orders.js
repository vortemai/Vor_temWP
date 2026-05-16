// External Library: jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and AJAX
// External Library: Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | Used for refresh button icon rotation
(function($) {
  'use strict';

  // WordPress AJAX wrapper
  const wpAjax = {
    post: function(action, data) {
      return $.ajax({
        url: vortemOrders.ajaxUrl,
        type: 'POST',
        data: $.extend({
          action: action,
          nonce: vortemOrders.nonce
        }, data || {})
      });
    }
  };

  // Helpers
  const qs = (sel, root = document) => (root || document).querySelector(sel);
  const qsa = (sel, root = document) => Array.from((root || document).querySelectorAll(sel));
  const showToast = (msg, kind = 'info') => {
    const el = qs('#toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast ' + kind;
    el.classList.add('show');
    clearTimeout(el._t);
    // Show error messages longer (5 seconds) than info messages (3 seconds)
    const duration = kind === 'error' ? 5000 : 3000;
    el._t = setTimeout(() => el.classList.remove('show'), duration);
  };

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
  }

  function decodeHtmlEntities(text) {
    if (!text) return text;
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  }

  function formatCurrency(amount, currencySymbol, currencyPos) {
    const formatted = parseFloat(amount).toFixed(2);
    const decodedSymbol = decodeHtmlEntities(currencySymbol || '$');
    if (currencyPos === 'right') {
      return formatted + ' ' + decodedSymbol;
    } else if (currencyPos === 'right_space') {
      return formatted + ' ' + decodedSymbol;
    } else if (currencyPos === 'left_space') {
      return decodedSymbol + ' ' + formatted;
    } else {
      return decodedSymbol + formatted;
    }
  }

  function getStatusBadgeClass(status) {
    const statusMap = {
      'pending': 'pending',
      'processing': 'processing',
      'on-hold': 'on-hold',
      'completed': 'completed',
      'cancelled': 'cancelled',
      'refunded': 'refunded',
      'failed': 'failed'
    };
    return statusMap[status] || 'pending';
  }

  // WordPress AJAX API wrapper
  const API = {
    getOrders: (params) => wpAjax.post('vortem_get_orders', params).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    searchOrders: (params) => wpAjax.post('vortem_search_orders', params).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getOrderDetails: (orderId) => wpAjax.post('vortem_get_order_details', {order_id: orderId}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    sendOrderToAliExpress: (orderId) => wpAjax.post('vortem_send_order_to_aliexpress', {order_id: orderId}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
  };

  // State
  let currentPage = 1;
  let showRecentFirst = false; // Toggle for showing recent orders first
  let currentFilters = {
    status: 'all',
    search: '',
    date_from: '',
    date_to: ''
  };

  // Load orders
  function loadOrders(page = 1) {
    const tbody = qs('#orders-tbody');
    if (!tbody) return Promise.resolve();

    const loadingText = (vortemOrders && vortemOrders.strings && vortemOrders.strings.loading_orders) ? vortemOrders.strings.loading_orders : 'Loading orders...';
    tbody.innerHTML = '<tr><td colspan="8" class="loading"><div class="spinner"></div>' + loadingText + '</td></tr>';

    const params = {
      page: page,
      per_page: 20,
      status: currentFilters.status,
      search: currentFilters.search,
      date_from: currentFilters.date_from,
      date_to: currentFilters.date_to,
      order: showRecentFirst ? 'DESC' : 'ASC'
    };

    return API.getOrders(params)
      .then(data => {
        currentPage = data.page;
        renderOrders(data.orders);
        renderPagination(data);
        updateStats(data);
      })
      .catch(error => {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--mega-danger);">Error loading orders: ' + escapeHtml(error.message) + '</td></tr>';
        showToast('Error loading orders: ' + error.message, 'error');
      });
  }

  // Render orders table
  function renderOrders(orders) {
    const tbody = qs('#orders-tbody');
    if (!tbody) return;

    if (orders.length === 0) {
      const noOrdersText = (vortemOrders && vortemOrders.strings && vortemOrders.strings.no_orders_found) ? vortemOrders.strings.no_orders_found : 'No orders found';
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--mega-text-muted);">' + escapeHtml(noOrdersText) + '</td></tr>';
      return;
    }

    const currencySymbol = vortemOrders.currencySymbol || '$';
    const currencyPos = vortemOrders.currencyPos || 'left';

    tbody.innerHTML = orders.map(order => {
      const statusClass = getStatusBadgeClass(order.status);
      const totalDisplay = order.total ? formatCurrency(order.total, currencySymbol, currencyPos) : decodeHtmlEntities(order.total_formatted || '');
      return `
        <tr>
          <td><strong>#${escapeHtml(order.order_number)}</strong></td>
          <td>${escapeHtml(order.date_created_formatted)}</td>
          <td><span class="status-badge ${statusClass}">${escapeHtml(order.status_label)}</span></td>
          <td>
            <div>${escapeHtml(order.customer_name || 'Guest')}</div>
            <div style="font-size:12px;color:var(--mega-text-muted);">${escapeHtml(order.customer_email || '')}</div>
          </td>
          <td>${order.item_count}</td>
          <td><strong>${escapeHtml(totalDisplay)}</strong></td>
          <td>${escapeHtml(order.payment_method || 'N/A')}</td>
          <td>
            <div class="order-actions">
              <button class="btn btn-icon view-order-btn" data-order-id="${order.id}" title="View Details">
                <span class="dashicons dashicons-visibility"></span>
              </button>
              <a href="${escapeHtml(order.edit_url)}" class="btn btn-icon" title="Edit Order" target="_blank">
                <span class="dashicons dashicons-edit"></span>
              </a>
              ${order.status && String(order.status).trim().toLowerCase() === 'processing' ? `
              <button class="btn btn-icon send-order-btn" data-order-id="${order.id}" title="Send to AliExpress">
                <svg class="aliexpress-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2"><path d="M6 118.468C6 56.356 56.356 6 118.468 6H393.53C455.643 6 506 56.356 506 118.468V393.53C505.999 455.643 455.643 506 393.53 506H118.468C56.356 505.999 6 455.643 6 393.53V118.468z" fill="#f90" fill-rule="nonzero"/><path d="M6 159.843c0-50.206 40.7-90.906 90.912-90.906h318.18c50.207 0 90.907 40.7 90.907 90.906V393.53C505.999 455.643 455.643 506 393.53 506H118.468C56.356 505.999 6 455.643 6 393.53V159.843z" fill="#e43225" fill-rule="nonzero"/><path d="M145.28 149.78c1.888 7.7.826 16.013 2.676 23.838 6.469-4.731 10.775-12.543 9.894-20.68-.132-14.563-16.513-25.976-30.22-21.076-8.618 2.463-14.993 10.2-16.437 18.938-1.725 10.006 4.282 20.237 13.22 24.812-.882-8.619-2.957-17.706-.638-26.137 3.537-9.194 18.23-9.007 21.506.306zm255.056 10.438c3.144-11.3-3.693-24.4-14.9-28.018-10.774-4.232-24.112 1.156-28.862 11.656-5.294 9.719-2.094 23.55 7.588 29.244 1.912-7.8.5-16.163 2.768-23.744 3.619-9.056 18.131-8.794 21.431.387 2.1 8.207.2 16.825-.593 25.106 6.575-2.03 10.675-8.312 12.562-14.606l.006-.025z" fill="#b32100" fill-rule="nonzero"/><path d="M366.962 149.35c-2.282 7.662-.87 15.95-2.77 23.75-5.637 33.862-28.312 64.13-59.012 79.3a109.319 109.319 0 01-98.506-.126c-30.418-15.13-52.912-45.143-58.693-78.643-1.85-7.838-.8-16.063-2.681-23.838-3.263-9.237-17.963-9.506-21.507-.312-2.306 8.512-.23 17.531.644 26.137 6.463 41.875 34.481 79.475 72.437 98.138a133.42 133.42 0 0056.032 13.8 133.357 133.357 0 0056.612-11.138c41.05-17.725 71.737-57.312 78.3-101.6.793-8.281 2.693-16.906.587-25.106-3.312-9.181-17.812-9.431-21.437-.381l-.006.019zM164.893 391.061v-56.324h33.294v7.062h-26.4v17.319h23.712v7.062h-23.712v17.656h28.25v7.063h-35.144v.162zM235.018 391.061L223.587 376.1l-11.438 14.962h-8.068l15.637-20.006-16.481-20.68h9.081l11.269 15.468 11.431-15.469h8.913l-15.638 20.681 14.794 20.006h-8.069zM254.18 385.011v27.744h-6.893v-41.531c0-10.594 8.069-21.862 20.681-21.862 12.781 0 22.362 8.075 22.362 21.356 0 12.95-9.75 21.856-20.85 21.856-5.38 0-12.612-2.35-15.3-7.563zm28.92-14.293c0-9.081-5.882-14.463-16.307-13.956-5.044.168-12.781 3.868-12.106 16.812.169 4.206 4.537 12.106 14.125 12.106 8.237 0 14.287-4.706 14.287-14.962zM296.218 350.374h6.894v4.369c3.362-3.869 8.575-5.213 14.125-5.213v7.4c-.838-.168-9.082-1.175-14.125 9.582v24.718h-6.894v-40.856zM319.087 370.718c0-11.769 8.406-21.356 20.012-21.356 14.456 0 19.838 9.587 19.838 21.862v3.363h-32.282c.507 7.73 7.4 11.768 13.788 11.6 4.706-.17 7.9-1.513 11.262-4.876l4.544 4.707c-4.206 4.037-9.587 6.725-16.144 6.725-12.275-.169-21.018-9.244-21.018-22.025zm19.506-14.463c-6.556 0-11.6 5.719-11.938 11.938h25.05c0-6.05-4.368-11.938-13.112-11.938zM362.468 385.349l5.05-4.544c-.169 0 2.519 2.694 2.856 2.863 1.175 1.006 2.35 1.681 3.869 2.018 4.368 1.175 12.275.838 12.943-5.212.338-3.363-2.18-5.213-5.043-6.394-3.7-1.343-7.731-1.85-11.431-3.531-4.207-1.85-6.894-5.044-6.894-9.75 0-12.275 17.487-14.294 25.387-8.575.338.338 4.206 3.869 4.038 3.869l-5.044 4.031c-2.525-3.025-4.881-4.537-10.263-4.537-2.687 0-6.387 1.175-7.056 4.037-1.012 4.031 3.532 5.544 6.556 6.388 4.038 1.006 8.407 1.68 11.938 3.868 4.875 3.025 6.056 9.581 4.206 14.625-2.019 5.55-8.075 7.738-13.456 7.906-6.387.332-11.937-1.68-16.475-6.225-.337 0-1.181-.837-1.181-.837zM397.949 385.349l5.044-4.544c-.17 0 2.525 2.694 2.862 2.863 1.175 1.006 2.35 1.681 3.863 2.018 4.375 1.175 12.275.838 12.95-5.212.337-3.363-2.188-5.213-5.044-6.394-3.7-1.343-7.738-1.85-11.438-3.531-4.2-1.85-6.893-5.044-6.893-9.75 0-12.275 17.487-14.294 25.393-8.575.338.338 4.2 3.869 4.032 3.869l-5.044 4.031c-2.519-3.025-4.875-4.537-10.256-4.537-2.688 0-6.388 1.175-7.063 4.037-1.006 4.031 3.531 5.544 6.563 6.388 4.03 1.006 8.406 1.68 11.937 3.868 4.875 3.025 6.05 9.581 4.2 14.625-2.012 5.55-8.069 7.738-13.45 7.906-6.387.332-11.937-1.68-16.481-6.225-.331 0-1.175-.837-1.175-.837zM430.73 350.368v-4.369h-1.512v-.844h4.037V346h-1.519v4.369h-1.006zM438.293 350.368v-4.031l-1.513 4.03h-.337l-1.513-4.03v4.03h-.844v-5.212h1.35l1.344 3.532 1.344-3.532h1.344v5.213h-1.175zM118.143 391.061l-5.043-13.45H85.862l-5.044 13.45h-7.23l21.855-56.324h7.907l21.687 56.324h-6.894zm-19-48.256l-10.256 27.913h21.356l-11.1-27.913zM129.58 334.737h7.063v56.33h-7.062zM147.237 351.212h7.063v39.85h-7.063zM161.03 338.268v-.675c-5.38-.169-9.755-4.538-9.924-9.919H150.1c-.17 5.381-4.544 9.75-9.92 9.919v.675c5.376.169 9.75 4.537 9.92 9.919h1.006c.169-5.382 4.544-9.75 9.925-9.92z" fill="#fff" fill-rule="nonzero"/></svg>
              </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `;
    }).join('');

    // Attach event listeners
    qsa('.view-order-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-order-id');
        showOrderDetails(orderId);
      });
    });

    qsa('.send-order-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const orderId = btn.getAttribute('data-order-id');
        if (!orderId) {
          showToast('Order ID is missing', 'error');
          return;
        }
        
        // Disable button and show loading state
        btn.disabled = true;
        const originalTitle = btn.getAttribute('title');
        btn.setAttribute('title', 'Sending...');
        const icon = btn.querySelector('.dashicons');
        if (icon) {
          icon.classList.add('dashicons-update');
          icon.style.animation = 'spin 1s linear infinite';
        }
        
        try {
          await API.sendOrderToAliExpress(orderId);
          showToast('Order sent to AliExpress successfully', 'success');
          // Reload orders to refresh the list
          loadOrders(currentPage);
        } catch (error) {
          showToast('Failed to send order: ' + (error.message || 'Unknown error'), 'error');
        } finally {
          // Re-enable button
          btn.disabled = false;
          btn.setAttribute('title', originalTitle || 'Send');
          if (icon) {
            icon.classList.remove('dashicons-update');
            icon.style.animation = '';
          }
        }
      });
    });
  }

  // Function to format number - always use English numerals
  function formatNumberForLanguage(num) {
    if (num === null || num === undefined || num === '') {
      return num;
    }
    return String(num);
  }

  // Render pagination
  function renderPagination(data) {
    const container = qs('#pagination-container');
    if (!container) return;

    if (data.total_pages <= 1) {
      container.innerHTML = '';
      return;
    }

    const strings = vortemOrders && vortemOrders.strings ? vortemOrders.strings : {};
    const previousText = strings.previous || 'Previous';
    const nextText = strings.next || 'Next';
    const pageText = strings.page || 'Page';
    const ofText = strings.of || 'of';
    const ordersText = strings.orders || 'orders';
    
    let html = '<div style="display:flex;align-items:center;gap:8px;">';
    
    // Previous button
    html += `<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">${escapeHtml(previousText)}</button>`;
    
    // Page numbers
    const maxPages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPages / 2));
    let endPage = Math.min(data.total_pages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
      startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    if (startPage > 1) {
      html += `<button class="pagination-btn" data-page="1">${formatNumberForLanguage(1)}</button>`;
      if (startPage > 2) {
        html += '<span class="pagination-info">...</span>';
      }
    }
    
    for (let i = startPage; i <= endPage; i++) {
      html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${formatNumberForLanguage(i)}</button>`;
    }
    
    if (endPage < data.total_pages) {
      if (endPage < data.total_pages - 1) {
        html += '<span class="pagination-info">...</span>';
      }
      html += `<button class="pagination-btn" data-page="${data.total_pages}">${formatNumberForLanguage(data.total_pages)}</button>`;
    }
    
    // Next button
    html += `<button class="pagination-btn" ${currentPage === data.total_pages ? 'disabled' : ''} data-page="${currentPage + 1}">${escapeHtml(nextText)}</button>`;
    
    html += `<span class="pagination-info">${escapeHtml(pageText)} ${formatNumberForLanguage(currentPage)} ${escapeHtml(ofText)} ${formatNumberForLanguage(data.total_pages)} (${formatNumberForLanguage(data.total)} ${escapeHtml(ordersText)})</span>`;
    html += '</div>';
    
    container.innerHTML = html;

    // Attach event listeners
    qsa('.pagination-btn:not(:disabled)').forEach(btn => {
      btn.addEventListener('click', () => {
        const page = parseInt(btn.getAttribute('data-page'));
        if (page && page !== currentPage) {
          loadOrders(page);
        }
      });
    });
  }

  // Update stats
  function updateStats(data) {
    const statsContainer = qs('#orders-stats');
    if (!statsContainer) return;

    const strings = vortemOrders && vortemOrders.strings ? vortemOrders.strings : {};
    const totalOrdersLabel = strings.total_orders || 'Total Orders';
    const currentPageLabel = strings.current_page || 'Current Page';
    const ordersPerPageLabel = strings.orders_per_page || 'Orders Per Page';

    statsContainer.innerHTML = `
      <div class="stat-card">
        <div class="stat-card-label">${escapeHtml(totalOrdersLabel)}</div>
        <div class="stat-card-value">${formatNumberForLanguage(data.total)}</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">${escapeHtml(currentPageLabel)}</div>
        <div class="stat-card-value">${formatNumberForLanguage(data.page)} / ${formatNumberForLanguage(data.total_pages)}</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">${escapeHtml(ordersPerPageLabel)}</div>
        <div class="stat-card-value">${formatNumberForLanguage(data.per_page)}</div>
      </div>
    `;
  }

  // Show order details modal
  function showOrderDetails(orderId) {
    const modal = qs('#order-details-modal');
    const content = qs('#order-details-content');
    if (!modal || !content) return;

    content.innerHTML = '<div class="loading"><div class="spinner"></div>Loading order details...</div>';
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    API.getOrderDetails(orderId)
      .then(order => {
        renderOrderDetails(order);
      })
      .catch(error => {
        content.innerHTML = '<div style="text-align:center;padding:40px;color:var(--mega-danger);">Error loading order details: ' + escapeHtml(error.message) + '</div>';
        showToast('Error loading order details: ' + error.message, 'error');
      });
  }

  // Render order details
  function renderOrderDetails(order) {
    const content = qs('#order-details-content');
    if (!content) return;

    const statusClass = getStatusBadgeClass(order.status);
    const currencySymbol = vortemOrders.currencySymbol || '$';
    const currencyPos = vortemOrders.currencyPos || 'left';

    let itemsHtml = '';
    if (order.items && order.items.length > 0) {
      itemsHtml = '<table class="order-items-table"><thead><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Total</th></tr></thead><tbody>';
      order.items.forEach(item => {
        const itemTotalDisplay = item.total ? formatCurrency(item.total, currencySymbol, currencyPos) : decodeHtmlEntities(item.total_formatted || '');
        itemsHtml += `
          <tr>
            <td>${escapeHtml(item.name)}</td>
            <td>${escapeHtml(item.sku || 'N/A')}</td>
            <td>${item.quantity}</td>
            <td>${escapeHtml(itemTotalDisplay)}</td>
          </tr>
        `;
      });
      itemsHtml += '</tbody></table>';
    }

    content.innerHTML = `
      <div class="order-details-section">
        <h4>Order Information</h4>
        <div class="order-details-grid">
          <div class="order-details-item">
            <div class="order-details-item-label">Order Number</div>
            <div class="order-details-item-value">#${escapeHtml(order.order_number)}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Status</div>
            <div class="order-details-item-value"><span class="status-badge ${statusClass}">${escapeHtml(order.status_label)}</span></div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Date</div>
            <div class="order-details-item-value">${escapeHtml(order.date_created_formatted)}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Total</div>
            <div class="order-details-item-value"><strong>${escapeHtml(order.total ? formatCurrency(order.total, currencySymbol, currencyPos) : decodeHtmlEntities(order.total_formatted || ''))}</strong></div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Payment Method</div>
            <div class="order-details-item-value">${escapeHtml(order.payment_method || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Items</div>
            <div class="order-details-item-value">${order.item_count}</div>
          </div>
        </div>
      </div>

      <div class="order-details-section">
        <h4>Billing Address</h4>
        <div class="order-details-grid">
          <div class="order-details-item">
            <div class="order-details-item-label">Name</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.first_name + ' ' + order.billing.last_name)}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Email</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.email || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Phone</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.phone || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Company</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.company || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Address</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.address_1 || '')} ${escapeHtml(order.billing.address_2 || '')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">City</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.city || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">State</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.state || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Postcode</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.postcode || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Country</div>
            <div class="order-details-item-value">${escapeHtml(order.billing.country || 'N/A')}</div>
          </div>
        </div>
      </div>

      <div class="order-details-section">
        <h4>Shipping Address</h4>
        <div class="order-details-grid">
          <div class="order-details-item">
            <div class="order-details-item-label">Name</div>
            <div class="order-details-item-value">${escapeHtml((order.shipping.first_name + ' ' + order.shipping.last_name).trim() || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Company</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.company || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Address</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.address_1 || '')} ${escapeHtml(order.shipping.address_2 || '')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">City</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.city || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">State</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.state || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Postcode</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.postcode || 'N/A')}</div>
          </div>
          <div class="order-details-item">
            <div class="order-details-item-label">Country</div>
            <div class="order-details-item-value">${escapeHtml(order.shipping.country || 'N/A')}</div>
          </div>
        </div>
      </div>

      <div class="order-details-section">
        <h4>Order Items</h4>
        ${itemsHtml || '<p>No items found</p>'}
      </div>

      <div style="margin-top:24px;text-align:right;">
        <a href="${escapeHtml(order.edit_url)}" class="btn btn-primary" target="_blank">Edit Order in WooCommerce</a>
      </div>
    `;
  }

  // Close modal
  function closeModal() {
    const modal = qs('#order-details-modal');
    if (modal) {
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
    }
  }

  // Expose loadOrders globally so it can be called when switching tabs
  window.vortemLoadOrders = loadOrders;

  // Event listeners
  $(document).ready(function() {
    // Initial load
    loadOrders(1);

    // Refresh button
    const refreshBtn = qs('#refresh-orders');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        // Start rotation animation
        const refreshIcon = refreshBtn.querySelector('[data-lucide]');
        if (refreshIcon) {
          refreshIcon.style.animation = 'spin 1s linear infinite';
        }
        
        // Disable button during refresh
        refreshBtn.disabled = true;
        
        // Clear search input
        const searchInput = qs('#search-orders');
        if (searchInput) {
          searchInput.value = '';
          currentFilters.search = '';
        }
        
        // Clear date fields
        const dateFrom = qs('#filter-date-from');
        const dateTo = qs('#filter-date-to');
        if (dateFrom) {
          dateFrom.value = '';
          currentFilters.date_from = '';
        }
        if (dateTo) {
          dateTo.value = '';
          currentFilters.date_to = '';
        }
        
        // Reload orders from page 1
        loadOrders(1)
          .then(() => {
            showToast('Orders refreshed', 'success');
          })
          .finally(() => {
            // Stop rotation animation and re-enable button
            if (refreshIcon) {
              refreshIcon.style.animation = '';
            }
            refreshBtn.disabled = false;
          });
      });
    }

    // Search input
    const searchInput = qs('#search-orders');
    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          currentFilters.search = e.target.value;
          loadOrders(1);
        }, 500);
      });
    }

    // Status filter
    const statusFilter = qs('#filter-status');
    if (statusFilter) {
      statusFilter.addEventListener('change', (e) => {
        currentFilters.status = e.target.value;
        loadOrders(1);
      });
    }

    // Date filters
    const dateFrom = qs('#filter-date-from');
    const dateTo = qs('#filter-date-to');
    if (dateFrom) {
      dateFrom.addEventListener('change', (e) => {
        currentFilters.date_from = e.target.value;
        // If start date is set, we can load orders (end date can be empty)
        loadOrders(1);
      });
    }
    if (dateTo) {
      dateTo.addEventListener('change', (e) => {
        const endDateValue = e.target.value;
        // Validate: if end date is entered, start date must also be entered
        if (endDateValue && !currentFilters.date_from) {
          // Show toast/modal in bottom-right corner
          showToast('Please enter a start date.', 'error');
          // Clear the end date field
          e.target.value = '';
          currentFilters.date_to = '';
          return;
        }
        currentFilters.date_to = endDateValue;
        loadOrders(1);
      });
    }

    // Toggle recent orders button
    const toggleRecentBtn = qs('#toggle-recent-orders');
    if (toggleRecentBtn) {
      const sortIcon = qs('#sort-icon');
      
      // Update button visual state and icon direction
      function updateToggleButtonState() {
        if (showRecentFirst) {
          // Recent first (DESC) - show down arrow
          toggleRecentBtn.classList.add('active');
          toggleRecentBtn.setAttribute('title', 'Sort: Recent first (click for default)');
          toggleRecentBtn.setAttribute('aria-pressed', 'true');
          if (sortIcon) {
            // Down arrow icon
            sortIcon.innerHTML = '<path d="M12 22v-20M12 22l4-4M12 22L8 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
          }
        } else {
          // Default order (ASC) - show up arrow
          toggleRecentBtn.classList.remove('active');
          toggleRecentBtn.setAttribute('title', 'Sort: Default order (click for recent first)');
          toggleRecentBtn.setAttribute('aria-pressed', 'false');
          if (sortIcon) {
            // Up arrow icon
            sortIcon.innerHTML = '<path d="M12 2v20M12 2l4 4M12 2L8 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
          }
        }
      }
      
      // Initial state
      updateToggleButtonState();
      
      toggleRecentBtn.addEventListener('click', () => {
        // Toggle between default (ASC) and recent first (DESC)
        showRecentFirst = !showRecentFirst;
        updateToggleButtonState();
        // Reload orders from page 1 with new sort order
        loadOrders(1);
      });
    }

    // Close modal buttons
    const closeModalBtn = qs('#close-order-details-modal');
    if (closeModalBtn) {
      closeModalBtn.addEventListener('click', closeModal);
    }

    const modalBackdrop = qs('.modal-backdrop[data-close="order-details-modal"]');
    if (modalBackdrop) {
      modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) {
          closeModal();
        }
      });
    }
  });

})(jQuery);

