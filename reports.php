<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/ai_engine.php';

$types = [
  'inventory'  => ['INV',  'Inventory Report',   'Current products and stock levels.'],
  'sales'      => ['SLS',  'Sales Report',       'Recorded sales and total revenue.'],
  'low_stock'  => ['LOW',  'Low-Stock Report',   'Products that need restocking.'],
  'high_stock' => ['HIGH', 'High-Stock Report',  'Products with large stock on hand.'],
  'best_seller'=> ['TOP',  'Best-Seller Report', 'Products with the highest sales.'],
];
$type = $_GET['type'] ?? '';
if (!isset($types[$type])) $type = '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

/** Returns [headers, rows] for a report type */
function report_data(string $type, string $from, string $to): array {
    if ($type === 'sales') {
        $sql = "SELECT s.sale_id, s.sale_date, p.product_name, si.quantity, si.unit_price, si.subtotal FROM sales s
                JOIN sale_items si ON si.sale_id=s.sale_id JOIN products p ON p.product_id=si.product_id WHERE 1=1";
        $args = [];
        if ($from) { $sql .= ' AND DATE(s.sale_date) >= ?'; $args[] = $from; }
        if ($to)   { $sql .= ' AND DATE(s.sale_date) <= ?'; $args[] = $to; }
        $st = db()->prepare($sql . ' ORDER BY s.sale_date DESC, s.sale_id DESC'); $st->execute($args);
        $rows = []; $total = 0;
        foreach ($st->fetchAll() as $r) {
            $rows[] = [receipt_no($r['sale_id']), substr($r['sale_date'], 0, 10), $r['product_name'], $r['quantity'], peso($r['unit_price']), peso($r['subtotal'])];
            $total += $r['subtotal'];
        }
        if ($rows) $rows[] = ['', '', '', '', 'TOTAL REVENUE', peso($total)];
        return [['Receipt', 'Date', 'Product', 'Qty', 'Unit Price', 'Subtotal'], $rows];
    }
    $stats = ai_product_stats();
    if ($type === 'low_stock')   $stats = array_filter($stats, fn($p) => $p['status'] === 'Low');
    if ($type === 'high_stock')  $stats = array_filter($stats, fn($p) => $p['status'] === 'High');
    if ($type === 'best_seller') { usort($stats, fn($a, $b) => $b['sold_total'] <=> $a['sold_total']); $stats = array_slice($stats, 0, 10); }
    $rows = [];
    foreach ($stats as $p) {
        $rows[] = $type === 'best_seller'
            ? [$p['product_name'], $p['category_name'], $p['sold_total'], peso($p['sold_total'] * $p['unit_price']), $p['stock_quantity']]
            : [$p['product_name'], $p['category_name'], peso($p['unit_price']), $p['stock_quantity'], $p['status'], $type === 'low_stock' ? $p['suggested'] : $p['sold_total']];
    }
    $head = $type === 'best_seller' ? ['Product', 'Category', 'Units Sold', 'Est. Revenue', 'Stock Left']
          : ['Product', 'Category', 'Price', 'Stock', 'Status', $type === 'low_stock' ? 'Suggested Order' : 'Sold'];
    return [$head, $rows];
}

function log_report(string $type, string $from, string $to, string $fmt): void {
    db()->prepare('INSERT INTO reports (user_id,report_type,date_from,date_to,export_format) VALUES (?,?,?,?,?)')
        ->execute([current_user()['user_id'], $type, $from ?: null, $to ?: null, $fmt]);
}

if ($type && isset($_GET['log']) && $_GET['log'] === 'pdf') { log_report($type, $from, $to, 'pdf'); http_response_code(204); exit; }
if ($type && isset($_GET['csv'])) {
    [$head, $rows] = report_data($type, $from, $to);
    log_report($type, $from, $to, 'csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stockwise_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $head); foreach ($rows as $r) fputcsv($out, $r); fclose($out); exit;
}

$pageTitle = 'Reports';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head no-print">
  <div><h2>Reports</h2><p class="muted">Generate quick summaries for store management.</p></div>
  <?php if ($type): ?><div class="actions">
    <a class="btn" href="?type=<?= e($type) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&csv=1">Download CSV</a>
    <button class="btn btn-primary" id="print-btn" data-type="<?= e($type) ?>" data-from="<?= e($from) ?>" data-to="<?= e($to) ?>">Print / Save as PDF</button>
  </div><?php endif; ?>
</div>
<section class="cards5 no-print">
  <?php foreach ($types as $k => [$tag, $title, $desc]): ?>
    <a class="card report-card <?= $type === $k ? 'selected' : '' ?>" href="?type=<?= $k ?>">
      <span class="tag tag-<?= strtolower($tag) ?>"><?= $tag ?></span><h4><?= $title ?></h4><p class="muted"><?= $desc ?></p></a>
  <?php endforeach; ?>
</section>
<div class="card">
  <h3>Report Preview</h3>
  <?php if (!$type): ?>
    <p>Select a report above to display a summary.</p>
  <?php else: [$head, $rows] = report_data($type, $from, $to); ?>
    <p class="muted"><strong><?= e($types[$type][1]) ?></strong> — <?= e(STORE_NAME) ?> — generated <?= date('M d, Y h:i A') ?> by <?= e($u['first_name'] . ' ' . $u['last_name']) ?></p>
    <?php if ($type === 'sales'): ?>
    <form method="get" class="filters no-print"><input type="hidden" name="type" value="sales">
      <label>From</label><input type="date" name="from" value="<?= e($from) ?>"><label>To</label><input type="date" name="to" value="<?= e($to) ?>">
      <button class="btn btn-sm">Filter</button></form>
    <?php endif; ?>
    <div class="table-wrap"><table class="responsive">
      <thead><tr><?php foreach ($head as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?><tr><?php foreach (array_values($r) as $i => $c): ?><td data-label="<?= e($head[$i] ?? '') ?>"><?= e($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>" class="muted">No data for this report.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
