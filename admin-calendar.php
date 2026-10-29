<?php
/* Calendar notes - add, show/hide and delete public calendar items */
require_once __DIR__ . '/config.php';
require_admin();
ensure_calendar_notes_table($pdo);

$errors = [];
$values = [
    'note_date' => date('Y-m-d'),
    'title' => '',
    'note_text' => '',
    'is_visible' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $values = [
            'note_date' => trim($_POST['note_date'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'note_text' => trim($_POST['note_text'] ?? ''),
            'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
        ];

        if ($values['note_date'] === '') {
            $errors['note_date'] = 'Select a date.';
        }
        if ($values['title'] === '') {
            $errors['title'] = 'Enter a calendar note title.';
        } elseif (strlen($values['title']) > 160) {
            $errors['title'] = 'Title must be 160 characters or fewer.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO calendar_notes (note_date, title, note_text, is_visible, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $values['note_date'],
                $values['title'],
                $values['note_text'],
                $values['is_visible'],
                $_SESSION['user_id'] ?? null,
            ]);
            set_flash('Calendar note added.');
            redirect('admin-calendar.php');
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['calendar_note_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM calendar_notes WHERE calendar_note_id = ?');
        $stmt->execute([$id]);
        $note = $stmt->fetch();

        if ($note) {
            $pdo->prepare('UPDATE calendar_notes SET is_visible = ? WHERE calendar_note_id = ?')
                ->execute([$note['is_visible'] ? 0 : 1, $id]);
            set_flash($note['is_visible'] ? 'Calendar note hidden.' : 'Calendar note shown.');
        }
        redirect('admin-calendar.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['calendar_note_id'] ?? 0);
        $pdo->prepare('DELETE FROM calendar_notes WHERE calendar_note_id = ?')->execute([$id]);
        set_flash('Calendar note deleted.');
        redirect('admin-calendar.php');
    }
}

$notes = $pdo->query(
    'SELECT cn.*, u.full_name
       FROM calendar_notes cn
  LEFT JOIN users u ON u.user_id = cn.created_by
   ORDER BY cn.note_date DESC, cn.calendar_note_id DESC'
)->fetchAll();

$pageTitle = 'Calendar';
$pageSub = 'Add dates and notes shown beside the public search bar';
$pageActions = '<a href="index.php" class="btn btn-ghost">View board</a>';
$activeNav = 'admin-calendar.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="form-layout">
          <div class="form-panel">
            <h2>Add calendar note</h2>
            <p class="panel-lede">Create a dated item for the public notice board calendar.</p>

            <div class="form-banner <?= $errors ? 'visible' : '' ?>">
              <span>Please correct the highlighted fields before saving.</span>
            </div>

            <form method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">

              <div class="field <?= isset($errors['note_date']) ? 'has-error' : '' ?>">
                <label for="note_date">Date</label>
                <input type="date" id="note_date" name="note_date" value="<?= e($values['note_date']) ?>">
                <p class="field-error"><?= e($errors['note_date'] ?? '') ?></p>
              </div>

              <div class="field <?= isset($errors['title']) ? 'has-error' : '' ?>">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" placeholder="e.g. Exam registration closes">
                <p class="field-error"><?= e($errors['title'] ?? '') ?></p>
              </div>

              <div class="field">
                <label for="note_text">Note</label>
                <textarea id="note_text" name="note_text" rows="4" placeholder="Optional details"><?= e($values['note_text']) ?></textarea>
              </div>

              <label class="check-card">
                <input type="checkbox" name="is_visible" <?= $values['is_visible'] ? 'checked' : '' ?>>
                <span>
                  <strong>Show on public calendar</strong>
                  <span>Visible notes appear beside the search bar.</span>
                </span>
              </label>

              <button type="submit" class="btn btn-primary btn-block">Add note</button>
            </form>
          </div>

          <div class="form-panel">
            <h2>Calendar notes</h2>
            <p class="panel-lede">Control what users see on the public board calendar.</p>

            <div class="calendar-admin-list">
              <?php if (!$notes): ?>
              <div class="empty-state compact">
                <h3>No calendar notes</h3>
                <p>Add the first date to show it on the notice board.</p>
              </div>
              <?php endif; ?>

              <?php foreach ($notes as $note): ?>
              <div class="calendar-admin-item">
                <div>
                  <span class="calendar-date-pill"><?= format_date($note['note_date']) ?></span>
                  <h3><?= e($note['title']) ?></h3>
                  <?php if ($note['note_text']): ?><p><?= e($note['note_text']) ?></p><?php endif; ?>
                  <span class="cell-sub"><?= $note['is_visible'] ? 'Visible' : 'Hidden' ?><?= $note['full_name'] ? ' by ' . e($note['full_name']) : '' ?></span>
                </div>
                <div class="row-actions">
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="calendar_note_id" value="<?= (int)$note['calendar_note_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $note['is_visible'] ? 'Hide' : 'Show' ?></button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="calendar_note_id" value="<?= (int)$note['calendar_note_id'] ?>">
                    <button type="button" class="btn btn-ghost btn-sm danger"
                            data-confirm='"<?= e($note['title']) ?>" will be removed from the calendar.'
                            data-confirm-title="Delete calendar note?"
                            data-confirm-label="Delete note">Delete</button>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
