<?php declare(strict_types=1); ?>
    <div class="small" style="margin-top:16px; opacity:.8;">
      © <?= date('Y') ?> MailGuard • Powered by Dalina Canhasi
    </div>
  </div>
  <script>
document.addEventListener('click', function (e) {
  const row = e.target.closest('.clickable-row');
  if (!row) return;

  // Prevent conflict with text selection or links
  if (e.target.tagName.toLowerCase() === 'a') return;

  const href = row.getAttribute('data-href');
  if (href) window.location.href = href;
});
</script>
</body>
</html>