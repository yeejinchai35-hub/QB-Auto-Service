<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'], $_SESSION['user']['id'])) {
  header('Location: ../../website/auth/auth.html');
  exit;
}

define('MAX_VEHICLES_ALLOWED', 5);
define('CUSTOMER_IMAGE_DIR', __DIR__ . '/../../images/customers');
define('CUSTOMER_IMAGE_PUBLIC_BASE', '../../images/customers');

function ensureCustomersProfilePhotoColumn($mysqli): void {
  $colCheck = $mysqli->query("SHOW COLUMNS FROM customers LIKE 'profile_photo'");
  if ($colCheck && $colCheck->num_rows === 0) {
    $mysqli->query("ALTER TABLE customers ADD COLUMN profile_photo varchar(255) DEFAULT NULL AFTER preferred_contact");
  }
  if ($colCheck instanceof mysqli_result) { $colCheck->close(); }
}

function fetchUserRecord($mysqli, int $customerId): ?array {
  if (!$stmt = $mysqli->prepare('SELECT customer_id, full_name, email, phone, preferred_contact, member_since, profile_photo, password FROM customers WHERE customer_id = ? LIMIT 1')) {
    return null;
  }
  $stmt->bind_param('i', $customerId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data   = $result->fetch_assoc() ?: null;
  $stmt->close();
  return $data;
}

function fetchUserVehicles($mysqli, int $customerId): array {
  $vehicles = [];
  if (!$stmt = $mysqli->prepare('SELECT vehicle_id, license_plate_number, vehicle_model FROM vehicles WHERE customer_id = ? ORDER BY vehicle_id ASC')) {
    return $vehicles;
  }
  $stmt->bind_param('i', $customerId);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) { $vehicles[] = $row; }
  $stmt->close();
  return $vehicles;
}

function fetchUpcomingAppointments($mysqli, int $customerId): array {
    $appointments = [];
    // UPDATED QUERY: Fetches 'progress_step' to show status bar
    $sql = "
      SELECT a.appointment_id, a.preferred_date, a.preferred_time, a.status, 
             a.additional_notes, a.progress_step,
             v.license_plate_number, v.vehicle_model,
             GROUP_CONCAT(s.service_name SEPARATOR '||') as services
      FROM appointments a
      LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
      LEFT JOIN appointment_services s ON a.appointment_id = s.appointment_id
      WHERE a.customer_id = ? 
      GROUP BY a.appointment_id
      ORDER BY a.preferred_date DESC, STR_TO_DATE(a.preferred_time, '%h:%i %p') ASC
    ";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
        $stmt->close();
    }
    return $appointments;
}

function getStatusBadgeClass($status) {
    return match(strtolower($status ?? '')) {
        'pending'     => 'bg-warning text-dark', 
        'scheduled'   => 'bg-primary',           
        'in progress' => 'bg-info text-dark',    
        'completed'   => 'bg-success',  
        'picked up'   => 'bg-secondary',         
        'cancelled'   => 'bg-danger',            
        default       => 'bg-secondary'          
    };
}

function sanitizePhone(string $value): string {
  $clean = preg_replace('/[^0-9+\- ]/', '', $value);
  return trim($clean ?? '');
}

function deleteCustomerPhotoFiles(int $customerId): void {
  if (!is_dir(CUSTOMER_IMAGE_DIR)) { return; }
  $pattern = CUSTOMER_IMAGE_DIR . DIRECTORY_SEPARATOR . 'customer-' . $customerId . '.*';
  $files   = glob($pattern);
  if (!$files) { return; }
  foreach ($files as $file) { @unlink($file); }
}

