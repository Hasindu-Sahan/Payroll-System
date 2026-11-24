<?php
// ── Shared Admin Layout ───────────────────────────────────────────────────────
// Requires: $admin (row), $pending_leaves (int), $active_page (string), $conn
// This file is included by multiple pages; guard against missing session/vars.
if (!isset($pending_leaves)) { $pending_leaves = 0; }


if (!function_exists('admin_initials')) {
    function admin_initials($name) {
        $parts = explode(' ', trim($name));
        $i = strtoupper(substr($parts[0],0,1));
        if (count($parts) > 1) $i .= strtoupper(substr(end($parts),0,1));
        return $i;
    }
}
if (!function_exists('initials')) {
    function initials($f, $l) { return strtoupper(substr($f,0,1).substr($l,0,1)); }
}

function nav_link($href, $icon, $label, $active_page, $badge = 0) {
    $active = ($active_page === $label) ? ' active' : '';
    $bdg    = $badge > 0 ? "<span class='nav-badge'>$badge</span>" : '';
    return "<a href='$href' class='nav-link$active'><i class='fa-solid $icon'></i> $label$bdg</a>";
}

$admin_role = $admin['admin_role'] ?? 'owner';

// Role display config
$role_label = match($admin_role) {
    'owner'           => 'Owner',
    'hr_manager'      => 'HR Manager',
    'payroll_manager' => 'Payroll Manager',
    default           => 'Administrator'
};
$role_color = match($admin_role) {
    'owner'           => '#f59e0b',
    'hr_manager'      => '#10b981',
    'payroll_manager' => '#6366f1',
    default           => '#818cf8'
};

