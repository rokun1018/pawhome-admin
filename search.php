<?php
// Search box in the top bar: looks through pets, applications, bookings and staff.
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_login();

$q = trim($_GET['q'] ?? '');
$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
$results = [];

if ($q !== '') {
    $stmt = $pdo->prepare("SELECT name, breed, species, status FROM pets
                           WHERE name ILIKE :q OR breed ILIKE :q2 OR ('PH-' || (1000 + id)) ILIKE :q3 ORDER BY name LIMIT 20");
    $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
    $results['Pets'] = ['pets.php', array_map(fn($r) => [$r['name'], trim(label($r['species']) . ', ' . $r['breed'], ', '), label($r['status'])], $stmt->fetchAll())];

    if (can('adoptions')) {
        $stmt = $pdo->prepare('SELECT applicant_name, applicant_email, status FROM applications
                               WHERE applicant_name ILIKE :q OR applicant_email ILIKE :q2 ORDER BY created_at DESC LIMIT 20');
        $stmt->execute(['q' => $like, 'q2' => $like]);
        $results['Adoption applications'] = ['adoptions.php', array_map(fn($r) => [$r['applicant_name'], $r['applicant_email'], label($r['status'])], $stmt->fetchAll())];
    }
    if (can('boarding')) {
        $stmt = $pdo->prepare('SELECT pet_name, owner_name, status FROM boarding
                               WHERE pet_name ILIKE :q OR owner_name ILIKE :q2 OR kennel ILIKE :q3 ORDER BY check_in DESC LIMIT 20');
        $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
        $results['Boarding'] = ['boarding.php', array_map(fn($r) => [$r['pet_name'], 'Owner: ' . $r['owner_name'], label($r['status'])], $stmt->fetchAll())];
    }
    if (can('users')) {
        $stmt = $pdo->prepare('SELECT full_name, email, role FROM users WHERE full_name ILIKE :q OR email ILIKE :q2 ORDER BY full_name LIMIT 20');
        $stmt->execute(['q' => $like, 'q2' => $like]);
        $results['Staff'] = ['user-management.php', array_map(fn($r) => [$r['full_name'], $r['email'], ROLES[$r['role']]], $stmt->fetchAll())];
    }
}
$total = array_sum(array_map(fn($r) => count($r[1]), $results));

layout_top('Search');
?>
        <div class="page-header">
            <div>
                <h2>Search results</h2>
                <p class="subtitle"><?= $q === '' ? 'Type in the search box above.' : $total . ' result' . ($total === 1 ? '' : 's') . ' for "' . e($q) . '"' ?></p>
            </div>
        </div>
        <?php foreach ($results as $section => [$page, $rows]): if (!$rows) continue; ?>
            <h3 class="section-title"><?= e($section) ?></h3>
            <section class="card card-flush" style="margin-bottom:20px">
                <div class="table-wrap">
                    <table class="data-table">
                        <tbody>
                        <?php foreach ($rows as [$title, $sub, $status]): ?>
                            <tr><td><strong><a href="<?= $page ?>?q=<?= urlencode($title) ?>"><?= e($title) ?></a></strong></td><td><?= e($sub) ?></td><td><?= e($status) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
<?php layout_main_end(); layout_bottom(); ?>