function e($value): string {
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

ensureCustomersProfilePhotoColumn($mysqli);
$USER_ID = (int)$_SESSION['user']['id'];

$userData = fetchUserRecord($mysqli, $USER_ID);
if (!$userData) {
  session_unset();
  session_destroy();
  header('Location: ../../website/auth/auth.html');
  exit;
}

$vehicles        = fetchUserVehicles($mysqli, $USER_ID);
$appointments    = fetchUpcomingAppointments($mysqli, $USER_ID);
$successMessages = [];
$errorMessages   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  switch ($action) {
    case 'update_profile':
      $fullName = trim($_POST['full_name'] ?? '');
      $email    = trim($_POST['email'] ?? '');
      $phone    = sanitizePhone($_POST['phone'] ?? '');
      $preferred= $_POST['preferred_contact'] ?? 'Email';
      $validPreferred = ['Email', 'Phone'];
      
      if (!in_array($preferred, $validPreferred, true)) { $preferred = 'Email'; }

      if ($fullName === '') { $errorMessages[] = 'Full name is required.'; }
      if ($email === '') { $errorMessages[] = 'Email is required.'; }
      if ($phone === '') { $errorMessages[] = 'Phone number is required.'; }

      if ($email !== '') {
          if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
              $errorMessages[] = 'Invalid email format.';
          } 
      }

      if (empty($errorMessages)) {
        if ($stmt = $mysqli->prepare('SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ? LIMIT 1')) {
          $stmt->bind_param('si', $email, $USER_ID);
          $stmt->execute();
          $stmt->store_result();
          if ($stmt->num_rows > 0) { 
              $errorMessages[] = 'This email is already registered.'; 
          }
          $stmt->close();
        }
        if ($stmt = $mysqli->prepare('SELECT customer_id FROM customers WHERE phone = ? AND customer_id <> ? LIMIT 1')) {
          $stmt->bind_param('si', $phone, $USER_ID);
          $stmt->execute();
          $stmt->store_result();
          if ($stmt->num_rows > 0) { 
              $errorMessages[] = 'This phone number is already in use.'; 
          }
          $stmt->close();
        }
      }

      if (empty($errorMessages) && $stmt = $mysqli->prepare('UPDATE customers SET full_name = ?, email = ?, phone = ?, preferred_contact = ? WHERE customer_id = ? LIMIT 1')) {
        $stmt->bind_param('ssssi', $fullName, $email, $phone, $preferred, $USER_ID);
        if ($stmt->execute()) {
          $successMessages[] = 'Profile details updated successfully.';
          $_SESSION['user']['full_name'] = $fullName;
          $_SESSION['user']['email']     = $email;
          $_SESSION['user']['username']  = $fullName !== '' ? $fullName : $email;
        } else {
          $errorMessages[] = 'Unable to update profile.';
        }
        $stmt->close();
      }
      break;

    case 'upload_photo':
      if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errorMessages[] = 'Please select a photo to upload.';
        break;
      }
      $file = $_FILES['profile_photo'];
      if ($file['error'] !== UPLOAD_ERR_OK) { $errorMessages[] = 'Upload failed.'; break; }

      $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
      $finfo   = finfo_open(FILEINFO_MIME_TYPE);
      $mime    = finfo_file($finfo, $file['tmp_name']);
      finfo_close($finfo);

      if (!isset($allowed[$mime])) { $errorMessages[] = 'Unsupported file type.'; break; }
      if ($file['size'] > 2 * 1024 * 1024) { $errorMessages[] = 'File too large. Max 2MB.'; break; }

      if (!is_dir(CUSTOMER_IMAGE_DIR)) { @mkdir(CUSTOMER_IMAGE_DIR, 0775, true); }

      $ext      = $allowed[$mime];
      $filename = 'customer-' . $USER_ID . '.' . $ext;
      $destPath = CUSTOMER_IMAGE_DIR . DIRECTORY_SEPARATOR . $filename;

      deleteCustomerPhotoFiles($USER_ID);
      if (!move_uploaded_file($file['tmp_name'], $destPath)) { $errorMessages[] = 'Unable to save photo.'; break; }

      $publicPath = CUSTOMER_IMAGE_PUBLIC_BASE . '/' . $filename;
      if ($stmt = $mysqli->prepare('UPDATE customers SET profile_photo = ? WHERE customer_id = ? LIMIT 1')) {
        $stmt->bind_param('si', $publicPath, $USER_ID);
        if ($stmt->execute()) { $successMessages[] = 'Profile photo updated.'; } 
        $stmt->close();
      }
      break;

    case 'delete_photo':
      deleteCustomerPhotoFiles($USER_ID);
      if ($stmt = $mysqli->prepare('UPDATE customers SET profile_photo = NULL WHERE customer_id = ? LIMIT 1')) {
        $stmt->bind_param('i', $USER_ID);
        if ($stmt->execute()) { $successMessages[] = 'Profile photo deleted.'; } 
        $stmt->close();
      }
      break;

    case 'add_vehicle':
      $licensePlate = strtoupper(trim($_POST['license_plate_number'] ?? ''));
      $vehicleModel = trim($_POST['vehicle_model'] ?? '');

      if ($licensePlate === '') { $errorMessages[] = 'License plate is required.'; break; }
      if (count($vehicles) >= MAX_VEHICLES_ALLOWED) { 
          $errorMessages[] = 'Maximum vehicles reached.'; 
          break; 
      }

      if ($dupStmt = $mysqli->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate_number = ? LIMIT 1")) {
          $dupStmt->bind_param('s', $licensePlate);
          $dupStmt->execute();
          $dupStmt->store_result();
          if ($dupStmt->num_rows > 0) {
              $errorMessages[] = "License plate already registered.";
              $dupStmt->close();
              break;
          }
          $dupStmt->close();
      }

      if ($stmt = $mysqli->prepare('INSERT INTO vehicles (customer_id, license_plate_number, vehicle_model) VALUES (?, ?, ?)')) {
        $stmt->bind_param('iss', $USER_ID, $licensePlate, $vehicleModel);
        if ($stmt->execute()) { $successMessages[] = 'Vehicle added.'; }
        $stmt->close();
      }
      break;

    case 'update_vehicle':
      $vehicleId    = (int)($_POST['vehicle_id'] ?? 0);
      $licensePlate = strtoupper(trim($_POST['license_plate_number'] ?? ''));
      $vehicleModel = trim($_POST['vehicle_model'] ?? '');

      if ($vehicleId <= 0) { $errorMessages[] = 'Invalid vehicle.'; break; }

      if ($dupStmt = $mysqli->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate_number = ? AND vehicle_id != ? LIMIT 1")) {
          $dupStmt->bind_param('si', $licensePlate, $vehicleId);
          $dupStmt->execute();
          $dupStmt->store_result();
          if ($dupStmt->num_rows > 0) {
              $errorMessages[] = "License plate already exists.";
              $dupStmt->close();
              break;
          }
          $dupStmt->close();
      }

      if ($stmt = $mysqli->prepare('UPDATE vehicles SET license_plate_number = ?, vehicle_model = ? WHERE vehicle_id = ? AND customer_id = ? LIMIT 1')) {
        $stmt->bind_param('ssii', $licensePlate, $vehicleModel, $vehicleId, $USER_ID);
        if ($stmt->execute()) { $successMessages[] = 'Vehicle updated.'; }
        $stmt->close();
      }
      break;

    case 'delete_vehicle':
      $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
      if ($stmt = $mysqli->prepare('DELETE FROM vehicles WHERE vehicle_id = ? AND customer_id = ?')) {
        $stmt->bind_param('ii', $vehicleId, $USER_ID);
        if ($stmt->execute()) { $successMessages[] = 'Vehicle deleted.'; }
        $stmt->close();
      }
      break;

    case 'update_password':
      $currentPassword = $_POST['current_password'] ?? '';
      $newPassword     = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';

      if (!password_verify($currentPassword, $userData['password'])) { 
          $errorMessages[] = 'Current password incorrect.'; 
          break; 
      }
      if ($newPassword !== $confirmPassword) { 
          $errorMessages[] = 'Passwords do not match.'; 
          break; 
      }

      $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
      if ($stmt = $mysqli->prepare('UPDATE customers SET password = ? WHERE customer_id = ? LIMIT 1')) {
        $stmt->bind_param('si', $newHash, $USER_ID);
        if ($stmt->execute()) { 
            $successMessages[] = 'Password updated.'; 
            $userData['password'] = $newHash; 
        }
        $stmt->close();
      }
      break;

    case 'delete_account':
      $confirmation = trim($_POST['delete_confirm'] ?? '');
      if ($confirmation !== 'DELETE') { $errorMessages[] = 'Type DELETE to confirm.'; break; }
      
      $mysqli->begin_transaction();
      try {
        $mysqli->query("DELETE FROM appointment_services WHERE appointment_id IN (SELECT appointment_id FROM appointments WHERE customer_id = $USER_ID)");
        $mysqli->query("DELETE FROM appointments WHERE customer_id = $USER_ID");
        $mysqli->query("DELETE FROM vehicles WHERE customer_id = $USER_ID");
        $mysqli->query("DELETE FROM customers WHERE customer_id = $USER_ID LIMIT 1");
        deleteCustomerPhotoFiles($USER_ID);
        $mysqli->commit();
        session_unset(); session_destroy();
        
        // --- CHANGED: Correct path and added status parameter ---
        header('Location: ../../website/home/home.html?status=account_deleted'); 
        exit;
      } catch (Throwable $e) { $mysqli->rollback(); $errorMessages[] = 'Delete failed.'; }
      break;
      
    case 'cancel_appointment':
        $apptId = (int)($_POST['appointment_id'] ?? 0);
        $reason = trim($_POST['cancel_reason'] ?? ''); 

        $noteAppend = "";
        if ($reason !== '') {
            $noteAppend = " [User Cancelled: " . htmlspecialchars($reason) . "]";
        }

        $sql = "UPDATE appointments 
                SET status = 'Cancelled', 
                    additional_notes = CONCAT(IFNULL(additional_notes, ''), ?) 
                WHERE appointment_id = ? AND customer_id = ? AND status != 'Completed' 
                LIMIT 1";

        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('sii', $noteAppend, $apptId, $USER_ID);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMessages[] = "Appointment #$apptId cancelled.";
            } else {
                $errorMessages[] = "Unable to cancel.";
            }
            $stmt->close();
        }
        break;

    case 'edit_appointment':
        $apptId   = (int)($_POST['appointment_id'] ?? 0);
        $newDate  = $_POST['date'] ?? '';
        $newTime  = $_POST['time'] ?? '';
        $newNotes = trim($_POST['notes'] ?? '');
        $newServices = $_POST['service_type'] ?? [];

        if ($apptId <= 0 || empty($newDate) || empty($newTime) || empty($newServices)) {
            $errorMessages[] = "Missing details."; break;
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($newDate < $tomorrow) {
            $errorMessages[] = "Date must be tomorrow or later."; break;
        }

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("UPDATE appointments SET preferred_date = ?, preferred_time = ?, additional_notes = ?, status = 'Pending', progress_step = 0 WHERE appointment_id = ? AND customer_id = ? AND status != 'Completed'");
            $stmt->bind_param('sssii', $newDate, $newTime, $newNotes, $apptId, $USER_ID);
            $stmt->execute();
            $stmt->close();

            $delStmt = $mysqli->prepare("DELETE FROM appointment_services WHERE appointment_id = ?");
            $delStmt->bind_param('i', $apptId);
            $delStmt->execute();
            $delStmt->close();

            $insStmt = $mysqli->prepare("INSERT INTO appointment_services (appointment_id, service_name) VALUES (?, ?)");
            foreach ($newServices as $svc) {
                if (!empty($svc)) {
                    $insStmt->bind_param('is', $apptId, $svc);
                    $insStmt->execute();
                }
            }
            $insStmt->close();

            $mysqli->commit();
            $successMessages[] = "Appointment rescheduled.";

        } catch (Exception $e) {
            $mysqli->rollback();
            $errorMessages[] = "Error: " . $e->getMessage();
        }
        break;
  }
  $userData = fetchUserRecord($mysqli, $USER_ID) ?? $userData;
  $vehicles = fetchUserVehicles($mysqli, $USER_ID);
  $appointments = fetchUpcomingAppointments($mysqli, $USER_ID);
}

