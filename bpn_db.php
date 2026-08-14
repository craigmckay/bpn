<?php
/**
 * Database connection.
 *
 * Credentials live in config.php, which is gitignored.
 * See config.sample.php for the expected shape.
 */

$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    die('Missing config.php - copy config.sample.php to config.php and set your database credentials.');
}

$config = require $configFile;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database']
    );
    $conn->set_charset($config['charset'] ?? 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Log the detail, show the user nothing useful to an attacker.
    error_log('BPN database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Database unavailable. Please try again later.');
}
