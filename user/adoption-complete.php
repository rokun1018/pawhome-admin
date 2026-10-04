<?php
// Shown after an application is sent (and from the profile). Only the owner can see it.
require __DIR__ . '/user-common.php';
$adopter = require_adopter();

// Pet columns first, then the application's own id/status/date under clear names
$stmt = $pdo->prepare("SELECT " . PUBLIC_PET_COLUMNS . ", s.area AS shelter_area, s.address AS shelter_address,
                              a.id AS app_id, a.status AS app_status, a.created_at AS app_created
                       FROM applications a LEFT JOIN pets p ON p.id = a.pet_id LEFT JOIN shelters s ON s.id = p.shelter_id
                       WHERE a.id = ? AND a.adopter_id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0), $adopter['id']]);
$app = $stmt->fetch();
if (!$app) redirect('profile.php?tab=applications');

$org = settings();
$approved = $app['app_status'] === 'approved';
$rejected = $app['app_status'] === 'rejected';
$phone = $org['contact_phone'] ?: '';
$email = $org['contact_email'];

// General pickup tips by kind of pet
$tips = [
    'dog' => ['Bring a carrier or crate that fits comfortably', 'Bring a proper-fitting collar and leash', 'Ask us which food they eat now, to avoid tummy upsets', 'Bring a blanket or toy for the car ride'],
    'cat' => ['Bring a well-ventilated cat carrier', 'Set up a litter box and a quiet room before pickup', 'Ask us which food they eat now', 'Keep them indoors for the first two weeks'],
][$app['species']] ?? ['Bring a safe carrier for the trip home', 'Ask us about their current food and routine', 'Prepare a quiet space for the first few days'];

