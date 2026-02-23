<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($pass, $u['password_hash'])) {
    $error = 'Invalid email or password.';
  } else {
    $_SESSION['user_id'] = (int)$u['id'];
    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
    header('Location: inbox.php');
    exit;
  }
}

require __DIR__ . '/header.php';
?>
<div class="grid">
  <div class="card">
    <h2>Welcome back</h2>
    <p>Login to your secure MailGuard dashboard.</p>

    <?php if ($error): ?>
      <div class="alert bad"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div>
        <div class="label">Email</div>
        <input class="input" name="email" required>
      </div>
      <div>
        <div class="label">Password</div>
        <input class="input" type="password" name="password" required>
      </div>
      <div class="btnrow">
        <button class="btn" type="submit">Login</button>
        <a class="btn secondary" href="signup.php">Create account</a>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Security notes</h2>
    <p class="small" style="margin-top:10px; line-height:1.6;">
      Passwords are stored hashed (PHP <code>password_hash</code>).  
      All DB queries use prepared statements.  
      CSRF tokens protect forms.
    </p>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>