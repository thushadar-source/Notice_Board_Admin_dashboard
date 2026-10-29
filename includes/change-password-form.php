<?php
/* Shared form markup for change-password.php (CR-01 / FR-23).
   Expects: $errors, $formError, $changed, $locked, $cancelUrl */
?>
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
        <button type="button" class="pw-toggle" data-target="currentPassword" aria-label="Show current password">Show</button>
      </div>
      <p class="field-error"><?= e($errors['current'] ?? 'Enter your current password.') ?></p>
    </div>

    <div class="field <?= isset($errors['new']) ? 'has-error' : '' ?>">
      <label for="newPassword">New password</label>
      <div class="pw-input">
        <input type="password" id="newPassword" name="newPassword" required autocomplete="new-password" maxlength="72">
        <button type="button" class="pw-toggle" data-target="newPassword" aria-label="Show new password">Show</button>
      </div>
      <div class="pw-meter" id="pwMeter" aria-hidden="true"><span></span></div>
      <p class="field-hint" id="pwHint">At least 8 characters, with letters and numbers.</p>
      <p class="field-error"><?= e($errors['new'] ?? 'Enter a valid new password.') ?></p>
    </div>

    <div class="field <?= isset($errors['confirm']) ? 'has-error' : '' ?>">
      <label for="confirmPassword">Confirm new password</label>
      <div class="pw-input">
        <input type="password" id="confirmPassword" name="confirmPassword" required autocomplete="new-password" maxlength="72">
        <button type="button" class="pw-toggle" data-target="confirmPassword" aria-label="Show confirm password">Show</button>
      </div>
      <p class="field-error"><?= e($errors['confirm'] ?? 'The two passwords do not match.') ?></p>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" <?= $locked ? 'disabled' : '' ?>>Update password</button>
      <a href="<?= e($cancelUrl) ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
