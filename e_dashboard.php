<?php
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
} else {
    $conn->error_list();
}


if(!$emp){
    session_destroy();
    header('Location: login.php');
    exit;
}

$is_manager = ($emp['designation'] === 'HR Manager' || $emp['designation'] === 'Payroll Manager') ? 1 : 0;

// Quota for employees (required: Total(21)=Annual(14)+Casual(7))
$annual_allowed   = 14;
$casual_allowed   = 7;
$total_allowed    = $annual_allowed + $casual_allowed;
$deduction_per_day= 1500.00;

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
$casual_taken = $typeCounts['casual'] + $typeCounts['sick'];
$annual_taken = $typeCounts['annual'];
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


$latest_sal = null;
$sal_res = $conn->query(
    "SELECT * FROM payroll WHERE emp_code = '".$conn->real_escape_string($emp_code)."' ORDER BY created_at DESC LIMIT 1"
);
if ($sal_res !== false) {
    $latest_sal = $sal_res->fetch_assoc();
}


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
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.sr{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.main{margin-left:0;}.sr{grid-template-columns:1fr;}}
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
        <a href="e_dashboard.php" class="nav-link active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="e_leaves.php"    class="nav-link"><i class="fa-solid fa-plane-departure"></i> My Leaves</a>
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
            <h1>Dashboard</h1>
            <p><?php echo date('l, d F Y'); ?></p>
        </div>
        <div class="topbar-actions">
            <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
        </div>
    </header>

    <div class="page">

        <!-- Welcome Banner -->
        <div class="wb">
            <div class="wb-left">
                <div class="wb-greeting">Good <?php echo (int)date('H') < 12 ? 'morning' : ((int)date('H') < 17 ? 'afternoon' : 'evening'); ?></div>
                <div class="wb-name"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                <div class="wb-role"><?php echo htmlspecialchars($emp['designation']); ?> — <?php echo htmlspecialchars($emp['section']); ?></div>
            </div>
            <div class="wb-right">
                <div class="wb-date-label">Today</div>
                <div class="wb-date-val"><?php echo date('d M Y'); ?></div>
                <div class="wb-time" id="wb-clock"><?php echo date('H:i:s'); ?></div>
            </div>
        </div>



        <!-- Charts & Side -->
        <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
            <div>
                <!-- Salary Chart -->
                <div class="card">
                    <div class="ch"><div class="ct"><i class="fa-solid fa-chart-line"></i> Salary Increment</div></div>
                    <div class="cb">
<?php
                        // Salary increment trend (year-wise): sums of increment_amount from joined year onward
                        $joinedYear = null;
                        if (!empty($emp['date_of_joining'])) {
                            $tsJoin = strtotime($emp['date_of_joining']);
                            $joinedYear = $tsJoin ? (int)date('Y', $tsJoin) : null;
                        }
                        $joinedYear = $joinedYear ?? (int)date('Y');

                        // Determine last year to draw: from effective_date, or current year
                        $maxYearRes = $conn->query(
                            "SELECT MAX(CAST(strftime('%Y', effective_date) AS INTEGER)) AS max_year
                             FROM salary_increments
                             WHERE emp_code='".$conn->real_escape_string($emp_code)."'"
                        );
                        $maxYear = $maxYearRes ? (int)($maxYearRes->fetch_assoc()['max_year'] ?? (int)date('Y')) : (int)date('Y');
                        $maxYear = max($maxYear, (int)date('Y'));

                        // Fetch increment sums by effective year
                        $inc_rows = $conn->query(
                            "SELECT CAST(strftime('%Y', effective_date) AS INTEGER) AS inc_year,
                                    SUM(COALESCE(increment_amount,0)) AS inc_amount
                             FROM salary_increments
                             WHERE emp_code='".$conn->real_escape_string($emp_code)."'
                             GROUP BY inc_year
                             ORDER BY inc_year ASC"
                        );
                        $inc = $inc_rows ? $inc_rows->fetch_all(MYSQLI_ASSOC) : [];

                        // Build continuous year series from joinedYear to maxYear
                        $incMap = [];
                        foreach ($inc as $row) {
                            $y = (int)($row['inc_year'] ?? 0);
                            if ($y > 0) $incMap[$y] = (float)($row['inc_amount'] ?? 0);
                        }

                        $years = [];
                        $values = [];
                        for ($y = $joinedYear; $y <= $maxYear; $y++) {
                            $years[] = $y;
                            $values[] = (float)($incMap[$y] ?? 0);
                        }

                        $max_net = max(1, max($values ?: [0]));
                        ?>
                        <div style="padding:14px 14px;border:1px solid #e1e8f0;border-radius:7px;background:#fff;">
