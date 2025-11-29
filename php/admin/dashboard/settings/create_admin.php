<?php
// FILE: php/admin/dashboard/settings/create_admin.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/auth/auth.php';

// Ensure only logged-in admins can access
$currentAdminId = $ADMIN_ID;
if ($currentAdminId === 0) {
    header("Location: ../../../../home.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['new_username'] ?? '');
    $email    = trim($_POST['new_email'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['new_password_confirm'] ?? ''; // <--- Capture Confirm Field

    $errors = [];

    // 1. Basic Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $errors[] = "All fields are required.";
    }

    // 2. Check if passwords match
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    // 3. Check if Email or Username is already registered
    if (empty($errors)) {
        if ($stmt = $mysqli->prepare("SELECT admin_id FROM admins WHERE email = ? OR username = ? LIMIT 1")) {
            $stmt->bind_param('ss', $email, $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $errors[] = "That email or username is already registered.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error checking duplicates.";
        }
    }

    // 4. Create the Admin
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($stmt = $mysqli->prepare("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())")) {
            // NOTE: Make sure your DB column is 'password_hash'. If it is 'password', change it here.
            $stmt->bind_param('sss', $username, $email, $passwordHash);
            
            if ($stmt->execute()) {
                $_SESSION['admin_settings_message'] = "New admin account created successfully.";
                header("Location: admin_settings.php");
                exit();
            } else {
                $errors[] = "Failed to insert admin into database.";
            }
            $stmt->close();
        }
    }

    // If we reached here, there were errors
    $_SESSION['admin_settings_errors'] = $errors;
    header("Location: admin_settings.php");
    exit();
}
?>