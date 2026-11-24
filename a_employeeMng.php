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
// Access control: Owner and HR Manager only
if(!in_array($admin_role, ['owner','hr_manager'], true)){
    header('Location: a_dashboard.php');
    exit;
}
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];

// Normalize legacy contract employment types out of live records.
$conn->query("UPDATE employees SET employment_type='temporary' WHERE LOWER(employment_type)='contract'");

$msg=''; $msg_type='';

// Handle Add
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='add'){
    // Collect form data
    $emp_code = strtoupper(trim($_POST['emp_code']??''));
    $password = sha1(trim($_POST['emp_password']??''));
    $first_name = trim($_POST['first_name']??'');
    $last_name = trim($_POST['last_name']??'');
    $date_of_birth = trim($_POST['dob']??'');
    $gender = trim($_POST['gender']??'male');
    $address = trim($_POST['address']??'');
    $city = trim($_POST['city']??'');
    $email = trim($_POST['email']??'');
    $contact_no = trim($_POST['mobile']??'');
    $employment_type = trim($_POST['emp_type']??'permanent');
    if(!in_array($employment_type, ['permanent','temporary'], true)){
        $employment_type = 'permanent';
    }
    $date_of_joining = trim($_POST['joining_date']??'');
    $designation = trim($_POST['designation']??'');
    $section = trim($_POST['section']??'');
    $basic_salary = floatval($_POST['basic_salary']??0);
    $bank_name = trim($_POST['bank_name']??'');
    $bank_account_no = trim($_POST['bank_account_no']??'');
    
    $ins=$conn->prepare("INSERT INTO employees (emp_code, password, first_name, last_name, date_of_birth, gender, address, city, email, contact_no, employment_type, date_of_joining, designation, section, basic_salary, bank_name, bank_account_no) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param("ssssssssssssssdss", $emp_code, $password, $first_name, $last_name, $date_of_birth, $gender, $address, $city, $email, $contact_no, $employment_type, $date_of_joining, $designation, $section, $basic_salary, $bank_name, $bank_account_no);
    
    if($ins->execute()){
        $msg='Employee added successfully.';
        $msg_type='success';
    }else{
        $msg='Error: '.$ins->error;
        $msg_type='error';
    }
    $ins->close();
}

// Handle Delete (archive into resigned_employees)
if(isset($_GET['delete'])){
    $dc=trim($_GET['delete']);

    // Fetch employee row first
    $fetch = $conn->prepare("SELECT * FROM employees WHERE emp_code=?");
    $fetch->bind_param("s", $dc);
    $fetch->execute();
    $empRow = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    if(!$empRow){
        $msg='Employee not found.';
        $msg_type='error';
    } else {
        // Insert into resigned archive using the actual archive schema.
        $ins = $conn->prepare(
            "INSERT INTO resigned_employees (emp_code, first_name, last_name, employment_type, designation, section, date_of_joining, basic_salary, contact_no, email, resigned_date, resigned_by, reason)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $resigned_at = date('Y-m-d H:i:s');
        $resigned_by = $admin_code;
        $reason = 'Deleted from employee management';

        $ins->bind_param(
            "sssssssdsssss",
            $empRow['emp_code'],
            $empRow['first_name'],
            $empRow['last_name'],
            $empRow['employment_type'],
            $empRow['designation'],
            $empRow['section'],
            $empRow['date_of_joining'],
            $empRow['basic_salary'],
            $empRow['contact_no'],
            $empRow['email'],
            $resigned_at,
            $resigned_by,
            $reason
        );

        $archived = $ins->execute();
        $ins->close();

        if($archived){
            $del=$conn->prepare("DELETE FROM employees WHERE emp_code=?");
            $del->bind_param("s",$dc);
            if($del->execute()){
                $msg='Employee deleted and archived.';
                $msg_type='success';
            } else {
                $msg='Archived, but delete failed.';
                $msg_type='error';
            }
            $del->close();
        } else {
            $msg='Delete failed (archive failed).';
            $msg_type='error';
        }
    }
}


// Handle Edit
$edit_emp=null;
if(isset($_GET['edit'])){
    $ec=trim($_GET['edit']);
    $se=$conn->prepare("SELECT * FROM employees WHERE emp_code=?");
    $se->bind_param("s",$ec);
    $se->execute();
    $edit_emp=$se->get_result()->fetch_assoc();
    $se->close();
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='edit'){
    $ec=trim($_POST['emp_code_orig']??'');
    $first_name = trim($_POST['first_name']??'');
    $last_name = trim($_POST['last_name']??'');
    $date_of_birth = trim($_POST['dob']??'');
    $gender = trim($_POST['gender']??'male');
    $address = trim($_POST['address']??'');
    $city = trim($_POST['city']??'');
    $email = trim($_POST['email']??'');
    $contact_no = trim($_POST['mobile']??'');
    $employment_type = trim($_POST['emp_type']??'permanent');
    if(!in_array($employment_type, ['permanent','temporary'], true)){
        $employment_type = 'permanent';
    }
    $date_of_joining = trim($_POST['joining_date']??'');
    $designation = trim($_POST['designation']??'');
    $section = trim($_POST['section']??'');
    $basic_salary = floatval($_POST['basic_salary']??0);
    $bank_name = trim($_POST['bank_name']??'');
    $bank_account_no = trim($_POST['bank_account_no']??'');
    
    $upd=$conn->prepare("UPDATE employees SET first_name=?, last_name=?, date_of_birth=?, gender=?, address=?, city=?, email=?, contact_no=?, employment_type=?, date_of_joining=?, designation=?, section=?, basic_salary=?, bank_name=?, bank_account_no=? WHERE emp_code=?");
    $upd->bind_param("ssssssssssssdsss", $first_name, $last_name, $date_of_birth, $gender, $address, $city, $email, $contact_no, $employment_type, $date_of_joining, $designation, $section, $basic_salary, $bank_name, $bank_account_no, $ec);
    
    if($upd->execute()){
        $msg='Employee updated.';
        $msg_type='success';
    }else{
        $msg='Update failed.';
        $msg_type='error';
    }
    $upd->close();
}

$employees=$conn->query("SELECT * FROM employees ORDER BY emp_id")->fetch_all(MYSQLI_ASSOC);
$show_add=isset($_GET['action'])&&$_GET['action']==='add';


$page_title='Employee Management';$active_page='Employee Management';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Employee Management</h1><p>Add, edit and manage all employees</p></div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y');?></span>
        </div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div><div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Employee Management</div><h2>All Employees</h2></div>
            <button onclick="document.getElementById('add-modal').classList.add('open')" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Employee</button>
        </div>
        <?php if($msg):?><div class="toast <?php echo $msg_type==='success'?'toast-success':'toast-error';?>" id="toast"><i class="fa-solid <?php echo $msg_type==='success'?'fa-circle-check':'fa-circle-xmark';?>"></i> <?php echo htmlspecialchars($msg);?></div><?php endif;?>

        <!-- Table -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-users"></i> Employee List</div><div style="font-size:15px;color:var(--right-muted);"><?php echo count($employees);?> employees</div></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
<thead><tr><th>#</th><th>Employee</th><th>Email</th><th>Contact</th><th>Section</th><th>Designation</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($employees as $i=>$e):
                     $type_val = $e['employment_type']; 
                    $bc = match(true){
                        stripos($type_val,'permanent')!==false => 'badge-perm',
                        stripos($type_val,'temporary')!==false => 'badge-intern',
                         default => 'badge-part'
                     };
                     $bl = ucfirst($type_val);
                ?>
                    <tr>
                        <td style="font-size:13.75px;color:var(--right-dim);"><?php echo $i+1;?></td>
                        <td><div class="emp-cell"><div class="emp-ava"><?php echo initials($e['first_name'],$e['last_name']);?></div><div><div class="emp-name"><?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']);?></div><div class="emp-desig" style="font-family:'JetBrains Mono',monospace;"><?php echo $e['emp_code'];?></div></div></div></td>
                        <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($e['email']);?></td>
                        <td style="font-size:15px;color:var(--right-muted);font-family:'JetBrains Mono',monospace;"><?php echo htmlspecialchars($e['contact_no']);?></td>
                        <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($e['section']);?></td>
                        <td style="font-size:15px;color:var(--right-text);font-weight:500;"><?php echo htmlspecialchars($e['designation']);?></td>
                        <td><span class="badge <?php echo $bc;?>"><?php echo $bl;?></span></td>

                <td>
                            <div style="display:flex;gap:6px;">
                                 <button onclick="openViewMore(<?php echo htmlspecialchars(json_encode($e));?>)" class="btn btn-ghost btn-sm btn-icon" title="View more"><i class="fa-solid fa-eye" style="font-size:13.75px;"></i></button>
                                <button onclick="openEdit(<?php echo htmlspecialchars(json_encode($e));?>)" class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fa-solid fa-pencil" style="font-size:13.75px;"></i></button>
                                <a href="#" onclick="requestDeleteEmployee('<?php echo htmlspecialchars($e['emp_code']);?>'); return false;" class="btn btn-danger btn-sm btn-icon" title="Delete"><i class="fa-solid fa-trash" style="font-size:13.75px;"></i></a>
                            </div>
                        </td>
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
    <div class="modal" style="width:640px;">
        <div class="modal-header"><div class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--accent);margin-right:8px;"></i>Add New Employee</div><button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
        <div class="modal-body">
            <div class="form-row cols-2">
                <div class="form-group"><label>Employee Code</label><input type="text" name="emp_code" class="form-control" placeholder="e.g. EMP001" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="emp_password" class="form-control" placeholder="Set password" required></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" required></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" class="form-control"></div>
                <div class="form-group"><label>Gender</label><select name="gender" class="form-control"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control"></div>
                <div class="form-group"><label>City</label><input type="text" name="city" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Mobile</label><input type="text" name="mobile" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Employment Type</label><select name="emp_type" class="form-control"><option value="permanent">Permanent</option><option value="temporary">Temporary</option></select></div>
                <div class="form-group"><label>Joining Date</label><input type="date" name="joining_date" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Designation</label><input type="text" name="designation" class="form-control" required></div>
                <div class="form-group"><label>Section</label><input type="text" name="section" class="form-control" required></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" class="form-control" placeholder="0.00"></div>
                <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control"></div>
            </div>
            <div class="form-row cols-1" style="grid-template-columns:1fr;">
                <div class="form-group"><label>Bank Account No</label><input type="text" name="bank_account_no" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="document.getElementById('add-modal').classList.remove('open')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Employee</button></div>
        </form>
    </div>
</div>

<!-- View More Modal -->
<div class="modal-overlay" id="view-more-modal">
    <div class="modal" style="width:760px;max-width:95vw;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-eye" style="color:var(--accent);margin-right:8px;"></i>Employee Details</div>
            <button class="modal-close" onclick="document.getElementById('view-more-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-row cols-2">
                <div class="form-group"><label>Employee Code</label><div class="view-box" id="vm-emp_code"></div></div>
                <div class="form-group"><label>Name</label><div class="view-box" id="vm-name"></div></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Email</label><div class="view-box" id="vm-email"></div></div>
                <div class="form-group"><label>Mobile</label><div class="view-box" id="vm-mobile"></div></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Department / Section</label><div class="view-box" id="vm-section"></div></div>
                <div class="form-group"><label>Designation</label><div class="view-box" id="vm-designation"></div></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Employment Type</label><div class="view-box" id="vm-employment_type"></div></div>
                <div class="form-group"><label>Basic Salary</label><div class="view-box" id="vm-basic_salary"></div></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Bank Name</label><div class="view-box" id="vm-bank_name"></div></div>
                <div class="form-group"><label>Bank Account No</label><div class="view-box" id="vm-bank_account_no"></div></div>
            </div>
            <div class="form-row cols-1">
                <div class="form-group"><label>Address</label><div class="view-box" id="vm-address"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal" style="width:640px;">
        <div class="modal-header"><div class="modal-title"><i class="fa-solid fa-pencil" style="color:var(--accent);margin-right:8px;"></i>Edit Employee</div><button class="modal-close" onclick="document.getElementById('edit-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="emp_code_orig" id="edit-emp-code">
        <div class="modal-body">
            <div class="form-row cols-2">
                <div class="form-group"><label>First Name</label><input type="text" name="first_name" id="e-first_name" class="form-control" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="last_name" id="e-last_name" class="form-control" required></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" id="e-date_of_birth" class="form-control"></div>
                <div class="form-group"><label>Gender</label><select name="gender" id="e-gender" class="form-control"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Address</label><input type="text" name="address" id="e-address" class="form-control"></div>
                <div class="form-group"><label>City</label><input type="text" name="city" id="e-city" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Email</label><input type="email" name="email" id="e-email" class="form-control" required></div>
                <div class="form-group"><label>Mobile</label><input type="text" name="mobile" id="e-contact_no" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Employment Type</label><select name="emp_type" id="e-employment_type" class="form-control"><option value="permanent">Permanent</option><option value="temporary">Temporary</option></select></div>
                <div class="form-group"><label>Joining Date</label><input type="date" name="joining_date" id="e-date_of_joining" class="form-control"></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Designation</label><input type="text" name="designation" id="e-designation" class="form-control" required></div>
                <div class="form-group"><label>Section</label><input type="text" name="section" id="e-section" class="form-control" required></div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group"><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" id="e-basic_salary" class="form-control"></div>
                <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" id="e-bank_name" class="form-control"></div>
            </div>
            <div class="form-row cols-1" style="grid-template-columns:1fr;">
                <div class="form-group"><label>Bank Account No</label><input type="text" name="bank_account_no" id="e-bank_account_no" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-modal').classList.remove('open')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEdit(e){
    document.getElementById('edit-emp-code').value=e.emp_code;
    document.getElementById('e-first_name').value=e.first_name||'';
    document.getElementById('e-last_name').value=e.last_name||'';
    document.getElementById('e-date_of_birth').value=e.date_of_birth||'';
    document.getElementById('e-gender').value=e.gender||'';
    document.getElementById('e-address').value=e.address||'';
    document.getElementById('e-city').value=e.city||'';
    document.getElementById('e-email').value=e.email||'';
    document.getElementById('e-contact_no').value=e.contact_no||'';
    document.getElementById('e-employment_type').value=e.employment_type||'';
    document.getElementById('e-date_of_joining').value=e.date_of_joining||'';
    document.getElementById('e-designation').value=e.designation||'';
    document.getElementById('e-section').value=e.section||'';
    document.getElementById('e-basic_salary').value=e.basic_salary||'';
    document.getElementById('e-bank_name').value=e.bank_name||'';
    document.getElementById('e-bank_account_no').value=e.bank_account_no||'';
    document.getElementById('edit-modal').classList.add('open');
}
function openViewMore(e){
    const set = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val || '—'; };
    set('vm-emp_code', e.emp_code);
    set('vm-name', ((e.first_name||'') + ' ' + (e.last_name||'')).trim());
    set('vm-email', e.email);
    set('vm-mobile', e.contact_no);
    set('vm-section', e.section);
    set('vm-designation', e.designation);
    set('vm-employment_type', e.employment_type ? String(e.employment_type).replace(/\b\w/g, c => c.toUpperCase()) : '—');
    set('vm-basic_salary', e.basic_salary ? Number(e.basic_salary).toFixed(2) : '0.00');
    set('vm-bank_name', e.bank_name);
    set('vm-bank_account_no', e.bank_account_no);
    set('vm-address', e.address);
    document.getElementById('view-more-modal').classList.add('open');
}
<?php if($show_add):?>document.getElementById('add-modal').classList.add('open');<?php endif;?>
// Delete modal is handled separately (requestDeleteEmployee)
const t=document.getElementById('toast');

