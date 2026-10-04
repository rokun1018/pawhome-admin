<?php
// POST { id, status: "approved" | "rejected" }  ->  { ok: true }
require __DIR__ . '/../staff-common.php';
staff_api_start();
if (!can('staff_applications')) staff_forbidden('approve or reject applications');

$in = json_input();
$status = $in['status'] ?? '';
if (!in_array($status, ['approved', 'rejected'], true)) json_response(['ok' => false, 'error' => 'Unknown decision.'], 400);

// Only open applications can be decided here; the admin panel can change anything
$stmt = $pdo->prepare('SELECT status FROM applications WHERE id = ?');
$stmt->execute([(int)($in['id'] ?? 0)]);
$current = $stmt->fetchColumn();
if ($current === false) json_response(['ok' => false, 'error' => 'That application no longer exists. Refresh the page.'], 404);
if (!in_array($current, ['pending', 'review'], true)) json_response(['ok' => false, 'error' => 'Someone already decided this application. Refresh the page.'], 409);

// Same rule as the admin panel: approving also marks the pet as adopted
if ($error = set_application_status((int)($in['id'] ?? 0), $status)) json_response(['ok' => false, 'error' => $error], 400);
json_response(['ok' => true]);
