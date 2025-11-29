<?php
// Central DB connection + secure session setup
// Adjust credentials if your MySQL differs

declare(strict_types=1);

// Hardened session cookie (safe defaults for localhost)
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define root path for includes
define('ROOT_PATH', dirname(__DIR__));

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'qb_db'; // Change if your DB name is different

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');

// Helper to redirect safely
function redirect(string $path): void {
    header('Location: ' . $path);
    exit();
}
