<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\UserRepository;

$page_title = 'Create Staff';
$u = current_user();
$error = '';
$users = new UserRepository();

$employee_code = trim($_POST['employee_code'] ?? '') ?: $users->nextEmployeeCode();
$departments = $users->distinctDepartments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $department = trim($_POST['department'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $user_group = trim($_POST['user_group'] ?? '');
    $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

    $photoError = '';
    if (!empty($_FILES['photo']['name'])) {
        if ($_FILES['photo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['photo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $photoError = 'Image is too large (5MB max).';
        } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $photoError = 'Photo upload failed.';
        } elseif ($_FILES['photo']['size'] > employee_photo_max_upload_bytes()) {
            $photoError = 'Image is too large (5MB max).';
        } else {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);
            if (!in_array($ext, allowed_photo_extensions(), true) || !in_array($mime, allowed_photo_mime_types(), true)) {
                $photoError = 'Only PNG or JPG images are allowed.';
            }
        }
    }

    if ($employee_code === '' || $name === '' || $email === '' || $password === '') {
        $error = 'ID, name, email and password are required.';
    } elseif (!is_valid_full_name($name)) {
        $error = 'Full name must be 2–100 characters and contain only letters, spaces, hyphens, and apostrophes (no numbers, symbols, or double spaces).';
    } elseif (!in_array($role, ['manager', 'employee'], true)) {
        $error = 'Invalid role selected.';
    } elseif ($department === '' || !in_array($department, $departments, true)) {
        $error = 'Please select a valid department.';
    } elseif (!is_valid_email($email)) {
        $error = 'Please enter a valid email address (e.g. name@bel.co.in).';
    } elseif (!is_valid_password($password)) {
        $error = 'Password must be 8–32 characters and include an uppercase letter, a lowercase letter, a number, a special character, and no spaces.';
    } elseif (!$manager_id || !($managerUser = $users->findActiveById($manager_id)) || !in_array($managerUser['role'], ['admin', 'manager'], true)) {
        $error = 'Please select a valid manager.';
    } elseif ($telephone !== '' && !preg_match('/^\+91 \d{10}$/', $telephone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } elseif (!is_valid_org_field($stream)) {
        $error = 'Stream contains characters that aren\'t allowed.';
    } elseif (!is_valid_org_field($user_group)) {
        $error = 'Group contains characters that aren\'t allowed.';
    } elseif ($users->emailOrCodeExists($email, $employee_code)) {
        $error = 'An employee with this email or staff ID already exists.';
    } elseif ($photoError !== '') {
        $error = $photoError;
    } else {
        $id = $users->create([
            'employee_code' => $employee_code,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'must_change_password' => true,
            'role' => $role,
            'department' => $department,
            'manager_id' => $manager_id,
            'stream' => $stream,
            'telephone' => $telephone,
            'user_group' => $user_group,
        ]);

        if (!empty($_FILES['photo']['name'])) {
            $dir = employee_photo_upload_dir();
            if (!is_dir($dir)) { mkdir($dir, 0755, true); }
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $storedFilename)) {
                $users->setPhoto($id, $storedFilename);
            }
        }

        header('Location: employee_detail.php?id=' . $id);
        exit;
    }
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<script src="assets/js/password-rules.js?v=<?= filemtime(__DIR__ . '/assets/js/password-rules.js') ?>"></script>

<div class="pa-page-narrow">

<div class="breadcrumb"><a href="employees.php">Employees</a> / Create Staff</div>

<div class="pa-header">
    <h2>Create New Staff</h2>
