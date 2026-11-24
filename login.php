<?php
session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
$conn = db();

$error       = '';
$posted_role = $_POST['role'] ?? 'employee';
$posted_id   = $_POST['identifier'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($posted_id);
    $password   = trim($_POST['password'] ?? '');
    $role       = trim($posted_role);
    if (!$identifier || !$password) {
        $error = 'Please enter both your ID / email and password.';
    } else {
        $hashed = sha1($password);
        if ($role === 'admin') {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE (admin_email=? OR admin_code=?) AND admin_password=? LIMIT 1");
            $admin = null;
            if ($stmt !== false) {
                $stmt->bind_param("sss", $identifier, $identifier, $hashed);
                $stmt->execute();
                $admin = $stmt->get_result()?->fetch_assoc();
                $stmt->close();
            } else {
                $error = 'DB prepare failed for admin login.';
            }
            if ($admin) {
                $_SESSION['admin_id']   = $admin['admin_id'];
                $_SESSION['admin_code'] = $admin['admin_code'];
                $_SESSION['admin_name'] = $admin['admin_name'];
                $_SESSION['admin_role'] = $admin['admin_role'];
                $_SESSION['Login_Type'] = 'admin';
                header('Location: a_dashboard.php'); exit;
            } else {
                $error = 'Invalid admin credentials. Please check your details and try again.';
            }
        } else {
            // Employee login
            $stmt = $conn->prepare("SELECT * FROM employees WHERE (email=? OR emp_code=?) AND password=? LIMIT 1");
            $employee = null;
            if ($stmt !== false) {
                $stmt->bind_param("sss", $identifier, $identifier, $hashed);
                $stmt->execute();
                $employee = $stmt->get_result()?->fetch_assoc();
                $stmt->close();
            }

            if ($employee) {
                $_SESSION['emp_code']   = $employee['emp_code'];
                $_SESSION['emp_id']     = $employee['emp_id'];
                $_SESSION['emp_name']   = $employee['first_name'].' '.$employee['last_name'];
                $_SESSION['Login_Type'] = 'employee';
                header('Location: e_dashboard.php'); exit;
            }

            // Clear failure reasons for employee logins
            $empExists = false;
            $pwMatches = false;
            try {
                $s1 = $conn->prepare("SELECT COUNT(*) c FROM employees WHERE email=? OR emp_code=?");
                if ($s1 !== false) {
                    $s1->bind_param("ss", $identifier, $identifier);
                    $s1->execute();
                    $row = $s1->get_result()?->fetch_assoc();
                    $empExists = ((int)($row['c'] ?? 0)) > 0;
                    $s1->close();
                }

                if ($empExists) {
                    $s2 = $conn->prepare("SELECT COUNT(*) c FROM employees WHERE (email=? OR emp_code=?) AND password=?");
                    if ($s2 !== false) {
                        $s2->bind_param("sss", $identifier, $identifier, $hashed);
                        $s2->execute();
                        $row2 = $s2->get_result()?->fetch_assoc();
                        $pwMatches = ((int)($row2['c'] ?? 0)) > 0;
                        $s2->close();
                    }
                }
            } catch (\Throwable $e) {
                // keep defaults
            }

            if (!$empExists) {
                $error = 'Employee code / email not found in database. (Check if your test rows exist in p_db.sqlite.)';
            } elseif (!$pwMatches) {
                $error = 'Employee exists but password does not match this system. Expected SHA1 hashing for stored password.';
            } else {
                $error = 'Invalid credentials. Please check your employee ID / email and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — Tiptop Apparel Payroll</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    
:root{--bg:#060f20;--panel-dark:rgba(10,22,46,0.7);--border:rgba(78,168,222,0.13);--border-hi:rgba(78,168,222,0.42);--accent:#2f80ed;--accent2:#27ae60;--glow:rgba(47,128,237,0.22);--text:#e8f4fd;--muted:#7a9ab8;--dim:#3c546e;--danger:#e53e3e;--r:12px;--r-sm:8px;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}html,body{height:100%;}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;font-size:312.5%;}


.bg-wrap{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
.orb{position:absolute;border-radius:50%;filter:blur(90px);animation:drift 14s ease-in-out infinite alternate;}
.orb1{width:600px;height:600px;top:-180px;left:-120px;background:rgba(47,128,237,0.18);animation-delay:0s;}
.orb2{width:420px;height:420px;bottom:-100px;right:-80px;background:rgba(39,174,96,0.13);animation-delay:-5s;}
.orb3{width:300px;height:300px;top:35%;right:8%;background:rgba(47,128,237,0.09);animation-delay:-9s;}
@keyframes drift{0%{transform:translate(0,0) scale(1);}50%{transform:translate(25px,18px) scale(1.04);}100%{transform:translate(-18px,32px) scale(0.96);}}
.bg-grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(78,168,222,0.035) 1px,transparent 1px),linear-gradient(90deg,rgba(78,168,222,0.035) 1px,transparent 1px);background-size:48px 48px;}
.shell{position:relative;z-index:1;width:100%;min-height:100vh;display:flex;}
.left-panel{flex:1;padding:60px 64px;display:flex;flex-direction:column;justify-content:center;}
.brand-row{display:flex;align-items:center;gap:12px;margin-bottom:60px;}
.brand-logo{width:46px;height:46px;border-radius:11px;overflow:hidden;border:1px solid rgba(78,168,222,0.35);flex-shrink:0;}
.brand-logo img{width:100%;height:100%;object-fit:contain;background:#fff;padding:3px;}
.brand-logo-fb{width:46px;height:46px;border-radius:11px;background:linear-gradient(135deg,#0b1e3d,#1a3a6e);border:1px solid rgba(78,168,222,0.35);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#fff;}
.brand-name{font-size:21px;font-weight:800;color:var(--text);}
.brand-name em{font-style:normal;color:var(--accent);}
.lp-headline{font-size:63px;font-weight:900;line-height:1.08;margin-bottom:20px;letter-spacing:-0.5px;}
.lp-headline em{font-style:normal;color:var(--accent);}
.lp-sub{font-size:19px;color:var(--muted);line-height:1.75;max-width:430px;margin-bottom:52px;}
.feature-list{display:flex;flex-direction:column;gap:16px;}
.fi{display:flex;align-items:center;gap:14px;padding:14px 16px;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:var(--r-sm);transition:border-color 0.2s;}
.fi:hover{border-color:rgba(78,168,222,0.25);}
.fi-icon{width:38px;height:38px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:19px;}
.fib{background:rgba(47,128,237,0.14);color:var(--accent);border:1px solid rgba(47,128,237,0.22);}
.fig{background:rgba(39,174,96,0.14);color:var(--accent2);border:1px solid rgba(39,174,96,0.22);}
.fip{background:rgba(124,58,237,0.14);color:#a78bfa;border:1px solid rgba(124,58,237,0.22);}
.fi-text{font-size:17px;font-weight:500;color:var(--muted);}
.right-panel{width:500px;flex-shrink:0;min-height:100vh;background:var(--panel-dark);backdrop-filter:blur(24px);border-left:1px solid var(--border);display:flex;align-items:center;justify-content:center;padding:40px 44px;}
.login-card{width:100%;max-width:380px;}
.lc-welcome{font-size:33px;font-weight:800;color:var(--text);margin-bottom:6px;}
.lc-sub{font-size:18px;color:var(--muted);margin-bottom:28px;}
.role-tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px;background:rgba(0,0,0,0.25);border:1px solid var(--border);border-radius:var(--r-sm);padding:4px;margin-bottom:24px;}
.rt-btn{padding:9px;border:none;border-radius:6px;background:transparent;color:var(--muted);font-family:'Outfit',sans-serif;font-size:16px;font-weight:600;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:7px;}
.rt-btn.active{background:var(--accent);color:#fff;box-shadow:0 2px 12px rgba(47,128,237,0.35);}
.alert{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:var(--r-sm);font-size:16px;font-weight:500;margin-bottom:18px;}
.alert-error{background:rgba(229,57,53,0.12);border:1px solid rgba(229,57,53,0.3);color:#fc8181;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:14px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:0.06em;}
.input-wrap{position:relative;}
.form-input{width:100%;padding:12px 14px 12px 42px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--r-sm);color:var(--text);font-family:'Outfit',sans-serif;font-size:18px;outline:none;transition:border-color .2s,box-shadow .2s;}
.form-input:focus{border-color:var(--border-hi);box-shadow:0 0 0 3px var(--glow);}
.form-input.err{border-color:rgba(229,57,53,0.5);}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--dim);font-size:18px;pointer-events:none;}
.pw-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--dim);font-size:16px;padding:4px;}
.btn-submit{width:100%;padding:14px;margin-top:6px;background:var(--accent);color:#fff;border:none;border-radius:var(--r-sm);font-family:'Outfit',sans-serif;font-size:19px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all .2s;box-shadow:0 4px 18px rgba(47,128,237,0.3);}
.btn-submit:hover{background:#2568c8;}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;}
.spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none;}
@keyframes spin{to{transform:rotate(360deg);}}
.lc-footer{margin-top:24px;font-size:15px;color:var(--dim);text-align:center;line-height:1.6;}
.punch-link{margin-top:16px;text-align:center;}
.punch-link a{display:inline-flex;align-items:center;gap:7px;font-size:16px;color:var(--muted);text-decoration:none;padding:8px 14px;border:1px solid var(--border);border-radius:var(--r-sm);transition:all .18s;}
.punch-link a:hover{color:var(--text);border-color:var(--border-hi);}
@media(max-width:1100px){.left-panel{display:none;}.right-panel{width:100%;border-left:none;}}
</style>
</head>
<body>
<div class="bg-wrap"><div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div></div>
<div class="bg-grid"></div>
<div class="shell">
    <div class="left-panel">
        <div class="brand-row">
            <?php if(file_exists('Logo.jpeg')): ?>
            <div class="brand-logo"><img src="Logo.jpeg" alt="Tiptop Apparel"></div>
            <?php else: ?>
            <div class="brand-logo-fb">TA</div>
            <?php endif; ?>
            <div class="brand-name">Tiptop <em>Apparel</em></div>
        </div>
        <div class="lp-headline">Smart<br><em>Payroll</em><br>Management</div>
        <div class="lp-sub">A complete payroll system — manage employees, attendance, leaves and salary calculations all in one place.</div>
        <div class="feature-list">
            <div class="fi"><div class="fi-icon fib"><i class="fa-solid fa-money-bill-wave"></i></div><div class="fi-text">Automated salary calculation with EPF, ETF and OT</div></div>

            <div class="fi"><div class="fi-icon fip"><i class="fa-solid fa-plane-departure"></i></div><div class="fi-text">Apply for leave and track approval status in real time</div></div>
        </div>
    </div>
    <div class="right-panel">
        <div class="login-card">
            <div class="lc-welcome">Welcome back</div>
            <div class="lc-sub">Sign in to your Payroll dashboard</div>
            <div class="role-tabs">
                <button type="button" class="rt-btn <?php echo ($posted_role!=='admin')?'active':''; ?>" id="btn-emp" onclick="setRole('employee')"><i class="fa-solid fa-user"></i> Employee</button>
                <button type="button" class="rt-btn <?php echo ($posted_role==='admin')?'active':''; ?>" id="btn-adm" onclick="setRole('admin')"><i class="fa-solid fa-shield-halved"></i> Admin</button>
            </div>
            <?php if($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>
            <form method="post" id="login-form" autocomplete="on" onsubmit="onSubmit(event)">

                <input type="hidden" name="role" id="role-val" value="<?php echo ($posted_role==='admin')?'admin':'employee'; ?>">
                <div class="form-group">
                    <label id="id-label">Employee Code or Email</label>
                    <div class="input-wrap">
                        <input type="text" name="identifier" id="identifier" class="form-input <?php echo $error?'err':''; ?>" placeholder="e.g. EMP001 or you@tiptop.com" value="<?php echo htmlspecialchars($posted_id); ?>" autocomplete="username" required>
                        <i class="fa-regular fa-id-badge input-icon" id="id-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <input type="password" name="password" id="pw-field" class="form-input <?php echo $error?'err':''; ?>" placeholder="Enter your password" autocomplete="current-password" style="padding-right:42px;" required>
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="pw-eye" onclick="togglePw()"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;margin:0 0 16px;">
                    <input type="checkbox" id="remember-me" style="width:18px;height:18px;accent-color: var(--accent);">
                    <label for="remember-me" style="font-size:15px;color:var(--muted);text-transform:none;letter-spacing:0;font-weight:600;cursor:pointer;">
                        Remember username & password
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="btn-submit">
                    <div class="spinner" id="spin"></div>
                    <i class="fa-solid fa-arrow-right-to-bracket" id="submit-icon"></i>
                    <span id="submit-label">Sign In</span>
                </button>
            </form>
            <div class="lc-footer">&copy; <?php echo date('Y'); ?> Tiptop Apparel &nbsp;·&nbsp; Payroll Management System</div>
        </div>
    </div>
</div>
<script>
let activeRole = document.getElementById('role-val').value||'employee';
function setRole(r){

    activeRole=r; document.getElementById('role-val').value=r;
    document.getElementById('btn-emp').classList.toggle('active',r==='employee');
    document.getElementById('btn-adm').classList.toggle('active',r==='admin');
    const lbl=document.getElementById('id-label'),icon=document.getElementById('id-icon'),inp=document.getElementById('identifier');
    if(r==='admin'){lbl.textContent='Admin Code or Email';icon.className='fa-solid fa-shield-halved input-icon';inp.placeholder='e.g. A001 or admin@tiptop.com';}
    else{lbl.textContent='Employee Code or Email';icon.className='fa-regular fa-id-badge input-icon';inp.placeholder='e.g. EMP001 or you@tiptop.com';}
}
function togglePw(){const f=document.getElementById('pw-field'),i=document.querySelector('.pw-eye i');f.type=f.type==='password'?'text':'password';i.className=f.type==='password'?'fa-regular fa-eye':'fa-regular fa-eye-slash';}

function getRemembered(){

    try{
        const raw=localStorage.getItem('tp_remember_login');
        return raw?JSON.parse(raw):null;
    }catch(e){return null;}
}

function applyRemembered(){
    const data=getRemembered();
    if(!data) return;
    const id=document.getElementById('identifier');
    const pw=document.getElementById('pw-field');
    const cb=document.getElementById('remember-me');
    if(id) id.value=data.identifier||'';
    if(pw) pw.value=data.password||'';
    if(cb) cb.checked=true;
}

function persistRemembered(){
    const cb=document.getElementById('remember-me');
    if(!cb) return;
    if(!cb.checked){
        try{localStorage.removeItem('tp_remember_login');}catch(e){}
        return;
    }
    const id=document.getElementById('identifier');
    const pw=document.getElementById('pw-field');
    if(!id || !pw) return;
    try{
        localStorage.setItem('tp_remember_login', JSON.stringify({identifier:id.value||'', password:pw.value||''}));
    }catch(e){}
}

function onSubmit(e){
    // Persist remembered fields (client-side) before navigation.
    persistRemembered();

    const btn=document.getElementById('btn-submit');
    document.getElementById('spin').style.display='block';
    document.getElementById('submit-icon').style.display='none';
    document.getElementById('submit-label').textContent='Signing in…';
    btn.disabled=true;
}

window.addEventListener('DOMContentLoaded',()=>{
    setRole(document.getElementById('role-val').value||'employee');
    applyRemembered();
});

</script>
</body></html>