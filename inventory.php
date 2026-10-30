<?php
$pageTitle = 'Inventory';
$hideSearch = true;   // this page has its own live search
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/ai_engine.php';
require_once __DIR__ . '/includes/demo_data.php';
$pdo = db();
$me = uid();

// ---------- ACTIONS (every query is limited to the logged-in account's own products) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['product_name'] ?? ''); $cat = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['unit_price'] ?? 0);
            $low = max(0, (int)($_POST['low_stock_threshold'] ?? 10)); $high = max(1, (int)($_POST['high_stock_level'] ?? 60));
            if ($name === '' || !$cat || $price < 0) throw new Exception('Please complete the product details.');
            $dup = $pdo->prepare('SELECT 1 FROM products WHERE is_active=1 AND LOWER(product_name)=LOWER(?) AND product_id<>?');
            $dup->execute([$name, (int)($_POST['product_id'] ?? 0)]);
            if ($dup->fetch()) throw new Exception('A product with that name already exists.');
            if ($action === 'add') {
                $qty = max(0, (int)($_POST['stock_quantity'] ?? 0));
                $pdo->prepare('INSERT INTO products (owner_id,category_id,product_name,unit_price,stock_quantity,low_stock_threshold,high_stock_level) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$me, $cat, $name, $price, $qty, $low, $high]);
                if ($qty > 0) $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,movement_type,quantity_change,remarks) VALUES (?,?,'initial',?,'Initial stock')")
                    ->execute([$pdo->lastInsertId(), $me, $qty]);
                flash('Product added.');
            } else {
                $pdo->prepare('UPDATE products SET category_id=?, product_name=?, unit_price=?, low_stock_threshold=?, high_stock_level=? WHERE product_id=?')
                    ->execute([$cat, $name, $price, $low, $high, (int)$_POST['product_id']]);
                flash('Product updated.');
            }
        } elseif ($action === 'stock') {
            $pid = (int)$_POST['product_id']; $type = $_POST['type'] ?? 'in'; $qty = (int)($_POST['qty'] ?? 0);
            if ($qty <= 0) throw new Exception('Quantity must be greater than zero.');
            $pdo->beginTransaction();
            $cur = $pdo->prepare('SELECT stock_quantity FROM products WHERE product_id=? AND is_active=1 FOR UPDATE');
            $cur->execute([$pid]);
            $have = $cur->fetchColumn();
            if ($have === false) { $pdo->rollBack(); throw new Exception('Product not found in the inventory.'); }
            $have = (int)$have;
            $change = $type === 'out' ? -$qty : $qty;
            if ($have + $change < 0) { $pdo->rollBack(); throw new Exception("Not enough stock. Only $have units available."); }
            $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id=?')->execute([$change, $pid]);
            $pdo->prepare('INSERT INTO stock_movements (product_id,user_id,movement_type,quantity_change,remarks) VALUES (?,?,?,?,?)')
                ->execute([$pid, $me, $type === 'out' ? 'adjustment' : 'stock_in', $change, trim($_POST['remarks'] ?? '') ?: ($type === 'out' ? 'Manual stock deduction' : 'Restock')]);
            $pdo->commit();
            flash('Stock updated.');
        } elseif ($action === 'delete') {
            $pdo->prepare('UPDATE products SET is_active=0 WHERE product_id=?')->execute([(int)$_POST['product_id']]);
            flash('Product removed.');
        } elseif ($action === 'demo') {
            [$n, $r] = load_demo_data($me);
            flash($n ? "Loaded $n sample products and $r sales receipts into the inventory." : 'The inventory already has products.', $n ? 'success' : 'error');
        }
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), 'error');
    }
    redirect('inventory.php');
}

