/* ============================================================
   Digital Notice Board — admin panel UI helpers
   All data handling is done server-side in PHP. This file only
   covers the mobile drawer, confirm dialog, toast and the
   attachment preview.
   ============================================================ */

/* ---------------- Toast ---------------- */

function showToast(message){
  if (!message) return;
  let toast = document.getElementById("adminToast");
  if (!toast){
    toast = document.createElement("div");
    toast.id = "adminToast";
    toast.className = "admin-toast";
    toast.setAttribute("role", "status");
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove("show"), 2800);
}

/* ---------------- Confirm dialog ---------------- */

let confirmAction = null;

function initConfirmModal(){
  const modal = document.getElementById("confirmModal");
  if (!modal) return;

  const accept = modal.querySelector("#confirmAccept");
  const cancel = modal.querySelector("#confirmCancel");

  function close(){
    modal.classList.remove("open");
    confirmAction = null;
  }

  accept.addEventListener("click", () => {
    const run = confirmAction;
    close();
    if (run) run();
  });
  cancel.addEventListener("click", close);
  modal.addEventListener("click", e => { if (e.target === modal) close(); });
  document.addEventListener("keydown", e => {
    if (e.key === "Escape" && modal.classList.contains("open")) close();
  });
}

function askConfirm(title, message, label, onConfirm){
  const modal = document.getElementById("confirmModal");
  if (!modal){ onConfirm(); return; }
  modal.querySelector("#confirmTitle").textContent = title;
  modal.querySelector("#confirmMessage").textContent = message;
  modal.querySelector("#confirmAccept").textContent = label;
  confirmAction = onConfirm;
  modal.classList.add("open");
}

/* Any element carrying data-confirm asks first, then submits its form.
   Used for delete notice, deactivate user, discard changes and logout. */
function initConfirmTriggers(){
  document.querySelectorAll("[data-confirm]").forEach(el => {
    el.addEventListener("click", e => {
      e.preventDefault();
      askConfirm(
        el.dataset.confirmTitle || "Are you sure?",
        el.dataset.confirm,
        el.dataset.confirmLabel || "Confirm",
        () => {
          if (el.dataset.href){
            window.location.href = el.dataset.href;
          } else if (el.form){
            el.form.submit();
          } else {
            const form = el.closest("form");
            if (form) form.submit();
          }
        }
      );
    });
  });
}

/* ---------------- Sidebar drawer ---------------- */

function initAdminShell(){
  const sidebar = document.getElementById("adminSidebar");
  if (!sidebar) return;

  const toggle = document.getElementById("adminMenuToggle");
  const closeBtn = document.getElementById("adminSidebarClose");
  const overlay = document.getElementById("adminOverlay");

  function open(){
    sidebar.classList.add("open");
    overlay && overlay.classList.add("active");
    toggle && toggle.setAttribute("aria-expanded", "true");
  }
  function close(){
    sidebar.classList.remove("open");
    overlay && overlay.classList.remove("active");
    toggle && toggle.setAttribute("aria-expanded", "false");
  }

  toggle && toggle.addEventListener("click", open);
  closeBtn && closeBtn.addEventListener("click", close);
  overlay && overlay.addEventListener("click", close);

  const logout = document.getElementById("adminLogout");
  logout && logout.addEventListener("click", () => {
    askConfirm(
      "Log out?",
      "Your session will end and you will be returned to the login page.",
      "Log out",
      () => document.getElementById("logoutForm").submit()
    );
  });
}

/* ---------------- Attachment preview (client-side check only —
     PHP validates the file again on upload, see admin-notice-form.php) ---------------- */

function initUploadPreview(){
  const input = document.getElementById("noticeFile");
  const chip = document.getElementById("fileChip");
  const chipName = document.getElementById("fileChipName");
  if (!input || !chip) return;

  input.addEventListener("change", () => {
    const file = input.files[0];
    if (!file) return;

    const allowed = ["application/pdf", "image/png", "image/jpeg"];
    if (!allowed.includes(file.type)){
      showToast("Only PDF, PNG or JPG attachments are accepted.");
      input.value = "";
      return;
    }
    if (file.size > 5 * 1024 * 1024){
      showToast("Attachment must be 5 MB or smaller.");
      input.value = "";
      return;
    }

    chipName.textContent = file.name + " · " + Math.round(file.size / 1024) + " KB";
    chip.classList.add("visible");
  });
}

