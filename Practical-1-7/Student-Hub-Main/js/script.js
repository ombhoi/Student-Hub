document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeIcon = themeToggleBtn ? themeToggleBtn.querySelector('.theme-icon') : null;

    // Apply saved theme on page load (defaults to light if nothing is saved)
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
        document.body.classList.add('dark-theme');
        if (themeIcon) themeIcon.textContent = 'light_mode';
    }

    // Toggle theme on button click
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.body.getAttribute('data-theme') === 'dark';

            if (isDark) {
                document.body.removeAttribute('data-theme');
                document.body.classList.remove('dark-theme');
                localStorage.setItem('theme', 'light');
                if (themeIcon) themeIcon.textContent = 'dark_mode';
            } else {
                document.body.setAttribute('data-theme', 'dark');
                document.body.classList.add('dark-theme');
                localStorage.setItem('theme', 'dark');
                if (themeIcon) themeIcon.textContent = 'light_mode';
            }
        });
    }

    // ==========================================
    // ==========================================
    // Collapsible FAQ Accordion & Fetch API Logic (FAQ.html - Practical 6)
    // ==========================================
    const faqList = document.querySelector('.faq-accordion-list');

    if (faqList) {
        const categoryButtons = document.querySelectorAll('.faq-categories .pill-btn');
        const searchInput = document.getElementById('faq-search-input') || document.querySelector('.faq-search-input-group input');
        const searchBtn = document.querySelector('.faq-search-btn');

        let faqsData = [];

        // Helper to bind accordion click & keyboard events to an item
        function bindAccordion(item) {
            const summary = item.querySelector('.faq-summary');
            if (!summary) return;

            const toggleAccordion = () => {
                const isOpen = item.hasAttribute('open');

                // Accordion behavior: close other open items
                document.querySelectorAll('.faq-accordion-item').forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.removeAttribute('open');
                        const otherSummary = otherItem.querySelector('.faq-summary');
                        if (otherSummary) otherSummary.setAttribute('aria-expanded', 'false');
                    }
                });

                if (isOpen) {
                    item.removeAttribute('open');
                    summary.setAttribute('aria-expanded', 'false');
                } else {
                    item.setAttribute('open', '');
                    summary.setAttribute('aria-expanded', 'true');
                }
            };

            summary.addEventListener('click', toggleAccordion);
            summary.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleAccordion();
                }
            });
        }

        // Render dynamic FAQ items from JSON
        function renderFaqs(items) {
            faqList.innerHTML = '';
            if (items.length === 0) {
                faqList.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                        <span class="material-symbols-outlined" style="font-size: 40px; color: var(--primary); display: block; margin-bottom: 8px;">help_outline</span>
                        <h3>No matching questions found</h3>
                        <p style="margin-top: 4px; font-size: 13.5px;">Try adjusting your keywords or selecting another category.</p>
                    </div>
                `;
                return;
            }

            items.forEach((faq, index) => {
                const item = document.createElement('div');
                item.className = 'faq-accordion-item';
                item.setAttribute('data-category', faq.category);
                if (index === 0) item.setAttribute('open', '');

                item.innerHTML = `
                    <div class="faq-summary" role="button" tabindex="0" aria-expanded="${index === 0 ? 'true' : 'false'}">
                        <span>${faq.question}</span>
                        <span class="material-symbols-outlined faq-icon">expand_more</span>
                    </div>
                    <div class="faq-content">
                        <p>${faq.answer}</p>
                    </div>
                `;
                faqList.appendChild(item);
                bindAccordion(item);
            });
        }

        // Filter FAQs by Category and Search Keyword
        const filterFaqs = () => {
            const activeBtn = document.querySelector('.faq-categories .pill-btn.active');
            const selectedCategory = activeBtn ? (activeBtn.getAttribute('data-filter') || activeBtn.textContent.trim()) : 'all';
            const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';

            const filtered = faqsData.filter(faq => {
                const matchesCategory = selectedCategory === 'all' || selectedCategory === 'All Questions' || faq.category.toLowerCase() === selectedCategory.toLowerCase();
                const matchesSearch = !searchTerm ||
                    faq.question.toLowerCase().includes(searchTerm) ||
                    faq.answer.toLowerCase().includes(searchTerm) ||
                    (faq.tags && faq.tags.some(tag => tag.toLowerCase().includes(searchTerm)));

                return matchesCategory && matchesSearch;
            });

            renderFaqs(filtered);
        };

        // Fetch faqs.json via Fetch API
        async function loadFaqs() {
            const isInSubdir = window.location.pathname.includes('/page/') || window.location.pathname.includes('/admin/');
            const paths = isInSubdir
                ? ['../data/faqs.json', 'data/faqs.json']
                : ['data/faqs.json', '../data/faqs.json'];

            for (const path of paths) {
                try {
                    const res = await fetch(path);
                    if (res.ok) {
                        const data = await res.json();
                        if (Array.isArray(data) && data.length > 0) {
                            faqsData = data;
                            filterFaqs();
                            return;
                        }
                    }
                } catch (e) {}
            }

            // Fallback to static items if JSON not reachable
            document.querySelectorAll('.faq-accordion-item').forEach(bindAccordion);
        }

        loadFaqs();

        // Category Filter Buttons
        if (categoryButtons.length > 0) {
            categoryButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    categoryButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    filterFaqs();
                });
            });
        }

        // Live Search Input
        if (searchInput) {
            searchInput.addEventListener('input', filterFaqs);
        }

        if (searchBtn) {
            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                filterFaqs();
            });
        }
    }

    // ===================================================
    // Campus Events Dynamic Loading, Sorting & Pagination (events.html)
    // Practical 6: Fetch API, Search, Filter, Sort, Pagination & Cache
    // Practical 8: Dual Data Source Switcher (JSON File vs MySQL DB)
    // ===================================================
    const eventsGrid = document.getElementById('events-grid');
    if (eventsGrid) {
        const searchInput = document.getElementById('search');
        const categorySelect = document.getElementById('category');
        const sortSelect = document.getElementById('event-sort');
        const searchBtn = document.getElementById('search-btn');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const paginationPages = document.getElementById('pagination-pages');
        const paginationSection = document.getElementById('events-pagination');
        const sourceRadios = document.querySelectorAll('input[name="event_source"]');
        const sourcePills = document.querySelectorAll('#source-toggle-wrapper .corner-radio-pill');

        const ITEMS_PER_PAGE = 6;
        let currentPage = 1;
        let allEvents = [];
        let filteredEvents = [];
        let currentSource = 'json'; // 'json' or 'db'

        // Render animated skeleton loaders while fetching
        function renderSkeletons() {
            eventsGrid.innerHTML = '';
            for (let i = 0; i < ITEMS_PER_PAGE; i++) {
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton-card';
                skeleton.innerHTML = `
                    <div class="skeleton-image"></div>
                    <div class="skeleton-body">
                        <div class="skeleton-line title"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line short"></div>
                    </div>
                `;
                eventsGrid.appendChild(skeleton);
            }
        }

        // Fetch events based on selected source (JSON File vs MySQL DB)
        async function fetchEvents(source = 'json') {
            renderSkeletons();
            const isInSubdir = window.location.pathname.includes('/page/') || window.location.pathname.includes('/admin/');
            
            if (source === 'db') {
                // Practical 8: Fetch live from MySQL database via PHP API
                const dbPath = isInSubdir ? '../php/api_db_events.php' : 'php/api_db_events.php';
                try {
                    const res = await fetch(dbPath);
                    if (res.ok) {
                        const data = await res.json();
                        if (data && data.success && Array.isArray(data.events)) {
                            return data.events;
                        } else if (Array.isArray(data)) {
                            return data;
                        }
                    }
                } catch (err) {
                    console.warn('MySQL API unreachable, checking cache or falling back...', err);
                }
            }

            // Practical 6: Fetch from external JSON file with localStorage cache
            const jsonPaths = isInSubdir
                ? ['../data/events.json', 'data/events.json']
                : ['data/events.json', '../data/events.json'];

            for (const path of jsonPaths) {
                try {
                    const res = await fetch(path);
                    if (res.ok) {
                        const data = await res.json();
                        if (Array.isArray(data) && data.length > 0) {
                            // Practical 6 Advanced: Cache in localStorage for offline display
                            try {
                                localStorage.setItem('studenthub_events_cache', JSON.stringify({
                                    timestamp: Date.now(),
                                    events: data
                                }));
                            } catch (e) {
                                // Quota exceeded or private browsing
                            }
                            return data;
                        }
                    }
                } catch (err) {
                    // Try next path
                }
            }

            // Offline / fallback cache recovery
            const cached = localStorage.getItem('studenthub_events_cache');
            if (cached) {
                try {
                    const parsed = JSON.parse(cached);
                    if (parsed && Array.isArray(parsed.events)) {
                        return parsed.events;
                    }
                } catch (e) {}
            }

            // Built-in resilient fallback data for offline / file protocol display
            return [
                { id: 1, title: "AI Workshop", category: "Tech & Innovation", badge: "Tech", description: "Join leading researchers for a deep dive into the ethical implications of large language models in academia.", date: "Oct 15, 2026", time: "2:00 PM", dateTime: "Oct 15, 2026 • 2:00 PM", location: "Main Library, Hall B", image: "image/AI-one.jpg" },
                { id: 2, title: "Autumn Arts Festival", category: "Arts & Culture", badge: "Cultural", description: "Celebrate the change of seasons with live music, local food vendors, and student art exhibitions.", date: "Oct 18, 2026", time: "11:00 AM", dateTime: "Oct 18, 2026 • 11:00 AM", location: "Central Quad", image: "https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&auto=format&fit=crop&q=80" },
                { id: 3, title: "Full-Stack Web Development Workshop", category: "Tech & Innovation", badge: "Academic", description: "Learn how to build production-ready web applications that handle millions of users using modern frameworks.", date: "Oct 22, 2026", time: "10:00 AM", dateTime: "Oct 22, 2026 • 10:00 AM", location: "Computing Lab 4", image: "image/Full-Stack.jpg" },
                { id: 4, title: "University Hackathon 2026", category: "Tech & Innovation", badge: "Competition", description: "48 hours of rapid prototyping, mentorship, and building solutions for smart sustainable campus life.", date: "Oct 26, 2026", time: "9:00 AM", dateTime: "Oct 26, 2026 • 9:00 AM", location: "Student Innovation Hub", image: "image/hackathon.webp" },
                { id: 5, title: "Inter-Collegiate Football Cup", category: "Sports & Wellness", badge: "Sports", description: "Cheer on the university varsity squad as they take on regional champions in the opening match of the season.", date: "Nov 02, 2026", time: "4:30 PM", dateTime: "Nov 02, 2026 • 4:30 PM", location: "Varsity Sports Stadium", image: "image/Footbal.jpg" },
                { id: 6, title: "Research Colloquium on Quantum Computing", category: "Academic Seminars", badge: "Seminar", description: "Keynote lectures by visiting scholars on superconducting qubits and fault-tolerant quantum algorithms.", date: "Nov 05, 2026", time: "1:30 PM", dateTime: "Nov 05, 2026 • 1:30 PM", location: "Faculty Auditorium West", image: "image/Quantum.jpg" }
            ];
        }

        // Render an individual event card
        function createEventCard(event) {
            const article = document.createElement('article');
            article.className = 'event-card';
            const eventId = event.id || event.event_id || 1;
            article.setAttribute('data-id', eventId);
            article.setAttribute('data-title', event.title || '');

            const displayDateTime = event.dateTime || `${event.date || ''} • ${event.time || ''}`;

            // Resolve relative image path according to folder level
            let imgSrc = event.image || 'image/Full-Stack.jpg';
            if (imgSrc && !imgSrc.startsWith('http://') && !imgSrc.startsWith('https://') && !imgSrc.startsWith('data:')) {
                const cleanImg = imgSrc.replace(/^(\.\.\/|\/)?image\//, '');
                const isInSubdir = window.location.pathname.includes('/page/') || window.location.pathname.includes('/admin/');
                imgSrc = (isInSubdir ? '../image/' : 'image/') + cleanImg;
            }

            article.innerHTML = `
                <div class="event-card-image">
                    <img src="${imgSrc}" alt="${event.title}" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=600&auto=format&fit=crop&q=80';">
                    <span class="event-badge">${event.badge || 'Campus'}</span>
                </div>
                <div class="event-card-body">
                    <h3>${event.title}</h3>
                    <p>${event.description}</p>
                    <div class="event-details">
                        <div class="detail-item">
                            <span class="material-symbols-outlined">calendar_today</span>
                            <span>${displayDateTime}</span>
                        </div>
                        <div class="detail-item">
                            <span class="material-symbols-outlined">location_on</span>
                            <span>${event.location || 'Campus Auditorium'}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-full card-btn" data-id="${eventId}" data-title="${encodeURIComponent(event.title || '')}">Register Now</button>
                </div>
            `;
            return article;
        }

        // Parse date string into comparable timestamp
        function parseEventDate(event) {
            if (event.event_date) return new Date(event.event_date).getTime();
            if (event.date) return new Date(event.date + ' 2026').getTime() || 0;
            return 0;
        }

        // Apply Search, Category Filtering, and Multi-Criteria Sorting
        function applyFilter() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const selectedCategory = categorySelect ? categorySelect.value : 'All Categories';
            const sortMode = sortSelect ? sortSelect.value : 'date-asc';

            // 1. Filter by category & search query
            filteredEvents = allEvents.filter(event => {
                const categoryMatch = (selectedCategory === 'All Categories' || !selectedCategory || event.category === selectedCategory);
                const queryMatch = !query ||
                    event.title.toLowerCase().includes(query) ||
                    (event.description && event.description.toLowerCase().includes(query)) ||
                    (event.location && event.location.toLowerCase().includes(query)) ||
                    (event.badge && event.badge.toLowerCase().includes(query));

                return categoryMatch && queryMatch;
            });

            // 2. Multi-Criteria Sort (Practical 6)
            filteredEvents.sort((a, b) => {
                if (sortMode === 'date-asc') {
                    return parseEventDate(a) - parseEventDate(b);
                } else if (sortMode === 'date-desc') {
                    return parseEventDate(b) - parseEventDate(a);
                } else if (sortMode === 'title-asc') {
                    return a.title.localeCompare(b.title);
                } else if (sortMode === 'title-desc') {
                    return b.title.localeCompare(a.title);
                }
                return 0;
            });

            currentPage = 1;
            renderPage();
        }

        // Render current page of events
        function renderPage() {
            eventsGrid.innerHTML = '';

            const totalEvents = filteredEvents.length;
            const totalPages = Math.ceil(totalEvents / ITEMS_PER_PAGE);

            if (totalEvents === 0) {
                eventsGrid.innerHTML = `
                    <div class="no-events-found" style="grid-column: 1/-1; text-align: center; padding: 48px 20px;">
                        <span class="material-symbols-outlined" style="font-size: 44px; color: var(--primary); display: block; margin-bottom: 0.5rem;">event_busy</span>
                        <h3>No events found</h3>
                        <p style="color: var(--text-secondary); margin-top: 6px;">Try adjusting your keywords, discipline filter, or sort criteria.</p>
                    </div>
                `;
                if (paginationSection) paginationSection.style.display = 'none';
                return;
            }

            if (paginationSection) paginationSection.style.display = 'flex';

            const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalEvents);
            const currentEvents = filteredEvents.slice(startIndex, endIndex);

            currentEvents.forEach(event => {
                eventsGrid.appendChild(createEventCard(event));
            });

            // Update Next & Previous Button states
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages;
            }

            // Update Page numbers
            if (paginationPages) {
                paginationPages.innerHTML = '';
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.className = `pagination-page-btn ${i === currentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.setAttribute('aria-label', `Page ${i}`);
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderPage();
                        eventsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    paginationPages.appendChild(pageBtn);
                }
            }
        }

        // Load data with error and retry handling
        async function loadData(source = 'json') {
            currentSource = source;
            const data = await fetchEvents(source);
            if (data === null || !Array.isArray(data)) {
                eventsGrid.innerHTML = `
                    <div class="error-retry-card">
                        <span class="material-symbols-outlined">error_outline</span>
                        <h3>Unable to load event records</h3>
                        <p>We encountered an issue fetching data from the ${source === 'db' ? 'MySQL Database' : 'external JSON file'}. Please verify your connection or try again.</p>
                        <button type="button" class="btn-retry" id="btn-retry-fetch">
                            <span class="material-symbols-outlined">refresh</span> Retry Fetch
                        </button>
                    </div>
                `;
                if (paginationSection) paginationSection.style.display = 'none';
                const retryBtn = document.getElementById('btn-retry-fetch');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => loadData(currentSource));
                }
                return;
            }

            allEvents = data;
            filteredEvents = [...allEvents];
            applyFilter();
        }

        // Initial Data Load
        loadData('json');

        // Data Source Switcher Listeners (Practical 6 vs Practical 8)
        function setEventSource(mode) {
            if (currentSource === mode) return;
            currentSource = mode;
            sourcePills.forEach(pill => {
                const r = pill.querySelector('input[type="radio"]');
                if (r && r.value === mode) {
                    pill.classList.add('active');
                    r.checked = true;
                } else {
                    pill.classList.remove('active');
                }
            });
            loadData(mode);
        }

        sourceRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                setEventSource(e.target.value);
            });
        });

        sourcePills.forEach(pill => {
            pill.addEventListener('click', () => {
                const r = pill.querySelector('input[type="radio"]');
                if (r && r.value) {
                    setEventSource(r.value);
                }
            });
        });

        // Event Listeners for Filters & Sorting
        if (searchInput) {
            searchInput.addEventListener('input', applyFilter);
        }
        if (categorySelect) {
            categorySelect.addEventListener('change', applyFilter);
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', applyFilter);
        }
        if (searchBtn) {
            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                applyFilter();
            });
        }

        // Previous & Next Buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage();
                    eventsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil(filteredEvents.length / ITEMS_PER_PAGE);
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage();
                    eventsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        // ===================================================
        // Practical 4 & Practical 7: Event Registration Modal System
        // ===================================================
        const registerModal = document.getElementById('register-modal');
        const modalCloseBtn = document.getElementById('modal-close-btn');
        const stepConfirm = document.getElementById('modal-step-confirm');
        const stepStudentId = document.getElementById('modal-step-student-id');
        const stepCompleted = document.getElementById('modal-step-completed');

        const btnLetsGo = document.getElementById('modal-btn-letsgo');
        const btnNotYet = document.getElementById('modal-btn-notyet');
        const btnBackStep1 = document.getElementById('btn-back-to-step1');
        const btnCompletedDone = document.getElementById('btn-completed-done');

        const studentIdForm = document.getElementById('student-id-submit-form');
        const studentIdInput = document.getElementById('student-unique-id-field');
        const btnSubmitStudentId = document.getElementById('btn-submit-student-id');
        const stepIdFeedback = document.getElementById('step-id-feedback');

        const confirmEventTitle = document.getElementById('confirm-event-title');
        const inputStepEventTitle = document.getElementById('input-step-event-title');
        const selectedEventIdInput = document.getElementById('selected-event-id');
        const selectedEventTitleInput = document.getElementById('selected-event-title');

        // Success Step Elements
        const ticketIdDisplay = document.getElementById('ticket-id-display');
        const ticketEventTitleDisplay = document.getElementById('ticket-event-title-display');
        const ticketStudentName = document.getElementById('ticket-student-name');
        const ticketStudentId = document.getElementById('ticket-student-id');
        const ticketStudentDept = document.getElementById('ticket-student-dept');
        const ticketDateDisplay = document.getElementById('ticket-date-display');

        let currentActiveEventId = '1';
        let currentActiveEventTitle = 'Campus Event';

        function setStep(stepName) {
            if (stepConfirm) stepConfirm.style.display = stepName === 'confirm' ? 'block' : 'none';
            if (stepStudentId) stepStudentId.style.display = stepName === 'student_id' ? 'block' : 'none';
            if (stepCompleted) stepCompleted.style.display = stepName === 'completed' ? 'block' : 'none';
        }

        function openRegisterModal(eventTitle = '', eventId = '1') {
            if (!registerModal) return;
            currentActiveEventId = eventId;
            currentActiveEventTitle = eventTitle || 'Campus Event';

            if (selectedEventIdInput) selectedEventIdInput.value = eventId;
            if (selectedEventTitleInput) selectedEventTitleInput.value = currentActiveEventTitle;

            if (confirmEventTitle) confirmEventTitle.textContent = currentActiveEventTitle;
            if (inputStepEventTitle) inputStepEventTitle.textContent = currentActiveEventTitle;

            // Reset inputs & feedback
            if (studentIdInput) {
                studentIdInput.value = '';
                studentIdInput.style.borderColor = '';
            }
            if (stepIdFeedback) {
                stepIdFeedback.style.display = 'none';
                stepIdFeedback.innerHTML = '';
            }
            if (btnSubmitStudentId) {
                btnSubmitStudentId.disabled = false;
                btnSubmitStudentId.innerHTML = '<span>Confirm &amp; Register</span><span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>';
            }

            // Always start at Step 1: Confirmation
            setStep('confirm');

            registerModal.style.display = 'flex';
            registerModal.style.visibility = 'visible';
            registerModal.style.opacity = '1';
            registerModal.classList.add('active');
            registerModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeRegisterModal() {
            if (!registerModal) return;
            registerModal.classList.remove('active');
            registerModal.style.opacity = '0';
            registerModal.style.visibility = 'hidden';
            registerModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            setTimeout(() => {
                if (!registerModal.classList.contains('active')) {
                    registerModal.style.display = 'none';
                    setStep('confirm');
                }
            }, 200);
        }

        // Intercept clicks on event card "Register Now" buttons only
        eventsGrid.addEventListener('click', (e) => {
            const regBtn = e.target.closest('.card-btn');
            if (regBtn) {
                e.preventDefault();
                e.stopPropagation();
                const card = regBtn.closest('.event-card');
                const title = regBtn.getAttribute('data-title') 
                    ? decodeURIComponent(regBtn.getAttribute('data-title')) 
                    : (card ? card.querySelector('h3')?.textContent : '');
                const eventId = regBtn.getAttribute('data-id') || (card ? card.getAttribute('data-id') : '1');
                openRegisterModal(title, eventId);
            }
        });

        // Step 1: "Let's Go" -> Switch to Step 2 (Accept Student Unique ID)
        if (btnLetsGo) {
            btnLetsGo.addEventListener('click', () => {
                setStep('student_id');
                if (studentIdInput) {
                    setTimeout(() => studentIdInput.focus(), 120);
                }
            });
        }

        // Step 1: "Not Yet" -> Close modal
        if (btnNotYet) {
            btnNotYet.addEventListener('click', closeRegisterModal);
        }

        // Step 2: "Back" -> Return to Step 1
        if (btnBackStep1) {
            btnBackStep1.addEventListener('click', () => {
                setStep('confirm');
            });
        }

        // Step 3: "Done" -> Close modal
        if (btnCompletedDone) {
            btnCompletedDone.addEventListener('click', closeRegisterModal);
        }

        // Close on 'x' button
        if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeRegisterModal);

        // Close on clicking backdrop outside modal card
        registerModal.addEventListener('click', (e) => {
            if (e.target === registerModal) {
                closeRegisterModal();
            }
        });

        // Close on Escape key press
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && registerModal && registerModal.classList.contains('active')) {
                closeRegisterModal();
            }
        });

        // Step 2: Submit Student Unique ID Form
        if (studentIdInput) {
            studentIdInput.addEventListener('input', () => {
                studentIdInput.style.borderColor = '';
                if (stepIdFeedback) {
                    stepIdFeedback.style.display = 'none';
                }
            });
        }

        if (studentIdForm) {
            studentIdForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const studentId = studentIdInput ? studentIdInput.value.trim() : '';

                if (!studentId) {
                    if (stepIdFeedback) {
                        stepIdFeedback.style.display = 'block';
                        stepIdFeedback.style.background = '#fef2f2';
                        stepIdFeedback.style.border = '1px solid #fecaca';
                        stepIdFeedback.style.color = '#991b1b';
                        stepIdFeedback.textContent = 'Please enter your Student Unique ID (e.g., STU-1001).';
                    }
                    if (studentIdInput) {
                        studentIdInput.style.borderColor = '#dc2626';
                        studentIdInput.focus();
                    }
                    return;
                }

                // Loading state
                if (btnSubmitStudentId) {
                    btnSubmitStudentId.disabled = true;
                    btnSubmitStudentId.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px;">sync</span><span>Verifying...</span>';
                }
                if (stepIdFeedback) {
                    stepIdFeedback.style.display = 'none';
                }

                try {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('event_id', currentActiveEventId);
                    formData.append('event_title', currentActiveEventTitle);
                    formData.append('student_id', studentId);

                    const res = await fetch('../php/process_event_registration.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await res.json();

                    if (data.success && data.registration) {
                        // Registration saved to event_registrations.json & event_registrations.csv!
                        // Switch to Step 3: Registration Completed Popup
                        if (ticketIdDisplay) ticketIdDisplay.textContent = data.registration.registration_id;
                        if (ticketEventTitleDisplay) ticketEventTitleDisplay.textContent = data.registration.event_title;
                        if (ticketStudentName) ticketStudentName.textContent = data.registration.fullname;
                        if (ticketStudentId) ticketStudentId.textContent = data.registration.student_id;
                        if (ticketStudentDept) ticketStudentDept.textContent = data.registration.department;
                        if (ticketDateDisplay) ticketDateDisplay.textContent = data.registration.registration_date;

                        setStep('completed');
                    } else if (data.not_registered || data.not_found) {
                        // Student not found in registrations
                        if (stepIdFeedback) {
                            stepIdFeedback.style.display = 'block';
                            stepIdFeedback.style.background = '#fef2f2';
                            stepIdFeedback.style.border = '1px solid #fecaca';
                            stepIdFeedback.style.color = '#991b1b';
                            stepIdFeedback.style.borderRadius = '12px';
                            stepIdFeedback.style.padding = '14px 16px';
                            stepIdFeedback.innerHTML = `
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span class="material-symbols-outlined" style="font-size: 22px; color: #dc2626; flex-shrink: 0; margin-top: 1px;">error</span>
                                    <div style="flex: 1;">
                                        <div style="font-size: 14.5px; font-weight: 700; color: #991b1b; margin-bottom: 4px;">ID doesn't found</div>
                                        <p style="margin: 0 0 12px; font-size: 12.5px; color: #7f1d1d; line-height: 1.4;">
                                            Student Unique ID "<strong>${studentId}</strong>" is not registered. You need to register your student account first before booking events.
                                        </p>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <a href="register.html" class="btn-modal btn-modal-confirm" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; font-size: 12.5px; text-decoration: none; border-radius: 8px; width: auto;">
                                                <span>Register Now</span>
                                                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        if (studentIdInput) {
                            studentIdInput.style.borderColor = '#dc2626';
                            studentIdInput.focus();
                        }
                        if (btnSubmitStudentId) {
                            btnSubmitStudentId.disabled = false;
                            btnSubmitStudentId.innerHTML = '<span>Confirm &amp; Register</span><span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>';
                        }
                    } else if (data.already_registered) {
                        // Already registered for this event
                        if (stepIdFeedback) {
                            stepIdFeedback.style.display = 'block';
                            stepIdFeedback.style.background = '#fffbeb';
                            stepIdFeedback.style.border = '1px solid #fde68a';
                            stepIdFeedback.style.color = '#92400e';
                            stepIdFeedback.innerHTML = `
                                <strong>Already Registered</strong>
                                <p style="margin-top: 4px; font-size: 12.5px;">${data.message}</p>
                            `;
                        }
                        if (btnSubmitStudentId) {
                            btnSubmitStudentId.disabled = false;
                            btnSubmitStudentId.innerHTML = '<span>Close</span>';
                            btnSubmitStudentId.onclick = closeRegisterModal;
                        }
                    } else {
                        if (stepIdFeedback) {
                            stepIdFeedback.style.display = 'block';
                            stepIdFeedback.style.background = '#fef2f2';
                            stepIdFeedback.style.border = '1px solid #fecaca';
                            stepIdFeedback.style.color = '#991b1b';
                            stepIdFeedback.textContent = data.message || 'Registration failed. Please check inputs and try again.';
                        }
                        if (btnSubmitStudentId) {
                            btnSubmitStudentId.disabled = false;
                            btnSubmitStudentId.innerHTML = '<span>Try Again</span>';
                        }
                    }
                } catch (err) {
                    if (stepIdFeedback) {
                        stepIdFeedback.style.display = 'block';
                        stepIdFeedback.style.background = '#fef2f2';
                        stepIdFeedback.style.border = '1px solid #fecaca';
                        stepIdFeedback.style.color = '#991b1b';
                        stepIdFeedback.textContent = 'Server connection error. Please make sure XAMPP Apache is running.';
                    }
                    if (btnSubmitStudentId) {
                        btnSubmitStudentId.disabled = false;
                        btnSubmitStudentId.innerHTML = '<span>Try Again</span>';
                    }
                }
            });
        }
    }

    // ===================================================
    // Welcome Notification Banner Dismissal (index.html)
    // ===================================================
    const welcomeBanner = document.getElementById('welcome-notification-banner');
    const closeBannerBtn = document.getElementById('close-welcome-banner');

    if (welcomeBanner && closeBannerBtn) {
        // Clear legacy persistent storage key so banner reliably shows on load/refresh
        localStorage.removeItem('welcomeBannerDismissed');
        welcomeBanner.style.display = 'flex';
        welcomeBanner.classList.remove('banner-hidden');

        // Dismiss banner when close button is clicked (will reappear on refresh)
        closeBannerBtn.addEventListener('click', () => {
            welcomeBanner.classList.add('banner-hidden');
            setTimeout(() => {
                welcomeBanner.style.display = 'none';
            }, 300);
        });
    }

    // ===================================================
    // Hero Banner Background Image Slider (index.html)
    // ===================================================
    const heroSlides = document.querySelectorAll('.hero-slide');
    const heroPrevBtn = document.getElementById('hero-prev-btn');
    const heroNextBtn = document.getElementById('hero-next-btn');
    const heroDots = document.querySelectorAll('#hero-slider-dots .slider-dot');
    const heroBanner = document.querySelector('.hero-banner');

    if (heroSlides.length > 0) {
        let currentSlide = 0;
        let slideTimer = null;
        const totalSlides = heroSlides.length;

        const updateSlide = (newIndex) => {
            // Normalize index in cyclic order
            if (newIndex < 0) {
                currentSlide = totalSlides - 1;
            } else if (newIndex >= totalSlides) {
                currentSlide = 0;
            } else {
                currentSlide = newIndex;
            }

            // Update slide classes
            heroSlides.forEach((slide, idx) => {
                if (idx === currentSlide) {
                    slide.classList.add('active');
                } else {
                    slide.classList.remove('active');
                }
            });

            // Update dot indicators
            heroDots.forEach((dot, idx) => {
                if (idx === currentSlide) {
                    dot.classList.add('active');
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.classList.remove('active');
                    dot.removeAttribute('aria-current');
                }
            });
        };

        const nextSlide = () => updateSlide(currentSlide + 1);
        const prevSlide = () => updateSlide(currentSlide - 1);

        // Next / Prev Button Click Handlers
        if (heroNextBtn) {
            heroNextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                nextSlide();
                restartAutoplay();
            });
        }

        if (heroPrevBtn) {
            heroPrevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                prevSlide();
                restartAutoplay();
            });
        }

        // Dot Navigation Click Handlers
        heroDots.forEach((dot) => {
            dot.addEventListener('click', (e) => {
                e.preventDefault();
                const targetIdx = parseInt(dot.getAttribute('data-slide'), 10);
                if (!isNaN(targetIdx)) {
                    updateSlide(targetIdx);
                    restartAutoplay();
                }
            });
        });

        // Autoplay Interval (5 seconds)
        const startAutoplay = () => {
            if (!slideTimer) {
                slideTimer = setInterval(nextSlide, 5000);
            }
        };

        const stopAutoplay = () => {
            if (slideTimer) {
                clearInterval(slideTimer);
                slideTimer = null;
            }
        };

        const restartAutoplay = () => {
            stopAutoplay();
            startAutoplay();
        };

        // Pause autoplay on hover, resume on leave
        if (heroBanner) {
            heroBanner.addEventListener('mouseenter', stopAutoplay);
            heroBanner.addEventListener('mouseleave', startAutoplay);
        }

        // Start initial autoplay
        startAutoplay();
    }

    // ===================================================
    // Responsive Mobile Hamburger Menu Toggle
    // ===================================================
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const hamburgerIcon = mobileMenuToggle ? mobileMenuToggle.querySelector('.hamburger-icon') : null;

    if (mobileMenuToggle && navMenu) {
        const toggleMenu = () => {
            const isOpen = navMenu.classList.toggle('mobile-open');
            mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (hamburgerIcon) {
                hamburgerIcon.textContent = isOpen ? 'close' : 'menu';
            }
        };

        const closeMenu = () => {
            if (navMenu.classList.contains('mobile-open')) {
                navMenu.classList.remove('mobile-open');
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
                if (hamburgerIcon) {
                    hamburgerIcon.textContent = 'menu';
                }
            }
        };

        mobileMenuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        // Close when clicking any nav link inside menu
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                closeMenu();
            });
        });

        // Close when clicking outside navbar
        document.addEventListener('click', (e) => {
            const navbar = document.querySelector('.navbar');
            if (navbar && !navbar.contains(e.target)) {
                closeMenu();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });
    }
});