function requestDeleteEmployee(empCode){
    const modal = document.getElementById('delete-emp-modal');
    const txt = document.getElementById('delete-emp-text');
    const codeInput = document.getElementById('delete-emp-code');

    if(txt){
        txt.innerHTML = 'Are you sure you want to delete <strong>' + empCode + '</strong>? This will archive the employee into Resigned Employees.';
    }
    if(codeInput){
        codeInput.value = empCode;
    }
    if(modal){ modal.classList.add('open'); }
}

function closeDeleteEmpModal(){
    const modal = document.getElementById('delete-emp-modal');
    if(modal){ modal.classList.remove('open'); }
}

</script>

<!-- Delete Confirm Modal (Employee) -->
<div class="modal-overlay" id="delete-emp-modal" style="z-index:10050;">
    <div class="modal" style="width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--accent);margin-right:8px;"></i>Delete Employee</div>
            <button class="modal-close" onclick="closeDeleteEmpModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p id="delete-emp-text" style="margin:0;color:var(--right-muted);font-size:15px;">
                Are you sure you want to delete this employee?
            </p>
        </div>
            <div class="modal-footer">
            	<button type="button" class="btn btn-ghost" onclick="closeDeleteEmpModal()">No</button>

            	<form method="GET" id="delete-emp-form" style="margin:0;">
            	    <input type="hidden" name="delete" id="delete-emp-code" value="">
            	    <button type="submit" class="btn btn-danger">Yes, Delete</button>
            	</form>
            </div>
    </div>
</div>

</body></html>
