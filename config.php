<?php
/* ============================================================
   Digital Notice Board Management System
   Database connection + shared helpers
   ============================================================ */

/* ---------- Environment loader & helpers ---------- */
if (!function_exists('load_env')) {
    function load_env($path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Strip quotes if wrapped
                if ((strlen($value) >= 2) && (
                    ($value[0] === '"' && substr($value, -1) === '"') ||
                    ($value[0] === "'" && substr($value, -1) === "'")
                )) {
                    $value = substr($value, 1, -1);
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($val === null || $val === false) {
            return $default;
        }

        $lower = strtolower($val);
        if ($lower === 'true' || $lower === '(true)') return true;
        if ($lower === 'false' || $lower === '(false)') return false;
        if ($lower === 'null' || $lower === '(null)') return null;
        if ($lower === 'empty' || $lower === '(empty)') return '';

        return $val;
    }
}

// Load local .env if present
load_env(__DIR__ . '/.env');

/* ---------- XAMPP / MySQL settings ---------- */
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'notice_board'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

/* ---------- Upload settings (NFR-04) ---------- */
$maxUploadMb = (int)env('MAX_UPLOAD_SIZE_MB', 5);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', ($maxUploadMb > 0 ? $maxUploadMb : 5) * 1024 * 1024);
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
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please import database/notice_board.sql and check .env or config.php. (' . $e->getMessage() . ')');
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

/** Display status: a published notice with future publish date shows as Scheduled; past expiry shows as Expired */
function display_status(array $notice)
{
    $today = date('Y-m-d');
    if ($notice['status'] === 'published') {
        if (!empty($notice['publish_date']) && $notice['publish_date'] > $today) {
            return 'scheduled';
        }
        if (!empty($notice['expiry_date']) && $notice['expiry_date'] < $today) {
            return 'expired';
        }
    }
    return $notice['status'];
}

function status_label($key)
{
    $labels = [
        'published'   => 'Published',
        'scheduled'   => 'Scheduled',
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
