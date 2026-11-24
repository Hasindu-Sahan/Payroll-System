<?php
// Updated employee dashboard: salary increment year-wise line chart (replaces bar chart).
ob_start();
session_start();
require 'db.php';
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
$conn = db();

if(!isset($_SESSION['Login_Type']) || $_SESSION['Login_Type'] !== 'employee'){
    header('Location: login.php');
    exit;
}

$emp_code = $_SESSION['emp_code'] ?? '';
$emp = null;
$s = $conn->prepare("SELECT * FROM employees WHERE emp_code = ?");
if ($s !== false) {
    $s->bind_param("s", $emp_code);
    $s->execute();
    $emp = $s->get_result()?->fetch_assoc();
    $s->close();
}

if(!$emp){
    session_destroy();
    header('Location: login.php');
    exit;
}

$is_manager = ($emp['designation'] === 'HR Manager' || $emp['designation'] === 'Payroll Manager') ? 1 : 0;

// Leave quotas (as required: Annual 14 + Casual 7 = Total 21)
$annual_allowed    = 14;
$casual_allowed    = 7;
$total_allowed     = $annual_allowed + $casual_allowed;
$deduction_per_day = 1500.00;

$year = date('Y');
$ym   = date('Y-m');

$approved_days = 0;
$al = $conn->prepare("SELECT SUM(no_of_days) AS total_days FROM leaves WHERE emp_code=? AND leave_status='approved' AND YEAR(applied_date)=?");
if ($al !== false) {
    $al->bind_param("si", $emp_code, $year);
    $al->execute();
    $row = $al->get_result()?->fetch_assoc();
    $approved_days = (int)($row['total_days'] ?? 0);
    $al->close();
}

$typeCounts = ['casual' => 0, 'sick' => 0, 'annual' => 0];
$tq = $conn->prepare("SELECT leave_type, SUM(no_of_days) AS total_days FROM leaves WHERE emp_code=? AND leave_status='approved' AND YEAR(applied_date)=? GROUP BY leave_type");
if ($tq !== false) {
    $tq->bind_param("si", $emp_code, $year);
    $tq->execute();
    $tr = $tq->get_result();
    while ($row = $tr->fetch_assoc()) {
        $lt = strtolower(trim((string)($row['leave_type'] ?? '')));
        if (isset($typeCounts[$lt])) {
            $typeCounts[$lt] += (int)($row['total_days'] ?? 0);
        }
    }
    $tq->close();
}

$casual_taken     = $typeCounts['casual'] + $typeCounts['sick'];
$annual_taken     = $typeCounts['annual'];
$casual_remaining = max(0, $casual_allowed - $casual_taken);
$annual_remaining = max(0, $annual_allowed - $annual_taken);
$remaining_leaves = $casual_remaining + $annual_remaining;

$pending_count = 0;
$pl = $conn->prepare("SELECT COUNT(*) c FROM leaves WHERE emp_code=? AND leave_status='pending'");
if ($pl !== false) {
    $pl->bind_param("s", $emp_code);
    $pl->execute();
    $row = $pl->get_result()?->fetch_assoc();
    $pending_count = (int)($row['c'] ?? 0);
    $pl->close();
}

// Latest salary for small text
$latest_sal = null;
$sal_res = $conn->query(
    "SELECT * FROM payroll WHERE emp_code = '".$conn->real_escape_string($emp_code)."' ORDER BY created_at DESC LIMIT 1"
);
if ($sal_res !== false) {
    $latest_sal = $sal_res->fetch_assoc();
}

// Upcoming holidays
$upcoming_holidays = [];
$hq = $conn->query("SELECT holiday_name, holiday_date FROM holidays");
if ($hq) {
    while ($h = $hq->fetch_assoc()) {
        $ts = strtotime($h['holiday_date'] ?? 'now');
        $upcoming_holidays[] = [
            'title' => $h['holiday_name'] ?? 'Holiday',
            'ts'    => $ts,
            'date'  => $h['holiday_date'] ?? 'N/A',
            'type'  => 'general'
        ];
    }
}
usort($upcoming_holidays, fn($a,$b) => $a['ts'] <=> $b['ts']);

