<?php
// POST { id, status: "confirmed" | "declined" }  ->  { ok: true }
require __DIR__ . '/../staff-common.php';
staff_api_start();
if (!can('staff_boarding')) staff_forbidden('confirm or decline boarding');

$in = json_input();
$status = $in['status'] ?? '';
if (!in_array($status, ['confirmed', 'declined'], true)) json_response(['ok' => false, 'error' => 'Unknown decision.'], 400);

// Only open requests can be decided here; the admin panel can change anything
$stmt = $pdo->prepare('SELECT status FROM boarding WHERE id = ?');
$stmt->execute([(int)($in['id'] ?? 0)]);
$current = $stmt->fetchColumn();
if ($current === false) json_response(['ok' => false, 'error' => 'That booking no longer exists. Refresh the page.'], 404);
if ($current !== 'pending') json_response(['ok' => false, 'error' => 'Someone already decided this request. Refresh the page.'], 409);

// Same rule as the admin panel: a kennel can't be double-booked
if ($error = set_booking_status((int)$in['id'], $status)) json_response(['ok' => false, 'error' => $error], 400);
json_response(['ok' => true]);