// --- NEW LOGIC: Determine Active Tab based on Action ---
$activeTab = 'appointments'; // Default tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastAction = $_POST['action'] ?? '';
    
    if (strpos($lastAction, 'vehicle') !== false) {
        // add_vehicle, update_vehicle, delete_vehicle
        $activeTab = 'vehicles';
    } 
    elseif (in_array($lastAction, ['update_profile', 'upload_photo', 'delete_photo', 'update_password', 'delete_account'])) {
        $activeTab = 'profile';
    }
}

$rawFullName = $userData['full_name'] ?? 'User';
$rawEmail    = $userData['email'] ?? '';
$rawPhone    = $userData['phone'] ?? '';
$preferredContact = $userData['preferred_contact'] ?? 'Email';
$memberSince = $userData['member_since'] ?? date('Y-m-d');
$profilePhotoUrl = $userData['profile_photo'] ?? '';
$avatarInitial = strtoupper(substr($rawFullName, 0, 1) ?: 'U');
$memberSinceDisplay = $memberSince ? date('F Y', strtotime($memberSince)) : date('F Y');
$vehiclesCount = count($vehicles);
$addVehiclePanelState = $vehiclesCount === 0 ? 'active' : '';

$_SESSION['user']['full_name'] = $rawFullName;
$_SESSION['user']['email']     = $rawEmail;
$_SESSION['user']['username']  = $rawFullName !== '' ? $rawFullName : $rawEmail;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Dashboard • QB Auto Service Centre</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700&family=Oswald:wght@200..700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="user_dashboard.css">
</head>
<body class="profile-page">
  <input type="hidden" id="serverActiveTab" value="<?php echo $activeTab; ?>">
  <div id="headerContainer"></div>
  <div id="top"></div>

  <section class="profile-hero">
    <div class="container">
      <div class="profile-title">
        <i class='bx bxs-dashboard'></i>
        <h1>Your Dashboard</h1>
      </div>
      <p class="profile-subtitle">Welcome back, <?php echo e($rawFullName); ?>. Manage your profile, view appointments, and keep your account up to date.</p>
    </div>
  </section>

  <main class="profile-grid">
    <div class="container">
      <?php if (!empty($successMessages)) : ?>
        <div class="flash-message alert alert-success" role="alert" data-flash>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="messages">
              <?php foreach ($successMessages as $message) : ?>
                <div><?php echo e($message); ?></div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn-close btn-close-white" aria-label="Close" data-flash-dismiss></button>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($errorMessages)) : ?>
        <div class="flash-message alert alert-danger" role="alert" data-flash>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="messages">
              <?php foreach ($errorMessages as $message) : ?>
                <div><?php echo e($message); ?></div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn-close" aria-label="Close" data-flash-dismiss></button>
          </div>
        </div>
      <?php endif; ?>

      <div class="dashboard-layout">
        <aside class="dashboard-sidebar">
          <div class="sidebar-card">
            <div class="sidebar-avatar">
              <?php if ($profilePhotoUrl) : ?>
                <img src="<?php echo e($profilePhotoUrl); ?>" alt="Profile photo" />
              <?php else : ?>
                <span><?php echo e($avatarInitial); ?></span>
              <?php endif; ?>
            </div>
            <div>
              <p class="sidebar-name"><?php echo e($rawFullName); ?></p>
              <p class="sidebar-email"><?php echo e($rawEmail); ?></p>
              <p class="sidebar-meta">Member since <?php echo e($memberSinceDisplay); ?></p>
            </div>
          </div>
          <nav class="sidebar-nav">
            <p class="sidebar-label">Dashboard</p>
            
            <button type="button" class="sidebar-link <?php echo $activeTab === 'appointments' ? 'active' : ''; ?>" data-tab-link="appointments">
              <i class='bx bx-calendar'></i>
              <span>Appointments</span>
            </button>
            
            <button type="button" class="sidebar-link <?php echo $activeTab === 'vehicles' ? 'active' : ''; ?>" data-tab-link="vehicles">
              <i class='bx bx-car'></i>
              <span>My Vehicles</span>
            </button>
            
            <button type="button" class="sidebar-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" data-tab-link="profile">
              <i class='bx bx-user'></i>
              <span>Profile Settings</span>
            </button>
            
            <p class="sidebar-label">Shortcuts</p>
            <a class="sidebar-link" href="../../website/appointment/appointment.html">
              <i class='bx bx-calendar-plus'></i>
              <span>Book Service</span>
            </a>
            <a class="sidebar-link" href="../../website/services/services.html">
              <i class='bx bx-cog'></i>
              <span>Browse Services</span>
            </a>
          </nav>
        </aside>

        <section class="dashboard-main">
          <div class="tab-switcher">
            <button type="button" class="tab-button <?php echo $activeTab === 'appointments' ? 'active' : ''; ?>" data-tab-link="appointments">Appointments</button>
            <button type="button" class="tab-button <?php echo $activeTab === 'vehicles' ? 'active' : ''; ?>" data-tab-link="vehicles">My Vehicles</button>
            <button type="button" class="tab-button <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" data-tab-link="profile">Profile Settings</button>
          </div>

          <div class="tab-panels">
            <section class="tab-panel <?php echo $activeTab === 'appointments' ? 'active' : ''; ?>" id="tab-appointments" data-tab-panel="appointments">
              <div class="card grid-span-full">
                <div class="card-header">
                  <div>
                    <h3>Upcoming Appointments</h3>
                    <p class="mb-0 small">Need to change plans? Click <strong>Reschedule</strong> on any booking.</p>
                  </div>
                  <div class="section-actions">
                    <a class="btn btn-outline btn-sm" href="../../website/appointment/appointment.html">New Booking</a>
                  </div>
                </div>
                <div class="list">
                  <?php if (empty($appointments)) : ?>
                    <div class="list-item">
                      <div class="info">
                        <div class="title">No upcoming appointments</div>
                        <div class="sub">Schedule a service to see it here.</div>
                      </div>
                    </div>
                  <?php else : ?>
                    <?php 
                        // Define the stage mapping for progress tracking
                        $stages = [
                            0 => 'Queued / Scheduled', 
                            1 => 'Received (Checked In)', 
                            2 => 'Inspection (Diagnosing)', 
                            3 => 'Feedback (Approving)', 
                            4 => 'Servicing (In Progress)', 
                            5 => 'Testing (QC Checks)', 
                            6 => 'Ready for Pickup',
                            7 => 'Service Closed'
                        ];
                        
                        foreach ($appointments as $appt) : 
                            $status = !empty($appt['status']) ? $appt['status'] : 'Pending';
                            // Parse services string
                            $serviceList = !empty($appt['services']) ? explode('||', $appt['services']) : [];
                            
                            // Progress Tracking Logic
                            $currentStep = (int)($appt['progress_step'] ?? 0);
                            $stepLabel = $stages[$currentStep] ?? 'Processing';
                            // Calculate percentage (0 to 6)
                            $progressPercent = min(100, max(5, ($currentStep / 6) * 100));
                    ?>
                      <div class="list-item d-flex justify-content-between align-items-start p-3 border-bottom">
    
                        <div class="info flex-grow-1 me-3"> 
                            
                            <div class="d-flex align-items-center mb-2">
                                <div class="title d-flex gap-2 align-items-center">
                                    <strong class="fs-5"><?php echo date('M d, Y', strtotime($appt['preferred_date'])); ?></strong>
                                    <span class="text-muted small text-uppercase fw-bold">at</span>
                                    <strong class="fs-5"><?php echo e($appt['preferred_time']); ?></strong>
                                </div>
                                <div class="vr mx-3 opacity-25 text-white" style="height: 1.2em;"></div>
                                <div class="text-white-50 font-monospace small" title="Booking ID">
                                    #<?php echo $appt['appointment_id']; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong class="text-white fs-5"><?php echo e($appt['vehicle_model'] ?: 'Unknown Vehicle'); ?></strong>
                                <span class="ms-2 font-monospace badge bg-dark border border-secondary text-light">
                                    <?php echo e($appt['license_plate_number']); ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="small text-uppercase fw-bold mb-1" style="color: #94a3b8; font-size: 0.7rem; letter-spacing: 0.5px;">
                                    Requested Services:
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!empty($serviceList)): ?>
                                        <?php foreach ($serviceList as $svc): ?>
                                            <span class="badge" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: #e2e8f0; font-weight: 400; padding: 6px 10px;">
                                                <?php echo e($svc); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">No specific services selected.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($appt['additional_notes'])): ?>
                            <div class="mb-3">
                                 <div class="small text-uppercase fw-bold mb-1" style="color: #94a3b8; font-size: 0.7rem; letter-spacing: 0.5px;">
                                    Your Note:
                                </div>
                                <div class="p-2 rounded d-flex align-items-start" 
                                    style="background: rgba(255,255,255,0.03); border-left: 3px solid #64748b;">
                                    <i class='bx bxs-note me-2 mt-1 text-secondary'></i>
                                    <em class="text-white-50 small">"<?php echo e($appt['additional_notes']); ?>"</em>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (($status === 'In Progress' || $status === 'Completed') && $currentStep < 7): ?>
                                <div class="mt-3 p-3 rounded" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-uppercase text-warning fw-bold">
                                            <i class='bx bx-loader-alt bx-spin me-1'></i> Workshop Status
                                        </small>
                                        <span class="text-white small"><?php echo e($stepLabel); ?></span>
                                    </div>
                                    </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex flex-column align-items-end gap-2 actions-col flex-shrink-0">
                          <span class="badge <?php echo getStatusBadgeClass($status); ?> rounded-pill px-3 py-2 text-uppercase" aria-hidden="true">
                            <?php echo e($status); ?>
                          </span>

                          <?php if ($status === 'Pending' || $status === 'Scheduled'): ?>
                            <div class="mt-2 d-flex gap-2 justify-content-end align-items-center">
                              <button type="button" class="btn btn-sm btn-outline btn-action"
                                title="Edit or Reschedule"
                                data-bs-toggle="modal"
                                data-bs-target="#editApptModal"
                                data-id="<?php echo $appt['appointment_id']; ?>"
                                data-date="<?php echo $appt['preferred_date']; ?>"
                                data-time="<?php echo $appt['preferred_time']; ?>"
                                data-notes="<?php echo e($appt['additional_notes']); ?>"
                                data-services="<?php echo e($appt['services']); ?>">
                                <i class='bx bx-edit-alt'></i>
                                <span class="d-none d-sm-inline ms-1">Reschedule</span>
                              </button>

                              <form method="POST" class="cancel-form m-0">
                                <input type="hidden" name="action" value="cancel_appointment">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                <input type="hidden" name="cancel_reason" value=""> 
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Cancel Booking">
                                  <i class='bx bx-trash'></i>
                                  <span class="d-none d-sm-inline ms-1">Cancel</span>
                                </button>
                              </form>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </section>

            <section class="tab-panel <?php echo $activeTab === 'vehicles' ? 'active' : ''; ?>" id="tab-vehicles" data-tab-panel="vehicles">
              <div class="card" id="vehicle-management">
                <div class="card-header">
                  <div>
                    <h3>Your Vehicles</h3>
                    <p class="mb-0 small">You can store up to <?php echo MAX_VEHICLES_ALLOWED; ?> license plates.</p>
                  </div>
                  <div class="section-actions">
                    <button type="button" class="btn btn-outline btn-sm" data-panel-toggle="add-vehicle-panel" data-label-open="Add Vehicle" data-label-close="Hide Form" aria-expanded="<?php echo $addVehiclePanelState === 'active' ? 'true' : 'false'; ?>">
                      <?php echo $addVehiclePanelState === 'active' ? 'Hide Form' : 'Add Vehicle'; ?>
                    </button>
                  </div>
                </div>
                <div class="panel-collapsible <?php echo $addVehiclePanelState; ?>" id="add-vehicle-panel">
                  <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="col-md-6">
                      <label class="form-label" for="new_plate">License Plate *</label>
                      <input type="text" id="new_plate" name="license_plate_number" class="form-control" maxlength="20" required <?php echo $vehiclesCount >= MAX_VEHICLES_ALLOWED ? 'disabled' : ''; ?> placeholder="e.g., WQB 1234">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="new_model">Vehicle Model</label>
                      <input type="text" id="new_model" name="vehicle_model" class="form-control" maxlength="100" <?php echo $vehiclesCount >= MAX_VEHICLES_ALLOWED ? 'disabled' : ''; ?> placeholder="e.g., Honda Civic">
                    </div>
                    <div class="col-12 form-actions">
                      <button class="btn btn-main btn-sm" type="submit" <?php echo $vehiclesCount >= MAX_VEHICLES_ALLOWED ? 'disabled' : ''; ?>>Save Vehicle</button>
                      <button class="btn btn-outline" type="button" data-panel-cancel="add-vehicle-panel">Cancel</button>
                    </div>
                    <?php if ($vehiclesCount >= MAX_VEHICLES_ALLOWED) : ?>
                      <p class="small text-danger text-center mt-2 mb-0">Remove an existing vehicle before adding a new one.</p>
                    <?php endif; ?>
                  </form>
                </div>

                <div class="vehicle-list mt-3">
                  <?php if ($vehiclesCount === 0) : ?>
                    <div class="empty">No vehicles on file yet.</div>
                  <?php else : ?>
                    <?php foreach ($vehicles as $vehicle) : ?>
                      <?php $vehicleRowId = 'vehicle-row-' . (int)$vehicle['vehicle_id']; ?>
                      <div class="vehicle-item" id="<?php echo e($vehicleRowId); ?>">
                        <div class="vehicle-summary">
                          <div>
                            <div class="title"><?php echo e($vehicle['license_plate_number']); ?></div>
                            <div class="sub"><?php echo e($vehicle['vehicle_model'] ?: 'Model not specified'); ?></div>
                          </div>
                          <div class="vehicle-actions">
                            <button type="button" class="btn btn-outline btn-sm" data-vehicle-toggle="<?php echo e($vehicleRowId); ?>">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this vehicle?');">
                              <input type="hidden" name="action" value="delete_vehicle">
                              <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_id']; ?>">
                              <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                            </form>
                          </div>
                        </div>
                        <form method="POST" class="vehicle-edit-form">
                          <input type="hidden" name="action" value="update_vehicle">
                          <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_id']; ?>">
                          <div class="row g-3">
                            <div class="col-md-6">
                              <label class="form-label">License Plate *</label>
                              <input type="text" name="license_plate_number" class="form-control" value="<?php echo e($vehicle['license_plate_number']); ?>" maxlength="20" required>
                            </div>
                            <div class="col-md-6">
                              <label class="form-label">Vehicle Model</label>
                              <input type="text" name="vehicle_model" class="form-control" value="<?php echo e($vehicle['vehicle_model']); ?>" maxlength="100">
                            </div>
                            <div class="col-12 form-actions">
                              <button class="btn btn-main btn-sm" type="submit">Save</button>
                              <button class="btn btn-outline" type="button" data-vehicle-cancel>Cancel</button>
                            </div>
                          </div>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </section>

            <section class="tab-panel <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" id="tab-profile" data-tab-panel="profile">
              <div class="card" id="contact-details-card">
                <div class="card-header">
                  <div>
                    <h3>Contact Details</h3>
                    <p class="mb-0 small">Keep your contact information current for booking reminders.</p>
                  </div>
                  <div class="section-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="editContactBtn">
                      Edit
                    </button>
                  </div>
                </div>
                <form method="POST" class="card-body-form" id="contactDetailsForm">
                  <input type="hidden" name="action" value="update_profile">
                  
                  <fieldset class="row g-3" id="contactFormFields" disabled> 
                    <div class="col-md-6">
                      <label class="form-label" for="full_name">Full Name *</label>
                      <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo e($rawFullName); ?>" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="email">Email *</label>
                      <input type="email" id="email" name="email" class="form-control" value="<?php echo e($rawEmail); ?>" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="phone">Phone *</label>
                      <input type="text" id="phone" name="phone" class="form-control" value="<?php echo e($rawPhone); ?>" required maxlength="20" placeholder="e.g., 0192106661">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="preferred_contact">Preferred Contact *</label>
                      <select id="preferred_contact" name="preferred_contact" class="form-select" required>
                        <option value="Email" <?php echo $preferredContact === 'Email' ? 'selected' : ''; ?>>Email</option>
                        <option value="Phone" <?php echo $preferredContact === 'Phone' ? 'selected' : ''; ?>>Phone</option>
                      </select>
                    </div>
                  </fieldset>
                  
                  <div class="form-actions mt-4 contact-actions hidden" id="contactFormActions">
                    <button class="btn btn-main" type="submit">Save Changes</button>
                    <button class="btn btn-outline" type="button" id="cancelContactBtn">Cancel</button>
                  </div>
                </form>
              </div>

              <div class="card">
                <div class="card-header">
                  <div>
                    <h3>Profile Picture</h3>
                    <p class="mb-0 small">Optional. Supports JPG, PNG, or WEBP up to 2MB.</p>
                  </div>
                </div>
                <div class="profile-photo-wrapper">
                  <div class="profile-photo-preview" id="photoPreview">
                    <?php if ($profilePhotoUrl) : ?>
                      <img src="<?php echo e($profilePhotoUrl); ?>" alt="Current profile photo" id="currentPhoto">
                    <?php else : ?>
                      <div class="placeholder mb-0">You have not uploaded a profile picture yet.</div>
                    <?php endif; ?>
                  </div>

                  <div class="photo-input-controls">
                    <form method="POST" enctype="multipart/form-data" class="photo-form" id="uploadPhotoForm">
                      <input type="hidden" name="action" value="upload_photo">
                      <div class="file-chooser">
                        <label class="btn choose-file-btn" for="profile_photo_input">CHOOSE FILE</label>
                        <span id="chosenFileName" class="file-name">No file chosen</span>
                        <input type="file" id="profile_photo_input" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden" />
                      </div>
                      <div class="form-actions">
                        <button class="btn btn-main" type="submit" id="uploadPhotoBtn" disabled>UPLOAD PHOTO</button>
                      </div>
                    </form>
                    <?php if ($profilePhotoUrl) : ?>
                      <form method="POST" onsubmit="return confirm('Remove your profile photo?');" class="remove-photo-form">
                        <input type="hidden" name="action" value="delete_photo">
                        <button class="btn btn-outline remove-photo-btn" type="submit">REMOVE PHOTO</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="card grid-span-full" id="account-security">
                <div class="card-header">
                  <div>
                    <h3>Account Security</h3>
                    <p class="mb-0 small">Update your password regularly to protect your account.</p>
                  </div>
                </div>
                <form method="POST" class="card-body-form">
                  <input type="hidden" name="action" value="update_password">
                  <div class="row g-3 align-items-start">
                    <div class="col-md-4 col-sm-12"> <label class="form-label" for="current_password">Current Password *</label>
                      <div class="position-relative">
                        <input type="password" id="current_password" name="current_password" class="form-control pe-5" required>
                        <i class='bx bx-show password-toggle' data-target="current_password"></i>
                      </div>
                    </div>
                    <div class="col-md-4 col-sm-12"> <label class="form-label" for="new_password">New Password *</label>
                      <div class="position-relative">
                        <input type="password" id="new_password" name="new_password" class="form-control pe-5" required minlength="8">
                        <i class='bx bx-show password-toggle' data-target="new_password"></i>
                      </div>
                    </div>
                    <div class="col-md-4 col-sm-12"> <label class="form-label" for="confirm_password">Confirm Password *</label>
                      <div class="position-relative">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control pe-5" required minlength="8">
                        <i class='bx bx-show password-toggle' data-target="confirm_password"></i>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="form-actions mt-2 justify-content-end"> <button class="btn btn-main w-auto" type="submit">Update Password</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <div class="card grid-span-full" id="account-actions">
                <div class="card-header">
                  <div>
                    <h3>Delete Account</h3>
                    <p class="mb-0 small text-danger">Deleting your account removes all vehicles and booking history.</p>
                  </div>
                </div>
                <form method="POST" class="card-body-form" onsubmit="return confirm('This will permanently remove your account. Continue?');">
                  <input type="hidden" name="action" value="delete_account">
                  <div class="row g-3 align-items-end">
                    <div class="col-md-7 col-lg-7">
                      <label class="form-label" for="delete_confirm">Type DELETE to confirm *</label>
                      <input type="text" id="delete_confirm" name="delete_confirm" class="form-control" placeholder="DELETE" required>
                    </div>
                    <div class="col-md-5 col-lg-5">
                      <div class="form-actions mt-0">
                        <button class="btn btn-danger w-100" type="submit">Delete / Deactivate Account</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
            </section>
          </div>
        </section>
      </div>
    </div>
  </main>

  <div class="modal fade" id="editApptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit_appointment">
            <input type="hidden" name="appointment_id" id="editApptId">
            <div class="modal-header">
                <h5 class="modal-title">Manage Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" id="editDate" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time</label>
                        <select name="time" id="editTime" class="form-select" required>
                             <option>09:00 AM</option><option>09:30 AM</option>
                             <option>10:00 AM</option><option>10:30 AM</option>
                             <option>11:00 AM</option><option>11:30 AM</option>
                             <option>12:00 PM</option><option>12:30 PM</option>
                             <option>01:00 PM</option><option>01:30 PM</option>
                             <option>02:00 PM</option><option>02:30 PM</option>
                             <option>03:00 PM</option><option>03:30 PM</option>
                             <option>04:00 PM</option><option>04:30 PM</option>
                             <option>05:00 PM</option><option>05:30 PM</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Services</label>
                            <button type="button" class="btn btn-sm btn-outline" id="modalAddServiceBtn"> + Add Service</button>
                        </div>
                        <div id="modalServiceContainer" class="d-flex flex-column gap-2"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-main">Save Changes</button>
            </div>
        </form>
    </div>
  </div>

  <div id="authContainer"></div>
  <div id="footerContainer"></div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../website/header&footer/header.js"></script>
  <script src="../../website/header&footer/footer.js"></script>
  <script src="../auth/auth.js"></script>
  <script src="user_dashboard.js"></script>
</body>
</html>