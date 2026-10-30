# StudentHub - Academic & Campus Operations Web Portal

This repository contains the complete coursework practical implementations for the **StudentHub** academic web application developed as part of **Web Development Frameworks (ITUE203)**.

---

## 📁 Repository Structure

```
Student-Hub/
├── .gitignore
├── README.md                          # Master coursework documentation & sitemap
├── Practical-1-7/
│   └── Student-Hub-Main/              # Practical 1 to Practical 7 Implementation
└── Practical-8-15/
    └── Student-Hub-Main/              # Practical 8 to Practical 15 Implementation
```

---

## 📌 Coursework Practicals Breakdown

### 1. `Practical-1-7/Student-Hub-Main/`
This module encapsulates **Practicals 1 through 7**, focusing on foundational front-end web architecture, accessibility-ready semantic HTML5 structure, responsive layout styling, client-side interactivity, form validation, external JSON consumption, and early server-side file handling:

- **Practical 1: Project Initiation, Requirement Analysis, Sitemap, Wireframe, and GitHub Setup**
  - Scope definition, user role modeling, and multi-page navigation planning.
  - Architectural sitemap (`sitemap.md`, `.drawio`), low-fidelity wireframes (`draw-img/`, `Drawio/`), and folder structuring.
- **Practical 2: Semantic HTML5 Pages with Accessibility-Ready Structure**
  - Development of semantic HTML5 skeletons across 10+ pages: `index.html`, `page/about-us.html`, `page/contact.html`, `page/events.html`, `page/FAQ.html`, `page/feed-back.html`, `page/login.html`, `page/register.html`, `page/student-dashboard.html`, `page/student-profile.html`, and `admin/admin.html`.
  - Accessible landmarks (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<aside>`, `<footer>`), ARIA attributes, and accessible label linkages.
- **Practical 3: Responsive UI Design using CSS2.0/CSS3.0, CSS Grid, and Flexbox**
  - Responsive, mobile-first layouts adapting cleanly across desktop, tablet, and mobile viewports.
  - Multi-column squircle cards, fluid grids, and flexbox containers without relying on heavy external UI frameworks.
- **Practical 4: JavaScript DOM Manipulation, Event Handling, and UI Interactivity**
  - Interactive components: collapsible FAQ accordions, modal dialog popups, dynamic image banners, mobile hamburger drawer, and persistent light/dark theme switcher using `localStorage`.
- **Practical 5: Registration Form with Frontend Validation and User-Friendly Error Handling**
  - Client-side validation using Regular Expressions for First Name, Last Name, Email, 10-digit Phone, Password strength rules, Department selection, and Terms acceptance with inline error messaging.
- **Practical 6: Rendering External JSON Data using Fetch API, Search, and Filter**
  - Asynchronous fetching of external data (`data/events.json`, `data/students.json`, `data/faqs.json`) via `fetch()` API.
  - Real-time client-side search filtering, category sorting, and dynamic card rendering.
- **Practical 7: PHP Form Processing with Server-Side Validation and CSV/JSON File Storage**
  - Server-side form data processing via HTTP POST (`php/process_contact.php`, `php/process_registration_file.php`).
  - Input sanitization, validation, and flat-file persistence in CSV and JSON formats (`data/contact.csv`, `data/registrations.csv`, `data/students.json`).

---

### 2. `Practical-8-15/Student-Hub-Main/`
This module encapsulates **Practicals 8 through 15**, expanding StudentHub into a complete dynamic full-stack enterprise web application with relational MySQL database integration, secure authentication, session management, role-based access control (RBAC), multi-entity CRUD operations, poster image uploads, security audit logging, and asynchronous Fetch APIs:

- **Practical 8: MySQL Schema Design, ER Model, and Database Connectivity**
  - Relational schema design with normalized tables (`students`, `events`, `registrations`, `audit_logs`, `contact_inquiries`) in `sql/`.
  - Centralized, resilient database connection handler (`php/db.php`) using prepared MySQLi connections with ACID transaction support.
- **Practical 9: Secure User Registration with Database Insert, Duplicate Email Check, and Password Hashing**
  - Dual-tier frontend & backend validation ensuring clean inputs.
  - Duplicate email verification and secure cryptographic password hashing using PHP's `password_hash()` with `PASSWORD_DEFAULT`.
- **Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token**
  - Complete session management engine (`php/session.php`, `php/login.php`, `php/logout.php`).
  - Session fixation prevention via `session_regenerate_id(true)`.
  - Client-side strict authentication guard (`js/auth_guard.js`) with automatic 15-minute idle session timeout and unauthorized access prevention.
- **Practical 11: Student Management CRUD with Search, Filter, and MySQL Statements**
  - Complete administrative CRUD interface (`php/student_crud.php`, `js/admin_crud.js`).
  - Search by student name/code/email, department filtering, pagination, modal record editing, password provisioning, 10-digit phone enforcement, and confirmed deletions.
- **Practical 12: Event Management CRUD with Poster Upload and File Validation**
  - Comprehensive campus event moderation suite (`php/event_crud.php`, `js/admin_event_crud.js`).
  - File upload engine validating MIME types (JPEG/PNG/WEBP) and file size limits, saving into `uploads/events/` with safe file renaming.
- **Practical 13: PHP JSON API and AJAX/Fetch-Based CRUD without Page Reload**
  - Dedicated RESTful JSON endpoints (`php/api_db_events.php`, `php/event_crud.php?action=list`).
  - Asynchronous client-side consumption via `fetch()`, dynamic DOM rendering, and instant modal updates without whole-page reloading.
- **Practical 14: Admin Dashboard with Role-Based Access Control, Dynamic Menu, and Security Audit Log**
  - Aggregated administrative intelligence console (`php/admin_dashboard.php`, `js/admin_dashboard.js`).
  - Live system metric counters (Total Students, Active Events, Registrations, Security Logs).
  - High-integrity security audit ledger (`php/audit_logger.php`) capturing admin mutations, authentication events, and client IP addresses.
  - Interactive top navbar notification bell popup with live badge count and instant quick-access security event previews.
- **Practical 15: Final Integration, Testing, Documentation, GitHub Submission, and Local Deployment**
  - Unified system integration ensuring smooth end-to-end user journeys for both **Students** and **Administrators**.
  - Strict Role-Based Access Control (RBAC) isolating Student views from the Administrative Control Console.
  - Complete documentation, SQL seed dumps, clean repository structure, and verified local deployment on Apache/XAMPP.

---

## 🚀 Setup & Local Deployment Guide

To run the full-stack application (**Practical-8-15**):

1. **Place in Apache Web Root**:
   Clone or copy the `Practical-8-15/Student-Hub-Main` directory to your local XAMPP web root:
   ```
   C:/xampp/htdocs/Practical/Practical-8-15/Student-Hub-Main/
   ```
2. **Start Services**:
   Launch **Apache** and **MySQL** from the XAMPP Control Panel.
3. **Import MySQL Database**:
   Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) or MySQL CLI, create database `studenthub_db`, and import the schema files located in `Practical-8-15/Student-Hub-Main/sql/`:
   ```bash
   mysql -u root -p studenthub_db < Practical-8-15/Student-Hub-Main/sql/student.sql
   mysql -u root -p studenthub_db < Practical-8-15/Student-Hub-Main/sql/events.sql
   mysql -u root -p studenthub_db < Practical-8-15/Student-Hub-Main/sql/registration.sql
   mysql -u root -p studenthub_db < Practical-8-15/Student-Hub-Main/sql/audit_logs.sql
   mysql -u root -p studenthub_db < Practical-8-15/Student-Hub-Main/sql/contact_inquiries.sql
   ```
4. **Access Portals in Browser**:
   - **Student Public Portal**: `http://localhost/Practical/Practical-8-15/Student-Hub-Main/index.html`
   - **Administrative Console**: `http://localhost/Practical/Practical-8-15/Student-Hub-Main/admin/admin.html`
