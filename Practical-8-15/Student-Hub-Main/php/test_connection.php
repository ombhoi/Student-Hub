<?php
/**
 * Practical 8: StudentHub Connection Diagnostic & Schema Verifier
 * File: php/test_connection.php
 * Description: Verifies PDO connection, demonstrates Prepared Statements, and checks 3 tables.
 */

require_once __DIR__ . '/db.php';

// Diagnostic state
$serverConnected  = false;
$serverInfo       = '';
$errorMessage     = '';
$errorResolution  = '';
$dbExists         = false;
$tablesStatus     = [
    'students'          => ['exists' => false, 'rows' => 0, 'pk' => 'student_id', 'file' => 'student.sql'],
    'events'            => ['exists' => false, 'rows' => 0, 'pk' => 'event_id', 'file' => 'events.sql'],
    'registrations'     => ['exists' => false, 'rows' => 0, 'pk' => 'registration_id', 'file' => 'registration.sql'],
    'contact_inquiries' => ['exists' => false, 'rows' => 0, 'pk' => 'id', 'file' => 'contact_inquiries.sql'],
];
$recentRegistrations = [];
$preparedStatementTested = false;
$preparedStatementOutput = '';
$actionMessage = '';
$actionError   = '';

// -------------------------------------------------------------
// Helper: Execute a .sql file safely using PDO
// -------------------------------------------------------------
function executeSqlFile(PDO $pdo, string $filePath): bool {
    return executeSqlScript($pdo, $filePath);
}

