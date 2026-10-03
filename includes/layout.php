<?php
// =====================================================================
//  includes/layout.php
//  The sidebar and top bar shared by every admin page.
//  Usage in a page:
//      layout_top('Pets', 'pets');   ...page content...
//      layout_main_end();            ...modals...
//      layout_bottom();
// =====================================================================

function html_head(string $title): void
{ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title) ?> | PawHome Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐾</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<?php }

function avatar_html(array $user, string $extraClass = ''): string
{
    $class = trim('avatar ' . $extraClass);
    if (!empty($user['has_avatar'])) {
        return '<span class="' . $class . '"><img src="photo.php?user=' . (int)$user['id'] . '" alt="" style="width:100%;height:100%;border-radius:inherit;object-fit:cover"></span>';
    }
    return '<span class="' . $class . '">' . e(initials($user['full_name'])) . '</span>';
}

// A delete button that really deletes: a tiny form that asks first, then posts.
function delete_button(int $id, string $question): string
{
    return '<form method="post" data-server data-confirm="' . e($question) . '" style="display:contents">'
        . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="icon-action danger" title="Delete" aria-label="Delete">' . icon('trash', 16) . '</button></form>';
}

function nav_link(string $href, string $iconName, string $text, string $active, string $key, string $extra = ''): string
{
    $isActive = $active === $key;
    return '<a href="' . $href . '" class="nav-item' . ($isActive ? ' active" aria-current="page' : '') . '">'
        . icon($iconName) . e($text) . $extra . '</a>';
}

