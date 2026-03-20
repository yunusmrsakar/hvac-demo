<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch booking
$stmt = $db->prepare('SELECT * FROM bookings WHERE id = ?');
$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: index.php');
    exit;
}

$success = '';
$error   = '';

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    $newStatus     = $_POST['status']      ?? '';
    $adminNotes    = trim($_POST['admin_notes'] ?? '');

    if (!in_array($newStatus, $validStatuses, true)) {
        $error = 'Invalid status selected.';
    } else {
        $upd = $db->prepare(
            'UPDATE bookings
             SET status = :status, admin_notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $upd->execute([
            ':status' => $newStatus,
            ':notes'  => $adminNotes,
            ':id'     => $id,
        ]);

        // Refresh from DB
        $stmt->execute([$id]);
        $booking = $stmt->fetch();

        $success = 'Booking updated successfully.';
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$statusConfig = [
    'pending'     => ['label' => 'Pending',     'bg' => '#fff7ed', 'color' => '#c2410c'],
    'confirmed'   => ['label' => 'Confirmed',   'bg' => '#eff6ff', 'color' => '#1d4ed8'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#f5f3ff', 'color' => '#6d28d9'],
    'completed'   => ['label' => 'Completed',   'bg' => '#f0fdf4', 'color' => '#166534'],
    'cancelled'   => ['label' => 'Cancelled',   'bg' => '#fef2f2', 'color' => '#991b1b'],
];

$dateObj  = DateTime::createFromFormat('Y-m-d', $booking['preferred_date']);
$dateDisp = $dateObj ? $dateObj->format('l, F j, Y') : htmlspecialchars($booking['preferred_date']);

$created = new DateTime($booking['created_at']);
$updated = new DateTime($booking['updated_at']);
$cfg     = $statusConfig[$booking['status']] ?? ['label' => ucfirst($booking['status']), 'bg' => '#f1f5f9', 'color' => '#475569'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Booking <?php echo htmlspecialchars($booking['reference']); ?> – Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy: #1a237e; --navy-dark: #0d1257; --navy-light: #283593;
      --cyan: #00bcd4; --cyan-dark: #0097a7; --cyan-light: #e0f7fa;
      --white: #ffffff;
      --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
      --gray-400: #94a3b8; --gray-600: #475569; --gray-800: #1e293b;
      --shadow: 0 4px 16px rgba(0,0,0,.08); --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
      --radius: 10px; --radius-lg: 16px; --transition: .25s ease;
    }
    body {
      font-family: 'Poppins', sans-serif; font-size: 14px;
      background: var(--gray-50); color: var(--gray-800); min-height: 100vh;
    }

    /* ===== TOP NAV ===== */
    .topnav {
      background: linear-gradient(135deg, var(--navy-dark), var(--navy));
      height: 64px; display: flex; align-items: center;
      padding: 0 32px; gap: 24px;
      box-shadow: 0 2px 12px rgba(0,0,0,.2);
    }
    .topnav-logo {
      display: flex; align-items: center; gap: 10px;
      font-size: 1.05rem; font-weight: 800; color: var(--white); margin-right: 16px;
    }
    .topnav-logo .icon {
      width: 34px; height: 34px; background: var(--cyan); border-radius: 9px;
      display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .topnav-logo span { color: var(--cyan); }
    .topnav-link {
      color: rgba(255,255,255,.75); font-size: .87rem; font-weight: 600;
      padding: 7px 14px; border-radius: 8px; transition: var(--transition); text-decoration: none;
    }
    .topnav-link:hover { background: rgba(255,255,255,.12); color: var(--white); }
    .topnav-spacer { flex: 1; }
    .topnav-logout {
      background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
      color: var(--white); font-family: 'Poppins', sans-serif;
      font-size: .82rem; font-weight: 600; padding: 7px 16px; border-radius: 8px;
      cursor: pointer; transition: var(--transition); text-decoration: none;
    }
    .topnav-logout:hover { background: rgba(255,255,255,.2); }

    /* ===== MAIN ===== */
    .main { max-width: 960px; margin: 0 auto; padding: 32px 24px; }

    /* Breadcrumb */
    .breadcrumb {
      font-size: .82rem; color: var(--gray-400);
      margin-bottom: 20px; display: flex; align-items: center; gap: 6px;
    }
    .breadcrumb a { color: var(--cyan-dark); text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }

    /* Page heading */
    .page-heading {
      display: flex; align-items: flex-start; justify-content: space-between;
      flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
    }
    .page-title { font-size: 1.4rem; font-weight: 800; color: var(--navy); }
    .page-ref   { font-size: .9rem; color: var(--gray-400); font-family: 'Courier New', monospace; margin-top: 4px; }

    .status-badge {
      display: inline-block; padding: 6px 18px; border-radius: 50px;
      font-size: .82rem; font-weight: 700;
    }

    /* ===== GRID LAYOUT ===== */
    .content-grid {
      display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start;
    }
    @media (max-width: 860px) { .content-grid { grid-template-columns: 1fr; } }

    /* ===== CARDS ===== */
    .card {
      background: var(--white); border-radius: var(--radius-lg);
      box-shadow: var(--shadow); border: 1px solid var(--gray-200);
      overflow: hidden; margin-bottom: 20px;
    }
    .card:last-child { margin-bottom: 0; }
    .card-header {
      padding: 16px 24px; border-bottom: 1px solid var(--gray-100);
      display: flex; align-items: center; gap: 10px;
    }
    .card-header h3 { font-size: .9rem; font-weight: 700; color: var(--navy); }
    .card-body { padding: 24px; }

    /* Detail rows */
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
    .detail-item.full { grid-column: 1 / -1; }
    .detail-label { font-size: .76rem; color: var(--gray-400); font-weight: 600;
                    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
    .detail-value { font-size: .9rem; color: var(--gray-800); font-weight: 600; }

    /* ===== UPDATE FORM ===== */
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { font-size: .82rem; font-weight: 600; color: var(--gray-600); }
    .form-control {
      padding: 11px 14px; font-family: 'Poppins', sans-serif; font-size: .87rem;
      color: var(--gray-800); background: var(--gray-50);
      border: 1.5px solid var(--gray-200); border-radius: 8px; outline: none;
      transition: var(--transition); -webkit-appearance: none;
    }
    .form-control:focus { border-color: var(--cyan); background: var(--white);
                          box-shadow: 0 0 0 3px rgba(0,188,212,.12); }
    textarea.form-control { resize: vertical; min-height: 110px; }

    .btn-save {
      width: 100%; padding: 13px; background: var(--cyan); color: var(--white);
      font-family: 'Poppins', sans-serif; font-size: .9rem; font-weight: 700;
      border: none; border-radius: 10px; cursor: pointer; transition: var(--transition);
      box-shadow: 0 4px 16px rgba(0,188,212,.3); margin-top: 4px;
    }
    .btn-save:hover { background: var(--cyan-dark); transform: translateY(-2px); }

    .btn-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 10px 20px; background: transparent; color: var(--navy);
      font-family: 'Poppins', sans-serif; font-size: .87rem; font-weight: 600;
      border: 2px solid var(--gray-200); border-radius: 10px; cursor: pointer;
      transition: var(--transition); text-decoration: none; margin-bottom: 24px;
    }
    .btn-back:hover { border-color: var(--cyan); color: var(--cyan); transform: translateY(-1px); }

    /* Alerts */
    .alert {
      padding: 14px 18px; border-radius: 10px; font-size: .88rem;
      margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }
    .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    /* History row */
    .history-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 0; border-bottom: 1px solid var(--gray-100);
      font-size: .85rem;
    }
    .history-row:last-child { border-bottom: none; }
    .history-label { color: var(--gray-400); font-weight: 500; }
    .history-value { color: var(--gray-800); font-weight: 600; }

    .link-track {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--cyan-dark); font-weight: 600; font-size: .85rem;
      text-decoration: none; transition: var(--transition);
    }
    .link-track:hover { color: var(--cyan); }
  </style>
</head>
<body>

<nav class="topnav">
  <div class="topnav-logo">
    <div class="icon">❄️</div>
    CoolBreeze <span>HVAC</span>
  </div>
  <a href="index.php" class="topnav-link">📊 Dashboard</a>
  <a href="index.php" class="topnav-link">📋 Bookings</a>
  <a href="../index.php" class="topnav-link" target="_blank">🌐 View Site</a>
  <div class="topnav-spacer"></div>
  <a href="logout.php" class="topnav-logout">🚪 Logout</a>
</nav>

<div class="main">

  <div class="breadcrumb">
    <a href="index.php">Dashboard</a> › Booking
    <strong style="color:var(--gray-600);"><?php echo htmlspecialchars($booking['reference']); ?></strong>
  </div>

  <a href="index.php" class="btn-back">← Back to Dashboard</a>

  <?php if ($success): ?>
  <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="page-heading">
    <div>
      <div class="page-title">Booking Details</div>
      <div class="page-ref"><?php echo htmlspecialchars($booking['reference']); ?></div>
    </div>
    <span class="status-badge"
          style="background:<?php echo htmlspecialchars($cfg['bg']); ?>;color:<?php echo htmlspecialchars($cfg['color']); ?>;">
      <?php echo htmlspecialchars($cfg['label']); ?>
    </span>
  </div>

  <div class="content-grid">

    <!-- Left column: booking + customer info -->
    <div>
      <!-- Service Details -->
      <div class="card">
        <div class="card-header"><h3>📋 Service Details</h3></div>
        <div class="card-body">
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Service Type</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['service_type']); ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Appointment Date</div>
              <div class="detail-value"><?php echo htmlspecialchars($dateDisp); ?></div>
            </div>
            <div class="detail-item full">
              <div class="detail-label">Time Slot</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['time_slot']); ?></div>
            </div>
            <div class="detail-item full">
              <div class="detail-label">Service Address</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['address']); ?></div>
            </div>
            <?php if (!empty($booking['notes'])): ?>
            <div class="detail-item full">
              <div class="detail-label">Customer Notes</div>
              <div class="detail-value"><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Customer Info -->
      <div class="card">
        <div class="card-header"><h3>👤 Customer Information</h3></div>
        <div class="card-body">
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Full Name</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Phone</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['phone']); ?></div>
            </div>
            <div class="detail-item full">
              <div class="detail-label">Email</div>
              <div class="detail-value"><?php echo htmlspecialchars($booking['email']); ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Booking History -->
      <div class="card">
        <div class="card-header"><h3>🕐 Booking History</h3></div>
        <div class="card-body" style="padding-top:8px;padding-bottom:8px;">
          <div class="history-row">
            <span class="history-label">Booking Created</span>
            <span class="history-value"><?php echo htmlspecialchars($created->format('M j, Y g:i A')); ?></span>
          </div>
          <div class="history-row">
            <span class="history-label">Last Updated</span>
            <span class="history-value"><?php echo htmlspecialchars($updated->format('M j, Y g:i A')); ?></span>
          </div>
          <div class="history-row">
            <span class="history-label">Current Status</span>
            <span class="history-value">
              <span class="status-badge" style="background:<?php echo htmlspecialchars($cfg['bg']); ?>;color:<?php echo htmlspecialchars($cfg['color']); ?>;">
                <?php echo htmlspecialchars($cfg['label']); ?>
              </span>
            </span>
          </div>
          <div class="history-row">
            <span class="history-label">Customer Tracking</span>
            <span class="history-value">
              <a href="../track.php?ref=<?php echo urlencode($booking['reference']); ?>"
                 class="link-track" target="_blank">
                🔍 View Tracker
              </a>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right column: update form -->
    <div>
      <div class="card">
        <div class="card-header"><h3>✏️ Update Booking</h3></div>
        <div class="card-body">
          <form method="POST" action="">
            <div class="form-group">
              <label for="status">Status</label>
              <select name="status" id="status" class="form-control">
                <?php foreach ($statusConfig as $key => $c): ?>
                <option value="<?php echo htmlspecialchars($key); ?>"
                        <?php echo $booking['status'] === $key ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['label']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="admin_notes">Admin Notes</label>
              <textarea name="admin_notes" id="admin_notes" class="form-control"
                        placeholder="Internal notes for your team, or a message to the customer…"><?php echo htmlspecialchars($booking['admin_notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-save">💾 Save Changes</button>
          </form>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="card">
        <div class="card-header"><h3>⚡ Quick Actions</h3></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <a href="../confirmation.php?ref=<?php echo urlencode($booking['reference']); ?>"
             target="_blank"
             style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;font-weight:600;color:var(--navy);text-decoration:none;transition:var(--transition);">
            📄 View Customer Confirmation
          </a>
          <a href="../track.php?ref=<?php echo urlencode($booking['reference']); ?>"
             target="_blank"
             style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;font-weight:600;color:var(--navy);text-decoration:none;transition:var(--transition);">
            🔍 View Tracking Page
          </a>
          <a href="index.php"
             style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;font-weight:600;color:var(--navy);text-decoration:none;transition:var(--transition);">
            ← Back to All Bookings
          </a>
        </div>
      </div>
    </div>

  </div><!-- /.content-grid -->
</div><!-- /.main -->
</body>
</html>
