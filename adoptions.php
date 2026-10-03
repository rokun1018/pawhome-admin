<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('adoptions');

// ---------- Decisions and notes (sent in the background by backend.js) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT a.*, p.name AS pet_name FROM applications a LEFT JOIN pets p ON p.id = a.pet_id WHERE a.id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) json_response(['ok' => false, 'error' => 'That application no longer exists. Refresh the page.'], 404);

    if (isset($_POST['admin_notes'])) {
        $pdo->prepare('UPDATE applications SET admin_notes = ? WHERE id = ?')->execute([trim($_POST['admin_notes']), $id]);
    }

    if (($_POST['action'] ?? '') === 'status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['pending', 'review', 'approved', 'rejected'], true)) {
            json_response(['ok' => false, 'error' => 'Unknown status.'], 400);
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE applications SET status = ?, decided_at = CASE WHEN ? IN ('approved','rejected') THEN NOW() ELSE NULL END WHERE id = ?")
            ->execute([$status, $status, $id]);

        // Keep the pet's status in step with the decision
        if ($app['pet_id']) {
            if ($status === 'approved') {
                $pdo->prepare("UPDATE pets SET status = 'adopted' WHERE id = ?")->execute([$app['pet_id']]);
            } elseif ($app['status'] === 'approved') {
                $pdo->prepare("UPDATE pets SET status = 'available' WHERE id = ? AND status = 'adopted'")->execute([$app['pet_id']]);
            }
        }
        $pdo->commit();

        $words = ['approved' => 'Adoption approved', 'rejected' => 'Application rejected', 'review' => 'Application under review', 'pending' => 'Application set to pending'];
        log_activity($words[$status], $app['applicant_name'] . ($app['pet_name'] ? ' for ' . $app['pet_name'] : ''));
    }
    json_response(['ok' => true]);
}

