<?php
/* FR-18 Download Attachment — serves a stored file to the browser.
   Users may only download attachments of published, non-expired notices;
   administrators may download any attachment. */
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT a.*, n.status, n.expiry_date
       FROM attachments a
       JOIN notices n ON n.notice_id = a.notice_id
      WHERE a.attachment_id = ?'
);
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('Attachment not found.');
}

$isLive = $file['status'] === 'published' && $file['expiry_date'] >= date('Y-m-d');
if (!$isLive && !is_admin()) {                    // BR-04
    http_response_code(403);
    die('This attachment is not available.');
}

$path = UPLOAD_DIR . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    die('The stored file is missing.');
}

header('Content-Type: ' . $file['file_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
