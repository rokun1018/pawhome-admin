<?php
// =====================================================================
//  report.php?type=...&date_from=...&date_to=...&shelter_id=...
//  CSV reports download straight away. "PDF" reports open as a clean
//  printable page; the browser's print window can save them as PDF.
// =====================================================================
require __DIR__ . '/includes/config.php';
require_access('reports');

$type = $_GET['type'] ?? '';
if (!isset(REPORTS[$type])) redirect('reports.php');
[$reportName, $format] = REPORTS[$type];

$valid = fn($d) => $d && DateTime::createFromFormat('Y-m-d', $d) ? $d : null;
$from = $valid($_GET['date_from'] ?? '') ?? date('Y-01-01');
$to = $valid($_GET['date_to'] ?? '') ?? date('Y-m-d');
$shelterId = ctype_digit((string)($_GET['shelter_id'] ?? '')) ? (int)$_GET['shelter_id'] : null;

$shelterName = 'All shelters';
if ($shelterId) {
    $stmt = $pdo->prepare('SELECT name FROM shelters WHERE id = ?');
    $stmt->execute([$shelterId]);
    $shelterName = $stmt->fetchColumn() ?: 'All shelters';
}

log_activity('Downloaded report', "$reportName ($format)");

// ---------- Helpers ----------
function send_csv(string $filename, array $header, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // lets Excel read accents and Bangla correctly
    fputcsv($out, $header);
    foreach ($rows as $row) fputcsv($out, array_values($row));
    fclose($out);
    exit;
}

