<?php
/* Add / edit notice — FR-07 Add, FR-08 Update, FR-13 Dates, FR-14 Pin,
   FR-15 Category, FR-17 Upload attachment.
   Business rules: BR-03, BR-05, BR-07, BR-09 */
require_once __DIR__ . '/config.php';
require_admin();

$id     = (int)($_GET['id'] ?? 0);
$notice = null;
$attachment = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM notices WHERE notice_id = ?');
    $stmt->execute([$id]);
    $notice = $stmt->fetch();

    if (!$notice) {
        set_flash('That notice no longer exists.');
        redirect('admin-notices.php');
    }

    $stmt = $pdo->prepare('SELECT * FROM attachments WHERE notice_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $attachment = $stmt->fetch() ?: null;
}

/* Values shown in the form */
$values = [
    'title'        => $notice['title']        ?? '',
    'category_id'  => $notice['category_id']  ?? '',
    'description'  => $notice['description']  ?? '',
    'status'       => $notice['status']       ?? 'published',
    'publish_date' => $notice['publish_date'] ?? date('Y-m-d'),
    'expiry_date'  => $notice['expiry_date']  ?? '',
    'is_pinned'    => $notice['is_pinned']    ?? 0,
];
$formCategories = get_visible_categories($pdo);
if ($notice && $notice['category_id']) {
    $hasCurrentCategory = false;
    foreach ($formCategories as $category) {
        if ((int)$category['category_id'] === (int)$notice['category_id']) {
            $hasCurrentCategory = true;
            break;
        }
    }
    if (!$hasCurrentCategory) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = ?');
        $stmt->execute([(int)$notice['category_id']]);
        $currentCategory = $stmt->fetch();
        if ($currentCategory) {
            $formCategories[] = $currentCategory;
        }
    }
}

$errors = [];

/* ---------------- Save ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $values = [
        'title'        => trim($_POST['title'] ?? ''),
        'category_id'  => (int)($_POST['category_id'] ?? 0),
        'description'  => trim($_POST['description'] ?? ''),
        'status'       => $_POST['status'] ?? 'draft',
        'publish_date' => $_POST['publish_date'] ?? '',
        'expiry_date'  => $_POST['expiry_date'] ?? '',
        'is_pinned'    => isset($_POST['is_pinned']) ? 1 : 0,
    ];

    if (isset($_POST['save_draft'])) {
        $values['status'] = 'draft';
    }

    /* --- Validation (BR-07) --- */
    if ($values['title'] === '')        { $errors['title'] = 'Enter a notice title.'; }
    if (!$values['category_id'])        { $errors['category_id'] = 'Select a category.'; }
    if ($values['description'] === '')  { $errors['description'] = 'Enter the notice description.'; }
    if (!$values['publish_date'])       { $errors['publish_date'] = 'Select a publication date.'; }
    if (!$values['expiry_date'])        { $errors['expiry_date'] = 'Select an expiry date.'; }

    /* BR-09 — expiry must not be earlier than publication */
    if ($values['publish_date'] && $values['expiry_date']
        && $values['expiry_date'] < $values['publish_date']) {
        $errors['expiry_date'] = 'Expiry date cannot be earlier than the publication date.';
    }

    if (!in_array($values['status'], ['draft', 'published', 'unpublished'], true)) {
        $errors['status'] = 'Select a valid status.';
    }

    /* --- Attachment validation (NFR-04, BR-05) --- */
    $newFile = null;
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['attachment'] = 'The file could not be uploaded. Please try again.';
        } else {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($info, $file['tmp_name']);
            finfo_close($info);

            if (!in_array($ext, ALLOWED_EXT, true) || !in_array($mime, ALLOWED_TYPES, true)) {
                $errors['attachment'] = 'Only PDF, PNG or JPG files are accepted.';
            } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
                $errors['attachment'] = 'The attachment must be 5 MB or smaller.';
            } else {
                $newFile = [
                    'tmp'   => $file['tmp_name'],
                    'name'  => basename($file['name']),
                    'store' => 'notice_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext,
                    'mime'  => $mime,
                    'size'  => (int)$file['size'],
                ];
            }
        }
    }

    /* --- Write to the database --- */
    if (!$errors) {
        if ($notice) {                                            // FR-08 Update
            $pdo->prepare(
                'UPDATE notices
                    SET title = ?, description = ?, category_id = ?, status = ?,
                        publish_date = ?, expiry_date = ?, is_pinned = ?
                  WHERE notice_id = ?'
            )->execute([
                $values['title'], $values['description'], $values['category_id'],
                $values['status'], $values['publish_date'], $values['expiry_date'],
                $values['is_pinned'], $notice['notice_id'],
            ]);
            $noticeId = $notice['notice_id'];
            $message  = 'Notice updated successfully.';
        } else {                                                  // FR-07 Add
            $pdo->prepare(
                'INSERT INTO notices
                    (title, description, category_id, status, publish_date, expiry_date, is_pinned, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $values['title'], $values['description'], $values['category_id'],
                $values['status'], $values['publish_date'], $values['expiry_date'],
                $values['is_pinned'], $_SESSION['user_id'],
            ]);
            $noticeId = (int)$pdo->lastInsertId();
            $message  = $values['status'] === 'draft'
                ? 'Notice saved as a draft.'
                : 'Notice saved successfully.';
        }

        /* Remove the existing attachment if asked */
        if (!empty($_POST['remove_attachment']) && $attachment) {
            $path = UPLOAD_DIR . $attachment['stored_name'];
            if (is_file($path)) { unlink($path); }
            $pdo->prepare('DELETE FROM attachments WHERE attachment_id = ?')
                ->execute([$attachment['attachment_id']]);
            $attachment = null;
        }

        /* Store the new attachment (FR-17) */
        if ($newFile) {
            if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0775, true); }

            if (move_uploaded_file($newFile['tmp'], UPLOAD_DIR . $newFile['store'])) {
                if ($attachment) {
                    $old = UPLOAD_DIR . $attachment['stored_name'];
                    if (is_file($old)) { unlink($old); }
                    $pdo->prepare('DELETE FROM attachments WHERE attachment_id = ?')
                        ->execute([$attachment['attachment_id']]);
                }
                $pdo->prepare(
                    'INSERT INTO attachments (notice_id, file_name, stored_name, file_type, file_size)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $noticeId, $newFile['name'], $newFile['store'], $newFile['mime'], $newFile['size'],
                ]);
            }
        }

        set_flash($message);
        redirect('admin-notices.php');
    }
}

