<?php
/* Category management - add, edit, hide/show and delete notice categories */
require_once __DIR__ . '/config.php';
require_admin();

function category_slug_from_name($name)
{
    $slug = strtolower(trim((string)$name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'category';
}

function unique_category_slug(PDO $pdo, $name, $ignoreId = 0)
{
    $base = category_slug_from_name($name);
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ? AND category_id <> ?');

    while (true) {
        $stmt->execute([$slug, $ignoreId]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function validate_category_name(PDO $pdo, $name, $ignoreId = 0)
{
    if ($name === '') {
        return 'Enter a category name.';
    }
    if (strlen($name) > 60) {
        return 'Category name must be 60 characters or fewer.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?) AND category_id <> ?');
    $stmt->execute([$name, $ignoreId]);
    if ((int)$stmt->fetchColumn() > 0) {
        return 'That category already exists.';
    }

    return '';
}

$errors = [];
$categoryName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categoryName = trim($_POST['name'] ?? '');
        $error = validate_category_name($pdo, $categoryName);

        if ($error) {
            $errors['name'] = $error;
        } else {
            $slug = unique_category_slug($pdo, $categoryName);
            $pdo->prepare('INSERT INTO categories (slug, name, is_hidden) VALUES (?, ?, 0)')
                ->execute([$slug, $categoryName]);
            set_flash('Category added successfully.');
            redirect('admin-categories.php');
        }
    }

    if ($action === 'update') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $error = validate_category_name($pdo, $name, $categoryId);

        $stmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            set_flash('Category not found.');
        } elseif ($error) {
            set_flash($error);
        } else {
            $slug = unique_category_slug($pdo, $name, $categoryId);
            $pdo->prepare('UPDATE categories SET name = ?, slug = ? WHERE category_id = ?')
                ->execute([$name, $slug, $categoryId]);
            set_flash('Category updated successfully.');
        }

        redirect('admin-categories.php');
    }

    if ($action === 'toggle_hidden') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            set_flash('Category not found.');
        } else {
            $hidden = $category['is_hidden'] ? 0 : 1;
            $pdo->prepare('UPDATE categories SET is_hidden = ? WHERE category_id = ?')
                ->execute([$hidden, $categoryId]);
            set_flash($hidden ? 'Category hidden from the public board.' : 'Category shown on the public board.');
        }

        redirect('admin-categories.php');
    }

    if ($action === 'delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT c.*, COUNT(n.notice_id) AS notice_count
               FROM categories c
          LEFT JOIN notices n ON n.category_id = c.category_id
              WHERE c.category_id = ?
           GROUP BY c.category_id'
        );
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();

        if (!$category) {
            set_flash('Category not found.');
        } elseif ((int)$category['notice_count'] > 0) {
            set_flash('Cannot delete "' . $category['name'] . '" because notices are using it.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$categoryId]);
            set_flash('Category deleted successfully.');
        }

        redirect('admin-categories.php');
    }
}

$categories = $pdo->query(
    'SELECT c.*, COUNT(n.notice_id) AS notice_count
       FROM categories c
  LEFT JOIN notices n ON n.category_id = c.category_id
   GROUP BY c.category_id
   ORDER BY c.category_id'
)->fetchAll();

$pageTitle = 'Categories';
$pageSub = 'Add, rename, hide or delete notice board categories';
$pageActions = '<a href="admin-notice-form.php" class="btn btn-primary">Add notice</a>';
$activeNav = 'admin-categories.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="form-layout">
          <div class="form-panel">
            <h2>Add category</h2>
            <p class="panel-lede">Create a category for grouping notices on the public board and admin forms.</p>

            <div class="form-banner <?= $errors ? 'visible' : '' ?>">
              <span><?= e($errors['name'] ?? 'Please correct the highlighted fields before saving.') ?></span>
            </div>

            <form method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">

              <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
                <label for="name">Category name</label>
                <input type="text" id="name" name="name" value="<?= e($categoryName) ?>" placeholder="e.g. Sports">
                <p class="field-hint">A URL-safe slug is created automatically.</p>
                <p class="field-error"><?= e($errors['name'] ?? '') ?></p>
              </div>

              <button type="submit" class="btn btn-primary">Add category</button>
            </form>
          </div>

          <div class="form-panel">
            <h2>Controls</h2>
            <p class="panel-lede">Rename changes the category everywhere. Hidden categories stay in admin but are removed from the public board and new notice category list.</p>
            <p class="field-hint">Delete is available only when no notices are assigned to that category.</p>
          </div>
        </div>
      </section>

      <section class="admin-section">
        <div class="admin-panel">
          <div class="table-scroll">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Slug</th>
                  <th>Status</th>
                  <th>Notices</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                  <td>
                    <form method="post" class="inline-edit-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                      <input type="text" name="name" value="<?= e($category['name']) ?>" aria-label="Category name">
                      <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                    </form>
                  </td>
                  <td><span class="cell-sub"><?= e($category['slug']) ?></span></td>
                  <td>
                    <span class="badge badge-<?= $category['is_hidden'] ? 'inactive' : 'active' ?>">
                      <?= $category['is_hidden'] ? 'Hidden' : 'Visible' ?>
                    </span>
                  </td>
                  <td><?= (int)$category['notice_count'] ?></td>
                  <td>
                    <div class="row-actions">
                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_hidden">
                        <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">
                          <?= $category['is_hidden'] ? 'Show' : 'Hide' ?>
                        </button>
                      </form>

                      <?php if ((int)$category['notice_count'] > 0): ?>
                      <span class="cell-sub">In use</span>
                      <?php else: ?>
                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                        <button type="button" class="btn btn-ghost btn-sm btn-delete"
                                data-confirm='"<?= e($category['name']) ?>" will be removed from category lists.'
                                data-confirm-title="Delete this category?"
                                data-confirm-label="Delete category">
                          Delete
                        </button>
                      </form>
                      <?php endif; ?>
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
