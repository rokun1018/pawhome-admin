<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('boarding');

const BOOKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled'];

// ---------- Save or delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'delete') {
        if (!can('delete')) { flash('Only Super Admins and Shelter Managers can delete bookings.', 'error'); redirect('boarding.php'); }
        $stmt = $pdo->prepare('DELETE FROM boarding WHERE id = ? RETURNING pet_name');
        $stmt->execute([$id]);
        if ($name = $stmt->fetchColumn()) {
            log_activity('Boarding booking deleted', $name);
            flash('Booking deleted');
        }
        redirect('boarding.php');
    }

    $data = [
        'pet_name'      => trim($_POST['pet_name'] ?? ''),
        'species'       => $_POST['species'] ?? '',
        'breed'         => trim($_POST['breed'] ?? ''),
        'kennel'        => trim($_POST['kennel'] ?? '') ?: null,
        'owner_name'    => trim($_POST['owner_name'] ?? ''),
        'owner_phone'   => trim($_POST['owner_phone'] ?? ''),
        'owner_email'   => strtolower(trim($_POST['owner_email'] ?? '')) ?: null,
        'check_in'      => $_POST['check_in'] ?? '',
        'check_out'     => $_POST['check_out'] ?? '',
        'status'        => $_POST['status'] ?? 'pending',
        'special_notes' => trim($_POST['special_notes'] ?? ''),
    ];

    $error = '';
    $validDates = DateTime::createFromFormat('Y-m-d', $data['check_in']) && DateTime::createFromFormat('Y-m-d', $data['check_out']);
    if ($data['pet_name'] === '' || $data['owner_name'] === '' || $data['owner_phone'] === '') {
        $error = 'Fill in the pet name, owner name and owner phone.';
    } elseif (!in_array($data['species'], ['dog', 'cat', 'rabbit', 'bird', 'other'], true) || !in_array($data['status'], BOOKING_STATUSES, true)) {
        $error = 'Choose a species and a status.';
    } elseif (!$validDates || $data['check_out'] < $data['check_in']) {
        $error = 'The check-out date must be on or after the check-in date.';
    } elseif ($data['owner_email'] && !filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'The owner email doesn\'t look right.';
    } elseif ($data['kennel'] && !in_array($data['status'], ['completed', 'cancelled'], true)) {
        // Is the kennel already taken for any of these nights?
        // Same-day turnover is fine: one pet can check out the morning another checks in.
        $stmt = $pdo->prepare("SELECT pet_name FROM boarding
                               WHERE kennel = ? AND id <> ? AND status IN ('pending','confirmed','checked_in')
                                 AND daterange(check_in, GREATEST(check_out, check_in + 1))
                                  && daterange(?::date, GREATEST(?::date, ?::date + 1))
                               LIMIT 1");
        $stmt->execute([$data['kennel'], $id, $data['check_in'], $data['check_out'], $data['check_in']]);
        if ($other = $stmt->fetchColumn()) {
            $error = "Kennel {$data['kennel']} is already booked for $other on some of those dates. Pick another kennel or change the dates.";
        }
    }

    if ($error) {
        flash($error, 'error');
    } elseif ($id) {
        $pdo->prepare('UPDATE boarding SET pet_name=:pet_name, species=:species, breed=:breed, kennel=:kennel, owner_name=:owner_name,
                       owner_phone=:owner_phone, owner_email=:owner_email, check_in=:check_in, check_out=:check_out,
                       status=:status, special_notes=:special_notes WHERE id=:id')
            ->execute($data + ['id' => $id]);
        log_activity('Boarding booking updated', $data['pet_name'] . ' (' . label($data['status']) . ')');
        flash('Booking saved');
    } else {
        $pdo->prepare('INSERT INTO boarding (pet_name, species, breed, kennel, owner_name, owner_phone, owner_email, check_in, check_out, status, special_notes)
                       VALUES (:pet_name, :species, :breed, :kennel, :owner_name, :owner_phone, :owner_email, :check_in, :check_out, :status, :special_notes)')
            ->execute($data);
        log_activity('New boarding booking', $data['pet_name'] . ', ' . fmt_date($data['check_in']));
        flash('Booking saved');
    }
    redirect('boarding.php');
}

// ---------- Load the page ----------
$kennels = kennel_list();
$stats = $pdo->query("SELECT
    COUNT(*) FILTER (WHERE status = 'checked_in')                                         AS staying,
    COUNT(*) FILTER (WHERE status = 'checked_in' AND check_out = CURRENT_DATE)            AS leaving_today,
    COUNT(*) FILTER (WHERE status IN ('pending','confirmed') AND check_in = CURRENT_DATE + 1) AS tomorrow,
    COUNT(*) FILTER (WHERE status = 'pending')                                            AS awaiting,
    COUNT(DISTINCT kennel) FILTER (WHERE kennel IS NOT NULL AND status IN ('confirmed','checked_in')
                                   AND CURRENT_DATE BETWEEN check_in AND check_out)        AS occupied
    FROM boarding")->fetch();
$total = count($kennels);
$free = max(0, $total - $stats['occupied']);
$occupancy = $total ? round($stats['occupied'] / $total * 100) : 0;

$bookings = $pdo->query("SELECT * FROM boarding
                         ORDER BY CASE status WHEN 'checked_in' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, check_in")->fetchAll();

layout_top('Boarding', 'boarding');
?>
        <div class="page-header">
            <div>
                <h2>Boarding</h2>
                <p class="subtitle">Short stays for pets whose owners are away. Manage check-ins, check-outs and kennels.</p>
            </div>
            <div class="page-actions"><button type="button" class="btn btn-primary" data-modal-open="bookingModal" data-modal-title="New boarding booking"><?= icon('plus') ?>New booking</button></div>
        </div>

        <section class="stats-grid">
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Staying now</span><span class="stat-icon tint-green"><?= icon('home', 20) ?></span></div><div class="stat-number"><?= $stats['staying'] ?></div><p class="stat-sub"><?= $stats['leaving_today'] ?> check out today</p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Checking in tomorrow</span><span class="stat-icon tint-blue"><?= icon('calendar', 20) ?></span></div><div class="stat-number"><?= $stats['tomorrow'] ?></div><p class="stat-sub"><?= $stats['tomorrow'] ? 'Get their kennels ready' : 'Nothing booked' ?></p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Awaiting confirmation</span><span class="stat-icon tint-honey"><?= icon('bell', 20) ?></span></div><div class="stat-number"><?= $stats['awaiting'] ?></div><p class="stat-sub<?= $stats['awaiting'] ? ' negative' : '' ?>"><?= $stats['awaiting'] ? 'Reply within 24 hours' : 'All replied to' ?></p></div>
            <div class="stat-card"><div class="stat-top"><span class="stat-label">Free kennels</span><span class="stat-icon tint-plum"><?= icon('building', 20) ?></span></div><div class="stat-number"><?= $free ?><small class="muted" style="font-size:16px"> / <?= $total ?></small></div><p class="stat-sub"><?= $occupancy ?>% occupancy</p></div>
        </section>

        <div class="card toolbar">
            <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search pet, owner or kennel" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="bookingsTable"></div>
            <select class="form-control" aria-label="Status" data-filter="status" data-filter-table="bookingsTable"><option value="all">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="checked_in">Checked in</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
            <select class="form-control" aria-label="Species" data-filter="species" data-filter-table="bookingsTable"><option value="all">All species</option><option value="dog">Dogs</option><option value="cat">Cats</option><option value="rabbit">Rabbits</option><option value="bird">Birds</option><option value="other">Other</option></select>
        </div>

        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table" id="bookingsTable">
                    <thead><tr><th>Pet</th><th>Owner</th><th>Check-in</th><th>Check-out</th><th>Kennel</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr data-id="<?= $b['id'] ?>" data-pet-name="<?= e($b['pet_name']) ?>" data-species="<?= e($b['species']) ?>" data-breed="<?= e($b['breed']) ?>" data-owner-name="<?= e($b['owner_name']) ?>" data-owner-phone="<?= e($b['owner_phone']) ?>" data-owner-email="<?= e($b['owner_email']) ?>" data-check-in="<?= e($b['check_in']) ?>" data-check-out="<?= e($b['check_out']) ?>" data-kennel="<?= e($b['kennel']) ?>" data-status="<?= e($b['status']) ?>" data-special-notes="<?= e($b['special_notes']) ?>">
                            <td><div class="cell-user"><span class="avatar avatar-sm <?= tint($b['id']) ?>"><?= e(mb_strtoupper(mb_substr($b['pet_name'], 0, 1))) ?></span><div><strong><?= e($b['pet_name']) ?></strong><small><?= e(trim($b['breed'] . ' ' . $b['species'])) ?></small></div></div></td>
                            <td><?= e($b['owner_name']) ?><small class="block"><?= e($b['owner_phone']) ?></small></td>
                            <td><?= fmt_date($b['check_in']) ?></td><td><?= fmt_date($b['check_out']) ?></td><td><?= e($b['kennel'] ?: 'Not assigned') ?></td>
                            <td><span class="badge badge-<?= e($b['status']) ?>" data-status-badge><?= e(label($b['status'])) ?></span></td>
                            <td><div class="actions">
                                <button type="button" class="icon-action" title="Edit" aria-label="Edit" data-modal-open="bookingModal" data-modal-title="Edit booking"><?= icon('edit', 16) ?></button>
                                <?php if (can('delete')): ?><?= delete_button($b['id'], "Delete the booking for {$b['pet_name']}?") ?><?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$bookings): ?><p class="card-sub" style="padding:20px">No bookings yet. Use <strong>New booking</strong> to add one.</p><?php endif; ?>
            </div>
            <div class="table-footer">
                <span data-result-count="bookingsTable" data-noun="bookings"></span>
            </div>
        </section>
<?php layout_main_end(); ?>

<div class="modal" id="bookingModal" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="bookingModalTitle">New boarding booking</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 20) ?></button>
        </div>
        <form method="post" action="boarding.php" data-server>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label for="pet_name_11">Pet name <span class="required">*</span></label><input class="form-control" type="text" id="pet_name_11" name="pet_name" required maxlength="80"></div>
                    <div class="form-group"><label for="species_12">Species <span class="required">*</span></label><select class="form-control" id="species_12" name="species" required><option value="">Choose species</option><option value="dog">Dog</option><option value="cat">Cat</option><option value="rabbit">Rabbit</option><option value="bird">Bird</option><option value="other">Other</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="breed_13">Breed</label><input class="form-control" type="text" id="breed_13" name="breed" maxlength="80"></div>
                    <div class="form-group"><label for="kennel_14">Kennel</label><select class="form-control" id="kennel_14" name="kennel"><option value="">Assign later</option>
                        <?php foreach ($kennels as $k): ?><option value="<?= e($k) ?>"><?= e($k) ?></option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="owner_name_15">Owner name <span class="required">*</span></label><input class="form-control" type="text" id="owner_name_15" name="owner_name" required maxlength="120"></div>
                    <div class="form-group"><label for="owner_phone_16">Owner phone <span class="required">*</span></label><input class="form-control" type="tel" id="owner_phone_16" name="owner_phone" placeholder="+880 1XXX-XXXXXX" required maxlength="40"></div>
                </div>
                <div class="form-group"><label for="owner_email_17">Owner email</label><input class="form-control" type="email" id="owner_email_17" name="owner_email"></div>
                <div class="form-row">
                    <div class="form-group"><label for="check_in_18">Check-in date <span class="required">*</span></label><input class="form-control" type="date" id="check_in_18" name="check_in" required></div>
                    <div class="form-group"><label for="check_out_19">Check-out date <span class="required">*</span></label><input class="form-control" type="date" id="check_out_19" name="check_out" required></div>
                </div>
                <div class="form-group"><label for="status_20">Status <span class="required">*</span></label><select class="form-control" id="status_20" name="status" required><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="checked_in">Checked in</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                <div class="form-group"><label for="special_notes">Care notes</label><textarea class="form-control" id="special_notes" name="special_notes" placeholder="Feeding schedule, medication, allergies"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save booking</button></div>
        </form>
    </div>
</div>
<?php layout_bottom(); ?>
