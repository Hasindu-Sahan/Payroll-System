<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
if($conn->connect_error)die('DB Error: '.$conn->connect_error);
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
if(!$admin)die('Admin not found.');
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];
$pw_msg=''; $edit_msg='';

// Handle Edit Admin Details
if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='edit_details'){
    $name=trim($_POST['admin_name']??''); $email=trim($_POST['admin_email']??'');
    if(!$name||!$email){$edit_msg='empty';}
    else{
        $upd=$conn->prepare("UPDATE admin SET admin_name=?,admin_email=? WHERE admin_code=?");
        $upd->bind_param("sss",$name,$email,$admin_code);
        if($upd->execute()){$edit_msg='success';$s2=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s2->bind_param("s",$admin_code);$s2->execute();$admin=$s2->get_result()->fetch_assoc();$s2->close();}
        else{$edit_msg='db_error';} $upd->close();
    }
}

// Handle Password Reset
if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='reset_password'){
    $cur=trim($_POST['current_password']??'');$new=trim($_POST['new_password']??'');$con=trim($_POST['confirm_password']??'');
    $ps=$conn->prepare("SELECT admin_password FROM admin WHERE admin_code=?");$ps->bind_param("s",$admin_code);$ps->execute();$stored=$ps->get_result()->fetch_assoc()['admin_password'];$ps->close();
    if(sha1($cur)!==$stored)$pw_msg='wrong_current';
    elseif(strlen($new)<6)$pw_msg='too_short';
    elseif($new!==$con)$pw_msg='mismatch';
    else{$nh=sha1($new);$pu=$conn->prepare("UPDATE admin SET admin_password=? WHERE admin_code=?");$pu->bind_param("ss",$nh,$admin_code);$pw_msg=$pu->execute()?'pw_success':'pw_error';$pu->close();}
}

