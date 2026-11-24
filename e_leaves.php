<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='employee'){header('Location: login.php');exit;}

date_default_timezone_set('Asia/Colombo');
$today = date('Y-m-d');
require_once __DIR__ . '/db.php';
$conn = db();


$emp_code = $_SESSION['emp_code'];
$stmt = $conn->prepare("SELECT * FROM employees WHERE emp_code = ?");
$stmt->bind_param("s", $emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$emp) die('<div style="color:red;padding:20px;">Employee not found.</div>');

$ll = $conn->query("SELECT annual_allowed_leaves, casual_allowed_leaves, deduction_per_day, manager_annual_leaves, manager_deduction_per_day FROM leavelimits LIMIT 1")->fetch_assoc();
$is_manager_emp = (int)($emp['is_manager'] ?? 0);

if($is_manager_emp){
    // Manager quota left unchanged unless you want HR/manager to also use Total 21.
    $annual_allowed    = (int)($ll['manager_annual_leaves'] ?? 24);
    $deduction_per_day = (float)($ll['manager_deduction_per_day'] ?? 3000);
    $casual_allowed    = 7;
} else {
    // Employee quota: Total 21 (Annual 14 + Casual 7)
    $annual_allowed    = 14;
    $casual_allowed    = (int)($ll['casual_allowed_leaves'] ?? 7);
    $deduction_per_day = (float)($ll['deduction_per_day'] ?? 2000);
}

$base_monthly    = (int)floor($annual_allowed / 12);
$remainder       = $annual_allowed % 12;
$cur_mon         = (int)date('n');
$monthly_allowed = $base_monthly + ($cur_mon <= $remainder && $remainder > 0 ? 1 : 0);

$year = date('Y');

$al = $conn->prepare("SELECT SUM(no_of_days) AS total_days FROM leaves WHERE emp_code=? AND leave_status='approved' AND YEAR(applied_date)=?");
$al->bind_param("si", $emp_code, $year);
$al->execute();
$approved_days = (int)($al->get_result()->fetch_assoc()['total_days'] ?? 0);
$al->close();

$typeCounts = [
    'casual' => 0,
    'annual' => 0,
];
$typeStmt = $conn->prepare("SELECT leave_type, SUM(no_of_days) AS total_days FROM leaves WHERE emp_code=? AND leave_status='approved' AND YEAR(applied_date)=? GROUP BY leave_type");
$typeStmt->bind_param("si", $emp_code, $year);
$typeStmt->execute();
$typeRes = $typeStmt->get_result();
while ($row = $typeRes->fetch_assoc()) {
    $lt = strtolower(trim((string)($row['leave_type'] ?? '')));
    if ($lt === 'sick' || $lt === 'medical') {
        $lt = 'casual';
    }
    $days = (int)($row['total_days'] ?? 0);
    if (isset($typeCounts[$lt])) {
        $typeCounts[$lt] += $days;
    }
}
$typeStmt->close();

$casual_taken = $typeCounts['casual'];
$annual_taken  = $typeCounts['annual'];
$casual_remaining = max(0, $casual_allowed - $casual_taken);
$annual_remaining = max(0, $annual_allowed - $annual_taken);

$apply_msg = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave'){
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date']   ?? '');
    $no_of_days = (int)($_POST['no_of_days'] ?? 1);
    $reason     = trim($_POST['leave_message'] ?? '');

    if(!$leave_type || !$start_date || !$end_date || !$reason){
        $apply_msg = 'empty_fields';
    } else {
        $today = date('Y-m-d');
        $start_date_obj = DateTime::createFromFormat('!Y-m-d', $start_date);
        $end_date_obj = DateTime::createFromFormat('!Y-m-d', $end_date);
        $valid_dates = $start_date_obj && $end_date_obj
            && $start_date_obj->format('Y-m-d') === $start_date
            && $end_date_obj->format('Y-m-d') === $end_date;

        if(!$valid_dates || $start_date < $today || $end_date < $today){
            $apply_msg = 'invalid_date_past';
        } elseif($end_date < $start_date){
            $apply_msg = 'invalid_date_range';
        } else {
            $ins = $conn->prepare("INSERT INTO leaves (emp_code,leave_type,start_date,end_date,no_of_days,reason,leave_status,applied_date) VALUES (?,?,?,?,?,?,'pending',NOW())");
            $ins->bind_param("ssssis", $emp_code, $leave_type, $start_date, $end_date, $no_of_days, $reason);
            $apply_msg = $ins->execute() ? 'success' : 'error';
            $ins->close();
        }
    }
}


