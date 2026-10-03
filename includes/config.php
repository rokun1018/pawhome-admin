<?php
// =====================================================================
//  includes/config.php
//  Database connection, sign-in sessions, permissions and small helpers.
//  Every page starts with:  require __DIR__ . '/includes/config.php';
// =====================================================================

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dhaka');

// ---------- 1. Database ------------------------------------------------
// The connection details come from environment variables that you set on
// Render (or your computer). Never type your real password into this file.
try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            getenv('DB_HOST'), getenv('DB_PORT') ?: '5432',
            getenv('DB_NAME') ?: 'postgres', getenv('DB_SSLMODE') ?: 'require'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Could not connect to the database. Check the DB_ settings on your host.');
}

// ---------- 2. Sessions stored in the database --------------------------
// Free hosts restart often and wipe their disk. Keeping sessions in the
// database means staff stay signed in, and "Keep me signed in" works.
class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo) {}
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    public function write($id, $data): bool
    {
        $userId = $_SESSION['user']['id'] ?? null;
        if ($data === '' && !$userId) {
            return true; // don't store empty sessions for visitors
        }
        $this->pdo->prepare(
            'INSERT INTO sessions (id, user_id, data, updated_at) VALUES (?, ?, ?, NOW())
             ON CONFLICT (id) DO UPDATE SET user_id = EXCLUDED.user_id, data = EXCLUDED.data, updated_at = NOW()'
        )->execute([$id, $userId, $data]);
        return true;
    }

    public function destroy($id): bool
    {
        $this->pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc($maxLifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE updated_at < NOW() - (? * INTERVAL '1 second')");
        $stmt->execute([$maxLifetime]);
        return $stmt->rowCount();
    }
}

const REMEMBER_SECONDS = 60 * 60 * 24 * 30;   // "Keep me signed in" lasts 30 days
const IDLE_SECONDS     = 60 * 60 * 8;         // otherwise, sign out after 8 idle hours

ini_set('session.gc_maxlifetime', (string)REMEMBER_SECONDS);
session_set_save_handler(new DbSessionHandler($pdo), true);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// ---------- 3. Roles and what each one can open ------------------------
const ROLES = [
    'super_admin'           => 'Super Admin',
    'shelter_manager'       => 'Shelter Manager',
    'veterinarian'          => 'Veterinarian',
    'volunteer_coordinator' => 'Volunteer Coordinator',
    'volunteer'             => 'Volunteer',
];

// Change these lists to change who can see what.
const ACCESS = [
    'pets'         => ['super_admin', 'shelter_manager', 'veterinarian', 'volunteer_coordinator', 'volunteer'],
    'adoptions'    => ['super_admin', 'shelter_manager'],
    'boarding'     => ['super_admin', 'shelter_manager', 'volunteer_coordinator', 'volunteer'],
    'quiz'         => ['super_admin', 'shelter_manager'],
    'reports'      => ['super_admin', 'shelter_manager'],
    'users'        => ['super_admin'],
    'delete'       => ['super_admin', 'shelter_manager'],   // deleting pets and bookings
    'organisation' => ['super_admin'],
];

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function can(string $area): bool
{
    $user = current_user();
    return $user && in_array($user['role'], ACCESS[$area] ?? [], true);
}

// Put at the top of every private page. Re-reads the account each time, so
// a role change or deactivation takes effect straight away.
function require_login(): array
{
    global $pdo;
    $user = current_user();

    if ($user && empty($_SESSION['remember']) && time() - ($_SESSION['last_seen'] ?? 0) > IDLE_SECONDS) {
        $user = null; // idle too long
    }
    if ($user) {
        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role, status, last_active,
                                      (avatar IS NOT NULL) AS has_avatar
                               FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    }
    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['user'] = $user;
    $_SESSION['last_seen'] = time();
    if (!$user['last_active'] || strtotime($user['last_active']) < time() - 300) {
        $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$user['id']]);
    }
    return $user;
}

function require_access(string $area): array
{
    $user = require_login();
    if (!can($area)) {
        flash("Your role doesn't include that page. Ask a Super Admin if you need access.", 'error');
        header('Location: index.php');
        exit;
    }
    return $user;
}

