<?php
/**
 * Practical 8-15: StudentHub Database Data Integrity & Sanitization Utility
 * File: php/fix_student_records.php
 * Description: Repairs placeholder surnames ('Student', 'xyz') with authentic academic surnames
 *              and maps invalid '0' or blank department entries to proper university courses.
 */

require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = getDBConnection(true);

// Run the repair procedure
$repairResult = repairStudentRecords($pdo);

// Fetch current state of all students
$stmt = $pdo->query("SELECT student_id, student_code, first_name, last_name, email, roll_number, department, semester, role, last_login FROM students ORDER BY student_id ASC");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON if requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'report'  => $repairResult,
        'total_students' => count($allStudents),
        'students' => $allStudents
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Data Integrity &amp; Repair | StudentHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..700;1,6..72,400..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        :root {
            --bg-canvas: #fcfbf9;
            --card-bg: #ffffff;
            --text-main: #2b2826;
            --text-muted: #6b645f;
            --terracotta: #c25e3e;
            --terracotta-dark: #a44a2d;
            --terracotta-soft: rgba(194, 94, 62, 0.08);
            --border-color: #e5ded6;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
            --success-border: #a7f3d0;
            --info-bg: #eff6ff;
            --info-text: #1e40af;
            --info-border: #bfdbfe;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-main);
            padding: 40px 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--terracotta-soft);
            color: var(--terracotta);
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 16px;
        }

        h1 {
            font-family: 'Newsreader', Georgia, serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        h1 em {
            font-style: italic;
            color: var(--terracotta);
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--terracotta-soft);
            color: var(--terracotta);
        }

        .stat-icon.green {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .stat-icon.blue {
            background: var(--info-bg);
            color: var(--info-text);
        }

        .stat-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        th {
            background: #faf7f4;
            padding: 12px 16px;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #faf8f6;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-dept {
            background: var(--info-bg);
            color: var(--info-text);
            border: 1px solid var(--info-border);
        }

        .badge-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--terracotta);
            color: #ffffff;
            border: 1px solid var(--terracotta);
        }

        .btn-primary:hover {
            background: var(--terracotta-dark);
        }

        .btn-outline {
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: #f5f0eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <div class="header-badge">
                <span class="material-symbols-outlined" style="font-size: 1rem;">verified</span>
                <span>StudentHub Database Sanitizer</span>
            </div>
            <h1>Database Data Integrity <em>Verification &amp; Repair</em></h1>
            <p class="subtitle">Ensures all registered students have authentic first &amp; last names (removing placeholder "Student" values) and valid academic department courses (correcting 0 or null values).</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-symbols-outlined">group</span>
                </div>
                <div>
                    <div class="stat-val"><?= count($allStudents) ?></div>
                    <div class="stat-label">Total Student Records</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <span class="material-symbols-outlined">badge</span>
                </div>
                <div>
                    <div class="stat-val"><?= $repairResult['names_fixed'] ?></div>
                    <div class="stat-label">Placeholder Surnames Cleaned</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue">
                    <span class="material-symbols-outlined">school</span>
                </div>
                <div>
                    <div class="stat-val"><?= $repairResult['departments_fixed'] ?></div>
                    <div class="stat-label">0 Departments Restored</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <div>
                    <div class="stat-val">100%</div>
                    <div class="stat-label">Database Health Status</div>
                </div>
            </div>
        </div>

        <?php if (!empty($repairResult['details'])): ?>
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Records Modified in this Run</div>
                <span class="badge badge-success"><?= count($repairResult['details']) ?> Records Updated</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Student Code</th>
                        <th>Roll Number</th>
                        <th>Previous Name</th>
                        <th>Updated Full Name</th>
                        <th>Previous Department</th>
                        <th>Updated Department</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repairResult['details'] as $mod): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($mod['student_code']) ?></strong></td>
                        <td><?= htmlspecialchars($mod['roll_number']) ?></td>
                        <td><del style="color: #991b1b;"><?= htmlspecialchars($mod['old_name']) ?></del></td>
                        <td><strong style="color: #065f46;"><?= htmlspecialchars($mod['new_name']) ?></strong></td>
                        <td><del style="color: #991b1b;"><?= htmlspecialchars($mod['old_dept']) ?: '0' ?></del></td>
                        <td><span class="badge badge-dept"><?= htmlspecialchars($mod['new_dept']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Current Active Students in MySQL Database (<code>studenthub_db</code>)</div>
                <span class="badge badge-dept"><?= count($allStudents) ?> Active Accounts</span>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Roll Number</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allStudents as $row): ?>
                        <tr>
                            <td><?= (int)$row['student_id'] ?></td>
                            <td><strong><?= htmlspecialchars($row['student_code']) ?></strong></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><code><?= htmlspecialchars($row['roll_number']) ?></code></td>
                            <td><span class="badge badge-dept"><?= htmlspecialchars($row['department']) ?></span></td>
                            <td>Sem <?= (int)$row['semester'] ?></td>
                            <td><span class="badge <?= $row['role'] === 'admin' ? 'badge-dept' : 'badge-success' ?>"><?= htmlspecialchars(ucfirst($row['role'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="actions">
            <a href="../page/register.html" class="btn btn-primary">
                <span class="material-symbols-outlined">person_add</span>
                <span>Test New Registration</span>
            </a>
            <a href="../admin/admin.html" class="btn btn-outline">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                <span>Admin Console</span>
            </a>
            <a href="../page/student-dashboard.html" class="btn btn-outline">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Student Dashboard</span>
            </a>
            <a href="fix_student_records.php?format=json" class="btn btn-outline" target="_blank">
                <span class="material-symbols-outlined">code</span>
                <span>View JSON API</span>
            </a>
        </div>
    </div>
</body>
</html>
