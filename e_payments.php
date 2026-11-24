<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
$conn = db();

$owner_view = false;
if (($_SESSION['Login_Type'] ?? '') === 'admin') {
    $admin_code = $_SESSION['admin_code'] ?? '';
    $admin_stmt = $conn->prepare("SELECT admin_role FROM admin WHERE admin_code=? LIMIT 1");
    $admin_stmt->bind_param("s", $admin_code);
    $admin_stmt->execute();
    $admin_row = $admin_stmt->get_result()->fetch_assoc();
    $admin_stmt->close();

    if (strtoupper($admin_code) !== 'A001' || ($admin_row['admin_role'] ?? '') !== 'owner') {
        header('Location: login.php'); exit;
    }

    $emp_code = strtoupper(trim($_GET['emp_code'] ?? ''));
    $owner_view = true;
} elseif (($_SESSION['Login_Type'] ?? '') === 'employee') {
    $emp_code = $_SESSION['emp_code'] ?? '';
} else {
    header('Location: login.php'); exit;
}

// Fetch employee
$stmt = $conn->prepare("SELECT * FROM employees WHERE emp_code=?");
$stmt->bind_param("s",$emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$emp) die('<div style="color:red;padding:20px;">Employee not found.</div>');

// Fetch all payroll records for this employee (most recent first)
$pr = $conn->prepare("SELECT * FROM payroll WHERE emp_code=? ORDER BY pay_period_year DESC, pay_period_month DESC");
$pr->bind_param("s",$emp_code);
$pr->execute();
$payroll_rows = $pr->get_result()->fetch_all(MYSQLI_ASSOC);
$pr->close();

$latest = $payroll_rows[0] ?? null;

