<?php
// Expects $page_title to be set, and auth.php already required + require_login() called
require_once __DIR__ . '/helpers.php';
$u = current_user();
$current_file = basename($_SERVER['PHP_SELF']);

$unreadNotifCount = (new \App\Repositories\MemberReviewRepository())->unreadCountForUser((int)$u['id'])
    + (new \App\Repositories\NotificationRepository())->unreadCountForUser((int)$u['id']);

$projectCreateFiles = ['project_add.php'];

$sidebarSection = null;
// Nobody viewing their *own* profile has any use for the Directory/Create Staff sidebar —
// those are directory-management actions, not profile-page actions — so reserving the column
// there just pushes the page off-center. It still shows when browsing someone else's profile
// (reached from the Directory, where "back to the list" navigation is genuinely useful).
if ($current_file === 'employees.php' || $current_file === 'employee_add.php'
    || ($current_file === 'employee_detail.php' && empty($is_own_profile))) {
    $sidebarSection = 'employees';
} elseif (in_array($current_file, array_merge(['projects.php', 'project_detail.php'], $projectCreateFiles), true)) {
    $sidebarSection = 'projects';
} elseif (in_array($current_file, ['assets.php', 'asset_add.php'], true)) {
    $sidebarSection = 'assets';
}

// The full department breakdown already lives on the Directory/View All page itself, so the
// sidebar doesn't repeat it — it only ever shows the *current* department, as a sub-heading
// under Directory/View All, once you've drilled into one.
// Employees only get the flat "My Assets" view (assets.php ignores ?department= for them), so
// the sidebar must not claim one is active there either.
$activeDepartment = trim($_GET['department'] ?? '');
$activeProjectName = null;
// project_detail.php has no ?department= in its URL — pull the project's own department (and
// name, for a third sidebar level) from the $project the page already loaded before this include.
if ($current_file === 'project_detail.php' && isset($project) && is_array($project)) {
    $activeDepartment = trim($project['department'] ?? '');
    $activeProjectName = $project['name'] ?? null;
}

$showActiveDepartmentInSidebar = $activeDepartment !== ''
    && (($sidebarSection === 'employees' && $current_file === 'employees.php')
        || ($sidebarSection === 'projects' && in_array($current_file, ['projects.php', 'project_detail.php'], true))
        || ($sidebarSection === 'assets' && $current_file === 'assets.php' && $u['role'] !== 'employee'));