</div>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="employee_add.php" enctype="multipart/form-data" class="pa-form-spacious" id="createStaffForm">
    <div class="pa-section">
        <div class="pa-section-head">
            <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <div>
                <h3>Personal Information</h3>
            </div>
        </div>
        <div class="pa-section-body pa-personal-row">
            <div class="profile-photo-box">
                <div class="profile-photo-dropzone" id="createPhotoDropzone">
                    <span class="profile-photo-circle">
                        <svg id="createPhotoPlaceholder" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <img class="profile-photo-preview-img" id="createPhotoPreview" style="display:none;" alt="Profile preview">
                        <span class="profile-photo-camera-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </span>
                    </span>
                    <div class="profile-photo-label">Click to upload</div>
                    <div class="pa-hint">JPG, PNG (Max. 5MB)</div>
                    <input type="file" id="createPhotoInput" name="photo" accept="image/png,image/jpeg" style="display:none;">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>ID <span class="required-mark">*</span></label>
                    <input type="text" value="<?= htmlspecialchars($employee_code) ?>" readonly>
                    <input type="hidden" name="employee_code" value="<?= htmlspecialchars($employee_code) ?>">
                </div>
                <div class="field">
                    <label>Full Name <span class="required-mark">*</span></label>
                    <input type="text" name="name" id="createNameInput" maxlength="100" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <div class="field-error-hint" id="createNameError" style="display:none;">Enter a valid name (letters, spaces, hyphens, and apostrophes only).</div>
                </div>
                <div class="field">
                    <label>Email <span class="required-mark">*</span></label>
                    <input type="email" name="email" id="createEmailInput" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="field-error-hint" id="createEmailError" style="display:none;">Enter a valid email address.</div>
                </div>
                <div class="field">
                    <label>Temporary Password <span class="required-mark">*</span></label>
                    <input type="password" name="password" id="createPasswordInput" required>
                    <div id="createPasswordChecklist"></div>
                </div>
            </div>
        </div>

        <div class="pa-section-head pa-section-head-divided">
            <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <div>
                <h3>Organisation Details</h3>
            </div>
        </div>
        <div class="pa-section-body form-grid-3">
            <div class="field">
                <label>Department <span class="required-mark">*</span></label>
                <select name="department" id="createDepartmentSelect" required>
                    <option value="">Select</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= ($_POST['department'] ?? '') === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Role <span class="required-mark">*</span></label>
                <select name="role">
                    <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                    <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                </select>
            </div>
            <div class="field">
                <label>Stream</label>
                <input type="text" name="stream" id="createStreamInput" maxlength="100" value="<?= htmlspecialchars($_POST['stream'] ?? '') ?>">
                <div class="field-error-hint" id="createStreamError" style="display:none;">That contains characters that aren't allowed.</div>
            </div>
            <div class="field">
                <label>Group</label>
                <input type="text" name="user_group" id="createGroupInput" maxlength="100" value="<?= htmlspecialchars($_POST['user_group'] ?? '') ?>">
                <div class="field-error-hint" id="createGroupError" style="display:none;">That contains characters that aren't allowed.</div>
            </div>
            <div class="field">
                <label>Reports To (Manager) <span class="required-mark">*</span></label>
                <div id="managerPicker"></div>
                <div class="field-error-hint" id="createManagerError" style="display:none;">Please select who this employee reports to.</div>
            </div>
            <div class="field">
                <label>Telephone</label>
                <input type="tel" name="telephone" id="createTelephoneInput" value="<?= htmlspecialchars(($_POST['telephone'] ?? '') ?: '+91 ') ?>">
                <div class="field-error-hint" id="createTelephoneError" style="display:none;">Enter a full 10-digit phone number, or leave it blank.</div>
            </div>
        </div>
    </div>

    <div class="pa-actions">
        <a href="employees.php" class="pill-btn pill-btn-lg pill-btn-secondary">Cancel</a>
        <button type="submit" class="pill-btn pill-btn-lg">Create Staff</button>
    </div>
