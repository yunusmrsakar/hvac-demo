<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = trim($_GET['status'] ?? '');
$filterFrom   = trim($_GET['from']   ?? '');
$filterTo     = trim($_GET['to']     ?? '');
$search       = trim($_GET['q']      ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [];
foreach (['total','pending','confirmed','in_progress','completed','cancelled'] as $s) {
    if ($s === 'total') {
        $stmt = $db->query('SELECT COUNT(*) FROM bookings');
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) FROM bookings WHERE status = ?');
        $stmt->execute([$s]);
    }
    $stats[$s] = (int)$stmt->fetchColumn();
}

// ── Build filtered query ──────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($filterStatus !== '') {
    $where[]  = 'status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterFrom !== '') {
    $where[]  = 'preferred_date >= :from';
    $params[':from'] = $filterFrom;
}
if ($filterTo !== '') {
    $where[]  = 'preferred_date <= :to';
    $params[':to'] = $filterTo;
}
if ($search !== '') {
    $where[]  = '(customer_name LIKE :q OR reference LIKE :q2 OR email LIKE :q3)';
    $params[':q']  = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM bookings $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Fetch current page
$listStmt = $db->prepare(
    "SELECT id, reference, customer_name, service_type, preferred_date,
            time_slot, status, created_at
     FROM bookings $whereSql
     ORDER BY created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $listStmt->bindValue($k, $v);
}
$listStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$listStmt->execute();
$bookings = $listStmt->fetchAll();

// ── Helpers ───────────────────────────────────────────────────────────────────
$statusConfig = [
    'pending'     => ['label' => 'Pending',     'bg' => '#fff7ed', 'color' => '#c2410c'],
    'confirmed'   => ['label' => 'Confirmed',   'bg' => '#eff6ff', 'color' => '#1d4ed8'],
    'in_progress' => ['label' => 'In Progress', 'bg' => '#f5f3ff', 'color' => '#6d28d9'],
    'completed'   => ['label' => 'Completed',   'bg' => '#f0fdf4', 'color' => '#166534'],
    'cancelled'   => ['label' => 'Cancelled',   'bg' => '#fef2f2', 'color' => '#991b1b'],
];

function buildPageUrl(int $p, array $overrides = []): string {
    $params = array_merge([
        'status' => $_GET['status'] ?? '',
        'from'   => $_GET['from']   ?? '',
        'to'     => $_GET['to']     ?? '',
        'q'      => $_GET['q']      ?? '',
        'page'   => $p,
    ], $overrides);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== ''));
    return 'index.php' . ($qs ? '?' . $qs : '');
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard – <?php echo htmlspecialchars(APP_NAME); ?></title>
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
      background: var(--gray-50); color: var(--gray-800);
      min-height: 100vh;
    }

    /* ===== TOP NAV ===== */
    .topnav {
      background: linear-gradient(135deg, var(--navy-dark), var(--navy));
      height: 64px;
      display: flex; align-items: center;
      padding: 0 32px;
      gap: 24px;
      box-shadow: 0 2px 12px rgba(0,0,0,.2);
    }
    .topnav-logo {
      display: flex; align-items: center; gap: 10px;
      font-size: 1.05rem; font-weight: 800; color: var(--white);
      margin-right: 16px;
    }
    .topnav-logo .icon {
      width: 34px; height: 34px; background: var(--cyan);
      border-radius: 9px; display: flex; align-items: center;
      justify-content: center; font-size: 1.1rem;
    }
    .topnav-logo span { color: var(--cyan); }
    .topnav-link {
      color: rgba(255,255,255,.75);
      font-size: .87rem; font-weight: 600;
      padding: 7px 14px; border-radius: 8px;
      transition: var(--transition); text-decoration: none;
    }
    .topnav-link:hover, .topnav-link.active {
      background: rgba(255,255,255,.12); color: var(--white);
    }
    .topnav-spacer { flex: 1; }
    .topnav-logout {
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.2);
      color: var(--white); font-family: 'Poppins', sans-serif;
      font-size: .82rem; font-weight: 600;
      padding: 7px 16px; border-radius: 8px; cursor: pointer;
      transition: var(--transition); text-decoration: none;
    }
    .topnav-logout:hover { background: rgba(255,255,255,.2); }

    /* ===== MAIN ===== */
    .main { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }

    .page-title {
      font-size: 1.5rem; font-weight: 800; color: var(--navy); margin-bottom: 6px;
    }
    .page-sub {
      font-size: .87rem; color: var(--gray-400); margin-bottom: 32px;
    }

    /* ===== STATS ===== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 16px; margin-bottom: 32px;
    }
    .stat-card {
      background: var(--white); border-radius: var(--radius-lg);
      padding: 24px 20px; box-shadow: var(--shadow);
      border: 1px solid var(--gray-200); transition: var(--transition);
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
    .stat-icon { font-size: 1.6rem; margin-bottom: 10px; }
    .stat-num { font-size: 2rem; font-weight: 800; color: var(--navy); line-height: 1; }
    .stat-lbl { font-size: .78rem; color: var(--gray-400); font-weight: 600;
                text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
    .stat-card.stat-pending .stat-num   { color: #c2410c; }
    .stat-card.stat-confirmed .stat-num { color: #1d4ed8; }
    .stat-card.stat-progress .stat-num  { color: #6d28d9; }
    .stat-card.stat-done .stat-num      { color: #166534; }

    /* ===== FILTER BAR ===== */
    .filter-bar {
      background: var(--white); border-radius: var(--radius-lg);
      padding: 20px 24px; box-shadow: var(--shadow);
      border: 1px solid var(--gray-200); margin-bottom: 24px;
    }
    .filter-form {
      display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
    }
    .filter-group { display: flex; flex-direction: column; gap: 4px; }
    .filter-label { font-size: .76rem; font-weight: 600; color: var(--gray-600); }
    .filter-control {
      padding: 9px 14px; font-family: 'Poppins', sans-serif;
      font-size: .85rem; color: var(--gray-800);
      background: var(--gray-50); border: 1.5px solid var(--gray-200);
      border-radius: 8px; outline: none; transition: var(--transition);
    }
    .filter-control:focus { border-color: var(--cyan); background: var(--white); }
    .filter-control.search { min-width: 220px; }
    .btn-filter {
      padding: 9px 20px;
      background: var(--cyan); color: var(--white);
      font-family: 'Poppins', sans-serif; font-size: .85rem; font-weight: 700;
      border: none; border-radius: 8px; cursor: pointer; transition: var(--transition);
    }
    .btn-filter:hover { background: var(--cyan-dark); }
    .btn-reset {
      padding: 9px 16px;
      background: transparent; color: var(--gray-400);
      font-family: 'Poppins', sans-serif; font-size: .85rem; font-weight: 600;
      border: 1.5px solid var(--gray-200); border-radius: 8px;
      cursor: pointer; transition: var(--transition); text-decoration: none;
      display: inline-flex; align-items: center;
    }
    .btn-reset:hover { border-color: var(--gray-400); color: var(--gray-600); }

    /* ===== TABLE ===== */
    .table-card {
      background: var(--white); border-radius: var(--radius-lg);
      box-shadow: var(--shadow); border: 1px solid var(--gray-200);
      overflow: hidden; margin-bottom: 24px;
    }
    .table-header {
      padding: 18px 24px; border-bottom: 1px solid var(--gray-100);
      display: flex; align-items: center; justify-content: space-between;
    }
    .table-header h3 { font-size: .95rem; font-weight: 700; color: var(--navy); }
    .table-meta { font-size: .8rem; color: var(--gray-400); }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      padding: 12px 16px; text-align: left;
      font-size: .75rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      color: var(--gray-400);
      background: var(--gray-50);
      border-bottom: 1px solid var(--gray-200);
    }
    tbody tr { transition: background var(--transition); }
    tbody tr:hover { background: var(--gray-50); }
    tbody tr:not(:last-child) td { border-bottom: 1px solid var(--gray-100); }
    td { padding: 14px 16px; font-size: .87rem; color: var(--gray-800); }

    .ref-cell { font-weight: 700; font-family: 'Courier New', monospace;
                font-size: .82rem; color: var(--cyan-dark); }
    .name-cell { font-weight: 600; }
    .date-cell { color: var(--gray-600); }

    .status-badge {
      display: inline-block; padding: 3px 12px;
      border-radius: 50px; font-size: .76rem; font-weight: 700;
    }

    .btn-view {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 14px; background: var(--navy); color: var(--white);
      font-family: 'Poppins', sans-serif; font-size: .78rem; font-weight: 700;
      border-radius: 8px; text-decoration: none; transition: var(--transition);
    }
    .btn-view:hover { background: var(--navy-light); transform: translateY(-1px); }

    .empty-state {
      text-align: center; padding: 60px 20px;
      color: var(--gray-400); font-size: .92rem;
    }
    .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }

    /* ===== PAGINATION ===== */
    .pagination {
      display: flex; align-items: center; justify-content: center;
      gap: 6px; padding: 12px;
    }
    .page-link {
      display: inline-flex; align-items: center; justify-content: center;
      width: 36px; height: 36px; border-radius: 8px;
      font-size: .85rem; font-weight: 600;
      text-decoration: none; transition: var(--transition);
      color: var(--gray-600); background: var(--white);
      border: 1px solid var(--gray-200);
    }
    .page-link:hover { background: var(--cyan-light); color: var(--cyan-dark); border-color: var(--cyan); }
    .page-link.active { background: var(--navy); color: var(--white); border-color: var(--navy); }
    .page-link.disabled { opacity: .4; pointer-events: none; }

    @media (max-width: 900px) {
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
      .topnav { padding: 0 16px; gap: 8px; }
    }
    @media (max-width: 600px) {
      table thead { display: none; }
      table td { display: block; padding: 6px 16px; }
      table td::before { content: attr(data-label); font-weight: 700;
                         color: var(--gray-400); font-size: .75rem;
                         display: block; margin-bottom: 2px; }
      tbody tr { display: block; padding: 12px 0; border-bottom: 1px solid var(--gray-100); }
    }
  </style>
