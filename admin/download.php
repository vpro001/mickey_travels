<?php
require '../includes/init.php';
require '../includes/admin.php';

// Staff-only download of an uploaded visa document (the files themselves are not public)
$doc = db_one("SELECT * FROM visa_documents WHERE id = ?", [(int) get('id')]);
$path = $doc ? __DIR__ . '/../storage/uploads/visa/' . basename($doc['stored_name']) : '';
if (!$doc || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

db_insert('audit_log', ['user_id' => $_SESSION['staff']['id'], 'action' => 'download', 'entity_type' => 'visa_document',
    'entity_id' => $doc['id'], 'details' => json_encode(['application' => $doc['application_id']]), 'ip' => $_SERVER['REMOTE_ADDR']]);

header('Content-Type: ' . $doc['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $doc['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
