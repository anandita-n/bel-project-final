<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\UserRepository;

$page_title = 'Create Staff';
$u = current_user();
$error = '';
$users = new UserRepository();

$employee_code = trim($_POST['employee_code'] ?? '') ?: $users->nextEmployeeCode();

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
        $error = 'Staff ID, name, email and password are required.';
    } elseif (!in_array($role, ['manager', 'employee'], true)) {
        $error = 'Invalid role selected.';
    } elseif (!is_valid_email($email)) {
        $error = 'Please enter a valid email address (e.g. name@bel.co.in).';
    } elseif (!is_valid_password($password)) {
        $error = 'Password must be 8–32 characters and include an uppercase letter, a lowercase letter, a number, a special character, and no spaces.';
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
<script src="assets/js/emp-picker.js"></script>
<script src="assets/js/password-rules.js?v=<?= filemtime(__DIR__ . '/assets/js/password-rules.js') ?>"></script>

<div class="breadcrumb"><a href="employees.php">Employees</a> / Create Staff</div>

<?php if ($error): ?><div class="error-msg" style="max-width:760px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="employee_add.php" enctype="multipart/form-data" style="max-width:760px;">
    <div class="panel pa-panel">
        <div class="panel-head"><h3>Details</h3></div>
        <div class="panel-body">
            <div class="profile-photo-box">
                <span class="avatar avatar-photo avatar-employee" id="createPhotoPlaceholder">+</span>
                <img class="avatar-photo" id="createPhotoPreview" style="display:none;" alt="Profile preview">
                <div class="profile-photo-actions">
                    <input type="file" id="createPhotoInput" name="photo" accept="image/png,image/jpeg" style="display:none;">
                    <button type="button" class="link-btn" id="createChangePhotoBtn">Set Profile Picture</button>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Staff ID</label>
                    <input type="text" value="<?= htmlspecialchars($employee_code) ?>" readonly>
                    <input type="hidden" name="employee_code" value="<?= htmlspecialchars($employee_code) ?>">
                </div>
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Temporary Password</label>
                    <input type="password" name="password" id="createPasswordInput" required>
                    <div id="createPasswordChecklist"></div>
                </div>
                <div class="field">
                    <label>Department</label>
                    <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Role</label>
                    <select name="role">
                        <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                    </select>
                </div>
                <div class="field">
                    <label>Stream</label>
                    <input type="text" name="stream" value="<?= htmlspecialchars($_POST['stream'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Telephone</label>
                    <input type="text" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Group</label>
                    <input type="text" name="user_group" value="<?= htmlspecialchars($_POST['user_group'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Reports To (Manager)</label>
                    <div id="managerPicker"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="pa-actions">
        <button type="submit" class="btn btn-lg">Create Staff</button>
        <a href="employees.php" class="btn btn-lg btn-secondary">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    initEmpPicker(managerRoot, { roles: ['admin', 'manager'] });

    const photoInput = document.getElementById('createPhotoInput');
    const photoPreview = document.getElementById('createPhotoPreview');
    const photoPlaceholder = document.getElementById('createPhotoPlaceholder');
    document.getElementById('createChangePhotoBtn').addEventListener('click', function () { photoInput.click(); });
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
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
