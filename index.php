<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();

// ---- Key figures ----
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM pets WHERE status <> 'adopted')                                   AS in_care,
    (SELECT COUNT(*) FROM pets WHERE intake_date >= CURRENT_DATE - 7)                       AS new_this_week,
    (SELECT COUNT(*) FROM applications WHERE status = 'approved'
        AND decided_at >= date_trunc('year', NOW()))                                        AS adopted_this_year,
    (SELECT COUNT(*) FROM applications WHERE status = 'review')                             AS in_review,
    (SELECT COUNT(*) FROM applications WHERE status = 'pending')                            AS pending,
    (SELECT COUNT(*) FROM boarding WHERE status IN ('pending','confirmed') AND check_out >= CURRENT_DATE) AS boarding_upcoming,
    (SELECT COUNT(*) FROM boarding WHERE status IN ('pending','confirmed') AND check_in = CURRENT_DATE + 1) AS checkins_tomorrow
")->fetch();

// ---- Adoptions per month for the chosen year ----
$year = (int)($_GET['year'] ?? date('Y'));
$years = range((int)date('Y'), (int)date('Y') - 2);
$stmt = $pdo->prepare("SELECT EXTRACT(MONTH FROM decided_at)::int AS m, COUNT(*) AS n
                       FROM applications WHERE status = 'approved' AND EXTRACT(YEAR FROM decided_at) = ?
                       GROUP BY m");
$stmt->execute([$year]);
$perMonth = array_fill(1, $year == date('Y') ? (int)date('n') : 12, 0);
foreach ($stmt as $r) {
    if (isset($perMonth[$r['m']])) $perMonth[$r['m']] = (int)$r['n'];
}
$max = max(1, max($perMonth));
$peakMonth = array_search(max($perMonth), $perMonth);

// ---- Recent activity ----
$activity = $pdo->query("SELECT user_name, action, details, created_at FROM activity_log
                         WHERE action NOT IN ('Signed in', 'Signed out', 'Downloaded report')
                         ORDER BY created_at DESC LIMIT 5")->fetchAll();
function activity_dot(string $action): string
{
    $a = strtolower($action);
    if (str_contains($a, 'approved') || str_contains($a, 'added') || str_contains($a, 'registered')) return 'dot-green';
    if (str_contains($a, 'boarding') || str_contains($a, 'booking')) return 'dot-blue';
    if (str_contains($a, 'rejected') || str_contains($a, 'deleted') || str_contains($a, 'removed')) return 'dot-plum';
    return 'dot-honey';
}

// ---- Shelters ----
$shelters = $pdo->query("SELECT s.*,
        (SELECT COUNT(*) FROM pets p WHERE p.shelter_id = s.id AND p.status <> 'adopted') AS pets_now,
        (SELECT COUNT(*) FROM applications a JOIN pets p ON p.id = a.pet_id
          WHERE p.shelter_id = s.id AND a.status IN ('pending','review')) AS reviews
    FROM shelters s ORDER BY s.id")->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

layout_top('Dashboard', 'dashboard');
?>
        <div class="page-header">
            <div>
                <h2><?= $greeting ?>, <?= e(explode(' ', $user['full_name'])[0]) ?></h2>
                <p class="subtitle">Here is what is happening across your shelters today.</p>
            </div>
            <?php if (can('reports')): ?>
            <div class="page-actions"><a href="reports.php" class="btn btn-outline"><?= icon('file') ?>All reports</a><a href="report.php?type=summary" target="_blank" class="btn btn-primary"><?= icon('download') ?>Generate report PDF</a></div>
            <?php endif; ?>
        </div>

        <section class="stats-grid" aria-label="Key figures">
            <div class="stat-card">
                <div class="stat-top"><span class="stat-label">Pets in our care</span><span class="stat-icon tint-green"><?= icon('paw', 20) ?></span></div>
                <div class="stat-number"><?= number_format($stats['in_care']) ?></div>
                <p class="stat-sub<?= $stats['new_this_week'] ? ' positive' : '' ?>">+<?= $stats['new_this_week'] ?> new this week</p>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-label">Adoptions this year</span><span class="stat-icon tint-plum"><?= icon('home', 20) ?></span></div>
                <div class="stat-number"><?= number_format($stats['adopted_this_year']) ?></div>
                <p class="stat-sub"><?= $stats['in_review'] ?> under review right now</p>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-label">Pending applications</span><span class="stat-icon tint-honey"><?= icon('clipboard', 20) ?></span></div>
                <div class="stat-number"><?= number_format($stats['pending']) ?></div>
                <?php if ($stats['pending']): ?>
                    <p class="stat-sub negative">Needs review.<?php if (can('adoptions')): ?> <a href="adoptions.php">Open queue</a><?php endif; ?></p>
                <?php else: ?>
                    <p class="stat-sub positive">Queue is clear</p>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-label">Boarding requests</span><span class="stat-icon tint-blue"><?= icon('calendar', 20) ?></span></div>
                <div class="stat-number"><?= number_format($stats['boarding_upcoming']) ?></div>
                <p class="stat-sub"><?= $stats['checkins_tomorrow'] ?> check in tomorrow</p>
            </div>
        </section>

        <section class="grid-2-1">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Adoptions per month</h3>
                        <p class="card-sub">Pets placed in permanent homes</p>
                    </div>
                    <form method="get" data-server>
                        <select class="form-control form-control-sm" aria-label="Year" name="year" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?><option <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="bar-chart" role="img" aria-label="Bar chart of monthly adoptions in <?= $year ?>, peaking at <?= max($perMonth) ?>">
                    <?php foreach ($perMonth as $m => $n):
                        $name = date('M', mktime(0, 0, 0, $m, 1));
                        $hl = ($m === $peakMonth && $n > 0) ? ' highlight' : ''; ?>
                    <div class="bar-wrapper" title="<?= $name ?>: <?= $n ?> adoption<?= $n === 1 ? '' : 's' ?>"><span class="bar-value<?= $hl ?>"><?= $n ?></span>
                        <div class="bar<?= $hl ?>" style="height:<?= round($n / $max * 86) ?>%"></div><span class="bar-label<?= $hl ?>"><?= $name ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent activity</h3>
                    <?php if (can('reports')): ?><a href="report.php?type=staff_activity" class="link-sm">Download log</a><?php endif; ?>
                </div>
                <ul class="activity-list">
                    <?php foreach ($activity as $a): ?>
                    <li><span class="activity-dot <?= activity_dot($a['action']) ?>"></span>
                        <div><strong><?= e($a['action']) ?></strong><small><?= e($a['details'] ? $a['details'] . ', ' : '') ?><?= e(time_ago($a['created_at'])) ?></small></div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$activity): ?>
                    <li><span class="activity-dot dot-green"></span><div><strong>No activity yet</strong><small>Changes made by staff will show up here.</small></div></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>

        <section class="grid-2-1">
            <div class="card card-flush">
                <div class="card-header">
                    <h3 class="card-title">Shelter partners</h3><span class="card-sub">Capacity and review load</span>
                </div>
                <div class="table-wrap" style="margin-top:14px">
                    <table class="data-table">
                        <thead><tr><th>Shelter</th><th>Capacity</th><th>Pending reviews</th></tr></thead>
                        <tbody>
                            <?php foreach ($shelters as $s):
                                $pct = $s['capacity'] > 0 ? min(100, round($s['pets_now'] / $s['capacity'] * 100)) : 0; ?>
                            <tr>
                                <td><strong><?= e($s['name']) ?></strong><small class="block"><?= e($s['area']) ?></small></td>
                                <td><div class="progress-cell"><div class="progress<?= $pct >= 90 ? ' bad' : '' ?>"><span style="width:<?= $pct ?>%"></span></div><small><?= $pct ?>%</small></div></td>
                                <td><?php if (can('adoptions')): ?><a href="adoptions.php"><?= $s['reviews'] ?> application<?= $s['reviews'] == 1 ? '' : 's' ?></a><?php else: ?><?= $s['reviews'] ?> application<?= $s['reviews'] == 1 ? '' : 's' ?><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Quick actions</h3></div>
                <div class="quick-actions">
                    <a href="pets.php#open-petModal" class="quick-action"><span class="stat-icon tint-green"><?= icon('plus', 20) ?></span><span><strong>Register a new pet</strong><small>Log an intake</small></span></a>
                    <?php if (can('adoptions')): ?>
                    <a href="adoptions.php" class="quick-action"><span class="stat-icon tint-honey"><?= icon('clipboard', 20) ?></span><span><strong>Review applications</strong><small><?= $stats['pending'] ?> waiting</small></span></a>
                    <?php endif; ?>
                    <?php if (can('quiz')): ?>
                    <a href="generate-quiz.php" class="quick-action"><span class="stat-icon tint-plum"><?= icon('sparkle', 20) ?></span><span><strong>Generate quiz questions</strong><small>With the AI assistant</small></span></a>
                    <?php endif; ?>
                    <?php if (can('users')): ?>
                    <a href="user-management.php#open-userModal" class="quick-action"><span class="stat-icon tint-blue"><?= icon('users', 20) ?></span><span><strong>Add a staff member</strong><small>Invite and set a role</small></span></a>
                    <?php elseif (can('boarding')): ?>
                    <a href="boarding.php#open-bookingModal" class="quick-action"><span class="stat-icon tint-blue"><?= icon('calendar', 20) ?></span><span><strong>New boarding booking</strong><small>Book a kennel</small></span></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
<?php layout_main_end(); layout_bottom(); ?>
