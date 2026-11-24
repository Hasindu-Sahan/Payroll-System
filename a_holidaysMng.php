<?php
/* a_holidaysMng.php */
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
if($conn->connect_error)die('DB Error: '.$conn->connect_error);
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$admin_role=$admin['admin_role'];
if(!in_array($admin_role, ['owner','hr_manager'], true)){
    header('Location: a_dashboard.php');
    exit;
}
$pending_leaves=(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];

$msg='';$msg_type='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='add'){
    $title=trim($_POST['holiday_title']??'');$desc=trim($_POST['holiday_desc']??'');$date=trim($_POST['holiday_date']??'');
    $ins = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_desc, holiday_date) VALUES (?,?,?)");
    $ins->bind_param("sss", $title, $desc, $date);
    if($ins->execute()){$msg='Holiday added.';$msg_type='success';}else{$msg='Error: '.$ins->error;$msg_type='error';}$ins->close();
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])&&$_POST['action']==='edit'){
    $id=(int)$_POST['holiday_id'];$title=trim($_POST['holiday_title']??'');$desc=trim($_POST['holiday_desc']??'');$date=trim($_POST['holiday_date']??'');
    $upd = $conn->prepare("UPDATE holidays SET holiday_name=?, holiday_desc=?, holiday_date=? WHERE holiday_id=?");
    $upd->bind_param("sssi", $title, $desc, $date, $id);
    if($upd->execute()){$msg='Holiday updated.';$msg_type='success';}else{$msg='Update failed.';$msg_type='error';}$upd->close();
}
if(isset($_GET['delete'])){$id=(int)$_GET['delete'];$conn->query("DELETE FROM holidays WHERE holiday_id=$id");$msg='Holiday deleted.';$msg_type='success';}

$holidays=$conn->query("SELECT * FROM holidays ORDER BY holiday_date DESC, holiday_id DESC")->fetch_all(MYSQLI_ASSOC);

$active_date = trim($_GET['active_date'] ?? '');
if(!$active_date){$active_date=date('Y-m-d');}

$active_year = (int)date('Y', strtotime($active_date));
$active_month = (int)date('m', strtotime($active_date));
$month_start = date('Y-m-01', strtotime($active_date));
$month_end = date('Y-m-t', strtotime($active_date));

// Map holidays by date for quick calendar rendering
$byDate=[];
foreach($holidays as $h){
    $d=$h['holiday_date'];
    if(!isset($byDate[$d])) $byDate[$d]=[];
    $byDate[$d][]=$h;
}