$att_month = 0;
$att_res = $conn->query(
    "SELECT COUNT(DISTINCT attendance_date) c FROM attendance WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND DATE_FORMAT(attendance_date,'%Y-%m')='$ym' AND status='present'"
);
if ($att_res !== false) {
    $att_row = $att_res->fetch_assoc();
    $att_month = (int)($att_row['c'] ?? 0);
}

function initials($f,$l){
    return strtoupper(substr((string)$f,0,1) . substr((string)$l,0,1));
}

// Salary increment (year-wise) = payroll net_salary aggregated by pay_period_year
$inc_year_rows = $conn->query(
    "SELECT pay_period_year, SUM(net_salary) AS total_net_salary, AVG(net_salary) AS avg_net_salary " .
    "FROM payroll WHERE emp_code='".$conn->real_escape_string($emp_code)."' " .
    "GROUP BY pay_period_year ORDER BY pay_period_year ASC" 
);
$inc_year_data = [];
if ($inc_year_rows) {
    while($r = $inc_year_rows->fetch_assoc()){
        $inc_year_data[] = [
            'year' => (int)($r['pay_period_year'] ?? 0),
            'value'=> (float)($r['avg_net_salary'] ?? 0),
        ];
    }
}

// For line chart scaling
$inc_values = array_map(fn($x)=> (float)$x['value'], $inc_year_data);
$inc_max = $inc_values ? max(1, max($inc_values)) : 1;
$inc_min = $inc_values ? min($inc_values) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Tiptop Apparel Payroll</title>
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
/ * --- SIDEBAR --- * /
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
/ * --- MAIN --- * /
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
/ * --- PAGE CONTENT --- * /
.wb{background:linear-gradient(135deg,#1e3a8a,#1e40af,#2563eb);border-radius:10px;padding:24px 28px;margin-bottom:24px;box-shadow:0 4px 16px rgba(30,58,138,.15);color:#fff;display:flex;align-items:center;justify-content:space-between;}
.wb-left .wb-greeting{font-size:16px;font-weight:600;opacity:.85;margin-bottom:4px;letter-spacing:.03em;}
.wb-left .wb-name{font-size:28px;font-weight:800;margin-bottom:6px;}
.wb-left .wb-role{font-size:16px;opacity:.75;font-weight:500;}
.wb-right{text-align:right;}
.wb-date-label{font-size:14px;font-weight:600;opacity:.75;margin-bottom:4px;letter-spacing:.06em;text-transform:uppercase;}
.wb-date-val{font-size:23px;font-weight:800;margin-bottom:8px;}
.wb-time{font-size:20px;font-weight:600;opacity:.9;font-family:'JetBrains Mono',monospace;}
.sr{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px;}
.sc{background:#fff;border-radius:10px;padding:20px;border:1px solid #e1e8f0;display:flex;align-items:center;gap:16px;transition:all .2s;}
.sc:hover{box-shadow:0 2px 10px rgba(0,0,0,.05);}
.si{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:25px;flex-shrink:0;}
.si-i{background:#eef2ff;color:#4338ca;}
.si-o{background:#fff5f0;color:#dd6b20;}
.si-g{background:#f0fdf4;color:#16a34a;}
.si-v{background:#faf5ff;color:#7c3aed;}
.sv{font-size:28px;font-weight:800;line-height:1;color:#1a2740;}
.sl{font-size:15px;font-weight:600;color:#64748b;margin-top:5px;text-transform:uppercase;letter-spacing:.03em;}
.card{background:#fff;border-radius:10px;border:1px solid #e1e8f0;margin-bottom:22px;overflow:hidden;}
.ch{padding:16px 20px;border-bottom:1px solid #e1e8f0;display:flex;align-items:center;justify-content:space-between;}
.ct{font-size:18px;font-weight:700;color:#1a2740;display:flex;align-items:center;gap:8px;}
.ct i{color:#2f80ed;}
.cb{padding:20px;}
.qbar{height:10px;background:#e1e8f0;border-radius:20px;overflow:hidden;margin-top:2px;}
.qfill{height:100%;border-radius:20px;transition:width .3s;}
/ * Line chart * /
.linechart-wrap{border:1px solid #e1e8f0;border-radius:7px;background:#fff;padding:10px 12px;}
.linechart-legend{margin-top:10px;font-size:15px;color:#64748b;}
@media(max-width:900px){.sr{grid-template-columns:repeat(2,1fr);}}
