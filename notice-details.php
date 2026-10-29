<?php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$today = date('Y-m-d');
ensure_calendar_notes_table($pdo);

$stmt = $pdo->prepare(
    'SELECT n.*, c.slug AS category_slug, c.name AS category_name,
            a.attachment_id, a.file_name AS attachment_name, a.file_size AS attachment_size
       FROM notices n
       JOIN categories c ON c.category_id = n.category_id
  LEFT JOIN attachments a ON a.notice_id = n.notice_id
      WHERE n.notice_id = ?
        AND n.status = "published"
        AND c.is_hidden = 0
        AND n.publish_date <= ?
        AND n.expiry_date >= ?'
);
$stmt->execute([$id, $today, $today]);
$notice = $stmt->fetch();

if (!$notice) {
    http_response_code(404);
}

$title = $notice ? $notice['title'] . ' - Notice Board' : 'Notice not found - Notice Board';
$currentDate = format_date($today);
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

$calendarStmt = $pdo->prepare(
    'SELECT * FROM calendar_notes
      WHERE is_visible = 1
   ORDER BY (note_date < ?) ASC, note_date ASC, calendar_note_id DESC
      LIMIT 100'
);
$calendarStmt->execute([$today]);
$calendarNotes = $calendarStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<script>
document.documentElement.dataset.theme = localStorage.getItem('noticeTheme') || 'light';
</script>
<link rel="stylesheet" href="style.css">
</head>
<body class="details-page">

<header class="site-header details-site-header">
  <div class="header-actions details-header-actions details-header-meta">
    <span class="btn btn-text"><?= e($currentUser) ?></span>
    <span class="btn btn-text"><?= e($currentDate) ?></span>
  </div>
  <a href="index.php" class="brand">
    <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
  </a>
  <div class="header-actions details-header-actions details-header-tools">
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch to dark mode" aria-pressed="false">
      <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/></svg>
      <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z"/></svg>
    </button>
    <div class="board-calendar" aria-label="Calendar notes">
      <button type="button" class="calendar-trigger" id="calendarTrigger" aria-expanded="false" aria-controls="calendarPanel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Calendar</span>
        <?php if ($calendarNotes): ?><strong><?= count($calendarNotes) ?></strong><?php endif; ?>
      </button>
      <div class="calendar-panel" id="calendarPanel">
        <div class="calendar-panel-head">
          <button type="button" class="calendar-nav" id="calendarPrev" aria-label="Previous month">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <h2 id="calendarMonth"></h2>
          <button type="button" class="calendar-nav" id="calendarNext" aria-label="Next month">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
        <div class="calendar-weekdays" aria-hidden="true">
          <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
        </div>
        <div class="calendar-grid" id="calendarGrid"></div>
        <div class="calendar-note-view" id="calendarNoteView">
          <p class="calendar-empty">Click a marked date to view notes.</p>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="details-wrap">
  <a href="index.php" class="back-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to notice board
  </a>

  <article class="details-card" id="detailsContent">
    <?php if (!$notice): ?>
    <div class="empty-state">
      <h3>Notice not found</h3>
      <p>This notice is unavailable, unpublished or expired.</p>
    </div>
    <?php else: ?>
    <div class="details-tags">
      <span class="tag tag-<?= e($notice['category_slug']) ?>"><?= e($notice['category_name']) ?></span>
      <?php if ($notice['is_pinned']): ?><span class="tag tag-pinned">Pinned</span><?php endif; ?>
    </div>
    <h1><?= e($notice['title']) ?></h1>
    <p class="details-meta">
      Today <?= $currentDate ?> &middot; Published <?= format_date($notice['publish_date']) ?> &middot; Visible until <?= format_date($notice['expiry_date']) ?>
    </p>
    <div class="details-body">
      <?php foreach (preg_split('/\R{2,}/', trim($notice['description'])) as $paragraph): ?>
      <p><?= nl2br(e($paragraph)) ?></p>
      <?php endforeach; ?>
    </div>
    <?php if ($notice['attachment_id']): ?>
    <div class="attachment-block">
      <div class="attachment-info">
        <span class="attachment-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7"/><polyline points="7 8 12 3 17 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </span>
        <span><?= e($notice['attachment_name']) ?> &middot; <?= format_size($notice['attachment_size']) ?></span>
      </div>
      <a class="btn btn-ghost" href="download.php?id=<?= (int)$notice['attachment_id'] ?>">Download</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </article>
</div>

<footer class="site-footer">
  Digital Notice Board Management System &middot; Meridian Institute
</footer>

<script>
window.CALENDAR_NOTES = <?= json_encode($calendarNotes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="script.js"></script>
</body>
</html>
