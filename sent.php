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

function row_class(?string $status): string {
  return match ($status) {
    'ALLOWED' => 'row-good',
    'FLAGGED' => 'row-warn',
    'BLOCKED' => 'row-bad',
    default   => '',
  };
}

/*
  Sent emails are stored in `emails` table (sender side).
  Scans are stored in `ai_scans` tied to `received_email_id` (receiver side).
  We join sent emails -> received_emails by matching:
    received_emails.user_id = recipient user id
    received_emails.from_email = sent.from_email
    received_emails.subject = sent.subject
    received_emails.body = sent.body
  Then get the latest ai_scan for that received email.
  (Works well for this internal-app setup.)
*/

$stmt = db()->prepare("
  SELECT
    e.id,
    e.to_email,
    e.from_email,
    e.subject,
    e.sent_at,
    e.status AS send_status,

    s.status AS scan_status,
    s.risk_score,
    s.raw_json

  FROM emails e

  LEFT JOIN users u2
    ON u2.email = e.to_email

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

  WHERE e.user_id = ?
  ORDER BY e.id DESC
  LIMIT 100
");
$stmt->execute([(int)$u['id']]);
$rows = $stmt->fetchAll();

require __DIR__ . '/header.php';
?>

<div class="card" style="margin-top:16px;">
  <h2>Sent Emails</h2>
  <p class="small" style="margin-top:6px; opacity:.85;">
    Messages you sent to other registered users. Includes scan results (AI % + threat score) when available.
  </p>

  <?php if (!$rows): ?>
    <p style="margin-top:18px;">No sent emails yet.</p>
  <?php else: ?>
    <table class="table" style="margin-top:18px;">
      <thead>
        <tr>
          <th>ID</th>
          <th>To</th>
          <th>Subject</th>
          <th>AI %</th>
          
          <th>Scan Status</th>
          <th>Sent</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $scan = null;
        $aiPct = null;

        if (!empty($r['raw_json'])) {
          $scan = json_decode($r['raw_json'], true);
          if (is_array($scan) && isset($scan['ai_detection']['confidence'])) {
            $aiPct = (int)$scan['ai_detection']['confidence'];
          }
        }

        $scanStatus = (string)($r['scan_status'] ?? 'UNKNOWN');
        $risk = (int)($r['risk_score'] ?? 0);
      ?>
        <tr class="clickable-row <?= row_class($scanStatus) ?>"
        data-href="view_sent.php?id=<?= (int)$r['id'] ?>"
        >
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars((string)$r['to_email']) ?></td>
          <td><?= htmlspecialchars((string)$r['subject']) ?></td>
          <td><?= $aiPct === null ? '-' : ($aiPct . '%') ?></td>
          <td><span class="status <?= status_class($scanStatus) ?>"><?= htmlspecialchars($scanStatus) ?></span></td>
          <td><?= htmlspecialchars((string)($r['sent_at'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>