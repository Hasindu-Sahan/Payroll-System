<?php
// Print-only Payslip (same shared document structure rules).
// Designed to be embedded/triggered via existing system logic.
//
// NOTE: This file focuses on layout structure, not full business logic.

session_start();
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/doc_structure_shared.php';

$conn = db();

if(!isset($_SESSION['Login_Type']) || $_SESSION['Login_Type'] !== 'employee'){
    header('Location: login.php');
    exit;
}

$emp_code = $_SESSION['emp_code'] ?? '';

// Fetch employee
$stmt = $conn->prepare("SELECT * FROM employees WHERE emp_code=?");
$stmt->bind_param("s", $emp_code);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$emp){
    die('<div style="color:red;padding:20px;">Employee not found.</div>');
}

// Determine latest payroll row as a default
$pr = $conn->prepare("SELECT * FROM payroll WHERE emp_code=? ORDER BY pay_period_year DESC, pay_period_month DESC LIMIT 1");
$pr->bind_param("s", $emp_code);
$pr->execute();
$latest = $pr->get_result()->fetch_assoc();
$pr->close();

if(!$latest){
    die('<div style="color:#b91c1c;padding:20px;">No payroll found for this employee.</div>');
}

$monthName = date('F', mktime(0,0,0,(int)$latest['pay_period_month'],1));
$year = (int)$latest['pay_period_year'];

$signMonth = $monthName; // keep as requested structure: only month name

// Build a "payslip summary" style table, reusing the same 6x6 structure
// (business-specific values can be adjusted later)
$headers = [
    'Employee Name',
    'Employee ID',
    'Section',
    'Designation',
    'Total Leaves taken',
    'Remain Leaves'
];

// Use placeholders for payslip table cells (since you requested same structure).
// Leave values can be pulled from leaves table in a later refinement.
$employeeName = trim(($emp['first_name'] ?? '').' '.($emp['last_name'] ?? '')) ?: '—';
$employeeId = $emp['emp_code'] ?? '—';
$section = $emp['section'] ?? '—';
$designation = $emp['designation'] ?? '—';

// 6 body rows (excluding header). Total row at bottom.
$rows = [
    [$employeeName, $employeeId, $section, $designation, '—', '—'],
    ['—','—','—','—','—','—'],
    ['—','—','—','—','—','—'],
    ['—','—','—','—','—','—'],
    ['—','—','—','—','—','—'],
    ['—','—','—','—','—','—'],
];

$totalRow = ['', '', '', 'Total', '0', '0'];

// Render document using shared structure
// Title requirement in your spec is "Monthly leave summary"; for payslip we keep "Pay Slip".
tt_doc_begin('Pay Slip', $signMonth);

echo "<div style='font-size:12.5px;color:#4b5563;text-align:center;margin:-6px 0 12px;'>Period: ".tt_escape($monthName.' '.$year)."</div>";

tt_doc_table_begin($headers);
foreach($rows as $i=>$r){
    tt_doc_table_row($r, $i, false);
}

tt_doc_table_total_row($totalRow);

tt_doc_signoff();

tt_doc_end();
?>