$page_title='Admin Profile';$active_page='Profile';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Profile</h1><p>Edit your admin details &amp; change password</p></div>
        <div class="topbar-actions"><span class="tb-date"><?php echo date('D, d M Y');?></span></div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div><div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Profile</div><h2>Admin Profile</h2></div>
        </div>
        <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;">
            <!-- Left Avatar -->
            <div class="card" style="margin-bottom:0;">
                <div style="padding:32px 24px 20px;text-align:center;border-bottom:1px solid var(--right-border);">
                    <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:31.25px;font-weight:800;color:#fff;margin:0 auto 16px;box-shadow:0 0 0 5px rgba(99,102,241,0.18);"><?php echo admin_initials($admin['admin_name']);?></div>
                    <div style="font-size:20px;font-weight:800;color:var(--right-text);"><?php echo htmlspecialchars($admin['admin_name']);?></div>
                    <div style="margin-top:8px;">
                        <span style="background:#eef2ff;color:#4338ca;padding:5px 14px;border-radius:20px;font-size:14.375px;font-weight:700;">
                            <i class="fa-solid fa-shield-halved" style="margin-right:5px;"></i>
                            <?php echo match($admin['admin_role']){
                                'owner'=>'Owner',
                                'hr_manager'=>'HR Manager',
                                'payroll_manager'=>'Payroll Manager',
                                default=>'Administrator'
                            };?>
                        </span>
                    </div>
                </div>
                <div style="padding:18px 22px;">
                    <div style="display:flex;flex-direction:column;gap:11px;font-size:15px;">
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--right-muted);">Code</span>
                            <span style="font-weight:600;font-family:'JetBrains Mono',monospace;font-size:15px;"><?php echo $admin['admin_code'];?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--right-muted);">Email</span>
                            <span style="font-weight:500;font-size:14.375px;color:var(--right-text);"><?php echo htmlspecialchars($admin['admin_email']);?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--right-muted);">Since</span>
                            <span style="font-weight:600;font-size:15px;"><?php echo date('d M Y',strtotime($admin['admin_time']));?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Tabs -->
            <div class="card" style="margin-bottom:0;">
                <div style="display:flex;border-bottom:1px solid var(--right-border);">
                    <button onclick="gotoTab('edit-tab','edit-panel')" id="edit-tab" class="inner-tab" style="padding:16px 24px;border:none;background:none;font-family:'Outfit',sans-serif;font-size:16.25px;font-weight:600;color:var(--right-muted);cursor:pointer;border-bottom:2px solid transparent;transition:all .18s;">
                        <i class="fa-solid fa-pen-to-square" style="margin-right:7px;"></i>Edit Details
                    </button>
                    <button onclick="gotoTab('pw-tab','pw-panel')" id="pw-tab" class="inner-tab" style="padding:16px 24px;border:none;background:none;font-family:'Outfit',sans-serif;font-size:16.25px;font-weight:600;color:var(--right-muted);cursor:pointer;border-bottom:2px solid transparent;transition:all .18s;">
                        <i class="fa-solid fa-lock" style="margin-right:7px;"></i>Change Password
                    </button>
                </div>
                <!-- Edit Details -->
                <div id="edit-panel" class="inner-panel" style="display:none;padding:32px;">
                    <?php if($edit_msg==='success'):?>
                    <div style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:9px;">
                        <i class="fa-solid fa-circle-check"></i>Details updated successfully!
                    </div>
                    <?php endif;?>
                    <?php if($edit_msg==='empty'||$edit_msg==='db_error'):?>
                    <div style="background:#fff5f5;color:#b91c1c;border:1px solid #fecaca;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:9px;">
                        <i class="fa-solid fa-circle-xmark"></i>
                        <?php echo $edit_msg==='empty'?'Name and email are required.':'Update failed. Try again.';?>
                    </div>
                    <?php endif;?>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_details">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($admin['admin_name']);?>" required>
                            </div>
                            <div class="form-group">
                                <label>Admin Code <span style="font-size:13.125px;color:var(--right-dim);font-weight:400;">(read-only)</span></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['admin_code']);?>" disabled style="background:#f1f5f9;cursor:not-allowed;color:var(--right-dim);">
                            </div>
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Email Address</label>
                                <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin['admin_email']);?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </form>
                </div>
                <!-- Password -->
                <div id="pw-panel" class="inner-panel" style="display:none;padding:32px;">
                    <?php if($pw_msg==='wrong_current'):?>
                    <div style="background:#fff5f5;color:#b91c1c;border:1px solid #fecaca;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:9px;">
                        <i class="fa-solid fa-circle-xmark"></i>Current password is incorrect.
                    </div>
                    <?php endif;?>
                    <?php if($pw_msg==='mismatch'):?>
                    <div style="background:#fff5f5;color:#b91c1c;border:1px solid #fecaca;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:9px;">
                        <i class="fa-solid fa-circle-xmark"></i>Passwords do not match.
                    </div>
                    <?php endif;?>
                    <?php if($pw_msg==='too_short'):?>
                    <div style="background:#fff5f5;color:#b91c1c;border:1px solid #fecaca;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:9px;">
                        <i class="fa-solid fa-circle-xmark"></i>Password must be at least 6 characters.
                    </div>
                    <?php endif;?>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <div style="display:flex;flex-direction:column;gap:18px;margin-bottom:22px;">
                            <div class="form-group">
                                <label>Current Password</label>
                                <div style="position:relative;">
                                    <input type="password" name="current_password" id="inp-cur" class="form-control" placeholder="Enter current password" required>
                                    <button type="button" onclick="toggleEye('inp-cur',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--right-dim);font-size:17.5px;">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <div style="position:relative;">
                                    <input type="password" name="new_password" id="inp-new" class="form-control" placeholder="Enter new password" oninput="evalStrength(this.value)" required>
                                    <button type="button" onclick="toggleEye('inp-new',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--right-dim);font-size:17.5px;">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div style="height:5px;border-radius:5px;background:#e2e8f2;margin-top:9px;overflow:hidden;">
                                    <div id="s-fill" style="height:100%;width:0;border-radius:5px;transition:all .3s;"></div>
                                </div>
                                <div id="s-text" style="font-size:13.75px;color:var(--right-dim);margin-top:5px;">Enter a password to check strength</div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="inp-con" class="form-control" placeholder="Re-enter new password" oninput="evalMatch()" required>
                                    <button type="button" onclick="toggleEye('inp-con',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--right-dim);font-size:17.5px;">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div id="match-msg" style="display:none;margin-top:7px;font-size:14.375px;"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock-open"></i> Update Password</button>
                    </form>
                    <div style="margin-top:22px;padding:14px 18px;background:#f8fafd;border-radius:var(--r-sm);border:1px solid var(--right-border);">
                        <div style="font-size:14.375px;font-weight:700;color:var(--right-text);margin-bottom:7px;">
                            <i class="fa-solid fa-shield-halved" style="margin-right:7px;color:var(--accent);"></i>Security Tips
                        </div>
                        <ul style="padding-left:20px;font-size:14.375px;color:var(--right-muted);display:flex;flex-direction:column;gap:4px;">
                            <li>Use uppercase, lowercase, numbers and symbols</li>
                            <li>Avoid using your name or admin code</li>
                            <li>Change your password every 3–6 months</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
