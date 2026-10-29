<?php
/* Manage notices — FR-09 Delete, FR-10 View list, FR-12 Publish/Unpublish,
   FR-14 Pin, FR-16 Filter by category, FR-19 Search */
require_once __DIR__ . '/config.php';
require_admin();

/* ---------------- Actions (POST only) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['notice_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM notices WHERE notice_id = ?');
    $stmt->execute([$id]);
    $notice = $stmt->fetch();

    if ($notice) {
        if ($action === 'pin') {                                  // FR-14
            $pdo->prepare('UPDATE notices SET is_pinned = ? WHERE notice_id = ?')
                ->execute([$notice['is_pinned'] ? 0 : 1, $id]);
            set_flash($notice['is_pinned']
                ? 'Notice unpinned.'
                : 'Notice pinned to the top of the board.');

        } elseif ($action === 'publish') {                        // FR-12, AC-05
            $new = $notice['status'] === 'published' ? 'unpublished' : 'published';
            $pdo->prepare('UPDATE notices SET status = ? WHERE notice_id = ?')
                ->execute([$new, $id]);
            set_flash($new === 'published'
                ? 'Notice published - it is now visible to users.'
                : 'Notice unpublished - it is hidden from users.');

        } elseif ($action === 'delete') {                         // FR-09, AC-04
            /* Remove the stored attachment file as well */
            $att = $pdo->prepare('SELECT stored_name FROM attachments WHERE notice_id = ?');
            $att->execute([$id]);
            foreach ($att->fetchAll() as $file) {
                $path = UPLOAD_DIR . $file['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $pdo->prepare('DELETE FROM notices WHERE notice_id = ?')->execute([$id]);
            set_flash('Notice deleted successfully.');
        }
    }

    redirect('admin-notices.php?' . http_build_query([
        'q'        => $_POST['q'] ?? '',
        'category' => $_POST['category'] ?? 'all',
        'status'   => $_POST['status'] ?? 'all',
    ]));
}

/* ---------------- Filters ---------------- */
$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'all';
$status   = $_GET['status'] ?? 'all';
$today    = date('Y-m-d');
$categories = get_categories($pdo);
$categoryLabels = array_column($categories, 'name', 'slug');

$sql = 'SELECT n.*, c.name AS category_name, c.slug AS category_slug,
               a.file_name AS attachment_name
          FROM notices n
          JOIN categories c ON c.category_id = n.category_id
     LEFT JOIN attachments a ON a.notice_id = n.notice_id
         WHERE 1 = 1';
$params = [];

