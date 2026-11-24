<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
$conn = db();
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$admin_role=$admin['admin_role'];

// Access control: HR Manager can view leave settings; only A001 can approve/reject/delete.
$can_approve_leaves = ($admin_role === 'owner');
$can_manage_leave_actions = ($admin_code === 'A001');


$conn->query("ALTER TABLE leaves ADD COLUMN IF NOT EXISTS approved_by VARCHAR(255) DEFAULT NULL");
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];
$msg='';$msg_type='';

// Handle approve/reject/delete actions
if($can_manage_leave_actions && isset($_GET['approve'])){
    $id=(int)$_GET['approve'];
    $st=$conn->prepare("UPDATE leaves SET leave_status='approved', reviewed_by=?, reviewed_date=NOW() WHERE leave_id=?");
    $st->bind_param("si",$admin_code,$id);
    $st->execute();
    $st->close();
    $msg='Leave approved.';
    $msg_type='success';
    $pending_leaves=max(0,$pending_leaves-1);
}

if($can_manage_leave_actions && isset($_GET['reject'])) {
    $id=(int)$_GET['reject'];
    $st=$conn->prepare("UPDATE leaves SET leave_status='rejected', reviewed_by=?, reviewed_date=NOW() WHERE leave_id=?");
    $st->bind_param("si",$admin_code,$id);
    $st->execute();
    $st->close();
    $msg='Leave rejected.';
    $msg_type='error';
    $pending_leaves=max(0,$pending_leaves-1);
}


if($can_manage_leave_actions && isset($_GET['delete'])) {
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM leaves WHERE leave_id=$id");
    $msg='Leave deleted.';
    $msg_type='success';
}


// Fetch leave limits
$ll = $conn->query("SELECT * FROM leavelimits LIMIT 1")->fetch_assoc();
$annual = (int)($ll['annual_allowed_leaves']??14);
$casual = (int)($ll['casual_allowed_leaves']??7);
$deduct_day = (float)($ll['deduction_per_day']??2000);
$mgr_annual = (int)($ll['manager_annual_leaves']??24);
$mgr_deduct_day = (float)($ll['manager_deduction_per_day']??3000);

$filter=trim($_GET['filter']??'all');
$where_lv = match($filter){
    'pending' => "WHERE l.leave_status='pending'",
    'approved' => "WHERE l.leave_status='approved'",
    'rejected' => "WHERE l.leave_status='rejected'",
    default => ''
};

$leaves = $conn->query("
    SELECT l.*, e.first_name, e.last_name, e.designation, e.section 
    FROM leaves l 
    LEFT JOIN employees e ON e.emp_code = l.emp_code 
    $where_lv
    ORDER BY l.applied_date DESC
")->fetch_all(MYSQLI_ASSOC);

$total_all=(int)$conn->query("SELECT COUNT(*) c FROM leaves")->fetch_assoc()['c'];
$total_app=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='approved'")->fetch_assoc()['c'];
$total_rej=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='rejected'")->fetch_assoc()['c'];

function initials($f,$l){return strtoupper(substr($f,0,1).substr($l,0,1));}

// Block delete action for HR manager (Owner-only)
if(!$can_manage_leave_actions && isset($_GET['delete'])){
    header('Location: a_leavesMng.php?filter='.urlencode($filter));
    exit;
}

$page_title='Leaves Management';$active_page='Leaves Management';
require 'a_adminLayout.php';
?>

    <header class="topbar">
        <div class="topbar-left"><h1>Leaves Management</h1><p>Review, approve and manage employee leave requests</p></div>
        <div class="topbar-actions"><span class="tb-date"><?php echo date('D, d M Y');?></span></div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div><div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Leaves Management</div><h2>Leave Requests</h2></div>
        </div>
        <?php if($msg):?><div class="toast <?php echo $msg_type==='success'?'toast-success':'toast-error';?>" id="toast"><i class="fa-solid <?php echo $msg_type==='success'?'fa-circle-check':'fa-circle-xmark';?>"></i> <?php echo htmlspecialchars($msg);?></div><?php endif;?>

        <!-- Stats -->
        <div class="stats-row" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
            <div class="stat-card"><div class="stat-icon-wrap si-indigo"><i class="fa-solid fa-inbox"></i></div><div class="stat-body"><div class="stat-val"><?php echo $total_all;?></div><div class="stat-lbl">Total Requests</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--warn);"><?php echo $pending_leaves;?></div><div class="stat-lbl">Pending</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-circle-check"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--success);"><?php echo $total_app;?></div><div class="stat-lbl">Approved</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-circle-xmark"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--danger);"><?php echo $total_rej;?></div><div class="stat-lbl">Rejected</div></div></div>
        </div>

        <!-- Leave Settings -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-sliders"></i> Leave Settings</div>
                <div style="font-size:15px;color:var(--right-muted);">HR Manager view</div>
            </div>
            <div class="card-body">
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--r-sm);padding:18px 20px;">
                    <div style="font-size:13.75px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Leave Settings</div>
                    <div style="font-size:20px;font-weight:900;color:#1a2740;">
                        No-pay deduction rate = Basic salary / 30
                    </div>
                    <div style="font-size:15px;color:#64748b;margin-top:8px;line-height:1.7;">
                        Employee leave quota is fixed at <strong><?php echo $annual + $casual; ?> total days</strong>,
                        split into <strong><?php echo $annual; ?> annual</strong> and <strong><?php echo $casual; ?> casual</strong> days.
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
            <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $fv=>$fl): $act=$filter===$fv; ?>
            <a href="?filter=<?php echo $fv;?>" class="btn <?php echo $act?'btn-primary':'btn-ghost';?> btn-sm"><?php echo $fl;?></a>
            <?php endforeach;?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-plane-departure"></i> <?php echo ucfirst($filter==='all'?'All':$filter);?> Leave Requests</div>
                <div style="font-size:15px;color:var(--right-muted);"><?php echo count($leaves);?> records</div>
            </div>
            <?php if(empty($leaves)):?>
            <div class="table-empty"><i class="fa-solid fa-inbox"></i><p>No leave requests found.</p></div>
            <?php else:?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Employee</th><th>Type</th><th>Start Date</th><th>End Date</th><th>Days</th><th>Reason</th><th>Status</th><th>Applied</th></tr></thead>
                    <tbody>
