<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
$u = current_user();

/**
 * This page shows emails RECEIVED by the logged-in user (from other registered users),
 * and joins AI scan results (ai_scans.received_email_id = received_emails.id).
 */
$stmt = db()->prepare("
  SELECT
    r.id,
    r.from_email,
    r.subject,
    r.body,
    r.received_at,
    s.status AS scan_status,
    s.risk_score,
    s.triggers
  FROM received_emails r
  LEFT JOIN ai_scans s
    ON s.received_email_id = r.id
  WHERE r.user_id = ?
  ORDER BY r.id DESC
  LIMIT 100
");
$stmt->execute([(int)$u['id']]);
$rows = $stmt->fetchAll();

require __DIR__ . '/header.php';
?>

<div class="card" style="margin-top:16px;">
  <h2>Received Emails</h2>
  <p>Messages delivered to you by other registered users inside the app.</p>

  <div class="btnrow" style="margin-top:12px;">
    <a class="btn" href="send.php">✉️ Compose</a>
    <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
  </div>

  <?php if (!$rows): ?>
    <div class="alert" style="margin-top:14px;">
      No received emails yet. Create 2 users and send from one to the other.
    </div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>From</th>
          <th>Subject</th>
          <th>AI Status</th>
          <th>Risk</th>
          <th>Received</th>
          <th>Triggers</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['from_email']) ?></td>
          <td><?= htmlspecialchars($r['subject']) ?></td>
          <td><?= htmlspecialchars($r['scan_status'] ?? '-') ?></td>
          <td><?= (int)$r['risk_score'] ?></td>
          <td><?= htmlspecialchars($r['received_at']) ?></td>
          <td class="small"><?= htmlspecialchars($r['triggers'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="small" style="margin-top:10px; opacity:.85;">
      Showing latest <?= count($rows) ?> messages.
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>