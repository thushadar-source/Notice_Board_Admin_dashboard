/* ============================================================
   Digital Notice Board - public UI helpers
   Data is rendered by PHP from MySQL; this file handles filtering,
   navigation and cosmetic auth validation only.
   ============================================================ */

const CATEGORY_LABELS = window.CATEGORY_LABELS || {};
const CALENDAR_NOTES = window.CALENDAR_NOTES || [];

function initThemeToggle(){
  const toggle = document.getElementById("themeToggle");
  const savedTheme = localStorage.getItem("noticeTheme");
  const initialTheme = savedTheme || "light";

  function applyTheme(theme){
    document.documentElement.dataset.theme = theme;
    if (toggle){
      const isDark = theme === "dark";
      toggle.setAttribute("aria-pressed", isDark ? "true" : "false");
      toggle.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
    }
  }

  applyTheme(initialTheme);

  if (toggle){
    toggle.addEventListener("click", () => {
      const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
      localStorage.setItem("noticeTheme", nextTheme);
      applyTheme(nextTheme);
    });
  }
}

/* ---------------- Board page ---------------- */

function initBoard(){
  const grid = document.getElementById("noticeGrid");
  const countEl = document.getElementById("boardCount");
  const headingEl = document.getElementById("boardHeading");
  const searchInput = document.getElementById("searchInput");
  const categoryButtons = document.querySelectorAll(".category-list button");
  const sidebar = document.getElementById("sidebar");
  const menuToggle = document.getElementById("menuToggle");
  const sidebarClose = document.getElementById("sidebarClose");
  const sidebarOverlay = document.getElementById("sidebarOverlay");
  const emptyState = document.getElementById("emptyState");

  if (!grid) return;

  const cards = Array.from(grid.querySelectorAll(".notice-card"));

  function openSidebar(){
    if (!sidebar) return;
    sidebar.classList.add("open");
    sidebarOverlay && sidebarOverlay.classList.add("active");
    menuToggle && menuToggle.setAttribute("aria-expanded", "true");
  }

  function closeSidebar(){
    if (!sidebar) return;
    sidebar.classList.remove("open");
    sidebarOverlay && sidebarOverlay.classList.remove("active");
    menuToggle && menuToggle.setAttribute("aria-expanded", "false");
  }

  menuToggle && menuToggle.addEventListener("click", openSidebar);
  sidebarClose && sidebarClose.addEventListener("click", closeSidebar);
  sidebarOverlay && sidebarOverlay.addEventListener("click", closeSidebar);

  let activeCategory = "all";
  let query = "";

  function render(){
    if (headingEl){
      headingEl.textContent = activeCategory === "all"
        ? "Current notices"
        : (CATEGORY_LABELS[activeCategory] || "Current notices");
    }

    let visibleCount = 0;
    cards.forEach(card => {
      const matchesCategory = activeCategory === "all" || card.dataset.category === activeCategory;
      const haystack = card.dataset.search || "";
      const matchesQuery = !query || haystack.includes(query);
      const visible = matchesCategory && matchesQuery;

      card.classList.toggle("is-hidden", !visible);
      if (visible) visibleCount += 1;
    });

    if (countEl){
      countEl.textContent = `${visibleCount} notice${visibleCount === 1 ? "" : "s"}`;
    }
    emptyState && emptyState.classList.toggle("is-hidden", visibleCount > 0);
  }

  cards.forEach(card => {
    function openCard(){
      if (card.dataset.href) window.location.href = card.dataset.href;
    }
    card.addEventListener("click", openCard);
    card.addEventListener("keypress", e => {
      if (e.key === "Enter") openCard();
    });
  });

  categoryButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      categoryButtons.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      activeCategory = btn.dataset.category;
      render();
      closeSidebar();
    });
  });

  if (searchInput){
    searchInput.addEventListener("input", e => {
      query = e.target.value.trim().toLowerCase();
      render();
    });
  }

  render();
}

