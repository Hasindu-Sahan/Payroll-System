<?php
session_start();
if(!isset($_SESSION['Login_Type'])||$_SESSION['Login_Type']!=='admin'){header('Location: login.php');exit;}
date_default_timezone_set('Asia/Colombo');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reports_storage.php';
$conn = db();
$admin_code=$_SESSION['admin_code'];
$s=$conn->prepare("SELECT * FROM admin WHERE admin_code=?");$s->bind_param("s",$admin_code);$s->execute();$admin=$s->get_result()->fetch_assoc();$s->close();
$admin_role=$admin['admin_role'] ?? '';
if($admin_role!=='owner'){header('Location: a_dashboard.php');exit;}

$selected = trim($_GET['file'] ?? '');
$snapshots = list_report_snapshots();
$selected_snapshot = $selected ? read_report_snapshot($selected) : null;

$page_title='View Reports';
$active_page='View Reports';
require 'a_adminLayout.php';
?>

<style>
/* ── Layout ── */
.reports-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 20px;
    align-items: start;
}

/* ── Sidebar ── */
.reports-sidebar {
    background: #fff;
    border: 1px solid #dbe3ee;
    border-radius: 16px;
    overflow: hidden;
    position: sticky;
    top: 20px;
}
.reports-sidebar-header {
    padding: 14px 18px;
    border-bottom: 1px solid #dbe3ee;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}
.reports-sidebar-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.reports-count {
    background: #1f3b67;
    color: #fff;
    font-size: 11.5px;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 20px;
}
.reports-list {
    max-height: calc(100vh - 240px);
    overflow-y: auto;
}
.reports-list::-webkit-scrollbar { width: 4px; }
.reports-list::-webkit-scrollbar-track { background: transparent; }
.reports-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.report-item {
    display: block;
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
    text-decoration: none;
    transition: background 0.15s;
    border-left: 3px solid transparent;
}
.report-item:hover { background: #f8fafc; }
.report-item.active {
    background: #eef3fb;
    border-left-color: #1f3b67;
}
.report-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 5px;
}
.report-item-title {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    flex: 1;
    line-height: 1.4;
}
.report-type-badge {
    font-size: 10.5px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
    flex-shrink: 0;
    margin-top: 1px;
}
.badge-payroll { background: #e0f2fe; color: #0369a1; }
.badge-hr      { background: #dcfce7; color: #15803d; }

.report-item-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11.5px;
    color: #94a3b8;
}
.report-item-meta i { font-size: 10px; opacity: 0.7; }

.report-empty-sidebar {
    padding: 40px 20px;
    text-align: center;
    color: #94a3b8;
}
.report-empty-sidebar i {
    font-size: 28px;
    margin-bottom: 10px;
    opacity: 0.35;
    display: block;
}

/* ── Preview Panel ── */
.report-preview-wrap {
    background: #fff;
    border: 1px solid #dbe3ee;
    border-radius: 16px;
    overflow: hidden;
    min-height: 500px;
}

/* Preview Header */
.report-preview-header {
    padding: 14px 20px;
    border-bottom: 1px solid #e8edf5;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.preview-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.preview-icon {
    width: 38px;
    height: 38px;
    background: #1f3b67;
    color: #fff;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.preview-label {
    font-size: 11px;
    font-weight: 500;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 2px;
}
.preview-title {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.3;
}
.preview-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.preview-meta-pill {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 4px 11px;
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}
.preview-meta-pill i { font-size: 10px; opacity: 0.65; }

/* Preview Body — scoped styles for the rendered report */
.report-preview-body {
    padding: 28px;
    overflow: auto;
}
.report-preview-body .report-print-card {
    border: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
}
.report-preview-body .report-logo {
    width: 52px !important;
    height: 52px !important;
}
.report-preview-body .report-company {
    font-size: 20px !important;
}
.report-preview-body .report-meta {
    font-size: 12px !important;
}
.report-preview-body .report-title {
    font-size: 16px !important;
    margin-top: 6px !important;
}
.report-preview-body .report-header {
    margin-bottom: 20px !important;
}
.report-preview-body .report-grid th {
    font-size: 12.5px !important;
    padding: 8px 10px !important;
}
.report-preview-body .report-grid td {
    font-size: 13px !important;
    padding: 7px 10px !important;
}

/* ── Empty / Error States ── */
.report-state-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 30px;
    text-align: center;
    min-height: 460px;
}
.report-state-icon {
    width: 68px;
    height: 68px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 18px;
}
.report-state-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
.report-state-desc {
    margin: 0 0 20px;
    font-size: 13.5px;
    color: #64748b;
    max-width: 300px;
    line-height: 1.6;
}
.report-state-hint {
    font-size: 12.5px;
    color: #94a3b8;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 9px 18px;
}

/* ── Responsive ── */
@media (max-width: 960px) {
    .reports-layout { grid-template-columns: 1fr; }
    .reports-sidebar { position: static; }
    .reports-list { max-height: 280px; }
}
@media print {
    .sidebar, .topbar, .page-header, .reports-sidebar,
    .report-preview-header, footer { display: none !important; }
    .reports-layout { display: block; }
    .report-preview-wrap { border: none; border-radius: 0; }
    .report-preview-body { padding: 0; }
    .main { margin-left: 0; }
    .page { padding: 0; }
}
</style>

<header class="topbar">
    <div class="topbar-left">
        <h1>View Reports</h1>
        <p>Saved payroll and HR report snapshots</p>
    </div>
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
                View Reports
            </div>
            <h2>Saved Reports</h2>
            <p>Reports generated by Payroll and HR managers, stored here for your review.</p>
        </div>
    </div>

    <div class="reports-layout">

        <!-- ── LEFT SIDEBAR ── -->
        <div class="reports-sidebar">
            <div class="reports-sidebar-header">
                <h3>
                    <i class="fa-solid fa-folder-open" style="opacity:.65;"></i>
                    All Reports
                </h3>
                <span class="reports-count"><?php echo count($snapshots); ?></span>
            </div>
            <div class="reports-list">
                <?php if(empty($snapshots)): ?>
                    <div class="report-empty-sidebar">
                        <i class="fa-solid fa-inbox"></i>
                        <p style="margin:0;font-size:13px;">No reports generated yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($snapshots as $snap):
                        $isActive   = ($selected === $snap['file']);
                        $isPayroll  = strtolower($snap['report_type'] ?? '') === 'payroll';
                        $badgeClass = $isPayroll ? 'badge-payroll' : 'badge-hr';
                        $badgeLabel = $isPayroll ? 'Payroll' : 'HR';
                    ?>
                    <a href="?file=<?php echo urlencode($snap['file']); ?>"
                       class="report-item <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="report-item-top">
                            <div class="report-item-title"><?php echo htmlspecialchars($snap['report_title']); ?></div>
                            <span class="report-type-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                        </div>
                        <div class="report-item-meta">
                            <span><i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($snap['generated_at']); ?></span>
                            <span><i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($snap['generated_by']); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── RIGHT PANEL: Report Preview ── -->
        <div class="report-preview-wrap">
            <?php if($selected_snapshot && !empty($selected_snapshot['html'])): ?>

                <div class="report-preview-header">
                    <div class="preview-header-left">
                        <div class="preview-icon">
                            <i class="fa-solid fa-file-chart-column"></i>
                        </div>
                        <div>
                            <div class="preview-label">Viewing Report</div>
                            <div class="preview-title"><?php echo htmlspecialchars($selected_snapshot['report_title'] ?? 'Report Preview'); ?></div>
                        </div>
                    </div>
                    <div class="preview-header-right">
                        <div class="preview-meta-pill">
                            <i class="fa-solid fa-clock"></i>
                            <?php echo htmlspecialchars($selected_snapshot['generated_at'] ?? ''); ?>
                        </div>
                        <div class="preview-meta-pill">
                            <i class="fa-solid fa-circle-user"></i>
                            <?php echo htmlspecialchars($selected_snapshot['generated_by'] ?? ''); ?>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Print / PDF
                        </button>
                    </div>
                </div>

                <div class="report-preview-body">
                    <?php echo base64_decode($selected_snapshot['html']); ?>
                </div>

            <?php elseif($selected): ?>

                <div class="report-state-wrap">
                    <div class="report-state-icon" style="background:#fef2f2;color:#dc2626;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3 class="report-state-title">Report Not Found</h3>
                    <p class="report-state-desc">This report file could not be located. It may have been moved or deleted.</p>
                    <a href="?" class="btn btn-ghost btn-sm">
                        <i class="fa-solid fa-arrow-left"></i> Back to Reports
                    </a>
                </div>

            <?php else: ?>

                <div class="report-state-wrap">
                    <div class="report-state-icon" style="background:#eef3fb;color:#1f3b67;">
                        <i class="fa-solid fa-file-chart-column"></i>
                    </div>
                    <h3 class="report-state-title">No Report Selected</h3>
                    <p class="report-state-desc">Select a report from the list on the left to preview it here.</p>
                    <div class="report-state-hint">
                        <i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i>
                        Choose from <?php echo count($snapshots); ?> saved report<?php echo count($snapshots) !== 1 ? 's' : ''; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<footer>
    <strong>&copy; <?php echo date('Y'); ?> Tiptop Apparel Payroll Management System</strong>
    &nbsp;|&nbsp; Admin Panel
</footer>
</div>