<?php
// Expects $page_title to be set, and auth.php already required + require_login() called
require_once __DIR__ . '/helpers.php';
$u = current_user();
$current_file = basename($_SERVER['PHP_SELF']);

$unreadNotifCount = (new \App\Repositories\MemberReviewRepository())->unreadCountForUser((int)$u['id']);

$sidebarSection = null;
if (in_array($current_file, ['employees.php', 'employee_detail.php', 'employee_add.php'], true)) {
    $sidebarSection = 'employees';
} elseif (in_array($current_file, ['projects.php', 'project_detail.php', 'project_add.php'], true)) {
    $sidebarSection = 'projects';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($page_title ?? 'BEL PMS') ?> — BEL Project Management System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
    <div class="site-topstrip"></div>
    <nav class="site-nav">
        <div class="site-nav-inner">
            <a href="dashboard.php" class="site-nav-brand"><img class="site-logo" src="assets/img/bel-logo.png" alt="Bharat Electronics Limited"></a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="employees.php" class="<?= in_array($current_file, ['employees.php','employee_detail.php']) ? 'active' : '' ?>">Employees</a>
            <a href="organisation.php" class="<?= $current_file === 'organisation.php' ? 'active' : '' ?>">Organisation</a>
            <?php endif; ?>
            <a href="projects.php" class="<?= in_array($current_file, ['projects.php','project_detail.php','project_add.php']) ? 'active' : '' ?>">Projects</a>
            <div class="site-nav-user">
                <a href="dashboard.php#notifications" class="nav-bell" title="Notifications">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($unreadNotifCount > 0): ?><span class="nav-bell-badge"><?= $unreadNotifCount > 9 ? '9+' : $unreadNotifCount ?></span><?php endif; ?>
                </a>
                <span class="avatar avatar-sm <?= avatar_class($u['role']) ?>"><?= htmlspecialchars(initials($u['name'])) ?></span>
                <span><b><?= htmlspecialchars($u['name']) ?></b> · <?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                <a class="logout-link" href="logout.php">Log out</a>
            </div>
        </div>
    </nav>
    <div class="app-body">
        <?php if ($sidebarSection === 'employees'): ?>
        <aside class="site-sidebar">
            <div class="site-sidebar-title">Employees</div>
            <a href="employees.php" class="<?= $current_file === 'employees.php' ? 'active' : '' ?>">View All</a>
            <?php if ($u['role'] === 'admin'): ?>
            <a href="employee_add.php" class="<?= $current_file === 'employee_add.php' ? 'active' : '' ?>">Add Employee</a>
            <?php endif; ?>
        </aside>
        <?php elseif ($sidebarSection === 'projects'): ?>
        <aside class="site-sidebar">
            <div class="site-sidebar-title">Projects</div>
            <a href="projects.php" class="<?= $current_file === 'projects.php' ? 'active' : '' ?>">View All</a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="project_add.php" class="<?= $current_file === 'project_add.php' ? 'active' : '' ?>">Add Project</a>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
    <div class="main">
        <div class="content">
