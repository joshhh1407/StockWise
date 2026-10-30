<?php
require_once __DIR__ . '/auth.php';
require_login();
$u = current_user();
$nav = [
    'index.php'       => ['Dashboard', 'home'],
    'inventory.php'   => ['Inventory', 'package'],
    'sales.php'       => ['Sales', 'cart'],
    'analytics.php'   => ['Analytics', 'chart'],
    'ai_insights.php' => ['AI Insights', 'zap'],
    'reports.php'     => ['Reports', 'file'],
];
$bottom = ['index.php', 'inventory.php', 'sales.php', 'ai_insights.php'];
$active = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = $pageTitle ?? 'Dashboard';
$hideSearch = $hideSearch ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1f7a4d">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> | <?= e(STORE_NAME) ?></title>
<?php include __DIR__ . '/theme_boot.php'; ?>
<?php include __DIR__ . '/fonts.php'; ?>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/icons.php'; ?>
<div class="overlay" id="overlay"></div>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div>
<<<<<<< HEAD
      <a class="brand" href="index.php"><span class="logo"><?= icon('tag') ?></span><span><strong>Stockwise</strong></span></a>
=======
      <a class="brand" href="index.php"><span class="logo"><?= icon('tag') ?></span><span><strong>YouthCo.</strong><small>Shop</small></span></a>
>>>>>>> 8d01c4576897e530dadbc1ece83b3a55659c1a50
      <nav aria-label="Main">
        <?php foreach ($nav as $file => [$label, $ico]): ?>
          <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>"><?= icon($ico) ?><span><?= $label ?></span></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="side-foot">
      <div class="online"><span class="dot"></span> System online</div>
      <a href="logout.php" class="btn btn-logout"><?= icon('logout') ?> Log out</a>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn js-menu" type="button" aria-label="Open menu"><?= icon('menu') ?></button>
      <div class="title-block">
        <div class="crumb"><?= e(STORE_NAME) ?></div>
        <h1><?= e($pageTitle) ?></h1>
      </div>
      <div class="top-right">
        <?php if (!$hideSearch): ?>
        <form action="inventory.php" method="get" class="top-search"><?= icon('search') ?><input type="search" name="q" placeholder="Search products" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products"></form>
        <?php endif; ?>
        <button class="icon-btn theme-toggle" type="button" aria-label="Switch light or dark mode" title="Light / dark mode"><?= icon('moon') ?><?= icon('sun') ?></button>
        <?php include __DIR__ . '/theme_picker.php'; ?>
        <div class="user">
          <div class="avatar"><?= e(strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))) ?></div>
          <div class="user-name"><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><small><?= e($u['role']) ?></small></div>
        </div>
      </div>
    </header>
    <?= flash_show() ?>