$page_title='Holiday Management';$active_page='Holiday Management';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Holiday Management</h1><p>Manage company holidays and observances</p></div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y');?></span>
        </div>
    </header>
    <div class="page page-anim">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Holiday Management</div>
                <h2>Holidays Calendar</h2>
                <p style="margin-top:6px;font-size:15px;color:var(--right-muted);">
                    Click any date to view holidays. Use Add/Edit to maintain your list.
                </p>
            </div>
            <button onclick="document.getElementById('add-modal').classList.add('open')" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Add Holiday</button>
        </div>

        <?php if($msg):?>
            <div class="toast <?php echo $msg_type==='success'?'toast-success':'toast-error';?>" id="toast">
                <i class="fa-solid <?php echo $msg_type==='success'?'fa-circle-check':'fa-circle-xmark';?>"></i> <?php echo htmlspecialchars($msg);?>
            </div>
        <?php endif;?>

        <!-- Calendar Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-calendar-days"></i> <?php echo date('F Y', strtotime($month_start));?></div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <a class="btn btn-ghost" style="padding:8px 14px;" href="a_holidaysMng.php?active_date=<?php echo date('Y-m-d', strtotime(date('Y-m-01', strtotime($active_date)).' -1 month'));?>">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>
                    <a class="btn btn-ghost" style="padding:8px 14px;" href="a_holidaysMng.php?active_date=<?php echo date('Y-m-d');?>">
                        <i class="fa-solid fa-bullseye"></i> Today
                    </a>
                    <a class="btn btn-ghost" style="padding:8px 14px;" href="a_holidaysMng.php?active_date=<?php echo date('Y-m-d', strtotime(date('Y-m-01', strtotime($active_date)).' +1 month'));?>">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <div class="card-body" style="padding:18px 24px;">
                <div style="display:grid;grid-template-columns: 1.3fr 0.9fr;gap:18px;align-items:start;">

                    <div>
                        <div class="calendar-top-hints" style="display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;">
                            <div style="color:var(--right-muted);font-size:14.5px;">
                                <span class="holiday-dot" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#6366f1;margin-right:8px;"></span> Holiday dates
                            </div>
                            <div style="color:var(--right-muted);font-size:14.5px;">
                                Selected: <strong style="color:var(--right-text);"><?php echo htmlspecialchars($active_date);?></strong>
                            </div>
                        </div>

                        <div class="holiday-calendar">
                            <div class="dow" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:10px;">
                                <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow):?>
                                    <div style="font-size:15px;font-weight:800;color:var(--right-muted);text-align:center;letter-spacing:0.02em;">$dow</div>
                                <?php endforeach;?>
                            </div>

                            <?php
                                $start_ts = strtotime($month_start);
                                // Make Monday = 0
                                $weekday = (int)date('N', $start_ts); // 1..7 (Mon..Sun)
                                $first_cell_ts = strtotime('-'.($weekday-1).' days', $start_ts);
                                $cells = 42; // 6 weeks
                            ?>

                            <div class="grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;">
                                <?php for($i=0;$i<$cells;$i++):
                                    $cell_date = date('Y-m-d', strtotime('+'.$i.' days', $first_cell_ts));
                                    $in_month = ((int)date('m', strtotime($cell_date))) === $active_month;
                                    $is_selected = ($cell_date===$active_date);
                                    $is_holiday = isset($byDate[$cell_date]);
                                    $hol_count = $is_holiday ? count($byDate[$cell_date]) : 0;
                                ?>
                                    <a class="day-cell" href="a_holidaysMng.php?active_date=<?php echo htmlspecialchars($cell_date);?>" style="text-decoration:none;">
                                        <div class="day-inner" style="border:1px solid var(--right-border);border-radius:var(--r-sm);padding:10px 8px;min-height:86px; background:<?php echo $is_selected?'#eef2ff':'var(--right-card)';?>; opacity:<?php echo $in_month?1:0.45;?>;">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                                <div style="font-weight:900;font-size:15px;color:var(--right-text);"><?php echo (int)date('j', strtotime($cell_date));?></div>
                                                <?php if($is_holiday):?>
                                                    <div title="<?php echo (int)$hol_count;?> holiday(s)" style="width:11px;height:11px;border-radius:50%;background:#6366f1;"></div>
                                                <?php endif;?>
                                            </div>
                                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;">
                                                <?php if($is_holiday): $n=0; foreach($byDate[$cell_date] as $hh): if($n>=3) break; $n++; ?>
                                                    <div style="font-size:13.75px;color:var(--right-muted);font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                        <i class="fa-solid fa-asterisk" style="font-size:13.75px;color:#6366f1; margin-right:6px;"></i>
                                                        <?php echo htmlspecialchars($hh['holiday_name']);?>
                                                    </div>
                                                <?php endforeach; endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endfor;?>
                            </div>
                        </div>
                    </div>

                    <!-- Day panel -->
                    <div>
                        <div class="card" style="margin:0;">
                            <div class="card-header" style="border-bottom:1px solid var(--right-border);">
                                <div class="card-title"><i class="fa-solid fa-circle-info" style="color:var(--accent);"></i> Holidays on <?php echo htmlspecialchars($active_date);?></div>
                                <div style="font-size:15px;color:var(--right-muted);"> <?php echo isset($byDate[$active_date]) ? count($byDate[$active_date]) : 0;?> item(s)</div>
                            </div>
                            <div class="card-body" style="padding:16px 18px;">
                                <?php if(!isset($byDate[$active_date]) || empty($byDate[$active_date])):?>
                                    <div class="table-empty" style="padding:26px 10px;">
                                        <i class="fa-solid fa-calendar-xmark" style="font-size:44px;color:var(--right-border);"></i>
                                        <p style="margin-top:6px;">No holidays for this date</p>
                                    </div>
                                <?php else:?>
                                    <div style="display:flex;flex-direction:column;gap:12px;">
                                        <?php foreach($byDate[$active_date] as $h):?>
                                            <div style="border:1px solid var(--right-border);border-radius:var(--r-sm);padding:14px 12px;background:#fff;">
                                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                                                    <div>
                                                        <div style="font-size:15px;font-weight:900;color:var(--right-text);">
                                                            <i class="fa-solid fa-flag" style="color:#6366f1;margin-right:8px;"></i>
                                                            <?php echo htmlspecialchars($h['holiday_name']);?>
                                                        </div>
                                                        <?php if(!empty($h['holiday_desc'])):?>
                                                            <div style="margin-top:6px;font-size:14.25px;color:var(--right-muted);">
                                                                <?php echo htmlspecialchars($h['holiday_desc']);?>
                                                            </div>
                                                        <?php endif;?>
                                                    </div>
                                                    <div style="display:flex;gap:8px;">
                                                        <button type="button" onclick="openEditHol(<?php echo htmlspecialchars(json_encode($h));?>)" class="btn btn-ghost btn-sm" title="Edit" style="padding:7px 10px;">
                                                            <i class="fa-solid fa-pencil" style="font-size:13.75px;"></i>
                                                        </button>
                                                        <a href="#" onclick="requestDeleteHoliday(<?php echo (int)$h['holiday_id'];?>); return false;" class="btn btn-danger btn-sm" style="padding:7px 10px;" title="Delete">
                                                            <i class="fa-solid fa-trash" style="font-size:13.75px;"></i>
                                                        </a>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach;?>
                                    </div>
                                <?php endif;?>

                                <div style="margin-top:16px;">
                                    <button class="btn btn-primary" onclick="prefillAdd('<?php echo htmlspecialchars($active_date);?>')"><i class="fa-solid fa-calendar-plus"></i> Add for this date</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Keep list view (optional) -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-list"></i> List of Holidays</div>
                <div style="font-size:15px;color:var(--right-muted);"> <?php echo count($holidays);?> entries</div>
            </div>
            <?php if(empty($holidays)):?>
                <div class="table-empty"><i class="fa-solid fa-calendar-xmark"></i><p>No holidays found</p></div>
            <?php else:?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:80px;">#</th>
                                <th>Holiday Title</th>
                                <th>Description</th>
                                <th style="width:140px;">Date</th>
                                <th style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($holidays as $i=>$h):?>
                                <tr>
                                    <td style="font-size:15px;color:var(--right-dim);"><?php echo (int)$h['holiday_id'];?></td>
                                    <td style="font-weight:600;font-size:15px;color:var(--right-text);"><?php echo htmlspecialchars($h['holiday_name']); ?></td>
                                    <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($h['holiday_desc']??'—'); ?></td>
                                    <td>
                                        <div style="font-weight:600;font-size:15px;color:var(--right-text);"><?php echo date('d M Y',strtotime($h['holiday_date']));?></div>
                                        <div style="font-size:13.75px;color:var(--right-dim);"><?php echo date('l',strtotime($h['holiday_date']));?></div>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <button onclick="openEditHol(<?php echo htmlspecialchars(json_encode($h));?>)" class="btn btn-ghost btn-sm" title="Edit" style="padding:6px 12px;">
                                                <i class="fa-solid fa-pencil" style="font-size:13.75px;"></i>
                                            </button>
                                            <a href="#" onclick="requestDeleteHoliday(<?php echo (int)$h['holiday_id'];?>); return false;" class="btn btn-danger btn-sm" title="Delete" style="padding:6px 12px;">
                                                <i class="fa-solid fa-trash" style="font-size:13.75px;"></i>
                                            </a>

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

