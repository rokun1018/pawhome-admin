<?php
// =====================================================================
//  staff/staff-common.php
//  Loaded by every staff panel page and api/ file. Uses the SAME
//  database, accounts and rules as the admin panel (includes/).
// =====================================================================
require __DIR__ . '/../includes/config.php';

// For files in staff/api/: must be signed in, request must be genuine. Returns the user.
function staff_api_start(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    $user = require_login('staff');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') check_csrf_json();
    return $user;
}

function check_csrf_json(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_response(['ok' => false, 'error' => 'This page expired. Refresh it and try again.'], 400);
    }
}

// script.js sends JSON, not a normal form
function json_input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function staff_forbidden(string $what): never
{
    json_response(['ok' => false, 'error' => "Your role can't $what. Ask a Shelter Manager."], 403);
}

// ---------- Performance report ----------------------------------------
// Same shape script.js expects: { kpis: {...}, staff: [[name, role, ...], ...] }
function staff_report(string $period): array
{
    global $pdo;
    $today = new DateTimeImmutable('today');
    $firstThis = $today->modify('first day of this month');

    // [from (inclusive), to (exclusive)] for the period and the one before it
    switch ($period) {
        case 'last-month':
            $from = $firstThis->modify('-1 month'); $to = $firstThis;
            $prevFrom = $from->modify('-1 month'); $prevTo = $from;
            $name = 'last month'; $vs = 'vs previous month';
            break;
        case 'last-3-months':
            $from = $today->modify('-3 months')->modify('+1 day'); $to = $today->modify('+1 day');
            $prevFrom = $from->modify('-3 months'); $prevTo = $from;
            $name = 'last 3 months'; $vs = 'vs previous 3 months';
            break;
        default:
            $from = $firstThis; $to = $today->modify('+1 day');
            $prevFrom = $firstThis->modify('-1 month'); $prevTo = $firstThis;
            $name = 'this month'; $vs = 'vs last month';
    }

    $kennels = max(1, count(kennel_list()));
    $figures = function (DateTimeImmutable $a, DateTimeImmutable $b) use ($pdo, $kennels): array {
        $stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM applications WHERE status = 'approved' AND decided_at >= :a1 AND decided_at < :b1) AS adoptions,
            (SELECT AVG(quiz_score) FROM applications WHERE created_at >= :a2 AND created_at < :b2) AS match,
            (SELECT AVG(EXTRACT(EPOCH FROM decided_at - created_at) / 86400) FROM applications
              WHERE decided_at >= :a3 AND decided_at < :b3) AS review_days,
            (SELECT COALESCE(SUM(GREATEST(0, LEAST(check_out, :b4::date) - GREATEST(check_in, :a4::date))), 0) FROM boarding
              WHERE status IN ('confirmed','checked_in','completed') AND check_in < :b5::date AND check_out > :a5::date) AS nights");
        $p = [];
        foreach ([1, 2, 3, 4, 5] as $i) { $p["a$i"] = $a->format('Y-m-d'); $p["b$i"] = $b->format('Y-m-d'); }
        $stmt->execute($p);
        $r = $stmt->fetch();
        $days = max(1, $a->diff($b)->days);
        return [
            'adoptions'   => (int)$r['adoptions'],
            'match'       => $r['match'] !== null ? (float)$r['match'] : null,
            'occupancy'   => min(100, $r['nights'] / ($kennels * $days) * 100),
            'review_days' => $r['review_days'] !== null ? (float)$r['review_days'] : null,
        ];
    };
    $now = $figures($from, $to);
    $before = $figures($prevFrom, $prevTo);

    // "▲ 3 vs last month" style text. $higherIsGood decides the colour.
    $change = function ($a, $b, string $unit, bool $higherIsGood, int $decimals = 0) use ($vs): array {
        if ($a === null || $b === null) return ['change' => 'No earlier data to compare', 'good' => true];
        $diff = round($a - $b, $decimals);
        if ($diff == 0) return ['change' => "No change $vs", 'good' => true];
        $arrow = $diff > 0 ? '▲' : '▼';
        return ['change' => "$arrow " . abs($diff) . "$unit $vs", 'good' => ($diff > 0) === $higherIsGood];
    };

    $reviewChange = $change($now['review_days'], $before['review_days'], ' days', false, 1);
    if ($now['review_days'] !== null && $before['review_days'] !== null && round($now['review_days'] - $before['review_days'], 1) != 0) {
        $d = round($now['review_days'] - $before['review_days'], 1);
        $reviewChange['change'] = ($d < 0 ? '▼ ' . abs($d) . ' days faster' : '▲ ' . $d . ' days slower');
    }

    $kpis = [
        'adoptions' => ['label' => 'Adoptions ' . $name, 'value' => (string)$now['adoptions']]
                       + $change($now['adoptions'], $before['adoptions'], '', true),
        'match'     => ['label' => 'Avg match score', 'value' => $now['match'] !== null ? round($now['match']) . '%' : '–']
                       + $change($now['match'] !== null ? round($now['match']) : null, $before['match'] !== null ? round($before['match']) : null, '%', true),
        'occupancy' => ['label' => 'Boarding occupancy', 'value' => round($now['occupancy']) . '%']
                       + $change(round($now['occupancy']), round($before['occupancy']), '%', true),
        'review'    => ['label' => 'Avg review time', 'value' => $now['review_days'] !== null ? round($now['review_days'], 1) . ' days' : '–']
                       + $reviewChange,
    ];

    // Staff performance: decisions and care logs per person in the period
    $stmt = $pdo->prepare("SELECT u.full_name, u.role,
            COUNT(a.id) AS decided,
            COUNT(a.id) FILTER (WHERE a.status = 'approved') AS approved,
            AVG(EXTRACT(EPOCH FROM a.decided_at - a.created_at) / 86400) AS days,
            (SELECT COUNT(*) FROM care_logs c WHERE c.caretaker_id = u.id AND c.log_date >= :a2 AND c.log_date < :b2) AS logs
        FROM users u
        LEFT JOIN applications a ON a.decided_by = u.id AND a.decided_at >= :a1 AND a.decided_at < :b1
        WHERE u.status = 'active'
        GROUP BY u.id
        ORDER BY COUNT(a.id) + (SELECT COUNT(*) FROM care_logs c WHERE c.caretaker_id = u.id AND c.log_date >= :a3 AND c.log_date < :b3) DESC, u.full_name");
    $p = [];
    foreach ([1, 2, 3] as $i) { $p["a$i"] = $from->format('Y-m-d'); $p["b$i"] = $to->format('Y-m-d'); }
    $stmt->execute($p);
    $staff = [];
    foreach ($stmt as $r) {
        $staff[] = [
            $r['full_name'],
            ROLES[$r['role']] ?? $r['role'],
            (int)$r['decided'],
            $r['decided'] ? round($r['approved'] / $r['decided'] * 100) . '%' : '–',
            $r['days'] !== null ? round((float)$r['days'], 1) . ' days' : '–',
            (int)$r['logs'],
        ];
    }
    return ['kpis' => $kpis, 'staff' => $staff];
}
