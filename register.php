<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_logged_in()) {
    redirect(is_admin() ? 'admin-dashboard.php' : 'index.php');
}

$error = '';
$values = [
    'regName' => '',
    'regUsername' => '',
    'regEmail' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $values['regName'] = trim($_POST['regName'] ?? '');
    $values['regUsername'] = trim($_POST['regUsername'] ?? '');
    $values['regEmail'] = trim($_POST['regEmail'] ?? '');
    $password = $_POST['regPassword'] ?? '';

    if ($values['regName'] === '' || $values['regUsername'] === '' || $values['regEmail'] === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($values['regEmail'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $values['regUsername'])) {
        $error = 'Username must be 3-60 characters and use only letters, numbers, dots, underscores or hyphens.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([
            ':u' => $values['regUsername'],
            ':e' => $values['regEmail'],
        ]);
        $existing = $stmt->fetch();

        if ($existing && strcasecmp($existing['username'], $values['regUsername']) === 0) {
            $error = 'That username is already registered.';
        } elseif ($existing && strcasecmp($existing['email'], $values['regEmail']) === 0) {
            $error = 'That email address is already registered.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, username, email, password_hash, role, status)
                 VALUES (:name, :username, :email, :password_hash, 'user', 'active')"
            );
            $stmt->execute([
                ':name' => $values['regName'],
                ':username' => $values['regUsername'],
                ':email' => $values['regEmail'],
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['username'] = $values['regUsername'];
            $_SESSION['full_name'] = $values['regName'];
            $_SESSION['role'] = 'user';

            set_flash('Account created. Welcome, ' . $values['regName'] . '.');
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Meridian Notice Board</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-shell">

  <div class="auth-brand">
    <a href="login.php" class="brand">
      <span class="brand-logo-chip">
        <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
      </span>
    </a>
    <div class="auth-brand-copy">
      <h1>Create your account.</h1>
      <p>Register once to view, search, filter and download every published notice.</p>
    </div>
    <p class="auth-brand-foot">Digital Notice Board Management System</p>
  </div>

  <div class="auth-form-side">
    <a href="login.php" class="auth-close" aria-label="Return to login" title="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </a>
    <div class="auth-card">
      <h2>Create an account</h2>
      <p class="lede">Register first to enter the notice board.</p>

      <div class="form-banner <?= $error ? 'visible' : '' ?>">
        <span><?= e($error) ?></span>
      </div>

      <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <label for="regName">Full name</label>
          <input type="text" id="regName" name="regName" value="<?= e($values['regName']) ?>" required autocomplete="name">
          <p class="field-error">Enter your full name.</p>
        </div>

        <div class="field">
          <label for="regUsername">Username</label>
          <input type="text" id="regUsername" name="regUsername" value="<?= e($values['regUsername']) ?>" required autocomplete="username">
          <p class="field-error">Choose a username.</p>
        </div>

        <div class="field">
          <label for="regEmail">Email address</label>
          <input type="email" id="regEmail" name="regEmail" value="<?= e($values['regEmail']) ?>" required autocomplete="email">
          <p class="field-hint">Used for login and account recovery.</p>
          <p class="field-error">Enter a valid email address.</p>
        </div>

        <div class="field">
          <label for="regPassword">Password</label>
          <input type="password" id="regPassword" name="regPassword" required autocomplete="new-password">
          <p class="field-hint">At least 8 characters.</p>
          <p class="field-error">Choose a password.</p>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Create account</button>
      </form>

      <p class="auth-switch">Already registered? <a href="login.php">Log in</a></p>
    </div>
  </div>

</div>

</body>
</html>
