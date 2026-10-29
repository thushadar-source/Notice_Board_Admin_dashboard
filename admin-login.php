<?php
/* FR-01 Admin Login · FR-04 Login Validation · FR-05 Invalid Login Handling */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_logged_in()) {
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);
}

$error = '';
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $loginId  = trim($_POST['loginId'] ?? '');
    $password = $_POST['loginPassword'] ?? '';

    if ($loginId === '' || $password === '') {
        $error = 'Please fill in both fields to continue.';
    } elseif ($loginId !== 'admin') {
        $error = 'Invalid admin username or password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE username = :u AND role = "admin" LIMIT 1'
        );
        $stmt->execute([':u' => $loginId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid admin username or password.';           // FR-05
        } elseif ($user['status'] !== 'active') {
            $error = 'This account has been deactivated. Please contact the IT administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            set_flash('Welcome back, ' . $user['full_name'] . '.');
            redirect('admin-dashboard.php');
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Log in — Meridian Notice Board</title>
<link rel="stylesheet" href="style.css">
</head>
<body data-flash="<?= e($flash) ?>">

<div class="auth-shell">

  <div class="auth-brand">
    <a href="index.php" class="brand">
      <span class="brand-logo-chip">
        <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
      </span>
    </a>
    <div class="auth-brand-copy">
      <h1>Administration panel.</h1>
      <p>Publish, update and manage every notice on the board — categories, attachments, expiry dates and registered users.</p>
    </div>
    <p class="auth-brand-foot">Digital Notice Board Management System</p>
  </div>

  <div class="auth-form-side">
    <a href="index.php" class="auth-close" aria-label="Close and return to notice board" title="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </a>

    <div class="auth-card">
      <h2>Administrator log in</h2>
      <p class="lede">Enter the administrator credentials to access this area.</p>

      <div class="form-banner <?= $error ? 'visible' : '' ?>">
        <span><?= e($error) ?></span>
      </div>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <label for="loginId">Admin username</label>
          <input type="text" id="loginId" name="loginId" value="<?= e($loginId) ?>" autocomplete="username" required>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="loginPassword" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
      </form>

      <p class="auth-switch">Not an administrator? <a href="login.php">User log in</a></p>
    </div>
  </div>

</div>

<script src="admin-ui.js"></script>
</body>
</html>
