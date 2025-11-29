<?php
require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';

// Calculate absolute path to login page for the redirect
$projectRoot = '/project/Capstone-Car-Service-Draft4'; 
$loginUrl = $projectRoot . '/website/profile/profile.html';

// 1. Handle Password Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $postToken = $_POST['token'] ?? '';
    $pw = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if ($postToken === '' || $pw === '' || $confirm === '') { echo json_encode(['success'=>false,'message'=>'All fields required']); exit; }
    if ($pw !== $confirm) { echo json_encode(['success'=>false,'message'=>'Passwords do not match']); exit; }
    if (strlen($pw) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters']); exit; }

    // Verify Token AND Fetch Current Password Hash
    // We join the customers table to get the current password in one query
    $stmt = $mysqli->prepare('
        SELECT vt.token_id, vt.customer_id, vt.expires_at, c.password as current_hash 
        FROM verification_tokens vt 
        JOIN customers c ON vt.customer_id = c.customer_id
        WHERE vt.token = ? AND vt.token_type = "Password_Reset" 
        LIMIT 1
    ');
    $stmt->bind_param('s', $postToken);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) { echo json_encode(['success'=>false,'message'=>'Invalid token. Please request a new link.']); exit; }
    
    // Verify Expiry
    if (strtotime($row['expires_at']) < time()) { 
        echo json_encode(['success'=>false,'message'=>'This link has expired.']); 
        exit; 
    }

    // --- NEW CHECK: Cannot be same as current password ---
    if (password_verify($pw, $row['current_hash'])) {
        echo json_encode(['success'=>false, 'message'=>'New password cannot be the same as your current password.']);
        exit;
    }

    // Update Password
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $upd = $mysqli->prepare('UPDATE customers SET password = ? WHERE customer_id = ? LIMIT 1');
    $upd->bind_param('si', $hash, $row['customer_id']);
    $ok = $upd->execute();
    $upd->close();

    if ($ok) {
        // Delete used token
        $del = $mysqli->prepare('DELETE FROM verification_tokens WHERE token_id = ?');
        $del->bind_param('i', $row['token_id']);
        $del->execute();
        $del->close();
        echo json_encode(['success'=>true, 'message'=>'Password updated! Redirecting...']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Database error updating password.']);
    }
    exit;
}

// 2. Display Form (GET) - Validation logic remains same
if ($token === '') { die('Error: Missing token in URL.'); }

$stmt = $mysqli->prepare('SELECT token_id, expires_at FROM verification_tokens WHERE token = ? AND token_type = "Password_Reset" LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

$errorMsg = '';
if (!$row) {
    $errorMsg = 'Invalid reset link. Token not found in database.';
} elseif (strtotime($row['expires_at']) < time()) {
    $errorMsg = 'This link has expired.';
}

if ($errorMsg) { 
    echo '<body style="background:#121212;color:white;font-family:sans-serif;text-align:center;padding-top:50px;">';
    echo '<h3>' . htmlspecialchars($errorMsg) . '</h3>';
    echo '<br><a href="'.$loginUrl.'" style="color:#ff5722;text-decoration:none;border:1px solid #ff5722;padding:10px 20px;border-radius:5px;">Go to Login</a>';
    echo '</body>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link rel="stylesheet" href="reset_password.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <form id="resetForm" data-login-url="<?php echo htmlspecialchars($loginUrl); ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>" />
            
            <label>New Password</label>
            <input type="password" id="new_password" name="password" required minlength="8" placeholder="Min 8 characters" />
            
            <label>Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-type password" />
            
            <div style="margin-top: 12px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #ccc;">
                <input type="checkbox" id="togglePw" style="width: auto; margin: 0;">
                <label for="togglePw" style="margin: 0; cursor: pointer;">Show Password</label>
            </div>

            <button type="submit">Set New Password</button>
        </form>
        <p id="status"></p>
    </div>
    <script src="reset_password.js"></script>
</body>
</html>