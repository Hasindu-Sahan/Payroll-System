<?php
// Shared Employee Layout (sidebar + topbar icon)
// Expected variables from each employee page:
//   - $emp : employee row (associative array)
//   - $page_title (optional)
//   - $topbar_title (optional)
//   - $topbar_subtitle (optional)
//   - $active_nav (optional): one of Dashboard | My Leaves | Holidays | Payments | Profile
//   - $employee_content (optional): HTML content to inject

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['Login_Type']) || $_SESSION['Login_Type'] !== 'employee') {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Colombo');

if (!isset($emp) || !is_array($emp)) {
    die('Employee layout error: $emp not provided');
}

if (!function_exists('employee_initials')) {
    function employee_initials($f, $l) { return strtoupper(substr((string)$f,0,1) . substr((string)$l,0,1)); }
}

$script = basename($_SERVER['PHP_SELF']);

$activeMap = [
    'e_dashboard.php' => 'Dashboard',
    'e_leaves.php'    => 'My Leaves',
    'e_holidays.php'  => 'Holidays',
    'e_payments.php'  => 'Payments',
    'e_profile.php'   => 'Profile',
];

$active_nav = $active_nav ?? ($activeMap[$script] ?? 'Dashboard');

$page_title     = $page_title ?? 'Employee';
$topbar_title   = $topbar_title ?? 'Dashboard';
$topbar_subtitle= $topbar_subtitle ?? null;

$tabs = [
    'e_dashboard.php' => ['Dashboard', 'fa-solid fa-gauge-high'],
    'e_leaves.php'    => ['My Leaves', 'fa-solid fa-plane-departure'],
    'e_holidays.php'  => ['Holidays', 'fa-solid fa-calendar-days'],
    'e_payments.php'  => ['Payments', 'fa-solid fa-money-bill-wave'],
];

// Support nested profile tabs: ?tab=details (default) | password
$profile_tab = $_GET['tab'] ?? 'details';
$profile_tab = $profile_tab === 'password' ? 'password' : 'details';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($page_title); ?> — Tiptop Apparel Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --sbg:#0b1e3d;--sdk:#071529;--sbw:240px;--bside:rgba(255,255,255,0.07);
  --ts:#8a9ab5;--tshi:#fff;--tds:#3a4a5e;--ac:#2f80ed;
  --rbg:#f0f4f8;--rc:#fff;--rb:#e1e8f0;--rt:#1a2740;--rm:#64748b;
  --rd:#94a3b8;--danger:#e53e3e;--warn:#dd6b20;--success:#16a34a;
  --r:10px;--rs:7px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:'Outfit',sans-serif;display:flex;min-height:100vh;background:var(--rbg);font-size:156.25%;}

/* Sidebar (left) */
.sidebar{width:var(--sbw);background:var(--sbg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;border-right:1px solid var(--bside);}
.sb-brand{height:60px;background:var(--sdk);display:flex;align-items:center;gap:10px;padding:0 18px;border-bottom:1px solid var(--bside);flex-shrink:0;}
.sb-logo-fb{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,#2f80ed,#27ae60);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;color:#fff;}
.sb-company{font-weight:800;font-size:19px;color:#fff;}
.sb-company span{color:#60a5fa;}
.sb-user{padding:14px 18px;border-bottom:1px solid var(--bside);display:flex;align-items:center;gap:10px;flex-shrink:0;}
.sb-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#2f80ed,#0ea5e9);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:#fff;flex-shrink:0;box-shadow:0 0 0 3px rgba(47,128,237,.2);}
.sb-name{font-size:16px;font-weight:700;color:var(--tshi);line-height:1.3;}
.sb-code{font-size:14px;color:#60a5fa;margin-top:1px;font-family:'JetBrains+Mono',monospace;}
.sb-nav{flex:1;padding:14px 10px;overflow-y:auto;}
.nav-sec{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--tds);padding:0 8px;margin:12px 0 5px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:var(--rs);color:var(--ts);font-size:16px;font-weight:500;text-decoration:none;margin-bottom:2px;transition:all .18s;border:1px solid transparent;}
.nav-link:hover{background:rgba(255,255,255,.05);color:var(--tshi);}
.nav-link.active{background:rgba(47,128,237,.18);color:#93c5fd;border-color:rgba(47,128,237,.3);}
.nav-link i{width:16px;text-align:center;font-size:16px;}
.sb-ft{padding:12px;border-top:1px solid var(--bside);flex-shrink:0;}
.sb-ft a{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:var(--rs);color:#fc8181;font-size:16px;font-weight:500;text-decoration:none;transition:background .18s;}
.sb-ft a:hover{background:rgba(229,57,53,.1);}

/* Main / Topbar */
.main{margin-left:var(--sbw);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:60px;background:var(--rc);border-bottom:1px solid var(--rb);display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:100;flex-shrink:0;}
.topbar h1{font-size:21px;font-weight:700;color:var(--rt);}
.topbar p{font-size:15px;color:var(--rm);margin-top:1px;}
.topbar-actions{display:flex;gap:10px;align-items:center;}
.tb-icon{width:34px;height:34px;border-radius:var(--rs);background:var(--rbg);border:1px solid var(--rb);color:var(--rm);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s;font-size:19px;text-decoration:none;}
.tb-icon:hover{background:#e1e8f0;color:var(--rt);}
.page{flex:1;padding:26px 32px;max-width:1400px;margin:0 auto;width:100%;}
footer{flex-shrink:0;padding:14px 32px;text-align:center;font-size:14px;color:var(--rm);border-top:1px solid var(--rb);}
footer strong{color:var(--rm);}

@media(max-width:768px){.sidebar{transform:translateX(-100%);} .main{margin-left:0;}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-logo-fb">TA</div>
        <div class="sb-company">Tiptop <span>Apparel</span></div>
    </div>
    <div class="sb-user">
        <div class="sb-av"><?php echo employee_initials($emp['first_name'] ?? '', $emp['last_name'] ?? ''); ?></div>
        <div>
            <div class="sb-name"><?php echo htmlspecialchars(trim(($emp['first_name'] ?? '').' '.($emp['last_name'] ?? ''))); ?></div>
            <div class="sb-code"><?php echo htmlspecialchars($emp['emp_code'] ?? ''); ?></div>
        </div>
    </div>

    <div class="sb-nav">
        <div class="nav-sec">Main</div>
        <?php foreach ($tabs as $href => [$label, $icon]): ?>
            <a href="<?php echo $href; ?>" class="nav-link<?php echo ($active_nav === $label) ? ' active' : ''; ?>">
                <i class="<?php echo $icon; ?>"></i> <?php echo htmlspecialchars($label); ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-sec">Account</div>
        <a href="e_profile.php" class="nav-link<?php echo ($active_nav === 'Profile') ? ' active' : ''; ?>">
            <i class="fa-solid fa-circle-user"></i> Profile
        </a>
    </div>

    <div class="sb-ft">
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div>
            <h1><?php echo htmlspecialchars($topbar_title); ?></h1>
            <?php if (!empty($topbar_subtitle)): ?>
                <p><?php echo htmlspecialchars($topbar_subtitle); ?></p>
            <?php endif; ?>
        </div>
        <div class="topbar-actions">
            <a href="e_profile.php?tab=<?php echo htmlspecialchars($profile_tab); ?>" class="tb-icon" title="Profile">
                <i class="fa-solid fa-circle-user"></i>
            </a>
        </div>
    </header>

    <div class="page">
        <?php echo $employee_content ?? ''; ?>
    </div>

    <footer><strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Employee Portal</footer>
</div>

</body>
</html>

