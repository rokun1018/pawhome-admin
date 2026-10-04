<?php
// =====================================================================
//  Adoption application (3 steps). The questions are defined here once;
//  js/adoption-application.js draws them, and this file checks the answers.
//  A submitted application appears in the admin and staff panels as Pending.
// =====================================================================
require __DIR__ . '/user-common.php';
$adopter = require_adopter();

// ---------- The questions (same as your design) ----------
$phases = [
    ['id' => 1, 'title' => 'Living Situation', 'description' => 'Tell us about your home & life', 'questions' => [
        ['id' => 'homeType', 'type' => 'buttons', 'label' => 'What is your home type?', 'options' => [
            ['label' => 'House with Yard', 'value' => 'house-yard'], ['label' => 'Apartment / Condo', 'value' => 'apartment'],
            ['label' => 'Townhouse', 'value' => 'townhouse'], ['label' => 'Other', 'value' => 'other']]],
        ['id' => 'fencedYard', 'type' => 'buttons', 'label' => 'Do you have a fenced yard?', 'options' => [
            ['label' => 'Yes, fully fenced', 'value' => 'yes-fenced'], ['label' => 'No fence', 'value' => 'no-fence'], ['label' => 'No yard', 'value' => 'no-yard']]],
        ['id' => 'petExperience', 'type' => 'buttons', 'label' => 'What is your experience level with pets?', 'options' => [
            ['label' => 'First-time Owner', 'value' => 'first-time'], ['label' => 'Experienced Parent', 'value' => 'experienced'], ['label' => 'Expert / Trainer', 'value' => 'expert']]],
        ['id' => 'lifestyle', 'type' => 'quiz', 'label' => 'Quick Lifestyle Match Quiz', 'subtitle' => "Which phrase best describes your household's weekend energy?", 'options' => [
            ['label' => 'Cozy Couch Potato', 'value' => 'cozy', 'icon' => '🛋️', 'desc' => 'We love movies, indoor games, and long naps.'],
            ['label' => 'Active Hiker', 'value' => 'active', 'icon' => '🥾', 'desc' => 'We head out to nature trails, walks, or play fetch.'],
            ['label' => 'Busy & Chaotic', 'value' => 'chaotic', 'icon' => '⚡', 'desc' => 'Full house with kids, visitors, and constant action.']]],
    ]],
    ['id' => 2, 'title' => 'Household Details', 'description' => 'Tell us more about your household', 'questions' => [
        ['id' => 'householdMembers', 'type' => 'buttons', 'label' => 'How many people live in your household?', 'options' => [
            ['label' => 'Just me', 'value' => '1'], ['label' => '2-3 people', 'value' => '2-3'], ['label' => '4-5 people', 'value' => '4-5'], ['label' => '6+ people', 'value' => '6+']]],
        ['id' => 'childrenAges', 'type' => 'buttons', 'label' => 'Do you have children? If yes, what ages?', 'options' => [
            ['label' => 'No children', 'value' => 'no'], ['label' => 'Under 5 years', 'value' => 'under5'], ['label' => '5-12 years', 'value' => '5-12'], ['label' => '13+ years', 'value' => '13plus']]],
        ['id' => 'otherPets', 'type' => 'buttons', 'label' => 'Do you currently have other pets?', 'options' => [
            ['label' => 'No other pets', 'value' => 'no'], ['label' => 'Yes, dogs', 'value' => 'dogs'], ['label' => 'Yes, cats', 'value' => 'cats'], ['label' => 'Yes, other animals', 'value' => 'other']]],
        ['id' => 'workSchedule', 'type' => 'buttons', 'label' => 'What is your typical work schedule?', 'options' => [
            ['label' => 'Work from home', 'value' => 'home'], ['label' => 'Part-time (flexible)', 'value' => 'part-time'],
            ['label' => 'Full-time (8-9 hours daily)', 'value' => 'full-time'], ['label' => 'Multiple jobs / variable', 'value' => 'variable']]],
    ]],
    ['id' => 3, 'title' => 'Personal Information', 'description' => 'Contact & commitment details', 'questions' => [
        ['id' => 'fullName', 'type' => 'text', 'label' => 'Full Name', 'placeholder' => 'Enter your full name'],
        ['id' => 'email', 'type' => 'email', 'label' => 'Email Address', 'placeholder' => 'your.email@example.com'],
        ['id' => 'phone', 'type' => 'tel', 'label' => 'Phone Number', 'placeholder' => '+880 1XXX-XXXXXX'],
        ['id' => 'address', 'type' => 'text', 'label' => 'Home Address', 'placeholder' => 'House, Road, Area, City'],
        ['id' => 'vetReference', 'type' => 'text', 'label' => 'Previous Pet Vet Reference (if applicable)', 'placeholder' => 'Vet name, phone, and years of care', 'optional' => true],
        ['id' => 'agreement', 'type' => 'checkbox', 'label' => 'I understand the commitment required and agree to provide proper care'],
        ['id' => 'returnPolicy', 'type' => 'checkbox', 'label' => 'I agree to return the pet to PawHome if I can no longer provide care'],
    ]],
];

function available_pet(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, breed, species FROM pets WHERE id = ? AND status = 'available'");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function pending_application(int $adopterId, int $petId): bool
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM applications WHERE adopter_id = ? AND pet_id = ? AND status IN ('pending','review')");
    $stmt->execute([$adopterId, $petId]);
    return (bool)$stmt->fetchColumn();
}

