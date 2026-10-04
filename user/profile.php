<?php
// My Profile: personal info, adoption history, applications & boarding, preferences.
require __DIR__ . '/user-common.php';
$adopter = require_adopter();
$id = (int)$adopter['id'];

// ---------- Saving ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $taken = $pdo->prepare('SELECT 1 FROM adopters WHERE email = ? AND id <> ?');
        $taken->execute([$email, $id]);
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) user_flash('Please enter your name and a valid email.', 'error');
        elseif ($taken->fetch()) user_flash('That email is already used by another account.', 'error');
        elseif ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]{6,40}$/', $phone)) user_flash('Please enter a valid phone number.', 'error');
        else {
            $pdo->prepare('UPDATE adopters SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?')
                ->execute([mb_substr($name, 0, 120), $email, $phone ?: null, mb_substr($address, 0, 255) ?: null, $id]);
            user_flash('Profile updated.');
        }
        redirect('profile.php');
    }

    if ($action === 'preferences') {
        $pdo->prepare('UPDATE adopters SET notify_adoption = ?, notify_matches = ?, notify_marketing = ? WHERE id = ?')
            ->execute([isset($_POST['notify_adoption']) ? 'true' : 'false', isset($_POST['notify_matches']) ? 'true' : 'false',
                       isset($_POST['notify_marketing']) ? 'true' : 'false', $id]);
        user_flash('Preferences saved.');
        redirect('profile.php?tab=preferences');
    }

    if ($action === 'password') {
        $stmt = $pdo->prepare('SELECT password FROM adopters WHERE id = ?');
        $stmt->execute([$id]);
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $stmt->fetchColumn())) user_flash('Your current password is not right.', 'error');
        elseif (strlen($new) < 8) user_flash('The new password must be at least 8 characters.', 'error');
        elseif ($new !== ($_POST['confirm_password'] ?? '')) user_flash('The two new passwords do not match.', 'error');
        else {
            $pdo->prepare('UPDATE adopters SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
            user_flash('Password changed.');
        }
        redirect('profile.php?tab=preferences');
    }

    if ($action === 'cancel_boarding') {
        $stmt = $pdo->prepare("UPDATE boarding SET status = 'cancelled' WHERE id = ? AND adopter_id = ? AND status = 'pending' RETURNING pet_name");
        $stmt->execute([(int)($_POST['id'] ?? 0), $id]);
        if ($pet = $stmt->fetchColumn()) {
            log_activity('Boarding request cancelled by owner', $pet . ' (' . $adopter['full_name'] . ')');
            user_flash('Boarding request for ' . $pet . ' cancelled.');
        }
        redirect('profile.php?tab=applications');
    }
    redirect('profile.php');
}

