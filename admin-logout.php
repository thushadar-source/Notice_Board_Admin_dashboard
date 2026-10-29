<?php
/* FR-06 Logout · AC-10 — terminate the session and return to the login page */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
}

$_SESSION = [];
session_destroy();
session_start();
set_flash('You have been logged out.');
redirect('admin-login.php');
