<?php
/* Basic reports — FR-22 */
require_once __DIR__ . '/config.php';
require_admin();

$today = date('Y-m-d');

/* ---------- Notices by status ---------- */
$statusCounts = [
    'published'   => (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND expiry_date >= '$today'")->fetchColumn(),
    'unpublished' => (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'unpublished'")->fetchColumn(),
    'draft'       => (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'draft'")->fetchColumn(),
    'expired'     => (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND expiry_date < '$today'")->fetchColumn(),
];
$maxStatus = max(1, ...array_values($statusCounts));

/* ---------- Notices by category ---------- */
$catRows = $pdo->query(
    "SELECT c.slug, c.name,
            COUNT(n.notice_id) AS total,
            SUM(CASE WHEN n.status = 'published' AND n.expiry_date >= '$today' THEN 1 ELSE 0 END) AS live,
            SUM(CASE WHEN n.status = 'draft' THEN 1 ELSE 0 END) AS drafts,
            SUM(CASE WHEN n.status = 'published' AND n.expiry_date < '$today' THEN 1 ELSE 0 END) AS expired,
            SUM(CASE WHEN a.attachment_id IS NOT NULL THEN 1 ELSE 0 END) AS with_file
       FROM categories c
  LEFT JOIN notices n ON n.category_id = c.category_id
  LEFT JOIN attachments a ON a.notice_id = n.notice_id
   GROUP BY c.category_id
   ORDER BY c.category_id"
)->fetchAll();
$maxCat = max(1, ...array_map(fn($r) => (int)$r['total'], $catRows));

/* ---------- User summary ---------- */
$userRows = [
    'Registered users'         => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Administrators'           => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'Students / general users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'Active accounts'          => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'Deactivated accounts'     => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
];

$pageTitle   = 'Reports';
$pageSub     = 'Basic notice and user counts · generated ' . date('j M Y');
$pageActions = '<button type="button" class="btn btn-ghost" id="printReport">Print report</button>';
$activeNav   = 'admin-reports.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="admin-section-head"><h2>Notices by status</h2></div>
        <div class="admin-panel">
          <ul class="bar-list">
            <?php foreach ($statusCounts as $key => $count): ?>
            <li>
              <div class="bar-row-top">
                <span><?= status_label($key) ?></span>
                <span><?= $count ?></span>
              </div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= ($count / $maxStatus) * 100 ?>%"></div></div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-head"><h2>Notices by category</h2></div>
        <div class="admin-panel">
          <ul class="bar-list">
            <?php foreach ($catRows as $row): ?>
            <li>
              <div class="bar-row-top">
                <span><?= e($row['name']) ?></span>
                <span><?= (int)$row['total'] ?></span>
              </div>
              <div class="bar-track">
                <div class="bar-fill cat-<?= e($row['slug']) ?>" style="width:<?= ($row['total'] / $maxCat) * 100 ?>%"></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-head"><h2>Notice summary</h2></div>
        <div class="admin-panel">
          <div class="table-scroll">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Total</th>
                  <th>Published</th>
                  <th>Draft</th>
                  <th>Expired</th>
                  <th>With attachment</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($catRows as $row): ?>
                <tr>
                  <td><span class="cell-title"><?= e($row['name']) ?></span></td>
                  <td><?= (int)$row['total'] ?></td>
                  <td><?= (int)$row['live'] ?></td>
                  <td><?= (int)$row['drafts'] ?></td>
                  <td><?= (int)$row['expired'] ?></td>
                  <td><?= (int)$row['with_file'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-section-head"><h2>User summary</h2></div>
        <div class="admin-panel">
          <div class="table-scroll">
            <table class="admin-table" style="min-width:420px">
              <thead>
                <tr><th>Item</th><th>Count</th></tr>
              </thead>
              <tbody>
                <?php foreach ($userRows as $label => $count): ?>
                <tr>
                  <td><span class="cell-title"><?= $label ?></span></td>
                  <td><?= $count ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
