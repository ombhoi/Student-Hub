<?php
/**
 * Practical 8: Live MySQL Events JSON Endpoint
 * File: php/api_db_events.php
 * Description: Fetches events directly from MySQL database using PDO prepared statements
 *              and outputs JSON identical to data/events.json contract.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $pdo = getDBConnection(true);

    $sql = "SELECT 
                event_id AS id,
                title,
                description,
                category,
                badge,
                location,
                DATE_FORMAT(event_date, '%b %d, %Y') AS date,
                DATE_FORMAT(event_date, '%h:%i %p') AS time,
                DATE_FORMAT(event_date, '%b %d, %Y • %h:%i %p') AS dateTime,
                image,
                max_capacity,
                status
            FROM events
            ORDER BY event_date ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'source'  => 'MySQL Database (Practical 8)',
        'count'   => count($events),
        'events'  => $events
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'source'  => 'MySQL Database',
        'error'   => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
