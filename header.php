<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <!-- Logo -->
        <img
          src="assets/logo.png"
          alt="MailGuard"
          class="brand-logo"
        >

        <!-- Brand text -->
        <div class="brand-text">
          <div class="brand-title"><?= htmlspecialchars(APP_NAME) ?></div>
          <div class="brand-subtitle">Internal messaging</div>
        </div>
      </div>

      <div class="nav">
        <?php if ($u): ?>
          <?php
            $active = basename($_SERVER['PHP_SELF']); // inbox.php, send.php, sent.php, etc.
            $is = function(string $file) use ($active): bool { return $active === $file; };
            ?>

            <a class="pill <?= $is('inbox.php') ? 'active' : '' ?>" href="inbox.php">Inbox</a>
            <a class="pill <?= $is('send.php') ? 'active' : '' ?>" href="send.php">Compose & Scan</a>
            <a class="pill <?= $is('sent.php') ? 'active' : '' ?>" href="sent.php">Sent Emails</a>

            <span class="pill user-pill"><?= htmlspecialchars($u['email']) ?></span>
            <a class="pill" href="logout.php">Logout</a>
        <?php else: ?>
          <a class="pill" href="login.php">Login</a>
          <a class="pill" href="signup.php">Sign up</a>
        <?php endif; ?>
      </div>
    </div>