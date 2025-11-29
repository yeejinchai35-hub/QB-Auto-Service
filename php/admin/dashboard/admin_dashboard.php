<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth/auth.php'; // Shared admin auth bootstrap

$adminId = $ADMIN_ID; // Use the globally set ADMIN_ID

if ($adminId === 0) {
    // This is technically already handled by auth.php, but remains as a safeguard
    redirect('/project/Capstone-Car-Service-Draft4/home.html');
}

// --- HANDLE FORM ACTIONS (Approve, Complete, Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $apptId = (int)($_POST['appointment_id'] ?? 0);

    if ($apptId > 0) {
        // Normalize action to be safe against casing/extra spaces
        $normalizedAction = strtolower(trim((string)$action));
        $newStatus = match ($normalizedAction) {
            'approve' => 'Scheduled',
            'complete' => 'Completed',
            'reject' => 'Cancelled',
            default => null,
        };

        if ($newStatus !== null) {
            if ($stmt = $mysqli->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? LIMIT 1")) {
                $stmt->bind_param('si', $newStatus, $apptId);
                $stmt->execute();
                $stmt->close();
                // Refresh page to show changes immediately
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// Removed: $escape and $statusClass definitions (now in auth.php)

// --- FETCH ADMIN INFO ---
$adminInfo = [
    'name' => $_SESSION['admin']['username'] ?? 'Admin',
    'email' => $_SESSION['admin']['email'] ?? '',
    'member_since' => '',
    'profile_photo' => ''
];

if ($stmt = $mysqli->prepare('SELECT username, email, created_at FROM admins WHERE admin_id = ? LIMIT 1')) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $adminInfo['name'] = $row['username'] ?: $adminInfo['name'];
        $adminInfo['email'] = $row['email'] ?: $adminInfo['email'];
        $adminInfo['member_since'] = $row['created_at'] ?: '';
    }
    $stmt->close();
}

if ($adminInfo['member_since'] === '') {
    $adminInfo['member_since'] = date('Y-m-d');
}

// Handle Profile Photo (Check DB and local file path)
$photoColumnExists = false;
if ($result = $mysqli->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'")) {
    $photoColumnExists = $result->num_rows > 0;
    $result->close();
}

if ($photoColumnExists && ($photoStmt = $mysqli->prepare('SELECT profile_photo FROM admins WHERE admin_id = ? LIMIT 1'))) {
    $photoStmt->bind_param('i', $adminId);
    $photoStmt->execute();
    if ($row = $photoStmt->get_result()->fetch_assoc()) {
        $adminInfo['profile_photo'] = e($row['profile_photo'] ?? ''); // Use e() for safe output
    }
    $photoStmt->close();
}

if ($adminInfo['profile_photo'] === '') {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $candidate = "/project/Capstone-Car-Service-Draft4/images/admins/admin-{$adminId}.{$ext}";
        // Check for file existence using absolute path on the server
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $candidate;
        if (is_file($absolutePath)) {
            $adminInfo['profile_photo'] = $candidate;
            break;
        }
    }
}

// --- METRICS ---
$metrics = ['customers' => 0, 'active' => 0, 'completed' => 0, 'newCustomers' => 0];
$statSql = [
    'customers' => 'SELECT COUNT(*) AS total FROM customers',
    'active' => "SELECT COUNT(*) AS total FROM appointments WHERE status IN ('Scheduled','In Progress', 'Pending')",
    'completed' => "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Completed'",
    'newCustomers' => 'SELECT COUNT(*) AS total FROM customers WHERE member_since >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
];

foreach ($statSql as $key => $sql) {
    if ($result = $mysqli->query($sql)) {
        $row = $result->fetch_assoc();
        $metrics[$key] = (int)($row['total'] ?? 0);
        $result->close();
    }
}

