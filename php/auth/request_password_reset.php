<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Unable to process request'];

// 1. Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed';
    echo json_encode($response); exit;
}

$email = trim($_POST['email'] ?? '');
if ($email === '') { $response['message'] = 'Email is required.'; echo json_encode($response); exit; }

// 2. Find customer
$stmt = $mysqli->prepare('SELECT customer_id, full_name FROM customers WHERE email = ? LIMIT 1');
if (!$stmt) { $response['message'] = 'Database error.'; echo json_encode($response); exit; }
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    // Privacy: Pretend it worked even if email not found to prevent email scraping
    $response['success'] = true;
    $response['message'] = 'If an account exists, a reset link has been sent.';
    echo json_encode($response); exit;
}

$customerId = (int)$user['customer_id'];

// 3. Clean up old tokens for this specific user
$del = $mysqli->prepare('DELETE FROM verification_tokens WHERE customer_id = ? AND token_type = "Password_Reset"');
if ($del) { $del->bind_param('i', $customerId); $del->execute(); $del->close(); }

// 4. Generate New Token
$token = bin2hex(random_bytes(32));
$expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

$ins = $mysqli->prepare('INSERT INTO verification_tokens (customer_id, token, token_type, expires_at) VALUES (?, ?, "Password_Reset", ?)');
if (!$ins) { $response['message'] = 'Token creation failed.'; echo json_encode($response); exit; }
$ins->bind_param('iss', $customerId, $token, $expires);
$ok = $ins->execute();
$ins->close();

if (!$ok) { $response['message'] = 'Database error saving token.'; echo json_encode($response); exit; }

// 5. Generate Link (Robust Path Handling)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Ensure we get the correct folder path, normalizing slashes for Windows
$path = dirname($_SERVER['PHP_SELF']);
$path = str_replace('\\', '/', $path); 
$path = rtrim($path, '/');

// Link format: http://localhost/.../php/auth/reset_password.php?token=...
$resetLink = "$protocol://$host$path/reset_password.php?token=$token";

// 6. Send Email (Mocked for Localhost)
$subject = 'Password Reset Request';
$message = "Hello {$user['full_name']},\n\nClick here to reset your password:\n$resetLink\n\nExpires in 1 hour.";
$headers = 'From: no-reply@qbautoservice.com';

// Attempt to send (suppress errors if no mail server configured)
@mail($email, $subject, $message, $headers);

// 7. SUCCESS RESPONSE
$response['success'] = true;
$response['message'] = 'Reset link sent! (Check Console for link)';

// --- DEBUG LINK (For Localhost Testing Only) ---
$response['debug_link'] = $resetLink; 

echo json_encode($response);
exit;
?>