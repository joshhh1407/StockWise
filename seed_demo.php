<?php
/**
 * Run ONCE in the browser: http://localhost/YouthCo/seed_demo.php
 * Creates the demo admin account (admin / admin123) with sample products and 30 days of sales.
 * (Any other account can load the same sample data with the button on its Dashboard / Inventory page.)
 * Delete this file when you're done.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/demo_data.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$role = $pdo->query("SELECT role_id FROM roles WHERE role_name='Client'")->fetchColumn();
if (!$pdo->query("SELECT user_id FROM users WHERE username='admin'")->fetchColumn()) {
    $pdo->prepare('INSERT INTO users (role_id,last_name,first_name,middle_name,username,password_hash) VALUES (?,?,?,?,?,?)')
        ->execute([$role, 'Santos', 'Maria', null, 'admin', password_hash('admin123', PASSWORD_DEFAULT)]);
}
$adminId = (int)$pdo->query("SELECT user_id FROM users WHERE username='admin'")->fetchColumn();
[$p, $r] = load_demo_data($adminId);
if ($p === 0) die("The 'admin' account already has products, nothing to do.\n");
echo "Done!\n- Account: username 'admin' / password 'admin123'\n- $p products and $r sales receipts created for that account.\n\nNow open login.php. You may delete seed_demo.php.\n";