user_head('Application Submitted', ['adoption-complete.css']);
?>
<body>
    <?php user_header(); ?>

    <!-- Main Content -->
    <main class="complete-container">
        <div class="complete-content">
            <!-- Success Section -->
            <div class="success-section">
                <div class="success-icon"><?= $rejected ? 'ℹ' : '✓' ?></div>
                <?php if ($approved): ?>
                    <h1>Congratulations! You're All Set! 🎉</h1>
                    <p class="success-subtitle">Your adoption application has been approved. We'll call you to book a pickup time.</p>
                <?php elseif ($rejected): ?>
                    <h1>Thank You for Applying</h1>
                    <p class="success-subtitle">This application wasn't approved this time. Our team is happy to talk about other pets that may suit you.</p>
                <?php else: ?>
                    <h1>Application Submitted! 🎉</h1>
                    <p class="success-subtitle">Your adoption application has been submitted successfully. Our team will review it and get back to you.</p>
                <?php endif; ?>

                <div class="pet-summary" id="petSummary">
                    <?php if ($app['name']): ?>
                    <div class="pet-summary-item">
                        <div class="pet-image-card">
                            <img src="<?= e(pet_image($app)) ?>" alt="<?= e($app['name']) ?>" loading="lazy">
                        </div>
                        <div class="pet-info">
                            <h3><?= e($app['name']) ?></h3>
                            <p><?= e($app['breed'] ?: label($app['species'])) ?> • <?= e(pet_age_label((int)$app['age_months'])) ?> Old</p>
                            <p>Application #<?= (int)$app['app_id'] ?> · Sent <?= e(fmt_date($app['app_created'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pickup Details Section -->
            <div class="pickup-section">
                <h2><?= $approved ? 'Pickup Details' : 'What Happens Next' ?></h2>

                <div class="pickup-grid">
                    <div class="pickup-card">
                        <div class="card-icon">📍</div>
                        <h3>Location</h3>
                        <p class="card-label">Pickup Address</p>
                        <p class="card-value" id="pickupLocation">
                            <?= e($app['shelter_name'] ?: $org['org_name']) ?>
                            <?php if ($app['shelter_address']): ?><br><?= nl2br(e($app['shelter_address'])) ?><?php elseif ($app['shelter_area']): ?><br><?= e($app['shelter_area']) ?><?php endif; ?>
                        </p>
                    </div>

                    <div class="pickup-card">
                        <div class="card-icon">📅</div>
                        <h3>Pickup Date & Time</h3>
                        <p class="card-label">Status: <?= $app['app_status'] === 'review' ? 'Under review' : e(label($app['app_status'])) ?></p>
                        <p class="card-value" id="pickupDateTime">
                            <?= $approved ? 'Our team will call you to choose a day and time that suits you.'
                                : ($rejected ? 'No pickup for this application.' : 'You can book a pickup once your application is approved. We usually reply within a few days.') ?>
                        </p>
                    </div>

                    <div class="pickup-card">
                        <div class="card-icon">📞</div>
                        <h3>Contact Information</h3>
                        <p class="card-label">Questions? Reach Out</p>
                        <p class="card-value">
                            <?php if ($phone): ?><strong>Phone:</strong> <?= e($phone) ?><br><?php endif; ?>
                            <strong>Email:</strong> <?= e($email) ?>
                        </p>
                        <a class="card-link-btn" href="mailto:<?= e($email) ?>?subject=<?= rawurlencode('Application #' . $app['app_id']) ?>">Contact Us →</a>
                    </div>
                </div>
            </div>

            <!-- Important Notes & Requirements -->
            <?php if (!$rejected): ?>
            <div class="requirements-section">
                <h2>Important Notes & Requirements</h2>

                <div class="requirements-box">
                    <h3>What to Bring on Pickup Day</h3>
                    <ul class="requirement-list">
                        <li><span class="req-icon">✓</span><span class="req-text"><strong>Valid ID & Proof of Residence</strong> - National ID or passport, and proof of your current address</span></li>
                        <li><span class="req-icon">✓</span><span class="req-text"><strong>Pet Carrier or Travel Crate</strong> - For safe transport of your new pet home</span></li>
                        <li><span class="req-icon">✓</span><span class="req-text"><strong>Adoption Fee</strong> - <?= $app['adoption_fee'] !== null ? e(money($app['adoption_fee'])) : 'Our team will confirm the amount' ?></span></li>
                    </ul>
                </div>

                <div class="requirements-box warning">
                    <h3>Important Information</h3>
                    <ul class="requirement-list">
                        <?php if ($app['health_notes']): ?><li><span class="req-icon info">ℹ️</span><span class="req-text">Health: <?= e($app['health_notes']) ?></span></li><?php endif; ?>
                        <li><span class="req-icon info">ℹ️</span><span class="req-text">Allow your pet 7-10 days to adjust to their new home before introducing them to other pets</span></li>
                        <li><span class="req-icon info">ℹ️</span><span class="req-text">Schedule a vet visit within 2 weeks for a health checkup</span></li>
                        <li><span class="req-icon info">ℹ️</span><span class="req-text">If you have any concerns, contact our support team immediately</span></li>
                    </ul>
                </div>

                <div class="pet-notes" id="petSpecificNotes">
                    <div class="requirements-box">
                        <h3>Getting Ready for <?= e($app['name'] ?: 'Your Pet') ?></h3>
                        <ul class="pet-notes-list">
                            <?php foreach ($tips as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn-primary" type="button" onclick="goToPage('profile.php?tab=applications')">View My Applications</button>
                <button class="btn-secondary" type="button" onclick="goToPage('index.php')">Back to Dashboard</button>
            </div>

            <!-- Support Section -->
            <div class="support-section">
                <p><strong>Need Help?</strong> Our adoption team is here to support you!<?= $phone ? ' Call us at <strong>' . e($phone) . '</strong> or' : '' ?> email <strong><?= e($email) ?></strong></p>
            </div>

            <footer class="complete-footer">
                <p>Copyright © <?= date('Y') ?> <?= e($org['org_name']) ?>. All rights reserved.</p>
            </footer>
        </div>
    </main>

    <?php user_scripts(); ?>
</body>
</html>
