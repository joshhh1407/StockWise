<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');
$error = '';
$roles = db()->query('SELECT * FROM roles ORDER BY role_id')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $last = trim($_POST['last_name'] ?? ''); $first = trim($_POST['first_name'] ?? ''); $mid = trim($_POST['middle_name'] ?? '');
    $username = trim($_POST['username'] ?? ''); $pass = $_POST['password'] ?? ''; $role = (int)($_POST['role_id'] ?? 0);
    if ($last === '' || $first === '' || $username === '' || $pass === '' || !$role) $error = 'Please fill in all required fields.';
    elseif (strlen($pass) < 5) $error = 'Password must be at least 5 characters.';
    elseif ($pass !== ($_POST['confirm'] ?? '')) $error = 'Passwords do not match.';
    elseif (empty($_POST['agree'])) $error = 'You must agree to the Terms and Agreement to create an account.';
    else {
        $chk = db()->prepare('SELECT 1 FROM users WHERE username = ?'); $chk->execute([$username]);
        if ($chk->fetch()) $error = 'That username is already taken.';
        else {
            db()->prepare('INSERT INTO users (role_id,last_name,first_name,middle_name,username,password_hash) VALUES (?,?,?,?,?,?)')
                ->execute([$role, $last, $first, $mid ?: null, $username, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('Account created! You can now log in.');
            redirect('login.php');
        }
    }
}
$mode = 'register';
require __DIR__ . '/includes/auth_layout_top.php';
?>
<h2>Create your account</h2>
<p class="sub">Each account gets its own inventory.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="off">
  <?= csrf_field() ?>
  <div class="fgrid fg3">
    <div><label>Last name</label><input type="text" name="last_name" required value="<?= e($_POST['last_name'] ?? '') ?>"></div>
    <div><label>First name</label><input type="text" name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>"></div>
    <div class="fg-mid"><label>Middle name</label><input type="text" name="middle_name" placeholder="Optional" value="<?= e($_POST['middle_name'] ?? '') ?>"></div>
  </div>
  <div class="fgrid">
    <div><label>Username</label><input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>"></div>
    <div><label>Role</label>
      <select name="role_id" required>
        <?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>"><?= e($r['role_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="fgrid">
    <div><label>Password</label><input type="password" name="password" required></div>
    <div><label>Confirm password</label><input type="password" name="confirm" required></div>
  </div>
  <label class="agree"><input type="checkbox" name="agree" id="agree" value="1" required <?= !empty($_POST['agree']) ? 'checked' : '' ?>>
    <span>I have read and agree to the <a href="#" data-terms>Terms and Agreement</a>.</span></label>
  <button class="btn btn-primary btn-block" type="submit">Create account</button>
</form>
<dialog id="dlg-terms">
  <h3>Terms and Agreement</h3>
  <div class="terms-body">
    <p><b>1. Acceptance.</b> By creating an account for <?= e(STORE_NAME) ?> you agree to these terms. If you do not agree, do not register.</p>
    <p><b>2. Your account.</b> You are responsible for keeping your username and password private and for everything done under your account. Provide accurate information when registering.</p>
    <p><b>3. Shared inventory.</b> Products, stock levels, sales and reports are shared by all accounts in this system. Changes you make (adding, editing or deleting products, stock updates, recording sales) are saved and visible to other accounts.</p>
    <p><b>4. Proper use.</b> Use the system only for managing the store's inventory and sales. Do not enter false records, try to access data you are not allowed to, or interfere with the system.</p>
    <p><b>5. Data we keep.</b> We store your name, username, a hashed (not readable) password, and the inventory, sales and report records you create. Your data is used only to run this system and is not sold to third parties.</p>
    <p><b>6. AI suggestions.</b> AI insights and restock suggestions are estimates based on your recorded sales. Review them before making business decisions. Questions you send to the AI chat may be processed by an external AI service.</p>
    <p><b>7. Accuracy and availability.</b> The system is provided "as is". We do not guarantee it will be error-free or always available, so keep your own backups of important records.</p>
    <p><b>8. Suspension.</b> Accounts that break these terms may be suspended or removed.</p>
    <p><b>9. Changes.</b> These terms may be updated. Continued use of the system means you accept the updated terms.</p>
  </div>
  <div class="dlg-actions">
    <button type="button" class="btn" data-close>Close</button>
    <button type="button" class="btn btn-primary" id="terms-accept">I agree</button>
  </div>
</dialog>
<script>
(function(){
  const dlg=document.getElementById('dlg-terms'), box=document.getElementById('agree');
  document.querySelector('[data-terms]').addEventListener('click',e=>{e.preventDefault();dlg.showModal();});
  document.getElementById('terms-accept').addEventListener('click',()=>{box.checked=true;dlg.close();});
})();
</script>
<p class="alt">Already have an account? <a href="login.php">Log in</a></p>
<?php require __DIR__ . '/includes/auth_layout_bottom.php'; ?>
