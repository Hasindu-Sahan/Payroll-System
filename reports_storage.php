<?php
if (!function_exists('report_storage_dir')) {
    function report_storage_dir(): string {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'generated_reports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('sanitize_report_filename')) {
    function sanitize_report_filename(string $value): string {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
        $value = trim($value, '._-');
        return $value !== '' ? $value : 'report';
    }
}

if (!function_exists('report_snapshot_path')) {
    function report_snapshot_path(string $reportType, string $reportTitle, string $generatedAt): string {
        $slug = sanitize_report_filename($reportType);
        $title = sanitize_report_filename($reportTitle);
        $stamp = preg_replace('/[^0-9]+/', '', $generatedAt);
        return report_storage_dir() . DIRECTORY_SEPARATOR . "{$stamp}_{$slug}_{$title}.html";
    }
}

if (!function_exists('save_report_snapshot')) {
    function save_report_snapshot(array $meta, string $html): string {
        $generatedAt = $meta['generated_at'] ?? date('Y-m-d_His');
        $path = report_snapshot_path(
            (string)($meta['report_type'] ?? 'report'),
            (string)($meta['report_title'] ?? 'Report'),
            $generatedAt
        );

        $payload = [
            'meta' => $meta,
            'html' => base64_encode($html),
        ];

        file_put_contents($path . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return basename($path . '.json');
    }
}

if (!function_exists('list_report_snapshots')) {
    function list_report_snapshots(): array {
        $dir = report_storage_dir();
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $items = [];

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            $data = $raw ? json_decode($raw, true) : null;
            if (!is_array($data) || empty($data['meta']) || !is_array($data['meta'])) {
                continue;
            }
            $meta = $data['meta'];
            $items[] = [
                'file' => basename($file),
                'report_type' => (string)($meta['report_type'] ?? 'report'),
                'report_title' => (string)($meta['report_title'] ?? 'Report'),
                'generated_at' => (string)($meta['generated_at'] ?? ''),
                'generated_by' => (string)($meta['generated_by'] ?? ''),
                'admin_role' => (string)($meta['admin_role'] ?? ''),
                'month' => (string)($meta['month'] ?? ''),
                'year' => (string)($meta['year'] ?? ''),
            ];
        }

        usort($items, function($a, $b){
            return strcmp($b['generated_at'], $a['generated_at']);
        });

        return $items;
    }
}

if (!function_exists('read_report_snapshot')) {
    function read_report_snapshot(string $file): ?array {
        $path = report_storage_dir() . DIRECTORY_SEPARATOR . basename($file);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }
}
