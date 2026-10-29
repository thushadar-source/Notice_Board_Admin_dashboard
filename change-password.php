<?php
/* ============================================================
   CR-01 / FR-23  Change Password
   Lets any logged-in user (student or administrator) change
   their own password.  Re-uses the existing layouts:
     - administrators -> includes/admin-header.php layout
     - students/users -> public notice-board header layout
   No database change: it only updates users.password_hash.
   ============================================================ */
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    set_flash('Please log in to change your password.');
    redirect('login.php');
}

const PW_MIN_LENGTH   = 8;
const PW_MAX_LENGTH   = 72;      // bcrypt only uses the first 72 bytes
const PW_MAX_ATTEMPTS = 5;       // wrong "current password" tries ...
const PW_LOCK_SECONDS = 300;     // ... before a 5 minute lock

$isAdmin = is_admin();

/* Load the logged-in account fresh from the database */
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] !== 'active') {
    redirect($isAdmin ? 'admin-logout.php' : 'logout.php');
}

$errors    = [];     // field level errors: current | new | confirm
$formError = '';     // form level error (lock-out message)
$changed   = !empty($_SESSION['pw_changed']);
unset($_SESSION['pw_changed']);

$lockRemaining = max(0, (int)($_SESSION['pw_locked_until'] ?? 0) - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $current = $_POST['currentPassword'] ?? '';
    $new     = $_POST['newPassword'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';

    if ($lockRemaining > 0) {
        $formError = 'Too many incorrect attempts. Please try again in '
                   . ceil($lockRemaining / 60) . ' minute(s).';
    } else {
        /* 1. Required fields */
        if ($current === '') { $errors['current'] = 'Enter your current password.'; }
        if ($new === '')     { $errors['new']     = 'Enter a new password.'; }
        if ($confirm === '') { $errors['confirm'] = 'Confirm your new password.'; }

        /* 2. New password rules */
        if (!isset($errors['new'])) {
            if (strlen($new) < PW_MIN_LENGTH) {
                $errors['new'] = 'Password must be at least ' . PW_MIN_LENGTH . ' characters.';
            } elseif (strlen($new) > PW_MAX_LENGTH) {
                $errors['new'] = 'Password must be ' . PW_MAX_LENGTH . ' characters or fewer.';
            } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
                $errors['new'] = 'Use at least one letter and one number.';
            } elseif (strcasecmp($new, $user['username']) === 0) {
                $errors['new'] = 'Password must not be the same as your username.';
            } elseif ($current !== '' && hash_equals($current, $new)) {
                $errors['new'] = 'New password must be different from your current password.';
            }
        }

        /* 3. Confirmation */
        if (!isset($errors['new']) && !isset($errors['confirm']) && $new !== $confirm) {
            $errors['confirm'] = 'The two passwords do not match.';
        }

        /* 4. Verify the current password last (only when everything else is valid) */
        if (!$errors && !password_verify($current, $user['password_hash'])) {
            $attempts = (int)($_SESSION['pw_attempts'] ?? 0) + 1;
            if ($attempts >= PW_MAX_ATTEMPTS) {
                $_SESSION['pw_attempts']     = 0;
                $_SESSION['pw_locked_until'] = time() + PW_LOCK_SECONDS;
                $lockRemaining = PW_LOCK_SECONDS;
                $formError = 'Too many incorrect attempts. Please try again in '
                           . ceil(PW_LOCK_SECONDS / 60) . ' minute(s).';
            } else {
                $_SESSION['pw_attempts'] = $attempts;
                $errors['current'] = 'Current password is incorrect.';
            }
        }

        /* 5. Save */
        if (!$errors && $formError === '') {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$user['user_id']]);

            unset($_SESSION['pw_attempts'], $_SESSION['pw_locked_until']);
            session_regenerate_id(true);

            $_SESSION['pw_changed'] = true;
            if ($isAdmin) {
                set_flash('Password changed successfully.');   // admin toast
            }
            redirect('change-password.php');                   // Post/Redirect/Get
        }
    }
}

$locked    = $lockRemaining > 0;
$cancelUrl = $isAdmin ? 'admin-dashboard.php' : 'index.php';

/* ---------------- Administrator layout ---------------- */
if ($isAdmin) {
    $pageTitle   = 'Change password';
    $pageSub     = 'Update the password you use to log in';
    $pageActions = '<a href="admin-dashboard.php" class="btn btn-ghost">Back to dashboard</a>';
    $activeNav   = 'change-password.php';

    require __DIR__ . '/includes/admin-header.php';
    ?>
      <link rel="stylesheet" href="change-password.css">

      <section class="admin-section">
        <div class="admin-panel cp-panel">
          <?php require __DIR__ . '/includes/change-password-form.php'; ?>
        </div>
      </section>

      <script src="change-password.js"></script>
    <?php
    require __DIR__ . '/includes/admin-footer.php';
    exit;
}

/* ---------------- Student / user layout ---------------- */
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change password - Notice Board</title>
<script>
document.documentElement.dataset.theme = localStorage.getItem('noticeTheme') || 'light';
</script>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="change-password.css">
</head>
<body class="details-page">

<header class="site-header details-site-header">
  <div class="header-actions details-header-actions details-header-meta">
    <span class="btn btn-text"><?= e($currentUser) ?></span>
  </div>
  <a href="index.php" class="brand">
    <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
  </a>
  <div class="header-actions details-header-actions details-header-tools">
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch to dark mode" aria-pressed="false">
      <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/></svg>
      <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z"/></svg>
    </button>
    <form method="post" action="logout.php" class="header-logout-form">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">Log out</button>
    </form>
  </div>
</header>

<div class="details-wrap">
  <a href="index.php" class="back-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to notice board
  </a>

  <article class="details-card cp-panel">
    <?php require __DIR__ . '/includes/change-password-form.php'; ?>
  </article>
</div>

<footer class="site-footer">
  Digital Notice Board Management System &middot; Meridian Institute
</footer>

<script src="script.js"></script>
<script src="change-password.js"></script>
</body>
</html>
