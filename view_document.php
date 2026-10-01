<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();
$teamId = filter_var($_GET['team'] ?? null, FILTER_VALIDATE_INT);
if (!$teamId) {
    http_response_code(404);
    exit('Document not found.');
}
$query = db()->prepare('SELECT verification_file FROM teams WHERE id = ?');
$query->execute([$teamId]);
$filename = $query->fetchColumn();
if (!$filename || basename($filename) !== $filename) {
    http_response_code(404);
    exit('Document not found.');
}
$path = dirname(__DIR__) . '/khelmandu-private/verification/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(415);
    exit('Unsupported document type.');
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="team-document.' . ($mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg')) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);