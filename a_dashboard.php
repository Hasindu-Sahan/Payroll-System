<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
require 'db.php';
date_default_timezone_set('Asia/Colombo');
$conn = db();
if($conn->connect_error)die('DB Error: '.$conn->connect_error);
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
if(!$admin){session_destroy();header('Location: login.php');exit;}
$admin_role=$admin['admin_role'];

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_emp      =(int)$conn->query("SELECT COUNT(*) c FROM employees")->fetch_assoc()['c'];
$pending_leaves =(int)$conn->query("SELECT COUNT(*) c FROM leaves WHERE leave_status='pending'")->fetch_assoc()['c'];
$today          =date('Y-m-d');
$ym             =date('Y-m');
// Counts employees marked as 'present' for today
$att_today = (int)$conn->query("SELECT COUNT(*) c FROM attendance WHERE attendance_date='$today' AND status='present'")->fetch_assoc()['c'];

$cur_month_label=date('F, Y');
// Get the current month and year as integers
$cur_m = (int)date('m');
$cur_y = (int)date('Y');

// Query the 'payroll' table instead of 'salaries'
$sal = $conn->query("SELECT SUM(net_salary) tot FROM payroll WHERE pay_period_month=$cur_m AND pay_period_year=$cur_y")->fetch_assoc();
$monthly_payout = (float)($sal['tot'] ?? 0);

// Upcoming holidays
$upcoming_holidays = [];
// Changed 'holiday_title' to 'holiday_name' to match your database
$hq = $conn->query("SELECT holiday_name, holiday_date FROM holidays"); 
while($h = $hq->fetch_assoc()){
    $ts = strtotime($h['holiday_date']);
    if($ts !== false){
        $diff = $ts - strtotime('today');
        // Collect holidays occurring within the next 30 days
        if($diff >= 0 && $diff <= 30 * 86400) {
                        $upcoming_holidays[] = [
                'title' => $h['holiday_name'], // Match the selected column
                'ts'    => $ts,
                'date'  => $h['holiday_date']
            ];
        }
    }
}
// Sort holidays by date
usort($upcoming_holidays, fn($a, $b) => $a['ts'] - $b['ts']);