<?php if($pw_msg==='pw_success'):?>
<div class="toast toast-success" id="toast"><i class="fa-solid fa-circle-check"></i> Password changed successfully!</div>
<?php endif;?>
<style>
.inner-tab.active{color:var(--accent)!important;border-bottom-color:var(--accent)!important;}
.toast {
    position:fixed;
    top:80px;
    right:24px;
    padding:14px 20px;
    border-radius:var(--r-sm);
    font-size:15px;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:10px;
    z-index:9999;
    box-shadow:0 6px 20px rgba(0,0,0,0.15);
    transition:opacity 0.3s;
}
.toast-success {
    background:#f0fdf4;
    color:#15803d;
    border:1px solid #bbf7d0;
}
</style>
<script>
function gotoTab(tid,pid){
    document.querySelectorAll('.inner-tab').forEach(t=>{
        t.classList.remove('active');
        t.style.color='';
        t.style.borderBottomColor='transparent';
    });
    document.querySelectorAll('.inner-panel').forEach(p=>p.style.display='none');
    document.getElementById(tid).classList.add('active');
    document.getElementById(tid).style.color='var(--accent)';
    document.getElementById(tid).style.borderBottomColor='var(--accent)';
    document.getElementById(pid).style.display='block';
}
function toggleEye(id,btn){
    const i=document.getElementById(id),ic=btn.querySelector('i');
    if(i.type==='password'){
        i.type='text';
        ic.className='fa-regular fa-eye-slash';
    }else{
        i.type='password';
        ic.className='fa-regular fa-eye';
    }
}
function evalStrength(v){
    const f=document.getElementById('s-fill'),t=document.getElementById('s-text');
    const c={l:v.length>=6,u:/[A-Z]/.test(v),n:/[0-9]/.test(v),s:/[^A-Za-z0-9]/.test(v)};
    const s=Object.values(c).filter(Boolean).length;
    const lv=[
        {p:'0%',c:'',l:''},
        {p:'25%',c:'#e53e3e',l:'Very Weak'},
        {p:'50%',c:'#dd6b20',l:'Weak'},
        {p:'75%',c:'#d4a017',l:'Moderate'},
        {p:'100%',c:'#27ae60',l:'Strong'}
    ];
    const l=lv[s]||lv[0];
    f.style.width=l.p;
    f.style.background=l.c;
    t.textContent=l.l;
    t.style.color=l.c;
}
function evalMatch(){
    const n=document.getElementById('inp-new').value,c=document.getElementById('inp-con').value,m=document.getElementById('match-msg');
    if(!c){m.style.display='none';return;}
    m.style.display='block';
    m.innerHTML=n===c?
        '<i class="fa-solid fa-circle-check" style="color:#27ae60;margin-right:6px;"></i><span style="color:#27ae60;">Passwords match</span>':
        '<i class="fa-solid fa-circle-xmark" style="color:#e53e3e;margin-right:6px;"></i><span style="color:#e53e3e;">Passwords do not match</span>';
}
document.addEventListener('DOMContentLoaded',()=>{
    <?php if($pw_msg):?>gotoTab('pw-tab','pw-panel');<?php else:?>gotoTab('edit-tab','edit-panel');<?php endif;?>
    const t=document.getElementById('toast');
    if(t){
        setTimeout(()=>{t.style.opacity='0';},3700);
        setTimeout(()=>t.remove(),4200);
    }
});
</script>
</body></html>