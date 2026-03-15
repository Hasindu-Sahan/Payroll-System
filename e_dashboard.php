<?php
// ── DB Config ─────────────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'p_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<div style="color:red;padding:20px;">DB Error: ' . $conn->connect_error . '</div>');
}

session_start();

if (!isset($_SESSION['emp_code'])) {
    $_SESSION['emp_code'] = isset($_GET['emp']) ? $_GET['emp'] : 'WY01';
}
$emp_code = $_SESSION['emp_code'];

// ── Fetch employee ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM employees WHERE emp_code = ?");
$stmt->bind_param("s", $emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) die('<div style="color:red;padding:20px;">Employee not found.</div>');

// ── Leave limits (annual = monthly_allowed_leaves * 12) ──────────────────────
$ll = $conn->query("SELECT monthly_allowed_leaves FROM leavelimits LIMIT 1")->fetch_assoc();
$monthly_allowed = (int)($ll['monthly_allowed_leaves'] ?? 2);
$annual_allowed  = $monthly_allowed * 12;   // e.g. 2 * 12 = 24  (set to 18 override below if needed)
// Override: if you want exactly 18, change the line above to: $annual_allowed = 18;

// ── Approved leaves this year ─────────────────────────────────────────────────
$year = date('Y');
$al = $conn->prepare("SELECT leave_dates FROM leaves WHERE emp_code = ? AND leave_status = 'approve' AND YEAR(apply_date) = ?");
$al->bind_param("si", $emp_code, $year);
$al->execute();
$approved_rows = $al->get_result()->fetch_all(MYSQLI_ASSOC);
$al->close();

// Count total approved leave days (each leave_dates can be comma-separated)
$approved_days = 0;
foreach ($approved_rows as $row) {
    $dates = array_filter(array_map('trim', explode(',', $row['leave_dates'])));
    $approved_days += count($dates);
}
$remaining_leaves = max(0, $annual_allowed - $approved_days);

// ── Pending leaves ────────────────────────────────────────────────────────────
$pl = $conn->prepare("SELECT COUNT(*) as cnt FROM leaves WHERE emp_code = ? AND leave_status = 'pending'");
$pl->bind_param("s", $emp_code);
$pl->execute();
$pending_leaves = (int)$pl->get_result()->fetch_assoc()['cnt'];
$pl->close();

