<?php
/* Shared admin layout — expects $pageTitle, $pageSub, optional $pageActions, $activeNav */
if (!defined('DB_NAME')) {
    require_once __DIR__ . '/../config.php';
}
require_admin();

$activeNav   = $activeNav   ?? basename($_SERVER['PHP_SELF']);
$pageTitle   = $pageTitle   ?? 'Dashboard';
$pageSub     = $pageSub     ?? '';
$pageActions = $pageActions ?? '';

$navItems = [
    ['admin-dashboard.php',   'Dashboard',      'dash'],
    ['admin-notices.php',     'Manage notices', 'notices'],
    ['admin-notice-form.php', 'Add notice',     'add'],
    ['admin-calendar.php',    'Calendar',       'calendar'],
    ['admin-categories.php',  'Categories',     'categories'],
    ['admin-users.php',       'Users',          'users'],
    ['admin-reports.php',     'Reports',        'reports'],
];

$icons = [
 'dash'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
 'notices' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="13" y2="16"/></svg>',
 'add'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
 'calendar'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
 'categories' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h7v7H4z"/><path d="M13 4h7v7h-7z"/><path d="M4 13h7v7H4z"/><path d="M13 13h7v7h-7z"/></svg>',
 'users'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/></svg>',
 'reports' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="13"/><line x1="12" y1="20" x2="12" y2="6"/><line x1="18" y1="20" x2="18" y2="10"/></svg>',
 'board'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
 'logout'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
 'close'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
 'menu'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
];

/* Initials for the sidebar avatar */
$adminName = $_SESSION['full_name'] ?? 'Administrator';
$parts = preg_split('/\s+/', trim($adminName));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Admin | Meridian Notice Board</title>
<link rel="stylesheet" href="style.css">
</head>
<body data-flash="<?= e(get_flash()) ?>">

<div class="admin-overlay" id="adminOverlay"></div>

<div class="admin-body">

<aside class="admin-sidebar" id="adminSidebar">

  <div class="admin-sidebar-head">
    <a href="admin-dashboard.php" class="brand">
      <span class="brand-logo-chip">
        <img src="images/Logo.png" alt="Meridian Notice Board" class="brand-logo">
      </span>
    </a>
    <button type="button" class="admin-sidebar-close" id="adminSidebarClose" aria-label="Close admin menu">
      <?= $icons['close'] ?>
    </button>
  </div>

  <nav>
    <p class="admin-nav-label">Administration</p>
    <ul class="admin-nav">
      <?php foreach ($navItems as [$href, $label, $icon]): ?>
      <li>
        <a href="<?= $href ?>" class="<?= $activeNav === $href ? 'active' : '' ?>">
          <?= $icons[$icon] ?><?= $label ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <nav>
    <p class="admin-nav-label">Public site</p>
    <ul class="admin-nav">
      <li><a href="index.php"><?= $icons['board'] ?>View notice board</a></li>
    </ul>
  </nav>

  <div class="admin-sidebar-foot">
    <div class="admin-user-chip">
      <span class="admin-avatar"><?= e($initials) ?></span>
      <div>
        <strong><?= e($adminName) ?></strong>
        <span>Administrator</span>
      </div>
    </div>
    <form method="post" action="admin-logout.php" id="logoutForm">
      <?= csrf_field() ?>
      <button type="button" class="btn-logout" id="adminLogout">
        <?= $icons['logout'] ?>Log out
      </button>
    </form>
  </div>

</aside>

  <div class="admin-main">

    <header class="admin-topbar">
      <button type="button" class="admin-menu-toggle" id="adminMenuToggle" aria-label="Open admin menu" aria-expanded="false">
        <?= $icons['menu'] ?>
      </button>
      <div class="admin-topbar-title">
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($pageSub) ?></p>
      </div>
      <div class="admin-topbar-actions">
        <?= $pageActions ?>
      </div>
    </header>

    <div class="admin-content">
