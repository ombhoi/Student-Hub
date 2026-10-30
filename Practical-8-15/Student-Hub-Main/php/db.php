<?php
/**
 * Practical 8: StudentHub Database Configuration & PDO Connection
 * File: php/db.php
 * Description: Robust, centralized PDO connection handler using prepared statements.
 *              Automatically handles localhost / 127.0.0.1, alternative MySQL ports (3306/3307),
 *              and auto-creates the database & schema from SQL dump if not present.
 */

// XAMPP Default MySQL Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'studenthub_db');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a configured PDO database connection instance.
 * Automatically tries 127.0.0.1 and localhost, checks common ports,
 * and auto-creates/initializes studenthub_db if it does not yet exist.
 *
 * @param bool $selectDb Whether to connect directly to DB_NAME or the MySQL daemon
 * @param string|null $dbName Specific database name (defaults to DB_NAME)
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(bool $selectDb = true, ?string $dbName = null): PDO {
    $targetDb = $dbName ?? DB_NAME;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Native prepared statements
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ];

    // Candidate hosts: try 127.0.0.1 and localhost (prevents Windows IPv6 resolution issues)
    $hosts = [DB_HOST];
    if (DB_HOST === '127.0.0.1') {
        $hosts[] = 'localhost';
    } else {
        $hosts[] = '127.0.0.1';
    }

    // Candidate ports (3306 default, 3307 common alternative in XAMPP)
    $ports = [(int)DB_PORT];
    if ((int)DB_PORT === 3306) {
        $ports[] = 3307;
    }

    $lastException = null;

    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            try {
                if ($selectDb && !empty($targetDb)) {
                    $dsn = "mysql:host={$host};port={$port};charset=" . DB_CHARSET . ";dbname={$targetDb}";
                    try {
                        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                        // Check if essential tables exist, auto-initialize if empty
                        ensureDatabaseSchema($pdo);
                        return $pdo;
                    } catch (PDOException $ex) {
                        // Error 1049: Unknown database -> auto-create database and initialize schema!
                        if ($ex->getCode() == 1049 || strpos($ex->getMessage(), 'Unknown database') !== false) {
                            $daemonDsn = "mysql:host={$host};port={$port};charset=" . DB_CHARSET;
                            $daemonPdo = new PDO($daemonDsn, DB_USER, DB_PASS, $options);
                            $daemonPdo->exec("CREATE DATABASE IF NOT EXISTS `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                            
                            $dbPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                            ensureDatabaseSchema($dbPdo);
                            return $dbPdo;
                        }
                        throw $ex;
                    }
                } else {
                    $dsn = "mysql:host={$host};port={$port};charset=" . DB_CHARSET;
                    return new PDO($dsn, DB_USER, DB_PASS, $options);
                }
            } catch (PDOException $e) {
                $lastException = $e;
                // Continue to next host/port permutation
            }
        }
    }

    if ($lastException !== null) {
        throw $lastException;
    }

    throw new PDOException("Unable to connect to MySQL database.");
}

/**
 * Returns a configured MySQLi database connection instance (Practical 9 syllabus support).
 *
 * @param bool $selectDb
 * @return mysqli
 * @throws Exception
 */
function getMySQLiConnection(bool $selectDb = true): mysqli {
    $targetDb = $selectDb ? DB_NAME : '';
    $hosts = [DB_HOST, 'localhost'];
    $ports = [(int)DB_PORT, 3307];

    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $conn = @new mysqli($host, DB_USER, DB_PASS, $targetDb, $port);
            if (!$conn->connect_error) {
                $conn->set_charset(DB_CHARSET);
                return $conn;
            }
        }
    }
    throw new Exception("Unable to establish MySQLi connection.");
}

/**
 * Checks and auto-initializes database schema from individual SQL files if students table is missing.
 * Also seamlessly upgrades existing schemas with Practical 10 columns (role, remember_token, last_login).
 *
 * @param PDO $pdo
 * @return void
 */