/* ---------------- Auto-submit filters ---------------- */

function initFilterForm(){
  const form = document.getElementById("filterForm");
  if (!form) return;
  form.querySelectorAll("select").forEach(sel => {
    sel.addEventListener("change", () => form.submit());
  });
}

/* ---------------- Responsive custom selects ---------------- */

function initCustomSelects(){
  const selects = document.querySelectorAll("[data-custom-select]");
  if (!selects.length) return;

  const positionMenu = select => {
    const menu = select.querySelector(".admin-select-menu");
    if (!menu) return;

    select.classList.remove("drop-up");
    menu.style.maxHeight = "";
    menu.style.width = "";
    menu.style.left = "";
    menu.style.right = "";

    const rect = select.getBoundingClientRect();
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
    const spaceBelow = viewportHeight - rect.bottom - 12;
    const spaceAbove = rect.top - 12;
    const preferredHeight = Math.min(menu.scrollHeight, 220);
    const openUp = !select.classList.contains("force-drop-down")
      && spaceBelow < preferredHeight
      && spaceAbove > spaceBelow;
    const availableHeight = Math.max(140, openUp ? spaceAbove : spaceBelow);

    select.classList.toggle("drop-up", openUp);
    menu.style.maxHeight = `${Math.min(preferredHeight, availableHeight)}px`;

    if (viewportWidth <= 620) {
      const gutter = 12;
      const leftOverflow = Math.max(gutter - rect.left, 0);
      const rightOverflow = Math.max(rect.right - (viewportWidth - gutter), 0);

      menu.style.width = `${Math.min(rect.width, viewportWidth - gutter * 2)}px`;
      if (leftOverflow) menu.style.left = `${leftOverflow}px`;
      if (rightOverflow) menu.style.left = `${-rightOverflow}px`;
      menu.style.right = "auto";
    }
  };

  const closeAll = except => {
    selects.forEach(select => {
      if (select === except) return;
      select.classList.remove("open");
      select.classList.remove("drop-up");
      select.querySelector(".admin-select-trigger")?.setAttribute("aria-expanded", "false");
    });
  };

  selects.forEach(select => {
    const trigger = select.querySelector(".admin-select-trigger");
    const input = select.querySelector("input[type='hidden']");
    const label = trigger?.querySelector("span");
    const options = select.querySelectorAll(".admin-select-menu button");
    const shouldAutoSubmit = select.dataset.autoSubmit !== "false";

    if (!trigger || !input || !label) return;

    trigger.addEventListener("click", () => {
      const willOpen = !select.classList.contains("open");
      closeAll(select);
      select.classList.toggle("open", willOpen);
      trigger.setAttribute("aria-expanded", String(willOpen));
      if (willOpen) positionMenu(select);
    });

    options.forEach(option => {
      option.addEventListener("click", () => {
        input.value = option.dataset.value || "";
        label.textContent = option.textContent.trim();
        options.forEach(item => item.classList.toggle("selected", item === option));
        closeAll();
        if (shouldAutoSubmit) input.form && input.form.submit();
      });
    });
  });

  document.addEventListener("click", event => {
    if (!event.target.closest("[data-custom-select]")) closeAll();
  });

  document.addEventListener("keydown", event => {
    if (event.key === "Escape") closeAll();
  });

  window.addEventListener("resize", () => {
    document.querySelectorAll("[data-custom-select].open").forEach(positionMenu);
  });
}

/* ---------------- Boot ---------------- */

document.addEventListener("DOMContentLoaded", () => {
  initConfirmModal();
  initConfirmTriggers();
  initAdminShell();
  initUploadPreview();
  initFilterForm();
  initCustomSelects();
  showToast(document.body.dataset.flash || "");

  const printBtn = document.getElementById("printReport");
  printBtn && printBtn.addEventListener("click", () => window.print());
});