// ---------- DATA ----------
$list = ai_product_stats();
$cats = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
$q = trim($_GET['q'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h2>Inventory</h2><p class="muted">Manage products and stock quantities.</p></div>
  <button class="btn btn-primary" data-open="add"><?= icon('plus') ?> Add product</button>
</div>

<?php if (!$list): ?>
<section class="card empty">
  <?= empty_art() ?>
  <h3>Your inventory is empty</h3>
  <p class="muted">Add your first product, or load sample products and sales to try the system.</p>
  <button class="btn btn-primary" data-open="add"><?= icon('plus') ?> Add product</button>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="demo"><button class="btn">Load sample data</button></form>
</section>
<?php else: ?>
<form class="filters" id="inv-filters" autocomplete="off">
  <div class="search-field"><?= icon('search') ?><input type="search" id="f-q" placeholder="Search product or category" value="<?= e($q) ?>" aria-label="Search product"></div>
  <select id="f-cat" aria-label="Category">
    <option value="0">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?>
  </select>
  <select id="f-status" aria-label="Stock status">
    <option value="">Any stock status</option>
    <?php foreach (['Low', 'Sufficient', 'High'] as $s): ?><option><?= $s ?></option><?php endforeach; ?>
  </select>
</form>
<p class="result-count" id="inv-count"></p>

<div class="card"><div class="table-wrap"><table class="responsive">
  <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Sold</th><th>Status</th><th></th></tr></thead>
  <tbody id="inv-body">
  <?php foreach ($list as $p): $json = e(json_encode(['id' => $p['product_id'], 'name' => $p['product_name'], 'cat' => $p['category_id'], 'price' => $p['unit_price'], 'low' => $p['low_stock_threshold'], 'high' => $p['high_stock_level'], 'stock' => $p['stock_quantity']])); ?>
    <tr data-search="<?= e($p['product_name'] . ' ' . $p['category_name']) ?>" data-cat="<?= (int)$p['category_id'] ?>" data-status="<?= e($p['status']) ?>">
      <td data-label="Product"><div class="pcell"><?= pavatar($p['product_name'], $p['category_id']) ?><div><strong><?= e($p['product_name']) ?></strong><small class="sub-line"><?= e($p['category_name']) ?></small></div></div></td>
      <td data-label="Price"><?= peso($p['unit_price']) ?></td>
      <td data-label="Stock"><div class="stockcell"><b><?= (int)$p['stock_quantity'] ?></b><span class="meter m-<?= e($p['status']) ?>"><i style="--p:<?= min(100, (int)round($p['stock_quantity'] / max(1, $p['high_stock_level']) * 100)) ?>%"></i></span></div></td>
      <td data-label="Sold" class="red"><?= (int)$p['sold_total'] ?></td>
      <td data-label="Status"><?= status_badge($p['status']) ?></td>
      <td class="actions">
        <button class="btn btn-sm" data-open="edit" data-p="<?= $json ?>">Edit</button>
        <button class="btn btn-sm" data-open="stock" data-p="<?= $json ?>">Stock</button>
        <form method="post" class="inline" onsubmit="return confirm('Remove this product?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <tr id="inv-none" hidden><td colspan="6" class="muted">No products match your search.</td></tr>
  </tbody>
</table></div></div>
<?php endif; ?>

<!-- Add / Edit dialog -->
<dialog id="dlg-product">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="pf-action" value="add"><input type="hidden" name="product_id" id="pf-id">
    <h3 id="pf-title">Add product</h3>
    <label>Product name</label><input type="text" name="product_name" id="pf-name" required>
    <label>Category</label>
    <select name="category_id" id="pf-cat" required><?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
    <div class="grid3">
      <div><label>Price (₱)</label><input type="number" step="0.01" min="0" name="unit_price" id="pf-price" required></div>
      <div><label>Low-stock level</label><input type="number" min="0" name="low_stock_threshold" id="pf-low" value="10"></div>
      <div><label>High-stock level</label><input type="number" min="1" name="high_stock_level" id="pf-high" value="60"></div>
    </div>
    <div id="pf-stock-wrap"><label>Starting stock</label><input type="number" min="0" name="stock_quantity" id="pf-stock" value="0"></div>
    <div class="dlg-actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</dialog>

<!-- Stock dialog -->
<dialog id="dlg-stock">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="stock"><input type="hidden" name="product_id" id="sf-id">
    <h3>Update Stock — <span id="sf-name"></span></h3>
    <p class="muted">Current stock: <strong id="sf-cur"></strong></p>
    <label>Action</label>
    <select name="type"><option value="in">Add stock (+)</option><option value="out">Remove stock (−)</option></select>
    <label>Quantity</label><input type="number" min="1" name="qty" id="sf-qty" required>
    <div class="qty-chips"><button type="button" data-q="5">+5</button><button type="button" data-q="10">+10</button><button type="button" data-q="20">+20</button><button type="button" data-q="50">+50</button></div>
    <label>Remarks (optional)</label><input type="text" name="remarks" maxlength="255">
    <div class="dlg-actions"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary">Update stock</button></div>
  </form>
</dialog>
<?php if (isset($_GET['add'])): ?><script>window.addEventListener('DOMContentLoaded',()=>{const b=document.querySelector('[data-open="add"]');if(b)b.click();});</script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
