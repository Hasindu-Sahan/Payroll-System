<?php
/**
 * One-time migration: MySQL dump (Login & Database/p_db.sql) -> SQLite (p_db.sqlite)
 *
 * Safe behavior:
 * - Creates a SQLite schema if needed (idempotent CREATE TABLE IF NOT EXISTS).
 * - Loads INSERT statements into tables.
 * - Refuses to run if marker file exists.
 */

declare(strict_types=1);

$marker = __DIR__ . DIRECTORY_SEPARATOR . '.migration_p_db_sqlite.done';
if (file_exists($marker)) {
    http_response_code(409);
    echo 'Migration already completed. Marker file: ' . htmlspecialchars(basename($marker));
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$mysqlDumpPath = __DIR__ . DIRECTORY_SEPARATOR . 'Login & Database' . DIRECTORY_SEPARATOR . 'p_db.sql';
if (!file_exists($mysqlDumpPath)) {
    http_response_code(500);
    echo 'Missing dump file: ' . htmlspecialchars($mysqlDumpPath);
    exit;
}

$dump = file_get_contents($mysqlDumpPath);
if ($dump === false) {
    http_response_code(500);
    echo 'Unable to read dump file.';
    exit;
}

$conn = db();
$pdo = $conn->getPdo();
$pdo->exec('PRAGMA foreign_keys = ON');

// --- Helpers ---
function exec_ignoring(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* ignore */ }
}

