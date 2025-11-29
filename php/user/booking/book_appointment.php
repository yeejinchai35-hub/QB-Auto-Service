<?php
// File: php/user/booking/book_appointment.php

// 1. DISABLE HTML ERROR OUTPUT
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 2. Set Header
header('Content-Type: application/json');

try {
    // 3. LOCATE CONFIG (always resolves to php/config.php)
    $configPath = dirname(__DIR__, 2) . '/config.php';
    if (!file_exists($configPath)) {
        throw new Exception("Server Error: config.php not found.");
    }
    require_once $configPath;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 4. CHECK LOGIN
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in before booking.']);
        exit;
    }

    $customerId = (int)$_SESSION['user']['id'];

    // 5. RECEIVE DATA
    $inputPhone = trim($_POST['phone'] ?? '');
    $plate = strtoupper(trim($_POST['plate'] ?? ''));
    $model = trim($_POST['model'] ?? '');
    $date  = $_POST['date'] ?? '';
    $time  = $_POST['time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $services = $_POST['service_type'] ?? [];

    // 6. VALIDATION: Required Fields
    if (empty($inputPhone) || empty($plate) || empty($model) || empty($date) || empty($time) || empty($services)) {
        throw new Exception("All fields including Phone Number are required.");
    }

    // 7. VALIDATION: Date (Must be TOMORROW or later)
    $today = date('Y-m-d');
    if ($date <= $today) {
        throw new Exception("You can only book appointments for tomorrow onwards.");
    }

    $mysqli->begin_transaction();

    try {
        // ------------------------------------------------------
        // RULE 1: Phone Must Match Account
        // ------------------------------------------------------
        function cleanPhone($p) { return preg_replace('/[^0-9]/', '', $p); }

        $stmtUser = $mysqli->prepare("SELECT phone FROM customers WHERE customer_id = ? LIMIT 1");
        $stmtUser->bind_param("i", $customerId);
        $stmtUser->execute();
        $userRes = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();

        if (!$userRes) throw new Exception("User account not found.");

        $dbPhone = cleanPhone($userRes['phone']);
        $formPhone = cleanPhone($inputPhone);

        if ($dbPhone !== $formPhone) {
            throw new Exception("The phone number entered does not match your registered account number.");
        }

        // ------------------------------------------------------
        // RULE 2: Car Plate Logic (Auto-Register or Validate)
        // ------------------------------------------------------
        $vehicleId = 0;
        
        $stmt = $mysqli->prepare("SELECT vehicle_id, customer_id FROM vehicles WHERE license_plate_number = ? LIMIT 1");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $vResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($vResult) {
            if ((int)$vResult['customer_id'] === $customerId) {
                $vehicleId = $vResult['vehicle_id'];
            } else {
                throw new Exception("This vehicle ($plate) is already registered to another user.");
            }
        } else {
            $stmtInsert = $mysqli->prepare("INSERT INTO vehicles (customer_id, license_plate_number, vehicle_model) VALUES (?, ?, ?)");
            $stmtInsert->bind_param("iss", $customerId, $plate, $model);
            if (!$stmtInsert->execute()) {
                throw new Exception("Failed to register new vehicle.");
            }
            $vehicleId = $stmtInsert->insert_id;
            $stmtInsert->close();
        }

        // ------------------------------------------------------
        // RULE 3: EXISTING ACTIVE BOOKING CHECK
        // ------------------------------------------------------
        // Check if this user already has an active job (Pending, Scheduled, In Progress) 
        // for this specific vehicle, regardless of date/time.
        
        $stmtDup = $mysqli->prepare("
            SELECT appointment_id 
            FROM appointments 
            WHERE customer_id = ? 
              AND vehicle_id = ? 
              AND status IN ('Pending', 'Scheduled', 'In Progress')
            LIMIT 1
        ");
        
        // We only bind customer_id and vehicle_id now (integers)
        $stmtDup->bind_param("ii", $customerId, $vehicleId);
        $stmtDup->execute();
        $stmtDup->store_result();
        
        if ($stmtDup->num_rows > 0) {
            $stmtDup->close();
            // New error message
            throw new Exception("You already have an active appointment for this vehicle ($plate). Please wait until the current service is completed before booking again.");
        }
        $stmtDup->close();

        // ------------------------------------------------------
        // CREATE APPOINTMENT
        // ------------------------------------------------------
        $stmtAppt = $mysqli->prepare("INSERT INTO appointments (customer_id, vehicle_id, preferred_date, preferred_time, additional_notes, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmtAppt->bind_param("iisss", $customerId, $vehicleId, $date, $time, $notes);
        
        if (!$stmtAppt->execute()) {
            throw new Exception("Failed to save appointment.");
        }
        $appointmentId = $stmtAppt->insert_id;
        $stmtAppt->close();

        // ------------------------------------------------------
        // SAVE SERVICES
        // ------------------------------------------------------
        $stmtSvc = $mysqli->prepare("INSERT INTO appointment_services (appointment_id, service_name) VALUES (?, ?)");
        foreach ($services as $serviceName) {
            if (!empty($serviceName)) {
                $stmtSvc->bind_param("is", $appointmentId, $serviceName);
                $stmtSvc->execute();
            }
        }
        $stmtSvc->close();

        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully!']);

    } catch (Exception $dbError) {
        $mysqli->rollback();
        throw new Exception($dbError->getMessage());
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>