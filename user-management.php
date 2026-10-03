<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
$me = require_access('users');

// ---------- Save or delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $isMe = $id === (int)$me['id'];

    if (($_POST['action'] ?? '') === 'delete') {
        if ($isMe) {
            flash("You can't remove your own account.", 'error');
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? RETURNING full_name');
            $stmt->execute([$id]);
            if ($name = $stmt->fetchColumn()) {
                log_activity('Staff member removed', $name);
                flash("$name removed");
            }
        }
        redirect('user-management.php');
    }

    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => strtolower(trim($_POST['email'] ?? '')),
        'phone'     => trim($_POST['phone'] ?? ''),
        'role'      => $_POST['role'] ?? '',
        'status'    => $_POST['status'] ?? 'active',
    ];
    $password = $_POST['password'] ?? '';

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $check->execute([$data['email'], $id]);

    $error = '';
    if ($data['full_name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a full name and a valid email.';
    } elseif (!isset(ROLES[$data['role']]) || !in_array($data['status'], ['active', 'inactive'], true)) {
        $error = 'Choose a role and a status.';
    } elseif ($check->fetch()) {
        $error = "{$data['email']} already belongs to another account.";
    } elseif ((!$id || $password !== '') && strlen($password) < 8) {
        $error = 'The password must be at least 8 characters.';
    } elseif ($password !== ($_POST['confirm_password'] ?? '')) {
        $error = 'The two passwords do not match.';
    } elseif ($isMe && ($data['role'] !== 'super_admin' || $data['status'] !== 'active')) {
        $error = "You can't change your own role or deactivate yourself. Ask another Super Admin.";
    }

    if ($error) {
        flash($error, 'error');
    } elseif ($id) {
        $sql = 'UPDATE users SET full_name=:full_name, email=:email, phone=:phone, role=:role, status=:status'
             . ($password !== '' ? ', password=:password' : '') . ' WHERE id=:id';
        if ($password !== '') $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare($sql)->execute($data + ['id' => $id]);
        if ($data['status'] === 'inactive' || $password !== '') {
            // Sign them out everywhere
            $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND id <> ?')->execute([$id, session_id()]);
        }
        log_activity('Staff account updated', $data['full_name'] . ', ' . ROLES[$data['role']]);
        flash('User saved');
    } else {
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (full_name, email, phone, role, status, password)
                       VALUES (:full_name, :email, :phone, :role, :status, :password)')->execute($data);
        log_activity('Staff member added', $data['full_name'] . ', ' . ROLES[$data['role']]);
        flash('User saved');
    }
    redirect('user-management.php');
}

// ---------- Load the page ----------
$users = $pdo->query("SELECT id, full_name, email, phone, role, status, last_active, (avatar IS NOT NULL) AS has_avatar
                      FROM users ORDER BY status, CASE role WHEN 'super_admin' THEN 0 WHEN 'shelter_manager' THEN 1
                      WHEN 'veterinarian' THEN 2 WHEN 'volunteer_coordinator' THEN 3 ELSE 4 END, full_name")->fetchAll();

layout_top('User Management', 'users');
?>
        <div class="page-header">
            <div>
                <h2>User management</h2>
                <p class="subtitle">Staff, volunteers and what each of them can access.</p>
            </div>
            <div class="page-actions"><button type="button" class="btn btn-primary" data-modal-open="userModal" data-modal-title="Add a new user"><?= icon('plus') ?>Add user</button></div>
        </div>

        <div class="card toolbar">
            <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search by name or email" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="usersTable"></div>
            <select class="form-control" aria-label="Role" data-filter="role" data-filter-table="usersTable"><option value="all">All roles</option>
                <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
            <select class="form-control" aria-label="Status" data-filter="status" data-filter-table="usersTable"><option value="all">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>

        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table" id="usersTable">
                    <thead><tr><th>User</th><th>Role</th><th>Phone</th><th>Last active</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
                        <tr data-id="<?= $u['id'] ?>" data-full-name="<?= e($u['full_name']) ?>" data-email="<?= e($u['email']) ?>" data-phone="<?= e($u['phone']) ?>" data-role="<?= e($u['role']) ?>" data-status="<?= e($u['status']) ?>">
                            <td><div class="cell-user"><?= avatar_html($u, 'avatar-sm ' . tint($u['id'])) ?><div><strong><?= e($u['full_name']) ?><?= $self ? ' (you)' : '' ?></strong><small><?= e($u['email']) ?></small></div></div></td>
                            <td><span class="badge badge-role"><?= e(ROLES[$u['role']]) ?></span></td>
                            <td><?= e($u['phone'] ?: '–') ?></td>
                            <td><?= e(time_ago($u['last_active'], 'Online now')) ?></td>
                            <td><span class="badge badge-<?= e($u['status']) ?>" data-status-badge><?= e(label($u['status'])) ?></span></td>
                            <td><div class="actions">
                                <button type="button" class="icon-action" title="Edit" aria-label="Edit" data-modal-open="userModal" data-modal-title="Edit user"><?= icon('edit', 16) ?></button>
                                <?php if (!$self): ?><?= delete_button($u['id'], "Remove {$u['full_name']} from the team? They will lose access immediately.") ?><?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span data-result-count="usersTable" data-noun="users"></span>
            </div>
        </section>

        <h3 class="section-title">What each role can do</h3>
        <div class="grid-3">
            <div class="card"><h4>Super Admin</h4><p class="card-sub">Everything, including users, roles and system settings.</p></div>
            <div class="card"><h4>Shelter Manager</h4><p class="card-sub">Pets, adoptions, boarding, quiz bank and reports.</p></div>
            <div class="card"><h4>Veterinarian</h4><p class="card-sub">Pet records and health notes only.</p></div>
            <div class="card"><h4>Volunteer Coordinator</h4><p class="card-sub">Pet records and boarding bookings.</p></div>
            <div class="card"><h4>Volunteer</h4><p class="card-sub">Pet records and boarding bookings. Can't delete anything.</p></div>
        </div>
<?php layout_main_end(); ?>

<div class="modal" id="userModal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Add a new user</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 20) ?></button>
        </div>
        <form method="post" action="user-management.php" data-server>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal-body">
                <div class="form-group"><label for="full_name_29">Full name <span class="required">*</span></label><input class="form-control" type="text" id="full_name_29" name="full_name" placeholder="e.g. Sarah Ahmed" required maxlength="120"></div>
                <div class="form-row">
                    <div class="form-group"><label for="email_30">Email <span class="required">*</span></label><input class="form-control" type="email" id="email_30" name="email" placeholder="name@pawhome.org" required></div>
                    <div class="form-group"><label for="phone_31">Phone</label><input class="form-control" type="tel" id="phone_31" name="phone" placeholder="+880 1XXX-XXXXXX" maxlength="40"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="role_32">Role <span class="required">*</span></label><select class="form-control" id="role_32" name="role" required><option value="">Choose role</option>
                        <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="status_33">Status <span class="required">*</span></label><select class="form-control" id="status_33" name="status" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="password_34">Password</label><input class="form-control" type="password" id="password_34" name="password" minlength="8" autocomplete="new-password" data-required-on-add><span class="form-hint">At least 8 characters. When editing, leave blank to keep the current one.</span></div>
                    <div class="form-group"><label for="user_confirm">Confirm password</label><input class="form-control" type="password" id="user_confirm" name="confirm_password" data-match="password" autocomplete="new-password"><span class="form-error">Passwords do not match.</span></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save user</button></div>
        </form>
    </div>
</div>
<?php layout_bottom(); ?>