// ---------- Submit (sent by js/adoption-application.js) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_response(['ok' => false, 'error' => 'This page expired. Please refresh it and try again.'], 400);
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $answers = (array)($in['answers'] ?? []);
    $pet = available_pet((int)($in['pet_id'] ?? 0));
    if (!$pet) json_response(['ok' => false, 'error' => 'Sorry, this pet is no longer available. Please choose another one.'], 400);
    if (pending_application((int)$adopter['id'], (int)$pet['id'])) {
        json_response(['ok' => false, 'error' => 'You already have an application for ' . $pet['name'] . ' waiting for review.'], 409);
    }
    if (!$adopter['quiz_taken_at']) json_response(['ok' => false, 'error' => 'Please take the eligibility quiz first.'], 400);

    // Check every answer against the questions above, and keep readable labels for staff
    $details = [];
    foreach ($phases as $phase) {
        foreach ($phase['questions'] as $q) {
            $value = $answers[$q['id']] ?? null;
            if ($q['type'] === 'checkbox') {
                if ($value !== true) json_response(['ok' => false, 'error' => 'Please agree to: ' . $q['label']], 400);
                continue;
            }
            if (isset($q['options'])) {
                $match = array_values(array_filter($q['options'], fn($o) => $o['value'] === $value));
                if (!$match) json_response(['ok' => false, 'error' => 'Please answer: ' . $q['label']], 400);
                $details[$q['label']] = $match[0]['label'];
                continue;
            }
            $value = trim((string)$value);
            if ($value === '' && empty($q['optional'])) json_response(['ok' => false, 'error' => 'Please answer: ' . $q['label']], 400);
            if (mb_strlen($value) > 255) json_response(['ok' => false, 'error' => $q['label'] . ' is too long.'], 400);
            if ($q['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 400);
            if ($q['type'] === 'tel' && !preg_match('/^[\d\s\-\+\(\)]{6,40}$/', $value)) json_response(['ok' => false, 'error' => 'Please enter a valid phone number.'], 400);
            if ($value !== '') $details[$q['label']] = $value;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO applications (pet_id, adopter_id, applicant_name, applicant_email, applicant_phone, home_type,
                                quiz_score, quiz_answers, details, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending') RETURNING id");
    $stmt->execute([
        $pet['id'], $adopter['id'], trim($answers['fullName']), strtolower(trim($answers['email'])), trim($answers['phone']),
        $details['What is your home type?'], (int)$adopter['quiz_score'], $adopter['quiz_answers'],
        json_encode($details, JSON_UNESCAPED_UNICODE),
    ]);
    $id = (int)$stmt->fetchColumn();
    log_activity('New adoption application', trim($answers['fullName']) . ' for ' . $pet['name']);
    json_response(['ok' => true, 'id' => $id]);
}

// ---------- Show the form ----------
$petId = (int)($_GET['pet'] ?? 0);
$pet = $petId ? available_pet($petId) : null;
if ($petId && !$pet) {
    user_flash('Sorry, that pet is no longer available. Please choose another one.', 'error');
    redirect('index.php');
}
if ($pet && pending_application((int)$adopter['id'], (int)$pet['id'])) {
    user_flash('You already applied for ' . $pet['name'] . '. You can follow it here.');
    redirect('profile.php?tab=applications');
}
// The eligibility quiz comes first; it brings the person back here afterwards
if (!$adopter['quiz_taken_at']) {
    user_flash('Please take the short eligibility quiz first. It brings you straight back to your application.');
    redirect('quiz.php?next=' . urlencode('adoption-application.php' . ($pet ? '?pet=' . $pet['id'] : '')));
}

// No pet chosen yet: add a "Which pet?" question at the top
$choices = [];
if (!$pet) {
    $choices = $pdo->query("SELECT id, name, breed, species FROM pets WHERE status = 'available' ORDER BY name")->fetchAll();
    if (!$choices) {
        user_flash('No pets are available for adoption right now. Please check back soon!', 'error');
        redirect('index.php');
    }
    array_unshift($phases[0]['questions'], ['id' => 'petId', 'type' => 'select', 'label' => 'Which pet would you like to adopt?',
        'options' => array_map(fn($c) => ['label' => $c['name'] . ' (' . ($c['breed'] ?: label($c['species'])) . ')', 'value' => (string)$c['id']], $choices)]);
}

$prefill = ['fullName' => $adopter['full_name'], 'email' => $adopter['email'], 'phone' => $adopter['phone'] ?? '', 'address' => $adopter['address'] ?? ''];
user_head('Adoption Application', ['adoption-application.css']);
?>
<body>
    <?php user_header('adoption-application.php'); ?>

    <!-- Main Content -->
    <main class="adoption-container">
        <?php if ($pet): ?>
            <p style="margin-bottom:1rem;color:#555">Applying to adopt <strong><?= e($pet['name']) ?></strong> (<?= e($pet['breed'] ?: label($pet['species'])) ?>). <a href="pet-details.php?id=<?= $pet['id'] ?>">View profile</a></p>
        <?php endif; ?>

        <!-- Progress Indicator -->
        <div class="progress-section">
            <h2>Adoption Progress</h2>
            <div class="progress-steps" id="progressSteps"></div>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Form Content -->
        <div class="form-section">
            <div id="formContainer"></div>

            <div id="applicationError" class="error-message" role="alert" hidden></div>

            <!-- Navigation Buttons -->
            <div class="form-buttons">
                <button type="button" class="btn-back-form" id="prevBtn" onclick="previousStep()" style="display: none;">Back</button>
                <button type="button" class="btn-next" id="nextBtn" onclick="nextStep()">Next Step</button>
            </div>
        </div>
    </main>

    <script>
        const ADOPTION_PHASES = <?= json_encode($phases, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        const ADOPTION_PREFILL = <?= json_encode($prefill, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        const ADOPTION_PET_ID = <?= $pet ? (int)$pet['id'] : 'null' ?>;
    </script>
    <?php user_scripts(['adoption-application.js']); ?>
</body>
</html>
