<?php
// One pet's public profile (same layout pet-details.js used to build)
require __DIR__ . '/user-common.php';
$adopter = current_adopter();

$stmt = $pdo->prepare("SELECT " . PUBLIC_PET_COLUMNS . ", s.area AS shelter_area FROM pets p LEFT JOIN shelters s ON s.id = p.shelter_id WHERE p.id = ?");
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$pet = $stmt->fetch();
if (!$pet) {
    user_flash('That pet could not be found.', 'error');
    redirect('index.php');
}
$traits = pet_traits($pet['traits']);

// Has this adopter already applied for this pet?
$mine = null;
if ($adopter) {
    $q = $pdo->prepare("SELECT id, status FROM applications WHERE adopter_id = ? AND pet_id = ? ORDER BY created_at DESC LIMIT 1");
    $q->execute([$adopter['id'], $pet['id']]);
    $mine = $q->fetch();
}

user_head($pet['name'], ['pet-details.css']);
?>
<body>
    <?php user_header(); ?>

    <!-- Pet Details -->
    <main class="pet-details-container">
        <button class="btn-back" type="button" onclick="goToPage('index.php')">← Back to Dashboard</button>

        <div class="pet-details-content" id="petDetailsContent">
            <div class="pet-details-header">
                <div class="pet-details-image">
                    <img src="<?= e(pet_image($pet)) ?>" alt="<?= e($pet['name']) ?>" loading="lazy">
                </div>
                <div class="pet-details-info">
                    <h1><?= e($pet['name']) ?></h1>
                    <p class="pet-details-breed"><?= e($pet['breed'] ?: label($pet['species'])) ?></p>

                    <div class="button-actions">
                        <?php if ($mine && in_array($mine['status'], ['pending', 'review'], true)): ?>
                            <button class="detail-btn" type="button" onclick="goToPage('profile.php?tab=applications')">Application Pending — View Status</button>
                        <?php elseif ($pet['status'] === 'available'): ?>
                            <button class="detail-btn" type="button" onclick="goToPage('adoption-application.php?pet=<?= (int)$pet['id'] ?>')">Apply to Adopt</button>
                        <?php elseif ($pet['status'] === 'adopted'): ?>
                            <button class="detail-btn" type="button" disabled>Already Adopted 🏡</button>
                        <?php else: ?>
                            <button class="detail-btn" type="button" disabled>Not Available Right Now</button>
                        <?php endif; ?>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Age</div>
                        <div class="detail-value"><?= e(pet_age_label((int)$pet['age_months'])) ?> Old</div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Size</div>
                        <div class="detail-value"><?= e(label($pet['size'])) ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="detail-label">Species</div>
                        <div class="detail-value"><?= e(label($pet['species'])) ?> · <?= e(label($pet['gender'])) ?></div>
                    </div>

                    <?php if ($traits): ?>
                    <div class="pet-details-traits">
                        <?php foreach ($traits as $t): ?><span class="trait-badge"><?= e($t) ?></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pet-details-body">
                <div class="detail-section">
                    <h3>About <?= e($pet['name']) ?></h3>
                    <p><?= $pet['description'] ? nl2br(e($pet['description'])) : e($pet['name']) . ' is waiting to meet you. Ask our team for more about their personality.' ?></p>
                </div>

                <?php if ($traits): ?>
                <div class="detail-section">
                    <h3>Personality</h3>
                    <p><?= e(implode(', ', $traits)) ?></p>
                </div>
                <?php endif; ?>

                <div class="detail-section">
                    <h3>Adoption Information</h3>
                    <p><strong>Adoption Fee:</strong> <?= $pet['adoption_fee'] !== null ? e(money($pet['adoption_fee'])) : 'Ask our team' ?></p>
                    <p><strong>Health Status:</strong> <?= e($pet['health_notes'] ?: 'Checked by our vet') ?></p>
                    <?php if ($pet['shelter_name']): ?><p><strong>Lives at:</strong> <?= e($pet['shelter_name']) ?><?= $pet['shelter_area'] ? ', ' . e($pet['shelter_area']) : '' ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php user_scripts(); ?>
</body>
</html>
