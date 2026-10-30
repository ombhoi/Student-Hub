/**
 * Practical 14: Institutional Security Audit Ledger & Aggregated Dashboard Controller
 * File: js/admin_dashboard.js
 * Description: Asynchronous controller for Practical 14.
 *              - Aggregates live system-wide statistics (Students, Events, Registrations, Audits)
 *              - Renders live student event registrations feed and category metrics
 *              - Provides interactive Security Audit Log ledger with instant filtering & search
 *              - Reactively synchronizes when events or student records are modified
 */

(function () {
    'use strict';

    const API_ENDPOINT = '../php/admin_dashboard.php';

    // State
    let currentAuditFilter = 'all';
    let cachedAuditLogs = [];
    let isRefreshing = false;

    // DOM Elements
    const elements = {
        heroStudents: document.getElementById('hero-total-students'),
        heroEvents: document.getElementById('hero-active-events'),
        heroRegistrations: document.getElementById('hero-total-registrations'),
        heroAudits: document.getElementById('hero-total-audits'),
        recentRegContainer: document.getElementById('recent-registrations-container'),
        regCountBadge: document.getElementById('registrations-count-badge'),
        categoryContainer: document.getElementById('category-distribution-container'),
        auditTableBody: document.getElementById('audit-table-body'),
        auditFilterPills: document.querySelectorAll('.audit-pill'),
        auditSearchInput: document.getElementById('audit-search-input'),
        btnRefreshAudit: document.getElementById('btn-refresh-audit')
    };

    /**
     * Escape HTML entities to prevent XSS
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * 1. Load Aggregated System Metrics & Category Distribution
     */
    async function loadOverview() {
        try {
            const response = await fetch(`${API_ENDPOINT}?action=overview&_=${Date.now()}`, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const data = await response.json();

            if (!data.success) throw new Error(data.message || 'Failed to load system metrics.');

            const m = data.metrics || {};

            // Update Hero Counters
            if (elements.heroStudents) elements.heroStudents.textContent = (m.students || 0).toLocaleString();
            if (elements.heroEvents) elements.heroEvents.textContent = (m.events || 0).toLocaleString();
            if (elements.heroRegistrations) elements.heroRegistrations.textContent = (m.registrations || 0).toLocaleString();
            if (elements.heroAudits) elements.heroAudits.textContent = (m.audit_logs_count || 0).toLocaleString();

            // Render Category Distribution
            renderCategoryDistribution(m.category_distribution || [], m.events || 1);
        } catch (err) {
            console.error('[AdminDashboard] Overview load error:', err);
        }
    }

    /**
     * Render Category Distribution Progress Bars
     */
    function renderCategoryDistribution(categories, totalEvents) {
        if (!elements.categoryContainer) return;

        if (!categories || categories.length === 0) {
            elements.categoryContainer.innerHTML = `
                <div class="table-empty-state" style="padding: 24px;">
                    <span class="material-symbols-outlined" style="font-size: 32px;">category</span>
                    <div style="font-size: 13px;">No accredited categories registered yet.</div>
                </div>
            `;
            return;
        }

        const maxCount = Math.max(...categories.map(c => c.count), 1);

        const html = categories.map(cat => {
            const percentage = Math.round((cat.count / Math.max(totalEvents, 1)) * 100);
            const barWidth = Math.round((cat.count / maxCount) * 100);

            return `
                <div class="cat-bar-item">
                    <div class="cat-bar-header">
                        <span class="cat-bar-name">
                            <span class="material-symbols-outlined" style="font-size: 16px; color: var(--primary);">sell</span>
                            <span>${escapeHtml(cat.category)}</span>
                        </span>
                        <span class="cat-bar-count">${cat.count} events (${percentage}%)</span>
                    </div>
                    <div class="cat-progress-track">
                        <div class="cat-progress-fill" style="width: ${barWidth}%;"></div>
                    </div>
                </div>
            `;
        }).join('');

        elements.categoryContainer.innerHTML = html;
    }

    /**
     * 2. Load Recent Event Registrations Feed
     */
    async function loadRecentRegistrations() {
        if (!elements.recentRegContainer) return;

        try {
            const response = await fetch(`${API_ENDPOINT}?action=recent_registrations&limit=5&_=${Date.now()}`, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();

            if (!data.success) throw new Error(data.message || 'Failed to load registrations feed.');

            const list = data.registrations || [];

            if (elements.regCountBadge) {
                elements.regCountBadge.textContent = `${list.length} Recent`;
            }

            if (list.length === 0) {
                elements.recentRegContainer.innerHTML = `
                    <div class="table-empty-state" style="padding: 32px 16px;">
                        <span class="material-symbols-outlined" style="font-size: 36px; color: var(--text-muted);">inbox</span>
                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-secondary);">No student event registrations found</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Student registrations will automatically stream into this feed.</div>
                    </div>
                `;
                return;
            }

            const html = list.map(reg => {
                const initials = (reg.student_name || 'ST')
                    .split(' ')
                    .map(n => n.charAt(0))
                    .join('')
                    .toUpperCase()
                    .substring(0, 2);

                return `
                    <div class="reg-item">
                        <div class="reg-left">
                            <div class="reg-avatar">${escapeHtml(initials)}</div>
                            <div class="reg-info">
                                <div class="reg-student-name">
                                    <span>${escapeHtml(reg.student_name)}</span>
                                    <span class="reg-student-code">#${escapeHtml(reg.student_code || '')}</span>
                                </div>
                                <div class="reg-event-title">${escapeHtml(reg.event_title)}</div>
                            </div>
                        </div>
                        <div class="reg-right">
                            <span class="reg-ticket-code">${escapeHtml(reg.ticket_code || 'CONFIRMED')}</span>
                            <span class="reg-time">${escapeHtml(reg.formatted_date || '')}</span>
                        </div>
                    </div>
                `;
            }).join('');

            elements.recentRegContainer.innerHTML = html;
        } catch (err) {
            console.error('[AdminDashboard] Registrations load error:', err);
            elements.recentRegContainer.innerHTML = `
                <div class="table-empty-state" style="padding: 24px;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: #ef4444;">error</span>
                    <div style="font-size: 13px; color: #ef4444;">Unable to connect to live registrations stream.</div>
                </div>
            `;
        }
    }

    /**
     * 3. Load Institutional Security Audit Logs
     */
    async function loadAuditLogs(filter = 'all') {
        if (!elements.auditTableBody) return;

        currentAuditFilter = filter;

        try {
            const url = `${API_ENDPOINT}?action=audit_logs&action_filter=${encodeURIComponent(filter)}&limit=50&_=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();

            if (!data.success) throw new Error(data.message || 'Failed to fetch audit log trail.');

            cachedAuditLogs = data.logs || [];
            renderAuditTable(cachedAuditLogs);
        } catch (err) {
            console.error('[AdminDashboard] Audit logs load error:', err);
            elements.auditTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-empty-state">
                        <span class="material-symbols-outlined" style="color: #ef4444;">error</span>
                        <div style="color: #ef4444;">Failed to connect to institutional audit log server.</div>
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Render Audit Table Rows
     */
    function renderAuditTable(logs) {
        if (!elements.auditTableBody) return;

        if (!logs || logs.length === 0) {
            elements.auditTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-empty-state">
                        <span class="material-symbols-outlined">verified_user</span>
                        <div>No audit records match the selected filter criteria.</div>
                    </td>
                </tr>
            `;
            return;
        }

        const html = logs.map(log => {
            const actionInfo = getActionBadgeInfo(log.action);
            const logIdFormatted = '#LOG-' + String(log.log_id).padStart(4, '0');

            return `
                <tr>
                    <td><span class="audit-log-id">${logIdFormatted}</span></td>
                    <td><span class="audit-time">${escapeHtml(log.formatted_time || log.created_at)}</span></td>
                    <td>
                        <div class="audit-actor">
                            <span class="audit-actor-name">${escapeHtml(log.user_name || 'Admin User')}</span>
                            <span class="audit-actor-role">${escapeHtml(log.role || 'admin')}</span>
                        </div>
                    </td>
                    <td>
                        <span class="audit-badge ${actionInfo.className}">
                            <span class="material-symbols-outlined" style="font-size: 14px;">${actionInfo.icon}</span>
                            <span>${escapeHtml(log.action)}</span>
                        </span>
                    </td>
                    <td>
                        <span class="audit-entity-tag">${escapeHtml(log.entity_type || 'system')}${log.entity_id ? ` #${log.entity_id}` : ''}</span>
                    </td>
                    <td>
                        <div class="audit-details-text">${escapeHtml(log.details || 'Administrative system operation recorded.')}</div>
                    </td>
                    <td style="text-align: right;">
                        <span class="audit-ip-code">${escapeHtml(log.ip_address || '127.0.0.1')}</span>
                    </td>
                </tr>
            `;
        }).join('');

        elements.auditTableBody.innerHTML = html;
    }

    /**
     * Resolves CSS Class and Material Icon for Action Types
     */
    function getActionBadgeInfo(action) {
        action = (action || '').toUpperCase();

        if (action.startsWith('AUTH_')) {
            return { className: 'audit-badge-auth', icon: 'key' };
        }
        if (action.startsWith('CREATE_') || action.startsWith('INSERT_')) {
            return { className: 'audit-badge-create', icon: 'add_circle' };
        }
        if (action.startsWith('UPDATE_') || action.startsWith('EDIT_')) {
            return { className: 'audit-badge-update', icon: 'edit' };
        }
        if (action.startsWith('DELETE_') || action.startsWith('REMOVE_')) {
            return { className: 'audit-badge-delete', icon: 'delete_forever' };
        }
        if (action === 'STATUS_TOGGLE' || action.includes('STATUS')) {
            return { className: 'audit-badge-status', icon: 'published_with_changes' };
        }

        return { className: 'audit-badge-auth', icon: 'info' };
    }

    /**
     * Filter cached audit records by search keyword
     */
    function handleAuditSearch() {
        if (!elements.auditSearchInput) return;
        const query = elements.auditSearchInput.value.trim().toLowerCase();

        if (!query) {
            renderAuditTable(cachedAuditLogs);
            return;
        }

        const filtered = cachedAuditLogs.filter(log => {
            return (
                String(log.log_id).includes(query) ||
                (log.user_name && log.user_name.toLowerCase().includes(query)) ||
                (log.action && log.action.toLowerCase().includes(query)) ||
                (log.details && log.details.toLowerCase().includes(query)) ||
                (log.entity_type && log.entity_type.toLowerCase().includes(query)) ||
                (log.ip_address && log.ip_address.includes(query))
            );
        });

        renderAuditTable(filtered);
    }

    /**
     * 4. Institutional Audit Quick-Access Notifications (Bell Pop-up)
     */
    function getNotif() {
        return {
            wrapper: document.getElementById('notification-wrapper'),
            btn: document.getElementById('btn-notifications'),
            badge: document.getElementById('notification-badge'),
            dropdown: document.getElementById('notification-dropdown'),
            countTag: document.getElementById('notification-count-tag'),
            list: document.getElementById('notification-list'),
            viewAllBtn: document.getElementById('btn-view-all-audit')
        };
    }

    function getNotifMeta(action) {
        const act = String(action || '').toUpperCase();
        if (act.startsWith('AUTH_') || act.includes('LOGIN') || act.includes('LOGOUT') || act.includes('PASSWORD')) {
            return { icon: 'lock', boxClass: 'sym-auth', itemClass: 'item-auth' };
        }
        if (act.includes('STUDENT')) {
            return { icon: 'person', boxClass: 'sym-student', itemClass: 'item-student' };
        }
        if (act.includes('EVENT')) {
            return { icon: 'event', boxClass: 'sym-event', itemClass: 'item-event' };
        }
        return { icon: 'security', boxClass: 'sym-security', itemClass: 'item-security' };
    }

    async function loadNotifications() {
        const notif = getNotif();
        if (!notif.list) return;

        try {
            const url = `${API_ENDPOINT}?action=audit_logs&action_filter=all&limit=2&_=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();

            const logs = Array.isArray(data.logs) ? data.logs : [];

            if (logs.length > 0) {
                if (notif.badge) notif.badge.classList.add('active');
                if (notif.countTag) notif.countTag.textContent = `${logs.length} New`;

                const html = logs.map(log => {
                    const meta = getNotifMeta(log.action);
                    return `
                        <div class="notification-item ${meta.itemClass}">
                            <div class="notif-symbol-box ${meta.boxClass}">
                                <span class="material-symbols-outlined">${meta.icon}</span>
                            </div>
                            <div class="notif-content">
                                <div class="notif-text">${escapeHtml(log.details || log.action)}</div>
                                <div class="notif-meta">
                                    <span class="notif-actor">${escapeHtml(log.user_name || 'System')}</span>
                                    <span class="notif-time">${escapeHtml(log.formatted_time || '')}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                notif.list.innerHTML = html;
            } else {
                if (notif.badge) notif.badge.classList.remove('active');
                if (notif.countTag) notif.countTag.textContent = '0 New';
                notif.list.innerHTML = `
                    <div style="padding: 18px; text-align: center; color: var(--text-muted); font-size: 12.5px;">
                        <span class="material-symbols-outlined" style="font-size: 24px; color: #10b981; display: block; margin-bottom: 4px;">check_circle</span>
                        No recent security events logged.
                    </div>
                `;
            }
        } catch (err) {
            console.warn('[AdminDashboard] Notifications load notice:', err);
            if (notif.list) {
                notif.list.innerHTML = `
                    <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 12px;">
                        <span class="material-symbols-outlined" style="font-size: 22px; color: var(--primary, #c25e3e); display: block; margin-bottom: 4px;">shield_lock</span>
                        Security ledger live standby
                    </div>
                `;
            }
        }
    }

    function initNotifications() {
        const notif = getNotif();
        if (!notif.btn || !notif.dropdown) return;

        function closeDropdown() {
            const cur = getNotif();
            if (!cur.dropdown) return;
            cur.dropdown.classList.remove('show');
            cur.dropdown.style.display = 'none';
            cur.dropdown.setAttribute('aria-hidden', 'true');
            if (cur.btn) cur.btn.setAttribute('aria-expanded', 'false');
        }

        function openDropdown() {
            const cur = getNotif();
            if (!cur.dropdown) return;
            cur.dropdown.style.display = 'block';
            cur.dropdown.classList.add('show');
            cur.dropdown.setAttribute('aria-hidden', 'false');
            if (cur.btn) cur.btn.setAttribute('aria-expanded', 'true');
            loadNotifications();
        }

        notif.btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const cur = getNotif();
            const isOpen = cur.dropdown && (cur.dropdown.style.display === 'block' || cur.dropdown.classList.contains('show'));
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        // Prevent clicks inside dropdown from bubbling up and closing it
        notif.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            const cur = getNotif();
            if (cur.wrapper && !cur.wrapper.contains(e.target)) {
                closeDropdown();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // Link in footer to view full audit ledger
        if (notif.viewAllBtn) {
            notif.viewAllBtn.addEventListener('click', (e) => {
                closeDropdown();
                const auditSection = document.getElementById('audit');
                if (auditSection) {
                    auditSection.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }

        // Initial fetch for notifications badge count on page load
        loadNotifications();
    }

    /**
     * Master Refresh Handler
     */
    async function refreshAll() {
        if (isRefreshing) return;
        isRefreshing = true;

        if (elements.btnRefreshAudit) {
            const icon = elements.btnRefreshAudit.querySelector('.material-symbols-outlined');
            if (icon) icon.classList.add('table-spinner');
        }

        try {
            await Promise.all([
                loadOverview(),
                loadRecentRegistrations(),
                loadAuditLogs(currentAuditFilter),
                loadNotifications()
            ]);
        } finally {
            if (elements.btnRefreshAudit) {
                const icon = elements.btnRefreshAudit.querySelector('.material-symbols-outlined');
                if (icon) icon.classList.remove('table-spinner');
            }
            isRefreshing = false;
        }
    }

    /**
     * Initialize Event Listeners
     */
    function initListeners() {
        // Filter Pills Click
        if (elements.auditFilterPills) {
            elements.auditFilterPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    elements.auditFilterPills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    const filter = pill.getAttribute('data-filter') || 'all';
                    loadAuditLogs(filter);
                });
            });
        }

        // Live Search Input
        if (elements.auditSearchInput) {
            elements.auditSearchInput.addEventListener('input', handleAuditSearch);
        }

        // Manual Refresh Button
        if (elements.btnRefreshAudit) {
            elements.btnRefreshAudit.addEventListener('click', () => {
                refreshAll();
            });
        }

        // Initialize Quick-Access Notifications
        initNotifications();

        // Reactive Event Listeners: Triggered from Practical 11/12/13 CRUD Operations
        window.addEventListener('studenthub:datachange', () => {
            console.log('[AdminDashboard] Mutation detected, refreshing stats & audit trail...');
            refreshAll();
        });

        // Hash Navigation support
        window.addEventListener('hashchange', () => {
            if (window.location.hash === '#audit') {
                loadAuditLogs(currentAuditFilter);
            }
        });
    }

    // Expose global interface
    window.AdminDashboard = {
        refresh: refreshAll,
        loadAuditLogs: loadAuditLogs,
        loadNotifications: loadNotifications
    };

    // Auto-bootstrap when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initListeners();
            refreshAll();
        });
    } else {
        initListeners();
        refreshAll();
    }
})();
