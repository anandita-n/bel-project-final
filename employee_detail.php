<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\AssetRepository;

$employee_id = (int)($_GET['id'] ?? 0);
$users = new UserRepository();

$employee = $users->findActiveById($employee_id);

if (!$employee) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Employee not found. <a href="employees.php">Back to employees</a></div>');
}

$is_admin_profile = $employee['role'] === 'admin';
$is_own_profile = (int)$employee['id'] === (int)current_user()['id'];
$manager = $employee['manager_id'] ? $users->findActiveById((int)$employee['manager_id']) : null;

// Admins don't carry real project assignments as employees, so this section is skipped for them
// entirely; everyone else sees it — as a flat section on colleagues' profiles, as an "Projects"
// tab on their own.
$show_projects = !$is_admin_profile;
$projects = $show_projects ? (new ProjectRepository())->projectsForEmployee($employee_id) : [];

// Employees don't get an "Asset Management" nav entry — their assigned assets are surfaced
// here on their profile instead (own view or someone else's, same as the Projects list below).
$show_assets = !$is_admin_profile;
$my_assets = $show_assets ? (new AssetRepository())->search('', '', '', $employee_id) : [];

$page_title = 'Employee: ' . $employee['name'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/password-rules.js?v=<?= filemtime(__DIR__ . '/assets/js/password-rules.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<?php if ($u['role'] === 'admin'): ?>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<?php endif; ?>

<div class="breadcrumb"><a href="employees.php">Employees</a> / <?= htmlspecialchars($employee['name']) ?></div>

<div class="emp-profile-page">

<div class="emp-profile-header" id="empDetailHead"
    data-id="<?= $employee['id'] ?>"
    data-name="<?= htmlspecialchars($employee['name']) ?>"
    data-role="<?= htmlspecialchars($employee['role']) ?>"
    data-department="<?= htmlspecialchars($employee['department'] ?? '') ?>"
    data-telephone="<?= htmlspecialchars($employee['telephone'] ?? '') ?>"
    data-manager-id="<?= htmlspecialchars($employee['manager_id'] ?? '') ?>"
    data-manager-name="<?= htmlspecialchars($manager['name'] ?? '') ?>"
    data-stream="<?= htmlspecialchars($employee['stream'] ?? '') ?>"
    data-group="<?= htmlspecialchars($employee['user_group'] ?? '') ?>">

    <div class="emp-profile-photo-col" id="profilePhotoBox" data-id="<?= $employee['id'] ?>">
        <?php if ($employee['photo_filename']): ?>
        <img class="emp-profile-avatar" src="api/employees/photo.php?action=view&id=<?= $employee['id'] ?>" alt="<?= htmlspecialchars($employee['name']) ?>">
        <?php else: ?>
        <span class="emp-profile-avatar <?= avatar_class($employee['role']) ?>"><?= htmlspecialchars(initials($employee['name'])) ?></span>
        <?php endif; ?>
        <?php if ($u['role'] === 'admin'): ?>
        <input type="file" id="photoInput" accept="image/png,image/jpeg" style="display:none;">
        <div class="emp-profile-photo-actions">
            <button type="button" class="link-btn" id="changePhotoBtn">Change Photo</button>
            <?php if ($employee['photo_filename']): ?>
            <button type="button" class="link-btn link-btn-danger" id="removePhotoBtn">Remove</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div id="photoError" class="error-msg" style="display:none;"></div>
    </div>

    <div class="emp-profile-idblock">
        <h2><?= htmlspecialchars($employee['name']) ?></h2>
        <?php if (!$is_own_profile): ?>
        <div class="emp-profile-code"><?= htmlspecialchars($employee['employee_code']) ?></div>
        <?php endif; ?>
    </div>

    <div class="emp-profile-header-actions">
        <?php if ($u['role'] === 'admin' && (int)$employee['id'] !== (int)$u['id']): ?>
        <button type="button" class="row-kebab emp-header-kebab" id="empDetailKebab" title="More actions">&#8942;</button>
        <?php endif; ?>
        <?php if ($is_own_profile): ?>
        <button type="button" class="change-password-btn" id="togglePasswordFormBtn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Change Password</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div id="profileTabs">
    <div class="tabs" role="tablist">
        <button type="button" class="tab-btn" data-tab="overview">Details</button>
        <?php if ($show_projects): ?>
        <button type="button" class="tab-btn" data-tab="projects">Projects <span class="tab-count"><?= count($projects) ?></span></button>
        <?php endif; ?>
        <?php if ($show_assets): ?>
        <button type="button" class="tab-btn" data-tab="assets">Assets</button>
        <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="overview">
        <div class="emp-details-row<?= $is_admin_profile ? '' : ' emp-details-row-2col' ?>">
        <div class="emp-details-col">
        <div class="emp-card">
            <h3 class="emp-card-title">Organisational Information</h3>
            <div class="emp-info-grid emp-info-grid-5">
                <div class="emp-info-item"><div class="emp-info-label">Department</div><div class="emp-info-value"><?= htmlspecialchars($employee['department'] ?: '—') ?></div></div>
                <div class="emp-info-item"><div class="emp-info-label">Role</div><div class="emp-info-value"><?= htmlspecialchars($employee['job_title'] ?: ucfirst($employee['role'])) ?></div></div>
                <div class="emp-info-item"><div class="emp-info-label">Stream</div><div class="emp-info-value"><?= htmlspecialchars($employee['stream'] ?: '—') ?></div></div>
                <div class="emp-info-item"><div class="emp-info-label">Group</div><div class="emp-info-value"><?= htmlspecialchars($employee['user_group'] ?: '—') ?></div></div>
                <div class="emp-info-item"><div class="emp-info-label">Date of Joining</div><div class="emp-info-value"><?= !empty($employee['created_at']) ? htmlspecialchars(date('j M Y', strtotime($employee['created_at']))) : '—' ?></div></div>
            </div>
        </div>

        <div class="emp-card">
            <h3 class="emp-card-title">Contact</h3>
            <div class="emp-contact-row">
                <div class="emp-contact-item">
                    <span class="emp-contact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg></span>
                    <div><div class="emp-info-label">Email</div><div class="emp-info-value emp-contact-link"><?= htmlspecialchars($employee['email']) ?></div></div>
                </div>
                <div class="emp-contact-item">
                    <span class="emp-contact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                    <div><div class="emp-info-label">Phone number</div><div class="emp-info-value"><?= htmlspecialchars($employee['telephone'] ?: '—') ?></div></div>
                </div>
            </div>
        </div>
        </div>

        <?php if (!$is_admin_profile): ?>
        <div class="emp-details-col emp-details-col-narrow emp-details-col-divided">
        <div class="emp-card emp-card-narrow">
            <h3 class="emp-card-title">Reporting</h3>
            <?php if ($manager): ?>
            <a href="employee_detail.php?id=<?= $manager['id'] ?>" class="emp-manager-row">
                <span class="emp-contact-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                <div><div class="emp-info-label">Reports to</div><div class="emp-manager-name"><?= htmlspecialchars($manager['name']) ?> (<?= htmlspecialchars($manager['employee_code']) ?>)</div></div>
            </a>
            <?php else: ?>
            <div class="empty-state">No manager assigned.</div>
            <?php endif; ?>
        </div>
        </div>
        <?php endif; ?>
        </div>
    </div>

    <?php if ($show_projects): ?>
    <div class="tab-panel" data-panel="projects">
        <?= render_emp_projects_table($projects, $employee['name'], $is_own_profile) ?>
    </div>
    <?php endif; ?>

    <?php if ($show_assets): ?>
    <div class="tab-panel" data-panel="assets">
        <?= render_emp_assets_table($my_assets, $is_own_profile) ?>
    </div>
    <?php endif; ?>
</div>
<script src="assets/js/tabs.js?v=<?= filemtime(__DIR__ . '/assets/js/tabs.js') ?>"></script>
<script>initTabs(document.getElementById('profileTabs'), { defaultTab: 'overview' });</script>
</div>

</div>
</div>

<?php if ($u['role'] === 'admin'): ?>
<script src="assets/js/pages/employee-detail.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/employee-detail.js') ?>"></script>
<?php endif; ?>

<?php if ($is_own_profile): ?>
<script>
(function () {
    const toggleBtn = document.getElementById('togglePasswordFormBtn');

    function openChangePasswordModal() {
        const overlay = openModal('Change Password', '' +
            '<div id="changePasswordError" class="error-msg" style="display:none;"></div>' +
            '<div id="changePasswordSuccess" class="success-msg" style="display:none;">Password updated successfully.</div>' +
            '<form id="changePasswordForm">' +
            '<div class="field">' +
            '<label>Current Password</label>' +
            '<input type="password" id="currentPasswordInput" required>' +
            '</div>' +
            '<div class="field">' +
            '<label>New Password</label>' +
            '<input type="password" id="newPasswordInput" required>' +
            '<div id="changePasswordChecklist"></div>' +
            '</div>' +
            '<div class="field">' +
            '<label>Confirm New Password</label>' +
            '<input type="password" id="confirmPasswordInput" required>' +
            '</div>' +
            '<div class="modal-actions">' +
            '<button type="button" class="pill-btn pill-btn-secondary" id="changePasswordCancel">Cancel</button>' +
            '<button type="submit" class="pill-btn">Update Password</button>' +
            '</div>' +
            '</form>');

        overlay.querySelector('#changePasswordCancel').addEventListener('click', closeModal);

        const form = overlay.querySelector('#changePasswordForm');
        const newInput = overlay.querySelector('#newPasswordInput');
        const checklistBox = overlay.querySelector('#changePasswordChecklist');
        checklistBox.innerHTML = passwordChecklistHTML();
        bindPasswordChecklist(newInput, checklistBox);

        const errorBox = overlay.querySelector('#changePasswordError');
        const successBox = overlay.querySelector('#changePasswordSuccess');

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            errorBox.style.display = 'none';
            successBox.style.display = 'none';

            const currentPassword = overlay.querySelector('#currentPasswordInput').value;
            const newPassword = newInput.value;
            const confirmPassword = overlay.querySelector('#confirmPasswordInput').value;

            if (newPassword !== confirmPassword) {
                errorBox.textContent = 'New password and confirmation do not match.';
                errorBox.style.display = 'block';
                return;
            }
            if (!isPasswordValid(newPassword)) {
                errorBox.textContent = 'New password does not meet the requirements above.';
                errorBox.style.display = 'block';
                return;
            }

            apiPost('api/employees/change_password.php', {
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword,
            }).then(function () {
                successBox.style.display = 'block';
                const banner = document.querySelector('.password-reminder-banner');
                if (banner) banner.remove();
                setTimeout(closeModal, 1200);
            }).catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.style.display = 'block';
            });
        });
    }

    toggleBtn.addEventListener('click', openChangePasswordModal);

    <?php if (!empty($u['must_change_password']) && $is_own_profile): ?>
    openChangePasswordModal();
    <?php endif; ?>

    if (location.hash === '#change-password') {
        openChangePasswordModal();
    }
})();
</script>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
