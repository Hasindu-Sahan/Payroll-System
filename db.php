<?php
/**
 * db.php — SQLite bootstrap with MySQLi-compatible wrapper for a portable standalone deployment.
 *
 * - Creates p_db.sqlite automatically if missing
 * - Creates schema (tables + indexes) on first run
 * - Returns a MySQLiCompat object that mimics MySQLi's interface but uses PDO / SQLite underneath
 */

if (session_status() === PHP_SESSION_NONE) {
    // no-op; keep side effects minimal
}

// ── PDO helper (internal) ──────────────────────────────────────────────────────

function _pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $baseDir = __DIR__;
    $dbFile = $baseDir . DIRECTORY_SEPARATOR . 'p_db.sqlite';

    // Optional debug log (best-effort). Helps confirm DB path on pen-drive copies.
    $logLine = sprintf("%s db.php using sqlite file: %s\n", date('c'), $dbFile);
    @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'db-debug.log', $logLine, FILE_APPEND);

    $needInit = !file_exists($dbFile);

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Ensure core schema exists.
    init_schema($pdo);

    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin (
        admin_id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_code TEXT UNIQUE NOT NULL,
        admin_name TEXT NOT NULL,
        admin_email TEXT NOT NULL,
        admin_password TEXT NOT NULL,
        admin_role TEXT NOT NULL DEFAULT "owner",
        admin_time TEXT
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS resigned_employees (
        resigned_id INTEGER PRIMARY KEY AUTOINCREMENT,
        emp_code TEXT NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        employment_type TEXT,
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

    $resignedCols = [];
    foreach ($pdo->query("PRAGMA table_info(resigned_employees)") as $col) {
        $resignedCols[] = strtolower((string)($col['name'] ?? ''));
    }
    if (!in_array('employment_type', $resignedCols, true)) {
        @$pdo->exec('ALTER TABLE resigned_employees ADD COLUMN employment_type TEXT');
    }

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
        approved_by TEXT DEFAULT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS attendance (
        attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
        emp_code TEXT NOT NULL,
        attendance_date TEXT NOT NULL,
        status TEXT DEFAULT "absent",
        time_in TEXT,
        time_out TEXT,
        note_in TEXT,
        note_out TEXT,
        marked_by TEXT,
        created_at TEXT,
        updated_at TEXT
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
        annual_allowed_leaves INTEGER NOT NULL DEFAULT 14,
        casual_allowed_leaves INTEGER NOT NULL DEFAULT 7,
        deduction_per_day REAL NOT NULL DEFAULT 2000,
        manager_annual_leaves INTEGER NOT NULL DEFAULT 24,
        manager_deduction_per_day REAL NOT NULL DEFAULT 3000
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_employee_link (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_code TEXT NOT NULL,
        emp_code TEXT NOT NULL
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

    // Helpful indexes
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_employees_emp_code ON employees(emp_code)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_date_emp ON attendance(attendance_date, emp_code)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leaves_emp ON leaves(emp_code)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_payroll_period ON payroll(pay_period_year, pay_period_month, emp_code)');

    // Lightweight schema migration for existing SQLite databases.
    $ensureColumn = function(PDO $pdo, string $table, string $column, string $definition): void {
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    };
    $ensureColumn($pdo, 'leavelimits', 'casual_allowed_leaves', 'INTEGER NOT NULL DEFAULT 7');

    // Seed admin + leavelimits if empty
    $countAdmins = (int)$pdo->query('SELECT COUNT(*) c FROM admin')->fetch()['c'];
    if ($countAdmins === 0) {
        $stmt = $pdo->prepare('INSERT INTO admin (admin_code, admin_name, admin_email, admin_password, admin_role, admin_time) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            'A001',
            'Default Admin',
            'admin@example.com',
            sha1('admin123'),
            'owner',
            date('Y-m-d H:i:s')
        ]);
    }

    $countLimits = (int)$pdo->query('SELECT COUNT(*) c FROM leavelimits')->fetch()['c'];
    if ($countLimits === 0) {
        $stmt = $pdo->prepare('INSERT INTO leavelimits (annual_allowed_leaves, casual_allowed_leaves, deduction_per_day, manager_annual_leaves, manager_deduction_per_day) VALUES (?,?,?,?,?)');
        $stmt->execute([14, 7, 2000, 24, 3000]);
    }

    // Employee seed data (login testing) — INSERT missing only, never overwrite.
    // Expected by www/login.php:
    //  - identifier: emp_code or email
    //  - password: sha1(raw password)
    $stmtInsert = $pdo->prepare(
        'INSERT INTO employees (
            emp_code, first_name, last_name, email, password, gender, date_of_birth, contact_no, address, city,
            designation, section, date_of_joining, employment_type, basic_salary,
            allowance_target, allowance_attendance, allowance_meal,
            bank_name, bank_account_no, status, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $now = date('Y-m-d H:i:s');
    $dob = '1995-01-01';
    $joining = date('Y-m-d');

    for ($i = 1; $i <= 12; $i++) {
        $code = sprintf('EMP%03d', $i); // EMP001
        $email = 'e' . str_pad((string)$i, 3, '0', STR_PAD_LEFT) . '@tiptop.com'; // e001@...
        $rawPw = 'emp@' . str_pad((string)$i, 3, '0', STR_PAD_LEFT); // emp@001...
        $pw = sha1($rawPw);

        $exists = (int)$pdo->query("SELECT COUNT(*) c FROM employees WHERE emp_code=" . $pdo->quote($code))->fetch()['c'];
        if ($exists > 0) {
            continue;
        }

        $stmtInsert->execute([
            $code,
            'Employee',
            (string)$i,
            $email,
            $pw,
            'M',
            $dob,
            '0770000' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'No 1, Test Street',
            'Colombo',
            'Staff',
            'Administration',
            $joining,
            'Full-time',
            50000,
            0,
            0,
            0,
            'Test Bank',
            '0000000000' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'active',
            $now
        ]);
    }
}

// Define MySQLi constants if not already defined
if (!defined('MYSQLI_ASSOC')) define('MYSQLI_ASSOC', 1);
if (!defined('MYSQLI_NUM'))    define('MYSQLI_NUM', 2);
if (!defined('MYSQLI_BOTH'))   define('MYSQLI_BOTH', 3);

// ── MySQLi Compatibility Wrapper ───────────────────────────────────────────────

class MySQLiCompat_Result {
    private array $rows = [];
    private int $pos = 0;
    public int $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    // MySQLi compatibility: some pages mistakenly call ->fetch()
    // Provide it so older code paths don't fatal-error.
    public function fetch(): ?array {
        return $this->fetch_assoc();
    }

    public function fetch_assoc(): ?array {
        if ($this->pos >= $this->num_rows) return null;
        return $this->rows[$this->pos++];
    }


    public function fetch_all(int $mode = MYSQLI_ASSOC): array {
        return $this->rows;
    }

    public function fetch_row(): ?array {
        if ($this->pos >= $this->num_rows) return null;
        return array_values($this->rows[$this->pos++]);
    }

    public function fetchColumn(int $col = 0): mixed {
        if ($this->pos >= $this->num_rows) return null;
        $vals = array_values($this->rows[$this->pos++]);
        return $vals[$col] ?? null;
    }
}

class MySQLiCompat_Stmt {
    private PDOStatement $stmt;
    private array $boundParams = [];
    public ?MySQLiCompat_Result $result = null;
    public int $affected_rows = 0;
    public int $num_rows = 0;

    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function bind_param(string $types, mixed &...$vars): bool {
        $this->boundParams = [];
        foreach ($vars as $v) {
            $this->boundParams[] = $v;
        }
        return true;
    }

    public function execute(): bool {
        try {
            $this->stmt->execute($this->boundParams);
            try {
                $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->result = new MySQLiCompat_Result($rows);
                $this->num_rows = count($rows);
                $this->affected_rows = $this->stmt->rowCount();
            } catch (\Throwable $e) {
                $this->affected_rows = $this->stmt->rowCount();
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function get_result(): ?MySQLiCompat_Result {
        return $this->result;
    }

    public function close(): bool {
        $this->stmt->closeCursor();
        return true;
    }

    public function store_result(): bool { return true; }

    public function free_result(): void { $this->stmt->closeCursor(); }

    public function affected_rows(): int { return $this->affected_rows; }
}

class MySQLiCompat {
    public string $error = '';
    public ?string $connect_error = null;
    public int $errno = 0;
    public int $affected_rows = 0;

    private PDO $pdo;

    public function __construct() {
        try {
            $this->pdo = _pdo();
            $this->connect_error = null;
            $this->errno = 0;
        } catch (\Throwable $e) {
            $this->connect_error = $e->getMessage();
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
        }
    }

    public function prepare(string $query): MySQLiCompat_Stmt|false {
        try {
            $query = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $query);
            $query = preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $query);
            $query = preg_replace(
                '/DATE_ADD\s*\(\s*(\S+?)\s*,\s*INTERVAL\s+(\d+)\s+(DAY|MONTH|YEAR)\s*\)/i',
                "date($1, '+$2 $3')",
                $query
            );
            $query = preg_replace(
                '/DATE_SUB\s*\(\s*(\S+?)\s*,\s*INTERVAL\s+(\d+)\s+(DAY|MONTH|YEAR)\s*\)/i',
                "date($1, '-$2 $3')",
                $query
            );
            $query = preg_replace(
                '/\bYEAR\s*\(\s*(\S+?)\s*\)\s*=\s*\?/i',
                "strftime('%Y', $1) = ?",
                $query
            );

            $stmt = $this->pdo->prepare($query);
            if ($stmt === false) return false;
            return new MySQLiCompat_Stmt($stmt);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            return false;
        }
    }

    public function query(string $sql): MySQLiCompat_Result|bool {
        try {
            $sql = preg_replace('/\bNOW\s*\(\s*\)/i', "datetime('now')", $sql);
            $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $sql);

            $stmt = $this->pdo->query($sql);
            $trimmed = trim(strtoupper($sql));
            if (str_starts_with($trimmed, 'SELECT') || str_starts_with($trimmed, 'WITH') || str_starts_with($trimmed, 'PRAGMA')) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->affected_rows = count($rows);
                return new MySQLiCompat_Result($rows);
            }

            $this->affected_rows = $stmt->rowCount();
            return true;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            return false;
        }
    }

    public function real_escape_string(string $str): string {
        return str_replace("'", "''", $str);
    }

    public function insert_id(): string {
        return $this->pdo->lastInsertId();
    }

    public function close(): bool { return true; }

    public function error_list(): array {
        if ($this->error) return [['errno' => $this->errno, 'error' => $this->error]];
        return [];
    }

    public function exec(string $sql): int|false {
        try {
            return $this->pdo->exec($sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            return false;
        }
    }

    public function getPdo(): PDO { return $this->pdo; }

    public function getAttribute(int $attr): mixed {
        try {
            return $this->pdo->getAttribute($attr);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

function db(): MySQLiCompat {
    static $conn = null;
    if ($conn instanceof MySQLiCompat) return $conn;
    $conn = new MySQLiCompat();
    return $conn;
}

