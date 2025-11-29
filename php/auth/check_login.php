<?php
session_start();
header('Content-Type: application/json');

$isUser  = isset($_SESSION['user']['id']);
$isAdmin = isset($_SESSION['admin']['id']);
$role    = $isAdmin ? 'admin' : ($isUser ? 'user' : null);

echo json_encode([
    'loggedIn' => $isUser || $isAdmin,
    'role'     => $role,
]);