function split_sql_statements(string $sql): array {
    // Very small splitter: splits on semicolons that end a statement.
    // This is good enough for the phpMyAdmin dump structure we have.
    $parts = preg_split('/;\s*\n/', $sql);
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

// We will parse only:
// - CREATE TABLE ... (for missing schema columns)
// - INSERT INTO ... VALUES ...

// 1) Ensure schema exists (extend db.php schema to include missing tables/columns).
// We do this here too, so migration works even if db.php schema is partial.

$pdo->exec('CREATE TABLE IF NOT EXISTS admin (
  admin_id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_code TEXT UNIQUE NOT NULL,
  admin_name TEXT NOT NULL,
  admin_email TEXT,
  admin_password TEXT NOT NULL,
  admin_role TEXT NOT NULL DEFAULT "owner",
  admin_time TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS admin_employee_link (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_code TEXT NOT NULL,
  emp_code TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS employees (
  emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT UNIQUE NOT NULL,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  email TEXT NOT NULL,
  password TEXT NOT NULL,
  gender TEXT NOT NULL,
  date_of_birth TEXT NOT NULL,
  contact_no TEXT NOT NULL,
  address TEXT NOT NULL,
  city TEXT NOT NULL,
  designation TEXT NOT NULL,
  section TEXT NOT NULL,
  date_of_joining TEXT NOT NULL,
  employment_type TEXT NOT NULL,
  basic_salary REAL NOT NULL,
  allowance_target REAL DEFAULT 0,
  allowance_attendance REAL DEFAULT 0,
  allowance_meal REAL DEFAULT 0,
  bank_name TEXT,
  bank_account_no TEXT,
  status TEXT DEFAULT "active",
  created_at TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS attendance (
  attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT NOT NULL,
  attendance_date TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT "absent",
  time_in TEXT,
  time_out TEXT,
  note_in TEXT,
  note_out TEXT,
  marked_by TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS holidays (
  holiday_id INTEGER PRIMARY KEY AUTOINCREMENT,
  holiday_date TEXT NOT NULL,
  holiday_name TEXT NOT NULL,
  holiday_desc TEXT,
  created_at TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS leavelimits (
  limit_id INTEGER PRIMARY KEY AUTOINCREMENT,
  annual_allowed_leaves INTEGER NOT NULL DEFAULT 24,
  deduction_per_day REAL NOT NULL DEFAULT 2000,
  manager_annual_leaves INTEGER NOT NULL DEFAULT 24,
  manager_deduction_per_day REAL NOT NULL DEFAULT 3000
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS leaves (
  leave_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT NOT NULL,
  leave_type TEXT NOT NULL,
  start_date TEXT NOT NULL,
  end_date TEXT NOT NULL,
  no_of_days INTEGER NOT NULL,
  reason TEXT NOT NULL,
  leave_status TEXT DEFAULT "pending",
  admin_remarks TEXT,
  applied_date TEXT NOT NULL,
  reviewed_date TEXT,
  reviewed_by TEXT,
  approved_by TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS payheads (
  payhead_id INTEGER PRIMARY KEY AUTOINCREMENT,
  payhead_name TEXT NOT NULL,
  payhead_type TEXT NOT NULL,
  calculation_type TEXT NOT NULL,
  default_value REAL DEFAULT 0,
  is_active INTEGER DEFAULT 1,
  description TEXT,
  created_at TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS payroll (
  payroll_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT NOT NULL,
  pay_period_month INTEGER NOT NULL,
  pay_period_year INTEGER NOT NULL,
  basic_salary REAL NOT NULL,
  allowance_target REAL DEFAULT 0,
  allowance_attendance REAL DEFAULT 0,
  allowance_meal REAL DEFAULT 0,
  ot_payment REAL DEFAULT 0,
  total_earnings REAL DEFAULT 0,
  total_deductions REAL DEFAULT 0,
  net_salary REAL DEFAULT 0,
  epf_employee REAL DEFAULT 0,
  epf_employer REAL DEFAULT 0,
  etf_employer REAL DEFAULT 0,
  payment_status TEXT DEFAULT "pending",
  payment_date TEXT,
  created_at TEXT,
  updated_at TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS resigned_employees (
  resigned_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT NOT NULL,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  designation TEXT NOT NULL,
  section TEXT NOT NULL,
  date_of_joining TEXT NOT NULL,
  basic_salary REAL NOT NULL,
  contact_no TEXT NOT NULL,
  email TEXT NOT NULL,
  resigned_date TEXT NOT NULL,
  resigned_by TEXT,
  reason TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS salary_increments (
  increment_id INTEGER PRIMARY KEY AUTOINCREMENT,
  emp_code TEXT NOT NULL,
  previous_salary REAL NOT NULL,
  increment_amount REAL DEFAULT 0,
  increment_percentage REAL DEFAULT 0,
  new_salary REAL NOT NULL,
  effective_date TEXT NOT NULL,
  remarks TEXT,
  processed_by TEXT,
  processed_at TEXT NOT NULL
)');

// 2) Parse INSERTs
$insertRegex = '/INSERT\s+INTO\s+`?(?<table>[a-zA-Z_]+)`?\s*\((?<cols>[^\)]*)\)\s*VALUES\s*(?<values>[^;]*);/m';

$ok = 0; $skipped = 0;

if (preg_match_all($insertRegex, $dump, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $table = $m['table'];
        $colsRaw = trim($m['cols']);
        $valuesRaw = trim($m['values']);

        // Clean columns
        $cols = array_map(function($c) {
            $c = trim($c, " \t\r\n\`");
            return $c;
        }, explode(',', $colsRaw));

        // Split multi-row values: (....), (....)
        // We'll do a simple state machine for top-level parentheses.
        $rows = [];
        $len = strlen($valuesRaw);
        $depth = 0; $start = 0;
        for ($i=0; $i<$len; $i++) {
            $ch = $valuesRaw[$i];
            if ($ch === '(') {
                if ($depth === 0) $start = $i;
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $rows[] = substr($valuesRaw, $start+1, $i-$start-1);
                }
            }
        }

        foreach ($rows as $row) {
            // Convert SQL literals into PHP strings for binding.
            // Approach: tokenise by commas not inside quotes.
            $vals = [];
            $token = '';
            $inQuote = false;
            $escape = false;
            for ($i=0; $i<strlen($row); $i++) {
                $c = $row[$i];
                if ($inQuote) {
                    if ($escape) {
                        $token .= $c;
                        $escape = false;
                        continue;
                    }
                    if ($c === "\\") { $token .= $c; $escape = true; continue; }
                    if ($c === "'" ) { $inQuote = false; $token .= $c; continue; }
                    $token .= $c;
                } else {
                    if ($c === "'") { $inQuote = true; $token .= $c; continue; }
                    if ($c === ',') { $vals[] = trim($token); $token=''; continue; }
                    $token .= $c;
                }
            }
            if (trim($token) !== '') $vals[] = trim($token);

            if (count($vals) !== count($cols)) {
                $skipped++;
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $colList = implode(',', array_map(fn($c) => "`".$c."`", $cols));

            $sql = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})";

            $params = [];
            foreach ($vals as $v) {
                $v = trim($v);
                if (strcasecmp($v, 'NULL') === 0) {
                    $params[] = null;
                    continue;
                }
                // if it's a quoted string: '...'
                if (strlen($v) >= 2 && $v[0] === "'" && $v[strlen($v)-1] === "'") {
                    $str = substr($v, 1, -1);
                    // MySQL dump may contain escaped sequences; keep as-is except unescaping backslash-escaped quotes.
                    $str = str_replace("\\'", "'", $str);
                    $params[] = $str;
                    continue;
                }
                // enum literals are also strings in dumps; but our regex includes quotes already.
                // numeric
                if (is_numeric($v)) {
                    $params[] = $v + 0;
                    continue;
                }
                $params[] = $v;
            }

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $ok++;
            } catch (Throwable $e) {
                // Skip duplicates or mismatches (safe migration)
                $skipped++;
            }
        }
    }
}

touch($marker);

echo 'Migration completed into SQLite. Inserts executed: ' . (int)$ok . '. Skipped: ' . (int)$skipped . '. Marker: ' . htmlspecialchars(basename($marker));