<?php if(empty($values) || max($values) <= 0) : ?>
                                <div style="width:100%;text-align:center;color:#64748b;font-weight:700;padding:32px 10px;">No salary increment data yet.</div>
<?php else: ?>
                                <?php
                                    $w = 520; $h = 170; $padL=34; $padR=12; $padT=14; $padB=28;
                                    $plotW = $w-$padL-$padR; $plotH = $h-$padT-$padB;
                                    $n = count($values); // one point per year in series
                                    $stepX = $n>1 ? ($plotW/($n-1)) : 0;
                                    $points = [];
                                    $labels = [];
                                    for($i=0;$i<$n;$i++){
                                        $year = (int)($years[$i] ?? 0);
                                        $val  = (float)($values[$i] ?? 0);
                                        $x = $padL + $i*$stepX;
                                        $y = $padT + ($max_net>0 ? ($plotH*(1-($val/$max_net))) : $plotH);
                                        $points[] = $x.','.$y;
                                        $labels[] = [$year,$x,$val];
                                    }
                                    $polyPoints = implode(' ', $points);
                                ?>
                                <div style="display:flex;gap:12px;align-items:flex-start;">
                                    <svg viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>" width="100%" height="170" style="overflow:visible;">
                                        <!-- grid -->
                                        <?php for($g=0;$g<=4;$g++): ?>
                                            <?php $gy = $padT + ($plotH*($g/4)); ?>
                                            <line x1="<?php echo $padL; ?>" y1="<?php echo $gy; ?>" x2="<?php echo $w-$padR; ?>" y2="<?php echo $gy; ?>" stroke="#eef2f7" stroke-width="1"></line>
                                        <?php endfor; ?>
                                        <!-- axis labels (max only) -->
                                        <text x="8" y="<?php echo $padT+12; ?>" font-size="12" fill="#94a3b8" font-weight="700">LKR <?php echo number_format($max_net,0); ?></text>
                                        <polyline points="<?php echo htmlspecialchars($polyPoints); ?>" fill="none" stroke="#2f80ed" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"></polyline>
                                        <?php foreach($labels as $idx=>$lbl): ?>
                                            <?php $year = $lbl[0]; $x=(float)$lbl[1]; $val=(float)$lbl[2]; $yParts = explode(',',$points[$idx]); $y=(float)$yParts[1]; ?>
                                            <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="5" fill="#fff" stroke="#2f80ed" stroke-width="3"></circle>
                                            <title><?php echo $year; ?>: LKR <?php echo number_format($val,2); ?></title>
                                        <?php endforeach; ?>
                                        <?php foreach($labels as $lbl): ?>
                                            <?php $year=(int)$lbl[0]; $x=(float)$lbl[1]; ?>
                                            <text x="<?php echo $x; ?>" y="<?php echo $h-$padB+18; ?>" text-anchor="middle" font-size="12" fill="#64748b" font-weight="700"><?php echo $year; ?></text>
                                        <?php endforeach; ?>
                                    </svg>
                                </div>
                                <div style="margin-top:10px;font-size:14px;color:#64748b;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                    <div>Trend shows <strong style="color:#1a2740;">salary increment (LKR)</strong> per year.</div>
                                    <div>Latest net salary: <strong style="color:#1a2740;"> <?php echo $latest_sal ? 'LKR '.number_format($latest_sal['net_salary'],2) : '—'; ?></strong></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Leave Quota -->
                <div class="card">
                    <div class="ch"><div class="ct"><i class="fa-solid fa-chart-pie"></i> Annual Leave Quota</div></div>
                    <div class="cb">
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
                            <div style="background:#f8fafd;border:1px solid #e1e8f0;border-radius:10px;padding:12px 14px;">
                                <div style="font-size:13px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Total</div>
                                <div style="font-size:28px;font-weight:900;color:#1a2740;"><?php echo $total_allowed; ?></div>
                                <div style="font-size:14px;color:#64748b;"><?php echo $annual_allowed; ?> annual + <?php echo $casual_allowed; ?> casual</div>

                            </div>
                            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;">
                                <div style="font-size:13px;font-weight:800;color:#166534;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Annual Taken</div>
                                <div style="font-size:28px;font-weight:900;color:#15803d;"><?php echo $annual_taken; ?></div>
                                <div style="font-size:14px;color:#64748b;">Remaining <?php echo $annual_remaining; ?></div>
                            </div>
                            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;">
                                <div style="font-size:13px;font-weight:800;color:#1d4ed8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Casual + Sick</div>
                                <div style="font-size:28px;font-weight:900;color:#1d4ed8;"><?php echo $casual_taken; ?></div>
                                <div style="font-size:14px;color:#64748b;">Remaining <?php echo $casual_remaining; ?></div>
                            </div>
                        </div>

                        <?php
                        $annualPct = $annual_allowed > 0 ? min(100, (int)round(($annual_taken / $annual_allowed) * 100)) : 0;
                        $casualPct = $casual_allowed > 0 ? min(100, (int)round(($casual_taken / $casual_allowed) * 100)) : 0;
                        $annualArc = 283 - (283 * $annualPct / 100);
                        $casualArc = 283 - (283 * $casualPct / 100);
                        ?>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                            <div style="background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:16px;text-align:center;">
                                <div style="font-size:15px;font-weight:800;color:#1a2740;margin-bottom:12px;">Annual Leave</div>
                                <div style="position:relative;width:120px;height:120px;margin:0 auto 12px;">
                                    <svg viewBox="0 0 120 120" width="120" height="120" style="transform:rotate(-90deg);">
                                        <circle cx="60" cy="60" r="45" stroke="#e5e7eb" stroke-width="12" fill="none"></circle>
                                        <circle cx="60" cy="60" r="45" stroke="#16a34a" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="<?php echo $annualArc; ?>"></circle>
                                    </svg>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;">
                                        <div style="font-size:26px;font-weight:900;color:#15803d;"><?php echo $annual_remaining; ?></div>
                                        <div style="font-size:13px;color:#64748b;">remaining</div>
                                    </div>
                                </div>
                                <div style="font-size:14px;color:#64748b;">Taken <?php echo $annual_taken; ?> of <?php echo $annual_allowed; ?></div>
                            </div>
                            <div style="background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:16px;text-align:center;">
                                <div style="font-size:15px;font-weight:800;color:#1a2740;margin-bottom:12px;">Casual Leave</div>
                                <div style="position:relative;width:120px;height:120px;margin:0 auto 12px;">
                                    <svg viewBox="0 0 120 120" width="120" height="120" style="transform:rotate(-90deg);">
                                        <circle cx="60" cy="60" r="45" stroke="#e5e7eb" stroke-width="12" fill="none"></circle>
                                        <circle cx="60" cy="60" r="45" stroke="#2563eb" stroke-width="12" fill="none" stroke-linecap="round" stroke-dasharray="283" stroke-dashoffset="<?php echo $casualArc; ?>"></circle>
                                    </svg>
                                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;">
                                        <div style="font-size:26px;font-weight:900;color:#2563eb;"><?php echo $casual_remaining; ?></div>
                                        <div style="font-size:13px;color:#64748b;">remaining</div>
                                    </div>
                                </div>
                                <div style="font-size:14px;color:#64748b;">Taken <?php echo $casual_taken; ?> of <?php echo $casual_allowed; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Holidays -->
            <div>
                <div class="card">
                    <div class="ch"><div class="ct"><i class="fa-solid fa-calendar-days"></i> Upcoming Holidays</div></div>
                    <?php if(empty($upcoming_holidays)): ?>
                        <div style="padding:18px;text-align:center;font-size:16px;color:#64748b;">No upcoming holidays.</div>
                    <?php else: ?>
                        <div style="padding:5px 0;">
                            <?php foreach(array_slice($upcoming_holidays,0,6) as $hol): $hts=$hol['ts']; ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:9px 16px;border-bottom:1px solid #f1f5f9;">
                                    <div style="width:36px;height:36px;border-radius:9px;background:#e0f2fe;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;">
                                        <span style="font-size:16px;font-weight:800;color:#0369a1;line-height:1;"><?php echo date('d',$hts); ?></span>
                                        <span style="font-size:13px;font-weight:700;color:#0ea5e9;"> <?php echo strtoupper(date('M',$hts)); ?> </span>
                                    </div>
                                    <div>
                                        <div style="font-size:16px;font-weight:600;color:#1a2740;"><?php echo htmlspecialchars($hol['title']); ?></div>
                                        <div style="font-size:15px;color:#94a3b8;"><?php echo date('l',$hts); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /page -->

    <footer>
        <strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; All rights reserved.
    </footer>
</div><!-- /main -->

<script>
function ec(){
  const n=new Date();
  const p=v=>String(v).padStart(2,'0');
  const t=p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());
  const el=document.getElementById('wb-clock');
  if(el) el.textContent=t;
}
setInterval(ec,1000); ec();
</script>
</body>
</html>
