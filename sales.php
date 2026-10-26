<?php
$pageTitle = 'Sales / Stock-Out';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
$me = uid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $items = [];
        foreach (($_POST['product_id'] ?? []) as $i => $pid) {
            $pid = (int)$pid; $qty = (int)($_POST['qty'][$i] ?? 0);
            if ($pid && $qty > 0) $items[$pid] = ($items[$pid] ?? 0) + $qty;
        }
        if (!$items) throw new Exception('Add at least one product with a quantity.');

        $pdo->beginTransaction();
        $total = 0; $lines = [];
        $sel = $pdo->prepare('SELECT product_id, product_name, unit_price, stock_quantity FROM products WHERE product_id=? AND is_active=1 FOR UPDATE');
        foreach ($items as $pid => $qty) {
            $sel->execute([$pid]); $p = $sel->fetch();
            if (!$p) throw new Exception('A selected product is not in your inventory.');
            if ($p['stock_quantity'] < $qty) throw new Exception("Not enough stock for {$p['product_name']} (only {$p['stock_quantity']} left).");
            $sub = $qty * $p['unit_price']; $total += $sub; $lines[] = [$p, $qty, $sub];
        }
        $pdo->prepare('INSERT INTO sales (user_id,total_amount) VALUES (?,?)')->execute([$me, $total]);
        $sid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)');
        $mv  = $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,sale_id,movement_type,quantity_change,remarks) VALUES (?,?,?,'stock_out',?,?)");
        $upd = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id=?');
        foreach ($lines as [$p, $qty, $sub]) {
            $ins->execute([$sid, $p['product_id'], $qty, $p['unit_price'], $sub]);
            $mv->execute([$p['product_id'], $me, $sid, -$qty, 'Sale ' . receipt_no($sid)]);
            $upd->execute([$qty, $p['product_id']]);
        }
        $pdo->commit();
        flash('Sale recorded: ' . receipt_no($sid) . ' (' . peso($total) . '). Stock updated automatically.');
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), 'error');
    }
    redirect('sales.php');
}

$sm = $pdo->prepare('SELECT COUNT(DISTINCT si.sale_id) n, COALESCE(SUM(si.subtotal),0) rev, COALESCE(SUM(si.quantity),0) units
                     FROM sale_items si JOIN products p ON p.product_id = si.product_id');
$sm->execute(); $sum = $sm->fetch();

$idq = $pdo->prepare('SELECT s.sale_id FROM sales s WHERE EXISTS (SELECT 1 FROM sale_items si JOIN products p ON p.product_id = si.product_id
                      WHERE si.sale_id = s.sale_id) ORDER BY s.sale_date DESC, s.sale_id DESC LIMIT 25');
$idq->execute();
$ids = $idq->fetchAll(PDO::FETCH_COLUMN);
$grouped = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $rq = $pdo->prepare("SELECT s.sale_id, s.sale_date, p.product_name, si.quantity, si.unit_price, si.subtotal
                         FROM sales s JOIN sale_items si ON si.sale_id = s.sale_id JOIN products p ON p.product_id = si.product_id
                         WHERE s.sale_id IN ($in) ORDER BY s.sale_date DESC, s.sale_id DESC, si.sale_item_id");
    $rq->execute();
    foreach ($rq->fetchAll() as $r) $grouped[$r['sale_id']][] = $r;
}
$pq = $pdo->prepare('SELECT product_id, product_name, unit_price, stock_quantity FROM products WHERE is_active=1 ORDER BY product_name');
$pq->execute(); $products = $pq->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h2>Sales and stock-out</h2><p class="muted">Record what you sold. Stock updates automatically.</p></div>
  <button class="btn btn-primary" data-open="sale" <?= $products ? '' : 'disabled' ?>><?= icon('plus') ?> Record sale</button>
</div>
<?php if (!$products): ?><div class="notice">You have no products yet. Add products in <a class="link" href="inventory.php">Inventory</a> first, then record sales here.</div><?php endif; ?>
<section class="ledger" style="--cols:3">
  <?= fig('Recorded revenue', peso($sum['rev']), 'All transactions', 'good') ?>
  <?= fig('Receipts', (int)$sum['n'], 'Transactions recorded') ?>
  <?= fig('Units sold', number_format((int)$sum['units']), 'Total quantity') ?>
</section>

<?php if (!$grouped): ?>
  <section class="card empty"><?= empty_art() ?><h3>No receipts yet</h3><p class="muted">Your first sale will show up here as a receipt.</p></section>
<?php else: ?>
<section class="receipts">
  <?php foreach ($grouped as $sid => $items): $rt = 0; ?>
    <article class="receipt">
      <header><strong><?= receipt_no($sid) ?></strong><span><?= e(substr($items[0]['sale_date'], 0, 16)) ?></span></header>
      <?php foreach ($items as $r): $rt += $r['subtotal']; ?>
        <div class="r-item"><span><?= e($r['product_name']) ?></span><div class="r-sub"><span><?= (int)$r['quantity'] ?> × <?= peso($r['unit_price']) ?></span><b><?= peso($r['subtotal']) ?></b></div></div>
      <?php endforeach; ?>
      <div class="r-total"><span>Total</span><span><?= peso($rt) ?></span></div>
    </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<dialog id="dlg-sale">
  <form method="post">
    <?= csrf_field() ?>
    <h3>Record sale</h3>
    <div id="sale-rows"></div>
    <button type="button" class="btn btn-sm" id="add-row">+ Add another product</button>
    <p class="sale-total">Total: <strong id="sale-total">₱0.00</strong></p>
    <div class="dlg-actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary">Save sale</button></div>
  </form>
</dialog>
<script>window.PRODUCTS = <?= json_encode($products) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