function initials($f,$l){ return strtoupper(substr($f,0,1).substr($l,0,1)); }
function fmt($n){ return 'LKR '.number_format((float)$n,2); }
function is_permanent($type){ return strtolower($type)==='permanent'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $owner_view ? 'Employee Payslip' : 'Payments'; ?> — Tiptop Apparel Payroll</title>
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
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:66px;background:var(--right-card);border-bottom:1px solid var(--right-border);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:100;flex-shrink:0;}
.topbar-left h1{font-size:22.5px;font-weight:700;color:var(--right-text);}
.topbar-left p{font-size:15px;color:var(--right-muted);margin-top:1px;}
.topbar-actions{display:flex;gap:10px;align-items:center;}
.tb-icon{width:36px;height:36px;border-radius:var(--r-sm);background:var(--right-bg);border:1px solid var(--right-border);color:var(--right-muted);display:flex;align-items:center;justify-content:center;font-size:17.5px;cursor:pointer;transition:all 0.18s;text-decoration:none;}
.tb-icon:hover{background:#e8f0fe;color:var(--accent);border-color:#c7d9fc;}
.page{padding:28px 30px;flex:1;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;animation:fadeUp 0.3s ease both;}
.section-gap{margin-bottom:24px;animation:fadeUp 0.35s ease 0.05s both;}
.card{background:var(--right-card);border:1px solid var(--right-border);border-radius:var(--r);overflow:hidden;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:15px 22px;border-bottom:1px solid var(--right-border);}
.card-title{font-size:18px;font-weight:700;color:var(--right-text);display:flex;align-items:center;gap:8px;}
.card-title i{color:var(--accent);}
.card-body{padding:22px;}
.current-banner{background:linear-gradient(120deg,#0b1e3d 0%,#1a3a6e 55%,#163060 100%);border-radius:var(--r);padding:22px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:20px;position:relative;overflow:hidden;animation:fadeUp 0.3s ease both;}
.current-banner::before{content:'';position:absolute;top:-50px;right:-50px;width:180px;height:180px;border-radius:50%;background:rgba(47,128,237,0.1);pointer-events:none;}
.cb-left{position:relative;z-index:1;}
.cb-label{font-size:15px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.45);margin-bottom:5px;}
.cb-month{font-size:29px;font-weight:800;color:#fff;}
.cb-right{position:relative;z-index:1;text-align:right;}
.cb-net-label{font-size:15px;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.07em;}
.cb-net-val{font-size:35px;font-weight:800;color:#6ee7b7;margin-top:3px;}
.pay-line{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:16px;}
.pay-line:last-child{border-bottom:none;}
.pay-line-name{color:var(--right-muted);font-weight:500;}
.pay-line-amt{font-weight:700;font-family:'JetBrains Mono',monospace;font-size:16px;}
.earn-amt{color:var(--success);}
.deduct-amt{color:var(--danger);}
.subtotal-row{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:var(--r-sm);margin-top:10px;font-size:16px;font-weight:700;}
.subtotal-earn{background:#f0fff4;border:1px solid #bbf7d0;color:#15803d;}
.subtotal-deduct{background:#fff5f5;border:1px solid #fecaca;color:#b91c1c;}
.subtotal-row span{font-family:'JetBrains Mono',monospace;}
.no-records{color:var(--right-dim);font-size:16px;font-style:italic;padding:12px 0;text-align:center;}
.net-highlight{background:linear-gradient(120deg,#0b1e3d,#1a3a6e);border-radius:var(--r-sm);padding:16px 20px;margin-top:16px;display:flex;align-items:center;justify-content:space-between;}
.nh-label{font-size:16px;font-weight:600;color:rgba(255,255,255,0.55);}
.nh-val{font-size:25px;font-weight:800;color:#6ee7b7;font-family:'JetBrains Mono',monospace;}
.ph-table{width:100%;border-collapse:collapse;font-size:16.25px;}
.ph-table thead tr{background:#f8fafd;}
.ph-table thead th{padding:11px 18px;text-align:left;font-size:15px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:var(--right-dim);border-bottom:1px solid var(--right-border);white-space:nowrap;}
.ph-table thead th:last-child{text-align:center;}
.ph-table tbody tr{border-bottom:1px solid #f1f5f9;transition:background 0.15s;}
.ph-table tbody tr:last-child{border-bottom:none;}
.ph-table tbody tr:hover{background:#f8fafd;}
.ph-table tbody td{padding:13px 18px;vertical-align:middle;}
.ph-table tbody td:last-child{text-align:center;}
.ph-month-val{font-weight:700;color:var(--right-text);font-size:16px;}
.ph-mono{font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:600;}
.ph-earn-val{color:var(--success);}
.ph-deduct-val{color:var(--danger);}
.ph-net-val{color:var(--accent);font-size:19px;font-weight:800;}
.ph-latest-tag{display:inline-block;background:#eff6ff;border:1px solid #bfdbfe;color:var(--accent);font-size:14px;font-weight:700;border-radius:10px;padding:2px 8px;margin-left:6px;vertical-align:middle;}
.btn-dl-sm{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:var(--r-sm);background:linear-gradient(135deg,#2f80ed,#1a6fd4);color:#fff;font-family:'Outfit',sans-serif;font-size:16px;font-weight:700;border:none;cursor:pointer;transition:all 0.18s;box-shadow:0 2px 6px rgba(47,128,237,0.25);}
.btn-dl-sm:hover{box-shadow:0 3px 10px rgba(47,128,237,0.4);transform:translateY(-1px);}
.empty-state{padding:50px 20px;text-align:center;color:var(--right-dim);}
.empty-state i{font-size:45px;opacity:0.2;display:block;margin-bottom:12px;}
.empty-state p{font-size:16px;}
footer{background:var(--right-card);border-top:1px solid var(--right-border);padding:14px 30px;font-size:15px;color:var(--right-dim);text-align:center;}
footer strong{color:var(--right-muted);}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
/* MODAL */
.ps-overlay{display:none;position:fixed;inset:0;background:rgba(5,15,40,0.6);z-index:500;align-items:flex-start;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:30px 16px;}
.ps-overlay.open{display:flex;animation:fadeIn 0.2s ease both;}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.ps-modal{background:#fff;border-radius:var(--r);width:100%;max-width:800px;box-shadow:0 24px 60px rgba(0,0,0,0.35);position:relative;animation:slideUp 0.25s ease both;margin:auto;}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.ps-modal-bar{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid var(--right-border);background:#fff;border-radius:var(--r) var(--r) 0 0;gap:12px;}
.ps-modal-bar-title{display:flex;align-items:center;gap:8px;font-size:19px;font-weight:700;color:var(--right-text);flex:1;min-width:0;}
.ps-modal-bar-title i{color:var(--accent);flex-shrink:0;}
.ps-modal-bar-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.ps-close-btn{width:34px;height:34px;border-radius:var(--r-sm);background:var(--right-bg);border:1px solid var(--right-border);color:var(--right-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.15s;font-size:18px;flex-shrink:0;}
.ps-close-btn:hover{background:#fff5f5;border-color:#fecaca;color:var(--danger);}
.btn-pdf{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:var(--r-sm);background:linear-gradient(135deg,#2f80ed,#1a6fd4);color:#fff;font-family:'Outfit',sans-serif;font-size:16px;font-weight:700;border:none;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 8px rgba(47,128,237,0.28);flex-shrink:0;}
.btn-pdf:hover{box-shadow:0 4px 14px rgba(47,128,237,0.42);transform:translateY(-1px);}
/* PAYSLIP DOC */
#ps-print-area{background:#fff;}
.ps-doc{padding:36px 40px;font-family:'Outfit',sans-serif;font-size:16px;color:#1a2740;}
.ps-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:26px;padding-bottom:20px;border-bottom:2px solid #e2e8f2;}
.ps-logo-box{width:50px;height:50px;border-radius:10px;background:linear-gradient(135deg,#0b1e3d,#1a3a6e);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;text-align:center;line-height:1.2;flex-shrink:0;}
.ps-logo-block{display:flex;align-items:center;gap:13px;}
.ps-company-name{font-size:21px;font-weight:800;color:#0b1e3d;}
.ps-company-sub{font-size:15px;color:#64748b;margin-top:2px;}
.ps-slip-info{text-align:right;}
.ps-slip-title{font-size:24px;font-weight:800;color:#0b1e3d;}
.ps-slip-month{font-size:16px;color:#64748b;margin-top:3px;}
.ps-slip-ref{display:inline-block;margin-top:7px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:14px;font-weight:700;padding:3px 11px;border-radius:20px;font-family:'JetBrains Mono',monospace;}
.ps-emp-grid{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #e2e8f2;border-radius:8px;overflow:hidden;margin-bottom:24px;}
.ps-emp-cell{padding:12px 16px;background:#f8fafd;border-right:1px solid #e2e8f2;}
.ps-emp-cell:last-child{border-right:none;}
.ps-emp-cell:nth-child(n+4){border-top:1px solid #e2e8f2;}
.ps-emp-label{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#94a3b8;margin-bottom:4px;}
.ps-emp-val{font-size:16px;font-weight:600;color:#1a2740;}
.ps-table-wrap{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;}
.ps-pay-table{width:100%;border-collapse:collapse;}
.ps-pay-table caption{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:8px 12px;text-align:left;border-radius:6px 6px 0 0;}
.caption-earn{background:#dcfce7;color:#15803d;}
.caption-deduct{background:#fee2e2;color:#b91c1c;}
.ps-pay-table thead th{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;padding:8px 12px;color:#64748b;border-bottom:1px solid #e2e8f2;background:#f8fafd;}
.ps-pay-table tbody tr{border-bottom:1px solid #f1f5f9;}
.ps-pay-table tbody tr:last-child{border-bottom:1px solid #e2e8f2;}
.ps-pay-table tbody td{padding:9px 12px;font-size:16px;}
.ps-pay-table tfoot td{padding:10px 12px;font-weight:800;font-size:16px;border-top:2px solid #e2e8f2;background:#f8fafd;}
.ps-net-box{border-radius:8px;padding:18px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(120deg,#0b1e3d,#1a3a6e);}
.ps-net-label{font-size:16px;font-weight:700;color:rgba(255,255,255,0.65);margin-bottom:4px;}
.ps-net-val{font-size:35px;font-weight:800;color:#6ee7b7;font-family:'JetBrains Mono',monospace;}
.ps-net-breakdown{text-align:right;}
.ps-net-item{font-size:15px;color:rgba(255,255,255,0.6);margin-top:4px;}
.ps-net-item span{font-weight:700;color:#fff;}
.ps-sig-area{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:28px;padding-top:20px;border-top:1px solid #e2e8f2;}
.ps-sig-label{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;margin-bottom:30px;}
.ps-sig-line{border-top:1px solid #1a2740;padding-top:6px;font-size:16px;color:#1a2740;font-weight:600;}
.ps-doc-footer{text-align:center;margin-top:22px;padding-top:14px;border-top:1px solid #e2e8f2;font-size:14px;color:#94a3b8;}
@media print{body *{visibility:hidden!important;}#ps-print-area,#ps-print-area *{visibility:visible!important;}#ps-print-area{position:fixed;inset:0;z-index:99999;background:#fff;padding:0;}@page{size:A4;margin:10mm;}}
@media(max-width:900px){.two-col{grid-template-columns:1fr;}}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}}
</style>
</head>
<body>
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
        <a href="e_leaves.php"    class="nav-link"><i class="fa-solid fa-plane-departure"></i> My Leaves</a>

        <a href="e_holidays.php"  class="nav-link"><i class="fa-solid fa-calendar-days"></i> Holidays</a>
        <a href="e_payments.php"  class="nav-link active"><i class="fa-solid fa-money-bill-wave"></i> Payments</a>
        <a href="e_profile.php"   class="nav-link"><i class="fa-solid fa-circle-user"></i> Profile</a>
    </nav>






    <div class="sb-footer">

        <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <h1><?php echo $owner_view ? 'Employee Payslip' : 'Payments'; ?></h1>
            <p><?php echo $owner_view ? 'View and download employee salary slips' : 'Your salary &amp; paysheet history'; ?></p>
        </div>
        <div class="topbar-actions">
            <?php if($owner_view): ?>
            <a href="a_payrollMng.php" class="tb-icon" title="Back to Payroll Management"><i class="fa-solid fa-arrow-left"></i></a>
            <?php else: ?>
            <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
            <?php endif; ?>
        </div>
    </header>

    <div class="page">
        <?php if(empty($payroll_rows)):?>
        <div class="card">
            <div class="empty-state">
                <i class="fa-solid fa-money-bill-slash"></i>
                <p>No salary records found. Paysheets will appear here once generated by admin.</p>
            </div>
        </div>
        <?php else:?>

        <!-- Current Month Banner -->
        <?php
        $lat_month=date('F Y',mktime(0,0,0,$latest['pay_period_month'],1,$latest['pay_period_year']));
        $total_allowances=($latest['allowance_target']??0)+($latest['allowance_attendance']??0)+($latest['allowance_meal']??0);
        $is_perm=is_permanent($emp['employment_type']);
        ?>
        <div class="current-banner">
            <div class="cb-left">
                <div class="cb-label"><i class="fa-regular fa-calendar" style="margin-right:5px;"></i>Latest Pay Period</div>
                <div class="cb-month"><?php echo $lat_month;?></div>
            </div>
            <div class="cb-right">
                <div class="cb-net-label">Net Salary</div>
                <div class="cb-net-val">LKR <?php echo number_format($latest['net_salary'],2);?></div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="two-col">
            <!-- Earnings -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-arrow-trend-up"></i> Earnings</div>
                    <span style="font-size:16px;color:var(--right-muted);"><?php echo $lat_month;?></span>
                </div>
                <div class="card-body">
                    <div class="pay-line">
                        <span class="pay-line-name">Basic Salary</span>
                        <span class="pay-line-amt earn-amt">+ LKR <?php echo number_format($latest['basic_salary'],2);?></span>
                    </div>
                    <?php if(($latest['allowance_target']??0)>0):?>
                    <div class="pay-line">
                        <span class="pay-line-name">Target Achievement Allowance</span>
                        <span class="pay-line-amt earn-amt">+ LKR <?php echo number_format($latest['allowance_target'],2);?></span>
                    </div>
                    <?php endif;?>
                    <?php if(($latest['allowance_attendance']??0)>0):?>
                    <div class="pay-line">
                        <span class="pay-line-name">Attendance Allowance</span>
                        <span class="pay-line-amt earn-amt">+ LKR <?php echo number_format($latest['allowance_attendance'],2);?></span>
                    </div>
                    <?php endif;?>
                    <?php if(($latest['allowance_meal']??0)>0):?>
                    <div class="pay-line">
                        <span class="pay-line-name">Meal Allowance</span>
                        <span class="pay-line-amt earn-amt">+ LKR <?php echo number_format($latest['allowance_meal'],2);?></span>
                    </div>
                    <?php endif;?>
                    <?php if(($latest['ot_payment']??0)>0):?>
                    <div class="pay-line">
                        <span class="pay-line-name">OT Payment</span>
                        <span class="pay-line-amt earn-amt">+ LKR <?php echo number_format($latest['ot_payment'],2);?></span>
                    </div>
                    <?php endif;?>
                    <div class="subtotal-row subtotal-earn">
                        <span>Total Earnings</span>
                        <span>LKR <?php echo number_format($latest['total_earnings'],2);?></span>
                    </div>
                </div>
            </div>

            <!-- Deductions -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-arrow-trend-down" style="color:var(--danger);"></i> Deductions</div>
                    <span style="font-size:16px;color:var(--right-muted);"><?php echo $lat_month;?></span>
                </div>
                <div class="card-body">
                    <?php if($is_perm && ($latest['epf_employee']??0)>0):?>
                    <div class="pay-line">
                        <span class="pay-line-name">EPF (Employee 8%)</span>
                        <span class="pay-line-amt deduct-amt">− LKR <?php echo number_format($latest['epf_employee'],2);?></span>
                    </div>
                    <?php endif;?>
                    <?php if($latest['total_deductions']==0):?>
                    <div class="no-records">No deductions recorded.</div>
                    <?php else:?>
                    <div class="subtotal-row subtotal-deduct">
                        <span>Total Deductions</span>
                        <span>LKR <?php echo number_format($latest['total_deductions'],2);?></span>
                    </div>
                    <?php endif;?>
                    <div class="net-highlight">
                        <span class="nh-label">Net Salary</span>
                        <span class="nh-val">LKR <?php echo number_format($latest['net_salary'],2);?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="card section-gap">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Paysheet History</div>
                <span style="font-size:16px;color:var(--right-muted);"><?php echo count($payroll_rows);?> record<?php echo count($payroll_rows)!==1?'s':'';?></span>
            </div>
            <div style="overflow-x:auto;">
                <table class="ph-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pay Period</th>
                            <th>Earnings (LKR)</th>
                            <th>Deductions (LKR)</th>
                            <th>Net Salary (LKR)</th>

                            <th>Paysheet</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($payroll_rows as $idx=>$p):
                        $period=date('F Y',mktime(0,0,0,$p['pay_period_month'],1,$p['pay_period_year']));
                    ?>
                    <tr>
                        <td style="color:var(--right-dim);font-size:16px;font-weight:700;font-family:'JetBrains Mono',monospace;"><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT);?></td>
                        <td>
                            <div class="ph-month-val">
                                <?php echo $period;?>
                                <?php if($idx===0):?><span class="ph-latest-tag">Latest</span><?php endif;?>
                            </div>
                        </td>
                        <td><span class="ph-mono ph-earn-val">+<?php echo number_format($p['total_earnings'],2);?></span></td>
                        <td><span class="ph-mono ph-deduct-val">−<?php echo number_format($p['total_deductions'],2);?></span></td>
                        <td><span class="ph-mono ph-net-val"><?php echo number_format($p['net_salary'],2);?></span></td>

                        <td>
                            <button class="btn-dl-sm" onclick="openPaysheet(<?php echo $idx;?>)">
                                <i class="fa-solid fa-file-pdf"></i> Download
                            </button>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif;?>
    </div>

    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll System</strong> &nbsp;|&nbsp; All rights reserved.</footer>
</div>

<!-- Paysheet Modal -->
<div class="ps-overlay" id="ps-overlay" onclick="overlayClick(event)">
    <div class="ps-modal" id="ps-modal">
        <div class="ps-modal-bar">
            <div class="ps-modal-bar-title">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span id="modal-title">Paysheet</span>
            </div>
            <div class="ps-modal-bar-actions">
                <button class="btn-pdf" onclick="printPaysheet()"><i class="fa-solid fa-download"></i> Download PDF</button>
                <button class="ps-close-btn" onclick="closePaysheet()"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div id="ps-print-area">
        <div class="ps-doc">
            <div class="ps-header">
                <div class="ps-logo-block">
                    <?php if(file_exists('Logo.jpeg')):?>
                    <img src="Logo.jpeg" alt="Tiptop Apparel" style="width:50px;height:50px;border-radius:8px;object-fit:contain;background:#fff;">
                    <?php else:?>
                    <div class="ps-logo-box">TIPTOP<br>APPAREL</div>
                    <?php endif;?>
                    <div>
                        <div class="ps-company-name">Tiptop Apparel</div>
                        <div class="ps-company-sub">Payroll Management System</div>
                    </div>
                </div>
                <div class="ps-slip-info">
                    <div class="ps-slip-title">Pay Slip</div>
                    <div class="ps-slip-month" id="ps-month-label"></div>
                    <div class="ps-slip-ref"   id="ps-ref-label"></div>
                </div>
            </div>

            <div class="ps-emp-grid">
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Employee Name</div>
                    <div class="ps-emp-val"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']);?></div>
                </div>
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Employee Code</div>
                    <div class="ps-emp-val" style="font-family:'JetBrains Mono',monospace;"><?php echo $emp['emp_code'];?></div>
                </div>
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Designation</div>
                    <div class="ps-emp-val"><?php echo htmlspecialchars($emp['designation']);?></div>
                </div>
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Section</div>
                    <div class="ps-emp-val"><?php echo htmlspecialchars($emp['section']);?></div>
                </div>
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Employment Type</div>
                    <div class="ps-emp-val"><?php echo ucfirst($emp['employment_type']);?></div>
                </div>
                <div class="ps-emp-cell">
                    <div class="ps-emp-label">Date of Joining</div>
                    <div class="ps-emp-val"><?php echo date('d M Y',strtotime($emp['date_of_joining']));?></div>
                </div>
            </div>

            <div class="ps-table-wrap">
                <div>
                    <table class="ps-pay-table">
                        <caption class="caption-earn">Earnings</caption>
                        <thead><tr><th style="text-align:left;">Pay Head</th><th style="text-align:right;">Amount (LKR)</th></tr></thead>
                        <tbody id="ps-earn-tbody"></tbody>
                        <tfoot><tr>
                            <td style="color:#15803d;">Total Earnings</td>
                            <td id="ps-earn-total" style="text-align:right;color:#15803d;font-family:'JetBrains Mono',monospace;"></td>
                        </tr></tfoot>
                    </table>
                </div>
                <div>
                    <table class="ps-pay-table">
                        <caption class="caption-deduct">Deductions</caption>
                        <thead><tr><th style="text-align:left;">Pay Head</th><th style="text-align:right;">Amount (LKR)</th></tr></thead>
                        <tbody id="ps-deduct-tbody"></tbody>
                        <tfoot><tr>
                            <td style="color:#b91c1c;">Total Deductions</td>
                            <td id="ps-deduct-total" style="text-align:right;color:#b91c1c;font-family:'JetBrains Mono',monospace;"></td>
                        </tr></tfoot>
                    </table>
                </div>
            </div>

            <div class="ps-net-box">
                <div>
                    <div class="ps-net-label">Net Salary Payable</div>
                    <div class="ps-net-val" id="ps-net-val"></div>
                </div>
                <div class="ps-net-breakdown">
                    <div class="ps-net-item">Total Earnings: <span id="ps-b-earn"></span></div>
                    <div class="ps-net-item">Total Deductions: <span id="ps-b-deduct"></span></div>
                </div>
            </div>

            <div class="ps-sig-area">
                <div>
                    <div class="ps-sig-label">Employee Signature</div>
                    <div class="ps-sig-line"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']);?></div>
                </div>
                <div>
                    <div class="ps-sig-label">Authorised Signatory</div>
                    <div class="ps-sig-line">Tiptop Apparel Payroll</div>
                </div>
            </div>

            <div class="ps-doc-footer">
                This is a computer-generated payslip and does not require a physical signature.
                &nbsp;|&nbsp; Generated on <span id="ps-gen-date"></span>
                &nbsp;|&nbsp; Tiptop Apparel Payroll System
            </div>
        </div>
        </div>
    </div>
</div>

<script>
const PAYROLL_DATA = <?php echo json_encode($payroll_rows);?>;
const IS_PERMANENT = <?php echo $is_perm?'true':'false';?>;

function fmt(n){ return 'LKR ' + parseFloat(n).toLocaleString('en-LK',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function openPaysheet(idx){
    const p = PAYROLL_DATA[idx];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const period = months[p.pay_period_month-1] + ' ' + p.pay_period_year;

    document.getElementById('modal-title').textContent = 'Paysheet — ' + period;
    document.getElementById('ps-month-label').textContent = period;
    document.getElementById('ps-ref-label').textContent = 'REF: <?php echo $emp['emp_code'];?>-' + p.pay_period_year + String(p.pay_period_month).padStart(2,'0');
    document.getElementById('ps-gen-date').textContent = new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'});

    // Build earnings rows
    const eTbody = document.getElementById('ps-earn-tbody');
    eTbody.innerHTML = '';
    const earnings = [
        {name:'Basic Salary', amount:p.basic_salary},
        {name:'Target Achievement Allowance', amount:p.allowance_target},
        {name:'Attendance Allowance', amount:p.allowance_attendance},
        {name:'Meal Allowance', amount:p.allowance_meal},
        {name:'OT Payment', amount:p.ot_payment}
    ];
    earnings.forEach(e => {
        if(parseFloat(e.amount)>0){
            eTbody.innerHTML += `<tr><td>${e.name}</td><td style="text-align:right;font-family:'JetBrains Mono',monospace;color:#15803d;">+ ${fmt(e.amount)}</td></tr>`;
        }
    });

    // Build deductions rows
    const dTbody = document.getElementById('ps-deduct-tbody');
    dTbody.innerHTML = '';
    if(IS_PERMANENT && parseFloat(p.epf_employee)>0){
        dTbody.innerHTML += `<tr><td>EPF (Employee 8%)</td><td style="text-align:right;font-family:'JetBrains Mono',monospace;color:#b91c1c;">− ${fmt(p.epf_employee)}</td></tr>`;
    }
    if(dTbody.innerHTML === ''){
        dTbody.innerHTML = '<tr><td colspan="2" style="color:#94a3b8;font-style:italic;padding:9px 12px;">No deductions recorded</td></tr>';
    }

    document.getElementById('ps-earn-total').textContent   = fmt(p.total_earnings);
    document.getElementById('ps-deduct-total').textContent = fmt(p.total_deductions);
    document.getElementById('ps-net-val').textContent      = fmt(p.net_salary);
    document.getElementById('ps-b-earn').textContent       = fmt(p.total_earnings);
    document.getElementById('ps-b-deduct').textContent     = fmt(p.total_deductions);

    document.getElementById('ps-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePaysheet(){
    document.getElementById('ps-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

function overlayClick(e){
    if(e.target===document.getElementById('ps-overlay')) closePaysheet();
}

function printPaysheet(){ window.print(); }

document.addEventListener('keydown', e => { if(e.key==='Escape') closePaysheet(); });
</script>
</body></html>