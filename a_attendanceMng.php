<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
if($conn->connect_error)die('DB Error: '.$conn->connect_error);
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];
$admin_role=$admin['admin_role'];

$filter_emp  =trim($_GET['emp']  ??'');
$filter_date =trim($_GET['date'] ??'');
$filter_from =trim($_GET['from'] ??'');
$filter_to   =trim($_GET['to']   ??'');
$where_parts=[];
if($filter_emp)  $where_parts[]="a.emp_code='".$conn->real_escape_string($filter_emp)."'";
if($filter_date) $where_parts[]="a.attendance_date='".$conn->real_escape_string($filter_date)."'";
elseif($filter_from&&$filter_to) $where_parts[]="a.attendance_date BETWEEN '".$conn->real_escape_string($filter_from)."' AND '".$conn->real_escape_string($filter_to)."'";
elseif($filter_from) $where_parts[]="a.attendance_date>='".$conn->real_escape_string($filter_from)."'";
$where=count($where_parts)?'WHERE '.implode(' AND ',$where_parts):'';
$OT_CUTOFF='17:00:00';

$records=$conn->query("
    SELECT a.emp_code, a.attendance_date, a.status, a.time_in, a.time_out, a.note_in, a.note_out,
        e.first_name, e.last_name, e.section
    FROM attendance a 
    LEFT JOIN employees e ON e.emp_code=a.emp_code
    $where
    ORDER BY a.attendance_date DESC, a.emp_code ASC
")->fetch_all(MYSQLI_ASSOC);

$employees=$conn->query("SELECT emp_code,first_name,last_name FROM employees WHERE status='active' ORDER BY emp_id")->fetch_all(MYSQLI_ASSOC);

function split_hours($ti,$to,$cutoff='17:00:00'){
    if(!$ti||!$to)return['n'=>0,'ot'=>0,'t'=>0];
    $in=strtotime($ti);$out=strtotime($to);if($out<=$in)return['n'=>0,'ot'=>0,'t'=>0];
    $ct=strtotime(date('Y-m-d',$in).' '.$cutoff);$total=$out-$in;
    if($ct<=$in)return['n'=>0,'ot'=>$total,'t'=>$total];
    if($out<=$ct)return['n'=>$total,'ot'=>0,'t'=>$total];
    return['n'=>$ct-$in,'ot'=>$out-$ct,'t'=>$total];
}
function fmtd($s){if($s<=0)return'—';return floor($s/3600).'h '.floor(($s%3600)/60).'m';}

$page_title='Attendance Details';$active_page='Attendance Details';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Attendance Details</h1><p>Employee attendance records with overtime breakdown</p></div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y');?></span>
        </div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div><div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Attendance Details</div><h2>Employee Attendance</h2></div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-filter"></i> Filter Attendance</div></div>
            <div class="card-body">
                <form method="GET" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:14px;align-items:end;">
                    <div class="form-group" style="margin:0;">
                        <label>Employee</label>
                        <select name="emp" class="form-control">
                            <option value="">All Employees</option>
                            <?php foreach($employees as $e):?>
                            <option value="<?php echo $e['emp_code'];?>" <?php echo $filter_emp===$e['emp_code']?'selected':'';?>>
                                <?php echo htmlspecialchars($e['first_name'].' '.$e['last_name']);?> (<?php echo $e['emp_code'];?>)
                            </option>
                            <?php endforeach;?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Specific Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date);?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filter_from);?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filter_to);?>">
                    </div>
                    <div style="display:flex;gap:9px;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        <a href="a_attendanceMng.php" class="btn btn-ghost">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Attendance Records</div>
                <div style="font-size:15px;color:var(--right-muted);">
                    <?php echo count($records);?> records &nbsp;·&nbsp; OT cutoff: <strong>5:00 PM</strong>
                </div>
            </div>
            <?php if(empty($records)):?>
            <div class="table-empty"><i class="fa-solid fa-clock"></i><p>No records found. Try adjusting the filter.</p></div>
            <?php else:?>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th style="background:#f0fdf4;color:#15803d;white-space:nowrap;">
                                Normal Hrs<br>
                                <small style="font-weight:400;opacity:.75;font-size:13.125px;">(before 5 PM)</small>
                            </th>
                            <th style="background:#fff7ed;color:#c05621;white-space:nowrap;">
                                OT Hrs<br>
                                <small style="font-weight:400;opacity:.75;font-size:13.125px;">(after 5 PM)</small>
                            </th>
                            <th>Total</th>
                            <th>IN Note</th>
                            <th>OUT Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($records as $a):
                        $h=split_hours($a['time_in'],$a['time_out'],$OT_CUTOFF);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:15px;color:var(--right-text);">
                                <?php echo date('d M Y',strtotime($a['attendance_date']));?>
                            </div>
                            <div style="font-size:13.75px;color:var(--right-dim);">
                                <?php echo date('l',strtotime($a['attendance_date']));?>
                            </div>
                        </td>
                        <td>
                            <div class="emp-cell">
                                <div class="emp-ava" style="width:36px;height:36px;font-size:13.75px;">
                                    <?php echo initials($a['first_name']??'?',$a['last_name']??'');?>
                                </div>
                                <div>
                                    <div class="emp-name" style="font-size:15px;">
                                        <?php echo htmlspecialchars(($a['first_name']??'').' '.($a['last_name']??''));?>
                                    </div>
                                    <div class="emp-desig" style="font-size:13.125px;">
                                        <?php echo $a['emp_code'];?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:15px;color:var(--right-muted);">
                            <?php echo htmlspecialchars($a['section']??'—');?>
                        </td>
                        <td>
                            <?php if($a['status']==='present'):?>
                            <span class="badge badge-present">Present</span>
                            <?php else:?>
                            <span class="badge badge-absent">Absent</span>
                            <?php endif;?>
                        </td>
                        <td>
                            <?php if($a['time_in']):?>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:600;color:var(--success);">
                                <?php echo date('h:i A',strtotime($a['time_in']));?>
                            </span>
                            <?php else:?>
                            <span style="color:var(--right-dim);font-style:italic;font-size:15px;">—</span>
                            <?php endif;?>
                        </td>
                        <td>
                            <?php if($a['time_out']):?>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:600;color:var(--danger);">
                                <?php echo date('h:i A',strtotime($a['time_out']));?>
                            </span>
                            <?php else:?>
                            <span style="color:var(--right-dim);font-style:italic;font-size:15px;">—</span>
                            <?php endif;?>
                        </td>
                        <td style="background:#f0fdf4;">
                            <strong style="color:#15803d;font-size:15px;"><?php echo fmtd($h['n']);?></strong>
                        </td>
                        <td style="background:#fff7ed;">
                            <strong style="color:#c05621;font-size:15px;"><?php echo fmtd($h['ot']);?></strong>
                        </td>
                        <td>
                            <strong style="font-size:15px;"><?php echo fmtd($h['t']);?></strong>
                        </td>
                        <td style="font-size:15px;color:var(--right-muted);">
                            <?php echo $a['note_in']?htmlspecialchars($a['note_in']):'<span style="font-style:italic;color:var(--right-dim);">—</span>';?>
                        </td>
                        <td style="font-size:15px;color:var(--right-muted);">
                            <?php echo $a['note_out']?htmlspecialchars($a['note_out']):'<span style="font-style:italic;color:var(--right-dim);">—</span>';?>
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
</body></html>