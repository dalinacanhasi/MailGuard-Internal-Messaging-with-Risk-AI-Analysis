<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
$u = current_user();

function status_class(?string $status): string {
  return match ($status) {
    'ALLOWED' => 'status-good',
    'FLAGGED' => 'status-warn',
    'BLOCKED' => 'status-bad',
    default   => 'status-muted',
  };
}

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("
  SELECT
    e.id,
    e.to_email,
    e.from_email,
    e.subject,
    e.body,
    e.sent_at,
    s.status,
    s.risk_score,
    s.triggers,
    s.raw_json
  FROM emails e
  LEFT JOIN users u2 ON u2.email = e.to_email
  LEFT JOIN received_emails r
    ON r.user_id = u2.id
   AND r.from_email = e.from_email
   AND r.subject = e.subject
   AND r.body = e.body
  LEFT JOIN ai_scans s
    ON s.id = (
      SELECT s2.id
      FROM ai_scans s2
      WHERE s2.received_email_id = r.id
      ORDER BY s2.id DESC
      LIMIT 1
    )
  WHERE e.id = ? AND e.user_id = ?
  LIMIT 1
");
$stmt->execute([$id, (int)$u['id']]);
$email = $stmt->fetch();

if (!$email) {
  http_response_code(404);
  die('Sent email not found.');
}

$aiPct = null;
if (!empty($email['raw_json'])) {
  $scan = json_decode($email['raw_json'], true);
  if (is_array($scan) && isset($scan['ai_detection']['confidence'])) {
    $aiPct = (int)$scan['ai_detection']['confidence'];
  }
}

$status = (string)($email['status'] ?? 'UNKNOWN');
$risk   = (int)($email['risk_score'] ?? 0);

require __DIR__ . '/header.php';
?>

<div style="max-width:1100px; margin:0 auto; padding:20px;">
  <div class="card">
    <h1><?= htmlspecialchars((string)$email['subject']) ?></h1>

    <div class="small" style="margin-top:10px; line-height:1.8;">
      <div><b>From:</b> <?= htmlspecialchars((string)$email['from_email']) ?></div>
      <div><b>To:</b> <?= htmlspecialchars((string)$email['to_email']) ?></div>
      <div><b>Sent:</b> <?= htmlspecialchars((string)$email['sent_at']) ?></div>
    </div>

    <hr style="margin:16px 0;">

    <div style="white-space:pre-wrap; line-height:1.7;">
      <?= htmlspecialchars((string)$email['body']) ?>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <h3>Scan Summary</h3>
    <div style="display:flex; gap:16px; flex-wrap:wrap;">
      <div><b>Status:</b> <span class="status <?= status_class($status) ?>"><?= htmlspecialchars($status) ?></span></div>
      <div><b>Risk:</b> <?= $risk ?></div>
      <div><b>AI %:</b> <?= $aiPct === null ? 'N/A' : ($aiPct . '%') ?></div>
    </div>
  </div>

  <div class="btnrow" style="margin-top:16px;">
    <a class="btn secondary" href="sent.php">← Back to Sent</a>
    <a class="btn" href="send.php">Compose & Scan</a>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>