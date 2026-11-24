<?php
session_start();
if(!isset($_SESSION["Login_Type"])||$_SESSION["Login_Type"]!=="employee"){header("Location: login.php");exit;}
date_default_timezone_set("Asia/Colombo");
require_once __DIR__ . '/db.php';
$conn = db();


if (!isset($_SESSION['emp_code'])||empty($_SESSION['emp_code'])) { header('Location: login.php');exit; // 
    // emp_code already in session from login
}
$emp_code = $_SESSION['emp_code'];

// ── Fetch employee ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM employees WHERE emp_code = ?");
$stmt->bind_param("s", $emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) die('<div style="color:red;padding:20px;">Employee not found.</div>');

// ── Handle Profile Update ─────────────────────────────────────────────────────
$update_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $contact_no = trim($_POST['contact_no'] ?? '');
    if (!preg_match('/^\d{10}$/', $contact_no)) {
        $update_msg = 'invalid_contact';
    } else {
    $fields = ['first_name','last_name','date_of_birth','gender','address','email','contact_no','employment_type','date_of_joining','designation','section','bank_name','bank_account_no'];    $sets = []; $vals = []; $types = '';
    foreach ($fields as $f) {
        $sets[] = "`$f` = ?";
        $vals[] = trim($_POST[$f] ?? '');
        $types .= 's';
    }
    $types .= 's';
    $vals[] = $emp_code;
    $sql = "UPDATE employees SET " . implode(', ', $sets) . " WHERE emp_code = ?";
    $upd = $conn->prepare($sql);
    $upd->bind_param($types, ...$vals);
    if ($upd->execute()) {
        $update_msg = 'success';
        $s2 = $conn->prepare("SELECT * FROM employees WHERE emp_code = ?");
        $s2->bind_param("s", $emp_code);
        $s2->execute();
        $emp = $s2->get_result()->fetch_assoc();
        $s2->close();
    } else {
        $update_msg = 'error';
    }
    $upd->close();
    }
}

// ── Handle Password Reset ─────────────────────────────────────────────────────
$pw_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $current = trim($_POST['current_password'] ?? '');
    $new_pw  = trim($_POST['new_password']      ?? '');
    $confirm = trim($_POST['confirm_password']  ?? '');

    $ps = $conn->prepare("SELECT password FROM employees WHERE emp_code = ?");
    $ps->bind_param("s", $emp_code);
    $ps->execute();
    $stored = $ps->get_result()->fetch_assoc()['password'];
    $ps->close();

    if (sha1($current) !== $stored) {
        $pw_msg = 'wrong_current';
    } elseif (strlen($new_pw) < 6) {
        $pw_msg = 'too_short';
    } elseif ($new_pw !== $confirm) {
        $pw_msg = 'mismatch';
    } else {
        $new_hash = sha1($new_pw);
        $pu = $conn->prepare("UPDATE employees SET password = ? WHERE emp_code = ?");
        $pu->bind_param("ss", $new_hash, $emp_code);
        $pu->execute();
        $pw_msg = $pu->execute() ? 'pw_success' : 'pw_error';
        $pu->close();
    }
}