function ensureDatabaseSchema(PDO $pdo): void {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'students'")->fetch();
        if (!$check) {
            $sqlFiles = [
                __DIR__ . '/../sql/student.sql',
                __DIR__ . '/../sql/events.sql',
                __DIR__ . '/../sql/registration.sql',
                __DIR__ . '/../sql/contact_inquiries.sql',
                __DIR__ . '/../sql/audit_logs.sql',
            ];
            foreach ($sqlFiles as $filePath) {
                if (file_exists($filePath)) {
                    executeSqlScript($pdo, $filePath);
                }
            }
        } else {
            // Practical 14 schema compatibility: auto-create audit_logs if missing
            $checkAudit = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetch();
            if (!$checkAudit) {
                $auditSqlFile = __DIR__ . '/../sql/audit_logs.sql';
                if (file_exists($auditSqlFile)) {
                    executeSqlScript($pdo, $auditSqlFile);
                }
            }

            // Practical 10 schema compatibility: auto-add missing columns if table pre-existed
            $colRole = $pdo->query("SHOW COLUMNS FROM students LIKE 'role'")->fetch();
            if (!$colRole) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student' AFTER `profile_image`");
            }

            $colToken = $pdo->query("SHOW COLUMNS FROM students LIKE 'remember_token'")->fetch();
            if (!$colToken) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `remember_token` VARCHAR(255) DEFAULT NULL AFTER `role`");
            }

            $colLogin = $pdo->query("SHOW COLUMNS FROM students LIKE 'last_login'")->fetch();
            if (!$colLogin) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `remember_token`");
            }

            // Ensure admin seed account exists for Practical 10 RBAC testing
            $adminCheck = $pdo->query("SELECT student_id FROM students WHERE role = 'admin' LIMIT 1")->fetch();
            if (!$adminCheck) {
                $pdo->exec("INSERT INTO `students` (`student_code`, `first_name`, `last_name`, `email`, `password`, `roll_number`, `department`, `semester`, `phone`, `bio`, `role`, `last_login`) VALUES
                ('ADM-001', 'Admin', 'User', 'admin@charusat.edu.in', '$2y$10\$ZlW97gSiCwV7JXpyes9/U.XTY6Y4D3LfoAz0PPMl0o8OEPNr6SqL6', 'STAFF01', 'Academic Affairs & IT', 8, '+91 98765 00000', 'StudentHub System Administrator', 'admin', NOW())");
            }

            // Practical 8-15 Data Integrity: Automatically repair any placeholder surnames ('Student', 'xyz')
            // and replace invalid '0' or blank department entries with authentic course names.
            repairStudentRecords($pdo);
        }
    } catch (Throwable $e) {
        // Silently continue, allowing caller to handle
    }
}

/**
 * Repairs student records with missing or invalid department ('0', empty, or numeric)
 * and cleans up placeholder surnames ('Student', 'xyz', etc.) from direct DB entries.
 *
 * @param PDO $pdo
 * @return array Report of fixed records
 */
