# 📁 Student Hub – Project Folder Structure

> A complete overview of every folder and file in the **Student Hub** project.

---

```
Student-Hub-One/
│
├── index.html                          # 🏠 Homepage – Main entry point of the website
├── README.md                           # 📄 Project documentation / description
├── sitemap.md                          # 🗺️ Sitemap documentation in markdown
│
├── admin/                              # 🔐 Admin Panel
│   └── admin.html                      #    Admin dashboard page
│
├── page/                               # 📑 All public-facing pages
│   ├── about-us.html                   #    About Us page
│   ├── contact.html                    #    Contact Us page
│   ├── events.html                     #    Events listing page
│   ├── FAQ.html                        #    Frequently Asked Questions page
│   ├── feed-back.html                  #    Feedback / review submission page
│   ├── login.html                      #    User login page
│   ├── register.html                   #    User registration page
│   ├── student-dashboard.html          #    Student dashboard (after login)
│   └── student-profile.html            #    Student profile page
│
├── css/                                # 🎨 Stylesheets
│   └── style.css                       #    Main CSS file for the entire site
│
├── js/                                 # ⚙️ JavaScript files
│   └── (empty – scripts to be added)   #    Placeholder for future JS logic
│
├── image/                              # 🖼️ Website images & logos
│   ├── sitemap-Fi.jpg                  #    Sitemap figure / diagram image
│   ├── st-h.jpg                        #    Student Hub thumbnail
│   ├── stu-hub.webp                    #    Student Hub icon (webp)
│   └── studenthub-logo.webp           #    Student Hub logo (webp)
│
├── draw-img/                           # 📐 Wireframe screenshots (exported from Draw.io)
│   ├── About-Us.png                    #    About Us wireframe
│   ├── Admin.png                       #    Admin panel wireframe
│   ├── Contact-Us.png                  #    Contact Us wireframe
│   ├── Events.png                      #    Events page wireframe
│   ├── FAQ.png                         #    FAQ page wireframe
│   ├── Feed-Back.png                   #    Feedback page wireframe
│   ├── Home.png                        #    Homepage wireframe
│   ├── Login.png                       #    Login page wireframe
│   ├── Register.png                    #    Register page wireframe
│   ├── Student-DashBoard.png           #    Student Dashboard wireframe
│   ├── Student-Profile.png             #    Student Profile wireframe
│   └── Practical_1_StudentHub_Wireframes.docx  # Wireframes document (Word)
│
├── Drawio/                             # ✏️ Draw.io source files (editable wireframes & sitemaps)
│   ├── About-Us.drawio                 #    About Us wireframe source
│   ├── Admin.drawio                    #    Admin panel wireframe source
│   ├── Contact-Us.drawio               #    Contact Us wireframe source
│   ├── Events.drawio                   #    Events page wireframe source
│   ├── FAQ.drawio                      #    FAQ page wireframe source
│   ├── Feed-Back.drawio                #    Feedback page wireframe source
│   ├── Home.drawio                     #    Homepage wireframe source
│   ├── Login.drawio                    #    Login page wireframe source
│   ├── Register.drawio                 #    Register page wireframe source
│   ├── Student-DashBoard.drawio        #    Student Dashboard wireframe source
│   ├── Student-Profile.drawio          #    Student Profile wireframe source
│   ├── Sitemap-True.drawio             #    Final sitemap diagram source
│   ├── sitemap.drawio                  #    Sitemap draft source
│   ├── sitemap-1.drawio                #    Sitemap alternate version source
│   └── studenthub-sitemap.drawio       #    Student Hub sitemap source
│
├── php/                                # 🐘 Backend PHP Logic (Practical 8+ MySQL Database)
│   ├── db.php                          #    Robust centralized PDO MySQL connection & auto-recovery
│   ├── test_connection.php             #    Practical 8: Database diagnostic & connection test
│   ├── process_registration_db.php     #    Practical 8: Student registration via MySQL PDO
│   ├── process_contact_db.php          #    Practical 8: Contact inquiry via MySQL PDO
│   ├── process_event_registration_db.php # Practical 8: Event registration via MySQL PDO
│   ├── api_db_events.php               #    Practical 8: Live MySQL events JSON API
│   ├── practical_hub.php               #    Master practical evaluation hub
│   ├── register.php                    #    Registration routing entrypoint
│   ├── index.php                       #    Redirect to practical hub
│   └── previous/                       # 📦 Archived Practical 7 File Storage (JSON/CSV)
│       ├── README.md                   #    Documentation for Practical 7 file storage archive
│       ├── process_registration_file.php #  Archived: Student registration to JSON/CSV
│       ├── process_contact.php         #    Archived: Contact inquiries to JSON/CSV
│       ├── process_event_registration.php # Archived: Event registrations to JSON/CSV
│       └── view_file_records.php       #    Archived: Interactive JSON/CSV records viewer
│
├── data/                               # 📁 JSON & CSV File Storage Datastores (Practical 6-7)
├── sql/                                # 🗄️ Relational Database Schemas (Modular Table Files)
│   ├── student.sql                     #    Entity 1: Students table schema & seed data
│   ├── events.sql                      #    Entity 2: Events table schema & seed data
│   ├── registration.sql                #    Entity 3: Registrations table schema, foreign keys & view
│   └── contact_inquiries.sql           #    Contact Inquiries table schema & seed data
└── .git/                               # 🔧 Git version control (auto-managed)
```

---

## 📋 Folder Summary

| Folder          | Purpose                                                                 |
| --------------- | ----------------------------------------------------------------------- |
| `/`             | Root – contains `index.html` (homepage) and master docs                 |
| `admin/`        | Admin panel HTML pages                                                  |
| `page/`         | All public pages (login, register, events, contact, dashboard, etc.)    |
| `css/`          | Global stylesheets and per-module CSS files                             |
| `js/`           | Client-side scripts, validation, dynamic rendering, and Fetch API logic |
| `php/`          | PHP backend scripts, PDO database connection, and API endpoints         |
| `php/previous/` | **Archived Practical 7 JSON & CSV file-storage processors & viewers**   |
| `data/`         | Static & runtime JSON and CSV data stores                               |
| `sql/`          | MySQL schemas, tables, seeds, view, and stored procedure dumps          |
| `image/`        | Website logos and images used across pages                              |
| `draw-img/`     | Exported wireframe screenshots (PNG) + wireframe doc                    |
| `Drawio/`       | Editable Draw.io source files for wireframes and sitemaps               |

---


