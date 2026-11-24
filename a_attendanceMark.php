<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
// SQLite PDO (db()) throws exceptions; no mysqli connect_error here.
// kept as comment for compatibility.

$admin_code=$_SESSION['admin_code'];
$s = $conn->prepare("SELECT * FROM admin WHERE admin_code=?");
if(!$s){
    $admin = null;
} else {
    $s->execute();
    // MySQLiCompat_Stmt::bind_param is used across the project; keep compatibility.
    // (Some older code paths call execute([$var]) which MySQLiCompat doesn't support.)
    // If execute() above failed to bind, try the safer fallback using a direct query.
    $admin = $s->get_result()?->fetch_assoc();
    if(!$admin){
        $adminRes = $conn->query("SELECT * FROM admin WHERE admin_code='".$conn->real_escape_string($admin_code)."' LIMIT 1");
        if($adminRes && is_object($adminRes)){
            $admin = $adminRes->fetch_assoc();
        }
    }
    $s = null;
}
$pendingLeavesRes = $conn->query("SELECT COUNT(*) AS c FROM leaves WHERE leave_status='pending'");
$pending_leaves = (int)(($pendingLeavesRes && method_exists($pendingLeavesRes,'fetch_assoc')) ? ($pendingLeavesRes->fetch_assoc()['c'] ?? 0) : 0);

$admin_role = $admin['admin_role'] ?? null;


// Only Owner and HR Manager can access
if($admin_role!=='owner' && $admin_role!=='hr_manager'){
    header('Location: a_dashboard.php');
    exit;
}

$today=date('Y-m-d');
$selected_date = trim($_GET['date'] ?? $today);
$search=trim($_GET['search']??'');
$filter_section=trim($_GET['section'] ?? '');
$filter_status=trim($_GET['status'] ?? 'all'); // all|present|absent
$filter_not_marked = isset($_GET['not_marked']) && $_GET['not_marked']==='1';

// Handle AJAX requests
if($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    $action=$_POST['action']??'';

    if($action==='update_status'){
        $emp_code=$_POST['emp_code']??'';
        $date=$_POST['date']??'';
        $status=$_POST['status']??'';

        if($emp_code && $date && in_array($status,['present','absent'])){
            $check=$conn->prepare("SELECT attendance_id FROM attendance WHERE emp_code=? AND attendance_date=?");
            $check->bind_param("ss",$emp_code,$date);
            $check->execute();
            $exists=$check->get_result()->fetch_assoc();
            $check->close();

            if($exists){
                $stmt=$conn->prepare("UPDATE attendance SET status=?, marked_by=? WHERE emp_code=? AND attendance_date=?");
                $stmt->bind_param("ssss",$status,$admin_code,$emp_code,$date);
            }else{
                $stmt=$conn->prepare("INSERT INTO attendance (emp_code, attendance_date, status, marked_by) VALUES (?,?,?,?)");
                $stmt->bind_param("ssss",$emp_code,$date,$status,$admin_code);
            }

            if($stmt->execute()){
                echo json_encode(['success'=>true]);
            }else{
                echo json_encode(['success'=>false,'message'=>'Failed to update status']);
            }
            $stmt->close();
        }
        exit;
    }

    if($action==='update_time'){
        $emp_code=$_POST['emp_code']??'';
        $date=$_POST['date']??'';
        $field=$_POST['field']??'';
        $value=$_POST['value']??'';

        if($emp_code && $date && in_array($field,['time_in','time_out','note_in','note_out'])){
            $check=$conn->prepare("SELECT attendance_id FROM attendance WHERE emp_code=? AND attendance_date=?");
            $check->bind_param("ss",$emp_code,$date);
            $check->execute();
            $exists=$check->get_result()->fetch_assoc();
            $check->close();

            if(!$exists){
                $ins=$conn->prepare("INSERT INTO attendance (emp_code, attendance_date, status, marked_by) VALUES (?,?,'absent',?)");
                $ins->bind_param("sss",$emp_code,$date,$admin_code);
                $ins->execute();
                $ins->close();
            }

            $stmt=$conn->prepare("UPDATE attendance SET $field=?, marked_by=? WHERE emp_code=? AND attendance_date=?");
            $stmt->bind_param("ssss",$value,$admin_code,$emp_code,$date);

            if($stmt->execute()){
                echo json_encode(['success'=>true]);
            }else{
                echo json_encode(['success'=>false,'message'=>'Failed to update time']);
            }
            $stmt->close();
        }
        exit;
    }
}

