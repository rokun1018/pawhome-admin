<?php
// =====================================================================
//  includes/shared.php
//  Rules used by BOTH the admin panel and the staff panel, so a decision
//  works exactly the same no matter where it is made.
// =====================================================================

const APPLICATION_STATUSES = ['pending', 'review', 'approved', 'rejected'];
const BOOKING_STATUSES     = ['pending', 'confirmed', 'checked_in', 'completed', 'declined', 'cancelled'];
const HEALTH_STATUSES      = ['healthy' => 'Healthy', 'monitoring' => 'Monitoring', 'medical' => 'Medical Attention'];
const CARE_ACTIVITIES      = ['feeding' => 'Feeding', 'walk' => 'Walk', 'playtime' => 'Playtime',
                              'grooming' => 'Grooming', 'medication' => 'Medication', 'health' => 'Health Check'];

// Approve / reject / review an adoption application.
// Returns null when it worked, or an error message.
function set_application_status(int $id, string $status, ?string $notes = null): ?string
{
    global $pdo;
    if (!in_array($status, APPLICATION_STATUSES, true)) return 'Unknown status.';

    $stmt = $pdo->prepare('SELECT a.*, p.name AS pet_name FROM applications a LEFT JOIN pets p ON p.id = a.pet_id WHERE a.id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) return 'That application no longer exists. Refresh the page.';

    $decided = in_array($status, ['approved', 'rejected'], true);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE applications SET status = ?, decided_at = ?, decided_by = ?, admin_notes = COALESCE(?, admin_notes) WHERE id = ?')
        ->execute([$status, $decided ? date('c') : null, $decided ? current_user()['id'] : null, $notes, $id]);

    // Keep the pet's adoption status in step with the decision
    if ($app['pet_id']) {
        if ($status === 'approved') {
            $pdo->prepare("UPDATE pets SET status = 'adopted' WHERE id = ?")->execute([$app['pet_id']]);
        } elseif ($app['status'] === 'approved') {
            $pdo->prepare("UPDATE pets SET status = 'available' WHERE id = ? AND status = 'adopted'")->execute([$app['pet_id']]);
        }
    }
    $pdo->commit();

    $words = ['approved' => 'Adoption approved', 'rejected' => 'Application rejected',
              'review' => 'Application under review', 'pending' => 'Application set to pending'];
    log_activity($words[$status], $app['applicant_name'] . ($app['pet_name'] ? ' for ' . $app['pet_name'] : ''));
    return null;
}

// Is this kennel already taken on any of these nights? Returns the other pet's name, or null.
// Same-day turnover is fine: one pet can leave the morning another arrives.
function kennel_conflict(?string $kennel, string $checkIn, string $checkOut, int $ignoreId = 0): ?string
{
    global $pdo;
    if (!$kennel) return null;
    $stmt = $pdo->prepare("SELECT pet_name FROM boarding
                           WHERE kennel = ? AND id <> ? AND status IN ('pending','confirmed','checked_in')
                             AND daterange(check_in, GREATEST(check_out, check_in + 1))
                              && daterange(?::date, GREATEST(?::date, ?::date + 1))
                           LIMIT 1");
    $stmt->execute([$kennel, $ignoreId, $checkIn, $checkOut, $checkIn]);
    return $stmt->fetchColumn() ?: null;
}

// Confirm / decline (or any other status) for a boarding booking.
function set_booking_status(int $id, string $status): ?string
{
    global $pdo;
    if (!in_array($status, BOOKING_STATUSES, true)) return 'Unknown status.';

    $stmt = $pdo->prepare('SELECT * FROM boarding WHERE id = ?');
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) return 'That booking no longer exists. Refresh the page.';

    if (in_array($status, ['pending', 'confirmed', 'checked_in'], true)
        && ($other = kennel_conflict($b['kennel'], $b['check_in'], $b['check_out'], $id))) {
        return "Kennel {$b['kennel']} is already booked for $other on those dates. Change the kennel in the admin panel first.";
    }

    $pdo->prepare('UPDATE boarding SET status = ?, decided_by = ? WHERE id = ?')
        ->execute([$status, current_user()['id'], $id]);
    log_activity('Boarding ' . strtolower(label($status)), $b['pet_name'] . ', ' . fmt_date($b['check_in']));
    return null;
}

// Signs a person in (used by the admin login and the staff login).
function sign_in(array $user, bool $remember = false, string $where = ''): void
{
    global $pdo;
    session_regenerate_id(true);
    unset($user['password']);
    $_SESSION['user'] = $user;
    $_SESSION['last_seen'] = time();
    $_SESSION['remember'] = $remember;
    if ($remember) {
        // Keep the sign-in cookie for 30 days instead of until the browser closes
        setcookie(session_name(), session_id(), [
            'expires' => time() + REMEMBER_SECONDS, 'path' => '/',
            'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
    log_activity('Signed in', $where);
}

function health_label(string $status): string
{
    return HEALTH_STATUSES[$status] ?? label($status);
}
