<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $avatar = uploaded_image('avatar', 1024 * 1024);
        $taken = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $taken->execute([$email, $user['id']]);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Enter your name and a valid email.', 'error');
        } elseif ($taken->fetch()) {
            flash('That email already belongs to another account.', 'error');
        } elseif ($avatar === false) {
            flash('The photo must be a JPG or PNG under 1 MB.', 'error');
        } else {
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?' . ($avatar ? ', avatar = ?' : '') . ' WHERE id = ?')
                ->execute($avatar ? [$name, $email, $phone, $avatar, $user['id']] : [$name, $email, $phone, $user['id']]);
            flash('Profile updated');
        }
        redirect('settings.php#profile');
    }

    if ($action === 'password') {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $stmt->fetchColumn())) {
            flash('Your current password is not right.', 'error');
        } elseif (strlen($new) < 8) {
            flash('The new password must be at least 8 characters.', 'error');
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('The two new passwords do not match.', 'error');
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            log_activity('Changed password');
            flash('Password changed');
        }
        redirect('settings.php#security');
    }

    if ($action === 'signout_others') {
        $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id <> ?')->execute([$user['id'], session_id()]);
        flash('Signed out of all other devices');
        redirect('settings.php#security');
    }

    if ($action === 'organisation' && can('organisation')) {
        $kennels = implode(',', array_unique(array_filter(array_map('trim', explode(',', $_POST['kennels'] ?? '')))));
        $s = [
            'org_name'       => trim($_POST['org_name'] ?? ''),
            'contact_email'  => trim($_POST['contact_email'] ?? ''),
            'contact_phone'  => trim($_POST['contact_phone'] ?? ''),
            'quiz_pass_mark' => (int)($_POST['quiz_pass_mark'] ?? 70),
            'kennels'        => $kennels,
            'boarding_rate'  => max(0, (float)($_POST['boarding_rate'] ?? 0)),
        ];
        if ($s['org_name'] === '' || !filter_var($s['contact_email'], FILTER_VALIDATE_EMAIL)) {
            flash('Enter the organisation name and a valid contact email.', 'error');
        } elseif ($s['quiz_pass_mark'] < 0 || $s['quiz_pass_mark'] > 100) {
            flash('The pass mark must be between 0 and 100.', 'error');
        } elseif ($kennels === '') {
            flash('List at least one kennel.', 'error');
        } else {
            $pdo->prepare('UPDATE settings SET org_name=:org_name, contact_email=:contact_email, contact_phone=:contact_phone,
                           quiz_pass_mark=:quiz_pass_mark, kennels=:kennels, boarding_rate=:boarding_rate WHERE id = 1')->execute($s);
            log_activity('Organisation settings changed');
            flash('Organisation settings saved');
        }
        redirect('settings.php#organisation');
    }

    if ($action === 'notifications') {
        $pdo->prepare('UPDATE users SET notify_new_application = ?, notify_boarding = ?, notify_capacity = ?, notify_weekly = ? WHERE id = ?')
            ->execute([
                isset($_POST['notify_new_application']) ? 'true' : 'false',
                isset($_POST['notify_boarding']) ? 'true' : 'false',
                isset($_POST['notify_capacity']) ? 'true' : 'false',
                isset($_POST['notify_weekly']) ? 'true' : 'false',
                $user['id'],
            ]);
        flash('Notification preferences saved');
        redirect('settings.php#notifications');
    }
    redirect('settings.php');
}

$prefs = $pdo->prepare('SELECT notify_new_application, notify_boarding, notify_capacity, notify_weekly FROM users WHERE id = ?');
$prefs->execute([$user['id']]);
$prefs = $prefs->fetch();
$sessions = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE user_id = ?');
$sessions->execute([$user['id']]);
$sessionCount = (int)$sessions->fetchColumn();
$org = settings();
$checked = fn($v) => $v ? ' checked' : '';

