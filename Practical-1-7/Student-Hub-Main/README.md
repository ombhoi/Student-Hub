# StudentHub — College Portal

StudentHub is a web application layout designed for campus management, student communication, event tracking, and administrative control. This project is built using pure, semantic HTML structure as part of web development practical coursework.

---

## Setup & How to Run

Follow these steps to open and preview the project correctly so that all relative file paths function without errors:

1. **Download / Clone the Repository**
   Download the project repository as a ZIP file and extract it, or clone it to your local machine.

2. **Open the Root Project Directory in VS Code**
   * Navigate to the extracted folder.
   * Right-click on the `Student-Hub` directory (or hold `Shift` + Right-click) and select **Open with Code**.
   * *Note:* Opening `Student-Hub` directly as the workspace root ensures that relative paths for internal links (`page/`, `admin/`, etc.) resolve properly.

3. **Preview Pages**
   * Double-click `index.html` to open it in any standard browser, or use the **Live Server** extension in VS Code.

---

## Project Directory Structure

```text
Student-Hub/
├── admin/
│   └── admin.html              # Administrative Control Console
├── css/                        # Stylesheets (Reserved for future CSS integration)
├── image/                      # Images and asset storage
├── js/                         # JavaScript files (Reserved for future scripts)
├── page/                       # Secondary application pages
│   ├── about-us.html           # About page
│   ├── contact.html            # Contact & Support page
│   ├── events.html             # Campus Events listing
│   ├── FAQ.html                # Frequently Asked Questions with accordions
│   ├── feed-back.html          # Portal & Event Feedback form
│   ├── login.html              # User Authentication (Login)
│   ├── register.html           # Student Registration page
│   ├── student-dashboard.html  # Student Overview & Dashboard
│   └── student-profile.html    # Student Profile management page
├── index.html                  # Main Homepage
└── README.md                   # Project documentation