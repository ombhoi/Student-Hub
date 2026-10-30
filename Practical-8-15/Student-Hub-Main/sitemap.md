# StudentHub — Site Map & User Flow

> **Project phase:** Phase 3 (HTML + CSS complete)  
> **Purpose of this document:** Map every page and show how users move through the site.

---

## 1. Overview

StudentHub is a college portal with three user areas:

| Area | Who | Entry point |
|------|-----|-------------|
| **Public site** | Visitors & students (not logged in) | `index.html` |
| **Student portal** | Logged-in students | `page/student-dashboard.html` |
| **Admin console** | Staff / administrators | `admin/admin.html` |

---

## 2. Complete Page Map

```
Student-Hub-Test/
├── index.html                      # Home (landing page)
├── page/
│   ├── about-us.html               # About the portal / institution
│   ├── contact.html                # Contact form & support info
│   ├── events.html                 # Campus events listing
│   ├── FAQ.html                    # Frequently asked questions
│   ├── feed-back.html              # Feedback form
│   ├── login.html                  # Student login
│   ├── register.html               # New student registration
│   ├── student-dashboard.html      # Student home (after login)
│   └── student-profile.html        # Student profile & settings
└── admin/
    └── admin.html                  # Admin dashboard (separate area)
```

---

## 3. High-Level User Flow

```mermaid
flowchart TD
    HOME[index.html<br/>Home]

    subgraph PUBLIC["Public pages (no login required)"]
        ABOUT[about-us.html]
        CONTACT[contact.html]
        EVENTS[events.html]
        FAQ[FAQ.html]
        FEEDBACK[feed-back.html]
        LOGIN[login.html]
        REGISTER[register.html]
    end

    subgraph STUDENT["Student area (login required — future)"]
        DASH[student-dashboard.html]
        PROFILE[student-profile.html]
    end

    subgraph ADMIN["Admin area (admin login — future)"]
        ADMINPAGE[admin/admin.html]
    end

    HOME --> ABOUT
    HOME --> CONTACT
    HOME --> EVENTS
    HOME --> FAQ
    HOME --> FEEDBACK
    HOME --> LOGIN
    HOME --> REGISTER

    REGISTER -->|"Success → redirect"| LOGIN
    LOGIN -->|"Valid credentials (future)"| DASH
    DASH --> PROFILE
    DASH --> EVENTS
    DASH --> FEEDBACK
    PROFILE --> DASH

    DASH -->|"Logout (future)"| LOGIN
    PROFILE -->|"Logout (future)"| LOGIN

    LOGIN -.->|"Admin credentials (future)"| ADMINPAGE
```

---

*Last updated: Phase 3 — HTML/CSS complete. Aligns with `Drawio/Sitemap-True.drawio` user flow diagram.*
