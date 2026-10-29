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
