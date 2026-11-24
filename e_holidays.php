<?php
session_start();
date_default_timezone_set('Asia/Colombo');

if(!isset($_SESSION['Login_Type']) || $_SESSION['Login_Type'] !== 'employee'){
    header('Location: login.php'); exit;
}

require_once __DIR__ . '/db.php';
$conn = db();

$emp_code = $_SESSION['emp_code'] ?? '';

$stmt = $conn->prepare('SELECT * FROM employees WHERE emp_code = ?');
$stmt->bind_param('s', $emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$emp) die('<div style="color:red;padding:20px;">Employee not found.</div>');

$holidays = $conn->query("SELECT * FROM holidays ORDER BY holiday_date ASC")->fetch_all(MYSQLI_ASSOC);
$today_ymd = date('Y-m-d');

function parseDate($raw){
    if(!$raw) return null;
    $clean = str_replace('/','- ',trim($raw));
    $ts = strtotime($clean);
    return $ts ? date('Y-m-d',$ts) : null;
}
function formatDisplay($raw){
    if(!$raw) return '—';
    $clean = str_replace('/','-',trim($raw));
    $ts = strtotime($clean);
    return $ts ? date('d M Y',$ts) : htmlspecialchars($raw);
}

$upcoming     = null;
$days_until   = null;
foreach($holidays as $h){
    $hd = parseDate($h['holiday_date'] ?? null);
    if($hd && $hd >= $today_ymd){
        $upcoming = $h;
        $upcoming['_ymd'] = $hd;
        $days_until = (int)(new DateTime($hd))->diff(new DateTime($today_ymd))->days;
        break;
    }
}

$active_date = trim($_GET['active_date'] ?? '');
if(!$active_date) $active_date = $today_ymd;

$active_month = (int)date('m', strtotime($active_date));
$month_start  = date('Y-m-01', strtotime($active_date));

$byDate = [];
foreach($holidays as $h){
    $d = parseDate($h['holiday_date'] ?? null);
    if($d){
        if(!isset($byDate[$d])) $byDate[$d] = [];
        $byDate[$d][] = $h;
    }
}

