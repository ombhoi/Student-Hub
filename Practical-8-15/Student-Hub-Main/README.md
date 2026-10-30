# StudentHub — Campus Management & Event Booking Portal
**Charotar University of Science and Technology (CHARUSAT)**  
**Faculty of Technology and Engineering (FTE) — Department of Information Technology**  
**Course:** Web Development Frameworks (`ITUE203`) | **Semester:** 3rd, 2026–27  
**Milestone:** **Practical 15 — Final Integration, Testing, Documentation, and Local Deployment**

---

## 🌟 Executive Summary

**StudentHub** is a comprehensive, production-ready full-stack academic web portal that unifies campus event management, student communication, secure role-based access control (RBAC), and administrative governance. 

The application successfully integrates the entire semester curriculum (**Practicals 1 through 15**) into a unified, secure, accessible, responsive, and database-backed web application deployed on **Apache/MySQL (XAMPP)**.

---

## 👥 Two Distinct User Roles & RBAC Architecture

The platform strictly differentiates and isolates functionality between two distinct roles:

| Feature / Permission | Student Role (`student`) | Administrator Role (`admin`) | Unauthenticated Guest |
| :--- | :---: | :---: | :---: |
| **Public Portal & Information** |  Full Access |  Full Access |  Full Access (Home, About, FAQ, Contact) |
| **Registration / Account Creation** |  Self-Register (`page/register.html`) |  Managed via DB Seed / Admin Console |  Public Access |
| **Authentication & Login** |  Via Email or `STU-xxx` |  Via Email or `ADM-001` |  Redirected to Login on protected routes |
| **Default Post-Login Route** | `page/student-dashboard.html` | `admin/admin.html` | N/A |
| **Campus Event Booking** |  Register for events & generate ticket | ❌ Event booking restricted to students | ❌ Must log in first |
| **My Registrations & Tickets** |  View confirmed bookings & ticket codes |  View all student registrations in real-time | ❌ Hidden |
| **Student Profile Management** |  View and update personal profile | ❌ Admin profile isolated | ❌ Hidden |
| **Student Management CRUD** | ❌ Forbidden (HTTP 403) |  Full CRUD (Add, Edit, View, Delete, CSV Export) | ❌ Forbidden (HTTP 401) |
| **Event Moderation CRUD** | ❌ Forbidden (HTTP 403) |  Full CRUD + Poster File Upload + Status Toggle | ❌ Forbidden (HTTP 401) |
| **Administrative Analytics** | ❌ Forbidden (HTTP 403) |  Real-time KPI aggregation & Category breakdown | ❌ Forbidden (HTTP 401) |
| **Security Audit Logs** | ❌ Forbidden (HTTP 403) |  Live tamper-evident audit ledger | ❌ Forbidden (HTTP 401) |
| **Strict Route Isolation** | Cannot view `admin/admin.html` | Cannot view student public routes | Instant hide (`body { display: none !important; }`) |

---

## 🔑 Default Test Credentials

| Role | Username / Identifier | Academic Email | Password | Assigned Landing Page |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `ADM-001` | `admin@charusat.edu.in` | `password123` | `admin/admin.html` |
| **Student #1** | `STU-001` | `alex.ce23@charusat.edu.in` | `password123` | `page/student-dashboard.html` |
| **Student #2** | `STU-002` | `marcus.vance@charusat.edu.in` | `password123` | `page/student-dashboard.html` |
| **New Registered Student** | Generated `STU-xxx` | Inputted at registration | User-chosen password | `page/student-dashboard.html` |

---

## 📋 Comprehensive Practical 1–15 Syllabus Mapping

