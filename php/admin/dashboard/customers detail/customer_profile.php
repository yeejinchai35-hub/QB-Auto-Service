<?php
require_once dirname(__DIR__, 3) . '/auth/auth.php';

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

// 1. Validate ID
$customerId = (int)($_GET['id'] ?? 0);
if ($customerId === 0) {
    header("Location: customers.php");
    exit;
}

// 2. Fetch Customer Data (Including Profile Photo and Preferred Contact)
$customer = null;
$stmt = $mysqli->prepare("SELECT * FROM customers WHERE customer_id = ? LIMIT 1");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    die("Customer not found.");
}

// 3. Fetch Vehicles
$vehicles = [];
$vStmt = $mysqli->prepare("SELECT * FROM vehicles WHERE customer_id = ?");
$vStmt->bind_param("i", $customerId);
$vStmt->execute();
$vResult = $vStmt->get_result();
while($row = $vResult->fetch_assoc()) {
    $vehicles[] = $row;
}
$vStmt->close();

// 4. Fetch Appointments (Joined with Vehicles to know which car was fixed)
$appointments = [];
$aSql = "SELECT a.*, v.license_plate_number, v.vehicle_model 
         FROM appointments a
         LEFT JOIN vehicles v ON a.vehicle_id = v.vehicle_id
         WHERE a.customer_id = ? 
         ORDER BY a.preferred_date DESC";
if($aStmt = $mysqli->prepare($aSql)) {
    $aStmt->bind_param("i", $customerId);
    $aStmt->execute();
    $aResult = $aStmt->get_result();
    while($row = $aResult->fetch_assoc()) {
        $appointments[] = $row;
    }
    $aStmt->close();
}

// 5. Helper Logic for Avatar (Matches User Dashboard Logic)
$rawFullName = $customer['full_name'];
$avatarInitial = strtoupper(substr($rawFullName, 0, 1) ?: 'U');
$profilePhotoUrl = $customer['profile_photo'] ?? '';

// FIX: Rebuild the path specifically for the Admin folder structure
if (!empty($profilePhotoUrl)) {
    // 1. Get just the filename (e.g., "customer-28.jpg") to ignore old relative paths
    $filename = basename($profilePhotoUrl);
    
    // 2. Build the correct path relative to THIS file
    // Path: customers_detail -> dashboard -> admin -> php -> (ROOT) -> images -> customers
    $relativePath = '../../../../images/customers/' . $filename;
    
    // 3. Check if file actually exists on the server
    if (file_exists(__DIR__ . '/' . $relativePath)) {
        $profilePhotoUrl = $relativePath;
    } else {
        $profilePhotoUrl = ''; // Fallback to initials if file is missing
    }
}

$memberSinceLabel = !empty($customer['member_since']) ? date('M Y', strtotime($customer['member_since'])) : '—';

// Helper for status colors
function getStatusColor($status) {
    return match(strtolower($status)) {
        'completed' => 'success',
        'scheduled' => 'warning',
        'in progress' => 'info',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin View: <?= htmlspecialchars($customer['full_name']) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
    <link rel="stylesheet" href="customer_profile.css">
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
                <h1>Customer Profile</h1>
                <p>Account overview and booking history.</p>
            </div>
        </div>
        <a href="customers.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i><span>Back to Customers</span></a>
    </header>

    <div class="profile-grid">
        <section class="card profile-card">
            <div class="card-body profile-body">
                <div class="profile-media">
                    <?php if($profilePhotoUrl): ?>
                        <img src="<?= htmlspecialchars($profilePhotoUrl) ?>" alt="User Photo" class="profile-photo">
                    <?php else: ?>
                        <div class="profile-fallback"><?= htmlspecialchars($avatarInitial) ?></div>
                    <?php endif; ?>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($customer['full_name']) ?></h2>
                <p class="profile-email"><?= htmlspecialchars($customer['email']) ?></p>
                <div class="profile-actions">
                    <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" class="btn btn-primary">Send Email</a>
                </div>
                <div class="profile-meta">
                    <span>Customer ID</span>
                    <strong>#<?= $customerId ?></strong>
                </div>
            </div>
        </section>

        <section class="card detail-card">
            <div class="card-header">
                <h3>Contact Details</h3>
            </div>
            <div class="card-body">
                <ul class="detail-list">
                    <li>
                        <span class="detail-label">Phone</span>
                        <span><?= htmlspecialchars($customer['phone'] ?: 'Not Set') ?></span>
                    </li>
                    <li>
                        <span class="detail-label">Preferred Contact</span>
                        <span class="tag"><?= htmlspecialchars($customer['preferred_contact'] ?: 'Email') ?></span>
                    </li>
                    <li>
                        <span class="detail-label">Member Since</span>
                        <span><?= htmlspecialchars($memberSinceLabel) ?></span>
                    </li>
                </ul>
            </div>
        </section>

        <section class="card garage-card">
            <div class="card-header">
                <h3>Garage (<?= count($vehicles) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if(empty($vehicles)): ?>
                    <div class="empty-state">
                        <i class='bx bx-car'></i>
                        <p>User has not added any vehicles yet.</p>
                    </div>
                <?php else: ?>
                    <div class="stack-list" aria-label="Vehicle list">
                        <?php foreach($vehicles as $car): ?>
                            <div class="stack-card">
                                <div class="stack-row">
                                    <div class="stack-label">License Plate</div>
                                    <div class="stack-value"><span class="plate-badge"><?= htmlspecialchars($car['license_plate_number']) ?></span></div>
                                </div>
                                <div class="stack-row">
                                    <div class="stack-label">Model</div>
                                    <div class="stack-value"><?= htmlspecialchars($car['vehicle_model'] ?: 'Unknown Model') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card history-card">
            <div class="card-header">
                <h3>Booking History</h3>
            </div>
            <div class="card-body">
                <?php if(empty($appointments)): ?>
                    <div class="empty-state">
                        <p>No appointment history found.</p>
                    </div>
                <?php else: ?>
                    <div class="stack-list" aria-label="Booking history">
                        <?php foreach($appointments as $appt): ?>
                        <?php 
                            $statusColor = getStatusColor($appt['status']);
                            $date = !empty($appt['preferred_date']) ? date('M d, Y', strtotime($appt['preferred_date'])) : '—';
                            $time = !empty($appt['preferred_time']) ? date('h:i A', strtotime($appt['preferred_time'])) : '—';
                        ?>
                        <div class="stack-card">
                            <div class="stack-row">
                                <div class="stack-label">Date</div>
                                <div class="stack-value">
                                    <div class="date-main"><?= htmlspecialchars($date) ?></div>
                                    <div class="date-sub"><?= htmlspecialchars($time) ?></div>
                                </div>
                            </div>
                            <div class="stack-row">
                                <div class="stack-label">Vehicle</div>
                                <div class="stack-value">
                                    <div class="vehicle-main"><?= htmlspecialchars($appt['vehicle_model']) ?></div>
                                    <div class="vehicle-sub"><?= htmlspecialchars($appt['license_plate_number']) ?></div>
                                </div>
                            </div>
                            <div class="stack-row">
                                <div class="stack-label">Status</div>
                                <div class="stack-value"><span class="status-pill status-<?= $statusColor ?>"><?= htmlspecialchars($appt['status']) ?></span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script src="../admin_dashboard.js"></script>

</body>
</html>