function initials($f, $l) { return strtoupper(substr($f,0,1) . substr($l,0,1)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile — Tiptop Apparel Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
body { font-family:'Outfit',sans-serif; display:flex; min-height:100vh; overflow-x:hidden; background:var(--right-bg); font-size:156.25%; }

/* ══════════ SIDEBAR ══════════════════════════════════════════════════ */
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
    font-size:16px; font-weight:800; color:#fff; flex-shrink:0;
}
.sb-company { font-weight:800; font-size:21px; color:#fff; letter-spacing:0.04em; white-space:nowrap; }
.sb-company span { color:#2f80ed; }

.sb-user {
    padding:20px; border-bottom:1px solid var(--border-side);
    display:flex; align-items:center; gap:12px; flex-shrink:0;
}
.sb-avatar {
    width:44px; height:44px; border-radius:50%;
    background:linear-gradient(135deg,#2f80ed,#27ae60);
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:19px; color:#fff;
    flex-shrink:0; position:relative;
    box-shadow:0 0 0 3px rgba(47,128,237,0.25);
}
.sb-avatar .dot {
    position:absolute; bottom:2px; right:2px;
    width:9px; height:9px; border-radius:50%;
    background:#27ae60; border:2px solid var(--sidebar-bg);
}
.sb-name { font-size:16px; font-weight:700; color:var(--text-side-hi); line-height:1.3; }
.sb-dept { font-size:14px; color:var(--text-dim-s); margin-top:2px; }

.sb-nav { flex:1; padding:16px 12px; overflow-y:auto; }
.nav-section {
    font-size:12.5px; font-weight:700; letter-spacing:0.12em;
    text-transform:uppercase; color:var(--text-dim-s);
    padding:0 8px; margin:14px 0 6px;
}
.nav-link {
    display:flex; align-items:center; gap:11px;
    padding:10px 12px; border-radius:var(--r-sm);
    color:var(--text-side); font-size:16px; font-weight:500;
    text-decoration:none; margin-bottom:3px;
    transition:all 0.18s; border:1px solid transparent;
}
.nav-link:hover { background:rgba(255,255,255,0.06); color:var(--text-side-hi); }
.nav-link.active { background:rgba(47,128,237,0.18); color:#6db3f7; border-color:rgba(47,128,237,0.28); }
.nav-link i { width:18px; text-align:center; font-size:18px; }
.sb-footer { padding:14px; border-top:1px solid var(--border-side); flex-shrink:0; }
.sb-footer a {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:var(--r-sm);
    color:#e57373; font-size:16px; font-weight:500;
    text-decoration:none; transition:background 0.18s;
}
.sb-footer a:hover { background:rgba(229,57,53,0.1); }

/* ══════════ MAIN / RIGHT PANEL ═══════════════════════════════════════ */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }

.topbar {
    height:66px; background:var(--right-card);
    border-bottom:1px solid var(--right-border);
    display:flex; align-items:center; justify-content:space-between;
    padding:0 30px; position:sticky; top:0; z-index:100; flex-shrink:0;
}
.topbar-left h1 { font-size:22px; font-weight:700; color:var(--right-text); }
.topbar-left p  { font-size:15px; color:var(--right-muted); margin-top:1px; }
.topbar-actions { display:flex; gap:10px; align-items:center; }
.tb-icon {
    width:36px; height:36px; border-radius:var(--r-sm);
    background:var(--right-bg); border:1px solid var(--right-border);
    color:var(--right-muted); display:flex; align-items:center; justify-content:center;
    font-size:18px; cursor:pointer; transition:all 0.18s; text-decoration:none;
}
.tb-icon:hover { background:#e8f0fe; color:var(--accent); border-color:#c7d9fc; }

.page { padding:28px 30px; flex:1; }

/* ══════════ PROFILE CARD ══════════════════════════════════════════════ */
.profile-card {
    background:var(--right-card);
    border:1px solid var(--right-border);
    border-radius:var(--r);
    overflow:hidden;
    margin-bottom:20px;
    box-shadow:0 1px 6px rgba(0,0,0,0.05);
}

/* Hero banner */
.profile-hero {
    background:linear-gradient(120deg,#0b1e3d 0%,#1a3a6e 55%,#163060 100%);
    padding:30px 28px;
    display:flex; align-items:center; gap:22px;
    position:relative; overflow:hidden;
}
.profile-hero::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; border-radius:50%;
    background:rgba(47,128,237,0.12); pointer-events:none;
}
.profile-hero::after {
    content:''; position:absolute; bottom:-40px; right:80px;
    width:130px; height:130px; border-radius:50%;
    background:rgba(39,174,96,0.08); pointer-events:none;
}
.profile-avatar-lg {
    width:80px; height:80px; border-radius:50%;
    background:linear-gradient(135deg,#2f80ed,#27ae60);
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:35px; color:#fff;
    box-shadow:0 0 0 4px rgba(255,255,255,0.18),0 6px 20px rgba(0,0,0,0.3);
    flex-shrink:0; position:relative; z-index:1;
}
.hero-info { position:relative; z-index:1; }
.hero-info h2 { font-size:28px; font-weight:800; color:#fff; }
.hero-code { font-family:'JetBrains Mono',monospace; font-size:16px; color:rgba(255,255,255,0.5); margin-top:5px; }
.hero-sub { font-size:16px; color:rgba(255,255,255,0.72); margin-top:7px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

.hero-actions { margin-left:auto; position:relative; z-index:1; display:flex; flex-direction:column; gap:8px; align-items:flex-end; }
.hero-btn {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 16px; border-radius:var(--r-sm);
    font-family:'Outfit',sans-serif; font-size:16px; font-weight:600;
    cursor:pointer; transition:all 0.2s; border:none; white-space:nowrap;
}
.hero-btn-edit {
    background:rgba(255,255,255,0.13); color:#fff;
    border:1px solid rgba(255,255,255,0.22); backdrop-filter:blur(6px);
}
.hero-btn-edit:hover { background:rgba(255,255,255,0.22); }
.hero-btn-pw {
    background:rgba(245,166,35,0.18); color:#ffd27a;
    border:1px solid rgba(245,166,35,0.32); backdrop-filter:blur(6px);
}
.hero-btn-pw:hover { background:rgba(245,166,35,0.3); }

/* Badges */
.badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:20px; font-size:15px; font-weight:600;
}
.badge-perm   { background:#dcfce7; color:#15803d; }
.badge-part   { background:#dbeafe; color:#1d4ed8; }
.badge-intern { background:#fef3c7; color:#92400e; }
.badge-hol    { background:#fee2e2; color:#b91c1c; }

/* ══════════ INNER TABS ════════════════════════════════════════════════ */
.inner-tabs {
    display:flex; border-bottom:1px solid var(--right-border);
    padding:0 28px; background:var(--right-card);
}
.inner-tab {
    padding:14px 20px; font-size:16px; font-weight:600;
    color:var(--right-muted); cursor:pointer;
    border-bottom:3px solid transparent; margin-bottom:-1px;
    transition:all 0.18s; display:flex; align-items:center; gap:7px;
    user-select:none;
}
.inner-tab:hover { color:var(--right-text); }
.inner-tab.active { color:var(--accent); border-bottom-color:var(--accent); }

/* ══════════ INNER PANELS ══════════════════════════════════════════════ */
.inner-panel { display:none; }
.inner-panel.active { display:block; animation:fadeUp 0.26s ease both; }
@keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* ══════════ VIEW MODE ═════════════════════════════════════════════════ */
.profile-view { padding:26px 28px; }
.view-section-title {
    font-size:14px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;
    color:var(--accent); margin-bottom:14px; padding-bottom:7px;
    border-bottom:2px solid #e8f0fe;
}
.view-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-bottom:22px; }
.view-grid.c2 { grid-template-columns:repeat(2,1fr); }
.view-grid.c1 { grid-template-columns:1fr; }
.view-label { font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:0.07em; color:var(--right-dim); margin-bottom:5px; }
.view-value {
    font-size:16px; font-weight:500; color:var(--right-text);
    background:var(--right-input); border:1px solid var(--right-border);
    border-radius:var(--r-sm); padding:9px 12px; min-height:38px;
}

/* ══════════ EDIT FORM ═════════════════════════════════════════════════ */
.edit-wrap { display:none; padding:26px 28px; }
.edit-wrap.open { display:block; animation:fadeUp 0.26s ease both; }

.form-section-hdr {
    font-size:14px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;
    color:var(--accent); margin:0 0 12px;
    padding-bottom:7px; border-bottom:2px solid #e8f0fe;
}
.mt-hdr { margin-top:22px; }
.form-row { display:grid; gap:14px; margin-bottom:14px; }
.form-row.c3 { grid-template-columns:repeat(3,1fr); }
.form-row.c2 { grid-template-columns:repeat(2,1fr); }
.form-row.c1 { grid-template-columns:1fr; }
.form-group label {
    display:block; font-size:14px; font-weight:600;
    text-transform:uppercase; letter-spacing:0.07em;
    color:var(--right-muted); margin-bottom:5px;
}
.form-control {
    width:100%; background:var(--right-input);
    border:1px solid var(--right-border); border-radius:var(--r-sm);
    color:var(--right-text); font-family:'Outfit',sans-serif; font-size:16px;
    padding:9px 12px; outline:none;
    transition:border-color 0.2s, box-shadow 0.2s; -webkit-appearance:none;
}
.form-control:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(47,128,237,0.1); }
.form-control::placeholder { color:var(--right-dim); }
textarea.form-control { resize:vertical; min-height:70px; }
select.form-control { cursor:pointer; }
.form-control.is-error { border-color:#fed7d7; box-shadow:0 0 0 3px rgba(229,57,53,0.08); }

/* ══════════ PASSWORD PANEL ════════════════════════════════════════════ */
.pw-panel-body { padding:30px 28px; max-width:560px; }

/* Password field */
.pw-field-wrap { position:relative; }
.pw-field-wrap .form-control { padding-right:42px; }
.pw-eye {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:var(--right-dim);
    cursor:pointer; font-size:18px; padding:0;
    transition:color 0.18s;
}
.pw-eye:hover { color:var(--accent); }

/* Strength bar */
.strength-wrap { margin-top:8px; }
.strength-bar { height:5px; border-radius:3px; background:#e2e8f2; overflow:hidden; margin-bottom:5px; }
.strength-fill { height:100%; border-radius:3px; width:0; transition:width 0.3s ease, background 0.3s ease; }
.strength-text { font-size:15px; color:var(--right-dim); font-weight:500; }

/* Rules */
.pw-rules { margin-top:12px; display:flex; flex-direction:column; gap:6px; }
.pw-rule { font-size:16px; color:var(--right-dim); display:flex; align-items:center; gap:7px; transition:color 0.2s; }
.pw-rule i { font-size:15px; transition:color 0.2s; }
.pw-rule.pass { color:var(--success); }
.pw-rule.fail { color:var(--danger); }

/* Match indicator */
.match-msg { font-size:16px; margin-top:6px; display:none; }

/* Inline form alert */
.form-alert {
    padding:12px 16px; border-radius:var(--r-sm);
    font-size:16px; font-weight:500;
    display:flex; align-items:center; gap:10px;
    margin-bottom:20px;
}
.alert-error   { background:#fff5f5; border:1px solid #fed7d7; color:#c53030; }
.alert-success { background:#f0fff4; border:1px solid #c6f6d5; color:#276749; }

/* Divider inside form */
.form-divider { height:1px; background:var(--right-border); margin:20px 0; }

/* Security tips box */
.security-tips {
    margin-top:24px; padding:16px 18px;
    background:#f8fafd; border:1px solid var(--right-border);
    border-radius:var(--r-sm);
}
.security-tips-title {
    font-size:15px; font-weight:700; text-transform:uppercase;
    letter-spacing:0.07em; color:var(--right-dim); margin-bottom:10px;
    display:flex; align-items:center; gap:6px;
}
.security-tips-title i { color:var(--accent); }
.security-tips ul { font-size:16px; color:var(--right-muted); padding-left:18px; line-height:2.1; margin:0; }

/* ══════════ BUTTONS ═══════════════════════════════════════════════════ */
.btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 20px; border-radius:var(--r-sm);
    font-family:'Outfit',sans-serif; font-size:16px; font-weight:600;
    cursor:pointer; border:none; text-decoration:none; transition:all 0.2s;
}
.btn-primary { background:var(--accent); color:#fff; box-shadow:0 2px 8px rgba(47,128,237,0.25); }
.btn-primary:hover { background:#2570d4; box-shadow:0 4px 14px rgba(47,128,237,0.35); transform:translateY(-1px); }
.btn-warning { background:var(--warn); color:#fff; box-shadow:0 2px 8px rgba(221,107,32,0.25); }
.btn-warning:hover { background:#c05c16; transform:translateY(-1px); }
.btn-ghost { background:#f1f5f9; color:var(--right-muted); border:1px solid var(--right-border); }
.btn-ghost:hover { background:#e2e8f0; color:var(--right-text); }
.btn-sm { padding:7px 16px; font-size:16px; }
.form-actions { display:flex; gap:10px; margin-top:22px; padding-top:18px; border-top:1px solid var(--right-border); }

/* ══════════ TOAST ═════════════════════════════════════════════════════ */
.toast {
    position:fixed; bottom:26px; right:26px; z-index:9999;
    padding:13px 20px; border-radius:var(--r-sm);
    font-size:16px; font-weight:500;
    display:flex; align-items:center; gap:10px;
    box-shadow:0 4px 20px rgba(0,0,0,0.1);
    animation:slideIn 0.3s ease both;
}
.toast-success { background:#fff; border:1px solid #c6f6d5; color:#276749; }
.toast-error   { background:#fff; border:1px solid #fed7d7; color:#c53030; }
@keyframes slideIn { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }

/* Footer */
footer {
    background:var(--right-card); border-top:1px solid var(--right-border);
    padding:14px 30px; font-size:15px; color:var(--right-dim); text-align:center;
}
footer strong { color:var(--right-muted); }

/* Responsive */
@media(max-width:1024px){.view-grid{grid-template-columns:repeat(2,1fr);}.form-row.c3{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}.view-grid,.form-row.c3,.form-row.c2{grid-template-columns:1fr;}.hero-actions{flex-direction:column;gap:6px;}.profile-hero{flex-wrap:wrap;}.hero-actions{margin-left:0;width:100%;flex-direction:row;}}
</style>
</head>
<body>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sb-brand">
        <?php if(file_exists('Logo.jpeg')): ?>
            <img src="Logo.jpeg" alt="Tiptop Apparel" class="sb-logo-img">
        <?php else: ?>
            <div class="sb-logo-fallback" style="font-size:13px;font-weight:800;text-align:center;line-height:1.2;">TA</div>
        <?php endif; ?>
        <div class="sb-company">Tiptop <span>Apparel</span></div>
    </div>
    <div class="sb-user">
        <div class="sb-avatar">
            <?php echo initials($emp['first_name'], $emp['last_name']); ?>
            <div class="dot"></div>
        </div>
        <div>
            <div class="sb-name"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
            <div class="sb-dept"><?php echo htmlspecialchars($emp['section']); ?></div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="nav-section">Navigation</div>
        <a href="e_dashboard.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="e_leaves.php"    class="nav-link"><i class="fa-solid fa-plane-departure"></i> My Leaves</a>
        <a href="e_holidays.php"  class="nav-link"><i class="fa-solid fa-calendar-days"></i> Holidays</a>
        <a href="e_payments.php"  class="nav-link"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
        <a href="e_profile.php"   class="nav-link active"><i class="fa-solid fa-circle-user"></i> Profile</a>
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
            <h1>My Profile</h1>
            <p><?php echo htmlspecialchars($emp['designation']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($emp['section']); ?></p>
        </div>
            <div class="topbar-actions">
                <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
                <a href="e_dashboard.php" class="tb-icon" title="Dashboard" style="margin-left:10px;"><i class="fa-solid fa-gauge-high"></i></a>
            </div>


    </header>

    <div class="page">
        <div class="profile-card">

            <!-- ── Hero Banner ────────────────────────────────────────────── -->
            <div class="profile-hero">
                <div class="profile-avatar-lg"><?php echo initials($emp['first_name'], $emp['last_name']); ?></div>
                <div class="hero-info">
                    <h2><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></h2>
                    <div class="hero-code"><?php echo $emp['emp_code']; ?> &nbsp;·&nbsp; <?php echo ucfirst($emp['gender']); ?></div>
                    <div class="hero-sub">
                        <?php echo htmlspecialchars($emp['designation']); ?>
                        <?php
                        $bt = ['Permanent position'=>'badge-perm','Part-time employee'=>'badge-part','Intern'=>'badge-intern','Holiday worker'=>'badge-hol'];
                        $bc = $bt[$emp['employment_type']] ?? 'badge-part';
                        ?>
                        <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($emp['employment_type']); ?></span>
                    </div>
                </div>
                <div class="hero-actions">
                    <button class="hero-btn hero-btn-edit" onclick="gotoTab('details-tab','details-panel'); showEditForm();">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                    <button class="hero-btn hero-btn-pw" onclick="gotoTab('password-tab','password-panel')">
                        <i class="fa-solid fa-lock"></i> Change Password
                    </button>
                </div>
            </div>

            <!-- ── Inner Tabs ─────────────────────────────────────────────── -->
                <div class="inner-tabs">
                    <div class="inner-tab active" id="details-tab" onclick="gotoTab('details-tab','details-panel')">
                        <i class="fa-regular fa-id-card"></i> Profile Details
                    </div>
                    <div class="inner-tab" id="password-tab" onclick="gotoTab('password-tab','password-panel')">
                        <i class="fa-solid fa-key"></i> Change Password
                    </div>
                </div>
                <div style="margin:18px 0 0; padding:16px 18px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:var(--r-sm); display:flex; align-items:center; gap:14px; max-width:560px;">
                    <?php if(file_exists('Logo.jpeg')): ?>
                        <img src="Logo.jpeg" alt="Tiptop Apparel" style="width:44px;height:44px;border-radius:12px;object-fit:contain;background:#fff;padding:4px;">
                    <?php else: ?>
                        <div style="width:44px;height:44px;border-radius:12px; background:linear-gradient(135deg,#0b1e3d,#1a3a6e); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900;">TA</div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:19px; font-weight:900; color:#0b1e3d;">Tiptop Apparel</div>
                        <div style="font-size:15px; color:#64748b; margin-top:2px;">Company logo + name</div>
                    </div>
                </div>


            <!-- ════════════════════════════════════════════════════════════
                 PANEL 1 — PROFILE DETAILS
            ════════════════════════════════════════════════════════════════ -->
            <div class="inner-panel active" id="details-panel">

                <!-- VIEW MODE -->
                <div id="view-mode" class="profile-view">

                    <div class="view-section-title">Personal Information</div>
                    <div class="view-grid">
                        <div><div class="view-label">First Name</div><div class="view-value"><?php echo htmlspecialchars($emp['first_name']); ?></div></div>
                        <div><div class="view-label">Last Name</div><div class="view-value"><?php echo htmlspecialchars($emp['last_name']); ?></div></div>
                        <div><div class="view-label">Date of Birth</div><div class="view-value"><?php echo htmlspecialchars($emp['date_of_birth'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Gender</div><div class="view-value"><?php echo ucfirst(htmlspecialchars($emp['gender'])); ?></div></div>
                    </div>

                    <div class="view-section-title">Contact Information</div>
                    <div class="view-grid c2">
                        <div><div class="view-label">Email Address</div><div class="view-value"><?php echo htmlspecialchars($emp['email'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Mobile Number</div><div class="view-value"><?php echo htmlspecialchars($emp['contact_no'] ?: '—'); ?></div></div>
                    </div>
                    <div class="view-grid c1">
                        <div><div class="view-label">Address</div><div class="view-value"><?php echo htmlspecialchars($emp['address'] ?: '—'); ?></div></div>
                    </div>

                    <div class="view-section-title">Bank Information</div>
                    <div class="view-grid c2">
                        <div><div class="view-label">Bank Name</div><div class="view-value"><?php echo htmlspecialchars($emp['bank_name'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Bank Account No</div><div class="view-value"><?php echo htmlspecialchars($emp['bank_account_no'] ?: '—'); ?></div></div>
                    </div>

                    <div class="view-section-title">Employment Details</div>
                    <div class="view-grid">
                        <div><div class="view-label">Employment Type</div><div class="view-value"><?php echo htmlspecialchars($emp['employment_type'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Joining Date</div><div class="view-value"><?php echo htmlspecialchars($emp['date_of_joining'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Designation</div><div class="view-value"><?php echo htmlspecialchars($emp['designation'] ?: '—'); ?></div></div>
                        <div><div class="view-label">Section</div><div class="view-value"><?php echo htmlspecialchars($emp['section'] ?: '—'); ?></div></div>
                    </div>

                    <button class="btn btn-primary btn-sm" onclick="showEditForm()">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                </div><!-- /view-mode -->

                <!-- EDIT MODE -->
                <div id="edit-mode" class="edit-wrap <?php echo ($update_msg==='error')?'open':''; ?>">
                    <form method="post">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-section-hdr">Personal Information</div>
                        <div class="form-row c3">
                            <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($emp['first_name']); ?>" required></div>
                            <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($emp['last_name']); ?>" required></div>
                            <div class="form-group"><label>Date of Birth (MM/DD/YYYY)</label><input type="text" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($emp['date_of_birth']); ?>" placeholder="MM/DD/YYYY"></div>
                        </div>
                        <div class="form-row c3">
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="male"   <?php echo $emp['gender']==='male'  ?'selected':''; ?>>Male</option>
                                    <option value="female" <?php echo $emp['gender']==='female'?'selected':''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Marital Status</label><input type="text" name="merital_status" class="form-control" value="<?php echo htmlspecialchars($emp['merital_status'] ?? ''); ?>" placeholder="e.g. Single"></div>
                            <div class="form-group"><label>Identity Number</label><input type="text" name="identity_no" class="form-control" value="<?php echo htmlspecialchars($emp['identity_no'] ?? ''); ?>"></div>
                        </div>

                        <div class="form-section-hdr mt-hdr">Contact Information</div>
                        <div class="form-row c2">
                            <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($emp['email']); ?>" required></div>
                            <div class="form-group"><label>Mobile Number</label><input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($emp['contact_no']); ?>" inputmode="numeric" maxlength="10" pattern="\d{10}" title="Enter exactly 10 digits" required></div>
                        </div>
                        <div class="form-row c1">
                            <div class="form-group"><label>Address</label><textarea name="address" class="form-control"><?php echo htmlspecialchars($emp['address']); ?></textarea></div>
                        </div>

                        <div class="form-section-hdr mt-hdr">Bank Information</div>
                        <div class="form-row c2">
                            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($emp['bank_name'] ?? ''); ?>"></div>
                            <div class="form-group"><label>Bank Account No</label><input type="text" name="bank_account_no" class="form-control" value="<?php echo htmlspecialchars($emp['bank_account_no'] ?? ''); ?>"></div>
                        </div>

                        <div class="form-section-hdr mt-hdr">Employment Details</div>
                        <div class="form-row c3">
                            <div class="form-group">
                                <label>Employment Type</label>
                                <select name="employment_type" class="form-control">
                                    <?php foreach(['Permanent position','Part-time employee','Intern','Holiday worker'] as $et): ?>
                                    <option value="<?php echo $et; ?>" <?php echo $emp['employment_type']===$et?'selected':''; ?>><?php echo $et; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Joining Date (MM/DD/YYYY)</label><input type="text" name="date_of_joining" class="form-control" value="<?php echo htmlspecialchars($emp['date_of_joining']); ?>" placeholder="MM/DD/YYYY"></div>
                            <div class="form-group"><label>Designation</label><input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($emp['designation']); ?>"></div>
                        </div>
                        <div class="form-row c2">
                            <div class="form-group"><label>Section</label><input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($emp['section']); ?>"></div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                            <button type="button" class="btn btn-ghost" onclick="hideEditForm()"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </form>
                </div><!-- /edit-mode -->

            </div><!-- /details-panel -->

            <!-- ════════════════════════════════════════════════════════════
                 PANEL 2 — CHANGE PASSWORD
            ════════════════════════════════════════════════════════════════ -->
            <div class="inner-panel" id="password-panel">
                <div class="pw-panel-body">

                    <!-- Inline Alert (server-side feedback) -->
                    <?php
                    $pw_alerts = [
                        'wrong_current' => ['error',   'fa-circle-xmark',  'Current password is incorrect. Please try again.'],
                        'too_short'     => ['error',   'fa-circle-xmark',  'New password must be at least 6 characters long.'],
                        'mismatch'      => ['error',   'fa-circle-xmark',  'New password and confirm password do not match.'],
                        'pw_error'      => ['error',   'fa-circle-xmark',  'Something went wrong. Password could not be updated.'],
                        'pw_success'    => ['success', 'fa-circle-check',  'Password changed successfully! Please use your new password next time.'],
                    ];
                    if ($pw_msg && isset($pw_alerts[$pw_msg])):
                        [$atype, $aicon, $amsg] = $pw_alerts[$pw_msg];
                    ?>
                    <div class="form-alert alert-<?php echo $atype; ?>">
                        <i class="fa-solid <?php echo $aicon; ?>"></i> <?php echo $amsg; ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" id="pw-form" novalidate>
                        <input type="hidden" name="action" value="reset_password">

                        <div class="form-section-hdr">Verify Identity</div>

                        <!-- Current password -->
                        <div class="form-row c1" style="margin-bottom:0;">
                            <div class="form-group">
                                <label>Current Password</label>
                                <div class="pw-field-wrap">
                                    <input type="password" name="current_password" id="inp-current"
                                        class="form-control <?php echo ($pw_msg==='wrong_current')?'is-error':''; ?>"
                                        placeholder="Enter your current password"
                                        autocomplete="current-password" required>
                                    <button type="button" class="pw-eye" onclick="toggleEye('inp-current',this)">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-divider"></div>

                        <div class="form-section-hdr">Set New Password</div>

                        <!-- New password -->
                        <div class="form-row c1" style="margin-bottom:18px;">
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="pw-field-wrap">
                                    <input type="password" name="new_password" id="inp-new"
                                        class="form-control <?php echo in_array($pw_msg,['too_short','mismatch'])?'is-error':''; ?>"
                                        placeholder="Minimum 6 characters"
                                        autocomplete="new-password" required
                                        oninput="evalStrength(this.value); evalMatch();">
                                    <button type="button" class="pw-eye" onclick="toggleEye('inp-new',this)">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <!-- Strength meter -->
                                <div class="strength-wrap">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="s-fill"></div>
                                    </div>
                                    <div class="strength-text" id="s-text">Enter a password to check strength</div>
                                </div>
                                <!-- Rules -->
                                <div class="pw-rules">
                                    <div class="pw-rule" id="rule-len">
                                        <i class="fa-solid fa-circle-dot"></i> At least 6 characters
                                    </div>
                                    <div class="pw-rule" id="rule-upper">
                                        <i class="fa-solid fa-circle-dot"></i> Contains an uppercase letter (A–Z)
                                    </div>
                                    <div class="pw-rule" id="rule-num">
                                        <i class="fa-solid fa-circle-dot"></i> Contains a number (0–9)
                                    </div>
                                    <div class="pw-rule" id="rule-special">
                                        <i class="fa-solid fa-circle-dot"></i> Contains a special character (!@#$…)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm password -->
                        <div class="form-row c1" style="margin-bottom:0;">
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="pw-field-wrap">
                                    <input type="password" name="confirm_password" id="inp-confirm"
                                        class="form-control <?php echo ($pw_msg==='mismatch')?'is-error':''; ?>"
                                        placeholder="Re-enter new password"
                                        autocomplete="new-password" required
                                        oninput="evalMatch()">
                                    <button type="button" class="pw-eye" onclick="toggleEye('inp-confirm',this)">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div class="match-msg" id="match-msg"></div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fa-solid fa-lock-open"></i> Update Password
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="resetPwForm()">
                                <i class="fa-solid fa-rotate-left"></i> Clear
                            </button>
                        </div>
                    </form>

                    <!-- Security tips -->
                    <div class="security-tips">
                        <div class="security-tips-title">
                            <i class="fa-solid fa-shield-halved"></i> Security Tips
                        </div>
                        <ul>
                            <li>Use a mix of uppercase, lowercase, numbers and symbols</li>
                            <li>Avoid using your name, employee code or birth date</li>
                            <li>Do not reuse passwords from other accounts</li>
                            <li>Change your password every 3–6 months</li>
                        </ul>
                    </div>

                </div>
            </div><!-- /password-panel -->

        </div><!-- /profile-card -->
    </div><!-- /page -->

    <footer>
        <strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; All rights reserved.
    </footer>
</div><!-- /main -->

<!-- ══ TOASTS ═════════════════════════════════════════════════════════════════ -->
<?php if ($update_msg === 'success'): ?>
<div class="toast toast-success" id="main-toast">
    <i class="fa-solid fa-circle-check"></i> Profile updated successfully!
</div>
<?php elseif ($update_msg === 'error'): ?>
<div class="toast toast-error" id="main-toast">
    <i class="fa-solid fa-circle-xmark"></i> Profile update failed. Please try again.
</div>
<?php elseif ($update_msg === 'invalid_contact'): ?>
<div class="toast toast-error" id="main-toast">
    <i class="fa-solid fa-circle-xmark"></i> Mobile number must contain exactly 10 digits.
</div>
<?php endif; ?>

<?php if ($pw_msg === 'pw_success'): ?>
<div class="toast toast-success" id="main-toast">
    <i class="fa-solid fa-circle-check"></i> Password changed successfully!
</div>
<?php endif; ?>

<script>
/* ── Tab switching ─────────────────────────────────────────── */
function gotoTab(tabId, panelId) {
    document.querySelectorAll('.inner-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.inner-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    document.getElementById(panelId).classList.add('active');
}

/* ── Profile edit toggle ───────────────────────────────────── */
function showEditForm() {
    document.getElementById('view-mode').style.display = 'none';
    document.getElementById('edit-mode').classList.add('open');
    document.getElementById('edit-mode').scrollIntoView({ behavior:'smooth', block:'start' });
}
function hideEditForm() {
    document.getElementById('edit-mode').classList.remove('open');
    document.getElementById('view-mode').style.display = '';
}

const contactInput = document.querySelector('input[name="contact_no"]');
if (contactInput) {
    contactInput.addEventListener('input', () => {
        contactInput.value = contactInput.value.replace(/\D/g, '').slice(0, 10);
    });
}

/* ── Password eye toggle ───────────────────────────────────── */
function toggleEye(inputId, btn) {
    const inp  = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}

/* ── Strength evaluator ────────────────────────────────────── */
function evalStrength(val) {
    const fill  = document.getElementById('s-fill');
    const text  = document.getElementById('s-text');

    const checks = {
        len:     val.length >= 6,
        upper:   /[A-Z]/.test(val),
        num:     /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val),
    };

    updateRule('rule-len',     checks.len,     val);
    updateRule('rule-upper',   checks.upper,   val);
    updateRule('rule-num',     checks.num,     val);
    updateRule('rule-special', checks.special, val);

    const score = Object.values(checks).filter(Boolean).length;

    if (!val) { fill.style.width='0'; text.textContent='Enter a password to check strength'; text.style.color=''; return; }

    const levels = [
        { pct:'0%',   color:'#e2e8f2', label:'' },
        { pct:'25%',  color:'#e53e3e', label:'Very Weak' },
        { pct:'50%',  color:'#dd6b20', label:'Weak' },
        { pct:'75%',  color:'#d4a017', label:'Moderate' },
        { pct:'100%', color:'#27ae60', label:'Strong' },
    ];
    const lv = levels[score] || levels[0];
    fill.style.width      = lv.pct;
    fill.style.background = lv.color;
    text.textContent      = lv.label;
    text.style.color      = lv.color;
}

function updateRule(id, pass, val) {
    const el   = document.getElementById(id);
    const icon = el.querySelector('i');
    el.classList.remove('pass','fail');
    if (val.length === 0) { icon.className='fa-solid fa-circle-dot'; return; }
    if (pass) {
        el.classList.add('pass');
        icon.className = 'fa-solid fa-circle-check';
    } else {
        el.classList.add('fail');
        icon.className = 'fa-solid fa-circle-xmark';
    }
}

/* ── Match checker ─────────────────────────────────────────── */
function evalMatch() {
    const nv  = document.getElementById('inp-new').value;
    const cv  = document.getElementById('inp-confirm').value;
    const msg = document.getElementById('match-msg');
    if (!cv) { msg.style.display='none'; return; }
    msg.style.display = 'block';
    if (nv === cv) {
        msg.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#27ae60;margin-right:5px;"></i><span style="color:#27ae60;font-size:16px;font-weight:500;">Passwords match</span>';
    } else {
        msg.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#e53e3e;margin-right:5px;"></i><span style="color:#e53e3e;font-size:16px;font-weight:500;">Passwords do not match</span>';
    }
}

/* ── Reset password form UI ────────────────────────────────── */
function resetPwForm() {
    document.getElementById('pw-form').reset();
    document.getElementById('s-fill').style.width = '0';
    document.getElementById('s-text').textContent = 'Enter a password to check strength';
    document.getElementById('s-text').style.color = '';
    document.getElementById('match-msg').style.display = 'none';
    document.querySelectorAll('.pw-rule').forEach(r => {
        r.classList.remove('pass','fail');
        r.querySelector('i').className = 'fa-solid fa-circle-dot';
    });
}

/* ── Auto-actions on page load ─────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

    // Profile update error → open edit form
    <?php if ($update_msg === 'error'): ?>
    showEditForm();
    <?php endif; ?>

    // Password response → switch to password tab
    <?php if ($pw_msg): ?>
    gotoTab('password-tab', 'password-panel');
    <?php endif; ?>

    // Auto-dismiss toast
    const toast = document.getElementById('main-toast');
    if (toast) {
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => { toast.style.opacity = '0'; }, 3700);
        setTimeout(() => toast.remove(), 4200);
    }
});
</script>
</body>
</html>
