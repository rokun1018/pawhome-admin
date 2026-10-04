<?php
// =====================================================================
//  user/user-common.php
//  Loaded by every page of the public website. Uses the SAME database as
//  the admin and staff panels, but adopters have their own accounts
//  (the "adopters" table), so they can never open the staff side.
// =====================================================================
require __DIR__ . '/../includes/config.php';

// ---------- Adopter accounts ------------------------------------------
function current_adopter(): ?array
{
    global $pdo;
    static $cache = false;
    if ($cache !== false) return $cache;

    $id = $_SESSION['adopter_id'] ?? null;
    if ($id && empty($_SESSION['adopter_remember']) && time() - ($_SESSION['adopter_seen'] ?? 0) > IDLE_SECONDS) {
        $id = null; // idle too long
    }
    $adopter = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM adopters WHERE id = ?');
        $stmt->execute([$id]);
        $adopter = $stmt->fetch() ?: null;
    }
    if (!$adopter || $adopter['status'] !== 'active') {
        unset($_SESSION['adopter_id'], $_SESSION['adopter_seen'], $_SESSION['adopter_remember']);
        return $cache = null;
    }
    $_SESSION['adopter_seen'] = time();
    if (!$adopter['last_active'] || strtotime($adopter['last_active']) < time() - 300) {
        $pdo->prepare('UPDATE adopters SET last_active = NOW() WHERE id = ?')->execute([$adopter['id']]);
    }
    return $cache = $adopter;
}

// Pages that need an account send visitors to log in, then bring them back
function require_adopter(): array
{
    $adopter = current_adopter();
    if (!$adopter) {
        if (is_api_request()) json_response(['ok' => false, 'error' => 'Please log in again.'], 401);
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
    return $adopter;
}

function adopter_sign_in(int $id, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['adopter_id'] = $id;
    $_SESSION['adopter_seen'] = time();
    $_SESSION['adopter_remember'] = $remember;
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + REMEMBER_SECONDS, 'path' => '/',
            'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    global $pdo;
    $pdo->prepare('UPDATE adopters SET last_active = NOW() WHERE id = ?')->execute([$id]);
}

// Only allow "come back to" links inside this website
function safe_next(?string $next, string $fallback = 'index.php'): string
{
    $next = (string)$next;
    if ($next === '' || !preg_match('#^(/user/)?[a-z0-9\-]+\.php(\?[^\s]*)?$#i', $next)) return $fallback;
    return $next;
}

// ---------- Messages shown once after a redirect ----------------------
function user_flash(string $message, string $type = 'success'): void
{
    $_SESSION['user_flash'][] = ['message' => $message, 'type' => $type];
}

function user_flash_html(): string
{
    $html = '';
    foreach ($_SESSION['user_flash'] ?? [] as $f) {
        $html .= '<div class="' . ($f['type'] === 'error' ? 'flash-error' : 'flash-success') . '" role="status">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['user_flash']);
    return $html ? '<div class="flash-wrap">' . $html . '</div>' : '';
}

// ---------- Money and pet helpers --------------------------------------
function money($amount): string
{
    $sign = settings()['currency'] ?? '৳';
    $amount = (float)$amount;
    return $sign . number_format($amount, fmod($amount, 1) == 0 ? 0 : 2);
}

function pet_age_label(int $months): string
{
    if ($months < 12) return $months . ($months === 1 ? ' Month' : ' Months');
    $years = intdiv($months, 12);
    return $years . ($years === 1 ? ' Year' : ' Years');
}

function pet_traits(?string $traits): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$traits))));
}

