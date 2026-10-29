<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    set_flash('Please register or log in to view the notice board.');
    redirect('login.php');
}

$today = date('Y-m-d');
ensure_calendar_notes_table($pdo);

$categoryRows = get_visible_categories($pdo);
$categoryLabels = [];
foreach ($categoryRows as $categoryRow) {
    $categoryLabels[$categoryRow['slug']] = $categoryRow['name'];
}

$stmt = $pdo->prepare(
    "SELECT n.notice_id, n.title, n.description, n.publish_date, n.expiry_date, n.is_pinned,
            c.slug AS category_slug, c.name AS category_name,
            a.attachment_id, a.file_name AS attachment_name
       FROM notices n
       JOIN categories c ON c.category_id = n.category_id
  LEFT JOIN attachments a ON a.notice_id = n.notice_id
      WHERE n.status = 'published'
        AND c.is_hidden = 0
        AND n.publish_date <= :publish_today
        AND n.expiry_date >= :expiry_today
   ORDER BY n.is_pinned DESC, n.publish_date DESC, n.notice_id DESC"
);
$stmt->execute([
    ':publish_today' => $today,
    ':expiry_today' => $today,
]);
$notices = $stmt->fetchAll();

$calendarStmt = $pdo->prepare(
    'SELECT * FROM calendar_notes
      WHERE is_visible = 1
   ORDER BY (note_date < ?) ASC, note_date ASC, calendar_note_id DESC
      LIMIT 100'
);
$calendarStmt->execute([$today]);
$calendarNotes = $calendarStmt->fetchAll();

function notice_excerpt($description)
{
    $text = trim(preg_replace('/\s+/', ' ', (string)$description));
    if (function_exists('mb_strlen') && mb_strlen($text) > 160) {
        return mb_substr($text, 0, 157) . '...';
    }
    return strlen($text) > 160 ? substr($text, 0, 157) . '...' : $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notice Board - Meridian Institute</title>
<script>
document.documentElement.dataset.theme = localStorage.getItem('noticeTheme') || 'light';
</script>
<link rel="stylesheet" href="style.css">
</head>
<body class="board-page">

<header class="site-header">
  <div class="header-row header-row-top">
    <a href="index.php" class="brand">
      <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
    </a>

    <div class="header-actions">
      <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch to dark mode" aria-pressed="false">
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/></svg>
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z"/></svg>
      </button>
      <span class="btn btn-text"><?= e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></span>
      <a href="change-password.php" class="btn btn-text">Change password</a>
      <form method="post" action="logout.php" class="header-logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Log out</button>
      </form>
    </div>
  </div>

  <div class="header-row header-row-bottom">
    <div class="header-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" id="searchInput" placeholder="Search notices by title or keyword" aria-label="Search notices">
    </div>

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

    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open categories menu" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="board-layout">

  <aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close categories menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <h2>Categories</h2>
    <ul class="category-list">
      <li><button type="button" class="active" data-category="all">All notices</button></li>
      <?php foreach ($categoryRows as $category): ?>
      <li>
        <button type="button" data-category="<?= e($category['slug']) ?>">
          <span class="dot dot-<?= e($category['slug']) ?>"></span><?= e($category['name']) ?>
        </button>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="sidebar-note">Pinned notices are shown first, and expired notices are automatically hidden.</p>
  </aside>

  <main class="board">
    <div class="board-heading">
      <h1 id="boardHeading">Current notices</h1>
      <span class="board-count" id="boardCount"></span>
    </div>
    <div class="notice-grid" id="noticeGrid">
      <?php foreach ($notices as $notice): ?>
      <article class="notice-card cat-<?= e($notice['category_slug']) ?> <?= $notice['is_pinned'] ? 'pinned' : '' ?>"
               data-category="<?= e($notice['category_slug']) ?>"
               data-title="<?= e(strtolower($notice['title'])) ?>"
               data-search="<?= e(strtolower($notice['title'] . ' ' . $notice['description'] . ' ' . $notice['category_name'])) ?>"
               data-href="notice-details.php?id=<?= (int)$notice['notice_id'] ?>"
               tabindex="0" role="link" aria-label="<?= e($notice['title']) ?>">
        <?php if ($notice['is_pinned']): ?><span class="pin-dot" aria-hidden="true"></span><?php endif; ?>
        <div class="notice-meta-row">
          <span class="category-chip"><?= e($notice['category_name']) ?></span>
          <span class="notice-date"><?= format_date($notice['publish_date']) ?></span>
        </div>
        <h3><?= e($notice['title']) ?></h3>
        <p class="notice-excerpt"><?= e(notice_excerpt($notice['description'])) ?></p>
        <?php if ($notice['attachment_id']): ?>
        <span class="notice-attachment">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7"/><polyline points="7 8 12 3 17 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <?= e($notice['attachment_name']) ?>
        </span>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
      <div class="empty-state <?= $notices ? 'is-hidden' : '' ?>" id="emptyState">
        <h3>No notices found</h3>
        <p>Try a different keyword or choose another category.</p>
      </div>
    </div>
  </main>

</div>

<footer class="site-footer">
  Digital Notice Board Management System &middot; Meridian Institute
</footer>

<script>
window.CATEGORY_LABELS = <?= json_encode($categoryLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.CALENDAR_NOTES = <?= json_encode($calendarNotes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="script.js"></script>
</body>
</html>