<!-- Add Modal -->
<div class="modal-overlay" id="add-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--accent);margin-right:8px;"></i>Add Holiday</div>
            <button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Holiday Title</label>
                    <input type="text" name="holiday_title" id="add-h-title" class="form-control" placeholder="e.g., New Year's Day" required>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" name="holiday_desc" id="add-h-desc" class="form-control" placeholder="Brief description">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="holiday_date" id="add-h-date" class="form-control" required value="<?php echo htmlspecialchars($active_date);?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-hol-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-pencil" style="color:var(--accent);margin-right:8px;"></i>Edit Holiday</div>
            <button class="modal-close" onclick="document.getElementById('edit-hol-modal').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="holiday_id" id="eh-id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Holiday Title</label>
                    <input type="text" name="holiday_title" id="eh-title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="holiday_desc" id="eh-desc" class="form-control">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="holiday_date" id="eh-date" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-hol-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditHol(h){

    document.getElementById('eh-id').value=h.holiday_id;
    document.getElementById('eh-title').value=h.holiday_name;
    document.getElementById('eh-desc').value=h.holiday_desc||'';
    document.getElementById('eh-date').value=h.holiday_date;
    document.getElementById('edit-hol-modal').classList.add('open');
}

function prefillAdd(date){
    const el = document.getElementById('add-h-date');
    if(el) el.value = date;
    document.getElementById('add-modal').classList.add('open');
}

const toast=document.getElementById('toast');
if(toast){
    setTimeout(()=>{toast.style.opacity='0';},3500);
    setTimeout(()=>toast.remove(),4000);
}
</script>

<!-- Delete Confirm Modal (Holiday) -->
<div class="modal-overlay" id="delete-hol-modal" style="z-index:10050;">
    <div class="modal" style="width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--accent);margin-right:8px;"></i>Delete Holiday</div>
            <button class="modal-close" onclick="closeDeleteHolModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p id="delete-hol-text" style="margin:0;color:var(--right-muted);font-size:15px;">Are you sure you want to delete this holiday?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeDeleteHolModal()">No</button>
            <a id="delete-hol-yes" class="btn btn-danger" href="#" style="text-decoration:none;">Yes, Delete</a>
        </div>
    </div>
</div>

<script>
let deleteHolId = 0;
function requestDeleteHoliday(holidayId){
    deleteHolId = holidayId;
    const txt = document.getElementById('delete-hol-text');
    if(txt){ txt.innerHTML = 'Are you sure you want to delete this holiday (ID: <strong>'+holidayId+'</strong>)?'; }
    const yesBtn = document.getElementById('delete-hol-yes');
    if(yesBtn){ yesBtn.href = '?delete=' + encodeURIComponent(String(holidayId)); }
    const modal = document.getElementById('delete-hol-modal');
    if(modal){ modal.classList.add('open'); }
}
function closeDeleteHolModal(){
    const modal = document.getElementById('delete-hol-modal');
    if(modal){ modal.classList.remove('open'); }
}
</script>

<style>
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
.toast-error {
    background:#fff5f5;
    color:#b91c1c;
    border:1px solid #fecaca;
}

.day-inner:hover{ border-color:#c7d2fe; }
</style>
</body></html>