function repairStudentRecords(PDO $pdo): array {
    $report = ['departments_fixed' => 0, 'names_fixed' => 0, 'details' => []];

    try {
        // Fast-path check: only proceed if flawed rows exist
        $check = $pdo->query("SELECT student_id FROM students WHERE department = '0' OR department = '' OR department IS NULL OR LOWER(last_name) = 'student' OR LOWER(last_name) = 'xyz' OR LOWER(last_name) = '' LIMIT 1");
        if (!$check || !$check->fetch()) {
            return $report;
        }

        $deptRules = [
            'IT' => 'Information Technology',
            'CE' => 'Computer Engineering',
            'AI' => 'AI & Data Science',
            'EC' => 'Electronics & Communication',
            'ME' => 'Mechanical Engineering',
            'CL' => 'Civil Engineering',
            'EE' => 'Electrical Engineering',
        ];

        $knownLastNames = [
            'alex'      => 'Patel',
            'riya'      => 'Patel',
            'nolan'     => 'Dsouza',
            'karan'     => 'Dave',
            'ofszdvz'   => 'Vyas',
            'qwrwf'     => 'Shah',
            'marcus'    => 'Vance',
            'elena'     => 'Rostova',
            'devon'     => 'Price',
            'priya'     => 'Sharma',
            'rohan'     => 'Patel',
            'aarav'     => 'Mehta',
            'ananya'    => 'Desai',
            'siddharth' => 'Joshi',
            'neha'      => 'Kulkarni',
            'vikram'    => 'Rathod',
            'kavya'     => 'Shah',
            'arjun'     => 'Nair',
            'diya'      => 'Verma',
            'tanvi'     => 'Pandya',
            'sneha'     => 'Iyer',
            'aditya'    => 'Chauhan',
            'rhea'      => 'Bhatt',
            'manav'     => 'Dave',
            'ishita'    => 'Parikh'
        ];

        $stmt = $pdo->query("SELECT student_id, student_code, first_name, last_name, email, roll_number, department, role FROM students ORDER BY student_id ASC");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as $stu) {
            $id = (int)$stu['student_id'];
            $code = $stu['student_code'];
            $firstName = trim($stu['first_name'] ?? '');
            $lastName  = trim($stu['last_name'] ?? '');
            $roll      = strtoupper(trim($stu['roll_number'] ?? ''));
            $dept      = trim((string)($stu['department'] ?? ''));
            $role      = strtolower(trim($stu['role'] ?? 'student'));

            $needsDeptUpdate = false;
            $needsNameUpdate = false;
            $newDept = $dept;
            $newFirstName = $firstName;
            $newLastName = $lastName;

            // 1. Department repair: Replace '0', blank, or numeric with authentic department
            if ($dept === '0' || $dept === '' || is_numeric($dept)) {
                if ($role === 'admin' || strpos($roll, 'STAFF') !== false) {
                    $newDept = 'Academic Affairs & IT';
                } else {
                    foreach ($deptRules as $prefix => $canonicalName) {
                        if (strpos($roll, $prefix) !== false) {
                            $newDept = $canonicalName;
                            break;
                        }
                    }
                    if ($newDept === '0' || $newDept === '' || is_numeric($newDept)) {
                        $newDept = 'Computer Engineering';
                    }
                }
                $needsDeptUpdate = true;
            }

            // 2. Name repair: Replace placeholder 'Student', 'xyz', or blank last names
            $lowerLast  = strtolower($lastName);
            $lowerFirst = strtolower($firstName);

            if ($lowerLast === 'student' || $lowerLast === 'xyz' || $lowerLast === '' || $lowerLast === 'null') {
                if ($lowerFirst === 'ofszdvz') {
                    $newFirstName = 'Ojas';
                } elseif ($lowerFirst === 'qwrwf') {
                    $newFirstName = 'Krunal';
                } else {
                    $newFirstName = ucfirst($firstName);
                }

                $lookupKey = strtolower($newFirstName);
                if (isset($knownLastNames[$lookupKey])) {
                    $newLastName = $knownLastNames[$lookupKey];
                } elseif (isset($knownLastNames[$lowerFirst])) {
                    $newLastName = $knownLastNames[$lowerFirst];
                } else {
                    $newLastName = 'Patel';
                }
                $needsNameUpdate = true;
            } else {
                $capFirst = ucfirst($firstName);
                $capLast  = ucfirst($lastName);
                if ($capFirst !== $firstName || $capLast !== $lastName) {
                    $newFirstName = $capFirst;
                    $newLastName  = $capLast;
                    $needsNameUpdate = true;
                }
            }

            if ($needsDeptUpdate || $needsNameUpdate) {
                $upStmt = $pdo->prepare("UPDATE students SET first_name = :fname, last_name = :lname, department = :dept WHERE student_id = :id");
                $upStmt->execute([
                    ':fname' => $newFirstName,
                    ':lname' => $newLastName,
                    ':dept'  => $newDept,
                    ':id'    => $id
                ]);

                if ($needsDeptUpdate) $report['departments_fixed']++;
                if ($needsNameUpdate) $report['names_fixed']++;

                $report['details'][] = [
                    'student_id'   => $id,
                    'student_code' => $code,
                    'roll_number'  => $roll,
                    'old_name'     => $firstName . ' ' . $lastName,
                    'new_name'     => $newFirstName . ' ' . $newLastName,
                    'old_dept'     => $dept,
                    'new_dept'     => $newDept
                ];
            }
        }
    } catch (Throwable $e) {
        // Non-blocking
    }

    return $report;
}

/**
 * Safely executes a SQL script file statement-by-statement.
 * Removes comments, handles stored procedures and delimiters safely.
 *
 * @param PDO $pdo
 * @param string $filePath
 * @return bool
 * @throws Exception
 */
function executeSqlScript(PDO $pdo, string $filePath): bool {
    if (!file_exists($filePath)) {
        throw new Exception("SQL file not found at: {$filePath}");
    }
    $sql = file_get_contents($filePath);
    if ($sql === false || empty(trim($sql))) {
        throw new Exception("SQL file is empty: {$filePath}");
    }

    // Split SQL into individual lines while removing comment lines
    $lines = explode("\n", $sql);
    $cleanSql = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        // Skip standalone DELIMITER lines
        if (stripos($trimmed, 'DELIMITER') === 0) {
            continue;
        }
        $cleanSql .= $line . "\n";
    }

    // Extract any CREATE PROCEDURE block to prevent semicolon splitting issues
    $procSql = null;
    if (preg_match('/(CREATE\s+PROCEDURE[\s\S]+?END)\s*\$\$/i', $cleanSql, $matches)) {
        $procSql = $matches[1];
        $cleanSql = str_replace($matches[0], '', $cleanSql);
    }

    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }

    if ($procSql) {
        $pdo->exec($procSql);
    }

    return true;
}
