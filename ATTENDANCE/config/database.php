<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost     = getenv('DB_HOST') ?: '://tidbcloud.com';
$dbPort     = (int)(getenv('DB_PORT') ?: 4000);
$dbUser     = getenv('DB_USER') ?: '2GgocvPUJFzaTaZ.root';
$dbPassword = getenv('DB_PASSWORD') ?: 'PASTE_YOUR_TIDB_PASSWORD_HERE';
$dbName     = getenv('DB_NAME') ?: 'student_attendance_db';

try {
    $conn = mysqli_init();
    $conn->ssl_set(null, null, null, null, null);
    $conn->real_connect($dbHost, $dbUser, $dbPassword, $dbName, $dbPort, null, MYSQLI_CLIENT_SSL);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database.');
}
