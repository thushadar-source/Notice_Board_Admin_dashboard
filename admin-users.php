


<?php
/* User management — FR-20 View users, FR-21 Manage user status */
require_once __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $userId = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['user_id'] === (int)$_SESSION['user_id']) {
            set_flash('You cannot deactivate your own account.');
        } else {
            $new = $user['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?')
                ->execute([$new, $userId]);
            set_flash($new === 'active'
                ? 'User account activated.'
                : 'User account deactivated.');
        }
    }

    redirect('admin-users.php?' . http_build_query([
        'q'    => $_POST['q'] ?? '',
        'role' => $_POST['role'] ?? 'all',
    ]));
}

$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? 'all';

$sql = 'SELECT * FROM users WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (full_name LIKE :q1 OR username LIKE :q2 OR email LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if ($role !== 'all') {
    $sql .= ' AND role = :role';
    $params[':role'] = $role;
}
$sql .= ' ORDER BY role, full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

$pageTitle   = 'Users';
$pageSub     = 'View registered users and manage account status';
$pageActions = '<a href="admin-reports.php" class="btn btn-ghost">View reports</a>';
$activeNav   = 'admin-users.php';

require __DIR__ . '/includes/admin-header.php';
?>

      <section class="admin-section">
        <div class="admin-panel admin-panel-toolbar">

          <form class="admin-toolbar" id="filterForm" method="get">
            <div class="admin-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, username or email" aria-label="Search users">
            </div>

            <?php $roleOptions = ['all' => 'All roles', 'admin' => 'Administrators', 'user' => 'Students / users']; ?>
            <div class="admin-custom-select" data-custom-select>
              <input type="hidden" name="role" value="<?= e($role) ?>">
              <button type="button" class="admin-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span><?= e($roleOptions[$role] ?? 'All roles') ?></span>
              </button>
              <div class="admin-select-menu" role="listbox">
                <?php foreach ($roleOptions as $val => $label): ?>
                <button type="button" role="option" data-value="<?= $val ?>" class="<?= $role === $val ? 'selected' : '' ?>"><?= $label ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn btn-ghost btn-sm">Search</button>
            <span class="board-count"><?= count($users) ?> of <?= $totalUsers ?> user<?= $totalUsers === 1 ? '' : 's' ?></span>
          </form>

          <div class="table-scroll">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Registered</th>
                  <th style="text-align:right">Actions</th>
                </tr>
              </thead>
              <tbody>

                <?php if (!$users): ?>
                <tr><td colspan="6">
                  <div class="empty-state">
                    <h3>No users found</h3>
                    <p>Try a different name, username or email.</p>
                  </div>
                </td></tr>
                <?php endif; ?>

                <?php foreach ($users as $u):
                    $self = (int)$u['user_id'] === (int)$_SESSION['user_id'];
                    $active = $u['status'] === 'active';
                ?>
                <tr>
                  <td>
                    <span class="cell-title"><?= e($u['full_name']) ?></span>
                    <span class="cell-sub">@<?= e($u['username']) ?><?= $self ? ' · you' : '' ?></span>
                  </td>
                  <td><?= e($u['email']) ?></td>
                  <td><span class="badge badge-<?= e($u['role']) ?>"><?= $u['role'] === 'admin' ? 'Administrator' : 'User' ?></span></td>
                  <td><span class="badge badge-<?= e($u['status']) ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
                  <td class="nowrap"><?= format_date($u['created_at']) ?></td>
                  <td>
                    <div class="row-actions">
                      <?php if ($self): ?>
                        <span class="cell-sub">—</span>
                      <?php else: ?>
                      <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <input type="hidden" name="q" value="<?= e($q) ?>">
                        <input type="hidden" name="role" value="<?= e($role) ?>">
                        <button type="button" class="status-toggle <?= $active ? 'is-active' : '' ?>"
                                data-confirm="<?= $active
                                    ? e($u['full_name']) . ' will no longer be able to log in until the account is reactivated.'
                                    : e($u['full_name']) . ' will be able to log in and view published notices again.' ?>"
                                data-confirm-title="<?= $active ? 'Deactivate this account?' : 'Activate this account?' ?>"
                                data-confirm-label="<?= $active ? 'Deactivate' : 'Activate' ?>"
                                aria-label="<?= $active ? 'Deactivate user account' : 'Activate user account' ?>"
                                title="<?= $active ? 'Active - click to deactivate' : 'Inactive - click to activate' ?>">
                          <span class="status-toggle-knob" aria-hidden="true"></span>
                          <span class="status-toggle-text"><?= $active ? 'Active' : 'Inactive' ?></span>
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