| Practical | Academic Title | Implemented Modules & Core Technical Artifacts |
| :---: | :--- | :--- |
| **1** | Project Initiation, Sitemap, Wireframe & GitHub Setup | `sitemap.md`, `project-folder-structure.md`, low-fidelity wireframes in `Drawio/`, and Git repository structure. |
| **2** | Semantic HTML5 Pages with Accessibility Structure | 10+ semantic pages (`index.html`, `about-us.html`, `contact.html`, `events.html`, `FAQ.html`, `feed-back.html`, `login.html`, `register.html`, `student-dashboard.html`, `student-profile.html`, `admin.html`). Form labels, landmark roles, and image `alt` tags. |
| **3** | Responsive UI Design using CSS Grid & Flexbox | 11 modular stylesheets in `css/` (`home.css`, `about.css`, `admin.css`, etc.) with mobile-first breakpoints (`1024px`, `960px`, `768px`, `640px`, `480px`, `360px`). Zero horizontal scrollbars. |
| **4** | JavaScript DOM Manipulation & Interactivity | Collapsible FAQ accordions, light/dark theme switcher persisted in `localStorage`, event registration modals, and toast notifications. |
| **5** | Registration Form with Frontend Validation | `js/validation.js`: Regex validation for name, email, phone, roll number; dynamic password strength meter; and custom HTML5 Canvas CAPTCHA. |
| **6** | Rendering External JSON Data using Fetch API | `data/events.json`, `data/students.json`, `data/faqs.json`. Dynamic rendering with search, category filtering, and sorting. |
| **7** | PHP Form Processing & Server-Side Validation | Server-side sanitization, dual file storage in CSV and JSON (`data/registrations.csv`, `data/registrations.json`) in `php/previous/`. |
| **8** | MySQL Schema Design, ER Model & DB Connectivity | `sql/student.sql`, `sql/events.sql`, `sql/registration.sql`, `sql/contact_inquiries.sql`, `sql/audit_logs.sql`. Relational architecture with foreign keys, indexes, and `php/db.php` supporting both PDO and MySQLi prepared statements. |
| **9** | Secure User Registration with Password Hashing | `php/process_registration_db.php`: Server-side duplicate email check, sequential `STU-xxx` student code generation, and Bcrypt `password_hash()`. |
| **10** | Secure Login/Logout, RBAC, Timeout & Remember-Me | `php/login.php`, `php/logout.php`, `php/session.php`: `password_verify()`, `session_regenerate_id()`, 30-minute inactivity timeout, SHA256 remember-me cookies, and role routing. |
| **11** | Student Management CRUD with Search & Filter | `php/student_crud.php` & `js/admin_crud.js`: Administrative student table with real-time search debounce, department filtering, pagination, and CSV export. |
| **12** | Event Management CRUD with Poster Upload | `php/event_crud.php` & `js/admin_event_crud.js`: Event CRUD with drag-and-drop poster upload, MIME type checking, 5MB size limit, random renaming, and directory protection. |
| **13** | PHP JSON API & AJAX/Fetch-Based CRUD | Asynchronous API endpoints returning standard JSON responses (`{ success: true, ... }`) consumed via `fetch()` without full-page reloads. |
| **14** | Admin Dashboard with RBAC, Dynamic Menu & Audit Log | `php/admin_dashboard.php` & `js/admin_dashboard.js`: Live SQL aggregated counts, dynamic navigation menu, and persistent security audit ledger (`audit_logs`). |
| **15** | Final Integration, Testing, Documentation & Deployment | Complete end-to-end integration across all 15 practicals, comprehensive test suites, SQL schemas, and local XAMPP deployment. |

---

## 🚀 Local Deployment Guide (XAMPP)

### Prerequisites
* **XAMPP** (Apache 2.4+ and MySQL / MariaDB 10.4+)
* Web Browser (Google Chrome, Firefox, or Microsoft Edge)

### Step 1: Place Repository in `htdocs`
Clone or copy the project into your local XAMPP `htdocs` directory:
```text
C:\xampp\htdocs\Practical\Practical-8-15\Student-Hub-Main\
-- OR --
D:\XAMPP\htdocs\Practical\Practical-8-15\Student-Hub-Main\
```

### Step 2: Start Apache and MySQL
1. Launch the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Import Database Schema & Seed Data
1. Open your browser and navigate to **phpMyAdmin**:  
   `http://localhost/phpmyadmin/`
2. Create a new database named `studenthub_db` (Collation: `utf8mb4_unicode_ci`).
3. Click on the `studenthub_db` database and import the SQL files located in the `sql/` folder in the following order:
   1. `sql/student.sql` (Creates `students` table and 22 seeded accounts including admin)
   2. `sql/events.sql` (Creates `events` table and 16 campus events)
   3. `sql/registration.sql` (Creates `registrations` junction table and view)
   4. `sql/contact_inquiries.sql` (Creates `contact_inquiries` table)
   5. `sql/audit_logs.sql` (Creates `audit_logs` table and baseline audit events)
   
*Alternatively, you can visit the built-in diagnostic and schema installer at:*  
`http://localhost/Practical/Practical-8-15/Student-Hub-Main/php/test_connection.php` and click **Import All Schemas**.

### Step 4: Access the Application
Open your browser and navigate to:  
`http://localhost/Practical/Practical-8-15/Student-Hub-Main/`

---

## 🔒 Security Architecture Highlights

1. **SQL Injection Prevention:** 100% of SQL queries across all backend PHP endpoints use prepared statements with parameterized inputs (both PDO and MySQLi).
2. **Password Security:** Passwords are never stored in plain text. Stored with PHP standard `PASSWORD_BCRYPT` through `password_hash()` and authenticated via `password_verify()`.
3. **Session Fixation & Cookie Safeguards:**
   - `session_regenerate_id(true)` invoked immediately upon successful credential validation.
   - Session cookies configured with `HttpOnly = true`, `SameSite = Lax`, and strict lifetime.
   - 30-minute idle inactivity timeout automatically destroys expired sessions.