// Recent leaves
$leave_requests = $conn->query("
    SELECT l.*, e.first_name, e.last_name, e.designation 
    FROM leaves l 
    LEFT JOIN employees e ON e.emp_code = l.emp_code 
    ORDER BY l.applied_date DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Today's attendance summary
$today_att = $conn->query("
    SELECT a.emp_code, e.first_name, e.last_name, e.section,
           a.status, a.time_in, a.time_out
    FROM attendance a 
    LEFT JOIN employees e ON e.emp_code = a.emp_code
    WHERE a.attendance_date = '$today'
    ORDER BY a.updated_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Employees list
$emps = $conn->query("SELECT emp_code, first_name, last_name, designation, section, employment_type FROM employees ORDER BY emp_id")->fetch_all(MYSQLI_ASSOC);

function initials($f,$l){return strtoupper(substr($f,0,1).substr($l,0,1));}
function admin_initials($name){$parts=explode(' ',trim($name));$i=strtoupper(substr($parts[0],0,1));if(count($parts)>1)$i.=strtoupper(substr(end($parts),0,1));return $i;}

$page_title='Dashboard';
// Must match layout nav label exactly
$active_page='Dashboard';
require 'a_adminLayout.php';

?>
    <header class="topbar">
        <div class="topbar-left"><h1>Dashboard</h1><p><?php echo date('l, d F Y'); ?></p></div>
        <div class="topbar-actions">
            <span class="tb-date" id="live-clock" style="font-family:'JetBrains Mono',monospace;"><?php echo date('H:i:s'); ?></span>
        </div>
    </header>

    <div class="page page-anim">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="wb-left">
                <div class="wb-greeting">Good <?php echo date('H')<12?'morning':(date('H')<17?'afternoon':'evening'); ?></div>
                <div class="wb-name"><?php echo htmlspecialchars($admin['admin_name']); ?></div>
                <div class="wb-role">
                    <?php echo match($admin_role){
                        'owner'           => '<i class="fa-solid fa-crown" style="color:#f59e0b;margin-right:6px;"></i>Owner — Full System Access',
                        'hr_manager'      => '<i class="fa-solid fa-users-gear" style="color:#10b981;margin-right:6px;"></i>HR Manager — People &amp; Time Management',
                        'payroll_manager' => '<i class="fa-solid fa-calculator" style="color:#6366f1;margin-right:6px;"></i>Payroll Manager — Salary &amp; Compliance',
                        default => 'Administrator'
                    }; ?>
                </div>
            </div>
            <div class="wb-right">
                <div class="wb-date-label">Today</div>
                <div class="wb-date-val"><?php echo date('d M Y'); ?></div>
                <div class="wb-time" id="wb-clock"><?php echo date('H:i:s'); ?></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row" style="grid-template-columns:repeat(<?php echo $admin_role==='owner'?5:($admin_role==='hr_manager'?3:3); ?>,1fr);">
            <?php if($admin_role==='owner'||$admin_role==='hr_manager'): ?>
            <div class="stat-card"><div class="stat-icon-wrap si-indigo"><i class="fa-solid fa-users"></i></div><div class="stat-body"><div class="stat-val"><?php echo $total_emp; ?></div><div class="stat-lbl">Total Employees</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--warn);"><?php echo $pending_leaves; ?></div><div class="stat-lbl">Pending Leaves</div></div></div>
            <div class="stat-card"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-clock"></i></div><div class="stat-body"><div class="stat-val" style="color:var(--success);"><?php echo $att_today; ?></div><div class="stat-lbl">Present Today</div></div></div>
            <?php endif; ?>
            <?php if($admin_role==='owner'||$admin_role==='payroll_manager'): ?>
            <?php endif; ?>

        </div>

        <?php if($admin_role==='owner'||$admin_role==='hr_manager'): ?>
        <!-- HR / Owner layout -->
        <div style="display:grid;grid-template-columns:1fr 320px;gap:22px;">
            <!-- Recent Leaves -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-plane-departure"></i> Recent Leave Requests</div><a href="a_leavesMng.php" class="see-all">View All →</a></div>
                <?php if(empty($leave_requests)): ?>
                <div class="table-empty"><i class="fa-solid fa-inbox"></i><p>No leave requests yet.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto;"><table class="data-table">
                    <thead><tr><th>Employee</th><th>Subject</th><th>Type</th><th>Status</th><th>Applied</th></tr></thead>
                    <tbody>
                    <?php foreach($leave_requests as $lv):
                        $sc=match($lv['leave_status']){'approved'=>'sp-approve','rejected'=>'sp-reject',default=>'sp-pending'};
                        $sl=match($lv['leave_status']){'approved'=>'Approved','rejected'=>'Rejected',default=>'Pending'};
                    ?>
                    <tr>
                        <td><div class="emp-cell"><div class="emp-ava" style="width:36px;height:36px;font-size:13.75px;"><?php echo initials($lv['first_name']??'?',$lv['last_name']??''); ?></div><div><div class="emp-name"><?php echo htmlspecialchars(($lv['first_name']??'').' '.($lv['last_name']??'')); ?></div><div class="emp-desig"><?php echo $lv['emp_code']; ?></div></div></div></td>
                        <td style="font-size:15px;max-width:180px;"> <?php echo htmlspecialchars($lv['leave_subject'] ?? $lv['subject'] ?? '—'); ?></td>

                        <td><span style="background:#eef2ff;color:#4338ca;padding:3px 9px;border-radius:20px;font-size:13.125px;font-weight:600;"><?php echo htmlspecialchars($lv['leave_type']); ?></span></td>
                        <td><span class="status-pill <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
                        <td style="font-size:13.75px;color:var(--right-muted);"> <?php echo date('d M', strtotime($lv['apply_date'] ?? $lv['applied_date'] ?? $lv['applied_at'] ?? '')); ?></td>

                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </div>

            <!-- Charts (replaces Upcoming Holidays + Quick Actions + Today's Attendance) -->
            <div>
                <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-pie"></i> Attendance Status — <?php echo date('d M Y'); ?></div></div>
                    <?php
                        // Attendance status counts for the chart (today)
                        $att_present = 0; $att_absent = 0; $att_not_marked = 0;
                        $att_today_rows = $conn->query("SELECT status, COUNT(*) c FROM attendance WHERE attendance_date='".$today."' GROUP BY status");
                        if($att_today_rows){
                            while($r=$att_today_rows->fetch_assoc()){
                                $st = $r['status'] ?? '';
                                if($st === 'present') $att_present = (int)$r['c'];
                                elseif($st === 'absent') $att_absent = (int)$r['c'];
                                else $att_not_marked += (int)$r['c'];
                            }
                        }
                        // If there are no rows at all, keep zeros; chart will show 0s.
                    ?>

                    <div style="padding:14px 18px 8px 18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px;">
                            <div style="font-size:15px;color:var(--right-muted);font-weight:700;">Summary</div>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <span class="badge badge-approved">Present: <?php echo $att_present; ?></span>
                                <span class="badge badge-rejected">Absent: <?php echo $att_absent; ?></span>
                            </div>
                        </div>
                        <div style="position:relative;height:230px;">
                            <canvas id="attendancePie" aria-label="Attendance chart" role="img"></canvas>
                        </div>
                        <div style="margin-top:8px;font-size:13.75px;color:var(--right-muted);display:flex;justify-content:space-between;">
                            <div><span class="badge badge-active" style="background:#e0f2fe;color:#0369a1;">Not Marked: <?php echo $att_not_marked; ?></span></div>
                            <div style="font-family:'JetBrains Mono',monospace;">Total records: <?php echo ($att_present + $att_absent + $att_not_marked); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>


        <?php if($admin_role==='payroll_manager'): ?>
            <?php
                // Payroll manager: real payroll stats and a DB-driven line chart.
                $latestPayroll = $conn->query("
                    SELECT pay_period_year AS y, pay_period_month AS m
                    FROM payroll
                    ORDER BY pay_period_year DESC, pay_period_month DESC
                    LIMIT 1
                ")->fetch_assoc();

                $pm = (int)($latestPayroll['m'] ?? date('m'));
                $py = (int)($latestPayroll['y'] ?? date('Y'));
                $period_label = date('F Y', mktime(0, 0, 0, $pm, 1, $py));

                $currentMonthRow = $conn->query("
                    SELECT
                        COUNT(*) AS payroll_count,
                        SUM(net_salary) AS net_total,
                        SUM(total_earnings) AS gross_total,
                        AVG(net_salary) AS avg_net
                    FROM payroll
                    WHERE pay_period_month=$pm AND pay_period_year=$py
                ")->fetch_assoc();

                $net_this_month = (float)($currentMonthRow['net_total'] ?? 0);
                $gross_this_month = (float)($currentMonthRow['gross_total'] ?? 0);
                $avg_net_this_month = (float)($currentMonthRow['avg_net'] ?? 0);
                $payroll_runs_this_month = (int)($currentMonthRow['payroll_count'] ?? 0);

                $payroll_year = $py;
                $yearPayrollRow = $conn->query("
                    SELECT COUNT(*) AS payroll_count
                    FROM payroll
                    WHERE pay_period_year=$payroll_year
                ")->fetch_assoc();
                $payroll_records_this_year = (int)($yearPayrollRow['payroll_count'] ?? 0);

                // Last 12 months labels + values
                $line_labels = [];
                $line_values = [];
                $cursor = new DateTime(sprintf('%04d-%02d-01', $py, $pm));
                for($i=11;$i>=0;$i--){
                    $d = (clone $cursor)->modify("-$i months");
                    $m = (int)$d->format('m');
                    $y = (int)$d->format('Y');

                    $lab = $d->format('M Y');
                    $q = $conn->query("
                        SELECT COALESCE(SUM(net_salary), 0) AS tot
                        FROM payroll
                        WHERE pay_period_month=$m AND pay_period_year=$y
                    ")->fetch_assoc();

                    $line_labels[] = $lab;
                    $line_values[] = (float)($q['tot'] ?? 0);
                }
            ?>

            <!-- Payroll Manager Dashboard -->
            <div style="margin-bottom:22px;">
                <!-- Stats: exactly 3 cards -->
                <div class="stats-row" style="grid-template-columns:repeat(3,1fr); margin-bottom:22px;">
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-violet"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div class="stat-body">
                            <div class="stat-val" style="font-size:18.75px;">LKR <?php echo number_format($gross_this_month,0); ?></div>
                            <div class="stat-lbl">Gross Payroll - <?php echo htmlspecialchars($period_label); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-indigo"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-body">
                            <div class="stat-val"><?php echo $total_emp; ?></div>
                            <div class="stat-lbl">Active Employees</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrap si-green"><i class="fa-solid fa-scale-balanced"></i></div>
                        <div class="stat-body">
                            <div class="stat-val" style="font-size:18.75px;">LKR <?php echo number_format($net_this_month,0); ?></div>
                            <div class="stat-lbl">Net Payroll - <?php echo htmlspecialchars($period_label); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-chart-line"></i> Net Payroll Trend (Last 12 Months)</div>
                    </div>
                    <div style="padding:18px 24px 10px 24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;">
                            <div style="font-size:15px;color:var(--right-muted);font-weight:700;">LKR (Net Salary Sum)</div>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <span class="badge badge-active" style="background:#e0f2fe;color:#0369a1;">Payroll runs this month: <?php echo $payroll_runs_this_month; ?></span>
                                <span class="badge badge-active" style="background:#eef2ff;color:#3730a3;">Records this year: <?php echo $payroll_records_this_year; ?></span>
                                <span class="badge badge-active" style="background:#f0fdf4;color:#166534;">Avg net: LKR <?php echo number_format($avg_net_this_month,0); ?></span>
                            </div>
                        </div>
                        <div style="position:relative;height:260px;">
                            <canvas id="payrollLine" aria-label="Net payroll trend chart" role="img"></canvas>
                        </div>
                        <div style="margin-top:10px;font-size:13.75px;color:var(--right-muted);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div>Total points: <?php echo count($line_labels); ?></div>
                            <div style="font-family:'JetBrains Mono',monospace;">Latest payroll month: <?php echo htmlspecialchars($period_label); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /page -->

    <footer><strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>

<!-- Chart.js (for dashboard chart) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function renderAttendanceChart(){

  // Payroll Manager line chart
  const lineEl = document.getElementById('payrollLine');
  if(lineEl && typeof Chart !== 'undefined'){
    const labels = <?php echo json_encode($line_labels ?? []); ?>;
    const values = <?php echo json_encode($line_values ?? []); ?>;

    if(!Array.isArray(labels) || !labels.length || !Array.isArray(values) || !values.length){
      lineEl.parentElement.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#64748b;font-weight:600;">No payroll data available.</div>';
      return;
    }

    new Chart(lineEl, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Net Payroll (LKR)',
          data: values,
          backgroundColor: 'rgba(99, 102, 241, 0.12)',
          borderColor: 'rgba(99, 102, 241, 0.9)',
          borderWidth: 3,
          pointBackgroundColor: 'rgba(99, 102, 241, 1)',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          tension: 0.35,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { callback: function(v){ return 'LKR ' + v; } } },
          x: { ticks: { maxRotation: 0, minRotation: 0 } }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context){
                const v = context.raw || 0;
                return 'LKR ' + Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
              }
            }
          }
        }
      }
    });
  }


  const el = document.getElementById('attendancePie');
  if(!el || typeof Chart === 'undefined') return;

  // Values injected by PHP (safe defaults)
  const present = <?php echo isset($att_present) ? (int)$att_present : 0; ?>;
  const absent = <?php echo isset($att_absent) ? (int)$att_absent : 0; ?>;
  const notMarked = <?php echo isset($att_not_marked) ? (int)$att_not_marked : 0; ?>;

  const total = present + absent + notMarked;

  new Chart(el, {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent', 'Not Marked'],
      datasets: [{
        data: [present, absent, notMarked],
        backgroundColor: ['#22c55e', '#ef4444', '#60a5fa'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function(context){
              const v = context.raw || 0;
              const pct = total > 0 ? Math.round((v/total)*100) : 0;
              return `${context.label}: ${v} (${pct}%)`;
            }
          }
        }
      }
    }
  });
})();
</script>
</body></html>
