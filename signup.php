<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $email = trim((string)($_POST['email'] ?? ''));
  $name  = trim((string)($_POST['full_name'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email.';
  elseif (strlen($pass) < 8) $error = 'Password must be at least 8 characters.';
  else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    try {
      $stmt = db()->prepare("INSERT INTO users (email, password_hash, full_name) VALUES (?, ?, ?)");
      $stmt->execute([$email, $hash, $name ?: null]);
      header('Location: login.php');
      exit;
    } catch (PDOException $e) {
      $error = str_contains($e->getMessage(), 'uq_users_email') ? 'Email already registered.' : 'Database error.';
    }
  }
}

require __DIR__ . '/header.php';
?>
<div class="grid">
  <div class="card">
    <h2>Create your account</h2>
    <p>Secure login + email sending with AI threat scanning.</p>

    <?php if ($error): ?>
      <div class="alert bad"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div>
        <div class="label">Full name (optional)</div>
        <input class="input" name="full_name" placeholder="Jane Doe">
      </div>
      <div>
        <div class="label">Email</div>
        <input class="input" name="email" placeholder="you@example.com" required>
      </div>
      <div>
        <div class="label">Password</div>
        <input class="input" type="password" name="password" placeholder="Min 8 characters" required>
      </div>

      <div class="btnrow">
        <button class="btn" type="submit">Create account</button>
        <a class="btn secondary" href="login.php">I already have an account</a>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>What you get</h2>
    <p>Before sending, your Python engine evaluates risk score + triggers.</p>
    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
      <span class="badge"><span class="dot good"></span> Allowed</span>
      <span class="badge"><span class="dot warn"></span> Flagged</span>
      <span class="badge"><span class="dot bad"></span> Blocked</span>
    </div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>