4. **Instant Client-Side Guarding:** Protected pages (`events.html`, `student-dashboard.html`, `student-profile.html`, `admin.html`) inject an immediate hiding style (`body { display: none !important; }`) before DOM parsing to guarantee **zero flash of unauthenticated content** before server verification finishes.
5. **Directory Traversal & Upload Security:**
   - File uploads validated on the server for MIME type (`image/jpeg`, `image/png`, `image/webp`) and size (<= 5MB).
   - Files are renamed using 32-character random hex tokens.
   - `uploads/` directory contains an `.htaccess` file disabling directory browsing (`Options -Indexes`).
6. **Institutional Audit Trail:** All mutations (admin logins, event creation, student updates, deletions) generate tamper-evident audit records in the `audit_logs` table with actor role, IP address, and timestamp.

---

## 📁 Repository Directory Structure

```text
Student-Hub-Main/
├── admin/
│   └── admin.html                      # Administrative Control Console
├── css/
│   ├── about.css                       # About Page Stylesheet
│   ├── admin.css                       # Admin Console Stylesheet
│   ├── contact.css                     # Contact Page Stylesheet
│   ├── dashboard.css                   # Student Dashboard Stylesheet
│   ├── events.css                      # Campus Events Stylesheet
│   ├── faq.css                         # FAQ Accordion Stylesheet
│   ├── feedback.css                    # Feedback Page Stylesheet
│   ├── home.css                        # Homepage Stylesheet
│   ├── login.css                       # Login Authentication Stylesheet
│   ├── profile.css                     # Student Profile Stylesheet
│   └── register.css                    # Student Registration Stylesheet
├── data/
│   ├── events.json                     # JSON Campus Events Dataset (P6)
│   ├── faqs.json                       # JSON FAQ Dataset (P6)
│   ├── students.json                   # JSON Students Dataset (P6)
│   ├── registrations.csv               # CSV File-Based Storage (P7)
│   └── registrations.json              # JSON File-Based Storage (P7)
├── Drawio/                             # Software Engineering Wireframes & Sitemap
├── image/                              # System graphics, event posters, and branding
├── js/
│   ├── admin_crud.js                   # Practical 11: Student CRUD AJAX Controller
│   ├── admin_dashboard.js              # Practical 14: Analytics & Audit Log Controller
│   ├── admin_event_crud.js             # Practical 12 & 13: Event CRUD & File Upload Controller
│   ├── auth_guard.js                   # Practical 10: Client Authentication Guard & Dynamic Menu
│   ├── script.js                       # Practical 4 & 6: DOM Events, Dark Theme, JSON Render
│   └── validation.js                   # Practical 5: Form Validation, Strength & CAPTCHA
├── page/
│   ├── about-us.html                   # About Campus & University Information
│   ├── contact.html                    # Contact Inquiries & Department Locator
│   ├── events.html                     # Campus Event Catalog & Booking System
│   ├── FAQ.html                        # Interactive Accordion FAQ
│   ├── feed-back.html                  # Student Coursework & Event Feedback Form
│   ├── login.html                      # Single Sign-On / Authentication Portal
│   ├── register.html                   # Student Registration with Validation & CAPTCHA
│   ├── student-dashboard.html          # Authenticated Student Overview Dashboard
│   └── student-profile.html            # Authenticated Student Profile Management
├── php/
│   ├── admin_dashboard.php             # Practical 14: Administrative Aggregated KPIs API
│   ├── api_db_events.php               # Practical 8: Read-Only Student Events API
│   ├── audit_logger.php                # Practical 14: Institutional Security Audit Helper
│   ├── auth_status.php                 # Practical 10: Session State & Profile API
│   ├── db.php                          # Practical 8: Centralized PDO & MySQLi DB Connection
│   ├── event_crud.php                  # Practical 12 & 13: Campus Event CRUD API
│   ├── index.php                       # Root PHP Redirect to index.html
│   ├── login.php                       # Practical 10: Login & Role-Based Redirection
│   ├── logout.php                      # Practical 10: Session Destruction & Token Cleanup
│   ├── process_contact_db.php          # Contact Inquiries Database Handler
│   ├── process_event_registration_db.php # Event Booking & Ticket Generation Handler
│   ├── process_registration_db.php     # Practical 9: User Registration Handler
│   ├── session.php                     # Centralized Session & RBAC Enforcement Module
│   ├── student_crud.php                # Practical 11: Student Management CRUD API
│   └── test_connection.php            # Practical 8: Database Diagnostic & Installer Tool
├── sql/
│   ├── audit_logs.sql                  # Security Audit Logs Schema & Seed Data
│   ├── contact_inquiries.sql           # Contact Form Inquiries Schema
│   ├── events.sql                      # Campus Events Schema & Seed Data
│   ├── registration.sql                # Event Registrations Junction Schema & Views
│   └── student.sql                     # Students & Users Table with Seed Data
├── uploads/
│   ├── .htaccess                       # Security: Directory listing prevention
│   └── posters/                        # Uploaded event poster image repository
├── CODEBASE_AUDIT_AND_CLEANUP_REPORT.md# Technical Audit & Architecture Review
├── ER_DIAGRAM.md                       # Entity Relationship Model & Schemas
├── IMPLEMENTATION_PLAN_PRACTICAL_1_TO_8.md # Practical 1-8 Architectural Specifications
├── index.html                          # StudentHub Main Public Landing Page
└── README.md                           # Master Project Documentation (This File)
```

