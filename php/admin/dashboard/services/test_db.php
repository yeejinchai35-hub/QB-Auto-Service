<?php
// test_db.php - RUN THIS IN YOUR BROWSER
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__, 3) . '/auth/auth.php';

$id = 19; // The ID from your screenshot

echo "<h1>Debug Report for ID: $id</h1>";

// 1. Check raw database value
$stmt = $mysqli->prepare("SELECT appointment_id, status, progress_step FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row) {
    echo "<p><strong>Found Row:</strong> <pre>" . print_r($row, true) . "</pre></p>";
    echo "<p>The 'progress_step' value in DB is: <strong>" . $row['progress_step'] . "</strong> (Should be 2)</p>";
} else {
    echo "<p style='color:red'>Row not found!</p>";
}?>