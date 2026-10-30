<?php
/**
 * Practical Verification Hub: StudentHub (Practicals 1 to 8)
 * File: php/practical_hub.php
 * Description: Master examination dashboard consolidating proof of work,
 *              interactive tests, and side-by-side verification for Practicals 1 to 8.
 */

require_once __DIR__ . '/db.php';

// Check file storage metrics (Prac 6 & 7)
$jsonPath      = __DIR__ . '/../data/registrations.json';
$csvPath       = __DIR__ . '/../data/registrations.csv';
$eventRegPath  = __DIR__ . '/../data/event_registrations.json';
$eventsPath    = __DIR__ . '/../data/events.json';
$faqsPath      = __DIR__ . '/../data/faqs.json';
$studentsPath  = __DIR__ . '/../data/students.json';

$jsonCount     = file_exists($jsonPath) ? count(json_decode(file_get_contents($jsonPath) ?: '[]', true) ?: []) : 0;
$eventRegCount = file_exists($eventRegPath) ? count(json_decode(file_get_contents($eventRegPath) ?: '[]', true) ?: []) : 0;
$eventsCount   = file_exists($eventsPath) ? count(json_decode(file_get_contents($eventsPath) ?: '[]', true) ?: []) : 0;
$faqsCount     = file_exists($faqsPath) ? count(json_decode(file_get_contents($faqsPath) ?: '[]', true) ?: []) : 0;
$studentsCount = file_exists($studentsPath) ? count(json_decode(file_get_contents($studentsPath) ?: '[]', true) ?: []) : 0;

// Check MySQL database metrics (Prac 8)
$dbConnected = false;
$dbStudents = 0;
$dbEvents = 0;
$dbRegistrations = 0;
$dbError = '';

