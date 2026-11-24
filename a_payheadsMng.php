<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
if($conn->connect_error)die('DB Error: '.$conn->connect_error);
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$admin_role=$admin['admin_role'];
if($admin_role==='hr_manager'){header('Location: a_dashboard.php');exit;}
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];

$msg='';$msg_type='';

// Handle Add
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='add'){
    $payhead_name = trim($_POST['payhead_name']??'');
    $description = trim($_POST['payhead_desc']??'');
    $payhead_type = trim($_POST['payhead_type']??'earning');
    $calculation_type = trim($_POST['calculation_type']??'fixed');
    $default_value = floatval($_POST['default_value']??0);
    $is_active = 1;
    
    $ins=$conn->prepare("INSERT INTO payheads (payhead_name, payhead_type, calculation_type, default_value, is_active, description) VALUES (?,?,?,?,?,?)");
    $ins->bind_param("sssdis", $payhead_name, $payhead_type, $calculation_type, $default_value, $is_active, $description);
    
    if($ins->execute()){
        $msg='Pay head added.';
        $msg_type='success';
    }else{
        $msg='Error: '.$ins->error;
        $msg_type='error';
    }
    $ins->close();
}

// Handle Edit
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='edit'){
    $payhead_id = (int)$_POST['payhead_id'];
    $payhead_name = trim($_POST['payhead_name']??'');
    $description = trim($_POST['payhead_desc']??'');
    $payhead_type = trim($_POST['payhead_type']??'earning');
    $calculation_type = trim($_POST['calculation_type']??'fixed');
    $default_value = floatval($_POST['default_value']??0);
    
    $upd=$conn->prepare("UPDATE payheads SET payhead_name=?, payhead_type=?, calculation_type=?, default_value=?, description=? WHERE payhead_id=?");
    $upd->bind_param("sssdsi", $payhead_name, $payhead_type, $calculation_type, $default_value, $description, $payhead_id);
    
    if($upd->execute()){
        $msg='Pay head updated.';
        $msg_type='success';
    }else{
        $msg='Update failed.';
        $msg_type='error';
    }
    $upd->close();
}

// Handle Delete
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete'){
    $id = (int)($_POST['payhead_id'] ?? 0);
    if($id > 0){
        $del = $conn->prepare("DELETE FROM payheads WHERE payhead_id=?");
        $del->bind_param("i", $id);
        if($del->execute()){
            $msg='Pay head deleted.';
            $msg_type='success';
        }else{
            $msg='Delete failed.';
            $msg_type='error';
        }
        $del->close();
    }else{
        $msg='Invalid pay head ID.';
        $msg_type='error';
    }
}

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    if($id > 0){
        $del = $conn->prepare("DELETE FROM payheads WHERE payhead_id=?");
        $del->bind_param("i", $id);
        if($del->execute()){
            $msg='Pay head deleted.';
            $msg_type='success';
        }else{
            $msg='Delete failed: '.$del->error;
            $msg_type='error';
        }
        $del->close();
    }else{
        $msg='Invalid pay head ID.';
        $msg_type='error';
    }
}

$payheads=$conn->query("SELECT * FROM payheads ORDER BY payhead_id")->fetch_all(MYSQLI_ASSOC);
$earnings=array_filter($payheads,fn($p)=>$p['payhead_type']==='earning');
$deductions=array_filter($payheads,fn($p)=>$p['payhead_type']==='deduction');
$page_title='Pay Head Management';$active_page='Pay Head Management';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Pay Head Management</h1><p>Manage earnings and deduction heads</p></div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y');?></span>
        </div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div><div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Pay Head Management</div><h2>Pay Heads</h2></div>
            <button onclick="document.getElementById('add-modal').classList.add('open')" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Pay Head</button>
        </div>
        <?php if($msg):?><div class="toast <?php echo $msg_type==='success'?'toast-success':'toast-error';?>" id="toast"><i class="fa-solid <?php echo $msg_type==='success'?'fa-circle-check':'fa-circle-xmark';?>"></i> <?php echo htmlspecialchars($msg);?></div><?php endif;?>

        <!-- Stats -->
        <div class="stats-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
            <div class="stat-card"><div class="stat-icon-wrap si-indigo"><i class="fa-solid fa-list-check"></i></div><div class="stat-body"><div class="stat-val"><?php echo count($payheads);?></div><div class="stat-lbl">Total Pay Heads</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-arrow-trend-up"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--success);"><?php echo count($earnings);?></div><div class="stat-lbl">Earnings Heads</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-arrow-trend-down"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--danger);"><?php echo count($deductions);?></div><div class="stat-lbl">Deduction Heads</div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-list-check"></i> All Pay Heads</div><div style="font-size:15px;color:var(--right-muted);"><?php echo count($payheads);?> entries</div></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
