<?php
// Shows a pet photo or staff profile photo stored in the database.
// photo.php?pet=12   or   photo.php?user=3
require __DIR__ . '/includes/config.php';
require_login();

if (isset($_GET['pet'])) {
    $stmt = $pdo->prepare('SELECT photo FROM pets WHERE id = ?');
    $stmt->execute([(int)$_GET['pet']]);
} else {
    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([(int)($_GET['user'] ?? 0)]);
}
$dataUri = $stmt->fetchColumn();

if (!$dataUri || !preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $dataUri, $m)) {
    http_response_code(404);
    exit;
}

$etag = '"' . md5($dataUri) . '"';
header('Cache-Control: private, max-age=300');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $m[1]);
header('X-Content-Type-Options: nosniff');
echo base64_decode($m[2]);
