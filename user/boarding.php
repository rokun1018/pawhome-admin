<?php
// Boarding request: goes to the admin and staff panels as "Pending".
require __DIR__ . '/user-common.php';
$adopter = require_adopter();
$rate = (float)(settings()['boarding_rate'] ?? 0);

// Pets this person adopted from us can be picked from the list
$stmt = $pdo->prepare("SELECT p.id, p.name, p.breed, p.species FROM applications a JOIN pets p ON p.id = a.pet_id
                       WHERE a.adopter_id = ? AND a.status = 'approved' ORDER BY p.name");
$stmt->execute([$adopter['id']]);
$myPets = $stmt->fetchAll();

$error = '';
$v = ['pet' => '', 'pet_name' => '', 'species' => '', 'breed' => '', 'check_in' => '', 'check_out' => '',
      'special_notes' => '', 'contact_name' => '', 'contact_phone' => '', 'owner_phone' => $adopter['phone'] ?? ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));

    // Which pet?
    if ($v['pet'] !== '' && $v['pet'] !== 'other') {
        $chosen = array_values(array_filter($myPets, fn($p) => (string)$p['id'] === $v['pet']));
        if ($chosen) { $v['pet_name'] = $chosen[0]['name']; $v['species'] = $chosen[0]['species']; $v['breed'] = $chosen[0]['breed'] ?? ''; }
    }
    $today = date('Y-m-d');
    $in = DateTime::createFromFormat('Y-m-d', $v['check_in']);
    $out = DateTime::createFromFormat('Y-m-d', $v['check_out']);

    if ($v['pet'] === '') $error = 'Please choose which pet is staying with us.';
    elseif ($v['pet_name'] === '' || mb_strlen($v['pet_name']) > 80) $error = "Please enter your pet's name.";
    elseif (!in_array($v['species'], ['dog', 'cat', 'rabbit', 'bird', 'other'], true)) $error = 'Please choose what kind of pet it is.';
    elseif (!$in || !$out) $error = 'Please choose both boarding dates.';
    elseif ($v['check_in'] < $today) $error = 'The start date cannot be in the past.';
    elseif ($v['check_out'] <= $v['check_in']) $error = 'The end date must be after the start date.';
    elseif ($in->diff($out)->days > 60) $error = 'For stays longer than 60 nights, please contact us directly.';
    elseif (!preg_match('/^[\d\s\-\+\(\)]{6,40}$/', $v['owner_phone'])) $error = 'Please enter a phone number we can reach you on.';
    elseif ($v['contact_name'] === '' || !preg_match('/^[\d\s\-\+\(\)]{6,40}$/', $v['contact_phone'])) $error = 'Please add an emergency contact name and phone number.';
    elseif (empty($_POST['agree'])) $error = 'Please agree to the Boarding & Liability Policy.';
    else {
        $pdo->prepare("INSERT INTO boarding (pet_name, species, breed, owner_name, owner_phone, owner_email, check_in, check_out,
                                             status, special_notes, emergency_contact, adopter_id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)")
            ->execute([$v['pet_name'], $v['species'], mb_substr($v['breed'], 0, 80) ?: null, $adopter['full_name'], $v['owner_phone'],
                       $adopter['email'], $v['check_in'], $v['check_out'], mb_substr($v['special_notes'], 0, 1000) ?: null,
                       mb_substr($v['contact_name'] . ', ' . $v['contact_phone'], 0, 200), $adopter['id']]);
        if (!$adopter['phone']) $pdo->prepare('UPDATE adopters SET phone = ? WHERE id = ?')->execute([$v['owner_phone'], $adopter['id']]);
        log_activity('New boarding request', $v['pet_name'] . ', ' . fmt_date($v['check_in']) . ' (from ' . $adopter['full_name'] . ')');
        user_flash('Boarding request sent for ' . $v['pet_name'] . '! We will confirm it soon.');
        redirect('profile.php?tab=applications');
    }
}

