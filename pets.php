<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('pets');

// ---------- Save or delete ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'delete') {
        if (!can('delete')) { flash('Only Super Admins and Shelter Managers can delete pets.', 'error'); redirect('pets.php'); }
        $stmt = $pdo->prepare('DELETE FROM pets WHERE id = ? RETURNING name');
        $stmt->execute([$id]);
        if ($name = $stmt->fetchColumn()) {
            log_activity('Pet removed', $name);
            flash("$name removed from records");
        }
        redirect('pets.php');
    }

    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'species'     => $_POST['species'] ?? '',
        'breed'       => trim($_POST['breed'] ?? ''),
        'age_months'  => (int)($_POST['age_months'] ?? 0),
        'gender'      => $_POST['gender'] ?? '',
        'size'        => $_POST['size'] ?? '',
        'shelter_id'  => (int)($_POST['shelter_id'] ?? 0) ?: null,
        'intake_date' => $_POST['intake_date'] ?? '',
        'status'      => $_POST['status'] ?? 'available',
        'description' => trim($_POST['description'] ?? ''),
    ];
    $photo = uploaded_image('photo', 2 * 1024 * 1024);

    $errors = [];
    if ($data['name'] === '') $errors[] = 'pet name';
    if (!in_array($data['species'], ['dog', 'cat', 'rabbit', 'bird', 'other'], true)) $errors[] = 'species';
    if (!in_array($data['gender'], ['male', 'female'], true)) $errors[] = 'gender';
    if (!in_array($data['size'], ['small', 'medium', 'large'], true)) $errors[] = 'size';
    if (!in_array($data['status'], ['available', 'reserved', 'adopted', 'medical'], true)) $errors[] = 'status';
    if (!DateTime::createFromFormat('Y-m-d', $data['intake_date'])) $errors[] = 'intake date';
    if ($data['age_months'] < 0 || $data['age_months'] > 360) $errors[] = 'age';

    if ($photo === false) {
        flash('The photo must be a JPG or PNG under 2 MB.', 'error');
    } elseif ($errors) {
        flash('Check these fields and try again: ' . implode(', ', $errors) . '.', 'error');
    } elseif ($id) {
        $sql = 'UPDATE pets SET name=:name, species=:species, breed=:breed, age_months=:age_months, gender=:gender,
                size=:size, shelter_id=:shelter_id, intake_date=:intake_date, status=:status, description=:description'
             . ($photo ? ', photo=:photo' : '') . ' WHERE id=:id';
        if ($photo) $data['photo'] = $photo;
        $pdo->prepare($sql)->execute($data + ['id' => $id]);
        log_activity('Pet record updated', $data['name']);
        flash('Pet saved');
    } else {
        $data['photo'] = $photo;
        $pdo->prepare('INSERT INTO pets (name, species, breed, age_months, gender, size, shelter_id, intake_date, status, description, photo)
                       VALUES (:name, :species, :breed, :age_months, :gender, :size, :shelter_id, :intake_date, :status, :description, :photo)')
            ->execute($data);
        log_activity('New pet registered', $data['name'] . ($data['breed'] ? ' (' . $data['breed'] . ')' : ''));
        flash('Pet saved');
    }
    redirect('pets.php');
}

