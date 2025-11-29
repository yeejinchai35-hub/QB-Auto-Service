<?php
declare(strict_types=1);

// 1. Path Correction: Adjust to point to your actual auth.php
// Assuming file is in: php/admin/dashboard/services/manage_progress.php
require_once dirname(__DIR__, 3) . '/auth/auth.php';

$adminId = $ADMIN_ID ?? 0; // Safe fallback

if ($adminId === 0) {
    header("Location: ../../../../home.html"); // Adjust redirect as needed
    exit();
}

// 2. Helper: Ensure 'e' (escape) function exists
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// 3. Handle Updates & Archiving
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION 1: ARCHIVE (REMOVE FROM VIEW)
    if (isset($_POST['action']) && $_POST['action'] === 'archive_job') {
        $apptId = (int)($_POST['appointment_id'] ?? 0);
        // We change status to 'Archived' so it no longer appears in the SELECT query below
        if ($stmt = $mysqli->prepare("UPDATE appointments SET status = 'Archived' WHERE appointment_id = ? LIMIT 1")) {
            $stmt->bind_param('i', $apptId);
            if ($stmt->execute()) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=archived");
                exit;
            }
            $stmt->close();
        }
    }

    // ACTION 2: UPDATE PROGRESS
    else {
        $apptId = (int)($_POST['appointment_id'] ?? 0);
        $newProgress = (int)($_POST['progress_stage'] ?? 0);
        
        // Ensure range is 0 to 7
        if ($apptId > 0 && $newProgress >= 0 && $newProgress <= 7) {
            
            $newStatus = 'In Progress'; // Default fallback

            if ($newProgress === 0) {
                $newStatus = 'Scheduled';
            } elseif ($newProgress === 6) {
                $newStatus = 'Completed';
            } elseif ($newProgress === 7) {
                $newStatus = 'Picked Up'; // This matches the SQL update above
            }

            // Update database
            if ($stmt = $mysqli->prepare("UPDATE appointments SET progress_step = ?, status = ? WHERE appointment_id = ? LIMIT 1")) {
                $stmt->bind_param('isi', $newProgress, $newStatus, $apptId);
                if ($stmt->execute()) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// Check for success messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $message = "Vehicle progress updated successfully.";
    if ($_GET['msg'] === 'archived') $message = "Job removed from active queue.";
}

// 4. Fetch Active Jobs
// We include 'Completed' jobs so you can see them, but you might want to limit this query 
// to only recent completed jobs in the future.
$sql = "SELECT a.appointment_id, a.preferred_date, a.preferred_time, a.status, a.progress_step,
               c.full_name, v.vehicle_model, v.license_plate_number
        FROM appointments a
        INNER JOIN customers c ON a.customer_id = c.customer_id
        LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
        WHERE a.status IN ('Scheduled', 'In Progress', 'Completed', 'Pending')
        ORDER BY 
            CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END, -- Show active jobs first
            a.preferred_date DESC"; // Show newest dates first

$jobs = [];
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

// --- FETCH ADMIN INFO ---
$adminInfo = [
    'name' => $_SESSION['admin']['username'] ?? 'Admin',
    'email' => $_SESSION['admin']['email'] ?? '',
    'profile_photo' => ''
];

if ($stmt = $mysqli->prepare('SELECT username, email FROM admins WHERE admin_id = ? LIMIT 1')) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $adminInfo['name'] = $row['username'] ?: $adminInfo['name'];
        $adminInfo['email'] = $row['email'] ?: $adminInfo['email'];
    }
    $stmt->close();
}

// Handle Profile Photo
$photoColumnExists = false;
if ($result = $mysqli->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'")) {
    $photoColumnExists = $result->num_rows > 0;
    $result->close();
}

if ($photoColumnExists && ($photoStmt = $mysqli->prepare('SELECT profile_photo FROM admins WHERE admin_id = ? LIMIT 1'))) {
    $photoStmt->bind_param('i', $adminId);
    $photoStmt->execute();
    if ($row = $photoStmt->get_result()->fetch_assoc()) {
        $adminInfo['profile_photo'] = e($row['profile_photo'] ?? '');
    }
    $photoStmt->close();
}

if ($adminInfo['profile_photo'] === '') {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $candidate = "/project/Capstone-Car-Service-Draft4/images/admins/admin-{$adminId}.{$ext}";
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $candidate;
        if (is_file($absolutePath)) {
            $adminInfo['profile_photo'] = $candidate;
            break;
        }
    }
}

$profileInitial = strtoupper(substr($adminInfo['name'], 0, 1) ?: 'Q');

