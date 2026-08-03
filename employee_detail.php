<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;

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
$projects = $is_admin_profile ? [] : (new ProjectRepository())->projectsForEmployee($employee_id);

$page_title = 'Employee: ' . $employee['name'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/password-rules.js?v=<?= filemtime(__DIR__ . '/assets/js/password-rules.js') ?>"></script>
<?php if ($u['role'] === 'admin'): ?>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<script src="assets/js/emp-picker.js"></script>
<?php endif; ?>

<div class="breadcrumb"><a href="employees.php">Employees</a> / <?= htmlspecialchars($employee['name']) ?></div>

<div class="panel panel-half">
    <div class="panel-head panel-head-compact" id="empDetailHead" data-id="<?= $employee['id'] ?>" data-name="<?= htmlspecialchars($employee['name']) ?>" data-role="<?= htmlspecialchars($employee['role']) ?>" data-department="<?= htmlspecialchars($employee['department'] ?? '') ?>" data-telephone="<?= htmlspecialchars($employee['telephone'] ?? '') ?>" data-manager-id="<?= htmlspecialchars($employee['manager_id'] ?? '') ?>" data-manager-name="<?= htmlspecialchars($manager['name'] ?? '') ?>"><h3>
        <span class="row-name">
            <?= htmlspecialchars($employee['name']) ?>
        </span>
    </h3>
        <div class="panel-head-tools">
            <span class="dir-badge dir-badge-<?= htmlspecialchars($employee['role']) ?>"><?= htmlspecialchars(ucfirst($employee['role'])) ?></span>
            <?php if ($u['role'] === 'admin' && (int)$employee['id'] !== (int)$u['id']): ?>
            <button type="button" class="row-kebab" id="empDetailKebab" title="More actions">&#8942;</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body">
        <div class="profile-photo-box" id="profilePhotoBox" data-id="<?= $employee['id'] ?>">
            <?php if ($employee['photo_filename']): ?>
            <img class="avatar-photo" src="api/employees/photo.php?action=view&id=<?= $employee['id'] ?>" alt="<?= htmlspecialchars($employee['name']) ?>">
            <?php else: ?>
            <span class="avatar avatar-photo <?= avatar_class($employee['role']) ?>"><?= htmlspecialchars(initials($employee['name'])) ?></span>
            <?php endif; ?>
            <div class="profile-photo-actions">
                <?php if ($u['role'] === 'admin'): ?>
                <input type="file" id="photoInput" accept="image/png,image/jpeg" style="display:none;">
                <button type="button" class="link-btn" id="changePhotoBtn">Change Profile Image</button>
                <?php if ($employee['photo_filename']): ?>
                <button type="button" class="link-btn link-btn-danger" id="removePhotoBtn">Remove</button>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($is_own_profile): ?>
                <button type="button" class="link-btn" id="togglePasswordFormBtn">Change Password</button>
                <?php endif; ?>
            </div>
            <div id="photoError" class="error-msg" style="display:none;"></div>
        </div>
        <div class="form-grid profile-field-grid">
            <div class="profile-field"><span class="profile-field-label">Employee Code</span><span class="profile-field-value"><?= htmlspecialchars($employee['employee_code']) ?></span></div>
            <div class="profile-field"><span class="profile-field-label">Email</span><span class="profile-field-value"><?= htmlspecialchars($employee['email']) ?></span></div>
            <div class="profile-field"><span class="profile-field-label">Telephone</span><span class="profile-field-value"><?= htmlspecialchars($employee['telephone'] ?? '—') ?></span></div>
            <div class="profile-field"><span class="profile-field-label">Department</span><span class="profile-field-value"><?= htmlspecialchars($employee['department'] ?? '—') ?></span></div>
            <?php if (!$is_admin_profile): ?>
            <div class="profile-field"><span class="profile-field-label">Stream</span><span class="profile-field-value"><?= htmlspecialchars($employee['stream'] ?? '—') ?></span></div>
            <div class="profile-field"><span class="profile-field-label">Group</span><span class="profile-field-value"><?= htmlspecialchars($employee['user_group'] ?? '—') ?></span></div>
            <div class="profile-field"><span class="profile-field-label">Reports To</span><span class="profile-field-value"><?= $manager ? htmlspecialchars($manager['name']) : '—' ?></span></div>
            <?php endif; ?>
        </div>

        <?php if ($is_own_profile): ?>
        <div id="changePasswordBody" style="display:none;">
            <div id="changePasswordError" class="error-msg" style="display:none;"></div>
            <div id="changePasswordSuccess" class="success-msg" style="display:none;">Password updated successfully.</div>
            <form id="changePasswordForm">
                <div class="field">
                    <label>Current Password</label>
                    <input type="password" id="currentPasswordInput" required>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <input type="password" id="newPasswordInput" required>
                    <div id="changePasswordChecklist"></div>
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirmPasswordInput" required>
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$is_admin_profile && !$is_own_profile): ?>
<div class="panel">
    <div class="panel-head"><h3>Projects (<?= count($projects) ?>)</h3></div>
    <div class="panel-body" style="padding:0;">
        <?php if (empty($projects)): ?>
            <div class="empty-state">Not currently part of any project.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Code</th><th>Project</th><th>Role</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                <tr>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['project_code']) ?></a></td>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                    <td><?= htmlspecialchars($p['role_in_project']) ?></td>
                    <td><span class="dir-badge dir-badge-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $p['status']))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($u['role'] === 'admin'): ?>
<script src="assets/js/pages/employee-detail.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/employee-detail.js') ?>"></script>
<?php endif; ?>

<?php if ($is_own_profile): ?>
<script>
(function () {
    const form = document.getElementById('changePasswordForm');
    const newInput = document.getElementById('newPasswordInput');
    const checklistBox = document.getElementById('changePasswordChecklist');
    checklistBox.innerHTML = passwordChecklistHTML();
    bindPasswordChecklist(newInput, checklistBox);

    const errorBox = document.getElementById('changePasswordError');
    const successBox = document.getElementById('changePasswordSuccess');
    const body = document.getElementById('changePasswordBody');
    const toggleBtn = document.getElementById('togglePasswordFormBtn');

    <?php if (!empty($u['must_change_password']) && $is_own_profile): ?>
    body.style.display = '';
    toggleBtn.textContent = 'Cancel';
    <?php endif; ?>

    if (location.hash === '#change-password') {
        body.style.display = '';
        toggleBtn.textContent = 'Cancel';
        toggleBtn.scrollIntoView({ block: 'center' });
    }

    toggleBtn.addEventListener('click', function () {
        const opening = body.style.display === 'none';
        body.style.display = opening ? '' : 'none';
        toggleBtn.textContent = opening ? 'Cancel' : 'Change Password';
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        errorBox.style.display = 'none';
        successBox.style.display = 'none';

        const currentPassword = document.getElementById('currentPasswordInput').value;
        const newPassword = newInput.value;
        const confirmPassword = document.getElementById('confirmPasswordInput').value;

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
            form.reset();
            checklistBox.innerHTML = passwordChecklistHTML();
            bindPasswordChecklist(newInput, checklistBox);
            const banner = document.querySelector('.password-reminder-banner');
            if (banner) banner.remove();
            setTimeout(function () {
                body.style.display = 'none';
                toggleBtn.textContent = 'Change Password';
                successBox.style.display = 'none';
            }, 1500);
        }).catch(function (err) {
            errorBox.textContent = err.message;
            errorBox.style.display = 'block';
        });
    });
})();
</script>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
