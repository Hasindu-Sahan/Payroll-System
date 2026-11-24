<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();

$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$pending_leaves = 0;
$pendingLeavesRes = $conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'");
if ($pendingLeavesRes !== false) {
    $row = $pendingLeavesRes->fetch_assoc();
    $pending_leaves = (int)(($row['c'] ?? 0));
}

$admin_role=$admin['admin_role'];

// Only Owner and Payroll Manager can access
if($admin_role!=='owner' && $admin_role!=='payroll_manager'){
    header('Location: a_dashboard.php');
    exit;
}

$msg='';$msg_type='';

// Update Employee Salary & Allowances
if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='update_salary'){
    $emp_code=$_POST['emp_code']??'';
    $basic_salary=(float)($_POST['basic_salary']??0);
    $allowance_target=(float)($_POST['allowance_target']??0);
    $allowance_attendance=(float)($_POST['allowance_attendance']??0);
    $allowance_meal=(float)($_POST['allowance_meal']??0);
    
    if($emp_code && $basic_salary>=0){
        $upd=$conn->prepare("UPDATE employees SET basic_salary=?, allowance_target=?, allowance_attendance=?, allowance_meal=? WHERE emp_code=?");
        $upd->bind_param("dddds",$basic_salary,$allowance_target,$allowance_attendance,$allowance_meal,$emp_code);
        if($upd->execute()){
            $msg='Salary and allowances updated successfully!';
            $msg_type='success';
        }else{
            $msg='Failed to update salary';
            $msg_type='error';
        }
        $upd->close();
    }
}

// Process Salary Increment
if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='process_increment'){
    $emp_code=$_POST['emp_code']??'';
    $current_salary=(float)($_POST['current_salary']??0);
    $increment_amount=(float)($_POST['increment_amount']??0);
    $increment_percentage=(float)($_POST['increment_percentage']??0);
    $effective_date=$_POST['effective_date']??'';
    $remarks=$_POST['remarks']??'';
    
    if($emp_code && $current_salary>0 && ($increment_amount>0 || $increment_percentage>0) && $effective_date){
        if($increment_amount>0){
            $new_salary=$current_salary+$increment_amount;
        }else{
            $new_salary=$current_salary+($current_salary*$increment_percentage/100);
        }
        
        $hist=$conn->prepare("INSERT INTO salary_increments (emp_code, previous_salary, increment_amount, increment_percentage, new_salary, effective_date, remarks, processed_by) VALUES (?,?,?,?,?,?,?,?)");
        $hist->bind_param("sddddsss",$emp_code,$current_salary,$increment_amount,$increment_percentage,$new_salary,$effective_date,$remarks,$admin_code);
        $hist->execute();
        $hist->close();
        
        $upd=$conn->prepare("UPDATE employees SET basic_salary=? WHERE emp_code=?");
        $upd->bind_param("ds",$new_salary,$emp_code);
        if($upd->execute()){
            $msg='Salary increment processed successfully! New salary: LKR '.number_format($new_salary,2);
            $msg_type='success';
        }
        $upd->close();
    }
}