// ---------- 4. Security helpers ----------------------------------------
function e($text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        die('This form expired. Go back, refresh the page and try again.');
    }
}

// ---------- 5. Messages, redirects, logging ----------------------------
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function log_activity(string $action, string $details = ''): void
{
    global $pdo;
    $user = current_user();
    $pdo->prepare('INSERT INTO activity_log (user_id, user_name, action, details) VALUES (?, ?, ?, ?)')
        ->execute([$user['id'] ?? null, $user['full_name'] ?? 'System', $action, $details]);
}

function settings(): array
{
    global $pdo;
    static $settings = null;
    return $settings ??= $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
}

function kennel_list(): array
{
    return array_values(array_filter(array_map('trim', explode(',', settings()['kennels']))));
}

// ---------- 6. Formatting helpers --------------------------------------
function fmt_date($value): string
{
    return $value ? (new DateTime($value))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('j M Y') : '';
}

function time_ago($value, string $justNow = 'just now'): string
{
    if (!$value) return 'Never';
    $seconds = time() - strtotime($value);
    if ($seconds < 120)    return $justNow;
    if ($seconds < 3600)   return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400)  return floor($seconds / 3600) . ' hour' . ($seconds < 7200 ? '' : 's') . ' ago';
    if ($seconds < 172800) return 'yesterday';
    if ($seconds < 604800) return floor($seconds / 86400) . ' days ago';
    return fmt_date($value);
}

function age_label(int $months): string
{
    if ($months < 12) return $months . ' mo';
    $years = intdiv($months, 12);
    return $years . ($years === 1 ? ' yr' : ' yrs');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '');
    return mb_strtoupper($letters);
}

function tint(int $id): string
{
    return ['tint-green', 'tint-honey', 'tint-blue', 'tint-plum'][$id % 4];
}

function label(string $value): string
{
    $special = ['review' => 'Under review', 'medical' => 'Medical care', 'checked_in' => 'Checked in', 'all' => 'All pets'];
    return $special[$value] ?? ROLES[$value] ?? ucwords(str_replace('_', ' ', $value));
}

const CATEGORIES = [
    'living_situation' => 'Living Situation',
    'time_commitment'  => 'Time Commitment',
    'home_environment' => 'Home Environment',
    'pet_experience'   => 'Pet Experience',
    'pet_care'         => 'Pet Care',
    'family_situation' => 'Family Situation',
];
// Downloadable reports: key => [name, format]
const REPORTS = [
    'adoptions'      => ['Adoptions', 'PDF'],
    'pets'           => ['Pet intake log', 'CSV'],
    'quiz'           => ['Quiz pass rates', 'CSV'],
    'boarding'       => ['Boarding summary', 'PDF'],
    'staff_activity' => ['Staff activity', 'CSV'],
    'applications'   => ['Adoption applications', 'CSV'],
    'summary'        => ['Shelter summary', 'PDF'],
];

const PET_TYPES = ['all' => 'All pets', 'dog' => 'Dogs', 'cat' => 'Cats', 'rabbit' => 'Rabbits', 'bird' => 'Birds'];

// Turns an uploaded image into text we can store in the database.
// Returns null if nothing was uploaded, or false if the file isn't allowed.
function uploaded_image(string $field, int $maxBytes): string|null|false
{
    $file = $_FILES[$field] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > $maxBytes) return false;
    $type = mime_content_type($file['tmp_name']);
    if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return false;
    return 'data:' . $type . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
}

// ---------- 7. Email (optional, used by "Forgot password") --------------
// Uses Brevo's free email API. Set BREVO_API_KEY and MAIL_FROM on Render.
function send_email(string $to, string $subject, string $html): bool
{
    $key  = getenv('BREVO_API_KEY');
    $from = getenv('MAIL_FROM');
    if (!$key || !$from) {
        error_log('Email not sent: BREVO_API_KEY or MAIL_FROM is not set.');
        return false;
    }
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'sender'      => ['email' => $from, 'name' => settings()['org_name']],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]),
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

function base_url(): string
{
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

require __DIR__ . '/icons.php';