function initials($f,$l){ return strtoupper(substr($f,0,1).substr($l,0,1)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Holidays — Tiptop Apparel Payroll</title>
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
    /* aliases used in holiday page styles */
    --rc:var(--right-card);--rb:var(--right-border);--rt:var(--right-text);
    --rm:var(--right-muted);--rd:var(--right-dim);--ac:var(--accent);--rbg:var(--right-bg);
    --danger:#e53e3e;--warn:#dd6b20;--success:#27ae60;--r:10px;--r-sm:7px;--rs:7px;
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
.upcoming-banner{background:linear-gradient(120deg,#0b1e3d 0%,#1a3a6e 55%,#163060 100%);border-radius:var(--r);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:20px;position:relative;overflow:hidden;animation:fadeUp 0.3s ease both;}
.upcoming-banner::before{content:'';position:absolute;top:-50px;right:-50px;width:180px;height:180px;border-radius:50%;background:rgba(47,128,237,0.1);pointer-events:none;}
.upcoming-banner::after{content:'';position:absolute;bottom:-40px;right:120px;width:110px;height:110px;border-radius:50%;background:rgba(39,174,96,0.07);pointer-events:none;}
.ub-left{position:relative;z-index:1;}
.ub-eyebrow{font-size:14px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:rgba(255,255,255,0.45);margin-bottom:7px;display:flex;align-items:center;gap:6px;}
.ub-eyebrow i{color:#6db3f7;}
.ub-title{font-size:28px;font-weight:800;color:#fff;}
.ub-desc{font-size:16px;color:rgba(255,255,255,0.58);margin-top:5px;}
.ub-date{display:inline-flex;align-items:center;gap:8px;margin-top:12px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:20px;padding:5px 15px;font-size:16px;font-weight:600;color:#fff;}
.ub-date i{color:#6db3f7;}
.ub-right{position:relative;z-index:1;text-align:center;flex-shrink:0;}
.ub-countdown{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:var(--r);padding:18px 28px;min-width:110px;}
.ub-days{font-size:50px;font-weight:800;color:#fff;line-height:1;}
.ub-days-lbl{font-size:15px;color:rgba(255,255,255,0.48);text-transform:uppercase;letter-spacing:0.08em;margin-top:4px;}
.card{background:var(--rc);border:1px solid var(--rb);border-radius:var(--r);box-shadow:0 1px 4px rgba(0,0,0,0.04);overflow:hidden;margin-bottom:24px;animation:fadeUp 0.35s ease 0.05s both;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--rb);flex-wrap:wrap;gap:12px;}
.card-title{font-size:18px;font-weight:700;color:var(--rt);display:flex;align-items:center;gap:8px;}
.card-title i{color:var(--ac);}
.btn-ghost{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--rs);border:1px solid var(--rb);background:var(--rc);color:var(--rt);font-family:'Outfit',sans-serif;font-size:16px;font-weight:600;text-decoration:none;cursor:pointer;transition:all .18s;}
.btn-ghost:hover{background:var(--rbg);border-color:#c7d2fe;color:var(--ac);}
.day-inner{border:1px solid var(--rb);border-radius:var(--rs);padding:10px 8px;min-height:86px;background:var(--rc);transition:border-color .15s;}
.day-inner:hover{border-color:#c7d2fe;}
.data-table{width:100%;border-collapse:collapse;font-size:15px;}
.data-table thead tr{background:#f8fafd;}
.data-table thead th{padding:11px 18px;text-align:left;font-size:14px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--rm);border-bottom:2px solid var(--rb);white-space:nowrap;}
.data-table tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
.data-table tbody tr:last-child{border-bottom:none;}
.data-table tbody tr:hover{background:#f8fafd;}
.data-table tbody td{padding:13px 18px;vertical-align:middle;font-size:16px;color:var(--rt);}
.table-empty{text-align:center;padding:48px 20px;color:var(--rd);}
.table-empty i{font-size:44px;display:block;margin-bottom:10px;opacity:.22;}
.table-empty p{font-size:16px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:900px){.upcoming-banner{flex-direction:column;align-items:flex-start;}}
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
        <a href="e_leaves.php"    class="nav-link"><i class="fa-solid fa-plane-departure"></i> My Leaves</a>
        <a href="e_holidays.php"  class="nav-link active"><i class="fa-solid fa-calendar-days"></i> Holidays</a>
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
            <h1>Holidays</h1>
            <p>Company holidays &amp; public observances</p>
        </div>
        <div class="topbar-actions">
            <a href="e_profile.php" class="tb-icon" title="Profile"><i class="fa-solid fa-circle-user"></i></a>
        </div>
    </header>

    <div class="page">

        <?php if($upcoming): ?>
        <div class="upcoming-banner">
            <div class="ub-left">
                <div class="ub-eyebrow">
                    <i class="fa-regular fa-bell"></i> Next Upcoming Holiday
                </div>
                <div class="ub-title"><?php echo htmlspecialchars($upcoming['holiday_name'] ?? 'Holiday'); ?></div>
                <div class="ub-desc"><?php echo htmlspecialchars($upcoming['description'] ?? $upcoming['holiday_desc'] ?? ''); ?></div>
                <div class="ub-date">
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo formatDisplay($upcoming['holiday_date'] ?? null); ?>
                    &nbsp;·&nbsp;
                    <?php echo date('l', strtotime($upcoming['holiday_date'] ?? 'now')); ?>
                </div>
            </div>
            <div class="ub-right">
                <div class="ub-countdown">
                    <div class="ub-days"><?php echo (int)$days_until; ?></div>
                    <div class="ub-days-lbl"><?php echo $days_until === 1 ? 'Day Away' : ($days_until === 0 ? 'Today!' : 'Days Away'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendar Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-calendar-days"></i>
                    <?php echo date('F Y', strtotime($month_start)); ?>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <a class="btn-ghost" href="e_holidays.php?active_date=<?php echo date('Y-m-d', strtotime($month_start.' -1 month')); ?>">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>
                    <a class="btn-ghost" href="e_holidays.php?active_date=<?php echo $today_ymd; ?>">
                        <i class="fa-solid fa-bullseye"></i> Today
                    </a>
                    <a class="btn-ghost" href="e_holidays.php?active_date=<?php echo date('Y-m-d', strtotime($month_start.' +1 month')); ?>">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <div class="card-body" style="padding:18px 24px;">
                <?php
                    $start_ts      = strtotime($month_start);
                    $weekday       = (int)date('N', $start_ts);
                    $first_cell_ts = strtotime('-'.($weekday - 1).' days', $start_ts);
                    $cells         = 42;
                ?>

                <div style="display:grid;grid-template-columns:1.3fr 0.9fr;gap:18px;align-items:start;">
                    <div>
                        <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;">
                            <div style="color:var(--rm);font-size:16px;">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#6366f1;margin-right:8px;"></span> Holiday dates
                            </div>
                            <div style="color:var(--rm);font-size:14.5px;">
                                Selected: <strong style="color:var(--rt);"><?php echo htmlspecialchars($active_date); ?></strong>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:10px;">
                            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow): ?>
                                <div style="font-size:15px;font-weight:800;color:var(--rm);text-align:center;letter-spacing:0.02em;"><?php echo $dow; ?></div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;">
                            <?php for($i=0;$i<$cells;$i++):
                                $cell_date  = date('Y-m-d', strtotime('+'.$i.' days', $first_cell_ts));
                                $in_month   = ((int)date('m', strtotime($cell_date))) === $active_month;
                                $is_selected= ($cell_date === $active_date);
                                $is_today   = ($cell_date === $today_ymd);
                                $is_holiday = isset($byDate[$cell_date]);
                                $hol_count  = $is_holiday ? count($byDate[$cell_date]) : 0;
                            ?>
                                <a href="e_holidays.php?active_date=<?php echo htmlspecialchars($cell_date); ?>" style="text-decoration:none;">
                                    <div class="day-inner" style="
                                        background:<?php echo $is_selected ? '#eef2ff' : ($is_today ? '#f0fdf4' : 'var(--rc)'); ?>;
                                        opacity:<?php echo $in_month ? 1 : 0.4; ?>;
                                        <?php echo $is_today ? 'border-color:#86efac;' : ''; ?>
                                        <?php echo $is_selected ? 'border-color:#c7d2fe;' : ''; ?>
                                    ">
                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:4px;">
                                            <div style="font-weight:900;font-size:16px;color:<?php echo $is_today ? '#15803d' : 'var(--rt)'; ?>;">
                                                <?php echo (int)date('j', strtotime($cell_date)); ?>
                                            </div>
                                            <?php if($is_holiday): ?>
                                                <div title="<?php echo (int)$hol_count; ?> holiday(s)" style="width:11px;height:11px;border-radius:50%;background:#6366f1;flex-shrink:0;"></div>
                                            <?php endif; ?>
                                        </div>

                                        <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;">
                                            <?php if($is_holiday): $n=0; foreach($byDate[$cell_date] as $hh): if($n>=2) break; $n++; ?>
                                                <div style="font-size:14px;color:var(--rm);font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                    <i class="fa-solid fa-asterisk" style="font-size:13px;color:#6366f1;margin-right:4px;"></i>
                                                    <?php echo htmlspecialchars($hh['holiday_name'] ?? ''); ?>
                                                </div>
                                            <?php endforeach; endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div>
                        <div class="card" style="margin:0;">
                            <div class="card-header" style="border-bottom:1px solid var(--rb);">
                                <div class="card-title" style="font-size:16px;">
                                    <i class="fa-solid fa-circle-info" style="color:var(--ac);"></i>
                                    <?php echo date('d M Y', strtotime($active_date)); ?>
                                </div>
                                <div style="font-size:16px;color:var(--rm);">
                                    <?php echo isset($byDate[$active_date]) ? count($byDate[$active_date]) : 0; ?> holiday(s)
                                </div>
                            </div>
                            <div style="padding:16px 18px;">
                                <?php if(empty($byDate[$active_date])): ?>
                                    <div class="table-empty" style="padding:26px 10px;">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                        <p>No holidays on this date</p>
                                    </div>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:12px;">
                                        <?php foreach($byDate[$active_date] as $h): ?>
                                            <div style="border:1px solid var(--rb);border-radius:var(--rs);padding:14px 12px;background:#fff;">
                                                <div style="font-size:16px;font-weight:800;color:var(--rt);">
                                                    <i class="fa-solid fa-flag" style="color:#6366f1;margin-right:8px;"></i>
                                                    <?php echo htmlspecialchars($h['holiday_name'] ?? ''); ?>
                                                </div>
                                                <?php if(!empty($h['holiday_desc'])): ?>
                                                    <div style="margin-top:6px;font-size:15px;color:var(--rm);">
                                                        <?php echo htmlspecialchars($h['holiday_desc']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div style="margin-top:8px;font-size:14px;color:var(--rd);display:flex;align-items:center;gap:5px;">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?php echo date('l, d M Y', strtotime($h['holiday_date'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Holidays Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-list"></i> All Holidays</div>
                <div style="font-size:15px;color:var(--rm);"><?php echo count($holidays); ?> entries</div>
            </div>

            <?php if(empty($holidays)): ?>
                <div class="table-empty">
                    <i class="fa-solid fa-calendar-xmark"></i>
                    <p>No holidays found</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Holiday</th>
                                <th>Description</th>
                                <th style="width:160px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($holidays as $i => $h):
                            $hd  = parseDate($h['holiday_date'] ?? null);
                            $past= $hd && $hd < $today_ymd;
                            $tod = $hd === $today_ymd;
                        ?>
                            <tr style="<?php echo $tod ? 'background:linear-gradient(90deg,#f0fff4,#fff);' : ($past ? 'color:var(--rd);background:rgba(241,245,249,0.55);' : ''); ?>">
                                <td style="font-size:15px;color:var(--rd);font-family:'JetBrains Mono',monospace;"><?php echo (int)($h['holiday_id'] ?? ($i+1)); ?></td>
                                <td>
                                    <div style="font-weight:700;font-size:15px;color:var(--rt);display:flex;align-items:center;gap:7px;">
                                        <?php if($tod): ?>
                                            <span style="background:#dcfce7;color:#15803d;font-size:12px;font-weight:800;border-radius:20px;padding:2px 8px;letter-spacing:.04em;">TODAY</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($h['holiday_name'] ?? ''); ?>
                                    </div>
                                </td>
                                <td style="font-size:15px;color:var(--rm);"><?php echo htmlspecialchars($h['holiday_desc'] ?? '—'); ?></td>
                                <td>
                                    <div style="font-weight:600;font-size:15px;color:var(--rt);"><?php echo $hd ? date('d M Y', strtotime($hd)) : '—'; ?></div>
                                    <div style="font-size:14px;color:var(--rd);"><?php echo $hd ? date('l', strtotime($hd)) : ''; ?></div>
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
        <strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; All rights reserved.
    </footer>
</div><!-- /main -->
</body>
</html>