// Fetch filter values for section dropdown
$sections_res = $conn->query("SELECT DISTINCT section FROM employees WHERE status='active' AND section IS NOT NULL AND TRIM(section)<>'' ORDER BY section");
$sections = [];
while($r=$sections_res->fetch_assoc()){$sections[]=$r['section'];}

$where_emp='WHERE status="active"';
if($search){
    $esc=$conn->real_escape_string($search);
    $where_emp.=" AND (emp_code LIKE '%$esc%' OR first_name LIKE '%$esc%' OR last_name LIKE '%$esc%' OR CONCAT(first_name,' ',last_name) LIKE '%$esc%')";
}
if($filter_section){
    $where_emp.=" AND section='".$conn->real_escape_string($filter_section)."'";
}

$employees=$conn->query("SELECT emp_code,first_name,last_name,section FROM employees $where_emp ORDER BY emp_code")->fetch_all(MYSQLI_ASSOC);

// Attendance lookup for selected date
$att_selected=[];
$att_stmt=$conn->prepare("SELECT emp_code,status,time_in,time_out,note_in,note_out FROM attendance WHERE attendance_date=?");
$att_stmt->bind_param("s", $selected_date);
$att_stmt->execute();
$rows=$att_stmt->get_result();
while($row=$rows?->fetch_assoc()){
    $att_selected[$row['emp_code']]=$row;
}
$att_stmt->close();


// Apply status/not-marked filters to the list
$filtered_employees=[];
foreach($employees as $emp){
    $att = $att_selected[$emp['emp_code']] ?? ['status'=>'absent','time_in'=>'','time_out'=>'','note_in'=>'','note_out'=>''];
    $is_present = ($att['status']==='present');

    if($filter_status==='present' && !$is_present) continue;
    if($filter_status==='absent' && $is_present) continue;

    if($filter_not_marked){
        $has_row = isset($att_selected[$emp['emp_code']]);
        // Not marked if there is no attendance row for the selected date
        if($has_row) continue;
    }

    $filtered_employees[]=$emp;
}

$view_count=count($filtered_employees);
$page_title='Attendance Mark';
// Must match label used in a_adminLayout.php nav_link() for active state
$active_page='Attendance Mark';
require 'a_adminLayout.php';

