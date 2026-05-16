// External Library: jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and AJAX
(function($) {
  'use strict';

  // Translation helper function
  function getTranslatedString(key, fallback) {
    if (typeof vortemEmailMarketing !== 'undefined' && vortemEmailMarketing.strings && vortemEmailMarketing.strings[key]) {
      return vortemEmailMarketing.strings[key];
    }
    return fallback;
  }

  // Status translation helper
  function translateStatus(status) {
    if (!status) return status;
    const statusMap = {
      'sent': getTranslatedString('status_sent', 'Sent'),
      'failed': getTranslatedString('status_failed', 'Failed'),
      'pending': getTranslatedString('status_pending', 'Pending')
    };
    return statusMap[status.toLowerCase()] || status;
  }

  // WordPress AJAX wrapper
  const wpAjax = {
    post: function(action, data) {
      return $.ajax({
        url: vortemEmailMarketing.ajaxUrl,
        type: 'POST',
        data: $.extend({
          action: action,
          nonce: vortemEmailMarketing.nonce
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
    el.classList.add('show');
    if (kind === 'error') el.style.borderColor = 'rgba(239,68,68,.5)'; else el.style.borderColor = '';
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2400);
  };

  // Toast Alert Modal - appears at bottom-right and auto-disappears
  const showToastAlert = (message, duration = 4000) => {
    const el = qs('#toast-alert');
    const messageEl = qs('#toast-alert-message');
    if (!el || !messageEl) return;
    
    messageEl.textContent = message;
    el.classList.add('show');
    
    // Clear any existing timeout
    clearTimeout(el._t);
    
    // Auto-hide after duration
    el._t = setTimeout(() => {
      el.classList.remove('show');
    }, duration);
  };

  // Custom Alert/Confirm Modal (keep original implementation)
  function showCustomAlert(options) {
    return new Promise((resolve) => {
      const modal = qs('#alert-modal');
      const iconEl = qs('#alert-icon');
      const titleEl = qs('#alert-modal-title');
      const messageEl = qs('#alert-message');
      const confirmBtn = qs('#alert-confirm');
      const cancelBtn = qs('#alert-cancel');
      const closeBtn = qs('#close-alert-modal');

      const {
        title = getTranslatedString('confirm_action', 'Confirm Action'),
        message = getTranslatedString('are_you_sure', 'Are you sure you want to proceed?'),
        type = 'warning',
        confirmText = getTranslatedString('confirm', 'Confirm'),
        cancelText = getTranslatedString('cancel', 'Cancel'),
        showCancel = true
      } = options;

      const icons = {
        warning: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        danger: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        success: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
        info: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="16" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="12" y1="8" x2="12.01" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`
      };

      iconEl.className = `alert-icon ${type}`;
      iconEl.innerHTML = icons[type] || icons.warning;
      titleEl.textContent = title;
      messageEl.textContent = message;
      confirmBtn.textContent = confirmText;
      cancelBtn.textContent = cancelText;
      cancelBtn.style.display = showCancel ? '' : 'none';

      const cleanup = () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        confirmBtn.onclick = null;
        cancelBtn.onclick = null;
        closeBtn.onclick = null;
        if (modal.backdropHandler) {
          modal.removeEventListener('click', modal.backdropHandler);
        }
      };

      confirmBtn.onclick = () => { cleanup(); resolve(true); };
      cancelBtn.onclick = () => { cleanup(); resolve(false); };
      closeBtn.onclick = () => { cleanup(); resolve(false); };

      modal.backdropHandler = (e) => {
        if (e.target.classList.contains('modal-backdrop')) {
          cleanup();
          resolve(false);
        }
      };
      modal.addEventListener('click', modal.backdropHandler);
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
    });
  }

  function customConfirm(message, title = null, type = 'warning') {
    const defaultTitle = getTranslatedString('confirm_action', 'Confirm Action');
    const defaultConfirm = getTranslatedString('confirm', 'Confirm');
    const defaultCancel = getTranslatedString('cancel', 'Cancel');
    return showCustomAlert({ 
      title: title || defaultTitle, 
      message, 
      type, 
      confirmText: defaultConfirm, 
      cancelText: defaultCancel 
    });
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
  }

  // Email Preview Modal - WordPress compliant alternative to document.write()
  function showEmailPreviewModal(options) {
    const {
      subject = '',
      recipient = '',
      content = '',
      recipients = [],
      recipientCount = 0,
      isListPreview = false
    } = options;

    const modal = qs('#email-preview-modal');
    const root = qs('#email-preview-root');
    const closeBtn = qs('#close-email-preview-modal');

    if (!modal || !root) {
      showToast('Preview modal not found', 'error');
      return;
    }

    // Build preview HTML using safe DOM construction
    let html = '<div class="email-preview-container">';
    html += '<div class="email-preview-header">';
    html += `<div class="email-preview-subject">${escapeHtml(subject || '(No subject)')}</div>`;
    
    if (isListPreview) {
      html += `<div class="email-preview-recipient">${getTranslatedString('recipients', 'Recipients')}: ${recipientCount}</div>`;
    } else {
      html += `<div class="email-preview-recipient">${getTranslatedString('to', 'To')}: ${escapeHtml(recipient)}</div>`;
    }
    
    html += '</div>';

    if (isListPreview && recipients.length > 0) {
      html += '<div class="email-preview-section-label">' + getTranslatedString('recipient_list', 'Recipient List') + ':</div>';
      html += '<div class="email-preview-recipients-list">';
      recipients.forEach(r => {
        const email = typeof r === 'string' ? r : (r.email || r.recipient || r.address || String(r));
        html += `<code>${escapeHtml(email)}</code>`;
      });
      html += '</div>';
    }

    html += '<div class="email-preview-section-label">' + getTranslatedString('email_content', 'Email Content') + ':</div>';
    html += `<div class="email-preview-body">${content || '<p>(No content)</p>'}</div>`;
    html += '</div>';

    // Set content using innerHTML (content is already escaped where needed)
    root.innerHTML = html;

    // Show modal
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    // Setup close handlers
    const closeModal = () => {
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
      root.innerHTML = ''; // Clean up
      closeBtn.onclick = null;
      if (modal.backdropHandler) {
        modal.removeEventListener('click', modal.backdropHandler);
      }
    };

    closeBtn.onclick = closeModal;

    modal.backdropHandler = (e) => {
      if (e.target.classList.contains('modal-backdrop')) {
        closeModal();
      }
    };
    modal.addEventListener('click', modal.backdropHandler);

    // Close on Escape key
    const escapeHandler = (e) => {
      if (e.key === 'Escape' && modal.classList.contains('show')) {
        closeModal();
        document.removeEventListener('keydown', escapeHandler);
      }
    };
    document.addEventListener('keydown', escapeHandler);
  }

  // WordPress AJAX API wrapper
  const API = {
    createEmail: (data) => wpAjax.post('vortem_em_create_email', data).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getEmails: () => wpAjax.post('vortem_em_get_emails').then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    searchEmails: (params) => wpAjax.post('vortem_em_search_emails', params).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getEmailById: (id) => wpAjax.post('vortem_em_get_email', {email_id: id}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getEmailStatus: (id) => wpAjax.post('vortem_em_get_email_status', {email_id: id}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    updateEmail: (id, data) => wpAjax.post('vortem_em_update_email', Object.assign({email_id: id}, data)).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    deleteEmail: (id) => wpAjax.post('vortem_em_delete_email', {email_id: id}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    bulkDeleteEmails: (ids) => wpAjax.post('vortem_em_bulk_delete_emails', {email_ids: ids}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    sendEmail: (id) => wpAjax.post('vortem_em_send_email', {email_id: id}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    sendEmailList: (listId) => wpAjax.post('vortem_em_send_email_list', {email_list_id: listId}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getUseg: () => wpAjax.post('vortem_em_get_useg').then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    getEmailLists: () => wpAjax.post('vortem_em_get_email_lists').then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    createEmailList: (data) => wpAjax.post('vortem_em_create_email_list', data).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    updateEmailList: (data) => wpAjax.post('vortem_em_update_email_list', data).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
    deleteEmailList: (id) => wpAjax.post('vortem_em_delete_email_list', {email_list_id: id}).then(r => r.success ? r.data : Promise.reject(new Error(r.data?.message || 'Failed'))),
  };

  // Tabs (remove login/auth checks - WordPress handles auth)
  qsa('.tab').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');
      // Remove active class from all tabs
      qsa('.tab').forEach(b => b.classList.remove('active'));
      // Force a reflow to ensure styles are reset
      void document.body.offsetHeight;
      // Add active class to all tabs with the same data-tab value
      qsa(`.tab[data-tab="${tab}"]`).forEach(t => {
        t.classList.add('active');
        // Force a reflow to ensure styles are applied immediately
        void t.offsetHeight;
      });
      // Update panels
      qsa('.panel').forEach(p => p.classList.remove('active'));
      qs(`#panel-${tab}`).classList.add('active');
    });
  });

  // EMAILS PANEL
  const emailsTableBody = qs('#emails-table tbody');
  const refreshEmailsBtn = qs('#refresh-emails');
  const searchInput = qs('#search-emails');
  const cardsGrid = qs('#emails-cards');
  const tableWrap = qs('#emails-table-wrap');
  const createModal = qs('#create-modal');
  const openCreateModalBtn = qs('#open-create-modal');
  const closeCreateModalBtn = qs('#close-create-modal');
  const viewCardsBtn = qs('#view-cards');
  const viewTableBtn = qs('#view-table');
  let currentEditEmailId = null;
  let originalEmailData = null; // Store original email data when editing
  let currentEmails = [];
  let allEmails = []; // Store all emails for client-side pagination
  let viewMode = localStorage.getItem('em_view_mode') || 'table';
  let currentPage = 1;
  let totalCount = 0;
  let itemsPerPage = 12;
  let searchQuery = '';
  let searchTimeout = null;

  function openCreateModal() {
    if (!createModal) return;
    currentEditEmailId = null;
    originalEmailData = null; // Clear original data when creating new
    const title = qs('#create-modal-title');
    if (title) title.textContent = getTranslatedString('create_new_email', 'Create New Email');
    
    // Reset button text to "Send Email" when creating new
    const sendEmailBtn = qs('#send-email-btn');
    if (sendEmailBtn) {
      sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
    }
    
    createModal.classList.add('show');
    createModal.setAttribute('aria-hidden', 'false');
    
    // Wait for the modal to be visible, then bind TinyMCE to the existing
    // wrapper rendered by wp_editor() in PHP. We must NOT call
    // wp.editor.initialize(id, settings) here — that re-renders the
    // toolbar / Add Media / Visual-Code wrapper on top of the existing one,
    // causing duplicates. tinymce.init() with WP's cached preInit settings
    // attaches to the existing markup without rebuilding it.
    setTimeout(() => {
      if (typeof tinymce === 'undefined') return;
      const editor = tinymce.get('email_content');
      if (editor) {
        editor.show();
        editor.nodeChanged();
        return;
      }
      if (typeof tinyMCEPreInit !== 'undefined' && tinyMCEPreInit.mceInit && tinyMCEPreInit.mceInit['email_content']) {
        tinymce.init(tinyMCEPreInit.mceInit['email_content']);
        if (window.quicktags && tinyMCEPreInit.qtInit && tinyMCEPreInit.qtInit['email_content']) {
          window.quicktags(tinyMCEPreInit.qtInit['email_content']);
        }
      }
    }, 150);
  }
  function closeCreateModal() {
    if (!createModal) return;
    
    // Save TinyMCE content to textarea before closing
    if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
      tinymce.get('email_content').save();
    }
    
    createModal.classList.remove('show');
    createModal.setAttribute('aria-hidden', 'true');
  }
  openCreateModalBtn?.addEventListener('click', openCreateModal);
  closeCreateModalBtn?.addEventListener('click', closeCreateModal);
  createModal?.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) closeCreateModal();
  });

  function formatEmailStatus(status) {
    if (!status) return '<span class="muted">—</span>';
    const readCount = status.readCount || 0;
    const sentCount = status.sentCount || 0;
    const revision = status.revision || 0;
    const readPercent = status.read_percent || 0;
    const sentPercent = status.sent_percent || 0;
    
    return `
      <div style="display: flex; flex-direction: column; gap: 4px; font-size: 12px;">
        <div><strong>${getTranslatedString('sent', 'Sent')}:</strong> ${sentCount} (${sentPercent}%)</div>
        <div><strong>${getTranslatedString('read', 'Read')}:</strong> ${readCount} (${readPercent}%)</div>
        <div><strong>${getTranslatedString('revision', 'Revision')}:</strong> ${revision}</div>
      </div>
    `;
  }

  async function renderEmailStats(emails, total) {
    const host = qs('#emails-stats');
    
    // Show loading state
    host.innerHTML = `
      <div class="stat-card"><div class="stat-title">${getTranslatedString('total', 'Total')}</div><div class="stat-value">${total}</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('sent', 'Sent')}</div><div class="stat-value">—</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('read', 'Read')}</div><div class="stat-value">—</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('revision', 'Revision')}</div><div class="stat-value">—</div></div>
    `;
    
    // Fetch statuses for all emails
    const statusMap = await fetchEmailStatuses(emails);
    
    // Aggregate stats
    let totalSent = 0;
    let totalRead = 0;
    let totalRevision = 0;
    let emailsWithStatus = 0;
    let emailsSent = 0;
    let emailsRead = 0;
    
    emails.forEach(email => {
      const status = statusMap[email.id];
      if (status) {
        const sentCount = status.sentCount || 0;
        const readCount = status.readCount || 0;
        totalSent += sentCount;
        totalRead += readCount;
        totalRevision += status.revision || 0;
        if (sentCount > 0) emailsSent++;
        if (readCount > 0) emailsRead++;
        emailsWithStatus++;
      }
    });
    
    // Calculate percentages based on how many emails have been sent/read
    const sentPercent = emails.length > 0 ? Math.round((emailsSent / emails.length) * 100) : 0;
    const readPercent = emails.length > 0 ? Math.round((emailsRead / emails.length) * 100) : 0;
    
    // Render stats
    host.innerHTML = `
      <div class="stat-card"><div class="stat-title">${getTranslatedString('total', 'Total')}</div><div class="stat-value">${total}</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('sent', 'Sent')}</div><div class="stat-value">${totalSent} (${sentPercent}%)</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('read', 'Read')}</div><div class="stat-value">${totalRead} (${readPercent}%)</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('revision', 'Revision')}</div><div class="stat-value">${totalRevision}</div></div>
    `;
  }

  function rowHtml(email, statusData = null) {
    const created = email.created_at ? new Date(email.created_at).toLocaleString() : '—';
    const statusHtml = statusData ? formatEmailStatus(statusData) : '<span class="muted">Loading...</span>';
    return `
      <tr data-id="${email.id}">
        <td>${escapeHtml(email.recipient)}</td>
        <td>${escapeHtml(email.email_subject)}</td>
        <td class="email-status-cell" data-email-id="${email.id}">${statusHtml}</td>
        <td>${created}</td>
        <td>
          <button class="btn btn-small" data-action="show" title="${getTranslatedString('show', 'Show')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small" data-action="edit" title="${getTranslatedString('edit', 'Edit')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small btn-danger" data-action="delete" title="${getTranslatedString('delete', 'Delete')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </td>
      </tr>
    `;
  }

  function updateViewMode() {
    if (viewMode === 'cards') {
      cardsGrid.style.display = '';
      tableWrap.style.display = 'none';
      viewCardsBtn.setAttribute('aria-pressed', 'true');
      viewTableBtn.setAttribute('aria-pressed', 'false');
    } else {
      cardsGrid.style.display = 'none';
      tableWrap.style.display = '';
      viewCardsBtn.setAttribute('aria-pressed', 'false');
      viewTableBtn.setAttribute('aria-pressed', 'true');
    }
    localStorage.setItem('em_view_mode', viewMode);
  }

  viewCardsBtn?.addEventListener('click', () => { viewMode = 'cards'; updateViewMode(); });
  viewTableBtn?.addEventListener('click', () => { viewMode = 'table'; updateViewMode(); });

  async function fetchEmailStatuses(emails) {
    const statusMap = {};
    const statusPromises = emails.map(async (email) => {
      try {
        const status = await API.getEmailStatus(email.id);
        statusMap[email.id] = status;
      } catch (e) {
        VortemLogger.error(`Failed to fetch status for email ${email.id}:`, e);
        statusMap[email.id] = null;
      }
    });
    await Promise.all(statusPromises);
    return statusMap;
  }

  async function renderEmails(emails) {
    // Render initial rows with loading status
    emailsTableBody.innerHTML = emails.map(email => rowHtml(email)).join('');
    renderCards(emails);
    
    // Fetch statuses and update display
    const statusMap = await fetchEmailStatuses(emails);
    
    // Update table rows
    emails.forEach(email => {
      const statusCell = qs(`tr[data-id="${email.id}"] .email-status-cell`);
      if (statusCell) {
        statusCell.innerHTML = formatEmailStatus(statusMap[email.id]);
      }
    });
    
    // Update cards
    emails.forEach(email => {
      const statusElement = qs(`.email-card[data-id="${email.id}"] .email-card-status`);
      if (statusElement) {
        statusElement.innerHTML = formatEmailStatus(statusMap[email.id]);
      }
    });
  }

  function renderCards(list, statusMap = {}) {
    if (!cardsGrid) return;
    cardsGrid.innerHTML = list.map(email => {
      const statusData = statusMap[email.id] || null;
      const statusHtml = statusData ? formatEmailStatus(statusData) : '<span class="muted">Loading...</span>';
      return `
      <div class="email-card" data-id="${email.id}">
        <div class="email-card-header">
          <div class="email-card-subject" title="${escapeHtml(email.email_subject)}">${escapeHtml(email.email_subject)}</div>
        </div>
        <div class="email-card-body">
          <div><strong>${getTranslatedString('to', 'To:')}</strong> ${escapeHtml(email.recipient)}</div>
          <div class="email-card-created-row">
            <span><strong>${getTranslatedString('created', 'Created:')}</strong> ${email.created_at ? new Date(email.created_at).toLocaleString() : '—'}</span>
          </div>
          <div class="email-card-status" data-email-id="${email.id}" style="margin-top: 8px;">
            ${statusHtml}
          </div>
        </div>
        <div class="email-card-actions">
          <button class="btn btn-small" data-action="show" title="${getTranslatedString('show', 'Show')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small" data-action="edit" title="${getTranslatedString('edit', 'Edit')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small btn-danger" data-action="delete" title="${getTranslatedString('delete', 'Delete')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
      </div>
    `;
    }).join('');
  }

  async function loadEmails(page = 1, search = '') {
    try {
      // Trim search query
      const searchQuery = search ? search.trim() : '';
      
      let data;
      if (searchQuery) {
        // Use search API for server-side pagination when search is provided
        data = await API.searchEmails({ q: searchQuery, page, limit: itemsPerPage });
        
        // Get regular emails
        let emails = data.emails || [];
        
        // Extract single-recipient emails from email_lists
        // These are individual emails that were sent as part of a list but only have 1 recipient
        if (data.email_lists && Array.isArray(data.email_lists)) {
          const singleRecipientEmails = data.email_lists
            .filter(list => {
              // Only include lists with exactly 1 recipient (these are individual emails)
              return list.email_recipients && Array.isArray(list.email_recipients) && list.email_recipients.length === 1;
            })
            .map(list => ({
              id: list.id,
              recipient: list.email_recipients[0], // Get the single recipient
              email_subject: list.email_subject || '',
              email_content: list.email_content || '',
              created_at: list.created_at || list.updated_at,
              updated_at: list.updated_at || list.created_at
            }));
          
          // Combine regular emails with single-recipient emails from lists
          emails = [...emails, ...singleRecipientEmails];
        }
        
        currentEmails = emails;
        totalCount = data.total_count || 0;
        allEmails = []; // Clear allEmails since we're using server-side pagination
      } else {
        // Use getEmails API for client-side pagination when search is empty (like before)
        data = await API.getEmails();
        
        // Get regular emails
        let emails = data.emails || [];
        
        // Extract single-recipient emails from email_lists
        // These are individual emails that were sent as part of a list but only have 1 recipient
        if (data.email_lists && Array.isArray(data.email_lists)) {
          const singleRecipientEmails = data.email_lists
            .filter(list => {
              // Only include lists with exactly 1 recipient (these are individual emails)
              return list.email_recipients && Array.isArray(list.email_recipients) && list.email_recipients.length === 1;
            })
            .map(list => ({
              id: list.id,
              recipient: list.email_recipients[0], // Get the single recipient
              email_subject: list.email_subject || '',
              email_content: list.email_content || '',
              created_at: list.created_at || list.updated_at,
              updated_at: list.updated_at || list.created_at
            }));
          
          // Combine regular emails with single-recipient emails from lists
          emails = [...emails, ...singleRecipientEmails];
        }
        
        allEmails = emails;
        totalCount = allEmails.length;
        
        // Adjust page if it's out of bounds
        const totalPages = Math.ceil(totalCount / itemsPerPage);
        if (page > totalPages && totalPages > 0) {
          page = totalPages;
        } else if (page < 1) {
          page = 1;
        }
        
        // Calculate pagination slice
        const startIndex = (page - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        currentEmails = allEmails.slice(startIndex, endIndex);
      }
      
      currentPage = page;
      await renderEmails(currentEmails);
      await renderEmailStats(searchQuery ? currentEmails : allEmails, totalCount);
      renderPagination();
    } catch (e) {
      showToast(`Failed to load emails: ${e.message}`, 'error');
    }
  }

  function renderPagination() {
    const paginationContainer = qs('#pagination-container');
    if (!paginationContainer) return;
    const totalPages = Math.ceil(totalCount / itemsPerPage);
    if (totalPages <= 1) {
      paginationContainer.innerHTML = '';
      return;
    }
    let paginationHTML = '<div class="pagination">';
    paginationHTML += `<button class="btn btn-small ${currentPage === 1 ? 'disabled' : ''}" id="prev-page" ${currentPage === 1 ? 'disabled' : ''}><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="15 18 9 12 15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Previous</button>`;
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    if (startPage > 1) {
      paginationHTML += `<button class="btn btn-small pagination-page" data-page="1">1</button>`;
      if (startPage > 2) paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
    for (let i = startPage; i <= endPage; i++) {
      paginationHTML += `<button class="btn btn-small pagination-page ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) paginationHTML += `<span class="pagination-ellipsis">...</span>`;
      paginationHTML += `<button class="btn btn-small pagination-page" data-page="${totalPages}">${totalPages}</button>`;
    }
    paginationHTML += `<button class="btn btn-small ${currentPage === totalPages ? 'disabled' : ''}" id="next-page" ${currentPage === totalPages ? 'disabled' : ''}>Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="9 18 15 12 9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>`;
    paginationHTML += `<div class="pagination-info">Page ${currentPage} of ${totalPages} (${totalCount} total)</div></div>`;
    paginationContainer.innerHTML = paginationHTML;
    qs('#prev-page')?.addEventListener('click', () => { if (currentPage > 1) loadEmails(currentPage - 1, searchQuery); });
    qs('#next-page')?.addEventListener('click', () => { if (currentPage < totalPages) loadEmails(currentPage + 1, searchQuery); });
    qsa('.pagination-page').forEach(btn => {
      btn.addEventListener('click', () => {
        const page = parseInt(btn.getAttribute('data-page'));
        loadEmails(page, searchQuery);
      });
    });
  }


  emailsTableBody?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const tr = e.target.closest('tr');
    const id = tr?.getAttribute('data-id');
    if (!id) return;
    try {
      if (btn.getAttribute('data-action') === 'delete') {
        const confirmed = await customConfirm(
          'Are you sure you want to delete this email? This action cannot be undone.',
          'Delete Email',
          'danger'
        );
        if (!confirmed) return;
        await API.deleteEmail(id);
        showToast('Email deleted');
        await loadEmails(currentPage, searchQuery);
      } else if (btn.getAttribute('data-action') === 'show') {
        await showEmailPreview(id);
      } else if (btn.getAttribute('data-action') === 'edit') {
        await openEditEmail(id);
      }
    } catch (err) {
      showToast(err.message || 'Action failed', 'error');
    }
  });

  cardsGrid?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const card = e.target.closest('.email-card');
    const id = card?.getAttribute('data-id');
    if (!id) return;
    try {
      if (btn.getAttribute('data-action') === 'delete') {
        const confirmed = await customConfirm(
          'Are you sure you want to delete this email? This action cannot be undone.',
          'Delete Email',
          'danger'
        );
        if (!confirmed) return;
        await API.deleteEmail(id);
        showToast('Email deleted');
        await loadEmails(currentPage, searchQuery);
      } else if (btn.getAttribute('data-action') === 'show') {
        await showEmailPreview(id);
      } else if (btn.getAttribute('data-action') === 'edit') {
        await openEditEmail(id);
      }
    } catch (err) { showToast(err.message || 'Action failed', 'error'); }
  });


  refreshEmailsBtn?.addEventListener('click', () => {
    loadEmails(currentPage, searchQuery);
  });

  searchInput?.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    searchQuery = query;
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      if (query) {
        loadEmails(1, query);
      } else {
        loadEmails(1, '');
      }
    }, 500);
  });

  searchInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (searchTimeout) clearTimeout(searchTimeout);
      const query = e.target.value.trim();
      searchQuery = query;
      loadEmails(1, query);
    }
  });

  // CREATE EMAIL PANEL
  const createForm = qs('#create-email-form');
  const previewBtn = qs('#preview-email');

  // Clear an inline field error as soon as the user starts editing the
  // offending input — otherwise the red message stays even after the value
  // is fixed and the next submit attempt is confusing.
  function attachInlineErrorClearers(form, ids) {
    if (!form) return;
    ids.forEach(id => {
      const el = form.querySelector('#' + id);
      if (!el) return;
      const handler = () => {
        const row = el.closest('.form-row');
        if (!row) return;
        qsa('.vortem-em-field-error', row).forEach(e2 => e2.remove());
        row.classList.remove('has-error');
      };
      el.addEventListener('input', handler);
      el.addEventListener('change', handler);
    });
  }
  attachInlineErrorClearers(createForm, ['recipient', 'email_subject', 'email_content']);

  // Reset handler for email form
  createForm?.addEventListener('reset', (e) => {
    clearFieldErrors(createForm);
    currentEditEmailId = null;
    originalEmailData = null; // Clear original data on reset
    const title = qs('#create-modal-title');
    if (title) title.textContent = getTranslatedString('create_new_email', 'Create New Email');
    
    // Re-enable Send Email button
    const sendEmailBtn = qs('#send-email-btn');
    if (sendEmailBtn) {
      sendEmailBtn.disabled = false;
      sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
    }
    
    // Clear TinyMCE content after form reset
    setTimeout(() => {
      if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
        tinymce.get('email_content').setContent('');
      }
    }, 100);
  });
  
  createForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Get content from TinyMCE if available, otherwise from textarea
    let emailContent = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
      const editor = tinymce.get('email_content');
      editor.save();
      emailContent = editor.getContent() || '';
    } else {
      const textarea = qs('#email_content');
      emailContent = textarea ? textarea.value : '';
    }
    
    const form = new FormData(createForm);
    const payload = {
      recipient: String(form.get('recipient') || '').trim(),
      email_subject: String(form.get('email_subject') || '').trim(),
      email_content: emailContent.trim(),
      email_created_time: new Date().toISOString(),
    };
    
    const errs = validateCreate(payload);
    if (Object.keys(errs).length) {
      renderFieldErrors(createForm, errs, {
        recipient: 'recipient',
        email_subject: 'email_subject',
        email_content: 'email_content',
      });
      // Surface a summary at the corner so the user notices even if they
      // can't see the offending field (e.g. body error scrolled off-screen).
      showToastAlert(Object.values(errs).join(' '), 6000);
      return;
    }
    clearFieldErrors(createForm);
    
    // Disable Send Email button after validation passes
    const sendEmailBtn = qs('#send-email-btn');
    if (sendEmailBtn) {
      sendEmailBtn.disabled = true;
      sendEmailBtn.textContent = getTranslatedString('sending', 'Sending...');
    }
    
    try {
      if (currentEditEmailId) {
        // Check if subject or body has changed
        if (originalEmailData) {
          const subjectChanged = payload.email_subject !== originalEmailData.email_subject;
          const bodyChanged = payload.email_content !== originalEmailData.email_content;
          
          if (!subjectChanged && !bodyChanged) {
            showToastAlert('You must change at least one field (subject or body) before updating and sending the email.', 5000);
            // Re-enable button
            if (sendEmailBtn) {
              sendEmailBtn.disabled = false;
              sendEmailBtn.textContent = getTranslatedString('update_send', 'Update & Send');
            }
            return;
          }
        }
        
        await API.updateEmail(currentEditEmailId, {
          recipient: payload.recipient,
          email_subject: payload.email_subject,
          email_content: payload.email_content,
        });
        showToast('Email updated successfully');
        createForm.reset();

        // Clear TinyMCE content
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
          tinymce.get('email_content').setContent('');
        }

        closeCreateModal();
        currentEditEmailId = null;
        originalEmailData = null;

        // Re-enable button
        if (sendEmailBtn) {
          sendEmailBtn.disabled = false;
          sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
        }

        // Refresh the emails list before the success modal so the updated row
        // is visible the moment the user dismisses the alert.
        document.querySelector('.tab[data-tab="emails"]').click();
        await loadEmails(1, '');

        await showCustomAlert({
          title: 'Email Updated',
          message: 'Your email has been updated successfully.',
          type: 'success',
          confirmText: 'OK',
          showCancel: false
        });
      } else {
        const createRes = await API.createEmail(payload);
        const id = createRes.email_id || createRes.id;
        const res = await API.sendEmail(id);
        const meta = summarizeBulkResponse(res);
        showToast(meta ? `${getTranslatedString('sent_with_colon', 'Sent:')} ${meta.sent}, ${getTranslatedString('failed_with_colon', 'Failed:')} ${meta.failed}` : getTranslatedString('email_sent_toast', 'Email sent'));
        createForm.reset();

        // Clear TinyMCE content
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
          tinymce.get('email_content').setContent('');
        }

        // Close create modal first
        closeCreateModal();
        currentEditEmailId = null;

        // Re-enable button
        if (sendEmailBtn) {
          sendEmailBtn.disabled = false;
          sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
        }

        // Refresh the emails list before the success modal so the freshly
        // sent email is visible the moment the user dismisses the alert
        // (instead of waiting on the modal's 3s auto-close).
        document.querySelector('.tab[data-tab="emails"]').click();
        if (searchInput) searchInput.value = '';
        searchQuery = '';
        await loadEmails(1, '');

        // Show Email Sent modal with countdown
        const message = meta ? `${getTranslatedString('successfully_sent', 'Successfully sent')} ${meta.sent} ${getTranslatedString('email_s', 'email(s)')}. ${meta.failed > 0 ? `${meta.failed} ${getTranslatedString('failed', 'failed')}.` : ''}` : getTranslatedString('email_sent_success', 'Your email has been sent successfully.');
        await showEmailSentModal(message);
      }
    } catch (e2) {
      let toastMessage = 'Failed to send email';
      const errorMsg = e2.message || '';
      
      if (errorMsg.includes('CreateEmailRequest.EmailSubject') || errorMsg.includes('EmailSubject')) {
        if (errorMsg.includes("'min' tag")) {
          toastMessage = 'Email subject must be at least 16 characters';
        } else if (errorMsg.includes("'max' tag")) {
          toastMessage = 'Email subject must not exceed 256 characters';
        }
      } else if (errorMsg.includes('CreateEmailRequest.EmailContent') || errorMsg.includes('EmailContent')) {
        if (errorMsg.includes("'min' tag")) {
          toastMessage = 'Email body must be at least 128 characters';
        } else if (errorMsg.includes("'max' tag")) {
          toastMessage = 'Email body must not exceed 8196 characters';
        }
      } else if (errorMsg) {
        toastMessage = errorMsg;
      }
      
      showToast(toastMessage, 'error');
      
      // Re-enable button on error
      if (sendEmailBtn) {
        sendEmailBtn.disabled = false;
        sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
      }
    }
  });
  
  // Handle form reset to clear TinyMCE content (duplicate handler - keeping for compatibility)
  createForm?.addEventListener('reset', () => {
    // Re-enable Send Email button
    const sendEmailBtn = qs('#send-email-btn');
    if (sendEmailBtn) {
      sendEmailBtn.disabled = false;
      sendEmailBtn.textContent = getTranslatedString('send_email', 'Send Email');
    }
    
    setTimeout(() => {
      if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
        tinymce.get('email_content').setContent('');
      }
    }, 10);
  });

  async function fetchEmailFromMeById(id) {
    const data = await API.getEmails();
    
    // Check regular emails first
    let list = data.emails || [];
    let email = list.find(e => String(e.id) === String(id));
    
    // If not found, check email_lists for single-recipient emails
    if (!email && data.email_lists && Array.isArray(data.email_lists)) {
      const singleRecipientList = data.email_lists.find(list => 
        String(list.id) === String(id) && 
        list.email_recipients && 
        Array.isArray(list.email_recipients) && 
        list.email_recipients.length === 1
      );
      
      if (singleRecipientList) {
        email = {
          id: singleRecipientList.id,
          recipient: singleRecipientList.email_recipients[0],
          email_subject: singleRecipientList.email_subject || '',
          email_content: singleRecipientList.email_content || '',
          created_at: singleRecipientList.created_at || singleRecipientList.updated_at,
          updated_at: singleRecipientList.updated_at || singleRecipientList.created_at
        };
      }
    }
    
    return email;
  }

  async function showEmailPreview(id) {
    try {
      const email = await fetchEmailFromMeById(id);
      const subject = email.email_subject || '';
      const recipient = email.recipient || '';
      const content = email.email_content || '';
      
      // Use WordPress compliant modal instead of document.write()
      showEmailPreviewModal({
        subject: subject,
        recipient: recipient,
        content: content,
        isListPreview: false
      });
    } catch (e) { showToast('Failed to load email', 'error'); }
  }

  async function openEditEmail(id) {
    try {
      const email = await fetchEmailFromMeById(id);
      qs('#recipient').value = email.recipient || '';
      qs('#email_subject').value = email.email_subject || '';
      
      // Set content in textarea first (will be picked up by TinyMCE)
      const textarea = qs('#email_content');
      if (textarea) {
        textarea.value = email.email_content || '';
      }
      
      currentEditEmailId = id;
      
      // Store original email data for comparison
      originalEmailData = {
        email_subject: email.email_subject || '',
        email_content: email.email_content || ''
      };
      
      openCreateModal();
      const title = qs('#create-modal-title');
      if (title) title.textContent = getTranslatedString('edit_email', 'Edit Email');
      
      // Update button text to "Update & Send" when editing
      const sendEmailBtn = qs('#send-email-btn');
      if (sendEmailBtn) {
        sendEmailBtn.textContent = getTranslatedString('update_send', 'Update & Send');
      }
      
      // Set content in TinyMCE after a short delay to ensure editor is initialized
      setTimeout(() => {
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
          const editor = tinymce.get('email_content');
          editor.setContent(email.email_content || '');
        } else if (textarea) {
          textarea.value = email.email_content || '';
        }
      }, 200);
    } catch (e) { showToast('Failed to open editor', 'error'); }
  }

  previewBtn?.addEventListener('click', () => {
    const subject = qs('#email_subject').value || '';
    const recipient = qs('#recipient').value || '';
    
    // Get content from TinyMCE if available, otherwise from textarea
    let content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
      const editor = tinymce.get('email_content');
      editor.save();
      content = editor.getContent() || '';
    } else {
      const textarea = qs('#email_content');
      content = textarea ? textarea.value : '';
    }
    
    // Use WordPress compliant modal instead of document.write()
    showEmailPreviewModal({
      subject: subject,
      recipient: recipient,
      content: content,
      isListPreview: false
    });
  });

  // Mirrors the backend `validate:` tags so the user gets a friendly error
  // before the request goes out. Backend rules: subject 16-256, body 128-8196,
  // valid email recipient(s). Returns an object keyed by canonical field name
  // so callers can render errors inline next to the offending input.
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  function validateEmailFields({ recipient, recipients, email_subject, email_content }) {
    const errs = {};
    if (typeof recipient !== 'undefined') {
      if (!recipient || !EMAIL_RE.test(recipient)) {
        errs.recipient = 'Recipient must be a valid email address.';
      }
    }
    if (typeof recipients !== 'undefined') {
      if (!Array.isArray(recipients) || recipients.length === 0) {
        errs.recipients = 'At least one valid recipient is required.';
      } else {
        const bad = recipients.filter(r => !EMAIL_RE.test(String(r || '').trim()));
        if (bad.length) {
          errs.recipients = `Some recipients are not valid email addresses: ${bad.slice(0, 5).join(', ')}.`;
        }
      }
    }
    const subjectLen = (email_subject || '').length;
    if (subjectLen < 16) {
      errs.email_subject = `Subject must be at least 16 characters (current: ${subjectLen}).`;
    } else if (subjectLen > 256) {
      errs.email_subject = `Subject must not exceed 256 characters (current: ${subjectLen}).`;
    }
    const contentLen = (email_content || '').length;
    if (contentLen < 128) {
      errs.email_content = `Email body must be at least 128 characters (current: ${contentLen}).`;
    } else if (contentLen > 8196) {
      errs.email_content = `Email body must not exceed 8196 characters (current: ${contentLen}).`;
    }
    return errs;
  }

  function validateCreate(p) {
    return validateEmailFields({
      recipient: p.recipient,
      email_subject: p.email_subject,
      email_content: p.email_content,
    });
  }

  // Inline error rendering. `idMap` maps the canonical field key returned by
  // validateEmailFields to the element id of the input on this particular form
  // (the create-email and email-list forms use different ids for the same
  // logical field).
  function clearFieldErrors(form) {
    if (!form) return;
    qsa('.vortem-em-field-error', form).forEach(el => el.remove());
    qsa('.has-error', form).forEach(el => el.classList.remove('has-error'));
  }
  function renderFieldErrors(form, errs, idMap) {
    if (!form) return;
    clearFieldErrors(form);
    Object.entries(errs).forEach(([key, msg]) => {
      const targetId = idMap[key] || key;
      const input = form.querySelector('#' + targetId);
      const row = input ? input.closest('.form-row') : null;
      if (!row) return;
      const errEl = document.createElement('div');
      errEl.className = 'vortem-em-field-error error';
      errEl.setAttribute('role', 'alert');
      errEl.textContent = msg;
      row.appendChild(errEl);
      row.classList.add('has-error');
    });
  }

  function summarizeBulkResponse(res) {
    if (!res || typeof res !== 'object') return null;
    const sent = (typeof res.sent_count === 'number') ? res.sent_count : Array.isArray(res.sent) ? res.sent.length : undefined;
    const failed = (typeof res.failed_count === 'number') ? res.failed_count : Array.isArray(res.failed) ? res.failed.length : undefined;
    if (sent == null && failed == null) return null;
    return { sent: sent || 0, failed: failed || 0 };
  }

  // Email Sent Modal with countdown
  function showEmailSentModal(message) {
    return new Promise((resolve) => {
      const modal = qs('#email-sent-modal');
      const messageEl = qs('#email-sent-message');
      const okBtn = qs('#email-sent-ok');
      const countdownEl = qs('#email-sent-countdown');
      const backdrop = modal ? qs('.modal-backdrop', modal) : null;
      
      if (!modal) {
        resolve();
        return;
      }

      // Set message
      if (messageEl) {
        messageEl.textContent = message || getTranslatedString('email_sent_success', 'Your email has been sent successfully.');
      }

      let countdown = 3;
      let countdownInterval = null;
      let autoCloseTimeout = null;

      let backdropHandler = null;
      
      const cleanup = () => {
        if (countdownInterval) clearInterval(countdownInterval);
        if (autoCloseTimeout) clearTimeout(autoCloseTimeout);
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        if (countdownEl) countdownEl.textContent = '';
        okBtn.onclick = null;
        if (backdropHandler) {
          modal.removeEventListener('click', backdropHandler);
          backdropHandler = null;
        }
      };

      const updateCountdown = () => {
        countdown--;
        
        // When countdown reaches 1, show it, then automatically trigger OK after 1 second
        if (countdown === 1) {
          if (countdownEl) {
            countdownEl.textContent = `(1)`;
          }
          // Clear the interval since we'll handle the final trigger with setTimeout
          if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
          }
          // After 1 more second, automatically trigger OK action (as if user clicked OK)
          setTimeout(() => {
            cleanup();
            resolve();
          }, 1000);
          return;
        }
        
        // If countdown is still greater than 1, show the countdown
        if (countdown > 1) {
          if (countdownEl) {
            countdownEl.textContent = `(${countdown})`;
          }
        } else {
          // This should not happen, but as fallback
          cleanup();
          resolve();
        }
      };

      // Show initial countdown
      if (countdownEl) {
        countdownEl.textContent = `(${countdown})`;
      }
      
      // Update countdown every second
      countdownInterval = setInterval(updateCountdown, 1000);
      
      // Auto-close after 3 seconds (as fallback)
      autoCloseTimeout = setTimeout(() => {
        if (countdownInterval) clearInterval(countdownInterval);
        cleanup();
        resolve();
      }, 3000);

      // OK button handler
      okBtn.onclick = () => {
        cleanup();
        resolve();
      };

      // Backdrop click handler
      backdropHandler = (e) => {
        if (e.target.classList.contains('modal-backdrop') && e.target.getAttribute('data-close') === 'email-sent-modal') {
          cleanup();
          resolve();
        }
      };
      modal.addEventListener('click', backdropHandler);

      // Show modal
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
    });
  }

  // LISTS PANEL (keeping same structure but adapted for WordPress AJAX)
  const listsTableBody = qs('#lists-table tbody');
  const listsCardsGrid = qs('#lists-cards');
  const listsTableWrap = qs('#lists-table-wrap');
  const viewListsCardsBtn = qs('#view-lists-cards');
  const viewListsTableBtn = qs('#view-lists-table');
  const searchListsInput = qs('#search-lists');
  const refreshListsBtn = qs('#refresh-lists');
  const listForm = qs('#email-list-form');
  const listModal = qs('#list-modal');
  const openListModalBtn = qs('#open-list-modal');
  const closeListModalBtn = qs('#close-list-modal');
  const selectAllLists = qs('#select-all-lists');
  const toggleSelectListsBtn = qs('#toggle-select-lists');
  const bulkDeleteListsQuickBtn = qs('#bulk-delete-lists-quick');
  const bulkBarLists = qs('#bulk-bar-lists');
  let currentLists = [];
  let allLists = []; // Store all lists for client-side pagination
  let listsViewMode = localStorage.getItem('em_lists_view_mode') || 'table';
  let listsCurrentPage = 1;
  let listsTotalCount = 0;
  let listsItemsPerPage = 12;
  let listsSearchQuery = '';
  let listsSearchTimeout = null;
  let listsSelectedIds = new Set();
  let listsSelectMode = false;
  let originalListData = null; // Store original list data when editing
  window.listsSelectedIds = listsSelectedIds;

  function openListModal(mode = 'create') {
    if (!listModal) return;
    const title = qs('#list-modal-title');
    if (title) title.textContent = mode === 'edit' ? getTranslatedString('update_email_list', 'Update Email List') : getTranslatedString('create_email_list', 'Create Email List');
    if (mode === 'create') {
      clearEmailTags();
      originalListData = null; // Clear original data when creating new
    }
    
    // Ensure Send button text is correct
    const saveListBtn = qs('#save-list');
    if (saveListBtn) {
      saveListBtn.textContent = mode === 'edit' ? getTranslatedString('update_send_list', 'Update & Send') : getTranslatedString('send', 'Send');
      saveListBtn.disabled = false;
    }
    
    listModal.classList.add('show');
    listModal.setAttribute('aria-hidden', 'false');
    // Focus on input when modal opens
    setTimeout(() => emailTagsInput?.focus(), 150);
  }
  function closeListModal() {
    if (!listModal) return;
    listModal.classList.remove('show');
    listModal.setAttribute('aria-hidden', 'true');
  }
  openListModalBtn?.addEventListener('click', () => {
    qs('#reset-list')?.click();
    openListModal('create');
  });
  closeListModalBtn?.addEventListener('click', closeListModal);
  listModal?.addEventListener('click', (e) => {
    const target = e.target;
    if (target.classList.contains('modal-backdrop')) closeListModal();
  });

  async function renderListsStats(lists, total) {
    const host = qs('#lists-stats');
    if (!host) return;
    
    // Show loading state
    host.innerHTML = `
      <div class="stat-card"><div class="stat-title">${getTranslatedString('total_lists', 'Total Lists')}</div><div class="stat-value">${total}</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('sent', 'Sent')}</div><div class="stat-value">—</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('read', 'Read')}</div><div class="stat-value">—</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('revision', 'Revision')}</div><div class="stat-value">—</div></div>
    `;
    
    // Fetch statuses for all lists
    const statusMap = await fetchListStatuses(lists);
    
    // Aggregate stats
    let totalSent = 0;
    let totalRead = 0;
    let totalRevision = 0;
    let listsWithStatus = 0;
    let listsSent = 0;
    let listsRead = 0;
    
    lists.forEach(list => {
      const status = statusMap[list.id];
      if (status) {
        const sentCount = status.sentCount || 0;
        const readCount = status.readCount || 0;
        totalSent += sentCount;
        totalRead += readCount;
        totalRevision += status.revision || 0;
        if (sentCount > 0) listsSent++;
        if (readCount > 0) listsRead++;
        listsWithStatus++;
      }
    });
    
    // Calculate percentages based on how many lists have been sent/read
    const sentPercent = lists.length > 0 ? Math.round((listsSent / lists.length) * 100) : 0;
    const readPercent = lists.length > 0 ? Math.round((listsRead / lists.length) * 100) : 0;
    
    // Render stats
    host.innerHTML = `
      <div class="stat-card"><div class="stat-title">${getTranslatedString('total_lists', 'Total Lists')}</div><div class="stat-value">${total}</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('sent', 'Sent')}</div><div class="stat-value">${totalSent} (${sentPercent}%)</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('read', 'Read')}</div><div class="stat-value">${totalRead} (${readPercent}%)</div></div>
      <div class="stat-card"><div class="stat-title">${getTranslatedString('revision', 'Revision')}</div><div class="stat-value">${totalRevision}</div></div>
    `;
  }

  function formatListStatus(status) {
    if (!status) return '<span class="muted">—</span>';
    const readCount = status.readCount || 0;
    const sentCount = status.sentCount || 0;
    const revision = status.revision || 0;
    const readPercent = status.read_percent || 0;
    const sentPercent = status.sent_percent || 0;
    
    return `
      <div style="display: flex; flex-direction: column; gap: 4px; font-size: 12px;">
        <div><strong>${getTranslatedString('sent', 'Sent')}:</strong> ${sentCount} (${sentPercent}%)</div>
        <div><strong>${getTranslatedString('read', 'Read')}:</strong> ${readCount} (${readPercent}%)</div>
        <div><strong>${getTranslatedString('revision', 'Revision')}:</strong> ${revision}</div>
      </div>
    `;
  }

  async function fetchListStatuses(lists) {
    const statusMap = {};
    const statusPromises = lists.map(async (list) => {
      try {
        const status = await API.getEmailStatus(list.id);
        statusMap[list.id] = status;
      } catch (e) {
        VortemLogger.error(`Failed to fetch status for list ${list.id}:`, e);
        statusMap[list.id] = null;
      }
    });
    await Promise.all(statusPromises);
    return statusMap;
  }

  function getListStatus(list) {
    // This function is kept for backward compatibility but will be replaced by API calls
    const statusData = getListStatusData(list.id);
    const sent = statusData?.sent_count || statusData?.sent?.length || 0;
    const failed = statusData?.failed_count || statusData?.failed?.length || 0;
    const totalRecipients = (list.email_recipients || []).length;
    const pending = Math.max(0, totalRecipients - sent - failed);
    return { sent, failed, pending, statusData };
  }

  function getListStatusData(listId) {
    try {
      const stored = localStorage.getItem(`email_list_status_${listId}`);
      return stored ? JSON.parse(stored) : null;
    } catch (e) {
      return null;
    }
  }

  function setListStatusData(listId, statusData) {
    try {
      localStorage.setItem(`email_list_status_${listId}`, JSON.stringify(statusData));
    } catch (e) {
      VortemLogger.error('Failed to store status data:', e);
    }
  }

  function listRowHtml(list, statusData = null) {
    const statusHtml = statusData ? formatListStatus(statusData) : '<span class="muted">Loading...</span>';
    const listSelectedIds = window.listsSelectedIds || new Set();
    const isSelected = listSelectedIds.has(String(list.id));
    const recipientCount = (list.email_recipients || []).length;
    
    return `
      <tr data-id="${list.id}">
        <td><input type="checkbox" class="row-check" data-id="${list.id}" ${isSelected ? 'checked' : ''}></td>
        <td>${escapeHtml(list.email_subject || '(no subject)')}</td>
        <td>
          <span style="display:inline-flex;align-items:center;gap:6px;">
            ${recipientCount}
            <button class="btn btn-small toggle-sent-emails" data-list-id="${list.id}" title="${getTranslatedString('sent_emails', 'Sent Emails')}" style="padding:2px 4px;min-width:auto;height:auto;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="toggle-icon">
                <polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </span>
        </td>
        <td class="list-status-cell" data-list-id="${list.id}">${statusHtml}</td>
        <td>${list.created_at ? new Date(list.created_at).toLocaleString() : (list.updated_at ? new Date(list.updated_at).toLocaleString() : '—')}</td>
        <td>
          <button class="btn btn-small" data-action="show" title="${getTranslatedString('show', 'Show')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small" data-action="edit" title="${getTranslatedString('edit', 'Edit')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small btn-danger" data-action="delete" title="${getTranslatedString('delete', 'Delete')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </td>
      </tr>
      <tr class="sent-emails-accordion-row" data-list-id="${list.id}" style="display:none;">
        <td colspan="6" style="padding:0;border-top:none;">
          <div class="sent-emails-accordion-content" style="padding:16px;background:#f9fafb;border-top:1px solid #e5e7eb;">
            <div style="color:#6b7280;font-size:13px;margin-bottom:12px;">Loading sent emails...</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderListsCards(lists, statusMap = {}) {
    if (!listsCardsGrid) return;
    listsCardsGrid.innerHTML = lists.map(list => {
      const statusData = statusMap[list.id] || null;
      const statusHtml = statusData ? formatListStatus(statusData) : '<span class="muted">Loading...</span>';
      const isSelected = listsSelectedIds.has(String(list.id));
      return `
      <div class="email-card" data-id="${list.id}">
        ${listsSelectMode ? `<div class="select-overlay"><input type="checkbox" class="row-check" data-id="${list.id}" ${isSelected ? 'checked' : ''}></div>` : ''}
        <div class="email-card-header">
          <div class="email-card-subject" title="${escapeHtml(list.email_subject || '(no subject)')}">${escapeHtml(list.email_subject || '(no subject)')}</div>
        </div>
        <div class="email-card-body">
          <div style="display:flex;align-items:center;gap:6px;">
            <strong>${getTranslatedString('recipients', 'Recipients')}:</strong> 
            <span>${(list.email_recipients || []).length}</span>
            <button class="btn btn-small toggle-sent-emails" data-list-id="${list.id}" title="View Sent Emails" style="padding:2px 4px;min-width:auto;height:auto;margin-left:4px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="toggle-icon">
                <polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
          <div class="list-card-status" data-list-id="${list.id}" style="margin-top: 8px;">
            ${statusHtml}
          </div>
          <div><strong>${getTranslatedString('updated', 'Updated')}:</strong> ${list.updated_at ? new Date(list.updated_at).toLocaleString() : '—'}</div>
          <div class="sent-emails-accordion-card" data-list-id="${list.id}" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;">
            <div class="sent-emails-accordion-content" style="padding:12px;background:#f9fafb;border-radius:6px;">
              <div style="color:#6b7280;font-size:13px;">Loading sent emails...</div>
            </div>
          </div>
        </div>
        <div class="email-card-actions">
          <button class="btn btn-small" data-action="show" title="${getTranslatedString('show', 'Show')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small" data-action="edit" title="${getTranslatedString('edit', 'Edit')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="btn btn-small btn-danger" data-action="delete" title="${getTranslatedString('delete', 'Delete')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
      </div>
    `;
    }).join('');
  }

  function updateListsViewMode() {
    if (listsViewMode === 'cards') {
      if (listsCardsGrid) listsCardsGrid.style.display = '';
      if (listsTableWrap) listsTableWrap.style.display = 'none';
      if (viewListsCardsBtn) viewListsCardsBtn.setAttribute('aria-pressed', 'true');
      if (viewListsTableBtn) viewListsTableBtn.setAttribute('aria-pressed', 'false');
    } else {
      if (listsCardsGrid) listsCardsGrid.style.display = 'none';
      if (listsTableWrap) listsTableWrap.style.display = '';
      if (viewListsCardsBtn) viewListsCardsBtn.setAttribute('aria-pressed', 'false');
      if (viewListsTableBtn) viewListsTableBtn.setAttribute('aria-pressed', 'true');
    }
    localStorage.setItem('em_lists_view_mode', listsViewMode);
  }

  viewListsCardsBtn?.addEventListener('click', () => { 
    listsViewMode = 'cards'; 
    updateListsViewMode(); 
    // Re-render lists in the new view mode, or reload if data is empty
    if (currentLists && currentLists.length > 0) {
      renderLists(currentLists);
    } else {
      loadLists(listsCurrentPage, listsSearchQuery);
    }
  });
  viewListsTableBtn?.addEventListener('click', () => { 
    listsViewMode = 'table'; 
    updateListsViewMode(); 
    // Re-render lists in the new view mode, or reload if data is empty
    if (currentLists && currentLists.length > 0) {
      renderLists(currentLists);
    } else {
      loadLists(listsCurrentPage, listsSearchQuery);
    }
  });

  function renderListInfo(listId) {
    const statusData = getListStatusData(listId);
    if (!statusData) return '';
    
    const sent = statusData.sent || [];
    const failed = statusData.failed || [];
    
    // Helper to extract email from string or object
    const getEmailAddress = (item) => {
      if (typeof item === 'string') return item;
      if (item && typeof item === 'object') {
        return item.email || item.recipient || item.address || JSON.stringify(item);
      }
      return String(item || '');
    };
    
    let html = '<div class="list-info-details" style="padding:16px;background:var(--bg-tertiary, #f8f9fa);border-radius:8px;">';
    
    if (sent.length > 0) {
      html += '<div style="margin-bottom:16px;"><h4 style="margin:0 0 8px 0;color:var(--accent-success);font-size:14px;font-weight:600;">✓ ' + getTranslatedString('sent_with_checkmark', 'Sent') + ' (' + sent.length + ')</h4>';
      html += '<div style="max-height:200px;overflow-y:auto;padding:8px;background:white;border-radius:4px;">';
      sent.forEach(item => {
        const email = getEmailAddress(item);
        html += '<div style="padding:4px 0;border-bottom:1px solid #e5e7eb;"><code style="font-size:12px;">' + escapeHtml(email) + '</code></div>';
      });
      html += '</div></div>';
    }
    
    if (failed.length > 0) {
      html += '<div><h4 style="margin:0 0 8px 0;color:var(--accent-danger);font-size:14px;font-weight:600;">✗ Failed (' + failed.length + ')</h4>';
      html += '<div style="max-height:200px;overflow-y:auto;padding:8px;background:white;border-radius:4px;">';
      failed.forEach(item => {
        const email = getEmailAddress(item);
        html += '<div style="padding:4px 0;border-bottom:1px solid #e5e7eb;"><code style="font-size:12px;">' + escapeHtml(email) + '</code></div>';
      });
      html += '</div></div>';
    }
    
    html += '</div>';
    return html;
  }

  function toggleListInfo(listId) {
    // Handle table view
    const infoRow = qs(`tr.list-info-row[data-list-id="${listId}"]`);
    if (infoRow) {
      const isVisible = infoRow.classList.contains('show');
      if (isVisible) {
        // Hide with animation
        infoRow.classList.remove('show');
        // After animation completes, hide the row completely
        setTimeout(() => {
          infoRow.style.display = 'none';
        }, 300);
      } else {
        // Show with animation
        // First ensure the row is visible in the DOM
        if (infoRow.style.display === 'none') {
          infoRow.style.display = '';
        }
        const contentCell = qs('.list-info-content', infoRow);
        if (contentCell) {
          contentCell.innerHTML = renderListInfo(listId);
        }
        // Trigger animation by adding show class after a brief delay
        // This allows the browser to calculate the initial state
        requestAnimationFrame(() => {
          infoRow.classList.add('show');
        });
      }
    }
    
    // Handle card view
    const infoCard = qs(`.email-card-info[data-list-id="${listId}"]`);
    if (infoCard) {
      const isVisible = infoCard.classList.contains('show');
      if (isVisible) {
        // Hide with animation
        infoCard.classList.remove('show');
        // After animation completes, hide the card completely
        setTimeout(() => {
          infoCard.style.display = 'none';
        }, 300);
      } else {
        // Show with animation
        // First ensure the card is visible in the DOM
        if (infoCard.style.display === 'none') {
          infoCard.style.display = '';
        }
        infoCard.innerHTML = renderListInfo(listId);
        // Trigger animation by adding show class after a brief delay
        // This allows the browser to calculate the initial state
        requestAnimationFrame(() => {
          infoCard.classList.add('show');
        });
      }
    }
  }

  async function renderLists(lists) {
    // Render initial rows/cards with loading status
    if (listsViewMode === 'cards') {
      renderListsCards(lists);
    } else {
      renderListsTable(lists);
    }
    
    // Fetch statuses and update display
    const statusMap = await fetchListStatuses(lists);
    
    // Update table rows
    lists.forEach(list => {
      const statusCell = qs(`tr[data-id="${list.id}"] .list-status-cell`);
      if (statusCell) {
        statusCell.innerHTML = formatListStatus(statusMap[list.id]);
      }
    });
    
    // Update cards
    lists.forEach(list => {
      const statusElement = qs(`.email-card[data-id="${list.id}"] .list-card-status`);
      if (statusElement) {
        statusElement.innerHTML = formatListStatus(statusMap[list.id]);
      }
    });
  }

  function renderListsTable(lists) {
    if (!listsTableBody) return;
    listsTableBody.innerHTML = lists.map(list => listRowHtml(list)).join('');
  }

  function applyListsFilter() {
    const q = (searchListsInput?.value || '').trim();
    listsSearchQuery = q;
    if (listsSearchTimeout) clearTimeout(listsSearchTimeout);
    listsSearchTimeout = setTimeout(() => {
      loadLists(1, q); // Reset to page 1 when filtering
    }, 500); // Debounce search by 500ms
  }

  async function loadLists(page = 1, search = '') {
    try {
      // Trim search query
      const searchQuery = search ? search.trim() : '';
      
      let data;
      if (searchQuery) {
        // Use search API for server-side pagination when search is provided
        data = await API.searchEmails({ q: searchQuery, page, limit: listsItemsPerPage });
        
        // Get email lists from response
        let emailLists = data.email_lists || [];
        
        // Filter out lists with only 1 recipient (these are individual emails, not lists)
        emailLists = emailLists.filter(list => {
          const recipientCount = (list.email_recipients || []).length;
          return recipientCount !== 1;
        });
        
        currentLists = emailLists;
        // Use email_lists_count from API response, or fallback to total_count
        // Note: This count includes lists with 1 recipient, but we filter them out in display
        listsTotalCount = data.email_lists_count !== undefined ? data.email_lists_count : (data.total_count || 0);
        allLists = []; // Clear allLists since we're using server-side pagination
      } else {
        // Use getEmailLists API for client-side pagination when search is empty (like before)
        data = await API.getEmailLists();
        allLists = data.email_lists || [];
        listsSearchQuery = search;
        
        // Filter out lists with only 1 recipient (these are individual emails, not lists)
        allLists = allLists.filter(list => {
          const recipientCount = (list.email_recipients || []).length;
          return recipientCount !== 1;
        });
        
        listsTotalCount = allLists.length;
        
        // Adjust page if it's out of bounds
        const totalPages = Math.ceil(listsTotalCount / listsItemsPerPage);
        if (page > totalPages && totalPages > 0) {
          page = totalPages;
        } else if (page < 1) {
          page = 1;
        }
        
        // Calculate pagination slice
        const startIndex = (page - 1) * listsItemsPerPage;
        const endIndex = startIndex + listsItemsPerPage;
        currentLists = allLists.slice(startIndex, endIndex);
      }
      
      listsSearchQuery = search;
      listsCurrentPage = page;
      listsSelectedIds.clear();
      if (selectAllLists) selectAllLists.checked = false;
      
      // Render stats cards (TOTAL, SENT, PENDING)
      renderListsStats(searchQuery ? currentLists : allLists, listsTotalCount);
      
      // Render lists (async - will fetch statuses)
      await renderLists(currentLists);
      renderListsPagination();
      updateListsBulkButtons();
    } catch (e) { showToast(`Failed to load lists: ${e.message}`, 'error'); }
  }

  function renderListsPagination() {
    const paginationContainer = qs('#lists-pagination-container');
    if (!paginationContainer) return;
    const totalPages = Math.ceil(listsTotalCount / listsItemsPerPage);
    if (totalPages <= 1) {
      paginationContainer.innerHTML = '';
      return;
    }
    let paginationHTML = '<div class="pagination">';
    paginationHTML += `<button class="btn btn-small ${listsCurrentPage === 1 ? 'disabled' : ''}" id="lists-prev-page" ${listsCurrentPage === 1 ? 'disabled' : ''}><svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="15 18 9 12 15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Previous</button>`;
    const maxVisiblePages = 5;
    let startPage = Math.max(1, listsCurrentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    if (startPage > 1) {
      paginationHTML += `<button class="btn btn-small lists-pagination-page" data-page="1">1</button>`;
      if (startPage > 2) paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
    for (let i = startPage; i <= endPage; i++) {
      paginationHTML += `<button class="btn btn-small lists-pagination-page ${i === listsCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) paginationHTML += `<span class="pagination-ellipsis">...</span>`;
      paginationHTML += `<button class="btn btn-small lists-pagination-page" data-page="${totalPages}">${totalPages}</button>`;
    }
    paginationHTML += `<button class="btn btn-small ${listsCurrentPage === totalPages ? 'disabled' : ''}" id="lists-next-page" ${listsCurrentPage === totalPages ? 'disabled' : ''}>Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="9 18 15 12 9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>`;
    paginationHTML += `<div class="pagination-info">Page ${listsCurrentPage} of ${totalPages} (${listsTotalCount} total)</div></div>`;
    paginationContainer.innerHTML = paginationHTML;
    qs('#lists-prev-page')?.addEventListener('click', () => { if (listsCurrentPage > 1) loadLists(listsCurrentPage - 1, listsSearchQuery); });
    qs('#lists-next-page')?.addEventListener('click', () => { if (listsCurrentPage < totalPages) loadLists(listsCurrentPage + 1, listsSearchQuery); });
    qsa('.lists-pagination-page').forEach(btn => {
      btn.addEventListener('click', () => {
        const page = parseInt(btn.getAttribute('data-page'));
        loadLists(page, listsSearchQuery);
      });
    });
  }

  // Lists Select Mode and Bulk Actions
  toggleSelectListsBtn?.addEventListener('click', () => {
    listsSelectMode = !listsSelectMode;
    toggleSelectListsBtn.textContent = listsSelectMode ? 'Exit Select' : 'Select';
    const table = qs('#lists-table');
    if (listsSelectMode) table.classList.remove('select-disabled');
    else table.classList.add('select-disabled');
    if (!listsSelectMode) { listsSelectedIds.clear(); if (selectAllLists) selectAllLists.checked = false; }
    renderLists(currentLists);
    updateListsBulkButtons();
  });

  selectAllLists?.addEventListener('change', () => {
    if (selectAllLists.checked) {
      currentLists.forEach(l => listsSelectedIds.add(String(l.id)));
    } else {
      listsSelectedIds.clear();
    }
    renderLists(currentLists);
    updateListsBulkButtons();
  });

  listsTableBody?.addEventListener('change', (e) => {
    const target = e.target;
    if (target.classList.contains('row-check')) {
      const id = target.getAttribute('data-id');
      if (target.checked) listsSelectedIds.add(id);
      else listsSelectedIds.delete(id);
      updateListsBulkButtons();
    }
  });

  listsCardsGrid?.addEventListener('change', (e) => {
    const target = e.target;
    if (target.classList.contains('row-check')) {
      const id = target.getAttribute('data-id');
      if (target.checked) listsSelectedIds.add(id);
      else listsSelectedIds.delete(id);
      updateListsBulkButtons();
    }
  });

  function updateListsBulkButtons() {
    if (bulkDeleteListsQuickBtn) {
      bulkDeleteListsQuickBtn.style.display = listsSelectedIds.size > 0 ? '' : 'none';
    }
    updateListsBulkBar();
  }

  function updateListsBulkBar() {
    if (!bulkBarLists) return;
    if (!listsSelectMode || listsSelectedIds.size === 0) {
      bulkBarLists.style.display = 'none';
      return;
    }
    bulkBarLists.style.display = '';
    bulkBarLists.innerHTML = `
      <div class="bulk-left">
        <div class="badge">${listsSelectedIds.size} selected</div>
        <div class="muted">Use actions to manage selection</div>
      </div>
      <div class="bulk-actions">
        <button class="btn btn-small" id="bulk-select-all-lists">${listsSelectedIds.size === currentLists.length ? 'Deselect All' : 'Select All'}</button>
        <button class="btn btn-small btn-danger" id="bulk-delete-lists-bar">Bulk Delete</button>
      </div>
    `;
    qs('#bulk-select-all-lists')?.addEventListener('click', (e) => {
      e.preventDefault();
      if (listsSelectedIds.size === currentLists.length) {
        listsSelectedIds.clear();
      } else {
        currentLists.forEach(l => listsSelectedIds.add(String(l.id)));
      }
      renderLists(currentLists);
      updateListsBulkButtons();
    });
    qs('#bulk-delete-lists-bar')?.addEventListener('click', async (e) => {
      e.preventDefault();
      if (!listsSelectedIds.size) return;
      const confirmed = await customConfirm(
        `Are you sure you want to delete ${listsSelectedIds.size} selected list(s)? This action cannot be undone.`,
        'Delete Selected Lists',
        'danger'
      );
      if (!confirmed) return;
      try {
        const idsArray = Array.from(listsSelectedIds);
        for (const id of idsArray) {
          await API.deleteEmailList(id);
          try {
            localStorage.removeItem(`email_list_status_${id}`);
          } catch (e) {
            VortemLogger.error('Failed to clear status data:', e);
          }
        }
        showToast(`Deleted ${idsArray.length} list(s)`);
        listsSelectedIds.clear();
        await loadLists(listsCurrentPage, listsSearchQuery);
      } catch (e) { showToast(e.message || 'Bulk delete failed', 'error'); }
    });
  }

  bulkDeleteListsQuickBtn?.addEventListener('click', async () => {
    if (!listsSelectedIds.size) return;
    const confirmed = await customConfirm(
      `Are you sure you want to delete ${listsSelectedIds.size} selected list(s)? This action cannot be undone.`,
      'Delete Selected Lists',
      'danger'
    );
    if (!confirmed) return;
    try {
      const idsArray = Array.from(listsSelectedIds);
      for (const id of idsArray) {
        await API.deleteEmailList(id);
        try {
          localStorage.removeItem(`email_list_status_${id}`);
        } catch (e) {
          VortemLogger.error('Failed to clear status data:', e);
        }
      }
      showToast(`Deleted ${idsArray.length} list(s)`);
      listsSelectedIds.clear();
      await loadLists(listsCurrentPage, listsSearchQuery);
    } catch (e) { showToast(e.message || 'Bulk delete failed', 'error'); }
  });

  refreshListsBtn?.addEventListener('click', () => loadLists(1, listsSearchQuery));
  searchListsInput?.addEventListener('input', applyListsFilter);
  searchListsInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (listsSearchTimeout) clearTimeout(listsSearchTimeout);
      const query = (searchListsInput?.value || '').trim();
      listsSearchQuery = query;
      loadLists(1, query);
    }
  });

  async function showListPreview(id) {
    try {
      // Try to find in current page first, then in all lists
      let list = currentLists.find(x => String(x.id) === String(id));
      if (!list) {
        list = allLists.find(x => String(x.id) === String(id));
      }
      if (!list) return showToast('List not found', 'error');
      
      const subject = list.email_subject || '(no subject)';
      const recipients = list.email_recipients || [];
      const content = list.email_content || '(No content)';
      const recipientCount = recipients.length;
      
      // Use WordPress compliant modal instead of document.write()
      showEmailPreviewModal({
        subject: subject,
        content: content,
        recipients: recipients,
        recipientCount: recipientCount,
        isListPreview: true
      });
    } catch (e) { showToast('Failed to load list preview', 'error'); }
  }

  function handleListAction(id, action) {
    if (action === 'delete') {
      customConfirm(
        'Are you sure you want to delete this email list? This action cannot be undone.',
        'Delete Email List',
        'danger'
      ).then(confirmed => {
        if (!confirmed) return;
        API.deleteEmailList(id).then(() => { 
          // Clear status data from localStorage
          try {
            localStorage.removeItem(`email_list_status_${id}`);
          } catch (e) {
            VortemLogger.error('Failed to clear status data:', e);
          }
          showToast('List deleted'); 
          loadLists(listsCurrentPage, listsSearchQuery); 
        }).catch(err => { showToast(err.message || 'Delete failed', 'error'); });
      });
    } else if (action === 'show') {
      showListPreview(id);
    } else if (action === 'edit') {
      // Try to find in current page first, then in all lists
      let match = currentLists.find(x => String(x.id) === String(id));
      if (!match) {
        match = allLists.find(x => String(x.id) === String(id));
      }
      if (!match) return showToast('List not found', 'error');
      qs('#email_list_id').value = match.id;
      qs('#list_subject').value = match.email_subject || '';
      
      // Set email tags from recipients array
      setEmailTags(match.email_recipients || []);
      
      // Set content in TinyMCE if available, otherwise set in textarea
      const content = match.email_content || '';
      if (typeof tinymce !== 'undefined' && tinymce.get('list_content')) {
        tinymce.get('list_content').setContent(content);
      } else {
        const textarea = qs('#list_content');
        if (textarea) textarea.value = content;
      }
      
      // Store original list data for comparison
      originalListData = {
        email_subject: match.email_subject || '',
        email_content: content || ''
      };
      
      openListModal('edit');
    }
  }

  // Sent emails accordion functionality
  async function toggleSentEmailsAccordion(listId) {
    // Handle table view
    const accordionRow = qs(`tr.sent-emails-accordion-row[data-list-id="${listId}"]`);
    let willBeExpanded = false;
    
    if (accordionRow) {
      const isVisible = accordionRow.style.display !== 'none' && accordionRow.classList.contains('show');
      const contentDiv = accordionRow.querySelector('.sent-emails-accordion-content');
      
      if (isVisible) {
        // Hide with animation
        accordionRow.classList.remove('show');
        willBeExpanded = false;
        setTimeout(() => {
          accordionRow.style.display = 'none';
        }, 300);
      } else {
        willBeExpanded = true;
        // Show with animation
        accordionRow.style.display = '';
        if (contentDiv) {
          contentDiv.innerHTML = '<div style="color:#6b7280;font-size:13px;">Loading sent emails...</div>';
        }
        
        // Load sent emails
        try {
          let statusData = getListStatusData(listId);
          if (!statusData) {
            try {
              statusData = await API.getEmailStatus(listId);
              if (statusData) {
                setListStatusData(listId, statusData);
              }
            } catch (e) {
              VortemLogger.error('Failed to fetch status:', e);
            }
          }

          const sent = statusData?.sent || [];
          
          // Helper to extract email from string or object
          const getEmailAddress = (item) => {
            if (typeof item === 'string') return item;
            if (item && typeof item === 'object') {
              return item.email || item.recipient || item.address || JSON.stringify(item);
            }
            return String(item || '');
          };

          if (sent.length === 0) {
            contentDiv.innerHTML = '<div style="color:#6b7280;font-size:13px;padding:8px;">No sent emails found for this list.</div>';
          } else {
            let emailsList = '';
            sent.forEach((item, index) => {
              const email = getEmailAddress(item);
              emailsList += `
                <div style="padding:8px 12px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:8px;background:white;border-radius:4px;margin-bottom:4px;">
                  <span style="color:#6b7280;font-size:12px;min-width:24px;font-weight:500;">${index + 1}.</span>
                  <code style="font-size:13px;font-family:monospace;color:#0f172a;flex:1;">${escapeHtml(email)}</code>
                </div>
              `;
            });
            contentDiv.innerHTML = `
              <div style="margin-bottom:8px;font-weight:600;font-size:13px;color:#0f172a;">${getTranslatedString('sent_emails', 'Sent Emails')} (${sent.length})</div>
              <div style="max-height:300px;overflow-y:auto;">
                ${emailsList}
              </div>
            `;
          }
        } catch (e) {
          VortemLogger.error('Failed to load sent emails:', e);
          if (contentDiv) {
            contentDiv.innerHTML = '<div style="color:#dc2626;font-size:13px;padding:8px;">Failed to load sent emails.</div>';
          }
        }
        
        // Trigger animation
        requestAnimationFrame(() => {
          accordionRow.classList.add('show');
        });
      }
    }
    
    // Handle card view
    const accordionCard = qs(`.sent-emails-accordion-card[data-list-id="${listId}"]`);
    
    if (accordionCard) {
      const isVisible = accordionCard.style.display !== 'none' && accordionCard.classList.contains('show');
      const contentDiv = accordionCard.querySelector('.sent-emails-accordion-content');
      
      if (isVisible) {
        // Hide with animation
        accordionCard.classList.remove('show');
        willBeExpanded = false;
        setTimeout(() => {
          accordionCard.style.display = 'none';
        }, 300);
      } else {
        willBeExpanded = true;
        // Show with animation
        accordionCard.style.display = '';
        if (contentDiv) {
          contentDiv.innerHTML = '<div style="color:#6b7280;font-size:13px;">Loading sent emails...</div>';
        }
        
        // Load sent emails
        try {
          let statusData = getListStatusData(listId);
          if (!statusData) {
            try {
              statusData = await API.getEmailStatus(listId);
              if (statusData) {
                setListStatusData(listId, statusData);
              }
            } catch (e) {
              VortemLogger.error('Failed to fetch status:', e);
            }
          }

          const sent = statusData?.sent || [];
          
          // Helper to extract email from string or object
          const getEmailAddress = (item) => {
            if (typeof item === 'string') return item;
            if (item && typeof item === 'object') {
              return item.email || item.recipient || item.address || JSON.stringify(item);
            }
            return String(item || '');
          };

          if (sent.length === 0) {
            contentDiv.innerHTML = '<div style="color:#6b7280;font-size:13px;padding:8px;">No sent emails found for this list.</div>';
          } else {
            let emailsList = '';
            sent.forEach((item, index) => {
              const email = getEmailAddress(item);
              emailsList += `
                <div style="padding:6px 10px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:8px;background:white;border-radius:4px;margin-bottom:4px;">
                  <span style="color:#6b7280;font-size:12px;min-width:20px;font-weight:500;">${index + 1}.</span>
                  <code style="font-size:12px;font-family:monospace;color:#0f172a;flex:1;">${escapeHtml(email)}</code>
                </div>
              `;
            });
            contentDiv.innerHTML = `
              <div style="margin-bottom:8px;font-weight:600;font-size:13px;color:#0f172a;">${getTranslatedString('sent_emails', 'Sent Emails')} (${sent.length})</div>
              <div style="max-height:250px;overflow-y:auto;">
                ${emailsList}
              </div>
            `;
          }
        } catch (e) {
          VortemLogger.error('Failed to load sent emails:', e);
          if (contentDiv) {
            contentDiv.innerHTML = '<div style="color:#dc2626;font-size:13px;padding:8px;">Failed to load sent emails.</div>';
          }
        }
        
        // Trigger animation
        requestAnimationFrame(() => {
          accordionCard.classList.add('show');
        });
      }
    }
    
    // Update icon rotation
    const toggleBtn = qs(`.toggle-sent-emails[data-list-id="${listId}"]`);
    if (toggleBtn) {
      const icon = toggleBtn.querySelector('.toggle-icon');
      if (icon) {
        icon.style.transform = willBeExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
        icon.style.transition = 'transform 0.3s ease';
      }
    }
  }

  // Event delegation for sent emails accordion toggle
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.toggle-sent-emails');
    if (btn) {
      e.stopPropagation();
      const listId = btn.getAttribute('data-list-id');
      if (listId) {
        toggleSentEmailsAccordion(listId);
      }
    }
  });

  // Add CSS for accordion animation
  if (!document.getElementById('sent-emails-accordion-styles')) {
    const style = document.createElement('style');
    style.id = 'sent-emails-accordion-styles';
    style.textContent = `
      .sent-emails-accordion-row {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
        opacity: 0;
      }
      .sent-emails-accordion-row.show {
        max-height: 500px;
        opacity: 1;
      }
      .sent-emails-accordion-card {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
        opacity: 0;
      }
      .sent-emails-accordion-card.show {
        max-height: 400px;
        opacity: 1;
      }
      .sent-emails-accordion-content {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
      }
      .sent-emails-accordion-content::-webkit-scrollbar {
        width: 6px;
      }
      .sent-emails-accordion-content::-webkit-scrollbar-track {
        background: #f1f5f9;
      }
      .sent-emails-accordion-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
      }
      .sent-emails-accordion-content::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
      }
    `;
    document.head.appendChild(style);
  }

  listsTableBody?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    // Skip if it's a toggle-sent-emails button (handled by document listener)
    if (btn.classList.contains('toggle-sent-emails')) return;
    const tr = e.target.closest('tr');
    const id = tr?.getAttribute('data-id');
    if (!id) return;
    const action = btn.getAttribute('data-action');
    handleListAction(id, action);
  });

  listsCardsGrid?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    // Skip if it's a toggle-sent-emails button (handled by document listener)
    if (btn.classList.contains('toggle-sent-emails')) return;
    const card = e.target.closest('.email-card');
    const id = card?.getAttribute('data-id');
    if (!id) return;
    const action = btn.getAttribute('data-action');
    handleListAction(id, action);
  });

  // Email Tags Management
  const emailTagsWrapper = qs('#email-tags-wrapper');
  const emailTagsInput = qs('#list_recipients_input');
  const addEmailBtn = qs('#add-email-btn');
  const emailRecipientsHidden = qs('#list_recipients');
  let emailTags = [];

  // Mirror the inline-error clearing behavior on the email-list form so red
  // messages don't linger after the user fixes a field.
  attachInlineErrorClearers(listForm, ['list_subject', 'list_recipients_input', 'list_content']);

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
  }

  function renderEmailTags() {
    if (!emailTagsWrapper) return;
    if (emailTags.length === 0) {
      emailTagsWrapper.innerHTML = '';
      updateHiddenInput();
      return;
    }
    emailTagsWrapper.innerHTML = emailTags.map((email, index) => `
      <span class="email-tag" data-index="${index}">
        <span class="email-tag-text">${escapeHtml(email)}</span>
        <button type="button" class="email-tag-remove" aria-label="Remove ${escapeHtml(email)}">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </span>
    `).join('');
    
    // Add event listeners to remove buttons
    qsa('.email-tag-remove', emailTagsWrapper).forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const tag = e.target.closest('.email-tag');
        const index = parseInt(tag.getAttribute('data-index'));
        removeEmailTag(index);
      });
    });
    
    updateHiddenInput();
  }

  function addEmailTag(email) {
    const trimmedEmail = email.trim();
    if (!trimmedEmail) return false;
    
    if (!isValidEmail(trimmedEmail)) {
      showToast('Please enter a valid email address', 'error');
      return false;
    }
    
    if (emailTags.includes(trimmedEmail)) {
      showToast('This email is already added', 'error');
      return false;
    }
    
    emailTags.push(trimmedEmail);
    renderEmailTags();
    if (emailTagsInput) emailTagsInput.value = '';
    // Clear any "recipients required / invalid" inline error now that we have one.
    if (listForm) {
      const row = listForm.querySelector('#list_recipients_input')?.closest('.form-row');
      if (row) {
        qsa('.vortem-em-field-error', row).forEach(e => e.remove());
        row.classList.remove('has-error');
      }
    }
    emailTagsInput?.focus();
    return true;
  }

  function removeEmailTag(index) {
    if (index >= 0 && index < emailTags.length) {
      emailTags.splice(index, 1);
      renderEmailTags();
      emailTagsInput?.focus();
    }
  }

  function updateHiddenInput() {
    if (emailRecipientsHidden) {
      emailRecipientsHidden.value = emailTags.join(', ');
    }
  }

  function setEmailTags(emails) {
    emailTags = Array.isArray(emails) ? emails.filter(e => isValidEmail(e)) : [];
    renderEmailTags();
  }

  function clearEmailTags() {
    emailTags = [];
    renderEmailTags();
  }

  // Handle adding emails
  function handleAddEmail() {
    if (!emailTagsInput) return;
    const email = emailTagsInput.value.trim();
    if (email) {
      addEmailTag(email);
    }
  }

  addEmailBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    handleAddEmail();
  });

  emailTagsInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      handleAddEmail();
    }
  });

  // Handle paste events - parse comma/newline separated emails
  emailTagsInput?.addEventListener('paste', (e) => {
    e.preventDefault();
    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
    const emails = pastedText.split(/[,\n]/g).map(s => s.trim()).filter(Boolean);
    let addedCount = 0;
    emails.forEach(email => {
      if (addEmailTag(email)) addedCount++;
    });
    if (addedCount > 0) {
      showToast(`Added ${addedCount} email(s)`);
    }
  });

  // Reset handler for list form
  qs('#reset-list')?.addEventListener('click', (e) => {
    e.preventDefault();
    qs('#email_list_id').value = '';
    qs('#list_subject').value = '';
    clearEmailTags();
    originalListData = null; // Clear original data on reset
    
    // Clear TinyMCE content if available
    if (typeof tinymce !== 'undefined' && tinymce.get('list_content')) {
      tinymce.get('list_content').setContent('');
    } else {
      const textarea = qs('#list_content');
      if (textarea) textarea.value = '';
    }
    
    // Re-enable Send button
    const saveListBtn = qs('#save-list');
    if (saveListBtn) {
      saveListBtn.disabled = false;
      saveListBtn.textContent = 'Send';
    }
  });

  // Preview handler for list email
  qs('#preview-list-email')?.addEventListener('click', () => {
    const subject = qs('#list_subject').value || '';
    const recipients = emailTags.length > 0 ? emailTags : [];
    
    // Get content from TinyMCE if available, otherwise from textarea
    let content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('list_content')) {
      const editor = tinymce.get('list_content');
      editor.save();
      content = editor.getContent() || '';
    } else {
      const textarea = qs('#list_content');
      content = textarea ? textarea.value : '';
    }
    
    // Use WordPress compliant modal instead of document.write()
    showEmailPreviewModal({
      subject: subject,
      content: content,
      recipients: recipients,
      recipientCount: recipients.length,
      isListPreview: true
    });
  });

  listForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = qs('#email_list_id').value.trim();
    const email_subject = qs('#list_subject').value.trim();
    
    // Get email recipients from tags array
    const email_recipients = emailTags.length > 0 ? emailTags : [];
    
    // Get content from TinyMCE if available, otherwise from textarea
    let email_content = '';
    if (typeof tinymce !== 'undefined' && tinymce.get('list_content')) {
      const editor = tinymce.get('list_content');
      editor.save();
      email_content = editor.getContent() || '';
    } else {
      const textarea = qs('#list_content');
      email_content = textarea ? textarea.value : '';
    }
    email_content = email_content.trim();
    
    const listErrs = validateEmailFields({ recipients: email_recipients, email_subject, email_content });
    if (Object.keys(listErrs).length) {
      renderFieldErrors(listForm, listErrs, {
        recipients: 'list_recipients_input',
        email_subject: 'list_subject',
        email_content: 'list_content',
      });
      showToastAlert(Object.values(listErrs).join(' '), 6000);
      return;
    }
    clearFieldErrors(listForm);
    
    // Get the Send button and disable it
    const saveListBtn = qs('#save-list');
    if (saveListBtn) {
      saveListBtn.disabled = true;
      saveListBtn.textContent = getTranslatedString('sending', 'Sending...');
    }
    
    try {
      let listId = id;
      
      // Create or update the list
      if (id) {
        // Check if subject or body has changed
        if (originalListData) {
          const subjectChanged = email_subject !== originalListData.email_subject;
          const bodyChanged = email_content !== originalListData.email_content;
          
          if (!subjectChanged && !bodyChanged) {
            showToastAlert('You must change at least one field (subject or body) before updating and sending the email list.', 5000);
            // Re-enable button
            if (saveListBtn) {
              saveListBtn.disabled = false;
              saveListBtn.textContent = 'Update & Send';
            }
            return;
          }
        }
        
        await API.updateEmailList({ email_list_id: id, email_subject, email_recipients, email_content: email_content || undefined });
      } else {
        const createResult = await API.createEmailList({ email_subject, email_recipients, email_content: email_content || undefined });
        listId = createResult.email_list_id || createResult.id || createResult.email_list?.id;
      }
      
      if (!listId) {
        throw new Error('Failed to create or update email list');
      }
      
      // Send the email list
      const res = await API.sendEmailList(listId);
      const meta = summarizeBulkResponse(res);
      
      // Store status data
      if (res) {
        const statusData = {
          sent_count: res.sent_count || (Array.isArray(res.sent) ? res.sent.length : 0),
          failed_count: res.failed_count || (Array.isArray(res.failed) ? res.failed.length : 0),
          sent: res.sent || [],
          failed: res.failed || [],
          sent_at: new Date().toISOString()
        };
        setListStatusData(listId, statusData);
      }
      
      // Reset form and close modal
      qs('#reset-list').click();
      originalListData = null; // Clear original data after successful submit
      loadLists(1, listsSearchQuery);
      closeListModal();
      
      // Show Email Sent modal with countdown
      const message = meta ? `${getTranslatedString('successfully_sent', 'Successfully sent')} ${meta.sent} ${getTranslatedString('email_s', 'email(s)')}. ${meta.failed > 0 ? `${meta.failed} ${getTranslatedString('failed', 'failed')}.` : ''}` : getTranslatedString('email_list_sent_success', 'Your email list has been sent successfully.');
      await showEmailSentModal(message);
      
    } catch (err) {
      let toastMessage = 'Failed to send email list';
      const errorMsg = err.message || '';
      
      if (errorMsg.includes('CreateEmailRequest.EmailSubject') || errorMsg.includes('EmailSubject')) {
        if (errorMsg.includes("'min' tag")) {
          toastMessage = 'Email subject must be at least 16 characters';
        } else if (errorMsg.includes("'max' tag")) {
          toastMessage = 'Email subject must not exceed 256 characters';
        }
      } else if (errorMsg.includes('CreateEmailRequest.EmailContent') || errorMsg.includes('EmailContent')) {
        if (errorMsg.includes("'min' tag")) {
          toastMessage = 'Email body must be at least 128 characters';
        } else if (errorMsg.includes("'max' tag")) {
          toastMessage = 'Email body must not exceed 8196 characters';
        }
      } else if (errorMsg) {
        toastMessage = errorMsg;
      }
      
      showToast(toastMessage, 'error');
      
      // Re-enable button on error
      if (saveListBtn) {
        saveListBtn.disabled = false;
        saveListBtn.textContent = 'Send';
      }
    }
  });

  function parseRecipients(text) {
    const parts = String(text || '').split(/[,\n]/g).map(s => s.trim()).filter(Boolean);
    return parts.filter(e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e));
  }

  // Initial loads
  updateViewMode();
  updateListsViewMode();
  loadEmails(1, '').then(() => { updateViewMode(); });
  loadLists(1, '');

})(jQuery);