// ---------- Load the page ----------
$passMark = (int)settings()['quiz_pass_mark'];
$apps = $pdo->query('SELECT a.*, p.name AS pet_name, p.breed AS pet_breed, p.species AS pet_species
                     FROM applications a LEFT JOIN pets p ON p.id = a.pet_id
                     ORDER BY a.created_at DESC')->fetchAll();

function pet_label(array $a): string
{
    if (!$a['pet_name']) return 'Pet removed';
    $extra = $a['pet_breed'] ?: label($a['pet_species']);
    return $a['pet_name'] . ' (' . $extra . ')';
}

layout_top('Adoptions', 'adoptions');
?>
        <div class="page-header">
            <div>
                <h2>Adoption applications</h2>
                <p class="subtitle">Review applicants, check their eligibility quiz score and make a decision.</p>
            </div>
            <div class="page-actions"><a href="report.php?type=applications" class="btn btn-outline"><?= icon('download') ?>Export CSV</a></div>
        </div>

        <section class="card card-flush">
            <div style="padding:16px 20px 0">
                <div class="tabs" role="tablist" data-filter="status" data-filter-table="appsTable"><button type="button" class="tab active" role="tab" data-value="all" aria-selected="true">All<span class="tab-count"></span></button><button type="button" class="tab" role="tab" data-value="pending" aria-selected="false">Pending<span class="tab-count"></span></button><button type="button" class="tab" role="tab" data-value="review" aria-selected="false">Under review<span class="tab-count"></span></button><button type="button" class="tab" role="tab" data-value="approved" aria-selected="false">Approved<span class="tab-count"></span></button><button type="button" class="tab" role="tab" data-value="rejected" aria-selected="false">Rejected<span class="tab-count"></span></button></div>
                <div class="toolbar" style="padding:0;margin-bottom:16px">
                    <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search applicant, email or pet" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="appsTable"></div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table" id="appsTable">
                    <thead><tr><th>Applicant</th><th>Pet</th><th>Quiz score</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($apps as $a):
                        $score = (int)$a['quiz_score'];
                        $bar = $score < $passMark ? 'bad' : ($score < $passMark + 5 ? 'warn' : '');
                        $pet = pet_label($a); ?>
                        <tr data-id="<?= $a['id'] ?>" data-status="<?= e($a['status']) ?>" data-applicant-name="<?= e($a['applicant_name']) ?>" data-applicant-email="<?= e($a['applicant_email']) ?>" data-applicant-phone="<?= e($a['applicant_phone']) ?>" data-pet="<?= e($pet) ?>" data-quiz-score="<?= $score ?>%" data-submitted="<?= fmt_date($a['created_at']) ?>" data-home-type="<?= e($a['home_type']) ?>" data-admin-notes="<?= e($a['admin_notes']) ?>">
                            <td><div class="cell-user"><span class="avatar avatar-sm <?= tint($a['id']) ?>"><?= e(initials($a['applicant_name'])) ?></span><div><strong><?= e($a['applicant_name']) ?></strong><small><?= e($a['applicant_email']) ?></small></div></div></td>
                            <td><?= e($pet) ?></td>
                            <td><div class="progress-cell"><div class="progress <?= $bar ?>"><span style="width:<?= $score ?>%"></span></div><small><?= $score ?>%</small></div></td>
                            <td><?= fmt_date($a['created_at']) ?></td>
                            <td><span class="badge badge-<?= e($a['status']) ?>" data-status-badge><?= e(label($a['status'])) ?></span></td>
                            <td><div class="actions">
                                <button type="button" class="icon-action" title="View application" aria-label="View application" data-modal-open="applicationModal"><?= icon('eye', 16) ?></button>
                                <button type="button" class="icon-action success" title="Approve" aria-label="Approve" data-set-status="approved" data-label="Approved" data-success="<?= e($a['applicant_name']) ?>'s application approved"><?= icon('check', 16) ?></button>
                                <button type="button" class="icon-action danger" title="Reject" aria-label="Reject" data-set-status="rejected" data-label="Rejected" data-success="<?= e($a['applicant_name']) ?>'s application rejected"><?= icon('x', 16) ?></button>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$apps): ?><p class="card-sub" style="padding:20px">No applications yet. They will appear here when people apply to adopt.</p><?php endif; ?>
            </div>
            <div class="table-footer">
                <span data-result-count="appsTable" data-noun="applications"></span>
            </div>
        </section>
<?php layout_main_end(); ?>

<div class="modal" id="applicationModal" role="dialog" aria-modal="true" aria-labelledby="applicationModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="applicationModalTitle">Application details</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 20) ?></button>
        </div>
        <div class="modal-body">
                <div class="cell-user" style="margin-bottom:18px">
                    <span class="avatar avatar-lg"><?= icon('user', 30) ?></span>
                    <div><strong style="font-size:18px" data-field="applicantName">Applicant</strong><small data-field="applicantEmail"></small></div>
                </div>
                <div class="score-ring"><strong data-field="quizScore">0%</strong><span>Eligibility quiz score. The pass mark is <?= $passMark ?>%<?= can('organisation') ? ' and can be changed in Settings' : '' ?>.</span></div>
                <dl class="detail-grid">
                    <div><dt>Pet requested</dt><dd data-field="pet"></dd></div>
                    <div><dt>Submitted</dt><dd data-field="submitted"></dd></div>
                    <div><dt>Phone</dt><dd data-field="applicantPhone"></dd></div>
                    <div><dt>Home type</dt><dd data-field="homeType"></dd></div>
                </dl>
                <div class="form-group"><label for="admin_notes">Internal notes</label><textarea class="form-control" id="admin_notes" name="admin_notes" placeholder="Only staff can see these notes. They save when you click away."></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-danger-ghost spacer" data-set-status="rejected" data-label="Rejected" data-success="Application rejected"><?= icon('x') ?>Reject</button><button type="button" class="btn btn-outline" data-set-status="review" data-label="Under review" data-success="Marked as under review">Mark under review</button><button type="button" class="btn btn-primary" data-set-status="approved" data-label="Approved" data-success="Application approved"><?= icon('check') ?>Approve</button></div>
    </div>
</div>
<?php layout_bottom(); ?>