try {
    // 1. Connect to MySQL server daemon
    $pdoServer = getDBConnection(false);
    $serverConnected = true;
    $serverInfo = $pdoServer->getAttribute(PDO::ATTR_SERVER_VERSION);

    // 2. Handle Schema Actions (POST / GET)
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'import_all') {
        try {
            $sqlDir = __DIR__ . '/../sql';
            $sqlOrder = ['student.sql', 'events.sql', 'registration.sql', 'contact_inquiries.sql'];
            foreach ($sqlOrder as $sqlFile) {
                $filePath = $sqlDir . '/' . $sqlFile;
                if (file_exists($filePath)) {
                    executeSqlFile($pdoServer, $filePath);
                }
            }
            $actionMessage = "All separate table files (student.sql, events.sql, registration.sql, contact_inquiries.sql) imported successfully!";
        } catch (Throwable $e) {
            $actionError = "Import failed: " . $e->getMessage();
        }
    }

    // 3. Check if target database exists
    $stmt = $pdoServer->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
    $stmt->execute(['dbname' => DB_NAME]);
    $dbExists = (bool)$stmt->fetchColumn();

    // 4. Inspect tables & run prepared statement if database exists
    if ($dbExists) {
        $pdoDb = getDBConnection(true);

        foreach ($tablesStatus as $tbl => &$meta) {
            $chk = $pdoDb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :tbl");
            $chk->execute(['db' => DB_NAME, 'tbl' => $tbl]);
            if ($chk->fetchColumn() > 0) {
                $meta['exists'] = true;
                $cnt = $pdoDb->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $meta['rows'] = (int)$cnt;
            }
        }
        unset($meta);

        // Intermediate Extension: Prepared Statement Verification
        if ($tablesStatus['students']['exists']) {
            $testEmail = 'alex.ce23@charusat.edu.in';
            $prepStmt = $pdoDb->prepare("SELECT student_id, first_name, last_name, email, department, roll_number FROM `students` WHERE `email` = :email");
            $prepStmt->bindValue(':email', $testEmail, PDO::PARAM_STR);
            $prepStmt->execute();
            $student = $prepStmt->fetch();

            if ($student) {
                $preparedStatementTested = true;
                $preparedStatementOutput = "Bound parameter query matched Student #{$student['student_id']}: {$student['first_name']} {$student['last_name']} ({$student['roll_number']} - {$student['department']})";
            }
        }

        // Fetch recent registrations from View
        if ($tablesStatus['registrations']['exists']) {
            try {
                $viewStmt = $pdoDb->query("SELECT ticket_code, student_name, student_email, event_title, event_date, registration_status FROM `vw_student_registrations` ORDER BY registration_id DESC LIMIT 4");
                $recentRegistrations = $viewStmt->fetchAll();
            } catch (Throwable $e) {
                // View might not be created if imported individually
            }
        }
    }

} catch (PDOException $e) {
    $errorMessage = $e->getMessage();

    if (strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, '2002') !== false) {
        $errorResolution = "MySQL daemon is offline. Open XAMPP Control Panel and start MySQL.";
    } elseif (strpos($errorMessage, 'Access denied') !== false || strpos($errorMessage, '1045') !== false) {
        $errorResolution = "Access denied. In XAMPP, verify user 'root' and empty password in php/db.php.";
    } else {
        $errorResolution = "Check MySQL configuration on port " . DB_PORT . ".";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 8: StudentHub DB Connection &amp; Schema Test</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        :root {
            /* StudentHub Autumn Warm Library Theme */
            --bg-color: #faf7f2;
            --text-primary: #1c1917;
            --text-secondary: #78716c;
            --text-muted: #86736d;

            --card-bg: #ffffff;
            --card-border: #ede4dc;
            --card-border-subtle: #f2ebe4;
            --card-shadow: 0 20px 45px -10px rgba(120, 80, 50, 0.09), 0 6px 18px -4px rgba(120, 80, 50, 0.04);

            --primary: #852d1b;
            --primary-hover: #702516;
            --primary-light: #faece5;
            --primary-border: #e9d5c8;
            --on-primary: #ffffff;

            --surface-box: #faf4ed;
            --surface-box-border: #eadccf;
            --surface-card: #ffffff;

            --code-bg: #f4ede3;
            --code-color: #852d1b;
            --code-border: #e6dacd;

            --success: #047857;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #065f46;

            --danger: #b91c1c;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;

            --warning: #b45309;
            --warning-bg: #fffbeb;
            --warning-border: #fde68a;
            --warning-text: #92400e;

            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body[data-theme="dark"] {
            --bg-color: #141210;
            --text-primary: #f5eee7;
            --text-secondary: #a39991;
            --text-muted: #736b64;

            --card-bg: #1c1815;
            --card-border: #2e2621;
            --card-border-subtle: #382c24;
            --card-shadow: 0 16px 40px -4px rgba(0, 0, 0, 0.7);

            --primary: #d46349;
            --primary-hover: #e0765e;
            --primary-light: #2d1d17;
            --primary-border: #443026;
            --on-primary: #ffffff;

            --surface-box: #241e1a;
            --surface-box-border: #382c24;
            --surface-card: #1c1815;

            --code-bg: #2a221d;
            --code-color: #e28e6c;
            --code-border: #3d312a;

            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.12);
            --success-border: rgba(16, 185, 129, 0.3);
            --success-text: #6ee7b7;

            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --danger-border: rgba(239, 68, 68, 0.3);
            --danger-text: #fca5a5;

            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.12);
            --warning-border: rgba(245, 158, 11, 0.3);
            --warning-text: #fde68a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--font-sans);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .container {
            width: 100%;
            max-width: 920px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: linear-gradient(180deg, var(--primary-light) 0%, var(--card-bg) 100%);
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 4px 10px rgba(133, 45, 27, 0.08);
            flex-shrink: 0;
        }

        .header-text h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.01em;
        }

        .header-text p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-btn-toggle {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .icon-btn-toggle:hover {
            color: var(--primary);
            background: var(--primary-light);
            border-color: var(--primary-border);
        }

        .content {
            padding: 28px 32px 34px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .banner {
            padding: 14px 18px;
            border-radius: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .banner.success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
        .banner.error   { background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger-text); }
        .banner.warning { background: var(--warning-bg); border: 1px solid var(--warning-border); color: var(--warning-text); }

        .banner .material-symbols-outlined { font-size: 22px; flex-shrink: 0; margin-top: 1px; }

        .server-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .server-box {
            background: var(--surface-box);
            border: 1px solid var(--surface-box-border);
            border-radius: 12px;
            padding: 12px 14px;
            transition: all 0.2s ease;
        }

        .server-box:hover {
            border-color: var(--card-border);
            box-shadow: 0 4px 12px rgba(120, 80, 50, 0.05);
        }

        .server-label {
            font-size: 0.72rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .server-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: var(--font-sans);
            word-break: break-all;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 8px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success); }
        .badge-danger  { background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); border: 1px solid var(--warning-border); color: var(--warning); }

        .section-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 4px 16px rgba(120, 80, 50, 0.03);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .section-title .material-symbols-outlined {
            color: var(--primary);
            font-size: 22px;
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .entity-box {
            background: var(--surface-box);
            border: 1px solid var(--surface-box-border);
            border-radius: 12px;
            padding: 14px 15px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .entity-box:hover {
            border-color: var(--card-border);
            box-shadow: 0 4px 12px rgba(120, 80, 50, 0.05);
        }

        .entity-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 0.94rem;
            color: var(--text-primary);
        }

        .entity-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .entity-meta code {
            background: var(--code-bg);
            color: var(--code-color);
            padding: 1px 6px;
            border-radius: 5px;
            border: 1px solid var(--code-border);
            font-size: 0.78rem;
            font-weight: 600;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .entity-source {
            color: var(--success) !important;
            font-weight: 600;
            font-size: 0.76rem !important;
            margin-top: 4px;
        }

        .data-table-wrapper {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--card-bg);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }

        .data-table th, .data-table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--card-border-subtle);
        }

        .data-table th {
            background: var(--surface-box);
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--card-border);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background: var(--primary-light);
        }

        .data-table code {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 2px 7px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .prep-verified-box {
            font-size: 0.88rem;
            color: var(--success-text);
            background: var(--success-bg);
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--success-border);
            font-weight: 600;
            line-height: 1.5;
        }

        .actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            font-family: var(--font-sans);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--on-primary);
            box-shadow: 0 4px 12px rgba(133, 45, 27, 0.22);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(133, 45, 27, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .btn-secondary:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-border);
        }

        @media (max-width: 860px) {
            .table-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-actions {
                width: 100%;
                justify-content: flex-end;
                flex-wrap: wrap;
            }
            .server-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .table-grid {
                grid-template-columns: 1fr;
            }
            .content {
                padding: 20px 18px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title-area">
                <div class="header-icon">
                    <span class="material-symbols-outlined">storage</span>
                </div>
                <div class="header-text">
                    <h1>Practical 8: StudentHub DB Verifier</h1>
                    <p>MySQL PDO connection, normalized schema &amp; prepared statements</p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" id="theme-toggle-btn" class="icon-btn-toggle" title="Toggle dark/light theme" aria-label="Toggle theme">
                    <span class="material-symbols-outlined" id="theme-toggle-icon" style="font-size: 20px;">dark_mode</span>
                </button>
                <a href="../admin/admin.html" class="btn btn-secondary" style="padding: 8px 14px; font-size: 0.82rem;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                    Admin Console
                </a>
                <a href="http://localhost/phpmyadmin/" target="_blank" class="btn btn-secondary" style="padding: 8px 14px; font-size: 0.82rem;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">open_in_new</span>
                    phpMyAdmin
                </a>
            </div>
        </div>

        <div class="content">

            <!-- Server Status -->
            <div class="server-grid">
                <div class="server-box">
                    <div class="server-label">Host &amp; Port</div>
                    <div class="server-value"><?= htmlspecialchars(DB_HOST) ?>:<?= htmlspecialchars((string)DB_PORT) ?></div>
                </div>
                <div class="server-box">
                    <div class="server-label">User</div>
                    <div class="server-value"><?= htmlspecialchars(DB_USER) ?></div>
                </div>
                <div class="server-box">
                    <div class="server-label">MySQL Daemon</div>
                    <div class="server-value">
                        <span class="badge <?= $serverConnected ? 'badge-success' : 'badge-danger' ?>">
                            <?= $serverConnected ? 'CONNECTED' : 'OFFLINE' ?>
                        </span>
                    </div>
                </div>
                <div class="server-box">
                    <div class="server-label">Database</div>
                    <div class="server-value">
                        <span class="badge <?= $dbExists ? 'badge-success' : 'badge-danger' ?>">
                            <?= $dbExists ? DB_NAME : 'NOT FOUND' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Feedback Notifications -->
            <?php if (!empty($actionMessage)): ?>
                <div class="banner success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <div><?= htmlspecialchars($actionMessage) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($actionError)): ?>
                <div class="banner error">
                    <span class="material-symbols-outlined">error</span>
                    <div><?= htmlspecialchars($actionError) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!$serverConnected): ?>
                <div class="banner error">
                    <span class="material-symbols-outlined">cancel</span>
                    <div>
                        <strong>Connection Error:</strong>
                        <p style="font-family: monospace; font-size: 0.82rem; margin-top: 4px;"><?= htmlspecialchars($errorMessage) ?></p>
                        <p style="margin-top: 6px;"><?= $errorResolution ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Relational Schema Verification -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined">schema</span>
                        <span>Relational Schema Verification (Entities)</span>
                    </div>
                    <div>
                        <span class="badge <?= ($tablesStatus['students']['exists'] && $tablesStatus['events']['exists'] && $tablesStatus['registrations']['exists']) ? 'badge-success' : 'badge-warning' ?>">
                            <?= ($tablesStatus['students']['exists'] && $tablesStatus['events']['exists'] && $tablesStatus['registrations']['exists']) ? 'All Entities Consistent' : 'Pending Import' ?>
                        </span>
                    </div>
                </div>

                <div class="table-grid">
                    <!-- Entity 1: Students -->
                    <div class="entity-box">
                        <div class="entity-title">
                            <span>1. students</span>
                            <span class="badge <?= $tablesStatus['students']['exists'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $tablesStatus['students']['exists'] ? $tablesStatus['students']['rows'] . ' rows' : 'Missing' ?>
                            </span>
                        </div>
                        <div class="entity-meta">PK: <code>student_id</code></div>
                        <div class="entity-meta">UQ: <code>email, roll_number</code></div>
                        <div class="entity-meta entity-source">Source: <code>sql/student.sql</code></div>
                    </div>

                    <!-- Entity 2: Events -->
                    <div class="entity-box">
                        <div class="entity-title">
                            <span>2. events</span>
                            <span class="badge <?= $tablesStatus['events']['exists'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $tablesStatus['events']['exists'] ? $tablesStatus['events']['rows'] . ' rows' : 'Missing' ?>
                            </span>
                        </div>
                        <div class="entity-meta">PK: <code>event_id</code></div>
                        <div class="entity-meta">IDX: <code>category, event_date</code></div>
                        <div class="entity-meta entity-source">Source: <code>sql/events.sql</code></div>
                    </div>

                    <!-- Entity 3: Registrations -->
                    <div class="entity-box">
                        <div class="entity-title">
                            <span>3. registrations</span>
                            <span class="badge <?= $tablesStatus['registrations']['exists'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $tablesStatus['registrations']['exists'] ? $tablesStatus['registrations']['rows'] . ' rows' : 'Missing' ?>
                            </span>
                        </div>
                        <div class="entity-meta">PK: <code>registration_id</code></div>
                        <div class="entity-meta">FK: <code>student_id, event_id</code></div>
                        <div class="entity-meta entity-source">Source: <code>sql/registration.sql</code></div>
                    </div>

                    <!-- Entity 4: Contact Inquiries -->
                    <div class="entity-box">
                        <div class="entity-title">
                            <span>4. contact_inquiries</span>
                            <span class="badge <?= $tablesStatus['contact_inquiries']['exists'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $tablesStatus['contact_inquiries']['exists'] ? $tablesStatus['contact_inquiries']['rows'] . ' rows' : 'Missing' ?>
                            </span>
                        </div>
                        <div class="entity-meta">PK: <code>id</code></div>
                        <div class="entity-meta">UQ: <code>inquiry_id</code></div>
                        <div class="entity-meta entity-source">Source: <code>sql/contact_inquiries.sql</code></div>
                    </div>
                </div>

                <div class="actions-row">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="import_all">
                        <button type="submit" class="btn btn-primary" <?= !$serverConnected ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined">rocket_launch</span>
                            <?= $dbExists ? 'Re-Sync Separate SQL Tables' : 'Import Separate Tables' ?>
                        </button>
                    </form>

                    <button type="button" onclick="window.location.reload();" class="btn btn-secondary">
                        <span class="material-symbols-outlined">refresh</span>
                        Refresh Status
                    </button>
                </div>
            </div>

            <!-- Intermediate Extension: Prepared Statement Verification -->
            <?php if ($preparedStatementTested): ?>
                <div class="section-card">
                    <div class="section-title">
                        <span class="material-symbols-outlined" style="color: var(--success);">verified_user</span>
                        <span>Practical 8 Extension: Prepared Statement Verified</span>
                    </div>
                    <p class="prep-verified-box">
                        <?= htmlspecialchars($preparedStatementOutput) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Live Sample Registrations View -->
            <?php if (!empty($recentRegistrations)): ?>
                <div class="section-card">
                    <div class="section-title">
                        <span class="material-symbols-outlined">preview</span>
                        <span>Joined View Output (<code>vw_student_registrations</code>)</span>
                    </div>
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Student</th>
                                    <th>Event Title</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRegistrations as $reg): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($reg['ticket_code']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($reg['student_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($reg['event_title']) ?></td>
                                        <td style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($reg['event_date']) ?></td>
                                        <td><span class="badge badge-success"><?= htmlspecialchars($reg['registration_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Theme synchronization with StudentHub localStorage -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const icon = document.getElementById('theme-toggle-icon');
            if (savedTheme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
                if (icon) icon.textContent = 'light_mode';
            }

            const btn = document.getElementById('theme-toggle-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    const isDark = document.body.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.body.removeAttribute('data-theme');
                        localStorage.setItem('theme', 'light');
                        if (icon) icon.textContent = 'dark_mode';
                    } else {
                        document.body.setAttribute('data-theme', 'dark');
                        localStorage.setItem('theme', 'dark');
                        if (icon) icon.textContent = 'light_mode';
                    }
                });
            }
        })();
    </script>
</body>
</html>