// --- UPCOMING APPOINTMENTS ---
$upcomingAppointments = [];
$upcomingSql = "SELECT a.appointment_id, a.preferred_date, a.preferred_time, a.status, c.full_name, c.customer_id, c.phone, v.vehicle_model, v.license_plate_number
    FROM appointments a
    INNER JOIN customers c ON a.customer_id = c.customer_id
    LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
    WHERE a.preferred_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Include today's appointments for visibility
    ORDER BY a.preferred_date ASC, STR_TO_DATE(a.preferred_time, '%h:%i %p') ASC
    LIMIT 10";
if ($result = $mysqli->query($upcomingSql)) {
    while ($row = $result->fetch_assoc()) {
        $upcomingAppointments[] = $row;
    }
    $result->close();
}

// --- SERVICE BREAKDOWN ---
$serviceBreakdown = [];
// UPDATED QUERY: Select directly from appointment_services, no JOIN needed
$serviceSql = "SELECT service_name, COUNT(*) AS total
    FROM appointment_services
    GROUP BY service_name
    ORDER BY total DESC
    LIMIT 6";

if ($result = $mysqli->query($serviceSql)) {
    while ($row = $result->fetch_assoc()) {
        $serviceBreakdown[] = $row;
    }
    $result->close();
}

$memberSinceLabel = date('F Y', strtotime($adminInfo['member_since']));
$profileInitial = strtoupper(substr($adminInfo['name'], 0, 1) ?: 'Q');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard • QB Auto Service</title>
  <!-- Boxicons -->
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="admin_dashboard.css" />
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">QB</div>
      <div class="brand-text">
        <h2>QB Auto</h2>
        <span>Admin Panel</span>
      </div>
    </div>

    <nav class="nav-links">
      <a href="#" class="nav-item active">
        <i class='bx bxs-dashboard'></i>
        <span>Dashboard</span>
      </a>
        <a href="appointments/view_appointments.php" class="nav-item">
        <i class='bx bxs-calendar'></i>
        <span>Appointments</span>
      </a>
    <a href="customers detail/customers.php" class="nav-item">
        <i class='bx bxs-user-detail'></i>
        <span>Customers</span>
      </a>
            <a href="services/manage_progress.php" class="nav-item">
                <i class='bx bxs-wrench'></i>
                <span>Services</span>
            </a>
            <a href="settings/admin_settings.php" class="nav-item">
                <i class='bx bxs-cog'></i>
                <span>Settings</span>
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
    <a href="../../auth/logout.php" class="logout-btn" title="Logout">
        <i class='bx bx-log-out'></i>
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <header class="top-bar">
      <div class="page-title">
        <button class="mobile-toggle" id="sidebarToggle">
            <i class='bx bx-menu'></i>
        </button>
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo e($adminInfo['name']); ?></p>
        </div>
      </div>
      <!-- Optional: Date or Search could go here -->
    </header>

    <!-- STATS ROW -->
    <section class="stats-grid">
      <div class="stat-card" data-kpi data-value="<?php echo $metrics['customers']; ?>">
        <div class="stat-header">
            <div>
                <span class="stat-label">Total Customers</span>
                <div class="stat-value"><?php echo number_format($metrics['customers']); ?></div>
            </div>
            <div class="stat-icon blue"><i class='bx bxs-group'></i></div>
        </div>
      </div>

      <div class="stat-card" data-kpi data-value="<?php echo $metrics['active']; ?>">
        <div class="stat-header">
            <div>
                <span class="stat-label">Active Jobs</span>
                <div class="stat-value"><?php echo number_format($metrics['active']); ?></div>
            </div>
            <div class="stat-icon orange"><i class='bx bxs-time-five'></i></div>
        </div>
      </div>

      <div class="stat-card" data-kpi data-value="<?php echo $metrics['completed']; ?>">
        <div class="stat-header">
            <div>
                <span class="stat-label">Completed</span>
                <div class="stat-value"><?php echo number_format($metrics['completed']); ?></div>
            </div>
            <div class="stat-icon green"><i class='bx bxs-check-circle'></i></div>
        </div>
      </div>

      <div class="stat-card" data-kpi data-value="<?php echo $metrics['newCustomers']; ?>">
        <div class="stat-header">
            <div>
                <span class="stat-label">New (30d)</span>
                <div class="stat-value"><?php echo number_format($metrics['newCustomers']); ?></div>
            </div>
            <div class="stat-icon purple"><i class='bx bxs-user-plus'></i></div>
        </div>
      </div>
    </section>

    <!-- CONTENT GRID -->
    <section class="dashboard-grid">
      
      <!-- APPOINTMENTS TABLE -->
      <div class="card" id="appointments">
        <div class="card-header">
            <h3>Upcoming Appointments</h3>
            <select id="statusFilter" style="background:var(--bg-body); color:var(--text-main); border:1px solid var(--border); padding:6px; border-radius:6px;">
                <option value="all">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="pending">Pending</option>
                <option value="in-progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Appt ID</th>
                            <th>Customer / Vehicle</th>
                            <th>Phone</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcomingAppointments)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No upcoming appointments.</td></tr>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appt): ?>
                                <?php
                                    $status = (string)($appt['status'] ?? '');
                                    $statusNorm = strtolower(trim($status));
                                    // Treat empty/unknown as Pending for display and actions
                                    $displayStatus = $status !== '' ? $status : 'Pending';
                                    $class = statusClass($displayStatus);
                                    $dateLabel = $appt['preferred_date'] ? date('M d', strtotime($appt['preferred_date'])) : '';
                                ?>
                                <tr data-status-row data-status="<?php echo e($class); ?>">
                                    <td>#<?php echo $appt['appointment_id']; ?></td>
                                    <td>
                                        <div class="customer-cell">
                                            <a href="view_customer.php?id=<?php echo $appt['customer_id']; ?>" class="customer-name">
                                                <?php echo e($appt['full_name']); ?>
                                            </a>
                                            <span class="vehicle-info">
                                                <?php echo e($appt['vehicle_model']); ?> • <?php echo e($appt['license_plate_number']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($appt['phone'])): ?>
                                            <?php $tel = preg_replace('/[^0-9+]/', '', (string)$appt['phone']); ?>
                                            <a href="tel:<?php echo e($tel); ?>" class="customer-phone"><?php echo e($appt['phone']); ?></a>
                                        <?php else: ?>
                                            <span class="customer-phone" style="opacity:.7;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo e($dateLabel); ?></div>
                                        <small style="color:var(--text-muted)"><?php echo e($appt['preferred_time']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e($class); ?>"><?php echo e($displayStatus); ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <?php if ($statusNorm === 'pending' || $statusNorm === ''): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <button type="submit" class="action-btn approve" title="Approve"><i class='bx bx-check'></i></button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($statusNorm === 'scheduled' || $statusNorm === 'in progress'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="complete">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <button type="submit" class="action-btn complete" title="Complete"><i class='bx bx-check-double'></i></button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($statusNorm !== 'cancelled' && $statusNorm !== 'completed'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?');">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                    <button type="submit" class="action-btn reject" title="Reject"><i class='bx bx-x'></i></button>
                                                </form>
                                            <?php endif; ?>
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

      <!-- RIGHT COLUMN -->
      <div style="display:flex; flex-direction:column; gap:24px;">
        
        <!-- SERVICES CARD -->
        <div class="card" id="services">
            <div class="card-header">
                <h3>Top Services</h3>
            </div>
            <div class="card-body">
                <?php if ($serviceBreakdown): ?>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <?php foreach ($serviceBreakdown as $service): ?>
                            <div class="service-item">
                                <span class="service-name"><?php echo e($service['service_name']); ?></span>
                                <span class="service-count"><?php echo (int)$service['total']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-muted); text-align:center;">No data yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROFILE / SETTINGS CARD -->
        <div class="card">
            <div class="card-header">
                <h3>Quick Settings</h3>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:12px;">
                <p style="font-size:0.9rem; color:var(--text-muted);">Manage your profile information, password, and avatar from the dedicated settings page.</p>
                <a href="settings/admin_settings.php" class="btn-primary" style="width:fit-content;">Open Settings</a>
            </div>
        </div>

      </div>

    </section>
  </main>

  <script src="admin_dashboard.js"></script>
</body>
</html>