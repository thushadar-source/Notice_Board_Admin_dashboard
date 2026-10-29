<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_logged_in()) {
    redirect(is_admin() ? 'admin-dashboard.php' : 'index.php');
}

$error = '';
$flash = get_flash();
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $loginId = trim($_POST['loginId'] ?? '');
    $password = $_POST['loginPassword'] ?? '';

    if ($loginId === '' || $password === '') {
        $error = 'Please fill in both fields to continue.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u OR email = :m LIMIT 1');
        $stmt->execute([':u' => $loginId, ':m' => $loginId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid username or password.';
        } elseif ($user['role'] === 'admin' && $user['username'] !== 'admin') {
            $error = 'Invalid admin username or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account has been deactivated. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            set_flash('Welcome back, ' . $user['full_name'] . '.');
            redirect($user['role'] === 'admin' ? 'admin-dashboard.php' : 'index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in - Meridian Notice Board</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-shell">

  <div class="auth-brand">
    <a href="index.php" class="brand">
      <span class="brand-logo-chip">
        <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
      </span>
    </a>
    <div class="auth-brand-copy">
      <h1>Every notice, in one place.</h1>
      <p>Academic updates, exam schedules, events and scholarships - published by the institute and searchable the moment they go live.</p>
    </div>
    <p class="auth-brand-foot">Digital Notice Board Management System</p>
  </div>

  <div class="auth-form-side">
    <a href="index.php" class="auth-close" aria-label="Close and return to notice board" title="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </a>
    <div class="auth-card">
      <h2>Welcome back</h2>
      <p class="lede">Log in to view notices and access your account.</p>

      <div class="form-banner <?= ($error || $flash) ? 'visible' : '' ?>">
        <span><?= e($error ?: $flash) ?></span>
      </div>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="field <?= $error && $loginId === '' ? 'has-error' : '' ?>">
          <label for="loginId">Username or email</label>
          <input type="text" id="loginId" name="loginId" value="<?= e($loginId) ?>" required autocomplete="username">
          <p class="field-error">Enter your username or email.</p>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="loginPassword" required autocomplete="current-password">
          <p class="field-error">Enter your password.</p>
        </div>

        <div class="form-row">
          <label class="checkbox-row">
            <input type="checkbox" id="remember" name="remember">
            <span>Remember me</span>
          </label>
          <a href="#">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
      </form>

      <p class="auth-switch">New user? <a href="register.php">Create account</a></p>
      <p class="auth-switch">Administrator? <a href="admin-login.php">Open admin login</a></p>
    </div>
  </div>

</div>

</body>
</html>
