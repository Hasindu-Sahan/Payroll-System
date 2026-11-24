<?php
require_once __DIR__ . '/reports_storage.php';

if (!function_exists('report_month_label')) {
    function report_month_label(int $month): string {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        return $months[$month] ?? 'January';
    }
}

if (!function_exists('report_period_title')) {
    function report_period_title(int $month, int $year): string {
        return report_month_label($month) . ' ' . $year;
    }
}

if (!function_exists('report_table_currency')) {
    function report_table_currency($value): string {
        return number_format((float)$value, 2);
    }
}

if (!function_exists('report_employee_name')) {
    function report_employee_name(array $row): string {
        return trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    }
}

if (!function_exists('report_employee_section')) {
    function report_employee_section(array $row): string {
        return (string)($row['section'] ?? '');
    }
}

if (!function_exists('report_employee_type')) {
    function report_employee_type(array $row): string {
        return (string)($row['employment_type'] ?? '');
    }
}

if (!function_exists('report_safe_int')) {
    function report_safe_int($value, int $default = 0): int {
        return is_numeric($value) ? (int)$value : $default;
    }
}

if (!function_exists('report_month_bounds')) {
    function report_month_bounds(int $month, int $year): array {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }
}

if (!function_exists('report_generate_meta')) {
    function report_generate_meta(string $reportType, string $reportTitle, int $month, int $year, array $admin): array {
        return [
            'report_type' => $reportType,
            'report_title' => $reportTitle,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => (string)($admin['admin_name'] ?? ''),
            'admin_code' => (string)($admin['admin_code'] ?? ''),
            'admin_role' => (string)($admin['admin_role'] ?? ''),
            'month' => $month,
            'year' => $year,
        ];
    }
}