// Steps Map
$stages = [
    0 => 'Queued / Scheduled', 
    1 => 'Received (Checked In)', 
    2 => 'Inspection (Diagnosing)', 
    3 => 'Feedback (Approving)', 
    4 => 'Servicing (In Progress)', 
    5 => 'Testing (QC Checks)', 
    6 => 'Completed (Ready)',
    7 => 'Vehicle Picked Up (Archive)'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Progress • Admin</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="../admin_dashboard.css" />
  <link rel="stylesheet" href="manage_progress.css" />
  <style>
      .alert-success { background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 10px; border-radius: 6px; margin-bottom: 20px; border: 1px solid rgba(16, 185, 129, 0.3); }
  </style>
</head>
<body>
  
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">QB</div>
      <div class="brand-text">
        <h2>QB Auto</h2>
        <span>Admin Panel</span>
      </div>
    </div>
    <nav class="nav-links">
      <a href="../admin_dashboard.php" class="nav-item">
        <i class='bx bxs-dashboard'></i><span>Dashboard</span>
      </a>
      <a href="../appointments/view_appointments.php" class="nav-item">
        <i class='bx bxs-calendar'></i><span>Appointments</span>
      </a>
      <a href="../customers%20detail/customers.php" class="nav-item">
        <i class='bx bxs-user-detail'></i><span>Customers</span>
      </a>
      <a href="manage_progress.php" class="nav-item active">
        <i class='bx bxs-wrench'></i><span>Services</span>
      </a>
      <a href="../settings/admin_settings.php" class="nav-item">
        <i class='bx bxs-cog'></i><span>Settings</span>
      </a>
    </nav>
    <div class="user-widget">
      <div class="user-avatar">
        <?php if ($adminInfo['profile_photo']): ?>
          <img src="<?php echo e($adminInfo['profile_photo']); ?>" alt="Profile" />
        <?php else: ?>
          <span><?php echo e($profileInitial); ?></span>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <h4><?php echo e($adminInfo['name']); ?></h4>
        <span><?php echo e($adminInfo['email']); ?></span>
      </div>
      <a href="../../../auth/logout.php" class="logout-btn" title="Logout"><i class='bx bx-log-out'></i></a>
    </div>
  </aside>

  <main class="main-content">
    <header class="top-bar">
      <div class="page-title">
        <button class="mobile-toggle" id="sidebarToggle"><i class='bx bx-menu'></i></button>
        <div>
          <h1>Service Progress</h1>
          <p>Update vehicle status in real-time.</p>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class='bx bx-check-circle'></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3>Active Jobs Queue</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Vehicle & Customer</th>
                <th>Current Status</th>
                <th>Update Stage</th>
                <th>Save</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($jobs)): ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No active jobs found.</td></tr>
              <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                  <tr>
                    <td>#<?= $job['appointment_id'] ?></td>
                    <td>
                        <div style="display:flex; flex-direction:column;">
                            <strong style="color:white;"><?= e($job['vehicle_model']) ?></strong>
                            <small style="color:#94a3b8;"><?= e($job['license_plate_number']) ?></small>
                            <span style="font-size:0.8rem; color:#64748b; margin-top:4px;">
                                <i class='bx bx-user'></i> <?= e($job['full_name']) ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php 
                            $badgeColor = '#3b82f6'; // Blue
                            if ($job['status'] === 'Completed') $badgeColor = '#10b981'; // Green
                            if ($job['status'] === 'Scheduled') $badgeColor = '#f97316'; // Orange
                        ?>
                        <span class="badge" style="background: <?= $badgeColor ?>20; color: <?= $badgeColor ?>; border:1px solid <?= $badgeColor ?>40;">
                            <?= e($job['status']) ?>
                        </span>
                    </td>
                    <td>
                        <form id="form-<?= $job['appointment_id'] ?>" method="POST" style="display:flex; align-items:center;">
                            <input type="hidden" name="appointment_id" value="<?= $job['appointment_id'] ?>">
                            
                            <select name="progress_stage" class="progress-select" style="width: 100%; min-width: 180px;">
                                <?php foreach ($stages as $step => $label): ?>
                                    <option value="<?= $step ?>" <?= (int)$job['progress_step'] === $step ? 'selected' : '' ?>>
                                        Step <?= $step ?>: <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex; gap: 8px; align-items: center;">
                            <button type="submit" form="form-<?= $job['appointment_id'] ?>" class="btn-update">
                                <i class='bx bx-save'></i> Update
                            </button>

                            <form method="POST" onsubmit="return confirm('Remove this job from the active queue?');">
                                <input type="hidden" name="action" value="archive_job">
                                <input type="hidden" name="appointment_id" value="<?= $job['appointment_id'] ?>">
                                <button type="submit" class="btn-update" style="background: #ef4444; border-color: #ef4444;" title="Remove from list">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </form>
                        </div>

                            
                        </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  <script src="../admin_dashboard.js"></script>
</body>
</html>