</form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    const departmentSelect = document.getElementById('createDepartmentSelect');
    const managerPickerOpts = { roles: ['admin', 'manager'], department: departmentSelect.value };
    initEmpPicker(managerRoot, managerPickerOpts);

    // Reports To narrows to the selected department (admins stay selectable regardless) —
    // switching department invalidates whatever was picked, since it may no longer qualify.
    departmentSelect.addEventListener('change', function () {
        managerPickerOpts.department = departmentSelect.value;
        managerRoot.querySelector('.emp-picker-hidden').value = '';
        managerRoot.querySelector('.emp-picker-search').value = '';
        managerRoot.querySelector('.emp-picker-list').style.display = 'none';
    });

    const photoInput = document.getElementById('createPhotoInput');
    const photoPreview = document.getElementById('createPhotoPreview');
    const photoPlaceholder = document.getElementById('createPhotoPlaceholder');
    document.getElementById('createPhotoDropzone').addEventListener('click', function () { photoInput.click(); });
    photoInput.addEventListener('change', function () {
        const file = photoInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (ev) {
            photoPreview.src = ev.target.result;
            photoPreview.style.display = '';
            photoPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    const passwordInput = document.getElementById('createPasswordInput');
    const checklistBox = document.getElementById('createPasswordChecklist');
    checklistBox.innerHTML = passwordChecklistHTML();
    bindPasswordChecklist(passwordInput, checklistBox);

    function bindFieldValidator(input, errorBox, isValid) {
        function run() {
            const valid = isValid(input.value);
            errorBox.style.display = valid ? 'none' : '';
            input.classList.toggle('field-invalid', !valid);
            return valid;
        }
        input.addEventListener('blur', run);
        input.addEventListener('input', function () {
            if (errorBox.style.display !== 'none') run();
        });
        return run;
    }

    const emailInput = document.getElementById('createEmailInput');
    const validateEmail = bindFieldValidator(emailInput, document.getElementById('createEmailError'),
        function (v) { return v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); });

    // Unicode letters, spaces, hyphens, apostrophes only; no double spaces; 2-100 chars after trim.
    const NAME_PATTERN = /^\p{L}[\p{L}\p{M}\s'-]*[\p{L}\p{M}]$|^\p{L}$/u;
    const nameInput = document.getElementById('createNameInput');
    const validateName = bindFieldValidator(nameInput, document.getElementById('createNameError'),
        function (v) {
            const trimmed = v.trim();
            if (trimmed.length < 2 || trimmed.length > 100) return false;
            if (/\s{2,}/.test(trimmed)) return false;
            return NAME_PATTERN.test(trimmed);
        });

    // Letters/digits/spaces plus a few punctuation marks real org values use (R&D, Tier-1, etc).
    const ORG_FIELD_PATTERN = /^[\p{L}\p{N}\s'\-&,./]*$/u;
    const streamInput = document.getElementById('createStreamInput');
    const validateStream = bindFieldValidator(streamInput, document.getElementById('createStreamError'),
        function (v) { return v.trim().length <= 100 && ORG_FIELD_PATTERN.test(v.trim()); });
    const groupInput = document.getElementById('createGroupInput');
    const validateGroup = bindFieldValidator(groupInput, document.getElementById('createGroupError'),
        function (v) { return v.trim().length <= 100 && ORG_FIELD_PATTERN.test(v.trim()); });

    const telInput = document.getElementById('createTelephoneInput');
    const telError = document.getElementById('createTelephoneError');
    const TEL_PREFIX = '+91 ';
    function normalizeTelephone() {
        let digits = telInput.value.replace(/\D/g, '');
        if (digits.startsWith('91')) digits = digits.slice(2);
        digits = digits.slice(0, 10);
        telInput.value = TEL_PREFIX + digits;
    }
    function validateTelephone() {
        const digits = telInput.value.slice(TEL_PREFIX.length);
        const valid = digits === '' || digits.length === 10;
        telError.style.display = valid ? 'none' : '';
        telInput.classList.toggle('field-invalid', !valid);
        return valid;
    }
    telInput.addEventListener('input', normalizeTelephone);
    telInput.addEventListener('blur', validateTelephone);
    telInput.addEventListener('focus', function () {
        if (telInput.value.length < TEL_PREFIX.length) telInput.value = TEL_PREFIX;
    });
    normalizeTelephone();

    const managerPickerRoot = document.getElementById('managerPicker');
    const managerError = document.getElementById('createManagerError');

    document.getElementById('createStaffForm').addEventListener('submit', function (ev) {
        const managerId = managerPickerRoot.querySelector('.emp-picker-hidden').value;
        const validManager = !!managerId;
        managerError.style.display = validManager ? 'none' : '';

        const checks = [validateName(), validateEmail(), validateStream(), validateGroup(), validateTelephone(), validManager];
        if (checks.includes(false)) {
            ev.preventDefault();
            const firstInvalid = document.querySelector('.field-invalid') || (!validManager ? managerPickerRoot.querySelector('.emp-picker-search') : null);
            if (firstInvalid) firstInvalid.focus();
            return;
        }
        if (telInput.value.trim() === TEL_PREFIX.trim()) telInput.value = '';
    });
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
