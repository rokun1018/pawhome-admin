<?php
// =====================================================================
//  Eligibility quiz. Step 1 asks what kind of pet the person wants (from
//  your design); the rest are the ACTIVE questions from the admin panel's
//  Quiz Management, so changing them there changes this quiz.
// =====================================================================
require __DIR__ . '/user-common.php';
$adopter = require_adopter();
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');

const PET_CHOICES = ['dog' => 'Dog', 'cat' => 'Cat', 'small' => 'Small Animal', 'any' => 'Any / Open'];
const MAX_QUESTIONS = 10;

// Which question pet types fit each choice
function quiz_types_for(string $choice): array
{
    return ['dog' => ['all', 'dog'], 'cat' => ['all', 'cat'], 'small' => ['all', 'rabbit', 'bird']][$choice]
        ?? ['all', 'dog', 'cat', 'rabbit', 'bird'];
}

$all = $pdo->query("SELECT id, question_text, pet_type, options, recommended_option FROM quiz_questions
                    WHERE status = 'active' ORDER BY category, id")->fetchAll();
foreach ($all as &$q) $q['options'] = json_decode($q['options'], true) ?: [];
unset($q);

// ---------- Submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $choice = $_POST['pet_type'] ?? '';
    if (!isset(PET_CHOICES[$choice])) {
        user_flash('Please choose the kind of pet you are interested in.', 'error');
        redirect('quiz.php');
    }
    $types = quiz_types_for($choice);
    $asked = array_slice(array_values(array_filter($all, fn($q) => in_array($q['pet_type'], $types, true))), 0, MAX_QUESTIONS);

    $answers = [];
    $correct = 0;
    foreach ($asked as $q) {
        $pick = $_POST['q'][$q['id']] ?? null;
        if ($pick === null || !ctype_digit((string)$pick) || (int)$pick >= count($q['options'])) {
            user_flash('Please answer every question.', 'error');
            redirect('quiz.php' . ($next ? '?next=' . urlencode($next) : ''));
        }
        $answers[(string)$q['id']] = (int)$pick;
        if ((int)$pick === (int)$q['recommended_option']) $correct++;
    }
    $score = $asked ? (int)round($correct / count($asked) * 100) : 100;

    $pdo->prepare('UPDATE adopters SET quiz_score = ?, quiz_answers = ?, quiz_pet_type = ?, quiz_taken_at = NOW() WHERE id = ?')
        ->execute([$score, json_encode($answers, JSON_FORCE_OBJECT), $choice, $adopter['id']]);
    redirect('quiz-result.php' . ($next ? '?next=' . urlencode($next) : ''));
}

user_head('Eligibility Quiz', ['quize.css']);
?>
<body>
    <div class="page">
        <div class="nav">
            <a class="logo" href="index.php">
                <div class="lo-img"><img src="pic/logo-icon.png" alt=""></div>
                <div class="lo-name">PawHome</div>
            </a>

            <div class="links">
                <a href="index.php">Dashboard</a>
                <div class="apply"><a href="adoption-application.php" class="adopt">Apply to Adopt</a></div>
            </div>
            <div class="profile">
                <a class="pro1" href="profile.php?tab=applications" title="My applications"><span>♧</span></a>
                <a class="pro2" href="profile.php" title="My profile"><i>👨🏻</i></a>
            </div>
        </div>

        <?= user_flash_html() ?>

        <div class="layout">
            <div class="quize">
                <div class="why">
                    <h3>Why Take the Quiz? 🐶</h3>
                </div>
                <p>At PawHome, we strive to build lifelong connections. This 2-minute quiz aligns your home environment
                    and experience level with shelter pets best suited to your lifestyle.</p>
                <hr>
                <div class="look"><b>What we look for:</b></div>
                <ul>
                    <li>Yard and fence availability</li>
                    <li>Existing household dynamics</li>
                    <li>Previous experience and training comfort</li>
                </ul>
            </div>

            <form class="right-lay" id="quizForm" method="post" action="quiz.php">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="progress-box">
                    <div class="progress-top">
                        <div class="text"><b>Quiz Progress</b></div>
                        <span id="quizProgressText">Question 1</span>
                    </div>
                    <div class="progress-bar" id="quizProgressBar"></div>
                </div>

                <!-- Step 1: what kind of pet (your design's question) -->
                <div class="question" data-step data-first>
                    <h1>What type of pet are you interested in?</h1>
                    <div class="options">
                        <?php $icons = ['pic/icon-bg.png', 'pic/icon-bg1.png', 'pic/icon-bg(1).png', 'pic/icon-bg(2).png']; $i = 0;
                        foreach (PET_CHOICES as $value => $text): ?>
                        <label class="option">
                            <input type="radio" name="pet_type" value="<?= $value ?>" <?= ($adopter['quiz_pet_type'] ?? '') === $value ? 'checked' : '' ?>>
                            <div class="icon"><img src="<?= e($icons[$i++]) ?>" alt=""></div>
                            <span><?= e($text) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn">
                        <button class="next" type="button" data-next>Next <b>→</b></button>
                    </div>
                </div>

                <!-- Questions from the admin quiz bank -->
                <?php foreach ($all as $q): ?>
                <div class="question" data-step data-pet-type="<?= e($q['pet_type']) ?>" hidden>
                    <h1><?= e($q['question_text']) ?></h1>
                    <p class="q-hint">Choose the answer that fits you best.</p>
                    <div class="options">
                        <?php foreach ($q['options'] as $n => $opt): ?>
                        <label class="option">
                            <input type="radio" name="q[<?= (int)$q['id'] ?>]" value="<?= $n ?>" disabled>
                            <div class="icon"><b><?= chr(65 + $n) ?></b></div>
                            <span><?= e($opt) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn1">
                        <button class="next" type="button" data-back><b>←</b> Back</button>
                        <button class="next" type="button" data-next>Next <b>→</b></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
    </div>

    <script>const QUIZ_TYPES = <?= json_encode(['dog' => quiz_types_for('dog'), 'cat' => quiz_types_for('cat'), 'small' => quiz_types_for('small'), 'any' => quiz_types_for('any')]) ?>, QUIZ_MAX = <?= MAX_QUESTIONS ?>;</script>
    <?php user_scripts(['quiz.js']); ?>
</body>
</html>