<?php foreach($leaves as $i=>$lv):
                        $sc=match($lv['leave_status']){'approved'=>'sp-approve','rejected'=>'sp-reject',default=>'sp-pending'};
                        $sl=match($lv['leave_status']){'approved'=>'Approved','rejected'=>'Rejected',default=>'Pending'};
                        $reviewed_by=trim($lv['reviewed_by']??'');
                        $show_approve_actions = $can_manage_leave_actions;
                    ?>
                    <tr>
                        <td style="font-size:13.75px;color:var(--right-dim);"><?php echo $i+1;?></td>

                        <td>
                            <div class="emp-cell">
                                <div class="emp-ava" style="width:36px;height:36px;font-size:13.75px;"><?php echo initials($lv['first_name']??'?',$lv['last_name']??'');?></div>
                                <div>
                                    <div class="emp-name"><?php echo htmlspecialchars(($lv['first_name']??'').' '.($lv['last_name']??''));?></div>
                                    <div class="emp-desig"><?php echo $lv['emp_code'];?></div>
                                </div>
                            </div>
                        </td>
                        <td><span style="background:#eef2ff;color:#4338ca;padding:3px 9px;border-radius:20px;font-size:13.125px;font-weight:600;text-transform:capitalize;"><?php echo htmlspecialchars($lv['leave_type']);?></span></td>
                        <td style="font-size:15px;color:var(--right-muted);font-family:'JetBrains Mono',monospace;"><?php echo date('d M Y',strtotime($lv['start_date']));?></td>
                        <td style="font-size:15px;color:var(--right-muted);font-family:'JetBrains Mono',monospace;"><?php echo date('d M Y',strtotime($lv['end_date']));?></td>
                        <td style="font-size:15px;font-weight:600;color:var(--right-text);"><?php echo $lv['no_of_days'];?></td>
                        <td style="font-size:13.75px;color:var(--right-muted);max-width:200px;white-space:normal;">
                            <?php $r=htmlspecialchars($lv['reason']??''); echo strlen($r)>100?substr($r,0,100).'…':$r; ?>
                        </td>
                        <td>
                            <span class="status-pill <?php echo $sc;?>"><?php echo $sl;?></span>
                            <?php if($reviewed_by&&$lv['leave_status']!=='pending'):?>
                            <div style="font-size:13.125px;color:var(--right-dim);margin-top:4px;"><i class="fa-solid fa-circle-user" style="margin-right:3px;opacity:.6;"></i>by <strong style="color:var(--right-muted);"><?php echo htmlspecialchars($reviewed_by);?></strong></div>
                            <?php endif;?>
                        </td>
                        <td style="font-size:13.75px;color:var(--right-muted);"><?php echo date('d M Y',strtotime($lv['applied_date']));?></td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <?php if($show_approve_actions):?>
                                    <?php if($lv['leave_status']==='pending'):?>
                                    <a href="?approve=<?php echo $lv['leave_id'];?>&filter=<?php echo $filter;?>" class="btn btn-success btn-sm btn-icon" title="Approve"><i class="fa-solid fa-check" style="font-size:13.75px;"></i></a>
                                    <a href="?reject=<?php echo $lv['leave_id'];?>&filter=<?php echo $filter;?>"  class="btn btn-danger  btn-sm btn-icon" title="Reject"><i class="fa-solid fa-xmark" style="font-size:13.75px;"></i></a>
                                    <?php else:?>
                                    <a href="?approve=<?php echo $lv['leave_id'];?>&filter=<?php echo $filter;?>" class="btn btn-ghost btn-sm btn-icon" title="Re-approve" style="color:var(--success);"><i class="fa-solid fa-check" style="font-size:13.75px;"></i></a>
                                    <a href="?reject=<?php echo $lv['leave_id'];?>&filter=<?php echo $filter;?>"  class="btn btn-ghost btn-sm btn-icon" title="Re-reject"  style="color:var(--danger);"><i class="fa-solid fa-xmark" style="font-size:13.75px;"></i></a>
                                    <?php endif;?>
                                <?php endif;?>
                                <?php if($can_manage_leave_actions): ?>
                                    <a href="?delete=<?php echo $lv['leave_id'];?>&filter=<?php echo $filter;?>" onclick="return confirm('Delete this leave request?')" class="btn btn-ghost btn-sm btn-icon" title="Delete"><i class="fa-solid fa-trash" style="font-size:13.75px;color:var(--danger);"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php endif;?>
        </div>
    </div>
    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
<script>const t=document.getElementById('toast');if(t){setTimeout(()=>{t.style.opacity='0';},3700);setTimeout(()=>t.remove(),4200);}</script>
</body></html>
