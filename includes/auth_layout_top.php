<?php /* shared layout for login.php and register.php */ ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1f7a4d">
<title><?= $mode === 'login' ? 'Log in' : 'Create account' ?> | <?= e(STORE_NAME) ?></title>
<?php include __DIR__ . '/fonts.php'; ?>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
<?php include __DIR__ . '/icons.php'; ?>
<div class="auth-card">
  <div class="auth-left">
    <div class="wordmark"><span class="logo"><?= icon('tag') ?></span><span>Stockwise</span></div>
    <h1>Know what's on your shelves.</h1>
    <p>Track stock, record every sale, and let the AI tell you what to restock next.</p>
    <article class="receipt receipt-mock" aria-hidden="true">
      <header><strong>RCT-1042</strong><span>Today</span></header>
      <div class="r-item"><span>Baby Wipes</span><div class="r-sub"><span>3 × ₱55.00</span><b>₱165.00</b></div></div>
      <div class="r-item"><span>Nescafé Stick</span><div class="r-sub"><span>4 × ₱8.00</span><b>₱32.00</b></div></div>
      <div class="r-item"><span>Lucky Me Pancit Canton</span><div class="r-sub"><span>5 × ₱15.00</span><b>₱75.00</b></div></div>
      <div class="r-total"><span>Total</span><span>₱272.00</span></div>
    </article>
  </div>
  <div class="auth-right">