function print_page(string $title, string $subtitle, string $body): never
{
    $org = settings()['org_name']; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($title) ?> | <?= e($org) ?></title>
<style>
    body { font-family: Figtree, system-ui, sans-serif; color: #1d2433; margin: 32px; line-height: 1.45; }
    header { border-bottom: 2px solid #1d2433; padding-bottom: 12px; margin-bottom: 20px; }
    h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 16px; margin: 24px 0 8px; }
    .muted { color: #5b6475; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #d9dde4; }
    th { background: #f2f4f7; }
    .figures { display: flex; gap: 16px; flex-wrap: wrap; }
    .figures div { border: 1px solid #d9dde4; padding: 10px 14px; min-width: 130px; }
    .figures strong { display: block; font-size: 22px; }
    .toolbar { margin-bottom: 16px; }
    @media print { .toolbar { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print or save as PDF</button></div>
<header>
    <h1><?= e($title) ?></h1>
    <div class="muted"><?= e($org) ?> &middot; <?= e($subtitle) ?> &middot; Generated <?= date('j M Y, g:i a') ?> by <?= e(current_user()['full_name']) ?></div>
</header>
<?= $body ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
<?php exit;
}

function table_html(array $header, array $rows): string
{
    if (!$rows) return '<p class="muted">Nothing in this date range.</p>';
    $html = '<table><thead><tr>';
    foreach ($header as $h) $html .= '<th>' . e($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) $html .= '<td>' . e($cell) . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

$range = fmt_date($from) . ' to ' . fmt_date($to) . ', ' . $shelterName;
$shelterSql = $shelterId ? ' AND p.shelter_id = ' . $shelterId : ''; // a whole number, safe to insert
$file = fn($name) => 'pawhome-' . $name . '-' . $from . '-to-' . $to . '.csv';

// ---------- The reports ----------
switch ($type) {
    case 'pets':
        $stmt = $pdo->prepare("SELECT 'PH-' || (1000 + p.id), p.name, p.species, p.breed, p.age_months, p.gender, p.size,
                                      s.name, p.intake_date, p.status, p.description
                               FROM pets p LEFT JOIN shelters s ON s.id = p.shelter_id
                               WHERE p.intake_date BETWEEN ? AND ? $shelterSql ORDER BY p.intake_date");
        $stmt->execute([$from, $to]);
        send_csv($file('pet-intake'), ['ID', 'Name', 'Species', 'Breed', 'Age (months)', 'Gender', 'Size', 'Shelter', 'Intake date', 'Status', 'Notes'], $stmt);

    case 'applications':
        $stmt = $pdo->prepare("SELECT a.id, a.created_at::date, a.applicant_name, a.applicant_email, a.applicant_phone, a.home_type,
                                      p.name, s.name, a.quiz_score, a.status, a.decided_at::date, a.admin_notes
                               FROM applications a LEFT JOIN pets p ON p.id = a.pet_id LEFT JOIN shelters s ON s.id = p.shelter_id
                               WHERE a.created_at::date BETWEEN ? AND ? $shelterSql ORDER BY a.created_at");
        $stmt->execute([$from, $to]);
        send_csv($file('applications'), ['ID', 'Submitted', 'Applicant', 'Email', 'Phone', 'Home type', 'Pet', 'Shelter', 'Quiz score (%)', 'Status', 'Decided', 'Internal notes'], $stmt);

    case 'staff_activity':
        $stmt = $pdo->prepare("SELECT to_char(created_at AT TIME ZONE ?, 'YYYY-MM-DD HH24:MI'), user_name, action, details
                               FROM activity_log WHERE created_at::date BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->execute([date_default_timezone_get(), $from, $to]);
        send_csv($file('staff-activity'), ['Date and time', 'User', 'Action', 'Details'], $stmt);

    case 'quiz':
        // For each question: how many applicants answered it, and how many picked the recommended answer
        $stmt = $pdo->prepare("SELECT q.id, q.question_text, q.category, q.pet_type, q.difficulty, q.status,
                    COUNT(a.id) AS answered,
                    COUNT(a.id) FILTER (WHERE (a.quiz_answers ->> q.id::text)::int = q.recommended_option) AS recommended
                FROM quiz_questions q
                LEFT JOIN applications a ON jsonb_exists(a.quiz_answers, q.id::text)
                    AND a.created_at::date BETWEEN ? AND ?
                    " . ($shelterId ? "AND a.pet_id IN (SELECT p.id FROM pets p WHERE true $shelterSql)" : '') . "
                GROUP BY q.id ORDER BY q.category, q.id");
        $stmt->execute([$from, $to]);
        $rows = [];
        foreach ($stmt as $r) {
            $rows[] = [$r['id'], $r['question_text'], CATEGORIES[$r['category']] ?? $r['category'], PET_TYPES[$r['pet_type']] ?? $r['pet_type'],
                       label($r['difficulty']), label($r['status']), $r['answered'],
                       $r['answered'] ? round($r['recommended'] / $r['answered'] * 100) . '%' : '–'];
        }
        send_csv($file('quiz-pass-rates'), ['ID', 'Question', 'Category', 'Pet type', 'Difficulty', 'Status', 'Times answered', 'Chose recommended answer'], $rows);

    case 'adoptions':
        $stmt = $pdo->prepare("SELECT a.decided_at, p.name AS pet, p.species, p.breed, a.applicant_name, a.applicant_phone, s.name AS shelter, a.quiz_score
                               FROM applications a LEFT JOIN pets p ON p.id = a.pet_id LEFT JOIN shelters s ON s.id = p.shelter_id
                               WHERE a.status = 'approved' AND a.decided_at::date BETWEEN ? AND ? $shelterSql ORDER BY a.decided_at");
        $stmt->execute([$from, $to]);
        $rows = array_map(fn($r) => [fmt_date($r['decided_at']), $r['pet'] ?? 'Pet removed', trim(label($r['species'] ?? '') . ', ' . $r['breed'], ', '),
                                     $r['applicant_name'], $r['applicant_phone'], $r['shelter'], $r['quiz_score'] . '%'], $stmt->fetchAll());
        print_page('Adoptions report', $range,
            '<div class="figures"><div><strong>' . count($rows) . '</strong>adoptions</div></div><h2>Adoptions</h2>'
            . table_html(['Date', 'Pet', 'Species and breed', 'Adopter', 'Phone', 'Shelter', 'Quiz score'], $rows));

    case 'boarding':
        $rate = (float)settings()['boarding_rate'];
        $kennels = count(kennel_list());
        $days = (new DateTime($from))->diff(new DateTime($to))->days + 1;
        // Only count the nights that fall inside the chosen dates
        $stmt = $pdo->prepare("SELECT *, GREATEST(0, LEAST(check_out, ?::date + 1) - GREATEST(check_in, ?::date)) AS nights_in_range
                               FROM boarding WHERE check_in <= ? AND check_out >= ? ORDER BY check_in");
        $stmt->execute([$to, $from, $to, $from]);
        $rows = [];
        $stays = $nights = $income = 0;
        foreach ($stmt as $b) {
            $rows[] = [$b['pet_name'], label($b['species']), $b['owner_name'], fmt_date($b['check_in']), fmt_date($b['check_out']), $b['kennel'] ?: '–', label($b['status'])];
            if ($b['status'] === 'cancelled') continue;
            $stays++;
            $nights += (int)$b['nights_in_range'];
            if (in_array($b['status'], ['checked_in', 'completed'], true)) $income += (int)$b['nights_in_range'] * $rate;
        }
        $occupancy = $kennels ? round($nights / ($kennels * $days) * 100) : 0;
        print_page('Boarding summary', fmt_date($from) . ' to ' . fmt_date($to),
            '<div class="figures"><div><strong>' . $stays . '</strong>stays</div><div><strong>' . $nights . '</strong>kennel nights</div>'
            . '<div><strong>' . $occupancy . '%</strong>occupancy (' . $kennels . ' kennels)</div>'
            . '<div><strong>' . number_format($income, 2) . '</strong>income from completed and current stays</div></div>'
            . ($rate == 0 ? '<p class="muted">Set a price per night in Settings &gt; Organisation to calculate income.</p>' : '')
            . '<h2>Bookings</h2>' . table_html(['Pet', 'Species', 'Owner', 'Check-in', 'Check-out', 'Kennel', 'Status'], $rows));

    case 'summary':
        $f = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM pets WHERE status <> 'adopted') AS in_care,
            (SELECT COUNT(*) FROM pets WHERE intake_date BETWEEN :f AND :t) AS intakes,
            (SELECT COUNT(*) FROM applications WHERE created_at::date BETWEEN :f2 AND :t2) AS applications,
            (SELECT COUNT(*) FROM applications WHERE status = 'approved' AND decided_at::date BETWEEN :f3 AND :t3) AS adoptions,
            (SELECT COUNT(*) FROM applications WHERE status IN ('pending','review')) AS open_apps,
            (SELECT COUNT(*) FROM boarding WHERE status <> 'cancelled' AND check_in <= :t4 AND check_out >= :f4) AS stays");
        $f->execute(['f' => $from, 't' => $to, 'f2' => $from, 't2' => $to, 'f3' => $from, 't3' => $to, 'f4' => $from, 't4' => $to]);
        $f = $f->fetch();
        $shelters = $pdo->query("SELECT s.name, s.capacity,
                (SELECT COUNT(*) FROM pets p WHERE p.shelter_id = s.id AND p.status <> 'adopted') AS pets_now
            FROM shelters s ORDER BY s.id")->fetchAll();
        $rows = array_map(fn($s) => [$s['name'], $s['pets_now'], $s['capacity'], ($s['capacity'] ? round($s['pets_now'] / $s['capacity'] * 100) : 0) . '%'], $shelters);
        print_page('Shelter summary', fmt_date($from) . ' to ' . fmt_date($to),
            '<div class="figures">'
            . '<div><strong>' . $f['in_care'] . '</strong>pets in care now</div>'
            . '<div><strong>' . $f['intakes'] . '</strong>new intakes</div>'
            . '<div><strong>' . $f['applications'] . '</strong>applications received</div>'
            . '<div><strong>' . $f['adoptions'] . '</strong>adoptions</div>'
            . '<div><strong>' . $f['open_apps'] . '</strong>applications still open</div>'
            . '<div><strong>' . $f['stays'] . '</strong>boarding stays</div></div>'
            . '<h2>Shelters</h2>' . table_html(['Shelter', 'Pets now', 'Capacity', 'Full'], $rows));
}
