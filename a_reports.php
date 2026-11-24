<?php
session_start();
if (!isset($_SESSION['Login_Type']) || $_SESSION['Login_Type'] !== 'admin') { header('Location: login.php'); exit; }
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/report_utils.php';

$conn = db();
$admin_code = $_SESSION['admin_code'];
$s = $conn->prepare("SELECT * FROM admin WHERE admin_code=?");
$s->bind_param("s", $admin_code);
$s->execute();
$admin = $s->get_result()->fetch_assoc();
$s->close();

$admin_role = $admin['admin_role'] ?? '';
if ($admin_role !== 'owner' && $admin_role !== 'payroll_manager') { header('Location: a_dashboard.php'); exit; }

$pending_leaves = 0;
$pendingLeavesRes = $conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'");
if ($pendingLeavesRes) {
    $pending_leaves = (int)($pendingLeavesRes->fetch_assoc()['c'] ?? 0);
}

$month = max(1, min(12, (int)($_REQUEST['month'] ?? date('n'))));
$year = max(2000, (int)($_REQUEST['year'] ?? date('Y')));
$report_type = trim((string)($_REQUEST['report_type'] ?? 'overall'));
[$period_start, $period_end] = report_month_bounds($month, $year);
$period_label = report_period_title($month, $year);

$employees = $conn->query("SELECT emp_code, first_name, last_name, section, employment_type, basic_salary, allowance_target, allowance_attendance, status FROM employees ORDER BY emp_code")->fetch_all(MYSQLI_ASSOC);
$employeeMap = [];
foreach ($employees as $emp) { $employeeMap[$emp['emp_code']] = $emp; }

