<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/layout.php';
require_access('reports');

$from = $_GET['date_from'] ?? date('Y-01-01');
$to = $_GET['date_to'] ?? date('Y-m-d');
$shelter = $_GET['shelter_id'] ?? 'all';
$query = http_build_query(['date_from' => $from, 'date_to' => $to, 'shelter_id' => $shelter]);
$shelters = $pdo->query('SELECT id, name FROM shelters ORDER BY id')->fetchAll();

// "Recently generated" comes from the activity log
$recent = $pdo->query("SELECT user_name, details, created_at FROM activity_log
                       WHERE action = 'Downloaded report' ORDER BY created_at DESC LIMIT 6")->fetchAll();
$byName = [];
foreach (REPORTS as $key => [$name, $format]) $byName["$name ($format)"] = $key;

function report_button(string $type, string $query): string
{
    [, $format] = REPORTS[$type];
    $target = $format === 'PDF' ? ' target="_blank"' : '';
    $text = $format === 'PDF' ? 'Open PDF' : 'Download CSV';
    return '<a class="btn btn-primary btn-sm" href="report.php?type=' . $type . '&amp;' . e($query) . '"' . $target . '>' . icon('download', 16) . $text . '</a>';
}

layout_top('Reports', 'reports');
?>
        <div class="page-header">
            <div>
                <h2>Reports</h2>
                <p class="subtitle">Download shelter statistics for board meetings, audits and partners.</p>
            </div>
        </div>

        <form class="card" style="margin-bottom:20px" method="get" action="reports.php" data-server>
            <div class="filter-row">
                <div class="form-group"><label for="date_from_35">From</label><input class="form-control" type="date" id="date_from_35" name="date_from" value="<?= e($from) ?>"></div>
                <div class="form-group"><label for="date_to_36">To</label><input class="form-control" type="date" id="date_to_36" name="date_to" value="<?= e($to) ?>"></div>
                <div class="form-group"><label for="shelter_id_37">Shelter</label><select class="form-control" id="shelter_id_37" name="shelter_id"><option value="all">All shelters</option>
                    <?php foreach ($shelters as $s): ?><option value="<?= $s['id'] ?>"<?= (string)$s['id'] === $shelter ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select></div>
                <button type="submit" class="btn btn-outline" style="margin-bottom:0;height:46px">Apply to all reports</button>
            </div>
        </form>

        <div class="grid-3">
            <div class="report-card">
                <span class="stat-icon tint-plum"><?= icon('home', 20) ?></span>
                <h3>Adoptions</h3>
                <p>Every approved adoption in the date range, with pet, adopter and date.</p>
                <div class="report-meta"><span class="format-tag">PDF</span><?= report_button('adoptions', $query) ?></div>
            </div>
            <div class="report-card">
                <span class="stat-icon tint-green"><?= icon('paw', 20) ?></span>
                <h3>Pet intake log</h3>
                <p>All pets taken in during the date range, with intake details.</p>
                <div class="report-meta"><span class="format-tag">CSV</span><?= report_button('pets', $query) ?></div>
            </div>
            <div class="report-card">
                <span class="stat-icon tint-honey"><?= icon('clipboard', 20) ?></span>
                <h3>Adoption applications</h3>
                <p>Every application with applicant details, quiz score and decision.</p>
                <div class="report-meta"><span class="format-tag">CSV</span><?= report_button('applications', $query) ?></div>
            </div>
            <div class="report-card">
                <span class="stat-icon tint-blue"><?= icon('chart', 20) ?></span>
                <h3>Quiz pass rates</h3>
                <p>How applicants answer each eligibility question.</p>
                <div class="report-meta"><span class="format-tag">CSV</span><?= report_button('quiz', $query) ?></div>
            </div>
            <div class="report-card">
                <span class="stat-icon tint-green"><?= icon('calendar', 20) ?></span>
                <h3>Boarding summary</h3>
                <p>Stays, occupancy and income from boarding.</p>
                <div class="report-meta"><span class="format-tag">PDF</span><?= report_button('boarding', $query) ?></div>
            </div>
            <div class="report-card">
                <span class="stat-icon tint-red"><?= icon('activity', 20) ?></span>
                <h3>Staff activity</h3>
                <p>Log of sign-ins and changes made by each user.</p>
                <div class="report-meta"><span class="format-tag">CSV</span><?= report_button('staff_activity', $query) ?></div>
            </div>
        </div>
        <p class="form-hint" style="margin-top:12px">PDF reports open in a new tab with your browser's print window. Choose "Save as PDF" as the printer.</p>

        <h3 class="section-title">Recently generated</h3>
        <section class="card card-flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Report</th><th>Generated by</th><th>Date</th><th>Format</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r):
                        $type = $byName[$r['details']] ?? null;
                        if (!$type) continue;
                        [$name, $format] = REPORTS[$type]; ?>
                        <tr><td><strong><?= e($name) ?></strong></td><td><?= e($r['user_name']) ?></td><td><?= fmt_date($r['created_at']) ?></td><td><span class="format-tag"><?= $format ?></span></td>
                            <td><div class="actions"><a class="icon-action" aria-label="Download again" title="Download again" href="report.php?type=<?= $type ?>&amp;<?= e($query) ?>"<?= $format === 'PDF' ? ' target="_blank"' : '' ?>><?= icon('download', 16) ?></a></div></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$recent): ?><p class="card-sub" style="padding:20px">Reports you download will be listed here.</p><?php endif; ?>
            </div>
        </section>
<?php layout_main_end(); layout_bottom(); ?>