// Fetch employee portal link for managers
$emp_portal_code = '';
if (($admin_role === 'hr_manager' || $admin_role === 'payroll_manager') && isset($conn)) {
    $lnk = $conn->prepare("SELECT emp_code FROM admin_employee_link WHERE admin_code=?");
    $lnk->bind_param("s", $admin['admin_code']);
    $lnk->execute();
    $lnk_row = $lnk->get_result()->fetch_assoc();
    $lnk->close();
    $emp_portal_code = $lnk_row['emp_code'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($page_title ?? 'Admin'); ?> — Tiptop Apparel Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════════════════
   SHARED ADMIN STYLES - FONT SIZE INCREASED BY 25%
═══════════════════════════════════════════════════════════ */
:root {
    --sidebar-bg:   #1e2533;
    --sidebar-dark: #161b27;
    --sidebar-w:    252px;
    --border-side:  rgba(255,255,255,0.07);
    --text-side:    #9aa3b5;
    --text-side-hi: #ffffff;
    --text-dim-s:   #4a5568;
    --accent:       #6366f1;
    --accent2:      #8b5cf6;
    --accent-light: #eef2ff;
    --right-bg:     #f4f6fb;
    --right-card:   #ffffff;
    --right-border: #e2e8f2;
    --right-text:   #1a2740;
    --right-muted:  #64748b;
    --right-dim:    #94a3b8;
    --right-input:  #f8fafd;
    --danger:       #e53e3e;
    --warn:         #dd6b20;
    --success:      #16a34a;
    --r:            10px;
    --r-sm:         7px;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
html, body { height:100%; }
body { font-family:'Outfit',sans-serif; display:flex; min-height:100vh; overflow-x:hidden; background:var(--right-bg); font-size:156.25%; }

/* SIDEBAR */
.sidebar { width:var(--sidebar-w); background:var(--sidebar-bg); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:200; border-right:1px solid var(--border-side); }
.sb-brand { height:66px; background:var(--sidebar-dark); display:flex; align-items:center; gap:11px; padding:0 20px; border-bottom:1px solid var(--border-side); flex-shrink:0; }
.sb-logo-img { width:36px; height:36px; border-radius:8px; object-fit:contain; background:#fff; padding:3px; flex-shrink:0; }
.sb-logo-fallback { width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; color:#fff; flex-shrink:0; }
.sb-company { font-weight:800; font-size:21.25px; color:#fff; letter-spacing:0.04em; }
.sb-company span { color:#818cf8; }
.sb-role-badge { margin:12px 16px 0; border-radius:6px; padding:6px 12px; display:flex; align-items:center; gap:8px; flex-shrink:0; }
.sb-role-badge i { font-size:13.75px; }
.sb-role-badge span { font-size:13.125px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; }
.sb-user { padding:14px 20px 14px; border-bottom:1px solid var(--border-side); display:flex; align-items:center; gap:12px; flex-shrink:0; margin-top:8px; }
.sb-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:17.5px; color:#fff; flex-shrink:0; position:relative; box-shadow:0 0 0 3px rgba(99,102,241,0.25); }
.sb-avatar .dot { position:absolute; bottom:2px; right:2px; width:9px; height:9px; border-radius:50%; background:#16a34a; border:2px solid var(--sidebar-bg); }
.sb-name { font-size:16.25px; font-weight:700; color:var(--text-side-hi); line-height:1.3; }
.sb-role { font-size:13.75px; color:#818cf8; margin-top:2px; font-weight:600; }
.sb-emp-link { padding:8px 14px 12px; border-bottom:1px solid var(--border-side); }
.sb-emp-link a { display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:var(--r-sm); background:rgba(99,102,241,0.1); color:#a5b4fc; font-size:15px; font-weight:600; text-decoration:none; transition:all .18s; border:1px solid rgba(99,102,241,0.2); }
.sb-emp-link a:hover { background:rgba(99,102,241,0.2); }
.sb-nav { flex:1; padding:14px 12px; overflow-y:auto; }
.nav-section { font-size:13.125px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-dim-s); padding:0 8px; margin:14px 0 6px; }
.nav-link { display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:var(--r-sm); color:var(--text-side); font-size:16.875px; font-weight:500; text-decoration:none; margin-bottom:3px; transition:all 0.18s; border:1px solid transparent; }
.nav-link:hover { background:rgba(255,255,255,0.05); color:var(--text-side-hi); }
.nav-link.active { background:rgba(99,102,241,0.18); color:#a5b4fc; border-color:rgba(99,102,241,0.3); }
.nav-link i { width:18px; text-align:center; font-size:17.5px; }
.nav-badge { margin-left:auto; background:#e53e3e; color:#fff; font-size:13.75px; font-weight:800; padding:1px 7px; border-radius:20px; line-height:1.6; }
.sb-footer { padding:14px; border-top:1px solid var(--border-side); flex-shrink:0; }
.sb-footer a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--r-sm); color:#fc8181; font-size:16.25px; font-weight:500; text-decoration:none; transition:background 0.18s; }
.sb-footer a:hover { background:rgba(229,57,53,0.1); }

/* MAIN */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
.topbar { height:66px; background:var(--right-card); border-bottom:1px solid var(--right-border); display:flex; align-items:center; justify-content:space-between; padding:0 30px; position:sticky; top:0; z-index:100; flex-shrink:0; }
.topbar-left h1 { font-size:22.5px; font-weight:700; color:var(--right-text); }
.topbar-left p  { font-size:15px; color:var(--right-muted); margin-top:1px; }
.topbar-actions { display:flex; gap:10px; align-items:center; }
.tb-icon { width:36px; height:36px; border-radius:var(--r-sm); background:var(--right-bg); border:1px solid var(--right-border); color:var(--right-muted); display:flex; align-items:center; justify-content:center; font-size:17.5px; cursor:pointer; transition:all 0.18s; text-decoration:none; position:relative; }
.tb-icon:hover { background:#eef2ff; color:var(--accent); border-color:#c7d2fe; }
.tb-badge { position:absolute; top:-4px; right:-4px; width:16px; height:16px; border-radius:50%; background:#e53e3e; color:#fff; font-size:13px; font-weight:800; display:flex; align-items:center; justify-content:center; border:2px solid var(--right-card); }
.tb-date { font-size:15px; color:var(--right-muted); font-weight:500; }

/* PAGE */
.page { flex:1; padding:28px 30px; }
.card { background:var(--right-card); border-radius:var(--r); margin-bottom:24px; border:1px solid var(--right-border); }
.card-header { padding:18px 24px; border-bottom:1px solid var(--right-border); display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:17.5px; font-weight:700; color:var(--right-text); display:flex; align-items:center; gap:9px; }
.card-title i { color:var(--accent); font-size:16.25px; }
.card-body { padding:22px 24px; }
.card-footer { padding:16px 24px; border-top:1px solid var(--right-border); display:flex; justify-content:flex-end; gap:10px; }

/* STATS */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--right-card); border:1px solid var(--right-border); border-radius:var(--r); padding:20px; display:flex; flex-direction:column; gap:10px; }
.stat-top { display:flex; align-items:center; justify-content:space-between; }
.stat-icon { width:44px; height:44px; border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; font-size:18.75px; }
.stat-val { font-size:31.25px; font-weight:800; color:var(--right-text); }
.stat-label { font-size:15px; color:var(--right-muted); }
.stat-badge { padding:4px 10px; border-radius:20px; font-size:13.75px; font-weight:700; }

/* TABLE */
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { padding:13px 16px; text-align:left; font-size:13.75px; font-weight:700; color:var(--right-muted); text-transform:uppercase; letter-spacing:0.04em; border-bottom:2px solid var(--right-border); background:var(--right-bg); white-space:nowrap; }
.data-table tbody td { padding:14px 16px; font-size:15px; color:var(--right-text); border-bottom:1px solid var(--right-border); }
.data-table tbody tr:hover { background:#f8fafd; }
.table-empty { padding:50px; text-align:center; color:var(--right-dim); }
.table-empty i { font-size:50px; color:var(--right-border); margin-bottom:12px; display:block; }
.table-empty p { font-size:16.25px; }

/* FORM */
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-size:15px; font-weight:600; color:var(--right-text); margin-bottom:7px; }
.form-control, select.form-control, textarea.form-control { width:100%; padding:11px 14px; border:1px solid var(--right-border); border-radius:var(--r-sm); font-size:15px; color:var(--right-text); background:var(--right-input); font-family:inherit; transition:all 0.15s; }
.form-control:focus, select.form-control:focus, textarea.form-control:focus { outline:none; border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,0.1); }
textarea.form-control { min-height:100px; resize:vertical; }
input[type="time"].form-control { font-family:'JetBrains Mono',monospace; }

/* BUTTON */
.btn { padding:10px 18px; border-radius:var(--r-sm); font-size:15px; font-weight:600; border:none; cursor:pointer; transition:all 0.18s; text-decoration:none; display:inline-flex; align-items:center; gap:7px; font-family:inherit; }
.btn-primary { background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; }
.btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(99,102,241,0.3); }
.btn-ghost { background:transparent; color:var(--right-muted); border:1px solid var(--right-border); }
.btn-ghost:hover { background:var(--right-bg); color:var(--right-text); }
.btn-danger { background:#e53e3e; color:#fff; }
.btn-danger:hover { background:#c53030; }
.btn-success { background:#16a34a; color:#fff; }
.btn-success:hover { background:#15803d; }
.btn-sm { padding:5px 10px; font-size:13.75px; }

/* BADGE */
.badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; font-size:13.125px; font-weight:700; }
.badge-pending { background:#fef3c7; color:#92400e; }
.badge-approved { background:#d1fae5; color:#065f46; }
.badge-rejected { background:#fee2e2; color:#991b1b; }
.badge-active { background:#dbeafe; color:#1e40af; }
.badge-inactive { background:#e5e7eb; color:#374151; }
.badge-present { background:#d1fae5; color:#065f46; }
.badge-absent { background:#fee2e2; color:#991b1b; }

/* EMPLOYEE CELL */
.emp-cell { display:flex; align-items:center; gap:11px; }
.emp-ava { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13.75px; color:#fff; flex-shrink:0; }
.emp-name { font-weight:600; font-size:15px; color:var(--right-text); }
.emp-desig { font-size:13.125px; color:var(--right-dim); margin-top:2px; }

/* PAGE HEADER */
.page-header { margin-bottom:22px; display:flex; align-items:flex-start; justify-content:space-between; }
.page-header h2 { font-size:25px; font-weight:800; color:var(--right-text); }
.page-header p  { font-size:16.25px; color:var(--right-muted); margin-top:3px; }
.breadcrumb { font-size:15px; color:var(--right-dim); display:flex; align-items:center; gap:6px; margin-bottom:4px; }
.breadcrumb a { color:var(--accent); text-decoration:none; }
.breadcrumb a:hover { text-decoration:underline; }

/* WELCOME BANNER */
.welcome-banner { background:linear-gradient(120deg,#161b27 0%,#1e2533 50%,#252d42 100%); border-radius:var(--r); padding:26px 28px; display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; position:relative; overflow:hidden; }
.welcome-banner::before { content:''; position:absolute; top:-50px; right:-30px; width:200px; height:200px; border-radius:50%; background:rgba(99,102,241,0.12); pointer-events:none; }
.welcome-banner::after  { content:''; position:absolute; bottom:-40px; right:120px; width:130px; height:130px; border-radius:50%; background:rgba(139,92,246,0.07); pointer-events:none; }
.wb-left { position:relative; z-index:1; }
.wb-greeting { font-size:16.25px; color:rgba(255,255,255,0.5); margin-bottom:4px; }
.wb-name { font-size:30px; font-weight:800; color:#fff; }
.wb-role { font-size:16.25px; color:rgba(165,180,252,0.85); margin-top:5px; }
.wb-right { position:relative; z-index:1; text-align:right; }
.wb-date-label { font-size:13.75px; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:0.07em; }
.wb-date-val { font-size:25px; font-weight:700; color:#fff; margin-top:3px; }
.wb-time { font-size:16.25px; color:rgba(255,255,255,0.55); margin-top:2px; font-family:'JetBrains Mono',monospace; }

/* FOOTER */
footer { background:var(--right-card); border-top:1px solid var(--right-border); padding:14px 30px; font-size:15px; color:var(--right-dim); text-align:center; }
footer strong { color:var(--right-muted); }

/* MODAL */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:500; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border-radius:var(--r); width:540px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2); animation:fadeUp 0.25s ease; }
.modal-header { padding:20px 24px; border-bottom:1px solid var(--right-border); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:20px; font-weight:700; color:var(--right-text); }
.modal-close { width:32px; height:32px; border-radius:var(--r-sm); background:var(--right-bg); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--right-muted); font-size:17.5px; transition:background 0.15s; }
.modal-close:hover { background:#fee2e2; color:#b91c1c; }
.modal-body { padding:22px 24px; }
.modal-footer { padding:16px 24px; border-top:1px solid var(--right-border); display:flex; gap:10px; justify-content:flex-end; }

/* READ-ONLY BANNER */
.readonly-banner { background:#fffbeb; border:1px solid #fed7aa; border-radius:var(--r-sm); padding:10px 16px; margin-bottom:16px; font-size:15.625px; color:#92400e; display:flex; align-items:center; gap:8px; }

/* ANIMATIONS */
@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.page-anim { animation:fadeUp 0.3s ease both; }

/* ACTION PILL */
.action-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:14.375px; font-weight:700; }
.pill-in  { background:#f0fdf4; color:#16a34a; }
.pill-out { background:#fff5f5; color:#b91c1c; }

/* INLINE EDIT */
.inline-edit-cell { position:relative; }
.inline-display { cursor:pointer; padding:4px 8px; border-radius:4px; transition:background 0.15s; }
.inline-display:hover { background:var(--right-bg); }
.inline-input { width:100%; padding:6px 10px; border:1px solid var(--accent); border-radius:var(--r-sm); font-size:15px; font-family:'JetBrains Mono',monospace; }

@media(max-width:1400px){ .stats-row{grid-template-columns:repeat(3,1fr);} }
@media(max-width:1100px){ .stats-row{grid-template-columns:repeat(2,1fr);} }
@media(max-width:768px) { .sidebar{transform:translateX(-100%);} .main{margin-left:0;} }

/* ── STAT CARD COMPONENTS ─────────────────────────────── */
.stat-icon-wrap { width:48px; height:48px; border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-body { display:flex; flex-direction:column; gap:4px; }
.stat-lbl { font-size:15px; color:var(--right-muted); font-weight:500; }
.si-indigo { background:#eef2ff; color:#6366f1; }
.si-orange { background:#fff7ed; color:#ea580c; }
.si-green  { background:#f0fdf4; color:#16a34a; }
.si-violet { background:#f5f3ff; color:#7c3aed; }
.si-red    { background:#fff5f5; color:#e53e3e; }
.si-blue   { background:#eff6ff; color:#2563eb; }

/* ── DASHBOARD COMPONENTS ─────────────────────────────── */
.see-all { font-size:15px; color:var(--accent); text-decoration:none; font-weight:600; }
.see-all:hover { text-decoration:underline; }
.status-pill { display:inline-flex; align-items:center; padding:4px 11px; border-radius:20px; font-size:13.125px; font-weight:700; }
.sp-approve { background:#d1fae5; color:#065f46; }
.sp-reject  { background:#fee2e2; color:#991b1b; }
.sp-pending { background:#fef3c7; color:#92400e; }

/* ── TOAST ────────────────────────────────────────────── */
.toast { position:fixed; top:20px; right:24px; z-index:9999; padding:14px 22px; border-radius:var(--r-sm); font-size:15px; font-weight:600; display:flex; align-items:center; gap:10px; box-shadow:0 8px 28px rgba(0,0,0,0.15); transition:opacity 0.4s; }
.toast-success { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.toast-error   { background:#fff5f5; color:#b91c1c; border:1px solid #fecaca; }

/* ── BADGE VARIANTS ───────────────────────────────────── */
.badge-perm   { background:#dbeafe; color:#1e40af; }
.badge-part   { background:#fef3c7; color:#92400e; }
.badge-intern { background:#f0fdf4; color:#166534; }

/* ── PAY HEAD PILLS ───────────────────────────────────── */
.pill-earn   { background:#d1fae5; color:#065f46; padding:4px 12px; border-radius:20px; font-size:13.125px; font-weight:700; display:inline-block; }
.pill-deduct { background:#fee2e2; color:#991b1b; padding:4px 12px; border-radius:20px; font-size:13.125px; font-weight:700; display:inline-block; }

/* ── BUTTON ICON ──────────────────────────────────────── */
.btn-icon { width:36px; height:36px; padding:0; display:inline-flex; align-items:center; justify-content:center; }
</style>
</head>
<body>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sb-brand">
        <?php if(file_exists('Logo.jpeg')): ?>
            <img src="Logo.jpeg" alt="Logo" class="sb-logo-img">
        <?php else: ?>
            <div class="sb-logo-fallback">TA</div>
        <?php endif; ?>
        <div class="sb-company">Tiptop <span>Apparel</span></div>
    </div>

    <!-- Role badge -->
    <div class="sb-role-badge"
         style="background:<?php echo $role_color;?>1a;border:1px solid <?php echo $role_color;?>44;">
        <i class="fa-solid fa-shield-halved" style="color:<?php echo $role_color;?>;"></i>
        <span style="color:<?php echo $role_color;?>;"><?php echo $role_label; ?></span>
    </div>

    <div class="sb-user">
            <div class="sb-avatar">
            	<?php echo admin_initials($admin['admin_name'] ?? 'Admin'); ?>
            	<div class="dot"></div>
            </div>

        <div>
            <div class="sb-name"><?php echo htmlspecialchars($admin['admin_name'] ?? 'Admin'); ?></div>
            <div class="sb-role"><?php echo htmlspecialchars($admin['admin_code'] ?? ''); ?></div>

        </div>
    </div>

    <!-- Employee portal shortcut for managers -->
    <?php if ($emp_portal_code): ?>
    <div class="sb-emp-link">
        <a href="e_dashboard.php">
            <i class="fa-solid fa-user-tie"></i>
            Employee Portal &nbsp;<span style="font-family:'JetBrains Mono',monospace;font-size:13px;opacity:.7;">(<?php echo $emp_portal_code; ?>)</span>
        </a>
    </div>
    <?php endif; ?>

    <nav class="sb-nav">
        <div class="nav-section">Main</div>
        <?php echo nav_link('a_dashboard.php','fa-gauge-high','Dashboard',$active_page); ?>
        <?php echo nav_link('a_profile.php','fa-circle-user','Profile',$active_page); ?>

        <?php if ($admin_role === 'owner' || $admin_role === 'hr_manager'): ?>
        <div class="nav-section">HR Management</div>
<?php /* Attendance Mark tab */ if($admin_role==='hr_manager'){ echo nav_link('a_attendanceMark.php','fa-calendar-check','Attendance Mark',$active_page); } ?>


        <?php echo nav_link('a_employeeMng.php','fa-users','Employee Management',$active_page); ?>
        <?php echo nav_link('a_resignedEmployees.php','fa-user-clock','Resigned Employees',$active_page); ?>

        <?php echo nav_link('a_leavesMng.php','fa-plane-departure','Leaves Management',$active_page, $pending_leaves ?? 0); ?>

        <?php echo nav_link('a_holidaysMng.php','fa-calendar-days','Holiday Management',$active_page); ?>
        <?php echo nav_link('a_attendanceMng.php','fa-clock','Attendance Details',$active_page); ?>
        <?php if($admin_role==='hr_manager'){ echo nav_link('a_hrReports.php','fa-chart-column','Reports',$active_page); } ?>
        <?php endif; ?>

        <?php if ($admin_role === 'owner'): ?>
        <div class="nav-section">Payroll</div>
        <?php echo nav_link('a_payheadsMng.php','fa-list-check','Pay Head Management',$active_page); ?>
        <?php echo nav_link('a_payrollMng.php','fa-money-bill-wave','Payroll Management',$active_page); ?>
        <?php echo nav_link('a_reports.php','fa-chart-bar','Reports',$active_page); ?>
        <?php echo nav_link('a_viewReports.php','fa-folder-open','View Reports',$active_page); ?>
        <?php endif; ?>

        <?php if ($admin_role === 'payroll_manager'): ?>
        <div class="nav-section">Payroll</div>
        <?php echo nav_link('a_attendanceMng.php','fa-clock','Attendance Details',$active_page); ?>
        <?php echo nav_link('a_payheadsMng.php','fa-list-check','Pay Head Management',$active_page); ?>
        <?php echo nav_link('a_payrollMng.php','fa-money-bill-wave','Payroll Management',$active_page); ?>
        <?php echo nav_link('a_reports.php','fa-chart-bar','Reports',$active_page); ?>
        <?php endif; ?>
    </nav>

    <div class="sb-footer">
        <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</a>
    </div>
</aside>

<!-- ══ MAIN ═══════════════════════════════════════════════════════════════════ -->
<div class="main">
