<?php
// POST { time: "HH:MM", pet: <pet id>, caretaker: <user id>, activity, notes }
//   ->  { ok: true, id, pet_name, caretaker_name, last_checkup? }
require __DIR__ . '/../staff-common.php';
$user = staff_api_start();

$in = json_input();
$time = (string)($in['time'] ?? '');
$petId = (int)($in['pet'] ?? 0);
$caretakerId = (int)($in['caretaker'] ?? 0);
$activity = (string)($in['activity'] ?? '');
$notes = trim((string)($in['notes'] ?? ''));

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) json_response(['ok' => false, 'error' => 'Enter a valid time.'], 400);
if (!isset(CARE_ACTIVITIES[$activity])) json_response(['ok' => false, 'error' => 'Choose an activity.'], 400);
if ($notes === '' || mb_strlen($notes) > 200) json_response(['ok' => false, 'error' => 'Add a note of up to 200 characters.'], 400);

$pet = $pdo->prepare("SELECT name FROM pets WHERE id = ? AND status <> 'adopted'");
$pet->execute([$petId]);
$petName = $pet->fetchColumn();
if (!$petName) json_response(['ok' => false, 'error' => 'That pet is no longer in our care. Refresh the page.'], 400);

$who = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND status = 'active'");
$who->execute([$caretakerId]);
$caretakerName = $who->fetchColumn();
if (!$caretakerName) json_response(['ok' => false, 'error' => 'Choose a caretaker from the list.'], 400);

$stmt = $pdo->prepare('INSERT INTO care_logs (pet_id, caretaker_id, activity, notes, log_date, log_time, created_by)
                       VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?) RETURNING id');
$stmt->execute([$petId, $caretakerId, $activity, $notes, $time, $user['id']]);
$id = (int)$stmt->fetchColumn();

$reply = ['ok' => true, 'id' => $id, 'pet_name' => $petName, 'caretaker_name' => $caretakerName];

// A health check counts as the pet's latest checkup
if ($activity === 'health') {
    $pdo->prepare('UPDATE pets SET last_checkup = CURRENT_DATE WHERE id = ?')->execute([$petId]);
    $reply['last_checkup'] = date('F j, Y');
}
log_activity('Care log added', CARE_ACTIVITIES[$activity] . ' for ' . $petName);
json_response($reply);
