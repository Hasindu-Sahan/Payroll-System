<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
$conn = db();

$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");
$s->bind_param("s",$admin_code);
$s->execute();
$admin=$s->get_result()->fetch_assoc();
$s->close();
if(!$admin){session_destroy();header('Location: login.php');exit;}
$admin_role=$admin['admin_role'] ?? 'owner';

// Access control: Owner and HR Manager only
if(!in_array($admin_role, ['owner','hr_manager'], true)){
    header('Location: a_dashboard.php');
    exit;
}

// Load resigned employees
$has_resigned_at = false;
$resigned_pragma = $conn->query("PRAGMA table_info(resigned_employees)");
if ($resigned_pragma) {
    while ($col = $resigned_pragma->fetch_assoc()) {
        if (($col['name'] ?? '') === 'resigned_at') { $has_resigned_at = true; break; }
    }
}
$orderCol = $has_resigned_at ? 'resigned_at' : 'resigned_id';
$resigned_res = $conn->query("SELECT * FROM resigned_employees ORDER BY {$orderCol} DESC");
$resigned = ($resigned_res && $resigned_res !== false) ? $resigned_res->fetch_all(MYSQLI_ASSOC) : [];

$page_title='Resigned Employees';
$active_page='Resigned Employees';
require 'a_adminLayout.php';
?>
    <header class="topbar">
        <div class="topbar-left"><h1>Resigned Employees</h1><p>Archive of removed employee records</p></div>
        <div class="topbar-actions">
            <span class="tb-date"><?php echo date('D, d M Y'); ?></span>
        </div>
    </header>

    <div class="page page-anim">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="a_dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i>
                    Resigned Employees
                </div>
                <h2>Employee Archive</h2>
            </div>
            <div class="btn btn-ghost" style="cursor:default;">
                Total: <?php echo count($resigned); ?>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-user-clock"></i> Resigned Records</div>
                <div style="font-size:15px;color:var(--right-muted);"><?php echo count($resigned); ?> records</div>
            </div>

            <?php if(empty($resigned)): ?>
                <div class="table-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No resigned records yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Section</th>
                                <th>Designation</th>
                                <th>Bank Name</th>
                                <th>Bank Account No</th>
                                <th>Resigned Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($resigned as $i => $r):
                            $type_val = $r['employment_type'] ?? '';
                            $bc = match(true){
                                stripos($type_val,'permanent') !== false => 'badge-perm',
                                stripos($type_val,'contract')  !== false => 'badge-part',
                                stripos($type_val,'temporary') !== false => 'badge-intern',
                                default => 'badge-part'
                            };
                            $bl = ucfirst($type_val);
                            $full_name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
                            $initials  = strtoupper(substr($r['first_name'] ?? 'R', 0, 1).substr($r['last_name'] ?? 'E', 0, 1));
                        ?>
                            <tr>
                                <td style="font-size:13.75px;color:var(--right-dim);"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="emp-cell">
                                        <div class="emp-ava" style="background:linear-gradient(135deg,#94a3b8,#64748b);"><?php echo $initials; ?></div>
                                        <div>
                                            <div class="emp-name"><?php echo htmlspecialchars($full_name); ?></div>
                                            <div class="emp-desig" style="font-family:'JetBrains Mono',monospace;"><?php echo htmlspecialchars($r['emp_code'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($r['email'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-muted);font-family:'JetBrains Mono',monospace;"><?php echo htmlspecialchars($r['contact_no'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($r['section'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-text);font-weight:500;"><?php echo htmlspecialchars($r['designation'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-muted);"><?php echo htmlspecialchars($r['bank_name'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-muted);font-family:'JetBrains Mono',monospace;"><?php echo htmlspecialchars($r['bank_account_no'] ?? ''); ?></td>
                                <td style="font-size:15px;color:var(--right-muted);white-space:nowrap;"><?php echo htmlspecialchars($r['resigned_at'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer><strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong> &nbsp;|&nbsp; Admin Panel</footer>
</div>
</body></html>
