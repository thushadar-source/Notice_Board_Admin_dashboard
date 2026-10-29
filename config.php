<?php
/* ============================================================
   Digital Notice Board Management System
   Database connection + shared helpers
   ============================================================ */

/* ---------- XAMPP / MySQL settings ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'notice_board');
define('DB_USER', 'root');
define('DB_PASS', '');          // default XAMPP password is empty

/* ---------- Upload settings (NFR-04) ---------- */
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);              // 5 MB
define('ALLOWED_TYPES', ['application/pdf', 'image/png', 'image/jpeg']);
define('ALLOWED_EXT',   ['pdf', 'png', 'jpg', 'jpeg']);

/* ---------- Session ---------- */
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = __DIR__ . '/sessions';
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0775, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }
    session_start();
}

/* ---------- PDO connection ---------- */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please import database/notice_board.sql and check config.php. (' . $e->getMessage() . ')');
}

/* ============================================================
   Helpers
   ============================================================ */

/** Escape output (prevents XSS) */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Redirect and stop */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** Store a one-time message shown as a toast on the next page */
function set_flash($message)
{
    $_SESSION['flash'] = $message;
}

/** Read and clear the flash message */
function get_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $msg = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $msg;
}

/* ---------- Authentication / authorisation (NFR-02, NFR-03) ---------- */

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function is_admin()
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** Protect every admin page — BR-01, BR-02 */
function require_admin()
{
    if (!is_admin()) {
        set_flash('Please log in as an administrator to continue.');
        redirect('admin-login.php');
    }
}

/** Simple CSRF token for admin forms */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf()
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('Invalid request token. Please go back and try again.');
    }
}

/* ---------- Domain helpers ---------- */

/** Display status: a published notice past its expiry date shows as Expired (AC-06) */
function display_status(array $notice)
{
    if ($notice['status'] === 'published' && $notice['expiry_date'] < date('Y-m-d')) {
        return 'expired';
    }
    return $notice['status'];
}

function status_label($key)
{
    $labels = [
        'published'   => 'Published',
        'draft'       => 'Draft',
        'unpublished' => 'Unpublished',
        'expired'     => 'Expired',
    ];
    return $labels[$key] ?? ucfirst($key);
}

function status_badge(array $notice)
{
    $s = display_status($notice);
    return '<span class="badge badge-' . $s . '">' . status_label($s) . '</span>';
}

function format_date($date)
{
    if (!$date) {
        return '&mdash;';
    }
    return date('j M Y', strtotime($date));
}

function format_size($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1024) . ' KB';
}

/** All categories, keyed by id */
function get_categories(PDO $pdo)
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM categories ORDER BY category_id')->fetchAll();
    }
    return $cache;
}

/** Categories available for public UI and new notice assignment */
function get_visible_categories(PDO $pdo)
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM categories WHERE is_hidden = 0 ORDER BY category_id')->fetchAll();
    }
    return $cache;
}

function ensure_calendar_notes_table(PDO $pdo)
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS calendar_notes (
          calendar_note_id INT AUTO_INCREMENT PRIMARY KEY,
          note_date DATE NOT NULL,
          title VARCHAR(160) NOT NULL,
          note_text TEXT NULL,
          is_visible TINYINT(1) NOT NULL DEFAULT 1,
          created_by INT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_calendar_note_date (note_date),
          INDEX idx_calendar_visible (is_visible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ready = true;
}