// ---------- Load the page ----------
$shelters = $pdo->query('SELECT id, name FROM shelters ORDER BY id')->fetchAll();
$short = fn($name) => trim(preg_replace('/^PawHome\s+|\s+(Rescue Sanctuary|Safe Haven)$/', '', $name));
$pets = $pdo->query('SELECT p.id, p.name, p.species, p.breed, p.age_months, p.gender, p.size, p.shelter_id, p.intake_date,
                            p.status, p.description, (p.photo IS NOT NULL) AS has_photo, s.name AS shelter_name
                     FROM pets p LEFT JOIN shelters s ON s.id = p.shelter_id
                     ORDER BY p.intake_date DESC, p.id DESC')->fetchAll();

layout_top('Pets', 'pets');
?>
        <div class="page-header">
            <div>
                <h2>Pets</h2>
                <p class="subtitle">Every animal currently in PawHome care, across all shelters.</p>
            </div>
            <div class="page-actions">
                <?php if (can('reports')): ?><a href="report.php?type=pets" class="btn btn-outline"><?= icon('download') ?>Export CSV</a><?php endif; ?>
                <button type="button" class="btn btn-primary" data-modal-open="petModal" data-modal-title="Register a new pet"><?= icon('plus') ?>Register pet</button>
            </div>
        </div>

        <div class="card toolbar">
            <div class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search by name, breed or ID" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>" data-filter="search" data-filter-table="petsTable"></div>
            <select class="form-control" aria-label="Species" data-filter="species" data-filter-table="petsTable"><option value="all">All species</option><option value="dog">Dog</option><option value="cat">Cat</option><option value="rabbit">Rabbit</option><option value="bird">Bird</option><option value="other">Other</option></select>
            <select class="form-control" aria-label="Status" data-filter="status" data-filter-table="petsTable"><option value="all">All statuses</option><option value="available">Available</option><option value="reserved">Reserved</option><option value="adopted">Adopted</option><option value="medical">Medical care</option></select>
            <select class="form-control" aria-label="Shelter" data-filter="shelterId" data-filter-table="petsTable"><option value="all">All shelters</option>
                <?php foreach ($shelters as $s): ?><option value="<?= $s['id'] ?>"><?= e($short($s['name'])) ?></option><?php endforeach; ?>
            </select>
        </div>

        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table" id="petsTable">
                    <thead><tr><th>Pet</th><th>Species</th><th>Age</th><th>Gender</th><th>Shelter</th><th>Intake date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($pets as $p): ?>
                        <tr data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-species="<?= e($p['species']) ?>" data-breed="<?= e($p['breed']) ?>" data-age-months="<?= $p['age_months'] ?>" data-gender="<?= e($p['gender']) ?>" data-size="<?= e($p['size']) ?>" data-shelter-id="<?= $p['shelter_id'] ?>" data-intake-date="<?= e($p['intake_date']) ?>" data-status="<?= e($p['status']) ?>" data-description="<?= e($p['description']) ?>">
                            <td><div class="cell-user">
                                <?php if ($p['has_photo']): ?>
                                    <span class="avatar avatar-sm <?= tint($p['id']) ?>"><img src="photo.php?pet=<?= $p['id'] ?>" alt="" loading="lazy" style="width:100%;height:100%;border-radius:inherit;object-fit:cover"></span>
                                <?php else: ?>
                                    <span class="avatar avatar-sm <?= tint($p['id']) ?>"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <div><strong><?= e($p['name']) ?></strong><small>#PH-<?= 1000 + $p['id'] ?></small></div></div></td>
                            <td><?= e(label($p['species'])) ?><small class="block"><?= e($p['breed']) ?></small></td>
                            <td><?= age_label($p['age_months']) ?></td><td><?= e(label($p['gender'])) ?></td><td><?= e($short($p['shelter_name'] ?? '')) ?></td><td><?= fmt_date($p['intake_date']) ?></td>
                            <td><span class="badge badge-<?= e($p['status']) ?>" data-status-badge><?= e(label($p['status'])) ?></span></td>
                            <td><div class="actions">
                                <button type="button" class="icon-action" title="Edit" aria-label="Edit" data-modal-open="petModal" data-modal-title="Edit pet"><?= icon('edit', 16) ?></button>
                                <?php if (can('delete')): ?><?= delete_button($p['id'], "Remove {$p['name']} from the pet records?") ?><?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$pets): ?><p class="card-sub" style="padding:20px">No pets yet. Use <strong>Register pet</strong> to log the first intake.</p><?php endif; ?>
            </div>
            <div class="table-footer">
                <span data-result-count="petsTable" data-noun="pets"></span>
            </div>
        </section>
<?php layout_main_end(); ?>

<div class="modal" id="petModal" role="dialog" aria-modal="true" aria-labelledby="petModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="petModalTitle">Register a new pet</h3>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 20) ?></button>
        </div>
        <form method="post" action="pets.php" enctype="multipart/form-data" data-server>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label for="name_1">Pet name <span class="required">*</span></label><input class="form-control" type="text" id="name_1" name="name" placeholder="e.g. Biscuit" required maxlength="80"></div>
                    <div class="form-group"><label for="species_2">Species <span class="required">*</span></label><select class="form-control" id="species_2" name="species" required><option value="">Choose species</option><option value="dog">Dog</option><option value="cat">Cat</option><option value="rabbit">Rabbit</option><option value="bird">Bird</option><option value="other">Other</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="breed_3">Breed</label><input class="form-control" type="text" id="breed_3" name="breed" placeholder="e.g. Golden Retriever" maxlength="80"></div>
                    <div class="form-group"><label for="age_months_4">Age in months <span class="required">*</span></label><input class="form-control" type="number" id="age_months_4" name="age_months" placeholder="e.g. 24" required min="0" max="360"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="gender_5">Gender <span class="required">*</span></label><select class="form-control" id="gender_5" name="gender" required><option value="">Choose</option><option value="male">Male</option><option value="female">Female</option></select></div>
                    <div class="form-group"><label for="size_6">Size <span class="required">*</span></label><select class="form-control" id="size_6" name="size" required><option value="">Choose</option><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="shelter_id_7">Shelter <span class="required">*</span></label><select class="form-control" id="shelter_id_7" name="shelter_id" required><option value="">Choose shelter</option>
                        <?php foreach ($shelters as $s): ?><option value="<?= $s['id'] ?>"><?= e($short($s['name'])) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="form-group"><label for="intake_date_8">Intake date <span class="required">*</span></label><input class="form-control" type="date" id="intake_date_8" name="intake_date" required value="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="status_9">Status <span class="required">*</span></label><select class="form-control" id="status_9" name="status" required><option value="available">Available</option><option value="reserved">Reserved</option><option value="adopted">Adopted</option><option value="medical">Medical care</option></select></div>
                    <div class="form-group"><label for="photo_10">Photo</label><input class="form-control" type="file" id="photo_10" name="photo" accept="image/jpeg,image/png,image/webp"><span class="form-hint">JPG or PNG, up to 2 MB. Leave empty to keep the current photo.</span></div>
                </div>
                <div class="form-group"><label for="pet_description">Notes and temperament</label><textarea class="form-control" id="pet_description" name="description" placeholder="Good with children, needs daily walks, vaccinated"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary"><?= icon('save') ?>Save pet</button></div>
        </form>
    </div>
</div>
<?php layout_bottom(); ?>