$lv = $conn->prepare("SELECT * FROM leaves WHERE emp_code=? ORDER BY applied_date DESC");
$lv->bind_param("s", $emp_code);
$lv->execute();
$leaves = $lv->get_result()->fetch_all(MYSQLI_ASSOC);
$lv->close();

$total_leaves   = count($leaves);
$approved_count = count(array_filter($leaves, fn($l) => $l['leave_status'] === 'approved'));
$pending_count  = count(array_filter($leaves, fn($l) => $l['leave_status'] === 'pending'));
$rejected_count = count(array_filter($leaves, fn($l) => $l['leave_status'] === 'rejected'));

function initials($f,$l){ return strtoupper(substr($f,0,1).substr($l,0,1)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Leaves — Tiptop Apparel Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --sidebar-bg:#0b1e3d;--sidebar-dark:#071529;--sidebar-w:248px;
    --accent:#2f80ed;--accent2:#27ae60;
    --border-side:rgba(255,255,255,0.08);
    --text-side:#a8c0db;--text-side-hi:#ffffff;--text-dim-s:#4e6e8e;
    --right-bg:#f5f7fb;--right-card:#ffffff;--right-border:#e2e8f2;
    --right-text:#1a2740;--right-muted:#64748b;--right-dim:#94a3b8;
    --danger:#e53e3e;--warn:#dd6b20;--success:#27ae60;--r:10px;--r-sm:7px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:'Outfit',sans-serif;display:flex;min-height:100vh;overflow-x:hidden;background:var(--right-bg);font-size:156.25%;}

/* ── SIDEBAR ─────────────────────────────────────────────────── */
.sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;border-right:1px solid var(--border-side);}
.sb-brand{height:66px;background:var(--sidebar-dark);display:flex;align-items:center;gap:11px;padding:0 20px;border-bottom:1px solid var(--border-side);flex-shrink:0;}
.sb-logo-fallback{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#2f80ed,#27ae60);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0;}
.sb-company{font-weight:800;font-size:21.25px;color:#fff;letter-spacing:0.03em;}
.sb-company span{color:#2f80ed;}
.sb-user{padding:20px;border-bottom:1px solid var(--border-side);display:flex;align-items:center;gap:12px;flex-shrink:0;}
.sb-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#2f80ed,#27ae60);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18.75px;color:#fff;flex-shrink:0;position:relative;box-shadow:0 0 0 3px rgba(47,128,237,0.25);}
.sb-avatar .dot{position:absolute;bottom:2px;right:2px;width:9px;height:9px;border-radius:50%;background:#27ae60;border:2px solid var(--sidebar-bg);}
.sb-name{font-size:16.25px;font-weight:700;color:var(--text-side-hi);line-height:1.3;}
.sb-dept{font-size:13.75px;color:var(--text-dim-s);margin-top:2px;}
.sb-nav{flex:1;padding:16px 12px;overflow-y:auto;}
.nav-section{font-size:12.5px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-dim-s);padding:0 8px;margin:14px 0 6px;}
.nav-link{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:var(--r-sm);color:var(--text-side);font-size:16.875px;font-weight:500;text-decoration:none;margin-bottom:3px;transition:all 0.18s;border:1px solid transparent;}
.nav-link:hover{background:rgba(255,255,255,0.06);color:var(--text-side-hi);}
.nav-link.active{background:rgba(47,128,237,0.18);color:#6db3f7;border-color:rgba(47,128,237,0.28);}
.nav-link i{width:18px;text-align:center;font-size:17.5px;}
.sb-footer{padding:14px;border-top:1px solid var(--border-side);flex-shrink:0;}
.sb-footer a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--r-sm);color:#e57373;font-size:16.25px;font-weight:500;text-decoration:none;transition:background 0.18s;}
.sb-footer a:hover{background:rgba(229,57,53,0.1);}

/* ── MAIN ────────────────────────────────────────────────────── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:66px;background:var(--right-card);border-bottom:1px solid var(--right-border);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:100;flex-shrink:0;}
.topbar-left h1{font-size:22.5px;font-weight:700;color:var(--right-text);}
.topbar-left p{font-size:15px;color:var(--right-muted);margin-top:1px;}
.topbar-actions{display:flex;gap:10px;align-items:center;}
.tb-icon{width:36px;height:36px;border-radius:var(--r-sm);background:var(--right-bg);border:1px solid var(--right-border);color:var(--right-muted);display:flex;align-items:center;justify-content:center;font-size:17.5px;cursor:pointer;transition:all 0.18s;text-decoration:none;}
.tb-icon:hover{background:#e8f0fe;color:var(--accent);border-color:#c7d9fc;}
.page{padding:28px 30px;flex:1;}
footer{background:var(--right-card);border-top:1px solid var(--right-border);padding:14px 30px;font-size:15px;color:var(--right-dim);text-align:center;}
footer strong{color:var(--right-muted);}

/* ── PAGE CONTENT ────────────────────────────────────────────── */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:16px;color:#94a3b8;margin-bottom:8px;}
.breadcrumb a{color:#2f80ed;text-decoration:none;font-weight:600;}
.breadcrumb a:hover{text-decoration:underline;}
.page-header h2{font-size:28px;font-weight:800;color:#1a2740;margin:0;}
.content-grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:18px 20px;display:flex;align-items:center;gap:16px;}
.stat-icon-wrap{width:50px;height:50px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:25px;flex-shrink:0;}
.si-indigo{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.si-green{background:linear-gradient(135deg,#27ae60,#56CCF2);color:#fff;}
.si-orange{background:linear-gradient(135deg,#F2994A,#F2C94C);color:#fff;}
.si-red{background:linear-gradient(135deg,#e53e3e,#FC466B);color:#fff;}
.stat-body{flex:1;}
.stat-val{font-size:33px;font-weight:800;color:#1a2740;line-height:1;margin-bottom:6px;}
.stat-lbl{font-size:15px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
.card{background:#fff;border:1px solid #e1e8f0;border-radius:10px;overflow:hidden;}
.card-header{padding:16px 20px;border-bottom:1px solid #e1e8f0;display:flex;align-items:center;justify-content:space-between;}
.card-title{font-size:18px;font-weight:700;color:#1a2740;display:flex;align-items:center;gap:8px;}
.card-title i{color:#2f80ed;}
.card-body{padding:20px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:16px;font-weight:600;color:#1a2740;margin-bottom:7px;}
.form-control,textarea.form-control{width:100%;padding:10px 13px;font-size:16px;font-family:inherit;color:#1a2740;background:#f0f4f8;border:1px solid #e1e8f0;border-radius:7px;outline:none;transition:all 0.2s;}
.form-control:focus,textarea.form-control:focus{border-color:#2f80ed;box-shadow:0 0 0 3px rgba(47,128,237,0.12);}
textarea.form-control{resize:vertical;min-height:80px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:16px;font-weight:600;font-family:inherit;color:#fff;background:#2f80ed;border:1px solid #2f80ed;border-radius:7px;cursor:pointer;text-decoration:none;transition:all 0.2s;outline:none;}
.btn:hover{background:#2667d4;border-color:#2667d4;transform:translateY(-1px);box-shadow:0 4px 12px rgba(47,128,237,0.3);}
.leaves-table{width:100%;border-collapse:collapse;}
.leaves-table thead{background:#f8fafd;}
.leaves-table thead th{padding:11px 14px;text-align:left;font-size:15px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;border-bottom:2px solid #e1e8f0;}
.leaves-table tbody tr{border-bottom:1px solid #e1e8f0;transition:background 0.15s;}
.leaves-table tbody tr:hover{background:rgba(47,128,237,0.03);}
.leaves-table tbody td{padding:12px 14px;font-size:16px;color:#1a2740;}
.status-pill{display:inline-block;padding:4px 12px;border-radius:20px;font-size:15px;font-weight:600;text-transform:uppercase;}
.sp-pending{background:#FEF3C7;color:#92400E;}
.sp-approved{background:#D1FAE5;color:#065F46;}
.sp-rejected{background:#FED7D7;color:#9B2C2C;}
.table-empty{padding:60px 20px;text-align:center;color:#94a3b8;}
.table-empty i{font-size:48px;margin-bottom:16px;opacity:0.3;}
.table-empty p{font-size:16px;margin:0;}
.table-footer{padding:14px 20px;border-top:1px solid #e1e8f0;display:flex;align-items:center;justify-content:space-between;font-size:16px;color:#64748b;}
.pagination{display:flex;gap:6px;}
.pg-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid #e1e8f0;border-radius:7px;font-size:16px;font-weight:600;color:#1a2740;cursor:pointer;transition:all 0.2s;}
.pg-btn:hover:not(.disabled){background:#2f80ed;color:#fff;border-color:#2f80ed;}
.pg-btn.active{background:#2f80ed;color:#fff;border-color:#2f80ed;}
.pg-btn.disabled{opacity:0.4;cursor:not-allowed;}
.toast{position:fixed;top:20px;right:20px;z-index:9999;background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:14px 18px;box-shadow:0 10px 25px rgba(0,0,0,0.1);display:flex;align-items:center;gap:12px;font-size:16px;font-weight:500;}
.toast-success{border-left:4px solid #16a34a;color:#16a34a;}
.toast-error{border-left:4px solid #e53e3e;color:#e53e3e;}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.content-grid{grid-template-columns:1fr;}}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}}
</style>
</head>
<body>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sb-brand">
        <?php if(file_exists('Logo.jpeg')):?>
        <img src="Logo.jpeg" alt="Tiptop Apparel" style="width:36px;height:36px;border-radius:8px;object-fit:contain;background:#fff;padding:3px;">
        <?php else:?>
        <div class="sb-logo-fallback">TA</div>
        <?php endif;?>
        <div class="sb-company">Tiptop <span>Apparel</span></div>
    </div>
    <div class="sb-user">
        <div class="sb-avatar">
            <?php echo initials($emp['first_name'],$emp['last_name']);?>
            <div class="dot"></div>
        </div>
        <div>
            <div class="sb-name"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']);?></div>
            <div class="sb-dept"><?php echo htmlspecialchars($emp['section']);?></div>
        </div>
    </div>
    <nav class="sb-nav">
        <div class="nav-section">Navigation</div>
        <a href="e_dashboard.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="e_leaves.php"    class="nav-link active"><i class="fa-solid fa-plane-departure"></i> My Leaves</a>
        <a href="e_holidays.php"  class="nav-link"><i class="fa-solid fa-calendar-days"></i> Holidays</a>
        <a href="e_payments.php"  class="nav-link"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
        <a href="e_profile.php"   class="nav-link"><i class="fa-solid fa-circle-user"></i> Profile</a>
    </nav>
    <div class="sb-footer">
        <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</a>
    </div>
</aside>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <h1>My Leaves</h1>
            <p>Request and track your leave applications</p>
        </div>
        <div class="topbar-actions">
            <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
        </div>
    </header>

    <div class="page">

        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="e_dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right" style="font-size:13px;"></i>
                    Leaves
                </div>
                <h2>My Leaves</h2>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon-wrap si-indigo"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="stat-body">
                    <div class="stat-val"><?php echo ($annual_allowed + $casual_allowed); ?></div>
                    <div class="stat-lbl">Total Leave Quota (Annual+Casual)</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-wrap si-green"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:#16a34a;"><?php echo $approved_count; ?></div>
                    <div class="stat-lbl">Approved Leaves</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:#dd6b20;"><?php echo $pending_count; ?></div>
                    <div class="stat-lbl">Pending Requests</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap si-red"><i class="fa-solid fa-calendar-xmark"></i></div>
                <div class="stat-body">
                    <div class="stat-val" style="color:#e53e3e;"><?php echo ($annual_remaining + $casual_remaining); ?></div>
                    <div class="stat-lbl">Remaining Leaves (Annual+Casual)</div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Apply Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-plus-circle"></i> Request New Leave</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="leave-form">
                        <input type="hidden" name="action" value="apply_leave">

                        <div class="form-group">
                            <label>Leave Type</label>
                            <select name="leave_type" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <option value="casual">Casual Leave</option>
                                <option value="sick">Sick Leave</option>
                                <option value="annual">Annual Leave</option>
                                <option value="no_pay">No Pay Leave</option>
                            </select>
                        </div>
                        <div style="background:#f8fafd;border:1px solid #e1e8f0;border-radius:7px;padding:12px 14px;margin-bottom:16px;font-size:15px;color:#64748b;">
                            <strong style="color:#1a2740;">Current quota:</strong>
                            Total <strong><?php echo ($annual_allowed + $casual_allowed); ?></strong> (Annual <strong><?php echo $annual_allowed; ?></strong> + Casual <strong><?php echo $casual_allowed; ?></strong>)<br>
                            Casual + Sick <strong><?php echo $casual_taken; ?>/<?php echo $casual_allowed; ?></strong>,
                            Annual <strong><?php echo $annual_taken; ?>/<?php echo $annual_allowed; ?></strong>
                        </div>

<div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" required min="<?php echo $today;?>">
                        </div>

                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" required min="<?php echo $today;?>">
                        </div>


                        <div class="form-group">
                            <label>Number of Days</label>
                            <input type="number" name="no_of_days" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="leave_message" class="form-control" placeholder="Explain the reason for your leave..." required></textarea>
                        </div>


                        <button type="submit" class="btn" style="width:100%;justify-content:center;">
                            <i class="fa-solid fa-paper-plane"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- History Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Leave History</div>
                    <div style="font-size:16px;color:#64748b;"><?php echo $total_leaves; ?> records</div>
                </div>

                <?php if(empty($leaves)): ?>
                    <div class="table-empty">
                        <i class="fa-solid fa-inbox"></i>
                        <p>No leave requests yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="leaves-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Applied</th>
                                </tr>
                            </thead>
                            <tbody id="leaves-tbody">
                                <?php foreach($leaves as $lv):
                                    $sc = match($lv['leave_status']){
                                        'approved' => 'sp-approved',
                                        'rejected' => 'sp-rejected',
                                        default    => 'sp-pending'
                                    };
                                    $sl = match($lv['leave_status']){
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        default    => 'Pending'
                                    };
                                ?>
                                <tr>
                                    <td style="text-transform:capitalize;"><?php echo htmlspecialchars($lv['leave_type']); ?></td>
                                    <td style="font-size:16px;color:#64748b;">
                                        <?php echo date('d M', strtotime($lv['start_date'])); ?> - <?php echo date('d M Y', strtotime($lv['end_date'])); ?>
                                    </td>
                                    <td><?php echo $lv['no_of_days']; ?></td>
                                    <td><span class="status-pill <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
                                    <td style="font-size:16px;color:#64748b;"><?php echo date('d M Y', strtotime($lv['applied_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div id="table-info">Showing <span id="showing-from">1</span>–<span id="showing-to"><?php echo min(10,count($leaves)); ?></span> of <?php echo count($leaves); ?> entries</div>
                        <div class="pagination" id="pagination"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /page -->

    <?php if($apply_msg === 'success'): ?>
    <div class="toast toast-success" id="main-toast">
        <i class="fa-solid fa-circle-check"></i> Leave request submitted successfully!
    </div>
    <?php elseif($apply_msg === 'error'): ?>
    <div class="toast toast-error" id="main-toast">
        <i class="fa-solid fa-circle-xmark"></i> Failed to submit request. Please try again.
    </div>
    <?php elseif($apply_msg === 'empty_fields'): ?>
    <div class="toast toast-error" id="main-toast">
        <i class="fa-solid fa-circle-xmark"></i> Please fill in all required fields.
    </div>
        <?php elseif($apply_msg === 'invalid_date_past'): ?>
        <div class="toast toast-error" id="main-toast">
            <i class="fa-solid fa-circle-xmark"></i> Leave dates cannot be before today.
        </div>
        <?php elseif($apply_msg === 'invalid_date_range'): ?>
        <div class="toast toast-error" id="main-toast">
            <i class="fa-solid fa-circle-xmark"></i> End date must be on or after the start date.
        </div>
    <?php endif; ?>

    <footer>
        <strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; All rights reserved.
    </footer>
</div><!-- /main -->

<script>
const ROWS_PER_PAGE = 10;
let currentPage = 1;
const startDateInput = document.querySelector('input[name="start_date"]');
const endDateInput = document.querySelector('input[name="end_date"]');
if(startDateInput && endDateInput){
    startDateInput.addEventListener('change', () => {
        endDateInput.min = startDateInput.value || '<?php echo $today; ?>';
        if(endDateInput.value && endDateInput.value < endDateInput.min){
            endDateInput.value = endDateInput.min;
        }
    });
}
function getRows(){ return Array.from(document.querySelectorAll('#leaves-tbody tr')); }
function renderPagination(){
    const rows = getRows();
    const total = rows.length;
    const pages = Math.ceil(total / ROWS_PER_PAGE);
    const pg = document.getElementById('pagination');
    const from = document.getElementById('showing-from');
    const to   = document.getElementById('showing-to');
    if(!pg) return;

    rows.forEach((r,i)=>{
        const start = (currentPage-1)*ROWS_PER_PAGE;
        r.style.display = (i>=start && i<start+ROWS_PER_PAGE) ? '' : 'none';
    });

    const startIdx = (currentPage-1)*ROWS_PER_PAGE + 1;
    const endIdx   = Math.min(currentPage*ROWS_PER_PAGE, total);
    if(from) from.textContent = total ? startIdx : 0;
    if(to)   to.textContent   = total ? endIdx   : 0;

    pg.innerHTML = '';
    const prev = document.createElement('div');
    prev.className = 'pg-btn' + (currentPage<=1 ? ' disabled' : '');
    prev.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size:13px;"></i>';
    prev.onclick = () => { if(currentPage>1){ currentPage--; renderPagination(); } };
    pg.appendChild(prev);

    for(let p=1;p<=pages;p++){
        const btn = document.createElement('div');
        btn.className = 'pg-btn' + (p===currentPage ? ' active' : '');
        btn.textContent = p;
        btn.onclick = () => { currentPage=p; renderPagination(); };
        pg.appendChild(btn);
    }

    const next = document.createElement('div');
    next.className = 'pg-btn' + (currentPage>=pages ? ' disabled' : '');
    next.innerHTML = '<i class="fa-solid fa-chevron-right" style="font-size:13px;"></i>';
    next.onclick = () => { if(currentPage<pages){ currentPage++; renderPagination(); } };
    pg.appendChild(next);
}

document.addEventListener('DOMContentLoaded', ()=>{
    renderPagination();
    const toast = document.getElementById('main-toast');
    if(toast){
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(()=>{ toast.style.opacity='0'; }, 3700);
        setTimeout(()=>toast.remove(), 4200);
    }
});
</script>
</body>
</html>
