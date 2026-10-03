<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
$user = require_access('quiz');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'discard') {
        unset($_SESSION['generated']);
        flash('Generated questions discarded');
        redirect('generate-quiz.php');
    }

    if ($action === 'save') {
        // backend.js sends the cards as they look now, after any edits and deletions
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'draft';
        $items = json_decode($_POST['questions'] ?? '[]', true);
        $clean = [];
        foreach (is_array($items) ? $items : [] as $q) {
            $options = array_values(array_filter(array_map(fn($o) => trim((string)$o), (array)($q['options'] ?? [])), 'strlen'));
            $text = trim((string)($q['question_text'] ?? ''));
            if ($text === '' || count($options) < 2) continue;
            $clean[] = [
                'question_text'      => $text,
                'category'           => isset(CATEGORIES[$q['category'] ?? '']) ? $q['category'] : 'living_situation',
                'pet_type'           => isset(PET_TYPES[$q['pet_type'] ?? '']) ? $q['pet_type'] : 'all',
                'difficulty'         => in_array($q['difficulty'] ?? '', ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
                'status'             => $status,
                'options'            => json_encode(array_slice($options, 0, 4)),
                'recommended_option' => min(max(0, (int)($q['recommended_option'] ?? 0)), min(3, count($options) - 1)),
                'created_by'         => $user['id'],
            ];
        }
        if (!$clean) json_response(['ok' => false, 'error' => 'There are no complete questions to save.']);

        $insert = $pdo->prepare('INSERT INTO quiz_questions (question_text, category, pet_type, difficulty, status, options, recommended_option, created_by)
                                 VALUES (:question_text, :category, :pet_type, :difficulty, :status, :options, :recommended_option, :created_by)');
        $pdo->beginTransaction();
        foreach ($clean as $row) $insert->execute($row);
        $pdo->commit();

        unset($_SESSION['generated']);
        $n = count($clean);
        log_activity("$n quiz question" . ($n > 1 ? 's' : '') . ' added', 'By ' . $user['full_name'] . ', with AI');
        flash("$n question" . ($n > 1 ? 's' : '') . ' saved to the question bank');
        json_response(['ok' => true]);
    }
}

$questions = $_SESSION['generated'] ?? [];
$params = $_SESSION['quiz_params'] ?? null;
if (!$questions || !$params) redirect('generate-quiz.php');

layout_top('Review Questions', 'quiz');
?>
        <div class="page-header">
            <div>
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="quiz-bank.php">Quiz Management</a><span aria-hidden="true">/</span><a href="generate-quiz.php">Generate</a><span aria-hidden="true">/</span><span aria-current="page">Review</span></nav>
                <h2>Review generated questions</h2>
                <p class="subtitle">Topic: <?= e(CATEGORIES[$params['category']]) ?>, <?= e(PET_TYPES[$params['pet_type']]) ?>, <?= e(label($params['difficulty'])) ?>. Click an answer to mark it as recommended.</p>
            </div>
        </div>

        <div class="alert alert-success" role="status"><?= icon('check') ?><span><strong data-question-count><?= count($questions) ?></strong> questions are ready for review. Nothing has been saved yet.</span></div>

        <div id="reviewList">
            <?php foreach ($questions as $i => $q): ?>
            <article class="review-card" data-category="<?= e($q['category']) ?>" data-pet-type="<?= e($q['pet_type']) ?>" data-difficulty="<?= e($q['difficulty']) ?>">
                <div class="review-card-header">
                    <div class="card-header-left">
                        <span class="q-number">Question <?= $i + 1 ?></span>
                        <span class="badge badge-category"><?= e(CATEGORIES[$q['category']]) ?></span>
                        <span class="badge badge-<?= e($q['difficulty']) ?>"><?= e(label($q['difficulty'])) ?></span>
                    </div>
                    <div class="page-actions">
                        <button type="button" class="btn btn-ghost btn-sm" data-edit-card><?= icon('edit', 16) ?> Edit</button>
                        <button type="button" class="btn btn-danger-ghost btn-sm" data-confirm="Delete this question?" data-success="Question removed"><?= icon('trash', 16) ?> Delete</button>
                    </div>
                </div>
                <p class="question-text"><?= e($q['question_text']) ?></p>
                <ul class="options-container"><?php foreach ($q['options'] as $j => $o): ?><li<?= $j === $q['recommended_option'] ? ' class="recommended"' : '' ?>><?= e($o) ?></li><?php endforeach; ?></ul>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="review-footer">
            <button type="button" class="btn btn-danger-ghost" id="discardAllBtn"><?= icon('trash') ?>Discard all</button>
            <div class="page-actions">
                <button type="button" class="btn btn-outline" id="regenerateBtn"><?= icon('refresh') ?>Regenerate</button>
                <label class="sr-only" for="saveStatus">Save as</label>
                <select class="form-control form-control-sm" id="saveStatus" style="width:auto">
                    <option value="draft">Save as drafts</option>
                    <option value="active">Save as active</option>
                </select>
                <button type="button" class="btn btn-primary" id="saveAllBtn"><?= icon('save') ?>Save all to question bank</button>
            </div>
        </div>

        <!-- Used by the Regenerate and Discard buttons -->
        <form id="regenerateForm" method="post" action="generate-quiz.php" hidden>
            <?= csrf_field() ?>
            <?php foreach ($params as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        </form>
        <form id="discardForm" method="post" action="review-quiz.php" hidden>
            <?= csrf_field() ?><input type="hidden" name="action" value="discard">
        </form>
<?php layout_main_end(); layout_bottom(); ?>