$pageTitle   = $notice ? 'Edit notice' : 'Add notice';
$pageSub     = $notice ? 'Editing "' . $notice['title'] . '"' : 'Create a new notice for the notice board';
$pageActions = '<a href="admin-notices.php" class="btn btn-ghost">Back to notices</a>';
$activeNav   = $notice ? 'admin-notices.php' : 'admin-notice-form.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="form-layout">

          <div class="form-panel">
            <h2>Notice details</h2>
            <p class="panel-lede">
              <?= $notice ? 'Update the notice details and save your changes.' : 'Fill in the notice information below and save it to the notice board.' ?>
            </p>

            <div class="form-banner <?= $errors ? 'visible' : '' ?>">
              <span>Please correct the highlighted fields before saving.</span>
            </div>

            <div class="field <?= isset($errors['title']) ? 'has-error' : '' ?>">
              <label for="title">Notice title</label>
              <input type="text" id="title" name="title" value="<?= e($values['title']) ?>"
                     placeholder="e.g. Semester 2 Examination Timetable Released">
              <p class="field-error"><?= e($errors['title'] ?? '') ?></p>
            </div>

            <div class="field <?= isset($errors['category_id']) ? 'has-error' : '' ?>">
              <label for="category_id">Category</label>
              <div class="admin-custom-select form-custom-select force-drop-down" data-custom-select data-auto-submit="false">
                <input type="hidden" id="category_id" name="category_id" value="<?= e($values['category_id']) ?>">
                <button type="button" class="admin-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                  <span>
                    <?php
                    $selectedCategoryLabel = 'Select a category';
                    foreach ($formCategories as $c) {
                        if ((int)$values['category_id'] === (int)$c['category_id']) {
                            $selectedCategoryLabel = $c['name'] . (!empty($c['is_hidden']) ? ' (hidden)' : '');
                            break;
                        }
                    }
                    echo e($selectedCategoryLabel);
                    ?>
                  </span>
                </button>
                <div class="admin-select-menu" role="listbox">
                  <button type="button" role="option" data-value="" class="<?= !$values['category_id'] ? 'selected' : '' ?>">Select a category</button>
                <?php foreach ($formCategories as $c): ?>
                  <button type="button" role="option" data-value="<?= (int)$c['category_id'] ?>" class="<?= (int)$values['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>">
                    <?= e($c['name']) ?><?= !empty($c['is_hidden']) ? ' (hidden)' : '' ?>
                  </button>
                <?php endforeach; ?>
                </div>
              </div>
              <p class="field-hint">Hidden categories appear only for notices already assigned to them.</p>
              <p class="field-error"><?= e($errors['category_id'] ?? '') ?></p>
            </div>

            <div class="field <?= isset($errors['description']) ? 'has-error' : '' ?>">
              <label for="description">Description</label>
              <textarea id="description" name="description"
                        placeholder="Write the full notice content here. Leave a blank line between paragraphs."><?= e($values['description']) ?></textarea>
              <p class="field-error"><?= e($errors['description'] ?? '') ?></p>
            </div>

            <h2 style="margin-top:6px">Attachment</h2>
            <p class="panel-lede">Optional — attach a PDF or image for users to download.</p>

            <div class="field <?= isset($errors['attachment']) ? 'has-error' : '' ?>" style="margin-bottom:0">
              <div class="upload-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7"/><polyline points="7 8 12 3 17 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <p><strong>Upload a supporting file</strong></p>
                <p class="field-hint">PDF, PNG or JPG &middot; maximum 5 MB</p>
                <label for="noticeFile" class="btn btn-ghost btn-sm">Choose file</label>
                <input type="file" id="noticeFile" name="attachment" accept=".pdf,.png,.jpg,.jpeg">

                <div class="file-chip <?= $attachment ? 'visible' : '' ?>" id="fileChip">
                  <span id="fileChipName">
                    <?= $attachment ? e($attachment['file_name']) . ' &middot; ' . format_size($attachment['file_size']) : '' ?>
                  </span>
                  <?php if ($attachment): ?>
                  <label class="checkbox-row" style="font-size:.78rem">
                    <input type="checkbox" name="remove_attachment" value="1">
                    <span>Remove</span>
                  </label>
                  <?php endif; ?>
                </div>
              </div>
              <p class="field-error"><?= e($errors['attachment'] ?? '') ?></p>
            </div>
          </div>

          <div class="form-panel">
            <h2>Publishing</h2>
            <p class="panel-lede">Control when and how this notice appears.</p>

            <div class="field <?= isset($errors['status']) ? 'has-error' : '' ?>">
              <label for="status">Status</label>
              <select id="status" name="status">
                <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'unpublished' => 'Unpublished'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $values['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <p class="field-error"><?= e($errors['status'] ?? '') ?></p>
            </div>

            <div class="field <?= isset($errors['publish_date']) ? 'has-error' : '' ?>">
              <label for="publish_date">Publication date</label>
              <input type="date" id="publish_date" name="publish_date" value="<?= e($values['publish_date']) ?>">
              <p class="field-error"><?= e($errors['publish_date'] ?? '') ?></p>
            </div>

            <div class="field <?= isset($errors['expiry_date']) ? 'has-error' : '' ?>">
              <label for="expiry_date">Expiry date</label>
              <input type="date" id="expiry_date" name="expiry_date" value="<?= e($values['expiry_date']) ?>">
              <p class="field-hint">Must not be earlier than the publication date.</p>
              <p class="field-error"><?= e($errors['expiry_date'] ?? '') ?></p>
            </div>

            <label class="switch-row" for="is_pinned">
              <input type="checkbox" id="is_pinned" name="is_pinned" value="1" <?= $values['is_pinned'] ? 'checked' : '' ?>>
              <span>
                <strong>Pin this notice</strong>
                <span>Pinned notices are highlighted and shown first on the board.</span>
              </span>
            </label>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary btn-block">Save notice</button>
              <button type="submit" name="save_draft" value="1" class="btn btn-ghost btn-block">Save as draft</button>
              <button type="button" class="btn btn-text btn-block"
                      data-confirm="Any unsaved changes to this notice will be lost."
                      data-confirm-title="Discard changes?"
                      data-confirm-label="Discard"
                      data-href="admin-notices.php">Cancel</button>
            </div>
          </div>

        </div>
      </form>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
