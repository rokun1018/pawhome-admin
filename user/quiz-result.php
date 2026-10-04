<?php
// Quiz result: the person's eligibility score and the available pets that suit them.
require __DIR__ . '/user-common.php';
$adopter = require_adopter();
if (!$adopter['quiz_taken_at']) redirect('quiz.php');
$next = safe_next($_GET['next'] ?? '', '');

$score = (int)$adopter['quiz_score'];
$passMark = (int)settings()['quiz_pass_mark'];
$choice = $adopter['quiz_pet_type'] ?: 'any';
$wanted = ['dog' => ['dog'], 'cat' => ['cat'], 'small' => ['rabbit', 'bird']][$choice] ?? null;

// Match = the quiz score, a bit lower for pets that aren't the kind they chose
$rows = $pdo->query("SELECT " . PUBLIC_PET_COLUMNS . " FROM pets p LEFT JOIN shelters s ON s.id = p.shelter_id
                     WHERE p.status = 'available' ORDER BY p.intake_date DESC, p.id DESC")->fetchAll();
$matches = [];
foreach ($rows as $p) {
    $fits = $wanted === null || in_array($p['species'], $wanted, true);
    $matches[] = ['pet' => $p, 'match' => (int)round($score * ($fits ? 1 : 0.8))];
}
usort($matches, fn($a, $b) => $b['match'] <=> $a['match']);
$matches = array_slice($matches, 0, 3);
$passed = $score >= $passMark;

user_head('Quiz Results & Matches', ['quiz-result.css']);
?>
<body>
    <?php user_header(); ?>

    <!-- Main Content -->
    <main class="results-container">
        <!-- Header Section -->
        <div class="results-header">
            <p class="results-tag"><?= $passed ? '✓ Compatibility Match Ready!' : 'Your quiz is saved' ?></p>
            <h1><?= $matches ? 'Your Perfect Matches!' : 'Thanks for Taking the Quiz!' ?></h1>
            <p class="results-subtitle">
                Your eligibility score is <strong><?= $score ?>%</strong>
                <?php if ($passed): ?>
                    (our pass mark is <?= $passMark ?>%). Based on your lifestyle quiz, here are the best companions currently waiting for forever families.
                <?php else: ?>
                    (our pass mark is <?= $passMark ?>%). You can still apply; our team reads every application and may ask you a few extra questions.
                <?php endif; ?>
            </p>
            <?php if ($next): ?>
                <p style="margin-top:1rem"><a class="btn-solid" href="<?= e($next) ?>">Continue My Application →</a></p>
            <?php endif; ?>
        </div>

        <!-- Matches Grid -->
        <div class="matches-grid" id="matchesGrid">
            <?php foreach ($matches as $m): $p = $m['pet']; ?>
            <div class="match-card">
                <div class="match-image">
                    <img src="<?= e(pet_image($p)) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                    <div class="match-badge"><?= $m['match'] ?>% Match</div>
                </div>
                <div class="match-card-info">
                    <div class="match-card-header">
                        <div>
                            <div class="match-name"><?= e($p['name']) ?></div>
                            <div class="match-breed"><?= e($p['breed'] ?: label($p['species'])) ?></div>
                        </div>
                        <div class="match-age"><?= e(pet_age_label((int)$p['age_months'])) ?></div>
                    </div>
                    <div class="match-traits">
                        <?php foreach (array_slice(pet_traits($p['traits']), 0, 3) as $t): ?><span class="match-trait"><?= e($t) ?></span><?php endforeach; ?>
                    </div>
                    <button class="match-cta" type="button" onclick="goToPage('pet-details.php?id=<?= (int)$p['id'] ?>')">View Profile</button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$matches): ?>
                <p style="grid-column:1/-1;text-align:center;color:#666">No pets are available right now. We'll have new friends soon!</p>
            <?php endif; ?>
        </div>

        <!-- Browse All Button -->
        <div class="browse-section">
            <button class="btn-browse-all" type="button" onclick="goToPage('index.php')">Browse All Pets</button>
            <p class="browse-subtitle">Not quite what you were looking for? <a href="quiz.php">Retake the quiz</a> anytime.</p>
        </div>
    </main>

    <?php user_scripts(); ?>
</body>
</html>
