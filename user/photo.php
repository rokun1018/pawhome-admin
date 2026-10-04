<?php
// Public pet photos
require __DIR__ . '/../includes/config.php';
$stmt = $pdo->prepare("SELECT photo FROM pets WHERE id = ?");
$stmt->execute([(int)($_GET['pet'] ?? 0)]);
$dataUri = $stmt->fetchColumn();
if (!$dataUri || !preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $dataUri, $m)) {
    http_response_code(404);
    exit;
}
$etag = '"' . md5($dataUri) . '"';
header('Cache-Control: public, max-age=600');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
header('Content-Type: ' . $m[1]);
header('X-Content-Type-Options: nosniff');
echo base64_decode($m[2]);
