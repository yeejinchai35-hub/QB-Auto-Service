<?php
// Protect admin-only pages
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin']) || !isset($_SESSION['admin']['id'])) {
    // Admin not logged in, redirect to homepage or login
    redirect('/project/Capstone-Car-Service-Draft4/home.html');
}

// Convenience variables with fallback
$ADMIN_ID = (int)($_SESSION['admin']['id'] ?? 0);
$ADMIN_USERNAME = htmlspecialchars($_SESSION['admin']['username'] ?? $_SESSION['admin']['email'] ?? 'Admin');

// --- GLOBAL UTILITY FUNCTIONS ---

// Function to safely escape HTML output
if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Function to generate CSS class based on status string
if (!function_exists('statusClass')) {
    function statusClass(string $value): string {
        return strtolower(str_replace(' ', '-', $value));
    }
}

// Function to generate the title color/class based on status (for view_customer.php)
if (!function_exists('getStatusColor')) {
    function getStatusColor(string $status): string {
        return match(strtolower($status)) {
            'completed' => 'success',
            'scheduled' => 'warning', // Use warning for scheduled
            'in progress' => 'info',
            'pending' => 'secondary', // Use secondary for pending in Admin view
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }
}