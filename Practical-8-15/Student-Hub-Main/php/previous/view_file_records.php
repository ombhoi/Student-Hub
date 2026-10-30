<?php
/**
 * Practical 7: Stored CSV & JSON Records Viewer
 * File: php/previous/view_file_records.php
 * Description: Interactive verification dashboard displaying submissions stored in
 *              data/registrations.json (students) and data/event_registrations.json (event bookings)
 *              with CSV exports and raw inspections.
 * Location: Archived in php/previous/ for safe preservation.
 */

$jsonPath        = __DIR__ . '/../../data/registrations.json';
$csvPath         = __DIR__ . '/../../data/registrations.csv';
$eventJsonPath   = __DIR__ . '/../../data/event_registrations.json';
$eventCsvPath    = __DIR__ . '/../../data/event_registrations.csv';
$contactJsonPath = __DIR__ . '/../../data/contact.json';
$contactCsvPath  = __DIR__ . '/../../data/contact.csv';

// Handle direct CSV download
if (isset($_GET['download'])) {
    if ($_GET['download'] === 'csv' && file_exists($csvPath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="studenthub_students_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($csvPath);
        exit;
    }
    if ($_GET['download'] === 'events_csv' && file_exists($eventCsvPath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="studenthub_event_registrations_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($eventCsvPath);
        exit;
    }
    if ($_GET['download'] === 'contact_csv' && file_exists($contactCsvPath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="studenthub_contact_inquiries_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($contactCsvPath);
        exit;
    }
}

// 1. Read Student Registrations
$jsonRecords = [];
$rawJsonContent = '';
if (file_exists($jsonPath)) {
    $rawJsonContent = file_get_contents($jsonPath);
    if ($rawJsonContent !== false && !empty(trim($rawJsonContent))) {
        $decoded = json_decode($rawJsonContent, true);
        if (is_array($decoded)) {
            $jsonRecords = $decoded;
        }
    }
}

// 2. Read Event Registrations
$eventRecords = [];
$rawEventJsonContent = '';
if (file_exists($eventJsonPath)) {
    $rawEventJsonContent = file_get_contents($eventJsonPath);
    if ($rawEventJsonContent !== false && !empty(trim($rawEventJsonContent))) {
        $decoded = json_decode($rawEventJsonContent, true);
        if (is_array($decoded)) {
            $eventRecords = $decoded;
        }
    }
}

// 3. Read Contact Inquiries
$contactRecords = [];
$rawContactJsonContent = '';
if (file_exists($contactJsonPath)) {
    $rawContactJsonContent = file_get_contents($contactJsonPath);
    if ($rawContactJsonContent !== false && !empty(trim($rawContactJsonContent))) {
        $decoded = json_decode($rawContactJsonContent, true);
        if (is_array($decoded)) {
            $contactRecords = $decoded;
        }
    }
}

$jsonSize = file_exists($jsonPath) ? round(filesize($jsonPath) / 1024, 2) : 0;
$csvSize  = file_exists($csvPath) ? round(filesize($csvPath) / 1024, 2) : 0;
$eventJsonSize = file_exists($eventJsonPath) ? round(filesize($eventJsonPath) / 1024, 2) : 0;
$contactJsonSize = file_exists($contactJsonPath) ? round(filesize($contactJsonPath) / 1024, 2) : 0;
$lastUpdated = file_exists($jsonPath) ? date('M d, Y H:i:s', filemtime($jsonPath)) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Practical 7 File Storage Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../../css/register.css">
    <style>
        .records-wrapper {
            max-width: 1240px;
            margin: 30px auto 80px;
            padding: 0 24px;
        }
        .records-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 28px;
        }
        .records-header h1 {
            font-family: var(--font-serif);
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .records-header p {
            color: var(--text-secondary);
            font-size: 14.5px;
            margin-top: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        @media (max-width: 860px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        .stat-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .records-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--card-hover-shadow);
        }
        .card-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid var(--card-border);
            flex-wrap: wrap;
            gap: 12px;
        }
        .tab-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            border: 1px solid var(--card-border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background-color: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-download-csv {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background-color: #15803d;
            color: #ffffff;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-download-csv:hover { background-color: #166534; }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .records-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        .records-table th {
            background-color: var(--bg-color);
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            white-space: nowrap;
        }
        .records-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-primary);
            white-space: nowrap;
        }
        .records-table tr:last-child td { border-bottom: none; }
        .records-table tr:hover td {
            background-color: rgba(133, 45, 27, 0.02);
        }
        .ticket-badge {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .stu-badge {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            background-color: #eff6ff;
            color: #2563eb;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .json-pre-box {
            background-color: #18181b;
            color: #38bdf8;
            font-family: monospace;
            font-size: 13px;
            padding: 20px;
            border-radius: 12px;
            overflow-x: auto;
            max-height: 480px;
            white-space: pre-wrap;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <!-- Standard StudentHub Navbar -->
    <header class="navbar">
        <div class="navbar-container">
            <a href="../../index.html" class="brand-link">
                <img src="../../image/studenthub-logo.webp" alt="StudentHub Logo" class="brand-logo-img">
                <div class="brand-text">
                    <span class="brand-title">StudentHub</span>
                    <span class="brand-subtitle">University Portal</span>
                </div>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="../../index.html" class="nav-link">Home</a></li>
                    <li><a href="../../page/events.html" class="nav-link">Events</a></li>
                    <li><a href="../../page/about-us.html" class="nav-link">About</a></li>
                    <li><a href="../../page/FAQ.html" class="nav-link">FAQ</a></li>
                    <li><a href="../../page/contact.html" class="nav-link">Contact</a></li>
                </ul>
            </nav>
            <div class="nav-actions">
                <a href="../practical_hub.php" class="nav-link" style="color: var(--primary); font-weight: 700;">
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">arrow_back</span>
                    Practical Hub
                </a>
                <button id="theme-toggle" class="icon-btn" aria-label="Toggle theme">
                    <span class="material-symbols-outlined theme-icon">dark_mode</span>
                </button>
                <span class="nav-divider"></span>
                <a href="../../page/register.html" class="btn-register">+ Register Student</a>
            </div>
        </div>
    </header>

    <main class="records-wrapper">
        <div class="records-header">
            <div>
                <span class="register-header-tag">Practical 7 Archive &bull; JSON &amp; CSV Datastores</span>
                <h1>File Storage Submissions <em>(Archived Records)</em></h1>
                <p>Real-time view of <code>registrations.json</code> (Students), <code>event_registrations.json</code> (Events), and <code>contact.json</code> (Inquiries).</p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="?download=csv" class="btn-download-csv">
                    <span class="material-symbols-outlined" style="font-size: 18px;">download</span>
                    <span>Students CSV</span>
                </a>
                <a href="?download=events_csv" class="btn-download-csv" style="background-color: #852d1b;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">event</span>
                    <span>Events CSV</span>
                </a>
                <a href="?download=contact_csv" class="btn-download-csv" style="background-color: #0369a1;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">mail</span>
                    <span>Inquiries CSV</span>
                </a>
                <a href="../practical_hub.php" class="btn-register" style="display: inline-flex; align-items: center; gap: 6px; background: #2563eb;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">hub</span>
                    <span>Practical Hub</span>
                </a>
            </div>
        </div>

        <!-- Metric Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">badge</span></div>
                <div>
                    <div class="stat-value"><?= count($jsonRecords) ?></div>
                    <div class="stat-label">Registered Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(21, 128, 61, 0.15); color: #15803d;"><span class="material-symbols-outlined">event_available</span></div>
                <div>
                    <div class="stat-value"><?= count($eventRecords) ?></div>
                    <div class="stat-label">Event Registrations</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(3, 105, 161, 0.15); color: #0369a1;"><span class="material-symbols-outlined">contact_support</span></div>
                <div>
                    <div class="stat-value"><?= count($contactRecords) ?></div>
                    <div class="stat-label">Contact Inquiries</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">update</span></div>
                <div>
                    <div class="stat-value" style="font-size: 13.5px;"><?= $lastUpdated ?></div>
                    <div class="stat-label">Last Synchronization</div>
                </div>
            </div>
        </div>

        <!-- Interactive Records Container -->
        <div class="records-card">
            <div class="card-header-row">
                <div class="tab-buttons">
                    <button type="button" class="tab-btn active" id="tab-students">
                        <span class="material-symbols-outlined" style="font-size: 16px;">school</span>
                        <span>Students (<?= count($jsonRecords) ?>)</span>
                    </button>
                    <button type="button" class="tab-btn" id="tab-events">
                        <span class="material-symbols-outlined" style="font-size: 16px;">event_seat</span>
                        <span>Events (<?= count($eventRecords) ?>)</span>
                    </button>
                    <button type="button" class="tab-btn" id="tab-contacts">
                        <span class="material-symbols-outlined" style="font-size: 16px;">mail</span>
                        <span>Inquiries (<?= count($contactRecords) ?>)</span>
                    </button>
                    <button type="button" class="tab-btn" id="tab-json">
                        <span class="material-symbols-outlined" style="font-size: 16px;">code</span>
                        <span>Raw JSON</span>
                    </button>
                </div>
                <span style="font-size: 12.5px; color: var(--text-secondary);">
                    Format: Linked JSON/CSV Datastores
                </span>
            </div>

            <!-- Tab 1: Students Table View -->
            <div id="view-students" class="table-responsive">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Academic Email</th>
                            <th>Mobile</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jsonRecords)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    No records found in registrations.json. Use the registration form to submit your first student.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jsonRecords as $rec): ?>
                                <tr>
                                    <td><?= $rec['id'] ?? '-' ?></td>
                                    <td><span class="stu-badge"><?= $rec['student_id'] ?? ('STU-' . (1000 + (int)($rec['id'] ?? 0))) ?></span></td>
                                    <td><strong><?= htmlspecialchars($rec['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($rec['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($rec['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($rec['department'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($rec['year'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($rec['gender'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="color: var(--text-muted); font-size: 12.5px;"><?= $rec['registered_at'] ?? '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab 2: Event Registrations Table View -->
            <div id="view-events" class="table-responsive" style="display: none;">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Booking ID</th>
                            <th>Event Title</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Academic Email</th>
                            <th>Department</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eventRecords)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    No event bookings yet. Register for an event from the <a href="../../page/events.html" style="color: var(--primary); font-weight: 600;">Events page</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eventRecords as $ereg): ?>
                                <tr>
                                    <td><?= $ereg['id'] ?? '-' ?></td>
                                    <td><span class="ticket-badge" style="background: rgba(133, 45, 27, 0.12); color: #852d1b;"><?= $ereg['registration_id'] ?? 'N/A' ?></span></td>
                                    <td><strong><?= htmlspecialchars($ereg['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> <span style="font-size: 11px; color: var(--text-muted);">(ID: <?= $ereg['event_id'] ?? 0 ?>)</span></td>
                                    <td><span class="stu-badge"><?= $ereg['student_id'] ?? '' ?></span></td>
                                    <td><strong><?= htmlspecialchars($ereg['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($ereg['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($ereg['department'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td style="color: var(--text-muted); font-size: 12.5px;"><?= $ereg['registration_date'] ?? '' ?></td>
                                    <td><span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534;"><?= $ereg['status'] ?? 'Confirmed' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab 3: Contact Inquiries Table View -->
            <div id="view-contacts" class="table-responsive" style="display: none;">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Inquiry ID</th>
                            <th>Name</th>
                            <th>Email Address</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Submitted Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contactRecords)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <span class="material-symbols-outlined" style="font-size: 36px; display: block; margin-bottom: 8px;">inbox</span>
                                    No contact inquiries stored yet in <code>data/contact.json</code> &amp; <code>data/contact.csv</code>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contactRecords as $inq): ?>
                                <tr>
                                    <td><?= $inq['id'] ?? '-' ?></td>
                                    <td><span class="ticket-badge" style="background: rgba(3, 105, 161, 0.12); color: #0369a1;"><?= $inq['inquiry_id'] ?? 'N/A' ?></span></td>
                                    <td><strong><?= htmlspecialchars($inq['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($inq['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($inq['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($inq['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($inq['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 12.5px;"><?= $inq['submitted_at'] ?? '' ?></td>
                                    <td><span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1;"><?= $inq['status'] ?? 'New' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab 4: Raw JSON View -->
            <div id="view-json" style="display: none; padding: 16px;">
                <div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
                    <button type="button" id="btn-show-stu-json" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #444; background: #333; color: #fff; font-size: 12px; cursor: pointer;">
                        registrations.json (Students)
                    </button>
                    <button type="button" id="btn-show-evt-json" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #444; background: transparent; color: #aaa; font-size: 12px; cursor: pointer;">
                        event_registrations.json (Events)
                    </button>
                    <button type="button" id="btn-show-contact-json" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #444; background: transparent; color: #aaa; font-size: 12px; cursor: pointer;">
                        contact.json (Inquiries)
                    </button>
                </div>
                <pre class="json-pre-box" id="json-display-box"><code><?= htmlspecialchars($rawJsonContent, ENT_QUOTES, 'UTF-8') ?></code></pre>
            </div>
        </div>
    </main>

    <script src="../../js/script.js"></script>
    <script>
        const tabStudents = document.getElementById('tab-students');
        const tabEvents   = document.getElementById('tab-events');
        const tabContacts = document.getElementById('tab-contacts');
        const tabJson     = document.getElementById('tab-json');
        
        const viewStudents = document.getElementById('view-students');
        const viewEvents   = document.getElementById('view-events');
        const viewContacts = document.getElementById('view-contacts');
        const viewJson     = document.getElementById('view-json');

        const btnShowStuJson     = document.getElementById('btn-show-stu-json');
        const btnShowEvtJson     = document.getElementById('btn-show-evt-json');
        const btnShowContactJson = document.getElementById('btn-show-contact-json');
        const jsonDisplayBox     = document.getElementById('json-display-box');

        const rawStuJson     = <?= json_encode($rawJsonContent) ?>;
        const rawEvtJson     = <?= json_encode($rawEventJsonContent) ?>;
        const rawContactJson = <?= json_encode($rawContactJsonContent) ?>;

        function setTab(activeTab, activeView) {
            [tabStudents, tabEvents, tabContacts, tabJson].forEach(t => t && t.classList.remove('active'));
            [viewStudents, viewEvents, viewContacts, viewJson].forEach(v => v && (v.style.display = 'none'));

            activeTab.classList.add('active');
            activeView.style.display = 'block';
        }

        if (tabStudents && tabEvents && tabContacts && tabJson) {
            tabStudents.addEventListener('click', () => setTab(tabStudents, viewStudents));
            tabEvents.addEventListener('click', () => setTab(tabEvents, viewEvents));
            tabContacts.addEventListener('click', () => setTab(tabContacts, viewContacts));
            tabJson.addEventListener('click', () => setTab(tabJson, viewJson));
        }

        function highlightJsonBtn(activeBtn) {
            [btnShowStuJson, btnShowEvtJson, btnShowContactJson].forEach(b => {
                if (b === activeBtn) {
                    b.style.background = '#333';
                    b.style.color = '#fff';
                } else {
                    b.style.background = 'transparent';
                    b.style.color = '#aaa';
                }
            });
        }

        if (btnShowStuJson && btnShowEvtJson && btnShowContactJson && jsonDisplayBox) {
            btnShowStuJson.addEventListener('click', () => {
                highlightJsonBtn(btnShowStuJson);
                jsonDisplayBox.querySelector('code').textContent = rawStuJson;
            });
            btnShowEvtJson.addEventListener('click', () => {
                highlightJsonBtn(btnShowEvtJson);
                jsonDisplayBox.querySelector('code').textContent = rawEvtJson;
            });
            btnShowContactJson.addEventListener('click', () => {
                highlightJsonBtn(btnShowContactJson);
                jsonDisplayBox.querySelector('code').textContent = rawContactJson;
            });
        }
    </script>
</body>
</html>