---

## 🧪 Verification & Acceptance Test Suite

To verify that Practical 15 is 100% compliant, execute this end-to-end test sequence:

### Test Case 1: Unauthenticated Guest Route Protection
1. Open a new Incognito / Private window.
2. Directly navigate to `http://localhost/Practical/Practical-8-15/Student-Hub-Main/admin/admin.html`.
3. **Expected Result:** Content is never displayed; browser is instantly redirected to `page/login.html?error=auth_required&redirect=admin.html`.
4. Directly navigate to `http://localhost/Practical/Practical-8-15/Student-Hub-Main/page/student-dashboard.html`.
5. **Expected Result:** Content is never displayed; browser is instantly redirected to `page/login.html?error=auth_required`.

### Test Case 2: Student Authentication & Event Booking Workflow
1. Navigate to `page/login.html`.
2. Login with student credentials: `alex.ce23@charusat.edu.in` / `password123`.
3. **Expected Result:**
   - Redirected to `page/student-dashboard.html`.
   - Navbar dynamically transforms: displays "Home", "Dashboard", "Events", "About", "FAQ", "Contact", Student initials badge ("AJ Alex Johnson"), and Logout icon.
   - Login and Register buttons are completely hidden.
4. Navigate to `page/events.html`.
5. Select an event (e.g., "Full-Stack Web Development Workshop") and click **Register Now**.
6. **Expected Result:**
   - Registration modal automatically pre-fills Alex's details (`STU-001`, `Alex Johnson`, `alex.ce23@charusat.edu.in`).
   - Confirming the registration triggers asynchronous submission to `php/process_event_registration_db.php`.
   - Success toast appears, button turns into a green **Registered** state with checkmark, and a unique ticket code (`TKT-...`) is generated.
7. Attempting to click **Registered** again notifies that the event is already confirmed (duplicate prevention).

### Test Case 3: Administrator Role Isolation & Management Console
1. Click the **Logout** icon to destroy the student session.
2. Navigate to `page/login.html` and sign in with admin credentials: `admin@charusat.edu.in` / `password123`.
3. **Expected Result:**
   - Role-Based Access Control routes admin directly to `admin/admin.html`.
   - Admin navbar displays "AD Admin User" and Logout icon. Public student navigation is replaced with administrative control links.
   - Live Dashboard loads: Active Students count (22), Campus Events count (16), Total Registrations, and Category Distribution progress bars.
   - Real-time Security Audit Log displays recent login and event actions.
4. In **Student Records Management**:
   - Live search filters students by name, email, or department with zero page reload.
   - Click **Add Student**: Form validates and creates student record via MySQLi prepared statement.
   - Click **Export CSV**: Downloads clean CSV file.
5. In **Campus Event Moderation**:
   - Event cards render with status tags (Upcoming, Ongoing, Completed, Cancelled).
   - Click **Create Event**: Drag and drop poster image, fill details, and submit. File validation confirms image type and creates record.
6. Open a new tab and try to visit `page/student-dashboard.html` while logged in as admin:
   - **Expected Result:** `auth_guard.js` isolates the administrator to the administrative console and redirects back to `admin/admin.html`.

---

## 🎓 Academic Attribution & Coursework Sign-Off

* **Institution:** Charotar University of Science and Technology (CHARUSAT)
* **Faculty:** Faculty of Technology and Engineering (FTE)
* **Department:** Department of Information Technology
* **Subject:** Web Development Frameworks (`ITUE203`)
* **Semester:** 3rd Semester, Academic Year 2026–27
* **Practicals Covered:** Practical 1 through Practical 15 (Complete Semester Portfolio)