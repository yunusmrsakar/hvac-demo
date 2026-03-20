<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$ref = trim($_GET['ref'] ?? '');

if ($ref === '') {
    header('Location: index.php');
    exit;
}

// Look up the booking
$db   = getDB();
$stmt = $db->prepare(
    'SELECT reference, service_type, customer_name, phone, email,
            preferred_date, time_slot, address, notes, status, created_at
     FROM bookings WHERE reference = ?'
);
$stmt->execute([$ref]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Format date
$dateObj = DateTime::createFromFormat('Y-m-d', $booking['preferred_date']);
$dateStr = $dateObj ? $dateObj->format('l, F j, Y') : htmlspecialchars($booking['preferred_date']);

$statusLabels = [
    'pending'     => 'Pending Confirmation',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];
$statusLabel = $statusLabels[$booking['status']] ?? ucfirst($booking['status']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Booking Confirmed – <?php echo htmlspecialchars(APP_NAME); ?></title>
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
      --success: #10b981;
      --shadow-lg: 0 10px 40px rgba(0,0,0,.15);
      --radius: 12px; --radius-lg: 20px;
      --transition: .3s cubic-bezier(.4,0,.2,1);
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Poppins', sans-serif;
      font-size: 16px;
      color: var(--gray-800);
      background: var(--gray-50);
      line-height: 1.6;
      min-height: 100vh;
    }
    a { color: inherit; text-decoration: none; }
    .container { max-width: 700px; margin: 0 auto; padding: 0 24px; }

    /* ===== HEADER ===== */
    .site-header {
      background: linear-gradient(135deg, var(--navy-dark), var(--navy));
      padding: 0;
    }
    .header-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 68px;
      max-width: 1140px;
      margin: 0 auto;
      padding: 0 24px;
    }
    .nav-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--white);
    }
    .logo-icon {
      width: 36px; height: 36px;
      background: var(--cyan);
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem;
    }
    .logo-text span { color: var(--cyan); }
    .header-links { display: flex; gap: 12px; }
    .header-links a {
      font-size: .85rem;
      font-weight: 500;
      color: rgba(255,255,255,.8);
      padding: 7px 14px;
      border-radius: 8px;
      transition: var(--transition);
    }
    .header-links a:hover { background: rgba(255,255,255,.12); color: var(--white); }

    /* ===== HERO BAND ===== */
    .confirm-hero {
      background: linear-gradient(135deg, var(--navy-dark), var(--navy));
      padding: 60px 24px 80px;
      text-align: center;
    }

    /* Checkmark animation */
    .check-circle {
      width: 100px; height: 100px;
      border-radius: 50%;
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 28px;
      box-shadow: 0 0 0 0 rgba(16,185,129,.5);
      animation: pulse-check 2s ease-out infinite;
      font-size: 3rem;
    }
    @keyframes pulse-check {
      0%   { box-shadow: 0 0 0 0 rgba(16,185,129,.5); }
      70%  { box-shadow: 0 0 0 18px rgba(16,185,129,0); }
      100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
    }

    .confirm-title {
      font-size: clamp(1.8rem, 4vw, 2.6rem);
      font-weight: 800;
      color: var(--white);
      margin-bottom: 12px;
    }

    .confirm-sub {
      font-size: 1rem;
      color: rgba(255,255,255,.7);
      max-width: 480px;
      margin: 0 auto 28px;
    }

    .ref-badge {
      display: inline-block;
      background: rgba(0,188,212,.2);
      border: 1.5px solid rgba(0,188,212,.4);
      color: #80deea;
      font-size: 1.15rem;
      font-weight: 700;
      letter-spacing: .08em;
      padding: 10px 28px;
      border-radius: 50px;
      font-family: 'Courier New', monospace;
    }

    /* ===== CARD ===== */
    .confirm-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      margin: -40px auto 0;
      position: relative;
      z-index: 2;
      overflow: hidden;
    }

    .card-section {
      padding: 32px 40px;
      border-bottom: 1px solid var(--gray-100);
    }
    .card-section:last-child { border-bottom: none; }

    .card-section-title {
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--cyan-dark);
      margin-bottom: 20px;
    }

    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px 32px;
    }

    .detail-item {}
    .detail-label {
      font-size: .78rem;
      color: var(--gray-400);
      font-weight: 500;
      margin-bottom: 3px;
    }
    .detail-value {
      font-size: .95rem;
      color: var(--gray-800);
      font-weight: 600;
    }
    .detail-item.full { grid-column: 1 / -1; }

    /* Status badge */
    .status-badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 50px;
      font-size: .8rem;
      font-weight: 700;
    }
    .status-pending    { background: #fff7ed; color: #c2410c; }
    .status-confirmed  { background: #eff6ff; color: #1d4ed8; }
    .status-in_progress { background: #f5f3ff; color: #6d28d9; }
    .status-completed  { background: #f0fdf4; color: #166534; }
    .status-cancelled  { background: #fef2f2; color: #991b1b; }

    /* Actions */
    .confirm-actions {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      padding: 32px 40px;
      background: var(--gray-50);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: .9rem;
      font-weight: 600;
      padding: 13px 26px;
      border-radius: 50px;
      border: none;
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--cyan);
      color: var(--white);
      box-shadow: 0 4px 20px rgba(0,188,212,.35);
    }
    .btn-primary:hover { background: var(--cyan-dark); transform: translateY(-2px); }
    .btn-outline {
      background: transparent;
      color: var(--navy);
      border: 2px solid var(--gray-200);
    }
    .btn-outline:hover { border-color: var(--cyan); color: var(--cyan); transform: translateY(-2px); }

    /* Info note */
    .info-note {
      background: var(--cyan-light);
      border-left: 4px solid var(--cyan);
      border-radius: 0 var(--radius) var(--radius) 0;
      padding: 16px 20px;
      font-size: .88rem;
      color: var(--cyan-dark);
      margin-top: 0;
    }
    .info-note strong { font-weight: 700; }

    /* ===== FOOTER ===== */
    .site-footer {
      background: var(--navy-dark);
      border-top: 1px solid rgba(255,255,255,.06);
      padding: 28px 24px;
      text-align: center;
      margin-top: 60px;
      font-size: .82rem;
      color: rgba(255,255,255,.35);
    }

    @media (max-width: 600px) {
      .detail-grid { grid-template-columns: 1fr; }
      .card-section { padding: 24px 20px; }
      .confirm-actions { padding: 24px 20px; flex-direction: column; }
      .btn { justify-content: center; }
    }
  </style>
</head>
<body>

<!-- Header -->
<header class="site-header">
  <div class="header-inner">
    <a href="index.php" class="nav-logo">
      <div class="logo-icon">❄️</div>
      <div class="logo-text">CoolBreeze <span>HVAC</span></div>
    </a>
    <div class="header-links">
      <a href="index.php">Home</a>
      <a href="track.php">Track Booking</a>
    </div>
  </div>
</header>

<!-- Hero -->
<div class="confirm-hero">
  <div class="check-circle">✅</div>
  <h1 class="confirm-title">Booking Confirmed!</h1>
  <p class="confirm-sub">
    Thank you, <strong style="color:var(--cyan);"><?php echo htmlspecialchars($booking['customer_name']); ?></strong>!
    Your service request has been received. Our team will call you within 1 hour to confirm.
  </p>
  <div class="ref-badge"><?php echo htmlspecialchars($booking['reference']); ?></div>
</div>

<!-- Card -->
<div class="container">
  <div class="confirm-card">

    <div class="card-section">
      <div class="card-section-title">Booking Details</div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Reference Number</div>
          <div class="detail-value"><?php echo htmlspecialchars($booking['reference']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Status</div>
          <div class="detail-value">
            <span class="status-badge status-<?php echo htmlspecialchars($booking['status']); ?>">
              <?php echo htmlspecialchars($statusLabel); ?>
            </span>
          </div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Service Type</div>
          <div class="detail-value"><?php echo htmlspecialchars($booking['service_type']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Appointment Date</div>
          <div class="detail-value"><?php echo htmlspecialchars($dateStr); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Time Slot</div>
          <div class="detail-value"><?php echo htmlspecialchars($booking['time_slot']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Booked On</div>
          <div class="detail-value">
            <?php
              $created = new DateTime($booking['created_at']);
              echo htmlspecialchars($created->format('M j, Y g:i A'));
            ?>
          </div>
        </div>
        <div class="detail-item full">
          <div class="detail-label">Service Address</div>
          <div class="detail-value"><?php echo htmlspecialchars($booking['address']); ?></div>
        </div>
        <?php if (!empty($booking['notes'])): ?>
        <div class="detail-item full">
          <div class="detail-label">Additional Notes</div>
          <div class="detail-value"><?php echo htmlspecialchars($booking['notes']); ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-section">
      <div class="card-section-title">Customer Information</div>
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

    <div class="card-section">
      <div class="info-note">
        <strong>What happens next?</strong> Our team will review your request and call you at
        <strong><?php echo htmlspecialchars($booking['phone']); ?></strong> within 1 hour to confirm the appointment.
        You can track your booking status anytime using your reference number.
      </div>
    </div>

    <div class="confirm-actions">
      <a href="track.php?ref=<?php echo urlencode($booking['reference']); ?>" class="btn btn-primary">
        🔍 Track This Booking
      </a>
      <a href="index.php" class="btn btn-outline">
        ← Back to Home
      </a>
      <a href="index.php#booking" class="btn btn-outline">
        📅 Book Another Service
      </a>
    </div>

  </div>
</div>

<!-- Footer -->
<footer class="site-footer">
  &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>.
  All rights reserved. &nbsp;·&nbsp; 🏅 EPA Certified &nbsp;·&nbsp; 🔒 Licensed &amp; Insured
</footer>

</body>
</html>
