<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/auth/auth.php';

$adminId = $ADMIN_ID;
if ($adminId === 0) {
    redirect('/project/Capstone-Car-Service-Draft4/home.html');
}

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

// Fetch profile photo if column exists
$photoColumnExists = false;
if ($result = $mysqli->query("SHOW COLUMNS FROM admins LIKE 'profile_photo'")) {
  $photoColumnExists = $result->num_rows > 0;
  $result->close();
}

if ($photoColumnExists && ($photoStmt = $mysqli->prepare('SELECT profile_photo FROM admins WHERE admin_id = ? LIMIT 1'))) {
  $photoStmt->bind_param('i', $adminId);
  $photoStmt->execute();
  if ($row = $photoStmt->get_result()->fetch_assoc()) {
    $adminInfo['profile_photo'] = $row['profile_photo'] ?: '';
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

$settingsSuccess = $_SESSION['admin_settings_message'] ?? null;
$settingsErrors = $_SESSION['admin_settings_errors'] ?? [];
unset($_SESSION['admin_settings_message'], $_SESSION['admin_settings_errors']);
if (!is_array($settingsErrors)) {
    $settingsErrors = $settingsErrors ? [$settingsErrors] : [];
}

$profileInitial = strtoupper(substr($adminInfo['name'], 0, 1) ?: 'A');
$memberSinceLabel = date('F Y', strtotime($adminInfo['member_since']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Settings • Admin Panel</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="admin_settings.css" />
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
      <a href="../customers detail/customers.php" class="nav-item">
        <i class='bx bxs-user-detail'></i><span>Customers</span>
      </a>
      <a href="../services/manage_progress.php" class="nav-item">
        <i class='bx bxs-wrench'></i><span>Services</span>
      </a>
      <a href="admin_settings.php" class="nav-item active">
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
          <h1>Account Settings</h1>
          <p>Update your profile details, password, and avatar.</p>
        </div>
      </div>
    </header>

    <section class="settings-grid">
      <div class="settings-card profile-info">
        <h2>Profile Information</h2>
        <p class="settings-help">Member since <?php echo e($memberSinceLabel); ?></p>
        
        <?php if ($settingsSuccess): ?>
          <div class="alert success">
            <span><?php echo e($settingsSuccess); ?></span>
            <i class='bx bx-x alert-close'></i>
          </div>
        <?php endif; ?>
        
        <?php if (!empty($settingsErrors)): ?>
          <div class="alert error">
            <div style="display:flex; flex-direction:column; gap:4px;">
              <?php foreach ($settingsErrors as $error): ?>
                <span><?php echo e($error); ?></span>
              <?php endforeach; ?>
            </div>
            <i class='bx bx-x alert-close'></i>
          </div>
        <?php endif; ?>
        
        <form class="settings-form" method="POST" action="update_admin_info.php">
          
          <div class="form-row">
            <div class="form-field">
              <label for="admin-name">Display Name <span class="text-danger">*</span></label>
              <input type="text" id="admin-name" name="username" value="<?php echo e($adminInfo['name']); ?>" required>
            </div>
            <div class="form-field">
              <label for="admin-email">Email <span class="text-danger">*</span></label>
              <input type="email" id="admin-email" name="email" value="<?php echo e($adminInfo['email']); ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="admin-phone">Phone Number</label>
              <input type="tel" id="admin-phone" name="phone" value="<?php echo e($adminInfo['phone'] ?? ''); ?>" placeholder="012-3456789">
            </div>
          </div>

          <hr class="form-divider">

          <div class="form-row">
            <div class="form-field">
              <label for="admin-password">New Password</label>
              <div class="password-wrapper">
                  <input type="password" id="admin-password" name="password" placeholder="Leave blank to keep current">
                  <i class='bx bx-show toggle-password' data-target="admin-password"></i>
              </div>
            </div>
            <div class="form-field">
              <label for="admin-password-confirm">Confirm Password</label>
              <div class="password-wrapper">
                  <input type="password" id="admin-password-confirm" name="confirm_password" placeholder="Repeat new password">
                  <i class='bx bx-show toggle-password' data-target="admin-password-confirm"></i>
              </div>
            </div>
          </div>
          
          <p class="settings-help">Password fields are optional. Leave empty to keep current password.</p>
          
          <button type="submit" class="btn-primary">Save Changes</button>
        </form>
      </div>

      <div class="settings-card profile-photo">
        <h2>Profile Photo</h2>
        <div class="profile-panel">
          <div class="profile-preview">
            <?php if ($adminInfo['profile_photo']): ?>
              <img src="<?php echo e($adminInfo['profile_photo']); ?>" alt="Profile preview">
            <?php else: ?>
              <span><?php echo e($profileInitial); ?></span>
            <?php endif; ?>
          </div>
          <p class="settings-help">Upload a square image (JPG, PNG, WEBP). Max size 2 MB.</p>
        </div>
        <form class="profile-upload" method="POST" action="/project/Capstone-Car-Service-Draft4/php/admin/dashboard/settings/upload_profile_photo.php" enctype="multipart/form-data">
          <div class="upload-group">
            <input type="file" name="photo" accept="image/*" required class="upload-input" />
            <button type="submit" class="btn-primary">Upload Photo</button>
          </div>
        </form>
      </div>

      <div class="settings-card create-admin">
        <h2>Create New Admin</h2>
        <p class="settings-help">Add a new administrator to the system.</p>
        <form class="settings-form" method="POST" action="create_admin.php">
          <div class="form-row">
            <div class="form-field">
              <label for="new-username">Username <span class="text-danger">*</span></label>
              <input id="new-username" type="text" name="new_username" required>
            </div>
            <div class="form-field">
              <label for="new-email">Email <span class="text-danger">*</span></label>
              <input id="new-email" type="email" name="new_email" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-field">
              <label for="new-password">Password <span class="text-danger">*</span></label>
              <div class="password-wrapper">
                  <input id="new-password" type="password" name="new_password" required minlength="8">
                  <i class='bx bx-show toggle-password' data-target="new-password"></i>
              </div>
            </div>
            <div class="form-field">
              <label for="new-password-confirm">Confirm Password <span class="text-danger">*</span></label>
              <div class="password-wrapper">
                  <input id="new-password-confirm" type="password" name="new_password_confirm" required>
                  <i class='bx bx-show toggle-password' data-target="new-password-confirm"></i>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-primary green">Create Admin</button>
        </form>
      </div>

      <div class="settings-card danger delete-account">
        <h2>Delete Account</h2>
        <p class="settings-help">Permanently remove your admin account.</p>
        <form method="POST" action="delete_admin.php" onsubmit="return confirm('Are you absolutely sure? This cannot be undone.');">
          <div class="form-row align-items-end">
            <div class="form-field" style="flex:2;">
              <label for="confirm-delete">Type 'DELETE' to confirm</label>
              <input id="confirm-delete" type="text" name="confirm_delete" placeholder="DELETE" required>
            </div>
            <button type="submit" class="btn-primary red">Delete Account</button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <script src="../admin_dashboard.js"></script>
  <script src="admin_settings.js"></script>
</html>
</body>
</html>
