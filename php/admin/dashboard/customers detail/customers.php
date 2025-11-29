<?php
require_once dirname(__DIR__, 3) . '/auth/auth.php'; // checks admin login and connects DB

$adminId = $ADMIN_ID ?? 0;

// --- FETCH ADMIN INFO ---
$adminInfo = [
    'name' => $_SESSION['admin']['username'] ?? 'Admin',
    'email' => $_SESSION['admin']['email'] ?? '',
    'profile_photo' => ''
];

if ($adminId > 0) {
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
            $adminInfo['profile_photo'] = htmlspecialchars($row['profile_photo'] ?? '');
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
}

$profileInitial = strtoupper(substr($adminInfo['name'], 0, 1) ?: 'Q');

// Fetch all customers
$customers = [];
$sql = "SELECT customer_id, full_name, email, phone, member_since FROM customers ORDER BY member_since DESC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $result->close();
}
$totalCustomers = count($customers);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="customers.css">
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
      <a href="customers.php" class="nav-item active">
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
          <img src="<?= htmlspecialchars($adminInfo['profile_photo']) ?>" alt="Profile" />
        <?php else: ?>
          <span><?= htmlspecialchars($profileInitial) ?></span>
        <?php endif; ?>
      </div>
      <div class="user-info">
        <h4><?= htmlspecialchars($adminInfo['name']) ?></h4>
        <span><?= htmlspecialchars($adminInfo['email']) ?></span>
      </div>
      <a href="../../../auth/logout.php" class="logout-btn" title="Logout"><i class='bx bx-log-out'></i></a>
    </div>
  </aside>

  <main class="main-content">
    <header class="top-bar">
      <div class="page-title">
        <button class="mobile-toggle" id="sidebarToggle"><i class='bx bx-menu'></i></button>
        <div>
          <h1>Customers</h1>
          <p>View and manage registered customers.</p>
        </div>
      </div>
    </header>

    <section class="card">
      <div class="card-header">
        <h3>Customer Directory</h3>
        <span class="record-count">Total: <?= $totalCustomers; ?></span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="data-table" aria-label="Customer list">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Contact</th>
                <th scope="col">Joined</th>
                <th scope="col" class="align-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($customers)): ?>
                <tr class="empty-row"><td colspan="5">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($customers as $user): ?>
                  <?php $joinedAt = !empty($user['member_since']) ? date('M d, Y', strtotime($user['member_since'])) : '—'; ?>
                  <tr>
                    <td class="cell-id">#<?= (int)$user['customer_id'] ?></td>
                    <td class="cell-name"><?= htmlspecialchars($user['full_name']) ?></td>
                    <td class="cell-contact">
                      <span class="contact-email"><?= htmlspecialchars($user['email']) ?></span>
                      <span class="contact-phone"><?= htmlspecialchars($user['phone'] ?: 'No phone on file') ?></span>
                    </td>
                    <td class="cell-date"><?= htmlspecialchars($joinedAt) ?></td>
                    <td class="cell-actions">
                      <a href="customer_profile.php?id=<?= $user['customer_id'] ?>" class="btn btn-primary btn-small">View Details</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <script src="../admin_dashboard.js"></script>
</body>
</html>