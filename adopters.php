<?php
// Admin: people who signed up on the public website (adopters).
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('adopters');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $status = ($_POST['action'] ?? '') === 'block' ? 'blocked' : 'active';
    $stmt = $pdo->prepare('UPDATE adopters SET status = ? WHERE id = ? RETURNING full_name');
    $stmt->execute([$status, $id]);
    if ($name = $stmt->fetchColumn()) {
        log_activity($status === 'blocked' ? 'Adopter blocked' : 'Adopter unblocked', $name);
        flash($status === 'blocked' ? "$name can no longer sign in" : "$name can sign in again");
    }
    redirect('adopters.php');
}

$adopters = $pdo->query("SELECT d.*,
        (SELECT COUNT(*) FROM applications a WHERE a.adopter_id = d.id) AS apps,
        (SELECT COUNT(*) FROM applications a WHERE a.adopter_id = d.id AND a.status = 'approved') AS adopted,
        (SELECT COUNT(*) FROM boarding b WHERE b.adopter_id = d.id) AS stays
    FROM adopters d ORDER BY d.created_at DESC")->fetchAll();
$passMark = (int)settings()['quiz_pass_mark'];

layout_top('Adopters', 'adopters');
?>
        <div class="page-header">
            <div>
                <h2>Adopters</h2>
                <p class="subtitle">People with an account on the public website. Their applications appear in Adoptions, their bookings in Boarding.</p>
            </div>
            <div class="page-actions"><a href="/user/" target="_blank" class="btn btn-outline"><?= icon('home') ?>Open public website</a></div>
        </div>

        <div class="card toolbar">
            <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search by name, email or phone" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="adoptersTable"></div>
            <select class="form-control" aria-label="Status" data-filter="status" data-filter-table="adoptersTable"><option value="all">All accounts</option><option value="active">Active</option><option value="blocked">Blocked</option></select>
        </div>

        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table" id="adoptersTable">
                    <thead><tr><th>Adopter</th><th>Phone</th><th>Quiz score</th><th>Applications</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($adopters as $d):
                        $score = $d['quiz_score']; ?>
                        <tr data-id="<?= $d['id'] ?>" data-status="<?= e($d['status']) ?>">
                            <td><div class="cell-user"><span class="avatar avatar-sm <?= tint($d['id']) ?>"><?= e(initials($d['full_name'])) ?></span><div><strong><?= e($d['full_name']) ?></strong><small><?= e($d['email']) ?></small></div></div></td>
                            <td><?= e($d['phone'] ?: '–') ?><small class="block"><?= e($d['address']) ?></small></td>
                            <td><?php if ($score === null): ?>Not taken<?php else: ?>
                                <div class="progress-cell"><div class="progress <?= $score < $passMark ? 'bad' : '' ?>"><span style="width:<?= (int)$score ?>%"></span></div><small><?= (int)$score ?>%</small></div>
                            <?php endif; ?></td>
                            <td><?= $d['apps'] ?><?= $d['adopted'] ? ' (' . $d['adopted'] . ' adopted)' : '' ?><?= $d['stays'] ? '<small class="block">' . $d['stays'] . ' boarding</small>' : '' ?></td>
                            <td><?= fmt_date($d['created_at']) ?><small class="block">Last seen <?= e(time_ago($d['last_active'])) ?></small></td>
                            <td><span class="badge badge-<?= $d['status'] === 'active' ? 'active' : 'inactive' ?>" data-status-badge><?= e(label($d['status'])) ?></span></td>
                            <td><div class="actions">
                                <?php if ($d['apps']): ?><a class="icon-action" title="See applications" aria-label="See applications" href="adoptions.php?q=<?= urlencode($d['email']) ?>"><?= icon('eye', 16) ?></a><?php endif; ?>
                                <form method="post" data-server style="display:contents"<?= $d['status'] === 'active' ? ' data-confirm="Block ' . e($d['full_name']) . '? They won\'t be able to sign in."' : '' ?>>
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <?php if ($d['status'] === 'active'): ?>
                                        <input type="hidden" name="action" value="block"><button type="submit" class="icon-action danger" title="Block" aria-label="Block"><?= icon('x', 16) ?></button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="unblock"><button type="submit" class="icon-action success" title="Unblock" aria-label="Unblock"><?= icon('check', 16) ?></button>
                                    <?php endif; ?>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$adopters): ?><p class="card-sub" style="padding:20px">No one has signed up on the public website yet.</p><?php endif; ?>
            </div>
            <div class="table-footer"><span data-result-count="adoptersTable" data-noun="adopters"></span></div>
        </section>
<?php layout_main_end(); layout_bottom(); ?>