?>
    <header class="topbar">
        <div class="topbar-left">
            <h1>Attendance Mark</h1>
            <p>Mark daily attendance for all employees</p>
        </div>
        <div class="topbar-actions">
            <span class="tb-date" style="background:var(--accent);color:#fff;padding:8px 16px;border-radius:var(--r-sm);font-weight:600;">
                <i class="fa-solid fa-calendar-day"></i> <?php echo htmlspecialchars(date('D, d M Y', strtotime($selected_date)));?>
            </span>
        </div>
    </header>

    <div class="page page-anim">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="a_dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Attendance Mark
                </div>
                <h2>Mark Attendance</h2>
                <p style="margin-top:6px;font-size:15px;color:var(--right-muted);">
                    <i class="fa-solid fa-info-circle"></i> Use filters to quickly narrow down who still needs marking.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="padding:16px 24px;">
                <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                        <label style="display:none;">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by Employee ID or Name..." value="<?php echo htmlspecialchars($search);?>">
                    </div>

                    <div class="form-group" style="margin:0;min-width:170px;">
                        <label>Section</label>
                        <select name="section" class="form-control">
                            <option value="">All Sections</option>
                            <?php foreach($sections as $sec):?>
                                <option value="<?php echo htmlspecialchars($sec);?>" <?php echo ($filter_section===$sec)?'selected':'';?>><?php echo htmlspecialchars($sec);?></option>
                            <?php endforeach;?>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;min-width:170px;">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="all" <?php echo ($filter_status==='all')?'selected':'';?>>All</option>
                            <option value="present" <?php echo ($filter_status==='present')?'selected':'';?>>Present</option>
                            <option value="absent" <?php echo ($filter_status==='absent')?'selected':'';?>>Absent</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin:0;min-width:170px;">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date);?>">
                    </div>

                    <div style="display:flex;flex-direction:column;gap:8px;min-width:190px;">
                        <label style="font-size:15px;color:var(--right-text);font-weight:600;display:flex;align-items:center;gap:10px;margin:0;">
                            <input type="checkbox" name="not_marked" value="1" <?php echo $filter_not_marked?'checked':'';?> >
                            Show only Not Marked
                        </label>
                        <div style="display:flex;gap:9px;align-items:center;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-magnifying-glass"></i> Apply
                            </button>
                            <?php if($search || $filter_section || $filter_status!=='all' || $filter_not_marked || $selected_date!==$today):?>
                                <a href="a_attendanceMark.php" class="btn btn-ghost">
                                    <i class="fa-solid fa-xmark"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-calendar-check"></i>
                    Attendance for <?php echo htmlspecialchars(date('l, F j, Y', strtotime($selected_date)));?>
                </div>
                <div style="font-size:15px;color:var(--right-muted);">
                    <?php echo (int)$view_count;?> employees
                </div>
            </div>

            <?php if(empty($filtered_employees)):?>
                <div class="table-empty">
                    <i class="fa-solid fa-users-slash"></i>
                    <p>No employees match your filters</p>
                </div>
            <?php else:?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:120px;">Date</th>
                                <th>Employee</th>
                                <th>Section</th>
                                <th style="width:140px;">Present/Absent</th>
                                <th style="width:130px;">IN</th>
                                <th style="width:130px;">OUT</th>
                                <th style="width:180px;">IN Note</th>
                                <th style="width:180px;">OUT Note</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($filtered_employees as $emp):
                            $att=$att_selected[$emp['emp_code']]??['status'=>'absent','time_in'=>'','time_out'=>'','note_in'=>'','note_out'=>''];
                            $is_present=($att['status']==='present');
                        ?>
                        <tr data-emp="<?php echo htmlspecialchars($emp['emp_code']);?>">
                            <td>
                                <div style="font-weight:600;font-size:15px;color:var(--right-text);">
                                    <?php echo date('d M',strtotime($selected_date));?>
                                </div>
                                <div style="font-size:13.75px;color:var(--right-dim);">
                                    <?php echo date('Y',strtotime($selected_date));?>
                                </div>
                            </td>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-ava">
                                        <?php echo initials($emp['first_name'],$emp['last_name']);?>
                                    </div>
                                    <div>
                                        <div class="emp-name">
                                            <?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']);?>
                                        </div>
                                        <div class="emp-desig"><?php echo htmlspecialchars($emp['emp_code']);?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:15px;color:var(--right-muted);">
                                <?php echo htmlspecialchars($emp['section']??'—');?>
                            </td>
                            <td>
                                <select class="form-control status-select" data-emp="<?php echo htmlspecialchars($emp['emp_code']);?>" style="padding:8px 12px;">
                                    <option value="present" <?php echo $is_present?'selected':'';?>>Present</option>
                                    <option value="absent" <?php echo !$is_present?'selected':'';?>>Absent</option>
                                </select>
                            </td>
                            <td class="inline-edit-cell">
                                <div class="inline-display" onclick="editTime(this,'<?php echo htmlspecialchars($emp['emp_code']);?>','time_in')">
                                    <?php echo $att['time_in']?date('h:i A',strtotime($att['time_in'])):'<span style="color:var(--right-dim);font-style:italic;">Click to set</span>'; ?>
                                </div>
                            </td>
                            <td class="inline-edit-cell">
                                <div class="inline-display" onclick="editTime(this,'<?php echo htmlspecialchars($emp['emp_code']);?>','time_out')">
                                    <?php echo $att['time_out']?date('h:i A',strtotime($att['time_out'])):'<span style="color:var(--right-dim);font-style:italic;">Click to set</span>'; ?>
                                </div>
                            </td>
                            <td class="inline-edit-cell">
                                <div class="inline-display" onclick="editNote(this,'<?php echo htmlspecialchars($emp['emp_code']);?>','note_in')">
                                    <?php echo $att['note_in']?htmlspecialchars($att['note_in']):'<span style="color:var(--right-dim);font-style:italic;">Click to add</span>'; ?>
                                </div>
                            </td>
                            <td class="inline-edit-cell">
                                <div class="inline-display" onclick="editNote(this,'<?php echo htmlspecialchars($emp['emp_code']);?>','note_out')">
                                    <?php echo $att['note_out']?htmlspecialchars($att['note_out']):'<span style="color:var(--right-dim);font-style:italic;">Click to add</span>'; ?>
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