try {
    $pdo = getDBConnection(true);
    $dbConnected = true;
    $dbStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $dbEvents = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $dbRegistrations = (int)$pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practicals 1 to 8 Evaluation Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../css/register.css">
    <style>
        .hub-container {
            max-width: 1200px;
            margin: 30px auto 80px;
            padding: 0 24px;
        }
        .hub-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }
        .hub-header h1 {
            font-family: var(--font-serif);
            font-size: 34px;
            color: var(--text-primary);
        }
        .badge-academic {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 10px;
        }
        .comparison-section {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 36px;
            box-shadow: var(--card-hover-shadow);
        }
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .comparison-grid { grid-template-columns: 1fr; }
        }
        .mode-box {
            background-color: var(--bg-color);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .mode-box.highlight {
            border-color: rgba(133, 45, 27, 0.4);
            box-shadow: 0 4px 20px rgba(133, 45, 27, 0.06);
        }
        .mode-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mode-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-mini-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 10px 14px;
            border-radius: 10px;
        }
        .stat-mini-val {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-mini-lbl {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        .practicals-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 860px) {
            .practicals-list { grid-template-columns: 1fr; }
        }
        .practical-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .practical-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
            border-color: #dfcebf;
        }
        .practical-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .prac-number {
            font-size: 12px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .prac-status {
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .prac-status.completed {
            background-color: #dcfce7;
            color: #15803d;
        }
        body[data-theme="dark"] .prac-status.completed {
            background-color: #052e16;
            color: #86efac;
        }
        .practical-title {
            font-family: var(--font-serif);
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 4px 0 6px;
        }
        .practical-desc {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.45;
        }
        .practical-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-prac-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-prac-primary {
            background-color: var(--primary);
            color: #ffffff;
        }
        .btn-prac-primary:hover { background-color: var(--primary-hover); }
        .btn-prac-secondary {
            background-color: var(--bg-color);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
        }
        .btn-prac-secondary:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="../index.html" class="brand-link">
                <img src="../image/studenthub-logo.webp" alt="StudentHub Logo" class="brand-logo-img">
                <div class="brand-text">
                    <span class="brand-title">StudentHub</span>
                    <span class="brand-subtitle">University Portal</span>
                </div>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="../index.html" class="nav-link">Home</a></li>
                    <li><a href="../page/events.html" class="nav-link">Events</a></li>
                    <li><a href="../page/about-us.html" class="nav-link">About</a></li>
                    <li><a href="../page/FAQ.html" class="nav-link">FAQ</a></li>
                    <li><a href="../page/contact.html" class="nav-link">Contact</a></li>
                </ul>
            </nav>
            <div class="nav-actions">
                <button id="theme-toggle" class="icon-btn" aria-label="Toggle theme">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                </button>
                <span class="nav-divider"></span>
                <a href="../page/register.html" class="btn-register">Register</a>
            </div>
        </div>
    </header>

    <main class="hub-container">
        <div class="hub-header">
            <div>
                <span class="badge-academic">CHARUSAT FTE • ITUE203 Web Development Frameworks</span>
                <h1>Practical 1 to 8: Master Verification Suite</h1>
                <p style="color: var(--text-secondary); font-size: 15px; margin-top: 6px;">
                    Comprehensive demonstration of client frontend, asynchronous Fetch API rendering, server-side form processing, and transition from JSON/CSV storage to MySQL PDO database.
                </p>
            </div>
            <div>
                <a href="../IMPLEMENTATION_PLAN_PRACTICAL_1_TO_8.md" target="_blank" class="btn-prac-action btn-prac-secondary">
                    <span class="material-symbols-outlined">menu_book</span>
                    <span>Read Implementation Plan (.md)</span>
                </a>
            </div>
        </div>

        <!-- Proof of Work Dual-Version Architecture -->
        <section class="comparison-section">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-family: var(--font-serif); font-size: 24px; color: var(--text-primary);">
                        Dual-Version Proof of Work: File Storage vs. MySQL Database
                    </h2>
                    <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 4px;">
                        Practical 6 &amp; 7 utilize server file I/O (JSON/CSV), while Practical 8 migrates to a normalized MySQL 8.x schema via PDO Prepared Statements.
                    </p>
                </div>
            </div>

            <div class="comparison-grid">
                <!-- File Storage Mode -->
                <div class="mode-box highlight">
                    <div class="mode-title">
                        <span class="material-symbols-outlined" style="color: var(--primary);">folder_zip</span>
                        <span>Mode A: JSON &amp; CSV File Mode (Prac 6 &amp; 7)</span>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.45;">
                        Separated datastores: student accounts are stored in <code>data/registrations.json</code>. When students register for events, bookings reference their unique student ID and are saved separately in <code>data/event_registrations.json</code>.
                    </p>
                    <div class="mode-stats">
                        <div class="stat-mini-card">
                            <div class="stat-mini-val"><?= $jsonCount ?></div>
                            <div class="stat-mini-lbl">Students (registrations.json)</div>
                        </div>
                        <div class="stat-mini-card">
                            <div class="stat-mini-val"><?= $eventRegCount ?></div>
                            <div class="stat-mini-lbl">Event Bookings (event_registrations.json)</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 6px;">
                        <a href="view_file_records.php" class="btn-prac-action btn-prac-primary">
                            <span class="material-symbols-outlined">table_view</span> View File Records
                        </a>
                        <a href="../page/events.html" class="btn-prac-action btn-prac-secondary">
                            <span class="material-symbols-outlined">event_available</span> Test Event Booking
                        </a>
                    </div>
                </div>

                <!-- Database Mode -->
                <div class="mode-box">
                    <div class="mode-title">
                        <span class="material-symbols-outlined" style="color: #2563eb;">database</span>
                        <span>Mode B: MySQL Database Mode (Prac 8)</span>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.45;">
                        Submissions use PDO parameterized queries with bcrypt hashing into normalized tables (<code>students</code>, <code>events</code>, <code>registrations</code>). Events load directly from MySQL via <code>api_db_events.php</code>.
                    </p>
                    <div class="mode-stats">
                        <div class="stat-mini-card">
                            <div class="stat-mini-val" style="color: #2563eb;"><?= $dbStudents ?></div>
                            <div class="stat-mini-lbl">MySQL Students</div>
                        </div>
                        <div class="stat-mini-card">
                            <div class="stat-mini-val" style="color: #2563eb;"><?= $dbEvents ?></div>
                            <div class="stat-mini-lbl">MySQL Events</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 6px;">
                        <a href="test_connection.php" class="btn-prac-action btn-prac-primary" style="background-color: #2563eb;">
                            <span class="material-symbols-outlined">construction</span> Test DB &amp; Schema
                        </a>
                        <a href="../page/register.html?mode=db" class="btn-prac-action btn-prac-secondary">
                            <span class="material-symbols-outlined">person_add</span> Test DB Form
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Practical 1 to 8 Cards -->
        <h2 style="font-family: var(--font-serif); font-size: 26px; color: var(--text-primary); margin-bottom: 20px;">
            Detailed Lab Deliverables (Practical 1 to 8)
        </h2>

        <div class="practicals-list">

            <!-- Practical 1 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 01</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">Project Initiation, Wireframe &amp; Setup</h3>
                    <p class="practical-desc">Problem scope definition, user roles, 10-page sitemap, low-fidelity wireframes, and GitHub workspace structure.</p>
                </div>
                <div class="practical-actions">
                    <a href="../sitemap.md" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">account_tree</span> Sitemap
                    </a>
                    <a href="../project-folder-structure.md" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">folder</span> Folder Structure
                    </a>
                </div>
            </div>

            <!-- Practical 2 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 02</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">Semantic HTML5 &amp; Accessibility</h3>
                    <p class="practical-desc">Structured semantic skeletons with ARIA labels, image alt text, header/nav/main/section/footer landmarks across 10 pages.</p>
                </div>
                <div class="practical-actions">
                    <a href="../index.html" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">home</span> Home Page
                    </a>
                    <a href="../page/about-us.html" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">info</span> About Us
                    </a>
                </div>
            </div>

            <!-- Practical 3 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 03</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">Responsive UI with CSS Grid &amp; Flexbox</h3>
                    <p class="practical-desc">Mobile-first fluid layouts, media query breakpoints, terracotta palette, and accessible typography adapting to mobile, tablet, and desktop.</p>
                </div>
                <div class="practical-actions">
                    <a href="../css/style.css" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">css</span> style.css
                    </a>
                    <a href="../page/student-dashboard.html" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">dashboard</span> Dashboard UI
                    </a>
                </div>
            </div>

            <!-- Practical 4 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 04</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">DOM Manipulation, Events &amp; Theme Switcher</h3>
                    <p class="practical-desc">Dynamic UI interactivity, cyclic hero image slider with pause-on-hover, accessible accordions, and persistent light/dark mode via localStorage.</p>
                </div>
                <div class="practical-actions">
                    <a href="../js/script.js" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">javascript</span> script.js
                    </a>
                    <a href="../page/FAQ.html" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">quiz</span> Interactive FAQ
                    </a>
                </div>
            </div>

            <!-- Practical 5 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 05</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">Registration Form Validation &amp; Canvas CAPTCHA</h3>
                    <p class="practical-desc">Client validation using regular expressions, real-time input listeners, 4-tier password strength meter, requirement checklist, and HTML5 Canvas CAPTCHA.</p>
                </div>
                <div class="practical-actions">
                    <a href="../page/register.html" class="btn-prac-action btn-prac-primary">
                        <span class="material-symbols-outlined">how_to_reg</span> Open Registration Form
                    </a>
                    <a href="../js/validation.js" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">code</span> validation.js
                    </a>
                </div>
            </div>

            <!-- Practical 6 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 06</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">Rendering External JSON using Fetch API</h3>
                    <p class="practical-desc">Asynchronous loading of <code>events.json</code> (16 items), <code>faqs.json</code> (16 items), multi-criteria sorting (Date/Title), live search, skeleton loaders, and offline localStorage cache.</p>
                </div>
                <div class="practical-actions">
                    <a href="../page/events.html" class="btn-prac-action btn-prac-primary">
                        <span class="material-symbols-outlined">event</span> Events Catalog
                    </a>
                    <a href="../data/events.json" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">data_object</span> events.json
                    </a>
                    <a href="../data/students.json" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">group</span> students.json
                    </a>
                </div>
            </div>

            <!-- Practical 7 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 07</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">PHP Form Processing &amp; File Storage (JSON/CSV)</h3>
                    <p class="practical-desc">POST request handling, input sanitization, duplicate email check, and concurrent atomic appending to both JSON and CSV files via <code>flock()</code>.</p>
                </div>
                <div class="practical-actions">
                    <a href="view_file_records.php" class="btn-prac-action btn-prac-primary">
                        <span class="material-symbols-outlined">table_view</span> Stored File Records
                    </a>
                    <a href="?download=csv" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">download</span> Export CSV
                    </a>
                </div>
            </div>

            <!-- Practical 8 -->
            <div class="practical-card">
                <div>
                    <div class="practical-top">
                        <span class="prac-number">Practical 08</span>
                        <span class="prac-status completed">Complete</span>
                    </div>
                    <h3 class="practical-title">MySQL Schema Design, ER Model &amp; PDO</h3>
                    <p class="practical-desc">3NF normalized database architecture (<code>students</code>, <code>events</code>, <code>registrations</code>), PDO connection handler with native prepared statements, view, and stored procedure.</p>
                </div>
                <div class="practical-actions">
                    <a href="test_connection.php" class="btn-prac-action btn-prac-primary" style="background-color: #2563eb;">
                        <span class="material-symbols-outlined">construction</span> Connection &amp; Schema Test
                    </a>
                    <a href="../ER_DIAGRAM.md" target="_blank" class="btn-prac-action btn-prac-secondary">
                        <span class="material-symbols-outlined">schema</span> ER Diagram Specification
                    </a>
                </div>
            </div>

        </div>
    </main>

    <script src="../js/script.js"></script>
</body>
</html>
