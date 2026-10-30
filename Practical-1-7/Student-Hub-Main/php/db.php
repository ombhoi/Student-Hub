<?php
/**
 * Practical 8: StudentHub Database Configuration & PDO Connection
 * File: php/db.php
 * Description: Secure, centralized PDO connection handler using prepared statements.
 */

// XAMPP Default MySQL Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'studenthub_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a configured PDO database connection instance.
 *
 * @param bool $selectDb Whether to connect directly to DB_NAME or the MySQL daemon
 * @param string|null $dbName Specific database name (defaults to DB_NAME)
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(bool $selectDb = true, ?string $dbName = null): PDO {
    $targetDb = $dbName ?? DB_NAME;
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
    
    if ($selectDb && !empty($targetDb)) {
        $dsn .= ";dbname=" . $targetDb;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Native prepared statements
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