function layout_top(string $title, string $active = ''): void
{
    global $pdo;
    $user = current_user();

    // Numbers for the sidebar badge and the notifications menu
    $pending = can('adoptions')
        ? (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn() : 0;
    $checkIns = can('boarding')
        ? $pdo->query("SELECT pet_name, kennel FROM boarding
                       WHERE check_in = CURRENT_DATE + 1 AND status IN ('pending','confirmed') ORDER BY pet_name")->fetchAll() : [];
    $recent = $pdo->query('SELECT user_name, action, details, created_at FROM activity_log
                           WHERE action NOT IN (\'Signed in\', \'Signed out\', \'Downloaded report\')
                           ORDER BY created_at DESC LIMIT 3')->fetchAll();

    html_head($title); ?>
<body class="app">

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <a href="index.php" class="logo"><span class="logo-mark"><?= icon('paw', 20) ?></span>PawHome</a>
        <span class="brand-tag">Admin</span>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section">Overview</span>
        <?= nav_link('index.php', 'dashboard', 'Dashboard', $active, 'dashboard') ?>
        <span class="nav-section">Shelter</span>
        <?= nav_link('pets.php', 'paw', 'Pets', $active, 'pets') ?>
        <?php if (can('adoptions')): ?>
            <?= nav_link('adoptions.php', 'home', 'Adoptions', $active, 'adoptions', $pending ? '<span class="nav-count">' . $pending . '</span>' : '') ?>
        <?php endif; ?>
        <?php if (can('boarding')): ?>
            <?= nav_link('boarding.php', 'calendar', 'Boarding', $active, 'boarding') ?>
        <?php endif; ?>
        <?php if (can('quiz')): ?>
            <span class="nav-section">Eligibility quiz</span>
            <?= nav_link('quiz-bank.php', 'clipboard', 'Quiz Management', $active, 'quiz') ?>
        <?php endif; ?>
        <span class="nav-section">Administration</span>
        <?php if (can('users')): ?>
            <?= nav_link('user-management.php', 'users', 'User Management', $active, 'users') ?>
        <?php endif; ?>
        <?php if (can('reports')): ?>
            <?= nav_link('reports.php', 'file', 'Reports', $active, 'reports') ?>
        <?php endif; ?>
        <?= nav_link('settings.php', 'sliders', 'Settings', $active, 'settings') ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item"><?= icon('logout') ?>Log out</a>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-close></div>

<div class="main-wrap">
    <header class="topbar">
        <button class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="Open menu"><?= icon('menu', 20) ?></button>
        <form class="topbar-search" role="search" action="search.php" method="get" data-server>
            <?= icon('search') ?>
            <label class="sr-only" for="globalSearch">Search</label>
            <input type="search" id="globalSearch" name="q" placeholder="Search pets, applicants or staff" value="<?= e($_GET['q'] ?? '') ?>">
        </form>
        <div class="topbar-actions">
            <div class="dropdown">
                <button class="icon-btn" data-dropdown-toggle aria-label="Notifications" aria-expanded="false"><?= icon('bell', 20) ?><?php if ($pending || $checkIns): ?><span class="notif-dot"></span><?php endif; ?></button>
                <div class="dropdown-menu notif-menu">
                    <div class="dropdown-header"><strong>Notifications</strong><button type="button" data-mark-read>Mark all as read</button></div>
                    <?php if ($pending): ?>
                        <a href="adoptions.php" class="notif-item"><span class="activity-dot dot-honey"></span><span><?= $pending ?> adoption application<?= $pending > 1 ? 's' : '' ?> waiting<small>Pending review</small></span></a>
                    <?php endif; ?>
                    <?php foreach ($checkIns as $c): ?>
                        <a href="boarding.php" class="notif-item"><span class="activity-dot dot-blue"></span><span><?= e($c['pet_name']) ?> checks in tomorrow<small>Boarding<?= $c['kennel'] ? ', kennel ' . e($c['kennel']) : '' ?></small></span></a>
                    <?php endforeach; ?>
                    <?php foreach ($recent as $r): ?>
                        <a href="index.php" class="notif-item"><span class="activity-dot dot-green"></span><span><?= e($r['action']) ?><small><?= e($r['details'] ?: $r['user_name']) ?>, <?= e(time_ago($r['created_at'])) ?></small></span></a>
                    <?php endforeach; ?>
                    <?php if (!$pending && !$checkIns && !$recent): ?>
                        <p class="notif-item"><span>You're all caught up.</span></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <button class="profile-btn" data-dropdown-toggle aria-expanded="false" aria-label="Account menu">
                    <?= avatar_html($user) ?>
                    <span class="profile-meta"><strong><?= e($user['full_name']) ?></strong><small><?= e(ROLES[$user['role']]) ?></small></span>
                    <?= icon('chevron', 16) ?>
                </button>
                <div class="dropdown-menu">
                    <a href="settings.php#profile"><?= icon('user', 16) ?>My profile</a>
                    <a href="settings.php#security"><?= icon('shield', 16) ?>Password &amp; security</a>
                    <hr>
                    <a href="logout.php" class="danger"><?= icon('logout', 16) ?>Log out</a>
                </div>
            </div>
        </div>
    </header>

    <main class="content" id="main">
<?php
    // Error messages show as a banner at the top of the page
    foreach ($_SESSION['flash'] ?? [] as $i => $f) {
        if ($f['type'] === 'error') {
            echo '<div class="alert alert-error" role="alert">' . icon('x') . '<span>' . e($f['message']) . '</span></div>';
            unset($_SESSION['flash'][$i]);
        }
    }
}

function layout_main_end(): void
{
    echo "\n    </main>\n</div>\n";
}

function layout_bottom(): void
{
    // Success messages use the design's own toast pop-up
    $messages = array_column($_SESSION['flash'] ?? [], 'message');
    unset($_SESSION['flash']); ?>
<script src="assets/js/app.js"></script>
<script src="assets/js/backend.js"></script>
<?php if ($messages): ?>
<script>
window.addEventListener('load', function () {
    <?php foreach ($messages as $m): ?>
    if (window.PawHome && PawHome.toast) PawHome.toast(<?= json_encode($m) ?>);
    <?php endforeach; ?>
});
</script>
<?php endif; ?>
</body>
</html>
<?php }

// ---------- Sign-in pages (login, forgot password, reset password) -------
function auth_top(string $title): void
{
    global $pdo;
    $facts = $pdo->query("SELECT
            (SELECT COUNT(*) FROM pets WHERE status <> 'adopted') AS in_care,
            (SELECT COUNT(*) FROM applications WHERE status = 'approved') AS adopted,
            (SELECT COUNT(*) FROM shelters) AS shelters")->fetch();
    html_head($title); ?>
<body>
<div class="auth-page">
    <section class="auth-brand">
        <svg class="auth-paws" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= ICONS['paw'] ?></svg>
        <a href="login.php" class="logo"><span class="logo-mark"><?= icon('paw', 22) ?></span>PawHome</a>
        <div class="auth-copy">
            <h1>Every pet here is waiting for someone.</h1>
            <p>The staff workspace for intakes, adoptions and boarding across all PawHome shelters.</p>
        </div>
        <div class="auth-facts">
            <div><strong><?= number_format($facts['in_care']) ?></strong><span>pets in our care</span></div>
            <div><strong><?= number_format($facts['adopted']) ?></strong><span>adoptions to date</span></div>
            <div><strong><?= number_format($facts['shelters']) ?></strong><span>partner shelters</span></div>
        </div>
    </section>

    <main class="auth-main">
        <div class="auth-card">
<?php }

function auth_bottom(): void
{ ?>
        </div>
    </main>
</div>
<script src="assets/js/app.js"></script>
<script src="assets/js/backend.js"></script>
</body>
</html>
<?php }