if ($q !== '') {                                                  // FR-19
    $sql .= ' AND (n.title LIKE :q1 OR n.description LIKE :q2)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}
if ($category !== 'all') {                                        // FR-16
    $sql .= ' AND c.slug = :cat';
    $params[':cat'] = $category;
}
if ($status === 'expired') {
    $sql .= " AND n.status = 'published' AND n.expiry_date < :today";
    $params[':today'] = $today;
} elseif ($status === 'published') {
    $sql .= " AND n.status = 'published' AND n.expiry_date >= :today";
    $params[':today'] = $today;
} elseif ($status !== 'all') {
    $sql .= ' AND n.status = :status';
    $params[':status'] = $status;
}

$sql .= ' ORDER BY n.is_pinned DESC, n.publish_date DESC, n.notice_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notices = $stmt->fetchAll();

$totalNotices = (int)$pdo->query('SELECT COUNT(*) FROM notices')->fetchColumn();

$iconPin    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M9 2h6l-1 8 3 3v2H7v-2l3-3-1-8z"/></svg>';
$iconEdit   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>';
$iconDelete = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';
$iconEye    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
$iconEyeOff = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.1-5.9"/><path d="M9.9 4.2A10.9 10.9 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.2 3.2"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

$pageTitle   = 'Manage notices';
$pageSub     = 'Add, edit, publish, pin or delete notices';
$pageActions = '<a href="admin-notice-form.php" class="btn btn-primary">'
             . '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
             . 'Add notice</a>';
$activeNav   = 'admin-notices.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="admin-panel admin-panel-toolbar">

          <form class="admin-toolbar" id="filterForm" method="get">
            <div class="admin-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search notices by title or keyword" aria-label="Search notices">
            </div>

            <div class="admin-custom-select" data-custom-select>
              <input type="hidden" name="category" value="<?= e($category) ?>">
              <button type="button" class="admin-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span><?= e($category === 'all' ? 'All categories' : ($categoryLabels[$category] ?? 'All categories')) ?></span>
              </button>
              <div class="admin-select-menu" role="listbox">
                <button type="button" role="option" data-value="all" class="<?= $category === 'all' ? 'selected' : '' ?>">All categories</button>
              <?php foreach ($categories as $c): ?>
                <button type="button" role="option" data-value="<?= e($c['slug']) ?>" class="<?= $category === $c['slug'] ? 'selected' : '' ?>"><?= e($c['name']) ?></button>
              <?php endforeach; ?>
              </div>
            </div>

            <div class="admin-custom-select" data-custom-select>
              <input type="hidden" name="status" value="<?= e($status) ?>">
              <button type="button" class="admin-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span><?= e(['all' => 'All statuses', 'published' => 'Published', 'unpublished' => 'Unpublished', 'draft' => 'Draft', 'expired' => 'Expired'][$status] ?? 'All statuses') ?></span>
              </button>
              <div class="admin-select-menu" role="listbox">
              <?php foreach (['all' => 'All statuses', 'published' => 'Published', 'unpublished' => 'Unpublished', 'draft' => 'Draft', 'expired' => 'Expired'] as $val => $label): ?>
                <button type="button" role="option" data-value="<?= $val ?>" class="<?= $status === $val ? 'selected' : '' ?>"><?= $label ?></button>
              <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-ghost btn-sm">Search</button>
            <span class="board-count"><?= count($notices) ?> of <?= $totalNotices ?> notice<?= $totalNotices === 1 ? '' : 's' ?></span>
          </form>

          <div class="table-scroll">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Notice</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Published</th>
                  <th>Expires</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>

                <?php if (!$notices): ?>
                <tr><td colspan="6">
                  <div class="empty-state">
                    <h3>No notices found</h3>
                    <p>Try a different keyword, category or status.</p>
                  </div>
                </td></tr>
                <?php endif; ?>

                <?php foreach ($notices as $n):
    $live = $n['status'] === 'published';

    $todayDate = new DateTime(date('Y-m-d'));
    $expiryDate = new DateTime($n['expiry_date']);
    $daysUntilExpiry = (int) $todayDate->diff($expiryDate)->format('%r%a');

    $expiringSoon = (
        $live &&
        $daysUntilExpiry >= 0 &&
        $daysUntilExpiry <= 3
    );
?>
                <tr>
                  <td>
                    <span class="cell-title">
                      <?php if ($n['is_pinned']): ?><span class="pin-flag" title="Pinned"><?= $iconPin ?></span><?php endif; ?>
                      <?= e($n['title']) ?>
                    </span>
                    <span class="cell-sub"><?= $n['attachment_name'] ? e($n['attachment_name']) : 'No attachment' ?></span>
                  </td>
                  <td class="nowrap"><?= e($n['category_name']) ?></td>
                  <td><?= status_badge($n) ?></td>
                  <td class="nowrap"><?= format_date($n['publish_date']) ?></td>
                  
                 <td class="nowrap">
    <?= format_date($n['expiry_date']) ?>

    <?php if ($expiringSoon): ?>
        <span class="expiry-warning">Expiring Soon</span>
    <?php endif; ?>
</td>
                  <td>
                    <div class="row-actions">

                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>">
                        <input type="hidden" name="category" value="<?= e($category) ?>">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <button type="submit" name="action" value="pin"
                                class="icon-btn <?= $n['is_pinned'] ? 'is-on' : '' ?>"
                                title="<?= $n['is_pinned'] ? 'Unpin notice' : 'Pin notice' ?>"><?= $iconPin ?></button>
                      </form>

                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>">
                        <input type="hidden" name="category" value="<?= e($category) ?>">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <button type="submit" name="action" value="publish"
                                class="icon-btn"
                                title="<?= $live ? 'Unpublish notice' : 'Publish notice' ?>"><?= $live ? $iconEyeOff : $iconEye ?></button>
                      </form>

                      <a class="icon-btn" href="admin-notice-form.php?id=<?= $n['notice_id'] ?>" title="Edit notice"><?= $iconEdit ?></a>

                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>">
                        <input type="hidden" name="category" value="<?= e($category) ?>">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="button" class="icon-btn danger" title="Delete notice"
                                data-confirm='"<?= e($n['title']) ?>" will be removed from the notice list. This action cannot be undone.'
                                data-confirm-title="Delete this notice?"
                                data-confirm-label="Delete notice"><?= $iconDelete ?></button>
                      </form>

                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>

              </tbody>
            </table>
          </div>

        </div>
      </section>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
