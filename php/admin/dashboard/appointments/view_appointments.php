<?php
declare(strict_types=1);

// Auth helper (lives in php/auth)
require_once dirname(__DIR__, 3) . '/auth/auth.php'; // Provides $mysqli, auth, e(), statusClass()

$adminId = $ADMIN_ID;
if ($adminId === 0) {
    redirect('/project/Capstone-Car-Service-Draft4/home.html');
}

// --- HANDLE STATUS ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim($_POST['action'] ?? ''));
    $apptId = (int)($_POST['appointment_id'] ?? 0);
    if ($apptId > 0 && in_array($action, ['approve','reject','complete'], true)) {
        $newStatus = match ($action) {
            'approve' => 'Scheduled',        // Treat as Approved
            'reject' => 'Cancelled',
            'complete' => 'Completed',
            default => null,
        };
        if ($newStatus) {
            if ($stmt = $mysqli->prepare('UPDATE appointments SET status = ? WHERE appointment_id = ? LIMIT 1')) {
                $stmt->bind_param('si', $newStatus, $apptId);
                $stmt->execute();
                $stmt->close();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
                exit;
            }
        }
    }
}

// --- FILTERS & PAGINATION ---
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($statusFilter !== 'all' && $statusFilter !== '') {
    $where[] = 'LOWER(a.status) = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(c.full_name LIKE ? OR v.license_plate_number LIKE ? OR v.vehicle_model LIKE ? OR a.appointment_id LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- TOTAL COUNT FOR PAGINATION ---
$total = 0;
$countSql = "SELECT COUNT(*) AS cnt FROM appointments a LEFT JOIN vehicles v ON a.vehicle_id=v.vehicle_id INNER JOIN customers c ON a.customer_id=c.customer_id $whereSql";
if ($stmt = $mysqli->prepare($countSql)) {
  if ($types !== '') {
    $bindParams = [$types];
    foreach ($params as $idx => $value) {
      $bindParams[] = &$params[$idx];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
  }
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// --- MAIN QUERY (WITH SERVICE AGGREGATION) ---
$sql = "SELECT a.appointment_id, a.preferred_date, a.preferred_time, a.status,
  c.customer_id, c.full_name, c.phone,
        v.vehicle_model, v.license_plate_number,
        s.services
    FROM appointments a
    INNER JOIN customers c ON a.customer_id = c.customer_id
    LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
    LEFT JOIN (
        SELECT appointment_id, GROUP_CONCAT(service_name ORDER BY service_name SEPARATOR ', ') AS services
        FROM appointment_services
        GROUP BY appointment_id
    ) s ON s.appointment_id = a.appointment_id
    $whereSql
    ORDER BY a.preferred_date DESC, STR_TO_DATE(a.preferred_time, '%h:%i %p') DESC
    LIMIT ? OFFSET ?";

$appointments = [];
if ($stmt = $mysqli->prepare($sql)) {
    // Bind dynamic filters plus limit/offset
    if ($types !== '') {
    $types2 = $types . 'ii';
    $bindParams = [$types2];
    foreach ($params as $idx => $value) {
      $bindParams[] = &$params[$idx];
    }
    $bindParams[] = &$perPage;
    $bindParams[] = &$offset;
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $appointments[] = $row; }
    $stmt->close();
}

function uiStatusLabel(string $status): string {
    return match (strtolower(trim($status))) {
        'scheduled' => 'Approved',
        'in progress' => 'In Progress',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($status ?: 'Pending'),
    };
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Appointments • Admin Panel</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="view_appointments.css" />
</head>
<body>
  <!-- SIDEBAR (Appointments active) -->
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
      <a href="view_appointments.php" class="nav-item active">
        <i class='bx bxs-calendar'></i><span>Appointments</span>
      </a>
      <a href="../customers detail/customers.php" class="nav-item">
        <i class='bx bxs-user-detail'></i><span>Customers</span>
      </a>
      <a href="../services/manage_progress.php" class="nav-item">
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
          <h1>Appointments</h1>
          <p>Manage all customer bookings</p>
        </div>
      </div>
    </header>

    <div class="card">
      <div class="card-header" style="flex-direction:column; align-items:stretch; gap:14px;">
        <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
          <h3>Appointment List</h3>
          <span style="font-size:0.75rem; color:var(--text-muted);">Total: <?php echo $total; ?></span>
        </div>
        <form class="filter-bar" method="GET" action="view_appointments.php">
          <input type="text" name="q" placeholder="Search name / plate / model / ID" value="<?php echo e($search); ?>" />
          <select name="status">
            <option value="all" <?php echo $statusFilter==='all'?'selected':''; ?>>All Status</option>
            <option value="pending" <?php echo $statusFilter==='pending'?'selected':''; ?>>Pending</option>
            <option value="scheduled" <?php echo $statusFilter==='scheduled'?'selected':''; ?>>Approved</option>
            <option value="in progress" <?php echo $statusFilter==='in progress'?'selected':''; ?>>In Progress</option>
            <option value="completed" <?php echo $statusFilter==='completed'?'selected':''; ?>>Completed</option>
            <option value="cancelled" <?php echo $statusFilter==='cancelled'?'selected':''; ?>>Cancelled</option>
          </select>
          <button type="submit" class="btn-primary" style="align-self:center;">Apply</button>
        </form>
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
                <th>Services</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($appointments)): ?>
                <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No appointments found.</td></tr>
              <?php else: ?>
                <?php foreach ($appointments as $appt): ?>
                  <?php
                    // Ensure display label and CSS class come from the same canonical status
                    $statusRaw = (string)($appt['status'] ?? '');
                    // If status is empty, treat as Pending (same behaviour as admin dashboard)
                    $displayStatus = $statusRaw !== '' ? $statusRaw : 'Pending';
                    $statusLabel = uiStatusLabel($displayStatus); // For display only
                    $statusClass = statusClass($displayStatus);   // Use canonical status for styling
                    $dateLabel = $appt['preferred_date'] ? date('M d', strtotime($appt['preferred_date'])) : '';
                    $services = $appt['services'] ?: '—';
                    $statusNorm = strtolower(trim($displayStatus));
                  ?>
                  <tr data-status-row data-status="<?php echo e($statusClass); ?>">
                    <td>#<?php echo (int)$appt['appointment_id']; ?></td>
                    <td>
                      <div class="customer-cell">
                        <a href="view_customer.php?id=<?php echo (int)$appt['customer_id']; ?>" class="customer-name"><?php echo e($appt['full_name']); ?></a>
                        <span class="vehicle-info"><?php echo e($appt['vehicle_model']); ?> • <?php echo e($appt['license_plate_number']); ?></span>
                      </div>
                    </td>
                    <td>
                      <?php if (!empty($appt['phone'])): ?>
                        <?php $tel = preg_replace('/[^0-9+]/', '', (string)$appt['phone']); ?>
                        <a href="tel:<?php echo e($tel); ?>" class="customer-phone"><?php echo e($appt['phone']); ?></a>
                      <?php else: ?>
                        <span class="customer-phone" style="opacity:.6;">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?php echo e($dateLabel); ?></div>
                      <small style="color:var(--text-muted)"><?php echo e($appt['preferred_time']); ?></small>
                    </td>
                    <td><span class="services-text"><?php echo e($services); ?></span></td>
                    <td><span class="badge <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span></td>
                    <td>
                      <div style="display:flex; gap:6px;">
                        <?php if ($statusNorm === 'pending' || $statusNorm === ''): ?>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['appointment_id']; ?>">
                            <button type="submit" class="action-btn approve" title="Approve"><i class='bx bx-check'></i></button>
                          </form>
                        <?php endif; ?>
                        <?php if ($statusNorm === 'scheduled' || $statusNorm === 'in progress'): ?>
                          <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['appointment_id']; ?>">
                            <button type="submit" class="action-btn complete" title="Mark Complete"><i class='bx bx-check-double'></i></button>
                          </form>
                        <?php endif; ?>
                        <?php if (!in_array($statusNorm, ['cancelled','completed'], true)): ?>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="appointment_id" value="<?php echo (int)$appt['appointment_id']; ?>">
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
      <?php if ($totalPages > 1): ?>
        <div class="card-body">
          <div class="pagination">
            <?php for ($i=1; $i <= $totalPages; $i++): ?>
              <?php
                $query = $_GET;
                $query['page'] = $i;
                $url = 'view_appointments.php?' . http_build_query($query);
              ?>
              <a href="<?php echo e($url); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="../admin_dashboard.js"></script>
</body>
</html>
