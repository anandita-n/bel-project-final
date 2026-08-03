<?php
// Expects $page_title to be set, and auth.php already required + require_login() called
require_once __DIR__ . '/helpers.php';
$u = current_user();
$current_file = basename($_SERVER['PHP_SELF']);

$unreadNotifCount = (new \App\Repositories\MemberReviewRepository())->unreadCountForUser((int)$u['id'])
    + (new \App\Repositories\NotificationRepository())->unreadCountForUser((int)$u['id']);

$projectWizardFiles = ['project_add.php', 'project_add_team.php', 'project_add_tasks.php', 'project_add_review.php', 'project_add_success.php'];

$sidebarSection = null;
if (in_array($current_file, ['employees.php', 'employee_detail.php', 'employee_add.php'], true)) {
    $sidebarSection = 'employees';
} elseif (in_array($current_file, array_merge(['projects.php', 'project_detail.php'], $projectWizardFiles), true)) {
    $sidebarSection = 'projects';
} elseif (in_array($current_file, ['assets.php', 'asset_add.php'], true)) {
    $sidebarSection = 'assets';
}
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
            <a href="projects.php" class="site-nav-brand"><img class="site-logo" src="assets/img/bel-logo-nav.png" alt="Bharat Electronics Limited"></a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="employees.php" class="<?= in_array($current_file, ['employees.php','employee_detail.php','employee_add.php'], true) ? 'active' : '' ?>">Staff</a>
            <?php endif; ?>
            <a href="projects.php" class="<?= in_array($current_file, array_merge(['projects.php','project_detail.php'], $projectWizardFiles), true) ? 'active' : '' ?>">Projects</a>
            <a href="organisation.php" class="<?= $current_file === 'organisation.php' ? 'active' : '' ?>">Organisation</a>
            <a href="assets.php" class="<?= in_array($current_file, ['assets.php','asset_add.php'], true) ? 'active' : '' ?>">Asset Management</a>
            <a href="forum.php" class="<?= in_array($current_file, ['forum.php','forum_ask.php','forum_question.php'], true) ? 'active' : '' ?>">Discussion Forum</a>
            <a href="notifications.php" class="<?= $current_file === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
            <div class="site-nav-user">
                <a href="notifications.php" class="nav-bell" title="Notifications">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($unreadNotifCount > 0): ?><span class="nav-bell-badge"><?= $unreadNotifCount > 9 ? '9+' : $unreadNotifCount ?></span><?php endif; ?>
                </a>
                <a href="employee_detail.php?id=<?= (int)$u['id'] ?>" class="nav-profile-link" title="My Profile">
                    <?= render_avatar($u, 'avatar-sm') ?>
                    <span><b><?= htmlspecialchars($u['name']) ?></b> · <?= htmlspecialchars(ucfirst($u['role'])) ?></span>
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
            <a href="employees.php" class="sidebar-link <?= in_array($current_file, ['employees.php','employee_detail.php'], true) ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="sidebar-label">Directory</span>
            </a>
            <?php endif; ?>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="employee_add.php" class="sidebar-link <?= $current_file === 'employee_add.php' ? 'active' : '' ?>">
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
            <a href="projects.php" class="sidebar-link <?= $current_file === 'projects.php' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                <span class="sidebar-label">View All</span>
            </a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="project_add.php" class="sidebar-link <?= in_array($current_file, $projectWizardFiles, true) ? 'active' : '' ?>">
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
            <a href="assets.php" class="sidebar-link <?= $current_file === 'assets.php' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span class="sidebar-label">All Assets</span>
            </a>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="asset_add.php" class="sidebar-link <?= $current_file === 'asset_add.php' ? 'active' : '' ?>">
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