layout_top('Settings', 'settings');
?>
        <div class="page-header">
            <div>
                <h2>Settings</h2>
                <p class="subtitle">Your account, security and how the shelter system behaves.</p>
            </div>
        </div>

        <div class="settings-layout">
            <nav class="settings-nav" data-tabs aria-label="Settings sections">
                <button type="button" data-tab="profile"><?= icon('user') ?>Profile</button>
                <button type="button" data-tab="security"><?= icon('lock') ?>Password &amp; security</button>
                <?php if (can('organisation')): ?><button type="button" data-tab="organisation"><?= icon('building') ?>Organisation</button><?php endif; ?>
                <button type="button" data-tab="notifications"><?= icon('bell') ?>Notifications</button>
            </nav>

            <div>
                <section class="settings-panel" data-panel="profile">
                    <form class="card" method="post" action="settings.php" enctype="multipart/form-data" data-server>
                        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                        <div class="card-header"><div><h3 class="card-title">Profile</h3><p class="card-sub">How you appear to other staff.</p></div></div>
                        <div class="profile-row">
                            <?= avatar_html($user, 'avatar-lg') ?>
                            <div><label class="btn btn-outline btn-sm" for="avatar_upload">Change photo</label><input type="file" id="avatar_upload" name="avatar" accept="image/jpeg,image/png,image/webp" hidden onchange="this.closest('div').querySelector('.form-hint').textContent = this.files[0] ? this.files[0].name + ' will be uploaded when you save.' : ''"><p class="form-hint" style="margin-top:6px">Square JPG or PNG, up to 1 MB.</p></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="full_name_38">Full name <span class="required">*</span></label><input class="form-control" type="text" id="full_name_38" name="full_name" required value="<?= e($user['full_name']) ?>"></div>
                            <div class="form-group"><label for="email_39">Email <span class="required">*</span></label><input class="form-control" type="email" id="email_39" name="email" required value="<?= e($user['email']) ?>"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="phone_40">Phone</label><input class="form-control" type="tel" id="phone_40" name="phone" value="<?= e($user['phone']) ?>"></div>
                            <div class="form-group"><label>Role</label><input class="form-control" value="<?= e(ROLES[$user['role']]) ?>" disabled></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save profile</button></div>
                    </form>
                </section>

                <section class="settings-panel" data-panel="security" hidden>
                    <form class="card" method="post" action="settings.php" data-server>
                        <?= csrf_field() ?><input type="hidden" name="action" value="password">
                        <div class="card-header"><div><h3 class="card-title">Change password</h3><p class="card-sub">You will stay signed in on this device.</p></div></div>
                        <div class="form-group"><label for="current_password_41">Current password <span class="required">*</span></label><input class="form-control" type="password" id="current_password_41" name="current_password" required autocomplete="current-password"></div>
                        <div class="form-row">
                            <div class="form-group"><label for="new_password_42">New password <span class="required">*</span></label><input class="form-control" type="password" id="new_password_42" name="new_password" required minlength="8" autocomplete="new-password"><span class="form-hint">At least 8 characters.</span></div>
                            <div class="form-group"><label for="confirm_new">Confirm new password <span class="required">*</span></label><input class="form-control" type="password" id="confirm_new" name="confirm_password" data-match="new_password" required autocomplete="new-password"><span class="form-error">Passwords do not match.</span></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('lock') ?>Change password</button></div>
                    </form>
                    <form class="card" method="post" action="settings.php" data-server data-confirm="Sign out of every other device?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="signout_others">
                        <div class="card-header"><div><h3 class="card-title">Sessions</h3><p class="card-sub">You're signed in on <?= $sessionCount ?> device<?= $sessionCount === 1 ? '' : 's' ?>, including this one.</p></div>
                        <?php if ($sessionCount > 1): ?><button type="submit" class="btn btn-danger-ghost btn-sm">Sign out other devices</button><?php endif; ?></div>
                    </form>
                </section>

                <?php if (can('organisation')): ?>
                <section class="settings-panel" data-panel="organisation" hidden>
                    <form class="card" method="post" action="settings.php" data-server>
                        <?= csrf_field() ?><input type="hidden" name="action" value="organisation">
                        <div class="card-header"><div><h3 class="card-title">Organisation</h3><p class="card-sub">Only Super Admins can change these.</p></div></div>
                        <div class="form-row">
                            <div class="form-group"><label for="org_name_43">Organisation name <span class="required">*</span></label><input class="form-control" type="text" id="org_name_43" name="org_name" required value="<?= e($org['org_name']) ?>"></div>
                            <div class="form-group"><label for="contact_email_44">Public contact email <span class="required">*</span></label><input class="form-control" type="email" id="contact_email_44" name="contact_email" required value="<?= e($org['contact_email']) ?>"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="contact_phone_45">Public phone</label><input class="form-control" type="tel" id="contact_phone_45" name="contact_phone" value="<?= e($org['contact_phone']) ?>"></div>
                            <div class="form-group"><label for="quiz_pass_mark_46">Quiz pass mark (%) <span class="required">*</span></label><input class="form-control" type="number" id="quiz_pass_mark_46" name="quiz_pass_mark" required value="<?= (int)$org['quiz_pass_mark'] ?>" min="0" max="100"><span class="form-hint">Applicants below this score are flagged for extra review.</span></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="kennels_47">Boarding kennels <span class="required">*</span></label><textarea class="form-control" id="kennels_47" name="kennels" required style="min-height:70px"><?= e(str_replace(',', ', ', $org['kennels'])) ?></textarea><span class="form-hint">Kennel names separated by commas. Used for bookings and the free kennel count.</span></div>
                            <div class="form-group"><label for="boarding_rate_48">Boarding price per night</label><input class="form-control" type="number" id="boarding_rate_48" name="boarding_rate" min="0" step="0.01" value="<?= e($org['boarding_rate']) ?>"><span class="form-hint">Used for income in the boarding report. Leave 0 if you don't charge.</span></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save settings</button></div>
                    </form>
                </section>
                <?php endif; ?>

                <section class="settings-panel" data-panel="notifications" hidden>
                    <form class="card" method="post" action="settings.php" data-server>
                        <?= csrf_field() ?><input type="hidden" name="action" value="notifications">
                        <div class="card-header"><div><h3 class="card-title">Email notifications</h3><p class="card-sub">Choose what lands in your inbox.</p></div></div>
                        <div class="switch-row"><div><strong>New adoption application</strong><small>When an applicant submits the form and quiz</small></div><label class="switch"><input type="checkbox" name="notify_new_application"<?= $checked($prefs['notify_new_application']) ?>><span></span><em class="sr-only">New adoption application</em></label></div>
                        <div class="switch-row"><div><strong>Boarding requests</strong><small>New bookings and cancellations</small></div><label class="switch"><input type="checkbox" name="notify_boarding"<?= $checked($prefs['notify_boarding']) ?>><span></span><em class="sr-only">Boarding requests</em></label></div>
                        <div class="switch-row"><div><strong>Low shelter capacity</strong><small>When a shelter passes 90% full</small></div><label class="switch"><input type="checkbox" name="notify_capacity"<?= $checked($prefs['notify_capacity']) ?>><span></span><em class="sr-only">Low shelter capacity</em></label></div>
                        <div class="switch-row"><div><strong>Weekly summary</strong><small>Every Monday morning</small></div><label class="switch"><input type="checkbox" name="notify_weekly"<?= $checked($prefs['notify_weekly']) ?>><span></span><em class="sr-only">Weekly summary</em></label></div>
                        <div class="form-actions" style="margin-top:12px"><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save preferences</button></div>
                    </form>
                </section>
            </div>
        </div>
<?php layout_main_end(); layout_bottom(); ?>
