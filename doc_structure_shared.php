<?php
// Shared printable document structure for Tiptop Apparel (Pvt) Ltd
// Provides: bordered container + standardized header + standardized table + standardized sign-off area.
//
// Usage:
//   require_once __DIR__.'/doc_structure_shared.php';
//   tt_doc_begin($title,$month,$extraVars);
//   tt_doc_table_begin($headers);
//   tt_doc_table_row([...], $isTotalRow=false, $rowIndex=0);
//   tt_doc_table_total_row([...]);
//   tt_doc_signoff();
//   tt_doc_end();

function tt_escape($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function tt_doc_begin(string $title, string $month): void {
    echo "<div class=\"tt-doc-container\">";
    echo "<div class=\"tt-doc-header\">";
    echo "<div class=\"tt-doc-company\">TIP TOP APPAREL (PVT) LTD</div>";
    echo "<div class=\"tt-doc-address\">No.175/2 Kandy Road, Pasyala.</div>";
    echo "<div class=\"tt-doc-contact\">Tel: 0714595001</div>";
    echo "<div class=\"tt-doc-title\">".tt_escape($title)."</div>";
    echo "<div class=\"tt-doc-month\">".tt_escape($month)."</div>";
    echo "</div>";
}

function tt_doc_css(): void {
    echo <<<CSS
<style>
.tt-doc-container{
  border:1px solid #1f2937;
  padding:18px 16px 14px;
  max-width:980px;
  margin:0 auto;
  background:#fff;
  font-family: Arial, Helvetica, sans-serif;
  color:#111827;
}
.tt-doc-header{text-align:center;margin-bottom:14px;}
.tt-doc-company{font-weight:800;font-size:22px;letter-spacing:.02em;}
.tt-doc-address{margin-top:4px;font-size:15px;}
.tt-doc-contact{margin-top:4px;font-size:15px;}
.tt-doc-title{margin-top:10px;font-weight:800;font-size:18px;text-transform:lowercase;}
.tt-doc-month{margin-top:6px;font-size:16px;font-weight:700;}

.tt-leave-table{
  width:100%;
  border-collapse:collapse;
  table-layout:fixed;
}
.tt-leave-table th,.tt-leave-table td{
  border:1px solid #111827;
  padding:9px 6px;
  font-size:14px;
  word-wrap:break-word;
}
.tt-leave-table thead th{
  background:#f3f4f6;
  font-weight:800;
  text-align:left;
}
.tt-leave-table tbody tr:nth-child(odd){ background:#ffffff; }
.tt-leave-table tbody tr:nth-child(even){ background:#f9fafb; }

.tt-total-row td{
  font-weight:900;
  background:#f3f4f6;
}

.tt-signoff{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
  margin-top:18px;
}
.tt-signcol{display:flex;flex-direction:column;gap:10px;}
.tt-signline{
  display:grid;
  grid-template-columns:120px 1fr;
  align-items:center;
  gap:10px;
  font-size:14px;
}
.tt-signlabel{font-weight:800;}
.tt-dotted{
  border-bottom:2px dotted #111827;
  height:18px;
}

/* print: keep this doc visible */
@media print{
  .tt-doc-container{margin:0;max-width:none;}
}
</style>
CSS;
}

function tt_doc_table_begin(array $headers): void {
    tt_doc_css();
    echo "<table class=\"tt-leave-table\">";
    echo "<thead><tr>";
    foreach($headers as $h){
        echo "<th>".tt_escape($h)."</th>";
    }
    echo "</tr></thead>";
    echo "<tbody>";
}

function tt_doc_table_row(array $cells, int $rowIndex, bool $isTotalRow=false): void {
    $cls = $isTotalRow ? ' class="tt-total-row"' : '';
    echo "<tr$cls>";
    for($i=0;$i<6;$i++){
        $v = $cells[$i] ?? '&nbsp;';
        echo "<td>".tt_escape($v)."</td>";
    }
    echo "</tr>";
}

function tt_doc_table_total_row(array $cells): void {
    tt_doc_table_row($cells, 999, true);
}

function tt_doc_signoff(): void {
    echo "<div class=\"tt-signoff\">";

    // Left: Issued date / Approved date
    echo "<div class=\"tt-signcol\">";
    echo "<div class=\"tt-signline\"><div class=\"tt-signlabel\">Issued date</div><div class=\"tt-dotted\"></div></div>";
    echo "<div class=\"tt-signline\"><div class=\"tt-signlabel\">Approved date</div><div class=\"tt-dotted\"></div></div>";
    echo "</div>";

    // Right: Issued by / Approved by
    echo "<div class=\"tt-signcol\">";
    echo "<div class=\"tt-signline\"><div class=\"tt-signlabel\">Issued by</div><div class=\"tt-dotted\"></div></div>";
    echo "<div class=\"tt-signline\"><div class=\"tt-signlabel\">Approved by</div><div class=\"tt-dotted\"></div></div>";
    echo "</div>";

    echo "</div>";
}

function tt_doc_end(): void {
    echo "</div>"; // .tt-doc-container
}

