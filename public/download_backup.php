<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');


$raw      = $_GET['file'] ?? '';
$filename = basename($raw);

// Strict whitelist: only lumina_backup_YYYYMMDD_HHMMSS.sql
if (!preg_match('/^lumina_backup_\d{8}_\d{6}\.sql$/', $filename)) {
    http_response_code(400);
    exit('Invalid filename.');
}

$filepath = APP_ROOT . '/storage/backups/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('Backup not found.');
}

logAction('BACKUP_DOWNLOADED', null, $filename);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

readfile($filepath);
exit;