// Uploaded photo, or a friendly drawing when there isn't one
function pet_image(array $pet): string
{
    if (!empty($pet['has_photo'])) return 'photo.php?pet=' . (int)$pet['id'];
    $emoji = ['dog' => '🐕', 'cat' => '🐈', 'rabbit' => '🐇', 'bird' => '🐦'][$pet['species']] ?? '🐾';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#e6f4f1"/>'
         . '<text x="200" y="185" font-size="120" text-anchor="middle">' . $emoji . '</text></svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

const PUBLIC_PET_COLUMNS = "p.id, p.name, p.species, p.breed, p.age_months, p.gender, p.size, p.status, p.description,
    p.traits, p.adoption_fee, p.health_notes, (p.photo IS NOT NULL) AS has_photo, s.name AS shelter_name";

// The same shape the design's JavaScript used for its sample pets
function pet_for_js(array $p): array
{
    return [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'breed'       => $p['breed'] ?: label($p['species']),
        'species'     => $p['species'],
        'age'         => round($p['age_months'] / 12, 1),
        'ageLabel'    => pet_age_label((int)$p['age_months']),
        'size'        => $p['size'],
        'sizeLabel'   => label($p['size']),
        'image'       => pet_image($p),
        'traits'      => pet_traits($p['traits']),
        'description' => $p['description'] ?: '',
    ];
}

// ---------- Header shared by the pages ----------------------------------
function user_header(string $active = ''): void
{
    global $pdo;
    $a = current_adopter();
    $links = ['index.php' => 'Dashboard', 'adoption-application.php' => 'Apply to Adopt', 'boarding.php' => 'Boarding'];
    if ($a) $links['profile.php'] = 'My Profile';

    // Red dot on the bell when an application was decided in the last 7 days
    $news = 0;
    if ($a) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE adopter_id = ? AND status IN ('approved','rejected') AND decided_at > NOW() - INTERVAL '7 days'");
        $stmt->execute([$a['id']]);
        $news = (int)$stmt->fetchColumn();
    }
    ?>
    <header class="header">
        <div class="header-left">
            <a class="logo" href="index.php" style="text-decoration:none">
                <span class="logo-icon">🐾</span>
                <span class="logo-text">PawHome</span>
            </a>
        </div>
        <nav class="nav">
            <?php foreach ($links as $href => $text): ?>
                <a href="<?= $href ?>" class="nav-link<?= $active === $href ? ' active' : '' ?>"><?= $text ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <a class="notification-btn" href="<?= $a ? 'profile.php?tab=applications' : 'login.php' ?>" title="<?= $news ? 'Your application has an update' : 'My applications' ?>" aria-label="Notifications">🔔<?php if ($news): ?><span class="notif-dot"></span><?php endif; ?></a>
            <div class="profile-dropdown-container">
                <div class="profile-avatar<?= $a ? ' initials' : '' ?>" id="profileAvatarHeader" onclick="toggleProfileDropdown()" role="button" tabindex="0" aria-label="Account menu"><?= $a ? e(initials($a['full_name'])) : '👤' ?></div>
                <div class="profile-dropdown" id="profileDropdown" style="display: none;">
                    <div class="dropdown-header"><?= $a ? e($a['full_name']) : 'Guest' ?></div>
                    <p class="dropdown-email"><?= $a ? e($a['email']) : 'Not logged in' ?></p>
                    <div class="dropdown-divider"></div>
                    <?php if ($a): ?>
                        <a href="profile.php" class="dropdown-item">👤 My Profile</a>
                        <a href="profile.php?tab=applications" class="dropdown-item">📝 My Applications</a>
                        <a href="logout.php" class="dropdown-item">🚪 Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="dropdown-item">🔐 Login</a>
                        <a href="signup.php" class="dropdown-item">📝 Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php
}

function user_head(string $title, array $css): void
{ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>PawHome - <?= e($title) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐾</text></svg>">
    <?php foreach ($css as $file): ?><link rel="stylesheet" href="css/<?= $file ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="css/common.css">
</head>
<?php }

// Small script every page uses for the profile menu
function user_scripts(array $files = []): void
{ ?>
    <script src="js/navigation.js"></script>
    <script src="js/user-header.js"></script>
    <?php foreach ($files as $f): ?><script src="js/<?= $f ?>"></script><?php endforeach;
}