// Generate Monthly Payroll
if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='generate_payroll'){
    $month=$_POST['month']??'';
    $year=$_POST['year']??'';
    
    if($month && $year){
        $pay_month="$year-".str_pad($month,2,'0',STR_PAD_LEFT);
        $employees=$conn->query("SELECT * FROM employees WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
        
        // Get holidays for the month
        $first_day="$year-".str_pad($month,2,'0',STR_PAD_LEFT)."-01";
        $last_day=date("Y-m-t",strtotime($first_day));
        $holidays_result=$conn->query("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN '$first_day' AND '$last_day'");
        $holiday_dates=[];
if ($holidays_result !== false) {
            while($h=$holidays_result->fetch_assoc()){
                $holiday_dates[]=$h['holiday_date'];
            }
        }
        
        foreach($employees as $emp){
            $emp_code=$emp['emp_code'];
            $basic_salary=$emp['basic_salary'];
            $allowance_target=$emp['allowance_target']??0;
            $allowance_attendance=$emp['allowance_attendance']??0;
            $allowance_meal=$emp['allowance_meal']??0;
            $employment_type=$emp['employment_type'];

            // Calculate OT Payment
            // OT Rate = (Basic Salary / 26) / 8
            $ot_hourly_rate=($basic_salary/26)/8;
            $ot_rate_per_second=$ot_hourly_rate/3600;

            // Get attendance records for the month
            $att_query=$conn->query("
                SELECT attendance_date, time_in, time_out 
                FROM attendance 
                WHERE emp_code='$emp_code' 
                AND MONTH(attendance_date)=$month 
                AND YEAR(attendance_date)=$year
                AND status='present'
                AND time_in IS NOT NULL 
                AND time_out IS NOT NULL
            ");

            $total_ot_payment=0;

            if($att_query !== false){
                while($att=$att_query->fetch_assoc()){
                    $date=$att['attendance_date'];
                    $time_in=strtotime($att['time_in']);
                    $time_out=strtotime($att['time_out']);

                    if($time_out>$time_in){
                        // Calculate OT hours after 5 PM
                        $date_obj=date('Y-m-d',strtotime($date));
                        $cutoff_time=strtotime("$date_obj 17:00:00");

                        $ot_seconds=0;
                        if($time_out>$cutoff_time){
                            if($time_in>=$cutoff_time){
                                $ot_seconds=$time_out-$time_in;
                            }else{
                                $ot_seconds=$time_out-$cutoff_time;
                            }
                        }

                        if($ot_seconds>0){
                            // Check if it's weekend (Saturday/Sunday) or holiday
                            $day_of_week=date('N',strtotime($date)); // 1=Monday, 7=Sunday
                            $is_weekend=($day_of_week==6 || $day_of_week==7); // Saturday or Sunday
                            $is_holiday=in_array($date,$holiday_dates);

                            $multiplier=($is_weekend || $is_holiday)?2:1;

                            $ot_payment=$ot_seconds*$ot_rate_per_second*$multiplier;
                            $total_ot_payment+=$ot_payment;
                        }
                    }
                }
            }

            // Total earnings
            $total_allowances=$allowance_target+$allowance_attendance+$allowance_meal;
            $gross_salary=$basic_salary+$total_allowances+$total_ot_payment;
            
            // Calculate EPF & ETF only for permanent workers
            $epf_employee=0;
            $epf_employer=0;
            $etf_employer=0;
            
            if($employment_type==='permanent'){
                $epf_employee=round($basic_salary*0.08,2);
                $epf_employer=round($basic_salary*0.12,2);
                $etf_employer=round($basic_salary*0.03,2);
            }
            
            $total_deductions=$epf_employee;
            $net_salary=$gross_salary-$total_deductions;
            
            // Insert or update payroll record
            $check_sql = "SELECT payroll_id FROM payroll WHERE emp_code=? AND pay_period_month=? AND pay_period_year=?";
            $check=$conn->prepare($check_sql);
            if($check===false){
                $msg='Failed to prepare payroll check query: '.htmlspecialchars($check_sql).' | '.$conn->error;
                $msg_type='error';
                break;
            }
            $check->bind_param("sii",$emp_code,$month,$year);
            if(!$check->execute()){
                $msg='Failed to execute payroll check query: '.htmlspecialchars($check_sql).' | '.$conn->error;
                $msg_type='error';
                $check->close();
                break;
            }
            $res = $check->get_result();
            $exists = $res ? $res->fetch_assoc() : null;
            $check->close();
            
            if($exists){
                $upd_sql = "UPDATE payroll SET basic_salary=?, allowance_target=?, allowance_attendance=?, allowance_meal=?, ot_payment=?, total_earnings=?, total_deductions=?, net_salary=?, epf_employee=?, epf_employer=?, etf_employer=? WHERE emp_code=? AND pay_period_month=? AND pay_period_year=?";
                $upd=$conn->prepare($upd_sql);
                if($upd===false){
                    $msg='Failed to prepare payroll update query: '.htmlspecialchars($upd_sql).' | '.$conn->error;
                    $msg_type='error';
                    break;
                }
                $upd->bind_param("dddddddddddsi",$basic_salary,$allowance_target,$allowance_attendance,$allowance_meal,$total_ot_payment,$gross_salary,$total_deductions,$net_salary,$epf_employee,$epf_employer,$etf_employer,$emp_code,$month,$year);
                if(!$upd->execute()){
                    $msg='Failed to execute payroll update query: '.htmlspecialchars($upd_sql).' | '.$conn->error;
                    $msg_type='error';
                    $upd->close();
                    break;
                }
                $upd->close();
            }else{
                $ins_sql = "INSERT INTO payroll (emp_code, pay_period_month, pay_period_year, basic_salary, allowance_target, allowance_attendance, allowance_meal, ot_payment, total_earnings, total_deductions, net_salary, epf_employee, epf_employer, etf_employer) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $ins=$conn->prepare($ins_sql);
                if($ins===false){
                    $msg='Failed to prepare payroll insert query: '.htmlspecialchars($ins_sql).' | '.$conn->error;
                    $msg_type='error';
                    break;
                }
                $ins->bind_param("siiddddddddddd",$emp_code,$month,$year,$basic_salary,$allowance_target,$allowance_attendance,$allowance_meal,$total_ot_payment,$gross_salary,$total_deductions,$net_salary,$epf_employee,$epf_employer,$etf_employer);
                if(!$ins->execute()){
                    $msg='Failed to execute payroll insert query: '.htmlspecialchars($ins_sql).' | '.$conn->error;
                    $msg_type='error';
                    $ins->close();
                    break;
                }
                $ins->close();
            }
        }
        
        $msg="Payroll generated for $pay_month successfully!";
        $msg_type='success';
    }
}

// Fetch data
$employees=$conn->query("SELECT emp_code,first_name,last_name,designation,section,basic_salary,allowance_target,allowance_attendance,allowance_meal,employment_type FROM employees WHERE status='active' ORDER BY emp_code")->fetch_all(MYSQLI_ASSOC);



$filter_month=trim($_GET['month']??'');
$filter_year=trim($_GET['year']??'');
$where_parts=[];
if($filter_month)$where_parts[]="p.pay_period_month=".(int)$filter_month;
if($filter_year)$where_parts[]="p.pay_period_year=".(int)$filter_year;
$where=count($where_parts)?' WHERE '.implode(' AND ',$where_parts):'';

$payroll_records=$conn->query("
    SELECT p.*, e.first_name, e.last_name, e.designation, e.section, e.employment_type
    FROM payroll p
    LEFT JOIN employees e ON e.emp_code=p.emp_code
    $where
    ORDER BY p.pay_period_year DESC, p.pay_period_month DESC, p.emp_code
")->fetch_all(MYSQLI_ASSOC);

$salary_increments=$conn->query("
    SELECT si.*, e.first_name, e.last_name, e.designation
    FROM salary_increments si
    LEFT JOIN employees e ON e.emp_code=si.emp_code
    ORDER BY si.effective_date DESC, si.increment_id DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$increment_year_rows = $conn->query("
    SELECT YEAR(effective_date) AS inc_year, SUM(increment_amount) AS total_amount, COUNT(*) AS total_count
    FROM salary_increments
    GROUP BY YEAR(effective_date)
    ORDER BY inc_year ASC
");
$increment_year_data = $increment_year_rows ? $increment_year_rows->fetch_all(MYSQLI_ASSOC) : [];

$page_title='Payroll Management';$active_page='Payroll Management';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Payroll Management</h1><p>Manage salaries, allowances, and increments</p></div>
        <div class="topbar-actions">
            <button onclick="document.getElementById('gen-payroll-modal').classList.add('open')" class="btn btn-primary" style="margin-right:8px;">
                <i class="fa-solid fa-calendar-check"></i> Generate Monthly Payroll
            </button>
            <span class="tb-date"><?php echo date('D, d M Y');?></span>
        </div>
    </header>
    
    <div class="page page-anim">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="a_dashboard.php">Dashboard</a> 
                    <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> 
                    Payroll Management
                </div>
                <h2>Employee Salary Management</h2>
            </div>
        </div>

        <?php if($msg):?>
        <div class="toast <?php echo $msg_type==='success'?'toast-success':'toast-error';?>" id="toast">
            <i class="fa-solid <?php echo $msg_type==='success'?'fa-circle-check':'fa-circle-xmark';?>"></i> 
            <?php echo htmlspecialchars($msg);?>
        </div>
        <?php endif;?>

        <!-- Info Box -->
        <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:var(--r);padding:16px 20px;margin-bottom:24px;display:flex;gap:12px;align-items:start;">
            <i class="fa-solid fa-info-circle" style="color:var(--accent);font-size:20px;margin-top:2px;"></i>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:16.25px;color:var(--accent);margin-bottom:6px;">OT Calculation & EPF/ETF Rules</div>
                <ul style="margin:0;padding-left:20px;font-size:15px;color:var(--accent);line-height:1.8;">
                    <li><strong>OT Rate Formula:</strong> (Basic Salary ÷ 26) ÷ 8 per hour</li>
                    <li><strong>Double OT:</strong> Saturday, Sunday, and Public Holidays = 2× normal OT rate</li>
                    <li><strong>OT Time:</strong> Calculated in seconds (e.g., 45min 30sec = accurate payment)</li>
                    <li><strong>EPF (8%) & ETF/EPF Employer:</strong> Applied to <strong>permanent workers only</strong></li>
                    <li><strong>Temporary Workers:</strong> No EPF/ETF deductions</li>
                </ul>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card" style="margin-bottom:24px;">
            <div style="display:flex;border-bottom:1px solid var(--right-border);">
                <button onclick="switchTab('salary-tab','salary-panel')" id="salary-tab" class="tab-btn active">
                    <i class="fa-solid fa-money-bill-wave"></i> Salary & Allowances
                </button>
                <button onclick="switchTab('payroll-tab','payroll-panel')" id="payroll-tab" class="tab-btn">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Payroll Records
                </button>
                <button onclick="switchTab('increment-tab','increment-panel')" id="increment-tab" class="tab-btn">
                    <i class="fa-solid fa-arrow-trend-up"></i> Salary Increments
                </button>
            </div>

            <!-- Tab 1: Salary & Allowances -->
            <div id="salary-panel" class="tab-panel">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-users-gear"></i> Employee Salary & Allowances</div>
                    <div style="font-size:15px;color:var(--right-muted);"><?php echo count($employees);?> employees</div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Section</th>
                                <th>Type</th>
                                <th>Basic Salary</th>
                                <th>OT Rate/Hour</th>
                                <th>Target</th>
                                <th>Attendance</th>
                                <th>Meal</th>
                                <th>Total Package</th>
                                <th style="width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($employees as $emp):
                            $ot_rate=($emp['basic_salary']/26)/8;
                            $total_package=$emp['basic_salary']+($emp['allowance_target']??0)+($emp['allowance_attendance']??0)+($emp['allowance_meal']??0);
                        ?>
                        <tr>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-ava">
                                        <?php echo initials($emp['first_name'],$emp['last_name']);?>
                                    </div>
                                    <div>
                                        <div class="emp-name">
                                            <?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']);?>
                                        </div>
                                        <div class="emp-desig"><?php echo $emp['emp_code'];?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:15px;color:var(--right-muted);">
                                <?php echo htmlspecialchars($emp['designation']);?>
                            </td>
                            <td style="font-size:15px;color:var(--right-muted);">
                                <?php echo htmlspecialchars($emp['section']);?>
                            </td>
                            <td>
                                <span class="badge <?php echo $emp['employment_type']==='permanent'?'badge-active':'badge-inactive';?>">
                                    <?php echo ucfirst($emp['employment_type']);?>
                                </span>
                            </td>
                            <td style="font-weight:600;font-size:15px;color:var(--right-text);">
                                LKR <?php echo number_format($emp['basic_salary'],2);?>
                            </td>
                            <td style="font-size:14.375px;color:var(--accent);font-family:'JetBrains Mono',monospace;">
                                LKR <?php echo number_format($ot_rate,2);?>
                            </td>
                            <td style="font-size:15px;color:var(--success);">
                                LKR <?php echo number_format($emp['allowance_target']??0,2);?>
                            </td>
                            <td style="font-size:15px;color:var(--success);">
                                LKR <?php echo number_format($emp['allowance_attendance']??0,2);?>
                            </td>
                            <td style="font-size:15px;color:var(--success);">
                                LKR <?php echo number_format($emp['allowance_meal']??0,2);?>
                            </td>
                            <td>
                                <strong style="font-size:16.25px;color:var(--accent);">
                                    LKR <?php echo number_format($total_package,2);?>
                                </strong>
                            </td>
                            <td>
                                <div style="display:flex;gap:5px;">
                                    <button onclick="editSalary(<?php echo htmlspecialchars(json_encode($emp));?>)" class="btn btn-ghost btn-sm" title="Edit">
                                        <i class="fa-solid fa-pencil"></i>
                                    </button>
                                    <button onclick="incrementSalary(<?php echo htmlspecialchars(json_encode($emp));?>)" class="btn btn-primary btn-sm" title="Increment">
                                        <i class="fa-solid fa-arrow-trend-up"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Payroll Records -->
            <div id="payroll-panel" class="tab-panel" style="display:none;">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-file-invoice-dollar"></i> Payroll Records</div>
                    <div style="font-size:15px;color:var(--right-muted);"><?php echo count($payroll_records);?> records</div>
                </div>
                
                <!-- Filter -->
                <div class="card-body" style="border-bottom:1px solid var(--right-border);">
                    <form method="GET" style="display:flex;gap:12px;align-items:end;">
                        <div class="form-group" style="margin:0;">
                            <label>Month</label>
                            <select name="month" class="form-control">
                                <option value="">All</option>
                                <?php for($m=1;$m<=12;$m++):?>
                                <option value="<?php echo $m;?>" <?php echo $filter_month==(string)$m?'selected':'';?>>
                                    <?php echo date('F',mktime(0,0,0,$m,1));?>
                                </option>
                                <?php endfor;?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Year</label>
                            <select name="year" class="form-control">
                                <option value="">All</option>
                                <?php for($y=2024;$y<=2030;$y++):?>
                                <option value="<?php echo $y;?>" <?php echo $filter_year==(string)$y?'selected':'';?>>
                                    <?php echo $y;?>
                                </option>
                                <?php endfor;?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <a href="a_payrollMng.php" class="btn btn-ghost">Reset</a>
                    </form>
                </div>

                <?php if(empty($payroll_records)):?>
                <div class="table-empty"><i class="fa-solid fa-file-invoice"></i><p>No payroll records found</p></div>
                <?php else:?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Type</th>
                                <th>Basic</th>
                                <th>Allowances</th>
                                <th>OT Payment</th>
                                <th>Gross</th>
                                <th>EPF (8%)</th>
                                <th>Net Salary</th>
                                <th>EPF (ER)</th>
                                <th>ETF</th>
                                <th> Payslip </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($payroll_records as $pr):
                            $total_allowances=($pr['allowance_target']??0)+($pr['allowance_attendance']??0)+($pr['allowance_meal']??0);
                        ?>
                        <tr>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-ava" style="width:32px;height:32px;font-size:12.5px;">
                                        <?php echo initials($pr['first_name']??'?',$pr['last_name']??'');?>
                                    </div>
                                    <div>
                                        <div class="emp-name" style="font-size:15px;">
                                            <?php echo htmlspecialchars(($pr['first_name']??'').' '.($pr['last_name']??''));?>
                                        </div>
                                        <div class="emp-desig"><?php echo $pr['emp_code'];?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight:600;font-size:15px;">
                                <?php echo date('M Y',mktime(0,0,0,$pr['pay_period_month'],1,$pr['pay_period_year']));?>
                            </td>
                            <td>
                                <span class="badge <?php echo $pr['employment_type']==='permanent'?'badge-active':'badge-inactive';?>" style="font-size:12.5px;">
                                    <?php echo ucfirst($pr['employment_type']);?>
                                </span>
                            </td>
                            <td style="font-size:15px;">
                                LKR <?php echo number_format($pr['basic_salary'],2);?>
                            </td>
                            <td style="font-size:15px;color:var(--success);">
                                LKR <?php echo number_format($total_allowances,2);?>
                            </td>
                            <td style="font-size:15px;color:#d97706;font-weight:600;">
                                LKR <?php echo number_format($pr['ot_payment']??0,2);?>
                            </td>
                            <td style="font-weight:600;font-size:15px;">
                                LKR <?php echo number_format($pr['total_earnings'],2);?>
                            </td>
                            <td style="font-size:15px;color:var(--danger);">
                                <?php if($pr['epf_employee']>0):?>
                                LKR <?php echo number_format($pr['epf_employee'],2);?>
                                <?php else:?>
                                <span style="color:var(--right-dim);">—</span>
                                <?php endif;?>
                            </td>
                            <td>
                                <strong style="font-size:16.25px;color:var(--success);">
                                    LKR <?php echo number_format($pr['net_salary'],2);?>
                                </strong>
                            </td>
                            <td style="font-size:14.375px;color:var(--right-muted);">
                                <?php if($pr['epf_employer']>0):?>
                                LKR <?php echo number_format($pr['epf_employer'],2);?>
                                <?php else:?>
                                <span style="color:var(--right-dim);">—</span>
                                <?php endif;?>
                            </td>
                            <td style="font-size:14.375px;color:var(--right-muted);">
                                <?php if($pr['etf_employer']>0):?>
                                LKR <?php echo number_format($pr['etf_employer'],2);?>
                                <?php else:?>
                                <span style="color:var(--right-dim);">—</span>
                                <?php endif;?>
                            </td>
                            <td>
                                <?php if($admin_role === 'owner' && strtoupper($admin_code) === 'A001'): ?>
                                <a href="e_payments.php?emp_code=<?php echo rawurlencode($pr['emp_code']); ?>" class="btn btn-ghost btn-sm" title="View payslip">
                                    <i class="fa-solid fa-file-invoice-dollar"></i> View
                                </a>
                                <?php else: ?>
                                <span style="color:var(--right-dim);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
                <?php endif;?>
            </div>

            <!-- Tab 3: Salary Increments -->
            <div id="increment-panel" class="tab-panel" style="display:none;">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-line"></i> Salary Increment History</div>
                    <div style="font-size:15px;color:var(--right-muted);"><?php echo count($salary_increments);?> records</div>
                </div>
                <div style="padding:20px;border-bottom:1px solid var(--right-border);background:#f8fafd;">
                    <?php
                    $yearPoints = [];
                    $yearLabels = [];
                    $yearValues = [];
                    foreach ($increment_year_data as $row) {
                        $yearPoints[] = [
                            'year' => (string)($row['inc_year'] ?? ''),
                            'value' => (float)($row['total_amount'] ?? 0),
                            'count' => (int)($row['total_count'] ?? 0),
                        ];
                        $yearLabels[] = (string)($row['inc_year'] ?? '');
                        $yearValues[] = (float)($row['total_amount'] ?? 0);
                    }
                    $maxYearValue = max(1, max($yearValues ?: [0]));
                    $pointCount = count($yearPoints);
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
                        <div>
                            <div style="font-size:17px;font-weight:800;color:#1a2740;">Year-wise Salary Increment</div>
                            <div style="font-size:14px;color:#64748b;">Total increment amount per year</div>
                        </div>
                        <div style="font-size:14px;color:#64748b;"><?php echo $pointCount; ?> year<?php echo $pointCount===1 ? '' : 's'; ?></div>
                    </div>
                    <div style="background:#fff;border:1px solid #e1e8f0;border-radius:10px;padding:14px;">
                        <?php if(empty($yearPoints)): ?>
                            <div class="table-empty" style="padding:20px 10px;"><i class="fa-solid fa-arrow-trend-up"></i><p>No increment data for chart.</p></div>
                        <?php else: ?>
                            <svg viewBox="0 0 700 260" width="100%" height="260" preserveAspectRatio="none" role="img" aria-label="Year-wise salary increment chart">
                                <defs>
                                    <linearGradient id="incLineGrad" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#2563eb" stop-opacity="0.25"></stop>
                                        <stop offset="100%" stop-color="#2563eb" stop-opacity="0"></stop>
                                    </linearGradient>
                                </defs>
                                <?php for($i=0;$i<=4;$i++): $y=220-($i*40); $label=number_format($maxYearValue*($i/4),0); ?>
                                    <line x1="50" y1="<?php echo $y; ?>" x2="670" y2="<?php echo $y; ?>" stroke="#eef2f7" stroke-width="1"></line>
                                    <text x="18" y="<?php echo $y+4; ?>" font-size="12" fill="#94a3b8"><?php echo $label; ?></text>
                                <?php endfor; ?>
                                <?php
                                $points = [];
                                $areaPoints = ['50,220'];
                                $countPts = max(1, count($yearPoints) - 1);
                                foreach ($yearPoints as $idx => $pt) {
                                    $x = 50 + ($countPts === 0 ? 0 : ($idx * 620 / $countPts));
                                    $val = (float)$pt['value'];
                                    $y = 220 - (($val / $maxYearValue) * 180);
                                    $points[] = $x . ',' . $y;
                                    $areaPoints[] = $x . ',' . $y;
                                    $areaPoints[] = '';
                                }
                                $areaPoints[] = '670,220';
                                $areaPath = implode(' ', array_filter($areaPoints, fn($v) => $v !== ''));
                                ?>
                                <polyline fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="<?php echo htmlspecialchars(implode(' ', $points)); ?>"></polyline>
                                <polygon fill="url(#incLineGrad)" points="<?php echo htmlspecialchars(implode(' ', array_merge($points, ['670,220', '50,220']))); ?>"></polygon>
                                <?php foreach ($yearPoints as $idx => $pt):
                                    $x = 50 + ($countPts === 0 ? 0 : ($idx * 620 / $countPts));
                                    $val = (float)$pt['value'];
                                    $y = 220 - (($val / $maxYearValue) * 180);
                                ?>
                                    <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="5" fill="#2563eb"></circle>
                                    <text x="<?php echo $x; ?>" y="242" text-anchor="middle" font-size="12" fill="#64748b"><?php echo htmlspecialchars($pt['year']); ?></text>
                                <?php endforeach; ?>
                            </svg>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;">
                                <?php foreach($yearPoints as $pt): ?>
                                    <div style="padding:8px 10px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;font-size:13px;color:#1d4ed8;font-weight:700;">
                                        <?php echo htmlspecialchars($pt['year']); ?>: LKR <?php echo number_format($pt['value'],2); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if(empty($salary_increments)):?>
                <div class="table-empty"><i class="fa-solid fa-arrow-trend-up"></i><p>No increment records found</p></div>
                <?php else:?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Previous Salary</th>
                                <th>Increment</th>
                                <th>New Salary</th>
                                <th>Effective Date</th>
                                <th>Remarks</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($salary_increments as $si):?>
                        <tr>
                            <td>
                                <div class="emp-cell">
                                    <div class="emp-ava" style="width:32px;height:32px;font-size:12.5px;">
                                        <?php echo initials($si['first_name']??'?',$si['last_name']??'');?>
                                    </div>
                                    <div>
                                        <div class="emp-name" style="font-size:15px;">
                                            <?php echo htmlspecialchars(($si['first_name']??'').' '.($si['last_name']??''));?>
                                        </div>
                                        <div class="emp-desig"><?php echo $si['emp_code'];?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:15px;color:var(--right-muted);">
                                <?php echo htmlspecialchars($si['designation']??'—');?>
                            </td>
                            <td style="font-size:15px;color:var(--right-text);">
                                LKR <?php echo number_format($si['previous_salary'],2);?>
                            </td>
                            <td>
                                <?php if($si['increment_amount']>0):?>
                                <span style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:20px;font-size:13.75px;font-weight:700;">
                                    +LKR <?php echo number_format($si['increment_amount'],2);?>
                                </span>
                                <?php endif;?>
                                <?php if($si['increment_percentage']>0):?>
                                <span style="background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:20px;font-size:13.75px;font-weight:700;">
                                    +<?php echo number_format($si['increment_percentage'],2);?>%
                                </span>
                                <?php endif;?>
                            </td>
                            <td>
                                <strong style="font-size:16.25px;color:var(--success);">
                                    LKR <?php echo number_format($si['new_salary'],2);?>
                                </strong>
                            </td>
                            <td style="font-size:15px;color:var(--right-text);">
                                <?php echo date('d M Y',strtotime($si['effective_date']));?>
                            </td>
                            <td style="font-size:15px;color:var(--right-muted);">
                                <?php echo htmlspecialchars($si['remarks']??'—');?>
                            </td>
                            <td style="font-size:14.375px;color:var(--right-dim);">
                                <?php echo htmlspecialchars($si['processed_by']);?>
                            </td>
                        </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                </div>
                <?php endif;?>
            </div>
        </div>
    </div>

    <!-- Edit Salary Modal -->
    <div class="modal-overlay" id="edit-salary-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-pencil"></i> Edit Salary & Allowances</div>
                <button class="modal-close" onclick="closeModal('edit-salary-modal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_salary">
                <input type="hidden" name="emp_code" id="es-emp-code">
                <div class="modal-body">
                    <div style="background:#f8fafd;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;">
                        <div style="font-size:17.5px;font-weight:700;color:var(--right-text);" id="es-emp-name"></div>
                        <div style="font-size:14.375px;color:var(--right-muted);margin-top:2px;" id="es-emp-desig"></div>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--right-border);">
                            <div style="font-size:13.75px;color:var(--right-dim);margin-bottom:4px;">Calculated OT Rate (per hour)</div>
                            <div style="font-size:20px;font-weight:700;color:var(--accent);" id="es-ot-rate">LKR 0.00</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Basic Salary (LKR) *</label>
                        <input type="number" name="basic_salary" id="es-basic" class="form-control" step="0.01" min="0" required onchange="updateOTRate()">
                        <div style="font-size:13.75px;color:var(--right-dim);margin-top:4px;">
                            <i class="fa-solid fa-info-circle"></i> OT Rate = (Basic Salary ÷ 26) ÷ 8 | EPF & ETF calculated on basic salary (permanent workers only)
                        </div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                        <div class="form-group">
                            <label>Target Achievement Allowance</label>
                            <input type="number" name="allowance_target" id="es-target" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Attendance Allowance</label>
                            <input type="number" name="allowance_attendance" id="es-attendance" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Meal Allowance</label>
                            <input type="number" name="allowance_meal" id="es-meal" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('edit-salary-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Increment Salary Modal -->
    <div class="modal-overlay" id="increment-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-arrow-trend-up"></i> Process Salary Increment</div>
                <button class="modal-close" onclick="closeModal('increment-modal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" onsubmit="return validateIncrement()">
                <input type="hidden" name="action" value="process_increment">
                <input type="hidden" name="emp_code" id="inc-emp-code">
                <input type="hidden" name="current_salary" id="inc-current-salary">
                <div class="modal-body">
                    <div style="background:#f8fafd;padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;">
                        <div style="font-size:17.5px;font-weight:700;color:var(--right-text);" id="inc-emp-name"></div>
                        <div style="font-size:14.375px;color:var(--right-muted);margin-top:2px;" id="inc-emp-desig"></div>
                    </div>
                    
                    <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:var(--r-sm);padding:14px;margin-bottom:18px;">
                        <div style="font-size:13.75px;color:var(--accent);margin-bottom:5px;">Current Basic Salary</div>
                        <div style="font-size:25px;font-weight:800;color:var(--accent);" id="inc-current-display"></div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                        <div class="form-group">
                            <label>Increment Amount (LKR)</label>
                            <input type="number" name="increment_amount" id="inc-amount" class="form-control" step="0.01" min="0" value="0" oninput="calculateNewSalary()">
                        </div>
                        <div class="form-group">
                            <label>Increment Percentage (%)</label>
                            <input type="number" name="increment_percentage" id="inc-percentage" class="form-control" step="0.01" min="0" max="100" value="0" oninput="calculateNewSalary()">
                        </div>
                    </div>
                    
                    <div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:var(--r-sm);padding:14px;margin-bottom:18px;">
                        <div style="font-size:13.75px;color:var(--success);margin-bottom:5px;">New Basic Salary</div>
                        <div style="font-size:25px;font-weight:800;color:var(--success);" id="inc-new-display">LKR 0.00</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Effective Date *</label>
                        <input type="date" name="effective_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Reason for increment (e.g., Annual increment, Promotion, Performance bonus)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('increment-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Process Increment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Generate Payroll Modal -->
    <div class="modal-overlay" id="gen-payroll-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-calendar-check"></i> Generate Monthly Payroll</div>
                <button class="modal-close" onclick="closeModal('gen-payroll-modal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="generate_payroll">
                <div class="modal-body">
                    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--r-sm);padding:12px 16px;margin-bottom:18px;font-size:14.375px;color:#92400e;">
                        <i class="fa-solid fa-triangle-exclamation"></i> This will generate payroll for all active employees including OT calculation based on attendance records. Existing records will be updated.
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="form-group">
                            <label>Month *</label>
                            <select name="month" class="form-control" required>
                                <option value="">Select Month</option>
                                <?php for($m=1;$m<=12;$m++):?>
                                <option value="<?php echo $m;?>" <?php echo $m==date('n')?'selected':'';?>>
                                    <?php echo date('F',mktime(0,0,0,$m,1));?>
                                </option>
                                <?php endfor;?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year *</label>
                            <select name="year" class="form-control" required>
                                <?php for($y=2024;$y<=2030;$y++):?>
                                <option value="<?php echo $y;?>" <?php echo $y==date('Y')?'selected':'';?>>
                                    <?php echo $y;?>
                                </option>
                                <?php endfor;?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('gen-payroll-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-play"></i> Generate Payroll</button>
                </div>
            </form>
        </div>
    </div>

    <footer><strong>&copy; <?php echo date('Y');?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>

<style>
.tab-btn {
    padding:16px 24px;
    border:none;
    background:none;
    font-family:'Outfit',sans-serif;
    font-size:16.25px;
    font-weight:600;
    color:var(--right-muted);
    cursor:pointer;
    border-bottom:2px solid transparent;
    transition:all .18s;
}
.tab-btn:hover {
    color:var(--right-text);
}
.tab-btn.active {
    color:var(--accent);
    border-bottom-color:var(--accent);
}
.tab-btn i {
    margin-right:7px;
}
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
</style>

<script>
function switchTab(tabId, panelId) {
    document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.getElementById(tabId).classList.add('active');
    document.getElementById(panelId).style.display = 'block';
}

function editSalary(emp) {
    document.getElementById('es-emp-code').value = emp.emp_code;
    document.getElementById('es-emp-name').textContent = emp.first_name + ' ' + emp.last_name;
    document.getElementById('es-emp-desig').textContent = emp.designation + ' • ' + emp.section;
    document.getElementById('es-basic').value = emp.basic_salary;
    document.getElementById('es-target').value = emp.allowance_target || 0;
    document.getElementById('es-attendance').value = emp.allowance_attendance || 0;
    document.getElementById('es-meal').value = emp.allowance_meal || 0;
    updateOTRate();
    document.getElementById('edit-salary-modal').classList.add('open');
}

function updateOTRate() {
    const basic = parseFloat(document.getElementById('es-basic').value) || 0;
    const otRate = (basic / 26) / 8;
    document.getElementById('es-ot-rate').textContent = 'LKR ' + otRate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function incrementSalary(emp) {
    document.getElementById('inc-emp-code').value = emp.emp_code;
    document.getElementById('inc-current-salary').value = emp.basic_salary;
    document.getElementById('inc-emp-name').textContent = emp.first_name + ' ' + emp.last_name;
    document.getElementById('inc-emp-desig').textContent = emp.designation + ' • ' + emp.section;
    document.getElementById('inc-current-display').textContent = 'LKR ' + parseFloat(emp.basic_salary).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('inc-amount').value = 0;
    document.getElementById('inc-percentage').value = 0;
    document.getElementById('inc-new-display').textContent = 'LKR 0.00';
    document.getElementById('increment-modal').classList.add('open');
}

function calculateNewSalary() {
    const current = parseFloat(document.getElementById('inc-current-salary').value) || 0;
    const amount = parseFloat(document.getElementById('inc-amount').value) || 0;
    const percentage = parseFloat(document.getElementById('inc-percentage').value) || 0;
    
    let newSalary = current;
    if (amount > 0) {
        newSalary = current + amount;
    } else if (percentage > 0) {
        newSalary = current + (current * percentage / 100);
    }
    
    document.getElementById('inc-new-display').textContent = 'LKR ' + newSalary.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function validateIncrement() {
    const amount = parseFloat(document.getElementById('inc-amount').value) || 0;
    const percentage = parseFloat(document.getElementById('inc-percentage').value) || 0;
    
    if (amount === 0 && percentage === 0) {
        alert('Please enter either increment amount or percentage');
        return false;
    }
    
    return confirm('Are you sure you want to process this salary increment?');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => { toast.style.opacity = '0'; }, 3500);
    setTimeout(() => toast.remove(), 4000);
}
</script>
</body></html>
