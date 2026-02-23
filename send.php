<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
$u = current_user();

function find_user_id_by_email(string $email): ?int {
  $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $row = $stmt->fetch();
  return $row ? (int)$row['id'] : null;
}

function run_python_scan(string $sender, string $subject, string $body): ?array {
  $payload = json_encode([
    'sender'  => $sender,
    'subject' => $subject,
    'body'    => $body,
  ], JSON_UNESCAPED_UNICODE);

  if ($payload === false) return null;

  $tmpIn  = tempnam(sys_get_temp_dir(), 'mg_in_');
  $tmpOut = tempnam(sys_get_temp_dir(), 'mg_out_');
  file_put_contents($tmpIn, $payload);

  $cmd = PYTHON_BIN . " " . escapeshellarg(PYTHON_BRAIN) . " " .
         escapeshellarg($tmpIn) . " " . escapeshellarg($tmpOut);

  $output = [];
  $exitCode = 0;
  exec($cmd . " 2>&1", $output, $exitCode);

  $out = @file_get_contents($tmpOut) ?: '';
  @unlink($tmpIn);
  @unlink($tmpOut);

  if ($exitCode !== 0) {
    file_put_contents(__DIR__ . '/python_error.log', implode("\n", $output));
    return null;
  }

  $data = json_decode($out, true);
  return is_array($data) ? $data : null;
}

$alertClass = '';
$alertText  = '';
$scanResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $to      = trim((string)($_POST['to_email'] ?? ''));
  $from    = trim((string)($_POST['from_email'] ?? $u['email']));
  $subject = trim((string)($_POST['subject'] ?? ''));
  $body    = trim((string)($_POST['body'] ?? ''));

  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $alertClass = 'bad'; $alertText = 'Invalid recipient email.';
  } elseif (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
    $alertClass = 'bad'; $alertText = 'Invalid from email.';
  } elseif ($subject === '' || $body === '') {
    $alertClass = 'bad'; $alertText = 'Subject and body are required.';
  } else {

    // Save outgoing
    $stmt = db()->prepare("
      INSERT INTO emails (user_id, to_email, from_email, subject, body, status)
      VALUES (?, ?, ?, ?, ?, 'DRAFT')
    ");
    $stmt->execute([(int)$u['id'], $to, $from, $subject, $body]);
    $emailId = (int)db()->lastInsertId();

    // Find recipient
    $recipientId = find_user_id_by_email($to);
    if (!$recipientId) {
      db()->prepare("UPDATE emails SET status='FAILED' WHERE id=? AND user_id=?")
        ->execute([$emailId, (int)$u['id']]);

      $alertClass = 'warn';
      $alertText = "Recipient not registered: {$to}";
    } else {
      // Deliver to recipient inbox
      $stmt = db()->prepare("
        INSERT INTO received_emails (user_id, from_email, subject, body)
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([(int)$recipientId, $from, $subject, $body]);
      $receivedId = (int)db()->lastInsertId();

      // Toggle
      $useScan = isset($_POST['use_ai_scan']);

      if ($useScan) {
        $scanResult = run_python_scan($from, $subject, $body);
      }

      // Ensure structured result ALWAYS stored
      if (!$useScan) {
        $scanResult = [
          'ai_detection' => ['is_ai' => null, 'confidence' => null],
          'threat_detection' => ['status' => 'NOT_SCANNED', 'risk_score' => 0, 'triggers' => 'AI scan disabled']
        ];
      } elseif ($scanResult === null) {
        // Python failed: do NOT block, but record it
        $scanResult = [
          'ai_detection' => ['is_ai' => null, 'confidence' => null],
          'threat_detection' => ['status' => 'UNKNOWN', 'risk_score' => 0, 'triggers' => 'Python scanner failed (see python_error.log)']
        ];
      }

      $threat = $scanResult['threat_detection'] ?? ['status'=>'UNKNOWN','risk_score'=>0,'triggers'=>'Missing threat_detection'];
      $status = (string)($threat['status'] ?? 'UNKNOWN');
      $risk   = (int)($threat['risk_score'] ?? 0);
      $trig   = (string)($threat['triggers'] ?? 'None');

      // Store scan in ai_scans linked to received_email_id
      db()->prepare("
        INSERT INTO ai_scans (received_email_id, user_id, risk_score, status, triggers, raw_json)
        VALUES (?, ?, ?, ?, ?, ?)
      ")->execute([
        $receivedId,
        (int)$recipientId,
        $risk,
        $status,
        $trig,
        json_encode($scanResult, JSON_UNESCAPED_UNICODE) ?: '{}'
      ]);

      // Mark outgoing as sent
      db()->prepare("UPDATE emails SET status='SENT', sent_at=NOW() WHERE id=? AND user_id=?")
        ->execute([$emailId, (int)$u['id']]);

      // Build a clean UI alert: "Sent successfully • AI: 0%"
      $aiPct = null;
      if (isset($scanResult['ai_detection']['confidence'])) {
        $aiPct = (int)$scanResult['ai_detection']['confidence'];
      }

      $alertClass = 'good';
      $alertText  = 'Sent successfully';
      $alertMeta  = ($aiPct !== null) ? ("AI: {$aiPct}%") : "AI: N/A";
    }
  }
}

require __DIR__ . '/header.php';
?>

<?php if (!empty($alertText)): ?>
  <div class="alert <?= htmlspecialchars($alertClass) ?>">
    <?= htmlspecialchars($alertText) ?>

    <?php if (!empty($alertMeta)): ?>
      <div class="small" style="margin-top:6px; opacity:.9;">
        <?= htmlspecialchars($alertMeta) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Compose & Scan</h2>

    <?php if ($alertText): ?>
      <div class="alert <?= htmlspecialchars($alertClass) ?>">
        <?= htmlspecialchars($alertText) ?>
      </div>
    <?php endif; ?>

    <form class="form" method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div>
        <div class="label">From</div>
        <input class="input" name="from_email" value="<?= htmlspecialchars($u['email']) ?>" required>
      </div>

      <div>
        <div class="label">To</div>
        <input class="input" name="to_email" placeholder="other_user@email.com" required>
      </div>

      <div>
        <div class="label">Subject</div>
        <input class="input" name="subject" required>
      </div>

      <div>
        <div class="label">Body</div>
        <textarea name="body" required></textarea>
      </div>

      <div style="margin-top:14px;">
        <label style="display:flex; gap:10px; align-items:center; cursor:pointer;">
          <input type="checkbox" name="use_ai_scan" value="1" checked>
          <strong>Run AI scan</strong>
        </label>
      </div>

      <div class="btnrow">
        <button class="btn" type="submit">Send</button>
        <a class="btn secondary" href="inbox.php">Inbox</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>