user_head('Boarding Care', ['boarding.css']);
?>
<body>
    <?php user_header('boarding.php'); ?>

    <!-- Main Content -->
    <main class="boarding-container">
        <div class="boarding-content">
            <!-- Left Section - Form -->
            <div class="boarding-form-section">
                <h2>Request Boarding Care</h2>
                <p class="subtitle">Safe, warm, and playful care for your pet when you need it most.</p>

                <?php if ($error): ?><div class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>

                <form class="boarding-form" id="boardingForm" method="post" action="boarding.php" data-rate="<?= e($rate) ?>" data-currency="<?= e(settings()['currency'] ?? '৳') ?>">
                    <?= csrf_field() ?>
                    <!-- Pet Selection -->
                    <div class="form-group">
                        <label for="petSelect">Which pet is staying with us?</label>
                        <select id="petSelect" name="pet" class="form-select" required>
                            <option value="">Select a pet...</option>
                            <?php foreach ($myPets as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $v['pet'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e($p['breed'] ?: label($p['species'])) ?>)</option>
                            <?php endforeach; ?>
                            <option value="other" <?= $v['pet'] === 'other' ? 'selected' : '' ?>><?= $myPets ? 'Another pet' : 'My pet (enter details)' ?></option>
                        </select>
                    </div>

                    <!-- Shown when "Another pet" is chosen -->
                    <div id="otherPet" <?= $v['pet'] === 'other' ? '' : 'hidden' ?>>
                        <div class="form-group">
                            <label for="petName">Pet's name</label>
                            <input type="text" id="petName" name="pet_name" class="form-input" maxlength="80" placeholder="e.g. Tommy" value="<?= e($v['pet'] === 'other' ? $v['pet_name'] : '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Kind of pet and breed</label>
                            <div class="contact-group">
                                <select name="species" class="form-select" aria-label="Kind of pet">
                                    <option value="">Kind of pet...</option>
                                    <?php foreach (['dog' => 'Dog', 'cat' => 'Cat', 'rabbit' => 'Rabbit', 'bird' => 'Bird', 'other' => 'Other'] as $k => $t): ?>
                                        <option value="<?= $k ?>" <?= $v['pet'] === 'other' && $v['species'] === $k ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="breed" class="form-input" maxlength="80" placeholder="Breed (optional)" aria-label="Breed" value="<?= e($v['pet'] === 'other' ? $v['breed'] : '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-group">
                        <label>Select Boarding Dates</label>
                        <div class="date-group">
                            <div class="date-input">
                                <span class="calendar-icon">📅</span>
                                <input type="date" id="startDate" name="check_in" class="form-input" min="<?= date('Y-m-d') ?>" required aria-label="Start date" value="<?= e($v['check_in']) ?>">
                            </div>
                            <div class="date-input">
                                <span class="calendar-icon">📅</span>
                                <input type="date" id="endDate" name="check_out" class="form-input" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required aria-label="End date" value="<?= e($v['check_out']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Special Instructions -->
                    <div class="form-group">
                        <label for="specialCare">Special Care & Dietary Instructions</label>
                        <textarea class="form-textarea" id="specialCare" name="special_notes" maxlength="1000" placeholder="Eats 2 times a day. Please keep active with fetch toys. Has medication in the morning."><?= e($v['special_notes']) ?></textarea>
                    </div>

                    <!-- Owner phone -->
                    <div class="form-group">
                        <label for="ownerPhone">Your phone number</label>
                        <input type="tel" id="ownerPhone" name="owner_phone" class="form-input" maxlength="40" required placeholder="+880 1XXX-XXXXXX" value="<?= e($v['owner_phone']) ?>">
                    </div>

                    <!-- Emergency Contact -->
                    <div class="form-group">
                        <label>Emergency Contact Details</label>
                        <div class="contact-group">
                            <input type="text" class="form-input" name="contact_name" placeholder="Name: Jane Doe" id="contactName" maxlength="100" required value="<?= e($v['contact_name']) ?>">
                            <input type="tel" class="form-input" name="contact_phone" placeholder="Phone: +880 1XXX-XXXXXX" id="contactPhone" maxlength="40" required value="<?= e($v['contact_phone']) ?>">
                        </div>
                    </div>

                    <!-- Agreement -->
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="agreeTerms" name="agree" value="1" required>
                            <span>I agree to the PawHome Boarding & Liability Policy agreement.</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">Submit Boarding Request</button>
                </form>
            </div>

            <!-- Right Section - Summary -->
            <div class="boarding-summary-section">
                <div class="summary-card">
                    <h3>Boarding Plan Summary</h3>

                    <div class="summary-item">
                        <span>Est. Boarding duration</span>
                        <span id="duration">Choose dates</span>
                    </div>

                    <div class="summary-item">
                        <span>Daily care package cost</span>
                        <span id="dailyPrice"><?= $rate > 0 ? e(money($rate)) . ' / night' : 'Confirmed by our team' ?></span>
                    </div>

                    <div class="summary-item">
                        <span>Special medication charge</span>
                        <span>Included</span>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-total">
                        <span>Estimated Total</span>
                        <span id="totalPrice">–</span>
                    </div>

                    <div class="activity-schedule">
                        <h4>Activity Schedule Includes:</h4>
                        <ul>
                            <li>✓ 3 daily outdoor bathroom breaks</li>
                            <li>✓ 1-on-1 socialization playtime</li>
                            <li>✓ Evening cuddle session & bedtime check</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php user_scripts(['boarding.js']); ?>
</body>
</html>
