<?php
// POST { id, health_status: "healthy" | "monitoring" | "medical" }  ->  { ok: true, label }
require __DIR__ . '/../staff-common.php';
staff_api_start();
if (!can('staff_health')) staff_forbidden("change a pet's health status");

$in = json_input();
$status = (string)($in['health_status'] ?? '');
if (!isset(HEALTH_STATUSES[$status])) json_response(['ok' => false, 'error' => 'Unknown health status.'], 400);

$stmt = $pdo->prepare('UPDATE pets SET health_status = ? WHERE id = ? RETURNING name');
$stmt->execute([$status, (int)($in['id'] ?? 0)]);
$name = $stmt->fetchColumn();
if (!$name) json_response(['ok' => false, 'error' => 'That pet no longer exists. Refresh the page.'], 404);

log_activity('Health status changed', "$name: " . HEALTH_STATUSES[$status]);
json_response(['ok' => true, 'label' => HEALTH_STATUSES[$status]]);
