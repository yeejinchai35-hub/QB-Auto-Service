<?php
require_once __DIR__ . '/../config.php';


header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid email or password', 'role' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $response['message'] = 'Email and password are required.';
    } else {
        // First check admins
        $stmt = $mysqli->prepare('SELECT admin_id, username, email, password FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res->fetch_assoc();
        $stmt->close();

        if ($admin && (password_verify($password, $admin['password']) || hash_equals($admin['password'], $password))) {
            $_SESSION['admin'] = [
                'id' => (int)$admin['admin_id'],
                'email' => $admin['email'],
                'username' => $admin['username'] ?? 'Admin'
            ];
            $response['success'] = true;
            $response['message'] = 'Admin login successful';
            $response['role'] = 'admin';
        } else {
            // Check customers + verification status
            $stmt = $mysqli->prepare('SELECT customer_id, full_name, email, password, is_verified FROM customers WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // OTP/verification removed: allow login regardless of is_verified
                $_SESSION['user'] = [
                    'id' => (int)$user['customer_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email']
                ];
                $response['success'] = true;
                $response['message'] = 'User login successful';
                $response['role'] = 'user';
            } else {
                $response['message'] = 'Invalid email or password';
            }
        }
    }
}

echo json_encode($response);
exit;
