<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('quiz');

// Asks the AI for questions. Returns a list of questions, or an error message (string).
function generate_questions(array $p): array|string
{
    $key = getenv('AI_API_KEY');
    if (!$key) {
        return 'The AI assistant is not set up yet. Add AI_API_KEY in your Render environment settings (see the README).';
    }

    $petText = $p['pet_type'] === 'all' ? 'any kind of pet' : rtrim(PET_TYPES[$p['pet_type']], 's') . 's';
    $difficultyText = $p['difficulty'] === 'mixed' ? 'a mix of easy, medium and hard' : $p['difficulty'];
    $prompt = "Write {$p['num_questions']} multiple-choice questions for an animal shelter's adoption eligibility quiz.\n"
        . "Topic: " . CATEGORIES[$p['category']] . "\n"
        . "The applicant wants to adopt: $petText\n"
        . "Difficulty: $difficultyText\n"
        . ($p['extra_instructions'] !== '' ? "Extra instructions from staff: {$p['extra_instructions']}\n" : '')
        . "\nRules: each question has exactly 4 answers and one recommended answer that best shows the applicant is a good fit. "
        . "Use friendly, plain wording aimed at everyday pet owners. Answers should help staff judge fit, not trick people.\n\n"
        . 'Reply with ONLY a JSON array, no other text. Each item: '
        . '{"question_text": "...", "difficulty": "easy|medium|hard", "options": ["...", "...", "...", "..."], "recommended_option": 0}';

    $ch = curl_init(getenv('AI_API_URL') ?: 'https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'content-type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => getenv('AI_MODEL') ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => 4000,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log('AI request failed: ' . $curlError);
        return 'Could not reach the AI service. Try again in a minute.';
    }
    $reply = json_decode($raw, true);
    if ($status !== 200) {
        error_log('AI error ' . $status . ': ' . $raw);
        return 'The AI service returned an error (' . ($reply['error']['message'] ?? "code $status") . ').';
    }

    $text = '';
    foreach ($reply['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
    $items = json_decode($text, true);
    if (!is_array($items)) {
        return 'The AI reply could not be read. Try again.';
    }

    // Keep only well-formed questions
    $questions = [];
    foreach ($items as $q) {
        $options = array_values(array_filter(array_map('trim', (array)($q['options'] ?? [])), 'strlen'));
        $text = trim((string)($q['question_text'] ?? ''));
        if ($text === '' || count($options) < 2) continue;
        $difficulty = $p['difficulty'] === 'mixed' ? ($q['difficulty'] ?? 'medium') : $p['difficulty'];
        $questions[] = [
            'question_text'      => $text,
            'category'           => $p['category'],
            'pet_type'           => $p['pet_type'],
            'difficulty'         => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium',
            'options'            => array_slice($options, 0, 4),
            'recommended_option' => min(max(0, (int)($q['recommended_option'] ?? 0)), min(3, count($options) - 1)),
        ];
    }
    return $questions ?: 'The AI did not return any usable questions. Try again.';
}

// ---------- Generate ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $params = [
        'category'           => $_POST['category'] ?? '',
        'pet_type'           => $_POST['pet_type'] ?? 'all',
        'num_questions'      => max(1, min(20, (int)($_POST['num_questions'] ?? 5))),
        'difficulty'         => $_POST['difficulty'] ?? 'medium',
        'extra_instructions' => mb_substr(trim($_POST['extra_instructions'] ?? ''), 0, 500),
    ];
    $_SESSION['quiz_params'] = $params;

    if (!isset(CATEGORIES[$params['category']]) || !isset(PET_TYPES[$params['pet_type']])
        || !in_array($params['difficulty'], ['easy', 'medium', 'hard', 'mixed'], true)) {
        flash('Choose a topic, pet type and difficulty.', 'error');
        redirect('generate-quiz.php');
    }

    $result = generate_questions($params);
    if (is_string($result)) {
        flash($result, 'error');
        redirect('generate-quiz.php');
    }
    $_SESSION['generated'] = $result;
    redirect('review-quiz.php');
}