// The department row reads as "active" only while you're actually on its listing page; once
// you've drilled into a specific project, the project row below takes over as the active one.
$departmentRowIsActive = $current_file !== 'project_detail.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title ?? 'BEL PMS') ?> — BEL Project Management System</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="app">
    <nav class="site-nav">
        <div class="site-nav-inner">
            <div class="site-nav-brand"><img class="site-logo" src="assets/img/bel-logo-nav.png" alt="Bharat Electronics Limited"></div>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="employees.php" class="<?= in_array($current_file, ['employees.php','employee_detail.php','employee_add.php'], true) ? 'active' : '' ?>">Staff</a>
            <?php endif; ?>
            <a href="projects.php" class="<?= in_array($current_file, array_merge(['projects.php','project_detail.php'], $projectCreateFiles), true) ? 'active' : '' ?>">Projects</a>
            <a href="organisation.php" class="<?= $current_file === 'organisation.php' ? 'active' : '' ?>">Organisation</a>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="assets.php" class="<?= in_array($current_file, ['assets.php','asset_add.php'], true) ? 'active' : '' ?>">Asset Management</a>
            <?php endif; ?>
            <a href="forum.php" class="<?= in_array($current_file, ['forum.php','forum_ask.php','forum_question.php'], true) ? 'active' : '' ?>">Discussion Forum</a>
            <a href="notifications.php" class="<?= $current_file === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
            <div class="site-nav-user">
                <div class="nav-bell-wrap" id="navBellWrap">
                    <button type="button" class="nav-bell" id="navBellBtn" title="Notifications" aria-expanded="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="nav-bell-badge" id="navBellBadge" style="<?= $unreadNotifCount > 0 ? '' : 'display:none;' ?>"><?= $unreadNotifCount > 9 ? '9+' : $unreadNotifCount ?></span>
                    </button>
                    <div class="nav-bell-panel" id="navBellPanel" style="display:none;">
                        <div class="nav-bell-panel-head">
                            <h3>Notifications</h3>
                        </div>
                        <div class="nav-bell-panel-body" id="navBellPanelBody">
                            <div class="nav-bell-loading">Loading…</div>
                        </div>
                        <div class="nav-bell-panel-foot">
                            <a href="notifications.php">View all notifications</a>
                        </div>
                    </div>
                </div>
                <a href="employee_detail.php?id=<?= (int)$u['id'] ?>" class="nav-profile-link" title="My Profile">
                    <?= render_avatar($u, 'avatar-sm') ?>
                    <span><span class="nav-profile-name"><?= htmlspecialchars($u['name']) ?></span> · <?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                </a>
                <a class="logout-link" href="logout.php">Log out</a>
            </div>
        </div>
    </nav>
    <div class="app-body">
        <?php if ($sidebarSection === 'employees'): ?>
        <aside class="site-sidebar" id="siteSidebar">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" title="Collapse sidebar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="site-sidebar-title">Employees</div>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="employees.php" class="sidebar-link <?= $current_file === 'employee_detail.php' || ($current_file === 'employees.php' && $activeDepartment === '') ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="sidebar-label">Directory</span>
            </a>
            <?php endif; ?>
            <?php if ($showActiveDepartmentInSidebar): ?>
            <a href="employees.php?department=<?= urlencode($activeDepartment) ?>" class="sidebar-link sidebar-sublink active">
                <span class="sidebar-label"><?= htmlspecialchars($activeDepartment) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="employee_add.php" class="sidebar-link sidebar-divider <?= $current_file === 'employee_add.php' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                <span class="sidebar-label">Create Staff</span>
            </a>
            <?php endif; ?>
        </aside>
        <?php elseif ($sidebarSection === 'projects'): ?>
        <aside class="site-sidebar" id="siteSidebar">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" title="Collapse sidebar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="site-sidebar-title">Projects</div>
            <?php $onMine = !empty($_GET['mine']); $onArchived = !empty($_GET['archived']); ?>
            <a href="projects.php" class="sidebar-link <?= $current_file === 'projects.php' && $activeDepartment === '' && !$onMine && !$onArchived ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                <span class="sidebar-label">View All</span>
            </a>
            <?php if ($showActiveDepartmentInSidebar && !$onMine): ?>
            <a href="projects.php?department=<?= urlencode($activeDepartment) ?>" class="sidebar-link sidebar-sublink<?= $departmentRowIsActive ? ' active' : '' ?>">
                <span class="sidebar-label"><?= htmlspecialchars($activeDepartment) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($activeProjectName !== null): ?>
            <a href="project_detail.php?id=<?= (int)$project['id'] ?>" class="sidebar-link sidebar-subsublink active">
                <span class="sidebar-label"><?= htmlspecialchars($activeProjectName) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($u['role'] !== 'admin'): ?>
            <a href="projects.php?mine=1" class="sidebar-link <?= $current_file === 'projects.php' && $onMine ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg>
                <span class="sidebar-label">My Projects</span>
            </a>
            <?php if ($showActiveDepartmentInSidebar && $onMine): ?>
            <a href="projects.php?mine=1&department=<?= urlencode($activeDepartment) ?>" class="sidebar-link sidebar-sublink<?= $departmentRowIsActive ? ' active' : '' ?>">
                <span class="sidebar-label"><?= htmlspecialchars($activeDepartment) ?></span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <a href="projects.php?archived=1" class="sidebar-link <?= $current_file === 'projects.php' && $onArchived ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                <span class="sidebar-label">Archived Projects</span>
            </a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="project_add.php" class="sidebar-link sidebar-divider <?= in_array($current_file, $projectCreateFiles, true) ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="9.5" y1="13.5" x2="14.5" y2="13.5"/></svg>
                <span class="sidebar-label">Create Project</span>
            </a>
            <?php endif; ?>
        </aside>
        <?php elseif ($sidebarSection === 'assets'): ?>
        <aside class="site-sidebar" id="siteSidebar">
            <button type="button" id="sidebarToggle" class="sidebar-toggle" title="Collapse sidebar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="site-sidebar-title">Assets</div>
            <a href="assets.php" class="sidebar-link <?= $current_file === 'assets.php' && $activeDepartment === '' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span class="sidebar-label">All Assets</span>
            </a>
            <?php if ($showActiveDepartmentInSidebar): ?>
            <a href="assets.php?department=<?= urlencode($activeDepartment) ?>" class="sidebar-link sidebar-sublink active">
                <span class="sidebar-label"><?= htmlspecialchars($activeDepartment) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="asset_add.php" class="sidebar-link sidebar-divider <?= $current_file === 'asset_add.php' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                <span class="sidebar-label">Add Asset</span>
            </a>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
        <?php if ($sidebarSection): ?>
        <script>document.getElementById('siteSidebar').classList.toggle('collapsed', localStorage.getItem('bel_sidebar_collapsed') === '1');</script>
        <?php endif; ?>
    <div class="main">
        <div class="content">
            <?php if (!empty($u['must_change_password'])): ?>
            <div class="password-reminder-banner">
                <span>You're signed in with a default password. For security, please change it from your profile.</span>
                <a href="employee_detail.php?id=<?= (int)$u['id'] ?>#change-password">Change Password</a>
            </div>
            <?php endif; ?>
