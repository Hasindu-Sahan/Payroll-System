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
if ($admin_role !== 'owner' && $admin_role !== 'hr_manager') { header('Location: a_dashboard.php'); exit; }

$pending_leaves = 0;
$pendingLeavesRes = $conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'");
if ($pendingLeavesRes) { $pending_leaves = (int)($pendingLeavesRes->fetch_assoc()['c'] ?? 0); }

$month = max(1, min(12, (int)($_REQUEST['month'] ?? date('n'))));
$year = max(2000, (int)($_REQUEST['year'] ?? date('Y')));
$report_type = trim((string)($_REQUEST['report_type'] ?? 'attendance'));
[$period_start, $period_end] = report_month_bounds($month, $year);
$period_label = report_period_title($month, $year);

$employees = $conn->query("SELECT emp_code, first_name, last_name, section, employment_type, status, date_of_joining FROM employees ORDER BY emp_code")->fetch_all(MYSQLI_ASSOC);

$snapshot_html = '';
$report_title = 'Monthly Attendance Details & Exceptions Summary';
$rendered_rows = [];

if ($report_type === 'employees') {
    $report_title = 'Master Employee Details Report';
    foreach ($employees as $emp) {
        $resigned = $conn->query("SELECT resigned_date FROM resigned_employees WHERE emp_code='".$conn->real_escape_string($emp['emp_code'])."' ORDER BY resigned_date DESC LIMIT 1");
        $resignedRow = $resigned ? $resigned->fetch_assoc() : null;
        $resignedDate = (string)($resignedRow['resigned_date'] ?? '');

        // If found in resigned_employees table → force status to inactive
        $status = $resignedRow ? 'inactive' : ($emp['status'] ?? 'active');

        $rendered_rows[] = [
            'emp_code'     => $emp['emp_code'],
            'name'         => report_employee_name($emp),
            'section'      => report_employee_section($emp),
            'type'         => report_employee_type($emp),
            'status'       => $status,
            'resigned_date'=> $resignedDate,
        ];
    }
} elseif ($report_type === 'leave') {
    $report_title = 'Monthly Leave Summary Report';
    foreach ($employees as $emp) {
        $emp_code = $emp['emp_code'];
        $leaves = $conn->query("SELECT leave_type, COALESCE(SUM(no_of_days),0) c FROM leaves WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND leave_status='approved' AND start_date <= '".$conn->real_escape_string($period_end)."' AND end_date >= '".$conn->real_escape_string($period_start)."' GROUP BY leave_type")->fetch_all(MYSQLI_ASSOC);
        $casual = $annual = 0;
        foreach ($leaves as $lv) {
            $days = (int)($lv['c'] ?? 0);
            $type = strtolower((string)($lv['leave_type'] ?? ''));
            if (str_contains($type, 'casual') || str_contains($type, 'sick') || str_contains($type, 'medical')) {
                $casual += $days;
            } else {
                $annual += $days;
            }
        }
        $rendered_rows[] = [
            'emp_code' => $emp_code,
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'casual' => $casual,
            'annual' => $annual,
            'total' => $casual + $annual,
        ];
    }
} else {
    $report_title = 'Monthly Attendance Details & Exceptions Summary';
    $daysInMonth = (int)date('t', strtotime($period_start));
    $holidayRows = $conn->query("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."'");
    $holidayDates = [];
    if ($holidayRows) { while ($h = $holidayRows->fetch_assoc()) { $holidayDates[$h['holiday_date']] = true; } }
    foreach ($employees as $emp) {
        $emp_code = $emp['emp_code'];
        $working_days = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ((int)date('N', strtotime($date)) === 7 || isset($holidayDates[$date])) continue;
            $working_days++;
        }
        $present = (int)($conn->query("SELECT COUNT(*) c FROM attendance WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND attendance_date BETWEEN '".$conn->real_escape_string($period_start)."' AND '".$conn->real_escape_string($period_end)."' AND status='present'")->fetch_assoc()['c'] ?? 0);
        $approved = (int)($conn->query("SELECT COALESCE(SUM(no_of_days),0) c FROM leaves WHERE emp_code='".$conn->real_escape_string($emp_code)."' AND leave_status='approved' AND start_date <= '".$conn->real_escape_string($period_end)."' AND end_date >= '".$conn->real_escape_string($period_start)."'")->fetch_assoc()['c'] ?? 0);
        $nopay = max(0, $working_days - $present - $approved);
        $attendance_pct = $working_days > 0 ? round(($present / $working_days) * 100, 2) : 0;
        $rendered_rows[] = [
            'emp_code' => $emp_code,
            'name' => report_employee_name($emp),
            'section' => report_employee_section($emp),
            'working_days' => $working_days,
            'present' => $present,
            'approved' => $approved,
            'nopay' => $nopay,
            'attendance_pct' => $attendance_pct,
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
        <?php if ($report_type === 'employees'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Type</th><th>Employment Status</th><th>Resignation Date</th></tr>
        <?php elseif ($report_type === 'leave'): ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Casual + Sick Taken</th><th>Annual Taken</th><th>Total Taken (Month)</th></tr>
        <?php else: ?>
            <tr><th>Emp ID</th><th>Employee Name</th><th>Section</th><th>Working Days</th><th>Days Present</th><th>Approved Leaves</th><th>No-Pay Absences</th><th>Attendance %</th></tr>
        <?php endif; ?>
        </thead>
       <tbody>
        <?php foreach ($rendered_rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['emp_code']); ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['section']); ?></td>
                <?php if ($report_type === 'employees'): ?>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst((string)$row['status'])); ?></td>
                    <td><?php echo htmlspecialchars($row['resigned_date'] ?: '-'); ?></td>
                <?php elseif ($report_type === 'leave'): ?>
                    <td><?php echo (int)$row['casual']; ?></td>
                    <td><?php echo (int)$row['annual']; ?></td>
                    <td><strong><?php echo (int)$row['total']; ?></strong></td>
                <?php else: ?>
                    <td><?php echo (int)$row['working_days']; ?></td>
                    <td><?php echo (int)$row['present']; ?></td>
                    <td><?php echo (int)$row['approved']; ?></td>
                    <td><?php echo (int)$row['nopay']; ?></td>
                    <td><strong><?php echo number_format($row['attendance_pct'], 2); ?>%</strong></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>

        <!-- ===== TOTALS ROW ===== -->
        <?php if ($report_type === 'leave'): ?>
            <?php
                $t_casual  = array_sum(array_column($rendered_rows, 'casual'));
                $t_annual  = array_sum(array_column($rendered_rows, 'annual'));
                $t_total   = array_sum(array_column($rendered_rows, 'total'));
            ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: center;"><strong>Total</strong></td>
                <td><strong><?php echo (int)$t_casual; ?></strong></td>
                <td><strong><?php echo (int)$t_annual; ?></strong></td>
                <td><strong><?php echo (int)$t_total; ?></strong></td>
            </tr>

        <?php elseif ($report_type !== 'employees'): // attendance ?>
            <?php
                $t_working  = array_sum(array_column($rendered_rows, 'working_days'));
                $t_present  = array_sum(array_column($rendered_rows, 'present'));
                $t_approved = array_sum(array_column($rendered_rows, 'approved'));
                $t_nopay    = array_sum(array_column($rendered_rows, 'nopay'));
            ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: center;"><strong>Total</strong></td>
                <td><strong><?php echo (int)$t_working; ?></strong></td>
                <td><strong><?php echo (int)$t_present; ?></strong></td>
                <td><strong><?php echo (int)$t_approved; ?></strong></td>
                <td><strong><?php echo (int)$t_nopay; ?></strong></td>
                <td></td> <!-- attendance_pct: no sum -->
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
</div>
<?php
$snapshot_html = ob_get_clean();

if (isset($_GET['month']) || isset($_POST['month'])) {
    save_report_snapshot(report_generate_meta('hr', $report_title, $month, $year, $admin), $snapshot_html);
}

$page_title = 'HR Reports';
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
@media print{
  .sidebar,.topbar,.page-header,.report-controls,footer{display:none!important}
  .main{margin-left:0}
  .page{padding:0}
}
</style>
<header class="topbar">
    <div class="topbar-left"><h1>Reports</h1><p>HR manager report set</p></div>
    <div class="topbar-actions"><span class="tb-date"><?php echo date('D, d M Y'); ?></span></div>
</header>
<div class="page page-anim">
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="a_dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i> Reports</div>
            <h2><?php echo htmlspecialchars($report_title . ' - ' . $period_label); ?></h2>
            <p>Attendance, employee records, and leave summaries pulled directly from the database.</p>
        </div>
    </div>
    <div class="card report-controls">
        <div class="card-body">
            <form method="get" class="report-filters">
                <div class="form-group report-filter">
                    <label>Report Type</label>
                    <select class="form-control" name="report_type">
                        <option value="attendance" <?php echo $report_type==='attendance'?'selected':''; ?>>Monthly Attendance Details &amp; Exceptions Summary</option>
                        <option value="employees" <?php echo $report_type==='employees'?'selected':''; ?>>Master Employee Details Report</option>
                        <option value="leave" <?php echo $report_type==='leave'?'selected':''; ?>>Monthly Leave Summary Report</option>
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