$p = $_SESSION['quiz_params'] ?? ['category' => 'living_situation', 'pet_type' => 'dog', 'num_questions' => 5, 'difficulty' => 'medium', 'extra_instructions' => ''];
$sel = fn($a, $b) => $a === $b ? ' selected' : '';

layout_top('Generate Questions', 'quiz');
?>
        <div class="page-header">
            <div>
                <nav class="breadcrumb" aria-label="Breadcrumb"><a href="quiz-bank.php">Quiz Management</a><span aria-hidden="true">/</span><span aria-current="page">Generate</span></nav>
                <h2>Generate quiz questions</h2>
                <p class="subtitle">Describe what you need and the assistant drafts questions for you to review.</p>
            </div>
        </div>

        <div class="form-split">
            <form class="card" id="generateForm" method="post" action="generate-quiz.php" data-server>
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group"><label for="category_25">Topic <span class="required">*</span></label><select class="form-control" id="category_25" name="category" required>
                        <?php foreach (CATEGORIES as $k => $v): ?><option value="<?= $k ?>"<?= $sel($k, $p['category']) ?>><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="pet_type_26">Pet type <span class="required">*</span></label><select class="form-control" id="pet_type_26" name="pet_type" required>
                        <?php foreach (PET_TYPES as $k => $v): ?><option value="<?= $k ?>"<?= $sel($k, $p['pet_type']) ?>><?= $v ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="num_questions_27">Number of questions <span class="required">*</span></label><input class="form-control" type="number" id="num_questions_27" name="num_questions" required value="<?= (int)$p['num_questions'] ?>" min="1" max="20"><span class="form-hint">Between 1 and 20</span></div>
                    <div class="form-group"><label for="difficulty_28">Difficulty <span class="required">*</span></label><select class="form-control" id="difficulty_28" name="difficulty" required>
                        <?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'mixed' => 'Mixed'] as $k => $v): ?><option value="<?= $k ?>"<?= $sel($k, $p['difficulty']) ?>><?= $v ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group">
                    <label for="extra_instructions">Anything specific to cover?</label>
                    <textarea class="form-control" id="extra_instructions" name="extra_instructions" maxlength="500" placeholder="For example: focus on high-energy breeds and owners who work from home"><?= e($p['extra_instructions']) ?></textarea>
                    <span class="form-hint">Optional. Leave blank for general questions on the chosen topic.</span>
                </div>
                <div class="form-actions" style="justify-content:space-between">
                    <a href="quiz-bank.php" class="btn btn-ghost"><?= icon('arrow-left') ?>Back to question bank</a>
                    <button type="submit" class="btn btn-primary btn-lg"><?= icon('sparkle') ?>Generate questions</button>
                </div>
                <p class="form-hint" style="margin-top:10px">This takes about 10 to 30 seconds. Keep this page open.</p>
            </form>

            <aside class="ai-helper-card">
                <h3><?= icon('sparkle', 20) ?>AI assistant</h3>
                <p>Nothing is published automatically. Generated questions wait on a review screen where you can edit, delete or regenerate them first.</p>
                <h4>How it works</h4>
                <ol class="steps">
                    <li>Choose a topic, pet type and difficulty.</li>
                    <li>Review each draft question and pick the recommended answer.</li>
                    <li>Save the ones you like to the question bank as drafts or active.</li>
                </ol>
                <h4>Every question follows these rules</h4>
                <ul class="rules-list">
                    <li><?= icon('check', 16) ?>Single-choice, four answers</li>
                    <li><?= icon('check', 16) ?>Friendly wording aimed at pet owners</li>
                    <li><?= icon('check', 16) ?>Answers that help staff judge fit</li>
                </ul>
            </aside>
        </div>
<?php layout_main_end(); layout_bottom(); ?>
