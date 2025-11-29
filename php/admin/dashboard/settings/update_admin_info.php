<?php
// FILE: php/admin/dashboard/settings/update_admin_info.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/auth/auth.php';

$currentAdminId = $ADMIN_ID;
if ($currentAdminId === 0) {
    header("Location: ../../../../home.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $newPass = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $errors = [];

    // 1. Basic Validation
    if (empty($username) || empty($email)) {
        $errors[] = "Display Name and Email are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // 2. Duplicate Check (Exclude Self)
    // We check if the email exists for ANY admin ID that is NOT ME.
    if (empty($errors)) {
        $sql = "SELECT admin_id FROM admins WHERE (email = ? OR username = ?) AND admin_id != ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('ssi', $email, $username, $currentAdminId);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $errors[] = "That email or username is already taken by another admin.";
            }
            $stmt->close();
        }
    }

    // 3. Password Logic
    $updatePassword = false;
    $passwordHash = '';

    if (!empty($newPass)) {
        if ($newPass !== $confirmPass) {
            $errors[] = "New passwords do not match.";
        } elseif (strlen($newPass) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        } else {
            $updatePassword = true;
            $passwordHash = password_hash($newPass, PASSWORD_DEFAULT);
        }
    }

    // 4. Update Database
    if (empty($errors)) {
        if ($updatePassword) {
            // Update with password
            $sql = "UPDATE admins SET username = ?, email = ?, phone = ?, password = ? WHERE admin_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ssssi', $username, $email, $phone, $passwordHash, $currentAdminId);
        } else {
            // Update without password
            $sql = "UPDATE admins SET username = ?, email = ?, phone = ? WHERE admin_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sssi', $username, $email, $phone, $currentAdminId);
        }

        if ($stmt->execute()) {
            // Update Session Data immediately so the UI reflects changes
            $_SESSION['admin']['username'] = $username;
            $_SESSION['admin']['email'] = $email;
            
            $_SESSION['admin_settings_message'] = "Account details updated successfully.";
            header("Location: admin_settings.php");
            exit();
        } else {
            $errors[] = "Database update failed.";
        }
        $stmt->close();
    }

    // Handle Errors
    $_SESSION['admin_settings_errors'] = $errors;
    header("Location: admin_settings.php");
    exit();
}
?>