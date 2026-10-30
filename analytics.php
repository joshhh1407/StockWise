<?php
$pageTitle = 'Analytics';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ai_engine.php';
$stats = ai_product_stats();
$cnt = fn($f) => count(array_filter($stats, $f));
$maxStock = max(1, max(array_column($stats, 'stock_quantity') ?: [1]));
$lowN = $cnt(fn($p) => $p['status'] === 'Low'); $highN = $cnt(fn($p) => $p['status'] === 'High'); $okN = count($stats) - $lowN - $highN;
$segs = [['Low', $lowN, 'var(--bad)'], ['Sufficient', $okN, 'var(--good)'], ['High', $highN, 'var(--warn)']];
$totN = max(1, count($stats));
$byCat = [];
foreach ($stats as $p) { $k = $p['category_id']; $byCat[$k] = $byCat[$k] ?? ['name' => $p['category_name'], 'units' => 0]; $byCat[$k]['units'] += (int)$p['stock_quantity']; }
uasort($byCat, fn($a, $b) => $b['units'] <=> $a['units']);
$totUnits = max(1, array_sum(array_column($byCat, 'units')));
$top = $stats; usort($top, fn($a, $b) => $b['sold_total'] <=> $a['sold_total']); $top = array_slice($top, 0, 5);
?>
<div class="page-head"><div><h2>Inventory analytics</h2><p class="muted">How your stock is moving and where it needs help.</p></div></div>
<?php if (!$stats): ?><div class="notice">No products yet. Add products in <a class="link" href="inventory.php">Inventory</a> (or load sample data on the Dashboard) to see analytics.</div><?php endif; ?>
<section class="ledger" style="--cols:4">
  <?= fig('Low stock', $cnt(fn($p) => $p['status'] === 'Low'), 'Need restocking', 'bad') ?>
  <?= fig('High stock', $cnt(fn($p) => $p['status'] === 'High'), 'Large stock on hand', 'warn') ?>
  <?= fig('Fast-moving', $cnt(fn($p) => $p['is_fast']), 'Sell often', 'good') ?>
  <?= fig('Slow-moving', $cnt(fn($p) => $p['is_slow']), 'Rarely sold', 'info') ?>
</section>
<section class="grid2">
  <div class="card">
    <h3>Stock health</h3><p class="muted">How your products are split by stock level</p>
    <div class="donut-row">
      <div class="donut-wrap">
        <svg class="donut" viewBox="0 0 42 42" role="img" aria-label="Stock health">
          <circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--line)" stroke-width="5"/>
          <?php $cum = 0; foreach ($segs as $k => [$lab, $n, $col]): $pc = $n / $totN * 100; if ($n > 0): ?>
            <circle class="donut-seg" cx="21" cy="21" r="15.9155" fill="none" stroke="<?= $col ?>" stroke-width="5" stroke-dasharray="<?= round($pc, 2) ?> <?= round(100 - $pc, 2) ?>" stroke-dashoffset="-<?= round($cum, 2) ?>" transform="rotate(-90 21 21)" style="--n:<?= $k ?>"/>
          <?php endif; $cum += $pc; endforeach; ?>
        </svg>
        <div class="donut-center"><strong><?= count($stats) ?></strong><span>products</span></div>
      </div>
      <ul class="legend">
        <?php foreach ($segs as [$lab, $n, $col]): ?><li><i style="background:<?= $col ?>"></i><span><?= $lab ?></span><b><?= $n ?></b></li><?php endforeach; ?>
      </ul>
    </div>
    <h3 style="margin-top:26px">Units by category</h3>
    <div class="stack" role="img" aria-label="Units by category">
      <?php foreach ($byCat as $cid => $c): ?><span style="width:<?= round($c['units'] / $totUnits * 100, 2) ?>%;background:hsl(<?= cat_hue($cid) ?> 58% 52%)" title="<?= e($c['name']) ?>: <?= (int)$c['units'] ?>"></span><?php endforeach; ?>
    </div>
    <ul class="legend legend-cols">
      <?php foreach ($byCat as $cid => $c): ?><li><i style="background:hsl(<?= cat_hue($cid) ?> 58% 52%)"></i><span><?= e($c['name']) ?></span><b><?= (int)$c['units'] ?></b></li><?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <h3>Stock on hand</h3>
    <?php foreach ($stats as $p): ?>
      <div class="bar-row"><span class="bar-name"><?= e($p['product_name']) ?></span>
        <div class="bar"><div style="width: <?= round($p['stock_quantity'] / $maxStock * 100) ?>%"></div></div><b><?= (int)$p['stock_quantity'] ?></b></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h3>Best sellers</h3>
    <?php foreach ($top as $i => $p): ?>
      <div class="rank-row"><span><?= $i + 1 ?>. <?= e($p['product_name']) ?></span><b><?= (int)$p['sold_total'] ?> sold</b></div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
