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
    'students'      => ['exists' => false, 'rows' => 0, 'pk' => 'student_id'],
    'events'        => ['exists' => false, 'rows' => 0, 'pk' => 'event_id'],
    'registrations' => ['exists' => false, 'rows' => 0, 'pk' => 'registration_id'],
];
$recentRegistrations = [];
$preparedStatementTested = false;
$preparedStatementOutput = '';
$actionMessage = '';
$actionError   = '';

// -------------------------------------------------------------
// Helper: Execute a .sql / .db file using PDO
// -------------------------------------------------------------
function executeSqlFile(PDO $pdo, string $filePath): bool {
    if (!file_exists($filePath)) {
        throw new Exception("File not found at: {$filePath}");
    }
    $sql = file_get_contents($filePath);
    if ($sql === false || empty(trim($sql))) {
        throw new Exception("File is empty: {$filePath}");
    }
    $pdo->exec($sql);
    return true;
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
            $dumpPath = realpath(__DIR__ . '/../sql/studenthub_full_dump.sql');
            executeSqlFile($pdoServer, $dumpPath);
            $actionMessage = "All 3 entities (students, events, registrations) and views imported successfully!";
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        :root {
            --bg: #090d16;
            --surface: #121824;
            --surface-card: #182234;
            --border: #26354a;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --accent: #38bdf8;
            --accent-hover: #0ea5e9;
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.12);
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --warning: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .container {
            width: 100%;
            max-width: 840px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .header {
            padding: 28px 36px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: linear-gradient(180deg, rgba(56, 189, 248, 0.06) 0%, transparent 100%);
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: rgba(56, 189, 248, 0.12);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .header-text h1 {
            font-size: 1.35rem;
            font-weight: 700;
        }

        .header-text p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 3px;
        }

        .content {
            padding: 32px 36px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .banner {
            padding: 16px 20px;
            border-radius: 12px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .banner.success { background: var(--success-bg); border: 1px solid var(--success); color: #6ee7b7; }
        .banner.error   { background: var(--danger-bg); border: 1px solid var(--danger); color: #fca5a5; }
        .banner.warning { background: var(--warning-bg); border: 1px solid var(--warning); color: #fde68a; }

        .banner .material-symbols-outlined { font-size: 24px; flex-shrink: 0; margin-top: 2px; }

        .server-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .server-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
        }

        .server-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .server-value {
            font-size: 0.92rem;
            font-weight: 600;
            font-family: monospace;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: var(--success-bg); border: 1px solid var(--success); color: #6ee7b7; }
        .badge-danger  { background: var(--danger-bg); border: 1px solid var(--danger); color: #fca5a5; }

        .section-card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 24px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .section-title .material-symbols-outlined {
            color: var(--accent);
            font-size: 22px;
        }

        .table-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .entity-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .entity-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .entity-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
            margin-top: 10px;
        }

        .data-table th, .data-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.74rem;
            text-transform: uppercase;
        }

        .actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary { background: var(--accent); color: #031525; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); }

        .btn-secondary { background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }
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
            <div>
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

            <!-- Schema Consistency: 3 Entities -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined">schema</span>
                        <span>Relational Schema Verification (3 Entities)</span>
                    </div>
                    <div>
                        <span class="badge <?= ($tablesStatus['students']['exists'] && $tablesStatus['events']['exists'] && $tablesStatus['registrations']['exists']) ? 'badge-success' : 'badge-warning' ?>">
                            <?= ($tablesStatus['students']['exists'] && $tablesStatus['events']['exists'] && $tablesStatus['registrations']['exists']) ? 'All 3 Entities Consistent' : 'Pending Import' ?>
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
                        <div class="entity-meta" style="color: #6ee7b7; font-size: 0.75rem;">Source: <code>sql/student.db</code></div>
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
                        <div class="entity-meta" style="color: #6ee7b7; font-size: 0.75rem;">Source: <code>sql/events.db</code></div>
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
                        <div class="entity-meta" style="color: #6ee7b7; font-size: 0.75rem;">Source: <code>sql/registration.db</code></div>
                    </div>
                </div>

                <div class="actions-row">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="import_all">
                        <button type="submit" class="btn btn-primary" <?= !$serverConnected ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined">rocket_launch</span>
                            <?= $dbExists ? 'Re-Sync All 3 DB Entities' : 'Import Complete Schema' ?>
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
                    <div class="section-title" style="margin-bottom: 8px;">
                        <span class="material-symbols-outlined" style="color: #10b981;">verified_user</span>
                        <span>Practical 8 Extension: Prepared Statement Verified</span>
                    </div>
                    <p style="font-size: 0.85rem; color: #a7f3d0; background: rgba(16, 185, 129, 0.1); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                        <?= htmlspecialchars($preparedStatementOutput) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Live Sample Registrations View -->
            <?php if (!empty($recentRegistrations)): ?>
                <div class="section-card">
                    <div class="section-title" style="margin-bottom: 8px;">
                        <span class="material-symbols-outlined">preview</span>
                        <span>Joined View Output (<code>vw_student_registrations</code>)</span>
                    </div>
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
                                    <td><code style="color: var(--accent);"><?= htmlspecialchars($reg['ticket_code']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($reg['student_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($reg['event_title']) ?></td>
                                    <td style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($reg['event_date']) ?></td>
                                    <td><span class="badge badge-success"><?= htmlspecialchars($reg['registration_status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>