$holidayRows = $conn->query("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."'");
$holidayDates = [];
if ($holidayRows) { while ($h = $holidayRows->fetch_assoc()) { $holidayDates[$h['holiday_date']] = true; } }

$rendered_rows = [];
$snapshot_html = '';
$report_title = 'Overall Payroll Summary';

if ($report_type === 'ot') {
    $report_title = 'Overtime Report';
    foreach ($employees as $emp) {
        $emp_code = $emp['emp_code'];
        $att = $conn->query("SELECT attendance_date, time_in, time_out, status FROM attendance WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND attendance_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."' AND status='present'");
        $normal_hours = 0;
        $double_hours = 0;
        if ($att) {
            while ($a = $att->fetch_assoc()) {
                if (empty($a['time_in']) || empty($a['time_out'])) continue;
                $in = strtotime($a['time_in']);
                $out = strtotime($a['time_out']);
                if ($out <= $in) continue;
                $date = date('Y-m-d', strtotime($a['attendance_date']));
                $cutoff = strtotime($date . ' 17:00:00');
                $seconds = max(0, $out - max($in, $cutoff));
                if ($seconds <= 0) continue;
                $isDouble = ((int)date('N', strtotime($date)) >= 6) || isset($holidayDates[$date]);
                if ($isDouble) $double_hours += $seconds / 3600;
                else $normal_hours += $seconds / 3600;
            }
        }
        $hourly_rate = ((float)$emp['basic_salary'] / 26) / 8;
        $normal_pay = $normal_hours * $hourly_rate;
        $double_pay = $double_hours * $hourly_rate * 2;
        $rendered_rows[] = [
            'emp_code' => $emp_code,
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'normal_hours' => $normal_hours,
            'normal_rate' => $hourly_rate,
            'double_hours' => $double_hours,
            'double_rate' => $hourly_rate * 2,
            'ot_pay' => $normal_pay + $double_pay,
        ];
    }
} elseif ($report_type === 'allowances') {
    $report_title = 'Allowances Report';
    foreach ($employees as $emp) {
        $target = (float)($emp['allowance_target'] ?? 0);
        $attendance = (float)($emp['allowance_attendance'] ?? 0);
        $rendered_rows[] = [
            'emp_code' => $emp['emp_code'],
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'type' => report_employee_type($emp),
            'basic_salary' => (float)$emp['basic_salary'],
            'target' => $target,
            'attendance' => $attendance,
            'total' => $target + $attendance,
        ];
    }
} elseif ($report_type === 'statutory') {
    $report_title = 'Statutory EPF / ETF Report';
    foreach ($employees as $emp) {
        $basic = (float)$emp['basic_salary'];
        $epf_employee = strtolower((string)$emp['employment_type']) === 'permanent' ? round($basic * 0.08, 2) : 0;
        $epf_employer = strtolower((string)$emp['employment_type']) === 'permanent' ? round($basic * 0.12, 2) : 0;
        $etf_employer = strtolower((string)$emp['employment_type']) === 'permanent' ? round($basic * 0.03, 2) : 0;
        $rendered_rows[] = [
            'emp_code' => $emp['emp_code'],
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'basic_salary' => $basic,
            'epf_employee' => $epf_employee,
            'epf_employer' => $epf_employer,
            'etf_employer' => $etf_employer,
            'total' => $epf_employee + $epf_employer + $etf_employer,
        ];
    }
} elseif ($report_type === 'nopay') {
    $report_title = 'No-Pay Deduction Report';
    foreach ($employees as $emp) {
        $emp_code = $emp['emp_code'];
        $present = (int)($conn->query("SELECT COUNT(*) c FROM attendance WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND attendance_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."' AND status='present'")->fetch_assoc()['c'] ?? 0);
        $approved = (int)($conn->query("SELECT COALESCE(SUM(no_of_days),0) c FROM leaves WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND leave_status='approved' AND start_date <= '".$conn->real_escape_string($period_end)."' AND end_date >= '".$conn->real_escape_string($period_start)."'")->fetch_assoc()['c'] ?? 0);
        $working_days = 0;
        for ($day = 1; $day <= (int)date('t', strtotime($period_start)); $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ((int)date('N', strtotime($date)) === 7 || isset($holidayDates[$date])) continue;
            $working_days++;
        }
        $nopay_days = max(0, $working_days - $present - $approved);
        $daily_rate = ((float)$emp['basic_salary']) / 30;
        $rendered_rows[] = [
            'emp_code' => $emp_code,
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'basic_salary' => (float)$emp['basic_salary'],
            'nopay_days' => $nopay_days,
            'daily_rate' => $daily_rate,
            'total' => $nopay_days * $daily_rate,
        ];
    }
} else {
    $report_title = 'Overall Payroll Summary';
    $payrollRows = $conn->query("SELECT p.*, e.first_name, e.last_name, e.section, e.employment_type FROM payroll p LEFT JOIN employees e ON e.emp_code=p.emp_code WHERE p.pay_period_month=".(int)$month." AND p.pay_period_year=".(int)$year." ORDER BY p.emp_code")->fetch_all(MYSQLI_ASSOC);
    foreach ($payrollRows as $row) {
        $emp = $employeeMap[$row['emp_code']] ?? $row;
        $working_days = 0;
        $present = (int)($conn->query("SELECT COUNT(*) c FROM attendance WHERE emp_code='".$conn->real_escape_string($row['emp_code'])."' AND attendance_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."' AND status='present'")->fetch_assoc()['c'] ?? 0);
        for ($day = 1; $day <= (int)date('t', strtotime($period_start)); $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ((int)date('N', strtotime($date)) === 7 || isset($holidayDates[$date])) continue;
            $working_days++;
        }
        $approved = (int)($conn->query("SELECT COALESCE(SUM(no_of_days),0) c FROM leaves WHERE emp_code='".$conn->real_escape_string($row['emp_code'])."' AND leave_status='approved' AND start_date <= '".$conn->real_escape_string($period_end)."' AND end_date >= '".$conn->real_escape_string($period_start)."'")->fetch_assoc()['c'] ?? 0);
        $nopay_days = max(0, $working_days - $present - $approved);
        $no_pay_deduction = ((float)$row['basic_salary'] / 30) * $nopay_days;
        $gross_deductions = (float)($row['epf_employee'] ?? 0) + $no_pay_deduction;
        $gross_salary = (float)($row['total_earnings'] ?? 0);
        $rendered_rows[] = [
            'emp_code' => $row['emp_code'],
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'type' => report_employee_type($emp),
            'basic_salary' => (float)$row['basic_salary'],
            'target' => (float)($row['allowance_target'] ?? 0),
            'attendance' => (float)($row['allowance_attendance'] ?? 0),
            'ot' => (float)($row['ot_payment'] ?? 0),
            'gross_deductions' => $gross_deductions,
            'net_salary' => $gross_salary - $gross_deductions,
        ];
    }
}

ob_start();
?>
<div class="report-print-card">
    <div class="report-header">
        <div class="report-brand">
            <img src="Logo.jpeg" alt="Logo" class="report-logo">
            <div>
                <div class="report-company">TIP TOP APPAREL (PVT) LTD</div>
                <div class="report-meta">Kandy Road, Pasyala, Sri Lanka | Tel: +94 33 2271455 | Email: info@tiptopapparel.lk</div>
            </div>
        </div>
        <div class="report-title">Report: <?php echo htmlspecialchars($report_title . ' - ' . $period_label); ?></div>
    </div>
    <table class="report-grid">
        <thead>
        <?php if ($report_type === 'ot'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Normal OT Hours</th><th>Normal OT Rate</th><th>Double OT Hours</th><th>Double OT Rate</th><th>Total OT Pay</th></tr>
        <?php elseif ($report_type === 'allowances'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Type</th><th>Basic Salary (LKR)</th><th>Target Allowance</th><th>Attendance Allowance</th><th>Total Allowances</th></tr>
        <?php elseif ($report_type === 'statutory'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Basic Salary (LKR)</th><th>EPF Employee (8%)</th><th>EPF Employer (12%)</th><th>ETF Employer (3%)</th><th>Total Contribution</th></tr>
        <?php elseif ($report_type === 'nopay'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Basic Salary (LKR)</th><th>No-Pay Days</th><th>Daily Rate</th><th>Total Deduction</th></tr>
        <?php else: ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Type</th><th>Basic Salary (LKR)</th><th>Target Allowance</th><th>Attendance Allowance</th><th>OT Pay</th><th>Gross Deductions</th><th>Net Salary</th></tr>
        <?php endif; ?>
        </thead>
        <tbody>
        <?php foreach ($rendered_rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['emp_code']); ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['section']); ?></td>
                <?php if ($report_type === 'ot'): ?>
                    <td><?php echo number_format($row['normal_hours'], 2); ?></td>
                    <td><?php echo number_format($row['normal_rate'], 2); ?></td>
                    <td><?php echo number_format($row['double_hours'], 2); ?></td>
                    <td><?php echo number_format($row['double_rate'], 2); ?></td>
                    <td><?php echo number_format($row['ot_pay'], 2); ?></td>
                <?php elseif ($report_type === 'allowances'): ?>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td><?php echo number_format($row['basic_salary'], 2); ?></td>
                    <td><?php echo number_format($row['target'], 2); ?></td>
                    <td><?php echo number_format($row['attendance'], 2); ?></td>
                    <td><strong><?php echo number_format($row['total'], 2); ?></strong></td>
                <?php elseif ($report_type === 'statutory'): ?>
                    <td><?php echo number_format($row['basic_salary'], 2); ?></td>
                    <td><?php echo number_format($row['epf_employee'], 2); ?></td>
                    <td><?php echo number_format($row['epf_employer'], 2); ?></td>
                    <td><?php echo number_format($row['etf_employer'], 2); ?></td>
                    <td><strong><?php echo number_format($row['total'], 2); ?></strong></td>
                <?php elseif ($report_type === 'nopay'): ?>
                    <td><?php echo number_format($row['basic_salary'], 2); ?></td>
                    <td><?php echo (int)$row['nopay_days']; ?></td>
                    <td><?php echo number_format($row['daily_rate'], 2); ?></td>
                    <td><strong><?php echo number_format($row['total'], 2); ?></strong></td>
                <?php else: ?>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td><?php echo number_format($row['basic_salary'], 2); ?></td>
                    <td><?php echo number_format($row['target'], 2); ?></td>
                    <td><?php echo number_format($row['attendance'], 2); ?></td>
                    <td><?php echo number_format($row['ot'], 2); ?></td>
                    <td><?php echo number_format($row['gross_deductions'], 2); ?></td>
                    <td><strong><?php echo number_format($row['net_salary'], 2); ?></strong></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>

        <!-- ===== TOTALS ROW ===== -->
        <?php if ($report_type === 'ot'): ?>
            <?php
                $t_normal_hours = array_sum(array_column($rendered_rows, 'normal_hours'));
                $t_double_hours = array_sum(array_column($rendered_rows, 'double_hours'));
                $t_ot_pay       = array_sum(array_column($rendered_rows, 'ot_pay'));
            ?>
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td><strong><?php echo number_format($t_normal_hours, 2); ?></strong></td>
                <td></td> <!-- normal_rate: no sum -->
                <td><strong><?php echo number_format($t_double_hours, 2); ?></strong></td>
                <td></td> <!-- double_rate: no sum -->
                <td><strong><?php echo number_format($t_ot_pay, 2); ?></strong></td>
            </tr>

        <?php elseif ($report_type === 'allowances'): ?>
            <?php
                $t_basic      = array_sum(array_column($rendered_rows, 'basic_salary'));
                $t_target     = array_sum(array_column($rendered_rows, 'target'));
                $t_attendance = array_sum(array_column($rendered_rows, 'attendance'));
                $t_total      = array_sum(array_column($rendered_rows, 'total'));
            ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: center;"><strong>Total</strong></td>
                <td></td> <!-- type: no sum -->
                <td><strong><?php echo number_format($t_basic, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_target, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_attendance, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_total, 2); ?></strong></td>
            </tr>

        <?php elseif ($report_type === 'statutory'): ?>
            <?php
                $t_basic = array_sum(array_column($rendered_rows, 'basic_salary'));
                $t_total = array_sum(array_column($rendered_rows, 'total'));
            ?>
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td><strong><?php echo number_format($t_basic, 2); ?></strong></td>
                <td></td> <!-- epf_employee: no sum -->
                <td></td> <!-- epf_employer: no sum -->
                <td></td> <!-- etf_employer: no sum -->
                <td><strong><?php echo number_format($t_total, 2); ?></strong></td>
            </tr>

        <?php elseif ($report_type === 'nopay'): ?>
            <?php
                $t_basic     = array_sum(array_column($rendered_rows, 'basic_salary'));
                $t_nopay     = array_sum(array_column($rendered_rows, 'nopay_days'));
                $t_total     = array_sum(array_column($rendered_rows, 'total'));
            ?>
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td><strong><?php echo number_format($t_basic, 2); ?></strong></td>
                <td><strong><?php echo (int)$t_nopay; ?></strong></td>
                <td></td> <!-- daily_rate: no sum -->
                <td><strong><?php echo number_format($t_total, 2); ?></strong></td>
            </tr>

        <?php else: // overall summary ?>
            <?php
                $t_basic      = array_sum(array_column($rendered_rows, 'basic_salary'));
                $t_target     = array_sum(array_column($rendered_rows, 'target'));
                $t_attendance = array_sum(array_column($rendered_rows, 'attendance'));
                $t_ot         = array_sum(array_column($rendered_rows, 'ot'));
                $t_deductions = array_sum(array_column($rendered_rows, 'gross_deductions'));
                $t_net        = array_sum(array_column($rendered_rows, 'net_salary'));
            ?>
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td></td> <!-- type: no sum -->
                <td><strong><?php echo number_format($t_basic, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_target, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_attendance, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_ot, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_deductions, 2); ?></strong></td>
                <td><strong><?php echo number_format($t_net, 2); ?></strong></td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
</div>
<?php
$snapshot_html = ob_get_clean();

if (isset($_GET['month']) || isset($_POST['month'])) {
    save_report_snapshot(report_generate_meta('payroll', $report_title, $month, $year, $admin), $snapshot_html);
}

$page_title = 'Reports';
$active_page = 'Reports';
require 'a_adminLayout.php';
?>
<style>
.report-filters{display:flex;gap:14px;flex-wrap:wrap;align-items:end}
.report-filter{min-width:220px}
.report-actions{display:flex;gap:10px;flex-wrap:wrap}
.report-print-card{background:#fff;border:1px solid #dbe3ee;border-radius:18px;padding:18px;overflow:auto}
.report-header{display:flex;flex-direction:column;gap:12px;margin-bottom:18px}
.report-brand{display:flex;align-items:center;gap:16px;justify-content:center;text-align:left}
.report-logo{width:72px;height:72px;object-fit:contain;border:1px solid #d7e1ef;border-radius:10px;padding:4px;background:#fff}
.report-company{font-size:30px;font-weight:800;text-align:center;color:#1f2937}
.report-meta{font-size:14px;color:#64748b;text-align:center}
.report-title{font-size:24px;font-weight:800;color:#334155;text-align:center}
.report-grid{width:100%;border-collapse:collapse}
.report-grid th{background:#1f3b67;color:#fff;padding:10px 12px;font-size:14px;text-align:left}
.report-grid td{border:1px solid #cfd8e3;padding:8px 10px;font-size:14px;white-space:nowrap}
.report-grid tbody tr:nth-child(even){background:#f8fafc}
.report-total{font-weight:800}
tr.total-row {
    background-color: #f0f0f0;
    font-weight: bold;
    border-top: 2px solid #ccc;
}
tr.total-row td {
    padding-top: 8px;
    padding-bottom: 8px;
}
@media print{
  .sidebar,.topbar,.page-header,.report-controls,footer{display:none!important}
  .main{margin-left:0}
  .page{padding:0}
  .card{border:none}
}
</style>
<header class="topbar">
    <div class="topbar-left"><h1>Reports</h1><p>Payroll manager report set</p></div>
    <div class="topbar-actions"><span class="tb-date"><?php echo date('D, d M Y'); ?></span></div>
</header>
<div class="page page-anim">
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Reports</div>
            <h2><?php echo htmlspecialchars($report_title . ' - ' . $period_label); ?></h2>
            <p>Generate payroll reports directly from the database and archive each run for the owner.</p>
        </div>
    </div>
    <div class="card report-controls">
        <div class="card-body">
            <form method="get" class="report-filters">
                <div class="form-group report-filter">
                    <label>Report Type</label>
                    <select class="form-control" name="report_type">
                        <option value="overall" <?php echo $report_type==='overall'?'selected':''; ?>>Overall Payroll Summary</option>
                        <option value="ot" <?php echo $report_type==='ot'?'selected':''; ?>>Overtime (OT) Report</option>
                        <option value="allowances" <?php echo $report_type==='allowances'?'selected':''; ?>>Allowances Report</option>
                        <option value="statutory" <?php echo $report_type==='statutory'?'selected':''; ?>>Statutory EPF/ETF Report</option>
                        <option value="nopay" <?php echo $report_type==='nopay'?'selected':''; ?>>No-Pay Deduction Report</option>
                    </select>
                </div>
                <div class="form-group report-filter">
                    <label>Month</label>
                    <select class="form-control" name="month">
                        <?php for($i=1;$i<=12;$i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $month===$i?'selected':''; ?>><?php echo report_month_label($i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group report-filter">
                    <label>Year</label>
                    <select class="form-control" name="year">
                        <?php for($y=(int)date('Y'); $y>=2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year===$y?'selected':''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="report-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrows-rotate"></i> Update Period</button>
                    <button type="button" class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
                </div>
            </form>
        </div>
    </div>
    <?php echo $snapshot_html; ?>
</div>
<footer><strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
