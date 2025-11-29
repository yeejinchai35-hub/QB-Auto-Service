<?php
// FILE: php/admin/dashboard/services/fetch_progress_data.php

// 1. Prevent Caching (Forces browser to get fresh data every time)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// 2. Turn off error printing so it doesn't break the JSON
error_reporting(0); 
ini_set('display_errors', 0);

session_start();

// 3. Connect to Database (Adjust path if needed)
require_once dirname(__DIR__, 3) . '/auth/auth.php';

$response = ['success' => false, 'message' => 'Initializing...'];
$apptId = 0;

// 4. Get the Appointment ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $apptId = intval($_GET['id']);
} elseif (isset($_SESSION['user']['id'])) {
    // If no ID provided, find the latest active one for the user
    $uId = $_SESSION['user']['id'];
    $stmt = $mysqli->prepare("SELECT appointment_id FROM appointments WHERE customer_id = ? AND status != 'Cancelled' ORDER BY appointment_id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $uId);
        $stmt->execute();
        $stmt->bind_result($foundId);
        if ($stmt->fetch()) {
            $apptId = $foundId;
        }
        $stmt->close();
    }
}

// 5. Fetch the Data (THE CRITICAL PART)
if ($apptId > 0) {
    // We select 'progress_step' because that is the column that exists in your DB.
    // We rename it 'as progress' so the JavaScript understands it.
    $sql = "SELECT 
                a.appointment_id, 
                a.status, 
                a.progress_step as progress, 
                v.vehicle_model as vehicle, 
                v.license_plate_number as license_plate
            FROM appointments a
            LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
            WHERE a.appointment_id = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $apptId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $response = [
                'success' => true,
                'data' => [
                    'appointment_id' => $row['appointment_id'],
                    'status' => $row['status'],
                    'vehicle' => $row['vehicle'],
                    'license_plate' => $row['license_plate'],
                    'progress' => (int)$row['progress'] // Force it to be a number
                ]
            ];
        } else {
            $response['message'] = 'Appointment ID not found in database.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Database query failed.';
    }
} else {
    $response['message'] = 'No active appointment ID found.';
}

echo json_encode($response);
exit;
?>