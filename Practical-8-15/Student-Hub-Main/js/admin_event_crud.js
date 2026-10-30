/**
 * Practical 12: Campus Event Moderation & Media Upload Frontend Controller
 * File: js/admin_event_crud.js
 * Description: Asynchronous CRUD interface for campus event management.
 *              Supports Live Search, Category/Status Filters, File Validation,
 *              Drag-and-Drop Poster Upload with Live Image Preview,
 *              Modal Add/Edit/View, Confirmed Deletion, and Toast Feedback.
 */

(function (window, document) {
    'use strict';

    const API_URL = '../php/event_crud.php';
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    // State management
    const state = {
        currentPage: 1,
        limit: 6,
        searchQuery: '',
        selectedCategory: 'all',
        selectedStatus: 'all',
        totalPages: 1,
        totalRecords: 0,
        pendingDeleteId: null,
        searchTimer: null,
        isSubmitting: false
    };

    // DOM Elements Cache
    let elements = {};

    function init() {
        cacheElements();
        bindEvents();
        loadEvents(1);
        updateHeroStats();
    }

    function cacheElements() {
        elements = {
            // Container & Grid
            eventsContainer: document.getElementById('events-cards-container'),
            eventsCountBadge: document.getElementById('events-count-badge'),

            // Filters & Search
            searchInput: document.getElementById('event-search-input'),
            categoryFilter: document.getElementById('event-category-filter'),
            statusFilter: document.getElementById('event-status-filter'),
            btnResetFilter: document.getElementById('event-reset-filter'),
            btnAddEvent: document.getElementById('btn-add-event'),

            // Pagination
            paginationBar: document.getElementById('event-pagination-bar'),
            paginationSummary: document.getElementById('event-pagination-summary'),
            btnPrevPage: document.getElementById('btn-event-prev-page'),
            btnNextPage: document.getElementById('btn-event-next-page'),
            pageIndicator: document.getElementById('event-page-indicator'),

            // Hero stat counter
            heroActiveEvents: document.getElementById('hero-active-events'),

            // Add/Edit Modal
            eventModal: document.getElementById('event-modal'),
            eventForm: document.getElementById('event-form'),
            eventModalTitle: document.getElementById('event-modal-title'),
            eventIdInput: document.getElementById('event-id-input'),
            eventTitle: document.getElementById('event-title'),
            eventCategory: document.getElementById('event-category'),
            eventBadge: document.getElementById('event-badge'),
            eventLocation: document.getElementById('event-location'),
            eventDate: document.getElementById('event-date'),
            eventCapacity: document.getElementById('event-capacity'),
            eventStatus: document.getElementById('event-status'),
            eventDescription: document.getElementById('event-description'),
            btnSaveEvent: document.getElementById('btn-save-event'),

            // Poster Upload & Preview
            posterInput: document.getElementById('event-poster-input'),
            posterDropzone: document.getElementById('event-poster-dropzone'),
            posterPreviewWrap: document.getElementById('poster-preview-wrap'),
            posterPreviewImg: document.getElementById('poster-preview-img'),
            posterFileInfo: document.getElementById('poster-file-info'),
            btnRemovePoster: document.getElementById('btn-remove-poster'),

            // Delete Modal
            deleteModal: document.getElementById('delete-event-modal'),
            deleteEventTitle: document.getElementById('delete-event-title'),
            deleteEventPoster: document.getElementById('delete-event-poster'),
            btnConfirmDelete: document.getElementById('btn-confirm-delete-event'),

            // View Modal
            viewModal: document.getElementById('view-event-modal'),
            viewContainer: document.getElementById('view-event-container'),

            // Practical 13: Live API Diagnostics
            btnTestApiPing: document.getElementById('btn-test-api-ping'),
            apiLatencyVal: document.getElementById('api-latency-val'),

            // Shared Toast Container
            toastContainer: document.getElementById('crud-toast-container')
        };
    }

    function bindEvents() {
        // Search Input with Debounce
        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', (e) => {
                clearTimeout(state.searchTimer);
                state.searchTimer = setTimeout(() => {
                    state.searchQuery = e.target.value.trim();
                    state.currentPage = 1;
                    loadEvents(1);
                }, 300);
            });
        }

        // Category Filter
        if (elements.categoryFilter) {
            elements.categoryFilter.addEventListener('change', (e) => {
                state.selectedCategory = e.target.value;
                state.currentPage = 1;
                loadEvents(1);
            });
        }

        // Status Filter
        if (elements.statusFilter) {
            elements.statusFilter.addEventListener('change', (e) => {
                state.selectedStatus = e.target.value;
                state.currentPage = 1;
                loadEvents(1);
            });
        }

        // Reset Filter
        if (elements.btnResetFilter) {
            elements.btnResetFilter.addEventListener('click', () => {
                if (elements.searchInput) elements.searchInput.value = '';
                if (elements.categoryFilter) elements.categoryFilter.value = 'all';
                if (elements.statusFilter) elements.statusFilter.value = 'all';
                state.searchQuery = '';
                state.selectedCategory = 'all';
                state.selectedStatus = 'all';
                state.currentPage = 1;
                loadEvents(1);
            });
        }

        // Add Event Button
        if (elements.btnAddEvent) {
            elements.btnAddEvent.addEventListener('click', openAddModal);
        }

        // Pagination Buttons
        if (elements.btnPrevPage) {
            elements.btnPrevPage.addEventListener('click', () => {
                if (state.currentPage > 1) {
                    loadEvents(state.currentPage - 1);
                }
            });
        }

        if (elements.btnNextPage) {
            elements.btnNextPage.addEventListener('click', () => {
                if (state.currentPage < state.totalPages) {
                    loadEvents(state.currentPage + 1);
                }
            });
        }

        // Poster Input Change & Drag-and-Drop
        setupPosterDropzone();

        // Form Submit
        if (elements.eventForm) {
            elements.eventForm.addEventListener('submit', handleFormSubmit);
        }

        // Delete Confirm Button
        if (elements.btnConfirmDelete) {
            elements.btnConfirmDelete.addEventListener('click', executeDelete);
        }

        // Practical 13: Live API Ping & Contract Verification Button
        if (elements.btnTestApiPing) {
            elements.btnTestApiPing.addEventListener('click', testApiPing);
        }

        // Modal Close Buttons (delegated)
        document.addEventListener('click', (e) => {
            const closeBtn = e.target.closest('[data-modal-close]');
            if (closeBtn) {
                const modalId = closeBtn.getAttribute('data-modal-close');
                closeModal(modalId);
            }

            // Click outside backdrop
            if (e.target.classList.contains('admin-modal-backdrop') && e.target.classList.contains('active')) {
                closeModal(e.target.id);
            }
        });

        // ESC key to close open modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.admin-modal-backdrop.active');
                if (activeModal) {
                    closeModal(activeModal.id);
                }
            }
        });
    }

    function setupPosterDropzone() {
        if (!elements.posterDropzone || !elements.posterInput) return;

        // Click to open file chooser
        elements.posterDropzone.addEventListener('click', (e) => {
            if (e.target !== elements.btnRemovePoster && !e.target.closest('#btn-remove-poster')) {
                elements.posterInput.click();
            }
        });

        // File selection
        elements.posterInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                processSelectedPoster(file);
            }
        });

        // Drag & drop events
        ['dragenter', 'dragover'].forEach(eventName => {
            elements.posterDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                elements.posterDropzone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            elements.posterDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                elements.posterDropzone.classList.remove('drag-over');
            });
        });

        elements.posterDropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                const file = dt.files[0];
                elements.posterInput.files = dt.files;
                processSelectedPoster(file);
            }
        });

        // Remove poster button
        if (elements.btnRemovePoster) {
            elements.btnRemovePoster.addEventListener('click', (e) => {
                e.stopPropagation();
                clearPosterSelection();
            });
        }
    }

    function processSelectedPoster(file) {
        // Validation: Extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_EXTS.includes(ext)) {
            showToast(`Unsupported file type (.${ext}). Allowed formats: JPG, PNG, WEBP, GIF.`, 'error');
            clearPosterSelection();
            return;
        }

        // Validation: Size
        if (file.size > MAX_FILE_SIZE) {
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            showToast(`File size (${sizeMb} MB) exceeds maximum allowed limit of 2MB.`, 'error');
            clearPosterSelection();
            return;
        }

        // Preview via FileReader
        const reader = new FileReader();
        reader.onload = function (e) {
            if (elements.posterPreviewImg) {
                elements.posterPreviewImg.src = e.target.result;
            }
            if (elements.posterFileInfo) {
                const sizeKb = Math.round(file.size / 1024);
                elements.posterFileInfo.textContent = `${file.name} (${sizeKb} KB) • Ready`;
            }
            if (elements.posterPreviewWrap) {
                elements.posterPreviewWrap.style.display = 'flex';
            }
            elements.posterDropzone.classList.add('has-preview');
        };
        reader.readAsDataURL(file);
    }

    function clearPosterSelection() {
        if (elements.posterInput) elements.posterInput.value = '';
        if (elements.posterPreviewImg) elements.posterPreviewImg.src = '';
        if (elements.posterFileInfo) elements.posterFileInfo.textContent = '';
        if (elements.posterPreviewWrap) elements.posterPreviewWrap.style.display = 'none';
        if (elements.posterDropzone) elements.posterDropzone.classList.remove('has-preview');
    }

    /**
     * Fetch & Render Events
     */
    async function loadEvents(page = 1) {
        state.currentPage = page;
        renderLoadingState();

        const params = new URLSearchParams({
            action: 'list',
            page: page,
            limit: state.limit,
            search: state.searchQuery,
            category: state.selectedCategory,
            status: state.selectedStatus
        });

        const reqStart = performance.now();
        try {
            const response = await fetch(`${API_URL}?${params.toString()}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const duration = Math.round(performance.now() - reqStart);
            if (elements.apiLatencyVal) {
                elements.apiLatencyVal.textContent = `${duration}ms`;
            }

            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load events.');
            }

            state.totalRecords = data.total;
            state.totalPages = data.total_pages || 1;

            populateCategories(data.categories);
            renderEventsGrid(data.events);
            renderPagination(data);
            updateHeroCounter(data.metrics);

        } catch (error) {
            console.error('Error loading events:', error);
            renderErrorState(error.message);
        }
    }

    function renderLoadingState() {
        if (!elements.eventsContainer) return;
        elements.eventsContainer.innerHTML = `
            <div class="crud-loading-state" style="grid-column: 1 / -1; padding: 40px; text-align: center;">
                <div class="crud-spinner"></div>
                <p style="margin-top: 12px; color: var(--text-secondary); font-size: 14px;">Loading campus events registry...</p>
            </div>
        `;
    }

    function renderErrorState(message) {
        if (!elements.eventsContainer) return;
        elements.eventsContainer.innerHTML = `
            <div class="crud-empty-state" style="grid-column: 1 / -1; padding: 40px; text-align: center;">
                <span class="material-symbols-outlined" style="font-size: 40px; color: #ef4444;">error_outline</span>
                <h4 style="margin-top: 10px; font-weight: 600;">Unable to load events</h4>
                <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">${escapeHtml(message)}</p>
                <button type="button" onclick="adminEventCrud.reload()" class="btn btn-outline btn-sm" style="margin-top: 14px;">
                    <span class="material-symbols-outlined">refresh</span> Retry
                </button>
            </div>
        `;
    }

    function renderEventsGrid(events) {
        if (!elements.eventsContainer) return;

        if (!events || events.length === 0) {
            elements.eventsContainer.innerHTML = `
                <div class="crud-empty-state" style="grid-column: 1 / -1; padding: 48px; text-align: center;">
                    <span class="material-symbols-outlined" style="font-size: 42px; color: var(--text-muted);">event_busy</span>
                    <h4 style="margin-top: 10px; font-size: 16px; font-weight: 600;">No Campus Events Found</h4>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">
                        ${state.searchQuery || state.selectedCategory !== 'all' || state.selectedStatus !== 'all' 
                            ? 'Try refining or resetting your search and category filters.' 
                            : 'Get started by creating your first accredited campus event.'}
                    </p>
                    <button type="button" onclick="adminEventCrud.openAddModal()" class="btn btn-primary btn-sm" style="margin-top: 16px;">
                        <span class="material-symbols-outlined">add_circle</span> Publish New Event
                    </button>
                </div>
            `;
            return;
        }

        const cardsHtml = events.map(event => {
            const posterSrc = formatImageSrc(event.image);
            const statusClass = getStatusBadgeClass(event.status);
            const statusLabel = capitalizeFirstLetter(event.status);

            return `
                <div class="event-crud-card" data-event-id="${event.event_id}">
                    <div class="event-card-poster-wrap">
                        <img src="${posterSrc}" alt="${escapeHtml(event.title)}" class="event-card-poster" onerror="this.src='../image/Full-Stack.jpg';">
                        <span class="event-card-status-badge ${statusClass}">${statusLabel}</span>
                        <span class="event-card-badge-tag">${escapeHtml(event.badge || 'Event')}</span>
                    </div>

                    <div class="event-card-body">
                        <div class="event-card-category">${escapeHtml(event.category)}</div>
                        <h3 class="event-card-title" title="${escapeHtml(event.title)}">${escapeHtml(event.title)}</h3>
                        
                        <p class="event-card-desc">${escapeHtml(truncateText(event.description || 'No description provided.', 90))}</p>

                        <div class="event-meta-list">
                            <div class="event-meta-item">
                                <span class="material-symbols-outlined">calendar_today</span>
                                <span>${escapeHtml(event.formatted_date || '')}</span>
                            </div>
                            <div class="event-meta-item">
                                <span class="material-symbols-outlined">schedule</span>
                                <span>${escapeHtml(event.formatted_time || '')}</span>
                            </div>
                            <div class="event-meta-item">
                                <span class="material-symbols-outlined">location_on</span>
                                <span title="${escapeHtml(event.location)}">${escapeHtml(truncateText(event.location, 26))}</span>
                            </div>
                            <div class="event-meta-item">
                                <span class="material-symbols-outlined">group</span>
                                <span>Cap: <strong>${event.max_capacity}</strong></span>
                            </div>
                        </div>

                        <div class="event-card-footer">
                            <div class="event-status-dropdown-wrap">
                                <select onchange="adminEventCrud.quickStatus(${event.event_id}, this.value)" class="event-quick-status" title="Change Event Status">
                                    <option value="upcoming" ${event.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                                    <option value="ongoing" ${event.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                                    <option value="completed" ${event.status === 'completed' ? 'selected' : ''}>Completed</option>
                                    <option value="cancelled" ${event.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>

                            <div class="event-action-buttons">
                                <button type="button" onclick="adminEventCrud.viewDetails(${event.event_id})" class="action-icon-btn btn-icon-view" title="View Event Details" aria-label="View Event">
                                    <span class="material-symbols-outlined">visibility</span>
                                </button>
                                <button type="button" onclick="adminEventCrud.openEditModal(${event.event_id})" class="action-icon-btn btn-icon-edit" title="Edit Event &amp; Poster" aria-label="Edit Event">
                                    <span class="material-symbols-outlined">edit</span>
                                </button>
                                <button type="button" onclick="adminEventCrud.openDeleteModal(${event.event_id}, '${escapeJsString(event.title)}', '${escapeJsString(event.image)}')" class="action-icon-btn danger btn-icon-danger" title="Delete Event" aria-label="Delete Event">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        elements.eventsContainer.innerHTML = cardsHtml;
    }

    function renderPagination(data) {
        if (!elements.paginationBar) return;

        if (data.total === 0) {
            elements.paginationBar.style.display = 'none';
            return;
        }

        elements.paginationBar.style.display = 'flex';

        const start = (data.page - 1) * data.limit + 1;
        const end = Math.min(data.page * data.limit, data.total);

        if (elements.paginationSummary) {
            elements.paginationSummary.textContent = `Showing ${start}–${end} of ${data.total} accredited events`;
        }

        if (elements.pageIndicator) {
            elements.pageIndicator.textContent = `Page ${data.page} of ${data.total_pages}`;
        }

        if (elements.btnPrevPage) {
            elements.btnPrevPage.disabled = (data.page <= 1);
        }

        if (elements.btnNextPage) {
            elements.btnNextPage.disabled = (data.page >= data.total_pages);
        }
    }

    function populateCategories(categories) {
        if (!elements.categoryFilter || !categories) return;
        const currentValue = elements.categoryFilter.value;

        // Preserve first option (All Categories)
        const allOption = elements.categoryFilter.options[0];
        elements.categoryFilter.innerHTML = '';
        elements.categoryFilter.appendChild(allOption);

        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat;
            opt.textContent = cat;
            if (cat === currentValue) opt.selected = true;
            elements.categoryFilter.appendChild(opt);
        });
    }

    function updateHeroCounter(metrics) {
        if (elements.heroActiveEvents && metrics && typeof metrics.total_events !== 'undefined') {
            elements.heroActiveEvents.textContent = metrics.total_events;
        }
        if (elements.eventsCountBadge && metrics) {
            elements.eventsCountBadge.textContent = `${metrics.total_events} Total`;
        }
    }

    async function updateHeroStats() {
        try {
            const res = await fetch(`${API_URL}?action=metrics`);
            if (res.ok) {
                const data = await res.json();
                if (data.success && data.metrics) {
                    updateHeroCounter(data.metrics);
                }
            }
        } catch (e) {
            // Non-blocking
        }
    }

    /**
     * Modal Operations
     */
    function openAddModal() {
        if (!elements.eventForm) return;
        elements.eventForm.reset();
        elements.eventIdInput.value = '';
        if (elements.eventModalTitle) {
            elements.eventModalTitle.textContent = 'Create New Campus Event';
        }
        if (elements.btnSaveEvent) {
            elements.btnSaveEvent.innerHTML = '<span class="material-symbols-outlined">add_circle</span> <span>Publish Event</span>';
        }

        // Set default date to tomorrow at 10:00 AM
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(10, 0, 0, 0);
        const isoString = tomorrow.toISOString().slice(0, 16);
        if (elements.eventDate) elements.eventDate.value = isoString;

        clearPosterSelection();
        openModal('event-modal');
    }

    async function openEditModal(eventId) {
        try {
            const res = await fetch(`${API_URL}?action=get&event_id=${eventId}`);
            if (!res.ok) throw new Error('Failed to fetch event data.');
            const data = await res.json();

            if (!data.success || !data.event) {
                throw new Error(data.message || 'Event not found.');
            }

            const ev = data.event;
            elements.eventIdInput.value = ev.event_id;
            elements.eventTitle.value = ev.title || '';
            elements.eventCategory.value = ev.category || '';
            elements.eventBadge.value = ev.badge || 'Event';
            elements.eventLocation.value = ev.location || '';
            elements.eventDate.value = ev.event_date_input || '';
            elements.eventCapacity.value = ev.max_capacity || 100;
            elements.eventStatus.value = ev.status || 'upcoming';
            elements.eventDescription.value = ev.description || '';

            if (elements.eventModalTitle) {
                elements.eventModalTitle.textContent = `Edit Event #${ev.event_id} — ${ev.title}`;
            }
            if (elements.btnSaveEvent) {
                elements.btnSaveEvent.innerHTML = '<span class="material-symbols-outlined">save</span> <span>Update Event</span>';
            }

            // Show existing poster in preview
            clearPosterSelection();
            if (ev.image) {
                elements.posterPreviewImg.src = formatImageSrc(ev.image);
                elements.posterFileInfo.textContent = `Current poster: ${ev.image} (upload new to replace)`;
                elements.posterPreviewWrap.style.display = 'flex';
                elements.posterDropzone.classList.add('has-preview');
            }

            openModal('event-modal');

        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function viewDetails(eventId) {
        try {
            const res = await fetch(`${API_URL}?action=get&event_id=${eventId}`);
            if (!res.ok) throw new Error('Failed to fetch event details.');
            const data = await res.json();

            if (!data.success || !data.event) {
                throw new Error(data.message || 'Event not found.');
            }

            const ev = data.event;
            const posterSrc = formatImageSrc(ev.image);
            const statusClass = getStatusBadgeClass(ev.status);

            if (elements.viewContainer) {
                elements.viewContainer.innerHTML = `
                    <div class="view-event-detail-card">
                        <div class="view-event-poster-header">
                            <img src="${posterSrc}" alt="${escapeHtml(ev.title)}" class="view-event-poster-large" onerror="this.src='../image/Full-Stack.jpg';">
                            <span class="event-card-status-badge ${statusClass}">${capitalizeFirstLetter(ev.status)}</span>
                        </div>
                        <div class="view-event-body">
                            <div class="view-event-top-meta">
                                <span class="event-card-category">${escapeHtml(ev.category)}</span>
                                <span class="event-card-badge-tag">${escapeHtml(ev.badge || 'Event')}</span>
                            </div>
                            <h2 class="view-event-title">${escapeHtml(ev.title)}</h2>
                            <p class="view-event-description">${escapeHtml(ev.description || 'No detailed description available.')}</p>

                            <div class="view-event-grid-info">
                                <div class="view-event-info-cell">
                                    <span class="material-symbols-outlined">event</span>
                                    <div>
                                        <div class="info-label">Date & Time</div>
                                        <div class="info-val">${escapeHtml(ev.formatted_datetime || '')}</div>
                                    </div>
                                </div>
                                <div class="view-event-info-cell">
                                    <span class="material-symbols-outlined">location_on</span>
                                    <div>
                                        <div class="info-label">Venue Location</div>
                                        <div class="info-val">${escapeHtml(ev.location)}</div>
                                    </div>
                                </div>
                                <div class="view-event-info-cell">
                                    <span class="material-symbols-outlined">groups</span>
                                    <div>
                                        <div class="info-label">Max Seating Capacity</div>
                                        <div class="info-val">${ev.max_capacity} Scholars</div>
                                    </div>
                                </div>
                                <div class="view-event-info-cell">
                                    <span class="material-symbols-outlined">verified</span>
                                    <div>
                                        <div class="info-label">Registry Identifier</div>
                                        <div class="info-val">EVT-${String(ev.event_id).padStart(4, '0')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            openModal('view-event-modal');

        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    function openDeleteModal(eventId, title, image) {
        state.pendingDeleteId = eventId;
        if (elements.deleteEventTitle) {
            elements.deleteEventTitle.textContent = title;
        }
        if (elements.deleteEventPoster) {
            elements.deleteEventPoster.src = formatImageSrc(image);
        }
        openModal('delete-event-modal');
    }

    async function executeDelete() {
        if (!state.pendingDeleteId) return;

        const originalBtnHtml = elements.btnConfirmDelete.innerHTML;
        elements.btnConfirmDelete.disabled = true;
        elements.btnConfirmDelete.innerHTML = '<span class="crud-btn-spinner"></span> <span>Deleting...</span>';

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('event_id', state.pendingDeleteId);

            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete event.');
            }

            showToast(data.message || 'Event removed successfully.', 'success');
            closeModal('delete-event-modal');
            state.pendingDeleteId = null;
            loadEvents(state.currentPage);
            updateHeroStats();

        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            elements.btnConfirmDelete.disabled = false;
            elements.btnConfirmDelete.innerHTML = originalBtnHtml;
        }
    }

    async function quickStatus(eventId, newStatus) {
        try {
            const formData = new FormData();
            formData.append('action', 'status_toggle');
            formData.append('event_id', eventId);
            formData.append('status', newStatus);

            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to update status.');
            }

            showToast(data.message, 'info');
            loadEvents(state.currentPage);
            updateHeroStats();

        } catch (err) {
            showToast(err.message, 'error');
            loadEvents(state.currentPage);
        }
    }

    /**
     * Handle Form Submission (Add or Update)
     */
    async function handleFormSubmit(e) {
        e.preventDefault();

        if (state.isSubmitting) return;

        const eventId = elements.eventIdInput.value.trim();
        const isEditing = (eventId !== '');

        // Validation
        const title = elements.eventTitle.value.trim();
        const category = elements.eventCategory.value.trim();
        const location = elements.eventLocation.value.trim();
        const date = elements.eventDate.value.trim();

        if (!title || !category || !location || !date) {
            showToast('Please fill all mandatory event fields.', 'error');
            return;
        }

        const formData = new FormData(elements.eventForm);
        formData.append('action', isEditing ? 'update' : 'create');

        state.isSubmitting = true;
        const originalBtnHtml = elements.btnSaveEvent.innerHTML;
        elements.btnSaveEvent.disabled = true;
        elements.btnSaveEvent.innerHTML = '<span class="crud-btn-spinner"></span> <span>Saving Event...</span>';

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to save event.');
            }

            showToast(data.message || 'Event saved successfully.', 'success');
            closeModal('event-modal');
            clearPosterSelection();
            loadEvents(isEditing ? state.currentPage : 1);
            updateHeroStats();

        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            state.isSubmitting = false;
            elements.btnSaveEvent.disabled = false;
            elements.btnSaveEvent.innerHTML = originalBtnHtml;
        }
    }

    /**
     * Helpers
     */
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.classList.add('modal-open');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            if (!document.querySelector('.admin-modal-backdrop.active')) {
                document.body.classList.remove('modal-open');
            }
        }
    }

    function formatImageSrc(imgPath) {
        if (!imgPath) return '../image/Full-Stack.jpg';
        if (imgPath.startsWith('http://') || imgPath.startsWith('https://')) return imgPath;
        if (imgPath.startsWith('../')) return imgPath;
        return `../${imgPath}`;
    }

    function getStatusBadgeClass(status) {
        switch (status) {
            case 'upcoming': return 'status-badge-upcoming';
            case 'ongoing': return 'status-badge-ongoing';
            case 'completed': return 'status-badge-completed';
            case 'cancelled': return 'status-badge-cancelled';
            default: return 'status-badge-upcoming';
        }
    }

    function capitalizeFirstLetter(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function truncateText(str, maxLen) {
        if (!str || str.length <= maxLen) return str;
        return str.substring(0, maxLen).trim() + '...';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeJsString(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function showToast(message, type = 'info') {
        const container = elements.toastContainer || document.getElementById('crud-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `crud-toast toast-${type}`;

        let icon = 'info';
        if (type === 'success') icon = 'check_circle';
        if (type === 'error') icon = 'error';

        toast.innerHTML = `
            <span class="material-symbols-outlined">${icon}</span>
            <span>${escapeHtml(message)}</span>
        `;

        container.appendChild(toast);

        if (type === 'success') {
            window.dispatchEvent(new CustomEvent('studenthub:datachange'));
        }

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px) scale(0.95)';
            toast.style.transition = 'all 0.25s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 250);
        }, 4000);
    }

    /**
     * Practical 13: Test API Ping & Live Contract Verification
     */
    async function testApiPing() {
        if (!elements.btnTestApiPing) return;
        const originalHtml = elements.btnTestApiPing.innerHTML;
        elements.btnTestApiPing.disabled = true;
        elements.btnTestApiPing.innerHTML = '<span class="crud-btn-spinner"></span> <span>Pinging API...</span>';

        const startTime = performance.now();
        try {
            const res = await fetch(`${API_URL}?action=ping`, {
                headers: { 'Accept': 'application/json' }
            });
            const duration = Math.round(performance.now() - startTime);

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (elements.apiLatencyVal) {
                elements.apiLatencyVal.textContent = `${duration}ms`;
            }

            showToast(`Practical 13 API Verified: Status ${data.status_code || 200} OK (${duration}ms). Zero-reload Fetch protocol active.`, 'success');

        } catch (err) {
            showToast(`API Ping Failed: ${err.message}`, 'error');
        } finally {
            elements.btnTestApiPing.disabled = false;
            elements.btnTestApiPing.innerHTML = originalHtml;
        }
    }

    // Expose public API
    window.adminEventCrud = {
        openAddModal,
        openEditModal,
        openDeleteModal,
        viewDetails,
        quickStatus,
        testApiPing,
        reload: () => loadEvents(state.currentPage),
        updateHeroStats
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
