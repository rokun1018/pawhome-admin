<?php
// =====================================================================
//  Staff panel. Same database and accounts as the admin panel, so every
//  approval, booking decision and care log shows up there too.
// =====================================================================
require __DIR__ . '/staff-common.php';
$user = require_login('staff');
$passMark = (int)settings()['quiz_pass_mark'];

// ---------- Applications (open ones, plus decisions from the last 60 days) ----------
$applications = $pdo->query("SELECT a.id, a.applicant_name, a.status, a.quiz_score, p.name AS pet_name
    FROM applications a LEFT JOIN pets p ON p.id = a.pet_id
    WHERE a.status IN ('pending','review') OR a.decided_at > NOW() - INTERVAL '60 days'
    ORDER BY CASE WHEN a.status IN ('pending','review') THEN 0 ELSE 1 END, a.created_at DESC")->fetchAll();

function match_words(int $score, int $passMark): string
{
    if ($score >= 90) return 'Strong match';
    if ($score >= $passMark) return 'Good match';
    return 'Below pass mark';
}

// ---------- Pets in our care (not yet adopted) ----------
$pets = $pdo->query("SELECT p.id, p.name, p.breed, p.species, p.kennel, p.health_status, p.last_checkup, u.full_name AS caretaker
    FROM pets p LEFT JOIN users u ON u.id = p.caretaker_id
    WHERE p.status <> 'adopted' ORDER BY p.name")->fetchAll();
const HEALTH_BADGE = ['healthy' => 'approved', 'monitoring' => 'pending', 'medical' => 'rejected'];

// ---------- Boarding (open requests, plus anything from the last 30 days) ----------
$bookings = $pdo->query("SELECT id, owner_name, pet_name, check_in, check_out, status FROM boarding
    WHERE status IN ('pending','confirmed','checked_in') OR check_out >= CURRENT_DATE - 30
    ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'checked_in' THEN 2 ELSE 3 END, check_in")->fetchAll();
const BOOKING_BADGE = [
    'pending' => ['pending', 'Pending'], 'confirmed' => ['approved', 'Confirmed'], 'checked_in' => ['approved', 'Checked in'],
    'completed' => ['approved', 'Completed'], 'declined' => ['rejected', 'Declined'], 'cancelled' => ['rejected', 'Cancelled'],
];
$day = fn($d) => date('F j', strtotime($d));

// ---------- Today's care logs ----------
$logs = $pdo->query("SELECT c.pet_id, to_char(c.log_time, 'HH24:MI') AS t, c.activity, c.notes, p.name AS pet_name, u.full_name AS caretaker
    FROM care_logs c JOIN pets p ON p.id = c.pet_id LEFT JOIN users u ON u.id = c.caretaker_id
    WHERE c.log_date = CURRENT_DATE ORDER BY c.log_time DESC, c.id DESC")->fetchAll();
$staffList = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();
$clock = fn($hhmm) => date('g:i A', strtotime($hhmm));

$report = can('staff_reports') ? staff_report('this-month') : null;
$canDecideApps = can('staff_applications');
$canDecideBoarding = can('staff_boarding');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Staff Panel — PawHome</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>

<!-- Panel switches (CSS tabs). Kept visually hidden but focusable for keyboard users. -->
<input class="tab-radio" type="radio" name="panel" id="applications-tab" checked>
<input class="tab-radio" type="radio" name="panel" id="pets-tab">
<input class="tab-radio" type="radio" name="panel" id="boarding-tab">
<input class="tab-radio" type="radio" name="panel" id="logs-tab">
<?php if ($report): ?><input class="tab-radio" type="radio" name="panel" id="reports-tab"><?php endif; ?>

<header class="topbar">
  <a class="brand" href="index.php">
    <span class="brand-mark" aria-hidden="true">🐾</span>
    PawHome
    <span class="brand-tag">Staff Panel</span>
  </a>

  <div class="top-right">
    <div class="user-meta">
      <strong id="userName"><?= e($user['full_name']) ?></strong>
      <small id="userRole"><?= e(ROLES[$user['role']] ?? 'Staff') ?></small>
    </div>
    <span class="avatar" id="userAvatar" aria-hidden="true"><?= e(initials($user['full_name'])) ?></span>
    <?php if (in_array($user['role'], ACCESS['admin_panel'], true)): ?>
      <a class="btn secondary" href="/" style="text-decoration:none">Admin panel</a>
    <?php endif; ?>
    <button class="btn secondary" type="button" id="logoutBtn">Log out</button>
  </div>
</header>

<div class="layout">

  <aside class="sidebar" aria-label="Staff panel sections">
    <label for="pets-tab">🐕 Pets Under Care</label>
    <label for="applications-tab">📋 Applications</label>
    <label for="boarding-tab">📅 Boarding Requests</label>
    <label for="logs-tab">🕐 Daily Care Logs</label>
    <?php if ($report): ?><label for="reports-tab">📊 Performance Reports</label><?php endif; ?>
  </aside>

  <main class="main">

    <!-- APPLICATIONS -->
    <section class="panel applications-panel" aria-labelledby="applications-title">

      <div class="page-head">
        <div>
          <h1 id="applications-title">Adoption Applications</h1>
          <p><?= $canDecideApps ? 'Manage and vet pending adopter profiles' : 'Adopter profiles waiting for a manager’s decision' ?></p>
        </div>

        <select data-filter aria-label="Filter applications by status"
                data-empty="No applications with this status.">
          <option value="all">All applications</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Target Pet</th>
              <th>Status</th>
              <th>Match Probability</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
          <?php foreach ($applications as $a):
              $open = in_array($a['status'], ['pending', 'review'], true);
              $score = (int)$a['quiz_score'];
              $badge = $open ? 'pending' : $a['status']; ?>
            <tr data-id="<?= $a['id'] ?>" data-status="<?= $badge ?>" data-applicant="<?= e($a['applicant_name']) ?>" data-pet="<?= e($a['pet_name'] ?? 'a removed pet') ?>">
              <td class="applicant"><?= e($a['applicant_name']) ?></td>
              <td><?= e($a['pet_name'] ?? 'Pet removed') ?></td>
              <td><span class="badge <?= $badge ?>"><?= $a['status'] === 'review' ? 'Under review' : e(label($a['status'])) ?></span></td>
              <td>
                <div class="match">
                  <div class="match-top"><strong><?= $score ?>%</strong><span><?= match_words($score, $passMark) ?></span></div>
                  <div class="meter<?= $score < $passMark ? ' low' : '' ?>"><span style="width:<?= $score ?>%"></span></div>
                </div>
              </td>
              <td class="actions">
                <?php if ($open && $canDecideApps): ?>
                  <button class="btn primary" type="button" data-action="approve">Approve</button>
                  <button class="btn secondary" type="button" data-action="reject">Reject</button>
                <?php elseif ($open): ?>
                  <small>Manager decides</small>
                <?php else: ?>
                  <span class="decided">Reviewed</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </section>


    <!-- PETS -->
    <section class="panel pets-panel" aria-labelledby="pets-title">

      <div class="page-head">
        <div>
          <h1 id="pets-title">Pets Under Care</h1>
          <p>Health, feeding, and kennel assignments for every animal</p>
        </div>

        <select data-filter aria-label="Filter pets by status"
                data-empty="No pets with this status.">
          <option value="all">All statuses</option>
          <option value="healthy">Healthy</option>
          <option value="monitoring">Monitoring</option>
          <option value="medical">Medical Attention</option>
        </select>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Pet</th>
              <th>Kennel</th>
              <th>Caretaker</th>
              <th>Status</th>
              <th>Last Checkup</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
          <?php foreach ($pets as $p):
              $breed = $p['breed'] ?: label($p['species']);
              $checkup = $p['last_checkup'] ? date('F j, Y', strtotime($p['last_checkup'])) : 'Not yet'; ?>
            <tr data-id="<?= $p['id'] ?>" data-status="<?= e($p['health_status']) ?>" data-name="<?= e($p['name']) ?>" data-breed="<?= e($breed) ?>"
                data-kennel="<?= e($p['kennel'] ?: '—') ?>" data-caretaker="<?= e($p['caretaker'] ?: 'Not assigned') ?>" data-checkup="<?= e($checkup) ?>">
              <td class="applicant"><?= e($p['name']) ?><small><?= e($breed) ?></small></td>
              <td><?= e($p['kennel'] ?: '—') ?></td>
              <td><?= e($p['caretaker'] ?: 'Not assigned') ?></td>
              <td><span class="badge <?= HEALTH_BADGE[$p['health_status']] ?? 'pending' ?>"><?= e(health_label($p['health_status'])) ?></span></td>
              <td class="checkup"><?= e($checkup) ?></td>
              <td><button class="btn secondary" type="button" data-action="view-pet">View</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </section>


    <!-- BOARDING -->
    <section class="panel boarding-panel" aria-labelledby="boarding-title">

      <div class="page-head">
        <div>
          <h1 id="boarding-title">Boarding Requests</h1>
          <p>Review and confirm short-term boarding stays</p>
        </div>

        <select data-filter aria-label="Filter boarding requests by status"
                data-empty="No boarding requests with this status.">
          <option value="all">All requests</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="checked_in">Checked in</option>
          <option value="completed">Completed</option>
          <option value="declined">Declined</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Owner</th>
              <th>Pet</th>
              <th>Stay Dates</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
          <?php foreach ($bookings as $b):
              $dates = $day($b['check_in']) . ' → ' . $day($b['check_out']);
              [$cls, $text] = BOOKING_BADGE[$b['status']]; ?>
            <tr data-id="<?= $b['id'] ?>" data-status="<?= e($b['status']) ?>" data-owner="<?= e($b['owner_name']) ?>" data-pet="<?= e($b['pet_name']) ?>" data-dates="<?= e($dates) ?>">
              <td class="applicant"><?= e($b['owner_name']) ?></td>
              <td><?= e($b['pet_name']) ?></td>
              <td><?= e($dates) ?></td>
              <td><span class="badge <?= $cls ?>"><?= $text ?></span></td>
              <td class="actions">
                <?php if ($b['status'] === 'pending' && $canDecideBoarding): ?>
                  <button class="btn primary" type="button" data-action="confirm">Confirm</button>
                  <button class="btn secondary" type="button" data-action="decline">Decline</button>
                <?php elseif ($b['status'] === 'pending'): ?>
                  <small>Manager decides</small>
                <?php else: ?>
                  <span class="decided">Reviewed</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </section>


    <!-- DAILY LOGS -->
    <section class="panel logs-panel" aria-labelledby="logs-title">

      <div class="page-head">
        <div>
          <h1 id="logs-title">Daily Care Logs</h1>
          <p>Feeding, walks, medication, and health checks for today, <?= date('F j') ?></p>
        </div>

        <button class="btn primary add" type="button" data-action="add-log"<?= $pets ? '' : ' disabled title="Add a pet in the admin panel first"' ?>>+ Add log entry</button>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Time</th>
              <th>Pet</th>
              <th>Caretaker</th>
              <th>Activity</th>
              <th>Notes</th>
            </tr>
          </thead>

          <tbody id="logsBody">
          <?php foreach ($logs as $l): ?>
            <tr data-time="<?= e($l['t']) ?>" data-pet-id="<?= $l['pet_id'] ?>">
              <td><?= e($clock($l['t'])) ?></td>
              <td class="applicant"><?= e($l['pet_name']) ?></td>
              <td><?= e($l['caretaker'] ?? 'Former staff') ?></td>
              <td><span class="activity <?= e($l['activity']) ?>"><?= e(CARE_ACTIVITIES[$l['activity']] ?? $l['activity']) ?></span></td>
              <td><?= e($l['notes']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$logs): ?>
            <tr class="empty-row"><td colspan="5">No care activity logged yet today.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    </section>


    <?php if ($report): ?>
    <!-- REPORTS -->
    <section class="panel reports-panel" aria-labelledby="reports-title">

      <div class="page-head">
        <div>
          <h1 id="reports-title">Performance Reports</h1>
          <p>Shelter-wide outcomes and staff performance</p>
        </div>

        <select id="reportPeriod" aria-label="Report period">
          <option value="this-month">This month</option>
          <option value="last-month">Last month</option>
          <option value="last-3-months">Last 3 months</option>
        </select>
      </div>

      <div class="kpi-grid" id="kpiGrid" aria-live="polite">
        <?php foreach ($report['kpis'] as $key => $k): ?>
        <div class="kpi" data-kpi="<?= $key ?>">
          <span class="kpi-label"><?= e($k['label']) ?></span>
          <strong class="kpi-value"><?= e($k['value']) ?></strong>
          <small class="kpi-change <?= $k['good'] ? 'good-text' : 'bad-text' ?>"><?= e($k['change']) ?></small>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="card staff-card">

        <h3>Staff performance</h3>

        <table>
          <thead>
            <tr>
              <th>Staff Member</th>
              <th>Role</th>
              <th>Applications</th>
              <th>Approval Rate</th>
              <th>Response Time</th>
              <th>Care Logs</th>
            </tr>
          </thead>

          <tbody id="staffBody">
          <?php foreach ($report['staff'] as $row): ?>
            <tr><td class="applicant"><?= e($row[0]) ?></td><td><?= e($row[1]) ?></td><td><?= e($row[2]) ?></td><td><?= e($row[3]) ?></td><td><?= e($row[4]) ?></td><td><?= e($row[5]) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>

      </div>

    </section>
    <?php endif; ?>

  </main>
</div>


<!-- CONFIRM ACTION DIALOG (approve / reject / confirm / decline) -->
<dialog class="dialog" id="confirmDialog" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
  <form method="dialog">
    <div class="dialog-head">
      <h2 id="confirmTitle">Confirm action</h2>
    </div>
    <div class="dialog-body">
      <p id="confirmMessage"></p>
    </div>
    <div class="dialog-foot">
      <button class="btn secondary" value="cancel">Cancel</button>
      <button class="btn primary" value="confirm" id="confirmOk">Confirm</button>
    </div>
  </form>
</dialog>


<!-- PET DETAILS DIALOG -->
<dialog class="dialog" id="petDialog" aria-labelledby="petName">
  <div class="dialog-head">
    <div>
      <h2 id="petName">Pet</h2>
      <p id="petBreed"></p>
    </div>
    <button class="icon-btn" type="button" data-close aria-label="Close">×</button>
  </div>

  <div class="dialog-body">
    <dl class="pet-facts">
      <div><dt>Kennel</dt><dd id="petKennel"></dd></div>
      <div><dt>Caretaker</dt><dd id="petCaretaker"></dd></div>
      <div><dt>Status</dt><dd id="petStatus"></dd></div>
      <div><dt>Last checkup</dt><dd id="petCheckup"></dd></div>
    </dl>

    <?php if (can('staff_health')): ?>
    <!-- Vets and senior staff can change the health status -->
    <form id="healthForm" novalidate style="margin-top:16px">
      <div class="field">
        <label for="healthStatus">Change health status</label>
        <div style="display:flex;gap:8px;align-items:center">
          <select id="healthStatus" name="health_status" style="flex:1">
            <?php foreach (HEALTH_STATUSES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
          <button class="btn primary" type="submit">Update status</button>
        </div>
        <small class="error"></small>
      </div>
    </form>
    <?php endif; ?>

    <h3 class="dialog-subhead">Today's care log</h3>
    <ul class="care-list" id="petCare"></ul>
  </div>

  <div class="dialog-foot">
    <button class="btn secondary" type="button" data-close>Close</button>
  </div>
</dialog>


<!-- ADD LOG ENTRY DIALOG -->
<dialog class="dialog" id="logDialog" aria-labelledby="logTitle">
  <form id="logForm" action="api/care_logs_create.php" method="post" novalidate>
    <div class="dialog-head">
      <div>
        <h2 id="logTitle">Add log entry</h2>
        <p>Record a care activity for today.</p>
      </div>
      <button class="icon-btn" type="button" data-close aria-label="Close">×</button>
    </div>

    <div class="dialog-body">
      <div class="field-row">
        <div class="field">
          <label for="logTime">Time</label>
          <input type="time" id="logTime" name="time" required data-error="Enter the time of the activity.">
          <small class="error"></small>
        </div>

        <div class="field">
          <label for="logActivity">Activity</label>
          <select id="logActivity" name="activity" required data-error="Choose an activity.">
            <option value="">Select activity</option>
            <?php foreach (CARE_ACTIVITIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
          <small class="error"></small>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="logPet">Pet</label>
          <select id="logPet" name="pet" required data-error="Choose a pet.">
            <option value="">Select pet</option>
            <?php foreach ($pets as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?><?= $p['kennel'] ? ' (' . e($p['kennel']) . ')' : '' ?></option><?php endforeach; ?>
          </select>
          <small class="error"></small>
        </div>

        <div class="field">
          <label for="logCaretaker">Caretaker</label>
          <select id="logCaretaker" name="caretaker" required data-error="Choose a caretaker." data-default="<?= $user['id'] ?>">
            <option value="">Select caretaker</option>
            <?php foreach ($staffList as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?></option><?php endforeach; ?>
          </select>
          <small class="error"></small>
        </div>
      </div>

      <div class="field">
        <label for="logNotes">Notes</label>
        <textarea id="logNotes" name="notes" rows="3" maxlength="200" required
                  data-error="Add a short note about the activity."
                  placeholder="What happened, and anything the next shift should know"></textarea>
        <small class="error"></small>
      </div>
    </div>

    <div class="dialog-foot">
      <button class="btn secondary" type="button" data-close>Cancel</button>
      <button class="btn primary" type="submit">Save entry</button>
    </div>
  </form>
</dialog>


<div class="toast-region" id="toastRegion" aria-live="polite"></div>

<script src="script.js"></script>
</body>
</html>
