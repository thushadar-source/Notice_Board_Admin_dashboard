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

/* Renders the form (styles + markup + script) - everything lives in this one file */
function cp_render_form(array $errors, string $formError, bool $changed, bool $locked, string $cancelUrl)
{
?>
<!-- change-password form v2 (eye toggle) -->
<style>
/* CR-01 / FR-23 Change Password - styles (embedded so no extra css file is needed) */
.cp-panel{ padding: 32px 36px; }
.details-page .details-card.cp-panel{ flex: none; width: 100%; max-width: 560px; margin: 0 auto; }
.admin-panel.cp-panel{ max-width: 560px; }
.cp-card{ width: 100%; }
.cp-title{ font-size: 1.4rem; margin: 0 0 6px; }
.cp-lede{ color: var(--ink-soft, #5b6475); font-size: .9rem; margin: 0 0 24px; }

.cp-card .field{ margin-bottom: 18px; }
.pw-input{ position: relative; }
.pw-input input{ width: 100%; padding-right: 48px; }
.pw-toggle{
  position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
  width: 36px; height: 36px; padding: 0; border: 0; border-radius: 8px;
  background: transparent; color: var(--ink-soft, #5b6475); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
}
.pw-toggle:hover{ background: rgba(0,0,0,.06); color: var(--primary, #1f3a7a); }
.pw-toggle:focus-visible{ outline: 2px solid var(--primary, #1f3a7a); outline-offset: 1px; }
.pw-toggle svg{ width: 20px; height: 20px; }
.pw-toggle .eye-off{ display: none; }
.pw-toggle.is-visible .eye{ display: none; }
.pw-toggle.is-visible .eye-off{ display: inline; }

.pw-meter{ height: 6px; margin-top: 10px; border-radius: 100px; background: var(--line, #e3e6ec); overflow: hidden; }
.pw-meter span{ display: block; height: 100%; width: 0; border-radius: 100px; transition: width .2s ease, background .2s ease; }
.pw-meter.s1 span{ width: 25%;  background: #c0362c; }
.pw-meter.s2 span{ width: 50%;  background: #d08a1f; }
.pw-meter.s3 span{ width: 75%;  background: #5b9a3c; }
.pw-meter.s4 span{ width: 100%; background: #2f8a4f; }

.form-banner.form-banner-success{ background: #e9f4ee; border-color: #bfdcca; color: #25603f; }
.cp-card .form-actions{ margin-top: 8px; display: flex; gap: 10px; flex-wrap: wrap; }
.cp-card .btn[disabled]{ opacity: .55; cursor: not-allowed; }
@media (max-width: 620px){ .cp-panel{ padding: 24px 20px; } }
</style>
<div class="cp-card">
  <h2 class="cp-title">Change password</h2>
  <p class="cp-lede">Enter your current password, then choose a new one. You will stay logged in on this device.</p>

  <?php if ($changed): ?>
  <div class="form-banner form-banner-success visible" role="status">
    <span>Your password has been changed successfully.</span>
  </div>
  <?php endif; ?>

  <?php if ($formError): ?>
  <div class="form-banner visible" role="alert">
    <span><?= e($formError) ?></span>
  </div>
  <?php endif; ?>

  <form method="post" id="changePasswordForm" novalidate>
    <?= csrf_field() ?>

    <div class="field <?= isset($errors['current']) ? 'has-error' : '' ?>">
      <label for="currentPassword">Current password</label>
      <div class="pw-input">
        <input type="password" id="currentPassword" name="currentPassword" required autocomplete="current-password">
        <button type="button" class="pw-toggle" data-target="currentPassword" data-label="current password" aria-label="Show current password" aria-pressed="false" title="Show / hide password"><svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
      </div>
      <p class="field-error"><?= e($errors['current'] ?? 'Enter your current password.') ?></p>
    </div>

    <div class="field <?= isset($errors['new']) ? 'has-error' : '' ?>">
      <label for="newPassword">New password</label>
      <div class="pw-input">
        <input type="password" id="newPassword" name="newPassword" required autocomplete="new-password" maxlength="72">
        <button type="button" class="pw-toggle" data-target="newPassword" data-label="new password" aria-label="Show new password" aria-pressed="false" title="Show / hide password"><svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
      </div>
      <div class="pw-meter" id="pwMeter" aria-hidden="true"><span></span></div>
      <p class="field-hint" id="pwHint">At least 8 characters, with letters and numbers.</p>
      <p class="field-error"><?= e($errors['new'] ?? 'Enter a valid new password.') ?></p>
    </div>

    <div class="field <?= isset($errors['confirm']) ? 'has-error' : '' ?>">
      <label for="confirmPassword">Confirm new password</label>
      <div class="pw-input">
        <input type="password" id="confirmPassword" name="confirmPassword" required autocomplete="new-password" maxlength="72">
        <button type="button" class="pw-toggle" data-target="confirmPassword" data-label="confirm password" aria-label="Show confirm password" aria-pressed="false" title="Show / hide password"><svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
      </div>
      <p class="field-error"><?= e($errors['confirm'] ?? 'The two passwords do not match.') ?></p>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" <?= $locked ? 'disabled' : '' ?>>Update password</button>
      <a href="<?= e($cancelUrl) ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<script>
/* CR-01 / FR-23 Change Password — client side helpers.
   The server (change-password.php) repeats every check, so this file
   only improves the experience: show/hide, strength meter, quick errors. */
(function () {
  "use strict";

  var form = document.getElementById("changePasswordForm");
  if (!form) return;

  var current = document.getElementById("currentPassword");
  var next    = document.getElementById("newPassword");
  var confirm = document.getElementById("confirmPassword");
  var meter   = document.getElementById("pwMeter");
  var hint    = document.getElementById("pwHint");
  var defaultHint = hint ? hint.textContent : "";

  /* ---------- Show / hide (eye toggle) ---------- */
  form.querySelectorAll(".pw-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = document.getElementById(btn.dataset.target);
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.classList.toggle("is-visible", show);
      btn.setAttribute("aria-pressed", show ? "true" : "false");
      btn.setAttribute("aria-label", (show ? "Hide " : "Show ") + btn.dataset.label);
      input.focus();
    });
  });

  /* ---------- Strength meter ---------- */
  function score(pw) {
    var s = 0;
    if (pw.length >= 8) s++;
    if (pw.length >= 12) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/[0-9]/.test(pw) && /[A-Za-z]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(4, Math.max(pw.length ? 1 : 0, s - (pw.length < 8 ? 1 : 0)));
  }

  next.addEventListener("input", function () {
    var s = score(next.value);
    var labels = ["", "Weak", "Fair", "Good", "Strong"];
    meter.className = "pw-meter" + (s ? " s" + s : "");
    hint.textContent = s ? "Strength: " + labels[s] : defaultHint;
  });

  /* ---------- Validation ---------- */
  function setError(input, message) {
    var field = input.closest(".field");
    field.classList.add("has-error");
    field.querySelector(".field-error").textContent = message;
  }

  function clearError(input) {
    input.closest(".field").classList.remove("has-error");
  }

  [current, next, confirm].forEach(function (input) {
    input.addEventListener("input", function () { clearError(input); });
  });

  form.addEventListener("submit", function (event) {
    var valid = true;
    [current, next, confirm].forEach(clearError);

    if (!current.value) {
      setError(current, "Enter your current password."); valid = false;
    }

    if (!next.value) {
      setError(next, "Enter a new password."); valid = false;
    } else if (next.value.length < 8) {
      setError(next, "Password must be at least 8 characters."); valid = false;
    } else if (!/[A-Za-z]/.test(next.value) || !/[0-9]/.test(next.value)) {
      setError(next, "Use at least one letter and one number."); valid = false;
    } else if (current.value && next.value === current.value) {
      setError(next, "New password must be different from your current password."); valid = false;
    }

    if (!confirm.value) {
      setError(confirm, "Confirm your new password."); valid = false;
    } else if (next.value && confirm.value !== next.value) {
      setError(confirm, "The two passwords do not match."); valid = false;
    }

    if (!valid) {
      event.preventDefault();
      var firstBad = form.querySelector(".field.has-error input");
      if (firstBad) firstBad.focus();
    }
  });
})();
</script>
<?php
}

/* ---------------- Administrator layout ---------------- */
if ($isAdmin) {
    $pageTitle   = 'Change password';
    $pageSub     = 'Update the password you use to log in';
    $pageActions = '<a href="admin-dashboard.php" class="btn btn-ghost">Back to dashboard</a>';
    $activeNav   = 'change-password.php';

    require __DIR__ . '/includes/admin-header.php';
    ?>
      <section class="admin-section">
        <div class="admin-panel cp-panel">
          <?php cp_render_form($errors, $formError, $changed, $locked, $cancelUrl); ?>
        </div>
      </section>

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
    <?php cp_render_form($errors, $formError, $changed, $locked, $cancelUrl); ?>
  </article>
</div>

<footer class="site-footer">
  Digital Notice Board Management System &middot; Meridian Institute
</footer>

<script src="script.js"></script>
</body>
</html>