<script>
const selectedDate = '<?php echo addslashes($selected_date);?>';

document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', function(){
        const emp = this.dataset.emp;
        const status = this.value;

        fetch('a_attendanceMark.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'update_status',
                emp_code: emp,
                date: selectedDate,
                status: status
            })
        })
        .then(r => r.json())
        .then(data => {
            if(!data.success){
                alert('Error: ' + (data.message || 'Failed to update'));
                location.reload();
            }
        });
    });
});

function editTime(el, emp, field){
    const current = el.textContent.trim();
    const hasValue = !current.includes('Click to set');
    let timeVal = '';

    if(hasValue){
        const parts = current.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        if(parts){
            let h = parseInt(parts[1]);
            const m = parts[2];
            const ap = parts[3].toUpperCase();
            if(ap==='PM' && h!==12) h+=12;
            if(ap==='AM' && h===12) h=0;
            timeVal = String(h).padStart(2,'0') + ':' + m;
        }
    }

    el.innerHTML = `<input type="time" class="inline-input" value="${timeVal}" data-emp="${emp}" data-field="${field}" onblur="saveTime(this)" onkeydown="if(event.key==='Enter')this.blur()">`;
    el.querySelector('input').focus();
}

function saveTime(inp){
    const emp = inp.dataset.emp;
    const field = inp.dataset.field;
    const value = inp.value;
    const cell = inp.parentElement;

    fetch('a_attendanceMark.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'update_time',
            emp_code: emp,
            date: selectedDate,
            field: field,
            value: value
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            if(value){
                const d = new Date('2000-01-01 ' + value);
                const h = d.getHours();
                const m = String(d.getMinutes()).padStart(2,'0');
                const ap = h>=12?'PM':'AM';
                const h12 = h%12||12;
                cell.innerHTML = `<div class="inline-display" onclick="editTime(this,'${emp}','${field}')">${h12}:${m} ${ap}</div>`;
            }else{
                cell.innerHTML = `<div class="inline-display" onclick="editTime(this,'${emp}','${field}')"><span style="color:var(--right-dim);font-style:italic;">Click to set</span></div>`;
            }
        }else{
            alert('Error: ' + (data.message || 'Failed to update'));
            location.reload();
        }
    });
}

function editNote(el, emp, field){
    const current = el.textContent.trim();
    const hasValue = !current.includes('Click to add');
    const noteVal = hasValue ? current : '';

    el.innerHTML = `<input type="text" class="inline-input" value="${noteVal}" data-emp="${emp}" data-field="${field}" onblur="saveNote(this)" onkeydown="if(event.key==='Enter')this.blur()" style="font-family:'Outfit',sans-serif;">`;
    el.querySelector('input').focus();
}

function saveNote(inp){
    const emp = inp.dataset.emp;
    const field = inp.dataset.field;
    const value = inp.value;
    const cell = inp.parentElement;

    fetch('a_attendanceMark.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'update_time',
            emp_code: emp,
            date: selectedDate,
            field: field,
            value: value
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            if(value){
                cell.innerHTML = `<div class="inline-display" onclick="editNote(this,'${emp}','${field}')">${value}</div>`;
            }else{
                cell.innerHTML = `<div class="inline-display" onclick="editNote(this,'${emp}','${field}')"><span style="color:var(--right-dim);font-style:italic;">Click to add</span></div>`;
            }
        }else{
            alert('Error: ' + (data.message || 'Failed to update'));
            location.reload();
        }
    });
}
</script>

    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
</body></html>