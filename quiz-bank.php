<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
$user = require_access('quiz');

// ---------- Save or delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM quiz_questions WHERE id = ?')->execute([$id]);
        log_activity('Quiz question deleted');
        flash('Question deleted');
        redirect('quiz-bank.php');
    }

    // Keep only the answers that were filled in, and keep the right one marked
    $options = [];
    $recommended = 0;
    foreach (array_slice((array)($_POST['options'] ?? []), 0, 4) as $i => $text) {
        $text = trim((string)$text);
        if ($text === '') continue;
        if ((int)($_POST['recommended_option'] ?? 0) === $i) $recommended = count($options);
        $options[] = $text;
    }

    $data = [
        'question_text'      => trim($_POST['question_text'] ?? ''),
        'category'           => $_POST['category'] ?? '',
        'pet_type'           => $_POST['pet_type'] ?? 'all',
        'difficulty'         => $_POST['difficulty'] ?? 'medium',
        'status'             => $_POST['status'] ?? 'draft',
        'options'            => json_encode($options),
        'recommended_option' => $recommended,
    ];

    if ($data['question_text'] === '' || count($options) < 2) {
        flash('Write the question and at least two answers.', 'error');
    } elseif (!isset(CATEGORIES[$data['category']]) || !isset(PET_TYPES[$data['pet_type']])
        || !in_array($data['difficulty'], ['easy', 'medium', 'hard'], true) || !in_array($data['status'], ['active', 'draft'], true)) {
        flash('Choose a category, pet type, difficulty and status.', 'error');
    } elseif ($id) {
        $pdo->prepare('UPDATE quiz_questions SET question_text=:question_text, category=:category, pet_type=:pet_type,
                       difficulty=:difficulty, status=:status, options=:options, recommended_option=:recommended_option WHERE id=:id')
            ->execute($data + ['id' => $id]);
        log_activity('Quiz question updated');
        flash('Question saved');
    } else {
        $pdo->prepare('INSERT INTO quiz_questions (question_text, category, pet_type, difficulty, status, options, recommended_option, created_by)
                       VALUES (:question_text, :category, :pet_type, :difficulty, :status, :options, :recommended_option, :created_by)')
            ->execute($data + ['created_by' => $user['id']]);
        log_activity('Quiz question added', 'By ' . $user['full_name']);
        flash('Question saved');
    }
    redirect('quiz-bank.php');
}

// ---------- Load the page ----------
$questions = $pdo->query('SELECT * FROM quiz_questions ORDER BY status, category, id')->fetchAll();
$counts = ['total' => count($questions), 'active' => 0, 'draft' => 0, 'categories' => []];
foreach ($questions as $q) {
    $counts[$q['status']]++;
    $counts['categories'][$q['category']] = true;
}

$passMark = (int)settings()['quiz_pass_mark'];
$p = $passMark; // a whole number, so it is safe to put straight into the query
$rate = $pdo->query("SELECT
    ROUND(100.0 * COUNT(*) FILTER (WHERE quiz_score >= $p) / NULLIF(COUNT(*), 0)) AS overall,
    ROUND(100.0 * COUNT(*) FILTER (WHERE quiz_score >= $p AND created_at >= date_trunc('month', NOW()))
          / NULLIF(COUNT(*) FILTER (WHERE created_at >= date_trunc('month', NOW())), 0)) AS this_month,
    ROUND(100.0 * COUNT(*) FILTER (WHERE quiz_score >= $p AND created_at >= date_trunc('month', NOW()) - INTERVAL '1 month'
                                   AND created_at < date_trunc('month', NOW()))
          / NULLIF(COUNT(*) FILTER (WHERE created_at >= date_trunc('month', NOW()) - INTERVAL '1 month'
                                   AND created_at < date_trunc('month', NOW())), 0)) AS last_month
    FROM applications")->fetch();
$change = ($rate['this_month'] !== null && $rate['last_month'] !== null) ? (int)$rate['this_month'] - (int)$rate['last_month'] : null;

layout_top('Quiz Management', 'quiz');
?>
        <div class="page-header">
            <div>
                <h2>Quiz question bank</h2>
                <p class="subtitle">Eligibility questions that adoption applicants answer before their application is reviewed.</p>
            </div>
            <div class="page-actions"><button type="button" class="btn btn-outline" data-modal-open="questionModal" data-modal-title="Add a question"><?= icon('plus') ?>Add manually</button><a href="generate-quiz.php" class="btn btn-primary"><?= icon('sparkle') ?>Generate with AI</a></div>
        </div>

        <section class="stats-grid">
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Total questions</span><span class="stat-icon tint-green"><?= icon('clipboard', 20) ?></span></div><div class="stat-number"><?= $counts['total'] ?></div><p class="stat-sub">Across <?= count($counts['categories']) ?> categor<?= count($counts['categories']) === 1 ? 'y' : 'ies' ?></p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Active</span><span class="stat-icon tint-blue"><?= icon('check', 20) ?></span></div><div class="stat-number"><?= $counts['active'] ?></div><p class="stat-sub">Shown to applicants</p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Drafts</span><span class="stat-icon tint-honey"><?= icon('edit', 20) ?></span></div><div class="stat-number"><?= $counts['draft'] ?></div><p class="stat-sub">Not yet published</p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Average pass rate</span><span class="stat-icon tint-plum"><?= icon('chart', 20) ?></span></div><div class="stat-number"><?= $rate['overall'] !== null ? (int)$rate['overall'] . '%' : '–' ?></div>
                <?php if ($change !== null): ?><p class="stat-sub <?= $change >= 0 ? 'positive' : 'negative' ?>"><?= $change >= 0 ? '+' : '' ?><?= $change ?>% since last month</p>
                <?php else: ?><p class="stat-sub">Pass mark is <?= $passMark ?>%</p><?php endif; ?>
            </div>
        </section>

        <div class="card toolbar">
            <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search questions" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="questionsTable"></div>
            <select class="form-control" aria-label="Category" data-filter="category" data-filter-table="questionsTable"><option value="all">All categories</option>
                <?php foreach (CATEGORIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
            <select class="form-control" aria-label="Pet type" data-filter="petType" data-filter-table="questionsTable"><option value="all">All pet types</option>
                <?php foreach (PET_TYPES as $k => $v): if ($k === 'all') continue; ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
            <select class="form-control" aria-label="Difficulty" data-filter="difficulty" data-filter-table="questionsTable"><option value="all">All difficulties</option><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select>
            <select class="form-control" aria-label="Status" data-filter="status" data-filter-table="questionsTable"><option value="all">Active and draft</option><option value="active">Active</option><option value="draft">Draft</option></select>
        </div>

        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table" id="questionsTable">
                    <thead><tr><th>Question</th><th>Category</th><th>Pet type</th><th>Difficulty</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $q): ?>
                        <tr data-id="<?= $q['id'] ?>" data-question-text="<?= e($q['question_text']) ?>" data-category="<?= e($q['category']) ?>" data-pet-type="<?= e($q['pet_type']) ?>" data-difficulty="<?= e($q['difficulty']) ?>" data-status="<?= e($q['status']) ?>" data-options="<?= e($q['options']) ?>" data-recommended-option="<?= (int)$q['recommended_option'] ?>">
                            <td class="question-cell"><?= e($q['question_text']) ?></td>
                            <td><span class="badge badge-category"><?= e(CATEGORIES[$q['category']] ?? label($q['category'])) ?></span></td>
                            <td><?= e(PET_TYPES[$q['pet_type']] ?? label($q['pet_type'])) ?></td>
                            <td><span class="badge badge-<?= e($q['difficulty']) ?>"><?= e(label($q['difficulty'])) ?></span></td>
                            <td><span class="badge badge-<?= e($q['status']) ?>" data-status-badge><?= e(label($q['status'])) ?></span></td>
                            <td><div class="actions">
                                <button type="button" class="icon-action" title="Edit" aria-label="Edit" data-modal-open="questionModal" data-modal-title="Edit question"><?= icon('edit', 16) ?></button>
                                <?= delete_button($q['id'], 'Delete this question from the bank?') ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$questions): ?><p class="card-sub" style="padding:20px">No questions yet. Add one yourself or generate a set with AI.</p><?php endif; ?>
            </div>
            <div class="table-footer">
                <span data-result-count="questionsTable" data-noun="questions"></span>
            </div>
        </section>
<?php layout_main_end(); ?>

<div class="modal" id="questionModal" role="dialog" aria-modal="true" aria-labelledby="questionModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="questionModalTitle">Add a question</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 20) ?></button>
        </div>
        <form method="post" action="quiz-bank.php" data-server>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal-body">
                <div class="form-group"><label for="question_text">Question <span class="required">*</span></label><textarea class="form-control" id="question_text" name="question_text" required placeholder="Write the question as an applicant would read it" style="min-height:80px"></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label for="category_21">Category <span class="required">*</span></label><select class="form-control" id="category_21" name="category" required><option value="">Choose category</option>
                        <?php foreach (CATEGORIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="pet_type_22">Pet type <span class="required">*</span></label><select class="form-control" id="pet_type_22" name="pet_type" required>
                        <?php foreach (PET_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="difficulty_23">Difficulty <span class="required">*</span></label><select class="form-control" id="difficulty_23" name="difficulty" required><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></div>
                    <div class="form-group"><label for="status_24">Status <span class="required">*</span></label><select class="form-control" id="status_24" name="status" required><option value="active">Active</option><option value="draft" selected>Draft</option></select></div>
                </div>
                <div class="form-group">
                    <span class="form-label">Answer choices</span>
                    <span class="form-hint">Select the circle beside the answer that best shows a good fit. At least two answers are required.</span>
                </div>
                <?php foreach (['A', 'B', 'C', 'D'] as $i => $letter): ?>
                    <div class="input-icon" style="margin-bottom:8px"><input class="form-control" type="text" name="options[]" placeholder="Answer <?= $letter ?>" style="padding-left:44px"<?= $i < 2 ? ' required' : '' ?>><label class="checkbox" style="position:absolute;left:12px;top:50%;transform:translateY(-50%)" title="Recommended answer"><input type="radio" name="recommended_option" value="<?= $i ?>"<?= $i === 0 ? ' checked' : '' ?> aria-label="Mark answer <?= $letter ?> as recommended"></label></div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save question</button></div>
        </form>
    </div>
</div>
<?php layout_bottom(); ?>