// ── Salary data per month for bar chart (current year) ───────────────────────
// Group by pay_month, get net_salary (distinct per month)
$sm = $conn->prepare("
    SELECT pay_month, MAX(net_salary) as net_salary
    FROM salaries
    WHERE emp_code = ?
    GROUP BY pay_month
    ORDER BY MIN(generate_date) ASC
");
$sm->bind_param("s", $emp_code);
$sm->execute();
$salary_rows = $sm->get_result()->fetch_all(MYSQLI_ASSOC);
$sm->close();

// Annual total
$annual_net = array_sum(array_column($salary_rows, 'net_salary'));

// ── Recent attendance (last 10 records) ──────────────────────────────────────
$att = $conn->prepare("
    SELECT attendance_date, action_name, action_time, action_location, emp_desc
    FROM attendance
    WHERE emp_code = ?
    ORDER BY attendance_date DESC, action_time DESC
    LIMIT 10
");
$att->bind_param("s", $emp_code);
$att->execute();
$attendance = $att->get_result()->fetch_all(MYSQLI_ASSOC);
$att->close();

function initials($f, $l) { return strtoupper(substr($f,0,1) . substr($l,0,1)); }

// Build chart data (net salary only)
$chart_labels = [];
$chart_net    = [];
foreach ($salary_rows as $sr) {
    $chart_labels[] = $sr['pay_month'];
    $chart_net[]    = (float)$sr['net_salary'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — MAS Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root {
    --sidebar-bg:   #0b1e3d;
    --sidebar-dark: #071529;
    --sidebar-w:    248px;
    --accent:       #2f80ed;
    --accent2:      #27ae60;
    --border-side:  rgba(255,255,255,0.08);
    --text-side:    #a8c0db;
    --text-side-hi: #ffffff;
    --text-dim-s:   #4e6e8e;

    --right-bg:     #f5f7fb;
    --right-card:   #ffffff;
    --right-border: #e2e8f2;
    --right-text:   #1a2740;
    --right-muted:  #64748b;
    --right-dim:    #94a3b8;
    --right-input:  #f8fafd;

    --danger:  #e53e3e;
    --warn:    #dd6b20;
    --success: #27ae60;
    --r:       10px;
    --r-sm:    7px;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
html, body { height:100%; }
body { font-family:'Outfit',sans-serif; display:flex; min-height:100vh; overflow-x:hidden; background:var(--right-bg); }

/* ══ SIDEBAR ═══════════════════════════════════════════════════════════ */
.sidebar {
    width:var(--sidebar-w); background:var(--sidebar-bg);
    display:flex; flex-direction:column;
    position:fixed; top:0; left:0; bottom:0; z-index:200;
    border-right:1px solid var(--border-side);
}
.sb-brand {
    height:66px; background:var(--sidebar-dark);
    display:flex; align-items:center; gap:11px;
    padding:0 20px; border-bottom:1px solid var(--border-side); flex-shrink:0;
}
.sb-logo-img { width:36px; height:36px; border-radius:8px; object-fit:contain; background:#fff; padding:3px; flex-shrink:0; }
.sb-logo-fallback {
    width:36px; height:36px; border-radius:8px;
    background:linear-gradient(135deg,#2f80ed,#27ae60);
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:800; color:#fff; flex-shrink:0;
}
.sb-company { font-weight:800; font-size:17px; color:#fff; letter-spacing:0.04em; }
.sb-company span { color:#2f80ed; }
.sb-user {
    padding:20px; border-bottom:1px solid var(--border-side);
    display:flex; align-items:center; gap:12px; flex-shrink:0;
}
.sb-avatar {
    width:44px; height:44px; border-radius:50%;
    background:linear-gradient(135deg,#2f80ed,#27ae60);
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:15px; color:#fff;
    flex-shrink:0; position:relative;
    box-shadow:0 0 0 3px rgba(47,128,237,0.25);
}
.sb-avatar .dot {
    position:absolute; bottom:2px; right:2px;
    width:9px; height:9px; border-radius:50%;
    background:#27ae60; border:2px solid var(--sidebar-bg);
}
.sb-name { font-size:13px; font-weight:700; color:var(--text-side-hi); line-height:1.3; }
.sb-dept { font-size:11px; color:var(--text-dim-s); margin-top:2px; }
.sb-nav { flex:1; padding:16px 12px; overflow-y:auto; }
.nav-section {
    font-size:9.5px; font-weight:700; letter-spacing:0.12em;
    text-transform:uppercase; color:var(--text-dim-s);
    padding:0 8px; margin:14px 0 6px;
}
.nav-link {
    display:flex; align-items:center; gap:11px;
    padding:10px 12px; border-radius:var(--r-sm);
    color:var(--text-side); font-size:13.5px; font-weight:500;
    text-decoration:none; margin-bottom:3px;
    transition:all 0.18s; border:1px solid transparent;
}
.nav-link:hover { background:rgba(255,255,255,0.06); color:var(--text-side-hi); }
.nav-link.active { background:rgba(47,128,237,0.18); color:#6db3f7; border-color:rgba(47,128,237,0.28); }
.nav-link i { width:18px; text-align:center; font-size:14px; }
.sb-footer { padding:14px; border-top:1px solid var(--border-side); flex-shrink:0; }
.sb-footer a {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:var(--r-sm);
    color:#e57373; font-size:13px; font-weight:500;
    text-decoration:none; transition:background 0.18s;
}
.sb-footer a:hover { background:rgba(229,57,53,0.1); }

/* ══ MAIN ═══════════════════════════════════════════════════════════════ */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
.topbar {
    height:66px; background:var(--right-card);
    border-bottom:1px solid var(--right-border);
    display:flex; align-items:center; justify-content:space-between;
    padding:0 30px; position:sticky; top:0; z-index:100; flex-shrink:0;
}
.topbar-left h1 { font-size:18px; font-weight:700; color:var(--right-text); }
.topbar-left p  { font-size:12px; color:var(--right-muted); margin-top:1px; }
.topbar-actions { display:flex; gap:10px; align-items:center; }
.tb-icon {
    width:36px; height:36px; border-radius:var(--r-sm);
    background:var(--right-bg); border:1px solid var(--right-border);
    color:var(--right-muted); display:flex; align-items:center; justify-content:center;
    font-size:14px; cursor:pointer; transition:all 0.18s; text-decoration:none;
}
.tb-icon:hover { background:#e8f0fe; color:var(--accent); border-color:#c7d9fc; }
.tb-date { font-size:12px; color:var(--right-muted); font-weight:500; }

.page { padding:28px 30px; flex:1; }

/* ══ WELCOME BANNER ═════════════════════════════════════════════════════ */
.welcome-banner {
    background:linear-gradient(120deg,#0b1e3d 0%,#1a3a6e 55%,#163060 100%);
    border-radius:var(--r);
    padding:26px 28px;
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px;
    position:relative; overflow:hidden;
}
.welcome-banner::before {
    content:''; position:absolute; top:-50px; right:-50px;
    width:200px; height:200px; border-radius:50%;
    background:rgba(47,128,237,0.1); pointer-events:none;
}
.welcome-banner::after {
    content:''; position:absolute; bottom:-40px; right:100px;
    width:120px; height:120px; border-radius:50%;
    background:rgba(39,174,96,0.07); pointer-events:none;
}
.wb-left { position:relative; z-index:1; }
.wb-greeting { font-size:13px; color:rgba(255,255,255,0.55); margin-bottom:4px; }
.wb-name { font-size:24px; font-weight:800; color:#fff; }
.wb-role { font-size:13px; color:rgba(255,255,255,0.65); margin-top:5px; }
.wb-right { position:relative; z-index:1; text-align:right; }
.wb-date-label { font-size:11px; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:0.07em; }
.wb-date-val   { font-size:20px; font-weight:700; color:#fff; margin-top:3px; }
.wb-time       { font-size:13px; color:rgba(255,255,255,0.6); margin-top:2px; font-family:'JetBrains Mono',monospace; }

/* ══ STAT CARDS ROW ═════════════════════════════════════════════════════ */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card {
    background:var(--right-card);
    border:1px solid var(--right-border);
    border-radius:var(--r);
    padding:20px;
    box-shadow:0 1px 4px rgba(0,0,0,0.04);
    display:flex; align-items:flex-start; gap:14px;
    transition:box-shadow 0.2s, transform 0.2s;
}
.stat-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); transform:translateY(-2px); }
.stat-icon-wrap {
    width:46px; height:46px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:18px;
}
.si-blue    { background:#eff6ff; color:#2f80ed; }
.si-green   { background:#f0fff4; color:#27ae60; }
.si-orange  { background:#fff7ed; color:#dd6b20; }
.si-red     { background:#fff5f5; color:#e53e3e; }
.si-purple  { background:#faf5ff; color:#7c3aed; }
.stat-body {}
.stat-val { font-size:26px; font-weight:800; color:var(--right-text); line-height:1; }
.stat-lbl { font-size:12px; color:var(--right-muted); margin-top:4px; font-weight:500; }
.stat-sub { font-size:11.5px; margin-top:8px; display:flex; align-items:center; gap:5px; font-weight:500; }
.sub-green  { color:var(--success); }
.sub-orange { color:var(--warn); }
.sub-red    { color:var(--danger); }
.sub-blue   { color:var(--accent); }

/* ══ CARD TITLE ═════════════════════════════════════════════════════════ */
.card-title {
    font-size:14px; font-weight:700; color:var(--right-text);
    display:flex; align-items:center; gap:8px; margin-bottom:0;
}
.card-title i { color:var(--accent); font-size:15px; }

/* ══ CARD SHELL ════════════════════════════════════════════════════════ */
.card {
    background:var(--right-card);
    border:1px solid var(--right-border);
    border-radius:var(--r);
    box-shadow:0 1px 4px rgba(0,0,0,0.04);
    overflow:hidden;
}
.card-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 22px; border-bottom:1px solid var(--right-border);
}
.card-header .card-title { margin-bottom:0; }
.see-all {
    font-size:12px; color:var(--accent); font-weight:600;
    text-decoration:none; transition:opacity 0.18s; white-space:nowrap;
}
.see-all:hover { opacity:0.7; }
.card-body { padding:20px 22px; }

/* ══ SALARY CHART ═══════════════════════════════════════════════════════ */
.chart-wrap { position:relative; height:280px; }
.chart-empty {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    height:180px; color:var(--right-dim); gap:10px;
}
.chart-empty i { font-size:32px; opacity:0.3; }
.chart-empty p { font-size:13px; }

/* ══ ATTENDANCE TABLE ═══════════════════════════════════════════════════ */
.att-table { width:100%; border-collapse:collapse; font-size:13px; }
.att-table thead tr { background:#f8fafd; }
.att-table thead th {
    padding:10px 14px; text-align:left;
    font-size:10px; font-weight:700; letter-spacing:0.08em;
    text-transform:uppercase; color:var(--right-dim);
    border-bottom:1px solid var(--right-border); white-space:nowrap;
}
.att-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
.att-table tbody tr:last-child { border-bottom:none; }
.att-table tbody tr:hover { background:#f8fafd; }
.att-table tbody td { padding:11px 14px; vertical-align:middle; }
.att-table tbody td:last-child { color:var(--right-muted); font-size:12px; }

.action-pill {
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 10px; border-radius:20px;
    font-size:11.5px; font-weight:600;
}
.pill-in  { background:#f0fff4; color:#15803d; }
.pill-out { background:#fff5f5; color:#c53030; }
.action-pill i { font-size:10px; }

.att-date { font-weight:600; color:var(--right-text); font-size:13px; }
.att-time { font-family:'JetBrains Mono',monospace; font-size:12.5px; color:var(--right-muted); margin-top:2px; }
.location-val {
    font-size:12px; color:var(--right-muted);
    display:flex; align-items:center; gap:5px; flex-wrap:wrap;
}
.location-val i { color:var(--accent); font-size:11px; flex-shrink:0; }
.no-location { color:var(--right-dim); font-size:12px; font-style:italic; }

.table-empty {
    text-align:center; padding:32px 20px;
    color:var(--right-dim);
}
.table-empty i { font-size:28px; display:block; margin-bottom:8px; opacity:0.3; }

/* ══ BADGE ══════════════════════════════════════════════════════════════ */
.badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;
}
.badge-perm   { background:#dcfce7; color:#15803d; }
.badge-part   { background:#dbeafe; color:#1d4ed8; }
.badge-intern { background:#fef3c7; color:#92400e; }
.badge-hol    { background:#fee2e2; color:#b91c1c; }

/* Footer */
footer {
    background:var(--right-card); border-top:1px solid var(--right-border);
    padding:14px 30px; font-size:12px; color:var(--right-dim); text-align:center;
}
footer strong { color:var(--right-muted); }

/* Animation */
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.stats-row   { animation:fadeUp 0.3s ease both; }
.salary-card { animation:fadeUp 0.35s ease 0.05s both; }
.att-card    { animation:fadeUp 0.4s ease 0.1s both; }

@media(max-width:1200px){ .stats-row{grid-template-columns:repeat(2,1fr);} }
@media(max-width:900px) { .stats-row{grid-template-columns:repeat(2,1fr);} }
@media(max-width:768px) { .sidebar{transform:translateX(-100%);} .main{margin-left:0;} .stats-row{grid-template-columns:1fr 1fr;} }
</style>
</head>
<body>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sb-brand">
        <?php if(file_exists('logo.png')): ?>
            <img src="logo.png" alt="MAS" class="sb-logo-img">
        <?php else: ?>
            <div class="sb-logo-fallback">M</div>
        <?php endif; ?>
        <div class="sb-company">M<span>A</span>S</div>
    </div>
    <div class="sb-user">
        <div class="sb-avatar">
            <?php echo initials($emp['first_name'], $emp['last_name']); ?>
            <div class="dot"></div>
        </div>
        <div>
            <div class="sb-name"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
            <div class="sb-dept"><?php echo htmlspecialchars($emp['department']); ?></div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="nav-section">Navigation</div>
        <a href="e_dashboard.php" class="nav-link active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="e_profile.php"   class="nav-link"><i class="fa-solid fa-circle-user"></i> Profile</a>
        <a href="e_leaves.php"    class="nav-link"><i class="fa-solid fa-plane-departure"></i> Leaves</a>
        <a href="e_holidays.php"  class="nav-link"><i class="fa-solid fa-calendar-days"></i> Holidays</a>
        <a href="e_payments.php"  class="nav-link"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
    </nav>
    <div class="sb-footer">
        <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</a>
    </div>
</aside>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="main">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p>Overview of your attendance, leaves &amp; salary</p>
        </div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y'); ?></span>
            <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
            <div class="tb-icon" title="Notifications"><i class="fa-regular fa-bell"></i></div>
        </div>
    </header>

    <div class="page">

        <!-- ── Welcome Banner ───────────────────────────────────────────────── -->
        <div class="welcome-banner">
            <div class="wb-left">
                <div class="wb-greeting">Good <?php
                    $h = (int)date('H');
                    echo $h < 12 ? 'Morning' : ($h < 17 ? 'Afternoon' : 'Evening');
                ?>,</div>
                <div class="wb-name"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?> 👋</div>
                <div class="wb-role">
                    <?php echo htmlspecialchars($emp['designation']); ?> &nbsp;·&nbsp;
                    <?php echo htmlspecialchars($emp['department']); ?> &nbsp;·&nbsp;
                    <?php echo $emp['emp_code']; ?>
                </div>
            </div>
            <div class="wb-right">
                <div class="wb-date-label">Today</div>
                <div class="wb-date-val"><?php echo date('d M Y'); ?></div>
                <div class="wb-time" id="live-clock"><?php echo date('H:i:s'); ?></div>
            </div>
        </div>

        <!-- ── Stat Cards ────────────────────────────────────────────────────── -->
        <div class="stats-row">
            <!-- Total Annual Leaves -->
            <div class="stat-card">
                <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo $annual_allowed; ?></div>
                    <div class="stat-lbl">Annual Leave Quota</div>
                    <div class="stat-sub sub-blue"><i class="fa-solid fa-circle-info"></i> <?php echo $monthly_allowed; ?> per month</div>
                </div>
            </div>
            <!-- Remaining Leaves -->
            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="fa-solid fa-leaf"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:var(--success);"><?php echo $remaining_leaves; ?></div>
                    <div class="stat-lbl">Remaining Leaves</div>
                    <div class="stat-sub sub-green"><i class="fa-solid fa-check-circle"></i> out of <?php echo $annual_allowed; ?> total</div>
                </div>
            </div>
            <!-- Approved (Used) -->
            <div class="stat-card">
                <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-plane-departure"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:var(--warn);"><?php echo $approved_days; ?></div>
                    <div class="stat-lbl">Leaves Taken (<?php echo $year; ?>)</div>
                    <div class="stat-sub sub-orange"><i class="fa-solid fa-circle-check"></i> Approved &amp; used</div>
                </div>
            </div>
            <!-- Pending Approvals -->
            <div class="stat-card">
                <div class="stat-icon-wrap si-red"><i class="fa-regular fa-clock"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:var(--danger);"><?php echo $pending_leaves; ?></div>
                    <div class="stat-lbl">Pending Approval</div>
                    <div class="stat-sub <?php echo $pending_leaves > 0 ? 'sub-red' : 'sub-green'; ?>">
                        <i class="fa-solid <?php echo $pending_leaves > 0 ? 'fa-hourglass-half' : 'fa-check'; ?>"></i>
                        <?php echo $pending_leaves > 0 ? 'Awaiting review' : 'All cleared'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Annual Net Salary Banner ─────────────────────────────────────── -->
        <?php if (!empty($salary_rows)): ?>
        <div style="background:linear-gradient(120deg,#0b1e3d 0%,#1a3a6e 55%,#163060 100%);border-radius:var(--r);padding:20px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:rgba(47,128,237,0.1);pointer-events:none;"></div>
            <div style="position:relative;z-index:1;">
                <div style="font-size:11px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px;">Annual Net Salary</div>
                <div style="font-size:32px;font-weight:800;color:#fff;letter-spacing:-0.5px;">LKR <?php echo number_format($annual_net, 2); ?></div>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.6);margin-top:5px;"><?php echo count($salary_rows); ?> pay period<?php echo count($salary_rows) !== 1 ? 's' : ''; ?> recorded</div>
            </div>
            <div style="position:relative;z-index:1;text-align:right;">
                <div style="font-size:11px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:6px;">Latest Pay Period</div>
                <?php $ls = end($salary_rows); ?>
                <div style="font-size:15px;font-weight:700;color:#fff;"><?php echo htmlspecialchars($ls['pay_month']); ?></div>
                <div style="font-size:20px;font-weight:800;color:#6db3f7;margin-top:2px;">LKR <?php echo number_format($ls['net_salary'], 2); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Salary Chart (full width) ────────────────────────────────────── -->
        <div class="card salary-card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-money-bill-trend-up"></i> Monthly Net Salary (LKR)
                </div>
                <a href="e_payments.php" class="see-all">View All →</a>
            </div>
            <div class="card-body">
                <?php if (empty($salary_rows)): ?>
                <div class="chart-empty">
                    <i class="fa-solid fa-chart-bar"></i>
                    <p>No salary records found.</p>
                </div>
                <?php else: ?>
                <div class="chart-wrap">
                    <canvas id="salaryChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Attendance Table ──────────────────────────────────────────────── -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Punch In / Out</div>
                <div style="font-size:12px;color:var(--right-muted);">Last 10 records</div>
            </div>
            <?php if (empty($attendance)): ?>
            <div class="table-empty"><i class="fa-solid fa-clock"></i>No attendance records found.</div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Action</th>
                            <th>Location</th>
                            <th>Note / Description</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($attendance as $a):
                        $is_in  = $a['action_name'] === 'punchin';
                        $loc    = trim($a['action_location'] ?? '');
                        $desc   = trim($a['emp_desc'] ?? '');
                    ?>
                        <tr>
                            <td>
                                <div class="att-date"><?php echo date('d M Y', strtotime($a['attendance_date'])); ?></div>
                                <div style="font-size:11px;color:var(--right-dim);"><?php echo date('l', strtotime($a['attendance_date'])); ?></div>
                            </td>
                            <td>
                                <span style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;color:var(--right-text);">
                                    <?php echo date('h:i A', strtotime($a['action_time'])); ?>
                                </span>
                                <div style="font-size:11px;color:var(--right-dim);"><?php echo $a['action_time']; ?></div>
                            </td>
                            <td>
                                <span class="action-pill <?php echo $is_in ? 'pill-in' : 'pill-out'; ?>">
                                    <i class="fa-solid <?php echo $is_in ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'; ?>"></i>
                                    <?php echo $is_in ? 'Punch In' : 'Punch Out'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($loc): ?>
                                    <div class="location-val">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <span><?php echo htmlspecialchars($loc); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="no-location"><i class="fa-regular fa-map" style="margin-right:4px;opacity:0.4;"></i>Not recorded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($desc): ?>
                                    <span style="font-size:12.5px;color:var(--right-muted);"><?php echo htmlspecialchars($desc); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--right-dim);font-style:italic;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /page -->

    <footer>
        <strong>&copy; <?php echo date('Y'); ?> MAS Payroll Management System</strong> &nbsp;|&nbsp; All rights reserved.
    </footer>
</div><!-- /main -->

<script>
/* ── Live Clock ──────────────────────────────────────────────────── */
function updateClock() {
    const now = new Date();
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('live-clock').textContent =
        pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
}
setInterval(updateClock, 1000);

/* ── Net Salary Bar Chart (LKR, net only) ────────────────────────── */
(function(){
    const ctx = document.getElementById('salaryChart');
    if (!ctx) return;

    const labels  = <?php echo json_encode($chart_labels); ?>;
    const netData = <?php echo json_encode($chart_net); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Net Salary (LKR)',
                    data: netData,
                    backgroundColor: function(context) {
                        const chart = context.chart;
                        const {ctx: c, chartArea} = chart;
                        if (!chartArea) return 'rgba(47,128,237,0.85)';
                        const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0,   'rgba(47,128,237,0.92)');
                        gradient.addColorStop(1,   'rgba(47,128,237,0.45)');
                        return gradient;
                    },
                    borderColor: 'rgba(47,128,237,1)',
                    borderWidth: 0,
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(47,128,237,1)',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1000, easing: 'easeInOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#1a2740',
                    bodyColor: '#2f80ed',
                    borderColor: '#e2e8f2',
                    borderWidth: 1,
                    padding: 14,
                    cornerRadius: 10,
                    titleFont: { family:'Outfit', weight:'700', size:13 },
                    bodyFont:  { family:'Outfit', size:13, weight:'600' },
                    callbacks: {
                        title: items => items[0].label,
                        label: item => '  Net Salary: LKR ' + Number(item.raw).toLocaleString('en-LK', {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { family:'Outfit', size:12 },
                        color: '#94a3b8',
                        maxRotation: 30,
                    }
                },
                y: {
                    grid: { color:'#f1f5f9' },
                    border: { display:false, dash:[4,4] },
                    ticks: {
                        font: { family:'Outfit', size:11 },
                        color: '#94a3b8',
                        callback: val => 'LKR ' + (val >= 1000 ? (val/1000).toFixed(0)+'K' : val)
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>