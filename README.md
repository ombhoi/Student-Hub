# StudentHub - Academic & Campus Operations Web Portal

This repository contains the complete coursework practical implementations for the **StudentHub** academic portal.

---

## 📁 Repository Structure

```
Student-Hub/
├── Practical-1-7/
│   └── Student-Hub-Main/      # Front-End Architecture, Semantic HTML5, CSS3 & Wireframes
└── Practical-8-15/
    └── Student-Hub-Main/      # Full-Stack Web Application (PHP, MySQL, RBAC, Sessions, CRUD & Security)
```

---

## 📌 Modules Overview

### 1. `Practical-1-7/Student-Hub-Main/`
- **Focus**: Semantic HTML5 wireframes, multi-page layout design, modular CSS, form design, site navigation, and accessibility.
- **Pages**:
  - `index.html` (Campus Portal Home)
  - `page/events.html`, `page/about-us.html`, `page/contact.html`, `page/FAQ.html`, `page/feed-back.html`
  - `page/login.html`, `page/register.html`, `page/student-dashboard.html`, `page/student-profile.html`
  - `admin/admin.html` (Administrative wireframe)
  - `Drawio/` & `draw-img/` (Visual diagrams & architectural wireframes)

---

### 2. `Practical-8-15/Student-Hub-Main/`
- **Focus**: Complete dynamic full-stack implementation with PHP & MySQL database connectivity, user sessions, role-based access control, file & image uploads, and security audit logging.
- **Core Capabilities**:
  - **Practical 8–9**: Server-side processing, input validation, and flat-file data persistence.
  - **Practical 10**: Client-side authentication guard (`auth_guard.js`) with idle session timeout protection.
  - **Practical 11**: MySQL Student Records Management CRUD with prepared statements (`student_crud.php`).
  - **Practical 12–13**: University Event Accreditation & Moderation CRUD with Fetch API & file upload (`event_crud.php`, `admin_event_crud.js`).
  - **Practical 14**: Institutional Security & Audit Ledger (`audit_logger.php`), live statistics analytics dashboard, and real-time security notification popup.
  - **Practical 15**: Comprehensive role-based access control (RBAC) isolating Student vs. Admin privileges end-to-end.

---

## 🚀 Setup & Execution (Practical-8-15)

1. Place the `Practical-8-15/Student-Hub-Main` directory in your local Apache web server root (`htdocs/`).
2. Start **Apache** and **MySQL** in XAMPP.
3. Import the SQL table definitions from `sql/` into your database:
   - `sql/student.sql`
   - `sql/events.sql`
   - `sql/registration.sql`
   - `sql/audit_logs.sql`
   - `sql/contact_inquiries.sql`
4. Access the web application:
   - **Student Portal**: `http://localhost/Practical/Practical-8-15/Student-Hub-Main/index.html`
   - **Admin Console**: `http://localhost/Practical/Practical-8-15/Student-Hub-Main/admin/admin.html`
