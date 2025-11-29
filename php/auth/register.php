<?php
// Backend handler for user registration
require_once __DIR__ . '/../config.php';

// Only accept POST submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
header('Content-Type: application/json');

// Gather and trim input (updated & expanded for requirements)
$fullName         = trim($_POST['name'] ?? '');
$phone            = trim($_POST['phone'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm          = $_POST['confirm_password'] ?? '';
$preferredContact = trim($_POST['preferred_contact'] ?? 'Email'); // Default Email
// Normalize preferred contact
if (!in_array($preferredContact, ['Email', 'Phone'], true)) {
    $preferredContact = 'Email';
}

$errors = [];

// Required fields check (ANY empty -> unified error message)
if ($fullName === '' || $phone === '' || $email === '' || $password === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill up all fields.']);
    exit;
}

// Full name: at least 3 chars, no special chars like @#$%
if (strlen($fullName) < 3) {
    $errors[] = 'Full name must be at least 3 characters.';
}
if (!preg_match('/^[A-Za-z\s\-\']+$/', $fullName)) {
    $errors[] = 'Full name contains invalid characters.';
}

// Email format + domain typo validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
} else {
    $parts = explode('@', $email, 2);
    $domain = strtolower($parts[1] ?? '');
    $allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','protonmail.com','icloud.com'];
    $commonTypos = ['gmils.com','gnail.com','yaho.com','hotnail.com','outlok.com'];
    if (in_array($domain, $commonTypos, true)) {
        $errors[] = 'Email domain appears misspelled.';
    } elseif (!in_array($domain, $allowedDomains, true)) {
        // Optional: suggest closest domain if within distance 2
        $closest = null; $minDist = 3;
        foreach ($allowedDomains as $d) {
            $dist = levenshtein($domain, $d);
            if ($dist < $minDist) { $minDist = $dist; $closest = $d; }
        }
        if ($closest !== null) {
            $errors[] = 'Unrecognized email domain. Did you mean ' . $closest . '?';
        } else {
            $errors[] = 'Unrecognized or unsupported email domain.';
        }
    }
}

// Phone: digits only, length 7-15 (adjust if needed)
if (!preg_match('/^\d{7,15}$/', $phone)) {
    $errors[] = 'Phone must be digits only (7-15 length).';
}

// Password rules
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Uniqueness: email & phone (schema currently only enforces email; we manually check phone)
$checkEmail = $mysqli->prepare('SELECT customer_id FROM customers WHERE email = ? LIMIT 1');
if (!$checkEmail) {
    echo json_encode(['success' => false, 'message' => 'Server error (email prep failed)']);
    exit;
}
$checkEmail->bind_param('s', $email);
$checkEmail->execute();
$resEmail = $checkEmail->get_result();
if ($resEmail && $resEmail->fetch_assoc()) {
    $checkEmail->close();
    echo json_encode(['success' => false, 'message' => 'Email already in use.']);
    exit;
}
$checkEmail->close();

$checkPhone = $mysqli->prepare('SELECT customer_id FROM customers WHERE phone = ? LIMIT 1');
if (!$checkPhone) {
    echo json_encode(['success' => false, 'message' => 'Server error (phone prep failed)']);
    exit;
}
$checkPhone->bind_param('s', $phone);
$checkPhone->execute();
$resPhone = $checkPhone->get_result();
if ($resPhone && $resPhone->fetch_assoc()) {
    $checkPhone->close();
    echo json_encode(['success' => false, 'message' => 'Phone already in use.']);
    exit;
}
$checkPhone->close();

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    echo json_encode(['success' => false, 'message' => 'Password hashing failed.']);
    exit;
}

// Create customer record (using existing schema without inline token columns)
$insert = $mysqli->prepare('INSERT INTO customers (full_name, email, password, phone, preferred_contact, is_verified) VALUES (?, ?, ?, ?, ?, 1)');
if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Server error (insert prep failed)']);
    exit;
}
$insert->bind_param('sssss', $fullName, $email, $hash, $phone, $preferredContact);
$ok = $insert->execute();
$newId = $mysqli->insert_id;
$insert->close();
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Could not create account.']);
    exit;
}

// OTP/Email verification removed: immediately consider account verified
echo json_encode(['success' => true, 'message' => 'Registration successful! You may now log in.', 'user_id' => $newId]);
exit;
