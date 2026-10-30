<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/ai_engine.php';
require_once __DIR__ . '/includes/demo_data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'demo') {
    csrf_check();
    [$n, $r] = load_demo_data(uid());
    flash($n ? "Loaded $n sample products and $r sales receipts into the inventory." : 'The inventory already has products.', $n ? 'success' : 'error');
    redirect('index.php');
}
$u = current_user();
require __DIR__ . '/includes/header.php';

$stats = ai_product_stats();
$recs  = array_values(array_filter(ai_recommendations($stats), fn($r) => $r['type'] === 'restock'));
$totalStock = array_sum(array_column($stats, 'stock_quantity'));
$lowCount   = count(array_filter($stats, fn($p) => $p['status'] === 'Low'));
$todaySales = array_sum(array_column($stats, 'revenue_today'));
$attention  = array_values(array_filter($stats, fn($p) => $p['status'] !== 'Sufficient'));
usort($attention, fn($a, $b) => ($a['status'] === 'Low' ? 0 : 1) <=> ($b['status'] === 'Low' ? 0 : 1));

$st = db()->prepare("SELECT s.sale_id, s.sale_date, p.product_name, si.quantity, si.subtotal
                     FROM sale_items si JOIN sales s ON s.sale_id = si.sale_id JOIN products p ON p.product_id = si.product_id
                     ORDER BY s.sale_date DESC, si.sale_item_id DESC LIMIT 6");
$st->execute();
$recent = $st->fetchAll();

// revenue per day, last 7 days
$wk = db()->prepare("SELECT DATE(s.sale_date) d, SUM(si.subtotal) t FROM sale_items si
                     JOIN sales s ON s.sale_id = si.sale_id JOIN products p ON p.product_id = si.product_id
                     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(s.sale_date)");
$wk->execute();
$byDay = array_column($wk->fetchAll(), 't', 'd');
$days = []; $maxDay = 0; $weekTotal = 0;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day")); $t = (float)($byDay[$d] ?? 0);
    $days[] = ['d' => $d, 't' => $t, 'label' => date('D', strtotime($d))];
    $maxDay = max($maxDay, $t); $weekTotal += $t;
}
// one-line "daily brief" from the same numbers the AI uses
$urgent = array_values(array_filter($stats, fn($p) => $p['urgent']));
usort($urgent, fn($a, $b) => ($a['days_left'] ?? 99) <=> ($b['days_left'] ?? 99));
$best = $stats; usort($best, fn($a, $b) => $b['sold_window'] <=> $a['sold_window']);
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
<section class="greet">
  <div>
    <h2><?= $greet ?>, <?= e($u['first_name']) ?>.</h2>
    <p class="muted"><?= date('l, F j') ?> &middot; <?= $lowCount ? $lowCount . ' product' . ($lowCount > 1 ? 's' : '') . ' running low.' : 'Your shelves look good today.' ?></p>
  </div>
  <a href="inventory.php?add=1" class="btn btn-primary"><?= icon('plus') ?> Add product</a>
</section>

<?php if ($stats): ?>
<section class="brief">
  <span class="brief-ico"><?= icon('zap') ?></span>
  <div class="brief-text">
    <strong><?= $urgent ? count($urgent) . ' product' . (count($urgent) > 1 ? 's' : '') . ' to restock soon' : 'Stock looks healthy' ?></strong>
    <span><?php if ($urgent): ?><?= e($urgent[0]['product_name']) ?> will run out in about <?= $urgent[0]['days_left'] !== null ? (int)$urgent[0]['days_left'] . ' days' : 'no time' ?>.<?php else: ?>Nothing is close to running out.<?php endif; ?>
    <?php if ($best && $best[0]['sold_window'] > 0): ?> Best seller this month: <?= e($best[0]['product_name']) ?>.<?php endif; ?></span>
  </div>
  <a class="btn btn-sm btn-outline" href="ai_insights.php">See the plan</a>
</section>
<?php endif; ?>

<?php if (!$stats): ?>
<section class="card empty">
  <?= empty_art() ?>
  <h3>Your inventory is empty</h3>
  <p class="muted">Each account has its own inventory. Add your first product, or load sample products and sales to try the AI right away.</p>
  <a href="inventory.php?add=1" class="btn btn-primary"><?= icon('plus') ?> Add product</a>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="demo"><button class="btn">Load sample data</button></form>
</section>
<?php endif; ?>

<section class="ledger" style="--cols:4">
  <?= fig('Products', count($stats), 'In your store') ?>
  <?= fig('Units in stock', number_format($totalStock), 'Across all products') ?>
  <?= fig("Today's sales", peso($todaySales), 'Recorded revenue', 'good') ?>
  <?= fig('Running low', $lowCount, 'Need restocking', $lowCount ? 'bad' : '') ?>
</section>

<section class="split">
  <div class="card">
    <div class="card-head"><div><h3>Sales, last 7 days</h3><p class="muted">Today is highlighted</p></div><span class="badge badge-good"><?= peso($weekTotal) ?></span></div>
    <div class="spark" role="img" aria-label="Daily sales for the last 7 days">
      <?php foreach ($days as $k => $d): $h = $maxDay > 0 ? max(4, (int)round($d['t'] / $maxDay * 100)) : 4; ?>
        <div class="spark-col<?= $d['d'] === date('Y-m-d') ? ' today' : '' ?>" title="<?= e(peso($d['t'])) ?>">
          <span class="spark-val"><?= $d['t'] > 0 ? '₱' . number_format($d['t'], 0) : '' ?></span>
          <div class="spark-track"><i style="--h:<?= $h ?>%;--n:<?= $k ?>"></i></div>
          <small><?= e($d['label']) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><div><h3>Needs attention</h3><p class="muted">Low or overstocked</p></div><a class="link" href="analytics.php">Analytics</a></div>
    <?php if (!$attention): ?><p class="muted">All products are sufficiently stocked.</p><?php endif; ?>
    <?php foreach (array_slice($attention, 0, 6) as $p): ?>
      <div class="row-box"><span class="pcell"><?= pavatar($p['product_name'], $p['category_id']) ?><span><strong><?= e($p['product_name']) ?></strong><small class="sub-line"><?= (int)$p['stock_quantity'] ?> units left</small></span></span><?= status_badge($p['status']) ?></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="split split-rev">
  <div class="card">
    <div class="card-head"><div><h3>Recent sales</h3><p class="muted">Latest stock-out transactions</p></div><a class="link" href="sales.php">All receipts</a></div>
    <div class="table-wrap"><table class="responsive">
      <thead><tr><th>Receipt</th><th>Product</th><th>Qty</th><th>Total</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr><td data-label="Receipt" class="mono"><?= receipt_no($r['sale_id']) ?></td><td data-label="Product"><?= e($r['product_name']) ?></td><td data-label="Qty"><?= (int)$r['quantity'] ?></td><td data-label="Total"><?= peso($r['subtotal']) ?></td><td data-label="Date"><?= e(substr($r['sale_date'], 0, 10)) ?></td></tr>
      <?php endforeach; if (!$recent): ?><tr><td colspan="5" class="muted">No sales recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card ai-panel">
    <div class="card-head"><div><h3>AI suggestions</h3><p class="muted">From your current stock and sales</p></div><span class="badge badge-info">AI</span></div>
    <?php if (!$recs): ?><p class="muted">Nothing urgent to restock right now.</p><?php endif; ?>
    <?php foreach (array_slice($recs, 0, 3) as $r): ?>
      <div class="row-box col"><strong><?= e($r['title']) ?></strong><span><?= e($r['msg']) ?></span></div>
    <?php endforeach; ?>
    <a href="ai_insights.php" class="btn btn-outline btn-block">Open AI insights</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
