<?php
/* Admin dashboard — FR-10, FR-22 */
require_once __DIR__ . '/config.php';
require_admin();

$today = date('Y-m-d');

/* ---------- Counts ---------- */
$total     = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();
$published = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND expiry_date >= '$today'")->fetchColumn();
$expired   = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND expiry_date < '$today'")->fetchColumn();
$drafts    = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'draft'")->fetchColumn();
$pinned    = (int)$pdo->query('SELECT COUNT(*) FROM notices WHERE is_pinned = 1')->fetchColumn();
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

/* ---------- Recent notices ---------- */
$recent = $pdo->query(
    'SELECT n.*, c.name AS category_name
       FROM notices n
       JOIN categories c ON c.category_id = n.category_id
   ORDER BY n.publish_date DESC, n.notice_id DESC
      LIMIT 5'
)->fetchAll();

/* ---------- Notices per category ---------- */
$catRows = $pdo->query(
    'SELECT c.name, COUNT(n.notice_id) AS total
       FROM categories c
  LEFT JOIN notices n ON n.category_id = c.category_id
   GROUP BY c.category_id
   ORDER BY c.category_id'
)->fetchAll();

$catSlugs = array_column(get_categories($pdo), 'slug');
$maxCat = max(1, ...array_map(fn($r) => (int)$r['total'], $catRows));

$iconPin = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M9 2h6l-1 8 3 3v2H7v-2l3-3-1-8z"/></svg>';

$stats = [
    ['value' => $total,     'label' => 'Total notices',    'tone' => '',           'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="13" y2="16"/></svg>'],
    ['value' => $published, 'label' => 'Published & live', 'tone' => 'tone-green', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
    ['value' => $pinned,    'label' => 'Pinned notices',   'tone' => 'tone-pin',   'icon' => $iconPin],
    ['value' => $drafts,    'label' => 'Drafts',           'tone' => '',           'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>'],
    ['value' => $expired,   'label' => 'Expired',          'tone' => 'tone-red',   'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>'],
    ['value' => $userCount, 'label' => 'Registered users', 'tone' => '',           'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/></svg>'],
];

$pageTitle   = 'Dashboard';
$pageSub     = 'Overview of notices and registered users';
$pageActions = '<a href="admin-notice-form.php" class="btn btn-primary">'
             . '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
             . 'Add notice</a>';
$activeNav   = 'admin-dashboard.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="stat-grid">
          <?php foreach ($stats as $s): ?>
          <div class="stat-card">
            <span class="stat-icon <?= $s['tone'] ?>"><?= $s['icon'] ?></span>
            <div>
              <div class="stat-value"><?= $s['value'] ?></div>
              <div class="stat-label"><?= $s['label'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-head">
          <h2>Recent notices</h2>
          <a href="admin-notices.php">Manage all notices</a>
        </div>
        <div class="admin-panel">
          <div class="table-scroll">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Notice</th>
                  <th>Published</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$recent): ?>
                <tr><td colspan="4">
                  <div class="empty-state">
                    <h3>No notices yet</h3>
                    <p>Create the first notice to get started.</p>
                  </div>
                </td></tr>
                <?php endif; ?>

                <?php foreach ($recent as $n): ?>
                <tr>
                  <td>
                    <span class="cell-title">
                      <?php if ($n['is_pinned']): ?><span class="pin-flag" title="Pinned"><?= $iconPin ?></span><?php endif; ?>
                      <?= e($n['title']) ?>
                    </span>
                    <span class="cell-sub"><?= e($n['category_name']) ?></span>
                  </td>
                  <td class="nowrap"><?= format_date($n['publish_date']) ?></td>
                  <td><?= status_badge($n) ?></td>
                  <td class="nowrap">
                    <a class="btn btn-ghost btn-sm" href="admin-notice-form.php?id=<?= $n['notice_id'] ?>">Edit</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-head">
          <h2>Notices by category</h2>
          <a href="admin-reports.php">Open reports</a>
        </div>
        <div class="admin-panel">
          <ul class="bar-list">
            <?php foreach ($catRows as $i => $row): ?>
            <li>
              <div class="bar-row-top">
                <span><?= e($row['name']) ?></span>
                <span><?= (int)$row['total'] ?></span>
              </div>
              <div class="bar-track">
                <div class="bar-fill cat-<?= e($catSlugs[$i] ?? 'general') ?>" style="width:<?= ($row['total'] / $maxCat) * 100 ?>%"></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
