<?php
$pageTitle = 'AI Insights';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ai_engine.php';
$stats = ai_product_stats();
$recs = ai_recommendations($stats);
ai_save_recommendations($recs);   // keep this account's ai_recommendations rows up to date

$needRestock = count(array_filter($stats, fn($p) => $p['status'] === 'Low'));
$runningOut  = count(array_filter($stats, fn($p) => $p['days_left'] !== null && $p['days_left'] <= AI_URGENT_DAYS));
$suggested   = array_sum(array_column($stats, 'suggested'));
$forecast = $stats; usort($forecast, fn($a, $b) => ($a['days_left'] ?? 9999) <=> ($b['days_left'] ?? 9999));
$engineLabel = AI_PROVIDER === 'builtin' ? 'Built-in AI' : (implode(' → ', AI_CHAIN) . ' → Built-in');
?>
<div class="page-head"><div><h2>AI insights</h2><p class="muted">Decision-support recommendations based on stock and sales patterns.</p></div>
  <span class="badge badge-warn">AI: <?= e($engineLabel) ?></span></div>
<?php if (!$stats): ?><div class="notice">You have no products yet, so the AI has nothing to analyze. Add products in <a class="link" href="inventory.php">Inventory</a> or load sample data from the Dashboard.</div>
<?php else: ?><div class="notice">The AI analyzes your own stock and sales. Results update every time you record a sale or add stock.</div><?php endif; ?>
<section class="ledger" style="--cols:3">
  <?= fig('Needs restocking', $needRestock, 'At or below minimum', $needRestock ? 'bad' : '') ?>
  <?= fig('Running out in ' . AI_URGENT_DAYS . ' days', $runningOut, 'Based on average daily sales', $runningOut ? 'warn' : '') ?>
  <?= fig('Suggested to order', number_format($suggested), 'Units across all products') ?>
</section>

<div class="card">
  <h3>Ask the AI</h3><p class="muted">Ask about stock, sales, or a product, in English, Filipino, or Taglish.</p>
  <div id="chat" class="chat"><div class="msg bot">Hi! Ask me what to restock, what is overstocked, or how a specific product is doing.</div></div>
  <div class="chips">
    <button class="chip" type="button">What should I restock?</button><button class="chip" type="button">Overstocked products</button>
    <button class="chip" type="button">Top sellers</button><button class="chip" type="button">Today's sales</button>
  </div>
  <form id="chat-form" class="chat-form"><input type="text" id="chat-input" placeholder="e.g. How many Coca-Cola are left?" autocomplete="off"><button class="btn btn-primary" aria-label="Send question"><?= icon('send') ?> Ask</button></form>
</div>

<div class="card">
  <h3>Restock forecast</h3><p class="muted">Estimated days of stock left and suggested order quantity (covers <?= AI_COVER_DAYS ?> days of sales).</p>
  <div class="table-wrap"><table class="responsive">
    <thead><tr><th>Product</th><th>Stock</th><th>Avg. sold / day</th><th>Days left</th><th>Suggested order</th></tr></thead>
    <tbody>
    <?php foreach ($forecast as $p): $d = $p['days_left']; $cls = $d === null ? 'good' : ($d <= AI_URGENT_DAYS ? 'bad' : 'good'); ?>
      <tr><td data-label="Product"><div class="pcell"><?= pavatar($p['product_name'], $p['category_id']) ?><strong><?= e($p['product_name']) ?></strong></div></td><td data-label="Stock"><?= (int)$p['stock_quantity'] ?></td><td data-label="Avg. sold / day"><?= $p['avg_daily'] ?></td>
        <td data-label="Days left"><span class="badge badge-<?= $cls ?>"><?= $d === null ? '—' : $d . ' days' ?></span></td>
        <td data-label="Suggested order"><?= $p['suggested'] > 0 ? $p['suggested'] . ' units' : '—' ?></td></tr>
    <?php endforeach; if (!$forecast): ?><tr><td colspan="5" class="muted">No data yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<h2 class="section-title">Recommendations</h2><p class="muted" style="margin-bottom:14px">Most urgent first.</p>
<section class="grid2">
  <?php foreach ($recs as $r): ?>
    <div class="card rec rec-<?= e($r['type']) ?>"><span class="badge badge-warn"><?= e($r['label']) ?></span><h4><?= e($r['title']) ?></h4><p class="muted"><?= e($r['msg']) ?></p></div>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
