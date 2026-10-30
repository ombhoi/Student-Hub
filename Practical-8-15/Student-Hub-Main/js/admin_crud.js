/**
 * Practical 11: Student Management CRUD Frontend Controller
 * File: js/admin_crud.js
 * Description: Real-time asynchronous CRUD interface for StudentHub Admin Console.
 *              Supports Live Search, Department Filters, Modal Add/Edit,
 *              Confirmed Deletion, CSV Export, and Toast Feedback Notifications.
 */

(function (window, document) {
    'use strict';

    const API_URL = '../php/student_crud.php';

    // State management
    const state = {
        currentPage: 1,
        limit: 8,
        searchQuery: '',
        selectedDepartment: 'all',
        totalPages: 1,
        totalRecords: 0,
        pendingDeleteId: null,
        searchTimer: null
    };

    // DOM Elements Cache
    let elements = {};

    function init() {
        cacheElements();
        bindEvents();
        loadStudents(1);
    }

    function cacheElements() {
        elements = {
            tableBody: document.getElementById('students-table-body'),
            searchInput: document.getElementById('student-search-input'),
            deptFilter: document.getElementById('student-dept-filter'),
            btnResetFilter: document.getElementById('student-reset-filter'),
            btnAddStudent: document.getElementById('btn-add-student'),
            btnExportCsv: document.getElementById('btn-export-csv'),
            
            // Pagination
            paginationBar: document.getElementById('crud-pagination-bar'),
            paginationSummary: document.getElementById('pagination-summary'),
            btnPrevPage: document.getElementById('btn-prev-page'),
            btnNextPage: document.getElementById('btn-next-page'),
            pageIndicator: document.getElementById('page-indicator'),

            // Hero stat counter
            heroTotalStudents: document.getElementById('hero-total-students'),

            // Add/Edit Modal
            studentModal: document.getElementById('student-modal'),
            studentForm: document.getElementById('student-form'),
            studentModalTitle: document.getElementById('student-modal-title'),
            studentIdInput: document.getElementById('student-id-input'),
            studentFirstName: document.getElementById('student-first-name'),
            studentLastName: document.getElementById('student-last-name'),
            studentEmail: document.getElementById('student-email'),
            studentRoll: document.getElementById('student-roll-number'),
            studentDeptSelect: document.getElementById('student-dept-select'),
            studentSemesterSelect: document.getElementById('student-semester-select'),
            studentPhone: document.getElementById('student-phone'),
            studentPassword: document.getElementById('student-password'),
            studentPasswordLabel: document.getElementById('student-password-label'),
            studentPasswordBadge: document.getElementById('student-password-badge'),
            studentPasswordHint: document.getElementById('student-password-hint'),
            toggleStudentPw: document.getElementById('toggle-student-pw'),
            studentBio: document.getElementById('student-bio'),
            btnSaveStudent: document.getElementById('btn-save-student'),

            // Delete Confirmation Modal
            deleteModal: document.getElementById('delete-confirm-modal'),
            deleteStudentName: document.getElementById('delete-student-name'),
            deleteStudentCode: document.getElementById('delete-student-code'),
            btnConfirmDelete: document.getElementById('btn-confirm-delete'),

            // View Details Modal
            viewModal: document.getElementById('view-student-modal'),
            viewContainer: document.getElementById('view-student-container'),

            // Toast Container
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
                    loadStudents(1);
                }, 300);
            });
        }

        // Department Filter
        if (elements.deptFilter) {
            elements.deptFilter.addEventListener('change', (e) => {
                state.selectedDepartment = e.target.value;
                state.currentPage = 1;
                loadStudents(1);
            });
        }

        // Reset Filter
        if (elements.btnResetFilter) {
            elements.btnResetFilter.addEventListener('click', () => {
                if (elements.searchInput) elements.searchInput.value = '';
                if (elements.deptFilter) elements.deptFilter.value = 'all';
                state.searchQuery = '';
                state.selectedDepartment = 'all';
                state.currentPage = 1;
                loadStudents(1);
            });
        }

        // Add Student Button
        if (elements.btnAddStudent) {
            elements.btnAddStudent.addEventListener('click', openAddModal);
        }

        // Student Form Submit
        if (elements.studentForm) {
            elements.studentForm.addEventListener('submit', handleFormSubmit);
        }

        // Delete Confirm Button
        if (elements.btnConfirmDelete) {
            elements.btnConfirmDelete.addEventListener('click', executeDelete);
        }

        // Export CSV Button
        if (elements.btnExportCsv) {
            elements.btnExportCsv.addEventListener('click', exportCsv);
        }

        // Toggle Password Visibility
        if (elements.toggleStudentPw && elements.studentPassword) {
            elements.toggleStudentPw.addEventListener('click', () => {
                const isPw = elements.studentPassword.getAttribute('type') === 'password';
                elements.studentPassword.setAttribute('type', isPw ? 'text' : 'password');
                const icon = elements.toggleStudentPw.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.textContent = isPw ? 'visibility_off' : 'visibility';
                }
            });
        }

        // Phone Input: Restrict strictly to 10 digits numeric only (matching register.html)
        if (elements.studentPhone) {
            elements.studentPhone.addEventListener('input', (e) => {
                let val = e.target.value.replace(/\D/g, '');
                if (val.length > 10) {
                    val = val.slice(0, 10);
                }
                e.target.value = val;
            });
        }

        // Pagination Controls
        if (elements.btnPrevPage) {
            elements.btnPrevPage.addEventListener('click', () => {
                if (state.currentPage > 1) {
                    loadStudents(state.currentPage - 1);
                }
            });
        }

        if (elements.btnNextPage) {
            elements.btnNextPage.addEventListener('click', () => {
                if (state.currentPage < state.totalPages) {
                    loadStudents(state.currentPage + 1);
                }
            });
        }

        // Modal Close Buttons (delegate for all [data-modal-close])
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetModalId = e.currentTarget.getAttribute('data-modal-close');
                closeModal(document.getElementById(targetModalId));
            });
        });

        // Close on backdrop click
        document.querySelectorAll('.admin-modal-backdrop').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal(modal);
                }
            });
        });
    }

    // =========================================================================
    // 1. LOAD & RENDER STUDENTS (READ)
    // =========================================================================
    async function loadStudents(page = 1) {
        state.currentPage = page;

        renderLoading();

        try {
            const params = new URLSearchParams({
                action: 'list',
                page: state.currentPage,
                limit: state.limit,
                search: state.searchQuery,
                department: state.selectedDepartment
            });

            const res = await fetch(`${API_URL}?${params.toString()}`);
            const data = await res.json();

            if (!res.ok || !data.success) {
                renderError(data.message || 'Failed to retrieve student records.');
                return;
            }

            state.totalPages = data.total_pages || 1;
            state.totalRecords = data.total || 0;

            // Sync hero stats counter
            if (elements.heroTotalStudents && data.metrics) {
                elements.heroTotalStudents.textContent = Number(data.metrics.total_students).toLocaleString();
            }

            // Sync department filter options if newly loaded
            syncDepartmentOptions(data.departments || []);

            // Render table rows
            renderTableRows(data.students || []);

            // Render pagination
            renderPagination(data);

        } catch (err) {
            console.error('Error fetching student records:', err);
            renderError('Network error connecting to student registry API.');
        }
    }

    function renderLoading() {
        if (!elements.tableBody) return;
        elements.tableBody.innerHTML = `
            <div class="table-loading-state">
                <div class="table-spinner"></div>
                <span>Querying MySQL student registry...</span>
            </div>
        `;
    }

    function renderError(msg) {
        if (!elements.tableBody) return;
        elements.tableBody.innerHTML = `
            <div class="table-empty-state">
                <span class="material-symbols-outlined" style="color: #ef4444;">error</span>
                <span style="color: #ef4444; font-weight: 600;">${escapeHtml(msg)}</span>
                <button class="btn btn-outline btn-sm" onclick="adminCrud.reload()">Retry Query</button>
            </div>
        `;
    }

    function renderTableRows(students) {
        if (!elements.tableBody) return;

        if (students.length === 0) {
            elements.tableBody.innerHTML = `
                <div class="table-empty-state">
                    <span class="material-symbols-outlined">person_search</span>
                    <h4>No Students Found</h4>
                    <p>No student registry records match your current search query or department filter.</p>
                </div>
            `;
            return;
        }

        let html = '';
        students.forEach(s => {
            const fullName = `${s.first_name} ${s.last_name}`;
            const studentCode = s.student_code || `STU-${s.student_id}`;
            const rollNum = s.roll_number || 'N/A';
            const dept = s.department || 'General Science';
            const sem = s.semester ? `Sem ${s.semester}` : 'Sem 1';
            const email = s.email || 'N/A';

            html += `
                <div class="request-row" data-id="${s.student_id}">
                    <div class="col-id-date">
                        <span class="req-title">${escapeHtml(fullName)}</span>
                        <span class="req-id">${escapeHtml(studentCode)} &bull; ${escapeHtml(sem)}</span>
                    </div>
                    <div class="col-title-user">
                        <span class="req-email">${escapeHtml(email)}</span>
                        <span class="req-dept">${escapeHtml(dept)} &bull; Roll: ${escapeHtml(rollNum)}</span>
                    </div>
                    <div class="col-category">
                        <span class="category-pill pill-student">${escapeHtml(sem)}</span>
                    </div>
                    <div class="col-status">
                        <span class="status-indicator active"><span class="dot"></span> Enrolled</span>
                    </div>
                    <div class="col-actions">
                        <button class="action-icon-btn" title="View Student Details" onclick="adminCrud.viewStudent(${s.student_id})">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                        <button class="action-icon-btn" title="Edit Student Record" onclick="adminCrud.openEditModal(${s.student_id})">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                        <button class="action-icon-btn danger" title="Delete Student" onclick="adminCrud.confirmDelete(${s.student_id}, '${escapeAttr(fullName)}', '${escapeAttr(studentCode)}')">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                </div>
            `;
        });

        elements.tableBody.innerHTML = html;
    }

    function renderPagination(data) {
        if (!elements.paginationBar) return;

        if (data.total === 0) {
            elements.paginationBar.style.display = 'none';
            return;
        }

        elements.paginationBar.style.display = 'flex';

        const start = ((data.page - 1) * data.limit) + 1;
        const end = Math.min(data.page * data.limit, data.total);

        if (elements.paginationSummary) {
            elements.paginationSummary.textContent = `Showing ${start}–${end} of ${data.total} registered students`;
        }

        if (elements.pageIndicator) {
            elements.pageIndicator.textContent = `Page ${data.page} of ${data.total_pages}`;
        }

        if (elements.btnPrevPage) {
            elements.btnPrevPage.disabled = data.page <= 1;
        }

        if (elements.btnNextPage) {
            elements.btnNextPage.disabled = data.page >= data.total_pages;
        }
    }

    function syncDepartmentOptions(departments) {
        if (!elements.deptFilter || !Array.isArray(departments)) return;

        const currentVal = elements.deptFilter.value;
        const existingValues = Array.from(elements.deptFilter.options).map(opt => opt.value);

        departments.forEach(d => {
            if (d && !existingValues.includes(d)) {
                const opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                elements.deptFilter.appendChild(opt);
                existingValues.push(d);
            }
        });

        if (elements.studentDeptSelect) {
            const modalExistingValues = Array.from(elements.studentDeptSelect.options).map(opt => opt.value);
            departments.forEach(d => {
                if (d && !modalExistingValues.includes(d)) {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    elements.studentDeptSelect.appendChild(opt);
                    modalExistingValues.push(d);
                }
            });
        }

        elements.deptFilter.value = currentVal || 'all';
    }

    // =========================================================================
    // 2. ADD & EDIT MODAL MANAGEMENT (CREATE & UPDATE)
    // =========================================================================
    function openAddModal() {
        if (!elements.studentModal || !elements.studentForm) return;

        elements.studentForm.reset();
        elements.studentIdInput.value = '';
        if (elements.studentPassword) {
            elements.studentPassword.value = '';
            elements.studentPassword.setAttribute('type', 'password');
            elements.studentPassword.placeholder = 'e.g. Password@123 (min 8 chars)';
        }
        if (elements.toggleStudentPw) {
            const icon = elements.toggleStudentPw.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = 'visibility';
        }
        if (elements.studentPasswordLabel) {
            elements.studentPasswordLabel.textContent = 'Student Account Password *';
        }
        if (elements.studentPasswordBadge) {
            elements.studentPasswordBadge.textContent = 'Admin sets password to give to student';
        }
        if (elements.studentPasswordHint) {
            elements.studentPasswordHint.innerHTML = 'Enter the password you will provide to the student (or leave blank to assign default <code>password123</code>).';
        }
        elements.studentModalTitle.textContent = 'Add New Student Record';
        elements.btnSaveStudent.innerHTML = `
            <span class="material-symbols-outlined" style="font-size: 1.1rem; margin-right: 0.35rem;">person_add</span>
            Save Student
        `;

        openModal(elements.studentModal);
    }

    async function openEditModal(studentId) {
        if (!elements.studentModal || !elements.studentForm) return;

        try {
            const res = await fetch(`${API_URL}?action=get&student_id=${studentId}`);
            const data = await res.json();

            if (!res.ok || !data.success || !data.student) {
                showToast(data.message || 'Failed to load student details.', 'error');
                return;
            }

            const s = data.student;
            elements.studentIdInput.value = s.student_id;
            elements.studentFirstName.value = s.first_name || '';
            elements.studentLastName.value = s.last_name || '';
            elements.studentEmail.value = s.email || '';
            elements.studentRoll.value = s.roll_number || '';
            elements.studentDeptSelect.value = s.department || 'Computer Engineering';
            elements.studentSemesterSelect.value = s.semester || 1;
            const rawPhone = (s.phone || '').replace(/\D/g, '');
            elements.studentPhone.value = rawPhone.length > 10 ? rawPhone.slice(-10) : rawPhone;
            elements.studentBio.value = s.bio || '';
            if (elements.studentPassword) {
                elements.studentPassword.value = '';
                elements.studentPassword.setAttribute('type', 'password');
                elements.studentPassword.placeholder = 'Leave blank to preserve current password';
            }
            if (elements.toggleStudentPw) {
                const icon = elements.toggleStudentPw.querySelector('.material-symbols-outlined');
                if (icon) icon.textContent = 'visibility';
            }
            if (elements.studentPasswordLabel) {
                elements.studentPasswordLabel.textContent = 'Reset Password (Optional)';
            }
            if (elements.studentPasswordBadge) {
                elements.studentPasswordBadge.textContent = 'Leave empty to keep existing password';
            }
            if (elements.studentPasswordHint) {
                elements.studentPasswordHint.textContent = 'Only enter a new password if you wish to reset the student account credential.';
            }

            elements.studentModalTitle.textContent = `Edit Student: ${s.student_code}`;
            elements.btnSaveStudent.innerHTML = `
                <span class="material-symbols-outlined" style="font-size: 1.1rem; margin-right: 0.35rem;">save</span>
                Update Student
            `;

            openModal(elements.studentModal);

        } catch (err) {
            console.error('Error opening edit modal:', err);
            showToast('Network error loading student details.', 'error');
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();

        const studentId = elements.studentIdInput.value.trim();
        const isUpdate = studentId !== '';

        // Validate phone number: must be 10 digits starting with 1-9 if provided
        const phone = elements.studentPhone ? elements.studentPhone.value.trim() : '';
        if (phone && (!/^[1-9]\d{9}$/.test(phone) || phone.length !== 10)) {
            showToast('Phone number must be a valid 10-digit number starting with 1-9.', 'error');
            elements.studentPhone.focus();
            return;
        }

        const formData = new FormData(elements.studentForm);
        formData.append('action', isUpdate ? 'update' : 'create');

        // UI state: loading
        elements.btnSaveStudent.disabled = true;
        const originalBtnText = elements.btnSaveStudent.innerHTML;
        elements.btnSaveStudent.innerHTML = `
            <span class="table-spinner" style="width: 16px; height: 16px; border-width: 2px;"></span>
            Saving...
        `;

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                showToast(data.message || 'Failed to save student record.', 'error');
                return;
            }

            showToast(data.message, 'success');
            closeModal(elements.studentModal);
            loadStudents(state.currentPage);

        } catch (err) {
            console.error('Form submit error:', err);
            showToast('Network error while processing request.', 'error');
        } finally {
            elements.btnSaveStudent.disabled = false;
            elements.btnSaveStudent.innerHTML = originalBtnText;
        }
    }

    // =========================================================================
    // 3. DELETE CONFIRMATION & EXECUTION (DELETE)
    // =========================================================================
    function confirmDelete(studentId, studentName, studentCode) {
        state.pendingDeleteId = studentId;

        if (elements.deleteStudentName) {
            elements.deleteStudentName.textContent = studentName;
        }
        if (elements.deleteStudentCode) {
            elements.deleteStudentCode.textContent = `#${studentCode}`;
        }

        openModal(elements.deleteModal);
    }

    async function executeDelete() {
        if (!state.pendingDeleteId) return;

        elements.btnConfirmDelete.disabled = true;
        const originalText = elements.btnConfirmDelete.innerHTML;
        elements.btnConfirmDelete.innerHTML = `
            <span class="table-spinner" style="width: 16px; height: 16px; border-width: 2px;"></span>
            Deleting...
        `;

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('student_id', state.pendingDeleteId);

            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                showToast(data.message || 'Failed to delete student record.', 'error');
                return;
            }

            showToast(data.message, 'success');
            closeModal(elements.deleteModal);
            state.pendingDeleteId = null;

            // Reload table (if last item on page, go back 1 page)
            loadStudents(state.currentPage);

        } catch (err) {
            console.error('Delete error:', err);
            showToast('Network error deleting student record.', 'error');
        } finally {
            elements.btnConfirmDelete.disabled = false;
            elements.btnConfirmDelete.innerHTML = originalText;
        }
    }

    // =========================================================================
    // 4. VIEW STUDENT DETAILS MODAL
    // =========================================================================
    async function viewStudent(studentId) {
        if (!elements.viewModal || !elements.viewContainer) return;

        elements.viewContainer.innerHTML = `
            <div class="table-loading-state" style="padding: 30px 0;">
                <div class="table-spinner"></div>
                <span>Fetching student record details...</span>
            </div>
        `;

        openModal(elements.viewModal);

        try {
            const res = await fetch(`${API_URL}?action=get&student_id=${studentId}`);
            const data = await res.json();

            if (!res.ok || !data.success || !data.student) {
                elements.viewContainer.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">${escapeHtml(data.message || 'Record not found.')}</div>`;
                return;
            }

            const s = data.student;
            elements.viewContainer.innerHTML = `
                <div class="student-preview-card" style="margin-bottom: 16px; padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                        <div style="width: 52px; height: 52px; border-radius: 14px; background-color: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-size: 22px; font-weight: 700;">
                            ${escapeHtml(s.first_name[0] || 'S')}${escapeHtml(s.last_name[0] || 'T')}
                        </div>
                        <div>
                            <h4 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">${escapeHtml(s.first_name)} ${escapeHtml(s.last_name)}</h4>
                            <span style="font-size: 12px; font-weight: 600; color: var(--primary); background: var(--primary-light); padding: 2px 8px; border-radius: 9999px;">${escapeHtml(s.student_code)}</span>
                        </div>
                    </div>

                    <div class="student-preview-row">
                        <span class="label">Roll Number</span>
                        <span class="val">${escapeHtml(s.roll_number || 'N/A')}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">University Email</span>
                        <span class="val">${escapeHtml(s.email)}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">Academic Department</span>
                        <span class="val">${escapeHtml(s.department)}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">Current Semester</span>
                        <span class="val">Semester ${s.semester}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">Contact Phone</span>
                        <span class="val">${escapeHtml(s.phone || 'N/A')}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">Registered Timestamp</span>
                        <span class="val">${escapeHtml(s.created_at || 'N/A')}</span>
                    </div>
                    <div class="student-preview-row">
                        <span class="label">Last Activity</span>
                        <span class="val">${escapeHtml(s.last_login || 'Never')}</span>
                    </div>

                    ${s.bio ? `
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--card-border);">
                            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">Bio & Academic Notes:</span>
                            <p style="font-size: 13px; color: var(--text-primary); margin-top: 4px; line-height: 1.5;">${escapeHtml(s.bio)}</p>
                        </div>
                    ` : ''}
                </div>
            `;

        } catch (err) {
            console.error('Error loading view modal:', err);
            elements.viewContainer.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">Network error.</div>`;
        }
    }

    // =========================================================================
    // 5. CSV EXPORT (INTERMEDIATE EXTENSION)
    // =========================================================================
    function exportCsv() {
        const params = new URLSearchParams({
            action: 'export_csv',
            search: state.searchQuery,
            department: state.selectedDepartment
        });
        window.location.href = `${API_URL}?${params.toString()}`;
        showToast('Exporting student registry to CSV format...', 'info');
    }

    // =========================================================================
    // MODAL & TOAST HELPERS
    // =========================================================================
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function showToast(message, type = 'success') {
        if (!elements.toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `crud-toast toast-${type}`;
        
        let icon = 'check_circle';
        if (type === 'error') icon = 'error';
        if (type === 'info') icon = 'info';

        toast.innerHTML = `
            <span class="material-symbols-outlined" style="font-size: 20px;">${icon}</span>
            <span>${escapeHtml(message)}</span>
        `;

        elements.toastContainer.appendChild(toast);

        if (type === 'success') {
            window.dispatchEvent(new CustomEvent('studenthub:datachange'));
        }

        setTimeout(() => {
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px) scale(0.95)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // Expose public methods
    window.adminCrud = {
        init,
        reload: () => loadStudents(state.currentPage),
        openAddModal,
        openEditModal,
        viewStudent,
        confirmDelete,
        exportCsv
    };

    // Auto-init on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
