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

$stmt = db()->prepare("
  SELECT r.id, r.from_email, r.subject, r.received_at,
         s.status, s.risk_score, s.raw_json
  FROM received_emails r
  LEFT JOIN ai_scans s
    ON s.id = (
      SELECT s2.id
      FROM ai_scans s2
      WHERE s2.received_email_id = r.id
      ORDER BY s2.id DESC
      LIMIT 1
    )
  WHERE r.user_id = ?
  ORDER BY r.id DESC
");
$stmt->execute([(int)$u['id']]);
$rows = $stmt->fetchAll();

require __DIR__ . '/header.php';
?>

<div class="card" style="margin-top:16px;">
  <h2>Inbox</h2>

  <div class="btnrow" style="margin-top:12px;">
    <a class="btn" href="send.php">Compose & Scan</a>
  </div>

  <?php if (!$rows): ?>
    <p style="margin-top:20px;">No emails received yet.</p>
  <?php else: ?>
    <table class="table" style="margin-top:20px;">
      <thead>
        <tr>
          <th>ID</th>
          <th>From</th>
          <th>Subject</th>
          <th>AI %</th>
          <th>Risk</th>
          <th>Status</th>
          <th>Received</th>
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

        $status = (string)($r['status'] ?? 'UNKNOWN');
        $risk   = (int)($r['risk_score'] ?? 0);
      ?>
        <tr
        class="clickable-row <?= row_class($status) ?>"
        data-href="view_email.php?id=<?= (int)$r['id'] ?>"
        >
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars((string)$r['from_email']) ?></td>

          <!-- ✅ CLICK LINK -->
          <td>
            <a class="link" href="view_email.php?id=<?= (int)$r['id'] ?>">
              <?= htmlspecialchars((string)$r['subject']) ?>
            </a>
          </td>

          <td><?= $aiPct === null ? '-' : ($aiPct . '%') ?></td>
          <td><?= $risk ?></td>
          <td><span class="status <?= status_class($status) ?>"><?= htmlspecialchars($status) ?></span></td>
          <td><?= htmlspecialchars((string)$r['received_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>