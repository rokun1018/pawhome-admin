<?php
require __DIR__ . '/user-common.php';
$adopter = require_adopter();
$next = safe_next($_GET['next'] ?? '', '');
$quizLink = 'quiz.php' . ($next ? '?next=' . urlencode($next) : '');
$count = (int)$pdo->query("SELECT COUNT(*) FROM quiz_questions WHERE status = 'active'")->fetchColumn();
user_head('Account Created', ['account-created.css']);
?>
<body>
    <?php user_header(); ?>

    <!-- Main Content -->
    <main class="success-container">
        <div class="success-card">
            <div class="success-icon">✓</div>

            <h1>Account Created Successfully!</h1>

            <p class="subtitle">Welcome to PawHome, <?= e(explode(' ', $adopter['full_name'])[0]) ?>! Let's find your perfect pet match.</p>

            <p class="description">
                Complete a quick eligibility quiz to help our team understand your living environment and get personalized pet recommendations.
            </p>

            <div class="quiz-info">
                <span class="info-icon">🐾</span>
                <p>Takes about 2 minutes. You'll need it before applying to adopt.</p>
            </div>

            <div class="action-buttons">
                <?php if ($count): ?>
                    <button class="btn-start-quiz" type="button" onclick="goToPage('<?= e($quizLink) ?>')">Start Eligibility Quiz</button>
                <?php endif; ?>
                <button class="btn-skip" type="button" onclick="goToPage('<?= e($next ?: 'index.php') ?>')">Skip for now — Browse Pets</button>
            </div>

            <footer class="success-footer">
                <p>Copyright © <?= date('Y') ?> <?= e(settings()['org_name']) ?>. All rights reserved.</p>
            </footer>
        </div>
    </main>

    <?php user_scripts(); ?>
</body>
</html>