</head>
<body>

<!-- Top Nav -->
<nav class="topnav">
  <div class="topnav-logo">
    <div class="icon">❄️</div>
    CoolBreeze <span>HVAC</span>
  </div>
  <a href="index.php" class="topnav-link active">📊 Dashboard</a>
  <a href="index.php" class="topnav-link">📋 Bookings</a>
  <a href="../index.php" class="topnav-link" target="_blank">🌐 View Site</a>
  <div class="topnav-spacer"></div>
  <a href="logout.php" class="topnav-logout">🚪 Logout</a>
</nav>

<!-- Main -->
<div class="main">

  <div class="page-title">Admin Dashboard</div>
  <div class="page-sub">Manage all bookings, update statuses, and monitor service activity.</div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-num"><?php echo $stats['total']; ?></div>
      <div class="stat-lbl">Total Bookings</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-icon">🕐</div>
      <div class="stat-num"><?php echo $stats['pending']; ?></div>
      <div class="stat-lbl">Pending</div>
    </div>
    <div class="stat-card stat-confirmed">
      <div class="stat-icon">✅</div>
      <div class="stat-num"><?php echo $stats['confirmed']; ?></div>
      <div class="stat-lbl">Confirmed</div>
    </div>
    <div class="stat-card stat-progress">
      <div class="stat-icon">🔧</div>
      <div class="stat-num"><?php echo $stats['in_progress']; ?></div>
      <div class="stat-lbl">In Progress</div>
    </div>
    <div class="stat-card stat-done">
      <div class="stat-icon">🎉</div>
      <div class="stat-num"><?php echo $stats['completed']; ?></div>
      <div class="stat-lbl">Completed</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">❌</div>
      <div class="stat-num"><?php echo $stats['cancelled']; ?></div>
      <div class="stat-lbl">Cancelled</div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <form class="filter-form" method="GET" action="">
      <div class="filter-group">
        <span class="filter-label">Status</span>
        <select name="status" class="filter-control">
          <option value="">All Statuses</option>
          <?php foreach ($statusConfig as $key => $cfg): ?>
          <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($cfg['label']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <span class="filter-label">From Date</span>
        <input type="date" name="from" class="filter-control" value="<?php echo htmlspecialchars($filterFrom); ?>" />
      </div>
      <div class="filter-group">
        <span class="filter-label">To Date</span>
        <input type="date" name="to" class="filter-control" value="<?php echo htmlspecialchars($filterTo); ?>" />
      </div>
      <div class="filter-group">
        <span class="filter-label">Search</span>
        <input
          type="text" name="q"
          class="filter-control search"
          placeholder="Name, reference or email…"
          value="<?php echo htmlspecialchars($search); ?>"
        />
      </div>
      <button type="submit" class="btn-filter">Filter</button>
      <a href="index.php" class="btn-reset">Reset</a>
    </form>
  </div>

  <!-- Bookings Table -->
  <div class="table-card">
    <div class="table-header">
      <h3>Bookings</h3>
      <div class="table-meta">
        Showing <?php echo min($offset + 1, $totalRows); ?>–<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> bookings
      </div>
    </div>

    <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="icon">📭</div>
      <div>No bookings found matching your criteria.</div>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Customer</th>
          <th>Service</th>
          <th>Date</th>
          <th>Time Slot</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $row):
          $cfg       = $statusConfig[$row['status']] ?? ['label' => ucfirst($row['status']), 'bg' => '#f1f5f9', 'color' => '#475569'];
          $dateObj   = DateTime::createFromFormat('Y-m-d', $row['preferred_date']);
          $dateDisp  = $dateObj ? $dateObj->format('M j, Y') : htmlspecialchars($row['preferred_date']);
          $created   = new DateTime($row['created_at']);
        ?>
        <tr>
          <td data-label="Reference" class="ref-cell"><?php echo htmlspecialchars($row['reference']); ?></td>
          <td data-label="Customer"  class="name-cell"><?php echo htmlspecialchars($row['customer_name']); ?></td>
          <td data-label="Service"><?php echo htmlspecialchars($row['service_type']); ?></td>
          <td data-label="Date" class="date-cell"><?php echo htmlspecialchars($dateDisp); ?></td>
          <td data-label="Time"><?php echo htmlspecialchars($row['time_slot']); ?></td>
          <td data-label="Status">
            <span class="status-badge"
                  style="background:<?php echo htmlspecialchars($cfg['bg']); ?>;color:<?php echo htmlspecialchars($cfg['color']); ?>;">
              <?php echo htmlspecialchars($cfg['label']); ?>
            </span>
          </td>
          <td data-label="Created" class="date-cell"><?php echo htmlspecialchars($created->format('M j, Y')); ?></td>
          <td data-label="Actions">
            <a href="booking.php?id=<?php echo (int)$row['id']; ?>" class="btn-view">✏️ View/Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <a href="<?php echo buildPageUrl($page - 1); ?>"
         class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>

      <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);
        if ($start > 1) {
          echo '<a href="' . buildPageUrl(1) . '" class="page-link">1</a>';
          if ($start > 2) echo '<span style="padding:0 4px;color:var(--gray-400);">…</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
          $active = $i === $page ? 'active' : '';
          echo '<a href="' . buildPageUrl($i) . '" class="page-link ' . $active . '">' . $i . '</a>';
        }
        if ($end < $totalPages) {
          if ($end < $totalPages - 1) echo '<span style="padding:0 4px;color:var(--gray-400);">…</span>';
          echo '<a href="' . buildPageUrl($totalPages) . '" class="page-link">' . $totalPages . '</a>';
        }
      ?>

      <a href="<?php echo buildPageUrl($page + 1); ?>"
         class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">›</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div><!-- /.main -->
</body>
</html>