<thead><tr><th>#</th><th>Head Name</th><th>Description</th><th>Type</th><th>Calculation</th><th>Default Value</th><th>Actions</th></tr></thead>

                    <tbody>
                    <?php foreach($payheads as $i=>$ph):?>
                    <tr>
                        <td style="font-size:13.75px;color:var(--right-dim);"><?php echo $i+1;?></td>
                        <td style="font-weight:600;color:var(--right-text);"><?php echo htmlspecialchars($ph['payhead_name']);?></td>
                        <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($ph['description']??'');?></td>
                        <td><span class="pill <?php echo $ph['payhead_type']==='earning'?'pill-earn':'pill-deduct';?>"><?php echo ucfirst($ph['payhead_type']);?></span></td>
                        <td style="font-size:15px;color:var(--right-muted);"><?php echo ucfirst($ph['calculation_type']);?></td>
                        <td style="font-size:15px;color:var(--right-muted);"><?php echo number_format($ph['default_value'],2);?></td>
<td><div style="display:flex;gap:6px;align-items:center;">
    <button onclick="openEditPH(<?php echo htmlspecialchars(json_encode($ph));?>)" class="btn btn-ghost btn-sm btn-icon" title="Edit">
        <i class="fa-solid fa-pencil" style="font-size:13.75px;"></i>
    </button>
    <form method="GET" style="margin:0;display:inline;">
        <input type="hidden" name="delete" value="<?php echo (int)$ph['payhead_id'];?>">
        <button type="submit" class="btn btn-danger btn-sm btn-icon"
                onclick="return confirm('Delete this pay head?');"
                title="Delete">
            <i class="fa-solid fa-trash" style="font-size:13.75px;"></i>
        </button>
    </form>
</div></td>


                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-plus" style="color:var(--accent);margin-right:8px;"></i>Add Pay Head</div>
            <button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row cols-1" style="grid-template-columns:1fr;">
                    <div class="form-group">
                        <label>Head Name</label>
                        <input type="text" name="payhead_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="payhead_desc" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="payhead_type" class="form-control">
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Calculation Type</label>
                        <select name="calculation_type" class="form-control">
                            <option value="fixed">Fixed</option>
                            <option value="percentage">Percentage</option>
                            <option value="hours">Hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Default Value</label>
                        <input type="number" step="0.01" name="default_value" class="form-control" value="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add</button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Modal -->
<div class="modal-overlay" id="edit-ph-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-pencil" style="color:var(--accent);margin-right:8px;"></i>Edit Pay Head</div>
            <button class="modal-close" onclick="document.getElementById('edit-ph-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="payhead_id" id="eph-id">
            <div class="modal-body">
                <div class="form-row cols-1" style="grid-template-columns:1fr;">
                    <div class="form-group">
                        <label>Head Name</label>
                        <input type="text" name="payhead_name" id="eph-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="payhead_desc" id="eph-desc" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="payhead_type" id="eph-type" class="form-control">
                            <option value="earning">Earning</option>
                            <option value="deduction">Deduction</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Calculation Type</label>
                        <select name="calculation_type" id="eph-calc" class="form-control">
                            <option value="fixed">Fixed</option>
                            <option value="percentage">Percentage</option>
                            <option value="hours">Hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Default Value</label>
                        <input type="number" step="0.01" name="default_value" id="eph-value" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-ph-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </div>
</div>
<script>
function openEditPH(p){
    document.getElementById('eph-id').value=p.payhead_id;
    document.getElementById('eph-name').value=p.payhead_name;
    document.getElementById('eph-desc').value=p.description||'';
    document.getElementById('eph-type').value=p.payhead_type;
    document.getElementById('eph-calc').value=p.calculation_type||'fixed';
    document.getElementById('eph-value').value=p.default_value||0;
    document.getElementById('edit-ph-modal').classList.add('open');
}

const t=document.getElementById('toast');if(t){setTimeout(()=>{t.style.opacity='0';},3700);setTimeout(()=>t.remove(),4200);}
</script>
<?php if($msg): ?>
<script>
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, 'a_payheadsMng.php');
    }
</script>
<?php endif; ?>
</body></html>
