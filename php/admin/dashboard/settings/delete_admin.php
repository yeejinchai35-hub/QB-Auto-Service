<?php
// FILE: php/admin/dashboard/settings/delete_admin.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/auth/auth.php';

$currentAdminId = $ADMIN_ID;
if ($currentAdminId === 0) {
    header("Location: ../../../../website/home/home.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm_delete'] ?? '');

    if ($confirm === 'DELETE') {
        if ($stmt = $mysqli->prepare("DELETE FROM admins WHERE admin_id = ? LIMIT 1")) {
            $stmt->bind_param('i', $currentAdminId);
            
            if ($stmt->execute()) {
                session_unset();
                session_destroy();
                
                // --- CHANGED: Added ?status=account_deleted to the URL ---
                header("Location: ../../../../website/home/home.html?status=account_deleted"); 
                exit();
            } else {
                $_SESSION['admin_settings_errors'] = ["Database error: Could not delete account."];
            }
            $stmt->close();
        }
    } else {
        $_SESSION['admin_settings_errors'] = ["Incorrect confirmation text. Type 'DELETE' to confirm."];
    }
}

header("Location: admin_settings.php");
exit();
?>