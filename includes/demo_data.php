<?php
require_once __DIR__ . '/db.php';

/** Sample store: name, category, price, stock, low level, high level, units sold in the last 30 days */
function demo_products(): array {
    return [
     ['Coca-Cola 1.5L','Beverages',78,12,10,60,48], ['Sprite 1.5L','Beverages',78,25,10,60,26],
     ["Nature's Spring 500ml",'Beverages',15,45,15,80,21], ['Lucky Me Pancit Canton','Instant Noodles',15,8,10,60,52],
     ['Lucky Me Chicken Noodles','Instant Noodles',15,32,10,60,19], ['SkyFlakes','Snacks',9,68,10,60,7],
     ['Piattos Cheese','Snacks',22,18,10,60,34], ['Argentina Corned Beef','Canned Goods',38,14,10,60,25],
     ['Mega Sardines','Canned Goods',24,9,10,60,30], ['Nescafé Stick','Coffee',8,40,15,80,44],
     ['Bear Brand Milk','Milk',15,10,10,60,37], ['Pampers Diaper Small','Baby Products',12,15,10,60,16],
     ['Baby Wipes','Baby Products',55,6,15,60,54], ['Safeguard Soap','Personal Care',35,22,10,60,20],
     ['Colgate Toothpaste','Personal Care',45,30,10,60,14],
    ];
}

/**
 * Loads sample products + 30 days of sales history into ONE account (only if that account has no products yet).
 * Returns [products created, receipts created].
 */
function load_demo_data(int $uid): array {
    $pdo = db();
    $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE is_active = 1');
    $chk->execute();
    if ((int)$chk->fetchColumn() > 0) return [0, 0];

    mt_srand(2026 + $uid);
    $pdo->beginTransaction();
    $catQ = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
    $insC = $pdo->prepare('INSERT IGNORE INTO categories (category_name) VALUES (?)');
    $insP = $pdo->prepare('INSERT INTO products (owner_id,category_id,product_name,unit_price,stock_quantity,low_stock_threshold,high_stock_level) VALUES (?,?,?,?,?,?,?)');
    $insM = $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,movement_type,quantity_change,remarks,movement_date) VALUES (?,?,'initial',?,'Initial stock', DATE_SUB(NOW(), INTERVAL 31 DAY))");
    $lines = []; $data = demo_products();
    foreach ($data as [$name, $cat, $price, $stock, $low, $high, $sold]) {
        $catQ->execute([$cat]); $cid = $catQ->fetchColumn();
        if (!$cid) { $insC->execute([$cat]); $catQ->execute([$cat]); $cid = $catQ->fetchColumn(); }
        $insP->execute([$uid, $cid, $name, $price, $stock, $low, $high]);
        $pid = (int)$pdo->lastInsertId();
        $insM->execute([$pid, $uid, $stock + $sold]);
        for ($left = $sold; $left > 0;) {
            $q = min($left, mt_rand(1, 4)); $left -= $q;
            $lines[] = [$pid, $q, (float)$price, mt_rand(0, 29)];
        }
    }
    shuffle($lines);

    $insS = $pdo->prepare('INSERT INTO sales (user_id,sale_date,total_amount) VALUES (?, DATE_SUB(NOW(), INTERVAL ? MINUTE), ?)');
    $insI = $pdo->prepare('INSERT INTO sale_items (sale_id,product_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)');
    $insO = $pdo->prepare("INSERT INTO stock_movements (product_id,user_id,sale_id,movement_type,quantity_change,remarks,movement_date) VALUES (?,?,?,'stock_out',?,'Sale', DATE_SUB(NOW(), INTERVAL ? MINUTE))");
    $receipts = 0;
    while ($lines) {
        $group = array_splice($lines, 0, mt_rand(1, 3));
        $minutes = $group[0][3] * 1440 + mt_rand(0, 600);
        $total = array_sum(array_map(fn($l) => $l[1] * $l[2], $group));
        $insS->execute([$uid, $minutes, $total]);
        $sid = (int)$pdo->lastInsertId(); $receipts++;
        foreach ($group as [$pid, $q, $price]) {
            $insI->execute([$sid, $pid, $q, $price, $q * $price]);
            $insO->execute([$pid, $uid, $sid, -$q, $minutes]);
        }
    }
    $pdo->commit();
    return [count($data), $receipts];
}
