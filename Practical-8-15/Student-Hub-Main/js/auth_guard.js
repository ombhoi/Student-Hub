/**
 * Practical 10: Client Authentication Guard & Session State Manager
 * File: js/auth_guard.js
 * Description: 
 *   - Verifies session authentication with php/auth_status.php
 *   - Protects restricted pages (student-dashboard.html, student-profile.html, admin.html)
 *   - Seamlessly updates navbar across ALL pages with Dashboard, Profile badge, and Logout
 *   - Completely hides Login & Register buttons when authenticated
 *   - Pre-fills student credentials in event booking modals
 */

(function () {
    'use strict';

    // 1. Path Resolution
    const currentPath = window.location.pathname.replace(/\\/g, '/');
    const isInAdmin = currentPath.includes('/admin/');
    const isInPage = currentPath.includes('/page/');
    const isInSubfolder = isInAdmin || isInPage;
    const phpBasePath = isInSubfolder ? '../php/' : 'php/';
    const rootBasePath = isInSubfolder ? '../' : '';
    const pageBasePath = isInAdmin ? '../page/' : (isInPage ? '' : 'page/');

    // Strictly protected pages (Unauthenticated guests CANNOT view)
    const strictlyProtectedPages = [
        'events.html',
        'student-dashboard.html',
        'student-profile.html',
        'admin.html'
    ];

    const pathParts = currentPath.split('/');
    const currentPage = pathParts[pathParts.length - 1] || 'index.html';
    const isStrictlyProtected = strictlyProtectedPages.some(page => currentPage.toLowerCase().endsWith(page.toLowerCase()));
    const isAdminOnly = currentPage.toLowerCase().endsWith('admin.html');
    const isLoginPage = currentPage.toLowerCase().endsWith('login.html');
    const isRegisterPage = currentPage.toLowerCase().endsWith('register.html');

    // Immediately conceal protected page to guarantee zero flash of restricted content to guests
    if (isStrictlyProtected) {
        const protectStyle = document.createElement('style');
        protectStyle.id = 'auth-guard-protect-style';
        protectStyle.textContent = 'body { display: none !important; }';
        (document.head || document.documentElement).appendChild(protectStyle);
    }

    let currentUser = null;

    // Helper: Execute callback when DOM is ready
    function onDOMReady(fn) {
        const execute = () => {
            // Instantly eliminate any legacy avatar buttons from older template caches
            document.querySelectorAll('.user-avatar-btn').forEach(el => el.remove());
            // Instantly eliminate any lingering text 'Profile' links from .nav-menu
            document.querySelectorAll('.nav-menu a').forEach(a => {
                if (a.textContent.trim().toLowerCase() === 'profile' || (a.getAttribute('href') && a.getAttribute('href').includes('student-profile.html'))) {
                    a.closest('li')?.remove();
                }
            });
            fn();
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', execute, { once: true });
        } else {
            execute();
        }
    }

    // 2. Fetch Session Authentication Status
    async function checkAuthStatus() {
        try {
            const url = phpBasePath + 'auth_status.php?t=' + Date.now();
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                if (isStrictlyProtected) handleUnauthorized('session_error');
                return null;
            }

            const data = await response.json();

            // Handle Session Timeout
            if (data.timeout) {
                if (isStrictlyProtected) {
                    window.location.replace(pageBasePath + 'login.html?timeout=1');
                    return null;
                }
                onDOMReady(handleGuest);
                return null;
            }

            if (data.authenticated && data.user) {
                currentUser = data.user;
                window.__studentHubUser = data.user;
                try { localStorage.setItem('studenthub_auth_user', JSON.stringify(data.user)); } catch (e) {}
                handleAuthenticated(data.user);
            } else {
                if (isStrictlyProtected) {
                    handleUnauthorized('auth_required');
                } else {
                    onDOMReady(handleGuest);
                }
            }

            return data;
        } catch (error) {
            console.warn('[AuthGuard] Status check error:', error);
            if (isStrictlyProtected) {
                handleUnauthorized('auth_required');
            } else {
                onDOMReady(handleGuest);
            }
            return null;
        }
    }

    // 3. Handle Unauthorized Access to Strictly Protected Pages
    function handleUnauthorized(reason) {
        // Immediately redirect to login with 0 delay and 0 screen exposure
        const pageName = window.location.pathname.split('/').pop() || 'events.html';
        const loginUrl = pageBasePath + 'login.html?error=' + encodeURIComponent(reason) + '&redirect=' + encodeURIComponent(pageName);
        window.location.replace(loginUrl);
    }

    // 4. Handle Authenticated User
    function handleAuthenticated(user) {
        // Reveal protected page contents now that auth is verified
        const protectStyle = document.getElementById('auth-guard-protect-style');
        if (protectStyle) protectStyle.remove();
        const strictGuard = document.getElementById('auth-strict-guard');
        if (strictGuard) strictGuard.remove();
        document.documentElement.style.visibility = 'visible';

        // RBAC Check: Students cannot view Admin panel
        if (isAdminOnly && user.role !== 'admin') {
            alert('Access Denied: Administrator role required to view the Admin panel.');
            window.location.replace(pageBasePath + 'student-dashboard.html?error=forbidden');
            return;
        }

        // RBAC Isolation: Admin account is completely isolated to the Admin panel
        if (user.role === 'admin' && !isAdminOnly) {
            window.location.replace(rootBasePath + 'admin/admin.html');
            return;
        }

        // Redirect from login/register if already authenticated
        if (isLoginPage || isRegisterPage) {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('msg') && !urlParams.has('registered')) {
                const destination = user.role === 'admin' ? (rootBasePath + 'admin/admin.html') : (rootBasePath + 'page/student-dashboard.html');
                window.location.replace(destination);
                return;
            }
        }

        // Tag body as authenticated
        document.body.classList.add('is-authenticated');

        // Apply Authenticated UI as soon as DOM is ready
        onDOMReady(() => {
            applyAuthenticatedNavbar(user);
            applyPageSpecificData(user);
        });

        // Start session timeout monitor
        startIdleWatcher();
    }

    // 5. Apply Authenticated Navbar across ALL pages
    function applyAuthenticatedNavbar(user) {
        const dashboardUrl = user.role === 'admin' 
            ? (rootBasePath + 'admin/admin.html') 
            : (pageBasePath + 'student-dashboard.html');
        const profileUrl = pageBasePath + 'student-profile.html';
        const eventsUrl = pageBasePath + 'events.html';
        const homeUrl = isInSubfolder ? '../index.html' : 'index.html';
        const aboutUrl = pageBasePath + 'about-us.html';
        const faqUrl = pageBasePath + 'FAQ.html';
        const contactUrl = pageBasePath + 'contact.html';
        const logoutUrl = phpBasePath + 'logout.php';

        const isHome = currentPage.toLowerCase().endsWith('index.html') || currentPage === '';
        const isDashboard = currentPage.toLowerCase().endsWith('student-dashboard.html') || currentPage.toLowerCase().endsWith('admin.html');
        const isEvents = currentPage.toLowerCase().endsWith('events.html');
        const isAbout = currentPage.toLowerCase().endsWith('about-us.html');
        const isFaq = currentPage.toLowerCase().endsWith('faq.html');
        const isContact = currentPage.toLowerCase().endsWith('contact.html');
        const isProfile = currentPage.toLowerCase().endsWith('student-profile.html');

        // A. Standardize .nav-menu links:
        // For Admin: The Admin Panel is completely isolated with no public student links
        // For Students: Home -> Dashboard -> Events -> About -> FAQ -> Contact
        const navMenu = document.querySelector('.nav-menu');
        if (navMenu) {
            if (user.role === 'admin' || isAdminOnly) {
                navMenu.innerHTML = '';
            } else {
                navMenu.innerHTML = `
                    <li><a href="${homeUrl}" class="nav-link${isHome ? ' active' : ''}">Home</a></li>
                    <li><a href="${dashboardUrl}" class="nav-link${isDashboard ? ' active' : ''}">Dashboard</a></li>
                    <li><a href="${eventsUrl}" class="nav-link${isEvents ? ' active' : ''}">Events</a></li>
                    <li><a href="${aboutUrl}" class="nav-link${isAbout ? ' active' : ''}">About</a></li>
                    <li><a href="${faqUrl}" class="nav-link${isFaq ? ' active' : ''}">FAQ</a></li>
                    <li><a href="${contactUrl}" class="nav-link${isContact ? ' active' : ''}">Contact</a></li>
                `;
            }
        }

        // B. Setup unified Profile Avatar Icon & Logout button in .nav-actions
        const navActions = document.querySelector('.nav-actions');
        if (navActions) {
            // Hide guest login/register buttons strictly
            navActions.querySelectorAll('.login-link, .btn-register, .mobile-only-auth, a[href*="login.html"], a[href*="register.html"]').forEach(el => {
                el.style.setProperty('display', 'none', 'important');
            });

            // Clear previously appended or duplicate elements
            navActions.querySelectorAll('.user-info, .logout-link, .auth-injected-user').forEach(el => el.remove());

            // Build Profile Avatar Widget
            const userInfo = document.createElement('a');
            userInfo.className = `user-info auth-injected-user${isProfile ? ' active-profile' : ''}`;
            if (user.role === 'admin') {
                userInfo.href = 'javascript:void(0)';
                userInfo.style.cursor = 'default';
                userInfo.title = 'Institutional Administrator';
            } else {
                userInfo.href = profileUrl;
                userInfo.title = 'View Student Profile';
            }
            userInfo.innerHTML = `
                <span class="user-avatar-badge">${user.initials || 'AD'}</span>
                <span class="user-name">${user.name}</span>
            `;
            navActions.appendChild(userInfo);

            // Build Logout Icon Button
            const logoutLink = document.createElement('a');
            logoutLink.className = 'logout-link auth-injected-user';
            logoutLink.href = logoutUrl;
            logoutLink.title = 'Logout';
            logoutLink.onclick = () => confirm('Log out of StudentHub?');
            logoutLink.innerHTML = `<span class="material-symbols-outlined">logout</span>`;
            navActions.appendChild(logoutLink);
        }

        // C. Update all text/badge references across pages
        document.querySelectorAll('.user-avatar-badge, .avatar-initials').forEach(el => {
            el.textContent = user.initials || 'ST';
        });
        document.querySelectorAll('.user-name, .profile-name, .student-name').forEach(el => {
            el.textContent = user.name;
        });
        document.querySelectorAll('.student-code-badge, .user-code').forEach(el => {
            el.textContent = user.student_code;
        });

        // D. Update promotional and footer event links to point to events.html
        document.querySelectorAll('.auth-event-link').forEach(link => {
            link.href = eventsUrl;
        });
    }

    // 6. Handle Guest User (Not Logged In)
    function handleGuest() {
        document.body.classList.remove('is-authenticated');

        const homeUrl = isInSubfolder ? '../index.html' : 'index.html';
        const aboutUrl = isInSubfolder ? 'about-us.html' : 'page/about-us.html';
        const faqUrl = isInSubfolder ? 'FAQ.html' : 'page/FAQ.html';
        const contactUrl = isInSubfolder ? 'contact.html' : 'page/contact.html';

        const isHome = currentPage.toLowerCase().endsWith('index.html') || currentPage === '';
        const isAbout = currentPage.toLowerCase().endsWith('about-us.html');
        const isFaq = currentPage.toLowerCase().endsWith('faq.html');
        const isContact = currentPage.toLowerCase().endsWith('contact.html');

        // Guests see ONLY public pages in exact order: Home -> About -> FAQ -> Contact
        const navMenu = document.querySelector('.nav-menu');
        if (navMenu) {
            navMenu.innerHTML = `
                <li><a href="${homeUrl}" class="nav-link${isHome ? ' active' : ''}">Home</a></li>
                <li><a href="${aboutUrl}" class="nav-link${isAbout ? ' active' : ''}">About</a></li>
                <li><a href="${faqUrl}" class="nav-link${isFaq ? ' active' : ''}">FAQ</a></li>
                <li><a href="${contactUrl}" class="nav-link${isContact ? ' active' : ''}">Contact</a></li>
            `;
        }

        // Clear user widgets and reveal login/register buttons in .nav-actions
        const navActions = document.querySelector('.nav-actions');
        if (navActions) {
            navActions.querySelectorAll('.user-info, .logout-link, .auth-injected-user').forEach(el => el.remove());
            navActions.querySelectorAll('.login-link, .btn-register, .mobile-only-auth').forEach(el => {
                el.style.removeProperty('display');
                el.style.display = '';
            });
        }

        // Route promotional event links to login for guests
        document.querySelectorAll('.auth-event-link').forEach(link => {
            link.href = pageBasePath + 'login.html?redirect=events.html';
        });

        // Intercept clicks on private pages (events, dashboard, profile) for guests
        document.querySelectorAll('a[href*="events.html"], a[href*="student-dashboard.html"], a[href*="student-profile.html"]').forEach(link => {
            if (!link.hasAttribute('data-auth-bound')) {
                link.setAttribute('data-auth-bound', 'true');
                link.addEventListener('click', (e) => {
                    if (!currentUser) {
                        e.preventDefault();
                        const href = link.getAttribute('href') || '';
                        const page = href.split('/').pop() || 'events.html';
                        const loginUrl = pageBasePath + 'login.html?error=auth_required&redirect=' + encodeURIComponent(page);
                        window.location.href = loginUrl;
                    }
                });
            }
        });
    }

    // 7. Page-Specific Enhancements for Authenticated User
    function applyPageSpecificData(user) {
        // Home page hero section: personalize for authenticated student
        if (currentPage.toLowerCase().endsWith('index.html') || currentPage === '') {
            const welcomeBadge = document.getElementById('hero-welcome-badge');
            if (welcomeBadge) {
                welcomeBadge.textContent = `Welcome Back, ${user.first_name || user.name}`;
            }
            const actionPrimary = document.getElementById('hero-action-primary');
            if (actionPrimary) {
                actionPrimary.href = 'page/student-dashboard.html';
                actionPrimary.innerHTML = '<span class="material-symbols-outlined">space_dashboard</span> Access Dashboard';
            }
            const actionSecondary = document.getElementById('hero-action-secondary');
            if (actionSecondary) {
                actionSecondary.href = 'page/events.html';
                actionSecondary.innerHTML = '<span class="material-symbols-outlined">event</span> Campus Events';
            }
        }

        // A. Events page: prefill student ID field in modal
        if (currentPage.toLowerCase().endsWith('events.html')) {
            const stuIdField = document.getElementById('student-unique-id-field') || document.getElementById('student-id');
            if (stuIdField) {
                stuIdField.value = user.student_code;
                stuIdField.readOnly = true;
                stuIdField.style.backgroundColor = 'var(--bg-color)';
                stuIdField.style.opacity = '0.9';
            }
        }

        // B. Contact page: prefill name and email
        if (currentPage.toLowerCase().endsWith('contact.html')) {
            const nameField = document.getElementById('contact-name') || document.querySelector('input[name="fullname"]');
            if (nameField && !nameField.value) {
                nameField.value = user.name;
            }
            const emailField = document.getElementById('contact-email') || document.querySelector('input[name="email"]');
            if (emailField && !emailField.value) {
                emailField.value = user.email;
            }
        }

        // C. Student profile page: populate form fields
        if (currentPage.toLowerCase().endsWith('student-profile.html')) {
            const fn = document.getElementById('fullname');
            const em = document.getElementById('email');
            const rn = document.getElementById('rollnumber');
            const ph = document.getElementById('phone');
            const bio = document.getElementById('bio');
            const h3 = document.querySelector('.profile-card h3');
            const sub = document.querySelector('.profile-subtitle');
            const av = document.querySelector('.profile-avatar-large');

            if (fn) fn.value = user.name;
            if (em) em.value = user.email;
            if (rn) rn.value = user.student_code + ' (' + (user.roll_number || 'Enrolled') + ')';
            if (ph && user.phone) ph.value = user.phone;
            if (bio && user.bio) bio.value = user.bio;
            if (h3) h3.textContent = user.name;
            if (sub) sub.textContent = user.department || 'Enrolled Student';
            if (av) av.textContent = user.initials || 'ST';
        }

        // D. Student dashboard page: update welcome banner
        if (currentPage.toLowerCase().endsWith('student-dashboard.html')) {
            const welcomeEm = document.querySelector('.dashboard-banner h1 em');
            if (welcomeEm) welcomeEm.textContent = user.name;
            const codeBadge = document.querySelector('.student-code-badge');
            if (codeBadge) codeBadge.textContent = user.student_code;
        }
    }

    // 8. Access Denied Overlay
    function showAccessDeniedScreen(reason) {
        const overlay = document.createElement('div');
        overlay.id = 'auth-guard-overlay';
        overlay.style.cssText = `
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 999999; display: flex; align-items: center; justify-content: center;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #fff; padding: 20px;
        `;
        overlay.innerHTML = `
            <div style="background: #1e293b; border: 1px solid rgba(255,255,255,0.12); border-radius: 20px; padding: 36px 32px; max-width: 440px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(37, 99, 235, 0.15); border: 1px solid rgba(37, 99, 235, 0.4); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #60a5fa;">
                    <span class="material-symbols-outlined" style="font-size: 32px;">lock</span>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 10px; color: #f8fafc;">Authentication Required</h2>
                <p style="font-size: 14px; line-height: 1.5; color: #94a3b8; margin-bottom: 24px;">This section is restricted to logged-in students. Redirecting to StudentHub Login...</p>
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; color: #60a5fa;">
                    <span style="display:inline-block; width: 14px; height: 14px; border: 2px solid #60a5fa; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
                    <span>Redirecting...</span>
                </div>
            </div>
            <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
        `;
        document.documentElement.appendChild(overlay);
    }

    // 9. Idle Timeout Monitor (polls every 2 minutes)
    function startIdleWatcher() {
        setInterval(async () => {
            try {
                const res = await fetch(phpBasePath + 'auth_status.php?t=' + Date.now(), {
                    credentials: 'include',
                    cache: 'no-store'
                });
                if (res.ok) {
                    const data = await res.json();
                    if (!data.authenticated || data.timeout) {
                        alert('Your session has expired due to inactivity. Redirecting to login.');
                        window.location.replace(pageBasePath + 'login.html?timeout=1');
                    }
                }
            } catch (e) {
                // Ignore transient network errors
            }
        }, 120000);
    }

    // 10. Login Page URL Message Helpers
    function setupLoginPageMessages() {
        if (!isLoginPage) return;
        const urlParams = new URLSearchParams(window.location.search);
        const cardHeader = document.querySelector('.auth-header');
        if (!cardHeader) return;

        let bannerType = '';
        let bannerIcon = '';
        let bannerText = '';

        if (urlParams.get('msg') === 'logged_out') {
            bannerType = 'info';
            bannerIcon = 'check_circle';
            bannerText = 'You have been securely logged out of StudentHub.';
        } else if (urlParams.get('timeout') === '1' || urlParams.get('error') === 'session_timeout') {
            bannerType = 'warning';
            bannerIcon = 'timer';
            bannerText = 'Your session timed out due to 30 minutes of inactivity. Please log in again.';
        } else if (urlParams.get('error') === 'auth_required') {
            bannerType = 'warning';
            bannerIcon = 'lock';
            bannerText = 'Protected Page: Please log in with your credentials to access this portal section.';
        } else if (urlParams.get('error') === 'forbidden') {
            bannerType = 'error';
            bannerIcon = 'gpp_bad';
            bannerText = 'Access Denied: You do not have the required role permissions for that page.';
        } else if (urlParams.has('registered')) {
            const code = urlParams.get('code') || '';
            bannerType = 'success';
            bannerIcon = 'verified';
            bannerText = `Registration successful! Your Student ID is <strong>${code}</strong>. You can now log in below.`;
        } else if (urlParams.has('error')) {
            bannerType = 'error';
            bannerIcon = 'error';
            bannerText = decodeURIComponent(urlParams.get('error'));
        }

        if (bannerText) {
            const banner = document.createElement('div');
            banner.className = `auth-alert-banner alert-${bannerType}`;
            banner.style.cssText = `
                display: flex; align-items: center; gap: 12px; padding: 12px 16px; margin: 16px 0 20px;
                border-radius: 12px; font-size: 13.5px; line-height: 1.4;
                background-color: ${bannerType === 'success' ? '#ecfdf5' : bannerType === 'warning' ? '#fffbeb' : bannerType === 'error' ? '#fef2f2' : '#eff6ff'};
                border: 1px solid ${bannerType === 'success' ? '#a7f3d0' : bannerType === 'warning' ? '#fde68a' : bannerType === 'error' ? '#fecaca' : '#bfdbfe'};
                color: ${bannerType === 'success' ? '#065f46' : bannerType === 'warning' ? '#92400e' : bannerType === 'error' ? '#991b1b' : '#1e40af'};
            `;
            banner.innerHTML = `
                <span class="material-symbols-outlined" style="font-size: 20px; flex-shrink: 0;">${bannerIcon}</span>
                <span style="flex: 1;">${bannerText}</span>
                <button type="button" style="background:none;border:none;cursor:pointer;color:inherit;font-size:18px;padding:0;line-height:1;" onclick="this.parentElement.remove();">&times;</button>
            `;
            cardHeader.after(banner);
        }

        const emailOrCode = urlParams.get('email') || urlParams.get('code') || urlParams.get('login_id');
        if (emailOrCode) {
            const emailInput = document.getElementById('email') || document.querySelector('input[name="login_id"]');
            if (emailInput) emailInput.value = emailOrCode;
        }
    }

    // Initialize
    checkAuthStatus();
    onDOMReady(setupLoginPageMessages);

    // Global helper
    window.StudentHubAuth = {
        checkAuthStatus,
        getCurrentUser: () => currentUser,
        logout: () => { window.location.href = phpBasePath + 'logout.php'; }
    };
})();
