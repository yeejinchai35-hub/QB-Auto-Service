<?php
require_once dirname(__DIR__, 3) . '/auth/auth.php';

header('Content-Type: application/json');

$ADMIN_ID = (int)($_SESSION['admin']['id'] ?? 0);
if ($ADMIN_ID <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['photo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$file    = $_FILES['photo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error code ' . $file['error']]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) { // 2MB
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

$ext = $allowed[$mime];
$baseDir = realpath(dirname(__DIR__, 4) . '/images');
$destDir = $baseDir . DIRECTORY_SEPARATOR . 'admins';
if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

$filename = 'admin-' . $ADMIN_ID . '.' . $ext;
$fullPath = $destDir . DIRECTORY_SEPARATOR . $filename;

// Remove other formats for this admin to keep a single file
foreach (['jpg','jpeg','png','webp'] as $e) {
    $p = $destDir . DIRECTORY_SEPARATOR . 'admin-' . $ADMIN_ID . '.' . $e;
    if ($e !== $ext && file_exists($p)) { @unlink($p); }
}

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

$publicPath = '/project/Capstone-Car-Service-Draft4/images/admins/' . $filename;

// Ensure DB has profile_photo column; create if missing
$colCheck = $mysqli->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'");
if ($colCheck && $colCheck->num_rows === 0) {
    // try to add column
    $mysqli->query("ALTER TABLE admins ADD COLUMN profile_photo varchar(255) NULL");
}
if ($colCheck) { $colCheck->close(); }

// Update DB record if column exists now
$colExists = $mysqli->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'");
if ($colExists && $colExists->num_rows > 0) {
    if ($stmt = $mysqli->prepare('UPDATE admins SET profile_photo = ? WHERE admin_id = ? LIMIT 1')) {
        $stmt->bind_param('si', $publicPath, $ADMIN_ID);
        $stmt->execute();
        $stmt->close();
    }
}
if ($colExists) { $colExists->close(); }

// Redirect back to settings page for UX (HTML form post)
header('Location: /project/Capstone-Car-Service-Draft4/php/admin/dashboard/settings/admin_settings.php');
exit;