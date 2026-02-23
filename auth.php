<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
  }
}

function current_user(): ?array {
  if (empty($_SESSION['user_id'])) return null;
  $stmt = db()->prepare("SELECT id, email, full_name, created_at, last_login_at FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $u = $stmt->fetch();
  return $u ?: null;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_verify(): void {
  $token = $_POST['csrf'] ?? '';
  if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    http_response_code(403);
    exit('CSRF verification failed.');
  }
}