function initCalendar(){
  const calendarTrigger = document.getElementById("calendarTrigger");
  const calendarPanel = document.getElementById("calendarPanel");
  const calendarMonth = document.getElementById("calendarMonth");
  const calendarGrid = document.getElementById("calendarGrid");
  const calendarNoteView = document.getElementById("calendarNoteView");
  const calendarPrev = document.getElementById("calendarPrev");
  const calendarNext = document.getElementById("calendarNext");

  if (!calendarTrigger || !calendarPanel) return;

  const notesByDate = CALENDAR_NOTES.reduce((map, note) => {
    if (!map[note.note_date]) map[note.note_date] = [];
    map[note.note_date].push(note);
    return map;
  }, {});
  const today = new Date();
  let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);

  function formatKey(date){
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function showCalendarNotes(dateKey){
    const notes = notesByDate[dateKey] || [];
    if (!calendarNoteView) return;

    if (!notes.length){
      calendarNoteView.innerHTML = '<p class="calendar-empty">No notes for this date.</p>';
      return;
    }

    calendarNoteView.innerHTML = notes.map(note => `
      <article class="calendar-note-card">
        <time datetime="${note.note_date}">${note.note_date}</time>
        <h3>${escapeHtml(note.title)}</h3>
        ${note.note_text ? `<p>${escapeHtml(note.note_text)}</p>` : ""}
      </article>
    `).join("");
  }

  function renderCalendar(){
    if (!calendarMonth || !calendarGrid) return;
    const year = visibleMonth.getFullYear();
    const month = visibleMonth.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    calendarMonth.textContent = visibleMonth.toLocaleDateString("en-US", {
      month: "long",
      year: "numeric"
    });

    calendarGrid.innerHTML = "";

    for (let i = 0; i < firstDay; i += 1){
      const spacer = document.createElement("span");
      spacer.className = "calendar-day empty";
      calendarGrid.appendChild(spacer);
    }

    for (let day = 1; day <= daysInMonth; day += 1){
      const date = new Date(year, month, day);
      const dateKey = formatKey(date);
      const notes = notesByDate[dateKey] || [];
      const button = document.createElement("button");
      button.type = "button";
      button.className = "calendar-day";
      button.textContent = day;
      button.setAttribute("aria-label", `${dateKey}${notes.length ? " has calendar note" : ""}`);

      if (dateKey === formatKey(today)) button.classList.add("today");
      if (notes.length){
        button.classList.add("has-note");
        button.addEventListener("click", () => {
          calendarGrid.querySelectorAll(".calendar-day.selected").forEach(item => item.classList.remove("selected"));
          button.classList.add("selected");
          showCalendarNotes(dateKey);
        });
      }

      calendarGrid.appendChild(button);
    }

    if (calendarNoteView){
      calendarNoteView.innerHTML = CALENDAR_NOTES.length
        ? '<p class="calendar-empty">Click a marked date to view notes.</p>'
        : '<p class="calendar-empty">No calendar notes yet.</p>';
    }
  }

  calendarTrigger.addEventListener("click", () => {
    const isOpen = calendarPanel.classList.toggle("open");
    calendarTrigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
    if (isOpen) renderCalendar();
  });

  calendarPrev && calendarPrev.addEventListener("click", () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
    renderCalendar();
  });

  calendarNext && calendarNext.addEventListener("click", () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
    renderCalendar();
  });

  document.addEventListener("click", event => {
    if (!event.target.closest(".board-calendar")) {
      calendarPanel.classList.remove("open");
      calendarTrigger.setAttribute("aria-expanded", "false");
    }
  });

  renderCalendar();
}

function escapeHtml(value){
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/* ---------------- Auth forms (cosmetic validation only) ---------------- */

function initAuthForm(formId){
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", e => {
    e.preventDefault();
    let valid = true;

    form.querySelectorAll("[required]").forEach(field => {
      const wrapper = field.closest(".field");
      if (!field.value.trim()){
        wrapper.classList.add("has-error");
        valid = false;
      } else {
        wrapper.classList.remove("has-error");
      }
    });

    const banner = form.querySelector(".form-banner");
    if (banner) banner.classList.toggle("visible", !valid);

    if (valid){
      window.alert("This sign-in page is a UI preview. Use the admin login page for notice management.");
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initThemeToggle();
  initBoard();
  initCalendar();
  initAuthForm("loginForm");
  initAuthForm("registerForm");
});