// ---------- Loading ----------
$apps = $pdo->prepare("SELECT " . PUBLIC_PET_COLUMNS . ", a.id AS app_id, a.status AS app_status, a.created_at AS app_created, a.decided_at
                       FROM applications a LEFT JOIN pets p ON p.id = a.pet_id LEFT JOIN shelters s ON s.id = p.shelter_id
                       WHERE a.adopter_id = ? ORDER BY a.created_at DESC");
$apps->execute([$id]);
$apps = $apps->fetchAll();
$adopted = array_values(array_filter($apps, fn($a) => $a['app_status'] === 'approved' && $a['name']));

$stays = $pdo->prepare('SELECT id, pet_name, check_in, check_out, status, kennel FROM boarding WHERE adopter_id = ? ORDER BY check_in DESC');
$stays->execute([$id]);
$stays = $stays->fetchAll();

const APP_STATUS = ['pending' => ['pending', 'Pending Review'], 'review' => ['pending', 'Under Review'], 'approved' => ['approved', 'Approved'], 'rejected' => ['rejected', 'Not Approved']];
const STAY_STATUS = ['pending' => ['pending', 'Waiting'], 'confirmed' => ['approved', 'Confirmed'], 'checked_in' => ['approved', 'Staying Now'],
                     'completed' => ['approved', 'Completed'], 'declined' => ['rejected', 'Declined'], 'cancelled' => ['rejected', 'Cancelled']];
$tab = in_array($_GET['tab'] ?? '', ['personal', 'adoption', 'applications', 'preferences'], true) ? $_GET['tab'] : 'personal';
$checked = fn($v) => $v ? 'checked' : '';
$passMark = (int)settings()['quiz_pass_mark'];

user_head('My Profile', ['profile.css']);
?>
<body>
    <?php user_header('profile.php'); ?>
    <?= user_flash_html() ?>

    <main class="profile-container">
        <div class="profile-header">
            <div class="profile-banner"></div>
            <div class="profile-hero">
                <div class="profile-picture" id="profilePicture"><?= e(initials($adopter['full_name'])) ?></div>
                <div class="profile-basic-info">
                    <h1 id="profileName"><?= e($adopter['full_name']) ?></h1>
                    <p class="profile-status" id="profileStatus">✓ Active Member</p>
                    <p class="profile-join-date" id="profileJoinDate">Joined: <?= e(date('F j, Y', strtotime($adopter['created_at']))) ?></p>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <aside class="profile-sidebar">
                <div class="profile-menu">
                    <a href="?tab=personal" class="menu-item <?= $tab === 'personal' ? 'active' : '' ?>" data-tab="personal">📋 Personal Information</a>
                    <a href="?tab=adoption" class="menu-item <?= $tab === 'adoption' ? 'active' : '' ?>" data-tab="adoption">🏠 Adoption History</a>
                    <a href="?tab=applications" class="menu-item <?= $tab === 'applications' ? 'active' : '' ?>" data-tab="applications">📝 My Applications</a>
                    <a href="?tab=preferences" class="menu-item <?= $tab === 'preferences' ? 'active' : '' ?>" data-tab="preferences">⚙️ Preferences</a>
                </div>
            </aside>

            <section class="profile-main">
                <!-- PERSONAL INFORMATION -->
                <div id="personal-info-tab" class="tab-content <?= $tab === 'personal' ? 'active' : '' ?>">
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Personal Information</h2>
                            <button class="btn-edit" type="button" id="editProfileBtn" onclick="editProfile()">✎ Edit</button>
                        </div>

                        <div class="info-grid" id="profileView">
                            <div class="info-item"><label>Full Name</label><p id="infoName"><?= e($adopter['full_name']) ?></p></div>
                            <div class="info-item"><label>Email Address</label><p id="infoEmail"><?= e($adopter['email']) ?></p></div>
                            <div class="info-item"><label>Phone Number</label><p id="infoPhone"><?= e($adopter['phone'] ?: '-') ?></p></div>
                            <div class="info-item"><label>Home Address</label><p id="infoAddress"><?= e($adopter['address'] ?: '-') ?></p></div>
                            <div class="info-item"><label>Eligibility Quiz</label>
                                <p><?php if ($adopter['quiz_taken_at']): ?>
                                    <span class="quiz-chip"><?= (int)$adopter['quiz_score'] ?>%</span>
                                    <?= (int)$adopter['quiz_score'] >= $passMark ? 'Passed' : 'Below pass mark' ?> · <a href="quiz-result.php">See matches</a> · <a href="quiz.php">Retake</a>
                                <?php else: ?>Not taken yet · <a href="quiz.php">Take the quiz</a><?php endif; ?></p>
                            </div>
                        </div>

                        <form method="post" action="profile.php" id="profileEdit" hidden>
                            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                            <div class="form-grid">
                                <div class="form-field"><label for="pfName">Full Name</label><input id="pfName" name="full_name" required maxlength="120" value="<?= e($adopter['full_name']) ?>"></div>
                                <div class="form-field"><label for="pfEmail">Email Address</label><input id="pfEmail" type="email" name="email" required value="<?= e($adopter['email']) ?>"></div>
                                <div class="form-field"><label for="pfPhone">Phone Number</label><input id="pfPhone" type="tel" name="phone" maxlength="40" value="<?= e($adopter['phone']) ?>"></div>
                                <div class="form-field"><label for="pfAddress">Home Address</label><input id="pfAddress" name="address" maxlength="255" value="<?= e($adopter['address']) ?>"></div>
                            </div>
                            <div class="form-actions-row">
                                <button type="submit" class="btn-solid">Save Changes</button>
                                <button type="button" class="btn-plain" onclick="editProfile(false)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ADOPTION HISTORY -->
                <div id="adoption-history-tab" class="tab-content <?= $tab === 'adoption' ? 'active' : '' ?>">
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Adoption History</h2>
                            <button class="btn-edit" type="button" onclick="window.location.href='boarding.php'">🏠 Boarding</button>
                        </div>

                        <div id="adoptionHistoryContent">
                            <?php if (!$adopted): ?>
                                <div class="empty-state">
                                    <p>📦 You haven't adopted any pets yet.</p>
                                    <a href="index.php" class="btn-browse">Browse Available Pets</a>
                                </div>
                            <?php else: ?>
                                <div class="adoption-list">
                                <?php foreach ($adopted as $a): ?>
                                    <div class="adoption-item">
                                        <div class="adoption-image"><img src="<?= e(pet_image($a)) ?>" alt="<?= e($a['name']) ?>" loading="lazy"></div>
                                        <div class="adoption-info">
                                            <h3><?= e($a['name']) ?></h3>
                                            <p><?= e($a['breed'] ?: label($a['species'])) ?></p>
                                            <p class="adoption-date">✓ Successfully Adopted<?= $a['decided_at'] ? ' on ' . e(date('F j, Y', strtotime($a['decided_at']))) : '' ?></p>
                                        </div>
                                        <button class="btn-view-pet" type="button" onclick="goToPage('boarding.php')">Book Boarding</button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- APPLICATIONS + BOARDING -->
                <div id="applications-tab" class="tab-content <?= $tab === 'applications' ? 'active' : '' ?>">
                    <div class="section-card">
                        <h2>My Applications</h2>
                        <div id="applicationsContent">
                            <?php if (!$apps): ?>
                                <div class="empty-state">
                                    <p>📋 No applications yet.</p>
                                    <a href="index.php" class="btn-browse">Start an Application</a>
                                </div>
                            <?php else: ?>
                                <div class="applications-list">
                                <?php foreach ($apps as $a): [$cls, $text] = APP_STATUS[$a['app_status']]; ?>
                                    <div class="application-item">
                                        <div class="app-status <?= $cls ?>"><?= $text ?></div>
                                        <div class="app-content">
                                            <h3><?= e($a['name'] ?: 'Pet no longer listed') ?></h3>
                                            <p><?= e($a['breed'] ?: ($a['species'] ? label($a['species']) : '')) ?></p>
                                            <p class="app-date">Submitted <?= e(date('F j, Y', strtotime($a['app_created']))) ?><?= $a['decided_at'] ? ' · Decided ' . e(date('F j, Y', strtotime($a['decided_at']))) : '' ?></p>
                                        </div>
                                        <button class="btn-view-app" type="button" onclick="goToPage('adoption-complete.php?id=<?= (int)$a['app_id'] ?>')">View Details</button>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h3 class="list-subtitle">Boarding Requests</h3>
                        <?php if (!$stays): ?>
                            <p>No boarding requests yet. <a href="boarding.php">Request boarding care</a></p>
                        <?php else: ?>
                            <div class="applications-list">
                            <?php foreach ($stays as $b): [$cls, $text] = STAY_STATUS[$b['status']]; ?>
                                <div class="application-item">
                                    <div class="app-status <?= $cls ?>"><?= $text ?></div>
                                    <div class="app-content">
                                        <h3><?= e($b['pet_name']) ?></h3>
                                        <p><?= e(date('F j', strtotime($b['check_in']))) ?> → <?= e(date('F j, Y', strtotime($b['check_out']))) ?></p>
                                        <?php if ($b['kennel'] && in_array($b['status'], ['confirmed', 'checked_in'], true)): ?><p class="app-date">Kennel <?= e($b['kennel']) ?></p><?php endif; ?>
                                    </div>
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <form method="post" action="profile.php" class="item-actions" onsubmit="return confirm('Cancel this boarding request?')">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="cancel_boarding"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn-link-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PREFERENCES -->
                <div id="preferences-tab" class="tab-content <?= $tab === 'preferences' ? 'active' : '' ?>">
                    <div class="section-card">
                        <h2>Preferences & Settings</h2>
                        <form method="post" action="profile.php">
                            <?= csrf_field() ?><input type="hidden" name="action" value="preferences">
                            <div class="preferences-group">
                                <label class="preference-item"><input type="checkbox" name="notify_adoption" <?= $checked($adopter['notify_adoption']) ?>><span>Receive adoption notifications</span></label>
                                <label class="preference-item"><input type="checkbox" name="notify_matches" <?= $checked($adopter['notify_matches']) ?>><span>Email updates on new matches</span></label>
                                <label class="preference-item"><input type="checkbox" name="notify_marketing" <?= $checked($adopter['notify_marketing']) ?>><span>Marketing emails</span></label>
                            </div>
                            <div class="form-actions-row" style="margin-top:1rem"><button type="submit" class="btn-solid">Save Preferences</button></div>
                        </form>
                    </div>

                    <div class="section-card">
                        <h2>Change Password</h2>
                        <form method="post" action="profile.php">
                            <?= csrf_field() ?><input type="hidden" name="action" value="password">
                            <div class="form-grid">
                                <div class="form-field"><label for="pwCurrent">Current Password</label><input id="pwCurrent" type="password" name="current_password" autocomplete="current-password" required></div>
                                <div class="form-field"><label for="pwNew">New Password</label><input id="pwNew" type="password" name="new_password" minlength="8" autocomplete="new-password" required></div>
                                <div class="form-field"><label for="pwConfirm">Confirm New Password</label><input id="pwConfirm" type="password" name="confirm_password" autocomplete="new-password" required></div>
                            </div>
                            <div class="form-actions-row"><button type="submit" class="btn-solid">Change Password</button></div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php user_scripts(['profile.js']); ?>
</body>
</html>
