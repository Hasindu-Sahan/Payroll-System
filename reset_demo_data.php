<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$conn = db();
$pdo = $conn->getPdo();
$pdo->exec('PRAGMA foreign_keys = OFF');

$tables = [
    'admin_employee_link',
    'salary_increments',
    'payroll',
    'leaves',
    'attendance',
    'resigned_employees',
    'employees',
    'admin',
    'payheads',
    'leavelimits',
    'holidays',
];

$pdo->beginTransaction();
try {
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM $table");
    }
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('admin','employees','resigned_employees','leaves','attendance','holidays','leavelimits','payroll','payheads','admin_employee_link','salary_increments')");

    $now = date('Y-m-d H:i:s');

    $admins = [
        ['A001', 'Nuwan Perera', 'admin01@tiptop.com', 'a12', 'owner'],
        ['A002', 'Dilini Fernando', 'admin02@tiptop.com', 'a12', 'hr_manager'],
        ['A003', 'Sajith Wickramasinghe', 'admin03@tiptop.com', 'a12', 'payroll_manager'],
    ];
    $stmt = $pdo->prepare('INSERT INTO admin (admin_code, admin_name, admin_email, admin_password, admin_role, admin_time) VALUES (?,?,?,?,?,?)');
    foreach ($admins as [$code, $name, $email, $pw, $role]) {
        $stmt->execute([$code, $name, $email, sha1($pw), $role, $now]);
    }

    $pdo->exec('INSERT INTO leavelimits (annual_allowed_leaves, casual_allowed_leaves, deduction_per_day, manager_annual_leaves, manager_deduction_per_day) VALUES (14, 7, 2500, 24, 3500)');

    $employees = [
        ['EMP001','Nadeesha','Perera','e001@tiptop.com','female','1991-04-18','0771234001','12, Lake Road','Colombo','HR Manager','HR','2023-02-01','Permanent',125000,15000,9000,6000,'People\'s Bank','111100000001','active'],
        ['EMP002','Kasun','Silva','e002@tiptop.com','male','1990-09-05','0771234002','25, Galle Road','Galle','Payroll Manager','Finance','2023-03-15','Permanent',135000,18000,9000,7000,'BOC','111100000002','active'],
        ['EMP003','Tharushi','Fernando','e003@tiptop.com','female','1996-01-22','0771234003','41, Hill Street','Kandy','Worker','Cutting','2023-04-10','Permanent',65000,6000,5000,3500,'Sampath Bank','111100000003','active'],
        ['EMP004','Nimal','Jayasuriya','e004@tiptop.com','male','1994-07-11','0771234004','8, Station Lane','Matara','Worker','Sewing','2023-05-01','Permanent',62000,5000,4500,3000,'Commercial Bank','111100000004','active'],
        ['EMP005','Sachini','Rajapaksha','e005@tiptop.com','female','1995-12-30','0771234005','17, Temple View','Negombo','Worker','Ironing','2023-06-05','Permanent',61000,5000,4500,3000,'NDB','111100000005','active'],
        ['EMP006','Prabath','Mendis','e006@tiptop.com','male','1997-03-14','0771234006','99, Harbour Road','Kurunegala','Worker','Packing','2023-07-20','Contract',58000,4000,4000,2800,'HNB','111100000006','active'],
        ['EMP007','Hiruni','Ariyasinghe','e007@tiptop.com','female','1998-06-08','0771234007','14, Green Road','Kegalle','Worker','Cutting','2023-08-12','Contract',57500,4000,3800,2500,'People\'s Bank','111100000007','active'],
        ['EMP008','Vishwa','Alwis','e008@tiptop.com','male','1993-10-19','0771234008','56, River View','Anuradhapura','Worker','Sewing','2023-09-25','Permanent',60500,5000,4500,3000,'BOC','111100000008','active'],
        ['EMP009','Ishani','Rathnayake','e009@tiptop.com','female','1992-02-27','0771234009','73, Coconut Estate','Polonnaruwa','Worker','Ironing','2023-10-09','Contract',59000,4500,4000,3000,'Sampath Bank','111100000009','active'],
        ['EMP010','Danuka','Wijesinghe','e010@tiptop.com','male','1999-11-03','0771234010','28, Flower Lane','Ratnapura','Worker','Packing','2023-11-02','Probation',54000,3500,3500,2500,'Commercial Bank','111100000010','active'],
    ];
    $stmt = $pdo->prepare('INSERT INTO employees (emp_code, first_name, last_name, email, password, gender, date_of_birth, contact_no, address, city, designation, section, date_of_joining, employment_type, basic_salary, allowance_target, allowance_attendance, allowance_meal, bank_name, bank_account_no, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($employees as $emp) {
        [$code,$first,$last,$email,$gender,$dob,$contact,$address,$city,$designation,$section,$join,$etype,$salary,$allowTarget,$allowAttend,$allowMeal,$bank,$acct,$status] = $emp;
        $rawPw = 'e12';
        $stmt->execute([$code,$first,$last,$email,sha1($rawPw),$gender,$dob,$contact,$address,$city,$designation,$section,$join,$etype,$salary,$allowTarget,$allowAttend,$allowMeal,$bank,$acct,$status,$now]);
    }

    $links = [
        ['A002','EMP001'],
        ['A003','EMP002'],
    ];
    $stmt = $pdo->prepare('INSERT INTO admin_employee_link (admin_code, emp_code) VALUES (?,?)');
    foreach ($links as $link) {
        $stmt->execute($link);
    }

    $holidays = [
        ['2023-01-01', 'New Year Day'],
        ['2023-04-14', 'Sinhala and Tamil New Year'],
        ['2023-05-01', 'Labor Day'],
        ['2023-12-25', 'Christmas Day'],
        ['2024-01-01', 'New Year Day'],
        ['2024-04-12', 'Sinhala and Tamil New Year Eve'],
        ['2024-05-01', 'Labor Day'],
        ['2024-12-25', 'Christmas Day'],
        ['2025-01-01', 'New Year Day'],
        ['2025-04-14', 'Sinhala and Tamil New Year'],
        ['2025-05-01', 'Labor Day'],
        ['2025-12-25', 'Christmas Day'],
    ];
    $stmt = $pdo->prepare('INSERT INTO holidays (holiday_date, holiday_name) VALUES (?,?)');
    foreach ($holidays as $holiday) {
        $stmt->execute($holiday);
    }

    $payheads = [
        ['Basic Salary', 'earning', 'fixed', 0, 1, 'Monthly base salary'],
        ['Target Allowance', 'earning', 'fixed', 0, 1, 'Target achievement allowance'],
        ['Attendance Allowance', 'earning', 'fixed', 0, 1, 'Attendance incentive'],
        ['Meal Allowance', 'earning', 'fixed', 0, 1, 'Meal support allowance'],
        ['No Pay Deduction', 'deduction', 'fixed', 0, 1, 'Unpaid leave deduction'],
        ['OT Payment', 'earning', 'formula', 0, 1, 'Overtime and double OT payment'],
    ];
    $stmt = $pdo->prepare('INSERT INTO payheads (payhead_name, payhead_type, calculation_type, default_value, is_active, description, created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($payheads as $p) {
        $stmt->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $now]);
    }

    $leaveRows = [];
    $leaveReasons = [
        'annual' => [
            'Family wedding travel',
            'Religious festival trip',
            'Home visit and family function',
            'Annual trip with family',
        ],
        'casual' => [
            'Bank work and document submission',
            'School meeting and office errands',
            'Passport renewal and admin work',
            'Vehicle service and personal work',
        ],
        'sick' => [
            'Fever and doctor advised rest',
            'Clinic follow-up and recovery',
            'Migraine and prescribed rest',
            'Flu symptoms and medical leave',
        ],
    ];
    $leaveTypes = ['annual', 'casual', 'sick'];
    $approvedStatusMap = ['approved', 'approved', 'approved', 'pending'];
    $stmt = $pdo->prepare('INSERT INTO leaves (emp_code, leave_type, start_date, end_date, no_of_days, reason, leave_status, applied_date, reviewed_date, reviewed_by, approved_by, admin_remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach (array_slice($employees, 0, 10) as $idx => $emp) {
        $empCode = $emp[0];
        for ($year = 2023; $year <= 2025; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $monthlyLeaveDays = 1 + (($idx + $year + $month) % 10);
                $leaveCount = 1 + (($idx + $month) % 3);
                $daysLeft = $monthlyLeaveDays;
                for ($i = 0; $i < $leaveCount && $daysLeft > 0; $i++) {
                    $type = $leaveTypes[($idx + $year + $month + $i) % count($leaveTypes)];
                    $maxChunk = min(3, $daysLeft);
                    $days = min($maxChunk, 1 + (($idx + $month + $i) % $maxChunk));
                    if ($i === $leaveCount - 1) {
                        $days = $daysLeft;
                    }
                    $days = max(1, min(10, $days));
                    $daysLeft -= $days;
                    $day = min(25, 2 + (($idx + $month + $i + $year) % 24));
                    $start = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $end = (new DateTimeImmutable($start))->modify('+' . ($days - 1) . ' day')->format('Y-m-d');
                    $reason = $leaveReasons[$type][($idx + $month + $i) % count($leaveReasons[$type])];
                    $status = $approvedStatusMap[($idx + $month + $i + $year) % count($approvedStatusMap)];
                    $applied = sprintf('%04d-%02d-%02d 09:%02d:00', $year, $month, min(28, $day), ($idx + $i) % 50);
                    $reviewed = $status === 'pending' ? null : sprintf('%04d-%02d-%02d 16:%02d:00', $year, $month, min(28, $day), ($idx + $i * 3) % 50);
                    $reviewedBy = $status === 'pending' ? null : (($idx + $i) % 2 === 0 ? 'A002' : 'A001');
                    $approvedBy = $status === 'pending' ? null : (($idx + $i) % 2 === 0 ? 'A001' : 'A002');
                    $remarks = $status === 'approved' ? 'Approved after document verification.' : null;
                    $stmt->execute([$empCode, $type, $start, $end, $days, $reason, $status, $applied, $reviewed, $reviewedBy, $approvedBy, $remarks]);
                }
            }
        }
    }

    $resigned = [
        ['EMP011','Anuradha','Wijeratne','Worker','Cutting','2020-02-10',52000,'0772223001','anuradha@tiptop.com','2025-02-28','A002','Resigned after relocation.','Contract'],
        ['EMP012','Madhawa','Gunasekara','Worker','Sewing','2021-06-14',54000,'0772223002','madhawa@tiptop.com','2025-04-30','A003','Accepted a job closer to home.','Permanent'],
        ['EMP013','Sewmini','Dissanayake','Worker','Ironing','2020-10-05',50500,'0772223003','sewmini@tiptop.com','2024-12-20','A001','Career change.','Contract'],
        ['EMP014','Buddhika','Ranasinghe','Worker','Packing','2022-01-18',49500,'0772223004','buddhika@tiptop.com','2025-08-12','A002','Family migration overseas.','Probation'],
    ];
    $stmt = $pdo->prepare('INSERT INTO resigned_employees (emp_code, first_name, last_name, designation, section, date_of_joining, basic_salary, contact_no, email, resigned_date, resigned_by, reason, employment_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($resigned as $r) {
        $stmt->execute($r);
    }

    $attendance = [];
    $timeOutOptions = ['17:20:00', '17:45:00', '18:15:00', '18:45:00', '19:30:00', '20:15:00'];
    $attendanceTails = [
        'EMP001' => 2,
        'EMP002' => 3,
        'EMP003' => 2,
        'EMP004' => 4,
        'EMP005' => 2,
        'EMP006' => 5,
        'EMP007' => 5,
        'EMP008' => 3,
        'EMP009' => 4,
        'EMP010' => 5,
    ];
    $absencePlan = [
        'EMP001' => 0,
        'EMP002' => 0,
        'EMP003' => 0,
        'EMP004' => 1,
        'EMP005' => 0,
        'EMP006' => 2,
        'EMP007' => 2,
        'EMP008' => 1,
        'EMP009' => 2,
        'EMP010' => 3,
    ];
    foreach (array_slice($employees, 0, 10) as $idx => $emp) {
        $code = $emp[0];
        $absenceUsed = 0;
        for ($year = 2023; $year <= 2025; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $scheduledDays = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $weekday = (int)date('N', strtotime($date));
                    if ($weekday >= 6) {
                        continue;
                    }
                    if (isset($holidayDates[$date])) {
                        continue;
                    }
                    $scheduledDays[] = $date;
                }

                $targetAbsences = $absencePlan[$code];
                $monthAbsences = min($targetAbsences, max(0, (int)floor(count($scheduledDays) * 0.12)));
                $absentDates = [];
                if ($monthAbsences > 0) {
                    $step = max(1, (int)floor(count($scheduledDays) / ($monthAbsences + 1)));
                    for ($a = 0; $a < $monthAbsences; $a++) {
                        $pick = min(count($scheduledDays) - 1, (($a + 1) * $step) - 1);
                        $absentDates[$scheduledDays[$pick]] = true;
                    }
                }

                foreach ($scheduledDays as $dIndex => $date) {
                    $status = isset($absentDates[$date]) ? 'absent' : 'present';
                    if ($status === 'absent') {
                        $absenceUsed++;
                        $attendance[] = [$code, $date, 'absent', null, null, null, 'No show', 'A002', $now, $now];
                        continue;
                    }
                    $timeIn = ['07:55:00','08:00:00','08:05:00','08:10:00'][$idx % 4];
                    $timeOut = $timeOutOptions[($idx + $month + $dIndex) % count($timeOutOptions)];
                    $noteOut = ($timeOut >= '19:30:00') ? 'Double OT' : (($timeOut > '17:00:00') ? 'OT' : null);
                    $attendance[] = [$code, $date, 'present', $timeIn, $timeOut, null, $noteOut, 'A003', $now, $now];
                }
            }
        }
    }
    $stmt = $pdo->prepare('INSERT INTO attendance (emp_code, attendance_date, status, time_in, time_out, note_in, note_out, marked_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($attendance as $a) {
        $stmt->execute($a);
    }

    $yearBase = [
        'EMP001' => 125000,
        'EMP002' => 135000,
        'EMP003' => 65000,
        'EMP004' => 62000,
        'EMP005' => 61000,
        'EMP006' => 58000,
        'EMP007' => 57500,
        'EMP008' => 60500,
        'EMP009' => 59000,
        'EMP010' => 54000,
    ];
    $annualRaise = [
        'EMP001' => 0.07, 'EMP002' => 0.06, 'EMP003' => 0.08, 'EMP004' => 0.07, 'EMP005' => 0.07,
        'EMP006' => 0.06, 'EMP007' => 0.06, 'EMP008' => 0.07, 'EMP009' => 0.06, 'EMP010' => 0.05,
    ];
    $months = range(1, 12);
    $payrollStmt = $pdo->prepare('INSERT INTO payroll (emp_code, pay_period_month, pay_period_year, basic_salary, allowance_target, allowance_attendance, allowance_meal, ot_payment, total_earnings, total_deductions, net_salary, epf_employee, epf_employer, etf_employer, payment_status, payment_date, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $incStmt = $pdo->prepare('INSERT INTO salary_increments (emp_code, previous_salary, increment_amount, increment_percentage, new_salary, effective_date, remarks, processed_by, processed_at) VALUES (?,?,?,?,?,?,?,?,?)');

    foreach ($yearBase as $code => $baseSalary) {
        $salary = $baseSalary;
        for ($year = 2023; $year <= 2025; $year++) {
            if ($year > 2023) {
                $pct = $annualRaise[$code];
                $prev = $salary;
                $salary = round($salary * (1 + $pct), 2);
                $incStmt->execute([$code, $prev, round($salary - $prev, 2), $pct * 100, $salary, sprintf('%04d-01-01', $year), 'Annual salary increment', 'A003', sprintf('%04d-01-01 09:00:00', $year)]);
            }
            foreach ($months as $month) {
                $allowTarget = ($month % 2 === 0) ? round(($salary * 0.12), 2) : round(($salary * 0.08), 2);
                $allowAttendance = in_array($month, [1,4,7,10], true) ? 6000 : 4000;
                $allowMeal = in_array($month, [6,7,8], true) ? 4500 : 3000;
                $otHours = (($month + strlen($code)) % 3) + 1;
                $doubleOtHours = (($month + ord(substr($code, 3, 1))) % 2);
                $otPayment = round(($otHours * 650) + ($doubleOtHours * 950), 2);
                $noPayDays = (
                    ($code === 'EMP010' && in_array($month, [5, 10, 11], true)) ? 1 :
                    (($code === 'EMP009' && in_array($month, [7, 9], true)) ? 1 : 0)
                );
                $noPayDeduction = $noPayDays * 2500;
                $gross = round($salary + $allowTarget + $allowAttendance + $allowMeal + $otPayment, 2);
                $deductions = round($noPayDeduction + ($salary * 0.08) + ($salary * 0.03), 2);
                $net = round($gross - $deductions, 2);
                $paymentStatus = ($year === 2025 && $month >= 5) ? 'pending' : 'paid';
                $paymentDate = $paymentStatus === 'paid' ? sprintf('%04d-%02d-28', $year, $month) : null;
                $payrollStmt->execute([
                    $code, $month, $year, $salary, $allowTarget, $allowAttendance, $allowMeal, $otPayment,
                    $gross, $deductions, $net, round($salary * 0.08, 2), round($salary * 0.12, 2), round($salary * 0.03, 2),
                    $paymentStatus, $paymentDate, sprintf('%04d-%02d-28 18:00:00', $year, $month), sprintf('%04d-%02d-28 18:00:00', $year, $month)
                ]);
            }
        }
    }

    $pdo->commit();
    $pdo->exec('PRAGMA foreign_keys = ON');
    echo "Demo data reset completed.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    $pdo->exec('PRAGMA foreign_keys = ON');
    throw $e;
}
