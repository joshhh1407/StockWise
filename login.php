<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $st = db()->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.username = ?');
    $st->execute([$username]);
    $user = $st->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['user_id' => $user['user_id'], 'first_name' => $user['first_name'],
                             'last_name' => $user['last_name'], 'role' => $user['role_name']];
        redirect('index.php');
    }
    $error = 'Invalid username or password. Please try again.';
}
$mode = 'login';
require __DIR__ . '/includes/auth_layout_top.php';
?>
<h2>Welcome back</h2>
<p class="sub">Log in to manage your store.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?= flash_show() ?>
<form method="post" autocomplete="off">
  <?= csrf_field() ?>
  <label>Username</label>
  <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
  <label>Password</label>
  <input type="password" name="password" required>
  <button class="btn btn-primary btn-block" type="submit">Log in</button>
</form>
<p class="alt">Don't have an account? <a href="register.php">Register here</a></p>
<div class="disclaimer" role="note"><strong>Disclaimer:</strong> This website is created for demonstration and academic purposes only as part of an SIA subject project. It is not a real company, official service, registered business, or commercial website. No claims are made regarding actual business operations, legal standing, or real-world services.</div>
<?php require __DIR__ . '/includes/auth_layout_bottom.php'; ?>
