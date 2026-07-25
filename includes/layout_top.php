<?php
// Expects $page_title to be set, and auth.php already required + require_login() called
require_once __DIR__ . '/helpers.php';
$u = current_user();
$current_file = basename($_SERVER['PHP_SELF']);

$icon_dashboard = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>';
$icon_projects = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
$icon_employees = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
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
    <div class="sidebar">
        <div class="brand">
            <div class="mark">BE</div>
            <div>
                <strong>BEL PMS</strong>
                <span>Bharat Electronics Ltd.</span>
            </div>
        </div>
        <nav>
            <a href="dashboard.php" class="<?= $current_file === 'dashboard.php' ? 'active' : '' ?>"><?= $icon_dashboard ?> Dashboard</a>
            <a href="projects.php" class="<?= in_array($current_file, ['projects.php','project_detail.php','project_add.php']) ? 'active' : '' ?>"><?= $icon_projects ?> Projects</a>
            <?php if (in_array($u['role'], ['admin','manager'], true)): ?>
            <a href="employees.php" class="<?= in_array($current_file, ['employees.php','employee_detail.php']) ? 'active' : '' ?>"><?= $icon_employees ?> Employees</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="main">
        <div class="topbar">
            <h2><?= htmlspecialchars($page_title ?? '') ?></h2>
            <div class="user-info">
                <div class="topbar-user">
                    <span class="avatar avatar-sm <?= avatar_class($u['role']) ?>"><?= htmlspecialchars(initials($u['name'])) ?></span>
                    <span><b><?= htmlspecialchars($u['name']) ?></b> · <?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                </div>
                <a class="logout-link" href="logout.php">Log out</a>
            </div>
        </div>
        <div class="content">
